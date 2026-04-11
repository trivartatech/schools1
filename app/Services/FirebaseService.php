<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging (FCM) service.
 *
 * Uses the FCM HTTP v1 API via kreait/firebase-php.
 * Requires a Firebase service account JSON file.
 *
 * Setup:
 *   1. Create a Firebase project at https://console.firebase.google.com
 *   2. Go to Project Settings → Service Accounts → Generate new private key
 *   3. Save the JSON file and set FIREBASE_CREDENTIALS in .env
 *   4. Enable Cloud Messaging API in Google Cloud Console
 */
class FirebaseService
{
    protected $messaging = null;
    protected bool $enabled = false;

    public function __construct()
    {
        $credentialsPath = config('firebase.credentials');

        if ($credentialsPath && file_exists($credentialsPath)) {
            try {
                $factory = (new \Kreait\Firebase\Factory)
                    ->withServiceAccount($credentialsPath);
                $this->messaging = $factory->createMessaging();
                $this->enabled = true;
            } catch (\Throwable $e) {
                Log::warning('[Firebase] Failed to initialize: ' . $e->getMessage());
            }
        } else {
            Log::debug('[Firebase] No credentials configured — push notifications disabled');
        }
    }

    /**
     * Check if Firebase is configured and ready.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Send a push notification to a single user.
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        if (!$this->enabled || empty($user->fcm_token)) {
            return false;
        }

        return $this->sendToToken($user->fcm_token, $title, $body, $data);
    }

    /**
     * Send a push notification to a specific FCM token.
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        if (!$this->enabled) return false;

        try {
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $token)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData(array_map('strval', $data)); // FCM data values must be strings

            $this->messaging->send($message);

            Log::info('[Firebase] Notification sent', ['token' => substr($token, 0, 20) . '...', 'title' => $title]);
            return true;
        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            // Token is invalid/expired — clear it
            User::where('fcm_token', $token)->update(['fcm_token' => null]);
            Log::info('[Firebase] Invalid token cleared', ['token' => substr($token, 0, 20) . '...']);
            return false;
        } catch (\Throwable $e) {
            Log::error('[Firebase] Send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a notification to multiple users.
     * Returns count of successful sends.
     */
    public function sendToUsers($users, string $title, string $body, array $data = []): int
    {
        if (!$this->enabled) return 0;

        $tokens = collect($users)
            ->filter(fn($u) => !empty($u->fcm_token))
            ->pluck('fcm_token')
            ->unique()
            ->values()
            ->all();

        if (empty($tokens)) return 0;

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Send a notification to multiple FCM tokens (batch).
     * FCM supports up to 500 tokens per multicast.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        if (!$this->enabled || empty($tokens)) return 0;

        $message = \Kreait\Firebase\Messaging\CloudMessage::new()
            ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
            ->withData(array_map('strval', $data));

        $successCount = 0;
        $invalidTokens = [];

        // Process in batches of 500 (FCM limit)
        foreach (array_chunk($tokens, 500) as $batch) {
            try {
                $report = $this->messaging->sendMulticast($message, $batch);
                $successCount += $report->successes()->count();

                // Collect invalid tokens for cleanup
                foreach ($report->failures()->getItems() as $failure) {
                    if ($failure->error() && str_contains($failure->error()->getMessage(), 'not-registered')) {
                        $invalidTokens[] = $failure->target()->value();
                    }
                }
            } catch (\Throwable $e) {
                Log::error('[Firebase] Multicast failed: ' . $e->getMessage());
            }
        }

        // Clean up invalid tokens
        if (!empty($invalidTokens)) {
            User::whereIn('fcm_token', $invalidTokens)->update(['fcm_token' => null]);
            Log::info('[Firebase] Cleared ' . count($invalidTokens) . ' invalid tokens');
        }

        Log::info('[Firebase] Multicast sent', [
            'total' => count($tokens),
            'success' => $successCount,
            'failed' => count($tokens) - $successCount,
        ]);

        return $successCount;
    }
}

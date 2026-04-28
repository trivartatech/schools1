<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Expo Push Notification service.
 *
 * Wraps Expo's HTTP push API at https://exp.host/--/api/v2/push/send.
 * Tokens look like ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]. The service
 * handles batching (Expo accepts up to 100 messages per call) and prunes
 * tokens that come back as DeviceNotRegistered so we stop spamming dead
 * devices.
 *
 * No setup required: Expo Push works without google-services.json or APNs
 * certs because Expo's relay handles delivery.
 */
class ExpoPushService
{
    private const PUSH_URL  = 'https://exp.host/--/api/v2/push/send';
    private const BATCH_MAX = 100;

    /**
     * Send a push to a single user.
     * Returns true if the user has a token AND the API request succeeded.
     */
    public function sendToUser(?User $user, string $title, string $body, array $data = []): bool
    {
        if (!$user || empty($user->expo_push_token)) return false;
        return $this->sendToTokens([$user->expo_push_token], $title, $body, $data);
    }

    /**
     * Send the same payload to many tokens (batched).
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): bool
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if (empty($tokens)) return false;

        $allOk = true;
        foreach (array_chunk($tokens, self::BATCH_MAX) as $batch) {
            $messages = array_map(fn($t) => [
                'to'           => $t,
                'title'        => $title,
                'body'         => $body,
                'data'         => $data,
                'sound'        => 'default',
                'priority'     => 'high',
                'channelId'    => 'default',
            ], $batch);

            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'Accept'           => 'application/json',
                        'Accept-encoding'  => 'gzip, deflate',
                        'Content-Type'     => 'application/json',
                    ])
                    ->post(self::PUSH_URL, $messages);

                if (!$response->successful()) {
                    Log::warning('[ExpoPush] Send failed', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    $allOk = false;
                    continue;
                }

                $tickets = $response->json('data') ?? [];
                $this->pruneInvalidTokens($batch, $tickets);
            } catch (\Throwable $e) {
                Log::error('[ExpoPush] Exception during send: ' . $e->getMessage());
                $allOk = false;
            }
        }

        return $allOk;
    }

    /**
     * If Expo reports a token as no longer registered, null it on the User row
     * so we never send to it again. The user will re-register on next login.
     */
    protected function pruneInvalidTokens(array $batchTokens, array $tickets): void
    {
        foreach ($tickets as $i => $ticket) {
            if (($ticket['status'] ?? null) !== 'error') continue;

            $errCode = $ticket['details']['error'] ?? null;
            if (!in_array($errCode, ['DeviceNotRegistered', 'InvalidCredentials'])) continue;

            $deadToken = $batchTokens[$i] ?? null;
            if (!$deadToken) continue;

            User::where('expo_push_token', $deadToken)->update([
                'expo_push_token'        => null,
                'push_token_updated_at'  => now(),
            ]);
            Log::info('[ExpoPush] Pruned invalid token', ['error' => $errCode]);
        }
    }
}

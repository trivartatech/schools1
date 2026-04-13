<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use App\Models\CommunicationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NotificationService
{
    protected $school;
    protected ?FirebaseService $firebase;

    public function __construct(?School $school = null)
    {
        $this->school = $school ?? (app()->bound('current_school') ? app('current_school') : null);
        $this->firebase = app(FirebaseService::class);
    }

    /**
     * Send a push notification to a user — saves to DB + sends via FCM.
     */
    protected function sendPushToUser(?User $user, array $notiData): void
    {
        if (!$user) return;

        // Save to DB via Laravel notification system
        $user->notify(new \App\Notifications\CommunicationPortalNotification($notiData));

        // Send via FCM (non-blocking — failures are logged, not thrown)
        if ($this->firebase->isEnabled() && $user->fcm_token) {
            $this->firebase->sendToUser(
                $user,
                $notiData['title'] ?? 'EduConnect',
                $notiData['message'] ?? '',
                array_filter([
                    'type' => $notiData['type'] ?? 'general',
                    'id'   => $notiData['id'] ?? null,
                ])
            );
        }
    }

    protected function getTemplate($type, $slug)
    {
        return \App\Models\CommunicationTemplate::where('school_id', $this->school->id)
            ->where('type', $type)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    protected function replacePlaceholders($content, $data)
    {
        if (!isset($data['app_name'])) {
            $data['app_name'] = $this->school->name;
        }

        return preg_replace_callback('/##([A-Za-z0-9_]+)##/', function($matches) use ($data) {
            $key      = $matches[1];
            $lowerKey = strtolower($key);

            if (isset($data[$key]))       return (string)$data[$key];
            if (isset($data[$lowerKey]))  return (string)$data[$lowerKey];
            if ($lowerKey === 'app_name') return $this->school->name;

            return $matches[0];
        }, $content);
    }

    protected function extractOrderedWhatsAppParams($templateContent, $data)
    {
        if (empty($templateContent)) return array_values($data);

        preg_match_all('/##([A-Za-z0-9_]+)##/', $templateContent, $matches);
        if (empty($matches[1])) {
            return array_map(fn($v) => (string)$v, array_values($data));
        }

        $params = [];
        foreach ($matches[1] as $key) {
            $lowerKey = strtolower($key);
            if ($lowerKey === 'app_name' && !isset($data[$lowerKey]) && !isset($data[$key])) {
                $params[] = $this->school->name;
            } else {
                $params[] = (string)($data[$lowerKey] ?? ($data[$key] ?? ''));
            }
        }
        return $params;
    }

    protected function formatPhoneNumber($recipient, $config)
    {
        $cleanRecipient = preg_replace('/[^0-9]/', '', $recipient);

        $prefix = $config['number_prefix'] ?? ($this->school->settings['sms']['number_prefix'] ?? '');

        if (!empty($prefix)) {
            $cleanRecipient = ltrim($cleanRecipient, '0');
            if (!str_starts_with($cleanRecipient, $prefix)) {
                $cleanRecipient = $prefix . $cleanRecipient;
            }
        }

        if (!str_starts_with($cleanRecipient, '+') && strlen($cleanRecipient) > 10) {
            $cleanRecipient = '+' . $cleanRecipient;
        }

        return $cleanRecipient;
    }

    /**
     * Send SMS via MSG91
     */
    public function sendSms($recipient, $message, $templateId = null, $userId = null, $templateData = [])
    {
        $config = $this->school->settings['sms'] ?? [];
        if (empty($config['api_key'])) return;

        $authKey  = $config['api_key'];
        $senderId = $config['sender_id'] ?? 'SCHOOL';

        $cleanRecipient = $this->formatPhoneNumber($recipient, $config);

        try {
            $recipientData = [
                'mobiles' => preg_replace('/^0+/', '', $cleanRecipient)
            ];

            if (!isset($templateData['app_name'])) {
                $templateData['app_name'] = $this->school->name;
            }

            if (!empty($templateData)) {
                $varIndex = 1;
                foreach ($templateData as $key => $val) {
                    $val      = (string)($val ?? '');
                    $upperKey = strtoupper($key);
                    $lowerKey = strtolower($key);

                    $recipientData[$key]                   = $val;
                    $recipientData[$upperKey]               = $val;
                    $recipientData[$lowerKey]               = $val;
                    $recipientData['##' . $upperKey . '##'] = $val;
                    $recipientData['var' . $varIndex]       = $val;
                    $recipientData['VAR' . $varIndex]       = $val;
                    $varIndex++;
                }
            } else {
                $recipientData['var1'] = $message;
                $recipientData['VAR1'] = $message;
            }

            $payload = [
                'template_id' => $templateId,
                'sender'      => $senderId,
                'short_url'   => '1',
                'recipients'  => [$recipientData]
            ];

            Log::info("MSG91 SMS Payload: " . json_encode($payload));

            $response = Http::withHeaders([
                'content-type' => 'application/json',
                'accept'       => 'application/json'
            ])->post("https://control.msg91.com/api/v5/flow/?authkey={$authKey}", $payload);

            CommunicationLog::create([
                'school_id'         => $this->school->id,
                'user_id'           => $userId,
                'type'              => 'sms',
                'provider'          => 'msg91',
                'to'                => $cleanRecipient,
                'message'           => $message,
                'status'            => $response->successful() ? 'sent' : 'failed',
                'provider_response' => $response->json() ?? ['raw_response' => $response->body()]
            ]);
        } catch (\Exception $e) {
            Log::error("SMS Exception: " . $e->getMessage());
            CommunicationLog::create([
                'school_id'         => $this->school->id,
                'user_id'           => $userId,
                'type'              => 'sms',
                'provider'          => 'msg91',
                'to'                => $cleanRecipient,
                'message'           => $message,
                'status'            => 'failed',
                'provider_response' => ['error' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Send WhatsApp via MSG91
     */
    public function sendWhatsApp($recipient, $templateId, $parameters = [], $userId = null, $languageCode = 'en')
    {
        $config = $this->school->settings['whatsapp'] ?? [];
        if (empty($config['api_key'])) return;

        $authKey          = $config['api_key'];
        $integratedNumber = $config['identifier'] ?? '';

        $cleanRecipient = $this->formatPhoneNumber($recipient, $config);

        Log::info("Sending WhatsApp to {$cleanRecipient}: Template ID {$templateId}");

        try {
            $payload = [
                'integrated_number' => $integratedNumber,
                'content_type'      => 'template',
                'payload'           => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type'    => 'individual',
                    'to'                => $cleanRecipient,
                    'type'              => 'template',
                    'template'          => [
                        'name'       => $templateId,
                        'language'   => ['code' => $languageCode],
                        'components' => [
                            [
                                'type'       => 'body',
                                'parameters' => array_map(function($val) {
                                    return ['type' => 'text', 'text' => (string)$val];
                                }, array_values($parameters))
                            ]
                        ]
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'accept'       => 'application/json',
                'authkey'      => $authKey
            ])->post("https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/", $payload);

            CommunicationLog::create([
                'school_id'         => $this->school->id,
                'user_id'           => $userId,
                'type'              => 'whatsapp',
                'provider'          => 'msg91',
                'to'                => $cleanRecipient,
                'message'           => json_encode(['template_id' => $templateId, 'params' => $parameters]),
                'status'            => $response->successful() ? 'sent' : 'failed',
                'provider_response' => $response->json() ?? ['raw_response' => $response->body()]
            ]);

            if (!$response->successful()) {
                Log::error("MSG91 WhatsApp API Error: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("WhatsApp Exception: " . $e->getMessage());
            CommunicationLog::create([
                'school_id'         => $this->school->id,
                'user_id'           => $userId,
                'type'              => 'whatsapp',
                'provider'          => 'msg91',
                'to'                => $cleanRecipient,
                'message'           => json_encode(['template_id' => $templateId, 'params' => $parameters]),
                'status'            => 'failed',
                'provider_response' => ['error' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Automated Attendance Alert
     */
    public function notifyAttendance($student, $status)
    {
        $parent = $student->studentParent;
        if (!$parent || !$parent->primary_phone) return;

        $smsTemplate   = $this->getTemplate('sms', 'attendance_update');
        $waTemplate    = $this->getTemplate('whatsapp', 'attendance_update');
        $voiceTemplate = $this->getTemplate('voice', 'attendance_update');

        $data = [
            'name'        => $student->name,
            'attendance'  => $status,
            'date'        => now()->format('d-M-Y'),
            'father_name' => $parent->father_name ?? 'Parent',
            'course_name' => $student->currentAcademicHistory?->courseClass?->name ?? 'Class',
            'batch_name'  => $student->currentAcademicHistory?->section?->name ?? 'Section',
            'app_name'    => $this->school->name ?? config('app.name', 'School ERP')
        ];

        if ($smsTemplate) {
            $message = $this->replacePlaceholders($smsTemplate->content, $data);
            $this->sendSms($parent->primary_phone, $message, $smsTemplate->template_id, $parent->user_id, $data);
        }

        if ($waTemplate) {
            $orderedParams = $this->extractOrderedWhatsAppParams($waTemplate->content, $data);
            $this->sendWhatsApp($parent->primary_phone, $waTemplate->template_id, $orderedParams, $parent->user_id, $waTemplate->language_code ?? 'en');
        }

        if ($voiceTemplate) {
            $message = $this->replacePlaceholders($voiceTemplate->content, $data);
            $this->sendVoiceCall($parent->primary_phone, $voiceTemplate->audio_url, $message, $parent->user_id);
        }

        $pushTemplate = $this->getTemplate('push', 'attendance_update');
        if ($pushTemplate) {
            $message  = $this->replacePlaceholders($pushTemplate->content, $data);
            $notiData = [
                'type'    => 'attendance',
                'title'   => $this->replacePlaceholders($pushTemplate->subject ?? 'Attendance Update', $data),
                'message' => $message,
                'sender'  => $this->school->name,
            ];

            $this->sendPushToUser($parent->user ?? null, $notiData);
            $this->sendPushToUser($student->user ?? null, $notiData);
        }
    }

    /**
     * Automated Fee Alert
     */
    public function notifyFeePayment($payment)
    {
        $student = $payment->student;
        $parent  = $student->studentParent;
        if (!$parent || !$parent->primary_phone) return;

        $smsTemplate   = $this->getTemplate('sms', 'fee_payment_confirmed');
        $waTemplate    = $this->getTemplate('whatsapp', 'fee_payment_confirmed');
        $voiceTemplate = $this->getTemplate('voice', 'fee_payment_confirmed');

        $data = [
            'name'           => $student->name,
            'amount'         => $payment->amount,
            'receipt_no'     => $payment->receipt_no,
            'datetime'       => $payment->created_at->format('d-M-y H:i'),
            'payment_method' => $payment->payment_mode,
            'course_name'    => $student->currentAcademicHistory?->courseClass?->name ?? '',
            'batch_name'     => $student->currentAcademicHistory?->section?->name ?? ''
        ];

        if ($smsTemplate) {
            $message = $this->replacePlaceholders($smsTemplate->content, $data);
            $this->sendSms($parent->primary_phone, $message, $smsTemplate->template_id, $parent->user_id, $data);
        }

        if ($waTemplate) {
            $orderedParams = $this->extractOrderedWhatsAppParams($waTemplate->content, $data);
            $this->sendWhatsApp($parent->primary_phone, $waTemplate->template_id, $orderedParams, $parent->user_id, $waTemplate->language_code ?? 'en');
        }

        if ($voiceTemplate) {
            $message = $this->replacePlaceholders($voiceTemplate->content, $data);
            $this->sendVoiceCall($parent->primary_phone, $voiceTemplate->audio_url, $message, $parent->user_id);
        }

        $pushTemplate = $this->getTemplate('push', 'fee_payment_confirmed');
        if ($pushTemplate) {
            $message  = $this->replacePlaceholders($pushTemplate->content, $data);
            $notiData = [
                'type'    => 'fees',
                'title'   => $this->replacePlaceholders($pushTemplate->subject ?? 'Fee Payment Confirmed', $data),
                'message' => $message,
                'sender'  => $this->school->name,
            ];

            $this->sendPushToUser($parent->user ?? null, $notiData);
            $this->sendPushToUser($student->user ?? null, $notiData);
        }
    }

    /**
     * Automated Fee Due Reminder
     */
    public function notifyFeeDue($student, $amount, $dueDate)
    {
        $parent = $student->studentParent;
        if (!$parent || !$parent->primary_phone) return;

        $smsTemplate = $this->getTemplate('sms', 'fee_due_reminder');
        $waTemplate  = $this->getTemplate('whatsapp', 'fee_due_reminder');

        $data = [
            'name'        => $student->name,
            'amount'      => $amount,
            'date'        => $dueDate,
            'course_name' => $student->currentAcademicHistory?->courseClass?->name ?? '',
            'batch_name'  => $student->currentAcademicHistory?->section?->name ?? ''
        ];

        if ($smsTemplate) {
            $message = $this->replacePlaceholders($smsTemplate->content, $data);
            $this->sendSms($parent->primary_phone, $message, $smsTemplate->template_id, $parent->user_id, $data);
        }

        if ($waTemplate) {
            $orderedParams = $this->extractOrderedWhatsAppParams($waTemplate->content, $data);
            $this->sendWhatsApp($parent->primary_phone, $waTemplate->template_id, $orderedParams, $parent->user_id, $waTemplate->language_code ?? 'en');
        }
    }

    /**
     * Trigger OTP for Login
     */
    public function notifyOtp($user, $otp)
    {
        if (!$user->phone) return;

        $smsTemplate = $this->getTemplate('sms', 'otp');

        $data = [
            'otp'      => $otp,
            'app_name' => $this->school->name
        ];

        if ($smsTemplate) {
            $message = $this->replacePlaceholders($smsTemplate->content, $data);
            $this->sendSms($user->phone, $message, $smsTemplate->template_id, $user->id, $data);
        } else {
            $this->sendSms($user->phone, "Your OTP for {$this->school->name} is: {$otp}", null, $user->id, $data);
        }
    }

    /**
     * Send Test SMS
     */
    public function notifyTestSms($phone, $userId = null)
    {
        $smsTemplate = $this->getTemplate('sms', 'test_sms');

        $data = [
            'name'     => 'Test User',
            'date'     => now()->format('d-M-Y'),
            'app_name' => $this->school->name
        ];

        if ($smsTemplate) {
            $message = $this->replacePlaceholders($smsTemplate->content, $data);
            $this->sendSms($phone, $message, $smsTemplate->template_id, $userId, $data);
        } else {
            $this->sendSms($phone, "This is a test SMS from {$this->school->name}", null, $userId, $data);
        }
    }

    /**
     * Send Test WhatsApp
     */
    public function notifyTestWhatsapp($phone, $templateId, $languageCode = 'en', $userId = null)
    {
        $data = [
            'name'     => 'Test User',
            'date'     => now()->format('d-M-Y'),
            'app_name' => $this->school->name
        ];

        $this->sendWhatsApp($phone, $templateId, $data, $userId, $languageCode);
    }

    /**
     * Send Test Voice Call
     */
    public function notifyTestVoice($phone, $userId = null)
    {
        $content = "Hello. This is a test call from {$this->school->name}.";
        $this->sendVoiceCall($phone, null, $content, $userId);
    }

    /**
     * Automated Exam Alert
     */
    public function notifyExamPublished($examSchedule)
    {
        $class    = $examSchedule->courseClass;
        $sections = $examSchedule->sections;

        $smsTemplate   = $this->getTemplate('sms', 'exam_published');
        $waTemplate    = $this->getTemplate('whatsapp', 'exam_published');
        $voiceTemplate = $this->getTemplate('voice', 'exam_published');

        if (!$smsTemplate && !$waTemplate && !$voiceTemplate) return;

        $students = \App\Models\StudentAcademicHistory::where('class_id', $class->id)
            ->whereIn('section_id', $sections->pluck('id'))
            ->where('status', 'current')
            ->with('student.studentParent')
            ->get();

        foreach ($students as $history) {
            $student = $history->student;
            $parent  = $student->studentParent;
            if (!$parent || !$parent->primary_phone) continue;

            $data = [
                'name'       => $student->name,
                'title'      => $examSchedule->examType->name,
                'datetime'   => now()->format('d-M-y H:i'),
                'class_name' => $class->name,
                'type'       => $examSchedule->examType->name
            ];

            if ($smsTemplate) {
                $message = $this->replacePlaceholders($smsTemplate->content, $data);
                $this->sendSms($parent->primary_phone, $message, $smsTemplate->template_id, $parent->user_id, $data);
            }

            if ($waTemplate) {
                $orderedParams = $this->extractOrderedWhatsAppParams($waTemplate->content, $data);
                $this->sendWhatsApp($parent->primary_phone, $waTemplate->template_id, $orderedParams, $parent->user_id, $waTemplate->language_code ?? 'en');
            }

            if ($voiceTemplate) {
                $message = $this->replacePlaceholders($voiceTemplate->content, $data);
                $this->sendVoiceCall($parent->primary_phone, $voiceTemplate->audio_url, $message, $parent->user_id);
            }

            $pushTemplate = $this->getTemplate('push', 'exam_published');
            if ($pushTemplate) {
                $message  = $this->replacePlaceholders($pushTemplate->content, $data);
                $notiData = [
                    'type'    => 'exam',
                    'title'   => $this->replacePlaceholders($pushTemplate->subject ?? 'Exam Published', $data),
                    'message' => $message,
                    'sender'  => $this->school->name,
                ];

                $this->sendPushToUser($parent->user ?? null, $notiData);
                $this->sendPushToUser($student->user ?? null, $notiData);
            }
        }
    }

    /**
     * Resolve a stored audio path into a public URL that Exotel can reliably fetch.
     *
     * Exotel <Play> supports: MP3, WAV, OGG (Vorbis).
     * Browser MediaRecorder outputs audio/webm (Opus) — NOT supported by Exotel.
     *
     * This method:
     *   1. Reads the file from disk
     *   2. If WebM/Opus (browser recording), converts to WAV (8kHz mono) via FFmpeg
     *   3. Saves the WAV to public storage under voice_cache/
     *   4. Returns the direct /storage/voice_cache/{file}.wav URL
     *      Exotel Greeting applet requires a direct file URL returned as plain text.
     *
     * @param  string|null $pathOrUrl  Storage-relative path (e.g. "announcements/foo.webm")
     *                                 or an already-resolved full URL
     * @return string|null  Public URL for Exotel, or null if unresolvable
     */
    protected function resolveAudioUrl(?string $pathOrUrl): ?string
    {
        if (empty($pathOrUrl)) return null;

        // Full URL: check if it maps to our own server
        if (filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            $urlHost = parse_url($pathOrUrl, PHP_URL_HOST);

            if ($urlHost !== $appHost) {
                // External CDN / remote URL — Exotel fetches directly
                return $pathOrUrl;
            }

            // Strip to storage-relative path
            $urlPath   = parse_url($pathOrUrl, PHP_URL_PATH);
            $pathOrUrl = ltrim(str_replace('/storage/', '', $urlPath), '/');
        }

        // pathOrUrl is now a storage-relative path e.g. "announcements/foo.webm"
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        if (!$disk->exists($pathOrUrl)) {
            Log::warning("🔇 [Audio] File not found in storage: [{$pathOrUrl}]");
            return null;
        }

        $mimeType = $disk->mimeType($pathOrUrl) ?: 'audio/mpeg';

        $needsConversion = in_array($mimeType, ['audio/webm', 'audio/mp4', 'audio/aac', 'video/webm'])
            || str_ends_with(strtolower($pathOrUrl), '.webm');

        if (!$needsConversion) {
            // Already WAV or MP3 — serve via /api/voice/audio/{key} so Exotel gets
            // an explicit Content-Type: audio/wav header (static files may get
            // application/octet-stream from Nginx which Exotel silently skips).
            $bytes    = $disk->get($pathOrUrl);
            $cacheKey = 'audio_' . \Illuminate\Support\Str::random(16);
            Cache::put($cacheKey, ['content' => base64_encode($bytes), 'mime' => 'audio/wav'], now()->addMinutes(30));
            $publicUrl = rtrim(config('app.url'), '/') . '/api/voice/audio/' . substr($cacheKey, 6);
            Log::info("🎵 [Audio] Cached [{$pathOrUrl}] → [{$publicUrl}]");
            return $publicUrl;
        }

        // WebM (browser recording) — convert to 8kHz WAV first
        $absolutePath = $disk->path($pathOrUrl);
        $wavBytes     = $this->convertToWav($absolutePath);

        if ($wavBytes === null) {
            Log::warning("⚠️ [Audio] FFmpeg unavailable — skipping WebM audio. Install ffmpeg on server.");
            return null;
        }

        $cacheKey  = 'audio_' . \Illuminate\Support\Str::random(16);
        Cache::put($cacheKey, ['content' => base64_encode($wavBytes), 'mime' => 'audio/wav'], now()->addMinutes(30));
        $publicUrl = rtrim(config('app.url'), '/') . '/api/voice/audio/' . substr($cacheKey, 6);
        Log::info("🎵 [Audio] Converted WebM → WAV [{$pathOrUrl}] → [{$publicUrl}]");

        return $publicUrl;
    }

    /**
     * Convert an audio file to WAV (8kHz, mono, PCM) using system FFmpeg.
     *
     * Exotel telephony requires 8kHz sample rate, mono, 16-bit PCM WAV.
     * Returns raw WAV bytes on success, null if FFmpeg is not available.
     */
    protected function convertToWav(string $inputPath): ?string
    {
        // Locate ffmpeg binary
        $ffmpeg = null;
        foreach (['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg'] as $candidate) {
            if (file_exists($candidate)) { $ffmpeg = $candidate; break; }
        }
        if (!$ffmpeg) {
            exec('which ffmpeg 2>/dev/null', $out);
            if (!empty($out[0])) $ffmpeg = trim($out[0]);
        }
        if (!$ffmpeg) {
            Log::info('🔇 [FFmpeg] Binary not found on this server. Install with: apt-get install ffmpeg');
            return null;
        }

        $outputPath = sys_get_temp_dir() . '/voice_' . uniqid() . '.wav';

        // 8kHz sample rate, mono, 16-bit PCM — Exotel telephony standard
        $cmd = sprintf(
            '%s -y -i %s -ar 8000 -ac 1 -acodec pcm_s16le -f wav %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($outputPath)) {
            Log::error('🔴 [FFmpeg] Conversion failed (exit ' . $exitCode . '): ' . implode(' | ', $output));
            return null;
        }

        $bytes = file_get_contents($outputPath);
        @unlink($outputPath);

        if (empty($bytes)) {
            Log::error('🔴 [FFmpeg] Output WAV was empty.');
            return null;
        }

        Log::info('✅ [FFmpeg] WAV ready: ' . strlen($bytes) . ' bytes');
        return $bytes;
    }

    /**
     * Send Voice Call via Exotel
     *
     * Strategy — Greeting Applet Chain:
     *   App Flow has 3 Greeting applets:
     *     Greeting1 → /api/voice/intro    → returns intro audio URL (or empty)
     *     Greeting2 → /api/voice/play     → returns announcement audio URL (or empty)
     *     Greeting3 → /api/voice/greeting → returns TTS text (or empty)
     *     Hangup
     *
     *   All content is passed via CustomField (base64 JSON):
     *     'i' = intro audio URL
     *     'a' = announcement audio URLs
     *     's' = TTS text
     *
     *   This is fully self-contained — no cache lookup needed at call time.
     *   Cache is still stored as backup for handleStatus fallback.
     */
    public function sendVoiceCall($recipient, $audioUrl = null, $content = null, $userId = null)
    {
        $voiceConfig = $this->school->settings['voice'] ?? [];
        $provider    = $voiceConfig['provider'] ?? 'exotel';
        $apiKey      = $voiceConfig['api_key'] ?? '';
        $apiSid      = $voiceConfig['api_sid'] ?? $apiKey;
        $apiToken    = $voiceConfig['api_token'] ?? '';
        $callerId    = $voiceConfig['caller_id'] ?? '';

        Log::info("🔧 [Voice Config] provider={$provider} api_sid={$apiSid} caller_id={$callerId}");

        if ($provider !== 'exotel') {
            Log::warning("Unsupported voice provider: {$provider}");
            return;
        }

        // ── OPTION C: Audio if available, TTS fallback ──
        // Priority: intro_audio (global) → announcement audio → TTS only
        $primaryAudio   = null;
        $secondaryAudio = null;

        // Global intro audio from Voice Config (played first on every call)
        if (!empty($voiceConfig['intro_audio_path'])) {
            $primaryAudio = $this->resolveAudioUrl($voiceConfig['intro_audio_path']);
        }

        // Per-announcement / per-call audio
        if (!empty($audioUrl)) {
            $resolvedAudio = $this->resolveAudioUrl($audioUrl);
            if ($resolvedAudio) {
                if ($primaryAudio) {
                    // Play intro first, then the specific announcement audio
                    $secondaryAudio = $resolvedAudio;
                } else {
                    $primaryAudio = $resolvedAudio;
                }
            }
        }

        // If intro was null but secondary exists, promote it
        if (empty($primaryAudio) && !empty($secondaryAudio)) {
            $primaryAudio   = $secondaryAudio;
            $secondaryAudio = null;
        }

        // Force +91 + last 10 digits for Exotel India (e.g. +918660234312)
        $digits         = preg_replace('/[^0-9]/', '', $recipient);
        if (strlen($digits) > 10) $digits = substr($digits, -10);
        $cleanRecipient = '+91' . $digits;

        Log::info("🔍 [Voice] Recipient: [{$recipient}] → [{$cleanRecipient}] | CallerID: [{$callerId}]");

        // ── Separate intro URL from announcement audio ──────────────────
        // Greeting chain expects:
        //   'i' = intro audio URL (from Voice Config — plays on every call)
        //   'a' = announcement audio URL (from this specific announcement)
        //   's' = TTS text fallback
        $introUrl          = '';
        $announcementAudio = '';

        if (!empty($voiceConfig['intro_audio_path']) && !empty($primaryAudio)) {
            // School has intro configured — primaryAudio is intro, secondaryAudio is announcement
            $introUrl          = $primaryAudio;
            $announcementAudio = $secondaryAudio ?? '';
        } else {
            // No intro — primaryAudio is the announcement audio
            $introUrl          = '';
            $announcementAudio = $primaryAudio ?? '';
        }

        // ── CACHE the TTS + audio under the phone key (backup fallback) ──
        $phoneKey  = 'tts_' . $digits;
        $cacheData = [
            'i' => $introUrl,
            'a' => array_values(array_filter([$announcementAudio])),
            's' => $content ?? '',
        ];
        Cache::put($phoneKey, $cacheData, now()->addMinutes(10));

        Log::info("💾 [Voice] Cached under key [{$phoneKey}]:", $cacheData);

        $baseUrl = rtrim(config('app.url'), '/');

        // App ID from voice settings — the Exotel Greeting Chain app
        $appId      = $voiceConfig['app_id'] ?? '1203048';
        $appFlowUrl = "http://my.exotel.com/{$apiSid}/exoml/start_voice/{$appId}";

        // CustomField: base64(JSON) — Exotel substitutes {customfield} in Greeting applet URLs
        $customField = base64_encode(json_encode([
            'i' => $introUrl,
            'a' => array_values(array_filter([$announcementAudio])),
            's' => $content ?? '',
        ]));

        // From  = customer (who gets called, shown with ExoPhone as caller ID)
        // No To — Exotel uses CallerId as the ExoPhone to originate the call
        // Url   = Exotel internal app flow → runs the Greeting chain
        $payload = [
            'From'           => $cleanRecipient,
            'CallerId'       => $callerId,
            'Url'            => $appFlowUrl,
            'CustomField'    => $customField,
            'CallType'       => 'trans',
            'StatusCallback' => $baseUrl . '/api/voice/status',
        ];

        // If announcement has audio, use StartPlaybackValueNew to play directly
        // Exotel plays this audio to recipient when call connects (8kHz WAV format)
        if (!empty($announcementAudio)) {
            $payload['StartPlaybackValueNew'] = $announcementAudio;
            $payload['StartPlaybackToNew']    = 'Callee';
        }

        Log::info("🚀 [Voice Payload]", $payload);

        $subdomain = $voiceConfig['subdomain'] ?? 'api.exotel.com';

        try {
            $response = Http::withBasicAuth($apiKey, $apiToken)
                ->asForm()
                ->post("https://{$subdomain}/v1/Accounts/{$apiSid}/Calls/connect.json", $payload);

            Log::info("Exotel API Response for Account [{$apiSid}]:", [
                'http_status' => $response->status(),
                'body'        => $response->json(),
                'recipient'   => $cleanRecipient,
                'callerId'    => $callerId,
            ]);

            if (!$response->successful()) {
                Log::error("Exotel API Error [{$response->status()}]: " . $response->body());
            } else {
                // Also cache under CallSid as backup for handleStatus fallback
                $callSid = $response->json()['Call']['Sid'] ?? null;
                if ($callSid) {
                    Cache::put('tts_' . $callSid, $cacheData, now()->addMinutes(10));
                    Log::info("💾 [Voice] Also cached under CallSid [{$callSid}]");
                }
            }

            CommunicationLog::create([
                'school_id'         => $this->school->id,
                'user_id'           => $userId,
                'type'              => 'voice',
                'provider'          => 'exotel',
                'to'                => $cleanRecipient,
                'message'           => json_encode([
                    'intro_audio'        => $introUrl,
                    'announcement_audio' => $announcementAudio,
                    'tts'                => $content,
                    'caller_id'          => $callerId,
                ]),
                'status'            => $response->successful() ? 'sent' : 'failed',
                'provider_response' => $response->json() ?? ['raw_response' => $response->body()]
            ]);

        } catch (\Exception $e) {
            Log::error("Voice Exception: " . $e->getMessage());
            CommunicationLog::create([
                'school_id'         => $this->school->id,
                'user_id'           => $userId,
                'type'              => 'voice',
                'provider'          => 'exotel',
                'to'                => $cleanRecipient,
                'message'           => json_encode(['tts' => $content, 'caller_id' => $callerId]),
                'status'            => 'failed',
                'provider_response' => ['error' => $e->getMessage()]
            ]);
        }
    }
}

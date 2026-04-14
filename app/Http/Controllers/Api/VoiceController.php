<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VoiceController extends Controller
{
    // ── Shared helper ─────────────────────────────────────────────────────────

    private function resolveGreetingData(): array
    {
        $raw         = request()->all();
        $customField = $raw['customfield'] ?? $raw['CustomField'] ?? $raw['custom_field'] ?? '';

        // Strategy 1: customfield substituted by Exotel in URL
        if (!empty($customField) && $customField !== '{customfield}') {
            $decoded = json_decode(base64_decode(str_replace(' ', '+', $customField)), true);
            if (is_array($decoded)) {
                Log::info('📦 Greeting data via customfield', ['data' => $decoded, 'all' => $raw]);
                return $decoded;
            }
        }

        // Strategy 2: try phone fields — in Exotel outbound the customer phone
        // may appear as CallTo, not CallFrom.
        foreach (['CallFrom', 'callfrom', 'CallTo', 'callto', 'From', 'from', 'To', 'to'] as $field) {
            $num = $raw[$field] ?? '';
            if (empty($num)) continue;
            $digits = preg_replace('/[^0-9]/', '', $num);
            if (strlen($digits) > 10) $digits = substr($digits, -10);
            $cached = Cache::get('tts_' . $digits);
            if ($cached) {
                Log::info("📦 Greeting data via [{$field}={$digits}] cache", ['data' => $cached]);
                return $cached;
            }
        }

        // Strategy 3: CallSid cache fallback
        $callSid = $raw['CallSid'] ?? $raw['callsid'] ?? '';
        if (!empty($callSid)) {
            $cached = Cache::get('tts_' . $callSid);
            if ($cached) {
                Log::info("📦 Greeting data via CallSid cache [{$callSid}]", ['data' => $cached]);
                return $cached;
            }
        }

        // Strategy 4: Passthru cache
        if (!empty($callSid)) {
            $cached = Cache::get('passthru_' . $callSid);
            if ($cached) {
                Log::info("📦 Greeting data via passthru cache [passthru_{$callSid}]", ['data' => $cached]);
                return $cached;
            }
        }

        Log::warning('⚠️ Greeting data not found', ['all' => $raw]);
        return [];
    }

    // ── Greeting 1 — Intro audio ──────────────────────────────────────────────

    public function intro()
    {
        $data = $this->resolveGreetingData();
        Log::info('🔔 /api/voice/intro HIT', ['ip' => request()->ip(), 'method' => request()->method(), 'data' => $data]);

        $introUrl = $data['i'] ?? '';
        if (!empty($introUrl)) {
            Log::info('🔔 Returning intro URL as text/plain: ' . $introUrl);
            return response($introUrl . "\n", 200)
                ->header('Content-Type', 'text/plain')
                ->header('Cache-Control', 'no-store');
        }

        return response('', 200)
            ->header('Content-Type', 'text/plain')
            ->header('Cache-Control', 'no-store');
    }

    // ── Greeting 2 — Announcement audio ──────────────────────────────────────

    public function play()
    {
        $data = $this->resolveGreetingData();
        Log::info('🔊 /api/voice/play HIT', ['ip' => request()->ip(), 'method' => request()->method(), 'data' => $data]);

        $audios   = $data['a'] ?? [];
        $audioUrl = !empty($audios) ? $audios[0] : '';

        if (!empty($audioUrl)) {
            Log::info('🔊 Returning audio URL as text/plain: ' . $audioUrl);
            return response($audioUrl . "\n", 200)
                ->header('Content-Type', 'text/plain')
                ->header('Cache-Control', 'no-store');
        }

        return response('', 200)
            ->header('Content-Type', 'text/plain')
            ->header('Cache-Control', 'no-store');
    }

    // ── Greeting 3 — TTS text ─────────────────────────────────────────────────

    public function greeting()
    {
        $data = $this->resolveGreetingData();
        Log::info('🎤 /api/voice/greeting HIT', ['ip' => request()->ip(), 'method' => request()->method(), 'data' => $data]);

        $tts = $data['s'] ?? '';
        if (!empty($tts)) {
            Log::info('🎤 TTS: ' . $tts);
            return response($tts, 200)
                ->header('Content-Type', 'text/plain')
                ->header('Cache-Control', 'no-store');
        }

        return response('', 200)
            ->header('Content-Type', 'text/plain')
            ->header('Cache-Control', 'no-store');
    }

    // ── Passthru — copies phone cache → CallSid cache ─────────────────────────

    public function passthru()
    {
        $raw     = request()->all();
        $callSid = $raw['CallSid'] ?? $raw['callsid'] ?? '';
        $from    = $raw['From'] ?? $raw['CallFrom'] ?? $raw['callfrom'] ?? $raw['from'] ?? '';

        Log::info('🔵 /api/voice/passthru HIT', ['callSid' => $callSid, 'from' => $from, 'all' => $raw]);

        if (!empty($from)) {
            $digits = preg_replace('/[^0-9]/', '', $from);
            if (strlen($digits) > 10) $digits = substr($digits, -10);
            $data = Cache::get('tts_' . $digits);
            if ($data && !empty($callSid)) {
                Cache::put('passthru_' . $callSid, $data, now()->addMinutes(5));
                Log::info("💾 passthru: copied tts_{$digits} → passthru_{$callSid}");
            } elseif (!$data) {
                Log::warning("⚠️ passthru: cache miss for tts_{$digits}");
            }
        }

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    // ── Cached audio binary ───────────────────────────────────────────────────

    public function audio(string $key)
    {
        $cacheKey = 'audio_' . $key;
        $data     = Cache::get($cacheKey);

        Log::info('🎵 /api/voice/audio HIT', ['key' => $key, 'found' => (bool) $data, 'ip' => request()->ip()]);

        if (!$data) {
            return response('Audio not found.', 404);
        }

        $binary   = base64_decode($data['content']);
        $mimeType = $data['mime'] ?? 'audio/mpeg';

        return response($binary, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Length', strlen($binary))
            ->header('Accept-Ranges', 'bytes')
            ->header('Cache-Control', 'no-store');
    }

    // ── WAV file from storage ─────────────────────────────────────────────────

    public function wav(string $encoded)
    {
        $storagePath = base64_decode(str_replace(['-', '_'], ['+', '/'], $encoded));
        $disk        = Storage::disk('public');

        Log::info('🎵 /api/voice/wav HIT', ['path' => $storagePath, 'ip' => request()->ip()]);

        if (!$storagePath || !$disk->exists($storagePath)) {
            Log::warning('🎵 /api/voice/wav NOT FOUND: ' . $storagePath);
            return response('Not found.', 404);
        }

        $bytes    = $disk->get($storagePath);
        $mimeType = $disk->mimeType($storagePath) ?: 'audio/wav';
        if (in_array($mimeType, ['audio/x-wav', 'audio/wave', 'audio/vnd.wave'])) {
            $mimeType = 'audio/wav';
        }

        Log::info('🎵 /api/voice/wav serving ' . strlen($bytes) . ' bytes as ' . $mimeType);

        return response($bytes, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Length', strlen($bytes))
            ->header('Accept-Ranges', 'bytes')
            ->header('Cache-Control', 'no-store');
    }

    // ── ExoML — dynamic ExoML response ────────────────────────────────────────

    public function exoml()
    {
        $raw     = request()->all();
        $callSid = $raw['CallSid'] ?? $raw['callsid'] ?? '';

        Log::info('🎬 /api/voice/exoml HIT', ['all' => $raw]);

        // Resolve call data: try all possible phone fields, then CallSid fallback
        $data = [];
        foreach (['CallTo', 'callto', 'To', 'to', 'CallFrom', 'callfrom', 'From', 'from'] as $field) {
            $num = $raw[$field] ?? '';
            if (empty($num)) continue;
            $digits = preg_replace('/[^0-9]/', '', $num);
            if (strlen($digits) > 10) $digits = substr($digits, -10);
            $cached = Cache::get('tts_' . $digits);
            if ($cached) {
                Log::info("📦 ExoML data via [{$field}={$digits}]", ['data' => $cached]);
                $data = $cached;
                break;
            }
        }
        if (empty($data) && !empty($callSid)) {
            $cached = Cache::get('tts_' . $callSid);
            if ($cached) {
                Log::info("📦 ExoML data via CallSid [{$callSid}]", ['data' => $cached]);
                $data = $cached;
            }
        }

        if (empty($data)) {
            Log::warning('⚠️ ExoML: no call data found', ['all' => $raw]);
        }

        $introUrl  = $data['i'] ?? '';
        $audioUrls = $data['a'] ?? [];
        $ttsText   = $data['s'] ?? '';

        $xml        = '<Response>';
        $hasContent = false;

        if (!empty($introUrl)) {
            $xml .= '<Play>' . htmlspecialchars($introUrl, ENT_XML1, 'UTF-8') . '</Play>';
            $hasContent = true;
        }
        foreach ($audioUrls as $url) {
            if (!empty($url)) {
                $xml .= '<Play>' . htmlspecialchars($url, ENT_XML1, 'UTF-8') . '</Play>';
                $hasContent = true;
            }
        }
        if (!empty($ttsText)) {
            $xml .= '<Say>' . htmlspecialchars($ttsText, ENT_XML1, 'UTF-8') . '</Say>';
            $hasContent = true;
        }

        if (!$hasContent) {
            $xml .= '<Say>Hello, this is an automated announcement from your school.</Say>';
        }

        $xml .= '</Response>';

        Log::info('🎬 ExoML response', ['xml' => $xml, 'length' => strlen($xml)]);

        return response($xml, 200)
            ->header('Content-Type', 'text/xml')
            ->header('Cache-Control', 'no-store');
    }
}

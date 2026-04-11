<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ExomlController extends Controller
{
    /**
     * Resolve the cache key from request phone fields.
     * Exotel sends: From=customer, To=ExoPhone (08047358988)
     * We always cache under last 10 digits of the customer number.
     */
    protected function resolvePhoneKey(Request $request): array
    {
        $from = $request->input('From') ?? $request->input('from') ?? '';
        $to   = $request->input('To')   ?? $request->input('to')   ?? '';

        // Use From (customer) first; fall back to To only if From looks like the ExoPhone
        $callerIdHint = config('services.exotel.caller_id', '08047358988');
        $fromDigits   = preg_replace('/[^0-9]/', '', $from);
        $toDigits     = preg_replace('/[^0-9]/', '', $to);

        // If From matches our ExoPhone, swap — use To instead
        if (str_ends_with($callerIdHint, substr($fromDigits, -10)) && !empty($toDigits)) {
            $digits = $toDigits;
            $source = 'To';
            $phone  = $to;
        } else {
            $digits = $fromDigits;
            $source = 'From';
            $phone  = $from;
        }

        // Always use last 10 digits to normalize
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return [
            'key'    => 'tts_' . $digits,
            'digits' => $digits,
            'source' => $source,
            'phone'  => $phone,
        ];
    }

    /**
     * Generate Exoml for Exotel calls.
     *
     * Called either:
     * A) Directly with query params: ?s=TTS&a[]=audioUrl   (legacy/direct URL approach)
     * B) Via Landing Flow Passthrough: Exotel POSTs call details (CallSid, To, From, etc.)
     *    In case B, we look up the TTS from cache using the customer phone number as key.
     */
    public function generate(Request $request)
    {
        Log::info('🔵 /api/exoml/response HIT from: ' . $request->ip(), [
            'method' => $request->method(),
            'ua'     => $request->userAgent(),
            'all'    => $request->all(),
        ]);

        $audios = $request->input('a');
        $say    = $request->input('s');

        // B) Landing Flow Passthrough — no inline params, look up cache by phone
        if (empty($audios) && empty($say)) {
            $resolved = $this->resolvePhoneKey($request);
            $phoneKey = $resolved['key'];

            Log::info("🔵 No inline params. Looking up cache by phone key: [{$phoneKey}] (source: {$resolved['source']}={$resolved['phone']})");

            $data = Cache::get($phoneKey);

            if ($data) {
                Log::info("🟢 CACHE HIT for phone key [{$phoneKey}]", [
                    'audios' => $data['a'] ?? [],
                    'tts'    => $data['s'] ?? '(empty)',
                ]);
                Cache::forget($phoneKey);
                return $this->buildResponse($data['a'] ?? [], $data['s'] ?? '');
            }

            Log::error("🔴 CACHE MISS for phone key [{$phoneKey}]. Cache store: [" . config('cache.default') . "]");
            return $this->buildResponse([], 'Sorry, your announcement could not be loaded. Please try again later.');
        }

        return $this->buildResponse($audios, $say);
    }

    /**
     * Generate Exoml by fetching data from Cache by ID.
     */
    public function fetchById(Request $request, $id = null)
    {
        if (!$id) {
            $id = $request->query('id');
        }

        $allInput = $request->all();
        $headers  = collect($request->header())->map(fn($v) => implode('; ', $v))->toArray();
        Log::info("🔵 EXOTEL FETCH HIT: ID: [{$id}]", [
            'method'  => $request->method(),
            'ua'      => $request->userAgent(),
            'ip'      => $request->ip(),
            'input'   => $allInput,
            'headers' => $headers,
        ]);

        if ($request->isMethod('head')) {
            Log::info("Processing HEAD request for ID: {$id}");
            return response('', 200)->header('Content-Type', 'text/xml');
        }

        $cacheKey = str_contains($id ?? '', 'vcall_') ? $id : "vcall_{$id}";
        Log::info("🔵 Cache lookup for key: [{$cacheKey}]");

        $data = Cache::get($cacheKey);

        if (!$data) {
            Log::error("🔴 CACHE MISS — No Exoml data found for key: [{$cacheKey}]. Cache store: [" . config('cache.default') . "]");
            return $this->buildResponse([], "Sorry, your announcement could not be loaded. Please contact the school administrator.");
        }

        Log::info("🟢 CACHE HIT for key: [{$cacheKey}]", [
            'audios' => $data['a'] ?? [],
            'tts'    => $data['s'] ?? '(empty)',
        ]);

        return $this->buildResponse($data['a'] ?? [], $data['s'] ?? '');
    }

    protected function buildResponse($audios, $say)
    {
        Log::info("🚀 EXOTEL CALLBACK HIT: Building Exoml Response...");

        if (!is_array($audios)) {
            $audios = $audios ? explode(',', $audios) : [];
        }

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Response/>');

        foreach ($audios as $audio) {
            if (!empty($audio)) {
                $xml->addChild('Play', htmlspecialchars($audio));
            }
        }

        if (!empty($say)) {
            $xml->addChild('Say', htmlspecialchars($say));
        }

        $xmlOutput = $xml->asXML();
        Log::info("✅ Generated Exoml Response Content:", ['xml' => $xmlOutput]);

        return response($xmlOutput, 200)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Handle Exotel Status callbacks AND Landing Flow Passthru ExoML requests.
     *
     * Exotel posts to this URL with:
     * - Status=in-progress/ringing → wants ExoML instructions (serve from cache)
     * - Status=completed/failed    → final status log only
     *
     * Phone fields from Exotel connect.json calls:
     * - From = customer number (09901737937)
     * - To   = ExoPhone / CallerID (08047358988)
     */
    public function handleStatus(Request $request)
    {
        Log::info('🟡 /api/voice/status HIT', [
            'method' => $request->method(),
            'ip'     => $request->ip(),
            'ua'     => $request->userAgent(),
            'all'    => $request->all(),
        ]);

        // Legacy stealth fetch
        if ($request->has('fetch')) {
            Log::info('🟡 STEALTH FETCH: ' . $request->input('fetch'));
            return $this->fetchById($request, $request->input('fetch'));
        }

        $status = $request->input('Status', '');

        // Non-completed = Passthru applet is requesting ExoML instructions
        if ($status !== 'completed') {
            $callSid = $request->input('CallSid', '');

            // Strategy 1: CustomField contains base64-encoded JSON with TTS+audio
            // This is set in NotificationService and passed via connect.json CustomField param
            $customField = $request->input('CustomField', '');
            if (!empty($customField)) {
                $decoded = json_decode(base64_decode($customField), true);
                if ($decoded && isset($decoded['s'])) {
                    Log::info("🟢 CUSTOM FIELD HIT (CallSid: {$callSid})", ['tts' => $decoded['s']]);
                    return $this->buildResponse($decoded['a'] ?? [], $decoded['s']);
                }
            }

            // Strategy 2: CallSid-based cache (set after API response)
            $phoneKey = 'tts_' . $callSid;
            Log::info("🟡 PASSTHRU (Status=[{$status}]). Trying cache key: [{$phoneKey}]");
            $data = Cache::get($phoneKey);
            if ($data) {
                Log::info("🟢 CACHE HIT [{$phoneKey}]", ['tts' => $data['s'] ?? '(empty)']);
                Cache::forget($phoneKey);
                return $this->buildResponse($data['a'] ?? [], $data['s'] ?? '');
            }

            // Strategy 3: phone-based key fallback
            $resolved    = $this->resolvePhoneKey($request);
            $fallbackKey = $resolved['key'];
            if ($fallbackKey !== 'tts_') {
                $data = Cache::get($fallbackKey);
                if ($data) {
                    Log::info("🟢 CACHE HIT (phone fallback) [{$fallbackKey}]");
                    Cache::forget($fallbackKey);
                    return $this->buildResponse($data['a'] ?? [], $data['s'] ?? '');
                }
            }

            Log::error("🔴 ALL STRATEGIES FAILED (CallSid: {$callSid}). CustomField=[{$customField}]");
            return $this->buildResponse([], 'Sorry, your announcement could not be loaded. Please contact the school.');
        }

        // Status = completed → final status callback
        Log::info('Exotel Voice Status Callback (completed):', $request->all());
        return response('OK', 200);
    }

    /**
     * Handle direct ExoML requests where the content is Base64-encoded in the URL.
     * Use param 'p' for Base64 encoded TTS content.
     */
    public function direct(Request $request)
    {
        $payload = $request->input('p');
        $say     = $payload ? base64_decode(str_replace(' ', '+', $payload)) : '';
        $audios  = $request->input('a', []);

        if (is_string($audios)) {
            $audios = explode(',', $audios);
        }

        return $this->buildResponse($audios, $say);
    }
}

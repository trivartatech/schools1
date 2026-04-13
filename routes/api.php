<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

Route::get('/', function () {
    return response()->json(['message' => 'School ERP API v1', 'version' => '1.0.0']);
})->name('api.root');

// ── Mobile App Discovery ─────────────────────────────────────────────────────
Route::get('/public/school-config', [\App\Http\Controllers\Api\PublicApiController::class, 'schoolConfig'])
    ->name('api.public.school-config');

Route::get('/public/discover', [\App\Http\Controllers\Api\PublicApiController::class, 'discover'])
    ->name('api.public.discover');

// ── Chat API (token or session auth) ─────────────────────────────────────────
Route::middleware(['auth:sanctum', 'tenant'])->prefix('v1')->name('api.v1.')->group(function () {
    $CA = \App\Http\Controllers\Api\ChatApiController::class;
    Route::get('/chats',                             [$CA, 'conversations'])->name('chats');
    Route::get('/messages/{conversation}',           [$CA, 'messages'])->name('messages');
    Route::post('/send-message',                     [$CA, 'sendMessage'])->name('send-message');
    Route::post('/create-group',                     [$CA, 'createGroup'])->name('create-group');
    Route::post('/sync-section',                     [$CA, 'syncSection'])->name('sync-section');
    Route::get('/chats/{conversation}/poll',         [$CA, 'poll'])->name('chats.poll');
});

// GPS Device Update (no auth; secured by device_id lookup)
Route::post('/gps/update', [\App\Http\Controllers\Api\Transport\GpsLogController::class, 'update'])
    ->name('api.gps.update');

// Public Exoml endpoint for Exotel (Route::any because Exotel sends POST)
Route::any('/exoml/response', [\App\Http\Controllers\ExomlController::class, 'generate'])->name('api.exoml.response');

// ID-based Exoml fetch (using /api/ prefix to bypass Bot Protection)
Route::any('/voice/fetch/{id}', [\App\Http\Controllers\ExomlController::class, 'fetchById'])->name('api.voice.fetch');

// Status callback for Exotel (Supports Stealth Fetch)
Route::any('/voice/status', [\App\Http\Controllers\ExomlController::class, 'handleStatus'])->name('api.voice.status');

// Direct Base64 ExoML endpoint (Ultimate resilience)
Route::any('/voice/direct', [\App\Http\Controllers\ExomlController::class, 'direct'])->name('api.voice.direct');

// ─────────────────────────────────────────────────────────────────────────────
// Exotel Greeting Applet Chain
//
// Exotel app flow:
//   Greeting1(/voice/intro) → Greeting2(/voice/play) → Greeting3(/voice/greeting) → Hangup
//
// Each Greeting fetches its URL with CustomField as a GET param.
// Return plain text:
//   - Audio URL  → Exotel plays the MP3/WAV file
//   - Text       → Exotel reads it as TTS
//   - Empty      → Exotel skips this Greeting silently
//
// CustomField is base64(json): {"s": "TTS text", "a": ["audio_url"], "i": "intro_url"}
// ─────────────────────────────────────────────────────────────────────────────

// Resolve greeting data from customfield or CallSid cache fallback.
$resolveGreetingData = function () {
    $raw = request()->all();
    $customField = $raw['customfield'] ?? $raw['CustomField'] ?? $raw['custom_field'] ?? '';

    // Strategy 1: customfield substituted by Exotel in URL
    if (!empty($customField) && $customField !== '{customfield}') {
        $decoded = json_decode(base64_decode(str_replace(' ', '+', $customField)), true);
        if (is_array($decoded)) {
            Log::info('📦 Greeting data via customfield', ['data' => $decoded, 'all' => $raw]);
            return $decoded;
        }
    }

    // Strategy 2: try CallFrom then CallTo — in Exotel outbound (From=ExoPhone, To=customer)
    // the customer phone may appear as CallTo in the greeting request, not CallFrom.
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

    // Strategy 3: CallSid cache fallback (tts_{callSid})
    $callSid = $raw['CallSid'] ?? $raw['callsid'] ?? '';
    if (!empty($callSid)) {
        $cached = Cache::get('tts_' . $callSid);
        if ($cached) {
            Log::info("📦 Greeting data via CallSid cache [{$callSid}]", ['data' => $cached]);
            return $cached;
        }
    }

    // Strategy 4: Passthru cache — stored by /voice/passthru when Passthru applet fires
    if (!empty($callSid)) {
        $cached = Cache::get('passthru_' . $callSid);
        if ($cached) {
            Log::info("📦 Greeting data via passthru cache [passthru_{$callSid}]", ['data' => $cached]);
            return $cached;
        }
    }

    Log::warning('⚠️ Greeting data not found', ['all' => $raw]);
    return [];
};

// Greeting 1 — Intro audio (played on every call if school has intro configured)
// 302 redirect to audio file so Exotel follows and plays audio bytes.
Route::get('/voice/intro', function () use ($resolveGreetingData) {
    $data = $resolveGreetingData();
    Log::info('🔔 /api/voice/intro HIT', ['ip' => request()->ip(), 'data' => $data]);

    $introUrl = $data['i'] ?? '';
    if (!empty($introUrl)) {
        Log::info('🔔 Redirecting to intro: ' . $introUrl);
        return redirect($introUrl, 302);
    }

    return response('', 200)->header('Content-Type', 'text/plain');
})->name('api.voice.intro');

// Greeting 2 — Announcement audio
// Returns a 302 redirect to the WAV file so Exotel follows it and receives audio bytes
// (Content-Type: audio/wav) rather than a URL string that Exotel speaks as TTS.
Route::get('/voice/play', function () use ($resolveGreetingData) {
    $data = $resolveGreetingData();
    Log::info('🔊 /api/voice/play HIT', ['ip' => request()->ip(), 'data' => $data]);

    $audios   = $data['a'] ?? [];
    $audioUrl = !empty($audios) ? $audios[0] : '';

    if (!empty($audioUrl)) {
        Log::info('🔊 Redirecting to audio: ' . $audioUrl);
        return redirect($audioUrl, 302);
    }

    return response('', 200)->header('Content-Type', 'text/plain');
})->name('api.voice.play');

// Greeting 3 — TTS message (played if announcement uses text-to-speech)
// Returns TTS text as plain text, or empty to skip.
Route::get('/voice/greeting', function () use ($resolveGreetingData) {
    $data = $resolveGreetingData();
    Log::info('🎤 /api/voice/greeting HIT', ['ip' => request()->ip(), 'data' => $data]);

    $tts = $data['s'] ?? '';
    if (!empty($tts)) {
        Log::info('🎤 TTS: ' . $tts);
        return response($tts, 200)->header('Content-Type', 'text/plain');
    }

    return response('', 200)->header('Content-Type', 'text/plain');
})->name('api.voice.greeting');

// Passthru applet endpoint — Exotel Passthru POSTs here; we copy phone cache → callSid cache
// so the subsequent Greeting applet (no customfield) can resolve TTS by CallSid.
Route::any('/voice/passthru', function () {
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
})->name('api.voice.passthru');

// Serve cached audio binary for Exotel
// Audio files are base64-cached by NotificationService::resolveAudioUrl()
Route::get('/voice/audio/{key}', function (string $key) {
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
})->name('api.voice.audio');

// Serve announcement audio directly from storage with explicit Content-Type: audio/wav.
// Path is base64-encoded storage-relative path (e.g. base64("announcements/foo.wav")).
// Exotel fetches this URL when /voice/play returns it as plain text.
Route::get('/voice/wav/{encoded}', function (string $encoded) {
    $storagePath = base64_decode(str_replace(['-', '_'], ['+', '/'], $encoded));
    $disk        = \Illuminate\Support\Facades\Storage::disk('public');

    Log::info('🎵 /api/voice/wav HIT', ['path' => $storagePath, 'ip' => request()->ip()]);

    if (!$storagePath || !$disk->exists($storagePath)) {
        Log::warning('🎵 /api/voice/wav NOT FOUND: ' . $storagePath);
        return response('Not found.', 404);
    }

    $bytes    = $disk->get($storagePath);
    $mimeType = $disk->mimeType($storagePath) ?: 'audio/wav';
    // Normalise x-wav aliases so Exotel recognises the type
    if (in_array($mimeType, ['audio/x-wav', 'audio/wave', 'audio/vnd.wave'])) {
        $mimeType = 'audio/wav';
    }

    Log::info('🎵 /api/voice/wav serving ' . strlen($bytes) . ' bytes as ' . $mimeType);

    return response($bytes, 200)
        ->header('Content-Type', $mimeType)
        ->header('Content-Length', strlen($bytes))
        ->header('Accept-Ranges', 'bytes')
        ->header('Cache-Control', 'no-store');
})->name('api.voice.wav');

// ─────────────────────────────────────────────────────────────────────────────
// ExoML endpoint — Exotel fetches this URL when the call connects.
// Returns ExoML with <Play> for audio and <Say> for TTS.
// This replaces the Exotel App Builder Greeting chain for audio announcements.
//
// Exotel passes call params (CallFrom, CallTo, CallSid) as POST body.
// We look up cached call data by phone number, then build ExoML dynamically.
// ─────────────────────────────────────────────────────────────────────────────
Route::any('/voice/exoml', function () {
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

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<Response>\n";

    if (!empty($introUrl)) {
        $xml .= '  <Play>' . htmlspecialchars($introUrl, ENT_XML1, 'UTF-8') . '</Play>' . "\n";
    }
    foreach ($audioUrls as $url) {
        if (!empty($url)) {
            $xml .= '  <Play>' . htmlspecialchars($url, ENT_XML1, 'UTF-8') . '</Play>' . "\n";
        }
    }
    if (!empty($ttsText)) {
        $xml .= '  <Say>' . htmlspecialchars($ttsText, ENT_XML1, 'UTF-8') . '</Say>' . "\n";
    }

    $xml .= '  <Hangup/>' . "\n</Response>";

    Log::info('🎬 ExoML response', ['xml' => $xml]);

    return response($xml, 200)->header('Content-Type', 'application/xml');
})->name('api.voice.exoml');

// ─────────────────────────────────────────────────────────────────────────────
// MOBILE APP API — EduConnect Flutter App
// ─────────────────────────────────────────────────────────────────────────────

use App\Http\Controllers\Api\MobileApiController;

// Public — called by the app before login
Route::get('/public/school-config', [\App\Http\Controllers\Api\PublicApiController::class, 'schoolConfig'])
    ->name('api.public.school-config');

// Auth
Route::post('/login',   [\App\Http\Controllers\AuthController::class, 'apiLogin'])
    ->middleware('throttle:10,1')
    ->name('api.login');

// Authenticated mobile routes
Route::middleware(['auth:sanctum', 'tenant'])->prefix('mobile')->group(function () {
    $MA = MobileApiController::class;

    // Auth
    Route::post('/logout',                    [\App\Http\Controllers\AuthController::class, 'apiLogout'])->name('api.mobile.logout');
    Route::post('/refresh',                   [\App\Http\Controllers\AuthController::class, 'refresh'])->name('api.mobile.refresh');

    // Core data
    Route::get('/dashboard',                  [$MA, 'dashboard'])->name('api.mobile.dashboard');
    Route::get('/attendance',                 [$MA, 'attendance'])->name('api.mobile.attendance');
    Route::get('/timetable',                  [$MA, 'timetable'])->name('api.mobile.timetable');
    Route::get('/fees',                       [$MA, 'fees'])->name('api.mobile.fees');
    Route::get('/fees/{id}',                  [$MA, 'feeDetail'])->name('api.mobile.fees.detail');
    Route::get('/exams',                      [$MA, 'exams'])->name('api.mobile.exams');
    Route::get('/transport/live',             [$MA, 'transport'])->name('api.mobile.transport');
    Route::get('/announcements',              [$MA, 'announcements'])->name('api.mobile.announcements');

    // Multi-child (parent)
    Route::get('/children',                   [$MA, 'children'])->name('api.mobile.children');

    // Admin listings
    Route::get('/students',                   [$MA, 'studentList'])->name('api.mobile.students');
    Route::get('/students/{id}',              [$MA, 'studentDetail'])->name('api.mobile.students.detail');
    Route::get('/class-options',              [$MA, 'classOptions'])->name('api.mobile.class-options');
    Route::get('/teachers',                   [$MA, 'teacherList'])->name('api.mobile.teachers');

    // Profile
    Route::get('/profile',                    [$MA, 'profile'])->name('api.mobile.profile');
    Route::post('/profile/update',            [$MA, 'updateProfile'])->name('api.mobile.profile.update');
    Route::post('/profile/password',          [$MA, 'updatePassword'])->name('api.mobile.profile.password');

    // Biometric re-auth (device verified → issue fresh token)
    Route::post('/biometric/challenge',       [$MA, 'biometricChallenge'])->name('api.mobile.biometric');

    // Announcements
    Route::post('/announcements',               [$MA, 'storeAnnouncement'])->name('api.mobile.announcements.store');
    Route::post('/announcements/{id}/broadcast', [$MA, 'broadcastAnnouncement'])->name('api.mobile.announcements.broadcast');

    // Templates
    Route::get('/templates',                    [$MA, 'templateOptions'])->name('api.mobile.templates');

    // Notifications
    Route::get('/notifications',              [$MA, 'notifications'])->name('api.mobile.notifications');
    Route::post('/notifications/{id}/read',   [$MA, 'markNotificationRead'])->name('api.mobile.notifications.read');
    Route::post('/notifications/mark-all-read', [$MA, 'markAllNotificationsRead'])->name('api.mobile.notifications.read-all');

    // Device FCM registration
    Route::post('/device/register',           [$MA, 'registerDevice'])->name('api.mobile.device');

    // Results & Homework (added in v12)
    Route::get('/results',          [$MA, 'results'])->name('api.mobile.results');
    Route::get('/homework',         [$MA, 'homework'])->name('api.mobile.homework');

    // Student Leave Management
    Route::get('/leave-types',      [$MA, 'leaveTypes'])->name('api.mobile.leave-types');
    Route::get('/leaves',           [$MA, 'leaves'])->name('api.mobile.leaves');
    Route::post('/leaves',          [$MA, 'applyLeave'])->name('api.mobile.leaves.apply');
    Route::delete('/leaves/{id}',   [$MA, 'cancelLeave'])->name('api.mobile.leaves.cancel');

    // Social Buzz / Posts
    Route::get('/posts',            [$MA, 'posts'])->name('api.mobile.posts');
    Route::post('/posts',           [$MA, 'createPost'])->name('api.mobile.posts.create');
    Route::post('/posts/{id}/like', [$MA, 'toggleLike'])->name('api.mobile.posts.like');
    Route::post('/posts/{id}/bookmark', [$MA, 'toggleBookmark'])->name('api.mobile.posts.bookmark');
    Route::get('/posts/{id}/comments',  [$MA, 'postComments'])->name('api.mobile.posts.comments');
    Route::post('/posts/{id}/comments', [$MA, 'addComment'])->name('api.mobile.posts.comments.add');
    Route::delete('/posts/{id}',    [$MA, 'deletePost'])->name('api.mobile.posts.delete');

    // Profile Edit Requests
    Route::get('/edit-request/form',    [$MA, 'editRequestForm'])->name('api.mobile.edit-request.form');
    Route::post('/edit-request',        [$MA, 'submitEditRequest'])->name('api.mobile.edit-request.submit');
    Route::get('/edit-requests',        [$MA, 'editRequests'])->name('api.mobile.edit-requests');

    // ── New Module Endpoints ─────────────────────────────────────────────────

    // Holidays
    Route::get('/holidays',             [$MA, 'holidays'])->name('api.mobile.holidays');

    // Subjects for a class (used in create forms)
    Route::get('/subjects-for-class',   [$MA, 'subjectsForClass'])->name('api.mobile.subjects-for-class');

    // Student Diary
    Route::get('/diary',                [$MA, 'diary'])->name('api.mobile.diary');
    Route::post('/diary',               [$MA, 'storeDiary'])->name('api.mobile.diary.store');
    Route::post('/diary/{id}/toggle-complete', [$MA, 'toggleDiaryComplete'])->name('api.mobile.diary.toggle');

    // Homework Submission
    Route::post('/homework/{id}/submit', [$MA, 'submitHomework'])->name('api.mobile.homework.submit');

    // Assignments
    Route::get('/assignments',          [$MA, 'assignments'])->name('api.mobile.assignments');
    Route::post('/assignments',         [$MA, 'storeAssignment'])->name('api.mobile.assignments.store');

    // Syllabus
    Route::get('/syllabus',             [$MA, 'syllabus'])->name('api.mobile.syllabus');
    Route::post('/syllabus/topics',     [$MA, 'storeSyllabusTopic'])->name('api.mobile.syllabus.store-topic');

    // Report Cards
    Route::get('/report-cards',         [$MA, 'reportCards'])->name('api.mobile.report-cards');

    // Complaints
    Route::get('/complaints',           [$MA, 'complaints'])->name('api.mobile.complaints');
    Route::post('/complaints',          [$MA, 'submitComplaint'])->name('api.mobile.complaints.submit');

    // Library / Book List
    Route::get('/book-list',            [$MA, 'bookList'])->name('api.mobile.book-list');
    Route::post('/book-list',           [$MA, 'storeBook'])->name('api.mobile.book-list.store');
    Route::get('/resources',            [$MA, 'resources'])->name('api.mobile.resources');
    Route::post('/resources/material',  [$MA, 'storeMaterial'])->name('api.mobile.resources.store-material');

    // Student ID Card
    Route::get('/id-card',              [$MA, 'idCard'])->name('api.mobile.id-card');

    // Payments
    Route::get('/payments/history',     [$MA, 'paymentHistory'])->name('api.mobile.payments.history');
    Route::post('/payments/create-order', [$MA, 'createPaymentOrder'])->name('api.mobile.payments.create-order');
    Route::post('/payments/verify',     [$MA, 'verifyPayment'])->name('api.mobile.payments.verify');

    // Report Card Download
    Route::get('/report-cards/{scheduleId}/download', [$MA, 'downloadReportCard'])->name('api.mobile.report-cards.download');

    // Student Attendance (mark + report) — must come before generic /attendance
    Route::get('/attendance/students', [$MA, 'attendanceStudents'])->name('api.mobile.attendance.students');
    Route::post('/attendance/mark',    [$MA, 'markAttendance'])->name('api.mobile.attendance.mark');
    Route::get('/attendance/report',   [$MA, 'attendanceReport'])->name('api.mobile.attendance.report');

    // AI Insights (admin-only; delegates to AiInsightsController)
    Route::get('/ai/insights',  [$MA, 'aiInsights'])->name('api.mobile.ai.insights');
    Route::post('/ai/query',    [$MA, 'aiQuery'])->name('api.mobile.ai.query');
});

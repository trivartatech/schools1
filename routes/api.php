<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VoiceController;

Route::get('/', [App\Http\Controllers\Api\PublicApiController::class, 'index'])->name('api.root');

// ── Mobile App Discovery ─────────────────────────────────────────────────────
Route::get('/public/school-config', [\App\Http\Controllers\Api\PublicApiController::class, 'schoolConfig'])
    ->name('api.public.school-config');

Route::get('/public/discover', [\App\Http\Controllers\Api\PublicApiController::class, 'discover'])
    ->name('api.public.discover');

// ── Public file proxy — serves files from storage/app/public through Laravel
// so mobile clients don't depend on the /storage symlink being readable by nginx.
// The actual path lives in the ?p= query string so nginx's image-extension
// regex (which matches /*.jpg, /*.png etc and returns 404 before PHP is
// reached) doesn't intercept the request. Filenames are random 40-char
// hashes so the URL is effectively unguessable; we still block path
// traversal and reject anything outside storage/app/public.
Route::get('/media', [\App\Http\Controllers\Api\MobileApiController::class, 'serveFile'])
    ->name('api.mobile.file');

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
// Exotel Voice / Greeting Applet routes — handled by VoiceController
// ─────────────────────────────────────────────────────────────────────────────

Route::match(['get', 'head'], '/voice/intro',              [VoiceController::class, 'intro'])->name('api.voice.intro');
Route::match(['get', 'head'], '/voice/play',               [VoiceController::class, 'play'])->name('api.voice.play');
Route::match(['get', 'head'], '/voice/greeting',           [VoiceController::class, 'greeting'])->name('api.voice.greeting');
Route::any(                   '/voice/passthru',           [VoiceController::class, 'passthru'])->name('api.voice.passthru');
Route::get(                   '/voice/audio/{key}',        [VoiceController::class, 'audio'])->name('api.voice.audio');
Route::match(['get', 'head'], '/voice/wav/{encoded}',      [VoiceController::class, 'wav'])->name('api.voice.wav');
// URL ends in literal .wav so Exotel recognises it as an audio file
// (Exotel TTS-speaks URLs without a recognisable audio extension).
Route::match(['get', 'head'], '/voice/cache/{filename}',   [VoiceController::class, 'cacheWav'])
    ->where('filename', '[A-Za-z0-9_-]+\.wav')
    ->name('api.voice.cache');
Route::any(                   '/voice/exoml',              [VoiceController::class, 'exoml'])->name('api.voice.exoml');

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
    Route::post('/students/lookup-by-uuid',   [$MA, 'lookupStudentByUuid'])->name('api.mobile.students.lookup-by-uuid');
    Route::get('/students/{id}',              [$MA, 'studentDetail'])->whereNumber('id')->name('api.mobile.students.detail');
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
    Route::get('/payments/history',        [$MA, 'paymentHistory'])->name('api.mobile.payments.history');
    Route::get('/payments/{id}/receipt',   [$MA, 'paymentReceipt'])->whereNumber('id')->name('api.mobile.payments.receipt');
    Route::post('/payments/create-order',  [$MA, 'createPaymentOrder'])->name('api.mobile.payments.create-order');
    Route::post('/payments/verify',        [$MA, 'verifyPayment'])->name('api.mobile.payments.verify');

    // Report Card Download
    Route::get('/report-cards/{scheduleId}/download', [$MA, 'downloadReportCard'])->name('api.mobile.report-cards.download');

    // Student Attendance (mark + report) — must come before generic /attendance
    Route::get('/attendance/students',    [$MA, 'attendanceStudents'])->name('api.mobile.attendance.students');
    Route::post('/attendance/mark',       [$MA, 'markAttendance'])->name('api.mobile.attendance.mark');
    Route::get('/attendance/report',      [$MA, 'attendanceReport'])->name('api.mobile.attendance.report');
    Route::post('/attendance/rapid-scan', [$MA, 'rapidScanAttendance'])->name('api.mobile.attendance.rapid-scan');

    // AI Insights (admin-only; delegates to AiInsightsController)
    Route::get('/ai/insights',  [$MA, 'aiInsights'])->name('api.mobile.ai.insights');
    Route::post('/ai/query',    [$MA, 'aiQuery'])->name('api.mobile.ai.query');

    // Exam Marks Entry (teacher)
    $EMC = \App\Http\Controllers\Api\Mobile\ExamMarkController::class;
    Route::get('/exam-marks/schedules', [$EMC, 'schedules'])->name('api.mobile.exam-marks.schedules');
    Route::get('/exam-marks/students',  [$EMC, 'students'])->name('api.mobile.exam-marks.students');
    Route::post('/exam-marks/save',     [$EMC, 'save'])->name('api.mobile.exam-marks.save');
});

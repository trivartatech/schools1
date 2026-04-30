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
    Route::get('/users/search',                      [$CA, 'searchUsers'])->name('users.search');
    Route::post('/start-direct-chat',                [$CA, 'startDirect'])->name('start-direct');
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

// QR-only login — parent scans their child's printed/digital ID card,
// the student UUID resolves to the primary parent account, token issued.
// SECURITY NOTE: anyone holding the card can log in. Throttled the same
// as /login to slow brute-forcing UUIDs.
Route::post('/scan-id-card-login', [\App\Http\Controllers\AuthController::class, 'apiScanIdCardLogin'])
    ->middleware('throttle:10,1')
    ->name('api.scan-id-card-login');

// Bus status — auth'd but outside the /mobile prefix because the mobile app
// polls /api/bus/status directly. Returns the live state of the parent's
// active child's bus (or own bus for students).
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('/bus/status', [\App\Http\Controllers\Api\MobileApiController::class, 'busStatus'])
        ->name('api.bus.status');
});

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
    Route::get('/fees/collect/{studentId}',   [$MA, 'feeCollectShow'])->whereNumber('studentId')->name('api.mobile.fees.collect.show');
    Route::post('/fees/collect',              [$MA, 'feeCollectStore'])->name('api.mobile.fees.collect.store');
    Route::get('/fees/{id}',                  [$MA, 'feeDetail'])->whereNumber('id')->name('api.mobile.fees.detail');
    Route::get('/exams',                      [$MA, 'exams'])->name('api.mobile.exams');
    Route::get('/transport/live',             [$MA, 'transport'])->name('api.mobile.transport');

    // Driver tracking (used by the mobile driver app)
    $DT = \App\Http\Controllers\Api\Transport\DriverTrackingController::class;
    Route::get ('/transport/driver/vehicles',         [$DT, 'assignedVehicles'])->name('api.mobile.driver.vehicles');
    Route::post('/transport/driver-tracking/update',  [$DT, 'update'])->name('api.mobile.driver-tracking.update');
    Route::post('/transport/driver-tracking/stop',    [$DT, 'stop'])->name('api.mobile.driver-tracking.stop');

    // Transport attendance (driver marks students boarded / dropped)
    Route::get ('/transport/attendance/students',     [$MA, 'transportAttendanceStudents'])->name('api.mobile.transport.attendance.students');
    Route::post('/transport/attendance/mark',         [$MA, 'transportAttendanceMark'])->name('api.mobile.transport.attendance.mark');
    Route::get('/announcements',              [$MA, 'announcements'])->name('api.mobile.announcements');

    // Front gate keeper — gate passes + visitor log
    Route::get ('/front-office/gate/stats',                       [$MA, 'gateStats'])->name('api.mobile.front-office.gate.stats');
    Route::get ('/front-office/gate-passes/verify/{token}',       [$MA, 'verifyGatePass'])->where('token', '[A-Za-z0-9_\-]+')->name('api.mobile.front-office.gate-passes.verify');
    Route::post('/front-office/gate-passes/{id}/exit',            [$MA, 'gatePassExit'])->whereNumber('id')->name('api.mobile.front-office.gate-passes.exit');
    Route::post('/front-office/gate-passes/{id}/entry',           [$MA, 'gatePassEntry'])->whereNumber('id')->name('api.mobile.front-office.gate-passes.entry');
    Route::get ('/front-office/visitors',                          [$MA, 'visitorList'])->name('api.mobile.front-office.visitors');
    Route::post('/front-office/visitors',                          [$MA, 'logVisitor'])->name('api.mobile.front-office.visitors.log');
    Route::post('/front-office/visitors/{id}/exit',                [$MA, 'visitorExit'])->whereNumber('id')->name('api.mobile.front-office.visitors.exit');

    // Multi-child (parent)
    Route::get('/children',                   [$MA, 'children'])->name('api.mobile.children');

    // Admin listings
    Route::get('/students',                   [$MA, 'studentList'])->name('api.mobile.students');
    Route::post('/students/lookup-by-uuid',   [$MA, 'lookupStudentByUuid'])->name('api.mobile.students.lookup-by-uuid');
    Route::get('/students/{id}',              [$MA, 'studentDetail'])->whereNumber('id')->name('api.mobile.students.detail');
    Route::get('/class-options',              [$MA, 'classOptions'])->name('api.mobile.class-options');
    Route::get('/teachers',                   [$MA, 'teacherList'])->name('api.mobile.teachers');
    Route::get('/staff',                      [$MA, 'staffList'])->name('api.mobile.staff');

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

    // Edit Request approval queue (admin / principal review side)
    $ERA = \App\Http\Controllers\Api\Mobile\EditRequestAdminController::class;
    Route::get  ('/admin/edit-requests',                 [$ERA, 'index'])  ->name('api.mobile.admin.edit-requests');
    Route::get  ('/admin/edit-requests/{id}',            [$ERA, 'show'])   ->whereNumber('id')->name('api.mobile.admin.edit-requests.show');
    Route::post ('/admin/edit-requests/{id}/approve',    [$ERA, 'approve'])->whereNumber('id')->name('api.mobile.admin.edit-requests.approve');
    Route::post ('/admin/edit-requests/{id}/reject',     [$ERA, 'reject']) ->whereNumber('id')->name('api.mobile.admin.edit-requests.reject');

    // ── New Module Endpoints ─────────────────────────────────────────────────

    // Holidays
    Route::get('/holidays',             [$MA, 'holidays'])->name('api.mobile.holidays');
    Route::post  ('/holidays',          [$MA, 'storeHoliday'])  ->name('api.mobile.holidays.store');
    Route::patch ('/holidays/{id}',     [$MA, 'updateHoliday']) ->whereNumber('id')->name('api.mobile.holidays.update');
    Route::delete('/holidays/{id}',     [$MA, 'destroyHoliday'])->whereNumber('id')->name('api.mobile.holidays.destroy');

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

    // Finance — Due Report (admin/accountant)
    Route::get('/finance/due-report',      [$MA, 'dueReport'])->name('api.mobile.finance.due-report');

    // Communication Hub admin (logs + analytics + emergency broadcast)
    $COMM = \App\Http\Controllers\Api\Mobile\CommunicationAdminController::class;
    Route::get ('/communication/logs',                [$COMM, 'logs'])             ->name('api.mobile.communication.logs');
    Route::get ('/communication/analytics',           [$COMM, 'analytics'])        ->name('api.mobile.communication.analytics');
    Route::get ('/communication/emergency/options',   [$COMM, 'emergencyOptions']) ->name('api.mobile.communication.emergency.options');
    Route::post('/communication/emergency',           [$COMM, 'emergencyBroadcast'])->middleware('throttle:5,1')->name('api.mobile.communication.emergency.send');

    // Houses + Leaderboard (read-only for everyone; admin can also award points)
    $HOUSE = \App\Http\Controllers\Api\Mobile\HouseController::class;
    Route::get   ('/houses/leaderboard',                  [$HOUSE, 'leaderboard'])->name('api.mobile.houses.leaderboard');
    Route::get   ('/houses/my-house',                     [$HOUSE, 'myHouse'])    ->name('api.mobile.houses.my-house');
    Route::post  ('/houses/{houseId}/points',             [$HOUSE, 'awardPoints'])->whereNumber('houseId')->name('api.mobile.houses.points.award');
    Route::delete('/houses/{houseId}/points/{pointId}',   [$HOUSE, 'deletePoint'])->whereNumber('houseId')->whereNumber('pointId')->name('api.mobile.houses.points.delete');

    // Disciplinary Records (admin only)
    $DR = \App\Http\Controllers\Api\Mobile\DisciplinaryController::class;
    Route::get  ('/disciplinary',                       [$DR, 'index'])         ->name('api.mobile.disciplinary.index');
    Route::post ('/disciplinary',                       [$DR, 'store'])         ->name('api.mobile.disciplinary.store');
    Route::patch('/disciplinary/{id}',                  [$DR, 'update'])        ->whereNumber('id')->name('api.mobile.disciplinary.update');
    Route::get  ('/disciplinary/student/{studentId}',   [$DR, 'studentHistory'])->whereNumber('studentId')->name('api.mobile.disciplinary.student-history');

    // Transport — Admin (read-only fleet/route/allocation views)
    $TA = \App\Http\Controllers\Api\Mobile\TransportAdminController::class;
    Route::get('/transport/admin/routes',          [$TA, 'routes'])      ->name('api.mobile.transport.admin.routes');
    Route::get('/transport/admin/routes/{id}',     [$TA, 'routeDetail']) ->whereNumber('id')->name('api.mobile.transport.admin.routes.detail');
    Route::get('/transport/admin/vehicles',        [$TA, 'vehicles'])    ->name('api.mobile.transport.admin.vehicles');

    // Front Office — Call Logs + Follow-Ups
    $CL = \App\Http\Controllers\Api\Mobile\CallLogController::class;
    Route::get   ('/front-office/call-logs',                    [$CL, 'index'])         ->name('api.mobile.call-logs.index');
    Route::post  ('/front-office/call-logs',                    [$CL, 'store'])         ->name('api.mobile.call-logs.store');
    Route::patch ('/front-office/call-logs/{id}/follow-up',     [$CL, 'updateFollowUp'])->whereNumber('id')->name('api.mobile.call-logs.follow-up');
    Route::delete('/front-office/call-logs/{id}',               [$CL, 'destroy'])       ->whereNumber('id')->name('api.mobile.call-logs.destroy');
    Route::get   ('/front-office/call-logs/follow-ups',         [$CL, 'followUps'])     ->name('api.mobile.call-logs.follow-ups');

    // PTM — Parent-Teacher Meetings
    $PTM = \App\Http\Controllers\Api\Mobile\PtmController::class;
    // Admin
    Route::get  ('/ptm/sessions',                    [$PTM, 'sessions'])      ->name('api.mobile.ptm.sessions');
    Route::post ('/ptm/sessions',                    [$PTM, 'createSession']) ->name('api.mobile.ptm.sessions.create');
    Route::get  ('/ptm/sessions/{id}',               [$PTM, 'sessionDetail']) ->whereNumber('id')->name('api.mobile.ptm.sessions.detail');
    Route::patch('/ptm/sessions/{id}/status',        [$PTM, 'updateStatus'])  ->whereNumber('id')->name('api.mobile.ptm.sessions.status');
    // Teacher
    Route::get  ('/ptm/teacher/bookings',            [$PTM, 'teacherBookings'])->name('api.mobile.ptm.teacher.bookings');
    Route::patch('/ptm/bookings/{id}/notes',         [$PTM, 'addNotes'])      ->whereNumber('id')->name('api.mobile.ptm.bookings.notes');
    // Parent
    Route::get  ('/ptm/parent/sessions',             [$PTM, 'parentSessions'])->name('api.mobile.ptm.parent.sessions');
    Route::post ('/ptm/slots/{slotId}/book',         [$PTM, 'bookSlot'])      ->whereNumber('slotId')->name('api.mobile.ptm.slots.book');
    Route::patch('/ptm/bookings/{id}/cancel',        [$PTM, 'cancelBooking']) ->whereNumber('id')->name('api.mobile.ptm.bookings.cancel');

    // Report Card Download
    Route::get('/report-cards/{scheduleId}/download', [$MA, 'downloadReportCard'])->name('api.mobile.report-cards.download');

    // Admit Cards (parent + student view)
    Route::get('/admit-cards',                  [$MA, 'admitCards'])     ->name('api.mobile.admit-cards');
    Route::get('/admit-cards/{scheduleId}',     [$MA, 'admitCardDetail'])->whereNumber('scheduleId')->name('api.mobile.admit-cards.detail');

    // Transfer Certificate (parent + student view, latest TC only)
    Route::get('/transfer-certificate',         [$MA, 'transferCertificate'])->name('api.mobile.transfer-certificate');

    // Student Attendance (mark + report) — must come before generic /attendance
    Route::get('/attendance/students',    [$MA, 'attendanceStudents'])->name('api.mobile.attendance.students');
    Route::post('/attendance/mark',       [$MA, 'markAttendance'])->name('api.mobile.attendance.mark');
    Route::get('/attendance/report',      [$MA, 'attendanceReport'])->name('api.mobile.attendance.report');
    Route::get('/attendance/admin/date-wise', [$MA, 'attendanceDateWise'])->name('api.mobile.attendance.admin.date-wise');
    Route::get('/attendance/admin/forecast',  [$MA, 'attendanceForecast'])->name('api.mobile.attendance.admin.forecast');
    Route::post('/attendance/rapid-scan',       [$MA, 'rapidScanAttendance'])->name('api.mobile.attendance.rapid-scan');
    Route::post('/staff-attendance/rapid-scan', [$MA, 'rapidScanStaffAttendance'])->name('api.mobile.staff-attendance.rapid-scan');
    // Unified scan — auto-detects student vs staff from the QR payload.
    // Used by the single combined scanner shown to admin + gate keeper.
    Route::post('/attendance/scan',             [$MA, 'unifiedRapidScan'])->name('api.mobile.attendance.scan');
    Route::get('/staff-qr/me',                  [$MA, 'staffQrSelf'])->name('api.mobile.staff-qr.me');

    // AI Insights (admin-only; delegates to AiInsightsController)
    Route::get('/ai/insights',  [$MA, 'aiInsights'])->name('api.mobile.ai.insights');
    Route::post('/ai/query',    [$MA, 'aiQuery'])->name('api.mobile.ai.query');

    // Exam Marks Entry (teacher)
    $EMC = \App\Http\Controllers\Api\Mobile\ExamMarkController::class;
    Route::get('/exam-marks/schedules', [$EMC, 'schedules'])->name('api.mobile.exam-marks.schedules');
    Route::get('/exam-marks/students',  [$EMC, 'students'])->name('api.mobile.exam-marks.students');
    Route::post('/exam-marks/save',     [$EMC, 'save'])->name('api.mobile.exam-marks.save');

    // Hostel admin (lean v1: rooms / allocations)
    $HOST = \App\Http\Controllers\Api\Mobile\HostelController::class;
    Route::get  ('/hostel/hostels',                       [$HOST, 'hostels'])         ->name('api.mobile.hostel.hostels');
    Route::get  ('/hostel/rooms',                         [$HOST, 'rooms'])           ->name('api.mobile.hostel.rooms');
    Route::get  ('/hostel/available-beds',                [$HOST, 'availableBeds'])   ->name('api.mobile.hostel.available-beds');
    Route::get  ('/hostel/allocations',                   [$HOST, 'allocations'])     ->name('api.mobile.hostel.allocations');
    Route::post ('/hostel/allocations',                   [$HOST, 'createAllocation'])->name('api.mobile.hostel.allocations.create');
    Route::patch('/hostel/allocations/{id}/vacate',       [$HOST, 'vacateAllocation'])->whereNumber('id')->name('api.mobile.hostel.allocations.vacate');
    // Hostel gate passes (HostelLeaveRequest)
    Route::get  ('/hostel/gate-passes',                   [$HOST, 'gatePasses'])         ->name('api.mobile.hostel.gate-passes');
    Route::post ('/hostel/gate-passes',                   [$HOST, 'createGatePass'])     ->name('api.mobile.hostel.gate-passes.create');
    Route::patch('/hostel/gate-passes/{id}/status',       [$HOST, 'updateGatePassStatus'])->whereNumber('id')->name('api.mobile.hostel.gate-passes.status');
    // Hostel visitors
    Route::get  ('/hostel/visitors',                      [$HOST, 'visitors'])           ->name('api.mobile.hostel.visitors');
    Route::post ('/hostel/visitors',                      [$HOST, 'logVisitor'])         ->name('api.mobile.hostel.visitors.create');
    Route::patch('/hostel/visitors/{id}/checkout',        [$HOST, 'checkoutVisitor'])    ->whereNumber('id')->name('api.mobile.hostel.visitors.checkout');
    // Hostel mess menu
    Route::get   ('/hostel/mess-menu',                    [$HOST, 'messMenu'])           ->name('api.mobile.hostel.mess-menu');
    Route::post  ('/hostel/mess-menu',                    [$HOST, 'saveMessMenu'])       ->name('api.mobile.hostel.mess-menu.save');
    Route::delete('/hostel/mess-menu/{id}',               [$HOST, 'destroyMessMenu'])    ->whereNumber('id')->name('api.mobile.hostel.mess-menu.destroy');
    // Hostel roll call
    Route::get ('/hostel/roll-call',                      [$HOST, 'rollCall'])           ->name('api.mobile.hostel.roll-call');
    Route::post('/hostel/roll-call',                      [$HOST, 'saveRollCall'])       ->name('api.mobile.hostel.roll-call.save');

    // Stationary items (admin)
    $STAT = \App\Http\Controllers\Api\Mobile\StationaryController::class;
    Route::get   ('/stationary/items',          [$STAT, 'items'])      ->name('api.mobile.stationary.items');
    Route::post  ('/stationary/items',          [$STAT, 'storeItem'])  ->name('api.mobile.stationary.items.store');
    Route::patch ('/stationary/items/{id}',     [$STAT, 'updateItem']) ->whereNumber('id')->name('api.mobile.stationary.items.update');
    Route::delete('/stationary/items/{id}',     [$STAT, 'destroyItem'])->whereNumber('id')->name('api.mobile.stationary.items.destroy');
    // Stationary allocations + fee collection
    Route::get ('/stationary/allocations',                  [$STAT, 'allocations'])     ->name('api.mobile.stationary.allocations');
    Route::post('/stationary/allocations',                  [$STAT, 'createAllocation'])->name('api.mobile.stationary.allocations.create');
    Route::post('/stationary/allocations/{id}/collect',     [$STAT, 'collect'])         ->whereNumber('id')->name('api.mobile.stationary.allocations.collect');
    // Stationary issuance
    Route::get   ('/stationary/allocations/{id}/issuances', [$STAT, 'issuances'])      ->whereNumber('id')->name('api.mobile.stationary.allocations.issuances');
    Route::post  ('/stationary/allocations/{id}/issuances', [$STAT, 'createIssuance']) ->whereNumber('id')->name('api.mobile.stationary.allocations.issuances.create');
    Route::delete('/stationary/issuances/{id}',             [$STAT, 'voidIssuance'])   ->whereNumber('id')->name('api.mobile.stationary.issuances.void');
    // Stationary returns
    Route::get   ('/stationary/allocations/{id}/returns',   [$STAT, 'returns'])        ->whereNumber('id')->name('api.mobile.stationary.allocations.returns');
    Route::post  ('/stationary/allocations/{id}/returns',   [$STAT, 'createReturn'])   ->whereNumber('id')->name('api.mobile.stationary.allocations.returns.create');
    Route::delete('/stationary/returns/{id}',               [$STAT, 'voidReturn'])     ->whereNumber('id')->name('api.mobile.stationary.returns.void');

    // Inventory (admin)
    Route::get  ('/inventory',                 [$MA, 'inventoryAssets'])    ->name('api.mobile.inventory');
    Route::get  ('/inventory/categories',      [$MA, 'inventoryCategories'])->name('api.mobile.inventory.categories');
    Route::post ('/inventory',                 [$MA, 'storeInventoryAsset']) ->name('api.mobile.inventory.store');
    Route::patch('/inventory/{id}',            [$MA, 'updateInventoryAsset'])->whereNumber('id')->name('api.mobile.inventory.update');
    Route::get  ('/inventory/{id}',            [$MA, 'inventoryAsset'])      ->whereNumber('id')->name('api.mobile.inventory.detail');

    // Staff Punch (self-service clock-in / clock-out with geotag)
    Route::get('/staff-punch/status',    [$MA, 'staffPunchStatus'])->name('api.mobile.staff-punch.status');
    Route::post('/staff-punch/clock-in', [$MA, 'staffPunchClockIn'])->name('api.mobile.staff-punch.clock-in');
    Route::post('/staff-punch/clock-out',[$MA, 'staffPunchClockOut'])->name('api.mobile.staff-punch.clock-out');

    // Student Leave — approve/reject (admin/teacher only)
    Route::patch('/leaves/{id}/approve', [$MA, 'approveStudentLeave'])->whereNumber('id')->name('api.mobile.leaves.approve');
    Route::patch('/leaves/{id}/reject',  [$MA, 'rejectStudentLeave'])->whereNumber('id')->name('api.mobile.leaves.reject');

    // Staff Leave
    Route::get('/staff-leave-types',              [$MA, 'staffLeaveTypes'])->name('api.mobile.staff-leave-types');
    Route::get('/staff-leaves',                   [$MA, 'staffLeaves'])->name('api.mobile.staff-leaves');
    Route::post('/staff-leaves',                  [$MA, 'applyStaffLeave'])->name('api.mobile.staff-leaves.apply');
    Route::delete('/staff-leaves/{id}',           [$MA, 'cancelStaffLeave'])->whereNumber('id')->name('api.mobile.staff-leaves.cancel');
    Route::patch('/staff-leaves/{id}/approve',    [$MA, 'approveStaffLeave'])->whereNumber('id')->name('api.mobile.staff-leaves.approve');
    Route::patch('/staff-leaves/{id}/reject',     [$MA, 'rejectStaffLeave'])->whereNumber('id')->name('api.mobile.staff-leaves.reject');
});

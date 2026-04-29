<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Inertia\Inertia;

// Public Routes
Route::get('/', [\App\Http\Controllers\PublicController::class, 'home']);

// Signed PDF download for daily master reports — accessed via SMS link
Route::get('/reports/daily-master/{schoolId}/{date}.pdf',
    [\App\Http\Controllers\School\Reports\DailyMasterReportController::class, 'pdfSigned'])
    ->name('school.reports.daily-master.pdf-signed');

Route::get('/verify-receipt/{receipt_no}', [\App\Http\Controllers\PublicController::class, 'verifyReceipt'])->name('verify-receipt');
Route::get('/verify-transport-receipt/{receipt_no}', [\App\Http\Controllers\PublicController::class, 'verifyTransportReceipt'])->name('verify-transport-receipt');
Route::get('/verify-hostel-receipt/{receipt_no}', [\App\Http\Controllers\PublicController::class, 'verifyHostelReceipt'])->name('verify-hostel-receipt');
Route::get('/verify-stationary-receipt/{receipt_no}', [\App\Http\Controllers\PublicController::class, 'verifyStationaryReceipt'])->name('verify-stationary-receipt');
Route::get('/school/hostel/gate-passes/verify/{token}', [\App\Http\Controllers\PublicController::class, 'verifyGatePass'])->name('gate-pass.verify-public');
Route::get('/school/hostel/visitors/verify/{token}', [\App\Http\Controllers\PublicController::class, 'verifyVisitorPass'])->name('visitor-pass.verify-public');
Route::get('/verify/certificate/{token}', [\App\Http\Controllers\PublicController::class, 'verifyCertificate'])->name('certificate.verify-public');

// Student Universal QR
Route::get('/q/{uuid}', [\App\Http\Controllers\QRScanController::class, 'show'])->name('qr.student.show');
Route::post('/q/{uuid}/attendance', [\App\Http\Controllers\QRScanController::class, 'markAttendance'])->name('qr.student.attendance');

// Razorpay Webhook (no auth, no CSRF)
Route::post('/webhooks/razorpay', [\App\Http\Controllers\Portal\RazorpayWebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.razorpay');

// Authentication
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    
    // OTP Login Routes
    Route::post('/login/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:5,1')->name('login.otp.request');
    Route::post('/login/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('login.otp.verify');
});

use App\Http\Controllers\Admin\RolePermissionController;

// Protected Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Core Dashboard routing
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Parent Portal ─────────────────────────────────────────────────────
    // Route: /portal/student  →  Pages/Portal/StudentProfile.vue
    // Role: parent (enforced in controller — returns access_denied flag if not parent)
    Route::get('/portal/student', [\App\Http\Controllers\ParentPortalController::class, 'studentProfile'])
        ->name('portal.student');
    Route::post('/portal/student/edit-request', [\App\Http\Controllers\ParentPortalController::class, 'requestProfileEdit'])
        ->name('portal.student.edit-request');
    Route::post('/portal/student/{student}/documents', [\App\Http\Controllers\ParentPortalController::class, 'storeDocument'])
        ->name('portal.student.documents.store');
    Route::delete('/portal/student/{student}/documents/{document}', [\App\Http\Controllers\ParentPortalController::class, 'destroyDocument'])
        ->name('portal.student.documents.destroy');

    // ── Online Fee Payment (Parent/Student Portal) ───────────────────────
    Route::get('/portal/fees', [\App\Http\Controllers\Portal\PortalFeeController::class, 'index'])
        ->name('portal.fees');
    Route::post('/portal/fees/create-order', [\App\Http\Controllers\Portal\PortalFeeController::class, 'createOrder'])
        ->name('portal.fees.create-order');
    Route::post('/portal/fees/verify-payment', [\App\Http\Controllers\Portal\PortalFeeController::class, 'verifyPayment'])
        ->name('portal.fees.verify-payment');
    Route::get('/portal/fees/history', [\App\Http\Controllers\Portal\PortalFeeController::class, 'history'])
        ->name('portal.fees.history');

    // Platform Admin Routes (Super Admin Only)
    Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => ['super_admin']], function () {
        Route::get('/roles-permissions',   [App\Http\Controllers\Admin\RolePermissionController::class, 'index'])  ->name('roles.matrix');
        Route::post('/roles-permissions',  [App\Http\Controllers\Admin\RolePermissionController::class, 'update']) ->name('roles.update');
        Route::post('/roles',              [App\Http\Controllers\Admin\RolePermissionController::class, 'store'])  ->name('roles.store');
        Route::delete('/roles/{role}',     [App\Http\Controllers\Admin\RolePermissionController::class, 'destroy'])->name('roles.destroy');
        Route::get('/users',               [App\Http\Controllers\ImpersonationController::class, 'index'])->name('users.index');
    });

    // ── Impersonation (Super Admin Only at route level) ──────────────────
    Route::group(['middleware' => ['permission:impersonate_users']], function () {
        Route::post('/impersonate/exit',   [App\Http\Controllers\ImpersonationController::class, 'exit'])->name('impersonate.exit');
        Route::post('/impersonate/{user}', [App\Http\Controllers\ImpersonationController::class, 'impersonate'])->name('impersonate');
    });

    // Organization Admin Routes (Specific platform views)
    Route::group(['prefix' => 'admin/org', 'as' => 'org.admin.', 'middleware' => ['super_admin']], function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\OrganizationAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/schools/create', [\App\Http\Controllers\Admin\OrganizationAdminController::class, 'createSchool'])->name('schools.create');
        Route::post('/schools', [\App\Http\Controllers\Admin\OrganizationAdminController::class, 'storeSchool'])->name('schools.store');
        Route::get('/schools/{school}/manage', [\App\Http\Controllers\Admin\OrganizationAdminController::class, 'manageSchool'])->name('schools.manage');
    });

    // Platform Organization Management
    Route::group(['middleware' => ['auth', 'super_admin']], function () {
        Route::get('/admin/organizations', [\App\Http\Controllers\Admin\OrganizationManagementController::class, 'index'])->name('admin.organizations.index');
        Route::get('/admin/organizations/create', [\App\Http\Controllers\Admin\OrganizationManagementController::class, 'create'])->name('admin.organizations.create');
        Route::post('/admin/organizations', [\App\Http\Controllers\Admin\OrganizationManagementController::class, 'store'])->name('admin.organizations.store');
    });

    // User Profile
    Route::post('/profile/password', [\App\Http\Controllers\UserProfileController::class, 'updatePassword'])->name('profile.password');

    // School level routes (Scoped by ResolveTenant middleware implicitly)
    Route::group(['prefix' => 'school', 'as' => 'school.'], function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');

        // UI Sandbox — manual visual QA for shared layout primitives (Phase 1
        // of the layout-standardization migration). No controller — pure render.
        Route::get('/_ui-sandbox', fn () => \Inertia\Inertia::render('School/UISandbox'))
            ->name('ui-sandbox');

        // Staff Self-Service Punch Attendance (topbar panel — any logged-in staff)
        $SPC = \App\Http\Controllers\School\StaffPunchController::class;
        Route::post('staff-punch/clock-in',  [$SPC, 'clockIn'])  ->name('staff-punch.clock-in');
        Route::post('staff-punch/clock-out', [$SPC, 'clockOut']) ->name('staff-punch.clock-out');

        // AI Features
        Route::post('ai/chat', [\App\Http\Controllers\School\AiChatController::class, 'chat'])->middleware('throttle:30,1')->name('ai.chat');
        Route::post('ai/chat/stream', [\App\Http\Controllers\School\AiChatController::class, 'chatStream'])->middleware('throttle:30,1')->name('ai.chat.stream');
        Route::post('ai/report-card-comments', [\App\Http\Controllers\School\AiChatController::class, 'generateReportComments'])->middleware('throttle:5,1')->name('ai.report-card-comments');
        Route::get('ai/insights', [\App\Http\Controllers\School\AiInsightsController::class, 'index'])->name('ai.insights');
        Route::post('ai/insights/generate', [\App\Http\Controllers\School\AiInsightsController::class, 'generateInsights'])->middleware('throttle:10,1')->name('ai.insights.generate');
        Route::post('ai/query', [\App\Http\Controllers\School\AiInsightsController::class, 'queryData'])->middleware('throttle:30,1')->name('ai.query');
        Route::post('ai/query/stream', [\App\Http\Controllers\School\AiInsightsController::class, 'queryDataStream'])->middleware('throttle:30,1')->name('ai.query.stream');
        Route::post('ai/suggestions', [\App\Http\Controllers\School\AiSuggestionsController::class, 'index'])->middleware('throttle:60,1')->name('ai.suggestions');
        Route::post('ai/explain-chart', [\App\Http\Controllers\School\AiInsightsController::class, 'explainChart'])->middleware('throttle:20,1')->name('ai.explain-chart');
        Route::get('ai/insights/charts', [\App\Http\Controllers\School\AiInsightsController::class, 'charts'])->name('ai.insights.charts');
        Route::get('ai/insights/views', [\App\Http\Controllers\School\AiInsightViewsController::class, 'index'])->name('ai.insights.views.index');
        Route::post('ai/insights/views', [\App\Http\Controllers\School\AiInsightViewsController::class, 'store'])->middleware('throttle:30,1')->name('ai.insights.views.store');
        Route::delete('ai/insights/views/{view}', [\App\Http\Controllers\School\AiInsightViewsController::class, 'destroy'])->name('ai.insights.views.destroy');
        Route::post('ai/insights/export-pdf', [\App\Http\Controllers\School\AiInsightsController::class, 'exportPdf'])->middleware('throttle:10,1')->name('ai.insights.export.pdf');
        Route::get('ai/insights/export-excel', [\App\Http\Controllers\School\AiInsightsController::class, 'exportExcel'])->middleware('throttle:10,1')->name('ai.insights.export.excel');
        // School Profile → redirects to General Config (consolidated)
        Route::get('settings/profile', fn() => redirect('/school/settings/general-config', 301))->name('settings.profile');

        // General Configuration (canonical settings page)
        Route::get('settings/general-config',  [\App\Http\Controllers\School\GeneralConfigController::class, 'index']) ->name('settings.general-config');
        Route::post('settings/general-config', [\App\Http\Controllers\School\GeneralConfigController::class, 'update'])->name('settings.general-config.update');

        // Notification Preference Center (per-user, all roles)
        $NPC = \App\Http\Controllers\School\NotificationPreferencesController::class;
        Route::get('settings/notification-preferences',  [$NPC, 'show'])  ->name('settings.notification-preferences');
        Route::post('settings/notification-preferences', [$NPC, 'update'])->name('settings.notification-preferences.update');

        // Geofence Configuration (admin)
        Route::get('settings/geofence-config', function () {
            return \Inertia\Inertia::render('School/Settings/GeoFenceConfig', [
                'school' => app('current_school'),
            ]);
        })->name('settings.geofence-config');
        Route::post('settings/geofence', [$SPC, 'saveGeoFence'])->name('settings.geofence.update');

        // Asset Configuration (logo, icon, favicon)
        Route::get('settings/asset-config',  [\App\Http\Controllers\School\AssetConfigController::class, 'index']) ->name('settings.asset-config');
        Route::post('settings/asset-config', [\App\Http\Controllers\School\AssetConfigController::class, 'update'])->name('settings.asset-config.update');
        Route::delete('settings/asset-config/{type}', [\App\Http\Controllers\School\AssetConfigController::class, 'destroy'])->name('settings.asset-config.destroy');

        // System Configuration (date, time, currency, pagination)
        Route::get('settings/system-config',  [\App\Http\Controllers\School\SystemConfigController::class, 'index']) ->name('settings.system-config');
        Route::post('settings/system-config', [\App\Http\Controllers\School\SystemConfigController::class, 'update'])->name('settings.system-config.update');

        // Admin Contacts (admin numbers for system notifications)
        $ACC = \App\Http\Controllers\School\AdminContactController::class;
        Route::get   ('settings/admin-contacts',           [$ACC, 'index'])  ->name('settings.admin-contacts');
        Route::post  ('settings/admin-contacts',           [$ACC, 'store'])  ->name('settings.admin-contacts.store');
        Route::put   ('settings/admin-contacts/{contact}', [$ACC, 'update']) ->name('settings.admin-contacts.update');
        Route::delete('settings/admin-contacts/{contact}', [$ACC, 'destroy'])->name('settings.admin-contacts.destroy');

        // Daily Master Report — page, manual send, PDF download, settings
        $DMR = \App\Http\Controllers\School\Reports\DailyMasterReportController::class;
        Route::get ('reports/daily-master',       [$DMR, 'index']) ->name('reports.daily-master');
        Route::post('reports/daily-master/send',  [$DMR, 'send'])  ->name('reports.daily-master.send');
        Route::get ('reports/daily-master/pdf',   [$DMR, 'pdf'])   ->name('reports.daily-master.pdf');

        // Daily Report Settings (per-school sections, thresholds, schedule)
        $DRS = \App\Http\Controllers\School\Settings\DailyReportSettingsController::class;
        Route::get ('settings/daily-report',  [$DRS, 'index'])  ->name('settings.daily-report');
        Route::post('settings/daily-report',  [$DRS, 'update']) ->name('settings.daily-report.update');

        // Receipt Print Settings (paper size + copies for all fee receipts)
        $RPS = \App\Http\Controllers\School\Settings\ReceiptPrintSettingsController::class;
        Route::get ('settings/receipt-print', [$RPS, 'index'])  ->name('settings.receipt-print');
        Route::post('settings/receipt-print', [$RPS, 'update']) ->name('settings.receipt-print.update');

        // Mobile App QR Code — generate scannable QR for EduConnect app onboarding
        Route::get('settings/mobile-qr', [\App\Http\Controllers\School\MobileQrController::class, 'index'])->name('settings.mobile-qr');

        // Shared utility: quick student list for ID-card / certificate generate dropdowns
        Route::get('utility/students-quick', function (\Illuminate\Http\Request $request) {
            $schoolId       = app('current_school_id');
            $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

            $query = \App\Models\Student::where('school_id', $schoolId)
                ->where('status', 'active')
                ->orderBy('first_name');

            if ($request->filled('class_id') || $request->filled('section_id')) {
                $query->whereHas('currentAcademicHistory', function ($q) use ($request, $academicYearId) {
                    $q->where('academic_year_id', $academicYearId)->where('status', 'current');
                    if ($request->filled('class_id'))   $q->where('class_id',   $request->class_id);
                    if ($request->filled('section_id')) $q->where('section_id', $request->section_id);
                });
            }

            return $query->limit(300)->get(['id', 'first_name', 'last_name', 'admission_no'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => trim("{$s->first_name} {$s->last_name}"), 'admission_no' => $s->admission_no]);
        })->name('utility.students-quick');

        // Utility
        Route::get('utility/activity-log', [\App\Http\Controllers\School\ActivityLogController::class, 'index'])->name('utility.activity-log');
        Route::get('utility/error-log', [\App\Http\Controllers\School\ErrorLogController::class, 'index'])->name('utility.error-log');
        Route::get('utility/id-cards', [\App\Http\Controllers\School\IdCardController::class, 'index'])->name('utility.id-cards');
        Route::get('utility/id-cards/create', [\App\Http\Controllers\School\IdCardController::class, 'create'])->name('utility.id-cards.create');
        Route::post('utility/id-cards', [\App\Http\Controllers\School\IdCardController::class, 'store'])->name('utility.id-cards.store');
        Route::get('utility/id-cards/{idCardTemplate}/edit', [\App\Http\Controllers\School\IdCardController::class, 'edit'])->name('utility.id-cards.edit');
        Route::put('utility/id-cards/{idCardTemplate}', [\App\Http\Controllers\School\IdCardController::class, 'update'])->name('utility.id-cards.update');
        Route::delete('utility/id-cards/{idCardTemplate}', [\App\Http\Controllers\School\IdCardController::class, 'destroy'])->name('utility.id-cards.destroy');
        Route::get('utility/id-cards/{idCardTemplate}/generate', [\App\Http\Controllers\School\IdCardController::class, 'generate'])->name('utility.id-cards.generate');
        Route::get('utility/id-cards/{idCardTemplate}/print', [\App\Http\Controllers\School\IdCardController::class, 'print'])->name('utility.id-cards.print');

        // Certificates
        Route::get('utility/certificates', [\App\Http\Controllers\School\CertificateController::class, 'index'])->name('utility.certificates');
        Route::get('utility/certificates/create', [\App\Http\Controllers\School\CertificateController::class, 'create'])->name('utility.certificates.create');
        Route::post('utility/certificates', [\App\Http\Controllers\School\CertificateController::class, 'store'])->name('utility.certificates.store');
        Route::get('utility/certificates/{certificateTemplate}/edit', [\App\Http\Controllers\School\CertificateController::class, 'edit'])->name('utility.certificates.edit');
        Route::put('utility/certificates/{certificateTemplate}', [\App\Http\Controllers\School\CertificateController::class, 'update'])->name('utility.certificates.update');
        Route::delete('utility/certificates/{certificateTemplate}', [\App\Http\Controllers\School\CertificateController::class, 'destroy'])->name('utility.certificates.destroy');
        Route::get('utility/certificates/{certificateTemplate}/generate', [\App\Http\Controllers\School\CertificateController::class, 'generate'])->name('utility.certificates.generate');
        Route::get('utility/certificates/{certificateTemplate}/print', [\App\Http\Controllers\School\CertificateController::class, 'print'])->name('utility.certificates.print');
        Route::post('utility/certificates/{certificateTemplate}/issue', [\App\Http\Controllers\School\CertificateController::class, 'issue'])->name('utility.certificates.issue');
        Route::get('utility/certificates/{certificateTemplate}/issued', [\App\Http\Controllers\School\CertificateController::class, 'issued'])->name('utility.certificates.issued');

        Route::post('switch-academic-year', function (\Illuminate\Http\Request $request) {
            $request->validate(['academic_year_id' => 'required|exists:academic_years,id']);
            
            // Validate it belongs to this school
            $year = \App\Models\AcademicYear::where('id', $request->academic_year_id)
                ->where('school_id', app('current_school')->id)
                ->firstOrFail();
                
            session(['selected_academic_year_id' => $year->id]);
            
            return back()->with('status', "Switched view to Academic Year: {$year->name}");
        })->name('switch-academic-year');

        Route::post('switch-school', function (\Illuminate\Http\Request $request) {
            if (!auth()->user()->isSuperAdmin()) abort(403);
            $request->validate(['school_id' => 'required|exists:schools,id']);
            session(['superadmin_school_id' => $request->school_id]);
            
            // Unset academic year so it falls back to the current active one for the new school
            session()->forget('selected_academic_year_id');
            
            return back()->with('status', "Switched view to School");
        })->name('switch-school');

        // ── Academic Structure (management: school_admin + principal) ──
        Route::middleware(['school.management', 'module:settings'])->group(function () {
            Route::resource('academic-years', \App\Http\Controllers\School\AcademicYearController::class)->except(['create', 'show', 'edit']);
        });

        // ── Rollover Wizard (academic year transition) ──
        // Read-only routes require manage_rollover; mutations require execute_rollover.
        Route::middleware(['school.management', 'module:settings'])->group(function () {
            Route::get('settings/rollover',
                [\App\Http\Controllers\School\RolloverController::class, 'index']
            )->middleware('permission:manage_rollover')->name('settings.rollover');

            Route::get('settings/rollover/runs/{run}',
                [\App\Http\Controllers\School\RolloverController::class, 'show']
            )->middleware('permission:manage_rollover')->name('settings.rollover.show');

            Route::post('settings/rollover',
                [\App\Http\Controllers\School\RolloverController::class, 'execute']
            )->middleware('permission:execute_rollover')->name('settings.rollover.execute');

            Route::post('settings/rollover/runs/{run}/promote-students',
                [\App\Http\Controllers\School\RolloverController::class, 'promoteStudents']
            )->middleware('permission:execute_rollover')->name('settings.rollover.promote-students');

            // Manual (batch-by-batch) student promotion — driven by a dedicated UI page.
            Route::get('settings/rollover/runs/{run}/promote-manual',
                [\App\Http\Controllers\School\RolloverController::class, 'manualPromoteIndex']
            )->middleware('permission:execute_rollover')->name('settings.rollover.promote-manual');

            Route::get('settings/rollover/runs/{run}/classes',
                [\App\Http\Controllers\School\RolloverController::class, 'classesForYear']
            )->middleware('permission:execute_rollover')->name('settings.rollover.classes');

            Route::get('settings/rollover/runs/{run}/sections',
                [\App\Http\Controllers\School\RolloverController::class, 'sectionsForClass']
            )->middleware('permission:execute_rollover')->name('settings.rollover.sections');

            Route::get('settings/rollover/runs/{run}/eligible-students',
                [\App\Http\Controllers\School\RolloverController::class, 'eligibleStudents']
            )->middleware('permission:execute_rollover')->name('settings.rollover.eligible-students');

            Route::post('settings/rollover/runs/{run}/promote-manual',
                [\App\Http\Controllers\School\RolloverController::class, 'promoteManual']
            )->middleware('permission:execute_rollover')->name('settings.rollover.promote-manual.submit');

            Route::post('settings/rollover/runs/{run}/mark-students-done',
                [\App\Http\Controllers\School\RolloverController::class, 'markStudentsDone']
            )->middleware('permission:execute_rollover')->name('settings.rollover.mark-students-done');

            Route::post('settings/rollover/runs/{run}/carry-forward',
                [\App\Http\Controllers\School\RolloverController::class, 'carryForward']
            )->middleware('permission:execute_rollover')->name('settings.rollover.carry-forward');

            Route::post('settings/rollover/runs/{run}/finalize',
                [\App\Http\Controllers\School\RolloverController::class, 'finalize']
            )->middleware('permission:finalize_rollover')->name('settings.rollover.finalize');
        });

        // ── Bulk Import (Students, Staff & Photos) ──
        // Rate-limited: max 10 imports per minute per user to prevent server overload
        Route::middleware(['school.management'])->group(function () {
            $BIC = \App\Http\Controllers\School\BulkImportController::class;
            Route::get('bulk-import',                  [$BIC, 'index'])            ->name('bulk-import.index');
            Route::post('bulk-import',                 [$BIC, 'import'])           ->name('bulk-import.import')->middleware('throttle:10,1');
            Route::post('bulk-import/photos',          [$BIC, 'importPhotos'])     ->name('bulk-import.photos')->middleware('throttle:10,1');
            Route::get('bulk-import/template/{type}',  [$BIC, 'downloadTemplate']) ->name('bulk-import.template');
            Route::get('bulk-import/errors',           [$BIC, 'downloadErrors'])   ->name('bulk-import.errors');
        });

        // ── User Login Management (school.management only — no module restriction) ──
        Route::middleware(['school.management'])->prefix('users')->group(function () {
            Route::get('/', [\App\Http\Controllers\School\UserManagementController::class, 'index'])->name('users.index');
            Route::post('{id}/reset-password', [\App\Http\Controllers\School\UserManagementController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('{id}/toggle-status',  [\App\Http\Controllers\School\UserManagementController::class, 'toggleStatus'])->name('users.toggle-status');
            Route::post('{id}/update-username', [\App\Http\Controllers\School\UserManagementController::class, 'updateUsername'])->name('users.update-username');
            Route::post('create-missing',      [\App\Http\Controllers\School\UserManagementController::class, 'createMissing'])->name('users.create-missing');
            Route::post('bulk-reset',          [\App\Http\Controllers\School\UserManagementController::class, 'bulkReset'])->name('users.bulk-reset');
            Route::post('export-credentials',  [\App\Http\Controllers\School\UserManagementController::class, 'exportCredentials'])->name('users.export-credentials');
            Route::get('export-list',          [\App\Http\Controllers\School\UserManagementController::class, 'exportList'])->name('users.export-list');
        });

        Route::middleware(['school.management', 'module:students'])->group(function () {
            Route::get('students/search', [\App\Http\Controllers\School\StudentController::class, 'search'])->name('students.search');
            Route::get('students/scanner', [\App\Http\Controllers\School\StudentController::class, 'qrProfileScanner'])->name('students.scanner');
            Route::post('students/scan-by-uuid', [\App\Http\Controllers\School\StudentController::class, 'scanByUuid'])->name('students.scan-by-uuid');
            Route::get('students/export-qr',     [\App\Http\Controllers\School\StudentController::class, 'exportQRCodes'])->name('students.export-qr');
            Route::get('students/export-qr-pdf', [\App\Http\Controllers\School\StudentController::class, 'exportQrCodesPdf'])->name('students.export-qr-pdf');
            Route::get('students/bulk-photo', [\App\Http\Controllers\School\StudentController::class, 'bulkPhotoUploadForm'])->name('students.bulk-photo');
            Route::post('students/bulk-photo', [\App\Http\Controllers\School\StudentController::class, 'processBulkPhotoUpload'])->name('students.bulk-photo.store');
            Route::get('students/{student}/request-edit', [\App\Http\Controllers\School\StudentController::class, 'createRequest'])->name('students.request-edit');
            Route::post('students/{student}/request-edit', [\App\Http\Controllers\School\StudentController::class, 'storeRequest'])->name('students.request-edit.store');
            Route::resource('students', \App\Http\Controllers\School\StudentController::class);

            Route::post('students/{student}/documents', [\App\Http\Controllers\School\StudentDocumentController::class, 'store'])->name('students.documents.store');
            Route::delete('students/{student}/documents/{document}', [\App\Http\Controllers\School\StudentDocumentController::class, 'destroy'])->name('students.documents.destroy');
            Route::get('students/{student}/health', [\App\Http\Controllers\School\StudentHealthController::class, 'edit'])->name('students.health.edit');
            Route::put('students/{student}/health', [\App\Http\Controllers\School\StudentHealthController::class, 'update'])->name('students.health.update');
            Route::patch('students/{student}/admission-no', [\App\Http\Controllers\School\StudentController::class, 'updateAdmissionNo'])->name('students.admission-no.update');
            Route::patch('students/{student}/record', [\App\Http\Controllers\School\StudentController::class, 'updateRecord'])->name('students.record.update');
            Route::patch('students/{student}/defaulter', [\App\Http\Controllers\School\StudentController::class, 'toggleDefaulter'])->name('students.defaulter.toggle');
            Route::post('students/bulk-flag-defaulter', [\App\Http\Controllers\School\StudentController::class, 'bulkFlagDefaulter'])->name('students.defaulter.bulk');

            $R = \App\Http\Controllers\School\StudentApplicationController::class;
            Route::resource('registrations', $R)->except(['destroy']);
            Route::post('registrations/{registration}/approve', [$R, 'approve'])->name('registrations.approve');
            Route::post('registrations/{registration}/reject',  [$R, 'reject'])->name('registrations.reject');
        });

        // Student Leave Management — open to students, parents, and management
        // No school.management middleware so all authenticated roles can access.
        // Access control is enforced inside the controller.
        Route::middleware(['permission:view_student_leaves'])->group(function () {
            $SLC = \App\Http\Controllers\School\StudentLeaveController::class;
            Route::get('student-leaves',                                  [$SLC, 'index'])    ->name('student-leaves.index');
            Route::post('student-leaves',                                 [$SLC, 'store'])    ->name('student-leaves.store');
            Route::patch('student-leaves/{studentLeave}/approve',         [$SLC, 'approve'])  ->name('student-leaves.approve');
            Route::patch('student-leaves/{studentLeave}/reject',          [$SLC, 'reject'])   ->name('student-leaves.reject');
            Route::patch('student-leaves/{studentLeave}/revert',          [$SLC, 'revert'])   ->name('student-leaves.revert');
            // Secure document download — served through controller (private storage)
            Route::get('student-leaves/{studentLeave}/document',          [$SLC, 'document']) ->name('student-leaves.document');
        });

        // Student Leave Types Management — management only
        Route::middleware(['school.management', 'module:students'])->group(function () {
            $SLTC = \App\Http\Controllers\School\StudentLeaveTypeController::class;
            Route::get('student-leave-types',                               [$SLTC, 'index'])   ->name('student-leave-types.index');
            Route::post('student-leave-types',                              [$SLTC, 'store'])   ->name('student-leave-types.store');
            Route::put('student-leave-types/{studentLeaveType}',            [$SLTC, 'update'])  ->name('student-leave-types.update');
            Route::delete('student-leave-types/{studentLeaveType}',         [$SLTC, 'destroy']) ->name('student-leave-types.destroy');
            Route::patch('student-leave-types/{studentLeaveType}/toggle',   [$SLTC, 'toggle'])  ->name('student-leave-types.toggle');
            Route::post('student-leave-types/reorder',                      [$SLTC, 'reorder']) ->name('student-leave-types.reorder');
        });

        // Roll Number Management
        Route::middleware(['school.management', 'module:students'])->group(function () {
            $RNC = \App\Http\Controllers\School\RollNumberController::class;
            Route::get('roll-numbers',       [$RNC, 'index'])->name('roll-numbers.index');
            Route::post('roll-numbers/save', [$RNC, 'save']) ->name('roll-numbers.save');
        });

        // Transfer Certificates — management only
        Route::middleware(['school.management', 'module:students'])->group(function () {
            $TCC = \App\Http\Controllers\School\TransferCertificateController::class;
            Route::get('transfer-certificates',                             [$TCC, 'index'])   ->name('transfer-certificates.index');
            Route::get('transfer-certificates/create',                     [$TCC, 'create'])  ->name('transfer-certificates.create');
            Route::post('transfer-certificates',                            [$TCC, 'store'])   ->name('transfer-certificates.store');
            Route::get('transfer-certificates/{transferCertificate}',       [$TCC, 'show'])    ->name('transfer-certificates.show');
            Route::patch('transfer-certificates/{transferCertificate}/approve', [$TCC, 'approve'])->name('transfer-certificates.approve');
            Route::patch('transfer-certificates/{transferCertificate}/reject',  [$TCC, 'reject']) ->name('transfer-certificates.reject');
            Route::patch('transfer-certificates/{transferCertificate}/issue',   [$TCC, 'issue'])  ->name('transfer-certificates.issue');
            Route::get('transfer-certificates/{transferCertificate}/print', [$TCC, 'print'])   ->name('transfer-certificates.print');
        });

        // Attendance (open to teachers too — no extra middleware)
        Route::middleware(['module:attendance'])->group(function () {
            Route::get('attendance', [\App\Http\Controllers\School\AttendanceController::class, 'index'])->name('attendance.index');
            Route::get('attendance/scanner', [\App\Http\Controllers\School\AttendanceController::class, 'scanner'])->name('attendance.scanner');
            Route::post('attendance/rapid-scan', [\App\Http\Controllers\School\AttendanceController::class, 'rapidScan'])->name('attendance.rapid-scan');
            Route::post('attendance', [\App\Http\Controllers\School\AttendanceController::class, 'store'])->name('attendance.store');
            Route::get('attendance/report', [\App\Http\Controllers\School\AttendanceController::class, 'report'])->name('attendance.report');
            Route::get('attendance/forecast', [\App\Http\Controllers\School\AttendanceController::class, 'forecast'])->name('attendance.forecast');
            Route::get('attendance/date-wise', [\App\Http\Controllers\School\AttendanceController::class, 'dateWise'])->name('attendance.date-wise');
        });

        // ── Finance & Fees (school_admin only) ──
        Route::middleware(['school.management', 'module:fee'])->group(function () {
            $F = \App\Http\Controllers\School\FeeController::class;
            Route::get('fee/groups',              [$F, 'groupsIndex'])     ->name('fee.groups');
            Route::post('fee/groups',             [$F, 'groupStore'])      ->name('fee.groups.store');
            Route::put('fee/groups/{feeGroup}',   [$F, 'groupUpdate'])     ->name('fee.groups.update');
            Route::delete('fee/groups/{feeGroup}',[$F, 'groupDestroy'])    ->name('fee.groups.destroy');
            Route::post('fee/heads',              [$F, 'headStore'])       ->name('fee.heads.store');
            Route::put('fee/heads/{feeHead}',     [$F, 'headUpdate'])      ->name('fee.heads.update');
            Route::delete('fee/heads/{feeHead}',  [$F, 'headDestroy'])     ->name('fee.heads.destroy');
            Route::get('fee/structure',           [$F, 'structureIndex'])  ->name('fee.structure');
            Route::post('fee/structure',          [$F, 'structureStore'])  ->name('fee.structure.store');
            Route::get('fee/structure/history',   [$F, 'structureHistory'])->name('fee.structure.history');
            Route::delete('fee/structure/{feeStructure}', [$F, 'structureDestroy'])->name('fee.structure.destroy');
            Route::get('fee/collect',             [$F, 'collectIndex'])    ->name('fee.collect');
            // Rate-limited: max 60 payments per minute (prevents accidental double-submit spam)
            Route::post('fee/collect',            [$F, 'collectStore'])    ->name('fee.collect.store')->middleware('throttle:60,1');
            Route::put('fee/collect/{feePayment}',[$F, 'collectUpdate'])   ->name('fee.collect.update');
            Route::delete('fee/collect/{feePayment}',[$F, 'collectDestroy'])->name('fee.collect.destroy');
            Route::post('fee/collect/{feePayment}/post-gl', [$F, 'collectPostGl'])->name('fee.collect.post-gl');
            Route::post('fee/collect/batch-post-gl', [$F, 'batchPostGl'])->name('fee.collect.batch-post-gl');
            Route::get('fee/config', fn() => redirect('/school/settings/number-formats'))->name('fee.config');
            Route::patch('fee/collect/{feePayment}/receipt-no', [$F, 'updateReceiptNo'])->name('fee.collect.receipt-no.update');
            Route::get('fee/collect/{feePayment}/receipt', [$F, 'receipt'])->name('fee.collect.receipt');
            Route::get('fee/ledger/{student}', [$F, 'studentLedger'])->name('fee.student-ledger');

            $FC = \App\Http\Controllers\School\FeeConcessionController::class;
            Route::get('fee/concessions',                         [$FC, 'index'])        ->name('fee.concessions');
            Route::post('fee/concessions',                        [$FC, 'store'])        ->name('fee.concessions.store');
            Route::put('fee/concessions/{feeConcession}',         [$FC, 'update'])       ->name('fee.concessions.update');
            Route::patch('fee/concessions/{feeConcession}/toggle',[$FC, 'toggleActive']) ->name('fee.concessions.toggle');
            Route::delete('fee/concessions/{feeConcession}',      [$FC, 'destroy'])      ->name('fee.concessions.destroy');
            Route::get('fee/concessions/student/{student}',       [$FC, 'forStudent'])   ->name('fee.concessions.for-student');

            $FCT = \App\Http\Controllers\School\FeeConcessionTypeController::class;
            Route::patch('fee/concession-types/{feeConcessionType}/toggle', [$FCT, 'toggleActive'])->name('fee.concession-types.toggle');
            Route::resource('fee/concession-types', $FCT)->except(['create', 'show']);
        });

        Route::middleware(['school.management', 'module:expense'])->group(function () {
            Route::resource('expense-categories', \App\Http\Controllers\School\Finance\ExpenseCategoryController::class)->except(['create', 'show', 'edit']);
            Route::resource('expenses', \App\Http\Controllers\School\Finance\ExpenseController::class)->except(['create', 'show', 'edit']);
            Route::post('expenses/post-all-unposted', [\App\Http\Controllers\School\Finance\ExpenseController::class, 'postAllUnposted'])->name('expenses.post-all-unposted');
            Route::post('expenses/{expense}/post-gl', [\App\Http\Controllers\School\Finance\ExpenseController::class, 'postGl'])->name('expenses.post-gl');
        });

        Route::middleware(['school.management', 'module:reports'])->group(function () {
            $LC = \App\Http\Controllers\School\Finance\LedgerController::class;
            Route::get('finance/day-book', [$LC, 'dayBook'])->name('finance.day-book');
            Route::get('finance/due-report', [$LC, 'dueReport'])->name('finance.due-report');
            Route::post('finance/due-report/send-reminder', [$LC, 'sendDueReminder'])->name('finance.due-report.send-reminder');
            Route::get('finance/reports', [\App\Http\Controllers\School\Finance\ReportController::class, 'index'])->name('finance.reports.index');
        });

        // ── Accounting Ledger Module ──────────────────────────────
        Route::middleware(['school.management', 'module:fee'])->group(function () {
            $LT  = \App\Http\Controllers\School\Finance\LedgerTypeController::class;
            $LAC = \App\Http\Controllers\School\Finance\LedgerAccountController::class;
            $TC  = \App\Http\Controllers\School\Finance\TransactionController::class;

            // Ledger Types
            Route::get   ('finance/ledger-types',          [$LT, 'index'])  ->name('finance.ledger-types.index');
            Route::post  ('finance/ledger-types',          [$LT, 'store'])  ->name('finance.ledger-types.store');
            Route::put   ('finance/ledger-types/{ledgerType}', [$LT, 'update']) ->name('finance.ledger-types.update');
            Route::delete('finance/ledger-types/{ledgerType}', [$LT, 'destroy'])->name('finance.ledger-types.destroy');

            // Ledgers (Chart of Accounts)
            Route::get   ('finance/ledgers',               [$LAC, 'index'])  ->name('finance.ledgers.index');
            Route::post  ('finance/ledgers',               [$LAC, 'store'])  ->name('finance.ledgers.store');
            Route::get   ('finance/ledgers/{ledger}',      [$LAC, 'show'])   ->name('finance.ledgers.show');
            Route::put   ('finance/ledgers/{ledger}',      [$LAC, 'update']) ->name('finance.ledgers.update');
            Route::delete('finance/ledgers/{ledger}',      [$LAC, 'destroy'])->name('finance.ledgers.destroy');

            // Transactions (Journal Entries)
            Route::get   ('finance/transactions',              [$TC, 'index'])  ->name('finance.transactions.index');
            Route::get   ('finance/transactions/create',       [$TC, 'create']) ->name('finance.transactions.create');
            Route::post  ('finance/transactions',              [$TC, 'store'])  ->name('finance.transactions.store');
            Route::get   ('finance/transactions/{transaction}',        [$TC, 'show'])   ->name('finance.transactions.show');
            Route::get   ('finance/transactions/{transaction}/edit',   [$TC, 'edit'])   ->name('finance.transactions.edit');
            Route::put   ('finance/transactions/{transaction}',        [$TC, 'update']) ->name('finance.transactions.update');
            Route::delete('finance/transactions/{transaction}',        [$TC, 'destroy'])->name('finance.transactions.destroy');
            Route::post  ('finance/transactions/{transaction}/reverse', [$TC, 'reverse'])->name('finance.transactions.reverse');

            // Financial Statements
            $FSC = \App\Http\Controllers\School\Finance\FinancialStatementsController::class;
            Route::get('finance/statements/trial-balance', [$FSC, 'trialBalance'])->name('finance.statements.trial-balance');
            Route::get('finance/statements/profit-loss',   [$FSC, 'profitLoss'])  ->name('finance.statements.profit-loss');
            Route::get('finance/statements/balance-sheet', [$FSC, 'balanceSheet'])->name('finance.statements.balance-sheet');

            // Budget Management
            $BC = \App\Http\Controllers\School\Finance\BudgetController::class;
            Route::get   ('finance/budgets',          [$BC, 'index'])  ->name('finance.budgets.index');
            Route::post  ('finance/budgets',          [$BC, 'store'])  ->name('finance.budgets.store');
            Route::put   ('finance/budgets/{budget}', [$BC, 'update']) ->name('finance.budgets.update');
            Route::delete('finance/budgets/{budget}', [$BC, 'destroy'])->name('finance.budgets.destroy');

            // GL Auto-posting Config
            $GLC = \App\Http\Controllers\School\Finance\GlConfigController::class;
            Route::get ('finance/gl-config',                    [$GLC, 'show'])                 ->name('finance.gl-config.show');
            Route::post('finance/gl-config/update',             [$GLC, 'update'])               ->name('finance.gl-config.update');
            Route::post('finance/gl-config/category-mapping',  [$GLC, 'updateCategoryMapping'])->name('finance.gl-config.category-mapping');

            // Payment Methods (used across all transactions)
            $PMC = \App\Http\Controllers\School\Finance\PaymentMethodController::class;
            Route::get   ('finance/payment-methods',                          [$PMC, 'index'])       ->name('finance.payment-methods.index');
            Route::post  ('finance/payment-methods',                          [$PMC, 'store'])       ->name('finance.payment-methods.store');
            Route::put   ('finance/payment-methods/{paymentMethod}',          [$PMC, 'update'])      ->name('finance.payment-methods.update');
            Route::patch ('finance/payment-methods/{paymentMethod}/toggle',   [$PMC, 'toggleActive'])->name('finance.payment-methods.toggle');
            Route::delete('finance/payment-methods/{paymentMethod}',          [$PMC, 'destroy'])     ->name('finance.payment-methods.destroy');
        });

        Route::middleware(['school.management', 'module:settings'])->group(function () {
            // Number Formats Config
            $SC = \App\Http\Controllers\School\SchoolNumberConfigController::class;
            Route::get('settings/number-formats',  [$SC, 'show'])  ->name('settings.number-formats');
            Route::post('settings/number-formats', [$SC, 'update'])->name('settings.number-formats.update');
        });

        Route::middleware(['school.management', 'module:classes'])->group(function () {
            Route::resource('classes', \App\Http\Controllers\School\CourseClassController::class)->except(['create', 'show', 'edit']);
            Route::post('classes/reorder', [\App\Http\Controllers\School\CourseClassController::class, 'reorder'])->name('classes.reorder');
            Route::resource('sections', \App\Http\Controllers\School\SectionController::class)->except(['create', 'show', 'edit']);
            Route::post('sections/reorder', [\App\Http\Controllers\School\SectionController::class, 'reorder'])->name('sections.reorder');
            Route::resource('subjects', \App\Http\Controllers\School\SubjectController::class)->except(['create', 'show', 'edit']);
            Route::post('subjects/reorder', [\App\Http\Controllers\School\SubjectController::class, 'reorder'])->name('subjects.reorder');
            Route::resource('subject-types', \App\Http\Controllers\School\SubjectTypeController::class)->except(['create', 'show', 'edit']);
        });

        // Staff & HR
        Route::middleware(['school.management', 'module:staff'])->group(function () {
            // Moved from classes group
            Route::resource('departments', \App\Http\Controllers\School\DepartmentController::class)->except(['create', 'show', 'edit']);

            $DesigC = \App\Http\Controllers\School\DesignationController::class;
            Route::patch('designations/{designation}/toggle', [$DesigC, 'toggle'])->name('designations.toggle');
            Route::resource('designations', $DesigC)->except(['create', 'show', 'edit']);
            
            // Staff Directory & HR
            // Bulk QR exports — must come BEFORE Route::resource('staff', ...)
            // otherwise "qr-codes" gets caught by the {staff} parameter binding.
            Route::get('staff/qr-codes/excel', [\App\Http\Controllers\School\StaffController::class, 'exportQrCodesExcel'])->name('staff.qr-codes.excel');
            Route::get('staff/qr-codes/pdf',   [\App\Http\Controllers\School\StaffController::class, 'exportQrCodesPdf'])->name('staff.qr-codes.pdf');
            Route::get('staff/{staff}/salary', [\App\Http\Controllers\School\StaffController::class, 'salaryForm'])->name('staff.salary');
            Route::patch('staff/{staff}/salary', [\App\Http\Controllers\School\StaffController::class, 'updateSalary'])->name('staff.update-salary');
            Route::get('staff/{staff}/request-edit', [\App\Http\Controllers\School\StaffController::class, 'createRequest'])->name('staff.request-edit');
            Route::post('staff/{staff}/request-edit', [\App\Http\Controllers\School\StaffController::class, 'storeRequest'])->name('staff.request-edit.store');
            Route::resource('staff', \App\Http\Controllers\School\StaffController::class);
            
            // Staff Leave Management
            $LC = \App\Http\Controllers\School\LeaveController::class;
            Route::get('leaves',                  [$LC, 'index'])   ->name('leaves.index');
            Route::post('leaves',                 [$LC, 'store'])   ->name('leaves.store');
            Route::patch('leaves/{leave}/approve',[$LC, 'approve']) ->name('leaves.approve');
            Route::patch('leaves/{leave}/reject', [$LC, 'reject'])  ->name('leaves.reject');
            Route::patch('leaves/{leave}/revert', [$LC, 'revert'])  ->name('leaves.revert');

            // Leave Type Management
            $LTC = \App\Http\Controllers\School\LeaveTypeController::class;
            Route::get('leave-types',                         [$LTC, 'index'])   ->name('leave-types.index');
            Route::post('leave-types',                        [$LTC, 'store'])   ->name('leave-types.store');
            Route::put('leave-types/{leaveType}',             [$LTC, 'update'])  ->name('leave-types.update');
            Route::delete('leave-types/{leaveType}',          [$LTC, 'destroy']) ->name('leave-types.destroy');
            Route::patch('leave-types/{leaveType}/toggle',    [$LTC, 'toggle'])  ->name('leave-types.toggle');
            Route::post('leave-types/reorder',                [$LTC, 'reorder']) ->name('leave-types.reorder');

            // Staff Attendance — admin / principal / school_admin only.
            // Regular teachers must not be able to mark or view all staff attendance.
            $SAC = \App\Http\Controllers\School\StaffAttendanceController::class;
            Route::middleware('school.management:admin_only')->group(function () use ($SAC) {
                Route::get('staff-attendance',         [$SAC, 'index'])  ->name('staff-attendance.index');
                Route::post('staff-attendance',        [$SAC, 'store'])  ->name('staff-attendance.store');
                Route::get('staff-attendance/report',  [$SAC, 'report']) ->name('staff-attendance.report');
            });
        });

        Route::middleware(['school.management', 'module:payroll'])->group(function () {
            // Payroll
            $PC = \App\Http\Controllers\School\PayrollController::class;
            Route::get('payroll',                       [$PC, 'index'])    ->name('payroll.index');
            Route::post('payroll/generate',             [$PC, 'generate']) ->name('payroll.generate');
            Route::patch('payroll/{payroll}/mark-paid', [$PC, 'markPaid']) ->name('payroll.markPaid');
            Route::get('payroll/{payroll}/payslip',     [$PC, 'payslip'])  ->name('payroll.payslip');
            Route::post('payroll/{payroll}/post-gl',    [$PC, 'postGl'])   ->name('payroll.post-gl');
            Route::get('payroll/export',                [$PC, 'export'])   ->name('payroll.export');
        });

        Route::middleware(['school.management', 'module:settings'])->group(function () {
            // Custom Fields
            Route::resource('custom-fields', \App\Http\Controllers\School\CustomFieldController::class)->except(['create', 'show', 'edit']);
            Route::post('custom-fields/reorder', [\App\Http\Controllers\School\CustomFieldController::class, 'reorder'])->name('custom-fields.reorder');

            // Edit Requests Workflow
            $ERC = \App\Http\Controllers\School\EditRequestController::class;
            Route::get('edit-requests',              [$ERC, 'index'])  ->name('edit-requests.index');
            Route::get('edit-requests/{editRequest}',[$ERC, 'show'])   ->name('edit-requests.show');
            Route::post('edit-requests/{editRequest}/approve', [$ERC, 'approve'])->name('edit-requests.approve');
            Route::post('edit-requests/{editRequest}/reject',  [$ERC, 'reject']) ->name('edit-requests.reject');
            
            // Roles & Permissions (School-Level Mapping)
            Route::get('/roles-permissions',  [\App\Http\Controllers\Admin\RolePermissionController::class, 'index'])->name('roles.matrix');
            Route::post('/roles-permissions', [\App\Http\Controllers\Admin\RolePermissionController::class, 'update'])->name('roles.update');
            Route::post('/roles',             [\App\Http\Controllers\Admin\RolePermissionController::class, 'store'])->name('roles.store');
            Route::delete('/roles/{role}',    [\App\Http\Controllers\Admin\RolePermissionController::class, 'destroy'])->name('roles.destroy');
            Route::post('/users-permissions', [\App\Http\Controllers\Admin\RolePermissionController::class, 'updateUserPermissions'])->name('users.permissions.update');
        });

        Route::middleware(['school.management', 'module:classes'])->group(function () {
            // Class-Subject assignments
            Route::resource('class-subjects', \App\Http\Controllers\School\ClassSubjectController::class)->except(['create', 'show', 'edit']);
            Route::get('classes/{classId}/sections', [\App\Http\Controllers\School\ClassSubjectController::class, 'sectionsForClass'])->name('classes.sections');
        });

        Route::middleware(['school.management', 'module:schedule'])->group(function () {
            Route::resource('periods', \App\Http\Controllers\School\PeriodController::class)->except(['create', 'show', 'edit']);
            Route::get('timetable', [\App\Http\Controllers\School\TimetableController::class, 'index'])->name('timetable');
            Route::post('timetable/save', [\App\Http\Controllers\School\TimetableController::class, 'save'])->name('timetable.save');
            Route::post('timetable/generate', [\App\Http\Controllers\School\TimetableController::class, 'generate'])->name('timetable.generate');
            Route::post('timetable/reset', [\App\Http\Controllers\School\TimetableController::class, 'reset'])->name('timetable.reset');
        });

        Route::middleware(['school.management', 'module:communication'])->group(function () {
            Route::resource('holidays', \App\Http\Controllers\School\HolidayController::class)->except(['create', 'show', 'edit']);
        });

        // Examination Module
        Route::middleware(['module:exam'])->group(function () {
            Route::resource('exam-types', \App\Http\Controllers\School\ExamTypeController::class)->except(['create', 'show', 'edit']);
            Route::resource('exam-terms', \App\Http\Controllers\School\ExamTermController::class)->except(['create', 'show', 'edit']);
            Route::resource('grading-systems', \App\Http\Controllers\School\GradingSystemController::class)->except(['create', 'show', 'edit']);
            Route::resource('exam-assessments', \App\Http\Controllers\School\ExamAssessmentController::class)->except(['create', 'show', 'edit']);
            
            // Exam Schedules
            Route::get('exam-schedules-subjects', [\App\Http\Controllers\School\ExamScheduleController::class, 'subjects'])->name('exam-schedules.subjects');
            Route::post('exam-schedules/{examSchedule}/toggle-publish', [\App\Http\Controllers\School\ExamScheduleController::class, 'togglePublish'])->name('exam-schedules.toggle-publish');
            Route::resource('exam-schedules', \App\Http\Controllers\School\ExamScheduleController::class)->except(['create', 'show', 'edit']);

            // Admit Cards
            Route::get('admit-cards', [\App\Http\Controllers\School\AdmitCardController::class, 'index'])->name('exam-admit-cards.index');
            Route::post('admit-cards/generate', [\App\Http\Controllers\School\AdmitCardController::class, 'generate'])->name('exam-admit-cards.generate');
            Route::get('admit-cards/print', [\App\Http\Controllers\School\AdmitCardController::class, 'print'])->name('exam-admit-cards.print');

            // Report Cards
            $RC = \App\Http\Controllers\School\ReportCardController::class;
            Route::get('report-cards',           [$RC, 'index'])    ->name('report-cards.index');
            Route::post('report-cards/generate', [$RC, 'generate']) ->name('report-cards.generate');
            Route::get('report-cards/print',     [$RC, 'print'])    ->name('report-cards.print');

            // Exam Results (class-wise result sheet with ranks)
            $ER = \App\Http\Controllers\School\ExamResultController::class;
            Route::get('exam-results',       [$ER, 'index']) ->name('exam-results.index');
            Route::get('exam-results/data',  [$ER, 'data'])  ->name('exam-results.data');
            Route::get('exam-results/print', [$ER, 'print']) ->name('exam-results.print');

            // Mark Summary Report (assessment-item level breakdown across all students)
            $MS = \App\Http\Controllers\School\ExamMarkSummaryController::class;
            Route::get('exam-mark-summary',       [$MS, 'index']) ->name('exam-mark-summary.index');
            Route::get('exam-mark-summary/data',  [$MS, 'data'])  ->name('exam-mark-summary.data');
            Route::get('exam-mark-summary/print', [$MS, 'print']) ->name('exam-mark-summary.print');

            // Marks & Grades
            Route::get('exam-marks/students', [\App\Http\Controllers\School\ExamMarkController::class, 'students'])->name('exam-marks.students');
            Route::resource('exam-marks', \App\Http\Controllers\School\ExamMarkController::class)->except(['create', 'show', 'edit']);

            // AI Question Papers
            $QP = \App\Http\Controllers\School\QuestionPaperController::class;
            Route::get('question-papers/subjects', [$QP, 'getSubjects'])->name('question-papers.subjects');
            Route::get('question-papers/topics', [$QP, 'getTopics'])->name('question-papers.topics');
            Route::post('question-papers/generate', [$QP, 'generate'])->name('question-papers.generate');
            Route::post('question-papers/regenerate-section', [$QP, 'regenerateSection'])->name('question-papers.regenerate-section');
            Route::get('question-papers/{questionPaper}/pdf', [$QP, 'downloadPdf'])->name('question-papers.pdf');
            Route::resource('question-papers', $QP)->except(['edit', 'update']);
        });


        // Incharge Assignments (drag-and-drop)
        Route::middleware(['module:staff'])->group(function () {
            $IC = \App\Http\Controllers\School\InchargeController::class;
            Route::get('incharge', [$IC, 'index'])->name('incharge.index');
            Route::post('incharge/class/{class}',          [$IC, 'assignClassIncharge'])  ->name('incharge.assign.class');
            Route::post('incharge/section/{section}',      [$IC, 'assignSectionIncharge'])->name('incharge.assign.section');
            Route::post('incharge/subject/{classSubject}', [$IC, 'assignSubjectIncharge'])->name('incharge.assign.subject');
        });

        // Academic Resources
        Route::middleware(['module:academic'])->group(function () {
            Route::prefix('academic')->name('academic.')->group(function () {
                // Student Diary
                Route::resource('diary', \App\Http\Controllers\School\Academic\StudentDiaryController::class)->only(['index', 'create', 'store', 'destroy']);
                
                // Assignments
                Route::post('assignments/{assignment}/submit', [\App\Http\Controllers\School\Academic\AssignmentController::class, 'submit'])->name('assignments.submit');
                Route::post('assignments/{assignment}/grade/{student}', [\App\Http\Controllers\School\Academic\AssignmentController::class, 'gradeStudent'])->name('assignments.grade-student');
                Route::post('assignments/{assignment}/bulk-grade', [\App\Http\Controllers\School\Academic\AssignmentController::class, 'bulkGrade'])->name('assignments.bulk-grade');
                Route::post('assignments/{assignment}/close', [\App\Http\Controllers\School\Academic\AssignmentController::class, 'close'])->name('assignments.close');
                Route::post('assignments/{assignment}/duplicate', [\App\Http\Controllers\School\Academic\AssignmentController::class, 'duplicate'])->name('assignments.duplicate');
                Route::resource('assignments', \App\Http\Controllers\School\Academic\AssignmentController::class);

                // Syllabus
                Route::get('syllabus/create', [\App\Http\Controllers\School\Academic\SyllabusController::class, 'create'])->name('syllabus.create');
                Route::get('syllabus', [\App\Http\Controllers\School\Academic\SyllabusController::class, 'index'])->name('syllabus.index');
                Route::post('syllabus/topics', [\App\Http\Controllers\School\Academic\SyllabusController::class, 'storeTopic'])->name('syllabus.store-topic');
                Route::put('syllabus/topics/{topic}', [\App\Http\Controllers\School\Academic\SyllabusController::class, 'updateTopic'])->name('syllabus.update-topic');
                Route::delete('syllabus/topics/{topic}', [\App\Http\Controllers\School\Academic\SyllabusController::class, 'destroyTopic'])->name('syllabus.destroy-topic');
                Route::post('syllabus/topics/{topic}/status', [\App\Http\Controllers\School\Academic\SyllabusController::class, 'updateStatus'])->name('syllabus.update-status');
                Route::post('syllabus/reset-progress', [\App\Http\Controllers\School\Academic\SyllabusController::class, 'resetProgress'])->name('syllabus.reset-progress');
                Route::get('syllabus/export', [\App\Http\Controllers\School\Academic\SyllabusController::class, 'export'])->name('syllabus.export');

                // Resources (Online Classes & Learning Materials)
                Route::get('resources/material/create', [\App\Http\Controllers\School\Academic\ResourceController::class, 'createMaterial'])->name('resources.create-material');
                Route::get('resources', [\App\Http\Controllers\School\Academic\ResourceController::class, 'index'])->name('resources.index');
                Route::post('resources/online-class', [\App\Http\Controllers\School\Academic\ResourceController::class, 'storeOnlineClass'])->name('resources.store-online-class');
                Route::post('resources/material', [\App\Http\Controllers\School\Academic\ResourceController::class, 'storeMaterial'])->name('resources.store-material');
                Route::delete('resources/online-class/{onlineClass}', [\App\Http\Controllers\School\Academic\ResourceController::class, 'destroyOnlineClass'])->name('resources.destroy-online-class');
                Route::delete('resources/material/{material}', [\App\Http\Controllers\School\Academic\ResourceController::class, 'destroyMaterial'])->name('resources.destroy-material');
                Route::post('resources/online-class/{onlineClass}/recording', [\App\Http\Controllers\School\Academic\ResourceController::class, 'addRecording'])->name('resources.add-recording');
                Route::patch('resources/material/{material}/publish', [\App\Http\Controllers\School\Academic\ResourceController::class, 'togglePublish'])->name('resources.toggle-publish');
                Route::get('resources/material/{material}/download', [\App\Http\Controllers\School\Academic\ResourceController::class, 'trackDownload'])->name('resources.download-material');

                // Student/Parent portal (read-only views of diary, assignments, materials)
                Route::get('assignments/my', [\App\Http\Controllers\School\Academic\AssignmentController::class, 'studentIndex'])->name('assignments.student-index');
                Route::get('diary/my', [\App\Http\Controllers\School\Academic\StudentDiaryController::class, 'studentIndex'])->name('diary.student');
                Route::get('resources/my', [\App\Http\Controllers\School\Academic\ResourceController::class, 'studentIndex'])->name('resources.student');

                // P4: Diary upgrades — calendar, CSV export, read receipts, homework completion
                Route::get('diary/calendar', [\App\Http\Controllers\School\Academic\StudentDiaryController::class, 'calendar'])->name('diary.calendar');
                Route::get('diary/export', [\App\Http\Controllers\School\Academic\StudentDiaryController::class, 'export'])->name('diary.export');
                Route::post('diary/{diary}/read', [\App\Http\Controllers\School\Academic\StudentDiaryController::class, 'markRead'])->name('diary.mark-read');
                Route::post('diary/{diary}/complete', [\App\Http\Controllers\School\Academic\StudentDiaryController::class, 'toggleCompletion'])->name('diary.toggle-completion');

                // P5: Academic Dashboard + Unified Calendar + Health Score
                Route::get('dashboard', [\App\Http\Controllers\School\Academic\AcademicDashboardController::class, 'index'])->name('dashboard');
                Route::get('calendar', [\App\Http\Controllers\School\Academic\AcademicDashboardController::class, 'calendar'])->name('calendar');
                Route::get('health-score', [\App\Http\Controllers\School\Academic\AcademicDashboardController::class, 'healthScore'])->name('health-score');

                // Book List
                Route::get('book-list/create', [\App\Http\Controllers\School\Academic\BookListController::class, 'create'])->name('book-list.create');
                Route::get('book-list/export', [\App\Http\Controllers\School\Academic\BookListController::class, 'export'])->name('book-list.export');
                Route::get('book-list/subjects-for-class/{classId}', [\App\Http\Controllers\School\Academic\BookListController::class, 'subjectsForClass'])->name('book-list.subjects-for-class');
                Route::resource('book-list', \App\Http\Controllers\School\Academic\BookListController::class)->only(['index', 'store', 'destroy']);
            });
        });

        // Communication Configs & Templates
        Route::middleware(['school.management', 'module:communication'])->group(function () {
            Route::prefix('communication')->name('communication.')->group(function () {
                // Dashboard, Logs, Analytics, Emergency, Email, Scheduled Queue
                $CDC = \App\Http\Controllers\School\CommunicationDashboardController::class;
                Route::get('dashboard',         [$CDC, 'index'])->name('dashboard');
                Route::get('logs',              [$CDC, 'logs'])->name('logs');
                Route::get('analytics',         [$CDC, 'analytics'])->name('analytics');
                Route::get('emergency',         [$CDC, 'emergencyForm'])->name('emergency');
                Route::post('emergency',        [$CDC, 'emergencyBroadcast'])->name('emergency.send');
                Route::get('email-templates',   [$CDC, 'emailTemplates'])->name('email-templates');
                Route::post('email-templates',  [$CDC, 'storeEmailTemplate'])->name('email-templates.store');
                Route::post('email/send',       [$CDC, 'sendEmail'])->name('email.send');
                Route::get('scheduled',         [$CDC, 'scheduledQueue'])->name('scheduled');
                Route::delete('scheduled/{announcement}', [$CDC, 'cancelScheduled'])->name('scheduled.cancel');
                Route::post('scheduled/{announcement}/retry', [$CDC, 'retryBroadcast'])->name('scheduled.retry');
                Route::get('parent-history',    [$CDC, 'parentHistory'])->name('parent-history');

                // Configs
                Route::get('config/sms', [\App\Http\Controllers\School\CommunicationConfigController::class, 'smsConfig'])->name('config.sms');
                Route::post('config/sms', [\App\Http\Controllers\School\CommunicationConfigController::class, 'updateSmsConfig'])->name('config.sms.update');
                Route::post('config/sms/send-test', [\App\Http\Controllers\School\CommunicationConfigController::class, 'sendTestSms'])->name('config.sms.send-test');
                Route::get('config/whatsapp', [\App\Http\Controllers\School\CommunicationConfigController::class, 'whatsappConfig'])->name('config.whatsapp');
                Route::post('config/whatsapp', [\App\Http\Controllers\School\CommunicationConfigController::class, 'updateWhatsappConfig'])->name('config.whatsapp.update');
                Route::post('config/whatsapp/send-test', [\App\Http\Controllers\School\CommunicationConfigController::class, 'sendTestWhatsapp'])->name('config.whatsapp.send-test');
                Route::get('config/notifications', [\App\Http\Controllers\School\CommunicationConfigController::class, 'notificationConfig'])->name('config.notifications');
                Route::post('config/notifications', [\App\Http\Controllers\School\CommunicationConfigController::class, 'updateNotificationConfig'])->name('config.notifications.update');
                Route::get('config/voice', [\App\Http\Controllers\School\CommunicationConfigController::class, 'voiceConfig'])->name('config.voice');
                Route::post('config/voice', [\App\Http\Controllers\School\CommunicationConfigController::class, 'updateVoiceConfig'])->name('config.voice.update');
                Route::post('config/voice/send-test', [\App\Http\Controllers\School\CommunicationConfigController::class, 'sendTestVoice'])->name('config.voice.send-test');

                // Templates
                Route::get('templates/{type}', [\App\Http\Controllers\School\CommunicationTemplateController::class, 'index'])->name('templates.index');
                Route::post('templates', [\App\Http\Controllers\School\CommunicationTemplateController::class, 'store'])->name('templates.store');
                Route::put('templates/{template}', [\App\Http\Controllers\School\CommunicationTemplateController::class, 'update'])->name('templates.update');
                Route::delete('templates/{template}', [\App\Http\Controllers\School\CommunicationTemplateController::class, 'destroy'])->name('templates.destroy');
                Route::patch('templates/{template}/toggle', [\App\Http\Controllers\School\CommunicationTemplateController::class, 'toggle'])->name('templates.toggle');

                // Voice Announcements
                // Rate-limited: 20 broadcasts per minute — each broadcast fans out to potentially thousands of users
                Route::get('announcements', [\App\Http\Controllers\School\AnnouncementController::class, 'index'])->name('announcements.index');
                Route::post('announcements', [\App\Http\Controllers\School\AnnouncementController::class, 'store'])->name('announcements.store')->middleware('throttle:20,1');
                Route::post('announcements/{announcement}/broadcast', [\App\Http\Controllers\School\AnnouncementController::class, 'broadcast'])->name('announcements.broadcast')->middleware('throttle:20,1');

                // Social Buzz
                Route::prefix('social-buzz')->name('social-buzz.')->group(function () {
                    $SBC = \App\Http\Controllers\School\SocialFeedController::class;
                    Route::get('/',                  [$SBC, 'index'])          ->name('index');
                    Route::post('/',                 [$SBC, 'store'])          ->name('store');
                    Route::put('/{post}',            [$SBC, 'update'])         ->name('update');
                    Route::delete('/{post}',         [$SBC, 'destroy'])        ->name('destroy');
                    Route::post('/{post}/react',     [$SBC, 'toggleReaction']) ->name('react.toggle');
                    Route::post('/{post}/comment',   [$SBC, 'addComment'])     ->name('comment.add');
                    Route::post('/{post}/pin',       [$SBC, 'togglePin'])      ->name('pin.toggle');
                    Route::post('/{post}/bookmark',  [$SBC, 'toggleBookmark']) ->name('bookmark.toggle');
                });
            });
        });
        // Hostel Module
        Route::middleware(['module:hostel'])->group(function () {
            Route::group(['prefix' => 'hostel', 'as' => 'hostel.'], function () {
                // Dashboard
                Route::get('/', [\App\Http\Controllers\School\Hostel\DashboardController::class, 'index'])->name('dashboard');
                Route::get('reports/fee-defaulters', [\App\Http\Controllers\School\Hostel\DashboardController::class, 'feeDefaulters'])->name('reports.fee-defaulters');
                
                // Hostels Settings
                Route::resource('hostels', \App\Http\Controllers\School\Hostel\HostelController::class)->except(['create', 'show', 'edit']);
                
                // Rooms & Beds
                Route::get('rooms', [\App\Http\Controllers\School\Hostel\RoomController::class, 'index'])->name('rooms.index');
                Route::post('rooms', [\App\Http\Controllers\School\Hostel\RoomController::class, 'store'])->name('rooms.store');
                Route::put('rooms/{room}', [\App\Http\Controllers\School\Hostel\RoomController::class, 'update'])->name('rooms.update');
                Route::delete('rooms/{room}', [\App\Http\Controllers\School\Hostel\RoomController::class, 'destroy'])->name('rooms.destroy');
                
                // Allocations (Student Registration)
                Route::get('allocations', [\App\Http\Controllers\School\Hostel\AllocationController::class, 'index'])->name('allocations.index');
                Route::get('allocations/students-by-class', [\App\Http\Controllers\School\Hostel\AllocationController::class, 'studentsByClass'])->name('allocations.students-by-class');
                Route::post('allocations', [\App\Http\Controllers\School\Hostel\AllocationController::class, 'store'])->name('allocations.store');
                Route::put('allocations/{allocation}', [\App\Http\Controllers\School\Hostel\AllocationController::class, 'update'])->name('allocations.update');
                Route::post('allocations/{allocation}/vacate', [\App\Http\Controllers\School\Hostel\AllocationController::class, 'vacate'])->name('allocations.vacate');
                
                // Visitors
                Route::get('visitors', [\App\Http\Controllers\School\Hostel\VisitorController::class, 'index'])->name('visitors.index');
                Route::post('visitors', [\App\Http\Controllers\School\Hostel\VisitorController::class, 'store'])->name('visitors.store');
                Route::put('visitors/{visitor}', [\App\Http\Controllers\School\Hostel\VisitorController::class, 'update'])->name('visitors.update');
                Route::post('visitors/{visitor}/photo', [\App\Http\Controllers\School\Hostel\VisitorController::class, 'uploadPhoto'])->name('visitors.photo');
                
                // Gate Pass / Student In-Out
                Route::get('gate-passes', [\App\Http\Controllers\School\Hostel\GatePassController::class, 'index'])->name('gate-passes.index');
                Route::post('gate-passes', [\App\Http\Controllers\School\Hostel\GatePassController::class, 'store'])->name('gate-passes.store');
                Route::put('gate-passes/{gatePass}', [\App\Http\Controllers\School\Hostel\GatePassController::class, 'update'])->name('gate-passes.update');
                Route::patch('gate-passes/{gatePass}/status', [\App\Http\Controllers\School\Hostel\GatePassController::class, 'updateStatus'])->name('gate-passes.status.update');
                Route::delete('gate-passes/{gatePass}', [\App\Http\Controllers\School\Hostel\GatePassController::class, 'destroy'])->name('gate-passes.destroy');
                Route::post('gate-passes/{gatePass}/send-otp',   [\App\Http\Controllers\School\Hostel\GatePassController::class, 'sendParentOtp'])  ->name('gate-passes.send-otp');
                Route::post('gate-passes/{gatePass}/verify-otp', [\App\Http\Controllers\School\Hostel\GatePassController::class, 'verifyParentOtp'])->name('gate-passes.verify-otp');
                Route::post('gate-passes/{gatePass}/photo', [\App\Http\Controllers\School\Hostel\GatePassController::class, 'uploadPhoto'])->name('gate-passes.photo');

                // Student Self-Service: Gate Pass requests (student-facing)
                $SGP = \App\Http\Controllers\School\Hostel\StudentGatePassController::class;
                Route::get('my-gate-passes',                          [$SGP, 'index'])  ->name('my-gate-passes.index');
                Route::post('my-gate-passes',                         [$SGP, 'store'])  ->name('my-gate-passes.store');
                Route::patch('my-gate-passes/{gatePass}/cancel',      [$SGP, 'cancel']) ->name('my-gate-passes.cancel');

                // Allocations: Transfer
                Route::post('allocations/{allocation}/transfer', [\App\Http\Controllers\School\Hostel\AllocationController::class, 'transfer'])->name('allocations.transfer');

                // Mess Management
                Route::get('mess', [\App\Http\Controllers\School\Hostel\MessController::class, 'index'])->name('mess.index');
                Route::post('mess/menu', [\App\Http\Controllers\School\Hostel\MessController::class, 'storeMenu'])->name('mess.menu.store');
                Route::delete('mess/menu/{menu}', [\App\Http\Controllers\School\Hostel\MessController::class, 'destroyMenu'])->name('mess.menu.destroy');
                Route::get('mess/meal-report', [\App\Http\Controllers\School\Hostel\MessController::class, 'mealReport'])->name('mess.meal-report');

                // Roll Call / Bed Check
                $RCC = \App\Http\Controllers\School\Hostel\RollCallController::class;
                Route::get('roll-call',        [$RCC, 'index']) ->name('roll-call.index');
                Route::post('roll-call',       [$RCC, 'store']) ->name('roll-call.store');
                Route::get('roll-call/report', [$RCC, 'report'])->name('roll-call.report');

                // Complaints / Maintenance
                $CC = \App\Http\Controllers\School\Hostel\ComplaintController::class;
                Route::get('complaints',              [$CC, 'index'])  ->name('complaints.index');
                Route::post('complaints',             [$CC, 'store'])  ->name('complaints.store');
                Route::put('complaints/{complaint}',  [$CC, 'update']) ->name('complaints.update');
                Route::delete('complaints/{complaint}', [$CC, 'destroy'])->name('complaints.destroy');

                // Hostel Fee Collection (standalone — no link to Finance FeePayment)
                Route::middleware(['permission:view_hostel'])->group(function () {
                    $HFC = \App\Http\Controllers\School\Hostel\HostelFeeCollectionController::class;
                    Route::get('fees',                              [$HFC, 'index'])->name('fees.index');
                    Route::get('fees/receipts/{payment}/receipt',   [$HFC, 'receipt'])->name('fees.receipt');
                    Route::get('fees/{allocation}',                 [$HFC, 'show'])->name('fees.show');

                    Route::middleware(['permission:collect_hostel_fee'])->group(function () use ($HFC) {
                        Route::post('fees/batch-post-gl',                [$HFC, 'batchPostGl'])->name('fees.batch-post-gl');
                        Route::post('fees/{allocation}/collect',         [$HFC, 'store'])->name('fees.store');
                        Route::delete('fees/receipts/{payment}',         [$HFC, 'destroy'])->name('fees.receipt.destroy');
                    });
                });
            });
        });

        // Houses / School House System
        Route::middleware(['module:houses'])->group(function () {
            $HC  = \App\Http\Controllers\School\Houses\HouseController::class;
            $HSC = \App\Http\Controllers\School\Houses\HouseStudentController::class;
            $HPC = \App\Http\Controllers\School\Houses\HousePointController::class;
            $HLC = \App\Http\Controllers\School\Houses\HouseLeaderboardController::class;

            Route::group(['prefix' => 'houses', 'as' => 'houses.'], function () use ($HC, $HSC, $HPC, $HLC) {
                Route::get('/',                                    [$HC,  'index'])   ->name('index');
                Route::post('/',                                   [$HC,  'store'])   ->name('store');
                Route::get('/leaderboard',                         [$HLC, 'index'])   ->name('leaderboard');
                Route::get('/{house}',                             [$HC,  'show'])    ->name('show');
                Route::put('/{house}',                             [$HC,  'update'])  ->name('update');
                Route::delete('/{house}',                          [$HC,  'destroy']) ->name('destroy');

                Route::post('/{house}/students',                   [$HSC, 'store'])   ->name('students.store');
                Route::delete('/{house}/students/{student}',       [$HSC, 'destroy']) ->name('students.destroy');

                Route::post('/{house}/points',                     [$HPC, 'store'])   ->name('points.store');
                Route::delete('/{house}/points/{point}',           [$HPC, 'destroy']) ->name('points.destroy');
            });
        });

        // PTM (Parent-Teacher Meeting) Scheduling
        Route::middleware(['module:staff'])->group(function () {
            $PTMC = \App\Http\Controllers\School\PtmController::class;
            Route::group(['prefix' => 'ptm', 'as' => 'ptm.'], function () use ($PTMC) {
                Route::get('/',                                 [$PTMC, 'index'])          ->name('index');
                Route::post('/',                               [$PTMC, 'store'])          ->name('store');
                Route::patch('{ptmSession}/status',            [$PTMC, 'updateStatus'])   ->name('session.status');
                Route::get('{ptmSession}',                     [$PTMC, 'sessionDetail'])  ->name('session.detail');
                Route::patch('bookings/{ptmBooking}/notes',    [$PTMC, 'addNotes'])       ->name('bookings.notes');
                Route::post('slots/{ptmSlot}/book',            [$PTMC, 'book'])           ->name('slots.book');
                Route::patch('bookings/{ptmBooking}/cancel',   [$PTMC, 'cancelBooking'])  ->name('bookings.cancel');
                Route::get('parent/view',                      [$PTMC, 'parentView'])     ->name('parent.view');
            });
        });

        // Disciplinary / Behavior Tracking
        Route::middleware(['school.management:admin_only', 'module:students'])->group(function () {
            $DC = \App\Http\Controllers\School\DisciplinaryController::class;
            Route::get('disciplinary',                                          [$DC, 'index'])           ->name('disciplinary.index');
            Route::post('disciplinary',                                          [$DC, 'store'])           ->name('disciplinary.store');
            Route::post('disciplinary/bulk',                                    [$DC, 'storeBulk'])       ->name('disciplinary.store_bulk');
            Route::put('disciplinary/{disciplinaryRecord}',                     [$DC, 'update'])          ->name('disciplinary.update');
            Route::delete('disciplinary/{disciplinaryRecord}',                  [$DC, 'destroy'])         ->name('disciplinary.destroy');
            Route::get('students/{student}/disciplinary',                       [$DC, 'studentHistory'])  ->name('students.disciplinary.history');
            // Category management
            Route::post('disciplinary/categories',                              [$DC, 'storeCategory'])   ->name('disciplinary.categories.store');
            Route::put('disciplinary/categories/{disciplinaryCategory}',        [$DC, 'updateCategory'])  ->name('disciplinary.categories.update');
            Route::delete('disciplinary/categories/{disciplinaryCategory}',     [$DC, 'destroyCategory']) ->name('disciplinary.categories.destroy');
        });

        // Advanced Analytics Dashboard
        Route::middleware(['school.management:admin_only'])->group(function () {
            Route::get('analytics', [\App\Http\Controllers\School\AnalyticsDashboardController::class, 'index'])->name('analytics.dashboard');
        });

        // Inventory / Asset Management
        Route::middleware(['school.management:admin_only'])->group(function () {
            $INV = \App\Http\Controllers\School\InventoryController::class;
            $SUP = \App\Http\Controllers\School\SupplierController::class;
            $ISC = \App\Http\Controllers\School\ItemStoreController::class;

            Route::get('inventory',                                    [$INV, 'index'])              ->name('inventory.index');
            Route::post('inventory',                                   [$INV, 'store'])              ->name('inventory.store');
            Route::put('inventory/{asset}',                            [$INV, 'update'])             ->name('inventory.update');
            Route::post('inventory/{asset}/assign',                    [$INV, 'assign'])             ->name('inventory.assign');
            Route::patch('inventory/{asset}/return',                   [$INV, 'returnAsset'])        ->name('inventory.return');
            Route::post('inventory/{asset}/maintenance',               [$INV, 'maintenance'])        ->name('inventory.maintenance');
            Route::patch('inventory/maintenance/{record}/resolve',     [$INV, 'resolveMaintenance']) ->name('inventory.maintenance.resolve');
            Route::patch('inventory/maintenance/{record}/progress',    [$INV, 'markInProgress'])     ->name('inventory.maintenance.progress');
            Route::patch('inventory/{asset}/dispose',                  [$INV, 'dispose'])            ->name('inventory.dispose');
            // Static routes before {asset} to avoid route collision
            Route::get('inventory/reports',                            [$INV, 'reports'])            ->name('inventory.reports');
            Route::get('inventory/export',                             [$INV, 'export'])             ->name('inventory.export');
            Route::get('inventory/import-template',                    [$INV, 'importTemplate'])     ->name('inventory.import-template');
            Route::post('inventory/import',                            [$INV, 'import'])             ->name('inventory.import');
            Route::post('inventory/categories',                        [$INV, 'storeCategory'])      ->name('inventory.categories.store');
            Route::put('inventory/categories/{category}',              [$INV, 'updateCategory'])     ->name('inventory.categories.update');
            Route::delete('inventory/categories/{category}',           [$INV, 'destroyCategory'])    ->name('inventory.categories.destroy');
            // Asset detail (must come after static /inventory/* routes)
            Route::get('inventory/{asset}',                            [$INV, 'show'])               ->name('inventory.show');

            // Suppliers
            Route::get('inventory-suppliers',                          [$SUP, 'index'])              ->name('inventory.suppliers.index');
            Route::post('inventory-suppliers',                         [$SUP, 'store'])              ->name('inventory.suppliers.store');
            Route::put('inventory-suppliers/{supplier}',               [$SUP, 'update'])             ->name('inventory.suppliers.update');
            Route::delete('inventory-suppliers/{supplier}',            [$SUP, 'destroy'])            ->name('inventory.suppliers.destroy');

            // Item Stores — static before parameterised
            Route::get('inventory-stores',                             [$ISC, 'index'])              ->name('inventory.stores.index');
            Route::post('inventory-stores',                            [$ISC, 'storeStore'])         ->name('inventory.stores.store');
            Route::put('inventory-stores/items/{item}',                [$ISC, 'updateItem'])         ->name('inventory.stores.items.update');
            Route::delete('inventory-stores/items/{item}',             [$ISC, 'destroyItem'])        ->name('inventory.stores.items.destroy');
            Route::post('inventory-stores/items/{item}/transaction',   [$ISC, 'transaction'])        ->name('inventory.stores.items.transaction');
            Route::get('inventory-stores/{store}',                     [$ISC, 'show'])               ->name('inventory.stores.show');
            Route::put('inventory-stores/{store}',                     [$ISC, 'updateStore'])        ->name('inventory.stores.update');
            Route::delete('inventory-stores/{store}',                  [$ISC, 'destroyStore'])       ->name('inventory.stores.destroy');
            Route::post('inventory-stores/{store}/items',              [$ISC, 'storeItem'])          ->name('inventory.stores.items.store');
        });

        // Staff History / Promotion / Transfer
        Route::middleware(['school.management:admin_only'])->group(function () {
            $SH = \App\Http\Controllers\School\StaffHistoryController::class;
            Route::get('staff-history',                     [$SH, 'indexAll']) ->name('staff.history.all');
            Route::post('staff/{staff}/history',            [$SH, 'store'])    ->name('staff.history.store');
        });
        Route::middleware(['school.management'])->group(function () {
            $SH = \App\Http\Controllers\School\StaffHistoryController::class;
            Route::get('staff/{staff}/history',             [$SH, 'show'])     ->name('staff.history.show');
        });

        // Alumni Module
        Route::middleware(['school.management:admin_only'])->group(function () {
            $AL = \App\Http\Controllers\School\AlumniController::class;
            Route::get('alumni',                    [$AL, 'index'])         ->name('alumni.index');
            Route::post('alumni/graduate',          [$AL, 'graduate'])      ->name('alumni.graduate');
            Route::put('alumni/{alumnus}',          [$AL, 'update'])        ->name('alumni.update');
            Route::delete('alumni/{alumnus}',       [$AL, 'destroy'])       ->name('alumni.destroy');
            Route::get('alumni/search-students',    [$AL, 'searchStudents'])->name('alumni.search-students');
        });

        // Online Quiz Module
        Route::middleware(['module:exam'])->group(function () {
            $QC = \App\Http\Controllers\School\Quiz\OnlineQuizController::class;
            Route::group(['prefix' => 'quiz', 'as' => 'quiz.'], function () use ($QC) {
                Route::get('/',                                 [$QC, 'index'])     ->name('index');
                Route::get('create',                           [$QC, 'create'])    ->name('create');
                Route::post('/',                               [$QC, 'store'])     ->name('store');
                Route::get('{quiz}/edit',                      [$QC, 'edit'])      ->name('edit');
                Route::put('{quiz}',                           [$QC, 'update'])    ->name('update');
                Route::delete('{quiz}',                        [$QC, 'destroy'])   ->name('destroy');
                Route::get('{quiz}/results',                   [$QC, 'results'])   ->name('results');
                Route::get('my-quizzes',                       [$QC, 'myQuizzes']) ->name('my-quizzes');
                Route::get('{quiz}/take',                      [$QC, 'take'])      ->name('take');
                Route::post('{quiz}/submit',                   [$QC, 'submit'])    ->name('submit');
                Route::get('{quiz}/my-result/{attempt}',       [$QC, 'myResult'])  ->name('my-result');
            });
        });

        // Library Module
        Route::middleware(['school.management', 'module:library'])->group(function () {
            $LC = \App\Http\Controllers\School\Library\LibraryController::class;
            Route::group(['prefix' => 'library', 'as' => 'library.'], function () use ($LC) {
                Route::get('/',                             [$LC, 'dashboard'])    ->name('dashboard');
                Route::get('books',                         [$LC, 'books'])        ->name('books.index');
                Route::post('books',                        [$LC, 'storeBook'])    ->name('books.store');
                Route::put('books/{book}',                  [$LC, 'updateBook'])   ->name('books.update');
                Route::delete('books/{book}',               [$LC, 'destroyBook'])  ->name('books.destroy');
                Route::get('issues',                        [$LC, 'issues'])       ->name('issues.index');
                Route::post('issues',                       [$LC, 'issueBook'])    ->name('issues.store');
                Route::patch('issues/{issue}/return',       [$LC, 'returnBook'])   ->name('issues.return');
                Route::patch('issues/{issue}/fine-paid',    [$LC, 'markFinePaid']) ->name('issues.fine-paid');
                Route::get('settings',                      [$LC, 'settings'])     ->name('settings');
                Route::post('settings',                     [$LC, 'updateSettings'])->name('settings.update');
            });
        });

        // Transport Module — standard permission-based outer guard
        Route::middleware(['school.management', 'permission:view_transport'])->group(function () {
            Route::group(['prefix' => 'transport', 'as' => 'transport.'], function () {
                
                // Dashboard
                $TDC = \App\Http\Controllers\School\Transport\TransportDashboardController::class;
                Route::get('/', [$TDC, 'index'])->name('dashboard');
                Route::get('reports/route-report', [$TDC, 'routeReport'])->name('reports.route');
                Route::get('reports/fee-defaulters', [$TDC, 'feeDefaulters'])->name('reports.fee-defaulters');
                Route::get('parent-view', [$TDC, 'parentView'])->name('parent-view');

                // Attendance (Bus Roll Call)
                $TAC = \App\Http\Controllers\School\Transport\TransportAttendanceController::class;
                Route::get('attendance', [$TAC, 'index'])->name('attendance.index');
                Route::get('attendance/students', [$TAC, 'students'])->name('attendance.students');
                Route::post('attendance', [$TAC, 'store'])->name('attendance.store');
                Route::get('attendance/report', [$TAC, 'report'])->name('attendance.report');

                // Routes & Stops
                Route::middleware(['permission:view_transport_routes'])->group(function() {
                    $RC = \App\Http\Controllers\School\Transport\RouteController::class;
                    Route::get('routes',                [$RC, 'index'])->name('routes.index');
                    Route::get('routes/{route}/stops',  [$RC, 'stops'])->name('routes.stops');
                    
                    Route::middleware(['permission:create_transport_routes'])->group(function() use ($RC) {
                        Route::post('routes',           [$RC, 'store'])->name('routes.store');
                        Route::post('stops',            [$RC, 'storeStop'])->name('stops.store');
                        Route::post('stops/reorder',    [$RC, 'reorderStops'])->name('stops.reorder');
                    });
                    
                    Route::middleware(['permission:edit_transport_routes'])->group(function() use ($RC) {
                        Route::put('routes/{route}',    [$RC, 'update'])->name('routes.update');
                        Route::put('stops/{stop}',      [$RC, 'updateStop'])->name('stops.update');
                    });
                    
                    Route::middleware(['permission:delete_transport_routes'])->group(function() use ($RC) {
                        Route::delete('routes/{route}', [$RC, 'destroy'])->name('routes.destroy');
                        Route::delete('stops/{stop}',   [$RC, 'destroyStop'])->name('stops.destroy');
                    });
                });

                // Vehicles
                Route::middleware(['permission:view_transport_vehicles'])->group(function() {
                    $VC = \App\Http\Controllers\School\Transport\VehicleController::class;
                    Route::get('vehicles',              [$VC, 'index'])->name('vehicles.index');
                    
                    Route::middleware(['permission:create_transport_vehicles'])->group(function() use ($VC) {
                        Route::post('vehicles',         [$VC, 'store'])->name('vehicles.store');
                    });
                    
                    Route::middleware(['permission:edit_transport_vehicles'])->group(function() use ($VC) {
                        Route::put('vehicles/{vehicle}', [$VC, 'update'])->name('vehicles.update');
                    });
                    
                    Route::middleware(['permission:delete_transport_vehicles'])->group(function() use ($VC) {
                        Route::delete('vehicles/{vehicle}', [$VC, 'destroy'])->name('vehicles.destroy');
                    });
                });

                // Student Allocations
                Route::middleware(['permission:view_transport_allocations'])->group(function() {
                    $AC = \App\Http\Controllers\School\Transport\AllocationController::class;
                    Route::get('allocations',                   [$AC, 'index'])->name('allocations.index');
                    Route::get('allocations/students-by-class', [$AC, 'studentsByClass'])->name('allocations.students-by-class');

                    Route::middleware(['permission:create_transport_allocations'])->group(function() use ($AC) {
                        Route::post('allocations',              [$AC, 'store'])->name('allocations.store');
                    });

                    Route::middleware(['permission:edit_transport_allocations'])->group(function() use ($AC) {
                        Route::put('allocations/{allocation}',  [$AC, 'update'])->name('allocations.update');
                    });

                    Route::middleware(['permission:delete_transport_allocations'])->group(function() use ($AC) {
                        Route::delete('allocations/{allocation}', [$AC, 'destroy'])->name('allocations.destroy');
                    });
                });

                // Transport Fee Collection (standalone — no link to Finance FeePayment)
                Route::middleware(['permission:view_transport_allocations'])->group(function () {
                    $TFC = \App\Http\Controllers\School\Transport\TransportFeeCollectionController::class;
                    Route::get('fees',                              [$TFC, 'index'])->name('fees.index');
                    Route::get('fees/receipts/{payment}/receipt',   [$TFC, 'receipt'])->name('fees.receipt');
                    Route::get('fees/{allocation}',                 [$TFC, 'show'])->name('fees.show');

                    Route::middleware(['permission:collect_transport_fee'])->group(function () use ($TFC) {
                        Route::post('fees/batch-post-gl',                [$TFC, 'batchPostGl'])->name('fees.batch-post-gl');
                        Route::post('fees/{allocation}/collect',         [$TFC, 'store'])->name('fees.store');
                        Route::delete('fees/receipts/{payment}',         [$TFC, 'destroy'])->name('fees.receipt.destroy');
                    });
                });

                // Driver Tracking (phone as GPS)
                $DT = \App\Http\Controllers\Api\Transport\DriverTrackingController::class;
                Route::get('driver-tracking', fn () => inertia('School/Transport/DriverTracking/Index'))->name('driver-tracking');
                Route::get('driver-tracking/status', [$DT, 'status'])->name('driver-tracking.status');
                Route::post('driver-tracking/update', [$DT, 'update'])->name('driver-tracking.update');
                Route::post('driver-tracking/stop', [$DT, 'stop'])->name('driver-tracking.stop');

                // Live Tracking
                Route::middleware(['permission:view_transport_tracking'])->group(function() {
                    // Live tracking page (served as Inertia)
                    Route::get('live', fn () => inertia('School/Transport/Tracking/Index'))->name('live');

                    // Live vehicle locations JSON endpoint
                    $GC = \App\Http\Controllers\Api\Transport\GpsLogController::class;
                    Route::get('live-data', [$GC, 'live'])->name('live.data');
                });
            });
        });

        // Stationary Module — kit allocation, fee collection, issuance, returns
        Route::middleware(['school.management', 'permission:view_stationary'])->group(function () {
            Route::group(['prefix' => 'stationary', 'as' => 'stationary.'], function () {

                // Dashboard + module reports
                $SDC = \App\Http\Controllers\School\Stationary\StationaryDashboardController::class;
                Route::get('/', [$SDC, 'index'])->name('dashboard');
                Route::get('reports/fee-defaulters',     [$SDC, 'feeDefaulters'])->name('reports.fee-defaulters');
                Route::get('reports/collection-pending', [$SDC, 'collectionPending'])->name('reports.collection-pending');
                Route::get('reports/returns',            [$SDC, 'returnsReport'])->name('reports.returns');

                // Items
                Route::middleware(['permission:view_stationary_items'])->group(function () {
                    $IC = \App\Http\Controllers\School\Stationary\ItemController::class;
                    Route::get('items', [$IC, 'index'])->name('items.index');

                    Route::middleware(['permission:create_stationary_items'])->group(function () use ($IC) {
                        Route::post('items', [$IC, 'store'])->name('items.store');
                    });
                    Route::middleware(['permission:edit_stationary_items'])->group(function () use ($IC) {
                        Route::put('items/{item}', [$IC, 'update'])->name('items.update');
                    });
                    Route::middleware(['permission:delete_stationary_items'])->group(function () use ($IC) {
                        Route::delete('items/{item}', [$IC, 'destroy'])->name('items.destroy');
                    });
                });

                // Student Allocations
                Route::middleware(['permission:view_stationary_allocations'])->group(function () {
                    $AC = \App\Http\Controllers\School\Stationary\AllocationController::class;
                    Route::get('allocations',                      [$AC, 'index'])->name('allocations.index');
                    Route::get('allocations/students-by-class',    [$AC, 'studentsByClass'])->name('allocations.students-by-class');
                    Route::get('allocations/{allocation}',         [$AC, 'show'])->name('allocations.show');

                    Route::middleware(['permission:create_stationary_allocations'])->group(function () use ($AC) {
                        Route::post('allocations',                 [$AC, 'store'])->name('allocations.store');
                    });
                    Route::middleware(['permission:edit_stationary_allocations'])->group(function () use ($AC) {
                        Route::put('allocations/{allocation}',     [$AC, 'update'])->name('allocations.update');
                    });
                    Route::middleware(['permission:delete_stationary_allocations'])->group(function () use ($AC) {
                        Route::delete('allocations/{allocation}',  [$AC, 'destroy'])->name('allocations.destroy');
                    });

                    // Issuance log endpoints (nested under allocation)
                    $ISC = \App\Http\Controllers\School\Stationary\IssuanceController::class;
                    Route::get('allocations/{allocation}/issuances', [$ISC, 'index'])->name('issuances.index');

                    Route::middleware(['permission:issue_stationary_items'])->group(function () use ($ISC) {
                        Route::post('allocations/{allocation}/issuances', [$ISC, 'store'])->name('issuances.store');
                        Route::delete('issuances/{issuance}',             [$ISC, 'destroy'])->name('issuances.destroy');
                    });

                    // Return log endpoints (nested under allocation)
                    $RC = \App\Http\Controllers\School\Stationary\ReturnController::class;
                    Route::get('allocations/{allocation}/returns', [$RC, 'index'])->name('returns.index');

                    Route::middleware(['permission:accept_stationary_returns'])->group(function () use ($RC) {
                        Route::post('allocations/{allocation}/returns', [$RC, 'store'])->name('returns.store');
                        Route::delete('returns/{return}',               [$RC, 'destroy'])->name('returns.destroy');
                    });
                });

                // Fee Collection (standalone — no link to Finance FeePayment)
                Route::middleware(['permission:view_stationary_allocations'])->group(function () {
                    $SFC = \App\Http\Controllers\School\Stationary\StationaryFeeCollectionController::class;
                    Route::get('fees',                              [$SFC, 'index'])->name('fees.index');
                    Route::get('fees/receipts/{payment}/receipt',   [$SFC, 'receipt'])->name('fees.receipt');
                    Route::get('fees/{allocation}',                 [$SFC, 'show'])->name('fees.show');

                    Route::middleware(['permission:collect_stationary_fee'])->group(function () use ($SFC) {
                        Route::post('fees/{allocation}/collect',    [$SFC, 'store'])->name('fees.store');
                        Route::delete('fees/receipts/{payment}',    [$SFC, 'destroy'])->name('fees.receipt.destroy');
                    });
                });
            });
        });

        // ── Chat Module ──────────────────────────────────────────────────────
        Route::middleware(['module:chat'])->group(function () {
            Route::prefix('chat')->name('chat.')->group(function () {
                $CC = \App\Http\Controllers\School\ChatController::class;
                Route::get('/',                                              [$CC, 'index'])             ->name('index');
                Route::get('/conversations/poll',                           [$CC, 'pollConversations'])  ->middleware('throttle:120,1')->name('conversations.poll_all');
                Route::post('/direct',                                       [$CC, 'startDirect'])        ->name('direct.start');
                Route::post('/groups',                                       [$CC, 'createGroup'])        ->name('groups.create');
                Route::post('/broadcasts',                                   [$CC, 'createBroadcast'])    ->name('broadcasts.create');
                Route::post('/conversations/{conversation}/leave',           [$CC, 'leaveGroup'])         ->name('conversations.leave');
                Route::post('/conversations/{conversation}/participants',    [$CC, 'addParticipants'])    ->name('conversations.participants.add');
                Route::get('/conversations/{conversation}/messages',         [$CC, 'messages'])           ->name('conversations.messages');
                Route::post('/conversations/{conversation}/messages',        [$CC, 'send'])               ->name('conversations.messages.send');
                Route::get('/conversations/{conversation}/poll',             [$CC, 'poll'])               ->middleware('throttle:120,1')->name('conversations.poll');
                Route::get('/conversations/{conversation}/pinned',           [$CC, 'pinnedMessages'])     ->name('conversations.pinned');
                Route::get('/conversations/{conversation}/search',           [$CC, 'searchMessages'])     ->name('conversations.search');
                Route::post('/conversations/{conversation}/mark-read',       [$CC, 'markRead'])           ->name('conversations.mark-read');
                Route::post('/conversations/{conversation}/typing',          [$CC, 'typing'])             ->name('conversations.typing');
                Route::patch('/messages/{message}',                          [$CC, 'editMessage'])        ->name('messages.edit');
                Route::delete('/messages/{message}',                         [$CC, 'deleteMessage'])      ->name('messages.delete');
                Route::patch('/messages/{message}/pin',                      [$CC, 'pinMessage'])         ->name('messages.pin');
            });
        });

        // Front Office Module
        Route::middleware(['module:front_office'])->group(function () {
            Route::group(['prefix' => 'front-office', 'as' => 'front-office.'], function () {
                // Dashboard
                $DC = \App\Http\Controllers\School\FrontOffice\DashboardController::class;
                Route::get('/', [$DC, 'index'])->name('dashboard');
                Route::get('daily-report', [$DC, 'dailyReport'])->name('daily-report');

                // Visitors
                $VC = \App\Http\Controllers\School\FrontOffice\VisitorLogController::class;
                Route::get('visitors', [$VC, 'index'])->name('visitors.index');
                Route::post('visitors', [$VC, 'store'])->name('visitors.store');
                Route::put('visitors/{visitor}', [$VC, 'update'])->name('visitors.update');
                Route::delete('visitors/{visitor}', [$VC, 'destroy'])->name('visitors.destroy');
                Route::post('visitors/{visitor}/photo', [$VC, 'uploadPhoto'])->name('visitors.photo');
                Route::post('visitors/pre-register', [$VC, 'preRegister'])->name('visitors.pre-register');
                Route::post('visitors/{visitor}/check-in', [$VC, 'checkIn'])->name('visitors.check-in');

                // Gate Passes
                $GPC = \App\Http\Controllers\School\FrontOffice\GatePassController::class;
                Route::get('gate-passes', [$GPC, 'index'])->name('gate-passes.index');
                Route::post('gate-passes', [$GPC, 'store'])->name('gate-passes.store');
                Route::patch('gate-passes/{gatePass}/status', [$GPC, 'updateStatus'])->name('gate-passes.status.update');
                Route::delete('gate-passes/{gatePass}', [$GPC, 'destroy'])->name('gate-passes.destroy');
                Route::post('gate-passes/{gatePass}/photo', [$GPC, 'uploadPhoto'])->name('gate-passes.photo');
                Route::get('gate-passes/scanner', [$GPC, 'scanner'])->name('gate-passes.scanner');
                Route::post('gate-passes/verify-qr', [$GPC, 'verifyQR'])->name('gate-passes.verify-qr');

                // Complaints
                $CC = \App\Http\Controllers\School\FrontOffice\ComplaintController::class;
                Route::resource('complaints', $CC)->except(['create', 'show', 'edit']);

                // Call Logs
                $CLC = \App\Http\Controllers\School\FrontOffice\CallLogController::class;
                Route::resource('call-logs', $CLC)->except(['create', 'show', 'edit']);
                Route::get('call-logs-follow-ups', [$CLC, 'followUps'])->name('call-logs.follow-ups');

                // Correspondences
                $CorrC = \App\Http\Controllers\School\FrontOffice\CorrespondenceController::class;
                Route::resource('correspondence', $CorrC)->except(['create', 'show', 'edit']);
                Route::patch('correspondence/{correspondence}/status', [$CorrC, 'updateStatus'])->name('correspondence.update-status');
                Route::post('correspondence/{correspondence}/acknowledge', [$CorrC, 'acknowledge'])->name('correspondence.acknowledge');
            });
        });

        // ── Backup Module ──────────────────────────────────────────────────────
        Route::middleware(['school.management', 'permission:view_settings'])->group(function () {
            Route::group(['prefix' => 'backup', 'as' => 'backup.'], function () {
                $BC = \App\Http\Controllers\School\BackupController::class;
                Route::get('/',                     [$BC, 'index'])    ->name('index');
                Route::post('/',                    [$BC, 'store'])    ->name('store');
                Route::get('{backup}/download',     [$BC, 'download']) ->name('download');
                Route::delete('{backup}',           [$BC, 'destroy'])  ->name('destroy');
            });
        });

        // ── Bulk Export Routes (Excel / PDF / CSV) ─────────────────────────────
        Route::middleware(['school.management'])->prefix('export')->name('export.')->group(function () {
            Route::get('students',     \App\Http\Controllers\School\Export\StudentExportController::class)    ->name('students');
            Route::get('staff',        \App\Http\Controllers\School\Export\StaffExportController::class)       ->name('staff');
            Route::get('attendance',   \App\Http\Controllers\School\Export\AttendanceExportController::class)  ->name('attendance');
            Route::get('fee-payments', \App\Http\Controllers\School\Export\FeePaymentExportController::class)  ->name('fee-payments');
            Route::get('transactions', \App\Http\Controllers\School\Export\TransactionExportController::class) ->name('transactions');
            Route::get('expenses',     \App\Http\Controllers\School\Export\ExpenseExportController::class)     ->name('expenses');
            Route::get('budgets',      \App\Http\Controllers\School\Export\BudgetExportController::class)      ->name('budgets');
            Route::get('assignments',  \App\Http\Controllers\School\Export\AssignmentExportController::class)  ->name('assignments');
            Route::get('diary',        \App\Http\Controllers\School\Export\DiaryExportController::class)       ->name('diary');
            Route::get('book-list',    \App\Http\Controllers\School\Export\BookListExportController::class)    ->name('book-list');
            Route::get('ledgers',      \App\Http\Controllers\School\Export\LedgerExportController::class)      ->name('ledgers');
            Route::get('due-report',   \App\Http\Controllers\School\Export\DueReportExportController::class) ->name('due-report');
        });

    });
});

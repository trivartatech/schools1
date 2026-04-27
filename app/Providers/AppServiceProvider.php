<?php

namespace App\Providers;

use App\Contracts\ChatServiceContract;
use App\Contracts\FeeServiceContract;
use App\Contracts\FirebaseServiceContract;
use App\Contracts\NotificationServiceContract;
use App\Models\Announcement;
use App\Models\Expense;
use App\Models\FeeGroup;
use App\Models\FeePayment;
use App\Models\Hostel;
use App\Models\HostelStudent;
use App\Models\Leave;
use App\Models\Payroll;
use App\Models\TransportFeePayment;
use App\Models\User;
use App\Observers\ExpenseGLObserver;
use App\Observers\FeePaymentGLObserver;
use App\Observers\HostelStudentObserver;
use App\Observers\PayrollGLObserver;
use App\Observers\TransportFeePaymentGLObserver;
use App\Listeners\RoleChangeAuditListener;
use App\Observers\UserObserver;
use App\Services\ChatService;
use App\Services\FeeService;
use App\Services\FirebaseService;
use App\Services\NotificationService;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FeeServiceContract::class, FeeService::class);
        $this->app->singleton(FirebaseServiceContract::class, FirebaseService::class);
        $this->app->bind(NotificationServiceContract::class, NotificationService::class);
        $this->app->bind(ChatServiceContract::class, ChatService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Permission Registrar cache is handled by Spatie automatically.
        // We only clear it when switching team IDs in middleware if needed.

        // Only SuperAdmin bypasses all gate checks.
        // Admin / SchoolAdmin / Principal follow the normal policy path so that
        // business-logic rules (tenant isolation, per-record checks) still apply.
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) return true;
            return null;
        });

        \Illuminate\Support\Facades\Gate::policy(\App\Models\ExamTerm::class,    \App\Policies\ExamTermPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\ExamType::class,    \App\Policies\ExamTypePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Student::class,     \App\Policies\StudentPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Staff::class,       \App\Policies\StaffPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\FeePayment::class,  \App\Policies\FeePaymentPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Leave::class,       \App\Policies\LeavePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Announcement::class,\App\Policies\AnnouncementPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Payroll::class,     \App\Policies\PayrollPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Hostel::class,      \App\Policies\HostelPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\FeeGroup::class,    \App\Policies\FeeGroupPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Attendance::class,  \App\Policies\AttendancePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Expense::class,     \App\Policies\ExpensePolicy::class);

        // Audit log: fires whenever a Spatie role is attached or detached from any model.
        // config/permission.php: 'events_enabled' => true is required for these to fire.
        \Illuminate\Support\Facades\Event::listen(
            \Spatie\Permission\Events\RoleAttachedEvent::class,
            RoleChangeAuditListener::class
        );
        \Illuminate\Support\Facades\Event::listen(
            \Spatie\Permission\Events\RoleDetachedEvent::class,
            RoleChangeAuditListener::class
        );

        // Auto-assign Spatie role whenever a user is created or their user_type changes.
        User::observe(UserObserver::class);

        // GL Auto-posting observers — fire only when school has configured ledger mappings
        FeePayment::observe(FeePaymentGLObserver::class);
        TransportFeePayment::observe(TransportFeePaymentGLObserver::class);
        Expense::observe(ExpenseGLObserver::class);
        Payroll::observe(PayrollGLObserver::class);

        // Hostel fee sync — uses the Finance FeePayment bridge.
        HostelStudent::observe(HostelStudentObserver::class);

        // Log Viewer — token-based auth independent of ERP login.
        // Access: /log-viewer?token=<LOG_VIEWER_SECRET>
        // Once the token is verified it's stored in the session for the duration of the session.
        LogViewer::auth(function ($request) {
            $secret = config('log-viewer.secret');

            if (!$secret) {
                // No secret configured — allow only in local/dev
                return !app()->isProduction();
            }

            if ($request->get('token') === $secret) {
                session(['log_viewer_auth' => true]);
                return true;
            }

            return session('log_viewer_auth', false);
        });
    }
}

# Mobile API Routes — Domain Controller Mapping

This document describes the new domain-split route-to-controller mapping for the
mobile API (`prefix: mobile`). All routes sit under the `auth:sanctum` + `tenant`
middleware group.

The new controllers live in `app/Http/Controllers/Api/Mobile/`.  
The original monolithic controller `app/Http/Controllers/Api/MobileApiController.php`
is kept for backward compatibility but can be deleted once the new routes are wired in.

---

## New Route Group (replace the `$MA = MobileApiController::class` block)

```php
use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\DashboardController;
use App\Http\Controllers\Api\Mobile\AttendanceController;
use App\Http\Controllers\Api\Mobile\FeeController;
use App\Http\Controllers\Api\Mobile\AcademicController;
use App\Http\Controllers\Api\Mobile\NotificationController;
use App\Http\Controllers\Api\Mobile\LeaveController;
use App\Http\Controllers\Api\Mobile\SocialController;
use App\Http\Controllers\Api\Mobile\CommunicationController;

Route::middleware(['auth:sanctum', 'tenant'])->prefix('mobile')->group(function () {

    // ── Auth / Logout (AuthController) ─────────────────────────────────────
    Route::post('/logout',                     [\App\Http\Controllers\AuthController::class, 'apiLogout'])->name('api.mobile.logout');
    Route::post('/refresh',                    [\App\Http\Controllers\AuthController::class, 'refresh'])->name('api.mobile.refresh');
    Route::get('/profile',                     [AuthController::class, 'profile'])->name('api.mobile.profile');
    Route::post('/profile/update',             [AuthController::class, 'updateProfile'])->name('api.mobile.profile.update');
    Route::post('/profile/password',           [AuthController::class, 'updatePassword'])->name('api.mobile.profile.password');
    Route::post('/biometric/challenge',        [AuthController::class, 'biometricChallenge'])->name('api.mobile.biometric');
    Route::post('/device/register',            [AuthController::class, 'registerDevice'])->name('api.mobile.device');

    // ── Dashboard (DashboardController) ────────────────────────────────────
    Route::get('/dashboard',                   [DashboardController::class, 'dashboard'])->name('api.mobile.dashboard');
    Route::get('/children',                    [DashboardController::class, 'children'])->name('api.mobile.children');

    // ── Attendance (AttendanceController) ──────────────────────────────────
    Route::get('/attendance',                  [AttendanceController::class, 'attendance'])->name('api.mobile.attendance');

    // ── Fees / Payments (FeeController) ────────────────────────────────────
    Route::get('/fees',                        [FeeController::class, 'fees'])->name('api.mobile.fees');
    Route::get('/fees/{id}',                   [FeeController::class, 'feeDetail'])->name('api.mobile.fees.detail');
    Route::get('/payments/history',            [FeeController::class, 'paymentHistory'])->name('api.mobile.payments.history');
    Route::post('/payments/create-order',      [FeeController::class, 'createPaymentOrder'])->name('api.mobile.payments.create-order');
    Route::post('/payments/verify',            [FeeController::class, 'verifyPayment'])->name('api.mobile.payments.verify');

    // ── Academic (AcademicController) ──────────────────────────────────────
    Route::get('/homework',                            [AcademicController::class, 'homework'])->name('api.mobile.homework');
    Route::post('/homework/{id}/submit',               [AcademicController::class, 'submitHomework'])->name('api.mobile.homework.submit');
    Route::get('/syllabus',                            [AcademicController::class, 'syllabus'])->name('api.mobile.syllabus');
    Route::get('/diary',                               [AcademicController::class, 'diary'])->name('api.mobile.diary');
    Route::post('/diary/{id}/toggle-complete',         [AcademicController::class, 'toggleDiaryComplete'])->name('api.mobile.diary.toggle');
    Route::get('/resources',                           [AcademicController::class, 'resources'])->name('api.mobile.resources');
    Route::get('/book-list',                           [AcademicController::class, 'bookList'])->name('api.mobile.book-list');
    Route::get('/holidays',                            [AcademicController::class, 'holidays'])->name('api.mobile.holidays');
    Route::get('/exams',                               [AcademicController::class, 'exams'])->name('api.mobile.exams');
    Route::get('/results',                             [AcademicController::class, 'results'])->name('api.mobile.results');
    Route::get('/report-cards',                        [AcademicController::class, 'reportCards'])->name('api.mobile.report-cards');
    Route::get('/report-cards/{scheduleId}/download',  [AcademicController::class, 'downloadReportCard'])->name('api.mobile.report-cards.download');
    Route::get('/timetable',                           [AcademicController::class, 'timetable'])->name('api.mobile.timetable');
    Route::get('/id-card',                             [AcademicController::class, 'idCard'])->name('api.mobile.id-card');

    // ── Notifications (NotificationController) ─────────────────────────────
    Route::get('/notifications',                       [NotificationController::class, 'notifications'])->name('api.mobile.notifications');
    Route::post('/notifications/{id}/read',            [NotificationController::class, 'markNotificationRead'])->name('api.mobile.notifications.read');
    Route::post('/notifications/mark-all-read',        [NotificationController::class, 'markAllNotificationsRead'])->name('api.mobile.notifications.read-all');

    // ── Leaves (LeaveController) ────────────────────────────────────────────
    Route::get('/leave-types',                         [LeaveController::class, 'leaveTypes'])->name('api.mobile.leave-types');
    Route::get('/leaves',                              [LeaveController::class, 'leaves'])->name('api.mobile.leaves');
    Route::post('/leaves',                             [LeaveController::class, 'applyLeave'])->name('api.mobile.leaves.apply');
    Route::delete('/leaves/{id}',                      [LeaveController::class, 'cancelLeave'])->name('api.mobile.leaves.cancel');

    // ── Social / Posts (SocialController) ──────────────────────────────────
    Route::get('/posts',                               [SocialController::class, 'posts'])->name('api.mobile.posts');
    Route::post('/posts',                              [SocialController::class, 'createPost'])->name('api.mobile.posts.create');
    Route::post('/posts/{id}/like',                    [SocialController::class, 'toggleLike'])->name('api.mobile.posts.like');
    Route::post('/posts/{id}/bookmark',                [SocialController::class, 'toggleBookmark'])->name('api.mobile.posts.bookmark');
    Route::get('/posts/{id}/comments',                 [SocialController::class, 'postComments'])->name('api.mobile.posts.comments');
    Route::post('/posts/{id}/comments',                [SocialController::class, 'addComment'])->name('api.mobile.posts.comments.add');
    Route::delete('/posts/{id}',                       [SocialController::class, 'deletePost'])->name('api.mobile.posts.delete');

    // ── Communication (CommunicationController) ─────────────────────────────
    Route::get('/announcements',                       [CommunicationController::class, 'announcements'])->name('api.mobile.announcements');
    Route::get('/transport/live',                      [CommunicationController::class, 'transport'])->name('api.mobile.transport');
    Route::get('/complaints',                          [CommunicationController::class, 'complaints'])->name('api.mobile.complaints');
    Route::post('/complaints',                         [CommunicationController::class, 'submitComplaint'])->name('api.mobile.complaints.submit');
    Route::get('/edit-request/form',                   [CommunicationController::class, 'editRequestForm'])->name('api.mobile.edit-request.form');
    Route::post('/edit-request',                       [CommunicationController::class, 'submitEditRequest'])->name('api.mobile.edit-request.submit');
    Route::get('/edit-requests',                       [CommunicationController::class, 'editRequests'])->name('api.mobile.edit-requests');
});
```

---

## Controller Summary

| Controller | Namespace | Methods |
|---|---|---|
| `AuthController` | `Api\Mobile` | `profile`, `updateProfile`, `updatePassword`, `biometricChallenge`, `registerDevice` |
| `DashboardController` | `Api\Mobile` | `dashboard`, `children` |
| `AttendanceController` | `Api\Mobile` | `attendance` |
| `FeeController` | `Api\Mobile` | `fees`, `feeDetail`, `paymentHistory`, `createPaymentOrder`, `verifyPayment` |
| `AcademicController` | `Api\Mobile` | `homework`, `submitHomework`, `syllabus`, `diary`, `toggleDiaryComplete`, `resources`, `bookList`, `holidays`, `exams`, `results`, `reportCards`, `downloadReportCard`, `timetable`, `idCard` |
| `NotificationController` | `Api\Mobile` | `notifications`, `markNotificationRead`, `markAllNotificationsRead` |
| `LeaveController` | `Api\Mobile` | `leaveTypes`, `leaves`, `applyLeave`, `cancelLeave` |
| `SocialController` | `Api\Mobile` | `posts`, `createPost`, `toggleLike`, `toggleBookmark`, `postComments`, `addComment`, `deletePost` |
| `CommunicationController` | `Api\Mobile` | `announcements`, `transport`, `complaints`, `submitComplaint`, `editRequestForm`, `submitEditRequest`, `editRequests` |

---

## Private Helpers — Where They Live

| Helper Method | Used By | Copied Into |
|---|---|---|
| `resolveStudentId()` | All domains that need a student context | `DashboardController`, `AttendanceController`, `FeeController`, `AcademicController`, `LeaveController`, `SocialController`, `CommunicationController` |
| `childList()` | Dashboard, Auth/profile, Dashboard | `AuthController`, `DashboardController` |
| `userData()` | Dashboard only | `DashboardController` |
| `schoolData()` | Dashboard only | `DashboardController` |
| `stats()` | Dashboard only | `DashboardController` |
| `recentAnnouncements()` | Dashboard only | `DashboardController` |
| `attendanceSummary()` | Dashboard only | `DashboardController` |
| `calculateGrade()` | Report cards, results | `AcademicController` |
| `transportHaversine()` | Transport tracking | `CommunicationController` |

---

## Dependency Injection

Controllers that require `FeeService`:

- `DashboardController` — uses it in `stats()` helper
- `FeeController` — uses it in `fees()`

Both declare `public function __construct(protected FeeService $feeService) {}`.

---

## Migration Notes

1. The original `MobileApiController` at `app/Http/Controllers/Api/MobileApiController.php`
   is **not deleted** — it still handles all existing named routes defined in `api.php`.
2. To cut over, replace the `$MA = MobileApiController::class` block in `routes/api.php`
   with the route block shown above and add the `use` imports at the top of that file.
3. After verification, `MobileApiController.php` can be removed.

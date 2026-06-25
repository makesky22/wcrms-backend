<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WardController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\GpsLogController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\RecyclableLogController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\QRController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RegistrarController;

// Health check
Route::get('/health', fn() => response()->json(['status' => 'ok']));

// ── Public ─────────────────────────────────────────────────────────────────
Route::post('/auth/register',        [AuthController::class, 'register']);
Route::post('/auth/login',           [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password',  [AuthController::class, 'resetPassword']);

Route::get('/wards',                        [WardController::class, 'index']);
Route::get('/wards/{ward}/public-schedule', [ScheduleController::class, 'publicByWard']);

// ── Protected ──────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password',[AuthController::class, 'changePassword']);

    // Notifications
    Route::get('/notifications',             [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all',   [NotificationController::class, 'markAllRead']);

    // ── Resident ──────────────────────────────────────────────────────────
    Route::middleware('role:resident')->group(function () {
        Route::get('/qr/my-code',        [QRController::class,      'myCode']);
        Route::get('/payments/my-bills', [PaymentController::class, 'myBills']);
        Route::post('/complaints',       [ComplaintController::class, 'store']);
        Route::get('/complaints/my',     [ComplaintController::class, 'myComplaints']);
    });

    // ── Officer & Admin ───────────────────────────────────────────────────
    Route::middleware('role:officer,admin')->group(function () {
        Route::get('/officer/schedule',                          [ScheduleController::class,     'mySchedule']);
        Route::post('/officer/schedule/{schedule}/complete', [ScheduleController::class,     'markComplete']);

        Route::post('/gps/transmit', [GpsLogController::class, 'transmit']);
        Route::post('/gps/start',    [GpsLogController::class, 'startSession']);
        Route::post('/gps/stop',     [GpsLogController::class, 'stopSession']);

        Route::post('/recyclables',        [RecyclableLogController::class, 'store']);
        Route::get('/recyclables/my-logs', [RecyclableLogController::class, 'myLogs']);

        Route::post('/qr/scan-resident', [QRController::class, 'scanResident']);
        Route::get('/qr/today-scans',    [QRController::class, 'todayScans']);
    });

    // ── Supervisor & Admin ────────────────────────────────────────────────
    Route::middleware('role:supervisor,admin')->group(function () {
        Route::apiResource('wards',     WardController::class)->except(['index']);
        Route::apiResource('vehicles',  VehicleController::class);
        Route::apiResource('schedules', ScheduleController::class);
        Route::get('schedules/{schedule}/completions', [ScheduleController::class, 'completions']);

        Route::get('/gps/live',                     [GpsLogController::class, 'livePositions']);
        Route::get('/gps/vehicle/{vehicle}/replay', [GpsLogController::class, 'replay']);
        Route::get('/gps/inactive-alerts',          [GpsLogController::class, 'inactiveAlerts']);

        Route::get('/complaints',                      [ComplaintController::class, 'index']);
        Route::get('/complaints/{complaint}',          [ComplaintController::class, 'show']);
        Route::patch('/complaints/{complaint}/status', [ComplaintController::class, 'updateStatus']);

        // Reports — each route appears exactly once
        Route::get('/reports/dashboard-summary',    [ReportController::class, 'dashboardSummary']);
        Route::get('/reports/supervisor-dashboard', [ReportController::class, 'supervisorDashboard']);
        Route::get('/reports/officer-performance',  [ReportController::class, 'officerPerformance']);
        Route::get('/reports/complaint-volumes',    [ReportController::class, 'complaintVolumes']);
        Route::get('/reports/recyclable-trends',    [ReportController::class, 'recyclableTrends']);

        Route::get('/recyclables/summary',      [RecyclableLogController::class, 'summary']);
        Route::get('/payments',                  [PaymentController::class,      'index']);
        Route::get('/payments/unpaid-summary',   [ReportController::class,       'unpaidSummary']);

        // Collection-activity endpoint (supervisor view of daily QR scans)
        Route::get('/supervisor/collection-activity', [QRController::class, 'collectionActivity']);
    });

    // ── Admin Only ────────────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::patch('/users/{user}/activate',   [UserController::class,      'activate']);
        Route::patch('/users/{user}/deactivate', [UserController::class,      'deactivate']);
        Route::get('/admin/audit-trail',         [AuditTrailController::class, 'index']);

        Route::post('/payments/generate',             [PaymentController::class, 'generate']);
        Route::patch('/payments/{payment}/mark-paid', [PaymentController::class, 'markPaid']);
    });
});

// ── Registrar ────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:registrar,admin'])->prefix('registrar')->group(function () {
    Route::get('/residents',                              [RegistrarController::class, 'residents']);
    Route::post('/residents',                             [RegistrarController::class, 'registerResident']);
    Route::put('/residents/{user}',                       [RegistrarController::class, 'updateResident']);
    Route::get('/billing',                                [RegistrarController::class, 'billing']);
    Route::patch('/billing/{payment}/mark-paid',          [RegistrarController::class, 'markPaid']);
    Route::post('/scan-qr',                               [RegistrarController::class, 'scanQR']);
    Route::get('/rates',                                  [RegistrarController::class, 'rates']);
});

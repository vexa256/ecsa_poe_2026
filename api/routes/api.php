<?php

use App\Http\Controllers\AggregatedController as AGC;
use App\Http\Controllers\AlertsController as AC;
use App\Http\Controllers\HomeDashboardController as HDC;
use App\Http\Controllers\PrimaryScreeningController;
use App\Http\Controllers\PrimaryScreeningDashboardController as PSDC;
use App\Http\Controllers\PrimaryScreeningRecordsController as PSRC;
use App\Http\Controllers\SecondaryScreeningController as SSC;
use App\Http\Controllers\SecondaryScreeningRecordsController as SSRC;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserLoginController;
use Illuminate\Support\Facades\Route;

// ══════════════════════════════════════════════════════════════════════════════
//  POE Sentinel — routes/api.php
//  ECSA-HC · WHO IHR 2005 Aligned
//
//  ORDERING LAW: declare specific/named paths BEFORE parameterised {id} paths
//  in every group. Laravel matches top-to-bottom. "summary", "stats", "today",
//  "records", "by-notification" are all valid {id} values — they MUST come first.
//
//  AUTH: ALL routes are open by design. No auth middleware is applied here.
//  Auth middleware (auth:sanctum) will be added as a separate layer.
//  NEVER add Authorization headers in frontend fetches until that is done.
// ══════════════════════════════════════════════════════════════════════════════

// ── Auth ───────────────────────────────────────────────────────────────────
Route::post('/auth/login', [UserLoginController::class, 'login']);
Route::post('/auth/logout', [UserLoginController::class, 'logout']);

// ── Home Dashboard ─────────────────────────────────────────────────────────
Route::get('/home/summary', [HDC::class, 'summary']);
Route::get('/home/live', [HDC::class, 'live']);
Route::get('/home/activity', [HDC::class, 'activity']);

// ── Primary Screening Dashboard ────────────────────────────────────────────
Route::get('/dashboard/summary', [PSDC::class, 'summary']);
Route::get('/dashboard/trend', [PSDC::class, 'trend']);
Route::get('/dashboard/heatmap', [PSDC::class, 'heatmap']);
Route::get('/dashboard/funnel', [PSDC::class, 'funnel']);
Route::get('/dashboard/epi', [PSDC::class, 'epi']);
Route::get('/dashboard/poe-comparison', [PSDC::class, 'poeComparison']);
Route::get('/dashboard/screener-report', [PSDC::class, 'screenerReport']);
Route::get('/dashboard/device-health', [PSDC::class, 'deviceHealth']);
Route::get('/dashboard/alerts-summary', [PSDC::class, 'alertsSummary']);
Route::get('/dashboard/weekly-report', [PSDC::class, 'weeklyReport']);
Route::get('/dashboard/live', [PSDC::class, 'live']);

// ── Primary Screenings ─────────────────────────────────────────────────────
// ⚠ /stats/today and /referral-queue BEFORE /{id}
Route::get('/primary-screenings/stats/today', [PrimaryScreeningController::class, 'stats']);
Route::get('/primary-screenings', [PrimaryScreeningController::class, 'index']);
Route::post('/primary-screenings', [PrimaryScreeningController::class, 'store']);
Route::get('/primary-screenings/{id}', [PrimaryScreeningController::class, 'show']);
Route::patch('/primary-screenings/{id}/void', [PrimaryScreeningController::class, 'void']);

Route::get('/referral-queue', [PrimaryScreeningController::class, 'queue']);
Route::patch('/referral-queue/{notifId}/cancel', [PrimaryScreeningController::class, 'cancelReferral']);

// ── Primary Records (officer record register + analytics) ─────────────────
// ⚠ /stats, /heatmap, /trend, /export BEFORE /{id}
Route::get('/primary-records/stats', [PSRC::class, 'stats']);
Route::get('/primary-records/heatmap', [PSRC::class, 'heatmap']);
Route::get('/primary-records/trend', [PSRC::class, 'trend']);
Route::get('/primary-records/export', [PSRC::class, 'export']);
Route::get('/primary-records', [PSRC::class, 'index']);
Route::get('/primary-records/{id}', [PSRC::class, 'show']);
Route::patch('/primary-records/{id}/void', [PSRC::class, 'void']);

// ── Secondary Screenings ───────────────────────────────────────────────────
// ⚠ /by-notification/{uuid} BEFORE /{id} — "by-notification" would match {id}
Route::get('/secondary-screenings/by-notification/{uuid}', [SSC::class, 'showByNotification']);
Route::get('/secondary-screenings', [SSC::class, 'index']);
Route::post('/secondary-screenings', [SSC::class, 'store']);
Route::get('/secondary-screenings/{id}', [SSC::class, 'show']);
Route::delete('/secondary-screenings/{id}', [SSC::class, 'softDelete']);
Route::patch('/secondary-screenings/{id}', [SSC::class, 'update']);
Route::patch('/secondary-screenings/{id}/status', [SSC::class, 'updateStatus']);
Route::post('/secondary-screenings/{id}/symptoms', [SSC::class, 'syncSymptoms']);
Route::post('/secondary-screenings/{id}/exposures', [SSC::class, 'syncExposures']);
Route::post('/secondary-screenings/{id}/actions', [SSC::class, 'syncActions']);
Route::post('/secondary-screenings/{id}/samples', [SSC::class, 'syncSamples']);
Route::post('/secondary-screenings/{id}/travel', [SSC::class, 'syncTravel']);
Route::post('/secondary-screenings/{id}/diseases', [SSC::class, 'syncDiseases']);
Route::post('/secondary-screenings/{id}/sync', [SSC::class, 'fullSync']);

// ── Secondary Screening Records (case register read) ──────────────────────
// ⚠ /stats BEFORE /{id}
Route::get('/screening-records/stats', [SSRC::class, 'stats']);
Route::get('/screening-records', [SSRC::class, 'index']);
Route::get('/screening-records/{id}', [SSRC::class, 'show']);

// ── Alerts ─────────────────────────────────────────────────────────────────
// ⚠ /summary BEFORE /{id} — "summary" would match /{id} otherwise
// Role enforcement: DISTRICT_SUPERVISOR (DISTRICT), PHEOC_OFFICER (PHEOC), NATIONAL_ADMIN (NATIONAL)
Route::get('/alerts/summary', [AC::class, 'summary']);
Route::get('/alerts/compliance', [\App\Http\Controllers\AlertFollowupsController::class, 'compliance']);
Route::get('/alerts', [AC::class, 'index']);
Route::post('/alerts', [AC::class, 'store']);
Route::get('/alerts/{id}', [AC::class, 'show']);
Route::patch('/alerts/{id}/acknowledge', [AC::class, 'acknowledge']);
Route::patch('/alerts/{id}/close', [AC::class, 'close']);
Route::get('/alerts/{id}/followups', [\App\Http\Controllers\AlertFollowupsController::class, 'index']);
Route::post('/alerts/{id}/followups', [\App\Http\Controllers\AlertFollowupsController::class, 'store']);
Route::patch('/alert-followups/{id}', [\App\Http\Controllers\AlertFollowupsController::class, 'update']);

// ── Aggregated Data Submissions ────────────────────────────────────────────
// Roles: POE_DATA_OFFICER, POE_ADMIN, NATIONAL_ADMIN only
Route::get('/aggregated', [AGC::class, 'index']);
Route::post('/aggregated', [AGC::class, 'store']);
Route::get('/aggregated/{id}', [AGC::class, 'show']);

// ── Aggregated Templates (admin-managed, country-scoped) ──────────────────
// /active BEFORE /{id} to prevent "active" being treated as an integer id.
// /active + /published BEFORE /{id} so Laravel doesn't treat them as numeric id.
Route::get   ('/aggregated-templates/active',        [\App\Http\Controllers\AggregatedTemplatesController::class, 'active']);
Route::get   ('/aggregated-templates/published',     [\App\Http\Controllers\AggregatedTemplatesController::class, 'published']);
Route::get   ('/aggregated-templates',               [\App\Http\Controllers\AggregatedTemplatesController::class, 'index']);
Route::post  ('/aggregated-templates',               [\App\Http\Controllers\AggregatedTemplatesController::class, 'store']);
Route::get   ('/aggregated-templates/{id}',          [\App\Http\Controllers\AggregatedTemplatesController::class, 'show']);
Route::patch ('/aggregated-templates/{id}',          [\App\Http\Controllers\AggregatedTemplatesController::class, 'update']);
Route::delete('/aggregated-templates/{id}',          [\App\Http\Controllers\AggregatedTemplatesController::class, 'destroy']);
Route::post  ('/aggregated-templates/{id}/publish',  [\App\Http\Controllers\AggregatedTemplatesController::class, 'publish']);
Route::post  ('/aggregated-templates/{id}/retire',   [\App\Http\Controllers\AggregatedTemplatesController::class, 'retire']);
Route::post  ('/aggregated-templates/{id}/activate', [\App\Http\Controllers\AggregatedTemplatesController::class, 'activate']); // alias for publish
Route::post  ('/aggregated-templates/{id}/lock',     [\App\Http\Controllers\AggregatedTemplatesController::class, 'lock']);
Route::post  ('/aggregated-templates/{id}/columns',  [\App\Http\Controllers\AggregatedTemplatesController::class, 'addColumn']);
Route::patch ('/aggregated-templates/{id}/columns',  [\App\Http\Controllers\AggregatedTemplatesController::class, 'bulkUpdateColumns']);
Route::patch ('/aggregated-template-columns/{colId}',  [\App\Http\Controllers\AggregatedTemplatesController::class, 'updateColumn']);
Route::delete('/aggregated-template-columns/{colId}',  [\App\Http\Controllers\AggregatedTemplatesController::class, 'deleteColumn']);

// ── POE Notification Contacts ─────────────────────────────────────────────
Route::get   ('/poe-contacts/escalation-chain', [\App\Http\Controllers\PoeContactsController::class, 'escalationChain']);
Route::get   ('/poe-contacts',             [\App\Http\Controllers\PoeContactsController::class, 'index']);
Route::post  ('/poe-contacts',             [\App\Http\Controllers\PoeContactsController::class, 'store']);
Route::patch ('/poe-contacts/{id}',        [\App\Http\Controllers\PoeContactsController::class, 'update']);
Route::delete('/poe-contacts/{id}',        [\App\Http\Controllers\PoeContactsController::class, 'destroy']);

// ── Enterprise Notifications (alerts, escalations, reminders, reports) ────
Route::post('/notifications/alert-broadcast',   [\App\Http\Controllers\NotificationsController::class, 'alertBroadcast']);
Route::post('/notifications/escalation',        [\App\Http\Controllers\NotificationsController::class, 'escalation']);
Route::post('/notifications/followup-reminder', [\App\Http\Controllers\NotificationsController::class, 'followupReminder']);
Route::post('/notifications/pheic-advisory',    [\App\Http\Controllers\NotificationsController::class, 'pheicAdvisory']);
Route::post('/notifications/daily-report',      [\App\Http\Controllers\NotificationsController::class, 'dailyReport']);
Route::post('/notifications/weekly-report',     [\App\Http\Controllers\NotificationsController::class, 'weeklyReport']);
Route::post('/notifications/send',              [\App\Http\Controllers\NotificationsController::class, 'send']);
Route::post('/notifications/retry-failed',      [\App\Http\Controllers\NotificationsController::class, 'retryFailed']);
Route::get ('/notifications/log',               [\App\Http\Controllers\NotificationsController::class, 'log']);
Route::get ('/notifications/stats',             [\App\Http\Controllers\NotificationsController::class, 'stats']);

// ── Users ──────────────────────────────────────────────────────────────────
// ⚠ /me BEFORE /{id}
Route::get('/users/me', [UserController::class, 'me']);
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::patch('/users/{id}', [UserController::class, 'update']);
Route::patch('/users/{id}/status', [UserController::class, 'toggleStatus']);

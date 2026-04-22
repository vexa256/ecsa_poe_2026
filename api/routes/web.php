<?php

use App\Http\Controllers\Admin\DashboardAlertsController;
use App\Http\Controllers\Admin\DashboardPoeContactsController;
use App\Http\Controllers\Admin\DashboardUsersController;
use App\Http\Controllers\Admin\ReferenceDataController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| PHEOC Command Centre · Admin shell (walking skeleton)
|--------------------------------------------------------------------------
| All /admin/* routes will eventually be gated behind auth + RoleGate. For now
| the dashboard is unauthenticated so the layout can be previewed without
| seeding users. Wire `->middleware(['auth', 'role:NATIONAL_ADMIN,PHEOC_OFFICER'])`
| once auth session is finalised.
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::view('/login', 'admin.login')->name('login');
    Route::post('/logout', function () { return redirect('/admin/login'); })->name('logout');
    Route::view('/dashboard', 'admin.dashboard')->name('dashboard');
    Route::redirect('/', '/admin/dashboard');

    // Phase 2 · shadcn foundation preview. Not linked from the sidebar; open
    // manually at /admin/__theme to QA primitives before Phase 3 wires them
    // into the master layout. Delete after sign-off.
    Route::view('/__theme', 'admin.theme-preview')->name('__theme');

    // M7 · Administration → Users & Roles (enterprise CRUD).
    // Auth is deferred until session auth is wired — the API endpoint the
    // view calls (/api/admin/users) is fully guarded by Sanctum + RoleGate,
    // so the scope descriptor injected here is a preview only. The JSON API
    // is the enforcement surface.
    Route::get('/users', [DashboardUsersController::class, 'index'])->name('users.index');
    Route::get('/users/risk', [DashboardUsersController::class, 'risk'])->name('users.risk');
    Route::get('/users/dormant', [DashboardUsersController::class, 'dormant'])->name('users.dormant');
    Route::get('/assignments', [DashboardUsersController::class, 'assignments'])->name('assignments.index');

    // M2 · Alert Lifecycle — Hub (kanban) + War Room (tabbed single page)
    Route::get('/alerts', [DashboardAlertsController::class, 'hub'])->name('alerts.hub');
    Route::get('/alerts/{id}', [DashboardAlertsController::class, 'warRoom'])
         ->whereNumber('id')->name('alerts.warroom');

    // Reference data — the hard-coded SSOT catalogs (POES.js, Diseases.js, exposures.js)
    // published into public/ssot/ and parsed by App\Services\SsotRegistry.
    Route::get('/settings/poe-contacts', [DashboardPoeContactsController::class, 'index'])->name('settings.poe-contacts');
    Route::get('/settings/poes',      [ReferenceDataController::class, 'poes'])->name('settings.poes');
    Route::get('/settings/diseases',  [ReferenceDataController::class, 'diseases'])->name('settings.diseases');
    Route::get('/settings/exposures', [ReferenceDataController::class, 'exposures'])->name('settings.exposures');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Public auth landings reached from email CTAs. MUST be declared AFTER
// auth.php so these routes override the stock Breeze verify-email /
// reset-password routes — our versions are token-validating SPA pages that
// POST to /api/v2/auth/* instead of the old /password.store flow.
Route::view('/accept-invite',   'auth.accept-invite')->name('public.accept-invite');
Route::view('/reset-password',  'auth.reset-password')->name('public.reset-password');
Route::view('/verify-email',    'auth.verify-email')->name('public.verify-email');

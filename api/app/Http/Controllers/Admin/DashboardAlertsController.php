<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AlertsController as MobileAlertsController;
use App\Http\Controllers\Controller;
use App\Services\PheocScope;
use App\Services\SsotRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * DashboardAlertsController
 * ─────────────────────────────────────────────────────────────────────────
 * Renders the War Room surface — the Alert Hub (kanban) and the per-alert
 * War Room (tabbed single page with a persistent action bar). All data is
 * fetched client-side from /api/v2/alerts/* over Sanctum bearer auth.
 *
 *   GET  /admin/alerts          — hub (kanban by risk_level + filters)
 *   GET  /admin/alerts/{id}     — war room
 */
final class DashboardAlertsController extends Controller
{
    public function hub(Request $request, PheocScope $scope): View
    {
        $viewScope = $request->user() ? $scope->forUser($request->user()) : [
            'is_super' => true, 'scope_level' => 'NATIONAL', 'role_key' => 'NATIONAL_ADMIN',
            'label' => 'Preview · Uganda', 'country_code' => 'UG',
        ];

        return view('admin.alerts.hub', [
            'actorScope' => $viewScope,
            'closeCategories' => MobileAlertsController::CLOSE_CATEGORIES,
            'diseases' => array_values(array_map(
                fn($d) => ['id' => $d['id'], 'name' => $d['name'], 'priority_tier' => $d['priority_tier'] ?? null],
                SsotRegistry::diseases(),
            )),
        ]);
    }

    public function warRoom(Request $request, PheocScope $scope, int $id): View
    {
        $viewScope = $request->user() ? $scope->forUser($request->user()) : [
            'is_super' => true, 'scope_level' => 'NATIONAL', 'role_key' => 'NATIONAL_ADMIN',
            'label' => 'Preview · Uganda', 'country_code' => 'UG',
        ];

        return view('admin.alerts.war-room', [
            'alertId'        => $id,
            'actorScope'     => $viewScope,
            'closeCategories'=> MobileAlertsController::CLOSE_CATEGORIES,
        ]);
    }
}

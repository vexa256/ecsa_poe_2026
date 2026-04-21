<?php

declare (strict_types = 1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  AlertsController                                                            ║
 * ║  ECSA-HC POE Sentinel · WHO/IHR 2005 Annex 2 Aligned                        ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Database:  poe_2026  (DB:: facade — NO Eloquent models)                    ║
 * ║  Auth:      NONE — all routes open by design. Auth middleware added later.   ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  ROUTES (routes/api.php):                                                    ║
 * ║                                                                              ║
 * ║  use App\Http\Controllers\AlertsController as AC;                           ║
 * ║                                                                              ║
 * ║  Route::get   ('/alerts',                    [AC::class,'index']);           ║
 * ║  Route::get   ('/alerts/{id}',               [AC::class,'show']);            ║
 * ║  Route::post  ('/alerts',                    [AC::class,'store']);           ║
 * ║  Route::patch ('/alerts/{id}/acknowledge',   [AC::class,'acknowledge']);     ║
 * ║  Route::patch ('/alerts/{id}/close',         [AC::class,'close']);           ║
 * ║  Route::get   ('/alerts/summary',            [AC::class,'summary']);         ║
 * ║                                                                              ║
 * ║  ⚠ ORDER: /alerts/summary MUST be declared BEFORE /alerts/{id}             ║
 * ║    to prevent "summary" being treated as a numeric ID.                       ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  IHR COMPLIANCE:                                                             ║
 * ║  This controller implements the alert acknowledgement workflow per           ║
 * ║  IHR 2005 Annex 2. The routing hierarchy (DISTRICT → PHEOC → NATIONAL)      ║
 * ║  maps directly to the four-level geographic scope enforced by the engine.    ║
 * ║                                                                              ║
 * ║  Alert lifecycle:  OPEN → ACKNOWLEDGED → CLOSED                             ║
 * ║  Invalid:          CLOSED → any  (terminal state)                           ║
 * ║                    ACKNOWLEDGED → OPEN  (regression not allowed)            ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */
final class AlertsController extends Controller
{
    private const VALID_STATUSES       = ['OPEN', 'ACKNOWLEDGED', 'CLOSED'];
    private const VALID_RISK_LEVELS    = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
    private const VALID_ROUTED_TO      = ['DISTRICT', 'PHEOC', 'NATIONAL'];
    private const VALID_GENERATED_FROM = ['RULE_BASED', 'OFFICER'];
    private const VALID_PLATFORMS      = ['ANDROID', 'IOS', 'WEB'];

    /** Roles that may acknowledge/close at each routing level. */
    private const ACKNOWLEDGE_ROLES = [
        'DISTRICT' => ['DISTRICT_SUPERVISOR', 'PHEOC_OFFICER', 'NATIONAL_ADMIN'],
        'PHEOC'    => ['PHEOC_OFFICER', 'NATIONAL_ADMIN'],
        'NATIONAL' => ['NATIONAL_ADMIN'],
    ];

    private const MAX_PER_PAGE = 100;

    // ═════════════════════════════════════════════════════════════════════
    // GET /alerts
    // List alerts for the authenticated user's geographic scope.
    // Filters: status, risk_level, routed_to_level, date_from, date_to,
    //          ihr_tier, updated_after (cursor for incremental sync)
    // ═════════════════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->query('user_id', 0);
        if ($userId <= 0) {
            return $this->err(422, 'user_id query parameter is required.', [
                'hint' => 'Append ?user_id={AUTH_DATA.id}',
            ]);
        }

        $user = $this->resolveUser($userId);
        if (! $user) {
            return $this->err(404, 'User not found.', ['user_id' => $userId]);
        }

        $assignment = $this->resolvePrimaryAssignment($userId);
        if (! $assignment) {
            return $this->err(403, 'No active assignment.', ['user_id' => $userId]);
        }

        try {
            $query = DB::table('alerts as a')
                ->leftJoin('secondary_screenings as ss', 'ss.id', '=', 'a.secondary_screening_id')
                ->leftJoin('users as ack_u', 'ack_u.id', '=', 'a.acknowledged_by_user_id')
                ->whereNull('a.deleted_at');

            // Geographic scope enforcement
            $roleKey = $user->role_key ?? '';
            if (in_array($roleKey, ['POE_PRIMARY', 'POE_SECONDARY', 'POE_DATA_OFFICER', 'POE_ADMIN', 'SCREENER'], true)) {
                $query->where('a.poe_code', $assignment->poe_code);
            } elseif ($roleKey === 'DISTRICT_SUPERVISOR') {
                $query->where('a.district_code', $assignment->district_code);
            } elseif ($roleKey === 'PHEOC_OFFICER') {
                $query->where('a.pheoc_code', $assignment->pheoc_code);
            } else {
                // NATIONAL_ADMIN — sees everything in their country
                $query->where('a.country_code', $assignment->country_code);
            }

            // Filters
            if ($request->filled('status')) {
                $st = strtoupper($request->query('status'));
                if (in_array($st, self::VALID_STATUSES, true)) {
                    $query->where('a.status', $st);
                }
            }
            if ($request->filled('risk_level')) {
                $rl = strtoupper($request->query('risk_level'));
                if (in_array($rl, self::VALID_RISK_LEVELS, true)) {
                    $query->where('a.risk_level', $rl);
                }
            }
            if ($request->filled('routed_to_level')) {
                $rtl = strtoupper($request->query('routed_to_level'));
                if (in_array($rtl, self::VALID_ROUTED_TO, true)) {
                    $query->where('a.routed_to_level', $rtl);
                }
            }
            if ($request->filled('date_from')) {
                $query->where('a.created_at', '>=', $request->query('date_from') . ' 00:00:00');
            }
            if ($request->filled('date_to')) {
                $query->where('a.created_at', '<=', $request->query('date_to') . ' 23:59:59');
            }
            if ($request->filled('updated_after')) {
                $after = $this->safeDatetime($request->query('updated_after'));
                if ($after) {
                    $query->where('a.updated_at', '>', $after);
                }
            }
            // IHR tier filter — derived from alert_code prefix patterns
            if ($request->filled('ihr_tier')) {
                $tier = strtoupper($request->query('ihr_tier'));
                if ($tier === 'TIER_1') {
                    $query->where(function ($q) {
                        $q->where('a.alert_code', 'like', 'TIER1%')
                            ->orWhereIn('a.routed_to_level', ['NATIONAL']);
                    });
                } elseif ($tier === 'TIER_2') {
                    $query->where('a.routed_to_level', 'PHEOC');
                }
            }

            $total   = (clone $query)->count();
            $perPage = min((int) $request->query('per_page', 50), self::MAX_PER_PAGE);
            $page    = max(1, (int) $request->query('page', 1));
            $offset  = ($page - 1) * $perPage;

            // Default order: CRITICAL first, then most recent
            $alerts = $query
                ->select([
                    'a.*',
                    'ss.case_status        as case_status',
                    'ss.syndrome_classification as syndrome',
                    'ss.traveler_gender    as traveler_gender',
                    'ss.opened_at          as case_opened_at',
                    'ack_u.full_name       as acknowledged_by_name',
                ])
                ->orderByRaw("FIELD(a.risk_level, 'CRITICAL','HIGH','MEDIUM','LOW')")
                ->orderBy('a.created_at', 'desc')
                ->skip($offset)
                ->take($perPage)
                ->get();

            // Summary counts by status for the dashboard pill strip
            $statusCounts = DB::table('alerts')
                ->select('status', DB::raw('COUNT(*) as cnt'))
                ->whereNull('deleted_at')
                ->where('country_code', $assignment->country_code)
                ->groupBy('status')
                ->pluck('cnt', 'status')
                ->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Alerts retrieved.',
                'data'    => [
                    'items'         => $alerts->map(fn($a) => $this->formatAlert($a))->values(),
                    'total'         => $total,
                    'per_page'      => $perPage,
                    'page'          => $page,
                    'pages'         => (int) ceil($total / max(1, $perPage)),
                    'status_counts' => $statusCounts,
                ],
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e, 'alerts index');
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // GET /alerts/summary
    // Lightweight KPI counts for dashboard home pill strip.
    // Returns: open_critical, open_high, open_total, unacknowledged_24h
    // ⚠ Must be registered BEFORE /alerts/{id} in routes/api.php.
    // ═════════════════════════════════════════════════════════════════════

    public function summary(Request $request): JsonResponse
    {
        $userId = (int) $request->query('user_id', 0);
        if ($userId <= 0) {
            return $this->err(422, 'user_id query parameter is required.');
        }

        $user       = $this->resolveUser($userId);
        $assignment = $this->resolvePrimaryAssignment($userId);
        if (! $user || ! $assignment) {
            return $this->err(403, 'No active user or assignment.');
        }

        try {
            $base = DB::table('alerts')
                ->whereNull('deleted_at')
                ->where('country_code', $assignment->country_code)
                ->where('status', 'OPEN');

            // Scope to the user's level
            $roleKey = $user->role_key ?? '';
            if (in_array($roleKey, ['POE_PRIMARY', 'POE_SECONDARY', 'POE_DATA_OFFICER', 'POE_ADMIN', 'SCREENER'], true)) {
                $base->where('poe_code', $assignment->poe_code);
            } elseif ($roleKey === 'DISTRICT_SUPERVISOR') {
                $base->where('district_code', $assignment->district_code);
            } elseif ($roleKey === 'PHEOC_OFFICER') {
                $base->where('pheoc_code', $assignment->pheoc_code);
            }

            $openTotal    = (clone $base)->count();
            $openCritical = (clone $base)->where('risk_level', 'CRITICAL')->count();
            $openHigh     = (clone $base)->where('risk_level', 'HIGH')->count();
            $unacked24h   = (clone $base)->where('created_at', '<', now()->subHours(24))->count();
            $nationalOpen = (clone $base)->where('routed_to_level', 'NATIONAL')->count();

            return $this->ok([
                'open_total'          => $openTotal,
                'open_critical'       => $openCritical,
                'open_high'           => $openHigh,
                'unacknowledged_24h'  => $unacked24h,
                'national_level_open' => $nationalOpen,
                'ihr_action_required' => $unacked24h > 0 || $openCritical > 0,
            ], 'Alert summary retrieved.');
        } catch (Throwable $e) {
            return $this->serverError($e, 'alerts summary');
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // GET /alerts/{id}
    // ═════════════════════════════════════════════════════════════════════

    public function show(Request $request, int $id): JsonResponse
    {
        $userId = (int) $request->query('user_id', 0);
        if ($userId <= 0) {
            return $this->err(422, 'user_id query parameter is required.');
        }

        $assignment = $this->resolvePrimaryAssignment($userId);
        if (! $assignment) {
            return $this->err(403, 'No active assignment.');
        }

        try {
            $alert = DB::table('alerts')->where('id', $id)->whereNull('deleted_at')->first();
            if (! $alert) {
                return $this->err(404, 'Alert not found.', ['id' => $id]);
            }

            $scopeErr = $this->checkScope($alert, $assignment, $this->resolveUser($userId));
            if ($scopeErr) {
                return $scopeErr;
            }

            $formatted = $this->formatAlert($alert);

            // Attach related secondary case summary
            $case = DB::table('secondary_screenings')
                ->where('id', $alert->secondary_screening_id)
                ->whereNull('deleted_at')
                ->first();

            $formatted['secondary_case'] = $case ? [
                'id'                      => $case->id,
                'client_uuid'             => $case->client_uuid,
                'case_status'             => $case->case_status,
                'syndrome_classification' => $case->syndrome_classification,
                'risk_level'              => $case->risk_level,
                'final_disposition'       => $case->final_disposition,
                'traveler_gender'         => $case->traveler_gender,
                'traveler_full_name'      => $case->traveler_full_name,
                'opened_at'               => $case->opened_at,
                'dispositioned_at'        => $case->dispositioned_at,
            ] : null;

            // Attach top suspected disease
            $topDisease = DB::table('secondary_suspected_diseases')
                ->where('secondary_screening_id', $alert->secondary_screening_id)
                ->where('rank_order', 1)
                ->first();
            $formatted['top_suspected_disease'] = $topDisease ? (array) $topDisease : null;

            return $this->ok($formatted, 'Alert retrieved with case context.');
        } catch (Throwable $e) {
            return $this->serverError($e, 'alerts show');
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // POST /alerts
    // Sync an alert from the mobile device (generated by SecondaryScreening.vue).
    // Idempotent by client_uuid.
    // ═════════════════════════════════════════════════════════════════════

    public function store(Request $request): JsonResponse
    {
        $userId = (int) $request->input('created_by_user_id', 0);
        if ($userId <= 0) {
            return $this->err(422, 'created_by_user_id is required.', [
                'hint' => 'Send AUTH_DATA.id',
            ]);
        }

        $user = $this->resolveUser($userId);
        if (! $user || ! (bool) $user->is_active) {
            return $this->err(403, 'User not found or inactive.');
        }

        $assignment = $this->resolvePrimaryAssignment($userId);
        if (! $assignment) {
            return $this->err(403, 'No active assignment.');
        }

        // Required field validation
        $clientUuid = (string) $request->input('client_uuid', '');
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $clientUuid)) {
            return $this->err(422, 'client_uuid must be a valid UUID v4.', [
                'received' => $clientUuid,
            ]);
        }

        $secId = $this->resolveSecondaryScreeningId($request->input('secondary_screening_id'));
        if (! $secId) {
            return $this->err(422, 'secondary_screening_id not found. Ensure secondary screening is synced first.', [
                'received' => $request->input('secondary_screening_id'),
            ]);
        }

        $alertCode  = trim((string) $request->input('alert_code', ''));
        $alertTitle = trim((string) $request->input('alert_title', ''));
        $riskLevel  = strtoupper((string) $request->input('risk_level', 'HIGH'));
        $routedTo   = strtoupper((string) $request->input('routed_to_level', 'DISTRICT'));
        $genFrom    = strtoupper((string) $request->input('generated_from', 'RULE_BASED'));

        if (empty($alertCode)) {
            return $this->err(422, 'alert_code is required.');
        }
        if (! in_array($riskLevel, self::VALID_RISK_LEVELS, true)) {
            return $this->err(422, 'Invalid risk_level.', ['valid' => self::VALID_RISK_LEVELS]);
        }
        if (! in_array($routedTo, self::VALID_ROUTED_TO, true)) {
            return $this->err(422, 'Invalid routed_to_level.', ['valid' => self::VALID_ROUTED_TO]);
        }

        try {
            // IDEMPOTENCY: existing alert by client_uuid
            $existing = DB::table('alerts')->where('client_uuid', $clientUuid)->first();
            if ($existing) {
                Log::info('[Alerts][store] Idempotent resubmit', [
                    'client_uuid' => $clientUuid,
                    'server_id'   => $existing->id,
                ]);
                return $this->ok(
                    $this->formatAlert($existing),
                    'Alert already exists (idempotent resubmit).',
                    ['idempotent' => true, 'server_id' => $existing->id]
                );
            }

            $now = now()->format('Y-m-d H:i:s');

            $alertId = DB::table('alerts')->insertGetId([
                'client_uuid'             => $clientUuid,
                'idempotency_key'         => null,
                'reference_data_version'  => $request->input('reference_data_version', 'rda-2026-02-01'),
                'server_received_at'      => $now,
                'country_code'            => $assignment->country_code,
                'province_code'           => $assignment->province_code,
                'pheoc_code'              => $assignment->pheoc_code,
                'district_code'           => $assignment->district_code,
                'poe_code'                => $assignment->poe_code,
                'secondary_screening_id'  => $secId,
                'generated_from'          => $genFrom,
                'risk_level'              => $riskLevel,
                'alert_code'              => substr($alertCode, 0, 80),
                'alert_title'             => substr($alertTitle ?: $alertCode, 0, 150),
                'alert_details'           => $request->input('alert_details') ? substr($request->input('alert_details'), 0, 500) : null,
                'routed_to_level'         => $routedTo,
                'status'                  => 'OPEN',
                'acknowledged_by_user_id' => null,
                'acknowledged_at'         => null,
                'closed_at'               => null,
                'device_id'               => $request->input('device_id', 'unknown'),
                'app_version'             => $request->input('app_version'),
                'platform'                => in_array(strtoupper((string) $request->input('platform', 'ANDROID')), self::VALID_PLATFORMS, true)
                    ? strtoupper($request->input('platform'))
                    : 'ANDROID',
                'record_version'          => (int) $request->input('record_version', 1),
                'deleted_at'              => null,
                'sync_status'             => 'SYNCED',
                'synced_at'               => $now,
                'sync_attempt_count'      => 0,
                'last_sync_error'         => null,
                'created_at'              => $now,
                'updated_at'              => $now,
            ]);

            $newAlert = DB::table('alerts')->where('id', $alertId)->first();

            Log::info('[Alerts][store] Alert created', [
                'alert_id'     => $alertId,
                'alert_code'   => $alertCode,
                'risk_level'   => $riskLevel,
                'routed_to'    => $routedTo,
                'poe_code'     => $assignment->poe_code,
                'secondary_id' => $secId,
            ]);

            return $this->ok(
                $this->formatAlert($newAlert),
                'Alert created successfully.',
                ['server_id' => $alertId, 'idempotent' => false]
            );
        } catch (Throwable $e) {
            return $this->serverError($e, 'alerts store');
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // PATCH /alerts/{id}/acknowledge
    //
    // OPEN → ACKNOWLEDGED transition.
    // Role enforcement: only roles authorised for this routed_to_level
    // can acknowledge. A DISTRICT_SUPERVISOR cannot acknowledge a NATIONAL
    // level alert. A NATIONAL_ADMIN can acknowledge any level.
    //
    // IHR basis: Annex 2 — acknowledgement closes the reporting loop
    // and triggers the follow-up action chain.
    // ═════════════════════════════════════════════════════════════════════

    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $userId = (int) $request->input('user_id', 0);
        if ($userId <= 0) {
            return $this->err(422, 'user_id is required in request body.');
        }

        $user = $this->resolveUser($userId);
        if (! $user) {
            return $this->err(404, 'User not found.');
        }

        $assignment = $this->resolvePrimaryAssignment($userId);
        if (! $assignment) {
            return $this->err(403, 'No active assignment.');
        }

        try {
            $alert = DB::table('alerts')->where('id', $id)->whereNull('deleted_at')->first();
            if (! $alert) {
                return $this->err(404, 'Alert not found.', ['id' => $id]);
            }

            $scopeErr = $this->checkScope($alert, $assignment, $user);
            if ($scopeErr) {
                return $scopeErr;
            }

            if ($alert->status === 'CLOSED') {
                return $this->err(409, 'Alert is CLOSED — terminal state. Cannot acknowledge.', [
                    'alert_id'  => $id,
                    'closed_at' => $alert->closed_at,
                ]);
            }
            if ($alert->status === 'ACKNOWLEDGED') {
                return $this->ok($this->formatAlert($alert), 'Alert already acknowledged (idempotent).', [
                    'idempotent'              => true,
                    'acknowledged_at'         => $alert->acknowledged_at,
                    'acknowledged_by_user_id' => $alert->acknowledged_by_user_id,
                ]);
            }

            // Role authorisation for the alert's routing level
            $routedTo     = $alert->routed_to_level;
            $allowedRoles = self::ACKNOWLEDGE_ROLES[$routedTo] ?? [];
            $userRole     = $user->role_key ?? '';
            if (! in_array($userRole, $allowedRoles, true)) {
                return $this->err(403, "Your role ({$userRole}) is not authorised to acknowledge {$routedTo}-level alerts.", [
                    'routed_to_level' => $routedTo,
                    'required_roles'  => $allowedRoles,
                    'your_role'       => $userRole,
                    'ihr_basis'       => 'IHR Annex 2 — acknowledgement authority is tier-locked to the routing level.',
                ]);
            }

            $now = now()->format('Y-m-d H:i:s');

            DB::table('alerts')->where('id', $id)->update([
                'status'                  => 'ACKNOWLEDGED',
                'acknowledged_by_user_id' => $userId,
                'acknowledged_at'         => $now,
                'record_version'          => (int) $alert->record_version + 1,
                'updated_at'              => $now,
            ]);

            $updated = DB::table('alerts')->where('id', $id)->first();

            Log::info('[Alerts][acknowledge] Alert acknowledged', [
                'alert_id'   => $id,
                'by_user'    => $userId,
                'role'       => $userRole,
                'routed_to'  => $routedTo,
                'alert_code' => $alert->alert_code,
                'risk_level' => $alert->risk_level,
            ]);

            return $this->ok($this->formatAlert($updated), "Alert acknowledged by {$userRole}.", [
                'acknowledged_at'         => $now,
                'acknowledged_by_user_id' => $userId,
                'acknowledged_by_role'    => $userRole,
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e, 'alerts acknowledge');
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // PATCH /alerts/{id}/close
    //
    // OPEN → CLOSED  (direct, if alert was generated in error)
    // ACKNOWLEDGED → CLOSED  (normal closure after response actions)
    //
    // Only NATIONAL_ADMIN can close NATIONAL alerts.
    // PHEOC_OFFICER can close PHEOC and DISTRICT alerts.
    // DISTRICT_SUPERVISOR can close DISTRICT alerts only.
    // ═════════════════════════════════════════════════════════════════════

    public function close(Request $request, int $id): JsonResponse
    {
        $userId = (int) $request->input('user_id', 0);
        if ($userId <= 0) {
            return $this->err(422, 'user_id is required.');
        }

        $user = $this->resolveUser($userId);
        if (! $user) {
            return $this->err(404, 'User not found.');
        }

        $assignment = $this->resolvePrimaryAssignment($userId);
        if (! $assignment) {
            return $this->err(403, 'No active assignment.');
        }

        $closeReason = trim((string) $request->input('close_reason', ''));
        if (strlen($closeReason) < 5) {
            return $this->err(422, 'close_reason is required (minimum 5 characters) when closing an alert.', [
                'hint' => 'Explain why the alert is being closed: response completed, duplicate, or generated in error.',
            ]);
        }

        try {
            $alert = DB::table('alerts')->where('id', $id)->whereNull('deleted_at')->first();
            if (! $alert) {
                return $this->err(404, 'Alert not found.', ['id' => $id]);
            }

            $scopeErr = $this->checkScope($alert, $assignment, $user);
            if ($scopeErr) {
                return $scopeErr;
            }

            if ($alert->status === 'CLOSED') {
                return $this->ok($this->formatAlert($alert), 'Alert already closed (idempotent).', [
                    'idempotent' => true, 'closed_at' => $alert->closed_at,
                ]);
            }

            // Role authorisation for close
            $routedTo     = $alert->routed_to_level;
            $allowedRoles = self::ACKNOWLEDGE_ROLES[$routedTo] ?? [];
            $userRole     = $user->role_key ?? '';
            if (! in_array($userRole, $allowedRoles, true)) {
                return $this->err(403, "Your role ({$userRole}) is not authorised to close {$routedTo}-level alerts.", [
                    'required_roles' => $allowedRoles,
                    'your_role'      => $userRole,
                ]);
            }

            $now = now()->format('Y-m-d H:i:s');

            // Auto-acknowledge if closing from OPEN (direct close — skipping ACK)
            $ackAt = $alert->acknowledged_at;
            $ackBy = $alert->acknowledged_by_user_id;
            if ($alert->status === 'OPEN') {
                $ackAt = $now;
                $ackBy = $userId;
                Log::info('[Alerts][close] Direct close from OPEN — auto-acknowledging', [
                    'alert_id' => $id,
                    'by_user'  => $userId,
                ]);
            }

            $updatedDetails = $alert->alert_details
                ? $alert->alert_details . "\n[CLOSED: {$closeReason}]"
                : "[CLOSED: {$closeReason}]";

            DB::table('alerts')->where('id', $id)->update([
                'status'                  => 'CLOSED',
                'closed_at'               => $now,
                'acknowledged_by_user_id' => $ackBy,
                'acknowledged_at'         => $ackAt,
                'alert_details'           => substr($updatedDetails, 0, 500),
                'record_version'          => (int) $alert->record_version + 1,
                'updated_at'              => $now,
            ]);

            $updated = DB::table('alerts')->where('id', $id)->first();

            Log::info('[Alerts][close] Alert closed', [
                'alert_id'     => $id,
                'by_user'      => $userId,
                'role'         => $userRole,
                'close_reason' => $closeReason,
            ]);

            return $this->ok($this->formatAlert($updated), 'Alert closed.', [
                'closed_at'    => $now,
                'close_reason' => $closeReason,
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e, 'alerts close');
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═════════════════════════════════════════════════════════════════════

    private function resolveUser(int $id): ?object
    {
        return DB::table('users')->where('id', $id)->first() ?: null;
    }

    private function resolvePrimaryAssignment(int $userId): ?object
    {
        return DB::table('user_assignments')
            ->where('user_id', $userId)
            ->where('is_primary', 1)
            ->where('is_active', 1)
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->first() ?: null;
    }

    private function resolveSecondaryScreeningId(mixed $value): ?int
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value) && (int) $value > 0) {
            return DB::table('secondary_screenings')->where('id', (int) $value)->value('id') ?: null;
        }
        return DB::table('secondary_screenings')->where('client_uuid', (string) $value)->value('id') ?: null;
    }

    private function checkScope(object $alert, object $assignment, ?object $user): ?JsonResponse
    {
        $roleKey = $user->role_key ?? '';
        if (in_array($roleKey, ['POE_PRIMARY', 'POE_SECONDARY', 'POE_DATA_OFFICER', 'POE_ADMIN', 'SCREENER'], true)) {
            if ($alert->poe_code !== $assignment->poe_code) {
                return $this->err(403, 'Alert belongs to a different POE.', [
                    'alert_poe' => $alert->poe_code, 'user_poe' => $assignment->poe_code,
                ]);
            }
        } elseif ($roleKey === 'DISTRICT_SUPERVISOR') {
            if ($alert->district_code !== $assignment->district_code) {
                return $this->err(403, 'Alert is in a different district.');
            }
        } elseif ($roleKey === 'PHEOC_OFFICER') {
            if ($alert->pheoc_code !== $assignment->pheoc_code) {
                return $this->err(403, 'Alert is in a different PHEOC region.');
            }
        } else {
            if ($alert->country_code !== $assignment->country_code) {
                return $this->err(403, 'Alert is in a different country.');
            }
        }
        return null;
    }

    private function formatAlert(object $alert): array
    {
        // Derive IHR tier from routing level and alert code
        $ihrTier = null;
        if ($alert->routed_to_level === 'NATIONAL') {
            $ihrTier = str_starts_with($alert->alert_code ?? '', 'TIER1')
                ? 'TIER_1_ALWAYS_NOTIFIABLE'
                : 'TIER_2_ANNEX2';
        } elseif ($alert->routed_to_level === 'PHEOC') {
            $ihrTier = 'TIER_2_ANNEX2';
        }

        // Time since creation — relevant for IHR 24h notification tracking
        $createdAt  = $alert->created_at ?? null;
        $hoursSince = $createdAt ? round(now()->diffInMinutes($createdAt) / 60, 1) : null;
        $overdue24h = $hoursSince !== null && $hoursSince > 24 && ($alert->status ?? '') === 'OPEN';

        return [
            'id'                      => (int) $alert->id,
            'client_uuid'             => $alert->client_uuid,
            'reference_data_version'  => $alert->reference_data_version,
            'server_received_at'      => $alert->server_received_at,
            'country_code'            => $alert->country_code,
            'province_code'           => $alert->province_code,
            'pheoc_code'              => $alert->pheoc_code,
            'district_code'           => $alert->district_code,
            'poe_code'                => $alert->poe_code,
            'secondary_screening_id'  => (int) $alert->secondary_screening_id,
            'generated_from'          => $alert->generated_from,
            'risk_level'              => $alert->risk_level,
            'alert_code'              => $alert->alert_code,
            'alert_title'             => $alert->alert_title,
            'alert_details'           => $alert->alert_details,
            'routed_to_level'         => $alert->routed_to_level,
            'status'                  => $alert->status,
            'acknowledged_by_user_id' => $alert->acknowledged_by_user_id
                ? (int) $alert->acknowledged_by_user_id : null,
            'acknowledged_by_name'    => $alert->acknowledged_by_name ?? null,
            'acknowledged_at'         => $alert->acknowledged_at,
            'closed_at'               => $alert->closed_at,
            'device_id'               => $alert->device_id,
            'app_version'             => $alert->app_version,
            'platform'                => $alert->platform,
            'record_version'          => (int) $alert->record_version,
            'deleted_at'              => $alert->deleted_at,
            'sync_status'             => $alert->sync_status ?? 'SYNCED',
            'synced_at'               => $alert->synced_at,
            'created_at'              => $alert->created_at,
            'updated_at'              => $alert->updated_at,
            // Derived fields for UI
            'ihr_tier'                => $ihrTier,
            'hours_since_creation'    => $hoursSince,
            'overdue_24h'             => $overdue24h,
            // Joined fields (only present from index query)
            'case_status'             => $alert->case_status ?? null,
            'syndrome'                => $alert->syndrome ?? null,
            'traveler_gender'         => $alert->traveler_gender ?? null,
        ];
    }

    private function safeDatetime(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function ok(array $data, string $message, array $meta = []): JsonResponse
    {
        $body = ['success' => true, 'message' => $message, 'data' => $data];
        if (! empty($meta)) {
            $body['meta'] = $meta;
        }

        return response()->json($body, 200);
    }

    private function err(int $status, string $message, array $detail = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'error' => $detail], $status);
    }

    private function serverError(Throwable $e, string $ctx): JsonResponse
    {
        Log::error("[Alerts][ERROR] {$ctx}", [
            'exception' => get_class($e), 'message'        => $e->getMessage(),
            'file'      => basename($e->getFile()), 'line' => $e->getLine(),
        ]);
        return response()->json([
            'success' => false, 'message' => "Server error during: {$ctx}",
            'error' => [
                'exception' => get_class($e), 'message'        => $e->getMessage(),
                'file'      => basename($e->getFile()), 'line' => $e->getLine(),
            ],
        ], 500);
    }
}

<?php

declare (strict_types = 1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AlertFollowupsController
 *
 * Tracks the RTSL 14 early response actions per alert, per the 7-1-7
 * framework and IHR follow-up workflow.
 *
 * Routes:
 *   GET    /alerts/{id}/followups              → index by alert
 *   POST   /alerts/{id}/followups              → create follow-up
 *   PATCH  /alert-followups/{id}               → update follow-up status
 *   GET    /alerts/compliance                  → 7-1-7 + IHR compliance rollup
 *
 * Idempotent by client_uuid on create.
 */
final class AlertFollowupsController extends Controller
{
    private const VALID_STATUSES = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'BLOCKED', 'NOT_APPLICABLE'];

    // GET /alerts/{id}/followups?user_id=
    public function index(Request $request, int $alertId): JsonResponse
    {
        $userId = (int) $request->query('user_id', 0);
        if ($userId <= 0) {
            return $this->err(422, 'user_id is required.');
        }
        try {
            $rows = DB::table('alert_followups')
                ->where('alert_id', $alertId)
                ->whereNull('deleted_at')
                ->orderBy('due_at')
                ->orderBy('id')
                ->get();
            return $this->ok($rows->map(fn ($r) => (array) $r)->values()->all(), 'Follow-ups retrieved.');
        } catch (Throwable $e) {
            return $this->serverError($e, 'followups index');
        }
    }

    // POST /alerts/{id}/followups
    public function store(Request $request, int $alertId): JsonResponse
    {
        $userId = (int) $request->input('created_by_user_id', 0);
        if ($userId <= 0) {
            return $this->err(422, 'created_by_user_id is required.');
        }

        $alert = DB::table('alerts')->where('id', $alertId)->whereNull('deleted_at')->first();
        if (! $alert) {
            return $this->err(404, 'Alert not found.');
        }

        $clientUuid = (string) $request->input('client_uuid', '');
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $clientUuid)) {
            return $this->err(422, 'client_uuid must be a valid UUID v4.');
        }

        // Idempotent on client_uuid
        $existing = DB::table('alert_followups')->where('client_uuid', $clientUuid)->first();
        if ($existing) {
            return $this->ok((array) $existing, 'Follow-up already exists (idempotent).', ['idempotent' => true]);
        }

        $now = now()->format('Y-m-d H:i:s');
        $status = strtoupper((string) $request->input('status', 'PENDING'));
        if (! in_array($status, self::VALID_STATUSES, true)) {
            return $this->err(422, 'Invalid status.', ['valid' => self::VALID_STATUSES]);
        }

        try {
            $id = DB::table('alert_followups')->insertGetId([
                'client_uuid'           => $clientUuid,
                'alert_id'              => $alertId,
                'alert_client_uuid'     => $alert->client_uuid,
                'action_code'           => substr((string) $request->input('action_code', 'CUSTOM'), 0, 60),
                'action_label'          => substr((string) $request->input('action_label', 'Follow-up'), 0, 200),
                'status'                => $status,
                'due_at'                => $request->input('due_at'),
                'started_at'            => $status === 'IN_PROGRESS' ? $now : $request->input('started_at'),
                'completed_at'          => $status === 'COMPLETED' ? $now : $request->input('completed_at'),
                'completed_by_user_id'  => $status === 'COMPLETED' ? $userId : null,
                'assigned_to_user_id'   => $request->input('assigned_to_user_id'),
                'assigned_to_role'      => $request->input('assigned_to_role'),
                'notes'                 => substr((string) $request->input('notes', ''), 0, 500) ?: null,
                'evidence_ref'          => $request->input('evidence_ref'),
                'who_notification_reference' => $request->input('who_notification_reference'),
                'blocks_closure'        => (int) (bool) $request->input('blocks_closure', false),
                'country_code'          => $alert->country_code,
                'district_code'         => $alert->district_code,
                'poe_code'              => $alert->poe_code,
                'created_by_user_id'    => $userId,
                'device_id'             => $request->input('device_id', 'unknown'),
                'app_version'           => $request->input('app_version'),
                'platform'              => strtoupper((string) $request->input('platform', 'ANDROID')),
                'record_version'        => 1,
                'sync_status'           => 'SYNCED',
                'synced_at'             => $now,
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);
            $row = DB::table('alert_followups')->where('id', $id)->first();
            return $this->ok((array) $row, 'Follow-up created.', ['server_id' => $id]);
        } catch (Throwable $e) {
            return $this->serverError($e, 'followups store');
        }
    }

    // PATCH /alert-followups/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $userId = (int) $request->input('user_id', 0);
        if ($userId <= 0) {
            return $this->err(422, 'user_id is required.');
        }
        $row = DB::table('alert_followups')->where('id', $id)->whereNull('deleted_at')->first();
        if (! $row) {
            return $this->err(404, 'Follow-up not found.');
        }

        $now = now()->format('Y-m-d H:i:s');
        $update = ['updated_at' => $now, 'record_version' => (int) $row->record_version + 1];

        if ($request->has('status')) {
            $s = strtoupper((string) $request->input('status'));
            if (! in_array($s, self::VALID_STATUSES, true)) {
                return $this->err(422, 'Invalid status.');
            }
            $update['status'] = $s;
            if ($s === 'IN_PROGRESS' && ! $row->started_at) {
                $update['started_at'] = $now;
            }
            if ($s === 'COMPLETED') {
                $update['completed_at']        = $now;
                $update['completed_by_user_id'] = $userId;
            }
        }
        foreach (['notes', 'evidence_ref', 'who_notification_reference', 'assigned_to_user_id', 'assigned_to_role', 'due_at'] as $k) {
            if ($request->has($k)) {
                $update[$k] = $request->input($k);
            }
        }

        try {
            DB::table('alert_followups')->where('id', $id)->update($update);
            $fresh = DB::table('alert_followups')->where('id', $id)->first();
            return $this->ok((array) $fresh, 'Follow-up updated.');
        } catch (Throwable $e) {
            return $this->serverError($e, 'followups update');
        }
    }

    /**
     * GET /alerts/compliance?user_id=
     *
     * Returns a 7-1-7 + IHR compliance rollup scoped to the user's jurisdiction.
     * Enforcement logic: metrics are computed only from available fields; where
     * data is absent, the metric is returned with `computable: false` + a reason.
     */
    public function compliance(Request $request): JsonResponse
    {
        $userId = (int) $request->query('user_id', 0);
        if ($userId <= 0) {
            return $this->err(422, 'user_id is required.');
        }
        $user = DB::table('users')->where('id', $userId)->first();
        if (! $user) {
            return $this->err(404, 'User not found.');
        }
        $assignment = DB::table('user_assignments')
            ->where('user_id', $userId)
            ->where('is_primary', 1)
            ->where('is_active', 1)
            ->first();
        if (! $assignment) {
            return $this->err(403, 'No active assignment.');
        }

        try {
            $q = DB::table('alerts')->whereNull('deleted_at');
            $role = $user->role_key ?? '';
            if (in_array($role, ['POE_PRIMARY', 'POE_SECONDARY', 'POE_DATA_OFFICER', 'POE_ADMIN', 'SCREENER'], true)) {
                $q->where('poe_code', $assignment->poe_code);
            } elseif ($role === 'DISTRICT_SUPERVISOR') {
                $q->where('district_code', $assignment->district_code);
            } elseif ($role === 'PHEOC_OFFICER') {
                $q->where('pheoc_code', $assignment->pheoc_code);
            }

            $total = (clone $q)->count();
            $open   = (clone $q)->where('status', 'OPEN')->count();
            $acked  = (clone $q)->where('status', 'ACKNOWLEDGED')->count();
            $closed = (clone $q)->where('status', 'CLOSED')->count();

            // 7-1-7 notify stage: created_at → acknowledged_at ≤ 24h
            $notifyOnTarget = (clone $q)
                ->whereNotNull('acknowledged_at')
                ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, acknowledged_at) <= 24')->count();
            $notifyBreach = (clone $q)
                ->whereNotNull('acknowledged_at')
                ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, acknowledged_at) > 24')->count();
            $notifyPending = $open; // OPEN = never acknowledged

            // 7-1-7 respond stage: created_at → closed_at ≤ 168h
            $respondOnTarget = (clone $q)
                ->whereNotNull('closed_at')
                ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, closed_at) <= 168')->count();
            $respondBreach = (clone $q)
                ->whereNotNull('closed_at')
                ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, closed_at) > 168')->count();

            // IHR tier roll-up
            $tier1Count = (clone $q)->where('ihr_tier', 'LIKE', '%TIER_1%')->count();
            $tier2Count = (clone $q)->where('ihr_tier', 'LIKE', '%TIER_2%')->count();

            // Follow-up compliance
            $followupsTotal     = DB::table('alert_followups')->whereNull('deleted_at')->count();
            $followupsCompleted = DB::table('alert_followups')->whereNull('deleted_at')->where('status', 'COMPLETED')->count();
            $followupsOverdue   = DB::table('alert_followups')->whereNull('deleted_at')
                ->whereNotIn('status', ['COMPLETED', 'NOT_APPLICABLE'])
                ->whereNotNull('due_at')->where('due_at', '<', now())->count();

            return $this->ok([
                'scope' => [
                    'role'          => $role,
                    'country_code'  => $assignment->country_code,
                    'district_code' => $assignment->district_code ?? null,
                    'pheoc_code'    => $assignment->pheoc_code    ?? null,
                    'poe_code'      => $assignment->poe_code      ?? null,
                ],
                'counts' => compact('total', 'open', 'acked', 'closed'),
                'seven_one_seven' => [
                    'detect'  => [
                        'computable' => false,
                        'reason'     => 'Detection time requires emergence / onset timestamp which is not captured for every screening. Captured alerts only track detection-onwards timings.',
                    ],
                    'notify' => [
                        'computable'   => true,
                        'target_hours' => 24,
                        'on_target'    => $notifyOnTarget,
                        'breach'       => $notifyBreach,
                        'pending'      => $notifyPending,
                    ],
                    'respond' => [
                        'computable'   => true,
                        'target_hours' => 168,
                        'on_target'    => $respondOnTarget,
                        'breach'       => $respondBreach,
                    ],
                ],
                'ihr' => [
                    'tier1' => $tier1Count,
                    'tier2' => $tier2Count,
                ],
                'followups' => [
                    'total'     => $followupsTotal,
                    'completed' => $followupsCompleted,
                    'overdue'   => $followupsOverdue,
                ],
            ], 'Compliance rollup.');
        } catch (Throwable $e) {
            return $this->serverError($e, 'compliance');
        }
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
        Log::error("[AlertFollowups][ERROR] {$ctx}", ['exception' => get_class($e), 'message' => $e->getMessage()]);
        return response()->json(['success' => false, 'message' => "Server error: {$ctx}"], 500);
    }
}

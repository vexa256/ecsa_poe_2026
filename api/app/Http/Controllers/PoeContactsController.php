<?php

declare (strict_types = 1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PoeContactsController                                                   ║
 * ║  Manages per-POE notification contacts + escalation chains.              ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  RBAC:                                                                    ║
 * ║    NATIONAL_ADMIN — all POEs                                              ║
 * ║    POE_ADMIN      — only contacts for their assigned POE                  ║
 * ║    All others     — read-only                                             ║
 * ║                                                                           ║
 * ║  ROUTES:                                                                  ║
 * ║    GET    /poe-contacts?poe_code=&level=&user_id=                         ║
 * ║    POST   /poe-contacts                                                   ║
 * ║    GET    /poe-contacts/{id}                                              ║
 * ║    PATCH  /poe-contacts/{id}                                              ║
 * ║    DELETE /poe-contacts/{id}                                              ║
 * ║    GET    /poe-contacts/escalation-chain?poe_code=&level=                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class PoeContactsController extends Controller
{
    // NATIONAL_ADMIN has unrestricted access; PHEOC_OFFICER and
    // DISTRICT_SUPERVISOR may manage contacts within their own scope
    // (pheoc_code / district_code respectively); POE_ADMIN owns their POE.
    private const ADMIN_ROLES    = ['NATIONAL_ADMIN', 'POE_ADMIN', 'DISTRICT_SUPERVISOR', 'PHEOC_OFFICER'];
    private const VALID_LEVELS   = ['POE', 'DISTRICT', 'PHEOC', 'NATIONAL', 'WHO'];
    private const VALID_CHANNELS = ['EMAIL', 'SMS', 'BOTH'];

    /**
     * Returns true if $user may act on a contact with the given geographic
     * scope. NATIONAL_ADMIN always true. PHEOC_OFFICER restricted to own
     * pheoc_code. DISTRICT_SUPERVISOR restricted to own district_code.
     * POE_ADMIN restricted to own poe_code.
     */
    private function userCanManageScope(object $user, string $countryCode, string $districtCode, string $poeCode): bool
    {
        $role = $user->role_key ?? '';
        if ($role === 'NATIONAL_ADMIN') return true;
        if ($role === 'POE_ADMIN') {
            return $poeCode === ($user->poe_code ?? null);
        }
        if ($role === 'DISTRICT_SUPERVISOR') {
            return $districtCode === ($user->district_code ?? null);
        }
        if ($role === 'PHEOC_OFFICER') {
            // PHEOC officers operate on a country-level PHEOC scope; the
            // user.pheoc_code must be present. When the contact row lives
            // under a POE/DISTRICT with no explicit pheoc_code column, fall
            // back to country_code match.
            $userPheoc = $user->pheoc_code ?? null;
            if ($userPheoc) {
                // contacts table stores country/district/poe but not pheoc —
                // resolve via poes/districts would be ideal; for now match
                // country (PHEOC is always within a country).
                return $countryCode === ($user->country_code ?? null);
            }
            return $countryCode === ($user->country_code ?? null);
        }
        return false;
    }

    // ── GET /poe-contacts ───────────────────────────────────────────────────
    //
    // READ-ONLY UNIVERSAL ACCESS (2026-04-21 v3): every admin role sees every
    // contact across every scope. This lets DISTRICT and PHEOC admins
    // coordinate with peers, reference the national escalation chain, and
    // copy proven configurations. Write authority is still strictly gated by
    // userCanManageScope() — each row carries a `can_edit_by_me` flag so the
    // client can show lock icons on out-of-scope rows without re-implementing
    // the scope logic.
    //
    // Non-admin roles (SCREENER, POE_PRIMARY, POE_DATA_OFFICER, POE_SECONDARY)
    // are still capped at their own POE — there is no reason for a screener
    // to browse other POEs' contacts.
    public function index(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (! $user) return $this->err(403, 'Authentication required.');

        try {
            $q = DB::table('poe_notification_contacts')
                ->whereNull('deleted_at');

            // Non-admin roles: POE-lock. Admin roles (NATIONAL_ADMIN,
            // POE_ADMIN, DISTRICT_SUPERVISOR, PHEOC_OFFICER): unrestricted read.
            $role = $user->role_key ?? '';
            if (! in_array($role, self::ADMIN_ROLES, true)) {
                $q->where('poe_code', $user->poe_code ?? '');
            }

            if ($request->filled('poe_code'))      $q->where('poe_code', (string) $request->query('poe_code'));
            if ($request->filled('district_code')) $q->where('district_code', (string) $request->query('district_code'));
            if ($request->filled('country_code'))  $q->where('country_code', (string) $request->query('country_code'));
            if ($request->filled('level'))         $q->where('level', strtoupper((string) $request->query('level')));
            if ($request->filled('is_active'))     $q->where('is_active', (int) (bool) $request->query('is_active'));
            if ($request->filled('search')) {
                $s = '%' . (string) $request->query('search') . '%';
                $q->where(function ($qq) use ($s) {
                    $qq->where('full_name', 'LIKE', $s)
                       ->orWhere('email',    'LIKE', $s)
                       ->orWhere('phone',    'LIKE', $s)
                       ->orWhere('position', 'LIKE', $s)
                       ->orWhere('organisation', 'LIKE', $s);
                });
            }

            $rows = $q->orderBy('country_code')
                ->orderBy('district_code')
                ->orderBy('poe_code')
                ->orderByRaw("FIELD(level,'POE','DISTRICT','PHEOC','NATIONAL','WHO')")
                ->orderBy('priority_order')->orderBy('id')
                ->get();

            // Stamp per-row edit authority so the client mirrors the backend
            // exactly without re-implementing scope math.
            $out = $rows->map(function ($r) use ($user) {
                $row = (array) $r;
                $row['can_edit_by_me'] = $this->userCanManageScope(
                    $user,
                    (string) ($r->country_code ?? ''),
                    (string) ($r->district_code ?? ''),
                    (string) ($r->poe_code ?? ''),
                );
                return $row;
            })->values()->all();

            // Summary meta: counts + scope descriptor — lets the sidebar render
            // without a second request.
            $meta = [
                'total'         => count($out),
                'editable_here' => array_sum(array_map(fn ($r) => $r['can_edit_by_me'] ? 1 : 0, $out)),
                'your_role'     => $role,
                'your_scope'    => [
                    'country_code'  => $user->country_code  ?? null,
                    'district_code' => $user->district_code ?? null,
                    'pheoc_code'    => $user->pheoc_code    ?? null,
                    'poe_code'      => $user->poe_code      ?? null,
                ],
                'by_country'    => $this->groupCounts($out, 'country_code'),
                'by_district'   => $this->groupCounts($out, 'district_code'),
                'by_poe'        => $this->groupCounts($out, 'poe_code'),
                'by_level'      => $this->groupCounts($out, 'level'),
            ];

            return $this->ok($out, 'Contacts retrieved.', $meta);
        } catch (Throwable $e) {
            return $this->serverError($e, 'contacts index');
        }
    }

    /** Build a { key => count } map from a list of rows. */
    private function groupCounts(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $r) {
            $k = (string) ($r[$key] ?? '');
            if ($k === '') continue;
            $out[$k] = ($out[$k] ?? 0) + 1;
        }
        return $out;
    }

    // ── POST /poe-contacts ──────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $user = $this->requireAdmin($request);
        if ($user instanceof JsonResponse) return $user;

        $poeCode = trim((string) $request->input('poe_code', ''));
        $level   = strtoupper((string) $request->input('level', ''));
        $name    = trim((string) $request->input('full_name', ''));

        if (empty($poeCode) || empty($level) || empty($name)) {
            return $this->err(422, 'poe_code, level, full_name are required.');
        }
        if (! in_array($level, self::VALID_LEVELS, true)) {
            return $this->err(422, 'Invalid level.', ['valid' => self::VALID_LEVELS]);
        }

        // Scope guard: NATIONAL_ADMIN unrestricted; DISTRICT/PHEOC/POE admins
        // restricted to their own jurisdiction.
        $countryCode  = (string) $request->input('country_code',  $user->country_code  ?? 'UG');
        $districtCode = (string) $request->input('district_code', $user->district_code ?? '');
        if (! $this->userCanManageScope($user, $countryCode, $districtCode, $poeCode)) {
            return $this->err(403, 'You may only manage contacts within your own jurisdiction.', [
                'your_role'      => $user->role_key ?? '',
                'your_scope'     => [
                    'country_code'  => $user->country_code  ?? null,
                    'district_code' => $user->district_code ?? null,
                    'poe_code'      => $user->poe_code      ?? null,
                ],
                'contact_scope'  => compact('countryCode', 'districtCode', 'poeCode'),
            ]);
        }

        $email = trim((string) $request->input('email', ''));
        $phone = trim((string) $request->input('phone', ''));
        if (empty($email) && empty($phone)) {
            return $this->err(422, 'Provide at least one of email or phone.');
        }
        if (! empty($email) && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->err(422, 'Invalid email.');
        }

        $channel = strtoupper((string) $request->input('preferred_channel', 'EMAIL'));
        if (! in_array($channel, self::VALID_CHANNELS, true)) {
            return $this->err(422, 'Invalid preferred_channel.', ['valid' => self::VALID_CHANNELS]);
        }

        $now = now()->format('Y-m-d H:i:s');
        try {
            $id = DB::table('poe_notification_contacts')->insertGetId([
                'country_code'        => (string) $request->input('country_code', $user->country_code ?? 'UG'),
                'district_code'       => (string) $request->input('district_code', $user->district_code ?? ''),
                'poe_code'            => $poeCode,
                'level'               => $level,
                'full_name'           => substr($name, 0, 160),
                'position'            => substr((string) $request->input('position', ''), 0, 120) ?: null,
                'organisation'        => substr((string) $request->input('organisation', ''), 0, 160) ?: null,
                'phone'               => $phone ?: null,
                'alternate_phone'     => substr((string) $request->input('alternate_phone', ''), 0, 40) ?: null,
                'email'               => $email ?: null,
                'alternate_email'     => substr((string) $request->input('alternate_email', ''), 0, 160) ?: null,
                'priority_order'      => max(1, (int) $request->input('priority_order', 1)),
                'escalates_to_contact_id' => $request->input('escalates_to_contact_id') ?: null,
                'is_active'           => (int) (bool) $request->input('is_active', true),
                'receives_critical'   => (int) (bool) $request->input('receives_critical', true),
                'receives_high'       => (int) (bool) $request->input('receives_high', true),
                'receives_medium'     => (int) (bool) $request->input('receives_medium', false),
                'receives_low'        => (int) (bool) $request->input('receives_low', false),
                'receives_tier1'      => (int) (bool) $request->input('receives_tier1', true),
                'receives_tier2'      => (int) (bool) $request->input('receives_tier2', true),
                'receives_breach_alerts' => (int) (bool) $request->input('receives_breach_alerts', true),
                'receives_followup_reminders' => (int) (bool) $request->input('receives_followup_reminders', true),
                'receives_daily_report'       => (int) (bool) $request->input('receives_daily_report', false),
                'receives_weekly_report'      => (int) (bool) $request->input('receives_weekly_report', false),
                'preferred_channel'   => $channel,
                'notes'               => substr((string) $request->input('notes', ''), 0, 500) ?: null,
                'created_by_user_id'  => $user->id,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
            $row = DB::table('poe_notification_contacts')->where('id', $id)->first();
            return $this->ok((array) $row, 'Contact created.', ['server_id' => $id]);
        } catch (Throwable $e) {
            return $this->serverError($e, 'contacts store');
        }
    }

    // ── PATCH /poe-contacts/{id} ────────────────────────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->requireAdmin($request);
        if ($user instanceof JsonResponse) return $user;

        $row = DB::table('poe_notification_contacts')->where('id', $id)->whereNull('deleted_at')->first();
        if (! $row) return $this->err(404, 'Contact not found.');

        if (! $this->userCanManageScope($user, $row->country_code, $row->district_code, $row->poe_code)) {
            return $this->err(403, 'You may only edit contacts within your own jurisdiction.');
        }

        $patch = ['updated_by_user_id' => $user->id, 'updated_at' => now()->format('Y-m-d H:i:s')];
        foreach ([
            'full_name', 'position', 'organisation', 'phone', 'alternate_phone', 'email', 'alternate_email',
            'priority_order', 'escalates_to_contact_id', 'is_active',
            'receives_critical', 'receives_high', 'receives_medium', 'receives_low',
            'receives_tier1', 'receives_tier2', 'receives_breach_alerts',
            'receives_followup_reminders', 'receives_daily_report', 'receives_weekly_report',
            'preferred_channel', 'notes',
        ] as $k) {
            if ($request->has($k)) $patch[$k] = $request->input($k);
        }
        if (isset($patch['email']) && $patch['email'] !== null && ! filter_var($patch['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->err(422, 'Invalid email.');
        }
        if (isset($patch['preferred_channel']) && ! in_array(strtoupper((string) $patch['preferred_channel']), self::VALID_CHANNELS, true)) {
            return $this->err(422, 'Invalid preferred_channel.');
        }

        try {
            DB::table('poe_notification_contacts')->where('id', $id)->update($patch);
            $fresh = DB::table('poe_notification_contacts')->where('id', $id)->first();
            return $this->ok((array) $fresh, 'Contact updated.');
        } catch (Throwable $e) {
            return $this->serverError($e, 'contacts update');
        }
    }

    // ── DELETE /poe-contacts/{id} ───────────────────────────────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->requireAdmin($request);
        if ($user instanceof JsonResponse) return $user;

        $row = DB::table('poe_notification_contacts')->where('id', $id)->whereNull('deleted_at')->first();
        if (! $row) return $this->err(404, 'Contact not found.');

        if (! $this->userCanManageScope($user, $row->country_code, $row->district_code, $row->poe_code)) {
            return $this->err(403, 'You may only delete contacts within your own jurisdiction.');
        }
        try {
            DB::table('poe_notification_contacts')->where('id', $id)->update(['deleted_at' => now()]);
            return $this->ok([], 'Contact soft-deleted.');
        } catch (Throwable $e) {
            return $this->serverError($e, 'contacts destroy');
        }
    }

    // ── GET /poe-contacts/escalation-chain ──────────────────────────────────
    // Resolves the ordered list of contacts (including escalation backups) for a
    // (poe_code, level) tuple. Follows escalates_to_contact_id edges until a
    // terminal node or max-depth reached.
    public function escalationChain(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (! $user) return $this->err(403, 'Authentication required.');

        $poe   = (string) $request->query('poe_code', $user->poe_code ?? '');
        $level = strtoupper((string) $request->query('level', ''));
        if (empty($poe) || empty($level)) {
            return $this->err(422, 'poe_code and level are required.');
        }

        try {
            $primaries = DB::table('poe_notification_contacts')
                ->where('poe_code', $poe)->where('level', $level)
                ->where('is_active', 1)->whereNull('deleted_at')
                ->orderBy('priority_order')->orderBy('id')->get();

            $chain = [];
            $seen = [];
            foreach ($primaries as $p) {
                $chain[] = (array) $p; $seen[$p->id] = true;
                $cursor = $p->escalates_to_contact_id;
                $depth  = 0;
                while ($cursor && $depth < 5) {
                    if (isset($seen[$cursor])) break;
                    $next = DB::table('poe_notification_contacts')->where('id', $cursor)->whereNull('deleted_at')->first();
                    if (! $next) break;
                    $chain[] = (array) $next; $seen[$next->id] = true;
                    $cursor = $next->escalates_to_contact_id;
                    $depth++;
                }
            }
            return $this->ok(['chain' => $chain], 'Escalation chain resolved.');
        } catch (Throwable $e) {
            return $this->serverError($e, 'contacts escalationChain');
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Resolves the user row AND stitches the primary active user_assignment
    // onto it so scope fields (poe_code, district_code, pheoc_code,
    // province_code) are available directly on $user. Other controllers
    // split this into two DB calls — we inline it so scope guards do not
    // silently fail when the users row lacks these columns.
    private function authUser(Request $request): ?object
    {
        $userId = (int) ($request->input('user_id') ?? $request->query('user_id') ?? 0);
        if ($userId <= 0) return null;
        $user = DB::table('users')->where('id', $userId)->first();
        if (! $user) return null;
        $assignment = DB::table('user_assignments')
            ->where('user_id', $userId)
            ->where('is_primary', 1)
            ->where('is_active', 1)
            ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>', now()); })
            ->first();
        if ($assignment) {
            // Overlay assignment scope without clobbering user's country_code
            $user->country_code  = $user->country_code  ?? $assignment->country_code;
            $user->province_code = $assignment->province_code ?? null;
            $user->pheoc_code    = $assignment->pheoc_code    ?? null;
            $user->district_code = $assignment->district_code ?? null;
            $user->poe_code      = $assignment->poe_code      ?? null;
        }
        return $user;
    }
    private function requireAdmin(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->err(403, 'Authentication required.');
        $role = $user->role_key ?? '';
        if (! in_array($role, self::ADMIN_ROLES, true)) {
            return $this->err(403, 'Admin privileges required.', ['your_role' => $role, 'required_roles' => self::ADMIN_ROLES]);
        }
        return $user;
    }

    private function ok(array $data, string $message, array $meta = []): JsonResponse
    {
        $body = ['success' => true, 'message' => $message, 'data' => $data];
        if (! empty($meta)) $body['meta'] = $meta;
        return response()->json($body, 200);
    }
    private function err(int $status, string $message, array $detail = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'error' => $detail], $status);
    }
    private function serverError(Throwable $e, string $ctx): JsonResponse
    {
        Log::error("[PoeContacts][ERROR] {$ctx}", ['exception' => get_class($e), 'message' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
        return response()->json(['success' => false, 'message' => "Server error: {$ctx}"], 500);
    }
}

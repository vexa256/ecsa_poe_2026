<?php

declare (strict_types = 1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * UserController — POE Offline-First Screening System
 *
 * All authentication and authorization guards intentionally omitted.
 * Custom auth will be implemented as a separate layer.
 *
 * ════════════════════════════════════════════════════════════════
 * VERIFIED COLUMN MAP — poe_2026.sql, line-by-line
 * ════════════════════════════════════════════════════════════════
 *
 * TABLE: users
 *   id                bigint UNSIGNED NOT NULL AUTO_INCREMENT
 *   role_key          varchar(60)     DEFAULT NULL
 *   country_code      varchar(10)     DEFAULT NULL
 *   full_name         varchar(150)    DEFAULT NULL
 *   username          varchar(80)     DEFAULT NULL
 *   password_hash     varchar(255)    DEFAULT NULL
 *   email             varchar(190)    DEFAULT NULL
 *   phone             varchar(40)     DEFAULT NULL
 *   is_active         tinyint(1)      DEFAULT '1'
 *   last_login_at     datetime        DEFAULT NULL
 *   created_at        datetime        NOT NULL
 *   updated_at        datetime        NOT NULL
 *   email_verified_at timestamp       NULL DEFAULT NULL
 *   password          varchar(200)    DEFAULT NULL
 *   name              varchar(200)    DEFAULT NULL
 *
 * TABLE: user_assignments
 *   id                bigint UNSIGNED NOT NULL AUTO_INCREMENT
 *   user_id           bigint UNSIGNED NOT NULL
 *   country_code      varchar(10)     NOT NULL
 *   province_code     varchar(30)     DEFAULT NULL
 *   pheoc_code        varchar(30)     DEFAULT NULL
 *   district_code     varchar(30)     DEFAULT NULL
 *   poe_code          varchar(40)     DEFAULT NULL
 *   is_primary        tinyint(1)      NOT NULL DEFAULT '1'
 *   is_active         tinyint(1)      NOT NULL DEFAULT '1'
 *   starts_at         datetime        DEFAULT NULL
 *   ends_at           datetime        DEFAULT NULL
 *   created_at        datetime        NOT NULL
 *   updated_at        datetime        NOT NULL
 *
 * THERE IS NO client_uuid COLUMN ON EITHER TABLE.
 * client_uuid is accepted from the mobile payload for log tracing and is
 * echoed back in responses so the mobile can reconcile server IDs —
 * it is NEVER written to the database.
 *
 * THERE IS NO deleted_at COLUMN ON EITHER TABLE.
 * No soft-delete filters are applied anywhere in this controller.
 *
 * ════════════════════════════════════════════════════════════════
 */
final class UserController extends Controller
{
    // ----------------------------------------------------------------
    // CONSTANTS
    // ----------------------------------------------------------------

    /** All valid values for users.role_key VARCHAR(60). */
    private const VALID_ROLES = [
        'NATIONAL_ADMIN',
        'PHEOC_OFFICER',
        'DISTRICT_SUPERVISOR',
        'SCREENER',
    ];

    /**
     * ════════════════════════════════════════════════════════════════════════
     * GEOGRAPHIC SSOT CONSTANTS — sourced verbatim from POES.JS
     *
     * Rule: every value stored in user_assignments.province_code,
     * pheoc_code, district_code, and poe_code MUST exactly match one of the
     * strings below. No abbreviations, IDs, or aliases are accepted.
     *
     * These lists are the server-side mirror of window.POE_MAIN in POES.JS.
     * When POES.JS is updated these constants MUST be updated in lockstep.
     * ════════════════════════════════════════════════════════════════════════
     */

    /**
     * Valid values for user_assignments.province_code and pheoc_code.
     * Source: POES.JS → administrative_groups[].admin_level_1 where country = "Uganda".
     */
    private const VALID_PHEOC_NAMES = [
        'Arua RPHEOC',
        'Fort Portal RPHEOC',
        'Gulu RPHEOC',
        'Hoima RPHEOC',
        'Jinja RPHEOC',
        'Kabale RPHEOC',
        'Kampala RPHEOC',
        'Masaka RPHEOC',
        'Mbale RPHEOC',
        'National PHEOC',
    ];

    /**
     * Valid values for user_assignments.district_code.
     * Source: POES.JS → administrative_groups[].districts (all Uganda entries, flattened).
     */
    private const VALID_DISTRICT_NAMES = [
        // Arua RPHEOC
        'Koboko District',
        'Moyo District',
        'Nebbi District',
        'Zombo District',
        'Arua District',
        // Fort Portal RPHEOC
        'Kasese District',
        'Bundibugyo District',
        'Ntoroko District',
        'Kanungu District',
        // Gulu RPHEOC
        'Amuru District',
        'Lamwo District',
        // Hoima RPHEOC
        'Buliisa District',
        'Hoima District',
        // Jinja RPHEOC
        'Jinja District',
        'Namayingo District',
        // Kabale RPHEOC
        'Kabale District',
        'Kisoro District',
        'Ntungamo District',
        'Isingiro District',
        // Kampala RPHEOC
        'Kampala District',
        // Masaka RPHEOC
        'Rakai District',
        'Kyotera District',
        // Mbale RPHEOC
        'Busia District',
        'Tororo District',
        'Namisindwa District',
        'Bukwo District',
        // National PHEOC
        'Wakiso District',
    ];

    /**
     * Valid values for user_assignments.poe_code.
     * Source: POES.JS → poes[].poe_name where country = "Uganda".
     * These are the exact name strings — not IDs, not codes.
     */
    private const VALID_POE_NAMES = [
        // Gulu RPHEOC — Amuru District
        'Elegu / Atiak',
        // Gulu RPHEOC — Lamwo District
        'Ngoromoro',
        'Madi Opei',
        // Arua RPHEOC — Koboko District
        'Oraba',
        // Arua RPHEOC — Moyo District
        'Afogi',
        // Arua RPHEOC — Nebbi District
        'Goli',
        'Dei',
        // Arua RPHEOC — Zombo District
        'Paidha',
        'Padea',
        // Arua RPHEOC — Arua District
        'Vurra',
        'Odramachaka',
        'Lia',
        'Arua Airstrip',
        // Mbale RPHEOC — Busia District
        'Busia',
        // Mbale RPHEOC — Tororo District
        'Malaba',
        // Mbale RPHEOC — Namisindwa District
        'Lwakhakha',
        // Mbale RPHEOC — Bukwo District
        'Suam River',
        // Jinja RPHEOC — Jinja District
        'Kimaka Airstrip',
        'Jinja Port',
        // Jinja RPHEOC — Namayingo District
        'Lolwe Island',
        'Singulu Island',
        // Kabale RPHEOC — Kabale District
        'Katuna',
        'Kamwezi',
        // Kabale RPHEOC — Kisoro District
        'Cyanika',
        'Bunagana',
        // Kabale RPHEOC — Ntungamo District
        'Mirama Hills',
        'Kizinga',
        // Kabale RPHEOC — Isingiro District
        'Kikagati',
        'Bugango',
        // Fort Portal RPHEOC — Kasese District
        'Mpondwe',
        'Kayanja',
        // Fort Portal RPHEOC — Bundibugyo District
        'Busunga',
        // Fort Portal RPHEOC — Ntoroko District
        'Ntoroko',
        'Lwebisengo',
        // Fort Portal RPHEOC — Kanungu District
        'Ishasha',
        'Butogota',
        // Hoima RPHEOC — Buliisa District
        'Butiaba',
        'Wanseko',
        // Hoima RPHEOC — Hoima District
        'Kaiso-Tonya',
        'Sebagoro',
        // National PHEOC — Wakiso District
        'Entebbe International Airport',
        // Kampala RPHEOC — Kampala District
        'Port Bell',
        // Masaka RPHEOC — Rakai District
        'Mutukula',
        // Masaka RPHEOC — Kyotera District
        'Kansensero',
    ];

    /**
     * Minimum user_assignments geography required per role.
     * Drives enforceGeographyForRole() validation.
     */
    private const ROLE_GEO_REQUIREMENTS = [
        'NATIONAL_ADMIN'      => [],
        'PHEOC_OFFICER'       => ['province_or_pheoc'],
        'DISTRICT_SUPERVISOR' => ['province_or_pheoc', 'district_code'],
        'SCREENER'            => ['province_or_pheoc', 'district_code', 'poe_code'],
    ];

    // ----------------------------------------------------------------
    // ENDPOINTS
    // ----------------------------------------------------------------

    /**
     * GET /users
     *
     * Paginated list of users joined to their active primary assignment.
     * Filters: role_key, is_active, search (name/username/email/district/poe),
     * per_page, page.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_key'  => ['sometimes', 'string', Rule::in(self::VALID_ROLES)],
            'is_active' => ['sometimes', 'boolean'],
            'search'    => ['sometimes', 'string', 'max:100'],
            'per_page'  => ['sometimes', 'integer', 'min:1', 'max:200'],
            'page'      => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = $this->baseUserQuery();

        if (! empty($validated['role_key'])) {
            // filters on users.role_key VARCHAR(60)
            $query->where('u.role_key', $validated['role_key']);
        }

        if (isset($validated['is_active'])) {
            // filters on users.is_active TINYINT(1)
            $query->where('u.is_active', (int) $validated['is_active']);
        }

        if (! empty($validated['search'])) {
            $term = '%' . $validated['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('u.full_name', 'like', $term)      // users.full_name VARCHAR(150)
                    ->orWhere('u.name', 'like', $term)           // users.name VARCHAR(200)
                    ->orWhere('u.username', 'like', $term)       // users.username VARCHAR(80)
                    ->orWhere('u.email', 'like', $term)          // users.email VARCHAR(190)
                    ->orWhere('ua.district_code', 'like', $term) // user_assignments.district_code VARCHAR(30)
                    ->orWhere('ua.poe_code', 'like', $term);     // user_assignments.poe_code VARCHAR(40)
            });
        }

        $query->orderBy('u.created_at', 'desc'); // users.created_at datetime

        $perPage = (int) ($validated['per_page'] ?? 50);
        $result  = $query->paginate($perPage);

        return $this->ok([
            'items'        => $result->items(),
            'total'        => $result->total(),
            'per_page'     => $result->perPage(),
            'current_page' => $result->currentPage(),
            'last_page'    => $result->lastPage(),
        ]);
    }

    /**
     * POST /users
     *
     * Create a user from a mobile offline record.
     *
     * Password handling:
     *   Mobile sends plaintext in `password`. Hash::make() produces one bcrypt
     *   string written to both users.password (Laravel Auth) and
     *   users.password_hash (POE audit). Plaintext is never persisted.
     *
     * Name handling:
     *   The submitted full_name value is written to both users.full_name
     *   (POE identity) and users.name (Laravel auth display). Same string, two
     *   real columns that both exist in the schema.
     *
     * client_uuid handling:
     *   Accepted from payload, used for log tracing, echoed back in response.
     *   NOT written to the database — no such column exists on users.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateUserPayload($request, null);
        $this->enforceGeographyForRole($validated);

        try {
            $userId = DB::transaction(function () use ($validated): int {
                $now    = now()->toDateTimeString();
                $bcrypt = Hash::make($validated['password']);

                // ── INSERT users ───────────────────────────────────────────
                // Every key below is an exact column from poe_2026.users.
                // No key may be added here that is not in the verified column
                // map at the top of this file.
                $userId = DB::table('users')->insertGetId([
                    'role_key'          => $validated['role_key'],                   // varchar(60)
                    'country_code'      => $validated['country_code'],               // varchar(10)
                    'full_name'         => $validated['full_name'],                  // varchar(150)
                    'name'              => $validated['full_name'],                  // varchar(200) — same value, Laravel auth display
                    'username'          => strtolower(trim($validated['username'])), // varchar(80)
                    'email'             => $validated['email'] ?? null,              // varchar(190)
                    'phone'             => $validated['phone'] ?? null,              // varchar(40)
                    'password'          => $bcrypt,                                  // varchar(200) — Laravel Auth::attempt() reads this
                    'password_hash'     => $bcrypt,                                  // varchar(255) — POE audit copy
                    'email_verified_at' => null,                                     // timestamp
                    'is_active'         => (int) ($validated['is_active'] ?? 1),     // tinyint(1)
                    'last_login_at'     => null,                                     // datetime
                    'created_at'        => $now,                                     // datetime
                    'updated_at'        => $now,                                     // datetime
                ]);

                $this->upsertAssignment($userId, $validated['assignment'], $now);

                Log::info('[POE-USERS] User created', [
                    'server_id'   => $userId,
                    'role_key'    => $validated['role_key'],
                    'client_uuid' => $validated['client_uuid'] ?? null, // log only — not written to DB
                ]);

                return $userId;
            });
        } catch (Throwable $e) {
            Log::error('[POE-USERS] Store transaction failed', [
                'error'       => $e->getMessage(),
                'client_uuid' => $validated['client_uuid'] ?? null, // log only
            ]);
            return $this->fail('Failed to save user. Please retry.', 500);
        }

        return $this->ok(
            $this->buildResponseRecord($userId, $validated['client_uuid'] ?? null),
            'User created successfully.',
            201
        );
    }

    /**
     * PATCH /users/{id}
     *
     * Update an existing user.
     *
     * Password: null/absent = keep existing hash. Non-null = Hash::make() and
     * write to both users.password and users.password_hash simultaneously.
     *
     * client_uuid from payload is used for log tracing only, echoed in
     * response. NOT written to the database.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Confirm the row exists before validating the payload
        $target = DB::table('users')->where('id', $id)->first();
        if (! $target) {
            return $this->fail('User not found.', 404);
        }

        $validated = $this->validateUserPayload($request, $id);

        if (! empty($validated['assignment'])) {
            $this->enforceGeographyForRole($validated);
        }

        try {
            DB::transaction(function () use ($id, $validated, $target): void {
                $now      = now()->toDateTimeString();
                $fullName = $validated['full_name'] ?? $target->full_name;

                // ── UPDATE users ───────────────────────────────────────────
                // Every key below is an exact column from poe_2026.users.
                $update = [
                    'role_key'     => $validated['role_key'] ?? $target->role_key,         // varchar(60)
                    'country_code' => $validated['country_code'] ?? $target->country_code, // varchar(10)
                    'full_name'    => $fullName,                                           // varchar(150)
                    'name'         => $fullName,                                           // varchar(200) — kept in sync with full_name
                    'username'     => isset($validated['username'])
                        ? strtolower(trim($validated['username']))
                        : $target->username, // varchar(80)
                    'email'        => array_key_exists('email', $validated)
                        ? $validated['email']
                        : $target->email, // varchar(190)
                    'phone'        => array_key_exists('phone', $validated)
                        ? $validated['phone']
                        : $target->phone, // varchar(40)
                    'is_active'    => array_key_exists('is_active', $validated)
                        ? (int) $validated['is_active']
                        : $target->is_active,   // tinyint(1)
                    'updated_at'   => $now, // datetime
                ];

                // Re-hash only when a non-null plaintext password arrives.
                // users.password and users.password_hash are always written together.
                if (! empty($validated['password'])) {
                    $bcrypt                  = Hash::make($validated['password']);
                    $update['password']      = $bcrypt; // varchar(200)
                    $update['password_hash'] = $bcrypt; // varchar(255)
                }

                DB::table('users')->where('id', $id)->update($update);

                if (! empty($validated['assignment'])) {
                    $this->upsertAssignment($id, $validated['assignment'], $now);
                }

                Log::info('[POE-USERS] User updated', [
                    'server_id'   => $id,
                    'changed'     => array_keys($update),
                    'client_uuid' => $validated['client_uuid'] ?? null, // log only
                ]);
            });
        } catch (Throwable $e) {
            Log::error('[POE-USERS] Update transaction failed', [
                'server_id' => $id,
                'error'     => $e->getMessage(),
            ]);
            return $this->fail('Failed to update user. Please retry.', 500);
        }

        return $this->ok(
            $this->buildResponseRecord($id, $validated['client_uuid'] ?? null),
            'User updated successfully.'
        );
    }

    /**
     * GET /users/{id}
     *
     * Single user with their active primary assignment.
     */
    public function show(int $id): JsonResponse
    {
        $record = $this->buildResponseRecord($id, null);

        if (! $record) {
            return $this->fail('User not found.', 404);
        }

        return $this->ok($record);
    }

    /**
     * PATCH /users/{id}/status
     *
     * Lightweight is_active toggle. Minimal payload — no full re-validation.
     * Writes only users.is_active and users.updated_at, both real columns.
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $target = DB::table('users')->where('id', $id)->first();
        if (! $target) {
            return $this->fail('User not found.', 404);
        }

        $validated = $request->validate([
            'is_active'   => ['required', 'boolean'],
            // client_uuid accepted for log tracing — not written to DB
            'client_uuid' => ['sometimes', 'nullable', 'string', 'uuid'],
        ]);

        DB::table('users')
            ->where('id', $id)
            ->update([
                'is_active'  => (int) $validated['is_active'], // users.is_active tinyint(1)
                'updated_at' => now()->toDateTimeString(),     // users.updated_at datetime
            ]);

        Log::info('[POE-USERS] Status toggled', [
            'server_id'   => $id,
            'is_active'   => $validated['is_active'],
            'client_uuid' => $validated['client_uuid'] ?? null, // log only
        ]);

        return $this->ok(
            $this->buildResponseRecord($id, $validated['client_uuid'] ?? null),
            $validated['is_active'] ? 'User activated.' : 'User deactivated.'
        );
    }

    // ----------------------------------------------------------------
    // PRIVATE — VALIDATION
    // ----------------------------------------------------------------

    /**
     * Validate the incoming user payload for both POST (create) and PATCH (update).
     *
     * Rule anchoring:
     *   Every field rule below references its exact source column and type
     *   from poe_2026.users or poe_2026.user_assignments.
     *   client_uuid is the sole exception — it is NOT a DB column; it is
     *   accepted for mobile sync reconciliation only.
     *
     * @param  Request  $request
     * @param  int|null $userId  Server id of record being updated; null = create
     * @return array             Validated input
     * @throws ValidationException
     */
    private function validateUserPayload(Request $request, ?int $userId): array
    {
        $isUpdate = ($userId !== null);

        $rules = [
            // ── NOT a DB column — accepted for mobile tracing/reconciliation ──
            'client_uuid'              => ['sometimes', 'nullable', 'string', 'uuid'],

            // ── users.role_key varchar(60) ───────────────────────────────────
            'role_key'                 => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                Rule::in(self::VALID_ROLES),
            ],

            // ── users.country_code varchar(10) ───────────────────────────────
            'country_code'             => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:10',
            ],

            // ── users.full_name varchar(150) ─────────────────────────────────
            'full_name'                => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'min:2',
                'max:150',
            ],

            // ── users.name varchar(200) ──────────────────────────────────────
            // Optional in payload; server always writes full_name to this column.
            'name'                     => ['sometimes', 'nullable', 'string', 'max:200'],

            // ── users.username varchar(80) ───────────────────────────────────
            // uq_users_username uses utf8mb4_0900_ai_ci (case-insensitive).
            // The LOWER() clause mirrors that collation to surface a clean 422
            // before a DB constraint error fires.
            'username'                 => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'min:4',
                'max:80',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'username')
                    ->ignore($userId)
                    ->where(fn($q) => $q->whereRaw(
                        'LOWER(username) = ?',
                        [strtolower(trim($request->input('username', '')))]
                    )),
            ],

            // ── users.password varchar(200) ──────────────────────────────────
            // Required on create (mobile sends plaintext; hash created server-side).
            // Optional/nullable on update — null means keep existing hash.
            'password'                 => [
                $isUpdate ? 'sometimes' : 'required',
                'nullable',
                'string',
                'min:8',
                'max:200',
            ],

            // ── users.password_hash varchar(255) ─────────────────────────────
            // Mobile sends this field. Accepted but silently ignored —
            // the server derives it with Hash::make() in the write path.
            'password_hash'            => ['sometimes', 'nullable', 'string'],

            // ── users.email varchar(190) ─────────────────────────────────────
            // uq_users_email unique constraint; nullable; CI via LOWER().
            'email'                    => [
                'sometimes',
                'nullable',
                'email',
                'max:190',
                Rule::unique('users', 'email')
                    ->ignore($userId)
                    ->where(function ($q) use ($request) {
                        $email = trim($request->input('email', ''));
                        if ($email === '') {
                            return $q->whereRaw('1 = 0'); // skip uniqueness for null/empty
                        }
                        return $q->whereRaw('LOWER(email) = ?', [strtolower($email)]);
                    }),
            ],

            // ── users.phone varchar(40) ──────────────────────────────────────
            'phone'                    => ['sometimes', 'nullable', 'string', 'max:40'],

            // ── users.is_active tinyint(1) ───────────────────────────────────
            'is_active'                => ['sometimes', 'boolean'],

            // ── user_assignments nested object ───────────────────────────────
            // Every key below maps to an exact column in poe_2026.user_assignments.
            'assignment'               => [$isUpdate ? 'sometimes' : 'required', 'array'],
            'assignment.country_code'  => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:10'], // varchar(10) NOT NULL

            // ── user_assignments.province_code varchar(30) ───────────────────
            // Must exactly match an admin_level_1 value from POES.JS — the SSOT.
            // No abbreviations, aliases, or generated codes are accepted.
            'assignment.province_code' => [
                'sometimes', 'nullable', 'string', 'max:30',
                Rule::in(self::VALID_PHEOC_NAMES),
            ],

            // ── user_assignments.pheoc_code varchar(30) ──────────────────────
            // Same domain as province_code — both store the RPHEOC name string.
            'assignment.pheoc_code'    => [
                'sometimes', 'nullable', 'string', 'max:30',
                Rule::in(self::VALID_PHEOC_NAMES),
            ],

            // ── user_assignments.district_code varchar(30) ───────────────────
            // Must exactly match a district name from POES.JS administrative_groups.
            'assignment.district_code' => [
                'sometimes', 'nullable', 'string', 'max:30',
                Rule::in(self::VALID_DISTRICT_NAMES),
            ],

            // ── user_assignments.poe_code varchar(40) ────────────────────────
            // Must exactly match a poe_name from POES.JS — the SSOT name string.
            // e.g. "Mutukula", not "MTK", not "poe_001", not "UG-MAS-RAK-MUT-001".
            'assignment.poe_code'      => [
                'sometimes', 'nullable', 'string', 'max:40',
                Rule::in(self::VALID_POE_NAMES),
            ],

            'assignment.is_primary'    => ['sometimes', 'boolean'],                             // tinyint(1)
            'assignment.is_active'     => ['sometimes', 'boolean'],                             // tinyint(1)
            'assignment.starts_at'     => ['sometimes', 'nullable', 'date_format:Y-m-d H:i:s'], // datetime
            'assignment.ends_at'       => ['sometimes', 'nullable', 'date_format:Y-m-d H:i:s'], // datetime
        ];

        $messages = [
            'role_key.in'                      => 'Invalid role. Accepted: ' . implode(', ', self::VALID_ROLES),
            'username.unique'                  => 'Username is already taken on this system.',
            'username.regex'                   => 'Username may only contain letters, numbers, dots, underscores, and hyphens.',
            'email.unique'                     => 'Email address is already registered.',
            'password.min'                     => 'Password must be at least 8 characters.',
            'full_name.min'                    => 'Full name must be at least 2 characters.',
            'assignment.required'              => 'Geographic assignment details are required.',
            'assignment.country_code.required' => 'Assignment country code is required.',
            // Geography SSOT name-string enforcement
            'assignment.province_code.in'      => 'Invalid RPHEOC value. Must exactly match a name from the system reference list (e.g. "Gulu RPHEOC").',
            'assignment.pheoc_code.in'         => 'Invalid PHEOC value. Must exactly match a name from the system reference list (e.g. "Kabale RPHEOC").',
            'assignment.district_code.in'      => 'Invalid district value. Must exactly match a name from the system reference list (e.g. "Lamwo District").',
            'assignment.poe_code.in'           => 'Invalid POE value. Must exactly match a Point of Entry name from the system reference list (e.g. "Mutukula").',
        ];

        return $request->validate($rules, $messages);
    }

    /**
     * Validate geographic completeness for the submitted role_key.
     * Enforces National -> Province/PHEOC -> District -> POE hierarchy.
     *
     * Checks only the assignment sub-array fields — all of which are real
     * columns in user_assignments.
     *
     * @throws ValidationException
     */
    private function enforceGeographyForRole(array $validated): void
    {
        $roleKey    = $validated['role_key'] ?? null;
        $assignment = $validated['assignment'] ?? [];

        if (! $roleKey || empty($assignment)) {
            return;
        }

        $requirements = self::ROLE_GEO_REQUIREMENTS[$roleKey] ?? [];

        // user_assignments.province_code varchar(30) OR user_assignments.pheoc_code varchar(30)
        if (
            in_array('province_or_pheoc', $requirements, true)
            && empty($assignment['province_code'])
            && empty($assignment['pheoc_code'])
        ) {
            throw ValidationException::withMessages([
                'assignment.province_code' => 'RPHEOC / Province assignment is required for this role.',
            ]);
        }

        // user_assignments.district_code varchar(30)
        if (
            in_array('district_code', $requirements, true)
            && empty($assignment['district_code'])
        ) {
            throw ValidationException::withMessages([
                'assignment.district_code' => 'District assignment is required for this role.',
            ]);
        }

        // user_assignments.poe_code varchar(40)
        if (
            in_array('poe_code', $requirements, true)
            && empty($assignment['poe_code'])
        ) {
            throw ValidationException::withMessages([
                'assignment.poe_code' => 'Point of Entry assignment is required for the Screener role.',
            ]);
        }
    }

    // ----------------------------------------------------------------
    // PRIVATE — DATABASE HELPERS
    // ----------------------------------------------------------------

    /**
     * Base query builder: users LEFT JOIN user_assignments (primary active row only).
     *
     * SELECT list — every alias maps to an exact column in poe_2026.sql.
     * password, password_hash, email_verified_at excluded from all responses.
     *
     * users columns selected:
     *   id, role_key, country_code, full_name, name, username,
     *   email, phone, is_active, last_login_at, created_at, updated_at
     *
     * user_assignments columns selected (via alias):
     *   id → assignment_id, province_code, pheoc_code, district_code,
     *   poe_code, is_primary, is_active → assignment_is_active,
     *   starts_at, ends_at
     */
    private function baseUserQuery()
    {
        return DB::table('users as u')
            ->leftJoin('user_assignments as ua', function ($join) {
                $join->on('ua.user_id', '=', 'u.id')
                    ->where('ua.is_primary', '=', 1) // user_assignments.is_primary tinyint(1)
                    ->where('ua.is_active', '=', 1)  // user_assignments.is_active  tinyint(1)
                    ->whereNull('ua.ends_at');       // user_assignments.ends_at    datetime
            })
            ->select([
                'u.id',                                     // users.id               bigint
                'u.role_key',                               // users.role_key         varchar(60)
                'u.country_code',                           // users.country_code     varchar(10)
                'u.full_name',                              // users.full_name        varchar(150)
                'u.name',                                   // users.name             varchar(200)
                'u.username',                               // users.username         varchar(80)
                'u.email',                                  // users.email            varchar(190)
                'u.phone',                                  // users.phone            varchar(40)
                'u.is_active',                              // users.is_active        tinyint(1)
                'u.last_login_at',                          // users.last_login_at    datetime
                'u.created_at',                             // users.created_at       datetime
                'u.updated_at',                             // users.updated_at       datetime
                'ua.id            as assignment_id',        // user_assignments.id    bigint
                'ua.province_code',                         // user_assignments.province_code  varchar(30)
                'ua.pheoc_code',                            // user_assignments.pheoc_code     varchar(30)
                'ua.district_code',                         // user_assignments.district_code  varchar(30)
                'ua.poe_code',                              // user_assignments.poe_code       varchar(40)
                'ua.is_primary',                            // user_assignments.is_primary     tinyint(1)
                'ua.is_active     as assignment_is_active', // user_assignments.is_active   tinyint(1)
                'ua.starts_at',                             // user_assignments.starts_at      datetime
                'ua.ends_at',                               // user_assignments.ends_at        datetime
            ]);
    }

    /**
     * Upsert the primary user_assignment row — history-preserving.
     *
     * Every column written here is verified against poe_2026.user_assignments:
     *   user_id, country_code, province_code, pheoc_code, district_code,
     *   poe_code, is_primary, is_active, starts_at, ends_at, created_at, updated_at
     *
     * Strategy:
     *   1. Existing active primary row, same geography → update is_active only
     *      if it differs; return.
     *   2. Existing active primary row, geography changed → close it
     *      (is_active=0, is_primary=0, ends_at=now) to preserve audit history.
     *   3. Insert new active primary row.
     */
    private function upsertAssignment(int $userId, array $assignment, string $now): void
    {
        $newCountry  = $assignment['country_code'] ?? 'UG';       // user_assignments.country_code  varchar(10)
        $newProvince = $assignment['province_code'] ?? null;      // user_assignments.province_code varchar(30)
        $newPheoc    = $assignment['pheoc_code'] ?? $newProvince; // user_assignments.pheoc_code varchar(30)
        $newDistrict = $assignment['district_code'] ?? null;      // user_assignments.district_code varchar(30)
        $newPoe      = $assignment['poe_code'] ?? null;           // user_assignments.poe_code      varchar(40)
        $isPrimary   = (int) ($assignment['is_primary'] ?? 1);    // user_assignments.is_primary  tinyint(1)
        $isActive    = (int) ($assignment['is_active'] ?? 1);     // user_assignments.is_active   tinyint(1)
        $startsAt    = $assignment['starts_at'] ?? $now;          // user_assignments.starts_at   datetime
        $endsAt      = $assignment['ends_at'] ?? null;            // user_assignments.ends_at     datetime

        $existing = DB::table('user_assignments')
            ->where('user_id', $userId) // user_assignments.user_id bigint
            ->where('is_primary', 1)    // user_assignments.is_primary tinyint(1)
            ->where('is_active', 1)     // user_assignments.is_active  tinyint(1)
            ->whereNull('ends_at')      // user_assignments.ends_at    datetime
            ->first();

        $sameGeo = $existing && (
            ($existing->country_code ?? '') === ($newCountry ?? '') &&
            ($existing->province_code ?? '') === ($newProvince ?? '') &&
            ($existing->district_code ?? '') === ($newDistrict ?? '') &&
            ($existing->poe_code ?? '') === ($newPoe ?? '')
        );

        if ($existing && $sameGeo) {
            if ((int) $existing->is_active !== $isActive) {
                DB::table('user_assignments')
                    ->where('id', $existing->id)
                    ->update([
                        'is_active'  => $isActive, // user_assignments.is_active  tinyint(1)
                        'updated_at' => $now,      // user_assignments.updated_at datetime
                    ]);
            }
            return;
        }

        if ($existing) {
            // Close the existing primary row — preserve history
            DB::table('user_assignments')
                ->where('id', $existing->id)
                ->update([
                    'is_active'  => 0,    // user_assignments.is_active  tinyint(1)
                    'is_primary' => 0,    // user_assignments.is_primary tinyint(1)
                    'ends_at'    => $now, // user_assignments.ends_at    datetime
                    'updated_at' => $now, // user_assignments.updated_at datetime
                ]);
        }

        // Insert new primary active row
        DB::table('user_assignments')->insert([
            'user_id'       => $userId,      // user_assignments.user_id       bigint UNSIGNED
            'country_code'  => $newCountry,  // user_assignments.country_code  varchar(10)
            'province_code' => $newProvince, // user_assignments.province_code varchar(30)
            'pheoc_code'    => $newPheoc,    // user_assignments.pheoc_code    varchar(30)
            'district_code' => $newDistrict, // user_assignments.district_code varchar(30)
            'poe_code'      => $newPoe,      // user_assignments.poe_code      varchar(40)
            'is_primary'    => $isPrimary,   // user_assignments.is_primary    tinyint(1)
            'is_active'     => $isActive,    // user_assignments.is_active     tinyint(1)
            'starts_at'     => $startsAt,    // user_assignments.starts_at     datetime
            'ends_at'       => $endsAt,      // user_assignments.ends_at       datetime
            'created_at'    => $now,         // user_assignments.created_at    datetime
            'updated_at'    => $now,         // user_assignments.updated_at    datetime
        ]);
    }

    /**
     * Build the response record the mobile sync engine expects.
     *
     * $clientUuid is the mobile-supplied value echoed back so the mobile can
     * map server_id back to its local record. NOT sourced from DB (no column).
     *
     * Returns null if server id does not exist.
     */
    private function buildResponseRecord(int $userId, ?string $clientUuid): ?array
    {
        $row = $this->baseUserQuery()
            ->where('u.id', $userId)
            ->first();

        if (! $row) {
            return null;
        }

        return [
                                                       // users columns
            'id'            => $row->id,               // users.id            bigint  — mobile stores as server_user_id
            'client_uuid'   => $clientUuid,            // NOT from DB          — echoed from payload for mobile reconciliation
            'role_key'      => $row->role_key,         // users.role_key       varchar(60)
            'country_code'  => $row->country_code,     // users.country_code   varchar(10)
            'full_name'     => $row->full_name,        // users.full_name      varchar(150)
            'name'          => $row->name,             // users.name           varchar(200)
            'username'      => $row->username,         // users.username       varchar(80)
            'email'         => $row->email,            // users.email          varchar(190)
            'phone'         => $row->phone,            // users.phone          varchar(40)
            'is_active'     => (bool) $row->is_active, // users.is_active tinyint(1)
            'last_login_at' => $row->last_login_at,    // users.last_login_at  datetime
            'created_at'    => $row->created_at,       // users.created_at     datetime
            'updated_at'    => $row->updated_at,       // users.updated_at     datetime
                                                       // user_assignments columns
            'assignment'    => [
                'id'            => $row->assignment_id,                         // user_assignments.id            bigint
                'province_code' => $row->province_code,                         // user_assignments.province_code varchar(30)
                'pheoc_code'    => $row->pheoc_code,                            // user_assignments.pheoc_code    varchar(30)
                'district_code' => $row->district_code,                         // user_assignments.district_code varchar(30)
                'poe_code'      => $row->poe_code,                              // user_assignments.poe_code      varchar(40)
                'is_primary'    => (bool) ($row->is_primary ?? true),           // tinyint(1)
                'is_active'     => (bool) ($row->assignment_is_active ?? true), // tinyint(1)
                'starts_at'     => $row->starts_at,                             // user_assignments.starts_at     datetime
                'ends_at'       => $row->ends_at,                               // user_assignments.ends_at       datetime
            ],
            // Sync confirmation — read by mobile syncOne
            'sync_status'   => 'SYNCED',
            'synced_at'     => now()->toISOString(),
        ];
    }

    // ----------------------------------------------------------------
    // PRIVATE — RESPONSE HELPERS
    // ----------------------------------------------------------------

    /**
     * Standard success envelope.
     * Mobile syncOne reads: data?.data?.id -> stored as server_user_id
     */
    private function ok(mixed $data, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Standard error envelope.
     * Mobile syncOne reads: body.message -> stored as sync_note
     */
    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
        ], $status);
    }
}

/*
 * =============================================================================
 * ROUTE REGISTRATION — routes/api.php
 * =============================================================================
 *
 * use App\Http\Controllers\UserController;
 *
 * // No auth middleware — added separately as a custom layer.
 * Route::get   ('/users',             [UserController::class, 'index']);
 * Route::post  ('/users',             [UserController::class, 'store']);
 * Route::get   ('/users/{id}',        [UserController::class, 'show']);
 * Route::patch ('/users/{id}',        [UserController::class, 'update']);
 * Route::patch ('/users/{id}/status', [UserController::class, 'toggleStatus']);
 *
 * =============================================================================
 * App\Models\User — fillable / hidden / casts
 * =============================================================================
 *
 * Only real columns from poe_2026.users listed here.
 *
 * protected $fillable = [
 *     'role_key', 'country_code',
 *     'full_name', 'name',
 *     'username', 'email', 'phone',
 *     'password', 'password_hash',
 *     'email_verified_at',
 *     'is_active', 'last_login_at',
 * ];
 *
 * protected $hidden = ['password', 'password_hash', 'remember_token'];
 *
 * protected $casts = [
 *     'email_verified_at' => 'datetime',
 *     'is_active'         => 'boolean',
 * ];
 *
 * =============================================================================
 * IDEMPOTENCY NOTE — no client_uuid column in schema
 * =============================================================================
 *
 * Because users has no client_uuid column, duplicate-create prevention relies
 * on the unique constraints that DO exist in the schema:
 *   uq_users_username — users.username
 *   uq_users_email    — users.email
 *
 * If a mobile device retries a POST whose username already exists, the server
 * returns HTTP 422 with "Username is already taken". The mobile sync engine
 * should treat this specific message as SYNCED (the record exists) and use
 * GET /users with a search or a subsequent PATCH to retrieve the server id.
 *
 * =============================================================================
 */

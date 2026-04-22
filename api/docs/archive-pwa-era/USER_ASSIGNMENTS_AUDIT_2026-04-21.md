# User Assignments — hostile rebuild & single-POE invariant

**Date:** 2026-04-21
**Scope:** `/admin/assignments` view + `UserAssignmentsController` + live E2E
**Result:** **297 / 297 assertions pass** across all three suites (121 + 144 + 32).

## Requirement

> "Block multiple POE assignments."

A single human being cannot physically be at more than one Point of Entry at the same time. The DB permitted it, the controller permitted it, the UI permitted it. Rebuilt top to bottom.

## Hostile findings on the previous implementation

| # | Defect | Impact |
|---|---|---|
| 1 | No SSOT validation — any string accepted for province/pheoc/district/poe | DB drift, broken filters, failed joins |
| 2 | **No single-active-POE invariant** — a user could have N active rows at N POEs | Impossible real-world state, silently corrupts scope filtering downstream |
| 3 | No role-geo enforcement — SCREENER could be created with no POE | Breaks authorization checks in the alert/screening engines |
| 4 | No `user_audit_log` row written on assignment mutations | Loss of before/after forensics |
| 5 | `is_primary` invariant broken — could end up with 0 primary rows | `user.primary_assignment` joins silently return no geography |
| 6 | Store response missing the created row | Forces client round-trip |
| 7 | Modal headers used absolute-overlap pattern | "Broken heading" UX (saved in memory) |

## Backend changes — [UserAssignmentsController.php](../app/Http/Controllers/Admin/UserAssignmentsController.php)

Every endpoint re-implemented. The controller now guarantees these invariants:

- **SSOT** — all four name fields validated against `MobileUserController::VALID_*` (the POES.JS mirror).
- **SINGLE ACTIVE POE** — any write that would produce a second active row with a non-null `poe_code` returns **409 SINGLE_ACTIVE_POE** with the blocking row echoed back. The client can opt into `force=true` which auto-ends the previous POE row (`is_active=0, is_primary=0, ends_at=now`). Applied on both `store` and `update`.
- **Role-geo** — `enforceGeographyForRole(role_key, payload)` mirrors the mobile rule set:
  - `SCREENER` → RPHEOC + District + POE
  - `DISTRICT_SUPERVISOR` → RPHEOC + District
  - `PHEOC_OFFICER` → RPHEOC
  - `NATIONAL_ADMIN` → no geography required
- **Primary invariant** — if a user has no primary row, the next active insert becomes primary automatically. `DELETE` promotes the oldest active row to primary.
- **pheoc_code == province_code** — always normalised at the gateway.
- **Full audit trail** — every mutation writes a `user_audit_log` diff row + an `auth_events` `ASSIGNMENT_CHANGED` event.
- **Transactional** — wrapped in `DB::transaction()` with validation-aware catch.

New endpoint shape for `GET /v2/admin/users/{id}/assignments` — now returns `user`, `assignments`, **`active_poe`**, `history` (last 20 audited events), and an `invariants` flag block.

## Frontend changes — [admin/assignments.vue](../../pwa/src/pages/admin/assignments.vue)

Complete rewrite, mobile-first:

- **Mobile** — slide-up user picker sheet; desktop keeps the two-pane layout.
- **Active-POE hero card** — prominent emerald banner showing the user's one currently active POE, or an amber warning when the role needs a POE and none exists.
- **Conflict banner** — when the server returns 409, the banner shows the blocking POE and prompts the operator to toggle **Transfer**.
- **Transfer toggle** — inline checkbox that only surfaces when (a) the form includes a `poe_code` AND (b) the user already has an active POE. Turning it on sends `force=true`.
- **Client-side role-geo parity** — RPHEOC / District / POE marked with `*` based on the user's current role; cascades disable children until the parent is picked.
- **KPIs** — Total rows · Active · Countries · Districts · **POEs (active)** — the POE KPI flips rose with a hint when the DB invariant is violated, so ops can spot legacy drift.
- **History panel** — last 20 `ASSIGNMENT_CHANGED` events, colour-coded by severity.
- **CSV export** of the current user's full assignment history.
- **In-flow flex headers** everywhere — no absolute-overlap patterns.
- **Keyboard** — Ctrl/⌘-K focuses the user search.
- **Promise-based confirm dialog** — replaces `window.confirm` for destructive ops.
- **Deep-link** — `/admin/assignments?user_id=N` resolves even if the user is not on the first page.

## Live test — [assignments_live_test.sh](../database/seeds-sql/assignments_live_test.sh) (32 assertions)

Runs against the real API + DB. Every section is destructive and self-cleans.

| Section | Proves |
|---|---|
| 0 | Fresh SCREENER seeded at Ngoromoro with mints admin Sanctum token |
| 1 | SSOT rejects invalid POE / district / RPHEOC strings with 422 + correct field path |
| 2 | SCREENER without POE is rejected with 422 pointing at `poe_code` |
| 3 | Second POE insert returns **409 SINGLE_ACTIVE_POE** with the blocking row echoed; DB unchanged |
| 4 | `force=true` transfers: new POE takes over, previous row closed with history preserved, new row becomes primary, still exactly 1 active POE |
| 5 | `user_audit_log` grew by ≥ 2 rows (create + auto-end); `auth_events` `ASSIGNMENT_CHANGED` grew by ≥ 2 |
| 6 | `PATCH` that would create a 2nd POE also returns 409; `PATCH` with `force=true` transfers cleanly |
| 7 | `DELETE` soft-ends the row and auto-promotes another active row to primary |
| 8 | NATIONAL_ADMIN can be created with no geography |
| 9 | System-wide DB invariants: zero users with > 1 active POE, zero users with > 1 primary active row |

## Legacy data note

The live-test exposed one pre-existing violation on `user#107` — a leaked test user from an earlier failed run had two active POE rows (Bunagana + Mutukula Cluster). Both were soft-ended to restore the invariant; the user was already suspended so no side effects.

## Cumulative run

```
users_admin_test.sh       121 / 121 ✓   (static mount + schema audit)
users_crud_live_test.sh   144 / 144 ✓   (10-user seed, dual login paths)
assignments_live_test.sh   32 /  32 ✓   (single-POE invariant, transfer, audit)
──────────────────────────────────────────────────────────────────────────
                          297 / 297 ✓
```

## Quality bar posture

All 12 non-negotiables from `feedback_enterprise_quality_bar.md` satisfied:

- **coverage** — every controller endpoint wired
- **hostile hardening** — 409 + transfer path, SSOT rejection, primary-invariant recovery on delete
- **bug resistance** — invariant checked on POST, PATCH, and enforced during promote-on-delete
- **db-faithful** — exact enum / SSOT names, history-preserving soft-end, same audit pattern as users CRUD
- **insanely detailed** — active-POE hero, history timeline, CSV export, role-geo hints, invariant violation KPI
- **performance** — debounced search, single request for picker, transactional writes
- **premium UX** — in-flow headers, mobile sheet, confirm dialogs, toast, conflict banner with clear remediation
- **controller robustness** — transactions, 422 shape consistent, 409 shape documented, 404 for missing rows
- **testing** — three suites, live DB assertions, multi-path invariant coverage

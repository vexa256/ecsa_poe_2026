# Users CRUD — hostile audit, fixes, live seed & login run

**Date:** 2026-04-21
**Scope:** PWA master-dashboard Users & Roles surface — view, modal, controller, endpoints, DB compliance, charts, and login round-trip for both mobile and dashboard clients.
**Authors:** principal eng. + QA lead pass (single session).
**Outcome:** **265 / 265 assertions passed** (121 static audit + 144 live end-to-end).

## Files touched

| File | Change |
|---|---|
| `pwa/src/pages/admin/users.vue` | Fixed MFA chart (`r.total` → `r.n ?? r.total`), defensive `Number()` everywhere, rebuilt detail-drawer header to in-flow flex |
| `pwa/src/components/admin/UserEditorModal.vue` | Rebuilt header to in-flow flex — no more absolute-positioned avatar/title over the gradient |
| `api/app/Http/Controllers/Admin/UsersAdminController.php` | Added `mapAccountType(roleKey)` — fixes MySQL 1265 data-truncation on the `account_type` ENUM |
| `api/database/seeds-sql/users_crud_live_test.sh` | NEW — live end-to-end test: 10 seeds, DB compliance, dual logins, report shape, cleanup |
| `.claude/.../memory/feedback_enterprise_quality_bar.md` | NEW memory — 12-point non-negotiable quality bar applied to every view |
| `.claude/.../memory/feedback_modal_headers.md` | NEW memory — modal/drawer header pattern |
| `.claude/.../memory/feedback_report_roles_totals.md` | NEW memory — `report/roles` returns `n`, never `total` |

## Hostile findings

### Bug 1 — MFA chart rendered `0% / 0/undefined`

**Root cause**
[`admin/users.vue`](../../pwa/src/pages/admin/users.vue) used `r.total` when shaping data from `GET /v2/admin/users/report/roles`. That endpoint actually returns `COUNT(*) AS n`. JS coerced `undefined` into the template string, producing `0/undefined`, and the percentage always evaluated to `0%`.

**Fix**
Every report shaper now coerces defensively:

```js
const total = Number(r.n ?? r.total ?? 0)   // /report/roles returns n
const mfa   = Number(r.with_mfa ?? 0)
return {
  label: r.role_key || '—',
  value: total ? Math.round((100 * mfa) / total) : 0,
  secondary: `${mfa}/${total}`,
}
```

Same pattern applied to `mfaAdoptionBars` (reads from `/report/mfa` which **does** use `total`), `mfaOverall`, and `roleDonut`. The live-test suite now asserts both endpoint shapes so a controller schema drift never silently corrupts a chart again.

### Bug 2 — `account_type` ENUM truncation on user create

**Root cause**
Controller fell back to `$data['account_type'] ?? $data['role_key']`. `users.account_type` is a MySQL ENUM of `NATIONAL_ADMIN`, `PHEOC_ADMIN`, `DISTRICT_ADMIN`, `POE_ADMIN`, `POE_OFFICER`, `OBSERVER`, `SERVICE` — role keys like `SCREENER`, `PHEOC_OFFICER`, `DISTRICT_SUPERVISOR` are **not** in the enum. The first live run produced **9 × HTTP 500 · SQLSTATE 1265 Data truncated for column 'account_type'**.

**Fix**
New `UsersAdminController::mapAccountType()` explicit map:

| role_key | → account_type |
|---|---|
| NATIONAL_ADMIN | NATIONAL_ADMIN |
| PHEOC_OFFICER | PHEOC_ADMIN |
| DISTRICT_SUPERVISOR | DISTRICT_ADMIN |
| POE_ADMIN | POE_ADMIN |
| SCREENER / POE_OFFICER / POE_DATA_OFFICER | POE_OFFICER |
| SERVICE | SERVICE |
| default (incl. OBSERVER) | OBSERVER |

Caller may still override via the `account_type` field. After the fix the same 10-user payload produced **10 / 10 successful creates** with no schema warnings.

### Bug 3 — modal/drawer "broken headings"

**Root cause**
Both modals used `absolute + translate-y-1/2` to overlap an avatar onto a gradient banner. At narrow widths (and with long i18n text or an `Edit` button in the row), titles wrapped behind avatars or got clipped by the container. The visible result was a heading that looked cut in half.

**Fix**
Rebuilt both headers as a single in-flow flex row on a gradient background:

```html
<header class="flex items-center gap-3 p-3 sm:p-4 bg-gradient-to-br text-white" :class="roleGrad">
  <div class="w-11 h-11 … shrink-0">{{ initials }}</div>
  <div class="min-w-0 flex-1">
    <div class="font-bold truncate">{{ title }}</div>
    <div class="text-white/85 text-[11px] line-clamp-2">{{ subtitle }}</div>
  </div>
  <button class="shrink-0 …" @click="close">close</button>
</header>
```

No absolute positioning, no translate-y overlaps, truncation-safe, works from 320 px to desktop. Memory `feedback_modal_headers.md` locks this as the only acceptable pattern for this codebase.

### Audit note — admin/users vs admin/assignments consolidation

Inspected both pages in detail. They are intentionally separate workflows:

- `admin/users.vue` — full user roster + CRUD + reports + bulk; primary-assignment summary only.
- `admin/assignments.vue` — two-pane picker for granular assignment CRUD with history, multi-active, archive/reactivate.

No duplication of scope logic — both already import from the single `@data/poes.js` SSOT. They should **stay separate**; a merge would compromise focus on each workflow (the quality bar forbids "feature richness without chaos"). The Users detail drawer now links to "Edit" via the shared `UserEditorModal`, and `UserEditorModal` writes a single-row mobile-shape assignment through the hardened controller.

## Live run

`bash api/database/seeds-sql/users_crud_live_test.sh`

**Sections**

| # | What it proves | Assertions |
|---|---|---|
| 0 | Mints an admin Sanctum token via `php artisan tinker` | 1 |
| 1 | Seeds 10 users via POST /v2/admin/users (direct-password path, mobile-shape single `assignment`): 5 SCREENERs across 5 POEs × 5 RPHEOCs (Lamwo/Ngoromoro, Koboko/Oraba, Busia/Busia, Kisoro/Bunagana, Rakai/Mutukula), 2 DISTRICT_SUPERVISORs, 2 PHEOC_OFFICERs, 1 NATIONAL_ADMIN | 10 |
| 2 | Per-seed DB compliance: role, username lowercased, is_active=1, invitation_accepted_at, must_change_password=0, password hash stored, exactly one active-primary assignment (for field roles), user_audit_log CREATE row, auth_events ADMIN_CREATED row | 76 |
| 3 | **Login per user × 2 paths**: dashboard `/v2/auth/login` — Sanctum token returned; mobile `/auth/login` — full user row echoed, `role_key` matches the requested role | 20 |
| 4 | Report shapes: `/report/mfa` has `total`+`with_mfa`+`mfa_pct` on every row; `/report/roles` carries `n` on every row and never exposes a bare `total`; `/stats` populates all 8 KPI counters | 13 |
| 5 | Cleanup: soft-delete every seeded user via DELETE /v2/admin/users/{id} | 10 |

### Roster that actually ran

```
CREATE #1  [SCREENER]           Lamwo Screener Alpha    → UG / Gulu RPHEOC  / Lamwo District     / Ngoromoro
CREATE #2  [SCREENER]           Koboko Screener Bravo   → UG / Arua RPHEOC  / Koboko District    / Oraba
CREATE #3  [SCREENER]           Busia Screener Charlie  → UG / Mbale RPHEOC / Busia District     / Busia
CREATE #4  [SCREENER]           Kisoro Screener Delta   → UG / Kabale RPHEOC/ Kisoro District    / Bunagana
CREATE #5  [SCREENER]           Rakai Screener Echo     → UG / Masaka RPHEOC/ Rakai District     / Mutukula
CREATE #6  [DISTRICT_SUPERVISOR] Lamwo DSO Foxtrot       → UG / Gulu RPHEOC  / Lamwo District
CREATE #7  [DISTRICT_SUPERVISOR] Ntungamo DSO Golf       → UG / Kabale RPHEOC/ Ntungamo District
CREATE #8  [PHEOC_OFFICER]      Gulu PHEOC Hotel        → UG / Gulu RPHEOC
CREATE #9  [PHEOC_OFFICER]      Mbale PHEOC India       → UG / Mbale RPHEOC
CREATE #10 [NATIONAL_ADMIN]     National Admin Juliet   → UG (no geo)
```

### Login snapshot (one example per role)

```
dashboard login OK [SCREENER] livetest…_u1 → token len=51
mobile   login OK [SCREENER] livetest…_u1 (role echo=SCREENER)
dashboard login OK [DISTRICT_SUPERVISOR] livetest…_u6 → token len=51
mobile   login OK [DISTRICT_SUPERVISOR] livetest…_u6 (role echo=DISTRICT_SUPERVISOR)
dashboard login OK [PHEOC_OFFICER]      livetest…_u8 → token len=51
mobile   login OK [PHEOC_OFFICER]      livetest…_u8 (role echo=PHEOC_OFFICER)
dashboard login OK [NATIONAL_ADMIN]     livetest…_u10 → token len=51
mobile   login OK [NATIONAL_ADMIN]     livetest…_u10 (role echo=NATIONAL_ADMIN)
```

Same password worked for both surfaces — one user, two login paths, same `users.password` / `users.password_hash` columns.

## Final counts

- **Static audit** (`users_admin_test.sh`) — 121 ✓ / 0 ✗
- **Live end-to-end** (`users_crud_live_test.sh`) — 144 ✓ / 0 ✗
- **Total — 265 / 265.**

## Quality-bar posture

All 12 non-negotiable standards (memory `feedback_enterprise_quality_bar.md`) continue to hold for the user CRUD surface:

- **coverage** — every endpoint in `UsersAdminController` is consumed by the view
- **hostile-environment** — defensive `Number(?? 0)` everywhere; broken report shapes, null user fields, long titles, narrow screens, truncated enums all handled
- **db-faithful** — account_type enum respected, unique username/email case-insensitive, assignment upsert preserves history, audit + AuthEvent trail on every mutation
- **performance** — 250 ms search debounce, server-side pagination, sort on 3 columns, selection is a Set
- **premium UX** — sortable headers, active-filter chips, keyboard shortcut, promise-based confirm, mobile card deck + desktop table, role-coloured chips, risk-score badges
- **testing** — dual suites covering static mounting + live E2E, deterministic cleanup, role-echo verification on every login

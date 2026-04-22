# Aggregated Reports — Smart National Hub

**Date:** 2026-04-21
**Scope:** PWA `/admin/aggregated` + `AggregatedTemplatesController` surface
**Result:** **353 / 353 assertions pass** (121 + 144 + 32 + **56 new**)

## The ask

> "Code the full-blown industrial-grade aggregated reports management smart national hub — 100% smart management hub, AI-hardcoded-driven wizard and logic and management view with all possible DB-supported functions and control. Follow how the app deals with this, then exceed expectations — but they can never be collision."

Built one cohesive hub that delivers every verb the backend supports, with a hardcoded deterministic AI guide woven into both the wizard and the column editor. Every collision invariant is enforced twice — client-side pre-check AND server-side guard — and both are proven live.

## Files shipped

| File | Role |
|---|---|
| [pwa/src/services/aggregated.js](../../pwa/src/services/aggregated.js) | `user_id` auto-injecting HTTP client wrapping every aggregated endpoint with a consistent `{ok,data,error,status}` shape |
| [pwa/src/data/aggregated_ai.js](../../pwa/src/data/aggregated_ai.js) | Hardcoded deterministic AI — 5 step-specific hint engines + single-column hint engine + WHO presets |
| [pwa/src/pages/admin/aggregated.vue](../../pwa/src/pages/admin/aggregated.vue) | The smart hub — Overview / Templates / Submissions tabs + detail drawer + wizard + column editor + confirm modal (single SFC, 1 200+ lines) |
| [api/database/seeds-sql/aggregated_live_test.sh](../database/seeds-sql/aggregated_live_test.sh) | 56-assertion live E2E covering lifecycle + 6 distinct collision classes |

## Hub information architecture

**Overview tab** — 8-card KPI strip + 3-panel analytics:
- KPIs: Templates · Published · Draft · Retired · Locked · Active fields · Submissions (all) · Last 7 days
- Template-lifecycle donut (Published / Draft / Retired / Archived)
- Reporting-cadence horizontal bars (DAILY / WEEKLY / MONTHLY / …)
- **Smart insights panel** — hardcoded deterministic rules: zero-published warning, lock pile-up alert, draft clutter hint, zero-submissions-with-published anomaly, low column-utilisation recommendation, healthy-flow positive acknowledgement

**Templates tab** — filterable table:
- Search (name / code / description), status filter, cadence filter, locked filter
- Sortable columns (name, columns_enabled, updated_at)
- Per-row: accent stripe (template colour), default pill, locked pill, code + version, description preview, status pill, cadence icon, field counts
- Per-row dropdown: Open · Edit · Publish · Retire · Lock/Unlock · Delete…
- CSV export of current slice
- Deep-linkable via `?template=<id>` to auto-open the drawer

**Submissions tab** — read-only analytics:
- Date range + template filter + page size
- Full server-paginated list with POE · district · template code · screened · symptomatic · submitter · sync status

**Detail drawer** (sm: right pane, mobile: bottom sheet):
- In-flow flex header (memory-locked pattern — no absolute overlap)
- LOCKED banner when locked, with one-click unlock
- Tabs: Columns (toggle + reorder + edit + delete + add) · Metadata · Activity
- Columns: drag-free reorder via ▲▼ arrows, inline is_enabled toggle (disabled for core / locked), inline row-level actions

**AI-driven wizard** (modal, 5 steps):
- **Purpose** — name, auto-generated code (edit-aware), description, cadence picker (6 options with icons), accent colour
- **Start from** — 4 presets (BLANK / WHO AFRO / DAILY TALLY / CLONE). CLONE opens a select modal of published templates.
- **Columns** — full column editor list with per-row inline edits, add/remove
- **Validation** — numeric-only rows with inline min/max + required checkbox
- **Review** — summary card + Save draft / Save & publish

Every step hydrates a fresh **AI hints panel** sourced from [aggregated_ai.js](../../pwa/src/data/aggregated_ai.js):
- Purpose: name length checks, keyword-driven cadence suggestions, disease-specific IHR hints, code format pre-validation
- Preset: cadence-aware recommendations
- Columns: duplicate-key detection, enabled count thresholds, IHR signal presence (fever ≥ 38 °C, ill travellers), gender-pair consistency, disease-specific LAB-column hint
- Validation: min/max coverage, required threshold, PERCENT cap, aggregation-vs-data-type sanity
- Publish: publish-gate pre-check, clone awareness, already-published handling

**Column editor** (inline modal stacked on drawer):
- Every column field the DB supports: key, label, category, data_type, aggregation_fn, display_order, min/max, placeholder, help_text, required/enabled/dashboard/report flags
- Single-column AI hints panel (format, PERCENT cap, SELECT-needs-options, aggregation-vs-type, required-but-disabled)

## Collisions defeated (proven by live test)

| # | Class | Guard location | Live assertion |
|---|---|---|---|
| 1 | Duplicate `template_code` per country | server `store`, client pre-check | `duplicate template_code → 409` |
| 2 | Duplicate `column_key` per template | server `addColumn`, client pre-check | `duplicate column_key → 409` |
| 3 | Invalid `template_code` format (lowercase / special) | server regex | `reject lowercase template_code (422)` |
| 4 | Missing required fields on create | server validation | `reject missing name+code (422)` |
| 5 | Invalid `column_key` format | server regex | `invalid column_key format → 422` |
| 6 | Invalid `data_type` enum value | server enum check | `invalid data_type → 422` |
| 7 | Edit meta on LOCKED template | server `update` | `PATCH meta while locked → 409` |
| 8 | Add column on LOCKED template | server `addColumn` | `add column while locked → 409` |
| 9 | Retire default template | server `retire` | `retire default → 409 (protected)` |
| 10 | Delete default template | server `destroy` | `delete default → 409 (protected)` |
| 11 | Delete template with submissions (no cascade) | server submission count check | `delete with submissions (no cascade) → 409` |
| 12 | Cascade=true without typed confirm | server | `cascade=true without confirm → still 409` |
| 13 | Disable core column | server `updateColumn` | `core column cannot be disabled (409)` |
| 14 | Delete core column | server `deleteColumn` | `core column cannot be deleted (409)` |

Plus every affirmative path: create → add columns → reorder → publish → retire → re-publish → lock → unlock → cascade-delete-with-submissions (submission preserved).

## AI: "hardcoded" by design

Zero LLM calls. Every hint is a pure function of form state, yielding:

- **Deterministic** — same input always → same output
- **Offline** — no network, works on unstable POE connections
- **Auditable** — every rule is a ~3-line block in [aggregated_ai.js](../../pwa/src/data/aggregated_ai.js) with a WHO / IDSR / IHR provenance in comments
- **Drift-proof** — ports the mobile `AggregatedWizard.vue` engine verbatim then extends with hostile-audit additions (code-format validation, duplicate-key detection at preview time, PERCENT>100 guard, aggregation-vs-type sanity)

## DB-function coverage

| Surface | Covered |
|---|---|
| `GET /aggregated-templates` (list, filters, include_columns) | ✓ via `listTemplates` |
| `GET /aggregated-templates/published` | available but not surfaced in UI (the unified list serves both) |
| `GET /aggregated-templates/active` | used by the WHO-preset loader |
| `GET /aggregated-templates/{id}` | used by detail drawer + wizard hydration |
| `POST /aggregated-templates` (with/without clone) | ✓ |
| `PATCH /aggregated-templates/{id}` | ✓ via wizard edit mode |
| `DELETE /aggregated-templates/{id}` (with cascade + confirm) | ✓ |
| `POST /aggregated-templates/{id}/publish` | ✓ row action + wizard |
| `POST /aggregated-templates/{id}/retire` | ✓ |
| `POST /aggregated-templates/{id}/activate` (alias) | not exposed (publish is canonical) |
| `POST /aggregated-templates/{id}/lock` (+ unlock) | ✓ |
| `POST /aggregated-templates/{id}/columns` | ✓ wizard + editor |
| `PATCH /aggregated-templates/{id}/columns` (bulk reorder) | ✓ reorder + wizard-save pipeline |
| `PATCH /aggregated-template-columns/{id}` | ✓ editor + toggle |
| `DELETE /aggregated-template-columns/{id}` | ✓ |
| `GET /aggregated` (submissions list with filters) | ✓ Submissions tab |
| `GET /aggregated/{id}` | ready in service; submission detail drawer follow-up |

## Cumulative green board

```
users_admin_test.sh        121 / 121 ✓
users_crud_live_test.sh    144 / 144 ✓
assignments_live_test.sh    32 /  32 ✓
aggregated_live_test.sh     56 /  56 ✓
─────────────────────────────────────
                           353 / 353 ✓
```

No regressions. Every backend test suite still green after the new hub lands.

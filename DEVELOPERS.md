# POE Sentinel — Developer Guide (2026-04-21 expansion + v2)

> **Read this alongside [`PROJECT-GUIDLINES.md`](./PROJECT-GUIDLINES.md).** That file
> describes the foundational screening + notification workflows. This one
> documents the **aggregated template engine**, **report hub + wizard**,
> **POE notification contacts**, **notification engine**, **alert intelligence
> hub**, and the **RBAC opening** layered on top on 2026-04-21.
>
> The audit test suite at [`api/database/seeds-sql/audit_test.sh`](./api/database/seeds-sql/audit_test.sh)
> is the executable specification — if you change anything in this area, run it
> and keep it green (**85 assertions**, 0 failures).

---

## 1. What shipped in the 2026-04-21 expansion

| Area | Artefacts |
|---|---|
| **Aggregated reports v2** — multi-published templates per country, hub + dynamic submission wizard + admin wizard | 3 tables · 1 IDB cache store · 1 controller (publish/retire) · **3 new Vue views** (Hub, dynamic Submission, 5-step Wizard) |
| Country-customisable aggregated templates | 3 tables · 1 controller · 1 admin view · integrated into `AggregatedData.vue` |
| POE notification contacts (escalation) | 1 table · 1 controller · 1 admin view |
| Notification engine (alerts / escalations / reminders / reports) | 2 tables · 1 controller · 12 seeded templates |
| 7-1-7 alert follow-up tracker (RTSL 14 actions) | 1 table · 1 controller · tab in `AlertIntelligence.vue` |
| Alert Intelligence Hub / Matrix / History | 3 views · 1 composable (hardcoded IHR rules) |
| RBAC opening | NATIONAL unrestricted · PHEOC/DISTRICT/POE admins scope-locked |

All additions are **additive only**. No existing column was altered, no existing
endpoint changed shape, and no migration drops any table.

---

## 2. Database — the 7 new tables

All seven tables are declared in [`app.sql`](./app.sql) (canonical source) and
the Laravel migration
[`api/database/migrations/2026_04_21_000001_*`](./api/database/migrations/)
and
[`api/database/migrations/2026_04_21_000002_*`](./api/database/migrations/).
For environments where Laravel's `migrations` table is out of sync, apply
the idempotent SQL at
[`api/database/seeds-sql/`](./api/database/seeds-sql/) directly via `mysql`.

### 2.1 `aggregated_templates`
One row per (country, template). **Many templates may be PUBLISHED
simultaneously per country** — each one is a separate report type (e.g. "Daily
POE Screening Tally", "Weekly VHF Surveillance", "Monthly Vaccination
Coverage"). POE users see the full list at `/aggregated-data` and pick which
one to submit.

- `status` (enum DRAFT · PUBLISHED · RETIRED · ARCHIVED) — the authoritative
  signal. Only PUBLISHED templates appear in POE users' hub. `is_active`
  mirrors `status = PUBLISHED` for back-compat.
- `reporting_frequency` (DAILY · WEEKLY · MONTHLY · QUARTERLY · AD_HOC · EVENT)
  — pure presentation metadata; groups cards in the hub.
- `icon`, `colour` — optional presentation hints for card accent.
- `published_at`, `published_by_user_id`, `retired_at`, `retired_by_user_id`
  — audit trail for the status lifecycle.
- `is_default=1` — the system-seeded WHO-baseline template. Cannot be deleted
  or retired. There is exactly one per country.
- `locked=1` — no edits/deletes accepted (for "published & signed-off"
  templates). Publish/retire still work even when locked.
- `version` — monotonic version counter. Increment on breaking column changes.

### 2.2 `aggregated_template_columns`
Defines every field on a template.

| column | meaning |
|---|---|
| `is_core` | System-required. Cannot be disabled or deleted (enforced in API + audit test). |
| `is_required` | User must fill this in when submitting. |
| `is_enabled` | Hidden from the submission form + dashboards when 0. |
| `data_type` | `INTEGER` `DECIMAL` `TEXT` `BOOLEAN` `DATE` `PERCENT` `SELECT` |
| `aggregation_fn` | `SUM` `AVG` `MIN` `MAX` `COUNT` `LATEST` `NONE` — how dashboards roll this column up. |
| `dashboard_visible`, `report_visible` | Surface flags for downstream rendering. |
| `select_options` (JSON) | Dropdown choices when `data_type='SELECT'`. |
| `validation_rules` (JSON) | Optional per-column validators. |

**The 7 core columns** — never disable these, they are rendered as fixed fields
in [`AggregatedData.vue`](./src/views/AggregatedData.vue):
`total_screened`, `total_male`, `total_female`, `total_other`,
`total_unknown_gender`, `total_symptomatic`, `total_asymptomatic`.

### 2.3 `aggregated_submission_values`
EAV store for dynamic column values. **Existing fixed columns on
`aggregated_submissions` (total_screened, gender counts, symptom counts) are
untouched** — they stay as dedicated columns for backward-compat and dashboard
query ergonomics. Everything beyond the seven fixed fields writes to this
table. Dashboards must `LEFT JOIN aggregated_submissions` + `aggregated_submission_values`.

### 2.4 `poe_notification_contacts`
Recipients of alert/escalation emails per POE per level.

- `level` ∈ `POE | DISTRICT | PHEOC | NATIONAL | WHO`
- `priority_order` — within a level, 1 = primary, 2+ = backups
- `escalates_to_contact_id` — self-reference for automatic escalation chain
- 10 `receives_*` flags — filter who gets what (critical/high/medium/low,
  tier1/tier2, breach_alerts, followup_reminders, daily/weekly reports)

### 2.5 `notification_templates` + `notification_log`
12 seeded email templates with Mustache-style `{{token}}` placeholders. Every
send attempt is logged with status, error, retry_count.

### 2.6 `alert_followups`
RTSL 14 early response actions tracker per alert. `blocks_closure=1` means the
alert cannot be closed until this action is `COMPLETED` or `NOT_APPLICABLE`.

---

### 2.7 `aggregated_templates_cache` (IndexedDB only)
Client-side IDB store (added in schema v15). Holds the full payload of every
PUBLISHED template returned by `/aggregated-templates/published`. Keyed by
server template `id`. Refreshed by [`AggregatedHub.vue`](./src/views/AggregatedHub.vue)
on mount, on every view enter, on `window.online` event, and every 30s while
the hub is open. **This is why a POE user sees a new template within seconds
of an admin publishing it** — the cache overwrite is a full replace, so admin
`retire` also propagates instantly.

[`AggregatedData.vue`](./src/views/AggregatedData.vue) reads from this cache
first, then overlays a fresh `GET /aggregated-templates/{id}` if online. A
user with a stale cache and no network still gets the last-known template
schema and can submit.

---

## 3. API endpoints

All endpoints take `user_id` as query param (GET) or body field (POST/PATCH).
There is **no auth header** (per LAW 2 in `PROJECT-GUIDLINES.md`).

### 3.1 Aggregated templates

| method | path | role | purpose |
|---|---|---|---|
| GET  | `/aggregated-templates/published?country_code=` | any | **All PUBLISHED templates + inline columns.** Hub uses this. |
| GET  | `/aggregated-templates/active?country_code=` | any | First-published template (legacy) |
| GET  | `/aggregated-templates?country_code=&status=&include_columns=` | any | List with optional status filter |
| POST | `/aggregated-templates` | **NATIONAL_ADMIN** | Create DRAFT template (fields: name, code, description, reporting_frequency, icon, colour, clone_default_columns) |
| GET  | `/aggregated-templates/{id}` | any | One template + all columns |
| PATCH | `/aggregated-templates/{id}` | NATIONAL_ADMIN | Update name/desc/metadata/icon/colour/reporting_frequency |
| DELETE | `/aggregated-templates/{id}` | NATIONAL_ADMIN | Soft delete (blocked for default) |
| POST | `/aggregated-templates/{id}/publish` | NATIONAL_ADMIN | DRAFT/RETIRED → PUBLISHED (requires ≥1 enabled column) |
| POST | `/aggregated-templates/{id}/retire` | NATIONAL_ADMIN | PUBLISHED → RETIRED (blocked for default) |
| POST | `/aggregated-templates/{id}/activate` | NATIONAL_ADMIN | **Alias for publish** (back-compat) |
| POST | `/aggregated-templates/{id}/lock` | NATIONAL_ADMIN | Lock/unlock |
| POST | `/aggregated-templates/{id}/columns` | NATIONAL_ADMIN | Add a column |
| PATCH | `/aggregated-templates/{id}/columns` | NATIONAL_ADMIN | Bulk toggle/update columns |
| PATCH | `/aggregated-template-columns/{colId}` | NATIONAL_ADMIN | Edit one column |
| DELETE | `/aggregated-template-columns/{colId}` | NATIONAL_ADMIN | Soft delete (blocked for core) |

**Status lifecycle:**

```
      ┌─────────┐                    ┌─────────────┐                 ┌────────────┐
  ──▶ │  DRAFT  │ ──/publish──▶     │  PUBLISHED  │  ──/retire──▶   │  RETIRED   │
      └─────────┘                    └─────────────┘                 └────────────┘
                                             ▲                             │
                                             └─────── /publish ────────────┘
```

**Publish gate:** `POST /publish` returns 422 if the template has zero enabled
columns — the audit covers this.

### 3.2 POE notification contacts (scoped admin)

| method | path | allowed roles (scope) |
|---|---|---|
| GET  | `/poe-contacts` | any authenticated (read, scoped by role) |
| POST | `/poe-contacts` | NATIONAL (any) · PHEOC (country) · DISTRICT (district) · POE_ADMIN (poe) |
| PATCH | `/poe-contacts/{id}` | same matrix |
| DELETE | `/poe-contacts/{id}` | same matrix |
| GET  | `/poe-contacts/escalation-chain?poe_code=&level=` | any (resolves escalates_to_contact_id) |

### 3.3 Enterprise notifications

All POST endpoints log every send attempt to `notification_log`. Mail delivery
uses Laravel `Mail::html()`; in non-production it falls back to Laravel Log so
the system works offline.

| method | path | purpose | who triggers |
|---|---|---|---|
| POST | `/notifications/alert-broadcast` | Fan out new alert to tier-appropriate contacts | views + CRON |
| POST | `/notifications/escalation` | Escalate alert to PHEOC / NATIONAL / WHO | supervisor views |
| POST | `/notifications/followup-reminder` | Remind about due/overdue follow-up | CRON |
| POST | `/notifications/pheic-advisory` | Tier 1 / Annex 2 2-of-4 → NATIONAL + WHO contacts | NATIONAL_ADMIN |
| POST | `/notifications/daily-report` | 24h digest | CRON |
| POST | `/notifications/weekly-report` | Weekly digest | CRON |
| POST | `/notifications/send` | Ad-hoc single send | supervisor views |
| POST | `/notifications/retry-failed` | Retry FAILED log rows up to 4x | CRON |
| GET  | `/notifications/log` | Query the log | any |
| GET  | `/notifications/stats` | 30d rollup by status/template | any |

Templates marked `is_ai_enhanced=1` get a deterministic WHO/IHR-aware lead
paragraph prepended at send time (e.g. for `TIER1_ADVISORY`: "A single
confirmed case of the subject disease is sufficient trigger for WHO
notification within 24 hours…"). **No external LLM is called** — behaviour is
offline-friendly and deterministic. See `NotificationsController::aiEnhance`.

### 3.4 Alert follow-ups (RTSL 14 actions)

| method | path | purpose |
|---|---|---|
| GET  | `/alerts/{id}/followups` | List actions for an alert |
| POST | `/alerts/{id}/followups` | Create one follow-up (idempotent on `client_uuid`) |
| PATCH | `/alert-followups/{id}` | Update status / notes / who-notification-reference |
| GET  | `/alerts/compliance` | 7-1-7 rollup scoped to user's jurisdiction |

### 3.5 Alert intelligence (existing controller, new endpoints)

| method | path | notes |
|---|---|---|
| PATCH | `/alerts/{id}/acknowledge` | Role-gated by `ACKNOWLEDGE_ROLES` matrix (see §5.2) |
| PATCH | `/alerts/{id}/close` | Same matrix |

---

## 4. Vue views

| view | route | purpose |
|---|---|---|
| [`AggregatedHub.vue`](./src/views/AggregatedHub.vue) | `/aggregated-data` | **Landing view.** Offline-first card list of every PUBLISHED template for the user's country. Grouped by reporting_frequency. Shows sync pill, local draft count, pending-sync count, 5 most recent submissions. Polls server every 30s; reacts to `online` event. Admins see a "+" button that opens the wizard. |
| [`AggregatedData.vue`](./src/views/AggregatedData.vue) | `/aggregated-data/new/:templateId` | **Dynamic submission wizard** (Period → Fill → Notes → Review). Renders ALL enabled columns of the given template, grouped by category with typed inputs (INTEGER / DECIMAL / PERCENT / DATE / SELECT / BOOLEAN / TEXT), per-field validation, gender/symptom sum checks, auto-calculate from local screenings (daily/weekly), final review card, success animation. Cache-first, writes to IDB even offline. |
| [`AggregatedWizard.vue`](./src/views/AggregatedWizard.vue) | `/admin/aggregated-wizard` | **5-step admin template builder** (Purpose → Start from → Columns → Rules → Publish). Auto-generates `template_code` from name. Starting-point presets: Blank · WHO AFRO baseline · Daily tally · Clone existing. Toggle-switch columns. Validation rules per numeric field. Live preview. One-click Publish pushes to every POE instantly. |
| [`AggregatedTemplateAdmin.vue`](./src/views/AggregatedTemplateAdmin.vue) | `/admin/aggregated-templates` | Template manager. Toggle columns on/off, add custom columns, lock/activate, create country variants. **NATIONAL_ADMIN only for writes.** |
| [`PoeContactsAdmin.vue`](./src/views/PoeContactsAdmin.vue) | `/admin/poe-contacts` | Contact CRUD, grouped by (POE, level), scope-filtered by role. |
| [`ActiveAlerts.vue`](./src/views/ActiveAlerts.vue) | `/alerts` | Operational alert feed (Feed + Analytics tabs). Links to Intelligence / Matrix / History in header. |
| [`AlertIntelligence.vue`](./src/views/AlertIntelligence.vue) | `/alerts/intelligence` | 7-1-7 compliance dashboard (Compliance / Insights / Follow-ups / Analytics tabs). "Seed RTSL 14" button pre-populates 14 follow-up actions with correct due-at offsets per risk/tier. |
| [`AlertMatrix.vue`](./src/views/AlertMatrix.vue) | `/alerts/matrix` | WHO reference library (Tier 1 / Tier 2 / Annex 2 / 7-1-7 / Escalation / PHEIC). Static reading — no data fetch. |
| [`AlertHistory.vue`](./src/views/AlertHistory.vue) | `/alerts/history` | Historical feed + 7-1-7 compliance strip + scope-aware KPIs. |

Shared composable: [`useAlertIntelligence.js`](./src/composables/useAlertIntelligence.js) —
pure JS. Exports `classifyIHRTier`, `assessAnnex2`, `evaluate717`,
`nextEscalation`, `canActOnAlert`, `userScope`, `generateIntelligenceInsights`,
plus aggregates (`riskDistribution`, `syndromeCloud`, `concentrationByGeo`,
`responseTimeHistogram`, `escalationFunnel`, `compliance717Summary`,
`followupSummary`, `recommendedFollowups`). **Every rule is sourced** — see the
file header for WHO / IHR / RTSL citations.

---

## 5. RBAC model — canonical

### 5.1 Admin matrix

| role | aggregated templates | POE contacts | notifications trigger | alerts ack/close |
|---|---|---|---|---|
| `NATIONAL_ADMIN` | **full** (any country) | **full** (any POE) | yes | **any level** |
| `PHEOC_OFFICER` | read-only | scope: **country** | yes | DISTRICT + PHEOC |
| `DISTRICT_SUPERVISOR` | read-only | scope: **district** | yes | DISTRICT |
| `POE_ADMIN` | read-only | scope: **own POE** | yes | — |
| `POE_DATA_OFFICER` | read-only | read-only | yes | — |
| `POE_SECONDARY` | read-only | read-only | yes | — |
| `SCREENER` / `POE_PRIMARY` | read-only | read-only | — | — |

**Operating principle:** `NATIONAL_ADMIN` is unconditionally permitted
everywhere in the system. Supervisor roles (`PHEOC_OFFICER`,
`DISTRICT_SUPERVISOR`, `POE_ADMIN`) are permitted within their own
jurisdiction. Read-path queries at every controller already apply the correct
scope filter (`poe_code` / `district_code` / `pheoc_code` / `country_code`) —
supervisor roles see everything in their scope.

### 5.2 Alert acknowledge/close matrix

Mirrored exactly in [`useAlertIntelligence.js`](./src/composables/useAlertIntelligence.js)
(`ACK_ROLES`) and [`AlertsController.php`](./api/app/Http/Controllers/AlertsController.php)
(`ACKNOWLEDGE_ROLES`). **Keep both in sync.**

```
DISTRICT → [DISTRICT_SUPERVISOR, PHEOC_OFFICER, NATIONAL_ADMIN]
PHEOC    → [PHEOC_OFFICER, NATIONAL_ADMIN]
NATIONAL → [NATIONAL_ADMIN]
```

### 5.3 Important: scope fields live in `user_assignments`, NOT `users`

The `users` table carries only `role_key` and `country_code`. The per-user
`poe_code`, `district_code`, `pheoc_code`, `province_code` values are on
`user_assignments` (where `is_primary=1 AND is_active=1`).

**When writing a new controller that gates by scope**, resolve the assignment
explicitly — either via a helper like `resolvePrimaryAssignment($userId)`
(used by `AlertsController`, `AlertFollowupsController`) or by stitching the
assignment onto the user object inside `authUser()` (the pattern used by
`PoeContactsController`). Do not read `$user->poe_code` directly — it is always
`NULL`.

---

## 6. Offline-first + instant-propagation architecture

This is the single most paranoid offline path in the app. Three coordinated
mechanisms guarantee the user always has a usable template schema AND picks
up admin publish/retire within seconds:

**1. Full-overwrite cache**
[`AggregatedHub.vue#syncTemplatesFromServer`](./src/views/AggregatedHub.vue)
calls `GET /aggregated-templates/published` and does a full delete-by-index
(`country_code`) + batch insert. So if an admin retires a template, the next
sync removes it from the user's IDB too — propagation is automatic, no
reconciliation bugs.

**2. Cache-first read, network overlay**
[`AggregatedData.vue#loadTemplate`](./src/views/AggregatedData.vue) reads
from `STORE.AGGREGATED_TEMPLATES_CACHE` first and renders immediately, then
(only if online) does a `GET /aggregated-templates/{id}` to overlay fresher
data. A user opening a submission with no network still sees the last-known
schema.

**3. Multiple refresh triggers**
The Hub refreshes templates on: `onMounted`, `onIonViewDidEnter` (every
return to the tab), `window.online` event, and a 30-second polling interval.
This means: lock your phone for 10 minutes, admin publishes a new report,
unlock your phone → you see it within 30 seconds without touching anything.

**Submission offline guarantee**
Submissions write to IDB (`dbPut(STORE.AGGREGATED_SUBMISSIONS, record)`)
unconditionally. The server sync happens via the existing sync engine
(`SyncManagement.vue`). A submission made with zero network will show
`sync_status=UNSYNCED` locally and upload as soon as connectivity returns.
The legacy fixed columns (`total_screened`, gender counts, symptom counts)
plus the new dynamic `template_values` array are both saved, so the server
can reconstruct the full submission regardless of which template the
admin had published at submit time.

---

## 7. Frontend — how the dynamic template works

1. On mount, [`AggregatedData.vue`](./src/views/AggregatedData.vue) calls
   `GET /aggregated-templates/active?country_code={auth.country_code}`.
2. Server returns the active template + every enabled column (including core).
3. The view filters out the 7 `FIXED_KEYS` (which are already rendered as
   fixed inputs) and renders the rest as a dynamic grid.
4. On submit, custom values are collected into a `template_values` array of
   `{template_column_id, column_key, data_type, value_numeric, value_text,
   value_boolean}` and sent alongside the legacy fixed fields.
5. (Server-side) `aggregated_submission_values` INSERT writes one row per
   custom column. Dashboards JOIN the two tables.

### Adding a new column to the default template

1. **Preferred** — admin UI: navigate to `/admin/aggregated-templates` → "+ Add
   column". Validates `column_key` regex (`^[a-z][a-z0-9_]{1,58}$`) and
   prevents duplicates inside the same template.
2. **Via migration** — amend the seed array in
   [`2026_04_21_000002_create_templates_contacts_notifications_tables.php`](./api/database/migrations/2026_04_21_000002_create_templates_contacts_notifications_tables.php)
   — array shape is `[key, label, category, data_type, is_required, is_core, enabled_by_default]`.
3. **One-off SQL** — use the pattern in
   [`api/database/seeds-sql/add_who_afro_idsr_columns.sql`](./api/database/seeds-sql/add_who_afro_idsr_columns.sql)
   (idempotent via `NOT EXISTS`).

---

## 8. Notification engine — how to wire a new event

Every notification type is just a template + a trigger call. To add a new
event (e.g. "specimen shipping confirmation"):

1. Add a row to `notification_templates`:
   ```sql
   INSERT INTO notification_templates (template_code, channel, subject_template,
     body_html_template, body_text_template, is_ai_enhanced, is_active, ...)
   VALUES ('SPECIMEN_SHIPPED', 'EMAIL',
     'Specimen shipped: {{specimen_id}} from {{poe_code}}',
     '<p>Specimen {{specimen_id}} sent to {{lab_name}}…</p>',
     '...text version...', 0, 1, ...);
   ```
2. (Optional) add a case arm inside `NotificationsController::aiEnhance()` if
   you want a deterministic WHO/IHR-aware lead paragraph.
3. Call the engine from wherever the event happens. Simplest path is
   `/notifications/send` with `template_code=SPECIMEN_SHIPPED` and `vars={…}`.
   For fan-out to a whole POE's contacts, add a new endpoint that resolves the
   contact list (copy the pattern from `alertBroadcast`).

### The 12 seeded templates

`ALERT_CRITICAL` · `ALERT_HIGH` · `TIER1_ADVISORY` · `ANNEX2_HIT` ·
`BREACH_717` · `FOLLOWUP_DUE` · `FOLLOWUP_OVERDUE` · `DAILY_REPORT` ·
`WEEKLY_REPORT` · `ESCALATION` · `PHEIC_ADVISORY` · `ALERT_CLOSED`

### Cron scheduling (future — the controller endpoints are ready)

In `app/Console/Kernel.php`:
```php
$schedule->call(fn () => Http::post(url('/api/notifications/daily-report'),
  ['user_id' => 1, 'triggered_by' => 'CRON:daily']))->dailyAt('07:00');
$schedule->call(fn () => Http::post(url('/api/notifications/retry-failed'),
  ['user_id' => 1]))->hourly();
```

---

## 8.5 Sync pipeline — the SyncManagement hub

[`SyncManagement.vue`](./src/views/SyncManagement.vue) is the single place that
walks the offline queue and ships records to the server. It handles:

- **6 syncable stores** (registered in `STORE_META`, order matters):
  `primary_screenings` → `notifications` (server-authored, display-only)
  → `secondary_screenings` (deferred to case view) → `alerts` → `alert_followups`
  → `aggregated_submissions` → `poe_notification_contacts`.
- **Parent-child dependency resolution** — after an alert syncs successfully,
  SyncManagement walks IDB for any follow-ups whose `alert_client_uuid` matches
  and stamps the newly-minted server `alert_id` onto them. Same pattern for
  primary→notification.
- **Pre-flight validation** — each store has a `validate(record)` function in
  `STORE_META`. Records that fail pre-flight are marked `FAILED` with a human
  reason WITHOUT bumping `sync_attempt_count`, so they never reach quarantine
  from bad data alone.
- **Idempotency** — every POST carries the record's `client_uuid`. Server
  endpoints return `meta.idempotent: true` when the same uuid is resubmitted,
  which the client treats as success. Covered by audit assertions.
- **Quarantine** — after `QUARANTINE_THRESHOLD = 4` failed attempts a record
  stops retrying automatically. An operator can explicitly retry one via the
  Failed Records panel, which clears the attempt counter.
- **Version guard** — all writes go through `safeDbPut` which compares
  `record_version` and blocks stale overwrites.

### How to add a new syncable store

1. Declare the store in [poeDB.js](./src/services/poeDB.js) (`STORE.*`,
   `STORE_KEY`, `applySchema`). Bump `MIN_SCHEMA_VERSION`.
2. Register in [SyncManagement.vue](./src/views/SyncManagement.vue)
   `STORE_META` with:
   - `syncable: true` (else it's a display-only cache)
   - `endpoint`, `syncFn` (name matches a function you'll add)
   - `validate: (record) => boolean` (pre-flight)
3. Add `async function syncFoo(record, auth)` that:
   - Calls `bumpAttempt` at the top
   - Builds the payload
   - Calls `postJSON(url, payload)`
   - Returns `markSynced` on success or `markFailed(..., retryable)` on error
4. Place the store in the correct order in `STORE_META` — parents before children.

### Self-diagnostics panel

Click **Run checks** in the Diagnostics section of SyncManagement to run 8
internal tests:

1. **Authenticated session** — `AUTH_DATA` present + user id valid
2. **IndexedDB connectivity** — round-trip against `poe_offline_db`
3. **Schema version** — local DB is at ≥ `APP.MIN_SCHEMA_VERSION`
4. **Server reachability** — 4s-timeout GET to `/home/summary`
5. **Parent-child coherence** — follow-ups whose alert_client_uuid doesn't
   exist in IDB
6. **Quarantine status** — count of records at ≥4 failed attempts
7. **Idempotency keys present** — every UNSYNCED record has a `client_uuid`
8. **Sync-state coherence** — records marked `SYNCED` but missing a server
   `id` (auto-fix button flips them back to `UNSYNCED` for retry)

Checks with `status: 'fail'` get a pulsing red indicator. Checks with an
optional `fix` function (e.g. stuck-SYNCED) render a "Fix" button that runs
the remediation then re-runs diagnostics.

---

## 9. Running the audit

```bash
bash api/database/seeds-sql/audit_test.sh
```

Expected: **110 assertions, 0 failures**.

### What the audit covers

- §1-§3 Schema integrity (7 tables + indexes), seed integrity (57-col default
  template, 12 notification templates), user promotion
- §4-§6 RBAC fixtures + API liveness + enforcement (SCREENER locked,
  POE_ADMIN/DISTRICT/PHEOC scoped, NATIONAL unrestricted)
- §7 Data-integrity guards (core columns undroppable, locked templates
  reject edits, invalid column_key regex rejected)
- §7.5 Multi-published templates + publish/retire/publish gate (publish
  blocked if zero columns enabled)
- §7.6 Force-delete with submissions (409 without cascade + count →
  cascade+confirm=200, submissions preserved)
- §7.7 **Sync pipeline contract** (new) — every endpoint the client POSTs
  to accepts the exact payload shape, template_id is persisted on
  aggregated_submissions, follow-ups POST against missing parent returns
  404, /published response includes inline columns + status +
  reporting_frequency
- §8 Idempotency — re-running seeds produces zero new rows Exit code 0. Fixtures created and
cleaned up automatically. Cleanup runs on every exit path (trap EXIT).

**If any assertion fails**, do not merge. The fixture users are scoped to
(country=UG, district=Lamwo District, poe=Ngoromoro) so adjust if your test DB
uses different codes.

---

## 10. Countries beyond Uganda

The default seeded template is coded `UG / WHO_BASELINE_POE_V1`. To deploy in a
new country:

1. A `NATIONAL_ADMIN` of the new country clicks "+ New" in
   `/admin/aggregated-templates`. Provide `country_code`, a new
   `template_code` (e.g. `KE_POE_V1`), and tick "Pre-populate with WHO default
   columns".
2. Toggle country-specific columns on/off, add custom ones.
3. Click "Activate" — this demotes any prior active template for that country.
4. Submissions from users whose `country_code` matches now use the new
   template automatically.

**Research basis for the 57-column default:** WHO AFRO IDSR 3rd Ed. Booklet 1
Annex C (PoE surveillance) · Uganda MoH IDSR 3rd Ed. (2021) PoE register ·
HISP DHIS2 COVID-19 PoE Tracker v0.3.1 · IHR 2005 Annex 1B + Annex 7 · Africa
CDC Cross-Border Surveillance Strategic Framework (2024). **ECSA-HC does not
publish its own PoE template** — it defers to WHO AFRO IDSR as the technical
standard.

---

## 11. Gotchas future devs hit

- **"Migration won't run."** The existing `migrations` table is out of sync
  with the real schema. Use the SQL scripts in
  `api/database/seeds-sql/` directly via `mysql < file.sql`. They are all
  idempotent.
- **"500 on `/aggregated-templates/active`."** The 7 new tables weren't
  created. Run `/tmp/apply_new_tables.sql` (or check
  `api/database/seeds-sql/` for a committed copy) — `SHOW TABLES LIKE
  'aggregated_%'` must return 3 rows.
- **"POE_ADMIN gets 403 on their own POE."** `authUser()` must stitch
  `user_assignments` onto the user object. See `PoeContactsController::authUser`.
  New controllers that need scope fields: copy that pattern.
- **"The alert matrix shows 7-1-7 BREACH everywhere."** `evaluate717()` uses
  `alert.created_at` and `alert.hours_since_creation`. If `hours_since_creation`
  is null the detect stage falls back to `(now - created_at)`. For very old
  alerts you will see BREACH — that is correct historical behaviour.
- **"Notifications don't actually email."** In dev the `MAIL_MAILER` is
  usually `log`. Every send attempt is still captured in `notification_log`
  with `status='SENT'` (even though no real email went out). In production,
  set proper SMTP and the same code path delivers.
- **"I can't disable `total_screened` in the admin UI."** Good. It's core.
  `is_core=1` columns are enforced at three layers: UI (no toggle rendered),
  API (`409` on PATCH), and audit (test assertion).

---

## 12. File map — where to find what

```
api/
  app/Http/Controllers/
    AggregatedTemplatesController.php  — template CRUD + activate/lock + column mgmt
    PoeContactsController.php          — contacts + escalation chain
    NotificationsController.php        — alert broadcast / escalation / reminders / reports
    AlertFollowupsController.php       — RTSL 14 follow-up tracker + compliance rollup
  database/
    migrations/
      2026_04_21_000001_create_alert_followups_table.php
      2026_04_21_000002_create_templates_contacts_notifications_tables.php
    seeds-sql/
      add_who_afro_idsr_columns.sql    — 22 authoritative WHO AFRO columns (idempotent)
      audit_test.sh                    — run-the-tests
  routes/api.php                       — route registrations (§3)
src/
  views/
    AggregatedHub.vue                  — landing view (offline-first card list of PUBLISHED templates)
    AggregatedData.vue                 — dynamic submission wizard (per-template)
    AggregatedWizard.vue               — 5-step admin template builder
    AggregatedTemplateAdmin.vue        — template manager (edit columns / lock / retire)
    PoeContactsAdmin.vue               — contacts admin
    ActiveAlerts.vue                   — alert feed
    AlertIntelligence.vue              — 7-1-7 compliance dashboard
    AlertMatrix.vue                    — WHO/IHR reference
    AlertHistory.vue                   — historical alerts + compliance strip
  composables/
    useAlertIntelligence.js            — IHR / 7-1-7 rules engine (pure JS)
app.sql                                — canonical schema (authoritative)
```

---

## 13. When in doubt

- Run the audit (`bash api/database/seeds-sql/audit_test.sh`) — 67 assertions
  cover most of the RBAC + integrity surface.
- Read the citation comments in `useAlertIntelligence.js` — every threshold
  traces to a WHO/IHR document.
- Check `notification_log` for anything email-related — it captures the full
  send body regardless of whether SMTP actually delivered.

**Do not** build auth/scope logic from scratch. Copy a controller that already
does it correctly (`AlertsController`, `PoeContactsController`) — the pattern
is consistent and the audit will catch drift.

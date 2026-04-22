# POE Sentinel — Developer Guide (2026-04-21 expansion + v2)

> **Read this alongside [`PROJECT-GUIDLINES.md`](./PROJECT-GUIDLINES.md).** That file
> describes the foundational screening + notification workflows. This one
> documents the **aggregated template engine**, **report hub + wizard**,
> **POE notification contacts**, **notification engine**, **alert intelligence
> hub**, and the **RBAC opening** layered on top on 2026-04-21.
>
> The audit test suite at [`api/database/seeds-sql/audit_test.sh`](./api/database/seeds-sql/audit_test.sh)
> is the executable specification — if you change anything in this area, run it
> and keep it green (**161 assertions**, 0 failures).

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

## 8.4 Email notifications — Tesla-grade delivery layer

> Added 2026-04-21 v5. The notification subsystem is the single most
> end-to-end-tested area of the app — 32 audit assertions in §7.8.

### Architecture in one diagram

```
  ┌────────────────────┐    ┌──────────────────────┐    ┌──────────────────────┐
  │ Controllers        │    │ NotificationDispatcher│    │ Mail (SMTP, Gmail)   │
  │ (Alerts, Primary,  ├───▶│ • dispatchAlertCreated│───▶│ smtp.gmail.com:587  │
  │  Aggregated)       │    │ • dispatchAlertClosed │    │ TLS                  │
  │                    │    │ • dispatchScreening…  │    │ vexa256@gmail.com    │
  └────────────────────┘    │ • sendDailyDigest     │    └──────────────────────┘
                            │ • sendFollowupRem…    │             │
  ┌────────────────────┐    │ • retryFailed         │             ▼
  │ Cron (laravel      ├───▶│                       │    ┌──────────────────────┐
  │  schedule:run)     │    │ Renders {{tokens}} +  │    │ notification_log     │
  └────────────────────┘    │ logs every attempt    ├───▶│ (audit + retry queue)│
                            └──────────────────────┘    └──────────────────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │ poe_notification_    │
                            │ contacts             │
                            │ (19 Uganda + custom) │
                            └──────────────────────┘
```

### Files

| File | Role |
|---|---|
| [`app/Services/NotificationDispatcher.php`](api/app/Services/NotificationDispatcher.php) | The single email-sending service. Every controller and cron job calls into it. Never throws (caller can't be broken by an email failure). |
| [`app/Console/Commands/NotificationsDailyDigest.php`](api/app/Console/Commands/NotificationsDailyDigest.php) | `php artisan notifications:daily-digest` |
| [`app/Console/Commands/NotificationsFollowupReminders.php`](api/app/Console/Commands/NotificationsFollowupReminders.php) | `php artisan notifications:followup-reminders` |
| [`app/Console/Commands/NotificationsRetryFailed.php`](api/app/Console/Commands/NotificationsRetryFailed.php) | `php artisan notifications:retry-failed` |
| [`routes/console.php`](api/routes/console.php) | Schedule registration via `Schedule::command(...)` |
| [`database/seeds-sql/04_premium_email_templates.sql`](api/database/seeds-sql/04_premium_email_templates.sql) | 12 production-grade HTML templates (idempotent INSERT…ON DUPLICATE KEY UPDATE) |
| [`database/seeds-sql/05_uganda_default_contacts.sql`](api/database/seeds-sql/05_uganda_default_contacts.sql) | The 19-person Uganda national notification roster |

### The 12 email templates

Every template is inlined HTML (Gmail strips `<style>`), 600 px max width,
table-based, with a unified visual language (deep navy header → tier-coloured
gradient → facts grid → gradient CTA button → dark footer with WHO/IHR
citation). All 12 weigh between 2.5 and 5.0 KB compressed.

| `template_code` | When it fires | Trigger | Visual accent |
|---|---|---|---|
| `ALERT_CRITICAL` | Critical risk-level alert created | `AlertsController::store` | red → gold (`#7F1D1D → #F59E0B`) |
| `ALERT_HIGH` | High risk-level alert created | `AlertsController::store` / `PrimaryScreeningController::store` (HIGH referral) | orange (`#9A3412 → #C2410C`) |
| `TIER1_ADVISORY` | IHR Tier 1 disease detected | `AlertsController::store` (auto-detected by `ihr_tier`) | purple (`#581C87 → #6B21A8`) |
| `ANNEX2_HIT` | Annex 2 4-criteria threshold met | `AlertsController` / `AlertFollowupsController` | navy → blue (`#1E3A8A → #3B82F6`) |
| `BREACH_717` | 7-1-7 performance target breached | `AlertsController` / scheduler | red → amber (`#7F1D1D → #CA8A04`) |
| `FOLLOWUP_DUE` | Follow-up due in ≤4 h | `notifications:followup-reminders` cron | navy blue (`#1E40AF → #3B82F6`) |
| `FOLLOWUP_OVERDUE` | Follow-up past due_at | `notifications:followup-reminders` cron | red (`#7F1D1D → #991B1B`) |
| `DAILY_REPORT` | 24-h surveillance digest | `notifications:daily-digest` (cron 07:00) | navy → cobalt (`#001D3D → #003F88`) |
| `WEEKLY_REPORT` | Weekly summary | manual or future weekly cron | green → navy (`#064E3B → #001D3D`) |
| `ESCALATION` | Manual alert escalation | `NotificationsController::escalation` | amber → navy (`#854D0E → #001D3D`) |
| `PHEIC_ADVISORY` | PHEIC indicators met | `dispatchAlertCreated` (Tier 1 fan-out) | black → purple (`#0F172A → #9333EA`) |
| `ALERT_CLOSED` | Alert closed | `AlertsController::close` | green (`#064E3B → #047857`) |

To **edit a template**, run an `UPDATE notification_templates SET body_html_template = '...' WHERE template_code = '...'`. The sender renders `{{token}}` placeholders at send time so vars stay portable. To **add a new template**, INSERT a new row + add a `dispatch*` method on `NotificationDispatcher`.

### Default Uganda national roster (19 contacts)

Seeded by `05_uganda_default_contacts.sql`. All at `level=NATIONAL`, all
subscribed to: critical, high, tier1, tier2, breach_alerts, followup_reminders,
daily_report, weekly_report.

`priority_order=1` is the primary TO recipient (Ayebare Timothy). Priorities
2–19 are CCs. The dispatcher iterates by priority so the primary is always
first in `notification_log`.

To add a new default contact: `INSERT` into `poe_notification_contacts` with
`country_code='UG'`, `level='NATIONAL'`, `priority_order=` next free integer.
The next dispatch cycle picks them up immediately — no code or restart needed.

### How a new alert flows through the system

1. Officer or rule-engine calls `POST /api/alerts`.
2. `AlertsController::store` inserts the row, then calls
   `NotificationDispatcher::dispatchAlertCreated($newAlert, $userId)`.
3. The dispatcher:
   1. Picks template based on `risk_level` + `ihr_tier`
   2. Resolves recipients across the IHR ladder (e.g. DISTRICT alert →
      DISTRICT + PHEOC + NATIONAL contacts)
   3. Filters by `receives_*` flags (CRITICAL needs `receives_critical=1`)
   4. Renders HTML + plain-text body via `{{token}}` substitution
   5. Sends via `Mail::html()`
   6. Writes a `notification_log` row per attempt
4. The HTTP response includes a `notification: { sent, skipped, failed }`
   block so the UI can surface what was triggered.
5. If SMTP errors out, the row is logged with `status=FAILED` and the
   `notifications:retry-failed` cron will retry up to 4 times.

### Scheduling — the cron entry

Production needs **one** crontab entry on the host:

```cron
* * * * * cd /home/hacker/ecsa_poe_2026/api && php artisan schedule:run >> /dev/null 2>&1
```

That single line drives every scheduled task in `routes/console.php`:

| When | Command | What it does |
|---|---|---|
| Daily 07:00 (Africa/Kampala) | `notifications:daily-digest` | 24-h surveillance digest to subscribers |
| Hourly at :15 | `notifications:followup-reminders` | DUE-SOON + OVERDUE follow-ups |
| Every 15 min | `notifications:retry-failed` | Re-deliver FAILED log rows |

All commands use `withoutOverlapping()` so a slow run never collides with the
next tick. All use `onOneServer()` so multi-host deployments are safe.

Verify after deploy:
```bash
php artisan schedule:list
php artisan notifications:daily-digest         # manual dry-run any time
```

### Operating notes

- **SMTP credentials** live in `.env` — host `smtp.gmail.com`, port 587, TLS,
  username `ecsahccloudsurveillancealerts@gmail.com`. The visible From is
  `Uganda National POE Screening Tool <vexa256@gmail.com>`. Gmail rewrites
  Bcc/From if `vexa256@gmail.com` is not a configured "send mail as" alias on
  the authenticated mailbox — the user has confirmed this is set up.
- **Failure handling**: dispatcher uses `safely()` wrapper — every method is
  inside try/catch + logged. Controllers can call any dispatch method without
  exception handling.
- **Log retention**: `notification_log` is unbounded — schedule a quarterly
  cleanup if it grows large.
- **Local dev without SMTP**: just leave `MAIL_MAILER=log` in `.env`.
  Dispatcher catches the exception and writes the body to `laravel.log` so
  the UI flow still completes.

---

## 8.45 Alert engine v6 — deterministic, country-aware, anti-spam

> Added 2026-04-21 v6. The notification + alert workflow is now an
> operational system, not a CRUD wrapper. 25 of the 161 audit assertions
> live in §7.9 covering this subsystem end-to-end.

### What v6 added on top of v5

| Capability | What it gives operators |
|---|---|
| **Anti-spam suppression** | Same (template, entity, contact) cannot send twice inside the template's window. CRITICAL = 30 min, CASE_FILE = 6 h, DAILY = 60 min, etc. SKIPPED rows are logged with reason for audit. |
| **Country isolation** | Every dispatch runs against `alert.country_code`. The Uganda roster never receives a Rwanda / Zambia / Malawi / STP event and vice-versa. Verified by audit assertion. |
| **Email deliverability** | `MAIL_FROM_ADDRESS = MAIL_USERNAME` so Gmail does not rewrite (which kills deliverability). `MAIL_REPLY_TO_ADDRESS = vexa256@gmail.com` routes humans to the operations mailbox. Sender adds `Auto-Submitted`, `Precedence: list`, `List-Unsubscribe`, `X-Auto-Response-Suppress` headers — all anti-spam best-practice. |
| **`ALERT_CASE_FILE` rich template** | Fired automatically when `SecondaryScreeningController::fullSync` settles a case at any disposition other than NON_CASE. Renders a 9-block case file (what / where / when / who / why / status / actions taken / next required / owner+deadline + case IDs) — a stakeholder can act without opening the dashboard. |
| **`NATIONAL_INTELLIGENCE` digest** | Triennial briefing for top-3 priority NATIONAL_ADMIN contacts. Driven by `IntelligenceEngine` — 6 hardcoded WHO/IDSR detectors. Roster (priority 4-19) excluded to keep noise low. |
| **`RESPONDER_INFO_REQUEST`** | Outbound info-request email for hospitals / labs / partner agencies. Persists a one-time-use token in `responder_info_requests` so a future inbound endpoint can match the response back. |
| **RTSL 14 auto-seed on alert create** | `AlertsController::store` calls `NotificationDispatcher::seedRtsl14Followups($alert, $userId)` immediately after insert. Idempotent — re-runs are no-ops. 6 of the 14 actions block alert closure (CASE_INVESTIGATION, ISOLATION, CONTACT_LISTING, CONTACT_TRACING, EOC_ACTIVATION, WHO_NOTIFICATION). |

### IntelligenceEngine — six hardcoded detectors

`app/Services/IntelligenceEngine.php`. Country-scoped, deterministic, no
external services. Each detector returns a non-negative integer plus the
narrative builder produces a one-paragraph plain-English summary.

| Detector | Logic | Source |
|---|---|---|
| `silentPoes24h(country)` | POEs that screened anything in the prior 7 days but nothing in last 24h | WHO AFRO IDSR Booklet 1 §C |
| `unsubmittedPoes3d(country)` | POEs that submitted aggregated data in last 14d but nothing in last 3d | Uganda IDSR 3rd Ed. §6 |
| `dormantAccounts(country)` | Active users with `last_login_at` null or older than 14 days | Operational rule |
| `stuckAlerts(country)` | Alerts `status='OPEN'` with `TIMESTAMPDIFF(HOUR, created_at, NOW()) > 24` | RTSL/WHO 7-1-7 notify SLA |
| `overdueFollowups(country)` | Follow-ups past `due_at`, status not in (COMPLETED, NOT_APPLICABLE) | RTSL 14-action SLA |
| `caseSpikes(country)` | POEs whose 3-day symptomatic count ≥ 2× their 7-day baseline (window-normalised) | IDSR signal-detection rule |

`narrativeFor($country)` joins the non-zero findings into one paragraph; if
nothing is wrong it produces a calm "no anomalies detected" line — the email
template renders accordingly.

### Adding a new country (Rwanda / Zambia / Malawi / STP)

1. Seed a national roster — duplicate `05_uganda_default_contacts.sql`,
   change `@country := 'RW'` (or 'ZM' / 'MW' / 'ST') and the email list.
2. That's all. Every detector + dispatcher + cron is country-aware: the
   national-digest scheduler will iterate all distinct `country_code`
   values present in `poe_notification_contacts` at level NATIONAL.

### How a secondary case dispositioned to (anything other than) NON_CASE flows

```
1.  SecondaryScreeningController::fullSync detects new disposition + status_changed=true
                ↓
2.  NotificationDispatcher::dispatchCaseFile($case, $userId)
                ↓
3.  buildCaseFileVars($case, $alert) — pulls case + alert + secondary_actions
    + secondary_suspected_diseases + samples count → 22 contextual fields
                ↓
4.  resolveContactsByScope(country, district, poe, [POE,DISTRICT,PHEOC,NATIONAL])
                ↓
5.  For each contact:
      • wasRecentlySent(ALERT_CASE_FILE, 'CASE_FILE', case_id, contact_id)?
        – yes inside 6h → SKIPPED logged with "Suppressed — last sent N min ago"
        – no            → Mail::html() with Reply-To + anti-spam headers
                          → notification_log SENT + suppression row upsert
                ↓
6.  HTTP response carries notification: {sent, skipped, failed}
```

### Console commands + cron

```
notifications:daily-digest         — 07:00 daily       (Africa/Kampala)
notifications:followup-reminders   — every hour at :15
notifications:retry-failed         — every 15 minutes
notifications:national-digest      — every 3 days at 08:00  (NEW v6)
```

Production cron entry remains a single line:
```cron
* * * * * cd /home/hacker/ecsa_poe_2026/api && php artisan schedule:run >> /dev/null 2>&1
```

### Operational notes

- **Dispatcher is fire-and-forget** — every public method is wrapped in
  `safely()`. A failed email NEVER fails the underlying create operation.
- **Suppression keyed by triple** `(template_code, entity_type, entity_id, contact_id)`.
  Escalations beat suppression by definition because they use a different
  template_code.
- **Tone** — every template subject avoids `URGENT!!`, `ACTION NEEDED NOW`
  and similar spam triggers. Body copy is calm, structured, citation-rich.
- **External responders** are never on the routine roster. They only
  receive case-specific `RESPONDER_INFO_REQUEST` emails initiated by an
  operator + a one-time token.

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

Expected: **142 assertions, 0 failures**.

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

---

## 14. Repository architecture — the three trees

As of 2026-04-21 the monorepo has **three disjoint apps**. Each owns its own
dependencies, build pipeline, and lifecycle. **Never put code from one tree
into another** — the boundaries are enforced by our deploy pipeline and by
diverging tech stacks.

```
ecsa_poe_2026/
├── api/        ← Laravel 11 backend (PHP)
├── pwa/        ← Vue 3 National Dashboard web PWA (JavaScript)
├── src/        ← Ionic 8 + Capacitor mobile app (TypeScript)
├── public/     ← Ionic mobile app's public assets
├── vite.config.ts · capacitor.config.ts · ionic.config.json · tsconfig.json · package.json
│                 ← all belong to the Ionic mobile app at the root
├── DEVELOPERS.md · PROJECT-GUIDLINES.md · readme.txt · app.sql
└── (no other trees — if you are about to add one, don't)
```

### 14.1 What each tree is for

| Tree | What it is | Who runs it | Ship pipeline |
| --- | --- | --- | --- |
| **`/` (root)** | **Ionic 8 + Capacitor 5 mobile app.** Field-officer phone app POE staff carry into the booth. Offline-first, IndexedDB via `poeDB.js`, syncs to the Laravel API. | POE officers on Android + iOS phones / tablets | `ionic build` → Capacitor → Google Play / App Store |
| **`/api`** | **Laravel 11 backend.** All MySQL, all business logic, every email, every scheduled job, every intelligence detector, every template. | Every other tree talks to it. | `composer install` → `php artisan serve` (dev) · nginx + php-fpm (prod) |
| **`/pwa`** | **Vue 3 National Dashboard web PWA.** Master admin console — national / PHEOC / WHO strategic users open it in a browser (or install it as a PWA). War-room, intelligence, digests, template admin, responder registry, inbox. | National MoH + PHEOC + WHO users on desktop / tablet | `pnpm build` → static bundle → any CDN / nginx |

The mobile app is for **capture**; the PWA dashboard is for **response +
oversight**; the API is the **source of truth**.

### 14.2 Hard rule: no cross-tree leakage

- **Nothing belonging to the PWA dashboard may live outside `/pwa`.** No
  components, no icons, no manifest, no Tailwind config, no service worker
  — everything lives inside `/pwa` and `/pwa` alone.
- **Nothing belonging to the Ionic mobile app may live inside `/pwa`.** The
  root-level `src/`, `public/`, `index.html`, `vite.config.ts`,
  `capacitor.config.ts`, `ionic.config.json`, `package.json`, `tsconfig.json`
  are the mobile app.
- **Nothing belonging to the backend may live outside `/api`.** No PHP, no
  migrations, no `.env`, no templates — all under `/api`.

You can verify the boundary with a single grep:

```bash
# This MUST return zero matches — PWA dashboard libs must not appear at root.
grep -rl "maplibre\|unovis\|@nuxt/ui\|VitePWA" src/ public/ index.html vite.config.ts

# This MUST return zero matches — Ionic libs must not appear inside the PWA.
grep -rl "@ionic/vue\|@capacitor/core" pwa/src/
```

If either returns matches, you have a boundary violation. Move the offender
into the correct tree before merging.

---

## 15. The National Dashboard PWA — `/pwa`

Master web console that sits on top of the Laravel API. Stack locked to:

| Layer | Package | Why |
| --- | --- | --- |
| Framework | **Vue 3.5** (Composition API + `<script setup>`) | Official stable |
| Build | **Vite 5** | Official Vue dev stack |
| Router | **vue-router 4** + `unplugin-vue-router` | File-based routes from `pwa/src/pages/**` |
| State | **Pinia** (setup stores) | Official Vue store |
| Server state | **@tanstack/vue-query** | Mutations + caching |
| UI components | **@nuxt/ui v4** via the Vue plugin (NOT Nuxt) | Tailwind v4, a11y, theming |
| Styles | **Tailwind CSS v4** via `@tailwindcss/vite` | Official Tailwind Vite plugin |
| Icons | lucide + heroicons + simple-icons (`@iconify-json/*`) | On-demand, no full-pack ship |
| PWA | **vite-plugin-pwa** (Workbox) | Installable + offline-tolerant |
| Charting | **@unovis/vue** | Best-in-class open SVG charts |
| Advanced charts | **echarts** + **vue-echarts** | Sankey / sunburst / treemap |
| Maps | **maplibre-gl** + OpenFreeMap tiles | Best open Mapbox-GL alternative |
| HTTP client | **ofetch** | Tiny, sensible defaults |
| i18n | **vue-i18n v10** | Official i18n |

**No TypeScript in `/pwa`.** Plain JavaScript + JSDoc annotations where
useful. Deliberate — keeps bundle lean, keeps the learning curve flat, and
diverges from the Ionic app's TypeScript surface so the two teams do not
accidentally share types.

**Never install a second UI kit.** Nuxt UI v4 is the law in `/pwa`.
**Never use Chart.js.** Unovis first; ECharts only when Unovis cannot do it.

### 15.1 Folder layout

```
pwa/
├── index.html
├── package.json                  ← dependencies (see table above)
├── vite.config.js                ← Vite + Tailwind + Nuxt UI + VitePWA + file routes
├── jsconfig.json                 ← IDE path aliases
├── eslint.config.js              ← flat config (Vue plugin + Prettier)
├── .prettierrc.json
├── .env.example                  ← VITE_API_BASE, VITE_MAP_TILES_URL, VITE_DEFAULT_COUNTRY
├── public/
│   ├── favicon.svg
│   └── icons/
│       ├── icon.svg              ← W3C-compliant manifest icon (purpose: any)
│       └── icon-mask.svg         ← maskable variant
└── src/
    ├── main.js                   ← createApp → use(Pinia, Router, Nuxt UI, VueQuery, Motion)
    ├── App.vue                   ← <UApp> + <RouterView/>
    ├── assets/main.css           ← Tailwind v4 + Nuxt UI + MapLibre + risk palette
    ├── router/index.js           ← wraps pages under DefaultLayout; /login and /respond/:token standalone
    ├── services/api.js           ← single ofetch client, injects X-User-Id shim
    ├── stores/badges.js          ← Pinia setup store polling inbox/stuck/overdue counters
    ├── composables/useNavigation.js  ← sidebar + bottom-nav data (edit here to add menu items)
    ├── layouts/DefaultLayout.vue ← mother layout: desktop rail + mobile drawer + bottom tabs
    ├── components/PagePlaceholder.vue
    └── pages/
        ├── index.vue             → /
        ├── more.vue              → /more
        ├── login.vue             → /login (standalone)
        ├── respond/[token].vue   → /respond/:token (public, token-authenticated)
        ├── alerts/{index,war-room,followups,case-files,breaches}.vue
        ├── intelligence/{index,silent-poes,stuck-alerts,overdue,spikes,dormant,diseases,seven-one-seven}.vue
        ├── notifications/{index,activity,digests,retry}.vue
        ├── templates/index.vue
        ├── responders/{index,contacts,info-requests}.vue
        ├── admin/{users,assignments,aggregated,geography,audit,system}.vue
        └── settings/index.vue
```

**Path aliases** (both `jsconfig.json` and `vite.config.js`):
`@`, `@components`, `@composables`, `@layouts`, `@pages`, `@services`,
`@stores`, `@utils`.

### 15.2 The mother layout

[`pwa/src/layouts/DefaultLayout.vue`](./pwa/src/layouts/DefaultLayout.vue):

- **≥ lg (desktop)**: collapsible left rail (272 px ↔ 72 px) + sticky top
  bar with search, country switch, theme toggle, bell, avatar.
- **< lg (mobile)**: top bar with hamburger that opens a `USlideover`
  drawer; 5-slot bottom tab bar is always visible (Dashboard · Alerts ·
  Map · Inbox · More).
- **Safe-area** utilities (`.safe-pt`, `.safe-pb`) honour iOS installed-app cutouts.
- **Theme** toggled via `useColorMode` from VueUse; persisted to `localStorage`.

The sidebar is **data-driven** from
[`pwa/src/composables/useNavigation.js`](./pwa/src/composables/useNavigation.js).
Edit that file to add / hide / reorder entries — the layout re-renders.

### 15.3 PWA offline strategy

`vite-plugin-pwa` with Workbox, three cache layers:

| Cache | Pattern | Strategy | TTL · Entries |
| --- | --- | --- | --- |
| `poe-api-cache` | `/api/*` | NetworkFirst, 8 s timeout | 12 h · 250 |
| `poe-img-cache` | `*.{png,jpg,svg,webp,avif}` | CacheFirst | 30 days · 400 |
| `poe-tiles-cache` | `tiles.openfreemap.org/*` | StaleWhileRevalidate | default |

Open offline, browse last-known alerts, drill into a cached war-room, see
the map. Writes (POST / PATCH / DELETE) still require the network.

### 15.4 Backend wiring

[`pwa/src/services/api.js`](./pwa/src/services/api.js) exports a single
`ofetch` instance pointing at `VITE_API_BASE` (default
`http://localhost:8000/api`). It injects an `X-User-Id` header from
`localStorage['poe:user_id']` as the auth shim until Sanctum is wired.

Controllers the PWA targets (see
[`api/docs/COLLABORATION_CONTROLLERS.md`](./api/docs/COLLABORATION_CONTROLLERS.md)):

- `AlertsController` — live-alerts list
- `AlertCollaborationController` — war-room (30+ endpoints)
- `AlertFollowupsController` — RTSL-14 kanban
- `NotificationsInboxController` — per-user inbox
- `NotificationTemplatesController` — template admin + preview
- `ExternalRespondersController` — registry
- `ResponderInfoRequestsController` — inbound-response loop
- `IntelligenceController` — 12 dashboard feeds
- `DigestsController` — manual trigger + preview

### 15.5 Run it

```bash
cd pwa
cp .env.example .env            # point VITE_API_BASE at the Laravel backend
pnpm install                    # once
pnpm dev                        # http://localhost:3100
pnpm build && pnpm preview      # production bundle (static, deployable anywhere)
pnpm lint && pnpm format
```

### 15.6 Contribution rules inside `/pwa`

- **Nuxt UI v4 only.** Do not install a second component kit.
- **No TypeScript.** Plain JavaScript + JSDoc annotations.
- **Mobile-first.** Start every component at 320 px; upgrade at `sm:` and `lg:`.
- **No Chart.js.** Unovis first; ECharts when Unovis cannot do it.
- **Document every new page** via `PagePlaceholder` until the real view is in.
- **Accessibility**: prefer Nuxt UI primitives (they are a11y-correct by default).
- **Never add SSR.** The PWA is a client-rendered SPA for offline tolerance
  and zero backend compute per request. The dashboard is behind auth so SEO
  is irrelevant.

### 15.7 What the PWA is NOT

- **Not** a replacement for the Ionic mobile field app. The mobile app
  captures primary + secondary screenings at the POE; the PWA never does.
- **Not** a Nuxt project. Nuxt UI v4 is installed as a Vue plugin via
  `@nuxt/ui/vue-plugin`. If you're reaching for a Nuxt module that is not
  that plugin, you're in the wrong tree.
- **Not** a place to embed business rules. All rules live in the Laravel
  API — the PWA renders what the API tells it.

---

## 16. Dashboard Auth & User Management (v2)

Shipped in the 2026-04-21 hardening pass. **Master dashboard only.** The
mobile app keeps its custom `/api/auth/login` pathway in `UserLoginController`.

### 16.1 Schema additions ([09_auth_hardening.sql](./api/database/seeds-sql/09_auth_hardening.sql))

Columns added to `users` (all nullable / zero-default, so the mobile app
remains unaffected):

```
two_factor_secret · two_factor_recovery_codes_hash · two_factor_confirmed_at
failed_login_count · last_failed_login_at · locked_until
last_login_ip · last_login_ua · last_activity_at
password_changed_at · must_change_password
risk_score · risk_score_updated_at · risk_flags_json
locale · timezone · avatar_url · phone_verified_at
invitation_token_hash · invitation_expires_at · invitation_accepted_at
suspended_at · suspension_reason · created_by_user_id
account_type  (NATIONAL_ADMIN · PHEOC_ADMIN · DISTRICT_ADMIN · POE_ADMIN
              · POE_OFFICER · OBSERVER · SERVICE)
```

New tables (all cascade from `users.id`):

| Table | Purpose |
| --- | --- |
| `auth_events`            | Append-only audit of every LOGIN_OK / LOGIN_FAIL / LOCKED / TWOFA_* / TRUSTED_DEVICE_* / ADMIN_* / ROLE_CHANGED / ANOMALY_FLAGGED event |
| `email_verifications`    | Signed-token store for `VERIFY_EMAIL` · `RESET_PASSWORD` · `INVITATION` · `CHANGE_EMAIL`. SHA-256 hashed, single-use, typed TTL |
| `trusted_devices`        | Passkey-style device-bound tokens. 30-day TTL. Skip 2FA when presented |
| `user_audit_log`         | Admin-initiated mutations with before/after JSON diff |
| `user_anomaly_flags`     | Live flags raised by `UserAnomalyService` with severity + evidence |
| `webauthn_credentials`   | Registry for future full WebAuthn assertion flow (trusted-device is the interim) |
| `role_registry`          | Canonical `role_key` dictionary (seeded with 8 roles) |

### 16.2 Services ([api/app/Services/](./api/app/Services/) + [api/app/Support/](./api/app/Support/))

- **`AuthEventLogger::log()`** — append-only writer for `auth_events`;
  never throws (a logging failure must never block a login).
- **`AuthMailer::send()`** — sends via `notification_templates`; respects
  `NOTIFICATIONS_TEST_MODE` + whitelist; writes to `notification_log`.
- **`Totp`** — pure-PHP RFC 6238 TOTP + base32 + recovery-code generator.
  Google Authenticator / 1Password / Authy / MS Authenticator compatible.
- **`UserAnomalyService::scanUser()` / `scanAll()`** — deterministic
  rule engine raising 14 flag types (DORMANT, PASSWORD_STALE,
  FREQUENT_FAILED_LOGINS, MULTIPLE_IPS_24H, MULTIPLE_DEVICES_24H,
  NO_MFA_FOR_ADMIN, WEAK_PASSWORD_AGE, INVITATION_OLD, ACCOUNT_NEVER_USED,
  ROLE_ACTIVITY_MISMATCH, UNUSUAL_HOURS, IMPOSSIBLE_TRAVEL,
  EMAIL_UNVERIFIED_ADMIN, LOCKED_OUT). Risk score = Σ weights, clamped [0,100].

### 16.3 Controllers ([api/app/Http/Controllers/Auth/](./api/app/Http/Controllers/Auth/) + [api/app/Http/Controllers/Admin/](./api/app/Http/Controllers/Admin/))

All live-production controllers — **native Laravel Auth + Sanctum** only.

| Controller | Endpoints |
| --- | --- |
| `DashboardAuthController`               | login, 2fa-verify, logout, logout-all, me, refresh, change-password, sessions, revoke-session |
| `DashboardEmailVerificationController`  | verify-email/send, send-for, confirm |
| `DashboardPasswordResetController`      | password/forgot, password/reset |
| `TwoFactorController`                   | 2fa/status, 2fa/setup, 2fa/confirm, 2fa/disable, 2fa/recovery-codes |
| `TrustedDeviceController`               | trusted-devices CRUD + revoke-all |
| `Admin\UsersAdminController`            | users CRUD + stats + bulk + reports + activity + flags + rescan + invitation |
| `Admin\UserAssignmentsController`       | per-user and cross-user scoping (country → POE) |
| `Admin\GeographyController`             | countries / districts / POEs / nested tree |
| `Admin\SystemHealthController`          | /system/health — DB, emails, queue, storage, auth 24h |
| `Admin\AuditController`                 | /audit/feed (merged), /auth, /users, /alerts, /notifications, /stats |

All mount under `/api/v2/`. Admin surface is under `/api/v2/admin/*` and
gated by `auth:sanctum` today; role middleware will be layered in the next
pass. See [`routes/api.php`](./api/routes/api.php) — **60 v2 routes** in total.

### 16.4 Hardening rules enforced on login

- Rate limit: 5 failed attempts per 15 min per `(ip, email)` pair → 429 response.
- Progressive lockout: 5 fails = 15 min lock; 10 fails = 1 h lock.
- Generic error messaging — no user enumeration.
- Constant-time-ish bcrypt check (always hashes, even when user is missing).
- Every branch (success, failure, locked, rate-limited, 2FA-fail) writes an
  `auth_events` row so the audit trail is complete.
- On LOGIN_OK from an IP / UA unseen in the last 30 days → send
  `AUTH_NEW_LOGIN_DEVICE` email.
- On lockout → send `AUTH_ACCOUNT_LOCKED` email.
- After lockout / reset / 2FA-disable → revoke all personal_access_tokens for
  that user.
- Anomaly rescan runs fire-and-forget after every LOGIN_OK.

### 16.5 2FA flow

1. **Setup** — `/auth/2fa/setup` returns a fresh secret + `otpauth://` URI.
   Secret is held in cache (10 min TTL), NOT written to users yet.
2. **Confirm** — `/auth/2fa/confirm` with a 6-digit code promotes the secret
   into `users.two_factor_secret` + stores 10 SHA-256-hashed recovery codes.
   Raw codes returned ONCE. Emits `AUTH_TWOFA_ENABLED` email.
3. **Login challenge** — when `/auth/login` matches credentials, if
   `two_factor_confirmed_at` is set AND no trusted-device token presented,
   returns `challenge_id` instead of a token. Cache row expires in 5 min.
4. **Challenge verify** — `/auth/2fa-verify` accepts either a valid TOTP
   code (drift ±1 step) or a previously-unused recovery code.
5. **Disable** — `/auth/2fa/disable` requires current password. Emits
   `AUTH_TWOFA_DISABLED` (CRITICAL severity on `auth_events`).

### 16.6 Trusted devices ("passkey-lite")

No external crypto library is required. A trusted device is a server-side
random 32-byte token + SHA-256 hash bound to `(user_id, device_fingerprint,
user_agent)`. The raw token is returned once and stored in the browser's
`localStorage`. On next login the client sends `device_token`; the server
looks up the hash, confirms it belongs to the target user and hasn't expired
(30-day TTL), and skips the TOTP step.

Pair it with `navigator.credentials.get()` on the client to require platform
biometrics (Face ID / Touch ID / Windows Hello) before the trusted token can
leave local storage — zero-crypto passkey-UX equivalent that works on every
browser without requiring a FIDO server.

### 16.7 Anomaly flags + risk scoring

Every `LOGIN_OK`, every admin mutation, and every page load on
`/admin/users` triggers `UserAnomalyService::scanUser()`. The engine:

1. Runs each of 14 rules; raises / refreshes / clears `user_anomaly_flags`
   rows for this user.
2. Clears flags that no longer match (fresh scan = truth).
3. Computes `risk_score = sum(FLAG_WEIGHTS[code])`, clamps to [0, 100].
4. Writes `risk_score`, `risk_flags_json`, `risk_score_updated_at` back to `users`.

The PWA surfaces the score in three places: the global topbar (banner if
≥ 80), the `/settings` hero, and the `/admin/users` risk column.

### 16.8 Auth email templates

Seed: `php artisan db:seed --class=AuthEmailTemplatesSeeder`

Templates (all in `notification_templates`):

```
AUTH_WELCOME · AUTH_INVITATION · AUTH_VERIFY_EMAIL
AUTH_PASSWORD_RESET · AUTH_PASSWORD_CHANGED
AUTH_TWOFA_ENABLED · AUTH_TWOFA_DISABLED
AUTH_NEW_LOGIN_DEVICE · AUTH_ACCOUNT_LOCKED · AUTH_SUSPENDED
```

Each has a distinct hero palette (green for welcome / enabled, amber for
reset, red for locked / disabled / suspended, navy for invitation, sky for
verify / new-device). Multipart HTML + text, respects `NOTIFICATIONS_TEST_MODE`.

### 16.9 PWA: mother layout + command palette

[`DefaultLayout.vue`](./pwa/src/layouts/DefaultLayout.vue) is structured as
three stacked sections in the rail:

```
┌──────────────┐
│ STICKY brand │   ← company logo, environment, collapse toggle
├──────────────┤
│ SCROLL menu  │   ← nav groups (Operations, Intelligence, Communications, Administration)
├──────────────┤
│ STICKY footer│   ← current user + role chip
└──────────────┘
```

Only the middle **scrolls**. The top bar + bottom tab bar (mobile) are
sticky on their edges. Desktop rail can collapse to 72 px via chevron.

**⌘K command palette** ([CommandPalette.vue](./pwa/src/components/CommandPalette.vue)):
Fuse.js-backed fuzzy search across every nav entry + shortcuts (Invite
user · Open my profile · Security settings · Trigger daily digest · Retry
failed emails · Open war-room · Sign out). Keys: `⌘K` / `Ctrl+K` / `/` to
open · `↑↓` navigate · `↵` jump · `Esc` close. Number keys `1–9` jump
directly to the Nth nav entry when palette is closed.

### 16.10 PWA: auth pages

| Path | Purpose |
| --- | --- |
| `/login`              | Premium 2-step sign-in (credentials → optional 2FA) matching mobile-app visual language |
| `/forgot-password`    | Request reset email |
| `/reset-password`     | Confirm token + set new password with policy meter |
| `/verify-email`       | Click-through from AUTH_VERIFY_EMAIL |
| `/accept-invite`      | Invitation flow — user sets password + gets auto-verified email |

Pinia auth store ([`stores/auth.js`](./pwa/src/stores/auth.js)) is the
single source for token + user state. `services/api.js` injects the bearer
token on every request and auto-redirects to `/login?redirect=…` on 401.
Router guards in `router/index.js` keep all non-standalone pages behind
`isAuthenticated`.

### 16.11 PWA: settings + admin pages

| Path | Backing endpoint(s) |
| --- | --- |
| `/settings`              | `/v2/auth/me` + `PATCH /v2/admin/users/{id}` |
| `/settings/security`     | `/v2/auth/2fa/*`, `/v2/auth/sessions`, `/v2/auth/trusted-devices`, `/v2/auth/change-password` |
| `/admin/users`           | `/v2/admin/users` (list, detail, invite, bulk, rescan, reports) |
| `/admin/system`          | `/v2/admin/system/health` — auto-refresh 15 s |
| `/admin/audit`           | `/v2/admin/audit/{feed,auth,users,alerts,notifications,stats}` |
| `/admin/geography`       | `/v2/admin/geography/{countries,districts,poes,tree}` |

### 16.12 Open-source-only posture

- Password hashing via Laravel's bcrypt (native).
- 2FA: pure-PHP RFC 6238 (`app/Support/Totp.php`). No `pragmarx/*`,
  no Google Authenticator SaaS, no Twilio.
- QR code in `/settings/security` is fetched from an open public API
  (`api.qrserver.com`) — replace with a PHP QR generator if you need 100 %
  offline enrolment.
- Trusted-device + local biometric prompt via `navigator.credentials.get()`
  — browser-native, zero server-side WebAuthn crypto library needed.
- Anomaly detection is deterministic, not ML-based; no external model or
  analytics pipeline.

### 16.13 Smoke test (dev)

```bash
# API
cd api
php artisan serve --port=8000

# PWA
cd ../pwa
pnpm dev
# → http://localhost:3100/

# In another shell:
curl -X POST http://localhost:8000/api/v2/auth/login \
  -H "Content-Type: application/json" \
  -d '{"login":"ecsa","password":"SecureDemo123!xyz"}'
# → returns { token, user }  (seed this password via an artisan tinker one-liner)
```

---

## 17. National PHEOC Admin Panel — Mother Layout (2026-04-22)

> **Status:** walking-skeleton ready. Shell + navigation + command palette +
> AI Copilot dock are wired and render cleanly (`view('admin.dashboard')` →
> 123 KB HTML, zero PHP errors). All 70+ menu entries use `href="#"` until
> the corresponding module ships.

### 17.1 MVP scope (why this is small on purpose)

The original plan in `dashboard.txt` called for 13 modules / ~60 pages / 32
reports. That has been trimmed to **7 modules / ~20 pages / 5 artefacts**
because everything else was either (a) already expressible as a filter/tab
inside a core module, (b) ops concern not PHEOC concern, or (c) polish that
does not change outbreak response. The test for every screen is:
*"If this disappeared during a Marburg outbreak, would response degrade?"*

The 7 MVP modules — the whole product — are:

| # | Module | Route prefix | Purpose |
|---|---|---|---|
| M1 | Command Overview | `/admin/dashboard` | One-screen national cockpit + Copilot brief |
| M2 | Alert Lifecycle (Hub + War Room + 7-1-7 + Follow-ups) | `/admin/alerts*`, `/admin/compliance/717` | The product. Full case lifecycle, every terminal state covered |
| M3 | Cases & Screening | `/admin/cases*`, `/admin/primary*` | Read-through to secondary_screenings + 6 children; primary throughput |
| M4 | Intelligence | `/admin/intelligence*` | 6 tripwires + 72h national narrative brief |
| M5 | Communications | `/admin/comms*`, `/admin/responders*` | Inbox · Outbound (log + templates + digests + suppressions) · Responders |
| M6 | Aggregated Reports | `/admin/aggregated*` | Template CRUD · Submissions · National rollup |
| M7 | Administration | `/admin/users*`, `/admin/assignments`, `/admin/audit`, `/admin/system` | Users · Scope · Audit · Health |

**The 5 artefacts** (not 32 reports): live dashboard, case-file PDF, 7-1-7
quarterly PDF, 72h national brief email, audit CSV. Every other chart lives
inside a module, not as a standalone "report page".

### 17.2 Files shipped in this pass

```
api/
├── app/Support/blade_helpers.php              ← tone → Tailwind class maps (autoloaded)
├── composer.json                              ← added "files" autoload for helpers
├── routes/web.php                             ← /admin/dashboard demo route
└── resources/views/
    ├── admin/
    │   ├── layout.blade.php                   ← MOTHER LAYOUT (Alpine root state, theme, shortcuts)
    │   ├── dashboard.blade.php                ← walking-skeleton M1 page
    │   └── partials/
    │       ├── sidebar.blade.php              ← full 70-entry menu · desktop rail + mobile drawer
    │       ├── topbar.blade.php               ← hamburger · search · status chips · Copilot · inbox · user menu
    │       ├── command-palette.blade.php      ← ⌘K global palette (quick actions · navigate · ask Copilot)
    │       └── copilot-dock.blade.php         ← ⌘J AI guidance panel (brief · actions · quick prompts · composer)
    └── components/                            ← anonymous nav Blade components
        ├── nav-section.blade.php              ← section heading + accent divider
        ├── nav-item.blade.php                 ← top-level link (icon · label · badge)
        ├── nav-group.blade.php                ← collapsible group (Alpine-local open state)
        └── nav-sub.blade.php                  ← child link inside a group (coloured dot · badge)
```

### 17.3 Extension contract (for every future page)

```blade
@extends('admin.layout')

@section('title', 'Case Register')                {{-- <title> tab --}}
@section('heading', 'Case Register')              {{-- big H1 --}}
@section('subheading', '412 cases · 58 POEs · 24h') {{-- grey sub-title --}}

@section('breadcrumbs')                           {{-- optional; falls back to title --}}
    <a href="#">Command Centre</a> › <a href="#">Cases</a> › <span>Register</span>
@endsection

@section('page_actions')                          {{-- right-side buttons in header --}}
    <button …>Export</button>
@endsection

@section('content')
    {{-- page body --}}
@endsection

@push('head')    {{-- page-specific <head> tags (CSS, preloads) --}}   @endpush
@push('scripts') {{-- page-specific <script> tags (chart inits) --}}   @endpush
```

### 17.4 Mobile-first design contract

- **< 1024 px (`lg:`)** — sidebar becomes an off-canvas drawer. Hamburger in
  topbar opens it; backdrop dismiss; `translate-x-full` → `translate-x-0`.
- **≥ 1024 px** — sidebar is a fixed 18 rem rail. Collapse toggle shrinks it
  to a 5 rem icon-rail (persisted to `localStorage['pheoc.sidebar']`).
- **All controls are ≥ 40 × 40 px** tap targets (`h-10 w-10`).
- **Safe-area insets** honoured on topbar (`pt-safe`) and footer (`pb-safe`)
  for iOS PWA / notched devices.
- **Status chips collapse** to icon-only under `xl:` so the topbar never wraps.
- **`prefers-reduced-motion`** forces all animations to `.01ms` via CSS.
- **Tested** at 320 px (iPhone SE), 390 px (iPhone 14), 768 px (iPad),
  1024 / 1280 / 1536 / 1920 px.

### 17.5 Keyboard shortcuts

| Keys | Action |
|---|---|
| `⌘K` / `Ctrl+K` | Open command palette |
| `⌘J` / `Ctrl+J` | Toggle PHEOC Copilot dock |
| `ESC` | Close any open overlay (palette, dock, drawer, popovers) |

### 17.6 Alpine root state (lives on `<body>`)

```js
{
    sidebar:   boolean,   // desktop rail expanded
    drawer:    boolean,   // mobile drawer open
    cmd:       boolean,   // command palette open
    copilot:   boolean,   // AI dock open
    userMenu:  boolean,   // top-right account popover
    notifs:    boolean,   // top-right notifications popover
    tenant:    boolean,   // (reserved) tenant switcher popover
    closeAllPopovers() { /* resets the three above */ }
}
```

### 17.7 PHEOC Copilot — deterministic AI layer

The Copilot dock in the sidebar currently renders **hard-coded** demonstration
content. The live implementation lands as a single service:

```
App\Services\PheocCopilot
  ├─ narrate(Alert $a): string                    — timeline prose
  ├─ recommend(array $ctx): array                 — next-action list
  ├─ rankDifferentials(SecondaryScreening): array — disease confidence
  ├─ suggestCloseReason(Alert): ['cat' => …]      — close-dialog pre-fill
  ├─ escalationRationale(Alert): string           — pre-filled escalation note
  └─ triageBrief(IntelligenceSnapshot): string    — 72h national paragraph
```

**No LLM dependency.** Every method is rule-driven, fed by the existing
`IntelligenceEngine`, `CaseContextBuilder`, and `DiseaseIntel` (73 KB WHO
taxonomy at `api/app/Support/DiseaseIntel.php`). Deterministic →
unit-testable → safe to run during outbreak response.

### 17.8 Preview locally

```bash
cd api
php artisan serve --port=8000
# visit http://localhost:8000/admin/dashboard
```

Resize the browser below 1024 px to see the drawer; press ⌘K for the palette,
⌘J for the Copilot dock.

### 17.9 Next pass

1. Build `App\Services\PheocCopilot` + `POST /admin/copilot/ask` endpoint,
   wire the dock composer.
2. Implement **M1 Command Overview** (real KPIs from `HomeDashboardController`
   + `IntelligenceController` via Livewire 15 s poll).
3. Implement **M2 Alert Hub (kanban)** + **War Room** end-to-end, including
   all 7 close-state flows (RESOLVED · FALSE_POSITIVE · DUPLICATE → merged ·
   LOST_TO_FOLLOWUP · TRANSFERRED_OUT_OF_COUNTRY · DECEASED · OTHER+note).

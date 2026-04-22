# Collaboration & Notification Controllers — Reference

This is the authoritative reference for the controllers that drive the
PWA's **alert response workflow** — how stakeholders open, respond to,
and collaboratively resolve suspected cases. Read in tandem with
`routes/api.php`.

The underlying DB schema (added in
`database/seeds-sql/08_collaboration_tables.sql`):

```
alert_collaborators     ← war-room members + role
alert_comments          ← threaded discussion (parent_id)
alert_evidence          ← file / photo / lab-result refs
alert_timeline_events   ← unified chronological feed
alert_handoffs          ← formal level-to-level transitions
alert_breach_reports    ← 7-1-7 root-cause analyses
alerts (+columns)       ← current_owner_user_id, current_owner_level,
                          pheic_declared_at, pheic_declared_by_user_id
```

Response envelope across every endpoint:

```json
{ "ok": true,  "data": { … } }
{ "ok": false, "error": "…", "errors"?: {…}, "ctx"?: "handlerName" }
```

Actor identity is resolved from the `X-User-Id` header (shim until
Sanctum is wired).

---

## 1. `AlertCollaborationController` — the war-room

Coordinates multi-stakeholder response across the IHR escalation ladder
(POE → DISTRICT → PHEOC → NATIONAL → WHO). Every mutation writes into
`alert_timeline_events` so the UI shows a single chronological feed that
includes SYSTEM + HUMAN + EMAIL + WORKFLOW + BREACH + CLINICAL events.

### Snapshot + timeline

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `GET` | `/alerts/{id}/war-room` | One-shot payload: alert, context vars, collaborators, threaded comments, evidence, timeline (last 200), follow-ups, handoffs, breach reports, email activity. |
| `GET` | `/alerts/{id}/timeline?since=ISO&category=&limit=` | Incremental timeline feed (polling / websocket replacement). |

### Collaborators

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `GET`    | `/alerts/{id}/collaborators` | Active collaborators joined on `users`. |
| `POST`   | `/alerts/{id}/collaborators` | Add stakeholder. Body: `{ user_id, role, level?, notes? }`. Roles: `INCIDENT_COMMANDER`, `CASE_OWNER`, `CLINICAL_LEAD`, `LAB_LIAISON`, `DISTRICT_LIAISON`, `PHEOC_LIAISON`, `NATIONAL_LIAISON`, `WHO_LIAISON`, `CONTACT_TRACER`, `RISK_COMMS`, `LOGISTICS`, `OBSERVER`. Idempotent — reactivates a soft-removed row. |
| `PATCH`  | `/alert-collaborators/{id}` | Update role / level / notes. |
| `DELETE` | `/alert-collaborators/{id}` | Soft-remove (sets `is_active = 0`). |

### Comments (threaded, with @mentions + reactions)

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `GET`    | `/alerts/{id}/comments` | Returns comments nested by `parent_id`. |
| `POST`   | `/alerts/{id}/comments` | Body: `{ body, parent_id?, body_format?: MARKDOWN\|PLAIN\|HTML, mentions?: int[], visibility?: ALL\|INTERNAL\|EXTERNAL }`. |
| `PATCH`  | `/alert-comments/{id}` | Edit own comment (author-only). |
| `DELETE` | `/alert-comments/{id}` | Soft-delete. |
| `POST`   | `/alert-comments/{id}/pin` | Toggle pinned. |
| `POST`   | `/alert-comments/{id}/react` | Body: `{ code: ack\|thumbs_up\|thumbs_down\|flag\|done\|question }`. |

### Evidence (attachment refs)

The controller stores **references**, not raw file bytes. Upload to your
storage layer first (S3 / local disk), then POST the resulting `file_ref`.

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `GET`    | `/alerts/{id}/evidence` | List. |
| `POST`   | `/alerts/{id}/evidence` | Body: `{ category, title, description?, file_ref?, file_mime?, file_size_bytes?, external_url?, followup_id?, visibility? }`. Categories: `DOCUMENT · PHOTO · LAB_RESULT · CONSENT · WHO_FORM · CONTACT_LIST · SOP_SIGN_OFF · PPE_CHECKLIST · OTHER`. |
| `DELETE` | `/alert-evidence/{id}` | Soft-delete. |

### Handoffs (level transitions)

Formal acceptance / rejection of ownership when an alert escalates.

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `GET`  | `/alerts/{id}/handoffs` | List with from/to names. |
| `POST` | `/alerts/{id}/handoffs` | Body: `{ from_level, to_level, to_user_id?, reason, handoff_notes?, notify?: boolean }`. Updates `alerts.routed_to_level` + `current_owner_*`. Fires email fan-out if `notify=true`. |
| `POST` | `/alert-handoffs/{id}/accept` | Body: `{ decision_notes? }`. |
| `POST` | `/alert-handoffs/{id}/reject` | Body: `{ decision_notes }`. |

### Escalation · Reopen · Reassign · Breach · PHEIC · External info

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `POST`  | `/alerts/{id}/escalate` | Body: `{ to_level, reason, notify? }`. |
| `POST`  | `/alerts/{id}/reopen` | Body: `{ reason }`. 409 unless currently CLOSED. |
| `POST`  | `/alerts/{id}/reassign` | Body: `{ owner_user_id, level?, reason? }`. |
| `POST`  | `/alerts/{id}/breach-report` | Body: `{ phase: DETECT\|NOTIFY\|RESPOND, target_hours, elapsed_hours, root_cause_category, root_cause_text, mitigation_plan, owner_user_id?, owner_level?, target_resolve_at?, contributing_factors?: object }`. Computes `breach_minutes`. |
| `GET`   | `/alerts/{id}/breach-reports` | List. |
| `PATCH` | `/alert-breach-reports/{id}` | Update status / resolution. |
| `POST`  | `/alerts/{id}/pheic-declare` | Body: `{ reason, notify? }`. Sets `pheic_declared_at` + raises route to NATIONAL. |
| `POST`  | `/alerts/{id}/request-external-info` | Body: `{ responder_id, message, subject? }`. Dispatches `RESPONDER_INFO_REQUEST` email with a token. |

---

## 2. `ExternalRespondersController` — registry of partners

Hospitals, laboratories, EMS, law enforcement and partner agencies.

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `GET`    | `/external-responders` | Paginated + filter (`country_code`, `responder_type`, `district_code`, `search`). |
| `GET`    | `/external-responders/stats` | Totals by type + country. |
| `POST`   | `/external-responders` | Body: `{ responder_type, name, organisation?, position?, email, phone?, country_code, district_code?, notes?, is_active? }`. |
| `GET`    | `/external-responders/{id}` | Detail + last 50 info-requests. |
| `PATCH`  | `/external-responders/{id}` | Partial update. |
| `DELETE` | `/external-responders/{id}` | Soft-delete. |

Responder types: `HOSPITAL · LAB · EMS · LAW_ENFORCEMENT · PARTNER_AGENCY · OTHER`.

---

## 3. `ResponderInfoRequestsController` — inbound response loop

Tracks every token-gated info request + the external reply.

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `GET`   | `/responder-info-requests` | Filter by status, country, alert, responder. |
| `GET`   | `/responder-info-requests/{id}` | Detail. |
| `GET`   | `/responder-info-requests/by-token/{token}` | Token lookup. Returns **410 Gone** if expired. |
| `POST`  | `/responder-info-requests/{id}/cancel` | Cancel a `SENT` request. |
| `POST`  | `/responder-info-requests/by-token/{token}/respond` | **Public**, token-auth'd. Body: `{ response_body, responder_name?, payload_extra? }`. Flips status to `RECEIVED`, emits `EXTERNAL_INFO_RESPONDED` timeline event, auto-posts a SYSTEM comment on the parent alert. |

Token TTL: 7 days. Rate-limit at route-layer.

---

## 4. `NotificationTemplatesController` — admin CRUD + preview

Live edits against `notification_templates` — no deploy needed.

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `GET`    | `/notification-templates` | List (with body byte counts). |
| `GET`    | `/notification-templates/token-reference` | Token autocomplete source for the PWA editor. |
| `GET`    | `/notification-templates/{code}` | Detail + usage-by-status. |
| `POST`   | `/notification-templates` | Create. |
| `PATCH`  | `/notification-templates/{code}` | Update. |
| `DELETE` | `/notification-templates/{code}` | Soft-disable (`is_active = 0`). |
| `POST`   | `/notification-templates/{code}/preview` | Body: `{ alert_id?, sample?: true }`. Returns rendered `{ subject, html, text }`. |
| `GET`    | `/notification-templates/{code}/usage` | SENT/SKIPPED/FAILED counts for 24h + 7d. |

Both the `{{token}}` and `{{{html_block}}}` forms are supported in the
renderer — triple-brace is not HTML-escaped so `CaseContextBuilder`
HTML fragments (vitals_html, symptoms_html, disease_intel_html, etc.)
pass through verbatim.

---

## 5. `IntelligenceController` — read-only dashboard feeds

All country-scoped via `?country_code=UG` (defaults to `UG`).

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `GET` | `/intelligence/dashboard` | Combined snapshot. |
| `GET` | `/intelligence/silent-poes` | POEs silent > 24h (active in 7d). |
| `GET` | `/intelligence/unsubmitted` | POEs offline > 3d. |
| `GET` | `/intelligence/dormant-accounts?days=14` | Users without login. |
| `GET` | `/intelligence/stuck-alerts` | Open past SLA (4h CRIT · 24h HIGH · 48h other). |
| `GET` | `/intelligence/overdue-followups` | RTSL-14 items past due. |
| `GET` | `/intelligence/case-spikes` | Disease × district anomalies vs 14-day baseline. |
| `GET` | `/intelligence/kpi/seven-one-seven` | 30-day compliance % per phase. |
| `GET` | `/intelligence/timeline/national` | Country-wide timeline feed. |
| `GET` | `/intelligence/disease-ranking` | Top suspected diseases (7d). |
| `GET` | `/intelligence/heatmap/poes` | Per-POE × day activity cells (7d). |
| `GET` | `/intelligence/map/latest` | Latest 100 alerts with geography tags. |

---

## 6. `DigestsController` — manual preview + trigger

Mirrors the cron schedulers for admin UX.

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `GET`  | `/digests/daily/preview` | Render daily digest vars. |
| `GET`  | `/digests/national/preview` | Render national intelligence vars. |
| `POST` | `/digests/daily/send` | Fan-out DAILY_REPORT now. |
| `POST` | `/digests/national/send` | Fan-out NATIONAL_INTELLIGENCE now. |
| `POST` | `/digests/followups/send` | Fan-out FOLLOWUP_DUE / FOLLOWUP_OVERDUE now. |
| `POST` | `/digests/retry-failed` | Retry FAILED `notification_log` rows. |
| `GET`  | `/digests/history` | Last 200 digest send rows grouped by day/template/status. |

Respect `NOTIFICATIONS_TEST_MODE` in `.env` — when `=1`, only whitelisted
addresses receive.

---

## 7. `NotificationsInboxController` — per-user inbox

Read-state persists via `notification_log_reads` (auto-created).

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `GET`  | `/inbox` | Paginated (`limit`, `offset`) + filters (`template_code`, `status`, `entity_type`, `entity_id`, `unread_only`). |
| `GET`  | `/inbox/unread-count` | `{ count }` for the bell badge. |
| `GET`  | `/inbox/{id}` | Full rendered item. |
| `GET`  | `/inbox/facets` | Faceted counts by template, status, entity. |
| `POST` | `/inbox/mark-read` | Body: `{ ids: int[] }`. |
| `POST` | `/inbox/mark-unread` | Body: `{ ids: int[] }`. |
| `POST` | `/inbox/mark-all-read` | All items addressed to the authenticated user. |

---

## Timeline event taxonomy

`alert_timeline_events.event_code` values the PWA renders:

```
ALERT_CREATED · ACKNOWLEDGED · COMMENT_POSTED · COLLABORATOR_ADDED
COLLABORATOR_REMOVED · COLLABORATOR_UPDATED · EVIDENCE_UPLOADED
EVIDENCE_DELETED · HANDOFF_SENT · HANDOFF_ACCEPTED · HANDOFF_REJECTED
ESCALATED · FOLLOWUP_COMPLETED · FOLLOWUP_OVERDUE · BREACH_717_DETECTED
BREACH_ROOT_CAUSE_LOGGED · BREACH_UPDATED · EMAIL_SENT · EMAIL_FAILED
EXTERNAL_INFO_REQUESTED · EXTERNAL_INFO_RESPONDED
EXTERNAL_INFO_CANCELLED · PHEIC_DECLARED · ALERT_REOPENED
ALERT_CLOSED · REASSIGNED
```

Each has `event_category ∈ {SYSTEM, HUMAN, EMAIL, WORKFLOW, BREACH, CLINICAL}`
and a `severity ∈ {INFO, WARN, ERROR, CRITICAL}` that the UI colours.

---

## Worked example — "Loop in the district hospital"

1. Operator opens war-room:
   `GET /alerts/{id}/war-room` → full payload.
2. Picks a responder from the registry:
   `GET /external-responders?responder_type=HOSPITAL&district_code=KYOTERA`.
3. Sends the request:
   `POST /alerts/{id}/request-external-info`
   `{ "responder_id": 42, "message": "Please confirm…", "subject": "…" }`.
   → Dispatcher emails `RESPONDER_INFO_REQUEST` with a token, a
   `responder_info_requests` row is created, and a timeline event
   `EXTERNAL_INFO_REQUESTED` appears in the war-room feed.
4. The hospital clicks the token link in the email and opens the PWA at
   `/respond/{token}` (a **public** page, no auth).
5. They submit the response form, which `POST`s to
   `/responder-info-requests/by-token/{token}/respond`.
   → Status flips to `RECEIVED`, a `SYSTEM` comment is auto-posted on
   the parent alert, and an `EXTERNAL_INFO_RESPONDED` event is emitted.
6. Back on the war-room page, the PWA polling `GET /alerts/{id}/timeline?since=…`
   picks up the new event and injects the comment into the feed.

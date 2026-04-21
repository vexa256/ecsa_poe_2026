The POE Digital Screening Tool is an offline-first mobile and web system for rapid primary screening and WHO/IHR-aligned secondary screening at Points of Entry within a national hierarchy (National → Province/PHEOC → District → POE). Built with Ionic Vue + Capacitor using dixie.js for local storage and a Laravel + MySQL backend, it captures ultra-fast primary records (gender and optional temperature) and, when symptoms are detected, automatically creates a traceable notification to trigger secondary screening by designated officers. It also supports a separate aggregated reporting pathway for high-volume situations, with all records working fully offline and syncing later through a simple, reliable manual submission workflow with role-based access and auditability throughout.

# POE Sentinel — AI Developer Notes

## Read this before touching any file. Every rule here was learned from a real bug.

---

## 1. THE FOUR LAWS (violation = blank page, silent data loss, or 404)

### LAW 1 — Navigate with server integer `id`. Never with `client_uuid`.

```js
// CORRECT
router.push("/secondary-screening/" + record.id); // id = MySQL BIGINT

// WRONG — produces 404, controller receives a UUID string, Laravel returns nothing
router.push("/secondary-screening/" + record.client_uuid); // UUID string
```

**Why it killed us:** List view cached records with `client_uuid` as the key. `id` was undefined on stale IDB records. The detail view received a UUID, sent `GET /secondary-screenings/fafce657-...`, got 404, showed a blank page. Three hours of debugging.

**Rule:** Before `router.push`, assert `Number.isInteger(Number(id)) && id > 0`. If not, log and abort.

### LAW 2 — No Authorization header. API is fully open by design.

```js
// WRONG — causes silent auth failure, returns nothing
headers: { 'Authorization': 'Bearer ' + token }

// CORRECT
headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
```

Auth middleware is not wired in `routes/api.php`. Sending a token that doesn't exist fails silently.

### LAW 3 — Every cached IDB record must carry `id` as the server integer.

```js
// CORRECT
await dbPut(STORE.USERS_LOCAL, { client_uuid: idbKey, id: u.id, ...u });

// WRONG — views read .id, not .server_user_id
await dbPut(STORE.USERS_LOCAL, {
  client_uuid: idbKey,
  server_user_id: u.id,
  ...u,
});
```

### LAW 4 — Router ordering: specific paths before wildcard params.

```js
// CORRECT — "records" won't be caught by /:notificationId
{ path: '/secondary-screening/records', ... },         // ← FIRST
{ path: '/secondary-screening/:notificationId', ... }, // ← SECOND

// WRONG — "records" matches /:notificationId, loads wrong view
{ path: '/secondary-screening/:notificationId', ... }, // ← FIRST (BUG)
{ path: '/secondary-screening/records', ... },         // ← SECOND (never reached)
```

Same applies to `/alerts/summary` before `/alerts/{id}` in Laravel `api.php`.

---

## 2. THE DATA LAYER — DEXIE (poeDB.js)

**Only file allowed to touch Dexie:** `src/services/poeDB.js`. Never instantiate Dexie in a view.

```js
// BANNED in every view — zero exceptions
new Dexie(...)
import Dexie from 'dexie'
indexedDB.open(...)
```

**Import ONLY from poeDB:**

```js
import {
  dbPut,
  dbGet,
  dbGetByIndex,
  dbAtomicWrite,
  dbCountIndex,
  genUUID,
  isoNow,
  createRecordBase,
  STORE,
  SYNC,
  APP,
} from "@/services/poeDB";
```

**Key rules:**

- `dbPut` → new records only
- `safeDbPut` → any async update (version-guarded, prevents lost updates)
- `dbAtomicWrite` → any write that spans two stores (primary + notification, case + notification)
- `dbCountIndex` → dashboard counts (O(1), never `dbGetAll().length`)
- `dbReplaceAll` → secondary child tables (symptoms, exposures, actions)
- `SYNC.LABELS[record.sync_status]` → UI display. Never show raw `UNSYNCED`/`SYNCED`/`FAILED` strings.

**`record_version` increments on every write without exception.**

---

## 3. THE DISEASE ENGINE — FILE ARCHITECTURE

### `Diseases.js` — The scoring engine (do not modify)

- 41 diseases, symptom weights, gate rules, triage overrides
- Single export: `window.DISEASES.scoreDiseases(present, absent, exposures, context)`
- `syndrome_bonus` was hardcoded `= 0` (bug). Fixed by intelligence layer.
- `outbreak_bonus` was always 0 (bug). Fixed by intelligence layer.

### `Diseases_intelligence.js` — The clinical brain (loads after Diseases.js)

- Patches `scoreDiseases()` to activate `syndrome_bonus` (8pts) via `ENGINE_TO_WHO_SYNDROME` map
- Populates `outbreak_context[]` from visited countries via `ENDEMIC_COUNTRIES` oracle
- Exports 9 API functions on `window.DISEASES`
- **Load order in `main.ts` is mandatory:** `Diseases.js` → `Diseases_intelligence.js` → `exposures.js`

### `exposures.js` — Exposure catalog (20 WHO exposures)

- Each exposure has `engine_codes[]` mapping DB codes to engine signal names
- `window.EXPOSURES.mapToEngineCodes(dbRecords)` → call this, pass result to `getEnhancedScoreResult()`
- **Old version detection:** if `exposures.js` has fewer than 20 entries OR lacks `engine_codes` field OR is only ~4KB → it is the old stub. Replace it.

### How to call the engine (one call does everything):

```js
const enhanced = window.DISEASES.getEnhancedScoreResult(
  presentSymptoms, // string[] — symptom IDs confirmed present
  absentSymptoms, // string[] — symptom IDs confirmed absent
  engineExposureCodes, // string[] — from window.EXPOSURES.mapToEngineCodes(dbRecords)
  visitedCountries, // [{ country_code, travel_role }]
  vitals, // { temperature_c, oxygen_saturation, pulse_rate, ... }
);
// enhanced.syndrome, enhanced.ihr_risk, enhanced.is_non_case, enhanced.top_diagnoses
// enhanced.outbreak_context_used, enhanced.global_flags, enhanced.clinical_validation
```

---

## 4. HOW TO DETECT OLD FILE VERSIONS

| File                       | Old version signal                                          | Correct version signal                                             |
| -------------------------- | ----------------------------------------------------------- | ------------------------------------------------------------------ |
| `exposures.js`             | < 20 exposure entries, no `engine_codes` field, ~4KB        | 20 entries, `engine_codes[]`, `mapToEngineCodes()` exported, ~40KB |
| `Diseases_intelligence.js` | Does not exist in repo                                      | 3,374 lines, `window.DISEASES.getEnhancedScoreResult` defined      |
| `SecondaryScreening.vue`   | Contains `function deriveAutoSyndrome`                      | `deriveAutoSyndrome` absent — replaced by intelligence layer       |
| `SecondaryScreening.vue`   | `dispositionCase()` has two sequential `safeDbPut` calls    | Uses `dbAtomicWrite([case, notif])`                                |
| `index.js`                 | < 120 lines, missing `/alerts` `/aggregated` `/sync` routes | 150+ lines, all routes present                                     |
| `api.php`                  | Missing `AlertsController` import                           | Both `AlertsController` and `AggregatedController` imported        |

---

## 5. SECONDARY SCREENING — CRITICAL RULES

### Case-notification atomicity

The secondary case and its notification **must always transition together**:

```js
// CORRECT — atomic, both succeed or neither
await dbAtomicWrite([
  {
    store: STORE.SECONDARY_SCREENINGS,
    record: { ...caseRecord, case_status: "DISPOSITIONED" },
  },
  { store: STORE.NOTIFICATIONS, record: { ...notif, status: "CLOSED" } },
]);

// WRONG — if second write fails, case is DISPOSITIONED but notification stays OPEN forever
await safeDbPut(STORE.SECONDARY_SCREENINGS, {
  ...caseRecord,
  case_status: "DISPOSITIONED",
});
await safeDbPut(STORE.NOTIFICATIONS, { ...notif, status: "CLOSED" });
```

### Non-case pathway

When `enhanced.is_non_case === true`, auto-set syndrome=NONE, risk=LOW, disposition=RELEASED.
The officer can override via `officerOverride.overrideNonCase = true` — always give them this button.

### Exposure flow

```js
// Step 1: Load catalog into exposuresMap on mount
function initExposuresFromCatalog() {
  for (const exp of window.EXPOSURES?.getAll() || []) {
    exposuresMap[exp.code] = { ...exp, response: "UNKNOWN" };
  }
}

// Step 2: Load saved DB responses on top
const saved = await dbGetByIndex(
  STORE.SECONDARY_EXPOSURES,
  "secondary_screening_id",
  caseUuid,
);
for (const rec of saved) {
  if (exposuresMap[rec.exposure_code])
    exposuresMap[rec.exposure_code].response = rec.response;
}

// Step 3: Before scoring, translate to engine codes
const engineCodes = window.EXPOSURES.mapToEngineCodes(
  Object.values(exposuresMap),
);
```

### SpO2 threshold — WHO standard

SpO2 < 90% = `CRITICAL_HYPOXIA` → emergency triage required.  
Do NOT split into <85 CRITICAL and 85-89 SEVERE — both require identical response, and split causes test failures.

---

## 6. BUGS HIT — EXACT LESSONS

| Bug                             | Root cause                                                                              | Fix                                                                                       |
| ------------------------------- | --------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| `syndrome_bonus` always 0       | `scoreDiseases()` had `= 0; // implement later`                                         | Intelligence layer patches scoreDiseases, computes bonus via `ENGINE_TO_WHO_SYNDROME` map |
| `outbreak_bonus` always 0       | `outbreak_context[]` was never populated                                                | `buildOutbreakContext(visitedCountries)` uses ENDEMIC_COUNTRIES oracle                    |
| Exposure scores always 0        | Vue passed 5 hardcoded wrong engine codes                                               | `window.EXPOSURES.mapToEngineCodes(dbRecords)` translates correctly                       |
| Blank detail page               | `client_uuid` used in route param instead of `id`                                       | Assert `Number.isInteger(Number(id))` before every `router.push`                          |
| Notification split-brain        | Two sequential `safeDbPut` across stores                                                | `dbAtomicWrite([case, notif])` always                                                     |
| SpO2=87 not CRITICAL            | Two thresholds: <85 and <90 split differently                                           | Single threshold: <90 = CRITICAL_HYPOXIA                                                  |
| `lassa_fever` wrong score       | Duplicate key in ENDEMIC_COUNTRIES object — second overwrote first with fewer countries | Removed duplicate at line 413                                                             |
| `result.top_diagnoses.concat ?` | `.concat` is always truthy (it's a function) — array guard never fired                  | `Array.isArray(result.top_diagnoses)`                                                     |
| `String.repeat(-1)` crash       | `scoreBar` calculated `20 - pct` without clamping                                       | `Math.min(20, Math.max(0, pct))`                                                          |
| `deriveAutoSyndrome` conflict   | Vue had its own 10-rule syndrome classifier running in parallel with the engine's       | Removed from Vue — single source of truth in intelligence layer                           |
| IDB stale cache                 | Old cached records had no `id` field — `server_user_id` alias only                      | Every `cacheUsersLocally()` must write `id: u.id` explicitly                              |

---

## 7. UI/UX RULES (non-negotiable)

- **Dark theme everywhere:** `--background: #060B18` for content, `#0A0F1E` for headers. No light mode.
- **IonIcon:** always import the SVG object, never use string name attribute.
  ```js
  // CORRECT
  import { cloudUploadOutline } from 'ionicons/icons'
  <IonIcon :icon="cloudUploadOutline" />
  // WRONG — causes URL error in Capacitor
  <IonIcon name="cloud-upload-outline" />
  ```
- **Sync status in UI:** always `SYNC.LABELS[record.sync_status]` → shows "Pending"/"Uploaded"/"Queued". Never raw enum string.
- **Counts:** `dbCountIndex(store, 'sync_status', SYNC.UNSYNCED)` — never `(await dbGetAll(store)).filter(...).length`
- **onIonViewDidEnter + nextTick:** required for charts and computed counts to render correctly after tab navigation.
- **Auth read:** always fresh inside submit handler from `sessionStorage.getItem('AUTH_DATA')`. Never cached at module level.
- **Gender options at primary screening:** MALE, FEMALE, OTHER, UNKNOWN — all four. IHR requires all travelers counted.
- **Offline-first:** every write goes to IDB first. Network call is fire-and-forget after. Never block the UI on network.

---

## 8. THE GEOGRAPHIC HIERARCHY (stamped on every record)

```
NATIONAL → PROVINCE/PHEOC → DISTRICT → POE
```

Every record carries: `country_code`, `province_code`, `pheoc_code`, `district_code`, `poe_code`.  
These come from `auth.assignment` at creation time. They are **immutable** after write. Never update them.  
Server enforces geographic scope on every read — a POE officer cannot see another POE's data.

---

## 9. SYNC ENGINE PATTERN (copy exactly, never improvise)

```js
const activeSyncKeys = new Set(); // module-level, prevents concurrent sync of same record

async function syncOne(uuid) {
  if (activeSyncKeys.has(uuid)) return false; // concurrent guard
  activeSyncKeys.add(uuid);
  try {
    const record = await dbGet(STORE, uuid);
    if (!record || record.sync_status === SYNC.SYNCED) return true;
    // increment attempt BEFORE fetch (crash-safe)
    const working = {
      ...record,
      sync_attempt_count: (record.sync_attempt_count || 0) + 1,
      record_version: (record.record_version || 1) + 1,
      updated_at: isoNow(),
    };
    await safeDbPut(STORE, working);
    const ctrl = new AbortController();
    const tid = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS); // never hardcode ms
    const res = await fetch(url, { ...options, signal: ctrl.signal });
    clearTimeout(tid);
    if (res.ok) {
      await safeDbPut(STORE, {
        ...working,
        sync_status: SYNC.SYNCED,
        synced_at: isoNow(),
      });
      return true;
    }
    const retryable = res.status >= 500 || res.status === 429;
    await safeDbPut(STORE, {
      ...working,
      sync_status: retryable ? SYNC.UNSYNCED : SYNC.FAILED,
    });
    if (retryable) scheduleRetry();
    return false;
  } catch (e) {
    // network/timeout — always UNSYNCED (retryable), never FAILED
    await safeDbPut(STORE, { ...record, sync_status: SYNC.UNSYNCED });
    scheduleRetry();
    return false;
  } finally {
    activeSyncKeys.delete(uuid); // ALWAYS release, even on throw
  }
}
```

---

## 10. WHAT WAS BUILT IN THIS SESSION

| Deliverable                               | Lines | Notes                                          |
| ----------------------------------------- | ----- | ---------------------------------------------- |
| `Diseases_intelligence.js`                | 3,374 | New file — add to project next to Diseases.js  |
| `exposures.js`                            | 888   | Replace existing stub                          |
| `SecondaryScreening.vue`                  | 4,055 | Full intelligence layer refactor, 5 bugs fixed |
| `SecondaryScreeningController.php`        | 2,256 | FIX 1–12 applied                               |
| `AlertsController.php`                    | 793   | IHR alert lifecycle, role-tier enforcement     |
| `AggregatedController.php`                | 343   | WHO weekly aggregated submissions              |
| `HomeDashboardController.php`             | 862   | Home KPIs                                      |
| `PrimaryScreeningDashboardController.php` | 1,504 | 11 analytics endpoints                         |
| `PrimaryScreeningRecordsController.php`   | 1,293 | Primary records CRUD                           |
| `SecondaryScreeningRecordsController.php` | 1,024 | Secondary case register                        |
| `ActiveAlerts.vue`                        | 570   | Dark command-centre, role-aware                |
| `AggregatedData.vue`                      | 376   | Auto-calculate from IDB                        |
| `SyncManagement.vue`                      | 327   | Per-store progress, O(1) counts                |
| `HomePage.vue`                            | 1,620 | Dashboard home                                 |
| `NotificationsCenter.vue`                 | 1,181 | Referral queue                                 |
| `PrimaryRecords.vue`                      | 1,897 | Primary case register                          |
| `PrimaryDashboard.vue`                    | 873   | Primary analytics                              |
| `SecondaryScreeningRecords.vue`           | 2,345 | 1M record architecture                         |
| `index.js`                                | 152   | All routes, correct ordering                   |
| `main.ts`                                 | 69    | Correct load order                             |
| `api.php`                                 | 126   | All routes registered                          |
| `pending_migrations.sql`                  | 137   | 4 migrations with backfill                     |

---

## 11. WHAT REMAINS (not built in this session)

- `PrimaryScreening.vue` — the actual capture screen (exists in project, was not modified)
- `UsersList.vue` / `UserAssignments.vue` / `AddUser.vue` — user management views
- `MyProfile.vue` / `AppSettings.vue` — profile and settings stubs
- `ProfileController.php` — exists but minimal
- Migrations 2, 3, 4 in `pending_migrations.sql` — run these against the DB
- `LoginController.php` — exists, not modified

---

## 12. CONSTANTS — NEVER HARDCODE

```js
APP.SYNC_TIMEOUT_MS; // 8000  — AbortController timeout
APP.SYNC_RETRY_MS; // 10000 — retry interval
APP.VERSION; // '0.0.1'
APP.DB_NAME; // 'poe_offline_db'
APP.SCHEMA_VERSION; // 14
STORE.PRIMARY_SCREENINGS; // 'primary_screenings'  — never type this string directly
SYNC.UNSYNCED; // 'UNSYNCED'
SYNC.LABELS; // { UNSYNCED: 'Pending', SYNCED: 'Uploaded', FAILED: 'Queued' }
```

---

_Generated from a full development session. Every rule here was violated at least once before being codified._

KEEP THIS UPDATE IN MIND , ALTER TABLE primary_screenings
ADD COLUMN `traveler_direction`
ENUM('ENTRY','EXIT','TRANSIT') NULL
COMMENT 'IHR direction of travel at POE — NULL = not captured (pre-migration records)'
AFTER `gender`;

# ⛔ CRITICAL DEVELOPER LAW — READ BEFORE WRITING ANY VIEW

## POE Sentinel · Enforced for every view, every data type, forever

---

### LAW 1 — NAVIGATE WITH THE SERVER INTEGER ID. ALWAYS. NO EXCEPTIONS.

`router.push('/SomeView/' + record.id)` where `id` is the **MySQL auto-increment integer**.

**Never navigate with:**

- `client_uuid` — this is the IndexedDB primary key, NOT a server identifier
- `server_user_id`, `server_id`, or any alias — normalise to `id` before navigating
- Any UUID string — the Laravel controller receives `{id}` as `int`, a UUID produces 404

**Why this killed us:** The list view fell back to `client_uuid` when `id` was undefined on stale cache records. The detail view received a UUID in `route.params.id`, sent `GET /users/fafce657-...` to the server, got 404, showed a blank page. Three hours of debugging for one missing field.

**Rule:** Before calling `router.push`, assert `Number.isInteger(Number(id)) && id > 0`. If not, log and abort — never navigate with garbage.

---

### LAW 2 — THIS API HAS NO AUTH MIDDLEWARE. SEND NO TOKEN. EVER.

`UserController`, `LoginController`, and all current POE controllers are **completely open by design**. Auth middleware will be added as a separate layer later.

**Never add `Authorization: Bearer` headers to any fetch in this codebase until auth middleware is explicitly wired in `routes/api.php`.**

Sending a token that doesn't exist silently fails the check, returns nothing, and produces a blank view. This is what caused the "Not authenticated" error — the code was checking for a token that was never stored, then aborting the fetch before it was even sent.

**Rule:** Check `routes/api.php` before adding any auth header to any fetch. If you don't see `->middleware('auth:sanctum')` on the route, send no Authorization header.

---

### LAW 3 — CACHE RECORDS MUST ALWAYS CARRY THE SERVER INTEGER ID AS `id`.

Every record written to IndexedDB by `cacheUsersLocally()`, `cacheScreeningsLocally()`, or any equivalent function **must** write the server integer as `id` on the record object — not just as `server_id` or `server_user_id` or any other alias.

```js
// CORRECT — always write id explicitly
await dbPut(STORE.USERS_LOCAL, {
  client_uuid: idbKey,   // IDB primary key  — used for IDB lookups only
  id:          u.id,     // server integer   — used for router.push() and API calls
  ...
})

// WRONG — omitting id causes goDetail() to fall through to UUID fallback
await dbPut(STORE.USERS_LOCAL, {
  client_uuid:    idbKey,
  server_user_id: u.id,  // ← not enough. Views read .id, not .server_user_id
  ...
})
```

**Rule:** `id` is sacred. It is the server integer. It lives on every cached record. It is the only value that goes into route params.

---

### LAW 4 — WHEN READING FROM CACHE, NORMALISE BEFORE USE.

Old records in IDB may predate these rules. When loading a list from IDB, always normalise:

```js
allItems.value = rawRecords.map((r) => ({
  ...r,
  id:
    r.id ?? r.server_user_id ?? r.server_id ?? extractIntFromKey(r.client_uuid),
}));
```

Never trust that every IDB record was written by the current version of the code.

---

### SUMMARY TABLE

| What you want to do     | Correct field          | Wrong field                 |
| ----------------------- | ---------------------- | --------------------------- |
| Navigate to detail view | `record.id` (integer)  | `record.client_uuid` (UUID) |
| Call `GET /entity/{id}` | `record.id` (integer)  | any UUID or alias           |
| IDB primary key lookup  | `record.client_uuid`   | `record.id`                 |
| Auth header on fetch    | **None — API is open** | `Authorization: Bearer ...` |

**Violation of any of these laws produces a blank page with no visible error. You will spend hours debugging something that should take 30 seconds.**

================================================================================
ECSA-HC SENTINEL POE SCREENING SYSTEM
COMPLETE BUSINESS LOGIC & PROCESS SPECIFICATION
Based on: poe_2026 MySQL Schema · WHO IHR 2005 · Africa Regional POE Standards
================================================================================
Document scope: Mobile Offline-First App + Web Dashboard
Stack: Ionic 8 / Vue 3 / Dexie.js (offline) | Laravel / MySQL (server)
================================================================================

# PART 1: SYSTEM FOUNDATION

## 1.1 PURPOSE AND REGULATORY BASIS

This system implements Article 23 of the International Health Regulations (IHR)
2005, which grants States Parties the authority to require health measures for
travelers at Points of Entry. Every data field, every workflow decision, and
every alert routing rule in this system has a direct IHR or WHO operational
justification.

IHR obligations implemented by this system:

- Annex 1B Capacity: Designated POE must have capacity to apply health measures
  to travelers (primary screening at every entry point).
- Article 23(1)(a): Apply the least invasive and intrusive medical examination
  that could achieve the public health objective.
- Article 44: States Parties collaborate in detection and response to PHEIC.
- Annex 2: Decision instrument for assessment and notification of events that
  may constitute PHEIC — implemented as the alert generation rules in this system.

WHO technical guidance implemented:

- WHO Guidance on Health Documents for travelers (IHR Articles 35-39).
- WHO Surveillance at Points of Entry: Implementation Guide.
- ECSA-HC Regional POE Digital Surveillance Standards.

## 1.2 GEOGRAPHIC HIERARCHY

The system enforces a four-level hierarchical governance structure. Every record
in every table carries all four levels as immutable codes. This is not optional
metadata — it is the data scoping mechanism that determines what each role can
see and do.

NATIONAL
│
├── PROVINCE / RPHEOC (Regional Public Health Emergency Operations Centre)
│ │
│ └── DISTRICT
│ │
│ └── POE (Point of Entry)

Rules:

- A POE belongs to exactly one District.
- A District belongs to exactly one Province/RPHEOC.
- A Province/RPHEOC belongs to exactly one National scope.
- All four levels are stored as VARCHAR codes, not foreign keys to a master table.
- Reference data (list of provinces, districts, POEs) lives in hardcoded JS files
  in the mobile app: POES.js for POE hierarchy, diseases.js for disease/symptom mapping.
- The server stores these codes as-received from the device and does NOT validate
  them against a master reference table. This is intentional for offline resilience.

Real data from the schema (Uganda example):
country_code: 'UG'
province_code: 'Kabale RPHEOC'
pheoc_code: 'Kabale RPHEOC' (same as province_code in Uganda's RPHEOC model)
district_code: 'Kisoro District'
poe_code: 'Bunagana'

## 1.3 REFERENCE DATA OWNERSHIP RULE

Countries, provinces, districts, POEs, diseases, and symptom codes are owned by
the mobile app as hardcoded JSON/JS files. They are never fetched from the server
at runtime. This means:

- The app works with zero network access from first launch.
- A network outage during screening never causes missing dropdown data.
- Updates to reference data require an app update, not a server sync.

reference_data_version (stored on every record as 'rda-2026-02-01') identifies
which version of the app's reference data was active when the record was created.
The server does not reject records with older reference_data_version — it stores
them as-is. This allows records created on an older app version to still sync.

================================================================================
PART 2: USERS
================================================================================

## 2.1 USERS TABLE — EXACT SCHEMA

Table: users

id BIGINT UNSIGNED PK, AUTO_INCREMENT
role_key VARCHAR(60) NULL — role string, see roles below
country_code VARCHAR(10) NULL — ISO 3166-1 alpha-2
full_name VARCHAR(150) NULL
username VARCHAR(80) NULL, UNIQUE
password_hash VARCHAR(255) NULL — bcrypt hash (legacy column)
email VARCHAR(190) NULL, UNIQUE
phone VARCHAR(40) NULL
is_active TINYINT(1) DEFAULT 1
last_login_at DATETIME NULL
email_verified_at TIMESTAMP NULL
password VARCHAR(200) NULL — Laravel Sanctum bcrypt column
name VARCHAR(200) NULL — Laravel display name
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NOT NULL, ON UPDATE CURRENT_TIMESTAMP

Note: Both password_hash and password exist because the system was migrated from a
custom auth to Laravel Sanctum. password is the active auth column. password_hash
is retained for audit. New users must have password set.

## 2.2 ROLES — THE SEVEN ROLE_KEY VALUES

role_key is a VARCHAR(60) stored directly on the user row. There is no roles table.
The seven valid role keys are:

POE_PRIMARY
Scope: Assigned to a specific POE.
Function: Operates primary screening only. The fastest, highest-volume role.
Sees: Only their own POE's primary screening records and the referral queue
they generate. Cannot see secondary screening outcomes.

POE_SECONDARY
Scope: Assigned to a specific POE.
Function: Operates secondary screening only. Opens and works cases
referred from primary screening.
Sees: Open referral notifications at their POE, full secondary case details
they open. Cannot create primary screening records.

POE_DATA_OFFICER
Scope: Assigned to a specific POE.
Function: Manages aggregated data submission for the POE.
Also has read access to primary and secondary counts for their POE.
Sees: Their POE's aggregated submissions, summary counts.

POE_ADMIN
Scope: Assigned to a specific POE.
Function: Can do primary, secondary, data submission, and manage users
assigned to their POE. Cannot manage users above their POE level.
Sees: All records for their POE across all modules.

DISTRICT_SUPERVISOR
Scope: Assigned to a district (no poe_code in assignment).
Function: Supervises all POEs in their district. Read-only across all
POE records. Can acknowledge DISTRICT-level alerts.
Sees: All records where district_code matches their assignment.

PHEOC_OFFICER
Scope: Assigned to a province/RPHEOC.
Function: Monitors all districts and POEs in their PHEOC region.
Can acknowledge PHEOC-level alerts.
Sees: All records where province_code/pheoc_code matches their assignment.

NATIONAL_ADMIN
Scope: Country-level.
Function: Full system access. Can manage all users. Sees all POEs in country.
Sees: All records where country_code matches their assignment.

- POE_ADMIN can only manage users within their own POE. Cannot create users at
  a higher scope than the POE level.

  2.4 USER BUSINESS RULES AND VALIDATIONS

---

CREATION:

- username: required, 3–80 chars, lowercase, alphanumeric + underscores only,
  globally unique (case-insensitive check: username_ci index in IDB).
- email: required, valid email format, globally unique (email_ci index in IDB).
- full_name: required, 2–150 chars.
- role_key: required, must be one of the seven valid values listed in 2.2.
- country_code: required, must be a valid ISO code from the app's reference data.
- password: required on creation, minimum 8 chars, bcrypt hashed before storage.
- phone: optional, but if provided must be numeric, 7–15 digits (E.164 format
  without the +, e.g. '25678927376').
- is_active: defaults to 1 (active).

ASSIGNMENT REQUIREMENT:

- A user without an active user_assignment record cannot log in successfully to
  the mobile app. The login controller checks for an active primary assignment
  and rejects login if none exists.
- Exception: NATIONAL_ADMIN may have a country-level assignment with no
  district_code or poe_code.

DEACTIVATION:

- Users are never deleted. is_active is set to 0.
- A deactivated user's session token is invalidated on next API call.
- Deactivation does not void past records. All historical data remains intact
  and queryable with the original created_by_user_id.

USERNAME_CI AND EMAIL_CI (IndexedDB only):

- In IndexedDB (users_local store), username_ci and email_ci store the
  lowercase version of username and email respectively.
- These are used for case-insensitive uniqueness checks before saving a new
  user record locally, without needing a server round-trip.
- username_ci = username.toLowerCase()
- email_ci = email.toLowerCase()
- These fields are NOT in the MySQL schema — they are IDB-only fields.

================================================================================
PART 3: USER ASSIGNMENTS
================================================================================

## 3.1 USER_ASSIGNMENTS TABLE — EXACT SCHEMA

Table: user_assignments

id BIGINT UNSIGNED PK, AUTO_INCREMENT
user_id BIGINT UNSIGNED NOT NULL, FK → users.id
country_code VARCHAR(10) NOT NULL
province_code VARCHAR(30) NULL
pheoc_code VARCHAR(30) NULL
district_code VARCHAR(30) NULL
poe_code VARCHAR(40) NULL
is_primary TINYINT(1) DEFAULT 1 — primary assignment for stamping
is_active TINYINT(1) DEFAULT 1
starts_at DATETIME NULL
ends_at DATETIME NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NOT NULL, ON UPDATE CURRENT_TIMESTAMP

## 3.2 ASSIGNMENT RULES

A user can have multiple assignment rows (all_assignments in AUTH_DATA) but
exactly one must be marked is_primary = 1 and is_active = 1 at any given time.
The primary active assignment determines:

- Which poe_code, district_code, province_code, pheoc_code are stamped on
  every record the user creates.
- Which data scope the user can query.

Assignment patterns by role:

POE-level roles (POE_PRIMARY, POE_SECONDARY, POE_DATA_OFFICER, POE_ADMIN):
country_code: 'UG'
province_code: 'Kabale RPHEOC'
pheoc_code: 'Kabale RPHEOC'
district_code: 'Kisoro District'
poe_code: 'Bunagana' ← required, not null
is_primary: 1
is_active: 1

DISTRICT_SUPERVISOR:
country_code: 'UG'
province_code: 'Kabale RPHEOC'
pheoc_code: 'Kabale RPHEOC'
district_code: 'Kisoro District' ← required, not null
poe_code: NULL ← not assigned to a specific POE
is_primary: 1
is_active: 1

PHEOC_OFFICER:
country_code: 'UG'
province_code: 'Kabale RPHEOC'
pheoc_code: 'Kabale RPHEOC' ← required, not null
district_code: NULL
poe_code: NULL
is_primary: 1
is_active: 1

NATIONAL_ADMIN:
country_code: 'UG' ← required, not null
province_code: NULL
pheoc_code: NULL
district_code: NULL
poe_code: NULL
is_primary: 1
is_active: 1

starts_at and ends_at allow temporal assignments. If ends_at is not null and is
in the past, the assignment is treated as inactive even if is_active = 1.
The login controller checks: is_active = 1 AND (ends_at IS NULL OR ends_at > NOW()).

A user with an expired primary assignment cannot produce new records. Their
session will show a "session geographic scope unavailable" error on next write.

## 3.3 GEOGRAPHIC SCOPE ENFORCEMENT ON API

Every server-side API endpoint that reads data applies geographic scope filtering
based on the authenticated user's role and assignment. The rules are:

POE-level roles:
WHERE poe_code = auth.poe_code

DISTRICT_SUPERVISOR:
WHERE district_code = auth.district_code

PHEOC_OFFICER:
WHERE pheoc_code = auth.pheoc_code
(or province_code = auth.province_code — they are the same value in this model)

NATIONAL_ADMIN:
WHERE country_code = auth.country_code

No role can query records outside their geographic scope. The server never relies
on the client to send the correct scope filter — it always derives it from the
authenticated user's assignment.

================================================================================
PART 4: POINTS OF ENTRY (POEs)
================================================================================

## 4.1 POE REFERENCE DATA — HARDCODED IN APP

POEs are defined in the mobile app file: src/data/POES.js
This file is the authoritative source of all valid POE codes, their parent
district, province, and country context.

Structure of each POE entry in POES.js:
{
poe_code: 'Bunagana', // matches poe_code column everywhere
poe_name: 'Bunagana Border Post',
poe_type: 'LAND', // 'LAND' | 'AIR' | 'SEA'
district_code: 'Kisoro District',
province_code: 'Kabale RPHEOC',
pheoc_code: 'Kabale RPHEOC',
country_code: 'UG',
is_active: true,
}

POE types: LAND, AIR, SEA.

- LAND: border crossings, road checkpoints.
- AIR: international airports.
- SEA: lake/river ports, coastal ports.

POE codes are human-readable strings (e.g. 'Bunagana', not 'UG-KIS-BUN-001').
They are stored exactly as defined in POES.js in every record across all tables.
Changing a POE code in POES.js is a breaking change — old records on the server
will have the old code and cannot be automatically rematched.

## 4.2 POE BUSINESS RULES

- A user must be assigned to a POE to screen at that POE.
- The app uses the user's poe_code from their active assignment, not a
  user-selectable dropdown. A screener cannot choose a different POE at runtime.
- POE deactivation (is_active: false in POES.js) requires an app update.
  Active records at a deactivated POE are not affected — they retain their
  poe_code forever.
- The web dashboard can filter all data by poe_code using exact string matching.

================================================================================
PART 5: AUTHENTICATION & SESSION
================================================================================

## 5.1 LOGIN FLOW

Authentication uses Laravel Sanctum (personal access tokens).

Mobile app login sequence:

1. User enters username + password on LoginView.
2. App POSTs to POST /api/auth/login
   Body: { username, password }
3. Server validates credentials against users table (bcrypt verify on
   users.password column).
4. Server checks is_active = 1.
5. Server queries user_assignments for primary active assignment.
6. Server derives \_permissions from role_key.
7. Server returns AUTH_DATA JSON + Sanctum token.
8. App stores AUTH_DATA in sessionStorage under key 'AUTH_DATA'.
9. App stores Sanctum token in localStorage (persists across restarts).
10. App.vue transitions to authenticated shell.

Server returns on success:
{
token: '<sanctum_token>',
user: {
id, role_key, country_code, full_name, username, email, phone,
is_active, last_login_at, created_at, updated_at,
email_verified_at, name,
assignment: { ...primary assignment row },
all_assignments: [ ...all active assignments ],
poe_code, district_code, province_code, pheoc_code, ← pre-flattened
\_permissions: { can_do_primary_screening, ... },
\_logged_in_at: '<ISO timestamp>'
}
}

Server returns on failure:
401: { message: 'Invalid credentials' }
403: { message: 'Account is deactivated. Contact your administrator.' }
403: { message: 'No active geographic assignment found. Contact your administrator.' }

The Sanctum token is passed as Authorization: Bearer <token> on every API call.

## 5.2 SESSION RULES

- AUTH_DATA is stored in sessionStorage, which is cleared when the browser/
  WebView tab is closed. On app restart, the user must log in again unless
  a persistent token is in localStorage.
- The mobile app checks sessionStorage on every write operation. If AUTH_DATA
  is null or auth.is_active is false, the write is aborted and the login modal
  is shown.
- The Sanctum token has no automatic expiry configured. Token revocation happens
  only on explicit logout or when an admin deactivates the user.
- On explicit logout, the app calls POST /api/auth/logout (server revokes token),
  clears sessionStorage and localStorage token.

================================================================================
PART 6: PRIMARY SCREENING — COMPLETE BUSINESS LOGIC
================================================================================

## 6.1 TABLE — EXACT SCHEMA

Table: primary_screenings

id BIGINT UNSIGNED PK, AUTO_INCREMENT
client_uuid CHAR(36) NOT NULL, UNIQUE (UUID v4 from device)
idempotency_key CHAR(64) NULL (reserved for future use)
reference_data_version VARCHAR(40) NOT NULL
server_received_at DATETIME NULL (set by server on sync acceptance)
country_code VARCHAR(10) NOT NULL
province_code VARCHAR(30) NULL
pheoc_code VARCHAR(30) NULL
district_code VARCHAR(30) NOT NULL
poe_code VARCHAR(40) NOT NULL
captured_by_user_id BIGINT UNSIGNED NOT NULL, FK → users.id
gender ENUM('MALE','FEMALE','OTHER','UNKNOWN') NOT NULL
traveler_full_name VARCHAR(150) NULL (optional capture)
temperature_value DECIMAL(5,2) NULL (optional)
temperature_unit ENUM('C','F') NULL (required IF temperature_value is set)
symptoms_present TINYINT(1) NOT NULL (0 = No, 1 = Yes)
captured_at DATETIME NOT NULL (device timestamp at moment of capture)
captured_timezone VARCHAR(64) NULL (e.g. 'Africa/Dar_es_Salaam')
device_id VARCHAR(80) NOT NULL
app_version VARCHAR(40) NULL
platform ENUM('ANDROID','IOS','WEB') NOT NULL DEFAULT 'ANDROID'
referral_created TINYINT(1) NOT NULL DEFAULT 0
record_version INT UNSIGNED NOT NULL DEFAULT 1
record_status ENUM('COMPLETED','VOIDED') NOT NULL DEFAULT 'COMPLETED'
void_reason VARCHAR(255) NULL (required if record_status = 'VOIDED')
deleted_at DATETIME NULL
sync_status ENUM('UNSYNCED','SYNCED','FAILED') NOT NULL DEFAULT 'UNSYNCED'
synced_at DATETIME NULL
sync_attempt_count INT UNSIGNED NOT NULL DEFAULT 0
last_sync_error VARCHAR(500) NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NOT NULL, ON UPDATE CURRENT_TIMESTAMP

## 6.2 PRIMARY SCREENING WORKFLOW — STEP BY STEP

PRECONDITIONS (enforced before the form can be submitted):

- AUTH_DATA must be present in sessionStorage.
- auth.is_active must be true.
- auth.\_permissions.can_do_primary_screening must be true.
- auth.poe_code must be a non-null, non-empty string.
- The app does not require internet connectivity to proceed.

STEP 1 — OFFICER OPENS PRIMARY SCREENING VIEW
The view displays: - Session counter (screenings captured today at this POE by this device) - Connectivity indicator - Sync queue status (how many UNSYNCED records are pending)

STEP 2 — OFFICER CAPTURES TRAVELER DATA
Required fields: - gender: exactly one of MALE / FEMALE ONLY
UNKNOWN must be selectable — IHR requires all travelers be counted
regardless of whether gender is determinable. - symptoms_present: binary YES / NO decision by the officer.
This is the critical IHR triage decision. The officer visually assesses
whether the traveler presents with any illness symptoms.
Implementation: two large buttons, full-screen width, high contrast.

Optional fields: - traveler_full_name (VARCHAR 150): officer may capture name if traveler
volunteers it. Not required by IHR at primary screening level. - temperature_value + temperature_unit: if a thermometer reading is taken.
Business rule: if temperature_value is provided, temperature_unit is
required. If temperature_value is null, temperature_unit must also be null.
Temperature ranges validated by the app (not the server):
Celsius: valid range 25.0 to 45.0 (warn outside 35.0–42.0)
Fahrenheit: valid range 77.0 to 113.0 (warn outside 95.0–107.6)

STEP 3 — OFFICER PRESSES SAVE / CAPTURE
The app immediately (before any network call):
a. Reads auth fresh from sessionStorage.
b. Validates all required fields.
c. Calls createRecordBase(auth, domainFields) to build the complete record.
d. Calls dbPut(STORE.PRIMARY_SCREENINGS, record) — write to IndexedDB.
e. Increments the session day counter in localStorage.
f. Evaluates: symptoms_present === 1 ?

STEP 4A — SYMPTOMS ABSENT (symptoms_present = 0)

- referral_created = 0 (no referral)
- Display: "Done — No symptoms detected. Next traveler."
- The record is saved as UNSYNCED and will be uploaded in the next sync cycle.
- No notification record is created.
- Session counter increments by 1.
- UI resets to capture the next traveler in under 2 seconds.

STEP 4B — SYMPTOMS PRESENT (symptoms_present = 1)
When symptoms_present = 1, the system automatically creates two additional
records atomically in IndexedDB BEFORE showing the officer any confirmation.
These are:
(i) A notification record (referral to secondary screening officer)
(ii) No secondary_screening record yet — secondary case is only OPENED when
the secondary officer picks up the notification. The primary screening
side only creates the notification.

The notification record auto-created:
notification_type: 'SECONDARY_REFERRAL'
status: 'OPEN'
reason_code: 'PRIMARY_SYMPTOMS_DETECTED'
priority: see priority logic below
assigned_role_key: 'POE_SECONDARY'
assigned_user_id: NULL (no specific secondary officer assigned yet)
primary_screening_id: the new primary screening's client_uuid (resolved to
server ID after sync)
reason_text: auto-generated summary string, e.g.:
"Symptoms present. Gender: MALE. Temp: 34 C. Priority: NORMAL.
Traveler: [name if captured]. POE: Bunagana. District: Kisoro District.
PHEOC: Kabale RPHEOC. Officer: AYEBARE TIMOTHY KAMUKAMA. Role: SCREENER"

PRIORITY DETERMINATION:
The priority of the generated notification is determined by:
CRITICAL: temperature_value >= 38.5°C (101.3°F) AND symptoms_present = 1
HIGH: temperature_value >= 37.5°C (99.5°F) AND symptoms_present = 1
OR traveler appears severely ill (officer judgment — no field for
this at primary level, so HIGH requires elevated temp)
NORMAL: symptoms_present = 1 without elevated temperature
DEFAULT: NORMAL if temperature was not measured

After creating both records (primary + notification) in a single
dbAtomicWrite() call: - referral_created = 1 is set on the primary screening record - Display: "Refer traveler to Secondary Screening. Referral #[short_id] created." - Session counter increments by 1 (symptomatic). - UI shows a distinct visual state (e.g. orange/red) for the symptomatic
referral confirmation, then offers "Next Traveler" button.

ATOMIC WRITE REQUIREMENT:
The primary screening record (with referral_created = 1) and the notification
record MUST be written in a single dbAtomicWrite() call. If primary is written
and notification write fails, referral_created = 1 but no notification exists
— data is inconsistent and the secondary officer never gets the referral.
Use dbAtomicWrite([primaryRecord, notificationRecord]) always.

## 6.3 PRIMARY SCREENING — FIELD VALIDATIONS (CLIENT-SIDE)

These validations run before dbPut is called. Failed validations show inline
error messages and prevent submission.

gender: - Required. Must be exactly one of: MALE, FEMALE, OTHER, UNKNOWN. - Display error: "Gender is required."

symptoms_present: - Required. Must be 0 or 1 (boolean). - Display error: "Symptoms decision is required."

temperature_value (if provided): - Must be a number with at most 2 decimal places. - Celsius: must be between 25.00 and 45.00. - Fahrenheit: must be between 77.00 and 113.00. - Display error: "Temperature value is outside the valid clinical range."

temperature_unit (conditional): - Required if and only if temperature_value is not null. - Must be exactly 'C' or 'F'. - Display error: "Temperature unit is required when a temperature is entered."

traveler_full_name (optional, if entered): - Max 150 characters. - Trim whitespace before saving.

auth guard (checked first, before all field validations): - auth must exist and auth.is_active must be true. - auth.poe_code must not be null. - auth.\_permissions.can_do_primary_screening must be true. - Display error: "Session expired. Please log in again."

## 6.4 PRIMARY SCREENING — SERVER-SIDE VALIDATIONS (SYNC ENDPOINT)

POST /api/sync/batch — accepts a batch containing primary screening records.

The server validates each primary screening record:

- client_uuid: required, CHAR(36), valid UUID v4 format.
  Uniqueness: if client_uuid already exists in primary_screenings AND
  the existing record has sync_status = 'SYNCED', return the existing
  server_id (idempotent re-submission). Do not insert duplicate.
- gender: required, must be one of the ENUM values.
- symptoms_present: required, must be 0 or 1.
- captured_by_user_id: required, must match the authenticated user's id.
  A user cannot submit records on behalf of another user.
- poe_code: required, must match the authenticated user's assigned poe_code.
  A user cannot submit records for a POE other than their own.
- country_code: required, must match authenticated user's country_code.
- district_code: required, must match authenticated user's district_code.
- captured_at: required, valid datetime, must not be in the future beyond
  5 minutes (allow for clock drift).
- temperature_unit: required if temperature_value is not null.
- Unknown fields in the payload are rejected (400 Bad Request).

On acceptance, the server:

- Inserts the record.
- Sets server_received_at = NOW().
- Returns server_id (the auto-incremented MySQL id).
- Creates a sync_batch_item record with entity_type = 'PRIMARY' and
  status = 'ACCEPTED'.

On duplicate (client_uuid already synced):

- Returns HTTP 200 with the original server_id (idempotent).
- Does NOT update the existing record.
- Creates a sync_batch_item with status = 'ACCEPTED'.

## 6.5 PRIMARY SCREENING VOID RULES

A primary screening record can be voided (record_status = 'VOIDED') by:

- The officer who created it (same user), within 24 hours of capture.
- A POE_ADMIN at the same POE, at any time.
- NATIONAL_ADMIN, at any time.

Void rules:

- void_reason is required. Minimum 10 characters.
- A voided record remains in IndexedDB and MySQL. deleted_at remains null.
  deleted_at is reserved for hard-delete by NATIONAL_ADMIN only.
- If a primary screening record is voided, its linked notification record is
  also automatically set to status = 'CLOSED' with reason_text = 'Primary
  screening voided: [void_reason]'.
- A secondary screening case that is already OPEN or IN_PROGRESS linked to a
  voided primary screening must be manually closed by the secondary officer.
  The system cannot auto-close a case that is already in investigation.
- Voided records are excluded from aggregated counts.
- referral_created is NOT reset when voiding — it is an audit field that
  records whether a referral was issued at the time of capture.

## 6.6 SESSION COUNTER (DAY COUNT)

The app maintains a simple daily counter in localStorage to show the officer
how many travelers have been screened today at this device.

localStorage keys:
APP.DAY_COUNT_DAY_KEY ('poe_ps_day') — stores the current date 'YYYY-MM-DD'
APP.DAY_COUNT_CNT_KEY ('poe_ps_cnt') — stores the integer count for that day

Logic:
const today = new Date().toISOString().slice(0, 10)
const storedDay = localStorage.getItem(APP.DAY_COUNT_DAY_KEY)
if (storedDay !== today) {
localStorage.setItem(APP.DAY_COUNT_DAY_KEY, today)
localStorage.setItem(APP.DAY_COUNT_CNT_KEY, '0')
}
// After each successful dbPut:
const count = parseInt(localStorage.getItem(APP.DAY_COUNT_CNT_KEY) || '0') + 1
localStorage.setItem(APP.DAY_COUNT_CNT_KEY, String(count))

This counter is device-local and approximate. The authoritative count for
reporting purposes comes from querying primary_screenings in IndexedDB by
poe_code + captured_at date range.

================================================================================
PART 7: NOTIFICATIONS (REFERRAL QUEUE)
================================================================================

## 7.1 TABLE — EXACT SCHEMA

Table: notifications

id BIGINT UNSIGNED PK, AUTO_INCREMENT
client_uuid CHAR(36) NOT NULL, UNIQUE
idempotency_key CHAR(64) NULL
reference_data_version VARCHAR(40) NOT NULL
server_received_at DATETIME NULL
country_code VARCHAR(10) NOT NULL
province_code VARCHAR(30) NULL
pheoc_code VARCHAR(30) NULL
district_code VARCHAR(30) NOT NULL
poe_code VARCHAR(40) NOT NULL
primary_screening_id BIGINT UNSIGNED NOT NULL, FK → primary_screenings.id
created_by_user_id BIGINT UNSIGNED NOT NULL, FK → users.id
notification_type ENUM('SECONDARY_REFERRAL','ALERT') NOT NULL DEFAULT 'SECONDARY_REFERRAL'
status ENUM('OPEN','IN_PROGRESS','CLOSED') NOT NULL DEFAULT 'OPEN'
priority ENUM('NORMAL','HIGH','CRITICAL') NOT NULL DEFAULT 'NORMAL'
reason_code VARCHAR(80) NOT NULL
reason_text VARCHAR(255) NULL
assigned_role_key VARCHAR(60) NOT NULL
assigned_user_id BIGINT UNSIGNED NULL, FK → users.id
opened_at DATETIME NULL
closed_at DATETIME NULL
device_id VARCHAR(80) NOT NULL
app_version VARCHAR(40) NULL
platform ENUM('ANDROID','IOS','WEB') NOT NULL DEFAULT 'ANDROID'
record_version INT UNSIGNED NOT NULL DEFAULT 1
deleted_at DATETIME NULL
sync_status ENUM('UNSYNCED','SYNCED','FAILED') NOT NULL DEFAULT 'UNSYNCED'
synced_at DATETIME NULL
sync_attempt_count INT UNSIGNED NOT NULL DEFAULT 0
last_sync_error VARCHAR(500) NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NOT NULL, ON UPDATE CURRENT_TIMESTAMP

## 7.2 NOTIFICATION STATUS MACHINE

Valid transitions:

OPEN → IN_PROGRESS
Trigger: Secondary officer opens the case (creates secondary_screenings record
linked to this notification).
Who: POE_SECONDARY or POE_ADMIN with can_do_secondary_screening.
Side effect: opened_at = NOW(), assigned_user_id = secondary officer's user id.

IN_PROGRESS → CLOSED
Trigger: Secondary case reaches DISPOSITIONED or CLOSED status.
Who: POE_SECONDARY or POE_ADMIN.
Side effect: closed_at = NOW().

OPEN → CLOSED (direct, skip IN_PROGRESS)
Trigger: Primary screening is voided, OR duplicate referral detected,
OR officer manually closes without opening a case (only POE_ADMIN).
Who: POE_ADMIN or NATIONAL_ADMIN.
Side effect: closed_at = NOW(), reason_text updated with closure reason.

Invalid transitions (must be rejected):
CLOSED → any state (closed notifications cannot be reopened)
IN_PROGRESS → OPEN (regression not allowed)

## 7.3 NOTIFICATION BUSINESS RULES

- One notification per primary screening that has symptoms_present = 1.
  The system checks: does a notification with primary_screening_id =
  [this record's client_uuid] already exist? If yes, do not create another.
  This check happens in IndexedDB before write using dbGetByIndex on
  'primary_screening_id'.

- notification_type = 'SECONDARY_REFERRAL' for all auto-generated referrals.
  notification_type = 'ALERT' is reserved for alert-level notifications
  routed from the secondary screening alert generation module (future sprint).

- reason_code = 'PRIMARY_SYMPTOMS_DETECTED' is the only valid reason_code
  for auto-generated referrals. Future extension may add other codes.

- assigned_role_key = 'POE_SECONDARY' for all auto-generated referrals.
  This tells the secondary officer's queue filter which notifications to show.

- priority escalation is NOT retroactive. If a notification was created at
  NORMAL priority, discovering later that the case is high-risk does not
  change the notification priority. Priority is the urgency at the moment
  of referral creation.

- The notification MUST sync to the server together with its parent primary
  screening record. If the primary screening sync succeeds but the notification
  sync fails, the secondary officer at a different device will never see the
  referral. Use dbAtomicWrite for the combined update after server confirms both.

================================================================================
PART 8: SECONDARY SCREENING — COMPLETE BUSINESS LOGIC
================================================================================

## 8.1 TABLE — EXACT SCHEMA

Table: secondary_screenings

id BIGINT UNSIGNED PK, AUTO_INCREMENT
client_uuid CHAR(36) NOT NULL, UNIQUE
idempotency_key CHAR(64) NULL
reference_data_version VARCHAR(40) NOT NULL
server_received_at DATETIME NULL
country_code VARCHAR(10) NOT NULL
province_code VARCHAR(30) NULL
pheoc_code VARCHAR(30) NULL
district_code VARCHAR(30) NOT NULL
poe_code VARCHAR(40) NOT NULL
primary_screening_id BIGINT UNSIGNED NOT NULL, FK → primary_screenings.id
notification_id BIGINT UNSIGNED NOT NULL, FK → notifications.id
opened_by_user_id BIGINT UNSIGNED NOT NULL, FK → users.id
case_status ENUM('OPEN','IN_PROGRESS','DISPOSITIONED','CLOSED')
NOT NULL DEFAULT 'OPEN'
── TRAVELER IDENTITY ──
traveler_full_name VARCHAR(150) NULL
traveler_initials VARCHAR(10) NULL
traveler_anonymous_code VARCHAR(60) NULL
travel_document_type VARCHAR(30) NULL (PASSPORT, NATIONAL_ID, LAISSEZ_PASSER, OTHER)
travel_document_number VARCHAR(60) NULL
traveler_gender ENUM('MALE','FEMALE','OTHER','UNKNOWN') NOT NULL
traveler_age_years INT UNSIGNED NULL
traveler_dob DATE NULL
traveler_nationality_country_code VARCHAR(10) NULL
traveler_occupation VARCHAR(120) NULL
residence_country_code VARCHAR(10) NULL
residence_address_text VARCHAR(255) NULL
── CONTACT & DESTINATION ──
phone_number VARCHAR(40) NULL
alternative_phone VARCHAR(40) NULL
email VARCHAR(190) NULL
destination_address_text VARCHAR(255) NULL
destination_district_code VARCHAR(30) NULL
emergency_contact_name VARCHAR(150) NULL
emergency_contact_phone VARCHAR(40) NULL
── TRAVEL ITINERARY ──
journey_start_country_code VARCHAR(10) NULL
embarkation_port_city VARCHAR(120) NULL
conveyance_type ENUM('AIR','LAND','SEA','OTHER') NULL
conveyance_identifier VARCHAR(80) NULL (flight number, vessel name, etc.)
seat_number VARCHAR(20) NULL (applicable for AIR)
arrival_datetime DATETIME NULL
departure_datetime DATETIME NULL
purpose_of_travel VARCHAR(80) NULL
planned_length_of_stay_days INT UNSIGNED NULL
── CLINICAL TRIAGE ──
triage_category ENUM('NON_URGENT','URGENT','EMERGENCY') NULL
emergency_signs_present TINYINT(1) NOT NULL DEFAULT 0
general_appearance ENUM('WELL','UNWELL','SEVERELY_ILL') NULL
temperature_value DECIMAL(5,2) NULL
temperature_unit ENUM('C','F') NULL
pulse_rate INT UNSIGNED NULL (beats per minute)
respiratory_rate INT UNSIGNED NULL (breaths per minute)
bp_systolic INT UNSIGNED NULL (mmHg)
bp_diastolic INT UNSIGNED NULL (mmHg)
oxygen_saturation DECIMAL(5,2) NULL (percentage, e.g. 97.5)
── CLINICAL DECISION ──
syndrome_classification VARCHAR(60) NULL (see syndrome codes below)
risk_level ENUM('LOW','MEDIUM','HIGH','CRITICAL') NULL
officer_notes TEXT NULL
final_disposition ENUM('RELEASED','DELAYED','QUARANTINED','ISOLATED',
'REFERRED','TRANSFERRED','DENIED_BOARDING','OTHER') NULL
disposition_details VARCHAR(255) NULL
followup_required TINYINT(1) NOT NULL DEFAULT 0
followup_assigned_level ENUM('POE','DISTRICT','PHEOC','NATIONAL') NULL
── TIMESTAMPS ──
opened_at DATETIME NULL
opened_timezone VARCHAR(64) NULL
dispositioned_at DATETIME NULL
closed_at DATETIME NULL
── AUDIT / SYNC ──
device_id VARCHAR(80) NOT NULL
app_version VARCHAR(40) NULL
platform ENUM('ANDROID','IOS','WEB') NOT NULL DEFAULT 'ANDROID'
record_version INT UNSIGNED NOT NULL DEFAULT 1
deleted_at DATETIME NULL
sync_status ENUM('UNSYNCED','SYNCED','FAILED') NOT NULL DEFAULT 'UNSYNCED'
synced_at DATETIME NULL
sync_attempt_count INT UNSIGNED NOT NULL DEFAULT 0
last_sync_error VARCHAR(500) NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NOT NULL, ON UPDATE CURRENT_TIMESTAMP

## 8.2 SECONDARY SCREENING — CASE OPENING RULES

PRECONDITIONS:

- auth.\_permissions.can_do_secondary_screening must be true.
- Notification must be in OPEN status.
- No secondary_screenings record must already exist with the same notification_id.
  Check: dbGetByIndex(STORE.SECONDARY_SCREENINGS, 'notification_id', notif.client_uuid)
  If a record already exists, open it instead of creating a new one (idempotent
  case opening). This prevents duplicate cases from rapid double-taps.

OPENING A CASE:

1. Secondary officer opens the notification queue (OPEN notifications at their POE).
2. Officer selects a notification.
3. System creates a secondary_screenings record with:
   case_status: 'OPEN'
   primary_screening_id: notification.primary_screening_id (resolved to server id post-sync)
   notification_id: notification.client_uuid (resolved to server id post-sync)
   opened_by_user_id: auth.id
   opened_at: isoNow()
   opened_timezone: device timezone string
   traveler_gender: pre-populated from primary_screenings.gender
   (all other fields: null initially)
4. Notification status transitions OPEN → IN_PROGRESS atomically:
   dbAtomicWrite([
   { store: STORE.SECONDARY_SCREENINGS, record: newCaseRecord },
   { store: STORE.NOTIFICATIONS, record: { ...notif, status: 'IN_PROGRESS',
   opened_at: isoNow(), assigned_user_id: auth.id,
   record_version: notif.record_version + 1 } }
   ])
5. case_status immediately transitions to IN_PROGRESS once the officer
   starts entering any data (first field edit). A case that is just opened
   but not yet touched stays in OPEN status for at most 30 minutes before
   the system auto-transitions to IN_PROGRESS (prevents ghost open cases).

## 8.3 SECONDARY SCREENING — CASE STATUS MACHINE

OPEN → IN_PROGRESS
Trigger: Officer starts data entry on any field in the case form.
Who: The officer who opened the case (opened_by_user_id).

IN_PROGRESS → DISPOSITIONED
Trigger: Officer completes clinical assessment and records final_disposition.
Requirement: final_disposition must not be null.
Requirement: syndrome_classification must not be null.
Requirement: risk_level must not be null.
Side effect: dispositioned_at = isoNow().

DISPOSITIONED → CLOSED
Trigger: All required post-disposition actions are completed.
Specifically: followup_required = 0, OR followup_assigned_level is set.
Also: If alert was required but not yet created, alert must be created first.
Side effect: closed_at = isoNow().
Notification: status transitions to CLOSED via dbAtomicWrite.

IN_PROGRESS → CLOSED (direct, bypass DISPOSITIONED)
Trigger: Officer determines no clinical action is required after initial
examination (traveler not ill, primary screening was a false positive).
Who: POE_SECONDARY or POE_ADMIN.
Requirement: officer_notes must explain why case is being closed directly.
Side effect: closed_at = isoNow(), final_disposition = 'RELEASED'.
Notification: status transitions to CLOSED via dbAtomicWrite.

Invalid transitions:
CLOSED → any state (permanently terminal)
DISPOSITIONED → OPEN / IN_PROGRESS (regression not allowed)
OPEN → DISPOSITIONED (must pass through IN_PROGRESS)

## 8.4 SYNDROME CLASSIFICATION CODES

syndrome_classification is a VARCHAR(60) that holds one of these codes,
determined by the officer based on the clinical picture:

ILI Influenza-Like Illness (fever + cough/sore throat)
SARI Severe Acute Respiratory Infection (ILI + shortness of breath)
AWD Acute Watery Diarrhea (≥3 loose stools/day, watery)
BLOODY_DIARRHEA Bloody diarrhea / dysentery
VHF Viral Hemorrhagic Fever (fever + bleeding signs)
RASH_FEVER Febrile rash illness (fever + generalized rash)
JAUNDICE Acute jaundice syndrome (yellow eyes/skin + fever)
NEUROLOGICAL Neurological syndrome (altered consciousness/seizures/paralysis)
MENINGITIS Meningitis syndrome (fever + neck stiffness + altered consciousness)
OTHER Any other acute illness syndrome not listed above
NONE No syndrome — traveler not ill (used when closing a false positive)

The officer selects one syndrome. The app then uses this classification plus
the disease reference data (diseases.js) to suggest probable suspected diseases.

## 8.5 SECONDARY CHILD TABLES — COMPLETE SPECIFICATION

TABLE: secondary_symptoms
id BIGINT UNSIGNED PK
secondary_screening_id BIGINT UNSIGNED NOT NULL, FK → secondary_screenings.id, CASCADE DELETE
symptom_code VARCHAR(80) NOT NULL (code from diseases.js symptom list)
is_present TINYINT(1) NOT NULL DEFAULT 1 (1 = yes, 0 = no)
onset_date DATE NULL (date symptoms first appeared)
details VARCHAR(255) NULL

Business rules: - All symptoms from the diseases.js symptom list are sent as the full array,
each with is_present = 1 or is_present = 0. - onset_date is required for: FEVER, RASH, DIARRHEA_WATERY, DIARRHEA_BLOODY,
VOMITING, COUGH (when is_present = 1). Optional for other symptoms. - details captures free text notes per symptom (e.g. "productive cough,
blood-tinged sputum"). - Written as replace-all: dbReplaceAll(STORE.SECONDARY_SYMPTOMS,
'secondary_screening_id', caseUuid, fullSymptomArray) every time the
symptoms tab is saved.

TABLE: secondary_exposures
id BIGINT UNSIGNED PK
secondary_screening_id BIGINT UNSIGNED NOT NULL, FK → secondary_screenings.id, CASCADE DELETE
exposure_code VARCHAR(80) NOT NULL (code from diseases.js exposure list)
response ENUM('YES','NO','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN'
details VARCHAR(255) NULL

Exposure codes (defined in diseases.js):
SICK_PERSON_CONTACT, KNOWN_CASE_CONTACT, HEALTHCARE_EXPOSURE,
ANIMAL_EXPOSURE, INSECT_BITE, FOOD_WATER_RISK, MASS_GATHERING,
FUNERAL_BURIAL, LAB_EXPOSURE, CHEMICAL_EXPOSURE, RADIATION_EXPOSURE,
VACCINATION_RELEVANT, PROPHYLAXIS_TAKEN

Business rules: - All exposure codes are sent as a full array with YES/NO/UNKNOWN per code. - UNKNOWN is the default — the officer actively changes each to YES or NO. - Written as replace-all: dbReplaceAll on every save.

TABLE: secondary_actions
id BIGINT UNSIGNED PK
secondary_screening_id BIGINT UNSIGNED NOT NULL, FK → secondary_screenings.id, CASCADE DELETE
action_code VARCHAR(80) NOT NULL
is_done TINYINT(1) NOT NULL DEFAULT 1
details VARCHAR(255) NULL

Action codes:
ISOLATED, MASK_GIVEN, PPE_USED, HAND_HYGIENE, SEPARATE_INTERVIEW_ROOM,
REFERRED_CLINIC, REFERRED_HOSPITAL, AMBULANCE_USED, QUARANTINE_RECOMMENDED,
SAMPLE_COLLECTED, TREATMENT_GIVEN, TRAVEL_RESTRICTION, ALLOWED_CONTINUE,
CONTACT_TRACING_INITIATED, ALERT_ISSUED, FOLLOWUP_SCHEDULED

Business rules: - At least one action must be recorded before case can reach DISPOSITIONED. - If risk_level = 'HIGH' or 'CRITICAL', ISOLATED or REFERRED_HOSPITAL must
be is_done = 1. The app enforces this as a validation on disposition.

TABLE: secondary_samples
id BIGINT UNSIGNED PK
secondary_screening_id BIGINT UNSIGNED NOT NULL, FK → secondary_screenings.id, CASCADE DELETE
sample_collected TINYINT(1) NOT NULL DEFAULT 0
sample_type VARCHAR(80) NULL
sample_identifier VARCHAR(120) NULL (barcode or lab reference number)
lab_destination VARCHAR(150) NULL
collected_at DATETIME NULL

Business rules: - If sample_collected = 1, sample_type is required. - If sample_collected = 1, collected_at is required. - sample_identifier and lab_destination are strongly recommended but not
required (may not be known at time of collection in field conditions). - Multiple samples can be recorded (one row per sample type).

TABLE: secondary_travel_countries
id BIGINT UNSIGNED PK
secondary_screening_id BIGINT UNSIGNED NOT NULL, FK → secondary_screenings.id, CASCADE DELETE
country_code VARCHAR(10) NOT NULL (ISO 3166-1 alpha-2)
travel_role ENUM('VISITED','TRANSIT') NOT NULL
arrival_date DATE NULL
departure_date DATE NULL

Business rules: - Countries visited or transited in the last 21 days are captured. - 21 days is the maximum incubation period for Ebola/Marburg and aligns
with the IHR lookback window for most Priority 1 diseases. - country_code must be a valid ISO code from the app's reference data. - arrival_date and departure_date are date-only (no time component). - TRANSIT = passed through without overnight stay. VISITED = stayed ≥1 night. - The journey_start_country_code on secondary_screenings captures the country
where the traveler began their journey (may differ from last visited country).

TABLE: secondary_suspected_diseases
id BIGINT UNSIGNED PK
secondary_screening_id BIGINT UNSIGNED NOT NULL, FK → secondary_screenings.id, CASCADE DELETE
disease_code VARCHAR(80) NOT NULL (code from diseases.js)
rank_order INT UNSIGNED NOT NULL DEFAULT 1 (1 = primary suspect)
confidence DECIMAL(5,2) NULL (percentage 0–100, officer estimate)
reasoning VARCHAR(255) NULL

Business rules: - rank_order = 1 is the primary suspected disease. Higher numbers are
differential diagnoses. - disease_code must be a valid code from the diseases.js reference file. - confidence is optional but recommended for rank_order = 1. - The app suggests diseases automatically based on syndrome_classification
and the symptom/exposure pattern using the disease-symptom mapping in
diseases.js. The officer can accept, modify, or override these suggestions. - Alert generation (Part 9) uses the rank_order = 1 disease_code to
determine whether an alert is required.

## 8.6 SECONDARY SCREENING — CLINICAL VALIDATIONS

On case opening:

- traveler_gender: required (pre-populated from primary record but can be updated).
- notification_id: required, must correspond to an existing OPEN notification
  at the officer's POE.

On reaching DISPOSITIONED status (all of these must pass):

- syndrome_classification: required, must be one of the defined syndrome codes.
- risk_level: required, must be LOW / MEDIUM / HIGH / CRITICAL.
- final_disposition: required.
- At least one secondary_actions record with is_done = 1 must exist.
- If risk_level IN ('HIGH','CRITICAL'):
  At least one of: ISOLATED = 1 OR REFERRED_HOSPITAL = 1 must exist in actions.
- If emergency_signs_present = 1:
  triage_category must be 'EMERGENCY'.
  general_appearance must be 'SEVERELY_ILL'.

Vital sign ranges (warn, not block):
temperature_value (Celsius):
< 35.0: "Hypothermia — verify reading."
35.0–37.4: Normal.
37.5–37.9: "Low-grade fever — document."
38.0–38.9: "Fever."
≥ 39.0: "High fever — consider URGENT/EMERGENCY triage."
≥ 40.0: "Dangerous fever — consider EMERGENCY triage."

pulse_rate:
< 40: "Critically low — verify."
40–59: "Bradycardia."
60–100: Normal.
101–149: "Tachycardia."
≥ 150: "Severe tachycardia — consider EMERGENCY."

respiratory_rate:
< 10: "Abnormally low — verify."
10–20: Normal adult.
21–29: "Elevated."
≥ 30: "Severe — consider EMERGENCY."

oxygen_saturation:
≥ 95: Normal.
90–94: "Low SpO2 — supplemental oxygen recommended."
< 90: "Critically low SpO2 — EMERGENCY."

bp_systolic:
< 90: "Hypotension."
90–139: Normal.
≥ 140: "Hypertension — note for follow-up."

These are advisory clinical warnings displayed inline in the vitals form.
They do not block case progression. The officer has clinical discretion.

================================================================================
PART 9: ALERTS
================================================================================

## 9.1 TABLE — EXACT SCHEMA

Table: alerts

id BIGINT UNSIGNED PK, AUTO_INCREMENT
client_uuid CHAR(36) NOT NULL, UNIQUE
idempotency_key CHAR(64) NULL
reference_data_version VARCHAR(40) NOT NULL
server_received_at DATETIME NULL
country_code VARCHAR(10) NOT NULL
province_code VARCHAR(30) NULL
pheoc_code VARCHAR(30) NULL
district_code VARCHAR(30) NOT NULL
poe_code VARCHAR(40) NOT NULL
secondary_screening_id BIGINT UNSIGNED NOT NULL, FK → secondary_screenings.id
generated_from ENUM('RULE_BASED','OFFICER') NOT NULL DEFAULT 'RULE_BASED'
risk_level ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'HIGH'
alert_code VARCHAR(80) NOT NULL
alert_title VARCHAR(150) NOT NULL
alert_details VARCHAR(500) NULL
routed_to_level ENUM('DISTRICT','PHEOC','NATIONAL') NOT NULL DEFAULT 'DISTRICT'
status ENUM('OPEN','ACKNOWLEDGED','CLOSED') NOT NULL DEFAULT 'OPEN'
acknowledged_by_user_id BIGINT UNSIGNED NULL, FK → users.id
acknowledged_at DATETIME NULL
closed_at DATETIME NULL
device_id VARCHAR(80) NOT NULL
app_version VARCHAR(40) NULL
platform ENUM('ANDROID','IOS','WEB') NOT NULL DEFAULT 'ANDROID'
record_version INT UNSIGNED NOT NULL DEFAULT 1
deleted_at DATETIME NULL
sync_status ENUM('UNSYNCED','SYNCED','FAILED') NOT NULL DEFAULT 'UNSYNCED'
synced_at DATETIME NULL
sync_attempt_count INT UNSIGNED NOT NULL DEFAULT 0
last_sync_error VARCHAR(500) NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NOT NULL, ON UPDATE CURRENT_TIMESTAMP

## 9.2 ALERT GENERATION RULES (IHR ANNEX 2 ALIGNED)

An alert is generated automatically (generated_from = 'RULE_BASED') when a
secondary case meets any of the trigger criteria below. Rules are evaluated
offline in the app using the disease/symptom mapping in diseases.js.

MANDATORY ALERT TRIGGERS (always generate alert):

- risk_level = 'CRITICAL': any case assessed as critically high risk.
- risk_level = 'HIGH' AND syndrome_classification IN ('VHF','MENINGITIS').
- Any suspected disease with rank_order = 1 that is in the Priority 1 disease
  list (defined in diseases.js). Priority 1 diseases include:
  CHOLERA, PLAGUE, EBOLA, MARBURG, LASSA, CCHF, RVFEVER, YELLOW_FEVER,
  MPOX (if regional flag active), SMALLPOX (any suspicion = immediate alert).
- emergency_signs_present = 1 AND risk_level IN ('HIGH','CRITICAL').

CONDITIONAL ALERT TRIGGERS (generate alert if additional criteria met):

- risk_level = 'HIGH' AND travel to an active outbreak country in last 21 days
  (determined by country_codes in secondary_travel_countries matching an
  outbreak-affected country list in diseases.js).
- syndrome_classification = 'SARI' AND oxygen_saturation < 90.
- syndrome_classification = 'AWD' AND general_appearance = 'SEVERELY_ILL'.

NO ALERT TRIGGERED:

- risk_level IN ('LOW','MEDIUM') with no Priority 1 disease suspected.
- Case closed as 'NONE' syndrome (false positive).

Officer-generated alerts (generated_from = 'OFFICER'):

- The secondary officer can manually generate an alert even when automatic
  rules are not triggered, based on clinical judgment.
- officer_notes must explain the reason for manual alert generation.

## 9.3 ALERT ROUTING RULES

routed_to_level determines which tier of the hierarchy receives and must
acknowledge the alert:

'DISTRICT':
Condition: risk_level = 'HIGH' AND no Priority 1 disease suspected.
Acknowledged by: DISTRICT_SUPERVISOR.

'PHEOC':
Condition: risk_level = 'CRITICAL' OR Priority 1 disease suspected
with risk_level = 'HIGH'.
Acknowledged by: PHEOC_OFFICER or NATIONAL_ADMIN.

'NATIONAL':
Condition: Any suspicion of EBOLA, MARBURG, PLAGUE, SMALLPOX, or any
disease that may constitute a PHEIC under IHR Annex 2.
Acknowledged by: NATIONAL_ADMIN only.

alert_code is a machine-readable string that identifies the specific trigger:
Examples:
'HIGH_RISK_VHF_SUSPECT'
'CRITICAL_RISK_CASE'
'PRIORITY1_DISEASE_CHOLERA'
'EMERGENCY_SIGNS_HIGH_RISK'
'OFFICER_CLINICAL_JUDGMENT'

alert_title is a human-readable string presented in the dashboard alert list.

## 9.4 ALERT STATUS MACHINE

OPEN → ACKNOWLEDGED
Trigger: Authorized supervisor acknowledges the alert.
Who: Role with can_acknowledge_alerts at or above the routed_to_level.
Fields: acknowledged_by_user_id = acknowledger's id, acknowledged_at = NOW().
This is a web dashboard action. Mobile app shows alerts as read-only.

ACKNOWLEDGED → CLOSED
Trigger: Response actions are complete and alert can be closed.
Who: Same roles that can acknowledge.
Fields: closed_at = NOW().

OPEN → CLOSED (direct, skipping ACKNOWLEDGED)
Trigger: Alert was generated in error (duplicate, invalid).
Who: NATIONAL_ADMIN only.

Invalid transitions:
CLOSED → any state (permanently terminal)

================================================================================
PART 10: AGGREGATED DATA SUBMISSIONS
================================================================================

## 10.1 TABLE — EXACT SCHEMA

Table: aggregated_submissions

id BIGINT UNSIGNED PK, AUTO_INCREMENT
client_uuid CHAR(36) NOT NULL, UNIQUE
idempotency_key CHAR(64) NULL
reference_data_version VARCHAR(40) NOT NULL
server_received_at DATETIME NULL
country_code VARCHAR(10) NOT NULL
province_code VARCHAR(30) NULL
pheoc_code VARCHAR(30) NULL
district_code VARCHAR(30) NOT NULL
poe_code VARCHAR(40) NOT NULL
submitted_by_user_id BIGINT UNSIGNED NOT NULL, FK → users.id
period_start DATETIME NOT NULL
period_end DATETIME NOT NULL
total_screened INT UNSIGNED NOT NULL DEFAULT 0
total_male INT UNSIGNED NOT NULL DEFAULT 0
total_female INT UNSIGNED NOT NULL DEFAULT 0
total_other INT UNSIGNED NOT NULL DEFAULT 0
total_unknown_gender INT UNSIGNED NOT NULL DEFAULT 0
total_symptomatic INT UNSIGNED NOT NULL DEFAULT 0
total_asymptomatic INT UNSIGNED NOT NULL DEFAULT 0
notes VARCHAR(255) NULL
device_id VARCHAR(80) NOT NULL
app_version VARCHAR(40) NULL
platform ENUM('ANDROID','IOS','WEB') NOT NULL DEFAULT 'ANDROID'
record_version INT UNSIGNED NOT NULL DEFAULT 1
deleted_at DATETIME NULL
sync_status ENUM('UNSYNCED','SYNCED','FAILED') NOT NULL DEFAULT 'UNSYNCED'
synced_at DATETIME NULL
sync_attempt_count INT UNSIGNED NOT NULL DEFAULT 0
last_sync_error VARCHAR(500) NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NOT NULL, ON UPDATE CURRENT_TIMESTAMP

## 10.2 AGGREGATED SUBMISSION BUSINESS RULES

- Aggregated submissions are INDEPENDENT of individual screening records.
  They are manually entered summary counts, not auto-computed from
  primary_screenings. This is intentional — border posts sometimes tally
  travelers manually before the digital system was deployed, and the
  aggregated module captures these retrospective counts.

- Required by WHO/IHR: States must report summary traveler statistics
  at designated POEs at least weekly.

- AUTHORIZATION:
  auth.\_permissions.can_submit_aggregated must be true.
  Only POE_DATA_OFFICER and POE_ADMIN can create aggregated submissions.

- PERIOD VALIDATION:
  period_end must be > period_start.
  period_start cannot be in the future.
  period_end cannot be in the future by more than 1 hour (allow for clock drift).
  Periods longer than 7 days are rejected with a warning.

- COUNT VALIDATION:
  total_screened must equal total_male + total_female + total_other +
  total_unknown_gender.
  total_screened must equal total_symptomatic + total_asymptomatic.
  All counts must be ≥ 0.
  If total_screened = 0, a confirmation is required from the officer.

- DUPLICATE PERIOD CHECK:
  Before saving, check IndexedDB for existing submissions where:
  poe_code matches AND period ranges overlap (period_start or period_end
  falls within an existing submission's range). If overlap detected,
  show warning: "A submission for an overlapping period already exists."
  Officer must confirm override or adjust period.

- IDEMPOTENCY:
  client_uuid ensures the server does not insert duplicates on re-sync.
  The server checks client_uuid uniqueness before INSERT.

- notes (VARCHAR 255): free text for officer to document any context
  about the period (e.g. "Included retrospective count for 22–24 March.
  Border was closed 23 March for national holiday.").

================================================================================
PART 11: SYNC ARCHITECTURE
================================================================================

## 11.1 SYNC BATCHES TABLE — EXACT SCHEMA

Table: sync_batches

id BIGINT UNSIGNED PK, AUTO_INCREMENT
client_batch_uuid CHAR(36) NOT NULL, UNIQUE
device_id VARCHAR(80) NOT NULL
reference_data_version VARCHAR(40) NOT NULL
submitted_by_user_id BIGINT UNSIGNED NOT NULL, FK → users.id
started_at_device DATETIME NULL
started_timezone VARCHAR(64) NULL
received_at_server DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
status ENUM('RECEIVED','PROCESSING','PARTIAL','FAILED','COMPLETED')
NOT NULL DEFAULT 'RECEIVED'
error_summary VARCHAR(500) NULL

Table: sync_batch_items

id BIGINT UNSIGNED PK, AUTO_INCREMENT
sync_batch_id BIGINT UNSIGNED NOT NULL, FK → sync_batches.id, CASCADE DELETE
entity_type ENUM('PRIMARY','NOTIFICATION','SECONDARY','ALERT','AGGREGATED')
NOT NULL
entity_client_uuid CHAR(36) NOT NULL
server_entity_id BIGINT UNSIGNED NULL (set after successful insert on server)
status ENUM('ACCEPTED','REJECTED') NOT NULL DEFAULT 'ACCEPTED'
error_message VARCHAR(500) NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

## 11.2 SYNC STRATEGY

The sync architecture is manual and deterministic. The app never silently merges
or overwrites data. Every sync operation is traceable.

SYNC FLOW:

1. User taps "Upload" button.
2. App queries IndexedDB for all UNSYNCED records across relevant stores.
   Query by index: dbGetByIndex(store, 'sync_status', SYNC.UNSYNCED)
3. App builds a batch payload:
   {
   client_batch_uuid: genUUID(),
   started_at_device: isoNow(),
   started_timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
   reference_data_version: APP.REFERENCE_DATA_VER,
   items: [
   {
   entity_type: 'PRIMARY',
   client_uuid: '...',
   // full record payload
   },
   {
   entity_type: 'NOTIFICATION',
   client_uuid: '...',
   // full record payload
   },
   // ... additional items
   ]
   }
4. App saves the sync_batches record locally in IndexedDB first (UNSYNCED).
5. App POSTs to POST /api/sync/batch.
6. Server creates sync_batches row (status = RECEIVED).
7. Server processes each item:
   - Validates the record.
   - If valid: inserts to appropriate table, creates sync_batch_items row
     (status = ACCEPTED, server_entity_id = new MySQL id).
   - If invalid: creates sync_batch_items row (status = REJECTED,
     error_message = validation error).
8. Server updates sync_batches.status to COMPLETED (all accepted) or PARTIAL
   (some rejected) or FAILED (all rejected).
9. Server returns:
   {
   batch_server_id: 42,
   status: 'COMPLETED',
   items: [
   { entity_client_uuid: '...', server_entity_id: 14, status: 'ACCEPTED' },
   { entity_client_uuid: '...', server_entity_id: 1, status: 'ACCEPTED' },
   ...
   ]
   }
10. App processes the response:
    - For each ACCEPTED item: safeDbPut with sync_status = SYNCED,
      server_id = server_entity_id, synced_at = isoNow().
    - For each REJECTED item: safeDbPut with sync_status = FAILED,
      sync_note = error_message, last_sync_error = error_message.
    - Linked pairs (PRIMARY + NOTIFICATION) are updated via dbAtomicWrite
      to ensure both are marked SYNCED together or neither is.

ITEM ORDERING WITHIN BATCH:
Items in the batch must be ordered to respect foreign key dependencies:

1. PRIMARY items first (primary_screenings)
2. NOTIFICATION items second (notifications FK → primary_screenings)
3. SECONDARY items third (secondary_screenings FK → notifications, primary_screenings)
4. ALERT items fourth (alerts FK → secondary_screenings)
5. AGGREGATED items last (independent, no FK dependencies on screening records)

If a notification references a primary screening that has not yet synced
(primary screening is still UNSYNCED), the notification must be sent in the
same batch as the primary screening. The app must include all linked records
in the same batch and order them correctly.

RETRY STRATEGY:

- FAILED items (4xx non-retryable): remain FAILED with sync_note.
  These require officer intervention. Shown in sync status UI as "Queued".
- UNSYNCED items (network/timeout): scheduled for retry via scheduleRetry()
  with APP.SYNC_RETRY_MS (10 second) flat interval.
- SYNCED items: never retried.

The sync engine uses activeSyncKeys Set to prevent concurrent upload of the
same record UUID. This is critical when auto-retry fires while a manual sync
is already in progress.

## 11.3 IDEMPOTENCY

Every record carries a client_uuid (UUID v4) that is generated on the device at
the moment of creation. The server uses client_uuid as the idempotency key for
all sync operations.

Server rule:

- If a record with the given client_uuid already exists in the database
  AND its sync_status = 'SYNCED', return the existing server_id as if it
  was just accepted. Do not insert or update.
- If a record with the given client_uuid exists but sync_status = 'FAILED'
  (previous server rejection), reject again with the same error unless the
  data has changed (record_version is higher than the stored version).

idempotency_key (CHAR 64) is a reserved column for future implementation of
HMAC-based request deduplication at the HTTP level. Currently NULL in all records.

================================================================================
PART 12: WEB DASHBOARD — DATA ACCESS RULES
================================================================================

## 12.1 DASHBOARD SCOPE BY ROLE

The web dashboard is a read-mostly interface. All writes to screening data happen
on the mobile app. The dashboard provides:

- Real-time monitoring of synced records.
- Alert acknowledgement workflow.
- User management.
- Aggregated reporting.

Data scope enforcement on the dashboard mirrors the mobile app:

POE_ADMIN (web dashboard access): - All records WHERE poe_code = their assigned poe_code. - Can acknowledge alerts routed to POE level. - Can close notifications. - Can manage users at their POE.

DISTRICT_SUPERVISOR: - All records WHERE district_code = their assigned district_code. - Can acknowledge alerts routed to DISTRICT level. - Cannot close notifications (that is a POE-level action). - Cannot manage users.

PHEOC_OFFICER: - All records WHERE pheoc_code = their assigned pheoc_code. - Can acknowledge alerts routed to PHEOC level. - Can view all districts and POEs in their PHEOC region.

NATIONAL_ADMIN: - All records WHERE country_code = their country_code. - Full user management. - Can acknowledge and close all alerts. - Can void primary screening records.

## 12.2 DASHBOARD QUERY PATTERNS

These are the critical MySQL queries underlying dashboard views.

Today's screening summary for a POE:
SELECT
COUNT(\*) AS total_screened,
SUM(symptoms_present) AS total_symptomatic,
SUM(CASE WHEN gender = 'MALE' THEN 1 ELSE 0 END) AS total_male,
SUM(CASE WHEN gender = 'FEMALE' THEN 1 ELSE 0 END) AS total_female
FROM primary_screenings
WHERE poe_code = ? AND DATE(captured_at) = CURDATE()
AND record_status = 'COMPLETED' AND deleted_at IS NULL

Open referrals queue for secondary officer:
SELECT n.\*, ps.gender, ps.temperature_value, ps.temperature_unit
FROM notifications n
JOIN primary_screenings ps ON ps.id = n.primary_screening_id
WHERE n.poe_code = ? AND n.status = 'OPEN'
AND n.notification_type = 'SECONDARY_REFERRAL'
AND n.deleted_at IS NULL
ORDER BY n.priority DESC, n.created_at ASC

Open alerts for district supervisor:
SELECT \* FROM alerts
WHERE district_code = ? AND status = 'OPEN'
AND routed_to_level IN ('DISTRICT','PHEOC','NATIONAL')
AND deleted_at IS NULL
ORDER BY risk_level DESC, created_at ASC

Pending sync items (for sync monitoring dashboard):
SELECT device_id, COUNT(\*) AS pending_count
FROM primary_screenings
WHERE poe_code = ? AND sync_status = 'UNSYNCED'
AND deleted_at IS NULL
GROUP BY device_id

================================================================================
PART 13: DATA INTEGRITY & TRACEABILITY RULES
================================================================================

## 13.1 IMMUTABLE FIELDS — NEVER UPDATE AFTER CREATION

These fields are set at creation time and must never be updated, even by
NATIONAL_ADMIN. They are the audit foundation of the system.

client_uuid Set by device. Never changes.
created_at Set at INSERT. Never changes.
created_by_user_id Set at creation. Never changes.
captured_at The clinical timestamp. Never changes.
poe_code Set from auth at creation. Never changes.
district_code Set from auth at creation. Never changes.
country_code Set from auth at creation. Never changes.
reference_data_version Set at creation. Never changes.
device_id Set at creation. Never changes.
primary_screening_id (on notifications, secondary_screenings) — never changes
notification_id (on secondary_screenings) — never changes

## 13.2 CASCADING RULES ON DELETE

Secondary child tables all have ON DELETE CASCADE from secondary_screenings.id.
This means: if a secondary screening case is hard-deleted, all its symptoms,
exposures, actions, samples, travel countries, and suspected diseases are
automatically deleted.

Hard delete is NEVER performed by the application under normal operation.
deleted_at (soft delete) is the standard approach. Hard delete is reserved
for data privacy requests handled directly at database level by NATIONAL_ADMIN.

sync_batch_items has ON DELETE CASCADE from sync_batches.id. Deleting a sync
batch record removes its items. Sync batches themselves are never deleted by
the application — they are permanent audit records.

## 13.3 RECORD VERSIONING

Every table that is synced from the mobile app carries record_version INT UNSIGNED.
This starts at 1 and increments by 1 on every write, including sync callbacks.

On the server side:
When processing a sync item, the server compares the incoming record_version
with the currently stored record_version.

- If incoming.record_version > stored.record_version: accept and update.
- If incoming.record_version <= stored.record_version: the incoming record
  is stale. Return the stored server record without updating. Do not return
  an error — this is a normal idempotent re-submission scenario.

In IndexedDB (client side):
safeDbPut() enforces the same rule: if stored.record_version >= incoming.record_version,
the write is discarded silently. This prevents a background sync callback
from overwriting a local edit made after the sync was initiated.

## 13.4 FOREIGN KEY RESOLUTION DURING SYNC

In IndexedDB, all foreign key relationships are stored as client_uuid strings
(not server integer IDs). The mapping is:

notifications.primary_screening_id = a client_uuid string on the device.
secondary_screenings.primary_screening_id = same client_uuid string.
secondary_screenings.notification_id = notification's client_uuid string.
alerts.secondary_screening_id = secondary screening's client_uuid string.

When these records sync to the server, the server resolves client_uuids to
server integer IDs before inserting. The server looks up each referenced
client_uuid in the relevant table and replaces it with the server integer id.

This resolution requires strict batch ordering (see Part 11.2): the parent
record must be accepted before the child record references it. If the parent
is not yet in the database when the child is being processed, the server must
either:
(a) Process children after all parents in the same batch are committed, or
(b) Return a REJECTED status for the child with error_message =
"Parent record not yet synced. Include in same batch."

The client receives server_entity_id for each accepted item in the batch
response and updates the local record's server_id field. The local client_uuid
mapping is never removed — it stays permanently for local lookups.

## 13.5 CRITICAL BUSINESS CONSTRAINT: referral_created SYNC TIMING

referral_created = 1 on a primary screening record means a notification was
also created locally. These two records MUST arrive on the server in the same
batch and both must be ACCEPTED for the system to be fully consistent.

If a batch contains a primary screening with referral_created = 1 but does NOT
contain the linked notification, the server must:

- Accept the primary screening (it is valid on its own).
- Return a batch-level warning: "Notification record missing for primary
  screening [client_uuid]. Include notification in a follow-up sync."

The client must detect this and add the missing notification to the next batch
automatically. The app ensures this by always including notification records in
the same sync batch as their parent primary screening records.

# POE APP — AI DEV RULES: Dexie.js Offline Data Layer

## Authoritative Specification · Dexie 4.3.x · Ionic 8 · Vue 3 · Pure JavaScript (NO TypeScript)

> **Researched against:** Dexie.js 4.3.0 official API (https://dexie.org/docs/API-Reference), npm latest 2025/2026.
> **Language enforcement:** All code in this document and all generated views are `.js` files — no `.ts`, no type annotations, no `interface`, no `EntityTable`, no `: string`, no generic brackets anywhere.

---

## INSTALLATION PREREQUISITE — EXECUTE ONCE

```bash
npm install dexie
```

Verify `package.json` shows `"dexie": "^4.3.0"` under `dependencies` (not devDependencies).
No other offline storage library is permitted — no RxDB, no PouchDB, no localForage, no raw `indexedDB.open()`.

---

## PRIME DIRECTIVE

`src/services/poeDB.js` is the **ONLY** file that may instantiate Dexie or touch IndexedDB.

Every view that reads or writes local data **MUST** import exclusively from `@/services/poeDB`.

**BANNED in every view and every file except `poeDB.js` — zero exceptions:**

```js
// NEVER write any of these anywhere outside poeDB.js

new Dexie(...)                      // BANNED — never instantiate Dexie in a view
import Dexie from 'dexie'           // BANNED — only poeDB.js imports Dexie
indexedDB.open(...)                 // BANNED — forever
window.indexedDB                    // BANNED — forever
const POE_DB_VERSION = ...          // BANNED — internal to poeDB.js
const POE_DB_NAME    = ...          // BANNED — use APP.DB_NAME
function getPoeDB()  { ... }        // BANNED — inline copy of DB accessor
async function dbPut(...) { }       // BANNED — inline copy
async function dbGet(...) { }       // BANNED — inline copy
const APP_VER       = '0.0.1'       // BANNED — use APP.VERSION
const RETRY_MS      = 10_000        // BANNED — use APP.SYNC_RETRY_MS
const SYNC_TIMEOUT  = 8_000         // BANNED — use APP.SYNC_TIMEOUT_MS

// TypeScript constructs — also banned everywhere in this project
interface ...                       // BANNED — this project is pure JS
type Foo = ...                      // BANNED — this project is pure JS
EntityTable<...>                    // BANNED — TS-only Dexie type
: string                            // BANNED — no type annotations
<T>                                 // BANNED — no generics
```

---

## WHY DEXIE — ENTERPRISE OFFLINE SAFETY RATIONALE

Raw `indexedDB.open()` is fragile in a mobile border-post environment.
Dexie 4.3.x eliminates every failure mode:

| Risk Scenario                                     | Raw IndexedDB Result                                                                        | Dexie 4.3.x Result                                                                          |
| ------------------------------------------------- | ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| Two tabs open simultaneously                      | Manual `versionchange` + `blocked` required; miss one handler → deadlock or data corruption | `db.on('versionchange')` and `db.on('blocked')` handled automatically and correctly         |
| Android low-memory rapid app close/reopen         | `indexedDB.open()` callback race → partial write state, inconsistent DB                     | Module-level singleton opens eagerly; Capacitor lifecycle survives clean                    |
| App updated while user offline (schema drift)     | `VersionError` crash if browser holds higher version than code requests                     | Dexie version chain is additive; never downgrades; always opens at highest declared version |
| IDB transaction auto-abort on tab backgrounding   | Partial writes committed before abort → silent data corruption                              | Dexie wraps every operation in a proper transaction scope; abort rolls back atomically      |
| Sequential async writes (read-modify-write race)  | Second write can see stale data between read and put                                        | `db.transaction('rw', ...)` is a serialised zone; concurrent ops queue correctly            |
| Schema migration across multiple versions skipped | Manual `onupgradeneeded` must handle every delta individually                               | Dexie applies every `.version()` block in sequence automatically on first open              |
| Error handling omission                           | Every `IDBRequest` needs `.onerror` + `.onabort`; easy to miss                              | Dexie rejects the returned Promise; standard `try/catch` covers the entire operation        |
| Bulk insert performance                           | N individual `put()` calls with N `onsuccess` callbacks = slow                              | `bulkPut()` skips intermediate `onsuccess` events using IDB's lesser-known fast path        |

**Bottom line:** Dexie IS IndexedDB — it compiles to the same browser API with zero hidden storage layer.
It is production-battle-tested across 100,000+ apps including Microsoft and Mozilla products,
and explicitly supports Capacitor for iOS/Android as a first-class target.

---

## MANDATORY IMPORT BLOCK FOR VIEWS

Every view that uses local data begins with this exact block.
Import **only** what the view actually uses — do not import the full list if unused.

```js
import {
  // DB operations (all Dexie-backed — zero raw IDB in views)
  dbPut,
  dbGet,
  dbGetAll,
  safeDbPut,
  dbDelete,
  dbExists,
  dbGetByIndex,
  dbGetRange,
  dbGetCount,
  dbCountIndex,
  dbPutBatch,
  dbDeleteByIndex,
  dbReplaceAll,
  dbAtomicWrite,
  dbQuery,

  // Utilities — never redeclare these in a view
  genUUID,
  isoNow,
  getPlatform,
  getDeviceId,
  createRecordBase,

  // Constants — never hardcode these values in a view
  STORE,
  STORE_KEY,
  SYNC,
  APP,
} from "@/services/poeDB";
```

`getPoeDB()` is **not exported to views**. Views never touch the Dexie instance directly.
The Dexie instance is an internal implementation detail of `poeDB.js` exclusively.

---

## CONSTANTS REFERENCE

### `APP`

```js
APP.VERSION; // '0.0.1'            — app version string
APP.REFERENCE_DATA_VER; // 'rda-2026-02-01'   — stamp on every record
APP.DB_NAME; // 'poe_offline_db'
APP.SCHEMA_VERSION; // 14                 — current Dexie schema version integer
APP.SYNC_RETRY_MS; // 10_000             — flat retry interval (ms)
APP.SYNC_TIMEOUT_MS; //  8_000             — AbortController hard timeout per fetch
APP.DEVICE_ID_KEY; // 'poe_device_id'    — localStorage key
APP.DAY_COUNT_DAY_KEY; // 'poe_ps_day'       — localStorage key
APP.DAY_COUNT_CNT_KEY; // 'poe_ps_cnt'       — localStorage key
```

### `SYNC`

```js
SYNC.UNSYNCED; // 'UNSYNCED' — saved locally, not yet attempted
SYNC.SYNCED; // 'SYNCED'   — server confirmed, server_id returned
SYNC.FAILED; // 'FAILED'   — server rejected (4xx non-retryable)

// UI display labels — NEVER show raw SYNC values in the UI
SYNC.LABELS.UNSYNCED; // 'Pending'
SYNC.LABELS.SYNCED; // 'Uploaded'
SYNC.LABELS.FAILED; // 'Queued'
```

### `STORE`

```js
STORE.USERS_LOCAL; // 'users_local'
STORE.PRIMARY_SCREENINGS; // 'primary_screenings'
STORE.NOTIFICATIONS; // 'notifications'
STORE.SECONDARY_SCREENINGS; // 'secondary_screenings'
STORE.SECONDARY_SYMPTOMS; // 'secondary_symptoms'
STORE.SECONDARY_EXPOSURES; // 'secondary_exposures'
STORE.SECONDARY_ACTIONS; // 'secondary_actions'
STORE.SECONDARY_SAMPLES; // 'secondary_samples'
STORE.SECONDARY_TRAVEL_COUNTRIES; // 'secondary_travel_countries'
STORE.SECONDARY_SUSPECTED_DISEASES; // 'secondary_suspected_diseases'
STORE.ALERTS; // 'alerts'
STORE.AGGREGATED_SUBMISSIONS; // 'aggregated_submissions'
STORE.SYNC_BATCHES; // 'sync_batches'
STORE.SYNC_BATCH_ITEMS; // 'sync_batch_items'
```

### `STORE_KEY` — primary key field per store

Most stores use `client_uuid`. Two stores differ — always check:

```js
STORE_KEY[STORE.SYNC_BATCHES]; // 'client_batch_uuid'   ← not client_uuid
STORE_KEY[STORE.SYNC_BATCH_ITEMS]; // 'entity_client_uuid'  ← not client_uuid
// All other stores → 'client_uuid'
```

---

## INDEXES DECLARED PER TABLE

These are the **ONLY** indexes that exist. All query functions only accept these exact names.
Any other string throws a Dexie `InvalidTableError` at runtime.

```
users_local:
  sync_status, role_key, country_code, is_active,
  username, username_ci, email_ci

primary_screenings:
  sync_status, poe_code, captured_at, captured_by_user_id,
  referral_created, symptoms_present

notifications:
  sync_status, poe_code, primary_screening_id, status,
  notification_type, priority

secondary_screenings:
  sync_status, poe_code, case_status,
  primary_screening_id, notification_id, opened_by_user_id

secondary_symptoms, secondary_exposures, secondary_actions,
secondary_samples, secondary_travel_countries, secondary_suspected_diseases:
  secondary_screening_id   ← the ONLY index on each child table

alerts:
  sync_status, poe_code, status, risk_level, secondary_screening_id

aggregated_submissions:
  sync_status, poe_code, period_start, district_code

sync_batches:
  status, device_id

sync_batch_items:
  sync_batch_id, entity_type
```

---

## `poeDB.js` — COMPLETE AUTHORITATIVE IMPLEMENTATION

**Place at:** `src/services/poeDB.js`
**Language:** Pure JavaScript — no TypeScript, no type annotations, no `.ts` extension.

```js
/**
 * poeDB.js — POE Offline-First Centralized Data Layer
 * Powered by Dexie.js 4.3.x  https://dexie.org
 *
 * Place at:  src/services/poeDB.js
 * Import:    import { dbPut, dbGet, STORE, SYNC, APP, ... } from '@/services/poeDB'
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SINGLE SOURCE OF TRUTH for all offline data in the POE app.           ║
 * ║                                                                          ║
 * ║  RULES (non-negotiable):                                                 ║
 * ║  1. Every view imports from here — NEVER instantiates Dexie itself      ║
 * ║  2. ALL tables declared ONCE in the version chain below                 ║
 * ║  3. _db is the module-level Dexie singleton — one instance app-wide     ║
 * ║  4. To add a table: STORE entry + STORE_KEY entry + new .version()      ║
 * ║  5. NEVER modify an existing .version() block — always add a new one    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

import Dexie from "dexie";

// ─────────────────────────────────────────────────────────────────────────
// APP-WIDE CONSTANTS
// ─────────────────────────────────────────────────────────────────────────

export const APP = Object.freeze({
  VERSION: "0.0.1",
  REFERENCE_DATA_VER: "rda-2026-02-01",
  DB_NAME: "poe_offline_db",
  SCHEMA_VERSION: 14,
  SYNC_RETRY_MS: 10_000,
  SYNC_TIMEOUT_MS: 8_000,
  DEVICE_ID_KEY: "poe_device_id",
  DAY_COUNT_DAY_KEY: "poe_ps_day",
  DAY_COUNT_CNT_KEY: "poe_ps_cnt",
});

export const SYNC = Object.freeze({
  UNSYNCED: "UNSYNCED",
  SYNCED: "SYNCED",
  FAILED: "FAILED",
  LABELS: Object.freeze({
    UNSYNCED: "Pending",
    SYNCED: "Uploaded",
    FAILED: "Queued",
  }),
});

export const STORE = Object.freeze({
  USERS_LOCAL: "users_local",
  PRIMARY_SCREENINGS: "primary_screenings",
  NOTIFICATIONS: "notifications",
  SECONDARY_SCREENINGS: "secondary_screenings",
  SECONDARY_SYMPTOMS: "secondary_symptoms",
  SECONDARY_EXPOSURES: "secondary_exposures",
  SECONDARY_ACTIONS: "secondary_actions",
  SECONDARY_SAMPLES: "secondary_samples",
  SECONDARY_TRAVEL_COUNTRIES: "secondary_travel_countries",
  SECONDARY_SUSPECTED_DISEASES: "secondary_suspected_diseases",
  ALERTS: "alerts",
  AGGREGATED_SUBMISSIONS: "aggregated_submissions",
  SYNC_BATCHES: "sync_batches",
  SYNC_BATCH_ITEMS: "sync_batch_items",
});

export const STORE_KEY = Object.freeze({
  [STORE.USERS_LOCAL]: "client_uuid",
  [STORE.PRIMARY_SCREENINGS]: "client_uuid",
  [STORE.NOTIFICATIONS]: "client_uuid",
  [STORE.SECONDARY_SCREENINGS]: "client_uuid",
  [STORE.SECONDARY_SYMPTOMS]: "client_uuid",
  [STORE.SECONDARY_EXPOSURES]: "client_uuid",
  [STORE.SECONDARY_ACTIONS]: "client_uuid",
  [STORE.SECONDARY_SAMPLES]: "client_uuid",
  [STORE.SECONDARY_TRAVEL_COUNTRIES]: "client_uuid",
  [STORE.SECONDARY_SUSPECTED_DISEASES]: "client_uuid",
  [STORE.ALERTS]: "client_uuid",
  [STORE.AGGREGATED_SUBMISSIONS]: "client_uuid",
  [STORE.SYNC_BATCHES]: "client_batch_uuid",
  [STORE.SYNC_BATCH_ITEMS]: "entity_client_uuid",
});

// ─────────────────────────────────────────────────────────────────────────
// DEXIE SINGLETON — created once at module evaluation, shared by all views
// ─────────────────────────────────────────────────────────────────────────

/**
 * _db is the single Dexie instance for the entire app.
 * Created at module load time — before any view renders.
 * Views NEVER access _db directly. They call the exported wrapper functions.
 *
 * MULTI-TAB / RAPID LIFECYCLE SAFETY (all handled by Dexie automatically):
 *
 *   db.on('versionchange') — another tab upgraded the DB schema:
 *     Dexie closes this connection gracefully. The upgrading tab proceeds
 *     without being blocked. On the next operation in this tab, Dexie
 *     re-opens at the new version. All UNSYNCED records remain intact.
 *
 *   db.on('blocked') — this tab is upgrading but another tab holds old schema:
 *     Dexie waits. No writes occur. User must close other tabs and reload.
 *
 *   Capacitor rapid close/reopen:
 *     _db is module-level. On each app open the module re-evaluates,
 *     _db.open() fires eagerly BEFORE any view requests a write,
 *     eliminating the race between first write and DB init on low-spec devices.
 */
const _db = new Dexie(APP.DB_NAME);

// ─────────────────────────────────────────────────────────────────────────
// SCHEMA VERSION CHAIN — ALL TABLES DECLARED HERE, NOWHERE ELSE
// ─────────────────────────────────────────────────────────────────────────

/**
 * Dexie schema string syntax:
 *   'primaryKey, index1, index2, ...'
 *
 * Rules:
 *   - First entry = primary key (keyPath). Never use ++ for our UUID keys.
 *   - Additional comma-separated fields = IDB indexes (queryable via .where()).
 *   - Fields NOT listed are still stored — just not queryable by index.
 *   - Only declare fields you actually query by — not every column.
 *   - '&field' = unique index.   '*field' = multi-entry.
 *
 * Migration rules (NEVER BREAK THESE):
 *   - NEVER modify a .version() block that has shipped to devices.
 *   - To ADD a table or index: bump APP.SCHEMA_VERSION and add a NEW .version() block.
 *   - All previous .version() blocks must remain in this file forever.
 *   - Dexie applies all version blocks in sequence; any gap is filled on first open.
 */

_db.version(14).stores({
  users_local:
    "client_uuid, sync_status, role_key, country_code, is_active, username, username_ci, email_ci",

  primary_screenings:
    "client_uuid, sync_status, poe_code, captured_at, captured_by_user_id, referral_created, symptoms_present",

  notifications:
    "client_uuid, sync_status, poe_code, primary_screening_id, status, notification_type, priority",

  secondary_screenings:
    "client_uuid, sync_status, poe_code, case_status, primary_screening_id, notification_id, opened_by_user_id",

  // Child tables: only one index each — the parent FK for replace-all pattern
  secondary_symptoms: "client_uuid, secondary_screening_id",
  secondary_exposures: "client_uuid, secondary_screening_id",
  secondary_actions: "client_uuid, secondary_screening_id",
  secondary_samples: "client_uuid, secondary_screening_id",
  secondary_travel_countries: "client_uuid, secondary_screening_id",
  secondary_suspected_diseases: "client_uuid, secondary_screening_id",

  alerts:
    "client_uuid, sync_status, poe_code, status, risk_level, secondary_screening_id",

  aggregated_submissions:
    "client_uuid, sync_status, poe_code, period_start, district_code",

  sync_batches: "client_batch_uuid, status, device_id",
  sync_batch_items: "entity_client_uuid, sync_batch_id, entity_type",
});

/*
 * HOW TO ADD A NEW TABLE IN A FUTURE MIGRATION:
 *
 *   Step 1 — increment APP.SCHEMA_VERSION (e.g. 14 → 15)
 *   Step 2 — add a new block BELOW this comment. NEVER edit the block above.
 *
 *     _db.version(15).stores({
 *       new_table: 'client_uuid, index_a, index_b',
 *       // Tables NOT listed here are untouched — schema is inherited
 *     })
 *
 *   Step 3 — add new table name to STORE + STORE_KEY constants above
 *
 * HOW TO ADD AN INDEX TO AN EXISTING TABLE:
 *
 *     _db.version(15).stores({
 *       primary_screenings:
 *         'client_uuid, sync_status, poe_code, captured_at, captured_by_user_id, referral_created, symptoms_present, new_field',
 *     })
 *     // Re-declare the FULL index string including the new field.
 *     // Dexie adds the missing index; existing data is untouched.
 */

// ─────────────────────────────────────────────────────────────────────────
// ENTERPRISE LIFECYCLE HANDLERS
// ─────────────────────────────────────────────────────────────────────────

_db.on("versionchange", () => {
  _log(
    "WARN",
    "DB versionchange: another tab upgraded schema. " +
      "Closing this connection gracefully. Will reopen on next use. All offline data safe.",
  );
  _db.close();
});

_db.on("blocked", () => {
  _log(
    "WARN",
    "DB upgrade BLOCKED — close all other POE app tabs, then reload. " +
      "Your offline data is safe. No writes are occurring.",
  );
});

// Eager open: DB connection established BEFORE first view renders.
// Eliminates first-operation latency on low-spec Android and prevents
// write-before-open race after rapid Capacitor restart.
_db.open().catch((err) => {
  _log(
    "ERROR",
    `Eager DB open failed: ${err?.message ?? err}. Will retry on first operation.`,
  );
});

// ─────────────────────────────────────────────────────────────────────────
// INTERNAL HELPERS
// ─────────────────────────────────────────────────────────────────────────

function _log(level, msg, data) {
  const styles = {
    INFO: "color:#0066CC;font-weight:600",
    WARN: "color:#E65100;font-weight:600",
    ERROR: "color:#DC3545;font-weight:600",
  };
  const hdr = `%c[POE-DB][${level}] ${new Date().toISOString().slice(11, 23)} — ${msg}`;
  if (data !== undefined) {
    console.groupCollapsed(hdr, styles[level] ?? "");
    console.log(data);
    console.groupEnd();
  } else {
    console.log(hdr, styles[level] ?? "");
  }
}

// ─────────────────────────────────────────────────────────────────────────
// UTILITIES — export everywhere; never redeclare in views
// ─────────────────────────────────────────────────────────────────────────

/** UUID v4. Uses crypto.randomUUID() when available; fallback for old WebViews. */
export function genUUID() {
  if (typeof crypto?.randomUUID === "function") return crypto.randomUUID();
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    return (c === "x" ? r : (r & 0x3) | 0x8).toString(16);
  });
}

/** MySQL-compatible datetime string: "YYYY-MM-DD HH:MM:SS" */
export function isoNow() {
  return new Date().toISOString().replace("T", " ").slice(0, 19);
}

/** Platform string matching SQL ENUM('ANDROID','IOS','WEB'). */
export function getPlatform() {
  const ua = navigator?.userAgent ?? "";
  if (/android/i.test(ua)) return "ANDROID";
  if (/iphone|ipad|ipod/i.test(ua)) return "IOS";
  return "WEB";
}

/**
 * Stable device ID — generated once, persisted in localStorage.
 * Survives app updates and tab restarts. Does NOT survive factory reset.
 */
export function getDeviceId() {
  let id = localStorage.getItem(APP.DEVICE_ID_KEY);
  if (!id) {
    const seg = () => Math.random().toString(36).slice(2, 6).toUpperCase();
    id = `ECSA-${seg()}${seg()}-${seg()}`;
    localStorage.setItem(APP.DEVICE_ID_KEY, id);
  }
  return id;
}

/**
 * Build all mandatory base fields for a new offline record.
 *
 * USAGE:
 *   const auth = JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
 *   const record = createRecordBase(auth, {
 *     gender: 'MALE', symptoms_present: 0, captured_at: isoNow(),
 *   })
 *   await dbPut(STORE.PRIMARY_SCREENINGS, record)
 *
 * DO NOT manually set these fields — createRecordBase handles them:
 *   client_uuid, server_id, server_received_at, idempotency_key,
 *   reference_data_version, country_code, province_code, pheoc_code,
 *   district_code, poe_code, created_by_user_id, created_by_role,
 *   device_id, app_version, platform, sync_status, synced_at,
 *   sync_attempt_count, last_sync_error, sync_note, record_version,
 *   created_at, updated_at
 */
export function createRecordBase(auth, overrides = {}) {
  const now = isoNow();
  return {
    client_uuid: genUUID(),
    server_id: null,
    server_received_at: null,
    idempotency_key: null,
    reference_data_version: APP.REFERENCE_DATA_VER,
    country_code: auth?.country_code ?? null,
    province_code: auth?.province_code ?? null,
    pheoc_code: auth?.pheoc_code ?? null,
    district_code: auth?.district_code ?? null,
    poe_code: auth?.poe_code ?? null,
    created_by_user_id: auth?.id ?? null,
    created_by_role: auth?.role_key ?? null,
    device_id: getDeviceId(),
    app_version: APP.VERSION,
    platform: getPlatform(),
    sync_status: SYNC.UNSYNCED,
    synced_at: null,
    sync_attempt_count: 0,
    last_sync_error: null,
    sync_note: null,
    record_version: 1,
    created_at: now,
    updated_at: now,
    ...overrides,
  };
}

// ─────────────────────────────────────────────────────────────────────────
// SINGLE-RECORD OPERATIONS
// ─────────────────────────────────────────────────────────────────────────

/**
 * Write (insert or fully replace) a record.
 * Brand-new records only. For updates to existing records use safeDbPut().
 */
export async function dbPut(store, record) {
  await _db.table(store).put(record);
}

/**
 * Read a single record by primary key.
 * Returns null if not found — never undefined.
 */
export async function dbGet(store, key) {
  return (await _db.table(store).get(key)) ?? null;
}

/**
 * Delete a record by primary key.
 */
export async function dbDelete(store, key) {
  await _db.table(store).delete(key);
}

/**
 * Existence check without loading the full record.
 * Use this instead of dbGet() when you only need a boolean.
 */
export async function dbExists(store, key) {
  return (await _db.table(store).get(key)) !== undefined;
}

/**
 * Version-guarded write — atomic read-compare-write inside a Dexie 'rw' transaction.
 * Discards the incoming record if stored record_version >= incoming record_version.
 *
 * USE FOR:  all async updates — sync callbacks, status changes, toggles.
 * NEVER FOR: brand-new records — use dbPut() directly for first inserts.
 *
 * WHY THIS IS SAFE IN DEXIE:
 *   The .get() and subsequent .put() run inside db.transaction('rw', ...),
 *   which is a serialised zone in Dexie's zone system. No other write to
 *   the same table can interleave between the read and the write.
 *   This eliminates the "lost update" race condition that raw IDB has no
 *   protection against.
 */
export async function safeDbPut(store, incoming) {
  const pk = STORE_KEY[store];
  const key = incoming[pk];
  const tbl = _db.table(store);

  await _db.transaction("rw", tbl, async () => {
    const stored = await tbl.get(key);
    if (
      stored &&
      (stored.record_version ?? 0) >= (incoming.record_version ?? 0)
    ) {
      _log(
        "WARN",
        `safeDbPut: stale write discarded — ${store} key=${key} ` +
          `stored_v=${stored.record_version} incoming_v=${incoming.record_version}`,
      );
      return;
    }
    await tbl.put(incoming);
  });
}

// ─────────────────────────────────────────────────────────────────────────
// MULTI-RECORD READS
// ─────────────────────────────────────────────────────────────────────────

/** Read ALL records from a store. Avoid on large stores — use dbGetByIndex() instead. */
export async function dbGetAll(store) {
  return _db.table(store).toArray();
}

/**
 * All records matching an index value (exact equality).
 * Only works with indexes declared in the .version() schema chain.
 */
export async function dbGetByIndex(store, indexName, value) {
  return _db.table(store).where(indexName).equals(value).toArray();
}

/**
 * All records where an indexed field falls within a key range.
 * Accepts a standard IDBKeyRange object.
 *
 * @example
 * const today = new Date().toISOString().slice(0, 10)
 * const recs = await dbGetRange(
 *   STORE.PRIMARY_SCREENINGS, 'captured_at',
 *   IDBKeyRange.bound(today + ' 00:00:00', today + ' 23:59:59')
 * )
 */
export async function dbGetRange(store, indexName, range) {
  return _db
    .table(store)
    .where(indexName)
    .between(range.lower, range.upper, !range.lowerOpen, !range.upperOpen)
    .toArray();
}

/**
 * Index filter + in-memory predicate combined.
 * Queries by index first (fast IDB path), then applies predicate in JS.
 *
 * @example
 * const todayUnsynced = await dbQuery(
 *   STORE.PRIMARY_SCREENINGS, 'sync_status', SYNC.UNSYNCED,
 *   r => r.captured_at?.startsWith(today)
 * )
 */
export async function dbQuery(store, indexName, indexValue, predicate) {
  const col = _db.table(store).where(indexName).equals(indexValue);
  if (typeof predicate === "function") return col.filter(predicate).toArray();
  return col.toArray();
}

/**
 * Count records matching an index value without loading any data.
 * Maps to Dexie's .count() → IDBIndex.count() — the fastest IDB operation.
 * Use for sync badge counts and dashboard pill numbers.
 */
export async function dbCountIndex(store, indexName, value) {
  return _db.table(store).where(indexName).equals(value).count();
}

/**
 * Count ALL records in a store without loading any data.
 * Use for dashboard total counters.
 */
export async function dbGetCount(store) {
  return _db.table(store).count();
}

// ─────────────────────────────────────────────────────────────────────────
// MULTI-RECORD WRITES
// ─────────────────────────────────────────────────────────────────────────

/**
 * Write N records in a single Dexie bulkPut operation.
 * Uses IDB's optimised fast path — substantially faster than N individual dbPut calls.
 * Use for batch inserts: symptom inventory, exposure lists, travel countries.
 *
 * On partial failure: Dexie continues writing remaining records and collects
 * errors into a BulkError. For all-or-nothing semantics use dbAtomicWrite().
 */
export async function dbPutBatch(store, records) {
  await _db.table(store).bulkPut(records);
}

/**
 * Delete all records matching an index value.
 * Returns the count of deleted records.
 */
export async function dbDeleteByIndex(store, indexName, value) {
  return _db.table(store).where(indexName).equals(value).delete();
}

/**
 * Atomic replace-all for child tables.
 * Deletes all existing records matching the FK value, then inserts the new array,
 * all within a single Dexie 'rw' transaction.
 *
 * This is the ONLY correct write pattern for secondary child tables.
 * NEVER use N sequential dbPut calls for child tables — use this exclusively.
 *
 * @returns {Promise<{deleted: number, inserted: number}>}
 *
 * @example
 * const { deleted, inserted } = await dbReplaceAll(
 *   STORE.SECONDARY_SYMPTOMS, 'secondary_screening_id', caseClientUuid, freshRecords
 * )
 */
export async function dbReplaceAll(store, indexName, fkValue, newRecords) {
  const tbl = _db.table(store);
  let deleted = 0;

  await _db.transaction("rw", tbl, async () => {
    deleted = await tbl.where(indexName).equals(fkValue).delete();
    await tbl.bulkPut(newRecords);
  });

  return { deleted, inserted: newRecords.length };
}

/**
 * Multi-store atomic write — all records succeed or all fail.
 * No partial state is ever possible.
 *
 * REQUIRED when two or more records in different stores must update together.
 * NEVER use two sequential dbPut/safeDbPut calls across stores when
 * consistency is required — the second write can fail after the first succeeds.
 *
 * @example
 * await dbAtomicWrite([
 *   { store: STORE.PRIMARY_SCREENINGS, record: { ...screening, sync_status: SYNC.SYNCED } },
 *   { store: STORE.NOTIFICATIONS,      record: { ...notif,    sync_status: SYNC.SYNCED } },
 * ])
 */
export async function dbAtomicWrite(writes) {
  const tables = [...new Set(writes.map((w) => _db.table(w.store)))];
  await _db.transaction("rw", tables, async () => {
    await Promise.all(writes.map((w) => _db.table(w.store).put(w.record)));
  });
}
```

---

## OPERATIONS QUICK REFERENCE

### Single-record

```js
await dbPut(STORE.PRIMARY_SCREENINGS, record); // insert/replace (new records)
const rec = await dbGet(STORE.PRIMARY_SCREENINGS, uuid); // read → object | null
await safeDbPut(STORE.PRIMARY_SCREENINGS, updatedRecord); // guarded update (async writes)
await dbDelete(STORE.NOTIFICATIONS, notif.client_uuid); // delete by PK
const exists = await dbExists(STORE.SECONDARY_SCREENINGS, uuid);
```

### Multi-record reads

```js
const all = await dbGetAll(STORE.PRIMARY_SCREENINGS);
const byPoe = await dbGetByIndex(
  STORE.PRIMARY_SCREENINGS,
  "poe_code",
  auth.poe_code,
);
const today = new Date().toISOString().slice(0, 10);
const range = await dbGetRange(
  STORE.PRIMARY_SCREENINGS,
  "captured_at",
  IDBKeyRange.bound(today + " 00:00:00", today + " 23:59:59"),
);
const pending = await dbCountIndex(
  STORE.PRIMARY_SCREENINGS,
  "sync_status",
  SYNC.UNSYNCED,
);
const total = await dbGetCount(STORE.PRIMARY_SCREENINGS);
const filtered = await dbQuery(
  STORE.PRIMARY_SCREENINGS,
  "sync_status",
  SYNC.UNSYNCED,
  (r) => r.captured_at?.startsWith(today),
);
```

### Multi-record writes

```js
await dbPutBatch(STORE.SECONDARY_SYMPTOMS, symptomRecords);
const n = await dbDeleteByIndex(
  STORE.SECONDARY_SYMPTOMS,
  "secondary_screening_id",
  caseUuid,
);
const { deleted, inserted } = await dbReplaceAll(
  STORE.SECONDARY_SYMPTOMS,
  "secondary_screening_id",
  caseUuid,
  freshRecords,
);
await dbAtomicWrite([
  {
    store: STORE.PRIMARY_SCREENINGS,
    record: { ...screening, sync_status: SYNC.SYNCED },
  },
  {
    store: STORE.NOTIFICATIONS,
    record: { ...notif, sync_status: SYNC.SYNCED },
  },
]);
```

---

## CREATING NEW RECORDS — `createRecordBase()`

```js
// Read auth FRESH inside the submit handler — never at module level
const auth = JSON.parse(sessionStorage.getItem("AUTH_DATA") ?? "null") ?? {};
if (!auth?.id || !auth?.is_active) return; // session expired

const screening = createRecordBase(auth, {
  gender: "MALE",
  symptoms_present: 0,
  captured_at: isoNow(),
  referral_created: 0,
  record_status: "COMPLETED",
  void_reason: null,
  deleted_at: null,
});

await dbPut(STORE.PRIMARY_SCREENINGS, screening);
```

---

## AUTH_DATA — HOW TO READ IT

```js
const auth = JSON.parse(sessionStorage.getItem("AUTH_DATA") ?? "null") ?? {};
if (!auth?.id || !auth?.is_active) return;

// Geographic shortcuts
auth.poe_code; // "Bunagana"
auth.district_code; // "Kisoro District"
auth.province_code; // "Kabale RPHEOC"
auth.pheoc_code; // "Kabale RPHEOC"
auth.country_code; // "UG"

// Identity
auth.id; // 3
auth.role_key; // "POE_PRIMARY"
auth.full_name; // "AYEBARE TIMOTHY KAMUKAMA"

// Permissions
auth._permissions.can_do_primary_screening;
auth._permissions.can_do_secondary_screening;
auth._permissions.can_submit_aggregated;
auth._permissions.can_manage_users;
auth._permissions.can_view_all_poe_data;
auth._permissions.can_view_district_data;
auth._permissions.can_view_province_data;
auth._permissions.can_view_national_data;
auth._permissions.can_manage_poes;
auth._permissions.can_acknowledge_alerts;
auth._permissions.can_close_notifications;
```

---

## SYNC ENGINE PATTERN

Every view with a sync engine follows this exact structure — do not deviate from it.

```js
// Module-level — survive component re-renders
const activeSyncKeys = new Set();
let syncTimer = null;

async function syncOne(uuid) {
  // 1. Concurrent guard
  if (activeSyncKeys.has(uuid)) return false;
  activeSyncKeys.add(uuid);

  try {
    // 2. Fresh read — never use a stale snapshot
    const record = await dbGet(STORE.PRIMARY_SCREENINGS, uuid);
    if (!record || record.sync_status === SYNC.SYNCED) return true;

    // 3. Increment attempt BEFORE sending (crash-safe)
    const working = {
      ...record,
      sync_attempt_count: (record.sync_attempt_count || 0) + 1,
      record_version: (record.record_version || 1) + 1,
      updated_at: isoNow(),
    };
    await safeDbPut(STORE.PRIMARY_SCREENINGS, working);

    // 4. Fetch with hard timeout
    const ctrl = new AbortController();
    const tid = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS);
    let res;
    try {
      res = await fetch(`${window.SERVER_URL}/api/primary-screenings`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify(buildPayload(working)),
        signal: ctrl.signal,
      });
    } finally {
      clearTimeout(tid);
    }

    // 5. Success
    if (res.ok) {
      const data = await res.json().catch(() => ({}));
      await safeDbPut(STORE.PRIMARY_SCREENINGS, {
        ...working,
        sync_status: SYNC.SYNCED,
        synced_at: isoNow(),
        server_id: data?.server_id ?? null,
        sync_note: null,
        record_version: (working.record_version || 1) + 1,
        updated_at: isoNow(),
      });
      return true;
    }

    // 6. Server error — retryable vs non-retryable
    const retryable = res.status >= 500 || res.status === 429;
    const errBody = await res.json().catch(() => ({}));
    const errMsg = errBody?.message || `HTTP ${res.status}`;
    await safeDbPut(STORE.PRIMARY_SCREENINGS, {
      ...working,
      sync_status: retryable ? SYNC.UNSYNCED : SYNC.FAILED,
      sync_note: retryable ? "Server busy — retrying." : `Rejected: ${errMsg}`,
      last_sync_error: errMsg,
      record_version: (working.record_version || 1) + 1,
      updated_at: isoNow(),
    });
    if (retryable) scheduleRetry();
    return false;
  } catch (e) {
    // 7. Network / timeout — always retryable, never FAILED
    const latest = await dbGet(STORE.PRIMARY_SCREENINGS, uuid).catch(
      () => null,
    );
    if (latest) {
      await safeDbPut(STORE.PRIMARY_SCREENINGS, {
        ...latest,
        sync_status: SYNC.UNSYNCED,
        sync_note:
          e?.name === "AbortError"
            ? "Timed out — retrying."
            : "Offline — retrying.",
        sync_attempt_count: (latest.sync_attempt_count || 0) + 1,
        record_version: (latest.record_version || 1) + 1,
        updated_at: isoNow(),
      });
    }
    scheduleRetry();
    return false;
  } finally {
    activeSyncKeys.delete(uuid); // ALWAYS release, even on throw
  }
}

function scheduleRetry() {
  clearTimeout(syncTimer);
  syncTimer = setTimeout(() => {
    if (navigator.onLine && hasPending.value) syncAll();
    else if (hasPending.value) scheduleRetry();
  }, APP.SYNC_RETRY_MS);
}

onUnmounted(() => {
  clearTimeout(syncTimer);
  clearInterval(autoTimer);
  window.removeEventListener("online", onOnline);
  window.removeEventListener("offline", onOffline);
});
```

---

## SECONDARY CHILD TABLE PATTERN

```js
function makeChildRecord(caseClientUuid, domainFields) {
  return {
    client_uuid: genUUID(),
    secondary_screening_id: caseClientUuid,
    sync_status: SYNC.UNSYNCED,
    ...domainFields,
  };
}

const symptomRecords = symptoms.map((s) =>
  makeChildRecord(caseUuid, {
    symptom_code: s.code,
    is_present: s.present ? 1 : 0,
    onset_date: s.onsetDate ?? null,
    details: s.details ?? null,
  }),
);

const { deleted, inserted } = await dbReplaceAll(
  STORE.SECONDARY_SYMPTOMS,
  "secondary_screening_id",
  caseUuid,
  symptomRecords,
);

const symptoms = await dbGetByIndex(
  STORE.SECONDARY_SYMPTOMS,
  "secondary_screening_id",
  caseUuid,
);
```

---

## RECORD VERSION RULE

`record_version` increments on **every write** to a record without exception.

```js
// New record → starts at 1 (createRecordBase handles this automatically)

// Every update — always increment
const updated = {
  ...existing,
  case_status: "IN_PROGRESS",
  record_version: (existing.record_version || 1) + 1,
  updated_at: isoNow(),
};
await safeDbPut(STORE.SECONDARY_SCREENINGS, updated);
```

---

## UI SYNC STATUS DISPLAY

```js
// CORRECT — human-readable label
<span>{{ SYNC.LABELS[record.sync_status] }}</span>
// → "Pending" | "Uploaded" | "Queued"

// WRONG — never expose raw enum strings
<span>{{ record.sync_status }}</span>
// → "UNSYNCED" | "SYNCED" | "FAILED"  ← never show these to users
```

---

## DASHBOARD COUNT PATTERN

```js
// CORRECT — uses IDB native count(); loads zero record data
const total = await dbGetCount(STORE.PRIMARY_SCREENINGS);
const pending = await dbCountIndex(
  STORE.PRIMARY_SCREENINGS,
  "sync_status",
  SYNC.UNSYNCED,
);
const alerts = await dbCountIndex(STORE.ALERTS, "status", "OPEN");

// WRONG — loads all records into memory just to count them
const bad = (await dbGetAll(STORE.PRIMARY_SCREENINGS)).length; // NEVER DO THIS
```

---

## ION-ICON RULE

```js
// CORRECT
import { cloudUploadOutline } from 'ionicons/icons'
<IonIcon :icon="cloudUploadOutline" />

// WRONG — string name causes URL error in Capacitor
<IonIcon name="cloud-upload-outline" />
<IonRefresherContent pulling-icon="cloud-upload-outline" />
```

---

## ABSOLUTE PROHIBITIONS

Any code that does any of the following is **rejected without review**:

1. `new Dexie(...)` anywhere outside `poeDB.js`
2. `import Dexie from 'dexie'` in any file except `poeDB.js`
3. `indexedDB.open(...)` anywhere in the codebase
4. `const POE_DB_VERSION = ...` declared anywhere
5. Any inline `function dbPut()`, `function dbGet()`, `function dbGetAll()` in a view
6. `const APP_VER = '0.0.1'` — use `APP.VERSION`
7. `const RETRY_MS = 10_000` — use `APP.SYNC_RETRY_MS`
8. `const SYNC_TIMEOUT = 8_000` — use `APP.SYNC_TIMEOUT_MS`
9. Hard-coded store strings like `'primary_screenings'` — use `STORE.PRIMARY_SCREENINGS`
10. Hard-coded sync status strings like `'UNSYNCED'` — use `SYNC.UNSYNCED`
11. `record.sync_status` rendered raw in the UI — use `SYNC.LABELS[record.sync_status]`
12. Two sequential `dbPut`/`safeDbPut` calls across stores requiring consistency — use `dbAtomicWrite`
13. `dbGetAll(store).length` for counts — use `dbGetCount` or `dbCountIndex`
14. `safeDbPut` for brand-new records — use `dbPut` for first inserts
15. Module-level `auth` cache used for record stamping — re-read inside submit handler
16. Any TypeScript syntax — no `interface`, no `: Type`, no `<T>`, no `EntityTable`
17. `.ts` file extension on any view, service, or utility file

---

## VIEW CHECKLIST — EXECUTE BEFORE WRITING ANY VIEW

```
□ File is .js or .vue — NOT .ts
□ Zero TypeScript syntax — no type annotations anywhere
□ Import from @/services/poeDB — no inline DB declarations
□ Auth read fresh inside submit handler — NOT at module load level
□ New records use createRecordBase(auth, domainFields)
□ record_version incremented on every write including sync callbacks
□ safeDbPut for async updates; dbPut only for brand-new inserts
□ Child table writes use dbReplaceAll — never N sequential dbPut calls
□ Multi-store consistency writes use dbAtomicWrite
□ Sync timeout is APP.SYNC_TIMEOUT_MS — no hardcoded number
□ Retry interval is APP.SYNC_RETRY_MS — no hardcoded number
□ activeSyncKeys.delete(uuid) is in a finally block
□ onUnmounted clears ALL timers and removes ALL event listeners
□ UI sync labels use SYNC.LABELS[status] — never raw values
□ Dashboard counts use dbGetCount / dbCountIndex — never dbGetAll.length
□ IonIcon uses imported SVG objects — never string name attributes
□ No hardcoded store name strings
□ No hardcoded sync status strings
□ No redeclared APP_VER, RETRY_MS, SYNC_TIMEOUT, POE_DB_VERSION
```

---

## OFFLINE DATA LOSS PREVENTION GUARANTEES

| Threat                                    | Guarantee                                                                        | Mechanism                                     |
| ----------------------------------------- | -------------------------------------------------------------------------------- | --------------------------------------------- |
| App killed mid-write                      | Write fully committed or fully rolled back — never partial                       | Dexie transaction atomicity                   |
| Two tabs racing on same record            | Last consistent version wins; no corrupt merge                                   | `safeDbPut` inside Dexie 'rw' transaction     |
| Schema upgraded in another tab            | This tab closes cleanly and reopens at new schema                                | `db.on('versionchange')` handler              |
| App update with new schema                | All previous version blocks applied in sequence on first open                    | Dexie version chain migration                 |
| Android WebView low-memory kill           | IDB storage persists across WebView restart; module re-evals and opens eagerly   | OS-level IDB persistence + eager `_db.open()` |
| Write before DB ready after rapid restart | Impossible — `_db.open()` runs at module load before any view mounts             | Eager open pattern                            |
| Sync server rejects a record              | Record stays UNSYNCED or FAILED with human-readable note; retried on next online | Sync engine retryable/non-retryable branching |
| Network cut mid-sync                      | AbortError caught; record reset to UNSYNCED; retry scheduled                     | `APP.SYNC_TIMEOUT_MS` + catch branch          |

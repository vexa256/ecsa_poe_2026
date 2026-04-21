The POE Digital Screening Tool is an offline-first mobile and web system for rapid primary screening and WHO/IHR-aligned secondary screening at Points of Entry within a national hierarchy (National → Province/PHEOC → District → POE). Built with Ionic Vue + Capacitor using dixie.js for local storage and a Laravel + MySQL backend, it captures ultra-fast primary records (gender and optional temperature) and, when symptoms are detected, automatically creates a traceable notification to trigger secondary screening by designated officers. It also supports a separate aggregated reporting pathway for high-volume situations, with all records working fully offline and syncing later through a simple, reliable manual submission workflow with role-based access and auditability throughout.

# ══════════════════════════════════════════════════════════════════════════════════
# ECSA-HC POE SENTINEL — COMPLETE PROJECT & UI/UX MASTER DOCUMENT v5.0
# THE SINGLE SOURCE OF TRUTH FOR EVERYTHING · SUPERSEDES ALL PRIOR DOCUMENTS
# ══════════════════════════════════════════════════════════════════════════════════
#
# Status:       APPROVED · Production standard
# Framework:    Ionic 8 · Vue 3 Composition API · Capacitor 5+ · Dexie.js 4.3.x
# Backend:      Laravel 10+ · MySQL 8+ · Sanctum Auth (not yet wired)
# Theme:        Command Centre Dual-Tone (Dark header + Light content)
# Target:       Android API 24+ · iOS 15+ · Modern browsers
# Philosophy:   Offline-first · Single-hand · WCAG AA · Enterprise-grade
# Regulatory:   WHO IHR 2005 · ECSA-HC Regional POE Standards
#
# ⚠ THIS IS THE ONLY DOCUMENT ANY DEVELOPER OR AI NEEDS.
#   All prior UI documents, color specs, dev notes, and theming instructions are VOID.
#   In ANY conflict with older files, THIS DOCUMENT IS FINAL.
#   AI: read this COMPLETELY before writing a single line of code.
#
# DOCUMENT MAP:
#   PART A  — Developer Laws & AI Rules (read first, always)
#   PART B  — Complete Color System (112 CSS variables)
#   PART C  — Spacing System (8-point grid)
#   PART D  — Typography (3 font families, 10 scale tokens)
#   PART E  — Component Patterns (11 reusable patterns)
#   PART F  — Modals & Sheets (no content cutting)
#   PART G  — Large Data Handling (virtual scroll, counts)
#   PART H  — Animations & Effects (6 effects)
#   PART I  — Accessibility (WCAG 2.1 AA)
#   PART J  — IonIcon Rules
#   PART K  — Ionic Component Overrides (global CSS)
#   PART L  — Empty & Loading States
#   PART M  — Quick Reference Table
#   PART N  — Technology Stack & Architecture
#   PART O  — The Four Laws (violation = blank page or data loss)
#   PART P  — Geographic Hierarchy & Record Stamping
#   PART Q  — Authentication & Session
#   PART R  — Roles & Permissions (7 role_keys)
#   PART S  — Dexie.js Offline Data Layer (poeDB.js)
#   PART T  — Primary Screening — Complete Business Logic
#   PART U  — Notifications (Referral Queue)
#   PART V  — Secondary Screening — Complete Business Logic
#   PART W  — Alerts (IHR Annex 2 aligned)
#   PART X  — Aggregated Data Submissions
#   PART Y  — Sync Engine Pattern
#   PART Z  — Disease Engine Architecture
#   PART AA — Table Schemas (all 14 IDB stores)
#   PART BB — Bugs Hit & Lessons Learned
#   PART CC — Constants Reference (never hardcode)
#   PART DD — File Architecture & Load Order
#   PART EE — Absolute Prohibitions (code rejected without review)
# ══════════════════════════════════════════════════════════════════════════════════



# ══════════════════════════════════════════════════════════════════════════════════
# PART A — DEVELOPER LAWS & AI RULES (read first, always)
# ══════════════════════════════════════════════════════════════════════════════════

## A.1 THE FOUR LAWS (violation = blank page, silent data loss, or 404)

### LAW 1 — Navigate with server integer `id`. Never with `client_uuid`.

```js
// CORRECT
router.push('/secondary-screening/' + record.id)   // id = MySQL BIGINT

// WRONG — produces 404
router.push('/secondary-screening/' + record.client_uuid)  // UUID string
```
Before `router.push`, assert: `Number.isInteger(Number(id)) && id > 0`. If not, log and abort.

### LAW 2 — No Authorization header. API is fully open by design.

```js
// WRONG — causes silent auth failure
headers: { 'Authorization': 'Bearer ' + token }

// CORRECT
headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
```
Auth middleware is NOT wired in `routes/api.php`. Sending a token fails silently.

### LAW 3 — Every cached IDB record must carry `id` as the server integer.

```js
// CORRECT
await dbPut(STORE.USERS_LOCAL, { client_uuid: idbKey, id: u.id, ...u })

// WRONG — views read .id, not .server_user_id
await dbPut(STORE.USERS_LOCAL, { client_uuid: idbKey, server_user_id: u.id, ...u })
```

### LAW 4 — Router ordering: specific paths before wildcard params.

```js
// CORRECT
{ path: '/secondary-screening/records', ... },         // specific FIRST
{ path: '/secondary-screening/:notificationId', ... }, // wildcard SECOND
```

## A.2 ABSOLUTE CODING RULES

1.  READ THIS ENTIRE DOCUMENT before writing any view.
2.  Every color value MUST come from the CSS variables in Part B. No raw hex in templates.
3.  The theme is DUAL-TONE: dark headers + light content. Content areas are NEVER dark.
4.  Every surface uses a GRADIENT — never a flat solid color. No flat #fff. No flat #000.
5.  IonIcon: ALWAYS import the SVG object. NEVER use string `name` attribute.
6.  TypeScript: NEVER. Pure JS only. No .ts files, no type annotations, no interfaces.
7.  CSS: Scoped `<style scoped>` only. No Tailwind, Bootstrap, or external CSS frameworks.
8.  Vue: Composition API `<script setup>` ONLY. Never Options API.
9.  Modals: MUST be fully scrollable. NO content cutting. See Part F.
10. Lists > 50 items: MUST use virtual scrolling / IonInfiniteScroll. See Part G.
11. Touch targets: ≥ 44×44px on every interactive element.
12. ARIA labels: required on every button, input, and interactive element.
13. Input font size: ≥ 16px always (prevents iOS auto-zoom).
14. Auth: read fresh from sessionStorage inside submit handlers. Never cache at module level.
15. Sync status display: SYNC.LABELS[record.sync_status] always. Never raw enum strings.
16. Dashboard counts: dbCountIndex() / dbGetCount(). Never dbGetAll().length.
17. IonIcon imports: `import { iconName } from 'ionicons/icons'` then `:icon="iconName"`.
18. poeDB.js is the ONLY file allowed to touch Dexie. Never instantiate Dexie in a view.
19. Navigate with server integer `id`. Never `client_uuid` in route params.
20. Every record carries immutable geographic codes: country_code, province_code, pheoc_code, district_code, poe_code.
21. Gender options at primary screening: MALE, FEMALE — only these two.
22. Offline-first: every write goes to IDB first. Network call is fire-and-forget after.
23. onIonViewDidEnter + nextTick: required for post-navigation renders (charts, counts).
24. record_version increments on every write without exception.
25. traveler_direction ENUM('ENTRY','EXIT','TRANSIT') is on primary_screenings.

## A.3 PRE-FLIGHT CHECKLIST (run mentally before writing each view)

```
□ Read this document completely
□ File is .js or .vue — NOT .ts
□ Zero TypeScript syntax — no type annotations anywhere
□ Import from @/services/poeDB — no inline DB declarations
□ Header uses DARK gradient variables (--hdr-*)
□ Content area uses LIGHT gradient variables (--page-*, --card-*)
□ Every surface has a gradient background — nothing flat
□ All colors reference CSS variables — no raw hex
□ Spacing follows 8-point grid (Part C)
□ Typography follows the scale (Part D)
□ Cards use the approved card style (Part E)
□ Modals are full-screen or breakpoints [0,1] with scroll-y (Part F)
□ Lists >50 use IonInfiniteScroll (Part G)
□ IonIcons: imported SVG objects, never string names
□ Touch targets ≥ 44×44px
□ ARIA labels on all interactive elements
□ Inputs ≥ 16px font
□ Auth read fresh inside submit handler from sessionStorage
□ New records use createRecordBase(auth, domainFields)
□ record_version incremented on every write
□ safeDbPut for async updates; dbPut only for brand-new inserts
□ Child table writes use dbReplaceAll — never N sequential dbPut calls
□ Multi-store consistency writes use dbAtomicWrite
□ Sync timeout is APP.SYNC_TIMEOUT_MS — no hardcoded number
□ onIonViewDidEnter + nextTick for post-navigation renders
□ onUnmounted clears ALL timers and removes ALL event listeners
□ <style scoped> on component
```


# ══════════════════════════════════════════════════════════════════════════════════
# PART B — COMPLETE COLOR SYSTEM
# ══════════════════════════════════════════════════════════════════════════════════
#
# ARCHITECTURE:
#   The UI has two zones. Every view has both.
#     DARK ZONE — headers, toolbars, tab bars, side menus
#     LIGHT ZONE — content areas, cards, modals, forms, lists
#
#   The transition between zones uses a gradient strip (dark → light).
#   Accent colors have TWO versions: bright (for dark zone) and rich (for light zone).
#

## B.1 DARK ZONE BACKGROUNDS (headers, toolbars, tab bars, menus)

```css
--hdr-1: #070E1B;           /* Deepest — toolbar top */
--hdr-2: #0E1A2E;           /* Mid — toolbar bottom, tab bar top */
--hdr-3: #142640;           /* Lightest dark — stats strip, hover states on dark */
```

Usage:
```css
/* Standard toolbar */
--background: linear-gradient(180deg, #070E1B 0%, #0E1A2E 100%);

/* Tab bar */
--background: linear-gradient(0deg, #070E1B 0%, #0E1A2E 100%);

/* Stats ribbon within header */
background: linear-gradient(180deg, #0E1A2E 0%, #142640 100%);

/* Side menu */
background: linear-gradient(180deg, #070E1B 0%, #0E1A2E 100%);
```

## B.2 LIGHT ZONE BACKGROUNDS (content, cards, modals, inputs)

```css
/* ── PAGE CONTENT ── */
--page-1: #EAF0FA;          /* Top of content — soft blue-gray */
--page-2: #F2F5FB;          /* Middle — lighter */
--page-3: #E4EBF7;          /* Bottom — slightly deeper */

/* ── CARDS ── */
--card-1: #FFFFFF;           /* Card gradient start — pure white */
--card-2: #F4F7FC;           /* Card gradient end — barely blue */

/* ── INPUTS ── */
--input-1: #E8EDF7;          /* Input gradient start — recessed feel */
--input-2: #F0F3FA;          /* Input gradient end */

/* ── HOVER / FOCUS STATES ── */
--hover-1: #DCE4F3;          /* Hover gradient start */
--hover-2: #E8EEF8;          /* Hover gradient end */

/* ── ACTIVE / SELECTED ── */
--active-1: #D0DCEF;         /* Active gradient start */
--active-2: #E0E8F5;         /* Active gradient end */
```

Usage:
```css
/* Page content */
background: linear-gradient(180deg, #EAF0FA 0%, #F2F5FB 40%, #E4EBF7 100%);

/* Card */
background: linear-gradient(145deg, #FFFFFF 0%, #F4F7FC 100%);

/* Input */
background: linear-gradient(145deg, #E8EDF7 0%, #F0F3FA 100%);

/* Modal content */
background: linear-gradient(180deg, #EEF2FA 0%, #FFFFFF 50%, #F4F7FC 100%);

/* Action sheet */
background: linear-gradient(180deg, #F2F5FB 0%, #FFFFFF 100%);

/* Section alternate band */
background: linear-gradient(180deg, #E4EBF7 0%, #EAF0FA 100%);
```

## B.3 DARK-TO-LIGHT TRANSITION

The gradient strip between header and content:
```css
.transition-strip {
  height: 28px;
  background: linear-gradient(180deg, #0E1A2E 0%, #EAF0FA 100%);
}
```

The full page gradient (applied to the page wrapper or ion-content):
```css
background: linear-gradient(180deg,
  #070E1B 0%,          /* dark header zone */
  #0E1A2E 280px,       /* end of header */
  #EAF0FA 320px,       /* start of light content */
  #F2F5FB 60%,
  #E4EBF7 100%
);
```

## B.4 ACCENT COLORS — BRIGHT (for dark header zone)

These have high luminance for readability on dark backgrounds.
Use ONLY in the dark header/toolbar area.

```css
--bright-blue:   #00B4FF;
--bright-green:  #00E676;
--bright-amber:  #FFB300;
--bright-red:    #FF3D71;
--bright-purple: #B388FF;
--bright-cyan:   #00E5FF;
```

These colors include text-shadow glow for the "neon" effect on dark backgrounds:
```css
color: #00B4FF; text-shadow: 0 0 20px rgba(0,180,255,0.25);
color: #00E676; text-shadow: 0 0 20px rgba(0,230,118,0.25);
color: #FFB300; text-shadow: 0 0 20px rgba(255,179,0,0.25);
```

## B.5 ACCENT COLORS — RICH (for light content zone)

Deeper, richer versions calibrated for high contrast on light backgrounds.
Use ONLY in the content area, cards, modals, forms.

```css
--neon-blue:    #0070E0;    /* Primary actions, links, focus */
--neon-green:   #00A86B;    /* Success, synced, healthy, land POEs */
--neon-amber:   #CC8800;    /* Warning, pending, fever */
--neon-red:     #E02050;    /* Danger, critical, symptomatic */
--neon-purple:  #7B40D8;    /* RPHEOC, special classifications */
--neon-teal:    #008F7A;    /* Info, water POEs */
--neon-pink:    #D63384;    /* Alerts, urgent notices */
--neon-cyan:    #0095CC;    /* Secondary info, links */
```

## B.6 ACCENT SURFACE GRADIENTS (tinted card/badge backgrounds)

Every semantic color has a matching gradient surface for tinted cards and badges:

```css
/* ── Success (green-tinted surfaces) ── */
--surface-success: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
--border-success:  rgba(0, 168, 107, 0.12);

/* ── Warning (amber-tinted surfaces) ── */
--surface-warning: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
--border-warning:  rgba(204, 136, 0, 0.12);

/* ── Danger (red-tinted surfaces) ── */
--surface-danger:  linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%);
--border-danger:   rgba(224, 32, 80, 0.10);

/* ── Info / Teal (cyan-tinted surfaces) ── */
--surface-info:    linear-gradient(135deg, #ECFEFF 0%, #CFFAFE 100%);
--border-info:     rgba(0, 143, 122, 0.12);

/* ── Purple (violet-tinted surfaces) ── */
--surface-purple:  linear-gradient(135deg, #F5F3FF 0%, #EDE9FE 100%);
--border-purple:   rgba(123, 64, 216, 0.12);

/* ── Blue (primary-tinted surfaces) ── */
--surface-blue:    linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
--border-blue:     rgba(0, 112, 224, 0.12);

/* ── Pink (pink-tinted surfaces) ── */
--surface-pink:    linear-gradient(135deg, #FDF2F8 0%, #FCE7F3 100%);
--border-pink:     rgba(214, 51, 132, 0.10);

/* ── Amber (amber icon containers) ── */
--surface-amber:   linear-gradient(135deg, #FFF8E6 0%, #FFEEBB 100%);
--border-amber:    rgba(204, 136, 0, 0.15);
```

Usage — tinted status card:
```css
.status-card--success {
  background: var(--surface-success);
  border: 1.5px solid var(--border-success);
  border-radius: 12px;
}
```

Usage — badge:
```css
.badge--success {
  background: var(--surface-success);
  color: var(--neon-green);
  border: 1px solid var(--border-success);
}
```

## B.7 ICON CONTAINER COLORS (per transport mode / category)

```css
/* Land POE icon container */
background: linear-gradient(135deg, #E6F9EF, #CCEDD8);
border: 1px solid rgba(0, 168, 107, 0.15);
/* icon stroke: var(--neon-green) */

/* Air POE icon container */
background: linear-gradient(135deg, #E0ECFF, #CCE0FF);
border: 1px solid rgba(0, 112, 224, 0.15);
/* icon stroke: var(--neon-blue) */

/* Water POE icon container */
background: linear-gradient(135deg, #E0F7F4, #CCF0EA);
border: 1px solid rgba(0, 143, 122, 0.15);
/* icon stroke: var(--neon-teal) */
```

## B.8 TEXT COLORS

```css
/* ── On LIGHT backgrounds (content zone) ── */
--t-dark:  #0B1A30;         /* Primary — headings, main content, near-black navy */
--t-mid:   #475569;         /* Secondary — metadata, subtext, muted slate */
--t-light: #94A3B8;         /* Tertiary — placeholders, disabled, labels */

/* ── On DARK backgrounds (header zone) ── */
--t-on-dark:   #EDF2FA;     /* Primary on dark — high contrast white-blue */
--t-on-dark-2: #7E92AB;     /* Secondary on dark — muted */
```

IMPORTANT: Never use --t-dark on dark backgrounds. Never use --t-on-dark on light backgrounds.

## B.9 BORDERS

```css
/* ── On LIGHT surfaces ── */
--brd:       rgba(0, 0, 0, 0.06);    /* Subtle — card borders, dividers */
--brd-2:     rgba(0, 0, 0, 0.10);    /* Default — input borders, stronger dividers */
--brd-focus: rgba(0, 112, 224, 0.35); /* Focus ring — inputs, buttons */

/* ── On DARK surfaces ── */
--brd-dark:       rgba(255, 255, 255, 0.06);
--brd-dark-2:     rgba(255, 255, 255, 0.10);
--brd-dark-focus: rgba(0, 180, 255, 0.30);

/* ── Divider lines ── */
--divider:       rgba(0, 0, 0, 0.04);         /* On light */
--divider-glow:  linear-gradient(90deg, transparent, rgba(0,112,224,0.08), transparent);  /* Premium divider */
--divider-dark:  rgba(255, 255, 255, 0.04);    /* On dark */
```

## B.10 SHADOWS (calibrated for light content surfaces)

```css
--sh-card:       0 1px 3px rgba(0,0,0,0.04), 0 4px 20px rgba(0,30,80,0.06);
--sh-card-hover: 0 2px 8px rgba(0,0,0,0.06), 0 8px 30px rgba(0,30,80,0.10);
--sh-elevated:   0 8px 30px rgba(0,30,80,0.10), 0 1px 3px rgba(0,0,0,0.06);
--sh-modal:      0 -4px 30px rgba(0,30,80,0.12), 0 -1px 4px rgba(0,0,0,0.06);
```

## B.11 BUTTON GRADIENTS

```css
/* Primary (blue) */
--btn-primary:  linear-gradient(135deg, #0055CC 0%, #0070E0 50%, #3399FF 100%);
--btn-primary-hover: linear-gradient(135deg, #004AB3 0%, #0060C0 100%);

/* Success (green) */
--btn-success:  linear-gradient(135deg, #007A50 0%, #00A86B 100%);

/* Danger (red) */
--btn-danger:   linear-gradient(135deg, #B01840 0%, #E02050 100%);

/* Ghost (transparent with blue border) */
background: linear-gradient(145deg, var(--card-1), var(--card-2));
border: 1.5px solid rgba(0, 112, 224, 0.25);
color: var(--neon-blue);
```

All buttons have an inner top highlight:
```css
.btn::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent 20%, rgba(255,255,255,0.25) 50%, transparent 80%);
}
```

## B.12 SYNC STATUS COLORS

```css
--sync-pending:  #CC8800;    /* Amber — UNSYNCED */
--sync-uploaded: #00A86B;    /* Green — SYNCED */
--sync-queued:   #D95500;    /* Deep orange — FAILED / retry */
--sync-offline:  #94A3B8;    /* Gray — no connection */
--sync-active:   #0070E0;    /* Blue — sync in progress */
```

Display rule: ALWAYS use `SYNC.LABELS[record.sync_status]` for UI text.
Never show raw "UNSYNCED" / "SYNCED" / "FAILED" strings to users.

## B.13 CHART & VISUALIZATION PALETTE

For Recharts, Chart.js, D3, or inline SVG charts:
```css
--chart-1: #0070E0;   /* Blue */
--chart-2: #00A86B;   /* Green */
--chart-3: #CC8800;   /* Amber */
--chart-4: #E02050;   /* Red */
--chart-5: #7B40D8;   /* Purple */
--chart-6: #008F7A;   /* Teal */
--chart-7: #D63384;   /* Pink */
--chart-8: #D95500;   /* Orange */
```

## B.14 CARD EDGE ACCENT COLORS (left border glow on POE cards)

```css
/* Land POE */  background: #00A86B; box-shadow: 0 0 8px rgba(0,168,107,0.35);
/* Air POE */   background: #0070E0; box-shadow: 0 0 8px rgba(0,112,224,0.35);
/* Water POE */ background: #008F7A; box-shadow: 0 0 8px rgba(0,143,122,0.35);
/* Alert */     background: #E02050; box-shadow: 0 0 8px rgba(224,32,80,0.35);
```

## B.15 STATUS DOT GLOW (live indicators)

```css
.dot-green  { background: #00A86B; box-shadow: 0 0 8px rgba(0,168,107,0.4); }
.dot-amber  { background: #CC8800; box-shadow: 0 0 8px rgba(204,136,0,0.4); }
.dot-red    { background: #E02050; box-shadow: 0 0 8px rgba(224,32,80,0.4); }
.dot-blue   { background: #0070E0; box-shadow: 0 0 8px rgba(0,112,224,0.4); }
.dot-purple { background: #7B40D8; box-shadow: 0 0 8px rgba(123,64,216,0.4); }
.dot-gray   { background: #94A3B8; box-shadow: none; }
```

Red dot pulses to indicate live/critical:
```css
.dot-red { animation: dotPulse 1.5s ease-in-out infinite; }
@keyframes dotPulse {
  0%,100% { box-shadow: 0 0 6px rgba(224,32,80,0.3); }
  50%     { box-shadow: 0 0 14px rgba(224,32,80,0.6); }
}
```


# ══════════════════════════════════════════════════════════════════════════════════
# PART C — SPACING SYSTEM (8-point grid)
# ══════════════════════════════════════════════════════════════════════════════════

```css
--space-1:  4px;       /* Micro — icon-to-label, badge padding */
--space-2:  8px;       /* Tight — chip gaps, inline groups */
--space-3:  12px;      /* Compact — list vertical padding, card gap */
--space-4:  16px;      /* Standard — card padding, form gap, screen edge */
--space-5:  20px;      /* Comfortable — modal internal padding */
--space-6:  24px;      /* Roomy — section separation */
--space-8:  32px;      /* Wide — major block separation */
--space-10: 40px;      /* Extra — page top/bottom */
--space-12: 48px;      /* Maximum — hero sections */
```

Application table:
| Where                  | Value  |
|------------------------|--------|
| Screen edge padding    | 16px + safe-area-inset |
| Card internal padding  | 14–16px |
| Card-to-card gap       | 8–10px |
| Form field gap         | 12–14px |
| Section header→content | 4–8px |
| Section→section        | 14–20px |
| Modal internal padding | 16–20px |
| List item padding      | 12px vertical, 16px horizontal |
| Chip gap               | 6px |
| Badge internal padding | 3px 9px |


# ══════════════════════════════════════════════════════════════════════════════════
# PART D — TYPOGRAPHY
# ══════════════════════════════════════════════════════════════════════════════════

## D.1 FONT STACK

```
Primary:  'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif
Display:  'Syne', 'DM Sans', sans-serif          (KPI numbers, hero values)
Mono:     'JetBrains Mono', 'SF Mono', monospace  (codes, IDs, poe_code)
```

Load in index.html:
```html
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
```

## D.2 TYPE SCALE

| Token              | Size  | Weight | Line-H | Spacing | Font      | Usage                          |
|--------------------|-------|--------|--------|---------|-----------|--------------------------------|
| display            | 28-32px| 800   | 1.1    | -1px    | Syne      | KPI hero numbers               |
| h1                 | 20-22px| 700   | 1.3    | -0.5px  | DM Sans   | Page titles                    |
| h2                 | 17-18px| 600   | 1.35   | -0.25px | DM Sans   | Section headers                |
| h3                 | 14-15px| 600   | 1.4    | 0       | DM Sans   | Card titles, subsections       |
| body               | 13-14px| 400   | 1.5    | 0       | DM Sans   | Main body text                 |
| body-strong        | 13-14px| 600   | 1.5    | 0       | DM Sans   | Emphasized body                |
| small              | 11-12px| 400   | 1.4    | 0.15px  | DM Sans   | Metadata, card subtitles       |
| caption / eyebrow  | 9-10px | 600-700| 1.3   | 0.8-1.2px| DM Sans  | Section labels, badge text     |
| mono               | 13-14px| 500   | 1.4    | 0.3px   | JetBrains | Codes, IDs, poe_code, UUIDs    |
| button             | 13-14px| 600   | 1      | 0.5px   | DM Sans   | Button labels                  |
| input              | 16px   | 400   | 1.5    | 0       | DM Sans   | Form inputs (16px MIN!)        |

## D.3 EYEBROW PATTERN (reused everywhere)

```css
.eyebrow {
  font-size: 9px; font-weight: 700;
  letter-spacing: 1.2px; text-transform: uppercase;
}
/* On dark header: */  color: var(--t-on-dark-2);
/* On light content: */ color: var(--t-light);
```

## D.4 GRADIENT TEXT (header branding only)

```css
.gradient-text {
  background: linear-gradient(90deg, #00E5FF, #00B4FF);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
```

## D.5 TEXT RENDERING

```css
body {
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-rendering: optimizeLegibility;
}
```


# ══════════════════════════════════════════════════════════════════════════════════
# PART E — COMPONENT PATTERNS
# ══════════════════════════════════════════════════════════════════════════════════

## E.1 PAGE STRUCTURE (every view)

```html
<IonPage>
  <IonHeader :translucent="false">
    <IonToolbar style="--background: linear-gradient(180deg, #070E1B, #0E1A2E); --color: #EDF2FA; --border-width: 0;">
      <IonButtons slot="start"><IonBackButton default-href="/dashboard" text="" /></IonButtons>
      <IonTitle>Page Title</IonTitle>
      <IonButtons slot="end"><!-- actions --></IonButtons>
    </IonToolbar>
  </IonHeader>
  <IonContent :fullscreen="true" :scroll-y="true" class="sentinel-content">
    <!-- Content here — LIGHT background, fully scrollable -->
  </IonContent>
</IonPage>
```

```css
ion-content.sentinel-content {
  --background: linear-gradient(180deg, #EAF0FA 0%, #F2F5FB 40%, #E4EBF7 100%);
  --color: #0B1A30;
}
```

## E.2 CARD (light zone — gradient surface)

```css
.sentinel-card {
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px solid rgba(0,0,0,0.06);
  border-radius: 14px;
  padding: 14px 16px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 20px rgba(0,30,80,0.06);
  transition: all 0.25s cubic-bezier(0.16,1,0.3,1);
  position: relative;
  overflow: hidden;
}
/* Top shimmer highlight */
.sentinel-card::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent 20%, rgba(255,255,255,0.8) 50%, transparent 80%);
}
/* Data stream shimmer */
.sentinel-card::after {
  content: ''; position: absolute; top: 0; bottom: 0; width: 40%; left: 0;
  background: linear-gradient(90deg, transparent, rgba(0,112,224,0.02), transparent);
  animation: dataStream 6s ease-in-out infinite; pointer-events: none;
}
@keyframes dataStream { 0%{transform:translateX(-100%)} 100%{transform:translateX(350%)} }
.sentinel-card:active { transform: scale(0.98); }
.sentinel-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06), 0 8px 30px rgba(0,30,80,0.10); }
```

## E.3 KPI / STAT CARD (with colored glow blob)

```css
.kpi-card {
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px solid rgba(0,0,0,0.06);
  border-radius: 16px;
  padding: 18px 16px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 20px rgba(0,30,80,0.06);
  position: relative; overflow: hidden;
}
/* Ambient color blob — change color per KPI */
.kpi-card::before {
  content: ''; position: absolute; top: -10px; right: -10px;
  width: 60px; height: 60px; border-radius: 50%;
  filter: blur(20px); opacity: 0.25;
  background: var(--kpi-glow-color);  /* set per card */
}
.kpi-card__value {
  font-family: 'Syne'; font-size: 30px; font-weight: 800;
  line-height: 1; margin-bottom: 6px;
}
.kpi-card__label {
  font-size: 9px; font-weight: 700;
  letter-spacing: 1.2px; text-transform: uppercase;
  color: #94A3B8;
}
```

## E.4 BUTTON PATTERNS

```css
/* Primary */
.btn-primary {
  background: linear-gradient(135deg, #0055CC, #0070E0, #3399FF);
  color: #fff; border: none; border-radius: 10px;
  padding: 13px 16px; min-height: 48px;
  font-size: 13px; font-weight: 600; letter-spacing: 0.5px;
  box-shadow: 0 4px 16px rgba(0,112,224,0.25);
  position: relative; overflow: hidden;
}

/* Ghost */
.btn-ghost {
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  color: #0070E0; border: 1.5px solid rgba(0,112,224,0.25);
  border-radius: 10px; padding: 13px 16px; min-height: 48px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 20px rgba(0,30,80,0.06);
}

/* Success */
.btn-success {
  background: linear-gradient(135deg, #007A50, #00A86B);
  color: #fff; box-shadow: 0 4px 16px rgba(0,168,107,0.25);
}

/* Danger */
.btn-danger {
  background: linear-gradient(135deg, #B01840, #E02050);
  color: #fff; box-shadow: 0 4px 16px rgba(224,32,80,0.20);
}
```

## E.5 FORM INPUTS

```css
.sentinel-input {
  background: linear-gradient(145deg, #E8EDF7, #F0F3FA);
  border: 1.5px solid rgba(0,0,0,0.08);
  border-radius: 10px; color: #0B1A30;
  font-size: 16px; padding: 13px 16px;
  min-height: 48px; outline: none;
  transition: all 0.25s;
}
.sentinel-input:focus {
  border-color: rgba(0,112,224,0.35);
  box-shadow: 0 0 0 3px rgba(0,112,224,0.08);
}
.sentinel-input::placeholder { color: #94A3B8; }
```

## E.6 BADGES

```css
.badge {
  display: inline-flex; align-items: center; gap: 3px;
  padding: 3px 9px; border-radius: 5px;
  font-size: 9px; font-weight: 700;
  letter-spacing: 0.6px; text-transform: uppercase;
  border: 1px solid;
}
/* Apply matching --surface-* and --border-* and --neon-* per type */
```

## E.7 SEARCH BAR

```css
.sentinel-search {
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px solid rgba(0,0,0,0.06);
  border-radius: 14px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 20px rgba(0,30,80,0.06);
  overflow: hidden; position: relative;
}
.sentinel-search:focus-within {
  border-color: rgba(0,112,224,0.35);
  box-shadow: 0 0 0 3px rgba(0,112,224,0.08), 0 2px 8px rgba(0,0,0,0.06), 0 8px 30px rgba(0,30,80,0.10);
}
/* Include scan-line animation */
```

## E.8 FILTER CHIPS

```css
.chip {
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px solid rgba(0,0,0,0.06);
  border-radius: 22px; padding: 7px 14px;
  color: #475569; font-size: 12px; font-weight: 600;
  box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.chip--active {
  background: linear-gradient(135deg, #E0ECFF, #D0E0FF);
  border-color: rgba(0,112,224,0.3);
  color: #0070E0;
  box-shadow: 0 2px 8px rgba(0,112,224,0.10);
}
```

## E.9 SECTION HEADER

```css
.section-header {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 0 4px;
}
.section-glyph {
  width: 22px; height: 22px; border-radius: 6px;
  background: linear-gradient(135deg, #F0EBFF, #E5DCFF);
  border: 1px solid rgba(123,64,216,0.15);
  display: flex; align-items: center; justify-content: center;
}
```

## E.10 LIST ITEMS

```css
.sentinel-list-item {
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px solid rgba(0,0,0,0.06);
  border-radius: 12px; padding: 12px 16px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.03);
  display: flex; align-items: center; gap: 12px;
  min-height: 56px; transition: all 0.2s;
}
.sentinel-list-item:active { transform: scale(0.99); background: linear-gradient(145deg, #DCE4F3, #E8EEF8); }
```

## E.11 BORDER RADIUS SCALE

```css
--radius-xs:   5px;     /* Small badges */
--radius-sm:   8px;     /* Buttons, inputs */
--radius-md:   12px;    /* Standard cards, list items */
--radius-lg:   14px;    /* Main cards */
--radius-xl:   16px;    /* KPI cards, large surfaces */
--radius-2xl:  20px;    /* Modals */
--radius-full: 9999px;  /* Pills, dots, search bar */
```


# ══════════════════════════════════════════════════════════════════════════════════
# PART F — MODALS & SHEETS (NO CONTENT CUTTING — EVER)
# ══════════════════════════════════════════════════════════════════════════════════

## F.1 THE RULE

Content in modals and action sheets MUST NEVER be cut off.
Users MUST be able to scroll to see every field, every detail, every button.

## F.2 BANNED PATTERNS

```
✗ :initial-breakpoint="0.5"
✗ :initial-breakpoint="0.7"
✗ :initial-breakpoint="0.92"
✗ :breakpoints="[0, 0.5, 0.92]"
✗ Any fixed-height container inside a modal without overflow scroll
✗ max-height on modal content without overflow-y: auto
```

## F.3 REQUIRED PATTERNS

### Full-screen modal (PREFERRED for detail views):
```html
<IonModal :is-open="!!selected" :can-dismiss="true" @ionModalDidDismiss="selected = null">
  <IonHeader>
    <IonToolbar style="--background: linear-gradient(180deg, #070E1B, #0E1A2E); --color: #EDF2FA; --border-width: 0;">
      <IonButtons slot="start">
        <IonButton @click="selected = null" aria-label="Close">
          <IonIcon :icon="closeOutline" />
        </IonButton>
      </IonButtons>
      <IonTitle>Detail Title</IonTitle>
    </IonToolbar>
  </IonHeader>
  <IonContent :scroll-y="true" class="sentinel-content">
    <!-- ALL content — fully scrollable, never clipped -->
    <div style="padding-bottom: env(safe-area-inset-bottom, 24px);" />
  </IonContent>
</IonModal>
```

### Bottom sheet (ONLY for short 2-3 button confirmations):
```html
<IonModal :is-open="show" :initial-breakpoint="1" :breakpoints="[0, 1]" @ionModalDidDismiss="show = false">
  <IonContent :scroll-y="true" class="sentinel-content">
    <div style="padding-bottom: env(safe-area-inset-bottom, 24px);" />
  </IonContent>
</IonModal>
```

### Modal content background:
```css
/* Light gradient — matches content zone */
background: linear-gradient(180deg, #EEF2FA 0%, #FFFFFF 50%, #F4F7FC 100%);
```

### Modal toolbar:
```css
/* Dark gradient — matches header zone */
--background: linear-gradient(180deg, #070E1B 0%, #0E1A2E 100%);
```


# ══════════════════════════════════════════════════════════════════════════════════
# PART G — LARGE DATA HANDLING (enterprise scale)
# ══════════════════════════════════════════════════════════════════════════════════

Any list > 50 items: IonInfiniteScroll, load 50 per page.
Dashboard counts: dbCountIndex() — NEVER dbGetAll().length.
Search: debounce 300ms. Cap displayed results at 100 with "Showing X of Y".
Virtual scroll pattern documented in poeDB rules.


# ══════════════════════════════════════════════════════════════════════════════════
# PART H — ANIMATIONS & EFFECTS
# ══════════════════════════════════════════════════════════════════════════════════

## H.1 ENTRANCE ANIMATION

```css
@keyframes slideUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}
.anim-in { animation: slideUp 0.5s cubic-bezier(0.16,1,0.3,1) both; }
/* Stagger: style="animation-delay: {{ index * 40 }}ms" */
```

## H.2 CARD EFFECTS

Top shimmer highlight (::before) — always present on cards.
Data stream sweep (::after) — 6s infinite animation on interactive cards.

## H.3 SEARCH SCAN LINE

```css
.search::after {
  content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(0,112,224,0.03), transparent);
  animation: scan 5s ease-in-out infinite;
}
@keyframes scan { 0%{left:-50%} 100%{left:150%} }
```

## H.4 LIVE PULSE (status dots)

```css
@keyframes dotPulse {
  0%,100% { box-shadow: 0 0 6px rgba(var(--dot-rgb), 0.3); }
  50%     { box-shadow: 0 0 14px rgba(var(--dot-rgb), 0.6); }
}
```

## H.5 GRID TEXTURE (dark header only)

```css
.header-zone::before {
  background-image:
    linear-gradient(rgba(0,180,255,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,180,255,0.03) 1px, transparent 1px);
  background-size: 36px 36px;
  mask-image: linear-gradient(180deg, black 60%, transparent 100%);
}
```

## H.6 REDUCED MOTION

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
```


# ══════════════════════════════════════════════════════════════════════════════════
# PART I — ACCESSIBILITY (WCAG 2.1 AA)
# ══════════════════════════════════════════════════════════════════════════════════

Touch targets: ≥ 44×44px for all interactive elements.
ARIA: aria-label on every button, input, interactive element. aria-hidden="true" on decorative icons.
Focus: 2px solid var(--neon-blue), offset 2px.
Inputs: ≥ 16px font (prevents iOS auto-zoom).
Contrast verified:
  --t-dark (#0B1A30) on --page-1 (#EAF0FA): ratio ≈ 12:1 ✓
  --t-mid (#475569) on --page-1: ratio ≈ 5.5:1 ✓
  --t-on-dark (#EDF2FA) on --hdr-1 (#070E1B): ratio ≈ 15:1 ✓


# ══════════════════════════════════════════════════════════════════════════════════
# PART J — IONICON RULES
# ══════════════════════════════════════════════════════════════════════════════════

```js
// ✓ CORRECT — always
import { searchOutline, closeOutline, chevronForwardOutline } from 'ionicons/icons'
<IonIcon :icon="searchOutline" />

// ✗ WRONG — causes URL error in Capacitor, ALWAYS REJECTED
<IonIcon name="search-outline" />
```

For custom icons not in Ionicons, use raw inline SVG:
```html
<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"
     stroke-linecap="round" aria-hidden="true">...</svg>
```


# ══════════════════════════════════════════════════════════════════════════════════
# PART K — IONIC COMPONENT OVERRIDES (global CSS)
# ══════════════════════════════════════════════════════════════════════════════════

Place in global variables.css or theme file:

```css
/* Content — LIGHT */
ion-content {
  --background: linear-gradient(180deg, #EAF0FA 0%, #F2F5FB 40%, #E4EBF7 100%);
  --color: #0B1A30;
}

/* Toolbar — DARK */
ion-toolbar {
  --background: linear-gradient(180deg, #070E1B 0%, #0E1A2E 100%);
  --color: #EDF2FA;
  --border-width: 0;
}

/* Card — LIGHT gradient */
ion-card {
  --background: linear-gradient(145deg, #FFFFFF 0%, #F4F7FC 100%);
  --color: #0B1A30;
  border: 1.5px solid rgba(0,0,0,0.06);
  border-radius: 14px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 20px rgba(0,30,80,0.06);
}

/* Item — transparent */
ion-item { --background: transparent; --color: #0B1A30; --border-color: rgba(0,0,0,0.04); }

/* Input — LIGHT recessed gradient */
ion-input, ion-textarea {
  --background: linear-gradient(145deg, #E8EDF7, #F0F3FA);
  --color: #0B1A30; --placeholder-color: #94A3B8;
}

/* Segment */
ion-segment { --background: rgba(0,0,0,0.04); border-radius: 10px; }
ion-segment-button { --background-checked: #0070E0; --color: #475569; --color-checked: #fff; }

/* Tab bar — DARK */
ion-tab-bar { --background: linear-gradient(0deg, #070E1B, #0E1A2E); --border: none; --color: #4A5874; --color-selected: #00B4FF; }

/* Modal — LIGHT */
ion-modal { --background: linear-gradient(180deg, #EEF2FA 0%, #FFFFFF 50%, #F4F7FC 100%); --border-radius: 20px 20px 0 0; }

/* Loading — LIGHT */
ion-loading { --background: linear-gradient(145deg, #FFFFFF, #F4F7FC); --color: #0B1A30; --spinner-color: #0070E0; }

/* Refresher */
ion-refresher { --color: #0070E0; }

/* Scrollbar */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.08); border-radius: 4px; }
```


# ══════════════════════════════════════════════════════════════════════════════════
# PART L — EMPTY & LOADING STATES
# ══════════════════════════════════════════════════════════════════════════════════

Empty: centered SVG icon (stroke-only, --t-light), title (--t-mid), subtitle (--t-light), optional ghost button.

Loading skeleton shimmer:
```css
.skeleton { height: 14px; border-radius: 6px;
  background: linear-gradient(90deg, #E4EBF7 25%, #F2F5FB 50%, #E4EBF7 75%);
  background-size: 200% 100%; animation: shimmer 1.5s infinite;
}
@keyframes shimmer { 0%{background-position:-200% 0} 100%{background-position:200% 0} }
```


# ══════════════════════════════════════════════════════════════════════════════════
# PART M — QUICK REFERENCE
# ══════════════════════════════════════════════════════════════════════════════════

| What                     | Do This                                                   | Never This                       |
|--------------------------|-----------------------------------------------------------|----------------------------------|
| Header/toolbar bg        | linear-gradient(180deg, #070E1B, #0E1A2E)                | Light bg, flat color             |
| Content/page bg          | linear-gradient(180deg, #EAF0FA, #F2F5FB, #E4EBF7)      | Dark bg, flat #fff, flat #000    |
| Card bg                  | linear-gradient(145deg, #FFFFFF, #F4F7FC) + shadow       | Flat white, dark card            |
| Modal bg                 | Light gradient + IonContent scroll-y="true"              | Dark bg, breakpoint<1, no scroll |
| Text on content          | --t-dark (#0B1A30) / --t-mid (#475569)                   | #000, white text on light        |
| Text on header           | --t-on-dark (#EDF2FA)                                    | Dark text on dark bg             |
| Accent on content        | --neon-* colors (#0070E0, #00A86B, etc.)                 | --bright-* (too light for white) |
| Accent on header         | --bright-* colors (#00B4FF, #00E676, etc.) + text-shadow | --neon-* (too dark for dark bg)  |
| Badge / tinted surface   | --surface-* gradient + --border-* + --neon-*             | Flat rgba background             |
| Button                   | Gradient + inner top highlight + box-shadow              | Flat solid color                 |
| Input                    | Gradient (--input-1 → --input-2) + focus glow            | Flat white input                 |
| IonIcon                  | import SVG, :icon="x"                                    | name="x" string                  |
| Sync label               | SYNC.LABELS[status]                                      | Raw UNSYNCED/SYNCED/FAILED       |
| Counts                   | dbCountIndex() / dbGetCount()                            | dbGetAll().length                |
| Large list               | IonInfiniteScroll 50/page                                | Render all items                 |
| Touch target             | ≥ 44×44px                                                | Smaller                          |
| TypeScript               | NEVER                                                    | .ts, interface, : Type           |


# ══════════════════════════════════════════════════════════════════════════════════
# END OF DOCUMENT
# ══════════════════════════════════════════════════════════════════════════════════
#
# VERSION:    4.0
# DATE:       2026-04-17
# STATUS:     APPROVED — This is the production design standard
# SUPERSEDES: All prior UI documents including:
#             - UI_MANDATORY_REQUIREMENTS.txt (v1.0, Feb 2026)
#             - SENTINEL_UI_DESIGN_SYSTEM_v2.txt
#             - SENTINEL_UI_DESIGN_SYSTEM_v2.1.txt
#
# THEME SUMMARY:
#   DARK ZONE (headers, toolbars, tabs) → Deep navy gradients + neon bright accents + grid texture
#   LIGHT ZONE (content, cards, modals) → Soft luminous gradients + rich accent colors + glass effects
#   TRANSITION → Smooth dark-to-light gradient strip between zones
#   EFFECTS → Card shimmer, data stream sweep, scan lines, pulse dots, stagger animations
#   SURFACES → Every surface is a gradient. Nothing flat. Nothing plain. Everything premium.
#
# ══════════════════════════════════════════════════════════════════════════════════


# ══════════════════════════════════════════════════════════════════════════════════
# PART N — TECHNOLOGY STACK & ARCHITECTURE
# ══════════════════════════════════════════════════════════════════════════════════

## N.1 SYSTEM PURPOSE

This system implements Article 23 of the International Health Regulations (IHR)
2005, which grants States Parties the authority to require health measures for
travelers at Points of Entry. Every data field, every workflow decision, and
every alert routing rule has a direct IHR or WHO operational justification.

IHR obligations implemented:
- Annex 1B Capacity: POE must have capacity to apply health measures (primary screening).
- Article 23(1)(a): Least invasive medical examination for the public health objective.
- Article 44: Collaborate in detection and response to PHEIC.
- Annex 2: Decision instrument for PHEIC notification — implemented as alert generation rules.

## N.2 STACK

Frontend:
  Ionic 8 + Vue 3 (Composition API only, <script setup>)
  Capacitor 5+ (Android API 24+, iOS 15+)
  Dexie.js 4.3.x (IndexedDB wrapper — offline-first)
  Pure JavaScript — NO TypeScript anywhere
  NO external CSS frameworks (Tailwind, Bootstrap) — scoped CSS only

Backend:
  Laravel 10+ with Sanctum (token auth — NOT YET WIRED in routes)
  MySQL 8+ (InnoDB, utf8mb4)
  PHP 8.1+

Reference Data:
  Hardcoded JS files in the mobile app: POEs.js, Diseases.js, exposures.js
  Never fetched from server at runtime — app works with zero network from first launch
  reference_data_version (e.g. 'rda-2026-02-01') stamped on every record

## N.3 OFFLINE-FIRST ARCHITECTURE

Every write goes to IndexedDB (Dexie) FIRST. Network call is fire-and-forget AFTER.
The UI never blocks on a network request for data capture.
Sync is manual (user taps "Upload") or scheduled retry on failure.
All reference data (POEs, diseases, symptoms, exposures) lives in hardcoded JS files.
The server stores geographic codes as-received and does NOT validate against a master table.
Records created on an older app version still sync — server never rejects old reference_data_version.


# ══════════════════════════════════════════════════════════════════════════════════
# PART O — THE FOUR LAWS — EXPANDED (every rule learned from a real bug)
# ══════════════════════════════════════════════════════════════════════════════════

## O.1 LAW 1 — Navigate with server integer `id`. Always. No exceptions.

`router.push('/SomeView/' + record.id)` where `id` is the MySQL auto-increment integer.

NEVER navigate with:
- `client_uuid` — this is the IndexedDB primary key, NOT a server identifier
- `server_user_id`, `server_id`, or any alias — normalise to `id` before navigating
- Any UUID string — the Laravel controller receives `{id}` as `int`, a UUID produces 404

Why this killed us: The list view fell back to `client_uuid` when `id` was undefined on
stale cache records. The detail view received a UUID in `route.params.id`, sent
`GET /users/fafce657-...` to the server, got 404, showed a blank page. Three hours debugging.

When reading from cache, always normalise:
```js
allItems.value = rawRecords.map(r => ({
  ...r,
  id: r.id ?? r.server_user_id ?? r.server_id ?? extractIntFromKey(r.client_uuid)
}))
```

## O.2 LAW 2 — API has no auth middleware. Send no token. Ever.

All current controllers are completely open by design. Auth middleware will be added later.
NEVER add `Authorization: Bearer` headers to any fetch until auth middleware is explicitly
wired in `routes/api.php`.

Check `routes/api.php` before adding any auth header. If you don't see
`->middleware('auth:sanctum')` on the route, send no Authorization header.

## O.3 LAW 3 — Cache records must always carry `id` as the server integer.

Every record written to IndexedDB by `cacheUsersLocally()` or any equivalent function
MUST write the server integer as `id` on the record object — not just as `server_id`
or `server_user_id` or any other alias.

`id` is sacred. It is the server integer. It lives on every cached record. It is the
only value that goes into route params.

## O.4 LAW 4 — Router ordering: specific paths before wildcard params.

Same applies to `/alerts/summary` before `/alerts/{id}` in Laravel `api.php`.

## O.5 SUMMARY TABLE

| What you want to do     | Correct field          | Wrong field                 |
|--------------------------|------------------------|-----------------------------|
| Navigate to detail view  | `record.id` (integer)  | `record.client_uuid` (UUID) |
| Call `GET /entity/{id}`  | `record.id` (integer)  | any UUID or alias           |
| IDB primary key lookup   | `record.client_uuid`   | `record.id`                 |
| Auth header on fetch     | **None — API is open** | `Authorization: Bearer ...` |



# ══════════════════════════════════════════════════════════════════════════════════
# PART P — GEOGRAPHIC HIERARCHY & RECORD STAMPING
# ══════════════════════════════════════════════════════════════════════════════════

## P.1 THE HIERARCHY

```
NATIONAL
│
├── PROVINCE / RPHEOC (Regional Public Health Emergency Operations Centre)
│   │
│   └── DISTRICT
│       │
│       └── POE (Point of Entry)
```

Every record carries: country_code, province_code, pheoc_code, district_code, poe_code.
These come from `auth.assignment` at creation time. They are IMMUTABLE after write.
Server enforces geographic scope on every read — a POE officer cannot see another POE's data.

## P.2 IMMUTABLE FIELDS — NEVER UPDATE AFTER CREATION

client_uuid, created_at, created_by_user_id, captured_at, poe_code, district_code,
country_code, reference_data_version, device_id, primary_screening_id (on notifications
and secondary_screenings), notification_id (on secondary_screenings).

## P.3 GEOGRAPHIC SCOPE ENFORCEMENT ON API

POE-level roles:          WHERE poe_code = auth.poe_code
DISTRICT_SUPERVISOR:      WHERE district_code = auth.district_code
PHEOC_OFFICER:            WHERE pheoc_code = auth.pheoc_code
NATIONAL_ADMIN:           WHERE country_code = auth.country_code

The server never relies on the client to send the correct scope filter.

## P.4 REFERENCE DATA — HARDCODED IN APP

POEs are defined in: src/data/POEs.js (window.POE_MAIN)
61 Uganda POEs, 11 RPHEOCs, 33 districts. Uganda-only (Rwanda removed).
POE codes are human-readable strings (e.g. 'Bunagana', 'Katuna Cluster').
poe_code = poe_name on every entry.
Changing a POE code in POEs.js is a BREAKING CHANGE — old records on the
server will have the old code and cannot be automatically rematched.

## P.5 RPHEOC MAPPING (Uganda, from MoH National Guidelines May 2023)

9 established RPHEOCs: Mbale, Kampala Metro, West Nile/Arua, Fort Portal,
Masaka, Hoima, Mubende, Moroto, Lira. 14 total planned.

| RPHEOC              | POE Districts in this app                                  |
|---------------------|------------------------------------------------------------|
| Gulu RPHEOC         | Amuru, Lamwo, Kitgum                                       |
| Arua RPHEOC         | Koboko, Moyo, Nebbi, Zombo, Arua                           |
| Mbale RPHEOC        | Busia, Manafwa, Tororo, Bukwo                               |
| Kabale RPHEOC       | Kabale, Kisoro, Ntungamo, Isingiro                          |
| Fort Portal RPHEOC  | Kasese, Bundibugyo, Ntoroko, Kanungu, Rubirizi              |
| Hoima RPHEOC        | Buliisa, Hoima, Kikuube                                     |
| Kampala RPHEOC      | Kampala                                                     |
| Masaka RPHEOC       | Rakai, Masaka                                               |
| Jinja RPHEOC        | Jinja, Namayingo                                            |
| Moroto RPHEOC       | Kaabong, Amudat, Moroto                                     |
| National PHEOC      | Wakiso (Entebbe International Airport)                      |

6 operational OSBPs: Katuna, Mutukula, Busia, Malaba, Mirama Hills, Elegu.


# ══════════════════════════════════════════════════════════════════════════════════
# PART Q — AUTHENTICATION & SESSION
# ══════════════════════════════════════════════════════════════════════════════════

## Q.1 LOGIN FLOW

1. User enters username + password on LoginView.
2. App POSTs to POST /api/auth/login { username, password }
3. Server validates credentials (bcrypt on users.password column).
4. Server checks is_active = 1.
5. Server queries user_assignments for primary active assignment.
6. Server derives _permissions from role_key.
7. Server returns AUTH_DATA JSON + Sanctum token.
8. App stores AUTH_DATA in sessionStorage under key 'AUTH_DATA'.
9. App stores Sanctum token in localStorage (persists across restarts).
10. App.vue transitions to authenticated shell.

## Q.2 AUTH_DATA STRUCTURE (returned by server)

```js
{
  token: '<sanctum_token>',
  user: {
    id, role_key, country_code, full_name, username, email, phone,
    is_active, last_login_at, created_at, updated_at,
    assignment: { ...primary assignment row },
    all_assignments: [ ...all active assignments ],
    poe_code, district_code, province_code, pheoc_code,  // pre-flattened
    _permissions: {
      can_do_primary_screening, can_do_secondary_screening,
      can_submit_aggregated, can_manage_users, can_view_all_poe_data,
      can_view_district_data, can_view_province_data, can_view_national_data,
      can_manage_poes, can_acknowledge_alerts, can_close_notifications
    },
    _logged_in_at: '<ISO timestamp>'
  }
}
```

## Q.3 SESSION RULES

- AUTH_DATA is in sessionStorage — cleared when browser/WebView tab closes.
- Check sessionStorage on every write. If AUTH_DATA is null or auth.is_active false, abort.
- Sanctum token has no automatic expiry. Revocation only on logout or admin deactivation.
- On logout: POST /api/auth/logout, clear sessionStorage + localStorage token.

## Q.4 HOW TO READ AUTH IN VIEWS

```js
// ALWAYS read fresh inside submit handler — NEVER at module level
const auth = JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
if (!auth?.id || !auth?.is_active) return  // session expired

auth.poe_code       // 'Bunagana'
auth.district_code  // 'Kisoro District'
auth.province_code  // 'Kabale RPHEOC'
auth.pheoc_code     // 'Kabale RPHEOC'
auth.country_code   // 'UG'
auth.id             // 3  (server integer)
auth.role_key       // 'POE_PRIMARY'
auth._permissions.can_do_primary_screening  // true/false
```


# ══════════════════════════════════════════════════════════════════════════════════
# PART R — ROLES & PERMISSIONS (7 role_keys)
# ══════════════════════════════════════════════════════════════════════════════════

## R.1 THE SEVEN ROLES

POE_PRIMARY — Operates primary screening only. Sees only own POE's primary records.
POE_SECONDARY — Operates secondary screening only. Opens cases from referral queue.
POE_DATA_OFFICER — Manages aggregated data. Read access to primary/secondary counts.
POE_ADMIN — All of above at their POE + user management at POE level.
DISTRICT_SUPERVISOR — Read-only across all POEs in district. Acknowledges DISTRICT alerts.
PHEOC_OFFICER — Monitors all districts/POEs in PHEOC. Acknowledges PHEOC alerts.
NATIONAL_ADMIN — Full system access. All users, all alerts, all data country-wide.

## R.2 ASSIGNMENT PATTERNS

POE-level roles: country_code + province_code + pheoc_code + district_code + poe_code
DISTRICT_SUPERVISOR: country_code + province_code + pheoc_code + district_code + poe_code=NULL
PHEOC_OFFICER: country_code + province_code + pheoc_code + district_code=NULL + poe_code=NULL
NATIONAL_ADMIN: country_code only, everything else NULL

## R.3 ASSIGNMENT RULES

- A user can have multiple assignment rows but exactly one must be is_primary=1, is_active=1.
- A user without an active assignment cannot log in.
- If ends_at is not null and is in the past, assignment is inactive.
- POE_ADMIN can only manage users within their own POE.



# ══════════════════════════════════════════════════════════════════════════════════
# PART S — DEXIE.JS OFFLINE DATA LAYER (poeDB.js)
# ══════════════════════════════════════════════════════════════════════════════════

## S.1 PRIME DIRECTIVE

`src/services/poeDB.js` is the ONLY file that may instantiate Dexie or touch IndexedDB.
Every view imports exclusively from `@/services/poeDB`.

BANNED in every view (zero exceptions):
```
new Dexie(...)           import Dexie from 'dexie'     indexedDB.open(...)
window.indexedDB         function dbPut() {}            function dbGet() {}
const APP_VER = '0.0.1'  const RETRY_MS = 10_000       const SYNC_TIMEOUT = 8_000
```

## S.2 MANDATORY IMPORT BLOCK FOR VIEWS

```js
import {
  dbPut, dbGet, dbGetAll, safeDbPut, dbDelete, dbExists,
  dbGetByIndex, dbGetRange, dbGetCount, dbCountIndex,
  dbPutBatch, dbDeleteByIndex, dbReplaceAll, dbAtomicWrite, dbQuery,
  genUUID, isoNow, getPlatform, getDeviceId, createRecordBase,
  STORE, STORE_KEY, SYNC, APP
} from '@/services/poeDB'
```

## S.3 OPERATIONS REFERENCE

Single-record:
  dbPut(store, record)        — new records only (first insert)
  dbGet(store, key)           — read by PK, returns null if not found
  safeDbPut(store, record)    — version-guarded update (async writes, sync callbacks)
  dbDelete(store, key)        — delete by PK
  dbExists(store, key)        — boolean check without loading full record

Multi-record reads:
  dbGetAll(store)             — all records (avoid on large stores)
  dbGetByIndex(store, idx, v) — exact equality on index
  dbGetRange(store, idx, range) — IDBKeyRange query
  dbQuery(store, idx, v, fn)  — index filter + in-memory predicate
  dbCountIndex(store, idx, v) — O(1) count by index (for badges/dashboards)
  dbGetCount(store)           — O(1) total count

Multi-record writes:
  dbPutBatch(store, records)    — bulk insert via IDB fast path
  dbDeleteByIndex(store, idx, v) — delete all matching index
  dbReplaceAll(store, idx, fk, records) — atomic replace-all for child tables
  dbAtomicWrite([{store, record}...])   — multi-store atomic write (all or nothing)

## S.4 KEY RULES

- dbPut → new records ONLY. safeDbPut → any async update (version-guarded).
- dbAtomicWrite → any write that spans two stores (primary + notification).
- dbCountIndex → dashboard counts (O(1)). NEVER dbGetAll().length.
- dbReplaceAll → secondary child tables (symptoms, exposures, actions).
- record_version increments on EVERY write without exception.
- createRecordBase(auth, domainFields) builds all mandatory base fields.

## S.5 14 IDB STORES

users_local, primary_screenings, notifications, secondary_screenings,
secondary_symptoms, secondary_exposures, secondary_actions, secondary_samples,
secondary_travel_countries, secondary_suspected_diseases, alerts,
aggregated_submissions, sync_batches, sync_batch_items.

## S.6 STORE_KEY (primary key field per store)

Most stores: client_uuid.
sync_batches: client_batch_uuid.
sync_batch_items: entity_client_uuid.

## S.7 CREATING NEW RECORDS

```js
const auth = JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
if (!auth?.id || !auth?.is_active) return

const screening = createRecordBase(auth, {
  gender: 'MALE', symptoms_present: 0, captured_at: isoNow(),
  traveler_direction: 'ENTRY', referral_created: 0, record_status: 'COMPLETED',
})
await dbPut(STORE.PRIMARY_SCREENINGS, screening)
```


# ══════════════════════════════════════════════════════════════════════════════════
# PART T — PRIMARY SCREENING — COMPLETE BUSINESS LOGIC
# ══════════════════════════════════════════════════════════════════════════════════

## T.1 PURPOSE

Ultra-fast primary triage at POE: gender + optional temperature + symptoms yes/no.
The officer visually assesses whether the traveler presents illness symptoms.
When symptoms detected, auto-creates a notification to trigger secondary screening.

## T.2 REQUIRED FIELDS

gender: MALE or FEMALE (two options only).
symptoms_present: binary YES/NO (two large buttons, full-width, high contrast).
traveler_direction: ENTRY, EXIT, or TRANSIT (added per migration).

## T.3 OPTIONAL FIELDS

traveler_full_name: VARCHAR(150), officer may capture if traveler volunteers.
temperature_value + temperature_unit: if thermometer reading taken.
  Rule: if temperature_value is provided, temperature_unit is required.
  Celsius valid range: 25.0-45.0 (warn outside 35.0-42.0).
  Fahrenheit valid range: 77.0-113.0 (warn outside 95.0-107.6).

## T.4 WORKFLOW (step by step)

PRECONDITIONS:
- AUTH_DATA in sessionStorage, auth.is_active = true
- auth._permissions.can_do_primary_screening = true
- auth.poe_code not null

STEP 1: Officer opens primary screening view.
STEP 2: Officer captures gender, symptoms_present, optional temp/name.
STEP 3: Officer presses SAVE.
  a. Read auth FRESH from sessionStorage.
  b. Validate all required fields.
  c. createRecordBase(auth, domainFields).
  d. dbPut(STORE.PRIMARY_SCREENINGS, record).
  e. Increment day counter in localStorage.
  f. Evaluate: symptoms_present === 1 ?

STEP 4A — SYMPTOMS ABSENT (symptoms_present = 0):
  referral_created = 0. No notification created.
  UI resets to next traveler in under 2 seconds.

STEP 4B — SYMPTOMS PRESENT (symptoms_present = 1):
  Auto-create notification record atomically via dbAtomicWrite:
    notification_type: 'SECONDARY_REFERRAL'
    status: 'OPEN'
    reason_code: 'PRIMARY_SYMPTOMS_DETECTED'
    priority: CRITICAL (temp ≥ 38.5°C), HIGH (temp ≥ 37.5°C), NORMAL (default)
    assigned_role_key: 'POE_SECONDARY'
  referral_created = 1 on the primary screening record.
  BOTH records MUST be written in a single dbAtomicWrite() call.

## T.5 PRIORITY DETERMINATION

CRITICAL: temperature_value ≥ 38.5°C (101.3°F) AND symptoms_present = 1
HIGH:     temperature_value ≥ 37.5°C (99.5°F) AND symptoms_present = 1
NORMAL:   symptoms_present = 1 without elevated temperature (or no temp taken)

## T.6 VOID RULES

- Officer who created it: within 24 hours.
- POE_ADMIN at same POE: any time.
- NATIONAL_ADMIN: any time.
- void_reason required (min 10 chars).
- Linked notification auto-set to CLOSED.
- Voided records excluded from aggregated counts.
- referral_created is NOT reset (audit field).

## T.7 DAY COUNTER

localStorage keys: APP.DAY_COUNT_DAY_KEY ('poe_ps_day'), APP.DAY_COUNT_CNT_KEY ('poe_ps_cnt').
Resets daily. Device-local and approximate.


# ══════════════════════════════════════════════════════════════════════════════════
# PART U — NOTIFICATIONS (REFERRAL QUEUE)
# ══════════════════════════════════════════════════════════════════════════════════

## U.1 STATUS MACHINE

OPEN → IN_PROGRESS:    Secondary officer opens the case.
IN_PROGRESS → CLOSED:  Secondary case reaches DISPOSITIONED or CLOSED.
OPEN → CLOSED:         Primary voided, duplicate, or POE_ADMIN manual close.
CLOSED → any:          INVALID (permanently terminal).
IN_PROGRESS → OPEN:    INVALID (regression not allowed).

## U.2 BUSINESS RULES

- One notification per symptomatic primary screening. Check before creating.
- notification_type = 'SECONDARY_REFERRAL' for auto-generated referrals.
- reason_code = 'PRIMARY_SYMPTOMS_DETECTED' for auto-generated referrals.
- assigned_role_key = 'POE_SECONDARY'.
- Priority is NOT retroactive — set at moment of referral creation.
- Must sync together with parent primary screening in same batch.


# ══════════════════════════════════════════════════════════════════════════════════
# PART V — SECONDARY SCREENING — COMPLETE BUSINESS LOGIC
# ══════════════════════════════════════════════════════════════════════════════════

## V.1 CASE OPENING RULES

- auth._permissions.can_do_secondary_screening must be true.
- Notification must be OPEN.
- Check: dbGetByIndex for existing case with same notification_id → open it, don't dupe.
- Case and notification transition atomically via dbAtomicWrite.

## V.2 CASE STATUS MACHINE

OPEN → IN_PROGRESS:       Officer starts data entry.
IN_PROGRESS → DISPOSITIONED: Officer completes assessment + final_disposition.
DISPOSITIONED → CLOSED:   All post-disposition actions complete.
IN_PROGRESS → CLOSED:     False positive — officer_notes required, disposition = RELEASED.
CLOSED → any:             INVALID (permanently terminal).
OPEN → DISPOSITIONED:     INVALID (must pass through IN_PROGRESS).

## V.3 SYNDROME CLASSIFICATION CODES

ILI, SARI, AWD, BLOODY_DIARRHEA, VHF, RASH_FEVER, JAUNDICE, NEUROLOGICAL,
MENINGITIS, OTHER, NONE.

## V.4 DISPOSITION CODES

RELEASED, DELAYED, QUARANTINED, ISOLATED, REFERRED, TRANSFERRED,
DENIED_BOARDING, OTHER.

## V.5 SECONDARY CHILD TABLES

secondary_symptoms:            symptom_code, is_present (1/0), onset_date, details
secondary_exposures:           exposure_code, response (YES/NO/UNKNOWN), details
secondary_actions:             action_code, is_done (1/0), details
secondary_samples:             sample_type, sample_identifier, lab_destination, collected_at
secondary_travel_countries:    country_code, travel_role (VISITED/TRANSIT), dates
secondary_suspected_diseases:  disease_code, rank_order, confidence, reasoning

All child tables use dbReplaceAll pattern — NEVER N sequential dbPut calls.

## V.6 DISPOSITION VALIDATIONS (all must pass before DISPOSITIONED)

- syndrome_classification required.
- risk_level required (LOW/MEDIUM/HIGH/CRITICAL).
- final_disposition required.
- At least one action with is_done = 1.
- If risk HIGH/CRITICAL: ISOLATED=1 or REFERRED_HOSPITAL=1 required.
- If emergency_signs_present=1: triage_category must be EMERGENCY.

## V.7 VITAL SIGN RANGES (advisory, not blocking)

Temperature (°C): <35 hypothermia, 35-37.4 normal, 37.5-37.9 low-grade, 38-38.9 fever, ≥39 high, ≥40 dangerous.
Pulse: <40 critical low, 40-59 bradycardia, 60-100 normal, 101-149 tachycardia, ≥150 severe.
Respiratory: <10 abnormal low, 10-20 normal, 21-29 elevated, ≥30 severe.
SpO2: ≥95 normal, 90-94 low, <90 CRITICAL HYPOXIA — single threshold, no split.
BP systolic: <90 hypotension, 90-139 normal, ≥140 hypertension.

## V.8 CASE-NOTIFICATION ATOMICITY

```js
// CORRECT — atomic, both succeed or neither
await dbAtomicWrite([
  { store: STORE.SECONDARY_SCREENINGS, record: { ...caseRecord, case_status: 'DISPOSITIONED' } },
  { store: STORE.NOTIFICATIONS, record: { ...notif, status: 'CLOSED' } },
])

// WRONG — split-brain if second write fails
await safeDbPut(STORE.SECONDARY_SCREENINGS, { ...caseRecord, case_status: 'DISPOSITIONED' })
await safeDbPut(STORE.NOTIFICATIONS, { ...notif, status: 'CLOSED' })
```



# ══════════════════════════════════════════════════════════════════════════════════
# PART W — ALERTS (IHR ANNEX 2 ALIGNED)
# ══════════════════════════════════════════════════════════════════════════════════

## W.1 ALERT GENERATION — MANDATORY TRIGGERS (always generate)

- risk_level = CRITICAL
- risk_level = HIGH AND syndrome IN (VHF, MENINGITIS)
- rank_order=1 suspected disease is Priority 1: CHOLERA, PLAGUE, EBOLA, MARBURG,
  LASSA, CCHF, RVFEVER, YELLOW_FEVER, MPOX, SMALLPOX
- emergency_signs_present=1 AND risk_level IN (HIGH, CRITICAL)

## W.2 ALERT GENERATION — CONDITIONAL TRIGGERS

- risk_level=HIGH AND travel to outbreak country in last 21 days
- SARI syndrome AND SpO2 < 90
- AWD syndrome AND general_appearance = SEVERELY_ILL

## W.3 ALERT ROUTING

DISTRICT:  risk HIGH, no Priority 1 disease → acknowledged by DISTRICT_SUPERVISOR.
PHEOC:     risk CRITICAL or Priority 1 + HIGH → acknowledged by PHEOC_OFFICER or NATIONAL_ADMIN.
NATIONAL:  Any EBOLA, MARBURG, PLAGUE, SMALLPOX suspicion → NATIONAL_ADMIN only.

## W.4 ALERT STATUS MACHINE

OPEN → ACKNOWLEDGED → CLOSED.
OPEN → CLOSED (direct, NATIONAL_ADMIN only — alert in error).
CLOSED → any: INVALID.

## W.5 OFFICER MANUAL ALERTS

generated_from = 'OFFICER'. Officer_notes must explain reason. Available even
when automatic rules don't trigger — based on clinical judgment.


# ══════════════════════════════════════════════════════════════════════════════════
# PART X — AGGREGATED DATA SUBMISSIONS
# ══════════════════════════════════════════════════════════════════════════════════

## X.1 PURPOSE

Manual summary counts for retrospective/high-volume reporting. INDEPENDENT of
individual screening records. Required weekly by WHO/IHR.

## X.2 AUTHORIZATION

auth._permissions.can_submit_aggregated must be true.
Only POE_DATA_OFFICER and POE_ADMIN can create submissions.

## X.3 COUNT VALIDATION

total_screened = total_male + total_female + total_other + total_unknown_gender
total_screened = total_symptomatic + total_asymptomatic
All counts ≥ 0. If total_screened = 0, confirmation required.

## X.4 PERIOD VALIDATION

period_end > period_start. No future dates (1hr clock drift allowed).
Periods >7 days rejected with warning. Overlapping periods warned.


# ══════════════════════════════════════════════════════════════════════════════════
# PART Y — SYNC ENGINE PATTERN (copy exactly, never improvise)
# ══════════════════════════════════════════════════════════════════════════════════

## Y.1 THE PATTERN

```js
const activeSyncKeys = new Set()  // module-level, prevents concurrent sync
let syncTimer = null

async function syncOne(uuid) {
  if (activeSyncKeys.has(uuid)) return false  // concurrent guard
  activeSyncKeys.add(uuid)
  try {
    const record = await dbGet(STORE, uuid)
    if (!record || record.sync_status === SYNC.SYNCED) return true

    // Increment attempt BEFORE fetch (crash-safe)
    const working = { ...record,
      sync_attempt_count: (record.sync_attempt_count || 0) + 1,
      record_version: (record.record_version || 1) + 1,
      updated_at: isoNow()
    }
    await safeDbPut(STORE, working)

    const ctrl = new AbortController()
    const tid = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
    const res = await fetch(url, { ...options, signal: ctrl.signal })
    clearTimeout(tid)

    if (res.ok) {
      await safeDbPut(STORE, { ...working,
        sync_status: SYNC.SYNCED, synced_at: isoNow()
      })
      return true
    }

    const retryable = res.status >= 500 || res.status === 429
    await safeDbPut(STORE, { ...working,
      sync_status: retryable ? SYNC.UNSYNCED : SYNC.FAILED
    })
    if (retryable) scheduleRetry()
    return false
  } catch (e) {
    // Network/timeout — always UNSYNCED (retryable), never FAILED
    await safeDbPut(STORE, { ...record, sync_status: SYNC.UNSYNCED })
    scheduleRetry()
    return false
  } finally {
    activeSyncKeys.delete(uuid)  // ALWAYS release, even on throw
  }
}
```

## Y.2 BATCH ORDERING (foreign key dependencies)

1. PRIMARY items first
2. NOTIFICATION items second (FK → primary_screenings)
3. SECONDARY items third (FK → notifications, primary_screenings)
4. ALERT items fourth (FK → secondary_screenings)
5. AGGREGATED items last (independent)

## Y.3 IDEMPOTENCY

client_uuid is the idempotency key. If server already has the record with
sync_status SYNCED, return existing server_id (HTTP 200). Do not update.

## Y.4 RETRY STRATEGY

FAILED (4xx non-retryable): remains FAILED, requires officer intervention.
UNSYNCED (network/timeout): retried via scheduleRetry() at APP.SYNC_RETRY_MS.
SYNCED: never retried.

## Y.5 CLEANUP

```js
onUnmounted(() => {
  clearTimeout(syncTimer)
  clearInterval(autoTimer)
  window.removeEventListener('online', onOnline)
  window.removeEventListener('offline', onOffline)
})
```


# ══════════════════════════════════════════════════════════════════════════════════
# PART Z — DISEASE ENGINE ARCHITECTURE
# ══════════════════════════════════════════════════════════════════════════════════

## Z.1 FILE ARCHITECTURE

Diseases.js:               Scoring engine, 41 diseases, symptom weights, gate rules.
Diseases_intelligence.js:  Clinical brain, patches scoreDiseases(), activates bonuses.
exposures.js:              Exposure catalog, 20 WHO exposures with engine_codes[].

Load order in main.ts: Diseases.js → Diseases_intelligence.js → exposures.js.
This order is MANDATORY. Intelligence layer patches the base engine.

## Z.2 HOW TO CALL THE ENGINE

```js
const enhanced = window.DISEASES.getEnhancedScoreResult(
  presentSymptoms,       // string[] — confirmed present
  absentSymptoms,        // string[] — confirmed absent
  engineExposureCodes,   // string[] — from window.EXPOSURES.mapToEngineCodes(dbRecords)
  visitedCountries,      // [{ country_code, travel_role }]
  vitals                 // { temperature_c, oxygen_saturation, pulse_rate, ... }
)
// Returns: syndrome, ihr_risk, is_non_case, top_diagnoses, outbreak_context_used, etc.
```

## Z.3 EXPOSURE FLOW

```js
// Step 1: Init from catalog
for (const exp of window.EXPOSURES?.getAll() || []) {
  exposuresMap[exp.code] = { ...exp, response: 'UNKNOWN' }
}
// Step 2: Load saved DB responses on top
const saved = await dbGetByIndex(STORE.SECONDARY_EXPOSURES, 'secondary_screening_id', caseUuid)
for (const rec of saved) {
  if (exposuresMap[rec.exposure_code]) exposuresMap[rec.exposure_code].response = rec.response
}
// Step 3: Translate for engine
const engineCodes = window.EXPOSURES.mapToEngineCodes(Object.values(exposuresMap))
```

## Z.4 NON-CASE PATHWAY

When enhanced.is_non_case === true: auto-set syndrome=NONE, risk=LOW, disposition=RELEASED.
Officer can override via officerOverride.overrideNonCase = true — always provide this button.

## Z.5 SpO2 THRESHOLD — WHO STANDARD

SpO2 < 90% = CRITICAL_HYPOXIA → emergency triage required.
Do NOT split into <85 CRITICAL and 85-89 SEVERE — single threshold always.

## Z.6 OLD FILE DETECTION

| File                       | Old version signal                         | Correct signal                               |
|----------------------------|--------------------------------------------|----------------------------------------------|
| exposures.js               | < 20 entries, no engine_codes, ~4KB        | 20 entries, mapToEngineCodes(), ~40KB        |
| Diseases_intelligence.js   | Does not exist                             | 3,374 lines, getEnhancedScoreResult defined  |
| SecondaryScreening.vue     | Contains deriveAutoSyndrome                | deriveAutoSyndrome absent                    |
| SecondaryScreening.vue     | Two sequential safeDbPut calls             | Uses dbAtomicWrite                           |


# ══════════════════════════════════════════════════════════════════════════════════
# PART AA — TABLE SCHEMAS (KEY COLUMNS REFERENCE)
# ══════════════════════════════════════════════════════════════════════════════════

## AA.1 USERS

id (BIGINT PK AUTO_INCREMENT), role_key (VARCHAR 60), country_code (VARCHAR 10),
full_name (VARCHAR 150), username (VARCHAR 80 UNIQUE), password (VARCHAR 200),
email (VARCHAR 190 UNIQUE), phone (VARCHAR 40), is_active (TINYINT DEFAULT 1).

## AA.2 USER_ASSIGNMENTS

id (PK), user_id (FK→users), country_code, province_code, pheoc_code,
district_code, poe_code, is_primary (DEFAULT 1), is_active (DEFAULT 1),
starts_at, ends_at.

## AA.3 PRIMARY_SCREENINGS

id (PK), client_uuid (CHAR 36 UNIQUE), reference_data_version, country_code,
province_code, pheoc_code, district_code, poe_code, captured_by_user_id,
gender ENUM('MALE','FEMALE','OTHER','UNKNOWN'),
traveler_direction ENUM('ENTRY','EXIT','TRANSIT') NULL,
traveler_full_name, temperature_value (DECIMAL 5,2), temperature_unit ENUM('C','F'),
symptoms_present (TINYINT), captured_at, device_id, platform,
referral_created (TINYINT DEFAULT 0), record_version, record_status ENUM('COMPLETED','VOIDED'),
void_reason, sync_status ENUM('UNSYNCED','SYNCED','FAILED'), synced_at, sync_attempt_count.

## AA.4 NOTIFICATIONS

id (PK), client_uuid (UNIQUE), primary_screening_id (FK), created_by_user_id,
notification_type ENUM('SECONDARY_REFERRAL','ALERT'),
status ENUM('OPEN','IN_PROGRESS','CLOSED'),
priority ENUM('NORMAL','HIGH','CRITICAL'),
reason_code, reason_text, assigned_role_key, assigned_user_id,
opened_at, closed_at, record_version, sync_status.

## AA.5 SECONDARY_SCREENINGS

id (PK), client_uuid (UNIQUE), primary_screening_id, notification_id,
opened_by_user_id, case_status ENUM('OPEN','IN_PROGRESS','DISPOSITIONED','CLOSED'),
traveler identity fields, travel itinerary fields, clinical triage fields
(temperature, pulse, respiratory, bp, spo2),
syndrome_classification (VARCHAR 60), risk_level ENUM('LOW','MEDIUM','HIGH','CRITICAL'),
final_disposition ENUM('RELEASED','DELAYED','QUARANTINED','ISOLATED','REFERRED',
'TRANSFERRED','DENIED_BOARDING','OTHER'),
followup_required, followup_assigned_level, record_version, sync_status.

## AA.6 ALERTS

id (PK), client_uuid (UNIQUE), secondary_screening_id,
generated_from ENUM('RULE_BASED','OFFICER'),
risk_level ENUM('LOW','MEDIUM','HIGH','CRITICAL'),
alert_code, alert_title, alert_details,
routed_to_level ENUM('DISTRICT','PHEOC','NATIONAL'),
status ENUM('OPEN','ACKNOWLEDGED','CLOSED'),
acknowledged_by_user_id, acknowledged_at, closed_at, record_version, sync_status.

## AA.7 AGGREGATED_SUBMISSIONS

id (PK), client_uuid (UNIQUE), submitted_by_user_id,
period_start, period_end, total_screened, total_male, total_female,
total_other, total_unknown_gender, total_symptomatic, total_asymptomatic,
notes, record_version, sync_status.

## AA.8 SECONDARY CHILD TABLES

All have: id (PK), secondary_screening_id (FK CASCADE DELETE), client_uuid.
See Part V.5 for field details per child table.

## AA.9 SYNC TABLES

sync_batches: id (PK), client_batch_uuid (UNIQUE), device_id, submitted_by_user_id,
status ENUM('RECEIVED','PROCESSING','PARTIAL','FAILED','COMPLETED').

sync_batch_items: id (PK), sync_batch_id (FK CASCADE), entity_type
ENUM('PRIMARY','NOTIFICATION','SECONDARY','ALERT','AGGREGATED'),
entity_client_uuid, server_entity_id, status ENUM('ACCEPTED','REJECTED').

## AA.10 IDB INDEXES (the ONLY queryable fields)

```
users_local:              sync_status, role_key, country_code, is_active, username, username_ci, email_ci
primary_screenings:       sync_status, poe_code, captured_at, captured_by_user_id, referral_created, symptoms_present
notifications:            sync_status, poe_code, primary_screening_id, status, notification_type, priority
secondary_screenings:     sync_status, poe_code, case_status, primary_screening_id, notification_id, opened_by_user_id
secondary child tables:   secondary_screening_id (only index on each)
alerts:                   sync_status, poe_code, status, risk_level, secondary_screening_id
aggregated_submissions:   sync_status, poe_code, period_start, district_code
sync_batches:             status, device_id
sync_batch_items:         sync_batch_id, entity_type
```



# ══════════════════════════════════════════════════════════════════════════════════
# PART BB — BUGS HIT & LESSONS LEARNED (every rule from a real bug)
# ══════════════════════════════════════════════════════════════════════════════════

| Bug                             | Root Cause                                       | Fix                                                    |
|---------------------------------|--------------------------------------------------|--------------------------------------------------------|
| syndrome_bonus always 0         | scoreDiseases() had `= 0; // implement later`   | Intelligence layer patches via ENGINE_TO_WHO_SYNDROME   |
| outbreak_bonus always 0         | outbreak_context[] never populated                | buildOutbreakContext() uses ENDEMIC_COUNTRIES oracle    |
| Exposure scores always 0        | Vue passed 5 hardcoded wrong engine codes         | window.EXPOSURES.mapToEngineCodes() translates correctly|
| Blank detail page               | client_uuid used in route param instead of id     | Assert Number.isInteger(Number(id)) before router.push  |
| Notification split-brain        | Two sequential safeDbPut across stores            | dbAtomicWrite([case, notif]) always                    |
| SpO2=87 not CRITICAL            | Two thresholds <85 and <90 split differently      | Single threshold: <90 = CRITICAL_HYPOXIA               |
| lassa_fever wrong score         | Duplicate key in ENDEMIC_COUNTRIES                | Removed duplicate at line 413                          |
| result.top_diagnoses.concat?    | .concat is always truthy (it's a function)        | Array.isArray(result.top_diagnoses)                    |
| String.repeat(-1) crash         | scoreBar pct not clamped                          | Math.min(20, Math.max(0, pct))                         |
| deriveAutoSyndrome conflict     | Vue had own 10-rule classifier parallel to engine | Removed — single source of truth in intelligence layer  |
| IDB stale cache                 | Old records had no id field                       | Every cache function writes id: u.id explicitly         |
| IonIcon URL error in Capacitor  | Used string name="cloud-upload-outline"           | Import SVG: import { cloudUploadOutline } from 'ionicons/icons' |
| Modal content cut off           | :initial-breakpoint="0.92" :breakpoints="[0,0.92,1]" | Full-screen modal or breakpoints [0,1]              |
| Counts slow on large stores     | dbGetAll().filter().length                        | dbCountIndex() — O(1) IDB native count                 |
| Auth stale during sync          | Module-level auth cached at import time           | Read fresh from sessionStorage inside handler           |


# ══════════════════════════════════════════════════════════════════════════════════
# PART CC — CONSTANTS REFERENCE (never hardcode)
# ══════════════════════════════════════════════════════════════════════════════════

```js
APP.VERSION              // '0.0.1'
APP.REFERENCE_DATA_VER   // 'rda-2026-02-01'
APP.DB_NAME              // 'poe_offline_db'
APP.SCHEMA_VERSION       // 14
APP.SYNC_RETRY_MS        // 10_000
APP.SYNC_TIMEOUT_MS      // 8_000
APP.DEVICE_ID_KEY        // 'poe_device_id'
APP.DAY_COUNT_DAY_KEY    // 'poe_ps_day'
APP.DAY_COUNT_CNT_KEY    // 'poe_ps_cnt'

STORE.USERS_LOCAL                  // 'users_local'
STORE.PRIMARY_SCREENINGS           // 'primary_screenings'
STORE.NOTIFICATIONS                // 'notifications'
STORE.SECONDARY_SCREENINGS         // 'secondary_screenings'
STORE.SECONDARY_SYMPTOMS           // 'secondary_symptoms'
STORE.SECONDARY_EXPOSURES          // 'secondary_exposures'
STORE.SECONDARY_ACTIONS            // 'secondary_actions'
STORE.SECONDARY_SAMPLES            // 'secondary_samples'
STORE.SECONDARY_TRAVEL_COUNTRIES   // 'secondary_travel_countries'
STORE.SECONDARY_SUSPECTED_DISEASES // 'secondary_suspected_diseases'
STORE.ALERTS                       // 'alerts'
STORE.AGGREGATED_SUBMISSIONS       // 'aggregated_submissions'
STORE.SYNC_BATCHES                 // 'sync_batches'
STORE.SYNC_BATCH_ITEMS             // 'sync_batch_items'

SYNC.UNSYNCED    // 'UNSYNCED'
SYNC.SYNCED      // 'SYNCED'
SYNC.FAILED      // 'FAILED'
SYNC.LABELS      // { UNSYNCED: 'Pending', SYNCED: 'Uploaded', FAILED: 'Queued' }
```

NEVER show raw SYNC values in the UI. ALWAYS use SYNC.LABELS[record.sync_status].


# ══════════════════════════════════════════════════════════════════════════════════
# PART DD — FILE ARCHITECTURE & LOAD ORDER
# ══════════════════════════════════════════════════════════════════════════════════

## DD.1 CRITICAL FILES

src/services/poeDB.js          — ONLY file that touches Dexie. All views import from here.
src/data/POEs.js               — window.POE_MAIN. 61 Uganda POEs. Reference data.
src/data/Diseases.js           — Scoring engine. 41 diseases. Do not modify.
src/data/Diseases_intelligence.js — Clinical brain. Patches scoreDiseases(). 3,374 lines.
src/data/exposures.js          — 20 WHO exposures with engine_codes[]. 888 lines.
src/router/index.js            — All routes. Specific paths before wildcards.
routes/api.php                 — Laravel routes. AlertsController + AggregatedController imported.

## DD.2 LOAD ORDER IN main.ts (mandatory)

1. Diseases.js        (base scoring engine)
2. Diseases_intelligence.js  (patches base engine)
3. exposures.js       (exposure catalog)
4. App bootstrap

## DD.3 GOOGLE FONTS (add to index.html)

```html
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
```


# ══════════════════════════════════════════════════════════════════════════════════
# PART EE — ABSOLUTE PROHIBITIONS (code rejected without review)
# ══════════════════════════════════════════════════════════════════════════════════

1.  `new Dexie(...)` anywhere outside poeDB.js
2.  `import Dexie from 'dexie'` in any file except poeDB.js
3.  `indexedDB.open(...)` anywhere in the codebase
4.  Any inline `function dbPut()`, `function dbGet()`, `function dbGetAll()` in a view
5.  Hard-coded store strings like `'primary_screenings'` — use STORE.PRIMARY_SCREENINGS
6.  Hard-coded sync status strings like `'UNSYNCED'` — use SYNC.UNSYNCED
7.  `record.sync_status` rendered raw in the UI — use SYNC.LABELS[record.sync_status]
8.  Two sequential dbPut/safeDbPut calls across stores — use dbAtomicWrite
9.  `dbGetAll(store).length` for counts — use dbGetCount or dbCountIndex
10. `safeDbPut` for brand-new records — use dbPut for first inserts
11. Module-level auth cache used for record stamping — re-read inside submit handler
12. Any TypeScript syntax — no interface, no : Type, no <T>, no EntityTable
13. .ts file extension on any view, service, or utility file
14. `<IonIcon name="search-outline" />` — MUST use `:icon="searchOutline"` with SVG import
15. `:initial-breakpoint="0.92"` or any modal breakpoint that clips content
16. `Authorization: Bearer` header on any fetch (API has no auth middleware)
17. Navigating with `client_uuid` in route params — use `record.id` (server integer)
18. Dark background on content areas — content is ALWAYS the light zone
19. Flat solid colors on any surface — ALWAYS use gradients
20. Raw hex values in Vue templates — ALWAYS reference CSS variables from Part B
21. `const APP_VER = '0.0.1'` — use APP.VERSION
22. `const RETRY_MS = 10_000` — use APP.SYNC_RETRY_MS
23. `const SYNC_TIMEOUT = 8_000` — use APP.SYNC_TIMEOUT_MS
24. Options API in any Vue component — Composition API <script setup> ONLY
25. External CSS frameworks (Tailwind, Bootstrap) — scoped <style scoped> ONLY


# ══════════════════════════════════════════════════════════════════════════════════
# PART FF — MASTER QUICK REFERENCE TABLE
# ══════════════════════════════════════════════════════════════════════════════════

| What                     | Do This                                                   | Never This                       |
|--------------------------|-----------------------------------------------------------|----------------------------------|
| Header/toolbar bg        | linear-gradient(180deg, #070E1B, #0E1A2E)                | Light bg, flat color             |
| Content/page bg          | linear-gradient(180deg, #EAF0FA, #F2F5FB, #E4EBF7)      | Dark bg, flat #fff, flat #000    |
| Card bg                  | linear-gradient(145deg, #FFFFFF, #F4F7FC) + shadow       | Flat white, dark card            |
| Modal bg                 | Light gradient + IonContent scroll-y="true"              | Dark bg, breakpoint<1, no scroll |
| Text on content          | --t-dark (#0B1A30) / --t-mid (#475569)                   | #000, white text on light        |
| Text on header           | --t-on-dark (#EDF2FA)                                    | Dark text on dark bg             |
| Accent on content        | --neon-* colors (#0070E0, #00A86B, etc.)                 | --bright-* (too light for white) |
| Accent on header         | --bright-* colors (#00B4FF, #00E676, etc.) + text-shadow | --neon-* (too dark for dark bg)  |
| Badge / tinted surface   | --surface-* gradient + --border-* + --neon-*             | Flat rgba background             |
| Button                   | Gradient + inner top highlight + box-shadow              | Flat solid color                 |
| Input                    | Gradient (--input-1 → --input-2) + focus glow            | Flat white input                 |
| IonIcon                  | import SVG, :icon="x"                                    | name="x" string                  |
| Sync label               | SYNC.LABELS[status]                                      | Raw UNSYNCED/SYNCED/FAILED       |
| Counts                   | dbCountIndex() / dbGetCount()                            | dbGetAll().length                |
| Large list               | IonInfiniteScroll 50/page                                | Render all items                 |
| Touch target             | ≥ 44×44px                                                | Smaller                          |
| TypeScript               | NEVER                                                    | .ts, interface, : Type           |
| New record insert        | dbPut(STORE, record)                                     | safeDbPut for first insert       |
| Async update             | safeDbPut(STORE, record)                                 | dbPut for updates                |
| Cross-store write        | dbAtomicWrite([...])                                     | Two sequential writes            |
| Child table write        | dbReplaceAll(STORE, idx, fk, records)                    | N sequential dbPut calls         |
| Auth read                | Fresh from sessionStorage in handler                      | Module-level cache               |
| Navigation               | router.push('/view/' + record.id)                         | router.push + client_uuid        |
| API headers              | Content-Type + Accept only                                | Authorization: Bearer            |
| Record version           | Increment on every write                                  | Skip increment                   |
| Post-navigation render   | onIonViewDidEnter + nextTick                              | mounted() or onMounted()         |
| Date/time string         | isoNow() from poeDB                                      | new Date().toISOString()         |
| UUID generation          | genUUID() from poeDB                                      | Math.random() or manual UUID     |
| Device ID                | getDeviceId() from poeDB                                  | Manual localStorage read         |


# ══════════════════════════════════════════════════════════════════════════════════
# END OF DOCUMENT
# ══════════════════════════════════════════════════════════════════════════════════
#
# VERSION:    5.0
# DATE:       2026-04-17
# STATUS:     APPROVED — Complete project & UI/UX specification
# SUPERSEDES: ALL prior documents including:
#             - UI_MANDATORY_REQUIREMENTS.txt (v1.0)
#             - SENTINEL_UI_DESIGN_SYSTEM_v2.txt / v2.1.txt
#             - UI_SENTINEL_MASTER_v4.txt (UI-only, no business logic)
#             - All separate dev notes, AI developer notes, poeDB specs
#
# THIS IS THE ONLY DOCUMENT. DROP IT INTO THE PROJECT. EVERYTHING IS HERE.
#
# THEME SUMMARY:
#   DARK ZONE (headers, toolbars, tabs) → Deep navy gradients + neon bright accents
#   LIGHT ZONE (content, cards, modals) → Soft luminous gradients + rich accent colors
#   TRANSITION → Smooth dark-to-light gradient strip between zones
#   EFFECTS → Card shimmer, data stream sweep, scan lines, pulse dots, stagger animations
#   SURFACES → Every surface is a gradient. Nothing flat. Nothing plain. Everything premium.
#
# ARCHITECTURE SUMMARY:
#   Ionic 8 + Vue 3 Composition API + Capacitor 5+ + Dexie.js 4.3.x
#   Laravel 10+ + MySQL 8+ backend (API has NO auth middleware currently)
#   Offline-first: every write → IDB first → network fire-and-forget
#   Reference data: hardcoded JS files (POEs.js, Diseases.js, exposures.js)
#   14 IDB stores, 7 user roles, 4 geographic levels, WHO IHR 2005 compliant
#
# ══════════════════════════════════════════════════════════════════════════════════
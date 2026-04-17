<template>
  <IonApp>

    <!-- ════════════════════════════════════════════════════════════════
         LOGIN GUARD — plain fixed <div>, NOT IonModal.
         IonModal is a Web Component. Its shadow DOM ignores CSS variable
         overrides for width/height, always rendering a small floating card.
         A plain div with position:fixed + inset:0 + z-index:99999 is the
         ONLY reliable full-screen overlay in Ionic Vue on web + Capacitor.
         v-if removes it from DOM on login success (isAuthenticated=true).
         pointer-events:all blocks all interaction with content behind it.
    ════════════════════════════════════════════════════════════════ -->
    <div
      v-if="!isAuthenticated"
      class="lm-overlay"
      role="dialog"
      aria-modal="true"
      aria-label="Sign in to continue"
    >
      <!-- Atmospheric background — pointer-events:none, purely decorative -->
      <div class="lm-atm" aria-hidden="true">
        <div class="lm-orb lm-orb--1"></div>
        <div class="lm-orb lm-orb--2"></div>
        <div class="lm-orb lm-orb--3"></div>
        <div class="lm-grid"></div>
      </div>

          <!-- ── Atmospheric background (pointer-events:none, purely visual) ── -->
          <div class="lm-atm" aria-hidden="true">
            <div class="lm-orb lm-orb--1"></div>
            <div class="lm-orb lm-orb--2"></div>
            <div class="lm-orb lm-orb--3"></div>
            <div class="lm-grid"></div>
              </div>

          <!-- ── Root layout: flex column, fills 100dvh, no overflow ── -->
          <div class="lm-root">

            <!-- STATUS BAR SPACER — keeps content below the safe area -->
            <div class="lm-safe-top" aria-hidden="true"></div>

            <!-- ── BRAND BLOCK ── -->
            <header class="lm-brand" aria-label="ECSA-HC POE Surveillance System">
              <div class="lm-shield-wrap" aria-hidden="true">
                <div class="lm-pulse-ring"></div>
                <div class="lm-pulse-ring lm-pulse-ring--2"></div>
                <div class="lm-shield-icon">
                  <!-- Shield SVG — WHO/IHR health surveillance icon -->
                  <svg class="lm-shield-svg" viewBox="0 0 40 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 2L4 8V22C4 31.4 11.2 40.2 20 42C28.8 40.2 36 31.4 36 22V8L20 2Z" stroke="url(#sh1)" stroke-width="1.8" fill="rgba(37,99,235,0.12)"/>
                    <path d="M13 22L18 27L27 18" stroke="url(#sh2)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="20" cy="22" r="10" stroke="rgba(96,165,250,0.2)" stroke-width="1" fill="none"/>
                    <defs>
                      <linearGradient id="sh1" x1="4" y1="2" x2="36" y2="42" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#60A5FA"/>
                        <stop offset="100%" stop-color="#818CF8"/>
                      </linearGradient>
                      <linearGradient id="sh2" x1="13" y1="18" x2="27" y2="27" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#60A5FA"/>
                        <stop offset="100%" stop-color="#34D399"/>
                      </linearGradient>
                    </defs>
                  </svg>
                </div>
              </div>

              <p class="lm-tagline">ECSA-HC &nbsp;·&nbsp; SENTINEL</p>
              <h1 class="lm-name">POE <span class="lm-name-accent">Digital</span></h1>
              <p class="lm-sub">Point of Entry Surveillance System</p>

              <!-- IHR badge row -->
              <div class="lm-ihr-row" aria-label="Compliance badges">
                <div class="lm-ihr-badge">
                  <span class="lm-ihr-dot lm-ihr-dot--green"></span>
                  <span class="lm-ihr-txt">WHO / IHR</span>
                </div>
                <div class="lm-ihr-div" aria-hidden="true"></div>
                <div class="lm-ihr-badge">
                  <span class="lm-ihr-dot lm-ihr-dot--blue"></span>
                  <span class="lm-ihr-txt">Offline First</span>
                </div>
                <div class="lm-ihr-div" aria-hidden="true"></div>
                <div class="lm-ihr-badge">
                  <span class="lm-ihr-dot lm-ihr-dot--amber"></span>
                  <span class="lm-ihr-txt">v{{ APP_VERSION }}</span>
                </div>
              </div>
            </header>

            <!-- ── OFFLINE INDICATOR (shown when navigator.onLine=false) ── -->
            <div v-if="isOffline" class="lm-offline-banner" role="status" aria-live="polite">
              <div class="lm-ob-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="#34D399" stroke-width="2" stroke-linecap="round">
                  <path d="M1 6s4-4 11-4 11 4 11 4"/><path d="M5 10s3-3 7-3 7 3 7 3"/>
                  <line x1="1" y1="1" x2="23" y2="23"/>
                  <circle cx="12" cy="20" r="2"/>
                </svg>
              </div>
              <div>
                <p class="lm-ob-title">Offline Mode Active</p>
                <p class="lm-ob-sub">Signing in with cached credentials</p>
              </div>
            </div>

            <!-- ── FORM CARD ── -->
            <main class="lm-card" role="main">
              <!-- Top shimmer line -->
              <div class="lm-card-shimmer" aria-hidden="true"></div>

              <div class="lm-card-hdr">
                <h2 class="lm-card-title">Sign In</h2>
                <p class="lm-card-sub">Use your assigned credentials</p>
              </div>

              <!-- Error banner -->
              <div v-if="loginError" class="lm-err" role="alert" aria-live="assertive">
                <svg class="lm-err-ic" viewBox="0 0 24 24" fill="none" stroke="#F87171" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                  <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div class="lm-err-body">
                  <span class="lm-err-msg">{{ loginError }}</span>
                  <span v-if="loginErrorDetail" class="lm-err-detail">{{ loginErrorDetail }}</span>
                </div>
              </div>

              <form autocomplete="on" @submit.prevent="submitLogin" novalidate class="lm-form">

                <!-- Username / Email -->
                <div class="lm-fg">
                  <label class="lm-field-label"
                    :class="{ 'lm-field--focus': focusLogin, 'lm-field--filled': !!loginForm.login }"
                    for="f-login">Username or Email</label>
                  <div class="lm-field"
                    :class="{ 'lm-field--focus': focusLogin, 'lm-field--filled': !!loginForm.login, 'lm-field--error': !!loginError }">
                    <div class="lm-field-ic" aria-hidden="true">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                      </svg>
                    </div>
                    <IonInput
                      id="f-login"
                      v-model="loginForm.login"
                      type="text"
                      name="username"
                      autocomplete="username"
                      placeholder="Enter your username or email"
                      :disabled="loginLoading"
                      class="lm-field-input"
                      aria-required="true"
                      @ionFocus="focusLogin = true"
                      @ionBlur="focusLogin = false"
                    />
                  </div>
                </div>

                <!-- Password -->
                <div class="lm-fg">
                  <label class="lm-field-label"
                    :class="{ 'lm-field--focus': focusPw, 'lm-field--filled': !!loginForm.password }"
                    for="f-pw">Password</label>
                  <div class="lm-field"
                    :class="{ 'lm-field--focus': focusPw, 'lm-field--filled': !!loginForm.password, 'lm-field--error': !!loginError }">
                    <div class="lm-field-ic" aria-hidden="true">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                      </svg>
                    </div>
                    <IonInput
                      id="f-pw"
                      v-model="loginForm.password"
                      :type="showPw ? 'text' : 'password'"
                      name="password"
                      autocomplete="current-password"
                      placeholder="Enter your password"
                      :disabled="loginLoading"
                      class="lm-field-input"
                      aria-required="true"
                      @ionFocus="focusPw = true"
                      @ionBlur="focusPw = false"
                    />
                    <button
                      type="button"
                      class="lm-eye"
                      :aria-label="showPw ? 'Hide password' : 'Show password'"
                      @click="showPw = !showPw"
                    >
                      <svg v-if="!showPw" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                      </svg>
                      <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Submit -->
                <button
                  type="submit"
                  class="lm-submit"
                  :class="{ 'lm-submit--loading': loginLoading }"
                  :disabled="loginLoading || !loginForm.login.trim() || !loginForm.password"
                  aria-label="Sign in to the system"
                >
                  <span v-if="!loginLoading" class="lm-submit-inner">
                    <span class="lm-submit-txt">SIGN IN</span>
                    <svg class="lm-submit-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                      <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                    </svg>
                  </span>
                  <span v-else class="lm-dots" role="status" aria-label="Signing in">
                    <span class="lm-dot"></span><span class="lm-dot"></span><span class="lm-dot"></span>
                  </span>
                </button>

              </form>
            </main>

            <!-- ── TRUST / DEVICE STRIP ── -->
            <div class="lm-trust" aria-label="Device and security information">
              <div class="lm-trust-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="1.8" stroke-linecap="round">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
              </div>
              <div class="lm-trust-info">
                <p class="lm-trust-title">End-to-end encrypted · WHO/IHR 2005</p>
                <p class="lm-trust-sub">All data stored offline — syncs when connected</p>
              </div>
              <span class="lm-trust-badge">SECURE</span>
            </div>

            <!-- ── FOOTER ── -->
            <footer class="lm-foot" aria-label="Application footer">
              <span>ECSA-HC</span>
              <span class="lm-foot-dot" aria-hidden="true">·</span>
              <span>POE Digital Surveillance</span>
              <span class="lm-foot-dot" aria-hidden="true">·</span>
              <span>v{{ APP_VERSION }}</span>
            </footer>

            <!-- Safe area bottom spacer -->
            <div class="lm-safe-bottom" aria-hidden="true"></div>

          </div><!-- /lm-root -->
    </div><!-- /lm-overlay -->

    <!-- ═══════════════════════════════════════════════════════════════════
         ORIGINAL MENU — untouched
    ═══════════════════════════════════════════════════════════════════ -->
    <IonMenu menu-id="app-menu" content-id="main-content" type="overlay" side="start" :swipeGesture="true">
      <IonContent class="menu-content" :scrollY="true">

        <!-- IDENTITY PANEL -->
        <div class="ip">
          <div class="ip__bar" aria-hidden="true" />
          <div class="ip__body">
            <div class="ip__av-wrap">
              <div class="ip__av" role="img" :aria-label="`Avatar for ${displayName}`">
                <span class="ip__initials" aria-hidden="true">{{ userInitials }}</span>
              </div>
              <div class="ip__dot" :class="`ip__dot--${syncState}`" :aria-label="syncLabel" />
            </div>
            <div class="ip__info">
              <p class="ip__name">{{ displayName }}</p>
              <span class="ip__role" :class="`ip__role--${roleCss}`">{{ ROLE_LABELS[authData?.role_key as RoleKey] ?? authData?.role_key ?? '' }}</span>
              <p class="ip__scope">{{ scopeLabel }}</p>
            </div>
          </div>
          <button class="ip__sync" :class="`ip__sync--${syncState}`" type="button"
            :aria-label="`Sync: ${syncLabel}`" @click="navTo('/sync/queue')">
            <IonIcon :icon="syncIcon" class="ip__sync-icon" aria-hidden="true" />
            <span class="ip__sync-label">{{ syncLabel }}</span>
            <span v-if="mockCounts.unsynced > 0" class="ip__sync-ct">{{ mockCounts.unsynced }} pending</span>
          </button>
        </div>

        <!-- GENERATED NAVIGATION — single loop over menuGroups computed -->
        <nav class="mn" aria-label="Application navigation">
          <template v-for="group in menuGroups" :key="group.id">
            <div class="mn__group">
              <p v-if="group.title" class="mn__gt" aria-hidden="true">{{ group.title }}</p>

              <template v-for="item in group.items" :key="item.id">
                <button
                  class="mn__item"
                  :class="{
                    'mn__item--active':  item.route && currentPath === item.route,
                    'mn__item--danger':  item.danger,
                    [`mn__item--${item.accentColor}`]: !!item.accentColor,
                  }"
                  type="button"
                  :aria-label="item.ariaLabel || item.label"
                  @click="item.action ? item.action() : navTo(item.route!)"
                >
                  <IonIcon :icon="item.icon" class="mn__icon" :class="item.iconClass" aria-hidden="true" />
                  <div class="mn__text">
                    <span class="mn__label">{{ item.label }}</span>
                    <span class="mn__sub">{{ item.sub }}</span>
                  </div>
                  <!-- live badge count -->
                  <span v-if="item.badge && item.badge() > 0"
                    class="mn__badge" :class="`mn__badge--${item.badgeVariant || 'primary'}`"
                    aria-hidden="true">
                    {{ item.badge() }}
                  </span>
                  <!-- static tag (e.g. 10s, NATL) -->
                  <span v-if="item.tag" class="mn__tag" :class="`mn__tag--${item.tagVariant || 'default'}`"
                    aria-hidden="true">{{ item.tag }}</span>
                </button>
              </template>
            </div>
          </template>
        </nav>

        <!-- FOOTER -->
        <footer class="mf" aria-label="Build info">
          <div class="mf__div" aria-hidden="true" />
          <div v-for="row in footerRows" :key="row.key" class="mf__row">
            <span class="mf__k">{{ row.key }}</span>
            <span class="mf__v" :class="{ 'mf__mono': row.mono }">{{ row.val }}</span>
          </div>
        </footer>

      </IonContent>
    </IonMenu>

    <IonRouterOutlet id="main-content" :animated="true" />
  </IonApp>
</template>

<script setup lang="ts">
/**
 * App.vue — ECSA POE Offline-First Screening System
 *
 * APPROACH: Data-driven menu. `menuGroups` is a computed array of
 * group/item config objects. The template is a single v-for loop —
 * zero duplicated markup. Adding a new route = one object in the array.
 *
 * AUTH: Login guard modal sits above everything. isAuthenticated drives
 * its visibility. AUTH_DATA in sessionStorage is the single source of truth.
 * On success, body.data (flat users row) is stored + _permissions derived
 * from role_key so RBAC still works without a separate permissions endpoint.
 *
 * LIGHT THEME ONLY — dark.system.css permanently removed from main.ts
 */

import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
  IonApp, IonMenu, IonContent, IonRouterOutlet, IonIcon,
  IonPage, IonInput,
  menuController, alertController, toastController,
} from '@ionic/vue'
import {
  gridOutline,
  addCircleOutline,
  listOutline,
  gitMergeOutline,
  archiveOutline,
  medkitOutline,
  documentTextOutline,
  clipboardOutline,
  warningOutline,
  shieldCheckmarkOutline,
  cloudUploadOutline,
  barChartOutline,
  syncOutline,
  cloudDoneOutline,
  refreshOutline,
  peopleOutline,
  personAddOutline,
  mapOutline,
  analyticsOutline,
  statsChartOutline,
  pulseOutline,
  layersOutline,
  personCircleOutline,
  cogOutline,
  libraryOutline,
  settingsOutline,
  logOutOutline,
  // ── login modal icons ──
  alertCircleOutline,
  lockClosedOutline,
  eyeOutline,
  eyeOffOutline,
  personOutline,
} from 'ionicons/icons'

// ─── App constants ────────────────────────────────────────────────────────────
const APP_VERSION  = '0.0.1'
const REF_DATA_VER = 'rda-2026-02-01'

// ─── AUTH — sessionStorage key ────────────────────────────────────────────────
// AUTH_DATA shape (written on login success):
//   body.data from UserLoginController = flat users row:
//     id, role_key, country_code, full_name, username, email, phone,
//     is_active, last_login_at, created_at, updated_at, email_verified_at, name
//   plus client-added:
//     _permissions  — derived from role_key (see derivePermissions)
//     _logged_in_at — ISO timestamp of this login
const AUTH_KEY = 'AUTH_DATA'
declare global { interface Window { SERVER_URL: string } }

const authData        = ref<Record<string, any> | null>(null)
const isAuthenticated = computed((): boolean => !!authData.value)

// ─── Login form state ─────────────────────────────────────────────────────────
const loginForm        = ref({ login: '', password: '' })
const loginLoading     = ref(false)
const loginError       = ref('')
const loginErrorDetail = ref('')
const showPw           = ref(false)
const focusLogin       = ref(false)
const focusPw          = ref(false)

// ─── Offline detection (reactive — updates on network events) ─────────────────
const isOffline = ref(!navigator.onLine)
// Listen for network state changes so the offline banner appears/disappears live.
// These listeners are registered once at module load (App.vue is always mounted).
window.addEventListener('online',  () => { isOffline.value = false })
window.addEventListener('offline', () => { isOffline.value = true  })

// ─── Offline credential cache ─────────────────────────────────────────────────
// localStorage key that stores a bcrypt-free credential fingerprint for offline login.
// We store a SHA-256 hash of (username:password) so we NEVER persist the plaintext
// password. On offline login attempt we re-hash the input and compare.
// This is intentionally lightweight — the server hash is authoritative;
// the offline hash only unblocks the device when the server is unreachable.
const OFFLINE_CREDS_KEY = 'POE_OFFLINE_CREDS'

/** Store a credential fingerprint after a successful server login. */
async function cacheOfflineCredentials(login: string, password: string, authPayload: Record<string, any>): Promise<void> {
  try {
    const raw     = login.trim().toLowerCase() + ':' + password
    const encoded = new TextEncoder().encode(raw)
    const hashBuf = await crypto.subtle.digest('SHA-256', encoded)
    const hash    = Array.from(new Uint8Array(hashBuf)).map(b => b.toString(16).padStart(2, '0')).join('')
    const cacheEntry = {
      hash,
      // Store the last good AUTH_DATA payload (already contains _permissions, assignment, etc.)
      // This is what gets restored on offline login — exactly the same shape the server returns.
      authPayload,
      cachedAt: new Date().toISOString(),
    }
    localStorage.setItem(OFFLINE_CREDS_KEY, JSON.stringify(cacheEntry))
    console.log('%c[POE AUTH] Offline credential cache updated', 'color:#10B981;font-weight:600')
  } catch (e) {
    // Non-fatal — crypto.subtle unavailable in very old WebViews (should not happen on Android 8+)
    console.warn('[POE AUTH] Failed to cache offline credentials:', e)
  }
}

/** Attempt login using cached credentials. Returns authPayload or null. */
async function tryOfflineLogin(login: string, password: string): Promise<Record<string, any> | null> {
  try {
    const raw = localStorage.getItem(OFFLINE_CREDS_KEY)
    if (!raw) return null
    const cached = JSON.parse(raw) as { hash: string; authPayload: Record<string, any>; cachedAt: string }
    if (!cached?.hash || !cached?.authPayload) return null

    const input   = login.trim().toLowerCase() + ':' + password
    const encoded = new TextEncoder().encode(input)
    const hashBuf = await crypto.subtle.digest('SHA-256', encoded)
    const inputHash = Array.from(new Uint8Array(hashBuf)).map(b => b.toString(16).padStart(2, '0')).join('')

    if (inputHash === cached.hash) {
      console.log('%c[POE AUTH] Offline login matched cached credentials', 'color:#10B981;font-weight:700')
      console.log('%c[POE AUTH] Cached at:', 'color:#10B981', cached.cachedAt)
      return cached.authPayload
    }
    return null
  } catch (e) {
    console.warn('[POE AUTH] Offline login check failed:', e)
    return null
  }
}

// ─── SSOT role keys — users.role_key VARCHAR(60) ─────────────────────────────
type RoleKey =
  | 'POE_PRIMARY' | 'POE_SECONDARY' | 'POE_DATA_OFFICER'
  | 'POE_ADMIN' | 'DISTRICT_SUPERVISOR' | 'PHEOC_OFFICER' | 'NATIONAL_ADMIN'

const ROLE_LABELS: Record<RoleKey, string> = {
  POE_PRIMARY:         'Primary Officer',
  POE_SECONDARY:       'Secondary Officer',
  POE_DATA_OFFICER:    'Data Officer',
  POE_ADMIN:           'POE Admin',
  DISTRICT_SUPERVISOR: 'District Supervisor',
  PHEOC_OFFICER:       'PHEOC Officer',
  NATIONAL_ADMIN:      'National Admin',
}

// ─── Client-side permission derivation ───────────────────────────────────────
// The controller returns only the users row — no permissions object.
// Permissions are computed from role_key here so RBAC still works
// for menu visibility and route gating throughout the app.
function derivePermissions(roleKey: string | null): Record<string, boolean> {
  const base: Record<string, boolean> = {
    can_do_primary_screening:   false,
    can_do_secondary_screening: false,
    can_submit_aggregated:      false,
    can_manage_users:           false,
    can_view_all_poe_data:      false,
    can_view_district_data:     false,
    can_view_province_data:     false,
    can_view_national_data:     false,
    can_manage_poes:            false,
    can_acknowledge_alerts:     false,
    can_close_notifications:    false,
  }
  const m: Record<string, Partial<Record<string, boolean>>> = {
    POE_PRIMARY:         { can_do_primary_screening: true,  can_view_all_poe_data: true,  can_close_notifications: true },
    POE_SECONDARY:       { can_do_secondary_screening: true, can_view_all_poe_data: true, can_close_notifications: true, can_acknowledge_alerts: true },
    POE_DATA_OFFICER:    { can_do_primary_screening: true,  can_do_secondary_screening: true, can_submit_aggregated: true, can_view_all_poe_data: true, can_acknowledge_alerts: true, can_close_notifications: true },
    POE_ADMIN:           { can_do_primary_screening: true,  can_do_secondary_screening: true, can_submit_aggregated: true, can_manage_users: true, can_view_all_poe_data: true, can_manage_poes: true, can_acknowledge_alerts: true, can_close_notifications: true },
    DISTRICT_SUPERVISOR: { can_submit_aggregated: true,     can_view_all_poe_data: true,  can_view_district_data: true, can_manage_users: true, can_acknowledge_alerts: true, can_close_notifications: true },
    PHEOC_OFFICER:       { can_submit_aggregated: true,     can_view_all_poe_data: true,  can_view_district_data: true, can_view_province_code: true, can_manage_users: true, can_acknowledge_alerts: true, can_close_notifications: true },
    NATIONAL_ADMIN:      { can_do_primary_screening: true,  can_do_secondary_screening: true, can_submit_aggregated: true, can_manage_users: true, can_view_all_poe_data: true, can_view_district_data: true, can_view_province_data: true, can_view_national_data: true, can_manage_poes: true, can_acknowledge_alerts: true, can_close_notifications: true },
    SCREENER:            { can_do_primary_screening: true,  can_view_all_poe_data: true,  can_close_notifications: true },
  }
  return { ...base, ...(m[roleKey ?? ''] ?? {}) } as Record<string, boolean>
}

// ─── Router ───────────────────────────────────────────────────────────────────
const router      = useRouter()
const route       = useRoute()
const currentPath = computed(() => route.path)

// ─── Identity display — reads from real authData when logged in ───────────────
const displayName = computed(() =>
  authData.value?.full_name ?? authData.value?.name ?? authData.value?.username ?? 'User'
)

const userInitials = computed(() =>
  displayName.value.split(' ').filter(Boolean).slice(0, 2)
    .map((w: string) => w[0]?.toUpperCase() ?? '').join('')
)

const scopeLabel = computed(() => {
  const d = authData.value
  if (!d) return ''
  // Root-level shortcuts come from primary assignment (set by controller)
  if (d.poe_code)      return `POE · ${d.poe_code}`
  if (d.district_code) return `District · ${d.district_code}`
  if (d.pheoc_code)    return `PHEOC · ${d.pheoc_code}`
  if (d.province_code) return `Province · ${d.province_code}`
  // Fallback to users.country_code
  if (d.country_code)  return `Country · ${d.country_code}`
  return ''
})

const roleCss = computed(() =>
  (authData.value?.role_key ?? '').toLowerCase().replace(/_/g, '-')
)

// ─── Mock counts (replace with IndexedDB store reads in later sprint) ─────────
const mockCounts = {
  unsynced:      7,
  openReferrals: 3,
  activeCases:   5,
  openAlerts:    1,
}
const mockDeviceId = 'ECSA-7K2M'

// ─── Sync display ─────────────────────────────────────────────────────────────
type SyncState = 'synced' | 'unsynced' | 'syncing' | 'failed' | 'offline'
const syncState: SyncState = mockCounts.unsynced > 0 ? 'unsynced' : 'synced'
const syncLabel = { synced: 'All synced', unsynced: 'Sync pending', syncing: 'Syncing…', failed: 'Sync failed', offline: 'Offline' }[syncState]
const syncIcon  = { synced: cloudDoneOutline, unsynced: cloudUploadOutline, syncing: syncOutline, failed: warningOutline, offline: cloudUploadOutline }[syncState]

// ─── RBAC — derived from real role_key after login ────────────────────────────
const r = computed(() => authData.value?.role_key ?? '')
const inRoles = (...roles: RoleKey[]) => roles.includes(r.value as RoleKey)
const can = computed(() => ({
  primary:      inRoles('POE_PRIMARY', 'POE_SECONDARY', 'POE_ADMIN'),
  queue:        inRoles('POE_SECONDARY', 'POE_ADMIN', 'DISTRICT_SUPERVISOR', 'PHEOC_OFFICER', 'NATIONAL_ADMIN'),
  secondary:    inRoles('POE_SECONDARY', 'POE_ADMIN', 'DISTRICT_SUPERVISOR', 'PHEOC_OFFICER', 'NATIONAL_ADMIN'),
  alerts:       inRoles('POE_SECONDARY', 'POE_ADMIN', 'DISTRICT_SUPERVISOR', 'PHEOC_OFFICER', 'NATIONAL_ADMIN'),
  aggregated:   inRoles('POE_DATA_OFFICER', 'POE_ADMIN', 'DISTRICT_SUPERVISOR', 'PHEOC_OFFICER', 'NATIONAL_ADMIN'),
  surveillance: inRoles('DISTRICT_SUPERVISOR', 'PHEOC_OFFICER', 'NATIONAL_ADMIN'),
  admin:        inRoles('POE_ADMIN', 'DISTRICT_SUPERVISOR', 'PHEOC_OFFICER', 'NATIONAL_ADMIN'),
  system:       r.value === 'NATIONAL_ADMIN',
}))

// ─── Navigation helper ────────────────────────────────────────────────────────
async function navTo(path: string) {
  await menuController.close('app-menu')
  await router.push(path)
}

// ─── Sign out — replaces mock handleSignOut, uses real auth teardown ──────────
async function handleSignOut() {
  await menuController.close('app-menu')
  const alert = await alertController.create({
    header:  'Sign Out',
    message: mockCounts.unsynced > 0
      ? `${mockCounts.unsynced} unsynced record(s) will remain safely on this device. Sign out anyway?`
      : 'Are you sure you want to sign out?',
    buttons: [
      { text: 'Cancel', role: 'cancel' },
      {
        text: 'Sign Out', role: 'destructive',
        handler: async () => {
          console.log('%c[POE AUTH] User signed out — AUTH_DATA cleared', 'color:#DC3545;font-weight:700')
          sessionStorage.removeItem(AUTH_KEY)
          authData.value         = null
          loginError.value       = ''
          loginErrorDetail.value = ''
          void router.replace('/')
          const t = await toastController.create({
            message:  'You have been signed out.',
            duration: 2000,
            position: 'bottom',
            color:    'medium',
          })
          void t.present()
        },
      },
    ],
  })
  await alert.present()
}

// ─────────────────────────────────────────────────────────────────────────────
//  MENU CONFIG — original, untouched
// ─────────────────────────────────────────────────────────────────────────────

interface MenuItem {
  id:           string
  label:        string
  sub:          string
  route?:       string
  icon:         string
  iconClass?:   string
  badge?:       () => number
  badgeVariant?:string
  tag?:         string
  tagVariant?:  string
  ariaLabel?:   string
  danger?:      boolean
  accentColor?: string
  action?:      () => void
}
interface MenuGroup {
  id:    string
  title?:string
  show:  boolean
  items: MenuItem[]
}

const menuGroups = computed((): MenuGroup[] => [

 // ── 1. DASHBOARD ────────────────────────────────────────────────────────────
// Read-only summary: total screenings, open referrals, active cases, alerts,
// unsynced count. Entry point after login. Visible all roles.
{
  id: 'dashboard', show: true,
  items: [{
    id: 'dashboard', label: 'Dashboard', icon: gridOutline,
    sub: 'Operational summary · sync status',
    route: '/home',
  }],
},

// ── POE MANAGEMENT ───────────────────────────────────────────────────────────
{
  id: 'poe-management', title: 'POE MANAGEMENT', show: true /* DEV: auth guard disabled */,
  items: [
    {
      id: 'poe-management-create-poe', label: 'Manage POEs', icon: mapOutline,
      sub: 'Add · edit · assign district & PHEOC · set status',
      route: '/POEs',
      tag: 'core', tagVariant: 'primary',
      ariaLabel: 'Manage Points of Entry — register, configure and assign POE hierarchy',
    },
  ],
},

// ── USER MANAGEMENT ──────────────────────────────────────────────────────────
{
  id: 'user-management', title: 'USER MANAGEMENT', show: true /* DEV: auth guard disabled */,
  items: [
    {
      id: 'user-management-create-user', label: 'Manage Users', icon: peopleOutline,
      sub: 'Create · assign role · activate / deactivate',
      route: '/Users',
      ariaLabel: 'Manage user accounts — create users, assign roles and geographic scope',
    },
  ],
},

// ── 2. SCREENING WORKFLOWS ───────────────────────────────────────────────────
// DFD: Traveler arrives → primary officer captures gender / temp / symptoms
//   symptoms=NO → record saved offline, traveler exits
//   symptoms=YES → notification auto-created → secondary queue → case opened
// DB: primary_screenings + notifications + secondary_screenings (all offline-first)
{
  id: 'screening', title: 'SCREENING', show: true /* DEV: auth guard disabled */,
  items: [
    {
      // Ultra-fast capture: gender + optional temp + symptoms YES/NO.
      // symptoms=YES auto-creates SECONDARY_REFERRAL notification atomically.
      id: 'primary-new', label: ' Primary Screening', icon: medkitOutline,
      sub: 'Capture traveler · temperature · symptoms',
      iconClass: 'mn__icon--create',
      route: '/PrimaryScreening',
      tag: '10s', tagVariant: 'speed',
      ariaLabel: 'Start new primary screening — ultra-fast 10-second traveler capture',
    },
    {
      // Referral queue: lists all OPEN SECONDARY_REFERRAL notifications at this POE.
      // Secondary officer picks up a referral here to open a secondary case.
      id: 'secondary-queue', label: 'Secondary Screening', icon: gitMergeOutline,
      sub: 'Open referrals · pick up case · action required',
      iconClass: 'mn__icon--secondary',
      route: '/NotificationsCenter',
      badge: () => mockCounts.openReferrals, badgeVariant: 'danger',
      ariaLabel: 'Secondary referral queue — open notifications awaiting secondary officer',
    },
    {
      // Full WHO/IHR secondary case investigation records.
      id: 'secondary-records', label: 'Secondary Case Records', icon: documentTextOutline,
      sub: 'All cases · clinical findings · disposition',
      route: '/secondary-screening/records',
      ariaLabel: 'Secondary screening case records — view, search and filter all cases',
    },
    {
      // Paginated list of all primary screenings at this POE with sync status.
      id: 'primary-records', label: 'Primary Screening Records', icon: listOutline,
      sub: 'View · search · filter · sync status',
      route: '/primary-screening/records',
      ariaLabel: 'Primary screening records — full list with search, filters and sync state',
    },
    {
      // Analytics dashboard: trend, funnel, epi indicators, device health.
      id: 'primary-dashboard', label: 'Primary Screening Analytics', icon: barChartOutline,
      sub: 'Trends · funnel · epi indicators · devices',
      route: '/primary-screening/dashboard',
      ariaLabel: 'Primary screening analytics dashboard — trends, referral funnel and device health',
    },
  ],
},

  // ── 3. REFERRAL / NOTIFICATION WORKFLOW ────────────────────────────────────
  // DFD step: symptoms=YES → notification auto-created (OPEN) →
  //   Secondary officer picks up → moves to IN_PROGRESS → closes after case
  // DB: notifications  status: OPEN → IN_PROGRESS → CLOSED
  // {
  //   id: 'referrals', title: 'REFERRAL WORKFLOW', show: true  /* DEV: auth guard disabled */,
  //   items: [
  //     {
  //       id: 'notif-queue', label: 'Referral Queue', icon: gitMergeOutline,
  //       sub: 'Open · in-progress · action required',
  //       route: '/notifications',
  //       badge: () => mockCounts.openReferrals, badgeVariant: 'danger',
  //       ariaLabel: `Referral queue — ${mockCounts.openReferrals} open`,
  //     },
  //     {
  //       id: 'notif-history', label: 'Referral History', icon: archiveOutline,
  //       sub: 'Closed referrals · audit trail',
  //       route: '/notifications/history',
  //     },
  //   ],
  // },

  // ── 4. SECONDARY SCREENING ─────────────────────────────────────────────────
  // DFD step: Secondary officer opens referral → full WHO/IHR investigation:
  //   Tab 1 Triage & safety  Tab 2 Identity/demographics
  //   Tab 3 Travel history   Tab 4 Exposure & risk
  //   Tab 5 Symptoms         Tab 6 Vitals & clinical
  //   Tab 7 Measures taken   Tab 8 Assessment & disposition
  // DB: secondary_screenings (main) + child tables:
  //   secondary_travel_countries, secondary_symptoms, secondary_exposures,
  //   secondary_actions, secondary_samples, secondary_suspected_diseases
  // case_status: OPEN → IN_PROGRESS → DISPOSITIONED → CLOSED
  // {
  //   id: 'secondary', title: 'SECONDARY SCREENING', show: true  /* DEV: auth guard disabled */,
  //   items: [
  //     {
  //       id: 'secondary-active', label: 'Active Investigations', icon: medkitOutline,
  //       sub: 'WHO/IHR case investigation · 8 tabs',
  //       iconClass: 'mn__icon--secondary',
  //       route: '/secondary-screening',
  //       badge: () => mockCounts.activeCases, badgeVariant: 'warning',
  //       ariaLabel: `Active secondary investigations — ${mockCounts.activeCases} open`,
  //     },
  //     {
  //       id: 'secondary-records', label: 'Case Records', icon: documentTextOutline,
  //       sub: 'All cases · linked primary & referral',
  //       route: '/secondary-screening/records',
  //     },
  //     {
  //       id: 'secondary-samples', label: 'Samples & Testing', icon: clipboardOutline,
  //       sub: 'Sample IDs · lab destination · results',
  //       route: '/secondary-screening/samples',
  //     },
  //   ],
  // },

  // ── 5. ALERTS ───────────────────────────────────────────────────────────────
  // DFD step: Secondary officer assesses HIGH/CRITICAL risk →
  //   Alert auto-generated (rule-based) or officer-created →
  //   Routed to correct tier: DISTRICT | PHEOC | NATIONAL
  //   Supervisor acknowledges → escalates or closes
  // DB: alerts  status: OPEN → ACKNOWLEDGED → CLOSED
  //            risk_level: LOW | MEDIUM | HIGH | CRITICAL
  //            routed_to_level: DISTRICT | PHEOC | NATIONAL
  {
    id: 'alerts', title: 'ALERTS', show: true  /* DEV: auth guard disabled */,
    items: [
      {
        id: 'alerts-active', label: 'Active Alerts', icon: warningOutline,
        sub: 'Open · acknowledge · escalate · route',
        iconClass: 'mn__icon--alert',
        route: '/alerts',
        badge: () => mockCounts.openAlerts, badgeVariant: 'danger',
        ariaLabel: `Active alerts — ${mockCounts.openAlerts} open`,
      },
      {
        id: 'alerts-history', label: 'Alert History', icon: shieldCheckmarkOutline,
        sub: 'Acknowledged · closed · audit trail',
        route: '/alerts/history',
      },
    ],
  },

  // ── 6. AGGREGATED DATA SUBMISSION ──────────────────────────────────────────
  // DFD step: Independent parallel workflow — Data Officer aggregates counts
  //   (total_screened, by gender, symptomatic/asymptomatic) for reporting period
  //   → saves offline → queues for sync
  // DB: aggregated_submissions
  {
    id: 'aggregated', title: 'AGGREGATED DATA', show: true  /* DEV: auth guard disabled */,
    items: [
      {
        id: 'agg-new', label: 'New Report', icon: cloudUploadOutline,
        sub: 'Submit counts · period totals · offline',
        iconClass: 'mn__icon--create',
        route: '/aggregated-data/new',
      },
      {
        id: 'agg-history', label: 'Submission History', icon: barChartOutline,
        sub: 'Past reports · details · sync state',
        route: '/aggregated-data/history',
      },
    ],
  },

  // ── 7. SURVEILLANCE (hierarchy-scoped read views) ───────────────────────────
  // DFD step: Supervisors review cross-POE data within their geographic scope
  //   District: sees own district_code data
  //   PHEOC:    sees province_code / pheoc_code data
  //   National: sees all data
  // Sources: primary_screenings + secondary_screenings + alerts +
  //          aggregated_submissions + secondary_suspected_diseases
  // {
  //   id: 'surveillance', title: 'SURVEILLANCE', show: true  /* DEV: auth guard disabled */,
  //   items: [
  //     {
  //       id: 'surv-overview', label: 'Overview', icon: analyticsOutline,
  //       sub: 'Cross-POE summary · jurisdiction scope',
  //       route: '/surveillance/overview',
  //     },
  //     {
  //       id: 'surv-trends', label: 'Screening Trends', icon: statsChartOutline,
  //       sub: 'Primary + secondary volumes · time series',
  //       route: '/surveillance/screening-trends',
  //     },
  //     {
  //       id: 'surv-signals', label: 'Disease Signals', icon: pulseOutline,
  //       sub: 'Syndromes · suspected diseases · risk levels',
  //       route: '/surveillance/disease-signals',
  //     },
  //     {
  //       id: 'surv-alerts', label: 'Alert Summary', icon: layersOutline,
  //       sub: 'Alerts by POE · level · risk · status',
  //       route: '/surveillance/alert-summary',
  //     },
  //     {
  //       id: 'surv-agg', label: 'Aggregated Reports', icon: barChartOutline,
  //       sub: 'Submitted counts from all jurisdiction POEs',
  //       route: '/surveillance/aggregated-reports',
  //     },
  //   ],
  // },

  // ── 8. SYNC MANAGEMENT ──────────────────────────────────────────────────────
  // DFD step: All offline records across 5 stores queue as UNSYNCED →
  //   Officer initiates manual sync → sync_batch created (client_batch_uuid) →
  //   Server validates per entity → returns server IDs →
  //   sync_batch_items record ACCEPTED|REJECTED per entity →
  //   Local records updated to SYNCED | FAILED
  // DB: sync_batches + sync_batch_items
  //     entity_type: PRIMARY|NOTIFICATION|SECONDARY|ALERT|AGGREGATED
  {
    id: 'sync', title: 'SYNC MANAGEMENT', show: true,
    items: [
      {
        id: 'sync-queue', label: 'Sync Queue', icon: syncOutline,
        sub: 'Unsynced records · batch review · upload',
        route: '/sync/queue',
        badge: () => mockCounts.unsynced, badgeVariant: 'sync',
        ariaLabel: `Sync queue — ${mockCounts.unsynced} records pending`,
      },
      {
        id: 'sync-history', label: 'Sync History', icon: cloudDoneOutline,
        sub: 'Batches · accepted · rejected · errors',
        route: '/sync/history',
      },
      {
        id: 'sync-retry', label: 'Failed Syncs', icon: refreshOutline,
        sub: 'Retry failed records · error detail',
        route: '/sync/failed',
        iconClass: 'mn__icon--alert',
      },
    ],
  },

  // ── 9. ADMINISTRATION ───────────────────────────────────────────────────────
  // DFD step: Admin manages user accounts and geographic scope assignments
  // DB: users (CRUD) + user_assignments (CRUD)
  //     user_assignments scope: country → province → pheoc → district → poe
  // {
  //   id: 'admin', title: 'ADMINISTRATION', show: true  /* DEV: auth guard disabled */,
  //   items: [
  //     {
  //       id: 'admin-users', label: 'User Management', icon: peopleOutline,
  //       sub: 'Create · edit · roles · activate/deactivate',
  //       route: '/PrimaryScreening',
  //     },
  //     {
  //       id: 'admin-users-new', label: 'Add User', icon: personAddOutline,
  //       sub: 'New user account · assign role',
  //       iconClass: 'mn__icon--create',
  //       route: '/admin/users/new',
  //     },
  //     {
  //       id: 'admin-assignments', label: 'User Assignments', icon: mapOutline,
  //       sub: 'Country · district · PHEOC · POE scope',
  //       route: '/admin/assignments',
  //     },
  //   ],
  // },

  // ── 10. ACCOUNT & DEVICE ───────────────────────────────────────────────────
  // Profile: own users row (restricted fields only)
  // App Settings: device_id, platform, offline behavior config
  // Reference Data: READ-ONLY viewer of hardcoded JSON
  //   (countries, admin codes, symptoms, diseases, action codes, exposure codes)
  // {
  //   id: 'account', title: 'ACCOUNT & DEVICE', show: true,
  //   items: [
  //     {
  //       id: 'profile', label: 'My Profile', icon: personCircleOutline,
  //       sub: 'View · update own details',
  //       route: '/profile',
  //     },
  //     {
  //       id: 'settings', label: 'App Settings', icon: cogOutline,
  //       sub: 'Device info · offline behavior · display',
  //       route: '/settings',
  //     },
  //     {
  //       id: 'ref-data', label: 'Reference Data', icon: libraryOutline,
  //       sub: 'Countries · symptoms · diseases · codes',
  //       route: '/admin/reference-data',
  //     },
  //   ],
  // },

  // ── 11. SYSTEM SETTINGS (NATIONAL_ADMIN only) ──────────────────────────────
  // Global app config, reference data version control,
  // system-wide operational settings
  {
    id: 'system', title: 'SYSTEM', show: true  /* DEV: auth guard disabled */,
    items: [{
      id: 'system-settings', label: 'System Settings', icon: settingsOutline,
      sub: 'Global config · ref data version · controls',
      iconClass: 'mn__icon--system',
      route: '/admin/system',
      tag: 'NATL', tagVariant: 'restricted',
      ariaLabel: 'System settings — National Admin only',
    }],
  },

  // ── 12. SIGN OUT ────────────────────────────────────────────────────────────
  // Clears localStorage auth tokens ONLY.
  // IndexedDB is NEVER wiped — unsynced records must survive session end.
  {
    id: 'signout', show: true,
    items: [{
      id: 'sign-out', label: 'Sign Out', icon: logOutOutline,
      sub: mockCounts.unsynced > 0 ? `${mockCounts.unsynced} offline records stay on device` : 'Session will end',
      danger: true,
      action: handleSignOut,
      ariaLabel: 'Sign out — unsynced records remain on device',
    }],
  },

].filter(g => g.show))

// ─── Footer rows ──────────────────────────────────────────────────────────────
const footerRows = [
  { key: 'ECSA · POE SYSTEM', val: `v${APP_VERSION}`,  mono: false },
  { key: 'REF DATA',          val: REF_DATA_VER,        mono: true  },
  { key: 'DEVICE',            val: mockDeviceId,        mono: true  },
]

// ─── Session restore on mount ─────────────────────────────────────────────────
// Validates id (user exists) and is_active.
// The controller returns the flat users row — no nested user object.
onMounted((): void => {
  try {
    const raw = sessionStorage.getItem(AUTH_KEY)
    if (!raw) return
    const parsed = JSON.parse(raw) as Record<string, any>
    // Validate minimum fields:
    //   id          — user exists on server
    //   is_active   — account still enabled
    //   _logged_in_at — client-side timestamp; undefined = stale session from
    //                   a previous code version, must force re-login
    const isValid =
      parsed?.id &&
      parsed?.is_active === true &&
      typeof parsed._logged_in_at === 'string'

    if (isValid) {
      authData.value = parsed
      // DEV: dump exactly what was restored so the developer can see every field
      console.group('%c[POE AUTH] Session restored from sessionStorage', 'color:#0066CC;font-weight:700;font-size:13px')
      console.log('%c── users table fields ──', 'color:#0066CC;font-weight:600')
      console.table({
        id:                parsed.id,
        role_key:          parsed.role_key,
        full_name:         parsed.full_name,
        name:              parsed.name,
        username:          parsed.username,
        email:             parsed.email,
        phone:             parsed.phone,
        country_code:      parsed.country_code,
        is_active:         parsed.is_active,
        last_login_at:     parsed.last_login_at,
        created_at:        parsed.created_at,
        updated_at:        parsed.updated_at,
        email_verified_at: parsed.email_verified_at,
      })
      console.log('%c── assignment (primary) ──', 'color:#E83E8C;font-weight:600', parsed.assignment ?? 'none')
      console.log('%c── all_assignments ──', 'color:#FD7E14;font-weight:600', parsed.all_assignments ?? [])
      console.log('%c── geographic shortcuts ──', 'color:#28A745;font-weight:600', {
        poe_code:      parsed.poe_code,
        district_code: parsed.district_code,
        province_code: parsed.province_code,
        pheoc_code:    parsed.pheoc_code,
      })
      console.log('%c── _permissions ──', 'color:#6F42C1;font-weight:600', parsed._permissions)
      console.log('%c── _logged_in_at ──', 'color:#17A2B8;font-weight:600', parsed._logged_in_at)
      console.groupEnd()
    } else {
      // Stale or invalid session — clear and force re-login
      if (parsed) {
        console.warn('[POE AUTH] Stale/invalid session cleared', {
          had_id:            !!parsed.id,
          had_is_active:     parsed.is_active,
          had_logged_in_at:  parsed._logged_in_at,
          reason: !parsed._logged_in_at
            ? '_logged_in_at missing — session predates current code version'
            : !parsed.is_active
            ? 'is_active is false'
            : 'id missing',
        })
      }
      sessionStorage.removeItem(AUTH_KEY)
    }
  } catch {
    sessionStorage.removeItem(AUTH_KEY)
  }
})

// ─── Login ────────────────────────────────────────────────────────────────────
async function submitLogin(): Promise<void> {
  loginError.value       = ''
  loginErrorDetail.value = ''

  const loginVal    = loginForm.value.login.trim()
  const passwordVal = loginForm.value.password

  if (!loginVal || !passwordVal) {
    loginError.value = 'Please enter your username and password.'
    return
  }

  loginLoading.value = true

  // ── PATH A: Online — try server first ────────────────────────────────────────
  if (navigator.onLine) {
    try {
      const ctrl = new AbortController()
      const tid  = window.setTimeout(() => ctrl.abort(), 10_000) // 10s hard timeout

      const res = await fetch(`${window.SERVER_URL}/auth/login`, {
        method:  'POST',
        headers: {
          'Content-Type':     'application/json',
          'Accept':           'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body:   JSON.stringify({ login: loginVal, password: passwordVal }),
        signal: ctrl.signal,
      })
      window.clearTimeout(tid)

      const body = await res.json() as {
        success: boolean
        message: string
        data?:   Record<string, any>
        error?:  Record<string, any>
      }

      // DEV: log the raw server response BEFORE any processing
      console.group(`%c[POE AUTH] Raw API response  HTTP ${res.status}`, `color:${res.ok ? '#0066CC' : '#DC3545'};font-weight:700;font-size:13px`)
      console.log('success:', body.success, '| message:', body.message)
      if (body.data)  console.table(body.data)
      if (body.error) console.error('error detail:', body.error)
      console.groupEnd()

      if (!body.success) {
        loginError.value = body.message ?? 'Login failed.'
        if (body.error) {
          const e = body.error
          const detail = e.reason
                      ?? e.hint
                      ?? (e.validation_errors ? Object.values(e.validation_errors).flat().join(' ') : null)
                      ?? e.message
                      ?? null
          loginErrorDetail.value = detail ? String(detail) : ''
        }
        return
      }

      // ── ONLINE SUCCESS ────────────────────────────────────────────────────────
      const userData = body.data!
      const payload: Record<string, any> = {
        ...userData,
        _permissions:  derivePermissions(userData.role_key ?? null),
        _logged_in_at: new Date().toISOString(),
      }

      // Persist AUTH_DATA — single source of truth for auth state in this session
      sessionStorage.setItem(AUTH_KEY, JSON.stringify(payload))

      // Cache credentials for future offline logins (SHA-256 hash — no plaintext)
      await cacheOfflineCredentials(loginVal, passwordVal, payload)

      // DEV: full dump so developers can see every field
      console.group('%c[POE AUTH] LOGIN OK (online) — AUTH_DATA written', 'color:#28A745;font-weight:700;font-size:13px')
      console.table({ id: userData.id, role_key: userData.role_key, full_name: userData.full_name, username: userData.username, poe_code: userData.poe_code ?? null, district_code: userData.district_code ?? null })
      if (!userData.assignment) {
        console.warn('%c⚠ ASSIGNMENT MISSING — user id=' + userData.id, 'color:#DC3545;font-weight:700', '\nRun: INSERT INTO user_assignments (user_id,country_code,...) VALUES (' + userData.id + ",...);" )
      } else {
        console.log('%c── assignment ──', 'color:#E83E8C;font-weight:600', userData.assignment)
      }
      console.log('%c── _permissions ──', 'color:#6F42C1;font-weight:600', payload._permissions)
      console.groupEnd()

      finishLogin(payload)
      return

    } catch (err: unknown) {
      // Network error or AbortError — fall through to offline path
      const isTimeout = err instanceof Error && err.name === 'AbortError'
      console.warn('%c[POE AUTH] Online login failed, trying offline cache', 'color:#F59E0B;font-weight:600', isTimeout ? '(timed out)' : err)
    }
  }

  // ── PATH B: Offline (or online-but-server-unreachable fallback) ───────────────
  // Only attempt if the device is actually offline or the server timed out.
  // This allows officers to keep working through temporary connectivity gaps.
  const offlinePayload = await tryOfflineLogin(loginVal, passwordVal)

  if (offlinePayload) {
    // Refresh the timestamp so session validation passes
    const payload = {
      ...offlinePayload,
      _logged_in_at:      new Date().toISOString(),
      _offline_login:     true,   // flag — views can detect and show an offline banner
      // Re-derive permissions in case the role_key was updated since last cache
      _permissions:       derivePermissions(offlinePayload.role_key ?? null),
    }
    // Write to sessionStorage — same shape as online login, fully compatible with
    // every view that reads AUTH_DATA. No view changes needed.
    sessionStorage.setItem(AUTH_KEY, JSON.stringify(payload))

    console.group('%c[POE AUTH] LOGIN OK (offline cache)', 'color:#10B981;font-weight:700;font-size:13px')
    console.log('%c── user ──', 'color:#10B981;font-weight:600', { id: payload.id, role_key: payload.role_key, full_name: payload.full_name })
    console.log('%c── offline_login flag ──', 'color:#F59E0B;font-weight:600', true)
    console.groupEnd()

    finishLogin(payload)
    return
  }

  // ── PATH C: Nothing worked ────────────────────────────────────────────────────
  if (!navigator.onLine) {
    loginError.value       = 'You are offline and no cached credentials were found.'
    loginErrorDetail.value = 'Connect to the internet and sign in once to enable offline login on this device.'
  } else {
    loginError.value       = 'Unable to reach the server.'
    loginErrorDetail.value = 'Check your network connection and try again.'
  }

  loginLoading.value = false
}

/** Apply a successful auth payload — same steps whether online or offline. */
function finishLogin(payload: Record<string, any>): void {
  authData.value = payload

  loginForm.value        = { login: '', password: '' }
  loginError.value       = ''
  loginErrorDetail.value = ''
  showPw.value           = false
  loginLoading.value     = false

  void router.replace('/home')
}
</script>

<!-- ══════════════════════════════════════════════════════════════════════════
  GLOBAL — SSOT design tokens + light-mode enforcement
  color-scheme: light forced on all Ionic elements.
  No dark backgrounds defined anywhere in this file.
══════════════════════════════════════════════════════════════════════════ -->
<style>
/* ═══════════════════════════════════════════════════════════════════════
   DESIGN SYSTEM — SSOT TOKENS
   Light theme enforced globally. Color scheme preserved exactly.
   No dark mode anywhere.
═══════════════════════════════════════════════════════════════════════ */
ion-app, ion-menu, ion-page, ion-content,
ion-header, ion-toolbar, ion-footer,
ion-item, ion-list, ion-card { color-scheme: light !important; }

:root {
  /* ── Ionic overrides — always light ── */
  --ion-background-color:     #FFFFFF;
  --ion-background-color-rgb: 255,255,255;
  --ion-text-color:           #1A1A1A;
  --ion-text-color-rgb:       26,26,26;
  --ion-border-color:         #E0E0E0;
  --ion-item-background:      #FFFFFF;
  --ion-toolbar-background:   #FFFFFF;
  --ion-tab-bar-background:   #FFFFFF;
  --ion-card-background:      #FFFFFF;

  /* ── Brand primary ── */
  --ion-color-primary:          #0066CC;
  --ion-color-primary-rgb:      0,102,204;
  --ion-color-primary-contrast: #FFFFFF;
  --ion-color-primary-shade:    #005AB3;
  --ion-color-primary-tint:     #EBF3FF;

  /* ── Status palette ── */
  --ion-color-success:       #28A745;
  --ion-color-success-tint:  #E8F5E9;
  --ion-color-warning:       #FFC107;
  --ion-color-warning-tint:  #FFF8E1;
  --ion-color-warning-shade: #E6AD06;
  --ion-color-danger:        #DC3545;
  --ion-color-danger-tint:   #FFEBEE;
  --ion-color-danger-shade:  #C82333;
  --ion-color-info:          #17A2B8;

  /* ── Sync state colours ── */
  --clr-synced:  #28A745;
  --clr-queued:  #E6AD06;
  --clr-syncing: #2196F3;
  --clr-failed:  #DC3545;
  --clr-offline: #9E9E9E;

  /* ── Spacing scale ── */
  --sp-xs: 4px;
  --sp-sm: 8px;
  --sp-md: 16px;
  --sp-lg: 24px;
  --sp-xl: 32px;

  /* ── Radius ── */
  --r-sm:   4px;
  --r-md:   8px;
  --r-lg:   12px;
  --r-xl:   16px;
  --r-full: 9999px;

  /* ── Menu surface tokens ── */
  --mn-bg:          #FFFFFF;
  --mn-bg2:         #F8FAFC;
  --mn-border:      #EEF2F7;
  --mn-accent:      #0066CC;
  --mn-accent-bg:   #EBF3FF;
  --mn-text:        #0F172A;
  --mn-sub:         #64748B;
  --mn-micro:       #94A3B8;
  --mn-item-active: #EBF3FF;
  --mn-item-hover:  #F8FAFC;
  --mn-gt-color:    #94A3B8;
  --mn-width:       min(86vw, 304px);
  --mn-shadow:      0 24px 48px rgba(15,23,42,.08), 0 4px 12px rgba(0,102,204,.06);

  /* ── Identity panel ── */
  --ip-bg:        #F0F6FF;
  --ip-bg2:       #EBF3FF;
  --ip-border:    #D4E6FF;
  --ip-av-ring:   linear-gradient(135deg, #0066CC 0%, #1A75D1 50%, #17A2B8 100%);
}

*, *::before, *::after { box-sizing: border-box; }

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration:  .01ms !important;
    transition-duration: .01ms !important;
  }
}

/* ═══════════════════════════════════════════════════════════════════════
   LOGIN OVERLAY — plain fixed div, position:fixed inset:0 z-index:99999
   No IonModal. No Web Component. No shadow DOM. No CSS variable fighting.
   This is a regular HTML div that the browser paints over everything else.
   v-if="!isAuthenticated" removes it from the DOM when login succeeds.
═══════════════════════════════════════════════════════════════════════ */

/* ── THE overlay: covers 100% of the viewport, blocks all interaction below ── */
.lm-overlay {
  position: fixed !important;
  inset: 0 !important;
  z-index: 99999 !important;
  width: 100vw !important;
  height: 100vh !important;
  height: 100dvh !important;        /* dynamic viewport height on mobile */
  background: #080C1E;
  overflow: hidden;
  pointer-events: all;              /* block ALL clicks/taps on content below */
  display: flex;
  flex-direction: column;
  align-items: stretch;
}

/* FIX 4: Atmospheric background — NO blur animation, static blur only.
   Orbs render once; opacity breathe is cheap transform-only. */
.lm-atm { position:absolute; inset:0; overflow:hidden; z-index:0; pointer-events:none; }
.lm-orb {
  position:absolute; border-radius:50%;
  /* Static blur — rendered once by compositor, NOT re-painted every frame */
  filter:blur(80px);
  will-change:opacity;
  animation:lm-orb-fade 10s ease-in-out infinite;
}
.lm-orb--1 {
  width:min(360px,100vw); height:min(360px,100vw);
  background:radial-gradient(ellipse,rgba(37,99,235,.22) 0%,transparent 65%);
  top:-80px; left:-60px; animation-duration:9s;
}
.lm-orb--2 {
  width:min(260px,80vw); height:min(260px,80vw);
  background:radial-gradient(ellipse,rgba(6,182,212,.14) 0%,transparent 65%);
  bottom:-40px; right:-40px; animation-delay:-4s; animation-duration:12s;
}
.lm-orb--3 {
  width:min(200px,60vw); height:min(200px,60vw);
  background:radial-gradient(ellipse,rgba(124,58,237,.1) 0%,transparent 65%);
  top:40%; right:5%; animation-delay:-7s; animation-duration:14s;
}
/* Cheap opacity pulse — only opacity, no layout/paint */
@keyframes lm-orb-fade { 0%,100%{opacity:.8} 50%{opacity:.4} }

/* Subtle grid — no animation, static background-image */
.lm-grid {
  position:absolute; inset:0;
  background-image:
    linear-gradient(rgba(99,130,200,.04) 1px,transparent 1px),
    linear-gradient(90deg,rgba(99,130,200,.04) 1px,transparent 1px);
  background-size:32px 32px;
}

/* ── Root: fills the overlay completely, flex column, NO scroll ── */
.lm-root {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  /* Fill the parent .lm-overlay which is already position:fixed inset:0 */
  width: 100%;
  height: 100%;
  overflow: hidden;
  padding: 0 16px;
  box-sizing: border-box;
}

/* Safe-area spacers — push content away from notch/chin */
.lm-safe-top    { flex-shrink:0; height:max(env(safe-area-inset-top,0px),16px); width:100%; }
.lm-safe-bottom { flex-shrink:0; height:max(env(safe-area-inset-bottom,0px),8px); width:100%; }

/* ── Brand block: takes as little space as it needs ── */
.lm-brand {
  flex-shrink:0;
  display:flex; flex-direction:column; align-items:center;
  text-align:center;
  width:100%;
  padding-top:clamp(8px,2.5vh,24px);
  padding-bottom:clamp(6px,1.5vh,16px);
  animation:lm-up .6s cubic-bezier(.16,1,.3,1) both;
}

/* Shield icon — opacity-only animation (no box-shadow animation = no repaints) */
.lm-shield-wrap {
  position:relative; width:68px; height:68px;
  margin:0 auto clamp(8px,1.8vh,14px); flex-shrink:0;
}
.lm-pulse-ring {
  position:absolute; inset:-9px; border-radius:50%;
  border:1.5px solid rgba(37,99,235,.35);
  animation:lm-ring 2.5s cubic-bezier(.215,.61,.355,1) infinite;
  will-change:transform,opacity;
}
.lm-pulse-ring--2 {
  inset:-17px; border-width:1px; border-color:rgba(37,99,235,.18);
  animation-delay:.6s;
}
@keyframes lm-ring { 0%{transform:scale(1);opacity:.6} 100%{transform:scale(1.5);opacity:0} }
.lm-shield-icon {
  width:68px; height:68px; border-radius:18px;
  background:linear-gradient(145deg,#0F2057 0%,#1E3A8A 45%,#1D4ED8 100%);
  border:1px solid rgba(99,157,255,.22);
  display:flex; align-items:center; justify-content:center;
  /* Static glow via box-shadow — NOT animated (avoids repaint loop) */
  box-shadow:0 0 28px rgba(37,99,235,.45),0 0 56px rgba(37,99,235,.15);
  position:relative; overflow:hidden;
}
.lm-shield-icon::before {
  content:''; position:absolute; inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,.1),transparent 50%);
  pointer-events:none;
}
.lm-shield-svg { position:relative; z-index:1; width:38px; height:42px; }

.lm-tagline {
  font-size:9px; font-weight:700; letter-spacing:3px;
  text-transform:uppercase; color:#3B82F6; margin-bottom:3px;
}
.lm-name {
  font-size:clamp(20px,5vw,28px); font-weight:800; color:#FFFFFF;
  letter-spacing:-.5px; line-height:1.05; margin:0 0 2px;
}
.lm-name-accent { color:#60A5FA; }
.lm-sub { font-size:11px; font-weight:400; color:#4A6FA5; letter-spacing:.3px; margin-bottom:0; }

/* IHR badge row */
.lm-ihr-row {
  display:flex; align-items:center; gap:6px;
  margin-top:clamp(5px,1.2vh,10px); flex-wrap:wrap; justify-content:center;
}
.lm-ihr-badge {
  display:flex; align-items:center; gap:5px; padding:3px 9px;
  border-radius:20px; background:rgba(37,99,235,.1); border:1px solid rgba(37,99,235,.2);
}
.lm-ihr-dot { width:5px; height:5px; border-radius:50%; flex-shrink:0; }
.lm-ihr-dot--green { background:#10B981; }
.lm-ihr-dot--blue  { background:#3B82F6; }
.lm-ihr-dot--amber { background:#F59E0B; }
.lm-ihr-txt { font-size:9px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#60A5FA; }
.lm-ihr-div { width:1px; height:14px; background:rgba(99,130,200,.2); }

/* Offline banner */
.lm-offline-banner {
  flex-shrink:0; width:100%; max-width:440px;
  display:flex; align-items:center; gap:10px;
  background:rgba(16,185,129,.07); border:1px solid rgba(16,185,129,.2);
  border-radius:12px; padding:8px 14px; margin-bottom:8px;
  animation:lm-up .5s cubic-bezier(.16,1,.3,1) both;
}
.lm-ob-icon { width:28px; height:28px; border-radius:8px; background:rgba(16,185,129,.12); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.lm-ob-icon svg { width:14px; height:14px; }
.lm-ob-title { font-size:11.5px; font-weight:700; color:#34D399; }
.lm-ob-sub   { font-size:10px; color:#1D6B4A; margin-top:1px; }

/* ── Form card ── */
.lm-card {
  flex-shrink:0; width:100%; max-width:440px;
  background:linear-gradient(160deg,rgba(14,22,48,.97) 0%,rgba(8,14,34,.99) 100%);
  border:1px solid rgba(99,130,200,.14);
  border-radius:22px;
  padding:clamp(16px,2.8vh,26px) clamp(16px,4vw,24px) clamp(14px,2.2vh,22px);
  position:relative; overflow:hidden;
  /* Static shadow — no animation */
  box-shadow:0 20px 48px rgba(0,0,0,.55), inset 0 1px 0 rgba(255,255,255,.05);
  animation:lm-up .6s .08s cubic-bezier(.16,1,.3,1) both;
}
.lm-card::before {
  content:''; position:absolute; inset:0; border-radius:22px;
  background:linear-gradient(145deg,rgba(37,99,235,.05),transparent 50%);
  pointer-events:none;
}
.lm-card-shimmer {
  position:absolute; top:0; left:10%; right:10%; height:1px;
  background:linear-gradient(90deg,transparent,rgba(96,165,250,.55),rgba(129,140,248,.45),transparent);
}

.lm-card-hdr { margin-bottom:clamp(12px,2vh,18px); }
.lm-card-title { font-size:17px; font-weight:800; color:#E8EDF8; letter-spacing:-.3px; margin:0 0 2px; }
.lm-card-sub   { font-size:11.5px; color:#4A6FA5; font-weight:400; letter-spacing:.2px; margin:0; }

/* Error */
.lm-err {
  display:flex; align-items:flex-start; gap:10px;
  background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.22);
  border-radius:12px; padding:10px 13px; margin-bottom:12px;
  animation:lm-shake .4s cubic-bezier(.36,.07,.19,.97);
}
@keyframes lm-shake { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-6px)} 40%{transform:translateX(6px)} 60%{transform:translateX(-4px)} 80%{transform:translateX(4px)} }
.lm-err-ic     { width:16px; height:16px; flex-shrink:0; margin-top:1px; }
.lm-err-body   { display:flex; flex-direction:column; gap:2px; }
.lm-err-msg    { font-size:12.5px; font-weight:600; color:#FCA5A5; }
.lm-err-detail { font-size:11px; color:rgba(252,165,165,.65); line-height:1.4; }

/* ── Form ── */
.lm-form { display:flex; flex-direction:column; gap:clamp(10px,1.8vh,14px); }

/* FIX 2: Field group — label ABOVE the input box, not stacked inside.
   This is the correct pattern for IonInput which has its own internal height.
   .lm-fg  = field group wrapper (label on top, input row below)
   .lm-field = the actual input row (icon + IonInput + optional eye button)
   No fixed height on .lm-field — it sizes to its content. */
.lm-fg { display:flex; flex-direction:column; gap:5px; }

.lm-field-label {
  /* Label sits above the input box, clearly readable */
  font-size:10px; font-weight:700; letter-spacing:.9px;
  text-transform:uppercase; color:rgba(74,111,165,.7);
  padding-left:2px; transition:color .2s;
  line-height:1;
}
.lm-field--focus  .lm-field-label,
.lm-field--filled .lm-field-label { color:rgba(96,165,250,.8); }

.lm-field {
  display:flex; align-items:center;
  background:rgba(8,12,28,.9); border:1.5px solid rgba(37,78,140,.3);
  border-radius:14px; padding:0 0 0 14px;
  /* height:auto — grows with IonInput's native height, no clipping */
  min-height:52px;
  transition:border-color .2s,box-shadow .2s;
}
.lm-field--focus  { border-color:rgba(37,99,235,.7); box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.lm-field--filled { border-color:rgba(37,99,235,.35); }
.lm-field--error  { border-color:rgba(239,68,68,.4) !important; }

.lm-field-ic { width:18px; height:18px; flex-shrink:0; margin-right:10px; color:rgba(99,130,200,.45); transition:color .2s; }
.lm-field-ic svg { width:18px; height:18px; stroke:currentColor; display:block; }
.lm-field--focus  .lm-field-ic,
.lm-field--filled .lm-field-ic { color:rgba(96,165,250,.8); }

/* IonInput — takes remaining width, no extra wrapper div needed */
.lm-field-input {
  flex:1;
  --background:transparent; --border-width:0;
  --padding-start:0; --padding-end:0;
  --padding-top:14px; --padding-bottom:14px;
  --placeholder-color:rgba(46,66,104,.8);
  --color:#C4D0E8;
  font-size:15px; font-weight:500; caret-color:#60A5FA;
  min-height:52px;
}

/* Eye toggle — full height of the field */
.lm-eye {
  background:transparent; border:none; cursor:pointer;
  width:44px; min-height:52px;
  display:flex; align-items:center; justify-content:center; flex-shrink:0;
  color:rgba(99,130,200,.4); transition:color .2s;
}
.lm-eye svg { width:17px; height:17px; stroke:currentColor; display:block; }
.lm-eye:hover { color:rgba(96,165,250,.8); }

/* Submit */
.lm-submit {
  width:100%; height:52px;
  border:none; border-radius:16px;
  background:linear-gradient(135deg,#1D4ED8 0%,#2563EB 50%,#3B82F6 100%);
  cursor:pointer; position:relative; overflow:hidden;
  box-shadow:0 6px 20px rgba(37,99,235,.4),0 2px 8px rgba(0,0,0,.3);
  transition:transform .15s,box-shadow .15s,opacity .15s;
  margin-top:clamp(4px,1vh,8px);
}
.lm-submit::before {
  content:''; position:absolute; inset:0;
  background:linear-gradient(180deg,rgba(255,255,255,.12),transparent 60%);
  pointer-events:none;
}
.lm-submit:not(:disabled):active { transform:scale(.98); box-shadow:0 4px 12px rgba(37,99,235,.3); }
.lm-submit:disabled    { opacity:.4; cursor:not-allowed; }
.lm-submit--loading    { opacity:1 !important; cursor:wait; }
.lm-submit-inner { display:flex; align-items:center; justify-content:center; gap:8px; position:relative; z-index:1; }
.lm-submit-txt   { font-size:14px; font-weight:800; color:#fff; letter-spacing:1px; text-transform:uppercase; }
.lm-submit-arrow { width:18px; height:18px; transition:transform .2s; }
.lm-submit:not(:disabled):hover .lm-submit-arrow { transform:translateX(3px); }
.lm-dots { display:flex; align-items:center; justify-content:center; gap:6px; position:relative; z-index:1; }
.lm-dot  { width:7px; height:7px; border-radius:50%; background:rgba(255,255,255,.9); animation:lm-dot 1.2s ease-in-out infinite; }
.lm-dot:nth-child(2){ animation-delay:.2s } .lm-dot:nth-child(3){ animation-delay:.4s }
@keyframes lm-dot { 0%,80%,100%{transform:scale(.6);opacity:.4} 40%{transform:scale(1);opacity:1} }

/* ── Trust strip ── */
.lm-trust {
  flex-shrink:0; width:100%; max-width:440px;
  display:flex; align-items:center; gap:10px;
  background:rgba(10,18,40,.65); border:1px solid rgba(37,78,140,.18);
  border-radius:14px; padding:10px 14px;
  margin-top:clamp(8px,1.5vh,12px);
  animation:lm-up .6s .2s cubic-bezier(.16,1,.3,1) both;
}
.lm-trust-icon { width:32px; height:32px; border-radius:9px; background:rgba(37,99,235,.1); border:1px solid rgba(37,99,235,.2); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.lm-trust-icon svg { width:15px; height:15px; }
.lm-trust-info { flex:1; min-width:0; }
.lm-trust-title { font-size:11px; font-weight:700; color:#8BA4C8; margin:0; }
.lm-trust-sub   { font-size:10px; color:#2E4268; margin:1px 0 0; }
.lm-trust-badge { flex-shrink:0; font-size:9px; font-weight:800; letter-spacing:.8px; text-transform:uppercase; padding:3px 9px; border-radius:6px; background:rgba(16,185,129,.1); color:#10B981; border:1px solid rgba(16,185,129,.2); }

/* ── Footer ── */
.lm-foot {
  flex-shrink:0;
  display:flex; align-items:center; justify-content:center; gap:8px;
  font-size:9.5px; color:#1E3060; font-weight:500; letter-spacing:.3px;
  margin-top:clamp(6px,1vh,10px);
  animation:lm-up .6s .28s cubic-bezier(.16,1,.3,1) both;
}
.lm-foot-dot { color:rgba(37,78,140,.35); }

/* ── Shared entry animation — only opacity + translateY (cheap) ── */
@keyframes lm-up { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

/* ── Large screen: centre the card vertically ── */
@media (min-width:600px) {
  .lm-root { justify-content:center; padding:0 24px; }
  .lm-brand { padding-top:0; }
}
</style>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════
   SIDE MENU — PREMIUM LIGHT DESIGN
   All class names unchanged. Pure CSS upgrade.
═══════════════════════════════════════════════════════════════════════ */

ion-menu { --width: var(--mn-width); }

.menu-content {
  --background:    var(--mn-bg);
  --padding-start: 0;
  --padding-end:   0;
  --padding-top:   0;
  --padding-bottom:0;
}

/* ═══════════════════════════════════════════════════════════
   IDENTITY PANEL — premium frosted header
═══════════════════════════════════════════════════════════ */
.ip {
  position:         relative;
  background:       var(--ip-bg);
  padding-top:      env(safe-area-inset-top, 0px);
  overflow:         hidden;
}

/* Gradient accent bar — thicker, more vivid */
.ip__bar {
  height:     4px;
  background: linear-gradient(90deg, #0066CC 0%, #1A75D1 40%, #17A2B8 100%);
  box-shadow: 0 1px 8px rgba(0,102,204,.35);
}

/* Subtle diagonal shine overlay */
.ip::after {
  content:    '';
  position:   absolute;
  top:        0; left:0; right:0;
  height:     100%;
  background: linear-gradient(135deg, rgba(255,255,255,.55) 0%, transparent 60%);
  pointer-events: none;
}

.ip__body {
  display:     flex;
  align-items: center;
  gap:         12px;
  padding:     16px 16px 12px;
  position:    relative;
  z-index:     1;
}

/* ── Avatar ── */
.ip__av-wrap {
  position:    relative;
  width:       52px;
  height:      52px;
  flex-shrink: 0;
}

/* Gradient ring around avatar */
.ip__av-wrap::before {
  content:       '';
  position:      absolute;
  inset:         -3px;
  border-radius: 50%;
  background:    var(--ip-av-ring);
  padding:       2px;
  mask:          linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  mask-composite:exclude;
  -webkit-mask:  linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
}

.ip__av {
  width:           52px;
  height:          52px;
  border-radius:   50%;
  background:      linear-gradient(135deg, #0066CC, #1A75D1);
  display:         flex;
  align-items:     center;
  justify-content: center;
  box-shadow:      0 4px 12px rgba(0,102,204,.3), 0 0 0 3px #fff;
}

.ip__initials {
  font-size:      17px;
  font-weight:    800;
  color:          #fff;
  letter-spacing: .5px;
  user-select:    none;
  line-height:    1;
}

/* Status dot — sits outside the ring */
.ip__dot {
  position:      absolute;
  bottom:        2px;
  right:         2px;
  width:         13px;
  height:        13px;
  border-radius: 50%;
  border:        2.5px solid #fff;
  box-shadow:    0 1px 4px rgba(0,0,0,.2);
  z-index:       2;
}
.ip__dot--synced   { background: var(--clr-synced);  }
.ip__dot--unsynced { background: var(--clr-queued);  }
.ip__dot--syncing  { background: var(--clr-syncing); animation: pulse-dot 1.4s ease-in-out infinite; }
.ip__dot--failed   { background: var(--clr-failed);  }
.ip__dot--offline  { background: var(--clr-offline); }
@keyframes pulse-dot {
  0%,100% { box-shadow: 0 1px 4px rgba(0,0,0,.2), 0 0 0 0   rgba(33,150,243,.5); }
  50%     { box-shadow: 0 1px 4px rgba(0,0,0,.2), 0 0 0 5px rgba(33,150,243,0);  }
}

/* ── User info ── */
.ip__info { flex:1; min-width:0; position:relative; z-index:1; }

.ip__name {
  margin:         0 0 4px;
  font-size:      14px;
  font-weight:    700;
  color:          var(--mn-text);
  white-space:    nowrap;
  overflow:       hidden;
  text-overflow:  ellipsis;
  letter-spacing: -.2px;
}

.ip__role {
  display:        inline-flex;
  align-items:    center;
  margin-bottom:  4px;
  padding:        3px 9px;
  border-radius:  var(--r-full);
  font-size:      9px;
  font-weight:    800;
  letter-spacing: .8px;
  text-transform: uppercase;
  color:          #fff;
  box-shadow:     0 2px 6px rgba(0,0,0,.2);
}
.ip__role--poe-primary         { background: linear-gradient(135deg,#0066CC,#1A75D1); }
.ip__role--poe-secondary       { background: linear-gradient(135deg,#17A2B8,#0d8fa4); }
.ip__role--poe-data-officer    { background: linear-gradient(135deg,#6F42C1,#5a32a3); }
.ip__role--poe-admin           { background: linear-gradient(135deg,#E83E8C,#d4286e); }
.ip__role--district-supervisor { background: linear-gradient(135deg,#28A745,#1e8435); }
.ip__role--pheoc-officer       { background: linear-gradient(135deg,#FD7E14,#e56d05); }
.ip__role--national-admin      { background: linear-gradient(135deg,#DC3545,#c82333); }
.ip__role--screener            { background: linear-gradient(135deg,#0066CC,#1A75D1); }

.ip__scope {
  margin:         0;
  font-size:      9px;
  font-weight:    600;
  color:          var(--mn-sub);
  text-transform: uppercase;
  letter-spacing: .6px;
  font-family:    'Courier New', monospace;
}

/* ── Sync strip ── */
.ip__sync {
  display:           flex;
  align-items:       center;
  gap:               8px;
  width:             100%;
  padding:           9px 16px;
  border:            none;
  border-top:        1px solid var(--ip-border);
  background:        rgba(235,243,255,.5);
  cursor:            pointer;
  font-family:       inherit;
  position:          relative;
  z-index:           1;
  -webkit-tap-highlight-color: transparent;
  transition:        background .15s;
}
.ip__sync:hover  { background: rgba(0,102,204,.05); }
.ip__sync:active { background: rgba(0,102,204,.1); }

.ip__sync-icon  { width:14px; height:14px; flex-shrink:0; }
.ip__sync-label { font-size:10px; font-weight:800; letter-spacing:.6px; text-transform:uppercase; flex:1; }

.ip__sync-ct {
  font-size:     9px;
  font-weight:   700;
  padding:       2px 8px;
  border-radius: var(--r-full);
  background:    linear-gradient(135deg,#FFF8E1,#FFF3CD);
  color:         #7A5000;
  border:        1px solid rgba(230,173,6,.4);
  box-shadow:    0 1px 3px rgba(230,173,6,.2);
}
.ip__sync--synced   .ip__sync-icon, .ip__sync--synced   .ip__sync-label { color: var(--clr-synced);  }
.ip__sync--unsynced .ip__sync-icon, .ip__sync--unsynced .ip__sync-label { color: #9A6A00; }
.ip__sync--syncing  .ip__sync-icon, .ip__sync--syncing  .ip__sync-label { color: var(--clr-syncing); }
.ip__sync--failed   .ip__sync-icon, .ip__sync--failed   .ip__sync-label { color: var(--clr-failed);  }
.ip__sync--offline  .ip__sync-icon, .ip__sync--offline  .ip__sync-label { color: var(--clr-offline); }

/* ═══════════════════════════════════════════════════════════
   NAVIGATION — premium items
═══════════════════════════════════════════════════════════ */
.mn {
  padding:    6px 0 32px;
  background: var(--mn-bg);
}

.mn__group { padding: 2px 0; }

/* Hairline separator between groups — refined */
.mn__group + .mn__group {
  border-top:  1px solid var(--mn-border);
  margin-top:  2px;
  padding-top: 4px;
}

/* Group section title */
.mn__gt {
  margin:         0;
  padding:        14px 16px 3px;
  font-size:      8.5px;
  font-weight:    800;
  letter-spacing: 1.8px;
  text-transform: uppercase;
  color:          var(--mn-gt-color);
  font-family:    'Courier New', monospace;
  user-select:    none;
  display:        flex;
  align-items:    center;
  gap:            6px;
}
/* Decorative line after section title */
.mn__gt::after {
  content:    '';
  flex:       1;
  height:     1px;
  background: linear-gradient(90deg, var(--mn-border), transparent);
  margin-left:4px;
}

/* ── Navigation item ── */
.mn__item {
  display:         flex;
  align-items:     center;
  gap:             11px;
  width:           100%;
  padding:         0 12px 0 14px;
  min-height:      50px;
  border:          none;
  border-left:     3px solid transparent;
  background:      transparent;
  cursor:          pointer;
  text-align:      left;
  position:        relative;
  -webkit-tap-highlight-color: transparent;
  transition:      background .14s ease, border-color .14s ease, transform .1s ease;
}

.mn__item:hover {
  background:    var(--mn-item-hover);
  border-left-color: rgba(0,102,204,.2);
}

.mn__item:active { transform: scaleX(.99); }

/* Active state — premium gradient treatment */
.mn__item--active {
  background:        linear-gradient(90deg, rgba(0,102,204,.10) 0%, rgba(0,102,204,.04) 100%);
  border-left-color: var(--mn-accent);
}
.mn__item--active::after {
  content:    '';
  position:   absolute;
  right:      0;
  top:        20%;
  bottom:     20%;
  width:      3px;
  background: linear-gradient(180deg, rgba(0,102,204,.15), rgba(0,102,204,.05));
  border-radius: 2px 0 0 2px;
}
.mn__item--active .mn__icon  { color: var(--mn-accent); }
.mn__item--active .mn__label { color: var(--mn-accent); font-weight: 700; }
.mn__item--active .mn__sub   { color: rgba(0,102,204,.6); }

/* Danger item */
.mn__item--danger .mn__icon,
.mn__item--danger .mn__label { color: var(--ion-color-danger); }
.mn__item--danger .mn__sub   { color: rgba(220,53,69,.6); }
.mn__item--danger:hover      { background: var(--ion-color-danger-tint); border-left-color: var(--ion-color-danger); }

/* ── Icon ── */
.mn__icon {
  width:      20px;
  height:     20px;
  flex-shrink:0;
  color:      var(--mn-sub);
  transition: color .14s, transform .2s;
}
.mn__item:hover .mn__icon    { transform: scale(1.06); }
.mn__item--active .mn__icon  { transform: scale(1.08); }

/* Icon accent colours per section */
.mn__icon--create    { color: #0066CC; }
.mn__icon--secondary { color: #17A2B8; }
.mn__icon--alert     { color: #DC3545; }
.mn__icon--system    { color: #6F42C1; }

/* ── Text block ── */
.mn__text  { flex:1; min-width:0; display:flex; flex-direction:column; gap:1px; }
.mn__label {
  font-size:      13.5px;
  font-weight:    500;
  color:          var(--mn-text);
  line-height:    1.25;
  transition:     color .14s;
  letter-spacing: -.1px;
}
.mn__sub {
  font-size:      10px;
  font-weight:    400;
  color:          var(--mn-sub);
  letter-spacing: .05px;
  white-space:    nowrap;
  overflow:       hidden;
  text-overflow:  ellipsis;
}

/* ── Badges ── */
.mn__badge {
  flex-shrink:     0;
  min-width:       22px;
  height:          20px;
  padding:         0 6px;
  border-radius:   var(--r-full);
  font-size:       10px;
  font-weight:     800;
  display:         inline-flex;
  align-items:     center;
  justify-content: center;
  letter-spacing:  .2px;
  box-shadow:      0 2px 6px rgba(0,0,0,.12);
}
.mn__badge--danger  { background: var(--ion-color-danger);  color: #fff; }
.mn__badge--warning { background: var(--ion-color-warning-tint); color: #7A5000; border:1px solid var(--ion-color-warning-shade); }
.mn__badge--primary { background: var(--ion-color-primary); color: #fff; }
.mn__badge--sync    {
  background:   linear-gradient(135deg, #EBF3FF, #DCE9FF);
  color:        var(--mn-accent);
  border:       1px solid rgba(0,102,204,.25);
  box-shadow:   0 1px 4px rgba(0,102,204,.1);
}

/* ── Tags ── */
.mn__tag {
  flex-shrink:    0;
  font-size:      8px;
  font-weight:    800;
  letter-spacing: .5px;
  text-transform: uppercase;
  padding:        3px 7px;
  border-radius:  var(--r-full);
  font-family:    'Courier New', monospace;
}
.mn__tag--speed {
  background:  linear-gradient(135deg, #EBF3FF, #DCE9FF);
  color:       var(--mn-accent);
  border:      1px solid rgba(0,102,204,.2);
  box-shadow:  0 1px 3px rgba(0,102,204,.1);
}
.mn__tag--restricted {
  background: rgba(111,66,193,.08);
  color:      #6F42C1;
  border:     1px solid rgba(111,66,193,.2);
}
.mn__tag--primary {
  background: rgba(0,102,204,.08);
  color:      #0066CC;
  border:     1px solid rgba(0,102,204,.2);
}
.mn__tag--default {
  background: var(--mn-item-hover);
  color:      var(--mn-sub);
  border:     1px solid var(--mn-border);
}

/* ═══════════════════════════════════════════════════════════
   MENU FOOTER — build info strip
═══════════════════════════════════════════════════════════ */
.mf {
  padding:    6px 16px calc(12px + env(safe-area-inset-bottom, 0px));
  background: var(--mn-bg2);
  border-top: 1px solid var(--mn-border);
}
.mf__div    { display:none; }   /* replaced by border-top above */
.mf__row    { display:flex; justify-content:space-between; align-items:center; margin-bottom:3px; }
.mf__k      { font-size:7px; font-weight:800; letter-spacing:1.4px; text-transform:uppercase; color:var(--mn-micro); font-family:'Courier New',monospace; }
.mf__v      { font-size:9px; color:var(--mn-micro); }
.mf__mono   { font-family:'Courier New',monospace; letter-spacing:.6px; }

/* ═══════════════════════════════════════════════════════════
   KEYFRAMES
═══════════════════════════════════════════════════════════ */
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.15} }

/* ═══════════════════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════════════════ */
@media (min-width: 600px) { ion-menu { --width: 308px; } }
</style>
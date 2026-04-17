<template>
  <IonPage>

    <!-- ═══════════════════════════════════════════════════════════════════
         COMMAND HEADER — immersive, data-dense, dark gradient.
         Pattern: same architecture as PrimaryScreening.vue header.
         Contains: identity bar + hero metrics + sparkline + status strip.
    ══════════════════════════════════════════════════════════════════════ -->
    <IonHeader class="cmd-header" :translucent="false">

      <!-- Decorative radial light leaks (pointer-events none, purely visual) -->
      <div class="cmd-hdr-glow" aria-hidden="true" />

      <!-- ── TOP BAR ─────────────────────────────────────────────────── -->
      <div class="cmd-topbar">
        <IonButtons>
          <IonMenuButton menu="app-menu" class="cmd-menu-btn" />
        </IonButtons>

        <div class="cmd-poe-id">
          <span class="cmd-poe-type">{{ poeTypeLabel }} · {{ auth.district_code || auth.country_code }}</span>
          <span class="cmd-poe-name">{{ auth.poe_code || 'POE Sentinel' }}</span>
        </div>

        <div class="cmd-topbar-right">
          <div :class="['cmd-conn', isOnline ? 'cmd-conn--on' : 'cmd-conn--off']">
            <span class="cmd-conn-dot" />
            <span class="cmd-conn-lbl">{{ isOnline ? 'Live' : 'Offline' }}</span>
          </div>
          <button class="cmd-refresh-btn" @click="manualRefresh" :disabled="loading"
            aria-label="Refresh dashboard">
            <svg viewBox="0 0 18 18" fill="none" stroke="rgba(255,255,255,0.7)"
              stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
              :class="loading && 'cmd-spin'">
              <path d="M16 3v5h-5M2 15v-5h5M2.5 9A7 7 0 0115 6.7M15.5 9A7 7 0 013 11.3"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- ── HERO METRICS BLOCK ───────────────────────────────────────── -->
      <div class="cmd-hero-block">

        <!-- Left: today's primary count (the single most important number) -->
        <div class="cmd-hero-left">
          <span class="cmd-hero-eyebrow">Today · IHR Art.23</span>
          <div class="cmd-hero-num-row">
            <span class="cmd-hero-big">
              {{ live?.screened_today ?? screenedToday }}
            </span>
            <div class="cmd-hero-delta-stack" v-if="dayDelta !== null">
              <span :class="['cmd-hero-delta', dayDelta >= 0 ? 'cmd-delta--up' : 'cmd-delta--dn']">
                {{ dayDelta >= 0 ? '▲' : '▼' }}{{ Math.abs(dayDelta) }}
              </span>
              <span class="cmd-hero-delta-sub">vs yesterday</span>
            </div>
          </div>
          <span class="cmd-hero-label">Travelers Screened</span>

          <!-- Mini facts row -->
          <div class="cmd-hero-facts">
            <div class="cmd-fact">
              <span class="cmd-fact-n cmd-fact--symp">{{ live?.symptomatic_today ?? symptomaticToday }}</span>
              <span class="cmd-fact-l">Symptomatic</span>
            </div>
            <div class="cmd-fact-div" />
            <div class="cmd-fact">
              <span class="cmd-fact-n cmd-fact--fever">{{ feverToday }}</span>
              <span class="cmd-fact-l">Fever ≥37.5°</span>
            </div>
            <div class="cmd-fact-div" />
            <div class="cmd-fact">
              <span class="cmd-fact-n cmd-fact--ref">{{ referralsToday }}</span>
              <span class="cmd-fact-l">Referred</span>
            </div>
          </div>
        </div>

        <!-- Right: symptomatic rate donut (pure inline SVG, no library) -->
        <div class="cmd-hero-right">
          <div class="cmd-donut-wrap" role="img"
            :aria-label="`Symptomatic rate ${symptomaticRatePct}%`">
            <svg viewBox="0 0 100 100" class="cmd-donut" aria-hidden="true">
              <!-- Track -->
              <circle cx="50" cy="50" r="40"
                fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="9" />
              <!-- Progress — animated via CSS transition on dashoffset -->
              <circle cx="50" cy="50" r="40"
                fill="none"
                :stroke="donutColor"
                stroke-width="9"
                stroke-linecap="round"
                :stroke-dasharray="DONUT_C"
                :stroke-dashoffset="donutOffset"
                transform="rotate(-90 50 50)"
                class="cmd-donut-arc" />
              <!-- Rate label -->
              <text x="50" y="47" text-anchor="middle"
                font-size="21" font-weight="900" fill="white"
                font-family="system-ui,sans-serif"
                letter-spacing="-0.5">{{ symptomaticRatePct }}</text>
              <text x="50" y="59" text-anchor="middle"
                font-size="7.5" font-weight="700" fill="rgba(255,255,255,0.5)"
                font-family="system-ui,sans-serif" letter-spacing="1.2">%&nbsp;SYMP</text>
            </svg>
          </div>
          <span class="cmd-donut-label">Rate</span>
        </div>
      </div>

      <!-- ── 7-DAY SPARKLINE ─────────────────────────────────────────── -->
      <div class="cmd-spark-wrap" v-if="sparkPoints" aria-hidden="true">
        <span class="cmd-spark-label">7-day trend</span>
        <svg :viewBox="`0 0 ${SPARK_W} ${SPARK_H}`"
          class="cmd-spark-svg" preserveAspectRatio="none">
          <defs>
            <linearGradient id="cmd-sg" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" :stop-color="donutColor" stop-opacity="0.35" />
              <stop offset="100%" :stop-color="donutColor" stop-opacity="0" />
            </linearGradient>
          </defs>
          <path v-if="sparkAreaPath" :d="sparkAreaPath" fill="url(#cmd-sg)" />
          <polyline :points="sparkPoints" fill="none"
            :stroke="donutColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round" />
          <circle v-for="(pt, i) in sparkDots" :key="i"
            :cx="pt.x" :cy="pt.y" r="2.5" :fill="donutColor" />
        </svg>
        <span class="cmd-spark-label" style="text-align:right">
          {{ summary?.week_snapshot?.this_week ?? '—' }} this week
        </span>
      </div>

      <!-- ── OPERATIONAL STATUS STRIP ───────────────────────────────── -->
      <div :class="['cmd-status-strip', `cmd-status--${opStatus.toLowerCase()}`]">
        <div class="cmd-status-left">
          <span class="cmd-status-dot" />
          <span class="cmd-status-txt">{{ opStatus }}</span>
        </div>
        <span class="cmd-status-scope">{{ auth.role_key }}</span>
        <span class="cmd-status-time">{{ liveTimeLabel || currentTime }}</span>
      </div>

    </IonHeader>

    <!-- ═══════════════════════════════════════════════════════════════════
         OPERATIONS BOARD — light background, action-oriented cards.
    ══════════════════════════════════════════════════════════════════════ -->
    <IonContent class="cmd-content" :fullscreen="true">
      <IonRefresher slot="fixed"
        @ionRefresh="ev => { manualRefresh(); ev.target.complete() }">
        <IonRefresherContent pulling-text="Pull to refresh" refreshing-spinner="crescent" />
      </IonRefresher>

      <div class="cmd-board">

        <!-- ── TRIPTYCH: REFERRALS | CASES | ALERTS ──────────────────── -->
        <!-- These three numbers answer: "What needs attention RIGHT NOW?" -->
        <div class="cmd-triptych">

          <button class="cmd-tri" :class="triClass('ref', openReferrals)"
            @click="nav('/NotificationsCenter')" aria-label="Referral queue">
            <div class="cmd-tri-top">
              <span class="cmd-tri-icon" aria-hidden="true">↗</span>
              <span v-if="critReferrals > 0" class="cmd-tri-badge cmd-badge--c">
                {{ critReferrals }}C
              </span>
            </div>
            <span class="cmd-tri-num">{{ live?.open_referrals ?? openReferrals }}</span>
            <span class="cmd-tri-lbl">Referrals</span>
            <span class="cmd-tri-sub">
              {{ openReferrals === 0 ? 'Queue clear' : `${critReferrals} critical` }}
            </span>
          </button>

          <button class="cmd-tri" :class="triClass('case', activeCases)"
            @click="nav('/secondary-screening/records')" aria-label="Active cases">
            <div class="cmd-tri-top">
              <span class="cmd-tri-icon" aria-hidden="true">⚕</span>
              <span v-if="critCases > 0" class="cmd-tri-badge cmd-badge--c">
                {{ critCases }}C
              </span>
            </div>
            <span class="cmd-tri-num">{{ live?.active_cases ?? activeCases }}</span>
            <span class="cmd-tri-lbl">Cases</span>
            <span class="cmd-tri-sub">
              {{ activeCases === 0 ? 'No active' : `${emergencyCases} emergency` }}
            </span>
          </button>

          <button class="cmd-tri" :class="triClass('alert', openAlerts)"
            @click="nav('/alerts')" aria-label="Open alerts">
            <div class="cmd-tri-top">
              <span class="cmd-tri-icon" aria-hidden="true">⚠</span>
              <span v-if="critAlerts > 0" class="cmd-tri-badge cmd-badge--c">
                {{ critAlerts }}C
              </span>
            </div>
            <span class="cmd-tri-num">{{ live?.open_alerts ?? openAlerts }}</span>
            <span class="cmd-tri-lbl">Alerts</span>
            <span class="cmd-tri-sub">
              {{ openAlerts === 0 ? 'All clear' : `${nationalAlerts} national` }}
            </span>
          </button>

        </div>

        <!-- ── CRITICAL ALERT STRIP (conditional, max urgency) ─────────── -->
        <transition name="cmd-slide-down">
          <div v-if="hasCritical && !dismissed" class="cmd-crit-strip">
            <div class="cmd-crit-inner">
              <span class="cmd-crit-pulse" aria-hidden="true" />
              <div class="cmd-crit-body">
                <span class="cmd-crit-hed">
                  🚨 {{ critAlerts > 0 ? critAlerts + ' Critical Alert' + (critAlerts > 1 ? 's' : '') : 'Critical Referral Waiting' }}
                </span>
                <span class="cmd-crit-sub">{{ topCritMessage }}</span>
              </div>
              <button class="cmd-crit-view" @click="nav('/alerts')">View</button>
              <button class="cmd-crit-x" @click="dismissed = true" aria-label="Dismiss">✕</button>
            </div>
          </div>
        </transition>

        <!-- ── PRIMARY ACTIONS ────────────────────────────────────────── -->
        <div class="cmd-actions">
          <button class="cmd-action cmd-action--primary" @click="nav('/PrimaryScreening')">
            <div class="cmd-action-left">
              <div class="cmd-action-icon-box">
                <IonIcon :icon="medkitOutline" class="cmd-action-icon" />
              </div>
              <div class="cmd-action-text">
                <span class="cmd-action-title">New Primary Screening</span>
                <span class="cmd-action-sub">Ultra-fast · gender · temp · symptoms</span>
              </div>
            </div>
            <div class="cmd-action-right">
              <span class="cmd-action-tag">10s</span>
              <IonIcon :icon="chevronForwardOutline" class="cmd-action-chev" />
            </div>
          </button>

          <button class="cmd-action cmd-action--queue"
            @click="nav('/NotificationsCenter')">
            <div class="cmd-action-left">
              <div class="cmd-action-icon-box">
                <IonIcon :icon="gitMergeOutline" class="cmd-action-icon" />
                <span v-if="openReferrals > 0" class="cmd-action-dot" />
              </div>
              <div class="cmd-action-text">
                <span class="cmd-action-title">Secondary Referral Queue</span>
                <span class="cmd-action-sub">
                  {{ openReferrals === 0 ? 'Queue is clear' : `${openReferrals} open · ${critReferrals} critical` }}
                </span>
              </div>
            </div>
            <div class="cmd-action-right">
              <span v-if="openReferrals > 0" class="cmd-action-count">
                {{ openReferrals }}
              </span>
              <IonIcon :icon="chevronForwardOutline" class="cmd-action-chev" />
            </div>
          </button>
        </div>

        <!-- ── SYNC HEALTH ────────────────────────────────────────────── -->
        <div class="cmd-sync-row" :class="totalUnsynced > 0 && 'cmd-sync-row--warn'">
          <div class="cmd-sync-left">
            <span class="cmd-sync-icon" aria-hidden="true">⟳</span>
            <div class="cmd-sync-text">
              <span class="cmd-sync-title">
                {{ totalUnsynced === 0 ? 'All records synced' : `${totalUnsynced} records pending upload` }}
              </span>
              <span class="cmd-sync-sub">
                {{ totalUnsynced === 0
                  ? `Last synced ${lastSyncLabel}`
                  : `Primary: ${syncPrimary} · Cases: ${syncCases} · Alerts: ${syncAlerts}` }}
              </span>
            </div>
          </div>
          <div class="cmd-sync-right">
            <div class="cmd-sync-bar">
              <div class="cmd-sync-prog" :style="{ width: syncHealthPct + '%' }" />
            </div>
            <button v-if="totalUnsynced > 0" class="cmd-sync-btn" @click="nav('/sync/queue')">
              Sync
            </button>
          </div>
        </div>

        <!-- ── WEEK SNAPSHOT ──────────────────────────────────────────── -->
        <div class="cmd-week-strip" v-if="summary?.week_snapshot">
          <div class="cmd-ws-cell">
            <span class="cmd-ws-n">{{ summary.week_snapshot.this_week }}</span>
            <span class="cmd-ws-l">This week</span>
          </div>
          <div class="cmd-ws-div" />
          <div class="cmd-ws-cell">
            <span class="cmd-ws-n">{{ summary.week_snapshot.last_week }}</span>
            <span class="cmd-ws-l">Last week</span>
          </div>
          <div class="cmd-ws-div" />
          <div class="cmd-ws-cell">
            <span :class="['cmd-ws-n', weekDelta >= 0 ? 'cmd-ws--up' : 'cmd-ws--dn']">
              {{ weekDelta >= 0 ? '▲' : '▼' }}{{ Math.abs(weekDelta) }}
            </span>
            <span class="cmd-ws-l">Change</span>
          </div>
          <div class="cmd-ws-div" />
          <div class="cmd-ws-cell">
            <span class="cmd-ws-n">{{ summary.screening_today?.fever ?? '—' }}</span>
            <span class="cmd-ws-l">Fever today</span>
          </div>
        </div>

        <!-- ── SECONDARY LINKS ROW ────────────────────────────────────── -->
        <div class="cmd-links-row">
          <button class="cmd-link" @click="nav('/primary-screening/records')">
            <IonIcon :icon="listOutline" class="cmd-link-icon" />
            <span>Primary Records</span>
          </button>
          <button class="cmd-link" @click="nav('/secondary-screening/records')">
            <IonIcon :icon="documentTextOutline" class="cmd-link-icon" />
            <span>Case Records</span>
          </button>
          <button class="cmd-link" @click="nav('/primary-screening/dashboard')">
            <IonIcon :icon="barChartOutline" class="cmd-link-icon" />
            <span>Analytics</span>
          </button>
          <button class="cmd-link" @click="nav('/Users')">
            <IonIcon :icon="peopleOutline" class="cmd-link-icon" />
            <span>Users</span>
          </button>
        </div>

        <!-- ── RECENT ACTIVITY FEED ───────────────────────────────────── -->
        <div class="cmd-feed-card">
          <div class="cmd-feed-hdr">
            <span class="cmd-feed-title">Recent Activity</span>
            <span v-if="lastActivityAt" class="cmd-feed-age">
              {{ lastActivityAt }}
            </span>
          </div>

          <div v-if="activityLoading" class="cmd-feed-loading">
            <IonSpinner name="dots" class="cmd-feed-spinner" />
          </div>

          <div v-else-if="actEvents.length" class="cmd-feed-list">
            <div v-for="(ev, i) in actEvents" :key="i" class="cmd-feed-row">
              <div :class="['cmd-ev-dot', `cmd-evd--${ev.type.toLowerCase()}`]">
                <span class="cmd-ev-icon" aria-hidden="true">{{ evIcon(ev.type) }}</span>
              </div>
              <div class="cmd-ev-body">
                <span class="cmd-ev-title">{{ ev.title }}</span>
                <span class="cmd-ev-sub">{{ ev.subtitle }}</span>
              </div>
              <div class="cmd-ev-right">
                <span v-if="ev.risk_level && ev.risk_level !== 'LOW'"
                  :class="['cmd-ev-risk', `cmd-risk--${ev.risk_level.toLowerCase()}`]">
                  {{ ev.risk_level }}
                </span>
                <span class="cmd-ev-age">{{ fmtAge(ev.age_minutes) }}</span>
              </div>
            </div>
          </div>

          <div v-else class="cmd-feed-empty">
            <IonIcon :icon="cloudOfflineOutline" class="cmd-feed-empty-icon" />
            <span>{{ isOnline ? 'No recent activity' : 'Connect to load activity' }}</span>
          </div>
        </div>

        <!-- Bottom safe area pad -->
        <div style="height:52px" aria-hidden="true" />

      </div>
    </IonContent>

    <IonToast :is-open="toast.show" :message="toast.msg" :color="toast.color"
      :duration="3200" position="top" @didDismiss="toast.show=false" />
  </IonPage>
</template>

<script setup>
/**
 * HomePage.vue — ECSA-HC POE Sentinel
 * WHO/IHR 2005 · Article 23 · Executive Operational Command Dashboard
 *
 * Design: Immersive dark gradient header (command station) +
 *         light operations board (action cards). Mobile-first, 375px base.
 *
 * API endpoints:
 *   /home/summary  — full snapshot, loaded on mount + every 2 min
 *   /home/live     — lightweight ticker (6 counts), polled every 30s
 *   /home/activity — event feed, loaded once per view-entry
 *
 * Offline:
 *   summary → localStorage (4h TTL), shown instantly on mount
 *   liveData → blanked when offline (shows summary fallback)
 *   sparkline trend → localStorage (1h TTL)
 *   activity → not cached (requires network)
 *
 * CSS namespace: cmd-*  (command dashboard)
 */
import { ref, computed, reactive, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonPage, IonHeader, IonButtons, IonMenuButton,
  IonContent, IonIcon, IonSpinner,
  IonRefresher, IonRefresherContent, IonToast,
  onIonViewDidEnter, onIonViewWillLeave,
} from '@ionic/vue'
import {
  medkitOutline, gitMergeOutline, chevronForwardOutline,
  listOutline, documentTextOutline, barChartOutline,
  peopleOutline, cloudOfflineOutline,
} from 'ionicons/icons'
import { APP } from '@/services/poeDB'

const router = useRouter()
function nav(path) { router.push(path) }

// ─── AUTH ─────────────────────────────────────────────────────────────────────
function getAuth() { return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {} }
const auth = ref(getAuth())

// ─── DONUT / SPARKLINE CONSTANTS ──────────────────────────────────────────────
const DONUT_R  = 40
const DONUT_C  = parseFloat((2 * Math.PI * DONUT_R).toFixed(2))  // 251.33
const SPARK_W  = 320
const SPARK_H  = 44

// ─── CACHE KEYS ────────────────────────────────────────────────────────────────
const SUMMARY_KEY = 'cmd_summary_v2'
const TREND_KEY   = 'cmd_trend_v2'

// ─── STATE ────────────────────────────────────────────────────────────────────
const summary        = ref(null)
const trendData      = ref([])     // array of { date, total, symptomatic }
const live           = ref(null)   // live ticker data (30s polling)
const actEvents      = ref([])
const loading        = ref(false)
const activityLoading= ref(false)
const isOnline       = ref(navigator.onLine)
const dismissed      = ref(false)  // critical strip dismissed
const lastUpdated    = ref(null)   // ms timestamp of last summary load
const currentTime    = ref(fmtTime(new Date()))
const toast          = reactive({ show:false, msg:'', color:'success' })

let liveTimer    = null
let summaryTimer = null
let clockTimer   = null

// ─── POE TYPE LABEL ───────────────────────────────────────────────────────────
const poeTypeLabel = computed(() => {
  // Attempt to derive from POE name or default to role
  const role = auth.value?.role_key ?? ''
  if (role.includes('PRIMARY'))  return 'Primary Screener'
  if (role.includes('SECONDARY'))return 'Secondary Officer'
  if (role.includes('DATA'))     return 'Data Officer'
  if (role.includes('ADMIN'))    return 'POE Administrator'
  if (role.includes('DISTRICT')) return 'District Supervisor'
  if (role.includes('PHEOC'))    return 'PHEOC Officer'
  if (role.includes('NATIONAL')) return 'National Admin'
  return 'Sentinel Officer'
})

// ─── COMPUTED — SUMMARY METRICS ───────────────────────────────────────────────
const screenedToday    = computed(() => summary.value?.screening_today?.total           ?? '—')
const symptomaticToday = computed(() => summary.value?.screening_today?.symptomatic     ?? '—')
const feverToday       = computed(() => summary.value?.screening_today?.fever           ?? '—')
const referralsToday   = computed(() => summary.value?.screening_today?.referrals       ?? '—')
const dayDelta         = computed(() => summary.value?.screening_today?.vs_yesterday    ?? null)

const openReferrals = computed(() => summary.value?.referral_queue?.open             ?? 0)
const critReferrals = computed(() => summary.value?.referral_queue?.open_critical    ?? 0)
const activeCases   = computed(() => summary.value?.secondary_cases?.active          ?? 0)
const critCases     = computed(() => summary.value?.secondary_cases?.active_critical  ?? 0)
const emergencyCases= computed(() => summary.value?.secondary_cases?.emergency_active ?? 0)
const openAlerts    = computed(() => summary.value?.alerts?.open                     ?? 0)
const critAlerts    = computed(() => summary.value?.alerts?.open_critical             ?? 0)
const nationalAlerts= computed(() => summary.value?.alerts?.open_national             ?? 0)
const totalUnsynced = computed(() => summary.value?.sync_health?.grand_total_unsynced ?? 0)
const weekDelta     = computed(() => summary.value?.week_snapshot?.vs_last_week       ?? 0)
const opStatus      = computed(() => summary.value?.operational_status ?? 'NORMAL')
const hasCritical   = computed(() => !!(live.value?.has_critical_alert ?? (critAlerts.value > 0 || critReferrals.value > 0)))

// Top critical message for the banner
const topCritMessage = computed(() => {
  const a = summary.value?.alerts?.top_alerts?.[0]
  if (a) return a.alert_title
  const r = summary.value?.referral_queue?.top_critical?.[0]
  if (r) return r.reason_text || 'Symptomatic traveler awaiting secondary screening'
  return 'Immediate attention required at this POE'
})

// Sync health per-entity
const syncPrimary = computed(() => summary.value?.sync_health?.primary_screenings?.pending   ?? 0)
const syncCases   = computed(() => summary.value?.sync_health?.secondary_screenings?.pending ?? 0)
const syncAlerts  = computed(() => summary.value?.sync_health?.alerts?.pending               ?? 0)
const syncHealthPct = computed(() => {
  if (!totalUnsynced.value) return 100
  const sh = summary.value?.sync_health
  if (!sh) return 0
  const total  = Object.values(sh).filter(v => v && typeof v.total === 'number').reduce((a, v) => a + v.total, 0)
  const synced = total - totalUnsynced.value
  return total > 0 ? Math.round(synced / total * 100) : 100
})

const lastSyncLabel = computed(() => {
  if (!lastUpdated.value) return 'never'
  const m = Math.floor((Date.now() - lastUpdated.value) / 60000)
  if (m < 1)  return 'just now'
  if (m < 60) return `${m}m ago`
  return `${Math.floor(m / 60)}h ago`
})

const liveTimeLabel = computed(() => {
  if (!live.value?.server_time) return ''
  try {
    return new Date(live.value.server_time).toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' })
  } catch { return '' }
})

const lastActivityAt = computed(() => {
  if (!actEvents.value.length) return null
  const first = actEvents.value[0]
  return first?.age_minutes != null ? fmtAge(first.age_minutes) + ' ago' : null
})

// ─── COMPUTED — DONUT ─────────────────────────────────────────────────────────
const symptomaticRatePct = computed(() => {
  const t = summary.value?.screening_today?.total        ?? 0
  const s = summary.value?.screening_today?.symptomatic  ?? 0
  if (!t) return '0'
  return String(Math.round(s / t * 100))
})

const donutOffset = computed(() => {
  const rate = parseFloat(symptomaticRatePct.value) || 0
  return parseFloat((DONUT_C - (rate / 100) * DONUT_C).toFixed(2))
})

const donutColor = computed(() => {
  const r = parseFloat(symptomaticRatePct.value) || 0
  if (r >= 20) return '#EF4444'
  if (r >= 10) return '#F59E0B'
  if (r >= 5)  return '#60A5FA'
  return '#34D399'
})

// ─── COMPUTED — SPARKLINE ─────────────────────────────────────────────────────
const sparkDots = computed(() => {
  const s = trendData.value?.slice(-7) ?? []
  if (s.length < 2) return []
  const vals = s.map(d => +(d.total ?? 0))
  const max  = Math.max(...vals, 1)
  const PAD  = 4
  return s.map((d, i) => ({
    x: parseFloat((PAD + (i / (s.length - 1)) * (SPARK_W - PAD * 2)).toFixed(1)),
    y: parseFloat((SPARK_H - PAD - (+(d.total ?? 0) / max) * (SPARK_H - PAD * 2)).toFixed(1)),
  }))
})

const sparkPoints = computed(() => {
  if (sparkDots.value.length < 2) return ''
  return sparkDots.value.map(pt => `${pt.x},${pt.y}`).join(' ')
})

const sparkAreaPath = computed(() => {
  const dots = sparkDots.value
  if (dots.length < 2) return ''
  const first = dots[0], last = dots[dots.length - 1]
  const line  = dots.map(d => `${d.x},${d.y}`).join(' L ')
  return `M ${first.x},${SPARK_H} L ${line} L ${last.x},${SPARK_H} Z`
})

// ─── CARD STATE HELPERS ───────────────────────────────────────────────────────
function triClass(type, count) {
  const active = count > 0
  return active ? `cmd-tri--${type}-active` : `cmd-tri--${type}`
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function showToast(msg, color = 'success') { Object.assign(toast, { show:true, msg, color }) }
function fmtTime(d) { return d.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }) }
function fmtAge(mins) {
  if (mins == null) return '—'
  if (mins < 1)   return 'now'
  if (mins < 60)  return `${mins}m`
  const h = Math.floor(mins / 60); const m = mins % 60
  return m > 0 ? `${h}h ${m}m` : `${h}h`
}
function evIcon(type) {
  return { PRIMARY:'🩺', REFERRAL:'↗', SECONDARY:'⚕', ALERT:'⚠', AGGREGATED:'📊' }[type] ?? '·'
}

// ─── CACHE ────────────────────────────────────────────────────────────────────
function writeCache(key, data, ttlMs) {
  try { localStorage.setItem(key, JSON.stringify({ ts: Date.now(), ttl: ttlMs, data })) } catch {}
}
function readCache(key) {
  try {
    const raw = localStorage.getItem(key)
    if (!raw) return null
    const { ts, ttl, data } = JSON.parse(raw)
    return (Date.now() - ts) < ttl ? data : null
  } catch { return null }
}

// ─── API ──────────────────────────────────────────────────────────────────────
async function apiFetch(path, extra = {}) {
  const uid = auth.value?.id
  if (!uid || !isOnline.value) return null
  const p = new URLSearchParams({ user_id: uid, ...extra })
  const ctrl = new AbortController()
  const tid  = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
  try {
    const res = await fetch(`${window.SERVER_URL}${path}?${p}`,
      { headers: { Accept: 'application/json' }, signal: ctrl.signal })
    clearTimeout(tid)
    if (!res.ok) return null
    const j = await res.json()
    return j.success ? j.data : null
  } catch { clearTimeout(tid); return null }
}

// ─── LOADERS ──────────────────────────────────────────────────────────────────
async function loadSummary() {
  if (!isOnline.value) return
  loading.value = true
  try {
    const data = await apiFetch('/home/summary')
    if (data) {
      summary.value   = data
      lastUpdated.value = Date.now()
      dismissed.value = false
      writeCache(SUMMARY_KEY, data, 4 * 3600_000) // 4h
    }
  } finally { loading.value = false }
}

async function loadLive() {
  if (!isOnline.value) return
  const data = await apiFetch('/home/live')
  if (data) live.value = data
}

async function loadTrend() {
  if (!isOnline.value) return
  const data = await apiFetch('/home/live') // reuse live endpoint for simplicity
  // Use the full summary trend if available
  const trend = await apiFetch('/primary-records/trend', { days: 7 })
  if (trend?.series) {
    trendData.value = trend.series
    writeCache(TREND_KEY, trend.series, 3600_000) // 1h
  }
}

async function loadActivity() {
  if (!isOnline.value || activityLoading.value) return
  activityLoading.value = true
  try {
    const data = await apiFetch('/home/activity', { limit: 15 })
    if (data?.events) actEvents.value = data.events
  } finally { activityLoading.value = false }
}

async function manualRefresh() {
  auth.value   = getAuth()
  dismissed.value = false
  actEvents.value = []
  await Promise.all([loadSummary(), loadLive()])
  loadTrend()
  loadActivity()
}

// ─── CONNECTIVITY ─────────────────────────────────────────────────────────────
function onOnline()  { isOnline.value = true;  loadSummary(); loadLive() }
function onOffline() { isOnline.value = false }

// ─── LIFECYCLE ─────────────────────────────────────────────────────────────────
onMounted(() => {
  auth.value = getAuth()
  window.addEventListener('online',  onOnline)
  window.addEventListener('offline', onOffline)

  // Show cached data instantly
  const cachedSummary = readCache(SUMMARY_KEY)
  if (cachedSummary) summary.value = cachedSummary
  const cachedTrend = readCache(TREND_KEY)
  if (cachedTrend) trendData.value = cachedTrend

  // Fresh load
  if (isOnline.value) {
    loadSummary()
    loadLive()
    loadTrend()
    loadActivity()
  }

  // Live ticker: 30s
  liveTimer = setInterval(() => { if (isOnline.value) loadLive() }, 30_000)
  // Summary refresh: 2 minutes
  summaryTimer = setInterval(() => {
    if (isOnline.value && !loading.value) loadSummary()
  }, 120_000)
  // Clock: update every second
  clockTimer = setInterval(() => {
    currentTime.value = fmtTime(new Date())
  }, 1_000)
})

onIonViewDidEnter(() => {
  auth.value = getAuth()
  if (isOnline.value) {
    loadLive()
    // Re-load summary if >90s stale
    if (!lastUpdated.value || Date.now() - lastUpdated.value > 90_000) loadSummary()
    if (!actEvents.value.length) loadActivity()
  }
})

onIonViewWillLeave(() => { /* Ionic keeps page alive — timers continue */ })

onUnmounted(() => {
  clearInterval(liveTimer)
  clearInterval(summaryTimer)
  clearInterval(clockTimer)
  window.removeEventListener('online',  onOnline)
  window.removeEventListener('offline', onOffline)
})
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════
   COMMAND DASHBOARD · cmd-* namespace
   Mobile-first: 375px base, every number readable at arm's length.
   Rule: min touch target 56px. Min font 11px. No horizontal scroll.
   No position:absolute on content elements. Flex/grid gap-based spacing.
═══════════════════════════════════════════════════════════════════════ */

/* ── HEADER — the command station ──────────────────────────────────── */
.cmd-header {
  background: linear-gradient(160deg, #06111E 0%, #0D253F 55%, #0F3460 100%);
  position: relative;
  overflow: hidden;
}

/* Radial glow overlays — decorative only, pointer-events none */
.cmd-hdr-glow {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background-image:
    radial-gradient(ellipse at 85% 15%, rgba(0,212,170,0.08) 0%, transparent 55%),
    radial-gradient(ellipse at 10% 75%, rgba(96,165,250,0.06) 0%, transparent 45%);
}

/* ── TOP BAR ─────────────────────────────────────────────────────────── */
.cmd-topbar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px 6px;
  position: relative;
  z-index: 2;
}
.cmd-menu-btn { --color: rgba(255,255,255,0.7); flex-shrink: 0; }

.cmd-poe-id {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
}
.cmd-poe-type {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.2px;
  color: rgba(255,255,255,0.45);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
}
.cmd-poe-name {
  font-size: 16px;
  font-weight: 900;
  color: #fff;
  letter-spacing: -.3px;
  line-height: 1.15;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
}

.cmd-topbar-right {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}
.cmd-conn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 9px;
  border-radius: 99px;
  border: 1px solid;
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.cmd-conn--on  { background: rgba(52,211,153,0.15); border-color: rgba(52,211,153,0.35); color: #6EE7B7; }
.cmd-conn--off { background: rgba(148,163,184,0.12); border-color: rgba(148,163,184,0.25); color: rgba(255,255,255,0.4); }
.cmd-conn-dot  { width: 5px; height: 5px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
.cmd-conn--on .cmd-conn-dot { animation: cmd-pulse 1.8s ease-in-out infinite; }
.cmd-conn-lbl  { display: none; } /* hidden on tiny screens */
@media (min-width: 400px) { .cmd-conn-lbl { display: block; } }

.cmd-refresh-btn {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}
.cmd-refresh-btn svg { width: 16px; height: 16px; }
.cmd-refresh-btn:disabled { opacity: .5; }
.cmd-spin { animation: cmd-rotate .75s linear infinite; }

@keyframes cmd-pulse  { 0%,100%{opacity:1} 50%{opacity:.2} }
@keyframes cmd-rotate { to { transform: rotate(360deg); } }

/* ── HERO METRICS BLOCK ──────────────────────────────────────────────── */
.cmd-hero-block {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 8px 16px 0;
  position: relative;
  z-index: 2;
}

/* Left: big number + facts */
.cmd-hero-left {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
}
.cmd-hero-eyebrow {
  font-size: 8.5px;
  font-weight: 700;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: rgba(255,255,255,0.4);
  display: block;
  margin-bottom: 4px;
}
.cmd-hero-num-row {
  display: flex;
  align-items: baseline;
  gap: 10px;
  flex-wrap: nowrap;
}
.cmd-hero-big {
  font-size: clamp(56px, 17vw, 84px);
  font-weight: 900;
  color: #fff;
  line-height: 1;
  letter-spacing: -3px;
  font-variant-numeric: tabular-nums;
}
.cmd-hero-delta-stack {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0;
  padding-bottom: 4px;
}
.cmd-hero-delta {
  font-size: 14px;
  font-weight: 800;
  line-height: 1;
}
.cmd-delta--up { color: #6EE7B7; }
.cmd-delta--dn { color: #FCA5A5; }
.cmd-hero-delta-sub {
  font-size: 8px;
  font-weight: 600;
  color: rgba(255,255,255,0.35);
  text-transform: uppercase;
  letter-spacing: .5px;
}
.cmd-hero-label {
  font-size: 10px;
  font-weight: 700;
  color: rgba(255,255,255,0.45);
  text-transform: uppercase;
  letter-spacing: .8px;
  margin-top: 3px;
  display: block;
}

/* Mini facts: Symptomatic | Fever | Referred */
.cmd-hero-facts {
  display: flex;
  align-items: center;
  gap: 0;
  margin-top: 10px;
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 12px;
  overflow: hidden;
}
.cmd-fact {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 4px 7px;
  gap: 2px;
  position: relative;
}
.cmd-fact:not(:last-child)::after {
  content: '';
  position: absolute;
  right: 0;
  top: 20%;
  height: 60%;
  width: 1px;
  background: rgba(255,255,255,0.12);
}
.cmd-fact-n {
  font-size: 20px;
  font-weight: 900;
  color: #fff;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}
.cmd-fact--symp  { color: #FCA5A5; }
.cmd-fact--fever { color: #FCD34D; }
.cmd-fact--ref   { color: #93C5FD; }
.cmd-fact-l {
  font-size: 7.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: rgba(255,255,255,0.4);
}

/* Right: donut */
.cmd-hero-right {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  padding-top: 4px;
}
.cmd-donut-wrap {
  width: clamp(88px, 22vw, 110px);
  height: clamp(88px, 22vw, 110px);
  flex-shrink: 0;
}
.cmd-donut { width: 100%; height: 100%; display: block; }
.cmd-donut-arc { transition: stroke-dashoffset .8s ease, stroke .5s ease; }
.cmd-donut-label {
  font-size: 8px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: rgba(255,255,255,0.35);
}

/* ── SPARKLINE ────────────────────────────────────────────────────────── */
.cmd-spark-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px 4px;
  position: relative;
  z-index: 2;
}
.cmd-spark-label {
  font-size: 8.5px;
  font-weight: 700;
  color: rgba(255,255,255,0.3);
  text-transform: uppercase;
  letter-spacing: .6px;
  flex-shrink: 0;
  white-space: nowrap;
}
.cmd-spark-svg {
  flex: 1;
  height: 44px;
  display: block;
  overflow: visible;
}

/* ── STATUS STRIP ─────────────────────────────────────────────────────── */
.cmd-status-strip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 16px 9px;
  border-top: 1px solid rgba(255,255,255,0.08);
  position: relative;
  z-index: 2;
}
.cmd-status--normal   { background: rgba(0,0,0,0.15); }
.cmd-status--active   { background: rgba(59,130,246,0.2); }
.cmd-status--elevated { background: rgba(245,158,11,0.25); }
.cmd-status--critical { background: rgba(239,68,68,0.3); }

.cmd-status-left {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: none;
}
.cmd-status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(255,255,255,0.5);
  flex-shrink: 0;
}
.cmd-status--active .cmd-status-dot,
.cmd-status--elevated .cmd-status-dot,
.cmd-status--critical .cmd-status-dot {
  background: #fff;
  box-shadow: 0 0 0 3px rgba(255,255,255,0.2);
  animation: cmd-pulse 1.2s ease-in-out infinite;
}
.cmd-status-txt {
  font-size: 12px;
  font-weight: 900;
  color: #fff;
  text-transform: uppercase;
  letter-spacing: .8px;
}
.cmd-status-scope {
  flex: 1;
  font-size: 9px;
  font-weight: 700;
  color: rgba(255,255,255,0.4);
  text-align: center;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.cmd-status-time {
  font-size: 13px;
  font-weight: 800;
  color: rgba(255,255,255,0.6);
  font-variant-numeric: tabular-nums;
  flex-shrink: 0;
}

/* ── CONTENT ──────────────────────────────────────────────────────────── */
.cmd-content { --background: #F1F5F9; }
.cmd-board   { padding-bottom: 8px; }

/* ── TRIPTYCH ─────────────────────────────────────────────────────────── */
.cmd-triptych {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  padding: 12px 12px 0;
}

.cmd-tri {
  background: #fff;
  border-radius: 16px;
  padding: 12px 10px 10px;
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-height: 108px;
  cursor: pointer;
  text-align: left;
  border: 1.5px solid #E2E8F0;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
  transition: transform .1s;
  /* Critical: prevent overflow from stretching grid columns */
  min-width: 0;
  overflow: hidden;
}
.cmd-tri:active { transform: scale(.95); }

/* Active state — the card turns colored */
.cmd-tri--ref-active  { background: #EFF6FF; border-color: #2563EB; }
.cmd-tri--case-active { background: #FFF7ED; border-color: #EA580C; }
.cmd-tri--alert-active{ background: #FEF2F2; border-color: #DC2626; }
/* Default state — subtle identity color */
.cmd-tri--ref  { border-color: #E2E8F0; }
.cmd-tri--case { border-color: #E2E8F0; }
.cmd-tri--alert{ border-color: #E2E8F0; }

.cmd-tri-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 2px;
}
.cmd-tri-icon {
  font-size: 16px;
  line-height: 1;
}
.cmd-tri-badge {
  font-size: 8px;
  font-weight: 900;
  padding: 1px 5px;
  border-radius: 3px;
  text-transform: uppercase;
  letter-spacing: .2px;
}
.cmd-badge--c { background: #DC2626; color: #fff; }

.cmd-tri-num {
  font-size: clamp(26px, 7vw, 36px);
  font-weight: 900;
  color: #0F172A;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  letter-spacing: -1px;
}
.cmd-tri--ref-active  .cmd-tri-num { color: #1D4ED8; }
.cmd-tri--case-active .cmd-tri-num { color: #C2410C; }
.cmd-tri--alert-active.cmd-tri-num { color: #B91C1C; }

.cmd-tri-lbl {
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .6px;
  color: #64748B;
}
.cmd-tri-sub {
  font-size: 9px;
  font-weight: 600;
  color: #94A3B8;
  line-height: 1.2;
  /* Prevent overflow on tiny cards */
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── CRITICAL STRIP ───────────────────────────────────────────────────── */
.cmd-crit-strip {
  margin: 8px 12px 0;
  border-radius: 14px;
  background: linear-gradient(135deg, #7F1D1D 0%, #991B1B 100%);
  border: 1px solid rgba(255,255,255,0.15);
  overflow: hidden;
}
.cmd-crit-inner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 12px;
}
.cmd-crit-pulse {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #FCA5A5;
  flex-shrink: 0;
  animation: cmd-pulse 1s ease-in-out infinite;
}
.cmd-crit-body {
  flex: 1;
  min-width: 0;
}
.cmd-crit-hed {
  display: block;
  font-size: 11px;
  font-weight: 800;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cmd-crit-sub {
  display: block;
  font-size: 9px;
  color: rgba(255,255,255,0.6);
  margin-top: 1px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cmd-crit-view {
  padding: 5px 12px;
  background: rgba(255,255,255,0.18);
  border: 1px solid rgba(255,255,255,0.25);
  border-radius: 7px;
  font-size: 11px;
  font-weight: 800;
  color: #fff;
  cursor: pointer;
  flex-shrink: 0;
  min-height: 32px;
}
.cmd-crit-x {
  background: none;
  border: none;
  color: rgba(255,255,255,0.5);
  font-size: 14px;
  cursor: pointer;
  padding: 4px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  min-height: 32px;
}

/* Slide-down transition */
.cmd-slide-down-enter-active { animation: cmd-slidein .2s ease; }
.cmd-slide-down-leave-active  { animation: cmd-slidein .15s ease reverse; }
@keyframes cmd-slidein {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── ACTION BUTTONS ───────────────────────────────────────────────────── */
.cmd-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px 12px 0;
}

.cmd-action {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 14px;
  border-radius: 16px;
  border: none;
  cursor: pointer;
  text-align: left;
  transition: transform .1s;
  min-height: 68px;
}
.cmd-action:active { transform: scale(.98); }

.cmd-action--primary {
  background: linear-gradient(135deg, #0F3460 0%, #1E4D8C 100%);
  box-shadow: 0 4px 16px rgba(15,52,96,0.4);
}
.cmd-action--queue {
  background: #fff;
  border: 1.5px solid #E2E8F0;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
}

.cmd-action-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
}
.cmd-action-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(255,255,255,0.12);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  position: relative;
}
.cmd-action--queue .cmd-action-icon-box {
  background: #F1F5F9;
}
.cmd-action-icon { font-size: 22px; color: #fff; }
.cmd-action--queue .cmd-action-icon { color: #0F3460; }
.cmd-action-dot {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #EF4444;
  border: 2px solid #fff;
}

.cmd-action-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.cmd-action-title {
  font-size: 14px;
  font-weight: 800;
  color: #fff;
  line-height: 1.2;
}
.cmd-action--queue .cmd-action-title { color: #0F172A; }
.cmd-action-sub {
  font-size: 10px;
  font-weight: 600;
  color: rgba(255,255,255,0.55);
  line-height: 1.2;
}
.cmd-action--queue .cmd-action-sub { color: #64748B; }

.cmd-action-right {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}
.cmd-action-tag {
  font-size: 10px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 6px;
  background: rgba(255,255,255,0.15);
  color: rgba(255,255,255,0.85);
  letter-spacing: .3px;
}
.cmd-action-count {
  font-size: 15px;
  font-weight: 900;
  color: #DC2626;
  font-variant-numeric: tabular-nums;
  background: #FEF2F2;
  border-radius: 8px;
  padding: 2px 8px;
}
.cmd-action-chev { font-size: 18px; color: rgba(255,255,255,0.4); }
.cmd-action--queue .cmd-action-chev { color: #CBD5E1; }

/* ── SYNC HEALTH ──────────────────────────────────────────────────────── */
.cmd-sync-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  margin: 10px 12px 0;
  background: #fff;
  border-radius: 14px;
  border: 1.5px solid #E2E8F0;
  box-shadow: 0 1px 4px rgba(0,0,0,.05);
}
.cmd-sync-row--warn { border-color: #FCD34D; background: #FFFBEB; }

.cmd-sync-left {
  display: flex;
  align-items: center;
  gap: 9px;
  flex: 1;
  min-width: 0;
}
.cmd-sync-icon { font-size: 20px; flex-shrink: 0; }
.cmd-sync-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.cmd-sync-title {
  font-size: 12px;
  font-weight: 700;
  color: #0F172A;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cmd-sync-sub {
  font-size: 9px;
  font-weight: 600;
  color: #94A3B8;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cmd-sync-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
.cmd-sync-bar {
  width: 56px;
  height: 6px;
  background: #E2E8F0;
  border-radius: 3px;
  overflow: hidden;
}
.cmd-sync-prog {
  height: 100%;
  background: linear-gradient(90deg, #10B981 0%, #34D399 100%);
  border-radius: 3px;
  transition: width .5s ease;
  min-width: 4px;
}
.cmd-sync-btn {
  font-size: 11px;
  font-weight: 800;
  color: #1D4ED8;
  background: #EFF6FF;
  border: 1px solid #BFDBFE;
  border-radius: 7px;
  padding: 5px 10px;
  cursor: pointer;
  min-height: 30px;
}

/* ── WEEK SNAPSHOT ────────────────────────────────────────────────────── */
.cmd-week-strip {
  display: grid;
  grid-template-columns: 1fr auto 1fr auto 1fr auto 1fr;
  align-items: center;
  background: #fff;
  border-radius: 14px;
  margin: 8px 12px 0;
  border: 1.5px solid #E2E8F0;
  box-shadow: 0 1px 4px rgba(0,0,0,.05);
  overflow: hidden;
}
.cmd-ws-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  padding: 13px 6px;
}
.cmd-ws-div {
  width: 1px;
  height: 32px;
  background: #E2E8F0;
  flex-shrink: 0;
}
.cmd-ws-n {
  font-size: 19px;
  font-weight: 900;
  color: #0F172A;
  font-variant-numeric: tabular-nums;
  line-height: 1;
}
.cmd-ws-l {
  font-size: 7.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  color: #94A3B8;
  text-align: center;
  line-height: 1.2;
}
.cmd-ws--up { color: #10B981; }
.cmd-ws--dn { color: #EF4444; }

/* ── SECONDARY LINKS ROW ─────────────────────────────────────────────── */
.cmd-links-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
  padding: 8px 12px 0;
}
.cmd-link {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
  padding: 12px 4px;
  border-radius: 12px;
  background: #fff;
  border: 1.5px solid #E2E8F0;
  cursor: pointer;
  font-size: 10px;
  font-weight: 700;
  color: #475569;
  min-height: 60px;
  min-width: 0;
  transition: background .1s, border-color .1s;
}
.cmd-link:active { background: #F8FAFC; border-color: #CBD5E1; }
.cmd-link-icon { font-size: 20px; color: #0F3460; }

/* ── ACTIVITY FEED ────────────────────────────────────────────────────── */
.cmd-feed-card {
  background: #fff;
  border-radius: 14px;
  margin: 8px 12px 0;
  border: 1.5px solid #E2E8F0;
  box-shadow: 0 1px 4px rgba(0,0,0,.05);
  overflow: hidden;
}
.cmd-feed-hdr {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 13px 14px 8px;
  border-bottom: 1px solid #F1F5F9;
}
.cmd-feed-title {
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .6px;
  color: #64748B;
}
.cmd-feed-age {
  font-size: 10px;
  font-weight: 600;
  color: #94A3B8;
}
.cmd-feed-loading {
  display: flex;
  justify-content: center;
  padding: 20px;
}
.cmd-feed-spinner { --color: #0F3460; width: 24px; height: 24px; }

.cmd-feed-list { padding: 0 14px; }
.cmd-feed-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid #F8FAFC;
}
.cmd-feed-row:last-child { border-bottom: none; }

.cmd-ev-dot {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 1px;
}
.cmd-ev-icon { font-size: 14px; }
.cmd-evd--primary   { background: #EFF6FF; }
.cmd-evd--referral  { background: #F0FDF4; }
.cmd-evd--secondary { background: #FFF7ED; }
.cmd-evd--alert     { background: #FEF2F2; }
.cmd-evd--aggregated{ background: #FAF5FF; }

.cmd-ev-body { flex: 1; min-width: 0; }
.cmd-ev-title {
  display: block;
  font-size: 11px;
  font-weight: 700;
  color: #0F172A;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cmd-ev-sub {
  display: block;
  font-size: 9px;
  color: #94A3B8;
  font-weight: 600;
  margin-top: 1px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cmd-ev-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
  flex-shrink: 0;
}
.cmd-ev-risk {
  padding: 1px 5px;
  border-radius: 3px;
  font-size: 8px;
  font-weight: 900;
  text-transform: uppercase;
}
.cmd-risk--critical { background: #FEF2F2; color: #B91C1C; }
.cmd-risk--high     { background: #FFF7ED; color: #C2410C; }
.cmd-risk--medium   { background: #FEFCE8; color: #A16207; }
.cmd-ev-age {
  font-size: 9px;
  color: #94A3B8;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.cmd-feed-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 7px;
  padding: 24px;
  color: #94A3B8;
  font-size: 11px;
  font-weight: 600;
}
.cmd-feed-empty-icon { font-size: 26px; }

/* ── RESPONSIVE ──────────────────────────────────────────────────────── */
@media (min-width: 600px) {
  .cmd-board { max-width: 680px; margin: 0 auto; }
}
@media (max-width: 360px) {
  .cmd-hero-big { font-size: 52px; letter-spacing: -2px; }
  .cmd-donut-wrap { width: 80px; height: 80px; }
  .cmd-tri-num { font-size: 24px; }
  .cmd-links-row { grid-template-columns: repeat(2, 1fr); }
}
</style>
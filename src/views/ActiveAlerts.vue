<template>
  <IonPage>
    <IonHeader class="al-header ion-no-border">
      <IonToolbar class="al-toolbar">
        <IonButtons slot="start">
          <IonBackButton default-href="/home" class="al-back" />
        </IonButtons>
        <IonTitle class="al-title">
          <span class="al-title-icon">⚡</span>
          Active Alerts
          <span v-if="openCount > 0" class="al-title-badge">{{ openCount }}</span>
        </IonTitle>
        <IonButtons slot="end">
          <IonButton class="al-refresh-btn" @click="loadAlerts(true)" :disabled="loading">
            <IonIcon :icon="refreshOutline" />
          </IonButton>
        </IonButtons>
      </IonToolbar>
      <!-- KPI strip -->
      <div class="al-kpi-strip">
        <button class="al-kpi" :class="{'al-kpi--active': activeFilter === null}" @click="setFilter(null)">
          <span class="al-kpi-n">{{ counts.total || 0 }}</span>
          <span class="al-kpi-l">All</span>
        </button>
        <button class="al-kpi al-kpi--critical" :class="{'al-kpi--active': activeFilter === 'CRITICAL'}" @click="setFilter('CRITICAL')">
          <span class="al-kpi-n">{{ counts.critical || 0 }}</span>
          <span class="al-kpi-l">Critical</span>
        </button>
        <button class="al-kpi al-kpi--high" :class="{'al-kpi--active': activeFilter === 'HIGH'}" @click="setFilter('HIGH')">
          <span class="al-kpi-n">{{ counts.high || 0 }}</span>
          <span class="al-kpi-l">High</span>
        </button>
        <button class="al-kpi al-kpi--overdue" :class="{'al-kpi--active': activeFilter === 'OVERDUE'}" @click="setFilter('OVERDUE')">
          <span class="al-kpi-n">{{ counts.overdue || 0 }}</span>
          <span class="al-kpi-l">&gt;24h</span>
        </button>
        <button class="al-kpi al-kpi--acked" :class="{'al-kpi--active': activeFilter === 'ACKNOWLEDGED'}" @click="setFilter('ACKNOWLEDGED')">
          <span class="al-kpi-n">{{ counts.acked || 0 }}</span>
          <span class="al-kpi-l">Acked</span>
        </button>
      </div>
    </IonHeader>

    <IonContent class="al-content">

      <!-- Loading skeleton -->
      <div v-if="loading && alerts.length === 0" class="al-skeletons">
        <div v-for="i in 4" :key="i" class="al-skeleton-card">
          <div class="al-sk-line al-sk-line--wide" />
          <div class="al-sk-line" />
          <div class="al-sk-line al-sk-line--narrow" />
        </div>
      </div>

      <!-- Empty state -->
      <div v-else-if="!loading && filteredAlerts.length === 0" class="al-empty">
        <div class="al-empty-icon">✅</div>
        <div class="al-empty-title">No Alerts</div>
        <div class="al-empty-body">
          {{ activeFilter
             ? 'No ' + activeFilter + ' alerts in your scope.'
             : 'All clear. No open alerts in your geographic scope.' }}
        </div>
      </div>

      <!-- Alert cards -->
      <div v-else class="al-list">
        <div
          v-for="alert in filteredAlerts"
          :key="alert.id"
          class="al-card"
          :class="[
            'al-card--' + alert.risk_level.toLowerCase(),
            alert.overdue_24h ? 'al-card--overdue' : '',
            alert.status === 'ACKNOWLEDGED' ? 'al-card--acked' : '',
          ]"
        >
          <!-- Left risk bar -->
          <div class="al-risk-bar" :class="'al-risk-bar--' + alert.risk_level.toLowerCase()" />

          <div class="al-card-body">
            <!-- Top row: code + status + routing -->
            <div class="al-card-top">
              <span class="al-alert-code">{{ alert.alert_code.replace(/_/g, ' ') }}</span>
              <span class="al-status-pill" :class="'al-status--' + alert.status.toLowerCase()">
                {{ alert.status }}
              </span>
              <span v-if="alert.routed_to_level" class="al-route-pill" :class="'al-route--' + alert.routed_to_level.toLowerCase()">
                {{ alert.routed_to_level }}
              </span>
            </div>

            <!-- Title -->
            <div class="al-alert-title">{{ alert.alert_title }}</div>

            <!-- Meta row -->
            <div class="al-meta-row">
              <span class="al-meta-item">
                <span class="al-meta-k">Risk</span>
                <span class="al-meta-v" :class="'al-risk-text--' + alert.risk_level.toLowerCase()">
                  {{ alert.risk_level }}
                </span>
              </span>
              <span class="al-meta-item" v-if="alert.syndrome">
                <span class="al-meta-k">Syndrome</span>
                <span class="al-meta-v">{{ alert.syndrome }}</span>
              </span>
              <span class="al-meta-item">
                <span class="al-meta-k">POE</span>
                <span class="al-meta-v">{{ alert.poe_code }}</span>
              </span>
              <span class="al-meta-item">
                <span class="al-meta-k">Generated</span>
                <span class="al-meta-v">{{ alert.generated_from === 'OFFICER' ? 'Officer' : 'Auto' }}</span>
              </span>
            </div>

            <!-- Overdue warning -->
            <div v-if="alert.overdue_24h" class="al-overdue-warn" role="alert">
              ⏰ OVERDUE — {{ Math.round(alert.hours_since_creation) }}h since creation. IHR requires acknowledgement within 24h.
            </div>

            <!-- IHR tier -->
            <div v-if="alert.ihr_tier" class="al-ihr-badge" :class="alert.ihr_tier === 'TIER_1_ALWAYS_NOTIFIABLE' ? 'al-ihr-badge--t1' : 'al-ihr-badge--t2'">
              {{ alert.ihr_tier === 'TIER_1_ALWAYS_NOTIFIABLE' ? 'IHR Tier 1 — Always Notifiable' : 'IHR Tier 2 — Annex 2' }}
            </div>

            <!-- Details if present -->
            <div v-if="alert.alert_details" class="al-details">{{ alert.alert_details.replace(/\[CLOSED:.*\]/g, '').trim() }}</div>

            <!-- Ack info -->
            <div v-if="alert.status === 'ACKNOWLEDGED' && alert.acknowledged_at" class="al-ack-info">
              ✓ Acknowledged {{ formatTime(alert.acknowledged_at) }}
              {{ alert.acknowledged_by_name ? 'by ' + alert.acknowledged_by_name : '' }}
            </div>

            <!-- Actions -->
            <div class="al-actions">
              <button
                v-if="alert.status === 'OPEN' && canAcknowledge(alert)"
                class="al-btn al-btn--ack"
                @click="acknowledge(alert)"
                :disabled="actioning[alert.id]"
              >
                {{ actioning[alert.id] ? '…' : 'Acknowledge' }}
              </button>
              <button
                v-if="alert.status !== 'CLOSED' && canClose(alert)"
                class="al-btn al-btn--close"
                @click="promptClose(alert)"
                :disabled="actioning[alert.id]"
              >
                Close Alert
              </button>
              <button
                class="al-btn al-btn--detail"
                @click="viewDetail(alert)"
              >
                View Case →
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Load more -->
      <div v-if="hasMore && !loading" class="al-load-more">
        <button class="al-load-more-btn" @click="loadMore">Load More Alerts</button>
      </div>

      <!-- Close modal -->
      <IonModal :is-open="closeModal.open" @didDismiss="closeModal.open = false" class="al-close-modal">
        <div class="al-modal-inner">
          <div class="al-modal-title">Close Alert</div>
          <div class="al-modal-body">
            <p class="al-modal-p">Closing: <strong>{{ closeModal.alert?.alert_code }}</strong></p>
            <p class="al-modal-hint">Explain why this alert is being closed. This is recorded in the audit trail.</p>
            <textarea
              class="al-modal-input"
              v-model="closeModal.reason"
              rows="3"
              placeholder="Response completed, duplicate, generated in error…"
              maxlength="255"
            />
            <div v-if="closeModal.error" class="al-modal-err">{{ closeModal.error }}</div>
          </div>
          <div class="al-modal-actions">
            <button class="al-btn al-btn--cancel" @click="closeModal.open = false">Cancel</button>
            <button class="al-btn al-btn--confirm" @click="confirmClose" :disabled="closeModal.submitting">
              {{ closeModal.submitting ? 'Closing…' : 'Confirm Close' }}
            </button>
          </div>
        </div>
      </IonModal>

    </IonContent>
  </IonPage>
</template>

<script setup>
/**
 * ActiveAlerts.vue
 * Route: /alerts
 * Roles: DISTRICT_SUPERVISOR, PHEOC_OFFICER, NATIONAL_ADMIN
 *        (POE_ADMIN can view alerts at their POE)
 *
 * Laws:
 *  - No Authorization header (API is open)
 *  - Navigate with server integer id (Law 1)
 *  - IDB not used for alerts — server is authoritative for alert status
 *    (alerts must be acknowledged/closed in real-time, not offline)
 */
import { ref, computed, reactive, onMounted } from 'vue'
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent,
  IonButtons, IonButton, IonBackButton, IonIcon, IonModal,
} from '@ionic/vue'
import { onIonViewDidEnter } from '@ionic/vue'
import { refreshOutline } from 'ionicons/icons'
import { useRouter } from 'vue-router'

const router = useRouter()

function getAuth() {
  return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
}

// ── State ──────────────────────────────────────────────────────────────────
const alerts      = ref([])
const loading     = ref(false)
const error       = ref(null)
const page        = ref(1)
const hasMore     = ref(false)
const activeFilter = ref(null)
const actioning   = reactive({})   // alertId → true while API call in flight

const closeModal = reactive({
  open:       false,
  alert:      null,
  reason:     '',
  error:      null,
  submitting: false,
})

// ── Computed ────────────────────────────────────────────────────────────────
const filteredAlerts = computed(() => {
  if (activeFilter.value === null) return alerts.value
  if (activeFilter.value === 'OVERDUE') return alerts.value.filter(a => a.overdue_24h)
  if (activeFilter.value === 'ACKNOWLEDGED') return alerts.value.filter(a => a.status === 'ACKNOWLEDGED')
  return alerts.value.filter(a => a.risk_level === activeFilter.value && a.status === 'OPEN')
})

const counts = computed(() => ({
  total:    alerts.value.filter(a => a.status === 'OPEN').length,
  critical: alerts.value.filter(a => a.risk_level === 'CRITICAL' && a.status === 'OPEN').length,
  high:     alerts.value.filter(a => a.risk_level === 'HIGH'     && a.status === 'OPEN').length,
  overdue:  alerts.value.filter(a => a.overdue_24h).length,
  acked:    alerts.value.filter(a => a.status === 'ACKNOWLEDGED').length,
}))

const openCount = computed(() => counts.value.total)

// ── Role-based action permissions ──────────────────────────────────────────
const ACK_ROLES = {
  DISTRICT: ['DISTRICT_SUPERVISOR','PHEOC_OFFICER','NATIONAL_ADMIN'],
  PHEOC:    ['PHEOC_OFFICER','NATIONAL_ADMIN'],
  NATIONAL: ['NATIONAL_ADMIN'],
}

function canAcknowledge(alert) {
  const role = getAuth()?.role_key ?? ''
  return (ACK_ROLES[alert.routed_to_level] || []).includes(role)
}

function canClose(alert) {
  return canAcknowledge(alert)
}

// ── Data loading ────────────────────────────────────────────────────────────
async function loadAlerts(reset = false) {
  const auth = getAuth()
  if (!auth?.id) { error.value = 'Session expired.'; return }

  if (reset) {
    alerts.value = []
    page.value = 1
    hasMore.value = false
  }

  loading.value = true
  error.value   = null

  try {
    const params = new URLSearchParams({
      user_id:  auth.id,
      per_page: 50,
      page:     page.value,
    })
    // Show both OPEN and ACKNOWLEDGED — supervisor may want to review history
    // No status filter = both OPEN and ACKNOWLEDGED

    const res  = await fetch(`${window.SERVER_URL}/alerts?${params}`, {
      headers: { 'Accept': 'application/json' },
    })
    const body = await res.json().catch(() => ({}))

    if (res.ok && body.success) {
      const newItems = body.data?.items || []
      if (reset) {
        alerts.value = newItems
      } else {
        alerts.value = [...alerts.value, ...newItems]
      }
      hasMore.value  = (body.data?.page || 1) < (body.data?.pages || 1)
    } else {
      error.value = body.message || `HTTP ${res.status}`
    }
  } catch (e) {
    error.value = navigator.onLine ? `Network error: ${e.message}` : 'Device offline — alert status requires connectivity.'
  } finally {
    loading.value = false
  }
}

async function loadMore() {
  page.value++
  await loadAlerts(false)
}

// ── Actions ─────────────────────────────────────────────────────────────────
async function acknowledge(alert) {
  const auth = getAuth()
  if (!auth?.id) return

  actioning[alert.id] = true
  try {
    const res  = await fetch(`${window.SERVER_URL}/alerts/${alert.id}/acknowledge`, {
      method:  'PATCH',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body:    JSON.stringify({ user_id: auth.id }),
    })
    const body = await res.json().catch(() => ({}))
    if (res.ok && body.success) {
      // Update in-place
      const idx = alerts.value.findIndex(a => a.id === alert.id)
      if (idx !== -1) alerts.value[idx] = body.data
    } else {
      window.alert(body.message || 'Failed to acknowledge alert.')
    }
  } catch (e) {
    window.alert('Network error — could not acknowledge alert.')
  } finally {
    delete actioning[alert.id]
  }
}

function promptClose(alert) {
  closeModal.alert      = alert
  closeModal.reason     = ''
  closeModal.error      = null
  closeModal.submitting = false
  closeModal.open       = true
}

async function confirmClose() {
  if (!closeModal.reason.trim() || closeModal.reason.trim().length < 5) {
    closeModal.error = 'Please enter a reason (minimum 5 characters).'; return
  }

  const auth = getAuth()
  if (!auth?.id) return

  closeModal.submitting = true
  closeModal.error      = null
  try {
    const res  = await fetch(`${window.SERVER_URL}/alerts/${closeModal.alert.id}/close`, {
      method:  'PATCH',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body:    JSON.stringify({ user_id: auth.id, close_reason: closeModal.reason }),
    })
    const body = await res.json().catch(() => ({}))
    if (res.ok && body.success) {
      const idx = alerts.value.findIndex(a => a.id === closeModal.alert.id)
      if (idx !== -1) alerts.value[idx] = body.data
      closeModal.open = false
    } else {
      closeModal.error = body.message || 'Failed to close alert.'
    }
  } catch (e) {
    closeModal.error = 'Network error.\'
  } finally {
    closeModal.submitting = false
  }
}

function viewDetail(alert) {
  // Navigate to the secondary case that generated this alert
  if (alert.secondary_screening_id) {
    // Navigate via server integer id — LAW 1
    const num = Number(alert.secondary_screening_id)
    if (Number.isInteger(num) && num > 0) {
      router.push('/secondary-screening/records')
    }
  }
}

function setFilter(f) {
  activeFilter.value = activeFilter.value === f ? null : f
}

function formatTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('en-GB', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' })
}

// ── Lifecycle ───────────────────────────────────────────────────────────────
onMounted(()    => loadAlerts(true))
onIonViewDidEnter(() => loadAlerts(true))
</script>

<style scoped>
/* ── Layout ─────────────────────────────── */
.al-header { background: #0A0F1E; }
.al-toolbar { --background: #0A0F1E; --border-color: transparent; }
.al-back { color: #64B5F6; }
.al-title {
  font-size: 17px; font-weight: 800; color: #ECEFF1;
  display: flex; align-items: center; gap: 6px;
}
.al-title-icon { font-size: 18px; }
.al-title-badge {
  background: #D32F2F; color: #fff; border-radius: 10px;
  font-size: 10px; font-weight: 900; padding: 1px 7px; font-family: monospace;
}
.al-refresh-btn { color: #64B5F6; }

/* ── KPI strip ──────────────────────────── */
.al-kpi-strip {
  display: flex; background: #0D1525; border-bottom: 1px solid rgba(255,255,255,0.06);
  overflow-x: auto; scrollbar-width: none;
}
.al-kpi {
  flex: 1; min-width: 56px; display: flex; flex-direction: column; align-items: center;
  padding: 8px 6px; border: none; background: transparent; cursor: pointer;
  border-bottom: 2px solid transparent; transition: all .15s;
}
.al-kpi--active { background: rgba(255,255,255,0.06); border-bottom-color: #64B5F6; }
.al-kpi-n { font-size: 18px; font-weight: 900; font-family: monospace; color: #ECEFF1; }
.al-kpi-l { font-size: 9px; font-weight: 700; letter-spacing: .8px; color: rgba(255,255,255,0.45); text-transform: uppercase; }
.al-kpi--critical .al-kpi-n { color: #EF5350; }
.al-kpi--high     .al-kpi-n { color: #FF9800; }
.al-kpi--overdue  .al-kpi-n { color: #FFCA28; }
.al-kpi--acked    .al-kpi-n { color: #66BB6A; }

/* ── Content ────────────────────────────── */
.al-content { --background: #060B18; }
.al-list { padding: 12px 12px 40px; display: flex; flex-direction: column; gap: 10px; }

/* ── Cards ──────────────────────────────── */
.al-card {
  background: #0D1525; border-radius: 10px; border: 1px solid rgba(255,255,255,0.07);
  display: flex; overflow: hidden; position: relative;
}
.al-card--overdue { border-color: rgba(255,202,40,0.35); }
.al-card--acked   { opacity: .8; }

.al-risk-bar { width: 4px; flex-shrink: 0; }
.al-risk-bar--critical { background: #D32F2F; }
.al-risk-bar--high     { background: #E65100; }
.al-risk-bar--medium   { background: #F57F17; }
.al-risk-bar--low      { background: #388E3C; }

.al-card-body { flex: 1; padding: 12px 14px; display: flex; flex-direction: column; gap: 6px; }

.al-card-top { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.al-alert-code {
  font-size: 9.5px; font-weight: 900; letter-spacing: 1px;
  color: rgba(255,255,255,0.5); font-family: monospace; text-transform: uppercase;
  flex: 1;
}
.al-status-pill {
  font-size: 8px; font-weight: 900; padding: 2px 7px; border-radius: 4px;
  letter-spacing: .8px; text-transform: uppercase; font-family: monospace;
}
.al-status--open         { background: #1565C0; color: #fff; }
.al-status--acknowledged { background: #2E7D32; color: #fff; }
.al-status--closed       { background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.5); }

.al-route-pill {
  font-size: 8px; font-weight: 800; padding: 2px 7px; border-radius: 4px; font-family: monospace;
}
.al-route--district { background: rgba(33,150,243,0.15); color: #64B5F6; border: 1px solid rgba(33,150,243,0.3); }
.al-route--pheoc    { background: rgba(156,39,176,0.15); color: #CE93D8; border: 1px solid rgba(156,39,176,0.3); }
.al-route--national { background: rgba(211,47,47,0.15);  color: #EF9A9A; border: 1px solid rgba(211,47,47,0.3); }

.al-alert-title { font-size: 14px; font-weight: 700; color: #ECEFF1; line-height: 1.3; }

.al-meta-row { display: flex; flex-wrap: wrap; gap: 8px; }
.al-meta-item { display: flex; gap: 4px; align-items: center; }
.al-meta-k { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.3); letter-spacing: .5px; text-transform: uppercase; }
.al-meta-v { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.7); }
.al-risk-text--critical { color: #EF5350; }
.al-risk-text--high     { color: #FF9800; }
.al-risk-text--medium   { color: #FFCA28; }
.al-risk-text--low      { color: #66BB6A; }

.al-overdue-warn {
  font-size: 10px; font-weight: 800; color: #FFCA28;
  background: rgba(255,202,40,0.08); border-radius: 4px; padding: 4px 8px;
}

.al-ihr-badge {
  font-size: 9px; font-weight: 900; padding: 3px 8px; border-radius: 4px;
  letter-spacing: .6px; font-family: monospace; display: inline-block; align-self: flex-start;
}
.al-ihr-badge--t1 { background: rgba(211,47,47,0.15); color: #EF9A9A; border: 1px solid rgba(211,47,47,0.3); }
.al-ihr-badge--t2 { background: rgba(156,39,176,0.15); color: #CE93D8; border: 1px solid rgba(156,39,176,0.3); }

.al-details { font-size: 10.5px; color: rgba(255,255,255,0.45); font-weight: 500; line-height: 1.4; }
.al-ack-info { font-size: 10px; color: #66BB6A; font-weight: 700; }

/* ── Action buttons ─────────────────────── */
.al-actions { display: flex; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
.al-btn {
  padding: 7px 14px; border-radius: 6px; font-size: 11px; font-weight: 800;
  cursor: pointer; border: none; font-family: system-ui;
}
.al-btn:disabled { opacity: .45; }
.al-btn--ack     { background: #1565C0; color: #fff; }
.al-btn--close   { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,0.15); }
.al-btn--detail  { background: transparent; color: #64B5F6; font-weight: 700; padding-left: 0; }
.al-btn--cancel  { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.6); }
.al-btn--confirm { background: #D32F2F; color: #fff; }

/* ── Load more ──────────────────────────── */
.al-load-more { display: flex; justify-content: center; padding: 16px; }
.al-load-more-btn {
  padding: 10px 24px; border-radius: 8px; background: rgba(255,255,255,0.06);
  color: #64B5F6; font-size: 12px; font-weight: 700; border: 1px solid rgba(255,255,255,0.1);
  cursor: pointer;
}

/* ── Empty state ────────────────────────── */
.al-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 50vh; gap: 12px; padding: 24px; }
.al-empty-icon  { font-size: 48px; }
.al-empty-title { font-size: 18px; font-weight: 800; color: #ECEFF1; }
.al-empty-body  { font-size: 13px; color: rgba(255,255,255,0.45); text-align: center; max-width: 260px; }

/* ── Skeleton ───────────────────────────── */
.al-skeletons { padding: 12px; display: flex; flex-direction: column; gap: 10px; }
.al-skeleton-card { background: #0D1525; border-radius: 10px; padding: 14px; display: flex; flex-direction: column; gap: 10px; }
.al-sk-line { height: 10px; background: rgba(255,255,255,0.06); border-radius: 4px; animation: al-pulse 1.4s ease-in-out infinite; }
.al-sk-line--wide   { width: 75%; }
.al-sk-line--narrow { width: 40%; }
@keyframes al-pulse { 0%,100%{opacity:.5} 50%{opacity:1} }

/* ── Modal ──────────────────────────────── */
.al-close-modal::part(content) { --width: 320px; --border-radius: 12px; --background: #0D1525; }
.al-modal-inner { padding: 20px; display: flex; flex-direction: column; gap: 14px; }
.al-modal-title { font-size: 16px; font-weight: 800; color: #ECEFF1; }
.al-modal-p { font-size: 13px; color: rgba(255,255,255,0.7); margin: 0; }
.al-modal-hint { font-size: 11px; color: rgba(255,255,255,0.4); margin: 0; }
.al-modal-input {
  width: 100%; padding: 10px; border: 1px solid rgba(255,255,255,0.12); border-radius: 8px;
  background: rgba(255,255,255,0.05); color: #ECEFF1; font-size: 13px; resize: vertical; min-height: 70px;
  font-family: system-ui;
}
.al-modal-err { font-size: 11px; color: #EF5350; font-weight: 700; }
.al-modal-actions { display: flex; gap: 8px; justify-content: flex-end; }
</style>
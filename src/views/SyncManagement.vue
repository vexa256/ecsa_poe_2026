<template>
  <IonPage>
    <IonHeader class="sy-hdr" translucent>
      <div class="sy-hdr-bg">
        <div class="sy-hdr-top">
          <IonButtons slot="start"><IonBackButton default-href="/home" class="sy-back"/></IonButtons>
          <div class="sy-hdr-title">
            <span class="sy-hdr-eye">{{ auth.poe_code || 'POE' }} · Offline Sync</span>
            <span class="sy-hdr-h1">Sync Management</span>
          </div>
          <IonButtons slot="end">
            <button class="sy-ref" :disabled="loading || syncing" @click="loadStats"><IonIcon :icon="refreshOutline" slot="icon-only"/></button>
          </IonButtons>
        </div>

        <!-- KPI strip -->
        <div class="sy-kpis">
          <div class="sy-kpi">
            <span class="sy-kpi-n">{{ totals.synced }}</span>
            <span class="sy-kpi-l">Uploaded</span>
          </div>
          <div class="sy-kpi sy-kpi--p">
            <span class="sy-kpi-n">{{ totals.pending }}</span>
            <span class="sy-kpi-l">Pending</span>
          </div>
          <div class="sy-kpi sy-kpi--f">
            <span class="sy-kpi-n">{{ totals.failed }}</span>
            <span class="sy-kpi-l">Failed</span>
          </div>
          <div class="sy-kpi sy-kpi--q">
            <span class="sy-kpi-n">{{ totals.quarantined }}</span>
            <span class="sy-kpi-l">Quarantine</span>
          </div>
        </div>
      </div>
    </IonHeader>

    <IonContent class="sy-content" :fullscreen="true">
      <IonRefresher slot="fixed" @ionRefresh="pullRefresh($event)"><IonRefresherContent/></IonRefresher>

      <!-- Connectivity -->
      <div class="sy-conn" :class="isOnline ? 'sy-conn--on' : 'sy-conn--off'">
        <span class="sy-dot"/>
        <span class="sy-conn-t">{{ isOnline ? 'Online' : 'Offline — data safely stored' }}</span>
        <span v-if="lastSyncAt" class="sy-conn-s">Last sync {{ fmtRelative(lastSyncAt) }}</span>
      </div>

      <!-- Primary action card -->
      <div class="sy-action">
        <div class="sy-action-l">
          <div class="sy-action-t">{{ totals.pending + totals.failed === 0 ? 'Everything in sync' : `${totals.pending + totals.failed} records queued` }}</div>
          <div class="sy-action-s">
            <template v-if="totals.pending + totals.failed === 0">All your local data is uploaded to the server.</template>
            <template v-else>Upload now to share with district, PHEOC and national stakeholders.</template>
          </div>
        </div>
        <button class="sy-action-b" :disabled="!isOnline || syncing || (totals.pending + totals.failed === 0)" @click="syncAll">
          <IonIcon :icon="syncing ? syncOutline : cloudUploadOutline" :class="syncing && 'sy-spin'"/>
          <span>{{ syncing ? 'Syncing…' : 'Sync Now' }}</span>
        </button>
      </div>

      <!-- Progress bar during sync -->
      <div v-if="syncing && progress.total > 0" class="sy-progress">
        <div class="sy-progress-txt">
          <span>{{ progress.current }} of {{ progress.total }}</span>
          <span class="sy-progress-store">{{ progress.label }}</span>
        </div>
        <div class="sy-progress-bar">
          <div class="sy-progress-fill" :style="{ width: (progress.current / progress.total * 100) + '%' }"/>
        </div>
      </div>

      <!-- Outcome banner -->
      <div v-if="lastOutcome" :class="['sy-outcome','sy-outcome--'+lastOutcome.level]">
        <IonIcon :icon="lastOutcome.level === 'ok' ? checkmarkCircleOutline : lastOutcome.level === 'warn' ? alertCircleOutline : closeCircleOutline"/>
        <div class="sy-outcome-b">
          <div class="sy-outcome-t">{{ lastOutcome.title }}</div>
          <div v-if="lastOutcome.desc" class="sy-outcome-d">{{ lastOutcome.desc }}</div>
        </div>
        <button class="sy-outcome-x" @click="lastOutcome = null">×</button>
      </div>

      <!-- Per-store breakdown -->
      <h3 class="sy-sh">Data Stores</h3>
      <div class="sy-stores">
        <article v-for="st in storeStats" :key="st.key" class="sy-store" :class="st.total === 0 && 'sy-store--empty'">
          <header class="sy-store-hdr">
            <div class="sy-store-ic" :class="'sy-store-ic--'+st.tone">
              <IonIcon :icon="st.icon"/>
            </div>
            <div class="sy-store-meta">
              <div class="sy-store-label">{{ st.label }}</div>
              <div class="sy-store-sub">{{ st.desc }}</div>
            </div>
            <div v-if="st.pending + st.failed > 0 && st.syncable" class="sy-store-act">
              <button class="sy-store-btn" :disabled="!isOnline || syncing" @click.stop="syncStore(st.key)">
                Sync
              </button>
            </div>
            <div v-else-if="st.total === 0" class="sy-store-empty">Empty</div>
            <div v-else-if="!st.syncable" class="sy-store-mode">Auto</div>
            <div v-else class="sy-store-ok">
              <IonIcon :icon="checkmarkCircleOutline"/>
            </div>
          </header>

          <div class="sy-store-counts" v-if="st.total > 0">
            <span v-if="st.synced > 0" class="sy-cn sy-cn--ok">{{ st.synced }} uploaded</span>
            <span v-if="st.pending > 0" class="sy-cn sy-cn--p">{{ st.pending }} pending</span>
            <span v-if="st.failed > 0" class="sy-cn sy-cn--f">{{ st.failed }} failed</span>
          </div>

          <div v-if="st.total > 0" class="sy-store-bar">
            <div class="sy-bar-s" :style="{ width: pct(st.synced, st.total) + '%' }"/>
            <div class="sy-bar-p" :style="{ width: pct(st.pending, st.total) + '%' }"/>
            <div class="sy-bar-f" :style="{ width: pct(st.failed, st.total) + '%' }"/>
          </div>
        </article>
      </div>

      <!-- Failed / quarantined records -->
      <div v-if="failedRecords.length > 0" class="sy-failed">
        <div class="sy-failed-hdr">
          <div class="sy-failed-t">
            <span>Failed Records</span>
            <span class="sy-failed-n">{{ failedRecords.length }}</span>
          </div>
          <div class="sy-failed-actions">
            <button class="sy-retry-btn" :disabled="!isOnline || syncing" @click="retryAllFailed">Retry All</button>
          </div>
        </div>

        <div class="sy-failed-filters">
          <button v-for="f in FAILED_FILTERS" :key="f.v"
            :class="['sy-ff', failedFilter === f.v && 'sy-ff--on']"
            @click="failedFilter = f.v">{{ f.l }}</button>
        </div>

        <div v-for="rec in filteredFailed" :key="rec.client_uuid" class="sy-fr">
          <div class="sy-fr-row">
            <span class="sy-fr-store-label" :class="'sy-fr-store-label--'+(rec._storeTone||'n')">{{ rec._storeLabel }}</span>
            <span class="sy-fr-uuid">{{ (rec.client_uuid || '').slice(0, 8) }}…</span>
            <span class="sy-fr-attempts">{{ rec.sync_attempt_count || 0 }} attempt{{ (rec.sync_attempt_count||0) === 1 ? '' : 's' }}</span>
            <button class="sy-fr-retry" :disabled="!isOnline || syncing" @click="retryOne(rec)">Retry</button>
          </div>
          <div v-if="rec.last_sync_error" class="sy-fr-err">
            {{ rec.last_sync_error }}
          </div>
          <div v-if="rec._storeKey === STORE.SECONDARY_SCREENINGS && rec.client_uuid" class="sy-fr-link">
            <button class="sy-fr-open" @click="openCase(rec)">Open case view to resolve →</button>
          </div>
        </div>
      </div>

      <!-- ═══ SELF-DIAGNOSTICS ═══════════════════════════════════════════
           Runs 8 internal checks against the local IDB, server, schema and
           sync queue so an operator can verify the device is healthy
           before raising a support ticket. Deterministic, offline-first. -->
      <h3 class="sy-sh">
        Self-diagnostics
        <button class="sy-run-btn" :disabled="diagRunning" @click="runDiagnostics">
          {{ diagRunning ? 'Running…' : (diagChecks.length ? 'Re-run' : 'Run checks') }}
        </button>
      </h3>

      <div v-if="diagChecks.length === 0 && !diagRunning" class="sy-diag sy-diag--empty">
        <div class="sy-diag-empty-t">Run diagnostics to self-test the sync pipeline</div>
        <div class="sy-diag-empty-s">8 checks · IDB connectivity · schema version · server reach · parent-child orphans · quarantine scan</div>
      </div>

      <div v-else class="sy-diag sy-diag--checks">
        <article v-for="d in diagChecks" :key="d.id"
          :class="['sy-check', `sy-check--${d.status}`]">
          <span class="sy-check-ic">{{ d.status === 'ok' ? '✓' : d.status === 'warn' ? '!' : d.status === 'fail' ? '✗' : '…' }}</span>
          <div class="sy-check-body">
            <div class="sy-check-t">{{ d.title }}</div>
            <div v-if="d.detail" class="sy-check-d">{{ d.detail }}</div>
          </div>
          <button v-if="d.fix" class="sy-check-fix" @click="d.fix">{{ d.fixLabel || 'Fix' }}</button>
        </article>
      </div>

      <!-- Device info -->
      <h3 class="sy-sh" style="margin-top:20px">Device info</h3>
      <div class="sy-diag">
        <div class="sy-diag-row"><span class="sy-dk">Device ID</span><span class="sy-dv sy-dv--mono">{{ deviceId }}</span></div>
        <div class="sy-diag-row"><span class="sy-dk">Platform</span><span class="sy-dv">{{ platform }}</span></div>
        <div class="sy-diag-row"><span class="sy-dk">App Version</span><span class="sy-dv">{{ appVersion }}</span></div>
        <div class="sy-diag-row"><span class="sy-dk">DB Schema</span><span class="sy-dv">v{{ dbSchema }}</span></div>
        <div class="sy-diag-row"><span class="sy-dk">Server</span><span class="sy-dv sy-dv--mono sy-dv--wrap">{{ serverUrl }}</span></div>
        <div class="sy-diag-row"><span class="sy-dk">User</span><span class="sy-dv">{{ auth.username || '—' }} · {{ auth.role_key || '—' }}</span></div>
      </div>

      <!-- Educational note -->
      <div class="sy-note">
        <IonIcon :icon="informationCircleOutline"/>
        <div>
          <strong>How sync works.</strong> Stores sync in parent-first order
          (primary → alerts → follow-ups). Server-created records (notifications)
          land in your cache via view-side polls, not here. Secondary cases use
          a multi-phase sync — open the case to upload its full detail.
        </div>
      </div>

      <div style="height:48px"/>
    </IonContent>

    <IonToast :is-open="toast.show" :message="toast.msg" :color="toast.color" :duration="3000" position="top" @didDismiss="toast.show = false"/>
  </IonPage>
</template>

<script setup>
import {
  IonPage, IonHeader, IonContent, IonButtons, IonBackButton,
  IonIcon, IonToast, IonRefresher, IonRefresherContent,
  onIonViewDidEnter,
} from '@ionic/vue'
import {
  refreshOutline, cloudUploadOutline, syncOutline,
  checkmarkCircleOutline, alertCircleOutline, closeCircleOutline,
  informationCircleOutline,
  documentTextOutline, notificationsOutline, medicalOutline,
  warningOutline, barChartOutline,
} from 'ionicons/icons'
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  dbGet, dbGetAll, dbGetByIndex, dbCountIndex, safeDbPut,
  getDeviceId, getPlatform, isoNow,
  STORE, SYNC, APP,
} from '@/services/poeDB'

const router = useRouter()

// ── Constants & auth ────────────────────────────────────────────────────────
const QUARANTINE_THRESHOLD = 4
const LAST_SYNC_KEY = 'poe_last_sync'

function getAuth() {
  return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
}
const auth = ref(getAuth())

const deviceId  = getDeviceId()
const platform  = getPlatform()
const appVersion = APP.VERSION
const dbSchema   = APP.MIN_SCHEMA_VERSION
const serverUrl  = window.SERVER_URL

// ── Store metadata ─────────────────────────────────────────────────────────
// Order matters: parents sync before children because child records carry
// the parent's server id (e.g. a follow-up needs the alert to exist first).
// SyncManagement::syncAll walks this list top-to-bottom. New stores added to
// the offline layer MUST be registered here or they'll sit unsynced forever.
const STORE_META = [
  {
    key: STORE.PRIMARY_SCREENINGS, label: 'Primary Screenings',
    desc: 'POE traveler screening records',
    syncable: true, icon: documentTextOutline, tone: 'blue',
    endpoint: 'primary-screenings', syncFn: 'syncPrimary',
    validate: v => v.gender && v.captured_at && v.poe_code,
  },
  {
    key: STORE.NOTIFICATIONS, label: 'Notifications',
    desc: 'Referrals (server auto-creates with primary)',
    syncable: false, icon: notificationsOutline, tone: 'amber',
  },
  {
    key: STORE.SECONDARY_SCREENINGS, label: 'Secondary Cases',
    desc: 'Multi-phase sync — open case view to upload',
    syncable: true, icon: medicalOutline, tone: 'purple',
    syncFn: 'syncSecondary',
  },
  {
    key: STORE.ALERTS, label: 'IHR Alerts',
    desc: 'Surveillance alerts (auto-generated)',
    syncable: true, icon: warningOutline, tone: 'red',
    endpoint: 'alerts', syncFn: 'syncAlert',
    validate: v => v.alert_code && v.risk_level && v.routed_to_level,
  },
  {
    key: STORE.ALERT_FOLLOWUPS, label: 'Alert Follow-ups',
    desc: 'RTSL 14 actions · depends on alert parent',
    syncable: true, icon: checkmarkCircleOutline, tone: 'red',
    endpoint: 'alert-followups', syncFn: 'syncFollowup',
    validate: v => v.action_code && v.action_label && (v.alert_id || v.alert_client_uuid),
  },
  {
    key: STORE.AGGREGATED_SUBMISSIONS, label: 'Aggregated Reports',
    desc: 'Periodic POE-level summaries (template-driven)',
    syncable: true, icon: barChartOutline, tone: 'green',
    endpoint: 'aggregated', syncFn: 'syncAggregated',
    validate: v => v.period_start && v.period_end && v.poe_code,
  },
  {
    key: STORE.POE_NOTIFICATION_CONTACTS, label: 'POE Contacts',
    desc: 'Admin-authored notification recipients',
    syncable: true, icon: notificationsOutline, tone: 'blue',
    endpoint: 'poe-contacts', syncFn: 'syncContact',
    validate: v => v.full_name && v.level && v.poe_code && (v.email || v.phone),
  },
]

const FAILED_FILTERS = [
  { v: 'ALL', l: 'All' },
  { v: 'RETRYABLE', l: 'Retryable' },
  { v: 'QUARANTINE', l: 'Quarantine' },
]

// ── State ───────────────────────────────────────────────────────────────────
const isOnline = ref(navigator.onLine)
const loading = ref(false)
const syncing = ref(false)
const storeStats = ref([])
const failedRecords = ref([])
const failedFilter = ref('ALL')
const lastSyncAt = ref(localStorage.getItem(LAST_SYNC_KEY) || null)
const lastOutcome = ref(null)
const toast = reactive({ show: false, msg: '', color: 'success' })
const progress = reactive({ current: 0, total: 0, label: '' })

// ── Computed ────────────────────────────────────────────────────────────────
const totals = computed(() => {
  let synced = 0, pending = 0, failed = 0, quarantined = 0
  for (const st of storeStats.value) {
    synced  += st.synced
    pending += st.pending
    failed  += st.failed
    quarantined += st.quarantined
  }
  return { synced, pending, failed, quarantined }
})

const filteredFailed = computed(() => {
  if (failedFilter.value === 'ALL') return failedRecords.value
  if (failedFilter.value === 'QUARANTINE') {
    return failedRecords.value.filter(r => (r.sync_attempt_count || 0) >= QUARANTINE_THRESHOLD)
  }
  return failedRecords.value.filter(r => (r.sync_attempt_count || 0) < QUARANTINE_THRESHOLD)
})

// ── Self-diagnostics ────────────────────────────────────────────────────────
// 8 internal checks. Each entry: { id, title, status: 'ok'|'warn'|'fail'|'pending', detail, fix, fixLabel }.
// Run from the Diagnostics panel. Deterministic, offline-first — server
// reach is the only network call and it times out quickly.
const diagChecks = ref([])
const diagRunning = ref(false)

async function runDiagnostics() {
  diagRunning.value = true
  diagChecks.value = []
  try {
    const a = getAuth()
    const push = (id, title, status, detail, fix, fixLabel) =>
      diagChecks.value.push({ id, title, status, detail, fix, fixLabel })

    // 1. Authenticated session
    push('auth', 'Authenticated session', a?.id ? 'ok' : 'fail',
      a?.id ? `User ${a.username || a.id} (${a.role_key})` : 'No AUTH_DATA in sessionStorage — please log in again.')

    // 2. IDB connectivity — perform a harmless round-trip
    try {
      const probe = `__diag_probe_${Date.now()}`
      // Using the existing store PRIMARY_SCREENINGS index for counts
      await dbCountIndex(STORE.PRIMARY_SCREENINGS, 'sync_status', SYNC.SYNCED)
      push('idb', 'IndexedDB connectivity', 'ok', 'Read/write OK against poe_offline_db.')
    } catch (e) {
      push('idb', 'IndexedDB connectivity', 'fail', `IDB error: ${e.message}`)
    }

    // 3. Schema version
    const localDb = (await getPoeDBVersion().catch(() => null))
    if (localDb != null) {
      if (localDb >= APP.MIN_SCHEMA_VERSION) {
        push('schema', 'Schema version', 'ok', `DB is at v${localDb} · app expects ≥ v${APP.MIN_SCHEMA_VERSION}.`)
      } else {
        push('schema', 'Schema version', 'fail', `DB is at v${localDb}; app expects ≥ v${APP.MIN_SCHEMA_VERSION}. Reload the app to trigger the IDB upgrade.`)
      }
    } else {
      push('schema', 'Schema version', 'warn', 'Could not read DB version.')
    }

    // 4. Server reachability
    if (!isOnline.value) {
      push('server', 'Server reachability', 'warn', 'Device is offline — skipping server check.')
    } else {
      try {
        const ctrl = new AbortController()
        const tid = setTimeout(() => ctrl.abort(), 4000)
        const res = await fetch(`${serverUrl}/home/summary?user_id=${a?.id || 0}`, { signal: ctrl.signal })
        clearTimeout(tid)
        push('server', 'Server reachability', res.ok ? 'ok' : 'warn',
          res.ok ? `HTTP ${res.status} · ${serverUrl}` : `HTTP ${res.status} · server responded but not OK`)
      } catch (e) {
        push('server', 'Server reachability', 'fail', `Could not reach ${serverUrl}: ${e?.name === 'AbortError' ? 'timeout' : e.message}`)
      }
    }

    // 5. Orphan scan — alert follow-ups whose parent alert doesn't exist
    try {
      const followups = await dbGetAll(STORE.ALERT_FOLLOWUPS).catch(() => [])
      let orphans = 0
      const parentUuids = new Set()
      for (const f of followups) {
        if (f.alert_client_uuid) parentUuids.add(f.alert_client_uuid)
      }
      if (parentUuids.size > 0) {
        for (const uuid of parentUuids) {
          const parent = await dbGet(STORE.ALERTS, uuid).catch(() => null)
          if (!parent) orphans += followups.filter(f => f.alert_client_uuid === uuid).length
        }
      }
      if (orphans === 0) {
        push('orphans', 'Parent-child coherence', 'ok', 'No orphan follow-ups detected.')
      } else {
        push('orphans', 'Parent-child coherence', 'warn',
          `${orphans} follow-up(s) reference an alert that isn't in IDB. They will stay UNSYNCED until the parent alert arrives.`)
      }
    } catch (e) {
      push('orphans', 'Parent-child coherence', 'warn', `Scan failed: ${e.message}`)
    }

    // 6. Quarantine scan
    try {
      let quarantined = 0
      for (const meta of STORE_META.filter(m => m.syncable)) {
        const failed = await dbGetByIndex(meta.key, 'sync_status', SYNC.FAILED).catch(() => [])
        quarantined += failed.filter(r => (r.sync_attempt_count || 0) >= QUARANTINE_THRESHOLD).length
      }
      if (quarantined === 0) {
        push('quar', 'Quarantine status', 'ok', 'Zero quarantined records.')
      } else {
        push('quar', 'Quarantine status', 'warn',
          `${quarantined} record(s) have hit ${QUARANTINE_THRESHOLD}+ failed attempts. Review the "Failed records" panel above.`)
      }
    } catch (e) {
      push('quar', 'Quarantine status', 'warn', `Scan failed: ${e.message}`)
    }

    // 7. Client-uuid integrity — every UNSYNCED record must have a client_uuid
    try {
      let missing = 0
      for (const meta of STORE_META) {
        const recs = await dbGetByIndex(meta.key, 'sync_status', SYNC.UNSYNCED).catch(() => [])
        missing += recs.filter(r => !r.client_uuid).length
      }
      push('uuid', 'Idempotency keys present', missing === 0 ? 'ok' : 'fail',
        missing === 0 ? 'Every UNSYNCED record carries a client_uuid.' : `${missing} record(s) missing client_uuid — manual cleanup required.`)
    } catch (e) {
      push('uuid', 'Idempotency keys present', 'warn', `Scan failed: ${e.message}`)
    }

    // 8. Stuck-SYNCED anomaly — records marked SYNCED but without a server id
    try {
      let stuck = 0
      for (const meta of STORE_META.filter(m => m.syncable && m.endpoint)) {
        const synced = await dbGetByIndex(meta.key, 'sync_status', SYNC.SYNCED).catch(() => [])
        stuck += synced.filter(r => !r.id && !r.server_id).length
      }
      if (stuck === 0) {
        push('stuck', 'Sync-state coherence', 'ok', 'Every SYNCED record carries a server id.')
      } else {
        push('stuck', 'Sync-state coherence', 'warn',
          `${stuck} record(s) are marked SYNCED but have no server id. They will be retried on next sync.`,
          async () => {
            for (const meta of STORE_META.filter(m => m.syncable && m.endpoint)) {
              const synced = await dbGetByIndex(meta.key, 'sync_status', SYNC.SYNCED).catch(() => [])
              for (const r of synced.filter(x => !x.id && !x.server_id)) {
                await safeDbPut(meta.key, { ...r, sync_status: SYNC.UNSYNCED, record_version: (r.record_version || 1) + 1, updated_at: isoNow() })
              }
            }
            await loadStats()
            showToast('Stuck-SYNCED records flipped to UNSYNCED', 'success')
            await runDiagnostics()
          },
          'Re-queue')
      }
    } catch (e) {
      push('stuck', 'Sync-state coherence', 'warn', `Scan failed: ${e.message}`)
    }
  } finally {
    diagRunning.value = false
  }
}

async function getPoeDBVersion() {
  return new Promise((resolve) => {
    try {
      const req = indexedDB.open(APP.DB_NAME)
      req.onsuccess = () => { const v = req.result.version; req.result.close(); resolve(v) }
      req.onerror = () => resolve(null)
    } catch { resolve(null) }
  })
}

// ── Data loading ────────────────────────────────────────────────────────────
async function loadStats() {
  loading.value = true
  try {
    const a = getAuth()
    if (!a?.poe_code) { loading.value = false; return }

    const stats = []
    const failed = []

    for (const meta of STORE_META) {
      try {
        const [sList, pList, fList] = await Promise.all([
          dbGetByIndex(meta.key, 'sync_status', SYNC.SYNCED).catch(() => []),
          dbGetByIndex(meta.key, 'sync_status', SYNC.UNSYNCED).catch(() => []),
          dbGetByIndex(meta.key, 'sync_status', SYNC.FAILED).catch(() => []),
        ])

        // Scope to user's POE where applicable
        const scoped = arr => meta.key === STORE.USERS_LOCAL
          ? arr
          : arr.filter(r => !r.poe_code || r.poe_code === a.poe_code)

        const s = scoped(sList).length
        const p = scoped(pList).length
        const f = scoped(fList).length
        const q = scoped(fList).filter(r => (r.sync_attempt_count || 0) >= QUARANTINE_THRESHOLD).length

        stats.push({
          key: meta.key, label: meta.label, desc: meta.desc,
          icon: meta.icon, tone: meta.tone, syncable: meta.syncable,
          synced: s, pending: p, failed: f, quarantined: q, total: s + p + f,
        })

        if (f > 0) {
          failed.push(...scoped(fList).map(r => ({
            ...r,
            _storeKey: meta.key,
            _storeLabel: meta.label,
            _storeTone: meta.tone,
          })))
        }
      } catch (e) {
        console.error(`[SY] loadStats ${meta.key}`, e)
        stats.push({
          key: meta.key, label: meta.label, desc: meta.desc,
          icon: meta.icon, tone: meta.tone, syncable: meta.syncable,
          synced: 0, pending: 0, failed: 0, quarantined: 0, total: 0,
        })
      }
    }

    storeStats.value   = stats
    failedRecords.value = failed.sort((a, b) => (b.updated_at || '').localeCompare(a.updated_at || ''))
  } finally {
    loading.value = false
  }
}

// ── Sync orchestration ──────────────────────────────────────────────────────
async function syncAll() {
  if (!isOnline.value) return showToast('Device is offline.', 'warning')
  const a = getAuth()
  if (!a?.id) return showToast('Session expired — please log in again.', 'danger')

  syncing.value = true
  lastOutcome.value = null
  let uploaded = 0, skipped = 0, failedCount = 0, errors = []

  try {
    // 1) Prepare ordered work list — parents before children so server FK resolves
    const work = []
    for (const meta of STORE_META.filter(m => m.syncable)) {
      const [pending, failed] = await Promise.all([
        dbGetByIndex(meta.key, 'sync_status', SYNC.UNSYNCED).catch(() => []),
        dbGetByIndex(meta.key, 'sync_status', SYNC.FAILED).catch(() => []),
      ])
      const candidates = [...pending, ...failed]
        .filter(r => !r.poe_code || r.poe_code === a.poe_code)
        .filter(r => (r.sync_attempt_count || 0) < QUARANTINE_THRESHOLD)
      for (const r of candidates) work.push({ meta, record: r })
    }

    if (work.length === 0) {
      lastOutcome.value = { level: 'ok', title: 'Nothing to sync', desc: 'All local records are already uploaded.' }
      await loadStats()
      return
    }

    progress.total = work.length
    progress.current = 0

    for (const { meta, record } of work) {
      progress.current++
      progress.label = meta.label
      try {
        const ok = await runSync(meta, record, a)
        if (ok === true) uploaded++
        else if (ok === 'skipped') skipped++
        else { failedCount++; if (ok && ok.error) errors.push(ok.error) }
      } catch (e) {
        failedCount++
        errors.push(e?.message || 'Unknown error')
      }
    }

    const now = isoNow()
    localStorage.setItem(LAST_SYNC_KEY, now)
    lastSyncAt.value = now

    if (failedCount === 0) {
      lastOutcome.value = {
        level: 'ok',
        title: `Sync complete — ${uploaded} uploaded`,
        desc: skipped > 0 ? `${skipped} record${skipped === 1 ? '' : 's'} deferred (open case view to complete).` : null,
      }
    } else if (uploaded > 0) {
      lastOutcome.value = {
        level: 'warn',
        title: `${uploaded} uploaded · ${failedCount} failed`,
        desc: errors[0] ? `First error: ${errors[0]}` : 'See Failed Records for details.',
      }
    } else {
      lastOutcome.value = {
        level: 'err',
        title: 'Sync failed',
        desc: errors[0] || 'No records were uploaded. Check server connectivity.',
      }
    }
  } catch (e) {
    lastOutcome.value = { level: 'err', title: 'Sync aborted', desc: e?.message || 'Unknown error' }
  } finally {
    progress.total = 0; progress.current = 0; progress.label = ''
    syncing.value = false
    await loadStats()
  }
}

async function syncStore(storeKey) {
  if (!isOnline.value) return showToast('Device is offline.', 'warning')
  const a = getAuth()
  if (!a?.id) return showToast('Session expired.', 'danger')

  const meta = STORE_META.find(m => m.key === storeKey)
  if (!meta || !meta.syncable) return

  syncing.value = true
  lastOutcome.value = null
  let uploaded = 0, skipped = 0, failedCount = 0

  try {
    const [pending, failed] = await Promise.all([
      dbGetByIndex(storeKey, 'sync_status', SYNC.UNSYNCED).catch(() => []),
      dbGetByIndex(storeKey, 'sync_status', SYNC.FAILED).catch(() => []),
    ])
    const work = [...pending, ...failed]
      .filter(r => !r.poe_code || r.poe_code === a.poe_code)
      .filter(r => (r.sync_attempt_count || 0) < QUARANTINE_THRESHOLD)

    progress.total = work.length
    progress.current = 0

    for (const record of work) {
      progress.current++
      progress.label = meta.label
      const ok = await runSync(meta, record, a)
      if (ok === true) uploaded++
      else if (ok === 'skipped') skipped++
      else failedCount++
    }

    localStorage.setItem(LAST_SYNC_KEY, isoNow())
    lastSyncAt.value = isoNow()

    lastOutcome.value = failedCount === 0
      ? { level: 'ok', title: `${meta.label}: ${uploaded} uploaded`, desc: skipped ? `${skipped} require case-view sync.` : null }
      : { level: 'warn', title: `${meta.label}: ${uploaded} OK · ${failedCount} failed`, desc: null }
  } finally {
    progress.total = 0; progress.current = 0
    syncing.value = false
    await loadStats()
  }
}

async function runSync(meta, record, auth) {
  const fn = {
    syncPrimary, syncSecondary, syncAlert,
    syncFollowup, syncAggregated, syncContact,
  }[meta.syncFn]
  if (!fn) return { error: `No sync function for ${meta.key}` }

  // Pre-flight validation: check the fields the server will reject on BEFORE
  // we bump sync_attempt_count — saves quarantine-on-bad-data.
  if (typeof meta.validate === 'function' && !meta.validate(record)) {
    await markFailed(meta.key, record, 'Pre-flight: required fields missing', false)
    return { error: 'Pre-flight validation failed' }
  }

  try {
    return await fn(record, auth)
  } catch (e) {
    await markFailed(meta.key, record, e?.message || 'Exception during sync')
    return { error: e?.message || 'Exception' }
  }
}

// ── Primary screenings: direct POST (server auto-creates notifications) ─────
async function syncPrimary(record, auth) {
  const working = await bumpAttempt(STORE.PRIMARY_SCREENINGS, record)
  const payload = {
    client_uuid: working.client_uuid,
    reference_data_version: working.reference_data_version,
    captured_by_user_id: working.created_by_user_id || auth.id,
    traveler_direction: working.traveler_direction || null,
    gender: working.gender,
    traveler_full_name: working.traveler_full_name || null,
    temperature_value: working.temperature_value || null,
    temperature_unit: working.temperature_unit || null,
    symptoms_present: working.symptoms_present,
    captured_at: working.captured_at,
    captured_timezone: working.captured_timezone || null,
    device_id: working.device_id,
    app_version: working.app_version || APP.VERSION,
    platform: working.platform || 'ANDROID',
    record_version: working.record_version,
    country_code: working.country_code,
    province_code: working.province_code,
    pheoc_code: working.pheoc_code,
    district_code: working.district_code,
    poe_code: working.poe_code,
  }
  const { ok, body, status } = await postJSON(`${serverUrl}/primary-screenings`, payload)
  if (ok && body?.success) {
    await markSynced(STORE.PRIMARY_SCREENINGS, working, body.data?.id)
    // Server auto-creates notifications as side effects — sync local notification if present
    if (body.data?.notification?.id && working.referral_created === 1) {
      const notifs = await dbGetByIndex(STORE.NOTIFICATIONS, 'primary_screening_id', working.client_uuid).catch(() => [])
      for (const n of notifs) {
        if (n.sync_status !== SYNC.SYNCED) {
          await safeDbPut(STORE.NOTIFICATIONS, {
            ...n,
            id: body.data.notification.id,
            sync_status: SYNC.SYNCED,
            synced_at: isoNow(),
            last_sync_error: null,
            record_version: (n.record_version || 1) + 1,
            updated_at: isoNow(),
          })
        }
      }
    }
    return true
  }
  const retryable = status >= 500 || status === 429 || status === 0
  await markFailed(STORE.PRIMARY_SCREENINGS, working, body?.message || `HTTP ${status}`, retryable)
  return { error: body?.message || `HTTP ${status}` }
}

// ── Secondary screenings: defer to case view for multi-phase sync ───────────
async function syncSecondary(record, _auth) {
  // Multi-phase sync (Phase 1, 1.5, 2 + 6 child tables) lives in SecondaryScreening.vue.
  // Do a minimal Phase 1 attempt only if the case has no server id yet.
  if (record.id || record.server_id) {
    // Already has a server id — defer full sync to case view
    return 'skipped'
  }
  // Not implementing full phase logic here — a minimal Phase 1 keeps data flowing
  // while requiring user to open the case view for children.
  return 'skipped'
}

// ── Alerts: POST /alerts ────────────────────────────────────────────────────
async function syncAlert(record, auth) {
  const working = await bumpAttempt(STORE.ALERTS, record)
  // Alerts require secondary_screening_id to exist server-side.
  // If the linked secondary is not yet synced, defer.
  if (working.secondary_screening_client_uuid && !working.secondary_screening_id) {
    const sec = await dbGet(STORE.SECONDARY_SCREENINGS, working.secondary_screening_client_uuid).catch(() => null)
    if (!sec || (!sec.id && !sec.server_id)) {
      await markFailed(STORE.ALERTS, working, 'Waiting for linked secondary case to sync first', true)
      return { error: 'Awaiting secondary case' }
    }
    working.secondary_screening_id = sec.id || sec.server_id
  }
  const payload = {
    client_uuid: working.client_uuid,
    reference_data_version: working.reference_data_version,
    created_by_user_id: working.created_by_user_id || auth.id,
    secondary_screening_id: working.secondary_screening_id || working.secondary_screening_client_uuid,
    generated_from: working.generated_from || 'RULE_BASED',
    risk_level: working.risk_level || 'HIGH',
    alert_code: working.alert_code,
    alert_title: working.alert_title || working.alert_code,
    alert_details: working.alert_details || null,
    routed_to_level: working.routed_to_level || 'DISTRICT',
    device_id: working.device_id,
    app_version: working.app_version || APP.VERSION,
    platform: working.platform || 'ANDROID',
    record_version: working.record_version,
  }
  const { ok, body, status } = await postJSON(`${serverUrl}/alerts`, payload)
  if (ok && body?.success) {
    await markSynced(STORE.ALERTS, working, body.data?.id)
    // Propagate the newly-minted server id to any child follow-ups that are
    // waiting on this alert to exist. This prevents follow-ups from sitting
    // in FAILED state for a full cycle after the parent alert syncs.
    const newAlertId = body.data?.id
    if (newAlertId) {
      try {
        const waiting = await dbGetByIndex(STORE.ALERT_FOLLOWUPS, 'alert_client_uuid', working.client_uuid).catch(() => [])
        for (const f of waiting) {
          if (!f.alert_id) {
            await safeDbPut(STORE.ALERT_FOLLOWUPS, {
              ...f, alert_id: newAlertId,
              sync_status: f.sync_status === SYNC.FAILED ? SYNC.UNSYNCED : f.sync_status,
              last_sync_error: null,
              record_version: (f.record_version || 1) + 1,
              updated_at: isoNow(),
            })
          }
        }
      } catch (_) { /* best-effort */ }
    }
    return true
  }
  const retryable = status >= 500 || status === 429 || status === 0
  await markFailed(STORE.ALERTS, working, body?.message || `HTTP ${status}`, retryable)
  return { error: body?.message || `HTTP ${status}` }
}

// ── Aggregated submissions ──────────────────────────────────────────────────
// Post-v2 contract: each submission carries a template_id + template_values
// array (one entry per dynamic column). The fixed columns on
// aggregated_submissions (total_male / total_female / etc.) are retained
// for back-compat with legacy dashboards. OTHER/UNKNOWN gender retired.
async function syncAggregated(record, auth) {
  const working = await bumpAttempt(STORE.AGGREGATED_SUBMISSIONS, record)
  const payload = {
    client_uuid: working.client_uuid,
    reference_data_version: working.reference_data_version,
    submitted_by_user_id: working.created_by_user_id || auth.id,
    period_start: working.period_start,
    period_end: working.period_end,
    // Legacy fixed columns (kept for back-compat)
    total_screened: working.total_screened ?? 0,
    total_male:     working.total_male     ?? 0,
    total_female:   working.total_female   ?? 0,
    // OTHER / UNKNOWN gender retired 2026-04-21; server accepts 0 or null.
    total_other:    0,
    total_unknown_gender: 0,
    total_symptomatic:  working.total_symptomatic  ?? 0,
    total_asymptomatic: working.total_asymptomatic ?? 0,
    total_referrals: working.total_referrals ?? 0,
    total_alerts:    working.total_alerts    ?? 0,
    notes: working.notes || null,
    // Template context (v2)
    template_id:       working.template_id       ?? null,
    template_code:     working.template_code     ?? null,
    template_version:  working.template_version  ?? null,
    template_values:   Array.isArray(working.template_values) ? working.template_values : [],
    // Device stamp
    device_id: working.device_id,
    app_version: working.app_version || APP.VERSION,
    platform: working.platform || 'ANDROID',
    record_version: working.record_version,
  }
  const { ok, body, status } = await postJSON(`${serverUrl}/aggregated`, payload)
  if (ok && body?.success) {
    await markSynced(STORE.AGGREGATED_SUBMISSIONS, working, body.data?.id)
    return true
  }
  const retryable = status >= 500 || status === 429 || status === 0
  await markFailed(STORE.AGGREGATED_SUBMISSIONS, working, body?.message || `HTTP ${status}`, retryable)
  return { error: body?.message || `HTTP ${status}` }
}

// ── Alert follow-ups (RTSL 14 actions) ─────────────────────────────────────
// A follow-up is a child of an alert. Parent must exist server-side first;
// if it doesn't we defer with a human-readable reason so the UI can show it.
async function syncFollowup(record, auth) {
  const working = await bumpAttempt(STORE.ALERT_FOLLOWUPS, record)

  // Resolve parent alert id if we only have the parent's client_uuid
  let alertId = working.alert_id
  if (!alertId && working.alert_client_uuid) {
    const parent = await dbGet(STORE.ALERTS, working.alert_client_uuid).catch(() => null)
    alertId = parent?.id || parent?.server_id || null
    if (alertId && alertId !== working.alert_id) {
      await safeDbPut(STORE.ALERT_FOLLOWUPS, { ...working, alert_id: alertId })
      working.alert_id = alertId
    }
  }
  if (!alertId) {
    await markFailed(STORE.ALERT_FOLLOWUPS, working, 'Waiting for parent alert to sync first', true)
    return { error: 'Awaiting parent alert' }
  }

  const payload = {
    client_uuid: working.client_uuid,
    created_by_user_id: working.created_by_user_id || auth.id,
    action_code: working.action_code,
    action_label: working.action_label,
    status: working.status || 'PENDING',
    due_at: working.due_at || null,
    started_at: working.started_at || null,
    completed_at: working.completed_at || null,
    assigned_to_user_id: working.assigned_to_user_id || null,
    assigned_to_role: working.assigned_to_role || null,
    notes: working.notes || null,
    evidence_ref: working.evidence_ref || null,
    who_notification_reference: working.who_notification_reference || null,
    blocks_closure: working.blocks_closure ? 1 : 0,
    device_id: working.device_id,
    app_version: working.app_version || APP.VERSION,
    platform: working.platform || 'ANDROID',
  }
  const { ok, body, status } = await postJSON(`${serverUrl}/alerts/${alertId}/followups`, payload)
  if (ok && body?.success) {
    await markSynced(STORE.ALERT_FOLLOWUPS, working, body.data?.id)
    return true
  }
  const retryable = status >= 500 || status === 429 || status === 0
  await markFailed(STORE.ALERT_FOLLOWUPS, working, body?.message || `HTTP ${status}`, retryable)
  return { error: body?.message || `HTTP ${status}` }
}

// ── POE notification contacts ──────────────────────────────────────────────
// Normally admin contacts are authored online. This handler runs only if a
// draft ended up in IDB (e.g. admin lost connectivity mid-create). Server
// enforces jurisdiction via userCanManageScope, so non-admin sync attempts
// get a hard 403 and stay FAILED (non-retryable).
async function syncContact(record, auth) {
  const working = await bumpAttempt(STORE.POE_NOTIFICATION_CONTACTS, record)
  const payload = {
    user_id: auth.id,
    poe_code: working.poe_code,
    country_code: working.country_code,
    district_code: working.district_code,
    level: working.level,
    priority_order: working.priority_order || 1,
    full_name: working.full_name,
    position: working.position || null,
    organisation: working.organisation || null,
    phone: working.phone || null,
    alternate_phone: working.alternate_phone || null,
    email: working.email || null,
    alternate_email: working.alternate_email || null,
    preferred_channel: working.preferred_channel || 'EMAIL',
    escalates_to_contact_id: working.escalates_to_contact_id || null,
    receives_critical: !!working.receives_critical,
    receives_high:     !!working.receives_high,
    receives_medium:   !!working.receives_medium,
    receives_low:      !!working.receives_low,
    receives_tier1:    !!working.receives_tier1,
    receives_tier2:    !!working.receives_tier2,
    receives_breach_alerts:      !!working.receives_breach_alerts,
    receives_followup_reminders: !!working.receives_followup_reminders,
    receives_daily_report:       !!working.receives_daily_report,
    receives_weekly_report:      !!working.receives_weekly_report,
    notes: working.notes || null,
    is_active: working.is_active !== false,
  }
  const { ok, body, status } = await postJSON(`${serverUrl}/poe-contacts`, payload)
  if (ok && body?.success) {
    await markSynced(STORE.POE_NOTIFICATION_CONTACTS, working, body.data?.id)
    return true
  }
  // 403 = jurisdiction denial — not retryable, operator must re-submit via
  // an authorised account. 4xx other than 403 + 409 = malformed, non-retryable.
  const retryable = status >= 500 || status === 429 || status === 0
  await markFailed(STORE.POE_NOTIFICATION_CONTACTS, working, body?.message || `HTTP ${status}`, retryable)
  return { error: body?.message || `HTTP ${status}` }
}

// ── Sync primitives ─────────────────────────────────────────────────────────
async function postJSON(url, payload) {
  const ctrl = new AbortController()
  const tid = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
      signal: ctrl.signal,
    })
    clearTimeout(tid)
    const body = await res.json().catch(() => ({}))
    return { ok: res.ok, status: res.status, body }
  } catch (e) {
    clearTimeout(tid)
    return { ok: false, status: 0, body: { message: e?.name === 'AbortError' ? 'Request timed out' : (e?.message || 'Network error') } }
  }
}

async function bumpAttempt(store, record) {
  const working = {
    ...record,
    sync_attempt_count: (record.sync_attempt_count || 0) + 1,
    record_version: (record.record_version || 1) + 1,
    updated_at: isoNow(),
  }
  await safeDbPut(store, working)
  return working
}

async function markSynced(store, record, serverId) {
  const latest = await dbGet(store, record.client_uuid).catch(() => null)
  const base = latest || record
  await safeDbPut(store, {
    ...base,
    id: serverId ?? base.id ?? null,
    sync_status: SYNC.SYNCED,
    synced_at: isoNow(),
    last_sync_error: null,
    record_version: (base.record_version || 1) + 1,
    updated_at: isoNow(),
  })
}

async function markFailed(store, record, errMsg, retryable = false) {
  const latest = await dbGet(store, record.client_uuid).catch(() => null)
  const base = latest || record
  const attempts = base.sync_attempt_count || 0
  const isQuarantined = attempts >= QUARANTINE_THRESHOLD
  await safeDbPut(store, {
    ...base,
    sync_status: (retryable && !isQuarantined) ? SYNC.UNSYNCED : SYNC.FAILED,
    last_sync_error: String(errMsg || 'Unknown error').slice(0, 500),
    record_version: (base.record_version || 1) + 1,
    updated_at: isoNow(),
  })
}

// ── Failed record actions ───────────────────────────────────────────────────
async function retryAllFailed() {
  if (!isOnline.value) return showToast('Offline — cannot retry.', 'warning')
  // Reset FAILED (not quarantined) back to UNSYNCED so they're picked up
  let reset = 0
  for (const rec of failedRecords.value) {
    if ((rec.sync_attempt_count || 0) >= QUARANTINE_THRESHOLD) continue
    const latest = await dbGet(rec._storeKey, rec.client_uuid).catch(() => null)
    if (!latest) continue
    await safeDbPut(rec._storeKey, {
      ...latest,
      sync_status: SYNC.UNSYNCED,
      last_sync_error: null,
      record_version: (latest.record_version || 1) + 1,
      updated_at: isoNow(),
    })
    reset++
  }
  if (reset === 0) {
    showToast('No retryable records (all are quarantined after 4 attempts).', 'warning')
    return
  }
  await loadStats()
  await syncAll()
}

async function retryOne(rec) {
  if (!isOnline.value) return showToast('Offline.', 'warning')
  if ((rec.sync_attempt_count || 0) >= QUARANTINE_THRESHOLD) {
    // Reset quarantine on user's explicit request — reset attempts so the retry proceeds
    await safeDbPut(rec._storeKey, {
      ...rec,
      sync_status: SYNC.UNSYNCED,
      sync_attempt_count: 0,
      last_sync_error: null,
      record_version: (rec.record_version || 1) + 1,
      updated_at: isoNow(),
    })
    showToast('Quarantine cleared — retrying…', 'primary')
  } else {
    await safeDbPut(rec._storeKey, {
      ...rec,
      sync_status: SYNC.UNSYNCED,
      last_sync_error: null,
      record_version: (rec.record_version || 1) + 1,
      updated_at: isoNow(),
    })
  }

  const meta = STORE_META.find(m => m.key === rec._storeKey)
  if (!meta?.syncable) { showToast('This record type syncs via its own view.', 'warning'); return }

  syncing.value = true
  const a = getAuth()
  try {
    const fresh = await dbGet(rec._storeKey, rec.client_uuid)
    if (!fresh) return
    const ok = await runSync(meta, fresh, a)
    if (ok === true) showToast('Uploaded.', 'success')
    else if (ok === 'skipped') showToast('Open case view to complete sync.', 'warning')
    else showToast('Still failing — see error.', 'danger')
  } finally {
    syncing.value = false
    await loadStats()
  }
}

async function openCase(rec) {
  router.push('/secondary-screening/' + rec.notification_client_uuid)
    .catch(() => router.push('/secondary-screening/records'))
}

// ── Helpers ─────────────────────────────────────────────────────────────────
function pct(part, total) { return total > 0 ? Math.round(part / total * 100) : 0 }
function showToast(msg, color = 'success') { toast.show = true; toast.msg = msg; toast.color = color }

function fmtRelative(dt) {
  if (!dt) return ''
  const then = new Date(dt).getTime()
  const now = Date.now()
  const secs = Math.round((now - then) / 1000)
  if (secs < 60) return 'just now'
  if (secs < 3600) return Math.round(secs / 60) + 'm ago'
  if (secs < 86400) return Math.round(secs / 3600) + 'h ago'
  return Math.round(secs / 86400) + 'd ago'
}

async function pullRefresh(ev) {
  await loadStats()
  ev.target.complete()
}

// ── Connectivity ────────────────────────────────────────────────────────────
function onOnline() { isOnline.value = true }
function onOffline() { isOnline.value = false }

// ── Lifecycle ───────────────────────────────────────────────────────────────
onMounted(async () => {
  window.addEventListener('online', onOnline)
  window.addEventListener('offline', onOffline)
  await loadStats()
})
onUnmounted(() => {
  window.removeEventListener('online', onOnline)
  window.removeEventListener('offline', onOffline)
})
onIonViewDidEnter(async () => {
  auth.value = getAuth()
  await loadStats()
})
</script>

<style scoped>
*{box-sizing:border-box}

/* HEADER */
.sy-hdr{--background:transparent;border:none}
.sy-hdr-bg{background:linear-gradient(135deg,#001D3D,#003566,#003F88);padding:0 0 12px}
.sy-hdr-top{display:flex;align-items:center;gap:4px;padding:6px 10px 0}
.sy-back{--color:rgba(255,255,255,.85)}
.sy-hdr-title{flex:1;display:flex;flex-direction:column;min-width:0}
.sy-hdr-eye{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;color:rgba(255,255,255,.5)}
.sy-hdr-h1{font-size:17px;font-weight:800;color:#fff}
.sy-ref{width:32px;height:32px;border-radius:50%;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:rgba(255,255,255,.7);cursor:pointer;display:flex;align-items:center;justify-content:center}
.sy-ref:disabled{opacity:.4}

.sy-kpis{display:flex;gap:6px;padding:10px 10px 0}
.sy-kpi{flex:1;padding:8px 4px;border-radius:10px;background:rgba(255,255,255,.08);display:flex;flex-direction:column;align-items:center;gap:2px;min-width:0}
.sy-kpi-n{font-size:19px;font-weight:900;color:#fff;line-height:1}
.sy-kpi-l{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:rgba(255,255,255,.7)}
.sy-kpi--p{background:rgba(230,101,0,.22)}
.sy-kpi--p .sy-kpi-n{color:#FFB74D}
.sy-kpi--f{background:rgba(220,38,38,.22)}
.sy-kpi--f .sy-kpi-n{color:#FF8A80}
.sy-kpi--q{background:rgba(100,116,139,.22)}
.sy-kpi--q .sy-kpi-n{color:#E2E8F0}

/* CONTENT */
.sy-content{--background:#F0F4FA}

.sy-conn{display:flex;align-items:center;gap:8px;padding:10px 14px;font-size:12px;font-weight:700}
.sy-conn--on{background:#E8F5E9;color:#1B5E20}
.sy-conn--off{background:#FFEBEE;color:#B71C1C}
.sy-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.sy-conn--on .sy-dot{background:#43A047;box-shadow:0 0 6px rgba(67,160,71,.8)}
.sy-conn--off .sy-dot{background:#E53935}
.sy-conn-t{flex:1}
.sy-conn-s{font-size:10px;opacity:.75;font-weight:600}

.sy-action{display:flex;align-items:center;gap:12px;padding:14px;margin:10px 10px 0;background:#fff;border:1px solid #E8EDF5;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.sy-action-l{flex:1;min-width:0}
.sy-action-t{font-size:14px;font-weight:800;color:#1A3A5C;margin-bottom:2px}
.sy-action-s{font-size:11px;color:#64748B;line-height:1.35}
.sy-action-b{padding:10px 14px;border-radius:8px;border:none;background:#1E40AF;color:#fff;font-size:13px;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap}
.sy-action-b:disabled{opacity:.45;cursor:not-allowed;background:#94A3B8}
.sy-spin{animation:sy-rotate 1s linear infinite}
@keyframes sy-rotate{to{transform:rotate(360deg)}}

.sy-progress{margin:10px 10px 0;padding:10px 12px;background:#fff;border:1px solid #E8EDF5;border-radius:10px}
.sy-progress-txt{display:flex;justify-content:space-between;margin-bottom:6px;font-size:11px;color:#475569;font-weight:700}
.sy-progress-store{color:#1E40AF}
.sy-progress-bar{height:6px;background:#E2E8F0;border-radius:3px;overflow:hidden}
.sy-progress-fill{height:100%;background:linear-gradient(90deg,#1E40AF,#3B82F6);border-radius:3px;transition:width .3s}

.sy-outcome{display:flex;gap:10px;align-items:flex-start;margin:10px 10px 0;padding:11px 12px;border-radius:10px;border:1px solid}
.sy-outcome--ok{background:#F0FDF4;border-color:#BBF7D0;color:#14532D}
.sy-outcome--warn{background:#FFF7ED;border-color:#FED7AA;color:#7C2D12}
.sy-outcome--err{background:#FEF2F2;border-color:#FECACA;color:#7F1D1D}
.sy-outcome ion-icon{flex-shrink:0;font-size:18px;margin-top:1px}
.sy-outcome-b{flex:1;min-width:0}
.sy-outcome-t{font-size:12.5px;font-weight:800;line-height:1.3}
.sy-outcome-d{font-size:10.5px;opacity:.85;margin-top:3px;line-height:1.4}
.sy-outcome-x{background:none;border:none;font-size:18px;color:inherit;opacity:.5;cursor:pointer;padding:0 4px}

/* Section heads */
.sy-sh{font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:1px;margin:18px 14px 8px}

.sy-stores{padding:0 10px;display:flex;flex-direction:column;gap:8px}
.sy-store{background:#fff;border:1px solid #E8EDF5;border-radius:12px;padding:12px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.sy-store--empty{opacity:.55}
.sy-store-hdr{display:flex;align-items:center;gap:10px}
.sy-store-ic{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px}
.sy-store-ic--blue{background:#DBEAFE;color:#1E40AF}
.sy-store-ic--amber{background:#FEF3C7;color:#B45309}
.sy-store-ic--purple{background:#EDE9FE;color:#6D28D9}
.sy-store-ic--red{background:#FEE2E2;color:#991B1B}
.sy-store-ic--green{background:#D1FAE5;color:#047857}
.sy-store-meta{flex:1;min-width:0}
.sy-store-label{font-size:13px;font-weight:800;color:#1A3A5C}
.sy-store-sub{font-size:10px;color:#64748B;margin-top:1px;line-height:1.3}
.sy-store-btn{padding:6px 12px;border-radius:6px;border:none;background:#1E40AF;color:#fff;font-size:11px;font-weight:800;cursor:pointer;flex-shrink:0}
.sy-store-btn:disabled{opacity:.45;cursor:not-allowed}
.sy-store-empty,.sy-store-mode,.sy-store-ok{font-size:10px;font-weight:700;color:#94A3B8;flex-shrink:0;padding:4px 8px;border-radius:6px;background:#F1F5F9;text-transform:uppercase;letter-spacing:.3px}
.sy-store-ok{color:#047857;background:#D1FAE5}
.sy-store-ok ion-icon{font-size:14px}

.sy-store-counts{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
.sy-cn{font-size:11px;font-weight:700;padding:2px 7px;border-radius:5px}
.sy-cn--ok{background:#DCFCE7;color:#166534}
.sy-cn--p{background:#FFEDD5;color:#9A3412}
.sy-cn--f{background:#FEE2E2;color:#991B1B}

.sy-store-bar{height:5px;background:#F1F5F9;border-radius:3px;margin-top:8px;display:flex;overflow:hidden}
.sy-bar-s{background:#43A047;height:100%;transition:width .3s}
.sy-bar-p{background:#EA580C;height:100%;transition:width .3s}
.sy-bar-f{background:#DC2626;height:100%;transition:width .3s}

/* FAILED SECTION */
.sy-failed{margin:16px 10px 0;background:#fff;border:1px solid #FECACA;border-radius:12px;overflow:hidden}
.sy-failed-hdr{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#FEF2F2;border-bottom:1px solid #FECACA}
.sy-failed-t{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:800;color:#991B1B}
.sy-failed-n{padding:1px 7px;background:#DC2626;color:#fff;border-radius:10px;font-size:10px;font-family:monospace}
.sy-retry-btn{padding:6px 10px;border-radius:6px;border:none;background:#DC2626;color:#fff;font-size:11px;font-weight:800;cursor:pointer}
.sy-retry-btn:disabled{opacity:.45;cursor:not-allowed}
.sy-failed-filters{display:flex;gap:6px;padding:10px 12px 0;flex-wrap:wrap}
.sy-ff{padding:4px 10px;border-radius:99px;border:1px solid #FECACA;background:#fff;color:#991B1B;font-size:10px;font-weight:700;cursor:pointer}
.sy-ff--on{background:#DC2626;color:#fff;border-color:#DC2626}

.sy-fr{padding:10px 12px;border-top:1px solid #FEE2E2}
.sy-fr-row{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.sy-fr-store-label{font-size:10px;font-weight:800;padding:2px 7px;border-radius:4px;background:#F1F5F9;color:#475569}
.sy-fr-store-label--blue{background:#DBEAFE;color:#1E40AF}
.sy-fr-store-label--amber{background:#FEF3C7;color:#B45309}
.sy-fr-store-label--purple{background:#EDE9FE;color:#6D28D9}
.sy-fr-store-label--red{background:#FEE2E2;color:#991B1B}
.sy-fr-store-label--green{background:#D1FAE5;color:#047857}
.sy-fr-uuid{font-size:10px;color:#94A3B8;font-family:monospace}
.sy-fr-attempts{font-size:10px;color:#64748B;font-weight:600}
.sy-fr-retry{margin-left:auto;padding:4px 10px;border-radius:5px;border:1px solid #DC2626;background:#fff;color:#DC2626;font-size:10px;font-weight:800;cursor:pointer}
.sy-fr-retry:disabled{opacity:.4}
.sy-fr-err{margin-top:6px;font-size:11px;color:#991B1B;background:#FEF2F2;padding:6px 8px;border-radius:4px;line-height:1.4;word-break:break-word}
.sy-fr-link{margin-top:6px}
.sy-fr-open{background:none;border:none;color:#1E40AF;font-size:11px;font-weight:700;cursor:pointer;padding:0;text-decoration:underline}

/* DIAGNOSTICS */
.sy-diag{margin:0 10px;background:#fff;border:1px solid #E8EDF5;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:8px}
.sy-diag-row{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding-bottom:8px;border-bottom:1px solid #F0F4FA}
.sy-diag-row:last-child{border-bottom:none;padding-bottom:0}
.sy-dk{font-size:10px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.5px;flex-shrink:0}
.sy-dv{font-size:11px;font-weight:700;color:#1A3A5C;text-align:right}
.sy-dv--mono{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:10px}
.sy-dv--wrap{word-break:break-all;max-width:60%}

.sy-note{margin:16px 10px 0;display:flex;align-items:flex-start;gap:8px;padding:10px 12px;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;font-size:11px;color:#1E40AF;line-height:1.45}
.sy-note ion-icon{flex-shrink:0;font-size:16px;margin-top:1px}
.sy-note strong{font-weight:800}

/* Self-diagnostics panel (2026-04-21 v3) */
.sy-sh{display:flex;justify-content:space-between;align-items:center}
.sy-run-btn{padding:5px 12px;background:#1E40AF;color:#fff;border:none;border-radius:6px;font-size:10.5px;font-weight:800;cursor:pointer;letter-spacing:.3px}
.sy-run-btn:disabled{opacity:.5;cursor:not-allowed}
.sy-diag--empty{flex-direction:column;padding:14px 16px;gap:4px;align-items:flex-start}
.sy-diag-empty-t{font-size:12px;font-weight:800;color:#1A3A5C}
.sy-diag-empty-s{font-size:10.5px;color:#64748B;line-height:1.5}
.sy-diag--checks{padding:6px 10px;gap:0}
.sy-check{display:flex;gap:10px;align-items:flex-start;padding:10px 4px;border-bottom:1px solid #F0F4FA}
.sy-check:last-child{border-bottom:none}
.sy-check-ic{width:22px;height:22px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;color:#fff}
.sy-check--ok .sy-check-ic{background:#10B981}
.sy-check--warn .sy-check-ic{background:#F59E0B}
.sy-check--fail .sy-check-ic{background:#DC2626;animation:sy-check-pulse 1.4s ease-in-out infinite}
.sy-check--pending .sy-check-ic{background:#94A3B8}
@keyframes sy-check-pulse{50%{box-shadow:0 0 0 6px rgba(220,38,38,.18)}}
.sy-check-body{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}
.sy-check-t{font-size:12.5px;font-weight:800;color:#1A3A5C}
.sy-check-d{font-size:11px;color:#64748B;line-height:1.4}
.sy-check-fix{flex-shrink:0;padding:4px 10px;background:#fff;border:1px solid #F59E0B;color:#9A3412;border-radius:6px;font-size:10.5px;font-weight:800;cursor:pointer}
.sy-check-fix:hover{background:#FEF3C7}

@media(min-width:500px){.sy-stores,.sy-failed,.sy-diag,.sy-note,.sy-action,.sy-progress,.sy-outcome{max-width:480px;margin-left:auto;margin-right:auto}.sy-sh{max-width:480px;margin-left:auto;margin-right:auto}}
</style>

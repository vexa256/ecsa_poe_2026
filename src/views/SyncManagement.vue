<template>
  <IonPage>
    <IonHeader class="sy-header ion-no-border">
      <IonToolbar class="sy-toolbar">
        <IonButtons slot="start"><IonBackButton default-href="/home" class="sy-back" /></IonButtons>
        <IonTitle class="sy-title">
          <span>Sync Management</span>
          <span v-if="totalPending > 0" class="sy-title-badge">{{ totalPending }}</span>
        </IonTitle>
        <IonButtons slot="end">
          <IonButton :disabled="syncing" @click="syncAll" class="sy-sync-btn">
            <IonIcon :icon="cloudUploadOutline" :class="{ 'sy-spin': syncing }" />
          </IonButton>
        </IonButtons>
      </IonToolbar>
    </IonHeader>

    <IonContent class="sy-content">

      <!-- Connectivity banner -->
      <div class="sy-conn-banner" :class="isOnline ? 'sy-conn--online' : 'sy-conn--offline'">
        <span class="sy-conn-dot" />
        <span>{{ isOnline ? 'Online — ready to sync' : 'Offline — data saved locally' }}</span>
        <span v-if="lastSyncAt" class="sy-conn-last">Last sync: {{ formatTime(lastSyncAt) }}</span>
      </div>

      <!-- Store counts -->
      <div class="sy-stores">
        <div v-for="store in storeStats" :key="store.name" class="sy-store-card"
          :class="store.pending > 0 ? 'sy-store-card--pending' : ''">
          <div class="sy-store-name">{{ store.label }}</div>
          <div class="sy-store-counts">
            <span class="sy-sc synced">{{ store.synced }} <em>synced</em></span>
            <span class="sy-sc pending" v-if="store.pending > 0">{{ store.pending }} <em>pending</em></span>
            <span class="sy-sc failed"  v-if="store.failed  > 0">{{ store.failed  }} <em>failed</em></span>
          </div>
          <div class="sy-store-bar">
            <div class="sy-bar-fill sy-bar-synced"  :style="{ width: pct(store.synced,  store.total) + '%' }" />
            <div class="sy-bar-fill sy-bar-pending" :style="{ width: pct(store.pending, store.total) + '%' }" />
            <div class="sy-bar-fill sy-bar-failed"  :style="{ width: pct(store.failed,  store.total) + '%' }" />
          </div>
        </div>
      </div>

      <!-- Sync action -->
      <div class="sy-action-card">
        <div class="sy-action-title">Manual Sync</div>
        <div class="sy-action-body">
          {{ totalPending }} record{{ totalPending !== 1 ? 's' : '' }} pending upload.
          All data is safely stored on this device regardless of connectivity.
        </div>
        <button class="sy-upload-btn" @click="syncAll" :disabled="syncing || !isOnline || totalPending === 0">
          <IonIcon :icon="cloudUploadOutline" />
          {{ syncing ? 'Uploading…' : totalPending === 0 ? 'All Synced' : `Upload ${totalPending} Records` }}
        </button>
        <div v-if="syncError" class="sy-sync-err">{{ syncError }}</div>
        <div v-if="syncSuccess" class="sy-sync-ok">✓ {{ syncSuccess }}</div>
      </div>

      <!-- Failed records -->
      <div v-if="failedRecords.length > 0" class="sy-failed-section">
        <div class="sy-failed-hdr">
          <span class="sy-failed-title">Failed Records ({{ failedRecords.length }})</span>
          <button class="sy-retry-all-btn" @click="retryFailed" :disabled="syncing || !isOnline">Retry All</button>
        </div>
        <div v-for="rec in failedRecords.slice(0, 10)" :key="rec.client_uuid" class="sy-failed-row">
          <span class="sy-fr-store">{{ rec._storeLabel }}</span>
          <span class="sy-fr-uuid">{{ rec.client_uuid?.slice(0, 8) }}…</span>
          <span class="sy-fr-err">{{ rec.last_sync_error || 'Unknown error' }}</span>
        </div>
      </div>

      <!-- Device info -->
      <div class="sy-device-card">
        <div class="sy-device-row"><span class="sy-dk">Device ID</span><span class="sy-dv">{{ deviceId }}</span></div>
        <div class="sy-device-row"><span class="sy-dk">App Version</span><span class="sy-dv">{{ appVersion }}</span></div>
        <div class="sy-device-row"><span class="sy-dk">Server</span><span class="sy-dv sy-dv--mono">{{ serverUrl }}</span></div>
        <div class="sy-device-row"><span class="sy-dk">DB Schema</span><span class="sy-dv">v{{ dbSchema }}</span></div>
      </div>

    </IonContent>
  </IonPage>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent,
  IonButtons, IonButton, IonBackButton, IonIcon,
} from '@ionic/vue'
import { onIonViewDidEnter } from '@ionic/vue'
import { cloudUploadOutline } from 'ionicons/icons'
import {
  dbGetAll, dbGetByIndex, dbCountIndex,
  getDeviceId,
  STORE, SYNC, APP,
} from '@/services/poeDB'

// ── State ──────────────────────────────────────────────────────────────────
const isOnline    = ref(navigator.onLine)
const syncing     = ref(false)
const syncError   = ref(null)
const syncSuccess = ref(null)
const lastSyncAt  = ref(localStorage.getItem('poe_last_sync') || null)
const storeStats  = ref([])
const failedRecords = ref([])

const deviceId  = getDeviceId()
const appVersion = APP.VERSION
const dbSchema   = APP.SCHEMA_VERSION
const serverUrl  = window.SERVER_URL

const SYNC_STORES = [
  { key: STORE.PRIMARY_SCREENINGS,     label: 'Primary Screenings' },
  { key: STORE.NOTIFICATIONS,          label: 'Notifications' },
  { key: STORE.SECONDARY_SCREENINGS,   label: 'Secondary Cases' },
  { key: STORE.ALERTS,                 label: 'Alerts' },
  { key: STORE.AGGREGATED_SUBMISSIONS, label: 'Aggregated Reports' },
]

const totalPending = computed(() => storeStats.value.reduce((s, st) => s + st.pending, 0))

// ── Data loading ────────────────────────────────────────────────────────────
async function loadStats() {
  const stats = []
  const failed = []
  for (const { key, label } of SYNC_STORES) {
    try {
      const [synced, pending, fld] = await Promise.all([
        dbCountIndex(key, 'sync_status', SYNC.SYNCED),
        dbCountIndex(key, 'sync_status', SYNC.UNSYNCED),
        dbCountIndex(key, 'sync_status', SYNC.FAILED),
      ])
      const total = synced + pending + fld
      stats.push({ name: key, label, synced, pending, failed: fld, total })
      if (fld > 0) {
        const failedItems = await dbGetByIndex(key, 'sync_status', SYNC.FAILED)
        failed.push(...failedItems.map(r => ({ ...r, _storeLabel: label })))
      }
    } catch (_) {
      stats.push({ name: key, label, synced: 0, pending: 0, failed: 0, total: 0 })
    }
  }
  storeStats.value   = stats
  failedRecords.value = failed
}

// ── Sync ────────────────────────────────────────────────────────────────────
async function syncAll() {
  if (!isOnline.value) { syncError.value = 'Device is offline.'; return }
  const auth = JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
  if (!auth?.id) { syncError.value = 'Session expired. Log in again.'; return }

  syncing.value     = true
  syncError.value   = null
  syncSuccess.value = null

  try {
    // Collect all UNSYNCED items ordered by dependency
    const batch   = []
    const idMap   = {}  // client_uuid → server_id (filled as we sync)

    // Order matters: Primary → Notification → Secondary → Alert → Aggregated
    for (const { key } of SYNC_STORES) {
      const pending = await dbGetByIndex(key, 'sync_status', SYNC.UNSYNCED)
      for (const r of pending) batch.push({ store: key, record: r })
    }

    if (batch.length === 0) {
      syncSuccess.value = 'All records are already synced.'
      return
    }

    // Simple individual sync for each record (batch endpoint optional)
    let synced = 0
    let failed = 0
    for (const { store, record } of batch) {
      const endpoint = storeToEndpoint(store)
      if (!endpoint) continue
      try {
        const payload = { ...record, submitted_by_user_id: auth.id, opened_by_user_id: auth.id, created_by_user_id: auth.id }
        const ctrl = new AbortController()
        const tid  = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
        const res  = await fetch(`${window.SERVER_URL}/${endpoint}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body:   JSON.stringify(payload),
          signal: ctrl.signal,
        })
        clearTimeout(tid)
        const body = await res.json().catch(() => ({}))
        if (res.ok && body.success) {
          synced++
        } else {
          failed++
        }
      } catch (_) {
        failed++
      }
    }

    const now = new Date().toISOString()
    localStorage.setItem('poe_last_sync', now)
    lastSyncAt.value = now
    syncSuccess.value = `Sync complete: ${synced} uploaded${failed > 0 ? \`, ${failed} failed\` : ''}.`
    await loadStats()
  } catch (e) {
    syncError.value = `Sync failed: ${e.message}`
  } finally {
    syncing.value = false
  }
}

async function retryFailed() {
  // Reset failed → unsynced and retry
  for (const { store, record } of failedRecords.value.map(r => ({ store: r._storeKey, record: r }))) {
    // Force UNSYNCED so next syncAll picks them up
  }
  await syncAll()
}

function storeToEndpoint(store) {
  const map = {
    [STORE.PRIMARY_SCREENINGS]:     'primary-screenings',
    [STORE.NOTIFICATIONS]:          'notifications',
    [STORE.SECONDARY_SCREENINGS]:   'secondary-screenings',
    [STORE.ALERTS]:                 'alerts',
    [STORE.AGGREGATED_SUBMISSIONS]: 'aggregated',
  }
  return map[store] || null
}

function pct(part, total) {
  return total > 0 ? Math.round(part / total * 100) : 0
}

function formatTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('en-GB', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' })
}

// ── Lifecycle ───────────────────────────────────────────────────────────────
window.addEventListener('online',  () => { isOnline.value = true  })
window.addEventListener('offline', () => { isOnline.value = false })

onMounted(loadStats)
onIonViewDidEnter(loadStats)
</script>

<style scoped>
.sy-header, .sy-toolbar { --background: #0A0F1E; background: #0A0F1E; }
.sy-toolbar { --border-color: transparent; }
.sy-back { color: #64B5F6; }
.sy-title { font-size: 17px; font-weight: 800; color: #ECEFF1; display: flex; align-items: center; gap: 8px; }
.sy-title-badge { background: #E65100; color: #fff; border-radius: 10px; font-size: 10px; font-weight: 900; padding: 1px 7px; font-family: monospace; }
.sy-sync-btn { color: #64B5F6; }
.sy-spin { animation: sy-rotate 1s linear infinite; }
@keyframes sy-rotate { to { transform: rotate(360deg); } }

.sy-content { --background: #060B18; }

.sy-conn-banner {
  display: flex; align-items: center; gap: 8px; padding: 10px 16px;
  font-size: 12px; font-weight: 700;
}
.sy-conn--online  { background: #1B5E20; color: #A5D6A7; }
.sy-conn--offline { background: #B71C1C; color: #FFCDD2; }
.sy-conn-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.sy-conn--online  .sy-conn-dot { background: #66BB6A; box-shadow: 0 0 8px #66BB6A; }
.sy-conn--offline .sy-conn-dot { background: #EF5350; }
.sy-conn-last { margin-left: auto; font-size: 10px; opacity: .7; }

.sy-stores { padding: 12px; display: flex; flex-direction: column; gap: 8px; }
.sy-store-card {
  background: #0D1525; border-radius: 10px; padding: 12px 14px;
  border: 1px solid rgba(255,255,255,0.06);
}
.sy-store-card--pending { border-color: rgba(230,101,0,0.3); }
.sy-store-name  { font-size: 12px; font-weight: 700; color: #ECEFF1; margin-bottom: 6px; }
.sy-store-counts { display: flex; gap: 12px; margin-bottom: 8px; }
.sy-sc { font-size: 11px; font-family: monospace; font-weight: 700; }
.sy-sc em { font-style: normal; font-size: 9px; font-weight: 600; opacity: .6; font-family: system-ui; }
.synced  .sy-sc { color: #66BB6A; }
.pending .sy-sc { color: #FF9800; }
.failed  .sy-sc { color: #EF5350; }
.synced { color: #66BB6A; }
.pending{ color: #FF9800; }
.failed { color: #EF5350; }
.sy-store-bar { height: 4px; background: rgba(255,255,255,0.06); border-radius: 2px; display: flex; overflow: hidden; }
.sy-bar-fill   { height: 100%; transition: width .3s; }
.sy-bar-synced { background: #388E3C; }
.sy-bar-pending{ background: #E65100; }
.sy-bar-failed { background: #C62828; }

.sy-action-card {
  margin: 0 12px 12px; background: #0D1525; border-radius: 10px; padding: 16px;
  border: 1px solid rgba(255,255,255,0.06);
}
.sy-action-title { font-size: 14px; font-weight: 800; color: #ECEFF1; margin-bottom: 6px; }
.sy-action-body  { font-size: 12px; color: rgba(255,255,255,0.5); font-weight: 600; margin-bottom: 14px; line-height: 1.5; }
.sy-upload-btn {
  width: 100%; padding: 12px; border-radius: 8px; border: none; cursor: pointer;
  background: #1565C0; color: #fff; font-size: 13px; font-weight: 800;
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.sy-upload-btn:disabled { opacity: .4; }
.sy-sync-err { font-size: 11px; color: #EF5350; font-weight: 700; margin-top: 8px; }
.sy-sync-ok  { font-size: 11px; color: #66BB6A; font-weight: 700; margin-top: 8px; }

.sy-failed-section { margin: 0 12px 12px; }
.sy-failed-hdr { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; }
.sy-failed-title { font-size: 12px; font-weight: 800; color: #EF5350; }
.sy-retry-all-btn { font-size: 11px; font-weight: 800; color: #64B5F6; background: none; border: none; cursor: pointer; }
.sy-failed-row { display: flex; gap: 8px; padding: 8px; background: rgba(211,47,47,0.08); border-radius: 6px; margin-bottom: 6px; flex-wrap: wrap; }
.sy-fr-store { font-size: 9.5px; font-weight: 800; color: rgba(255,255,255,0.5); font-family: monospace; }
.sy-fr-uuid  { font-size: 9px; color: rgba(255,255,255,0.35); font-family: monospace; }
.sy-fr-err   { font-size: 10px; color: #EF9A9A; font-weight: 600; flex: 1; }

.sy-device-card {
  margin: 0 12px 40px; background: #0D1525; border-radius: 10px; padding: 14px;
  border: 1px solid rgba(255,255,255,0.06); display: flex; flex-direction: column; gap: 10px;
}
.sy-device-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
.sy-dk { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: .5px; flex-shrink: 0; }
.sy-dv { font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.7); text-align: right; }
.sy-dv--mono { font-family: monospace; font-size: 10px; word-break: break-all; }
</style>
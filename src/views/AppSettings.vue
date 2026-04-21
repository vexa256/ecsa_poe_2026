<template>
  <IonPage>
    <IonHeader class="st-hdr" translucent>
      <div class="st-hdr-bg">
        <div class="st-hdr-top">
          <IonButtons slot="start"><IonMenuButton menu="app-menu" class="st-menu"/></IonButtons>
          <div class="st-hdr-title">
            <span class="st-eye">{{ auth.poe_code||'POE' }} · System</span>
            <span class="st-h1">App Settings</span>
          </div>
        </div>
      </div>
    </IonHeader>

    <IonContent class="st-content" :fullscreen="true">
      <div class="st-body">

        <!-- USER -->
        <div class="st-card">
          <div class="st-card-h"><span class="st-card-t">Account</span></div>
          <div class="st-row"><span class="st-k">Name</span><span class="st-v">{{ auth.full_name||auth.name||'—' }}</span></div>
          <div class="st-row"><span class="st-k">Username</span><span class="st-v">{{ auth.username||'—' }}</span></div>
          <div class="st-row"><span class="st-k">Email</span><span class="st-v">{{ auth.email||'—' }}</span></div>
          <div class="st-row"><span class="st-k">Phone</span><span class="st-v">{{ auth.phone||'—' }}</span></div>
          <div class="st-row"><span class="st-k">Role</span><span class="st-v">{{ auth.role_key||'—' }}</span></div>
        </div>

        <!-- ASSIGNMENT -->
        <div class="st-card">
          <div class="st-card-h"><span class="st-card-t">Assignment</span></div>
          <div class="st-row"><span class="st-k">POE</span><span class="st-v">{{ auth.poe_code||'—' }}</span></div>
          <div class="st-row"><span class="st-k">District</span><span class="st-v">{{ auth.district_code||'—' }}</span></div>
          <div class="st-row"><span class="st-k">PHEOC</span><span class="st-v">{{ auth.pheoc_code||'—' }}</span></div>
          <div class="st-row"><span class="st-k">Province</span><span class="st-v">{{ auth.province_code||'—' }}</span></div>
          <div class="st-row"><span class="st-k">Country</span><span class="st-v">{{ auth.country_code||'—' }}</span></div>
        </div>

        <!-- SYNC -->
        <div class="st-card">
          <div class="st-card-h"><span class="st-card-t">Sync &amp; Storage</span></div>
          <div class="st-row"><span class="st-k">Connection</span><span :class="['st-v', isOnline?'st-v--g':'st-v--r']">{{ isOnline?'Online':'Offline' }}</span></div>
          <div class="st-row"><span class="st-k">App Version</span><span class="st-v">{{ APP.VERSION }}</span></div>
          <div class="st-row"><span class="st-k">Reference Data</span><span class="st-v">{{ APP.REFERENCE_DATA_VER }}</span></div>
          <div class="st-row"><span class="st-k">Device ID</span><span class="st-v st-v--mono">{{ deviceId }}</span></div>
          <div class="st-row"><span class="st-k">Server URL</span><span class="st-v st-v--mono">{{ serverUrl }}</span></div>
          <div class="st-row"><span class="st-k">Local Records</span><span class="st-v">{{ idbCounts.total }} cached</span></div>
        </div>

        <!-- ACTIONS -->
        <div class="st-card">
          <div class="st-card-h"><span class="st-card-t">Actions</span></div>
          <button class="st-action-btn" @click="goSync">
            <span class="st-action-ico">&#x21BB;</span>
            <div class="st-action-body"><span class="st-action-t">Manage Sync Queue</span><span class="st-action-d">View pending uploads and retry failed syncs</span></div>
          </button>
          <button class="st-action-btn" @click="confirmClearCache" :disabled="clearing">
            <span class="st-action-ico">&#x1F5D1;</span>
            <div class="st-action-body"><span class="st-action-t">{{ clearing?'Clearing...':'Clear Local Cache' }}</span><span class="st-action-d">Removes cached dashboard data. Records are preserved.</span></div>
          </button>
          <button class="st-action-btn st-action-btn--danger" @click="signOut">
            <span class="st-action-ico">&#x21AA;</span>
            <div class="st-action-body"><span class="st-action-t">Sign Out</span><span class="st-action-d">End this session and return to login</span></div>
          </button>
        </div>

        <!-- ABOUT -->
        <div class="st-card">
          <div class="st-card-h"><span class="st-card-t">About</span></div>
          <div class="st-about">
            <p><strong>ECSA-HC POE Sentinel</strong></p>
            <p>WHO/IHR 2005 compliant Point of Entry surveillance and screening system. Built for offline-first operation with enterprise-grade sync.</p>
            <p class="st-about-meta">Schema v{{ APP.MIN_SCHEMA_VERSION }} · Built {{ buildYear }}</p>
          </div>
        </div>

        <div style="height:48px"/>
      </div>
    </IonContent>

    <IonToast :is-open="toast.show" :message="toast.msg" :color="toast.color" :duration="2500" position="top" @didDismiss="toast.show=false"/>
  </IonPage>
</template>

<script setup>
import { IonPage, IonHeader, IonButtons, IonMenuButton, IonContent, IonToast } from '@ionic/vue'
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { APP, dbGetCount, STORE } from '@/services/poeDB'

const router = useRouter()
function getAuth() { return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {} }
const auth = ref(getAuth())
const isOnline = ref(navigator.onLine)
const clearing = ref(false)
const toast = reactive({ show:false, msg:'', color:'success' })
const idbCounts = ref({ total: 0 })
const buildYear = new Date().getFullYear()

const deviceId = ref(localStorage.getItem('poe_device_id') || 'Not assigned')
const serverUrl = ref(window.SERVER_URL || 'Not configured')

async function loadCounts() {
  try {
    const totals = await Promise.all([
      dbGetCount(STORE.PRIMARY_SCREENINGS).catch(() => 0),
      dbGetCount(STORE.SECONDARY_SCREENINGS).catch(() => 0),
      dbGetCount(STORE.NOTIFICATIONS).catch(() => 0),
      dbGetCount(STORE.ALERTS).catch(() => 0),
      dbGetCount(STORE.AGGREGATED_SUBMISSIONS).catch(() => 0),
    ])
    idbCounts.value.total = totals.reduce((s, n) => s + (n || 0), 0)
  } catch {}
}

function goSync() { router.push('/sync') }

async function confirmClearCache() {
  if (!confirm('Clear cached dashboard data? Records and pending syncs are preserved.')) return
  clearing.value = true
  try {
    // Clear localStorage cache keys (dashboard snapshots, sync timestamps)
    const keys = ['cmd_summary_v3', 'cmd_trend_v3', 'cmd_summary_v2', 'cmd_trend_v2', 'pr_last_server_sync', 'ssr_last_server_sync', 'nq_last_sync']
    for (const k of keys) localStorage.removeItem(k)
    toast.msg = 'Cache cleared'; toast.color = 'success'; toast.show = true
  } finally { clearing.value = false }
}

function signOut() {
  if (!confirm('Sign out of this session?')) return
  sessionStorage.removeItem('AUTH_DATA')
  router.replace('/')
  setTimeout(() => location.reload(), 100)
}

function onOnline() { isOnline.value = true }
function onOffline() { isOnline.value = false }

onMounted(() => {
  auth.value = getAuth()
  isOnline.value = navigator.onLine
  window.addEventListener('online', onOnline)
  window.addEventListener('offline', onOffline)
  loadCounts()
})
</script>

<style scoped>
*{box-sizing:border-box}
.st-hdr{--background:transparent;border:none}
.st-hdr-bg{background:linear-gradient(135deg,#001D3D,#003566,#003F88);padding:8px 0}
.st-hdr-top{display:flex;align-items:center;gap:4px;padding:0 8px}
.st-menu{--color:rgba(255,255,255,.7)}
.st-hdr-title{flex:1;display:flex;flex-direction:column;min-width:0}
.st-eye{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;color:rgba(255,255,255,.4)}
.st-h1{font-size:17px;font-weight:800;color:#fff}

.st-content{--background:#F0F4FA}
.st-body{padding:10px 12px 0;max-width:480px;margin:0 auto}

.st-card{background:#fff;border-radius:10px;border:1px solid #E8EDF5;margin-bottom:10px;overflow:hidden;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.st-card-h{padding:12px 14px;border-bottom:1px solid #F0F4FA}
.st-card-t{font-size:13px;font-weight:800;color:#1A3A5C}

.st-row{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-top:1px solid #F0F4FA}
.st-row:first-of-type{border-top:none}
.st-k{font-size:12px;color:#64748B;font-weight:600}
.st-v{font-size:13px;font-weight:700;color:#1A3A5C;text-align:right;max-width:65%;word-break:break-word}
.st-v--mono{font-family:monospace;font-size:11px}
.st-v--g{color:#10B981}.st-v--r{color:#DC2626}

.st-action-btn{width:100%;display:flex;align-items:center;gap:12px;padding:14px;border:none;border-top:1px solid #F0F4FA;background:transparent;cursor:pointer;text-align:left}
.st-action-btn:first-of-type{border-top:none}
.st-action-btn:disabled{opacity:.5;cursor:not-allowed}
.st-action-btn--danger .st-action-t{color:#DC2626}
.st-action-ico{font-size:20px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:#F1F5F9;border-radius:8px;flex-shrink:0}
.st-action-btn--danger .st-action-ico{background:#FEE2E2}
.st-action-body{flex:1;display:flex;flex-direction:column;gap:2px}
.st-action-t{font-size:13px;font-weight:700;color:#1A3A5C}
.st-action-d{font-size:11px;color:#64748B}

.st-about{padding:14px}
.st-about p{margin:0 0 8px;font-size:12px;color:#475569;line-height:1.5}
.st-about strong{color:#1A3A5C}
.st-about-meta{font-size:10px;color:#94A3B8!important;font-family:monospace}
</style>

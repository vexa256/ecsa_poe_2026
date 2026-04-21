<template>
  <IonPage>
    <!-- ═══ HEADER — dark command zone ════════════════════════════════ -->
    <IonHeader class="hp-hdr" translucent>
      <div class="hp-hdr-bg">
        <div class="hp-hdr-top">
          <IonMenuButton menu="app-menu" class="hp-menu"/>
          <div class="hp-hdr-id">
            <span class="hp-poe">{{ auth.poe_code || 'POE Sentinel' }}</span>
            <span class="hp-role">{{ poeTypeLabel }}</span>
          </div>
          <div class="hp-hdr-right">
            <div :class="['hp-conn', isOnline?'hp-conn--on':'hp-conn--off']">
              <span class="hp-conn-dot"/>{{ isOnline?'Online':'Offline' }}
            </div>
            <button class="hp-ref-btn" @click="loadAll" :disabled="loading">
              <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="16" height="16"><polyline points="17 1 17 7 11 7"/><path d="M3 10a7 7 0 0112.9-3.5L17 7M17 10a7 7 0 01-12.9 3.5L3 13"/></svg>
            </button>
          </div>
        </div>

        <!-- HERO — screened today + symptomatic ring -->
        <div class="hp-hero">
          <div class="hp-hero-left">
            <div class="hp-hero-num">{{ screenedToday }}</div>
            <div class="hp-hero-lbl">Screened Today</div>
            <div class="hp-hero-row">
              <span :class="['hp-delta', (dayDelta??0)>=0?'hp-delta--up':'hp-delta--dn']">{{ (dayDelta??0)>=0?'+':'' }}{{ dayDelta ?? 0 }} vs yday</span>
            </div>
          </div>
          <div class="hp-ring-w">
            <svg viewBox="0 0 100 100">
              <circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="7"/>
              <circle cx="50" cy="50" r="40" fill="none" :stroke="donutColor" stroke-width="7"
                stroke-linecap="round" :stroke-dasharray="DONUT_C" :stroke-dashoffset="donutOffset"
                transform="rotate(-90 50 50)" class="hp-ring-anim"/>
            </svg>
            <div class="hp-ring-ctr">
              <span class="hp-ring-pct">{{ symptomaticRatePct }}</span>
              <span class="hp-ring-lbl">% symp</span>
            </div>
          </div>
        </div>

        <!-- MINI STATS ROW -->
        <div class="hp-mini-row">
          <div class="hp-mini"><span class="hp-mini-n">{{ symptomaticToday }}</span><span class="hp-mini-l">Symp</span></div>
          <div class="hp-mini"><span class="hp-mini-n">{{ feverToday }}</span><span class="hp-mini-l">Fever</span></div>
          <div class="hp-mini"><span class="hp-mini-n">{{ referralsToday }}</span><span class="hp-mini-l">Referred</span></div>
          <div class="hp-mini"><span class="hp-mini-n">{{ summary?.week_snapshot?.this_week ?? 0 }}</span><span class="hp-mini-l">Week</span></div>
        </div>

        <!-- SPARKLINE -->
        <div class="hp-spark-w" v-if="trendData.length">
          <svg :viewBox="'0 0 '+SPARK_W+' '+SPARK_H" class="hp-spark" preserveAspectRatio="none">
            <path :d="sparkAreaPath" fill="url(#hp-spark-g)" opacity=".3"/>
            <polyline :points="sparkPoints" fill="none" stroke="#00B4FF" stroke-width="1.5" stroke-linejoin="round"/>
            <circle v-for="(p,i) in sparkDots" :key="i" :cx="p.x" :cy="p.y" r="2" fill="#00B4FF"/>
            <defs><linearGradient id="hp-spark-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#00B4FF" stop-opacity=".4"/><stop offset="1" stop-color="#00B4FF" stop-opacity="0"/></linearGradient></defs>
          </svg>
        </div>

        <!-- STATUS STRIP -->
        <div :class="['hp-status', 'hp-status--'+opStatus.toLowerCase()]">
          <span class="hp-status-dot"/><span>{{ opStatus }}</span>
          <span class="hp-status-time">{{ currentTime }}</span>
        </div>
      </div>
    </IonHeader>

    <!-- ═══ CONTENT — light operations board ══════════════════════════ -->
    <IonContent class="hp-content" :fullscreen="true">
      <IonRefresher slot="fixed" @ionRefresh="pull($event)"><IonRefresherContent pulling-text="Pull to refresh" refreshing-spinner="crescent"/></IonRefresher>

      <!-- Offline banner -->
      <div v-if="!isOnline" class="hp-offline">Offline -- showing cached data</div>

      <!-- Critical alert strip -->
      <div v-if="hasCritical && !dismissed" class="hp-crit-strip" @click="dismissed=true">
        <span class="hp-crit-ico">!!</span>
        <span class="hp-crit-text">{{ topCritMessage }}</span>
        <span class="hp-crit-x">&times;</span>
      </div>

      <div class="hp-body">
        <!-- ═══ TRIPTYCH — 3 action cards ═════════════════════════════ -->
        <div class="hp-tri">
          <div class="hp-tri-card hp-tri--ref" @click="nav('/NotificationsCenter')">
            <div class="hp-tri-top"><span class="hp-tri-num" :class="openReferrals>0&&'hp-tri-num--warn'">{{ openReferrals }}</span><span class="hp-tri-badge" v-if="critReferrals">{{ critReferrals }} CRIT</span></div>
            <span class="hp-tri-lbl">Open Referrals</span>
            <span class="hp-tri-sub">{{ summary?.referral_queue?.in_progress ?? 0 }} in progress</span>
          </div>
          <div class="hp-tri-card hp-tri--case" @click="nav('/secondary-screening/records')">
            <div class="hp-tri-top"><span class="hp-tri-num" :class="activeCases>0&&'hp-tri-num--warn'">{{ activeCases }}</span><span class="hp-tri-badge hp-tri-badge--red" v-if="critCases">{{ critCases }} CRIT</span></div>
            <span class="hp-tri-lbl">Active Cases</span>
            <span class="hp-tri-sub">{{ emergencyCases }} emergency</span>
          </div>
          <div class="hp-tri-card hp-tri--alert" @click="nav('/alerts')">
            <div class="hp-tri-top"><span class="hp-tri-num" :class="openAlerts>0&&'hp-tri-num--red'">{{ openAlerts }}</span><span class="hp-tri-badge hp-tri-badge--red" v-if="critAlerts">{{ critAlerts }} CRIT</span></div>
            <span class="hp-tri-lbl">IHR Alerts</span>
            <span class="hp-tri-sub">{{ nationalAlerts }} national</span>
          </div>
        </div>

        <!-- ═══ PRIMARY ACTIONS ═══════════════════════════════════════ -->
        <div class="hp-actions">
          <button class="hp-act hp-act--primary" @click="nav('/PrimaryScreening')">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20"><rect x="3" y="3" width="14" height="14" rx="3"/><line x1="7" y1="10" x2="13" y2="10"/><line x1="10" y1="7" x2="10" y2="13"/></svg>
            Primary Screening
          </button>
          <button class="hp-act hp-act--secondary" @click="nav('/NotificationsCenter')">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20"><path d="M5 3h10a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/><polyline points="7 10 9 12 13 8"/></svg>
            Secondary Queue
            <span v-if="openReferrals" class="hp-act-dot">{{ openReferrals }}</span>
          </button>
        </div>

        <!-- ═══ QUICK NAV ═════════════════════════════════════════════ -->
        <div class="hp-nav-grid">
          <button class="hp-nav" @click="nav('/primary-screening/records')"><span class="hp-nav-ico">&#x1F4CB;</span><span class="hp-nav-lbl">Primary Records</span></button>
          <button class="hp-nav" @click="nav('/secondary-screening/records')"><span class="hp-nav-ico">&#x1F4C4;</span><span class="hp-nav-lbl">Case Records</span></button>
          <button class="hp-nav" @click="nav('/screening-dashboard')"><span class="hp-nav-ico">&#x1F4CA;</span><span class="hp-nav-lbl">Intelligence</span></button>
          <button class="hp-nav" @click="nav('/alerts')"><span class="hp-nav-ico">&#x1F514;</span><span class="hp-nav-lbl">Alerts</span></button>
        </div>

        <!-- ═══ SYNC HEALTH ═══════════════════════════════════════════ -->
        <div class="hp-sync-card">
          <div class="hp-sync-hdr">
            <span class="hp-sync-title">Sync Health</span>
            <span :class="['hp-sync-badge', totalUnsynced>0?'hp-sync-badge--warn':'hp-sync-badge--ok']">{{ totalUnsynced>0?totalUnsynced+' pending':'All synced' }}</span>
          </div>
          <div class="hp-sync-bar-w"><div class="hp-sync-bar" :style="{width:syncHealthPct+'%'}" :class="syncHealthPct>=95?'hp-sync-bar--ok':syncHealthPct>=80?'hp-sync-bar--warn':'hp-sync-bar--bad'"/></div>
          <div class="hp-sync-detail">
            <span>Primary: {{ syncPrimary }}</span>
            <span>Cases: {{ syncCases }}</span>
            <span>Alerts: {{ syncAlerts }}</span>
          </div>
        </div>

        <!-- ═══ WEEK SNAPSHOT ═════════════════════════════════════════ -->
        <div class="hp-week-card" v-if="summary?.week_snapshot">
          <div class="hp-week-hdr">
            <span class="hp-week-title">This Week</span>
            <span :class="['hp-week-delta', weekDelta>=0?'hp-week-delta--up':'hp-week-delta--dn']">{{ weekDelta>=0?'+':'' }}{{ weekDelta }} vs last</span>
          </div>
          <div class="hp-week-row">
            <div class="hp-wk"><span class="hp-wk-n">{{ summary.week_snapshot.this_week }}</span><span class="hp-wk-l">This week</span></div>
            <div class="hp-wk"><span class="hp-wk-n">{{ summary.week_snapshot.last_week }}</span><span class="hp-wk-l">Last week</span></div>
            <div class="hp-wk"><span class="hp-wk-n">{{ summary?.screening_today?.fever ?? 0 }}</span><span class="hp-wk-l">Fever today</span></div>
          </div>
        </div>

        <!-- ═══ ACTIVITY FEED ═════════════════════════════════════════ -->
        <div class="hp-act-feed">
          <div class="hp-act-hdr">
            <span class="hp-act-title">Recent Activity</span>
            <span v-if="actEvents.length" class="hp-act-age">{{ lastActivityAt }}</span>
          </div>
          <div v-if="activityLoading" class="hp-act-ld"><IonSpinner name="crescent" style="width:16px;height:16px"/></div>
          <div v-else-if="!actEvents.length && !isOnline" class="hp-act-empty">Connect to load activity</div>
          <div v-else-if="!actEvents.length" class="hp-act-empty">No recent activity</div>
          <div v-else class="hp-events">
            <div v-for="e in actEvents.slice(0,8)" :key="e.entity_uuid" :class="['hp-ev', e.risk_level&&'hp-ev--'+e.risk_level.toLowerCase()]">
              <div class="hp-ev-dot" :class="'hp-ev-dot--'+e.type.toLowerCase()"/>
              <div class="hp-ev-body">
                <span class="hp-ev-title">{{ e.title }}</span>
                <span class="hp-ev-sub">{{ e.subtitle }}</span>
              </div>
              <span class="hp-ev-age">{{ fmtAge(e.age_minutes) }}</span>
            </div>
          </div>
        </div>

        <div style="height:32px"/>
      </div>
    </IonContent>
    <IonToast :is-open="toast.show" :message="toast.msg" :color="toast.color" :duration="3000" position="top" @didDismiss="toast.show=false"/>
  </IonPage>
</template>

<script setup>
import { ref, computed, reactive, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { IonPage, IonHeader, IonButtons, IonMenuButton, IonContent, IonSpinner, IonRefresher, IonRefresherContent, IonToast, onIonViewDidEnter } from '@ionic/vue'
import { APP } from '@/services/poeDB'

const router = useRouter()
function nav(path) { router.push(path) }
function getAuth() { return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {} }
const auth = ref(getAuth())

const DONUT_R = 40, DONUT_C = parseFloat((2*Math.PI*DONUT_R).toFixed(2))
const SPARK_W = 300, SPARK_H = 40
const SUMMARY_KEY = 'cmd_summary_v3', TREND_KEY = 'cmd_trend_v3'

const summary = ref(null)
const trendData = ref([])
const live = ref(null)
const actEvents = ref([])
const loading = ref(false)
const activityLoading = ref(false)
const isOnline = ref(navigator.onLine)
const dismissed = ref(false)
const currentTime = ref(fmtTime(new Date()))
const toast = reactive({show:false,msg:'',color:'success'})
let liveTimer=null, summaryTimer=null, clockTimer=null

const poeTypeLabel = computed(() => {
  const r=auth.value?.role_key??''
  if(r.includes('PRIMARY'))return'Primary Screener';if(r.includes('SECONDARY'))return'Secondary Officer'
  if(r.includes('DATA'))return'Data Officer';if(r.includes('ADMIN'))return'POE Admin'
  if(r.includes('DISTRICT'))return'District Supervisor';if(r.includes('PHEOC'))return'PHEOC Officer'
  if(r.includes('NATIONAL'))return'National Admin';if(r==='SCREENER')return'Screener'
  return'Sentinel Officer'
})

const screenedToday = computed(()=>live.value?.screened_today??summary.value?.screening_today?.total??'--')
const symptomaticToday = computed(()=>live.value?.symptomatic_today??summary.value?.screening_today?.symptomatic??'--')
const feverToday = computed(()=>summary.value?.screening_today?.fever??0)
const referralsToday = computed(()=>summary.value?.screening_today?.referrals??0)
const dayDelta = computed(()=>summary.value?.screening_today?.vs_yesterday??null)
const openReferrals = computed(()=>live.value?.open_referrals??summary.value?.referral_queue?.open??0)
const critReferrals = computed(()=>live.value?.critical_referrals??summary.value?.referral_queue?.open_critical??0)
const activeCases = computed(()=>live.value?.active_cases??summary.value?.secondary_cases?.active??0)
const critCases = computed(()=>summary.value?.secondary_cases?.active_critical??0)
const emergencyCases = computed(()=>summary.value?.secondary_cases?.emergency_active??0)
const openAlerts = computed(()=>live.value?.open_alerts??summary.value?.alerts?.open??0)
const critAlerts = computed(()=>live.value?.critical_alerts??summary.value?.alerts?.open_critical??0)
const nationalAlerts = computed(()=>summary.value?.alerts?.open_national??0)
const totalUnsynced = computed(()=>live.value?.total_unsynced??summary.value?.sync_health?.grand_total_unsynced??0)
const opStatus = computed(()=>summary.value?.operational_status??'NORMAL')
const hasCritical = computed(()=>critAlerts.value>0||critReferrals.value>0||(live.value?.has_critical_alert)||(live.value?.queue_critical))
const topCritMessage = computed(()=>{
  if(critAlerts.value>0)return`${critAlerts.value} CRITICAL alert${critAlerts.value>1?'s':''} require immediate attention`
  if(critReferrals.value>0)return`${critReferrals.value} CRITICAL referral${critReferrals.value>1?'s':''} awaiting secondary screening`
  return'Critical situation detected'
})

const symptomaticRatePct = computed(()=>{
  const t=Number(screenedToday.value)||0,s=Number(symptomaticToday.value)||0
  return t?Math.round(s/t*100):0
})
const donutColor = computed(()=>{const p=symptomaticRatePct.value;return p>=30?'#EF4444':p>=15?'#F59E0B':p>=5?'#60A5FA':'#34D399'})
const donutOffset = computed(()=>DONUT_C-(symptomaticRatePct.value/100*DONUT_C))

const syncPrimary = computed(()=>summary.value?.sync_health?.primary_screenings?.pending??0)
const syncCases = computed(()=>summary.value?.sync_health?.secondary_screenings?.pending??0)
const syncAlerts = computed(()=>summary.value?.sync_health?.alerts?.pending??0)
const syncHealthPct = computed(()=>{
  const sh=summary.value?.sync_health;if(!sh)return 100
  const t=(sh.primary_screenings?.total??0)+(sh.secondary_screenings?.total??0)+(sh.notifications?.total??0)+(sh.alerts?.total??0)
  const u=sh.grand_total_unsynced??0
  return t?Math.round(((t-u)/t)*100):100
})
const weekDelta = computed(()=>summary.value?.week_snapshot?.vs_last_week??0)
const lastActivityAt = computed(()=>actEvents.value.length?fmtAge(actEvents.value[0].age_minutes):'')

// Sparkline
const sparkDots = computed(()=>{
  const d=trendData.value;if(!d.length)return[]
  const vals=d.map(x=>x.total);const max=Math.max(...vals,1);const P=4
  return d.map((x,i)=>({x:P+(i/Math.max(d.length-1,1))*(SPARK_W-P*2),y:SPARK_H-P-(x.total/max)*(SPARK_H-P*2)}))
})
const sparkPoints = computed(()=>sparkDots.value.map(p=>`${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' '))
const sparkAreaPath = computed(()=>{
  const pts=sparkDots.value;if(!pts.length)return''
  return`M${pts[0].x},${pts[0].y} L${pts.map(p=>`${p.x},${p.y}`).join(' L')} L${SPARK_W-4},${SPARK_H} L4,${SPARK_H} Z`
})

function fmtTime(d){return d.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'})}
function fmtAge(m){if(m==null)return'';if(m<1)return'now';if(m<60)return m+'m';const h=Math.floor(m/60);if(h<24)return h+'h';return Math.floor(h/24)+'d'}

// ── API ─────────────────────────────────────────────────────────────────
async function api(path){
  const uid=auth.value?.id;if(!uid)return null
  const sep=path.includes('?')?'&':'?'
  const ctrl=new AbortController();const tid=setTimeout(()=>ctrl.abort(),APP.SYNC_TIMEOUT_MS)
  try{const res=await fetch(`${window.SERVER_URL}${path}${sep}user_id=${uid}`,{headers:{Accept:'application/json'},signal:ctrl.signal});clearTimeout(tid);if(!res.ok)return null;const j=await res.json();return j.success?j.data:null}catch{clearTimeout(tid);return null}
}

// ── CACHE ───────────────────────────────────────────────────────────────
function saveCache(key,data,ttlMs){try{localStorage.setItem(key,JSON.stringify({ts:Date.now(),ttl:ttlMs,d:data}))}catch{}}
function readCache(key){try{const raw=localStorage.getItem(key);if(!raw)return null;const{ts,ttl,d}=JSON.parse(raw);if(Date.now()-ts>ttl)return null;return d}catch{return null}}

// ── LOAD ────────────────────────────────────────────────────────────────
async function loadSummary(){
  if(!isOnline.value)return
  const d=await api('/home/summary');if(!d)return
  summary.value=d;saveCache(SUMMARY_KEY,d,4*3600_000)
}
async function loadLive(){
  if(!isOnline.value)return
  const d=await api('/home/live');if(d)live.value=d
}
async function loadTrend(){
  if(!isOnline.value)return
  const d=await api('/dashboard/trend?days=7');if(!d?.series)return
  trendData.value=d.series;saveCache(TREND_KEY,d.series,3600_000)
}
async function loadActivity(){
  if(!isOnline.value)return
  activityLoading.value=true
  try{const d=await api('/home/activity?limit=15');if(d?.events)actEvents.value=d.events}finally{activityLoading.value=false}
}
async function loadAll(){
  loading.value=true;auth.value=getAuth()
  // Cache first
  const cachedSum=readCache(SUMMARY_KEY);if(cachedSum)summary.value=cachedSum
  const cachedTrend=readCache(TREND_KEY);if(cachedTrend)trendData.value=cachedTrend
  if(isOnline.value){await Promise.all([loadSummary(),loadLive(),loadTrend()]);loadActivity().catch(()=>{})}
  loading.value=false
}
async function pull(ev){await loadAll();ev.target.complete()}

function onOnline(){isOnline.value=true;loadAll()}
function onOffline(){isOnline.value=false;live.value=null}

onMounted(()=>{
  auth.value=getAuth();isOnline.value=navigator.onLine
  window.addEventListener('online',onOnline);window.addEventListener('offline',onOffline)
  loadAll()
  liveTimer=setInterval(()=>{if(isOnline.value)loadLive()},30_000)
  summaryTimer=setInterval(()=>{if(isOnline.value)loadSummary()},120_000)
  clockTimer=setInterval(()=>{currentTime.value=fmtTime(new Date())},1000)
})
onIonViewDidEnter(()=>{auth.value=getAuth();if(isOnline.value){loadLive();loadSummary();loadActivity().catch(()=>{})}})
onUnmounted(()=>{
  window.removeEventListener('online',onOnline);window.removeEventListener('offline',onOffline)
  clearInterval(liveTimer);clearInterval(summaryTimer);clearInterval(clockTimer)
})
</script>

<style scoped>
*{box-sizing:border-box}

/* ═══ HEADER — dark command zone ═══════════════════════════════════ */
.hp-hdr{--background:transparent;border:none}
.hp-hdr-bg{background:linear-gradient(145deg,#06111E 0%,#0D253F 55%,#0F3460 100%);padding:0 0 4px;padding-top:env(safe-area-inset-top,0)}
.hp-hdr-top{display:flex;align-items:center;gap:4px;padding:8px 10px 0}
.hp-menu{--color:rgba(255,255,255,.7);flex-shrink:0}
.hp-hdr-id{flex:1;min-width:0}
.hp-poe{display:block;font-size:14px;font-weight:800;color:#EDF2FA;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hp-role{display:block;font-size:8px;font-weight:600;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.5px}
.hp-hdr-right{display:flex;align-items:center;gap:6px;flex-shrink:0}
.hp-conn{display:flex;align-items:center;gap:3px;font-size:9px;font-weight:700;color:rgba(255,255,255,.4)}
.hp-conn--on{color:#00E676}.hp-conn--off{color:#FF6B6B}
.hp-conn-dot{width:5px;height:5px;border-radius:50%;background:currentColor}
.hp-ref-btn{width:28px;height:28px;border-radius:50%;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.6);cursor:pointer}
.hp-ref-btn:disabled{opacity:.4}

/* HERO */
.hp-hero{display:flex;align-items:center;gap:12px;padding:12px 14px 4px}
.hp-hero-left{flex:1}
.hp-hero-num{font-size:36px;font-weight:900;color:#EDF2FA;line-height:1;font-variant-numeric:tabular-nums}
.hp-hero-lbl{font-size:10px;font-weight:600;color:rgba(255,255,255,.4);margin-top:2px}
.hp-hero-row{margin-top:4px}
.hp-delta{font-size:10px;font-weight:700}
.hp-delta--up{color:#00E676}.hp-delta--dn{color:#FF6B6B}

.hp-ring-w{position:relative;width:72px;height:72px;flex-shrink:0}
.hp-ring-w svg{width:100%;height:100%}
.hp-ring-anim{transition:stroke-dashoffset .6s ease}
.hp-ring-ctr{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.hp-ring-pct{font-size:18px;font-weight:900;color:#EDF2FA}
.hp-ring-lbl{font-size:6px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:rgba(255,255,255,.4)}

/* MINI STATS */
.hp-mini-row{display:flex;gap:2px;padding:4px 14px}
.hp-mini{flex:1;text-align:center;padding:4px 0;background:rgba(255,255,255,.04);border-radius:4px}
.hp-mini-n{display:block;font-size:14px;font-weight:900;color:#EDF2FA}
.hp-mini-l{display:block;font-size:6px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:rgba(255,255,255,.3)}

/* SPARKLINE */
.hp-spark-w{padding:4px 14px 0;height:44px}
.hp-spark{width:100%;height:100%;display:block}

/* STATUS */
.hp-status{display:flex;align-items:center;gap:4px;padding:4px 14px 6px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:rgba(255,255,255,.4)}
.hp-status-dot{width:5px;height:5px;border-radius:50%}
.hp-status--normal .hp-status-dot{background:#00E676}.hp-status--normal{color:#00E676}
.hp-status--active .hp-status-dot{background:#00B4FF}.hp-status--active{color:#00B4FF}
.hp-status--elevated .hp-status-dot{background:#FFB400}.hp-status--elevated{color:#FFB400}
.hp-status--critical .hp-status-dot{background:#FF6B6B;animation:hp-pulse 1s ease infinite}.hp-status--critical{color:#FF6B6B}
@keyframes hp-pulse{0%,100%{opacity:1}50%{opacity:.3}}
.hp-status-time{margin-left:auto;color:rgba(255,255,255,.25);font-variant-numeric:tabular-nums}

/* ═══ CONTENT ═══════════════════════════════════════════════════════ */
.hp-content{--background:#F1F5F9}
.hp-offline{padding:6px 12px;background:#FFF3E0;border-bottom:1px solid #FFB74D;font-size:10px;color:#BF360C;text-align:center;font-weight:600}
.hp-crit-strip{display:flex;align-items:center;gap:6px;padding:8px 12px;background:#FEF2F2;border-bottom:2px solid #EF4444;cursor:pointer}
.hp-crit-ico{width:20px;height:20px;border-radius:50%;background:#EF4444;color:#fff;display:flex;align-items:center;justify-content:center;font-size:8px;font-weight:900;flex-shrink:0}
.hp-crit-text{font-size:11px;font-weight:700;color:#991B1B;flex:1}
.hp-crit-x{font-size:16px;color:#EF4444;flex-shrink:0}
.hp-body{padding:8px 10px 0}

/* TRIPTYCH */
.hp-tri{display:flex;gap:6px;margin-bottom:8px}
.hp-tri-card{flex:1;padding:10px 8px;background:#fff;border-radius:10px;border:1px solid #E2E8F0;cursor:pointer;display:flex;flex-direction:column;gap:2px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.hp-tri-top{display:flex;align-items:center;gap:4px}
.hp-tri-num{font-size:20px;font-weight:900;color:#1A3A5C;line-height:1}
.hp-tri-num--warn{color:#F59E0B}.hp-tri-num--red{color:#EF4444}
.hp-tri-badge{font-size:7px;font-weight:800;padding:1px 4px;border-radius:99px;background:#FEF3C7;color:#92400E}
.hp-tri-badge--red{background:#FEE2E2;color:#991B1B}
.hp-tri-lbl{font-size:9px;font-weight:700;color:#475569}
.hp-tri-sub{font-size:7px;color:#94A3B8}

/* PRIMARY ACTIONS */
.hp-actions{display:flex;gap:6px;margin-bottom:8px}
.hp-act{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:12px 8px;border-radius:10px;border:none;font-size:12px;font-weight:700;cursor:pointer;position:relative}
.hp-act--primary{background:#0F3460;color:#EDF2FA}
.hp-act--secondary{background:#fff;color:#0F3460;border:1.5px solid #E2E8F0}
.hp-act-dot{position:absolute;top:-4px;right:-4px;width:18px;height:18px;border-radius:50%;background:#EF4444;color:#fff;font-size:9px;font-weight:800;display:flex;align-items:center;justify-content:center}

/* QUICK NAV */
.hp-nav-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:8px}
.hp-nav{display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 4px;background:#fff;border-radius:8px;border:1px solid #E2E8F0;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,.02)}
.hp-nav-ico{font-size:18px}
.hp-nav-lbl{font-size:8px;font-weight:700;color:#475569;text-align:center}

/* SYNC HEALTH */
.hp-sync-card{padding:10px 12px;background:#fff;border-radius:10px;border:1px solid #E2E8F0;margin-bottom:8px}
.hp-sync-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
.hp-sync-title{font-size:11px;font-weight:800;color:#1A3A5C}
.hp-sync-badge{font-size:8px;font-weight:700;padding:2px 6px;border-radius:99px}
.hp-sync-badge--ok{background:#ECFDF5;color:#047857}
.hp-sync-badge--warn{background:#FEF3C7;color:#92400E}
.hp-sync-bar-w{height:4px;background:#E2E8F0;border-radius:2px;overflow:hidden;margin-bottom:4px}
.hp-sync-bar{height:100%;border-radius:2px;transition:width .4s}
.hp-sync-bar--ok{background:#10B981}.hp-sync-bar--warn{background:#F59E0B}.hp-sync-bar--bad{background:#EF4444}
.hp-sync-detail{display:flex;gap:8px;font-size:9px;color:#94A3B8;font-weight:600}

/* WEEK SNAPSHOT */
.hp-week-card{padding:10px 12px;background:#fff;border-radius:10px;border:1px solid #E2E8F0;margin-bottom:8px}
.hp-week-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
.hp-week-title{font-size:11px;font-weight:800;color:#1A3A5C}
.hp-week-delta{font-size:10px;font-weight:700}
.hp-week-delta--up{color:#10B981}.hp-week-delta--dn{color:#EF4444}
.hp-week-row{display:flex;gap:4px}
.hp-wk{flex:1;text-align:center;padding:6px 4px;background:#F8FAFC;border-radius:6px}
.hp-wk-n{display:block;font-size:16px;font-weight:900;color:#1A3A5C}
.hp-wk-l{display:block;font-size:7px;font-weight:700;color:#94A3B8;text-transform:uppercase}

/* ACTIVITY FEED */
.hp-act-feed{background:#fff;border-radius:10px;border:1px solid #E2E8F0;overflow:hidden;margin-bottom:8px}
.hp-act-hdr{display:flex;align-items:center;justify-content:space-between;padding:10px 12px 4px}
.hp-act-title{font-size:11px;font-weight:800;color:#1A3A5C}
.hp-act-age{font-size:9px;color:#94A3B8;font-weight:600}
.hp-act-ld{padding:16px;text-align:center}
.hp-act-empty{padding:16px;text-align:center;font-size:11px;color:#94A3B8}
.hp-events{display:flex;flex-direction:column}
.hp-ev{display:flex;align-items:flex-start;gap:8px;padding:8px 12px;border-top:1px solid #F1F5F9}
.hp-ev:first-child{border-top:none}
.hp-ev-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;margin-top:4px}
.hp-ev-dot--primary{background:#3B82F6}.hp-ev-dot--referral{background:#F59E0B}.hp-ev-dot--secondary{background:#8B5CF6}.hp-ev-dot--alert{background:#EF4444}.hp-ev-dot--aggregated{background:#6B7280}
.hp-ev--critical .hp-ev-dot{background:#EF4444;animation:hp-pulse 1.5s ease infinite}
.hp-ev-body{flex:1;min-width:0}
.hp-ev-title{display:block;font-size:11px;font-weight:700;color:#1E293B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hp-ev-sub{display:block;font-size:9px;color:#94A3B8;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hp-ev-age{font-size:9px;color:#CBD5E1;font-weight:600;white-space:nowrap;flex-shrink:0}

@media(min-width:500px){.hp-body{max-width:480px;margin:0 auto}}
</style>

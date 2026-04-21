<template>
  <IonPage>
    <IonHeader class="sd-hdr" translucent>
      <IonToolbar class="sd-tb">
        <IonButtons slot="start"><IonMenuButton menu="app-menu"/></IonButtons>
        <div class="sd-tb-title" slot="start">
          <span class="sd-tb-eye">{{ auth.poe_code||'POE' }}</span>
          <span class="sd-tb-h1">Intelligence</span>
        </div>
        <IonButtons slot="end">
          <div :class="['sd-live',liveData&&'sd-live--on']"><span class="sd-live-dot"/><span class="sd-live-n">{{ liveData?.total??'--' }}</span><span class="sd-live-l">today</span></div>
          <button class="sd-pdf-btn" @click="onPDF" :disabled="pdfBusy">{{ pdfBusy?'...':'PDF' }}</button>
          <IonButton fill="clear" class="sd-rb" @click="loadAll" :disabled="loading"><IonIcon :icon="refreshOutline" slot="icon-only"/></IonButton>
        </IonButtons>
      </IonToolbar>
      <!-- Filters -->
      <div class="sd-fb">
        <div class="sd-fs"><button v-for="p in QUICK_PERIODS" :key="p.v" :class="['sd-fp',activeFilter===p.v&&'sd-fp--on']" @click="setFilter(p.v)">{{ p.l }}</button></div>
        <button :class="['sd-ft',showAdvFilter&&'sd-ft--on']" @click="showAdvFilter=!showAdvFilter">{{ advFilterCount?advFilterCount+' ':''}}&equiv;</button>
      </div>
      <transition name="sd-sl"><div v-if="showAdvFilter" class="sd-af">
        <div class="sd-ar"><span class="sd-al">Date</span><input type="date" v-model="filterDate" class="sd-ai" @change="activeFilter='custom'"/></div>
        <div class="sd-ar"><span class="sd-al">Month</span><div class="sd-ap"><button v-for="m in MONTHS" :key="m.v" :class="['sd-pill',filterMonth===m.v&&'sd-pill--on']" @click="filterMonth=filterMonth===m.v?null:m.v;activeFilter='custom'">{{ m.l }}</button></div></div>
        <div class="sd-ar"><span class="sd-al">Year</span><div class="sd-ap"><button v-for="y in YEARS" :key="y" :class="['sd-pill',filterYear===y&&'sd-pill--on']" @click="filterYear=filterYear===y?null:y;activeFilter='custom'">{{ y }}</button></div></div>
        <div class="sd-ar"><span class="sd-al">Range</span><input type="date" v-model="filterFrom" class="sd-ai sd-ai--h" @change="activeFilter='custom'"/><input type="date" v-model="filterTo" class="sd-ai sd-ai--h" @change="activeFilter='custom'"/></div>
        <div class="sd-aa"><button class="sd-apply" @click="loadAll();showAdvFilter=false">Apply</button><button class="sd-clear" @click="clearFilters">Clear</button></div>
      </div></transition>
    </IonHeader>

    <IonContent class="sd-c" :fullscreen="true">
      <IonRefresher slot="fixed" @ionRefresh="pullRefresh($event)"><IonRefresherContent pulling-text="Pull to refresh" refreshing-spinner="crescent"/></IonRefresher>

      <!-- Offline banner -->
      <div v-if="!isOnline" class="sd-offline">Offline -- showing cached data{{ lastSyncAt?' from '+fmtRel(lastSyncAt):'' }}</div>

      <!-- Loading -->
      <div v-if="loading&&!sum" class="sd-ld"><IonSpinner name="crescent"/><p>Loading...</p></div>

      <div v-else class="sd-b">
        <!-- ═══ HERO ══════════════════════════════════════════════════ -->
        <div class="sd-hero" @click="openSheet('summary')">
          <div class="sd-ring-w">
            <svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="42" fill="none" stroke="#E8EDF5" stroke-width="6"/><circle cx="50" cy="50" r="42" fill="none" :stroke="srColor" stroke-width="6" stroke-linecap="round" :stroke-dasharray="srDash" transform="rotate(-90 50 50)" class="sd-ring-anim"/></svg>
            <div class="sd-ring-ctr"><span class="sd-ring-pct">{{ sr }}<small>%</small></span><span class="sd-ring-lbl">symp</span></div>
          </div>
          <div class="sd-hero-r">
            <div class="sd-hero-big">{{ sum?.all_time?.total??'--' }}</div>
            <div class="sd-hero-sub">Total Screened</div>
            <div class="sd-hero-row">
              <div class="sd-hm"><span class="sd-hm-v">{{ sum?.today?.total??0 }}</span><span class="sd-hm-l">Today</span></div>
              <div class="sd-hm" :class="delta>=0?'sd-hm--up':'sd-hm--dn'"><span class="sd-hm-v">{{ delta>=0?'+':'' }}{{ delta }}</span><span class="sd-hm-l">vs Yday</span></div>
              <div class="sd-hm"><span class="sd-hm-v">{{ sum?.this_week?.total??0 }}</span><span class="sd-hm-l">Week</span></div>
              <div class="sd-hm"><span class="sd-hm-v">{{ sum?.this_month?.total??0 }}</span><span class="sd-hm-l">Month</span></div>
            </div>
          </div>
        </div>

        <!-- ═══ QUICK STATS ═══════════════════════════════════════════ -->
        <div class="sd-qs">
          <div class="sd-q"><span class="sd-qn">{{ sum?.all_time?.completed??0 }}</span><span class="sd-ql">Completed</span></div>
          <div class="sd-q sd-q--r"><span class="sd-qn">{{ sum?.all_time?.symptomatic??0 }}</span><span class="sd-ql">Symptomatic</span></div>
          <div class="sd-q"><span class="sd-qn">{{ sum?.all_time?.referrals??0 }}</span><span class="sd-ql">Referrals</span></div>
          <div class="sd-q" :class="(sum?.today?.fever_count??0)>0&&'sd-q--a'"><span class="sd-qn">{{ sum?.today?.fever_count??0 }}</span><span class="sd-ql">Fever Today</span></div>
          <div class="sd-q" :class="(sum?.referral_queue?.open??0)>0&&'sd-q--a'" @click="openSheet('referrals')"><span class="sd-qn">{{ sum?.referral_queue?.open??0 }}</span><span class="sd-ql">Open Ref</span></div>
          <div class="sd-q" :class="(sum?.alerts?.open??0)>0&&'sd-q--r'"><span class="sd-qn">{{ sum?.alerts?.open??0 }}</span><span class="sd-ql">Alerts</span></div>
          <div class="sd-q" :class="(sum?.all_time?.unsynced??0)>0&&'sd-q--a'"><span class="sd-qn">{{ sum?.all_time?.unsynced??0 }}</span><span class="sd-ql">Unsynced</span></div>
          <div class="sd-q"><span class="sd-qn">{{ sum?.all_time?.voided??0 }}</span><span class="sd-ql">Voided</span></div>
        </div>

        <!-- ═══ SIGNALS ═══════════════════════════════════════════════ -->
        <template v-if="signals.length">
          <div class="sd-sh"><span class="sd-sh-dot"/><span class="sd-sh-t">Signals</span><span class="sd-sh-b">{{ signals.length }}</span></div>
          <div class="sd-sigs">
            <div v-for="(s,i) in signals" :key="i" :class="['sd-sig','sd-sig--'+s.level]" @click="activeSig=s">
              <span class="sd-sig-i">{{ s.ico }}</span>
              <div class="sd-sig-b"><span class="sd-sig-c">{{ s.category }}</span><span class="sd-sig-t">{{ s.title }}</span><span class="sd-sig-d">{{ s.desc.length>100?s.desc.slice(0,100)+'...':s.desc }}</span></div>
            </div>
          </div>
        </template>

        <!-- ═══ TREND ═════════════════════════════════════════════════ -->
        <div class="sd-card" v-if="trend?.series?.length">
          <div class="sd-ch"><span class="sd-ct">Trend</span><span class="sd-cs">{{ filterLabel }}</span></div>
          <div class="sd-tw">
            <svg :viewBox="'0 0 '+TW+' 80'" class="sd-tsvg" preserveAspectRatio="none">
              <path :d="trendAreaPath('total')" fill="#1565C0" opacity=".1"/>
              <polyline :points="trendLine('total')" fill="none" stroke="#1565C0" stroke-width="2" stroke-linejoin="round"/>
              <polyline :points="trendLine('symptomatic')" fill="none" stroke="#E53935" stroke-width="1.5" stroke-linejoin="round" stroke-dasharray="4,3"/>
            </svg>
            <div class="sd-txl"><span v-for="(l,i) in trendLabels" :key="i">{{ l }}</span></div>
            <div class="sd-tleg"><span class="sd-tl sd-tl--t">Total</span><span class="sd-tl sd-tl--s">Symptomatic</span></div>
          </div>
        </div>

        <!-- ═══ GENDER ════════════════════════════════════════════════ -->
        <div class="sd-card" v-if="epi?.by_gender?.length">
          <div class="sd-ch"><span class="sd-ct">Gender</span></div>
          <div class="sd-bars"><div v-for="g in epi.by_gender" :key="g.gender" class="sd-bar">
            <span class="sd-bl">{{ GS[g.gender] }}</span><div class="sd-bt"><div :class="['sd-bf','sd-bf--'+g.gender.toLowerCase()]" :style="{width:genderPct(g)+'%'}"/></div><span class="sd-bv">{{ g.total }}</span>
          </div></div>
        </div>

        <!-- ═══ TEMPERATURE ═══════════════════════════════════════════ -->
        <div class="sd-card" v-if="epi?.temperature?.bands" @click="openSheet('temperature')">
          <div class="sd-ch"><span class="sd-ct">Temperature</span><span class="sd-cs">avg {{ epi.temperature.avg_c?.toFixed(1)||'--' }}C</span></div>
          <div class="sd-ts">
            <div class="sd-tsb"><div v-for="b in tBands" :key="b.k" class="sd-tss" :style="{flex:b.n||0.01,background:b.c}"/></div>
            <div class="sd-tsl"><div v-for="b in tBands" :key="b.k" class="sd-tsi"><span class="sd-tsd" :style="{background:b.c}"/><span>{{ b.l }} {{ b.n }} ({{ b.p }}%)</span></div></div>
          </div>
        </div>

        <!-- ═══ SYNDROMES ═════════════════════════════════════════════ -->
        <div class="sd-card" v-if="epi?.syndromes?.length">
          <div class="sd-ch"><span class="sd-ct">Syndromes</span></div>
          <div class="sd-bars"><div v-for="s in epi.syndromes" :key="s.syndrome" class="sd-bar">
            <span class="sd-bl sd-bl--w">{{ s.syndrome.replace(/_/g,' ') }}</span><div class="sd-bt"><div class="sd-bf sd-bf--syn" :style="{width:synPct(s)+'%'}"/></div><span class="sd-bv">{{ s.count }}</span>
          </div></div>
        </div>

        <!-- ═══ HOURS ═════════════════════════════════════════════════ -->
        <div class="sd-card" v-if="hmap?.buckets?.length">
          <div class="sd-ch"><span class="sd-ct">Peak Hours</span></div>
          <div class="sd-hrs"><div v-for="b in hmap.buckets" :key="b.bucket" class="sd-hc" :class="b.bucket%2!==0&&'sd-hc--odd'">
            <div class="sd-hbw"><div class="sd-hb" :style="{height:hourPct(b)+'%'}" :class="b.bucket===peakH&&'sd-hb--pk'"/></div><span class="sd-hl">{{ b.label }}</span>
          </div></div>
        </div>

        <!-- ═══ FUNNEL ════════════════════════════════════════════════ -->
        <div class="sd-card" v-if="fun?.funnel?.length">
          <div class="sd-ch"><span class="sd-ct">Referral Pipeline</span></div>
          <div class="sd-fun"><div v-for="f in fun.funnel" :key="f.stage" class="sd-fn">
            <div class="sd-fnb" :style="{width:funnelPct(f)+'%'}"/><div class="sd-fnf"><span class="sd-fnn">{{ f.count }}</span><span class="sd-fnl">{{ f.description||f.stage }}</span><span class="sd-fnp">{{ f.rate }}%</span></div>
          </div></div>
          <div class="sd-fk"><div class="sd-fki"><span class="sd-fkv">{{ fun.notifications?.avg_pickup_minutes??'--' }}</span><span class="sd-fkl">min pickup</span></div><div class="sd-fki"><span class="sd-fkv">{{ fun.secondary_cases?.avg_case_duration_minutes??'--' }}</span><span class="sd-fkl">min/case</span></div><div class="sd-fki"><span class="sd-fkv" :class="(fun.notifications?.open??0)>0&&'sd-fkv--w'">{{ fun.notifications?.open??0 }}</span><span class="sd-fkl">open</span></div><div class="sd-fki"><span class="sd-fkv" :class="(fun.secondary_cases?.risk_critical??0)>0&&'sd-fkv--d'">{{ fun.secondary_cases?.risk_critical??0 }}</span><span class="sd-fkl">crit</span></div></div>
        </div>

        <!-- ═══ SECONDARY ═════════════════════════════════════════════ -->
        <div class="sd-card" v-if="fun?.secondary_cases?.total" @click="openSheet('secondary')">
          <div class="sd-ch"><span class="sd-ct">Secondary Cases</span><span class="sd-cs">{{ fun.secondary_cases.total }}</span></div>
          <div class="sd-grid"><div class="sd-gc"><span class="sd-gn sd-gn--bl">{{ fun.secondary_cases.open??0 }}</span><span class="sd-gl">Open</span></div><div class="sd-gc"><span class="sd-gn sd-gn--am">{{ fun.secondary_cases.in_progress??0 }}</span><span class="sd-gl">In Prog</span></div><div class="sd-gc"><span class="sd-gn sd-gn--gr">{{ fun.secondary_cases.closed??0 }}</span><span class="sd-gl">Closed</span></div><div class="sd-gc"><span class="sd-gn sd-gn--rd">{{ fun.secondary_cases.risk_critical??0 }}</span><span class="sd-gl">Critical</span></div><div class="sd-gc"><span class="sd-gn sd-gn--or">{{ fun.secondary_cases.risk_high??0 }}</span><span class="sd-gl">High</span></div><div class="sd-gc"><span class="sd-gn">{{ fun.secondary_cases.quarantined??0 }}</span><span class="sd-gl">Quarant</span></div></div>
        </div>

        <!-- ═══ ALERTS ════════════════════════════════════════════════ -->
        <div class="sd-card" v-if="alertsData?.totals">
          <div class="sd-ch"><span class="sd-ct">IHR Alerts</span><span v-if="alertsData.totals.open" class="sd-cbadge">{{ alertsData.totals.open }}</span></div>
          <div class="sd-grid"><div class="sd-gc"><span class="sd-gn">{{ alertsData.totals.total??0 }}</span><span class="sd-gl">Total</span></div><div class="sd-gc"><span class="sd-gn sd-gn--rd">{{ alertsData.totals.open??0 }}</span><span class="sd-gl">Open</span></div><div class="sd-gc"><span class="sd-gn sd-gn--am">{{ alertsData.totals.acknowledged??0 }}</span><span class="sd-gl">Ack</span></div><div class="sd-gc"><span class="sd-gn sd-gn--gr">{{ alertsData.totals.closed??0 }}</span><span class="sd-gl">Closed</span></div><div class="sd-gc"><span class="sd-gn sd-gn--rd">{{ alertsData.totals.critical??0 }}</span><span class="sd-gl">Crit</span></div><div class="sd-gc"><span class="sd-gn">{{ alertsData.totals.avg_ack_minutes??'--' }}</span><span class="sd-gl">min ack</span></div></div>
        </div>

        <!-- ═══ WEEKLY ════════════════════════════════════════════════ -->
        <div class="sd-card" v-if="weekly?.report">
          <div class="sd-ch"><span class="sd-ct">Weekly</span><span class="sd-cs">{{ weekly.week_label }}</span></div>
          <div class="sd-grid"><div class="sd-gc"><span class="sd-gn">{{ weekly.report.total_screened??0 }}</span><span class="sd-gl">Screened</span></div><div class="sd-gc"><span class="sd-gn sd-gn--rd">{{ weekly.report.symptomatic_rate??0 }}%</span><span class="sd-gl">Symp%</span></div><div class="sd-gc"><span class="sd-gn">{{ weekly.report.total_referrals??0 }}</span><span class="sd-gl">Ref</span></div><div class="sd-gc"><span class="sd-gn sd-gn--am">{{ weekly.report.fever_count??0 }}</span><span class="sd-gl">Fever</span></div><div class="sd-gc"><span class="sd-gn">{{ weekly.report.avg_daily??0 }}</span><span class="sd-gl">Avg/d</span></div><div class="sd-gc"><span class="sd-gn" :class="(weekly.report.vs_previous_week??0)>=0?'sd-gn--gr':'sd-gn--rd'">{{ (weekly.report.vs_previous_week??0)>=0?'+':'' }}{{ weekly.report.vs_previous_week??0 }}</span><span class="sd-gl">vs last</span></div></div>
        </div>

        <!-- ═══ DEVICES ═══════════════════════════════════════════════ -->
        <div class="sd-card" v-if="devices?.devices?.length">
          <div class="sd-ch"><span class="sd-ct">Devices</span><span class="sd-cs">{{ devices.device_count }}</span><span v-if="devices.devices_at_risk" class="sd-cbadge sd-cbadge--w">{{ devices.devices_at_risk }}</span></div>
          <div v-for="d in devices.devices.slice(0,5)" :key="d.device_id" class="sd-dev"><div class="sd-dev-l"><span class="sd-dev-id">{{ d.device_id }}</span><span class="sd-dev-m">{{ d.platform }} {{ d.total_records }}rec</span></div><span :class="['sd-dev-s','sd-dev-s--'+d.status.toLowerCase()]">{{ d.status }}</span></div>
        </div>

        <!-- ═══ OFFICERS ══════════════════════════════════════════════ -->
        <div class="sd-card" v-if="officers?.screeners?.length">
          <div class="sd-ch"><span class="sd-ct">Officers</span><span class="sd-cs">{{ officers.screener_count }}</span></div>
          <div v-for="o in officers.screeners.slice(0,6)" :key="o.user_id" class="sd-off"><span class="sd-off-n">{{ o.full_name||o.username }}</span><div class="sd-off-bw"><div class="sd-off-b" :style="{width:officerPct(o)+'%'}"/></div><span class="sd-off-v">{{ o.total }}</span></div>
        </div>

        <div style="height:40px"/>
      </div>
    </IonContent>

    <!-- ═══ DETAIL SHEET ══════════════════════════════════════════════ -->
    <IonModal :is-open="!!sheet" @didDismiss="sheet=null" :initial-breakpoint="1" :breakpoints="[0,1]" class="sd-modal">
      <IonHeader><IonToolbar class="sd-st"><div slot="start" class="sd-handle"/><IonButtons slot="end"><IonButton fill="clear" @click="sheet=null">Close</IonButton></IonButtons></IonToolbar></IonHeader>
      <IonContent class="sd-sc" v-if="sheet">
        <div class="sd-sp">
          <template v-if="sheet==='summary'">
            <h2 class="sd-mt">Full Summary</h2>
            <div class="sd-dg"><div class="sd-dr" v-for="r in summaryRows" :key="r[0]"><span class="sd-dk">{{ r[0] }}</span><span :class="['sd-dv',r[2]||'']">{{ r[1] }}</span></div></div>
          </template>
          <template v-if="sheet==='referrals'">
            <h2 class="sd-mt">Referral Queue</h2>
            <div class="sd-dg"><div class="sd-dr" v-for="r in referralRows" :key="r[0]"><span class="sd-dk">{{ r[0] }}</span><span :class="['sd-dv',r[2]||'']">{{ r[1] }}</span></div></div>
          </template>
          <template v-if="sheet==='temperature'">
            <h2 class="sd-mt">Temperature Analysis</h2>
            <div class="sd-dg"><div class="sd-dr" v-for="r in tempRows" :key="r[0]"><span class="sd-dk">{{ r[0] }}</span><span :class="['sd-dv',r[2]||'']">{{ r[1] }}</span></div></div>
          </template>
          <template v-if="sheet==='secondary'">
            <h2 class="sd-mt">Secondary Cases</h2>
            <div class="sd-dg"><div class="sd-dr" v-for="r in secRows" :key="r[0]"><span class="sd-dk">{{ r[0] }}</span><span :class="['sd-dv',r[2]||'']">{{ r[1] }}</span></div></div>
          </template>
        </div>
        <div style="height:40px"/>
      </IonContent>
    </IonModal>

    <!-- ═══ SIGNAL SHEET ══════════════════════════════════════════════ -->
    <IonModal :is-open="!!activeSig" @didDismiss="activeSig=null" :initial-breakpoint="1" :breakpoints="[0,1]" class="sd-modal">
      <IonHeader><IonToolbar class="sd-st"><div slot="start" class="sd-handle"/><IonButtons slot="end"><IonButton fill="clear" @click="activeSig=null">Close</IonButton></IonButtons></IonToolbar></IonHeader>
      <IonContent class="sd-sc" v-if="activeSig">
        <div class="sd-sp">
          <div :class="['sd-sig-hdr','sd-sig-hdr--'+activeSig.level]"><span class="sd-sig-hc">{{ activeSig.category }}</span><span class="sd-sig-ht">{{ activeSig.title }}</span></div>
          <p class="sd-sig-desc">{{ activeSig.desc }}</p>
          <div v-if="activeSig.action" class="sd-sig-act"><span class="sd-sig-al">RECOMMENDED ACTION</span><p class="sd-sig-at">{{ activeSig.action }}</p></div>
        </div>
        <div style="height:40px"/>
      </IonContent>
    </IonModal>

    <IonToast :is-open="toast.show" :message="toast.msg" :color="toast.color" :duration="3000" position="top" @didDismiss="toast.show=false"/>
  </IonPage>
</template>

<script setup>
import { IonPage,IonHeader,IonToolbar,IonButtons,IonMenuButton,IonButton,IonContent,IonIcon,IonSpinner,IonRefresher,IonRefresherContent,IonToast,IonModal } from '@ionic/vue'
import { refreshOutline } from 'ionicons/icons'
import { ref, reactive, computed } from 'vue'
import { useIntelligenceData, QUICK_PERIODS, MONTHS, YEARS, GS, TW } from '@/composables/useIntelligenceData'
import { useIntelligenceAI } from '@/composables/useIntelligenceAI'

const {
  auth,isOnline,activeFilter,showAdvFilter,filterDate,filterMonth,filterYear,
  filterFrom,filterTo,advFilterCount,filterLabel,setFilter,clearFilters,
  loading,sum,trend,hmap,fun,epi,devices,officers,weekly,alertsData,liveData,lastSyncAt,
  sr,srColor,srDash,delta,peakH,tBands,trendLabels,
  trendLine,trendAreaPath,genderPct,synPct,hourPct,officerPct,funnelPct,
  loadAll,pullRefresh,
} = useIntelligenceData()
const { signals, generatePDFReport } = useIntelligenceAI({ sum,sr,delta,fun,weekly,epi,alertsData,devices,officers })

const toast = reactive({show:false,msg:'',color:'success'})
const pdfBusy = ref(false)
const activeSig = ref(null)
const sheet = ref(null)
function openSheet(s) { sheet.value = s }
function fmtRel(dt) { if(!dt)return''; try{const m=Math.floor((Date.now()-new Date(dt).getTime())/60000);if(m<1)return'just now';if(m<60)return m+'m ago';const h=Math.floor(m/60);if(h<24)return h+'h ago';return Math.floor(h/24)+'d ago'}catch{return''} }

async function onPDF() {
  pdfBusy.value=true
  try { const n=await generatePDFReport(); toast.msg='Report: '+n;toast.color='success';toast.show=true }
  catch(e) { toast.msg='PDF error: '+(e?.message||'Unknown');toast.color='danger';toast.show=true }
  finally { pdfBusy.value=false }
}

// Detail sheet rows
const summaryRows = computed(() => {
  const s=sum.value;if(!s)return[];const a=s.all_time||{};const t=s.today||{}
  return [
    ['Total Screened (All Time)',a.total??0],['Completed',a.completed??0],['Voided',a.voided??0,'sd-dv--dim'],
    ['Symptomatic',a.symptomatic??0,'sd-dv--r'],['Asymptomatic',a.asymptomatic??0,'sd-dv--g'],
    ['Symptomatic Rate',`${a.symptomatic_rate??0}%`,(a.symptomatic_rate??0)>=20?'sd-dv--r':''],
    ['Referrals Created',a.referrals??0],['Male',a.male??0],['Female',a.female??0],
    ['Today Total',t.total??0],['Today Symptomatic',t.symptomatic??0,'sd-dv--r'],['Today Rate',`${t.symptomatic_rate??0}%`],
    ['Today Referrals',t.referrals??0],['Fever Today',t.fever_count??0,'sd-dv--a'],['High Fever >=38.5',t.high_fever_count??0,'sd-dv--r'],
    ['Avg Temp Today',t.avg_temp_c?`${Number(t.avg_temp_c).toFixed(1)}C`:'--'],
    ['vs Yesterday',`${(t.vs_yesterday??0)>=0?'+':''}${t.vs_yesterday??0}`,(t.vs_yesterday??0)>=0?'sd-dv--g':'sd-dv--r'],
    ['This Week',s.this_week?.total??0],['This Month',s.this_month?.total??0],
    ['Unsynced',a.unsynced??0,(a.unsynced??0)>0?'sd-dv--a':''],['Sync Failed',a.sync_failed??0,(a.sync_failed??0)>0?'sd-dv--r':''],
  ]
})
const referralRows = computed(() => {
  const rq=sum.value?.referral_queue||{}
  return [
    ['Open',rq.open??0,(rq.open??0)>0?'sd-dv--a':'sd-dv--g'],['In Progress',rq.in_progress??0],['Closed',rq.closed_total??0,'sd-dv--g'],
    ['Critical Open',rq.critical_open??0,(rq.critical_open??0)>0?'sd-dv--r':''],['High Open',rq.high_open??0,(rq.high_open??0)>0?'sd-dv--a':''],
    ['Oldest Open (min)',rq.oldest_open_minutes??0,(rq.oldest_open_minutes??0)>30?'sd-dv--r':''],
    ['Queue Critical',rq.queue_critical?'YES':'NO',rq.queue_critical?'sd-dv--r':'sd-dv--g'],
  ]
})
const tempRows = computed(() => {
  const t=epi.value?.temperature;if(!t)return[]
  return [
    ['Recordings',t.count_with_temp??0],['Average',t.avg_c!=null?`${Number(t.avg_c).toFixed(1)}C`:'--'],
    ['Min',t.min_c!=null?`${t.min_c}C`:'--'],['Max',t.max_c!=null?`${t.max_c}C`:'--',(t.max_c??0)>=38.5?'sd-dv--r':''],
    ['High Fever >=38.5',t.bands?.high_fever??0,(t.bands?.high_fever??0)>0?'sd-dv--r':''],
    ['Low-Grade 37.5-38.5',t.bands?.low_grade_fever??0,(t.bands?.low_grade_fever??0)>0?'sd-dv--a':''],
    ['Normal 36-37.5',t.bands?.normal??0,'sd-dv--g'],['Hypothermia <36',t.bands?.hypothermia??0],
  ]
})
const secRows = computed(() => {
  const sc=fun.value?.secondary_cases;if(!sc)return[]
  return [
    ['Total',sc.total??0],['Open',sc.open??0,(sc.open??0)>0?'sd-dv--a':''],['In Progress',sc.in_progress??0],
    ['Dispositioned',sc.dispositioned??0],['Closed',sc.closed??0,'sd-dv--g'],
    ['Critical Risk',sc.risk_critical??0,(sc.risk_critical??0)>0?'sd-dv--r':''],['High Risk',sc.risk_high??0,(sc.risk_high??0)>0?'sd-dv--a':''],
    ['Released',sc.released??0],['Quarantined',sc.quarantined??0,(sc.quarantined??0)>0?'sd-dv--a':''],
    ['Isolated',sc.isolated??0,(sc.isolated??0)>0?'sd-dv--r':''],['Referred',sc.referred??0],
    ['Avg Duration',sc.avg_case_duration_minutes!=null?`${sc.avg_case_duration_minutes} min`:'--'],
  ]
})
</script>

<style scoped>
*{box-sizing:border-box}
.sd-tb{--background:linear-gradient(135deg,#001D3D,#003566,#003F88);--color:#fff;--padding-start:6px;--padding-end:6px;--min-height:48px}
.sd-tb-title{display:flex;flex-direction:column;margin-left:2px}
.sd-tb-eye{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;color:rgba(255,255,255,.35)}
.sd-tb-h1{font-size:17px;font-weight:800;color:#fff}
.sd-live{display:flex;align-items:center;gap:4px;padding:3px 8px;border-radius:99px;font-size:10px;font-weight:700;border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.4);margin-right:3px}
.sd-live--on{color:#90EE90;border-color:rgba(144,238,144,.2)}
.sd-live-dot{width:6px;height:6px;border-radius:50%;background:currentColor}
.sd-live-n{font-size:14px;font-weight:900}.sd-live-l{font-size:9px}
.sd-pdf-btn{padding:3px 9px;border-radius:5px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.08);color:#fff;font-size:10px;font-weight:800;cursor:pointer;margin-right:3px}
.sd-rb{--color:rgba(255,255,255,.6);font-size:18px}

/* FILTERS */
.sd-fb{display:flex;align-items:center;background:#002F6C;padding:4px 6px;gap:4px}
.sd-fs{flex:1;display:flex;gap:3px;overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch}.sd-fs::-webkit-scrollbar{display:none}
.sd-fp{padding:5px 10px;border-radius:99px;border:none;background:rgba(255,255,255,.07);color:rgba(255,255,255,.55);font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0}
.sd-fp--on{background:rgba(255,255,255,.2);color:#fff}
.sd-ft{padding:4px 8px;border-radius:5px;border:1px solid rgba(255,255,255,.12);background:transparent;color:rgba(255,255,255,.5);font-size:12px;font-weight:700;cursor:pointer;flex-shrink:0}
.sd-ft--on{color:#fff;background:rgba(255,255,255,.1)}
.sd-af{background:#001D3D;padding:8px 10px 6px}
.sd-ar{display:flex;align-items:center;gap:4px;margin-bottom:6px;flex-wrap:wrap}
.sd-al{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:rgba(255,255,255,.35);min-width:40px;flex-shrink:0}
.sd-ap{display:flex;gap:2px;overflow-x:auto;scrollbar-width:none;flex:1;-webkit-overflow-scrolling:touch}.sd-ap::-webkit-scrollbar{display:none}
.sd-pill{padding:4px 9px;border-radius:99px;border:1px solid rgba(255,255,255,.1);background:transparent;color:rgba(255,255,255,.45);font-size:10px;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0}
.sd-pill--on{background:rgba(255,255,255,.18);color:#fff;border-color:rgba(255,255,255,.25)}
.sd-ai{padding:4px 6px;border:1px solid rgba(255,255,255,.12);border-radius:5px;background:rgba(255,255,255,.05);color:#fff;font-size:11px;outline:none;flex:1;min-width:0}
.sd-ai--h{max-width:44%}
.sd-aa{display:flex;gap:6px;padding-top:2px}
.sd-apply{flex:1;padding:6px;border-radius:5px;border:none;background:#1565C0;color:#fff;font-size:11px;font-weight:700;cursor:pointer}
.sd-clear{padding:6px 10px;border-radius:5px;border:1px solid rgba(255,255,255,.15);background:transparent;color:rgba(255,255,255,.5);font-size:11px;font-weight:700;cursor:pointer}
.sd-sl-enter-active,.sd-sl-leave-active{transition:max-height .2s,opacity .2s;overflow:hidden}
.sd-sl-enter-from,.sd-sl-leave-to{max-height:0;opacity:0}
.sd-sl-enter-to,.sd-sl-leave-from{max-height:250px;opacity:1}

/* CONTENT */
.sd-c{--background:#F0F4FA}
.sd-offline{padding:8px 12px;background:#FFF3E0;border-bottom:1px solid #FFB74D;font-size:11px;color:#BF360C;text-align:center;font-weight:600}
.sd-ld{display:flex;flex-direction:column;align-items:center;gap:10px;padding:50px 20px;color:#607D8B}
.sd-b{padding:0 8px}

/* HERO */
.sd-hero{display:flex;align-items:center;gap:10px;padding:12px 4px 6px;cursor:pointer}
.sd-ring-w{position:relative;width:78px;height:78px;flex-shrink:0}
.sd-ring-w svg{width:100%;height:100%}
.sd-ring-anim{transition:stroke-dasharray .6s ease}
.sd-ring-ctr{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.sd-ring-pct{font-size:20px;font-weight:900;color:#1A3A5C}.sd-ring-pct small{font-size:11px;color:#90A4AE}
.sd-ring-lbl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#90A4AE}
.sd-hero-r{flex:1;min-width:0}
.sd-hero-big{font-size:28px;font-weight:900;color:#1A3A5C;line-height:1}
.sd-hero-sub{font-size:11px;font-weight:600;color:#607D8B;margin-top:2px}
.sd-hero-row{display:flex;gap:8px;margin-top:6px;flex-wrap:wrap}
.sd-hm{display:flex;flex-direction:column}
.sd-hm-v{font-size:15px;font-weight:900;color:#1A3A5C;line-height:1}
.sd-hm-l{font-size:9px;font-weight:600;color:#90A4AE;margin-top:1px}
.sd-hm--up .sd-hm-v{color:#2E7D32}.sd-hm--dn .sd-hm-v{color:#C62828}

/* QUICK STATS */
.sd-qs{display:flex;gap:4px;overflow-x:auto;scrollbar-width:none;padding:2px 0 8px;-webkit-overflow-scrolling:touch}.sd-qs::-webkit-scrollbar{display:none}
.sd-q{flex:0 0 auto;min-width:76px;padding:8px 10px;background:#fff;border-radius:10px;border:1px solid #E8EDF5;display:flex;flex-direction:column;gap:2px;cursor:pointer}
.sd-q--r{border-color:rgba(229,57,53,.18);background:#FFF5F5}.sd-q--a{border-color:rgba(255,152,0,.18);background:#FFF8E1}
.sd-qn{font-size:18px;font-weight:900;color:#1A3A5C;line-height:1}
.sd-q--r .sd-qn{color:#C62828}.sd-q--a .sd-qn{color:#E65100}
.sd-ql{font-size:9px;font-weight:700;color:#90A4AE;text-transform:uppercase;letter-spacing:.2px}

/* SIGNALS */
.sd-sh{display:flex;align-items:center;gap:6px;padding:4px 4px 6px}
.sd-sh-dot{width:8px;height:8px;border-radius:50%;background:#E53935}
.sd-sh-t{font-size:13px;font-weight:800;color:#1A3A5C}
.sd-sh-b{padding:1px 6px;border-radius:99px;font-size:10px;font-weight:800;background:#FFEBEE;color:#C62828}
.sd-sigs{display:flex;flex-direction:column;gap:4px;margin-bottom:8px}
.sd-sig{display:flex;align-items:flex-start;gap:6px;padding:8px 10px;border-radius:8px;border:1px solid;background:#fff;cursor:pointer}
.sd-sig--critical{border-color:rgba(229,57,53,.18);background:#FFF5F5}
.sd-sig--high{border-color:rgba(255,152,0,.15);background:#FFF8E1}
.sd-sig--medium{border-color:#E8EDF5;background:#FAFBFD}
.sd-sig-i{width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:900;color:#fff;flex-shrink:0;margin-top:1px}
.sd-sig--critical .sd-sig-i{background:#E53935}.sd-sig--high .sd-sig-i{background:#F57C00}.sd-sig--medium .sd-sig-i{background:#BDBDBD}
.sd-sig-b{flex:1;min-width:0}
.sd-sig-c{display:block;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:#90A4AE}
.sd-sig-t{display:block;font-size:12px;font-weight:700;color:#1A3A5C;margin-top:1px}
.sd-sig-d{display:block;font-size:10px;color:#546E7A;margin-top:2px;line-height:1.4}

/* CARD */
.sd-card{background:#fff;border-radius:10px;border:1px solid #E8EDF5;margin-bottom:8px;overflow:hidden;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.sd-ch{display:flex;align-items:baseline;gap:4px;padding:10px 12px 4px;flex-wrap:wrap}
.sd-ct{font-size:14px;font-weight:800;color:#1A3A5C}
.sd-cs{font-size:11px;color:#90A4AE;font-weight:600}
.sd-cbadge{padding:1px 6px;border-radius:99px;font-size:9px;font-weight:800;background:#FFEBEE;color:#C62828;margin-left:auto}
.sd-cbadge--w{background:#FFF3E0;color:#E65100}

/* TREND */
.sd-tw{padding:2px 12px 8px}.sd-tsvg{width:100%;height:auto;display:block}
.sd-txl{display:flex;justify-content:space-between;padding:3px 4px 0}.sd-txl span{font-size:9px;color:#B0BEC5}
.sd-tleg{display:flex;gap:10px;padding:5px 4px 0}
.sd-tl{font-size:10px;font-weight:700;color:#607D8B;display:flex;align-items:center;gap:4px}
.sd-tl::before{content:'';width:8px;height:2px;border-radius:1px}
.sd-tl--t::before{background:#1565C0}.sd-tl--s::before{background:#E53935}

/* BARS */
.sd-bars{padding:4px 12px 10px;display:flex;flex-direction:column;gap:4px}
.sd-bar{display:flex;align-items:center;gap:4px}
.sd-bl{font-size:11px;font-weight:700;color:#546E7A;min-width:14px;text-align:center}
.sd-bl--w{min-width:55px;text-transform:capitalize;font-size:10px}
.sd-bt{flex:1;height:8px;background:#E8EDF5;border-radius:4px;overflow:hidden}
.sd-bf{height:100%;border-radius:3px;transition:width .4s}
.sd-bf--male{background:#1E88E5}.sd-bf--female{background:#E91E63}.sd-bf--other{background:#9C27B0}.sd-bf--unknown{background:#607D8B}.sd-bf--syn{background:#7E57C2}
.sd-bv{font-size:11px;font-weight:700;color:#546E7A;min-width:22px;text-align:right}

/* TEMP STACK */
.sd-ts{padding:4px 12px 10px;cursor:pointer}
.sd-tsb{display:flex;height:10px;border-radius:5px;overflow:hidden;gap:1px}
.sd-tss{min-width:2px;transition:flex .4s}
.sd-tsl{display:flex;flex-wrap:wrap;gap:2px 8px;padding:5px 0 0}
.sd-tsi{display:flex;align-items:center;gap:4px;font-size:10px;font-weight:600;color:#546E7A}
.sd-tsd{width:5px;height:5px;border-radius:50%;flex-shrink:0}

/* HOURS */
.sd-hrs{display:flex;gap:1px;align-items:flex-end;height:60px;padding:4px 6px 4px}
.sd-hc{flex:1;display:flex;flex-direction:column;align-items:center;gap:1px;min-width:0}
.sd-hc--odd .sd-hl{visibility:hidden}
.sd-hbw{width:100%;height:40px;display:flex;align-items:flex-end}
.sd-hb{width:100%;background:#C5CAE9;border-radius:1px 1px 0 0;transition:height .3s;min-height:1px}
.sd-hb--pk{background:#1565C0}
.sd-hl{font-size:8px;color:#B0BEC5}

/* FUNNEL */
.sd-fun{padding:4px 12px 2px;display:flex;flex-direction:column;gap:3px}
.sd-fn{position:relative;height:34px;border-radius:6px;overflow:hidden;background:#F5F7FA}
.sd-fnb{position:absolute;inset:0;border-radius:5px;background:#E3F2FD;transition:width .5s}
.sd-fnf{position:relative;display:flex;align-items:center;gap:4px;padding:0 8px;height:100%;z-index:1}
.sd-fnn{font-size:14px;font-weight:900;color:#1A3A5C}
.sd-fnl{font-size:10px;font-weight:600;color:#546E7A;text-transform:capitalize;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sd-fnp{font-size:10px;font-weight:800;color:#90A4AE}
.sd-fk{display:flex;gap:3px;padding:4px 12px 10px}
.sd-fki{flex:1;padding:5px 3px;background:#F5F7FA;border-radius:4px;text-align:center}
.sd-fkv{display:block;font-size:14px;font-weight:900;color:#1A3A5C}
.sd-fkl{display:block;font-size:9px;font-weight:600;color:#90A4AE;text-transform:uppercase;margin-top:1px}
.sd-fkv--w{color:#E65100}.sd-fkv--d{color:#C62828}

/* GRID */
.sd-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;margin:0 12px 10px;background:#E8EDF5;border-radius:6px;overflow:hidden}
.sd-gc{display:flex;flex-direction:column;align-items:center;padding:10px 4px;background:#fff;gap:2px}
.sd-gn{font-size:17px;font-weight:900;color:#1A3A5C;line-height:1}
.sd-gl{font-size:9px;font-weight:700;color:#90A4AE;text-transform:uppercase;letter-spacing:.2px;text-align:center}
.sd-gn--rd{color:#C62828!important}.sd-gn--am{color:#E65100!important}.sd-gn--or{color:#F57C00!important}.sd-gn--gr{color:#2E7D32!important}.sd-gn--bl{color:#1565C0!important}

/* DEVICES */
.sd-dev{display:flex;align-items:center;gap:6px;padding:5px 12px;border-top:1px solid #F0F4FA}.sd-dev:first-of-type{border-top:none}
.sd-dev-l{flex:1;min-width:0}
.sd-dev-id{display:block;font-size:11px;font-weight:700;color:#1A3A5C;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sd-dev-m{display:block;font-size:10px;color:#90A4AE}
.sd-dev-s{font-size:9px;font-weight:800;padding:2px 6px;border-radius:3px;text-transform:uppercase}
.sd-dev-s--healthy{background:#E8F5E9;color:#2E7D32}.sd-dev-s--warning{background:#FFF3E0;color:#E65100}.sd-dev-s--critical{background:#FFEBEE;color:#C62828}
.sd-dev-s--silent,.sd-dev-s--pending,.sd-dev-s--unknown{background:#F5F5F5;color:#607D8B}

/* OFFICERS */
.sd-off{display:flex;align-items:center;gap:4px;padding:3px 12px}
.sd-off-n{font-size:11px;font-weight:600;color:#546E7A;min-width:60px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sd-off-bw{flex:1;height:8px;background:#E8EDF5;border-radius:4px;overflow:hidden}
.sd-off-b{height:100%;background:#1E88E5;border-radius:4px;transition:width .4s}
.sd-off-v{font-size:11px;font-weight:800;color:#546E7A;min-width:20px;text-align:right}

/* MODALS — full-screen with IonContent for native scroll */
.sd-modal::part(content){border-radius:14px 14px 0 0}
.sd-st{--background:#fff;--border-width:0 0 1px 0;--border-color:#E8EDF5;--min-height:36px}
.sd-handle{width:32px;height:3px;border-radius:2px;background:#DDE3EA;margin:0 10px}
.sd-sc{--background:#fff}
.sd-sp{padding:14px 16px 0}
.sd-mt{font-size:16px;font-weight:800;color:#1A3A5C;margin:0 0 12px}
.sd-dg{display:flex;flex-direction:column}
.sd-dr{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #F0F4FA}
.sd-dr:last-child{border-bottom:none}
.sd-dk{font-size:12px;font-weight:600;color:#546E7A}
.sd-dv{font-size:13px;font-weight:800;color:#1A3A5C}
.sd-dv--r{color:#C62828!important}.sd-dv--a{color:#E65100!important}.sd-dv--g{color:#2E7D32!important}.sd-dv--dim{color:#90A4AE!important}

/* SIGNAL DETAIL */
.sd-sig-hdr{padding:12px;border-radius:8px;margin-bottom:12px}
.sd-sig-hdr--critical{background:#FFEBEE}.sd-sig-hdr--high{background:#FFF3E0}.sd-sig-hdr--medium{background:#F5F5F5}
.sd-sig-hc{display:block;font-size:7px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#90A4AE;margin-bottom:4px}
.sd-sig-ht{display:block;font-size:15px;font-weight:800;color:#1A3A5C;line-height:1.3}
.sd-sig-desc{font-size:13px;color:#37474F;line-height:1.55;margin:0 0 14px}
.sd-sig-act{background:#E3F2FD;border-radius:8px;padding:12px;margin-bottom:14px}
.sd-sig-al{display:block;font-size:7px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#1565C0;margin-bottom:5px}
.sd-sig-at{font-size:12px;font-weight:600;color:#0D47A1;line-height:1.5;margin:0}

@media(min-width:500px){.sd-b{max-width:480px;margin:0 auto}.sd-hc--odd .sd-hl{visibility:visible}}
</style>

<template>
  <IonPage>

    <!-- ═══ HEADER ═══════════════════════════════════════════════════════ -->
    <IonHeader class="pd-header" translucent>
      <IonToolbar class="pd-toolbar">
        <IonButtons slot="start">
          <IonMenuButton menu="app-menu" class="pd-menu-btn" />
        </IonButtons>
        <div class="pd-title-block" slot="start">
          <span class="pd-eyebrow">IHR Art.23 · Analytics</span>
          <span class="pd-title">Screening Dashboard</span>
        </div>
        <IonButtons slot="end">
          <!-- Live ticker pill -->
          <div class="pd-live-pill" :class="liveData ? 'pd-live-pill--on' : ''">
            <span class="pd-live-dot" />
            <span class="pd-live-total">{{ liveData?.total ?? '—' }}</span>
            <span class="pd-live-lbl">today</span>
          </div>
          <!-- Period selector -->
          <button class="pd-period-btn" @click="periodMenuOpen = !periodMenuOpen">
            <IonIcon :icon="calendarOutline" />
            <span>{{ PERIOD_LABELS[activePeriod] }}</span>
          </button>
          <IonButton fill="clear" class="pd-refresh-btn" @click="loadAll" :disabled="anyLoading">
            <IonIcon :icon="refreshOutline" slot="icon-only" />
          </IonButton>
        </IonButtons>
      </IonToolbar>

      <!-- Period dropdown -->
      <div v-if="periodMenuOpen" class="pd-period-menu">
        <button v-for="p in PERIODS" :key="p.v"
          :class="['pd-period-opt', activePeriod === p.v && 'pd-period-opt--on']"
          @click="setPeriod(p.v)">
          {{ p.label }}
          <IonIcon v-if="activePeriod === p.v" :icon="checkmarkOutline" />
        </button>
      </div>
    </IonHeader>

    <!-- ═══ CONTENT ══════════════════════════════════════════════════════ -->
    <IonContent class="pd-content" :fullscreen="true">
      <IonRefresher slot="fixed" @ionRefresh="onPullRefresh($event)">
        <IonRefresherContent pulling-text="Pull to refresh" refreshing-spinner="crescent" />
      </IonRefresher>

      <!-- Global loading -->
      <div v-if="anyLoading && !summary" class="pd-global-loading">
        <IonSpinner name="crescent" class="pd-main-spinner" />
        <p>Loading analytics…</p>
      </div>

      <div v-else class="pd-body">

        <!-- ── SECTION 1: TODAY KPI HERO STRIP ─────────────────────────── -->
        <div class="pd-kpi-strip">
          <!-- Symptomatic rate radial — hero card -->
          <div class="pd-hero-card">
            <div class="pd-hero-chart">
              <VueApexcharts
                v-if="radialOpts"
                type="radialBar"
                height="160"
                :options="radialOpts"
                :series="radialSeries"
              />
              <div v-else class="pd-chart-skeleton" style="height:160px" />
            </div>
            <div class="pd-hero-meta">
              <span class="pd-hero-val">{{ summary?.today?.total ?? '—' }}</span>
              <span class="pd-hero-lbl">screened today</span>
              <span class="pd-hero-delta" :class="deltaClass">
                {{ deltaArrow }}{{ Math.abs(summary?.today?.vs_yesterday ?? 0) }} vs yesterday
              </span>
            </div>
          </div>

          <!-- KPI cards scroll row -->
          <div class="pd-kpi-row">
            <div class="pd-kpi-card pd-kpi--sym">
              <span class="pd-kpi-ico">⚠</span>
              <span class="pd-kpi-num">{{ summary?.today?.symptomatic ?? '—' }}</span>
              <span class="pd-kpi-sub">Symptomatic</span>
            </div>
            <div class="pd-kpi-card pd-kpi--ref">
              <span class="pd-kpi-ico">↗</span>
              <span class="pd-kpi-num">{{ summary?.today?.referrals ?? '—' }}</span>
              <span class="pd-kpi-sub">Referred</span>
            </div>
            <div class="pd-kpi-card pd-kpi--fever">
              <span class="pd-kpi-ico">🌡</span>
              <span class="pd-kpi-num">{{ summary?.today?.fever_count ?? '—' }}</span>
              <span class="pd-kpi-sub">Fever ≥37.5°</span>
            </div>
            <div class="pd-kpi-card pd-kpi--queue" :class="(summary?.referral_queue?.critical_open ?? 0) > 0 && 'pd-kpi--critical'">
              <span class="pd-kpi-ico">📋</span>
              <span class="pd-kpi-num">{{ summary?.referral_queue?.open ?? '—' }}</span>
              <span class="pd-kpi-sub">Open Referrals</span>
            </div>
            <div class="pd-kpi-card pd-kpi--alert" :class="(summary?.alerts?.critical_open ?? 0) > 0 && 'pd-kpi--critical'">
              <span class="pd-kpi-ico">🔔</span>
              <span class="pd-kpi-num">{{ summary?.alerts?.open ?? '—' }}</span>
              <span class="pd-kpi-sub">Open Alerts</span>
            </div>
            <div class="pd-kpi-card pd-kpi--week">
              <span class="pd-kpi-ico">📅</span>
              <span class="pd-kpi-num">{{ summary?.this_week?.total ?? '—' }}</span>
              <span class="pd-kpi-sub">This Week</span>
            </div>
          </div>
        </div>

        <!-- ── SECTION 2: 7-DAY TREND AREA CHART ──────────────────────── -->
        <div class="pd-card">
          <div class="pd-card-hdr">
            <span class="pd-card-title">Screening Trend</span>
            <span class="pd-card-sub">{{ PERIOD_LABELS[activePeriod] }} · rolling avg</span>
          </div>
          <div v-if="trendOpts && !trendLoading" class="pd-chart-wrap">
            <VueApexcharts type="area" height="200" :options="trendOpts" :series="trendSeries" />
          </div>
          <div v-else class="pd-chart-skeleton" style="height:200px">
            <IonSpinner v-if="trendLoading" name="crescent" class="pd-sk-spin" />
          </div>
        </div>

        <!-- ── SECTION 3: GENDER + SYMPTOMATIC RATE ────────────────────── -->
        <div class="pd-row-2">
          <!-- Gender donut -->
          <div class="pd-card pd-card--half">
            <div class="pd-card-hdr">
              <span class="pd-card-title">Gender</span>
            </div>
            <div v-if="genderOpts && !epiLoading" class="pd-chart-wrap">
              <VueApexcharts type="donut" height="180" :options="genderOpts" :series="genderSeries" />
            </div>
            <div v-else class="pd-chart-skeleton" style="height:180px" />
          </div>

          <!-- Temperature bands radial -->
          <div class="pd-card pd-card--half">
            <div class="pd-card-hdr">
              <span class="pd-card-title">Temperature</span>
            </div>
            <div v-if="tempBandOpts && !epiLoading" class="pd-chart-wrap">
              <VueApexcharts type="radialBar" height="180" :options="tempBandOpts" :series="tempBandSeries" />
            </div>
            <div v-else class="pd-chart-skeleton" style="height:180px" />
          </div>
        </div>

        <!-- ── SECTION 4: HOURLY HEATMAP ───────────────────────────────── -->
        <div class="pd-card">
          <div class="pd-card-hdr">
            <span class="pd-card-title">Peak Screening Hours</span>
            <span class="pd-card-sub">Volume by hour of day</span>
          </div>
          <div v-if="hourlyOpts && !heatmapLoading" class="pd-chart-wrap">
            <VueApexcharts type="bar" height="160" :options="hourlyOpts" :series="hourlySeries" />
          </div>
          <div v-else class="pd-chart-skeleton" style="height:160px" />
        </div>

        <!-- ── SECTION 5: REFERRAL FUNNEL ─────────────────────────────── -->
        <div class="pd-card">
          <div class="pd-card-hdr">
            <span class="pd-card-title">Referral Funnel</span>
            <span class="pd-card-sub">Primary → Secondary conversion</span>
          </div>
          <div v-if="funnelOpts && !funnelLoading" class="pd-chart-wrap">
            <VueApexcharts type="bar" height="200" :options="funnelOpts" :series="funnelSeries" />
          </div>
          <div v-else class="pd-chart-skeleton" style="height:200px" />
          <!-- Funnel KPIs below chart -->
          <div v-if="funnelData" class="pd-funnel-kpis">
            <div class="pd-fkpi">
              <span class="pd-fkpi-val">{{ funnelData?.notifications?.avg_pickup_minutes ?? '—' }}</span>
              <span class="pd-fkpi-lbl">min avg pickup</span>
            </div>
            <div class="pd-fkpi">
              <span class="pd-fkpi-val">{{ funnelData?.secondary_cases?.avg_case_duration_minutes ?? '—' }}</span>
              <span class="pd-fkpi-lbl">min avg case</span>
            </div>
            <div class="pd-fkpi">
              <span class="pd-fkpi-val pd-fkpi--warn">{{ funnelData?.notifications?.open ?? '—' }}</span>
              <span class="pd-fkpi-lbl">open referrals</span>
            </div>
            <div class="pd-fkpi">
              <span class="pd-fkpi-val pd-fkpi--danger">{{ funnelData?.secondary_cases?.risk_critical ?? '—' }}</span>
              <span class="pd-fkpi-lbl">critical cases</span>
            </div>
          </div>
        </div>

        <!-- ── SECTION 6: WEEKDAY SYMPTOMATIC HEATMAP ─────────────────── -->
        <div class="pd-card">
          <div class="pd-card-hdr">
            <span class="pd-card-title">Risk by Weekday</span>
            <span class="pd-card-sub">Symptomatic rate per day</span>
          </div>
          <div v-if="weekdayOpts && !epiLoading" class="pd-chart-wrap">
            <VueApexcharts type="bar" height="160" :options="weekdayOpts" :series="weekdaySeries" />
          </div>
          <div v-else class="pd-chart-skeleton" style="height:160px" />
        </div>

        <!-- ── SECTION 7: WEEKLY REPORT SUMMARY ───────────────────────── -->
        <div class="pd-card" v-if="weeklyData">
          <div class="pd-card-hdr">
            <span class="pd-card-title">Weekly Report</span>
            <span class="pd-card-sub">{{ weeklyData.week_label }}</span>
          </div>
          <div class="pd-weekly-grid">
            <div class="pd-wg-item">
              <span class="pd-wg-val">{{ weeklyData.report?.total_screened ?? '—' }}</span>
              <span class="pd-wg-lbl">Screened</span>
            </div>
            <div class="pd-wg-item pd-wg--sym">
              <span class="pd-wg-val">{{ weeklyData.report?.symptomatic_rate ?? '—' }}%</span>
              <span class="pd-wg-lbl">Symp. Rate</span>
            </div>
            <div class="pd-wg-item">
              <span class="pd-wg-val">{{ weeklyData.report?.total_referrals ?? '—' }}</span>
              <span class="pd-wg-lbl">Referrals</span>
            </div>
            <div class="pd-wg-item pd-wg--fever">
              <span class="pd-wg-val">{{ weeklyData.report?.fever_count ?? '—' }}</span>
              <span class="pd-wg-lbl">Fever</span>
            </div>
            <div class="pd-wg-item">
              <span class="pd-wg-val">{{ weeklyData.report?.avg_daily ?? '—' }}</span>
              <span class="pd-wg-lbl">Avg/Day</span>
            </div>
            <div class="pd-wg-item" :class="(weeklyData.report?.vs_previous_week ?? 0) >= 0 ? 'pd-wg--up' : 'pd-wg--down'">
              <span class="pd-wg-val">
                {{ (weeklyData.report?.vs_previous_week ?? 0) >= 0 ? '▲' : '▼' }}{{ Math.abs(weeklyData.report?.vs_previous_week ?? 0) }}
              </span>
              <span class="pd-wg-lbl">vs Last Week</span>
            </div>
          </div>
          <!-- Gender breakdown mini-bar -->
          <div class="pd-gender-mini">
            <div class="pd-gm-bar">
              <div class="pd-gm-seg pd-gm--m" :style="{flex: weeklyData.report?.male ?? 1}" title="Male" />
              <div class="pd-gm-seg pd-gm--f" :style="{flex: weeklyData.report?.female ?? 1}" title="Female" />
              <div class="pd-gm-seg pd-gm--o" :style="{flex: (weeklyData.report?.other ?? 0) + (weeklyData.report?.unknown ?? 0)}" title="Other/Unknown" />
            </div>
            <div class="pd-gm-legend">
              <span class="pd-gml pd-gml--m">Male {{ weeklyData.report?.male ?? 0 }}</span>
              <span class="pd-gml pd-gml--f">Female {{ weeklyData.report?.female ?? 0 }}</span>
              <span class="pd-gml pd-gml--o">Other {{ (weeklyData.report?.other ?? 0) + (weeklyData.report?.unknown ?? 0) }}</span>
            </div>
          </div>
        </div>

        <!-- ── SECTION 8: DEVICE HEALTH ────────────────────────────────── -->
        <div class="pd-card" v-if="deviceData?.devices?.length">
          <div class="pd-card-hdr">
            <span class="pd-card-title">Device Sync Health</span>
            <span class="pd-card-sub">{{ deviceData.device_count }} devices</span>
            <span v-if="deviceData.devices_at_risk > 0" class="pd-at-risk-badge">
              ⚠ {{ deviceData.devices_at_risk }} at risk
            </span>
          </div>
          <!-- Sync health stacked bar -->
          <div v-if="syncHealthOpts" class="pd-chart-wrap">
            <VueApexcharts type="bar" height="120" :options="syncHealthOpts" :series="syncHealthSeries" />
          </div>
          <!-- Device list (top 5) -->
          <div class="pd-device-list">
            <div v-for="d in deviceData.devices.slice(0,5)" :key="d.device_id"
              :class="['pd-device-row', 'pd-device-row--'+d.status.toLowerCase()]">
              <div class="pd-device-info">
                <span class="pd-device-id">{{ d.device_id }}</span>
                <span class="pd-device-meta">{{ d.platform }} · {{ d.total_records }} records</span>
              </div>
              <div class="pd-device-right">
                <span :class="['pd-device-status', 'pd-ds--'+d.status.toLowerCase()]">
                  {{ d.status }}
                </span>
                <span v-if="d.unsynced > 0" class="pd-device-unsynced">{{ d.unsynced }} pending</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── SECTION 9: SCREENER LEADERBOARD ────────────────────────── -->
        <div class="pd-card" v-if="screenerData?.screeners?.length">
          <div class="pd-card-hdr">
            <span class="pd-card-title">Officer Activity</span>
            <span class="pd-card-sub">{{ screenerData.screener_count }} officers · {{ PERIOD_LABELS[activePeriod] }}</span>
          </div>
          <div v-if="screenerBarOpts" class="pd-chart-wrap">
            <VueApexcharts type="bar" height="180" :options="screenerBarOpts" :series="screenerBarSeries" />
          </div>
          <div v-else class="pd-chart-skeleton" style="height:180px" />
        </div>

        <!-- Bottom pad -->
        <div style="height:40px" />
      </div>
    </IonContent>

    <!-- Toast -->
    <IonToast :is-open="toast.show" :message="toast.msg" :color="toast.color" :duration="3000"
      position="top" @didDismiss="toast.show=false" />
  </IonPage>
</template>

<script setup>
// ─────────────────────────────────────────────────────────────────────────────
// ECSA-HC POE SENTINEL — PrimaryDashboard.vue
// WHO/IHR 2005 · Primary Screening Analytics Dashboard
//
// Chart library: ApexCharts + vue3-apexcharts
// Install: npm install apexcharts vue3-apexcharts
// Register globally in main.ts / main.js:
//   import VueApexCharts from 'vue3-apexcharts'
//   app.use(VueApexCharts)
//
// All API calls go to the PrimaryScreeningDashboardController routes.
// No IDB reads in this view — dashboard is a server-only analytics surface.
// ─────────────────────────────────────────────────────────────────────────────

import { ref, computed, reactive, onMounted, onUnmounted } from 'vue'
import {
  IonPage, IonHeader, IonToolbar, IonButtons, IonMenuButton,
  IonButton, IonContent, IonIcon, IonSpinner,
  IonRefresher, IonRefresherContent, IonToast,
  onIonViewWillEnter,
} from '@ionic/vue'
import {
  refreshOutline, calendarOutline, checkmarkOutline,
} from 'ionicons/icons'
import { APP } from '@/services/poeDB'

// ── ApexCharts: imported locally so this works even without global app.use(VueApexCharts)
// If vue3-apexcharts is not installed: npm install apexcharts vue3-apexcharts
import VueApexcharts from 'vue3-apexcharts'

// ─── AUTH ──────────────────────────────────────────────────────────────────
function getAuth() { return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {} }
const auth = ref(getAuth())

// ─── CONSTANTS ─────────────────────────────────────────────────────────────
const PERIODS = [
  { v: 7,  label: '7 days'  },
  { v: 14, label: '14 days' },
  { v: 30, label: '30 days' },
  { v: 90, label: '90 days' },
]
const PERIOD_LABELS = { 7:'7 days', 14:'14 days', 30:'30 days', 90:'90 days' }

// ─── CHART THEME ───────────────────────────────────────────────────────────
// Consistent dark-on-light theme for all ApexCharts instances.
// These settings are merged into every chart options object.
const CHART_BASE = {
  toolbar:    { show: false },
  animations: { enabled: true, easing: 'easeinout', speed: 500 },
  fontFamily: 'system-ui, -apple-system, sans-serif',
  foreColor:  '#546E7A',
  sparkline:  { enabled: false },
}
const GRID_BASE = {
  borderColor: '#EEF1F7',
  strokeDashArray: 4,
  xaxis: { lines: { show: false } },
  yaxis: { lines: { show: true } },
}
const TOOLTIP_BASE = {
  theme: 'dark',
  style: { fontSize: '12px' },
}
const AXIS_BASE = {
  axisBorder: { show: false },
  axisTicks:  { show: false },
  labels:     { style: { fontSize: '10px', colors: '#90A4AE' } },
}
// Brand palette
const PALETTE = {
  primary:    '#1A3A5C',
  symptomatic:'#EF5350',
  asymptomatic:'#26A69A',
  referral:   '#FF8F00',
  fever:      '#F4511E',
  male:       '#1E88E5',
  female:     '#E91E63',
  other:      '#9C27B0',
  synced:     '#43A047',
  unsynced:   '#FB8C00',
  failed:     '#E53935',
  gradient1:  '#0D47A1',
  gradient2:  '#1976D2',
}

// ─── STATE ─────────────────────────────────────────────────────────────────
const activePeriod    = ref(30)
const periodMenuOpen  = ref(false)

// Server data
const summary        = ref(null)
const trendData      = ref(null)
const heatmapData    = ref(null)
const funnelData     = ref(null)
const epiData        = ref(null)
const deviceData     = ref(null)
const screenerData   = ref(null)
const weeklyData     = ref(null)
const liveData       = ref(null)

// Loading states
const summaryLoading  = ref(false)
const trendLoading    = ref(false)
const heatmapLoading  = ref(false)
const funnelLoading   = ref(false)
const epiLoading      = ref(false)
const deviceLoading   = ref(false)
const screenerLoading = ref(false)
const weeklyLoading   = ref(false)

const toast = reactive({ show: false, msg: '', color: 'success' })

let liveTimer = null
let pollTimer = null

// ─── COMPUTED ───────────────────────────────────────────────────────────────
const anyLoading = computed(() =>
  summaryLoading.value || trendLoading.value || epiLoading.value
)

const deltaClass = computed(() => {
  const d = summary.value?.today?.vs_yesterday ?? 0
  return d >= 0 ? 'pd-hero-delta--up' : 'pd-hero-delta--down'
})
const deltaArrow = computed(() => (summary.value?.today?.vs_yesterday ?? 0) >= 0 ? '▲ ' : '▼ ')

// ─── CHART: RADIAL BAR (symptomatic rate) ──────────────────────────────────
const radialSeries = computed(() => {
  const rate = summary.value?.today?.symptomatic > 0 && summary.value?.today?.total > 0
    ? parseFloat(((summary.value.today.symptomatic / summary.value.today.total) * 100).toFixed(1))
    : 0
  return [rate]
})
const radialOpts = computed(() => ({
  chart: { ...CHART_BASE, type: 'radialBar', height: 160 },
  plotOptions: {
    radialBar: {
      startAngle: -110, endAngle: 110,
      hollow: { size: '62%' },
      track: { background: '#E8EDF5', strokeWidth: '100%' },
      dataLabels: {
        name: { show: true, color: '#90A4AE', fontSize: '10px', offsetY: 16 },
        value: {
          show: true, color: '#1A3A5C', fontSize: '22px', fontWeight: 900,
          formatter: v => v + '%', offsetY: -6,
        },
      },
    },
  },
  fill: {
    type: 'gradient',
    gradient: { shade: 'dark', type: 'horizontal', gradientToColors: ['#EF5350'], stops: [0, 100] },
  },
  colors: [PALETTE.symptomatic],
  labels: ['Symptomatic'],
  tooltip: { enabled: false },
}))

// ─── CHART: AREA TREND ─────────────────────────────────────────────────────
const trendSeries = computed(() => {
  if (!trendData.value?.series?.length) return []
  const s = trendData.value.series
  return [
    { name: 'Total',       data: s.map(d => ({ x: d.date, y: d.total })) },
    { name: 'Symptomatic', data: s.map(d => ({ x: d.date, y: d.symptomatic })) },
    { name: '7-day avg',   data: s.map(d => ({ x: d.date, y: d.avg7 })) },
  ]
})
const trendOpts = computed(() => ({
  chart: { ...CHART_BASE, type: 'area', height: 200,
    zoom: { enabled: false },
    dropShadow: { enabled: true, top: 4, left: 0, blur: 6, opacity: 0.1 },
  },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: [2, 1.5, 2], dashArray: [0, 0, 4] },
  fill: {
    type: ['gradient', 'gradient', 'none'],
    gradient: {
      shadeIntensity: 1, opacityFrom: [0.5, 0.3, 0], opacityTo: [0.05, 0.02, 0],
      stops: [0, 95, 100],
    },
  },
  colors: [PALETTE.primary, PALETTE.symptomatic, PALETTE.referral],
  xaxis: {
    type: 'datetime',
    ...AXIS_BASE,
    labels: { ...AXIS_BASE.labels, datetimeFormatter: { day: 'dd MMM' }, rotate: 0 },
  },
  yaxis: { ...AXIS_BASE, min: 0, tickAmount: 4, labels: { ...AXIS_BASE.labels } },
  grid: { ...GRID_BASE, padding: { left: 0, right: 0 } },
  tooltip: { ...TOOLTIP_BASE, x: { format: 'dd MMM yyyy' } },
  legend: {
    position: 'top', horizontalAlign: 'left', fontSize: '11px',
    markers: { radius: 3 }, offsetY: -4,
  },
}))

// ─── CHART: GENDER DONUT ───────────────────────────────────────────────────
const genderSeries = computed(() => {
  if (!epiData.value?.by_gender?.length) return []
  return epiData.value.by_gender.map(g => g.total)
})
const genderOpts = computed(() => {
  const labels = epiData.value?.by_gender?.map(g => g.gender) ?? []
  const colors = labels.map(g =>
    g==='MALE'?PALETTE.male : g==='FEMALE'?PALETTE.female : g==='OTHER'?PALETTE.other : '#90A4AE'
  )
  return {
    chart: { ...CHART_BASE, type: 'donut', height: 180 },
    colors,
    labels,
    dataLabels: { enabled: true, style: { fontSize: '10px' } },
    plotOptions: {
      pie: {
        donut: {
          size: '65%',
          labels: {
            show: true,
            total: { show: true, label: 'Total', fontSize: '10px', color: '#90A4AE',
              formatter: w => w.globals.seriesTotals.reduce((a,b) => a+b, 0) },
          },
        },
      },
    },
    legend: { show: true, position: 'bottom', fontSize: '10px', offsetY: 4 },
    tooltip: { ...TOOLTIP_BASE },
  }
})

// ─── CHART: TEMPERATURE BANDS RADIAL ───────────────────────────────────────
const tempBandSeries = computed(() => {
  if (!epiData.value?.temperature?.bands) return []
  const { hypothermia, normal, low_grade_fever, high_fever } = epiData.value.temperature.bands
  const total = (hypothermia||0) + (normal||0) + (low_grade_fever||0) + (high_fever||0)
  if (!total) return [0, 0, 0, 0]
  return [
    parseFloat(((high_fever||0)/total*100).toFixed(1)),
    parseFloat(((low_grade_fever||0)/total*100).toFixed(1)),
    parseFloat(((normal||0)/total*100).toFixed(1)),
    parseFloat(((hypothermia||0)/total*100).toFixed(1)),
  ]
})
const tempBandOpts = computed(() => ({
  chart: { ...CHART_BASE, type: 'radialBar', height: 180 },
  plotOptions: {
    radialBar: {
      offsetY: 0,
      startAngle: -90, endAngle: 270,
      hollow: { size: '30%' },
      track: { background: '#EEF1F7' },
      dataLabels: { name: { fontSize: '10px' }, value: { fontSize: '11px', fontWeight: 700 } },
    },
  },
  colors: ['#E53935', '#FF7043', '#26A69A', '#1E88E5'],
  labels: ['High Fever', 'Low-Grade', 'Normal', 'Hypo'],
  legend: { show: true, position: 'bottom', fontSize: '9px', offsetY: 8 },
  tooltip: { ...TOOLTIP_BASE },
}))

// ─── CHART: HOURLY BAR ─────────────────────────────────────────────────────
const hourlySeries = computed(() => {
  if (!heatmapData.value?.buckets?.length) return []
  return [{
    name: 'Screenings',
    data: heatmapData.value.buckets.map(b => b.total),
  }]
})
const hourlyOpts = computed(() => {
  const buckets = heatmapData.value?.buckets ?? []
  // Find peak hour for annotation
  const maxBucket = buckets.reduce((m,b) => b.total > (m?.total||0) ? b : m, null)
  return {
    chart: { ...CHART_BASE, type: 'bar', height: 160 },
    plotOptions: {
      bar: {
        borderRadius: 3,
        columnWidth: '70%',
        colors: {
          ranges: [{
            from: maxBucket?.total ?? 0,
            to: (maxBucket?.total ?? 0) + 0.1,
            color: PALETTE.symptomatic,
          }],
        },
        distributed: false,
      },
    },
    fill: {
      type: 'gradient',
      gradient: {
        type: 'vertical',
        gradientToColors: ['#42A5F5'],
        stops: [0, 100],
        opacityFrom: 1, opacityTo: 0.6,
      },
    },
    colors: [PALETTE.primary],
    dataLabels: { enabled: false },
    xaxis: {
      ...AXIS_BASE,
      categories: buckets.map(b => b.label),
      labels: {
        ...AXIS_BASE.labels,
        rotate: 0,
        formatter: v => v?.split(':')[0] ?? v,
      },
    },
    yaxis: { ...AXIS_BASE, show: false },
    grid: { ...GRID_BASE, yaxis: { lines: { show: false } } },
    tooltip: { ...TOOLTIP_BASE, y: { formatter: v => v + ' screenings' } },
    annotations: maxBucket ? {
      xaxis: [{
        x: maxBucket.label,
        strokeDashArray: 0,
        borderColor: PALETTE.symptomatic,
        label: {
          text: 'PEAK',
          style: { color: '#fff', background: PALETTE.symptomatic, fontSize: '9px', padding: { left: 4, right: 4, top: 2, bottom: 2 } },
        },
      }],
    } : {},
  }
})

// ─── CHART: FUNNEL (isFunnel bar) ──────────────────────────────────────────
const funnelSeries = computed(() => {
  if (!funnelData.value?.funnel?.length) return []
  return [{
    name: 'Count',
    data: funnelData.value.funnel.map(f => f.count),
  }]
})
const funnelOpts = computed(() => {
  const stages = funnelData.value?.funnel ?? []
  return {
    chart: { ...CHART_BASE, type: 'bar', height: 200 },
    plotOptions: {
      bar: {
        borderRadius: 4,
        horizontal: true,
        isFunnel: true,
        distributed: true,
        dataLabels: { position: 'bottom' },
      },
    },
    colors: ['#1A3A5C','#1565C0','#EF5350','#FF8F00','#43A047'],
    dataLabels: {
      enabled: true,
      formatter: (val, { dataPointIndex }) => {
        const s = stages[dataPointIndex]
        return s ? `${s.stage}: ${val} (${s.rate}%)` : val
      },
      style: { fontSize: '10px', colors: ['#fff'] },
      dropShadow: { enabled: false },
    },
    xaxis: {
      ...AXIS_BASE,
      categories: stages.map(s => s.stage),
      labels: { show: false },
    },
    yaxis: {
      ...AXIS_BASE,
      labels: { ...AXIS_BASE.labels, style: { ...AXIS_BASE.labels.style, fontSize: '9px' } },
    },
    grid: { show: false },
    legend: { show: false },
    tooltip: { ...TOOLTIP_BASE,
      y: { formatter: (val, { dataPointIndex }) => {
        const s = stages[dataPointIndex]
        return s ? `${val} travelers (${s.rate}%)` : val
      }},
    },
  }
})

// ─── CHART: WEEKDAY SYMPTOMATIC RATE ───────────────────────────────────────
const weekdaySeries = computed(() => {
  if (!epiData.value?.symp_by_weekday?.length) return []
  return [
    { name: 'Total',      data: epiData.value.symp_by_weekday.map(d => d.total) },
    { name: 'Symptomatic', data: epiData.value.symp_by_weekday.map(d => d.symptomatic) },
  ]
})
const weekdayOpts = computed(() => {
  const days = epiData.value?.symp_by_weekday ?? []
  return {
    chart: { ...CHART_BASE, type: 'bar', height: 160, stacked: false },
    plotOptions: { bar: { borderRadius: 3, columnWidth: '50%' } },
    colors: [PALETTE.primary, PALETTE.symptomatic],
    dataLabels: { enabled: false },
    xaxis: {
      ...AXIS_BASE,
      categories: days.map(d => d.label),
    },
    yaxis: { ...AXIS_BASE, show: false },
    grid: { ...GRID_BASE, yaxis: { lines: { show: false } } },
    tooltip: {
      ...TOOLTIP_BASE,
      y: {
        formatter: (val, { dataPointIndex, seriesIndex }) => {
          const d = days[dataPointIndex]
          if (seriesIndex === 1 && d) return `${val} (${d.symp_rate}%)`
          return val
        },
      },
    },
    legend: { show: true, position: 'top', horizontalAlign: 'left', fontSize: '10px', offsetY: -4 },
  }
})

// ─── CHART: DEVICE SYNC HEALTH ─────────────────────────────────────────────
const syncHealthSeries = computed(() => {
  if (!deviceData.value?.devices?.length) return []
  const devices = deviceData.value.devices.slice(0, 5)
  return [
    { name: 'Synced',  data: devices.map(d => d.synced) },
    { name: 'Pending', data: devices.map(d => d.unsynced) },
    { name: 'Failed',  data: devices.map(d => d.failed) },
  ]
})
const syncHealthOpts = computed(() => {
  const devices = deviceData.value?.devices?.slice(0, 5) ?? []
  return {
    chart: { ...CHART_BASE, type: 'bar', height: 120, stacked: true, stackType: '100%' },
    plotOptions: { bar: { horizontal: true, borderRadius: 2 } },
    colors: [PALETTE.synced, PALETTE.unsynced, PALETTE.failed],
    dataLabels: { enabled: false },
    xaxis: { ...AXIS_BASE, categories: devices.map(d => d.device_id.slice(-8)), labels: { show: false } },
    yaxis: { ...AXIS_BASE, labels: { ...AXIS_BASE.labels, style: { ...AXIS_BASE.labels.style, fontSize: '9px' } } },
    grid: { show: false },
    legend: { show: true, position: 'top', fontSize: '10px', offsetY: -4 },
    tooltip: {
      ...TOOLTIP_BASE,
      y: { formatter: v => v + ' records' },
    },
  }
})

// ─── CHART: SCREENER BAR ───────────────────────────────────────────────────
const screenerBarSeries = computed(() => {
  if (!screenerData.value?.screeners?.length) return []
  const top = screenerData.value.screeners.slice(0, 6)
  return [
    { name: 'Total',      data: top.map(s => s.total) },
    { name: 'Symptomatic', data: top.map(s => s.symptomatic) },
  ]
})
const screenerBarOpts = computed(() => {
  const top = screenerData.value?.screeners?.slice(0, 6) ?? []
  const names = top.map(s => (s.full_name ?? s.username ?? 'Unknown').split(' ')[0])
  return {
    chart: { ...CHART_BASE, type: 'bar', height: 180 },
    plotOptions: { bar: { borderRadius: 3, columnWidth: '55%' } },
    colors: [PALETTE.primary, PALETTE.symptomatic],
    dataLabels: { enabled: false },
    xaxis: { ...AXIS_BASE, categories: names },
    yaxis: { ...AXIS_BASE, show: false },
    grid: { ...GRID_BASE, yaxis: { lines: { show: false } } },
    legend: { show: true, position: 'top', horizontalAlign: 'left', fontSize: '10px', offsetY: -4 },
    tooltip: {
      ...TOOLTIP_BASE,
      y: { formatter: (val, { dataPointIndex, seriesIndex }) => {
        const s = top[dataPointIndex]
        if (seriesIndex === 0) return `${val} (symp rate: ${s?.symptomatic_rate ?? 0}%)`
        return val
      }},
    },
  }
})

// ─── API FETCHER ────────────────────────────────────────────────────────────
async function api(path, extraParams = {}) {
  const userId = auth.value?.id
  if (!userId) return null
  const p = new URLSearchParams({ user_id: userId, days: activePeriod.value, ...extraParams })
  const ctrl = new AbortController()
  const tid  = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
  try {
    const res = await fetch(`${window.SERVER_URL}${path}?${p}`, {
      headers: { Accept: 'application/json' }, signal: ctrl.signal,
    })
    clearTimeout(tid)
    if (!res.ok) return null
    const j = await res.json()
    return j.success ? j.data : null
  } catch { clearTimeout(tid); return null }
}

// ─── LOAD FUNCTIONS ─────────────────────────────────────────────────────────
async function loadSummary() {
  summaryLoading.value = true
  summary.value = await api('/dashboard/summary')
  summaryLoading.value = false
}

async function loadTrend() {
  trendLoading.value = true
  trendData.value = await api('/dashboard/trend')
  trendLoading.value = false
}

async function loadHeatmap() {
  heatmapLoading.value = true
  heatmapData.value = await api('/dashboard/heatmap', { group_by: 'hour' })
  heatmapLoading.value = false
}

async function loadFunnel() {
  funnelLoading.value = true
  funnelData.value = await api('/dashboard/funnel')
  funnelLoading.value = false
}

async function loadEpi() {
  epiLoading.value = true
  epiData.value = await api('/dashboard/epi')
  epiLoading.value = false
}

async function loadDeviceHealth() {
  deviceLoading.value = true
  deviceData.value = await api('/dashboard/device-health')
  deviceLoading.value = false
}

async function loadScreenerReport() {
  screenerLoading.value = true
  screenerData.value = await api('/dashboard/screener-report')
  screenerLoading.value = false
}

async function loadWeekly() {
  weeklyLoading.value = true
  weeklyData.value = await api('/dashboard/weekly-report')
  weeklyLoading.value = false
}

async function loadLive() {
  const d = await api('/dashboard/live')
  if (d) liveData.value = d
}

/**
 * Load all dashboard data.
 * Prioritises visible data first (summary + trend), then defers secondary
 * panels (epi, funnel, device, screener) so the hero loads fast.
 */
async function loadAll() {
  auth.value = getAuth()
  periodMenuOpen.value = false

  // Priority 1: hero data (renders immediately visible content)
  await Promise.all([loadSummary(), loadTrend()])

  // Priority 2: above-fold charts  
  await Promise.all([loadEpi(), loadHeatmap()])

  // Priority 3: below-fold panels
  Promise.all([loadFunnel(), loadDeviceHealth(), loadScreenerReport(), loadWeekly()])
    .catch(() => {})
}

// ─── PERIOD SELECTOR ────────────────────────────────────────────────────────
function setPeriod(v) {
  activePeriod.value   = v
  periodMenuOpen.value = false
  loadAll()
}

// ─── PULL TO REFRESH ────────────────────────────────────────────────────────
async function onPullRefresh(ev) {
  await loadAll()
  ev.target.complete()
}

// ─── LIVE POLLING ───────────────────────────────────────────────────────────
function startLivePolling() {
  loadLive()
  liveTimer = setInterval(() => loadLive(), 30_000)
}
function stopLivePolling() {
  clearInterval(liveTimer)
}

// ─── LIFECYCLE ──────────────────────────────────────────────────────────────
onMounted(() => {
  loadAll()
  startLivePolling()
})

onIonViewWillEnter(() => {
  auth.value = getAuth()
  loadSummary()
  loadLive()
})

onUnmounted(() => {
  stopLivePolling()
  clearInterval(pollTimer)
})
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════
   PRIMARY SCREENING DASHBOARD · Namespace: pd-*
   Design: Navy command centre — premium enterprise analytics, mobile-first.
   Light background, deep navy cards, gradient chart fills.
   Light theme only. No dark mode.
═══════════════════════════════════════════════════════════════════════ */

/* ── HEADER ──────────────────────────────────────────────────────────── */
.pd-toolbar {
  --background: #0D253F;
  --color: #fff;
  --padding-start: 8px;
  --padding-end: 8px;
  --min-height: 52px;
}
.pd-menu-btn { --color: rgba(255,255,255,.75); }
.pd-title-block { display:flex; flex-direction:column; margin-left:4px; }
.pd-eyebrow { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1.4px; color:rgba(255,255,255,.45); line-height:1; }
.pd-title   { font-size:16px; font-weight:800; color:#fff; line-height:1.2; }

/* Live pill */
.pd-live-pill {
  display:inline-flex; align-items:center; gap:4px;
  padding:4px 9px; border-radius:9999px; margin-right:4px;
  font-size:11px; font-weight:700;
  border:1px solid rgba(255,255,255,.2);
  background:rgba(255,255,255,.08); color:rgba(255,255,255,.6);
  transition:all .3s;
}
.pd-live-pill--on { background:rgba(67,160,71,.25); color:#A5D6A7; border-color:rgba(67,160,71,.4); }
.pd-live-dot {
  width:6px; height:6px; border-radius:50%; background:currentColor;
  animation:pd-pulse 2s ease-in-out infinite;
}
@keyframes pd-pulse { 0%,100%{opacity:1} 50%{opacity:.35} }
.pd-live-total { font-size:13px; font-weight:900; }
.pd-live-lbl   { font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }

/* Period button */
.pd-period-btn {
  display:inline-flex; align-items:center; gap:4px;
  padding:4px 8px; border-radius:7px; margin-right:4px;
  border:1px solid rgba(255,255,255,.2);
  background:rgba(255,255,255,.08); color:rgba(255,255,255,.8);
  font-size:11px; font-weight:700; cursor:pointer; white-space:nowrap;
}
.pd-period-btn ion-icon { font-size:13px; }
.pd-refresh-btn { --color:rgba(255,255,255,.7); }

/* Period dropdown */
.pd-period-menu {
  position:absolute; top:100%; right:8px; z-index:999;
  background:#1A3A5C; border-radius:10px;
  box-shadow:0 8px 24px rgba(0,0,0,.35);
  overflow:hidden; min-width:140px;
  animation:pd-drop .15s ease;
}
@keyframes pd-drop { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.pd-period-opt {
  width:100%; padding:12px 16px; text-align:left;
  border:none; background:transparent; color:rgba(255,255,255,.75);
  font-size:13px; font-weight:600; cursor:pointer;
  display:flex; align-items:center; justify-content:space-between;
  transition:background .1s;
}
.pd-period-opt:hover, .pd-period-opt--on { background:rgba(255,255,255,.12); color:#fff; }
.pd-period-opt ion-icon { font-size:14px; color:#42A5F5; }

/* ── CONTENT ──────────────────────────────────────────────────────────── */
.pd-content { --background:#F0F4F8; }

.pd-global-loading {
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  height:60vh; gap:14px; color:#607D8B; font-size:14px;
}
.pd-main-spinner { --color:#1A3A5C; width:36px; height:36px; }

.pd-body { padding:0 0 16px; }

/* ── KPI HERO STRIP ──────────────────────────────────────────────────── */
.pd-kpi-strip {
  background:linear-gradient(180deg, #0D253F 0%, #1A3A5C 60%, #F0F4F8 60%);
  padding:0 12px 0;
}

.pd-hero-card {
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.12);
  border-radius:14px;
  display:flex; align-items:center;
  padding:6px 16px 6px 0;
  margin-bottom:10px;
  backdrop-filter:blur(8px);
}
.pd-hero-chart { flex-shrink:0; width:160px; }
.pd-hero-meta  { flex:1; padding-left:4px; }
.pd-hero-val   { display:block; font-size:36px; font-weight:900; color:#fff; line-height:1; }
.pd-hero-lbl   { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.6px; color:rgba(255,255,255,.5); margin-top:2px; }
.pd-hero-delta { display:block; font-size:12px; font-weight:700; margin-top:6px; }
.pd-hero-delta--up   { color:#A5D6A7; }
.pd-hero-delta--down { color:#EF9A9A; }

/* KPI card scroll row */
.pd-kpi-row {
  display:flex; gap:8px; overflow-x:auto; scrollbar-width:none;
  padding-bottom:14px;
}
.pd-kpi-row::-webkit-scrollbar { display:none; }

.pd-kpi-card {
  flex:0 0 80px; background:#fff; border-radius:12px;
  padding:10px 8px; text-align:center;
  box-shadow:0 2px 8px rgba(0,0,0,.1);
  border:1px solid rgba(255,255,255,.5);
  display:flex; flex-direction:column; align-items:center; gap:3px;
  transition:transform .1s;
}
.pd-kpi-card:active { transform:scale(.97); }
.pd-kpi-ico { font-size:18px; line-height:1; }
.pd-kpi-num { font-size:20px; font-weight:900; color:#1A3A5C; line-height:1; }
.pd-kpi-sub { font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#90A4AE; text-align:center; }

.pd-kpi--sym   { border-top:3px solid #EF5350; }
.pd-kpi--ref   { border-top:3px solid #FF8F00; }
.pd-kpi--fever { border-top:3px solid #F4511E; }
.pd-kpi--queue { border-top:3px solid #1565C0; }
.pd-kpi--alert { border-top:3px solid #7B1FA2; }
.pd-kpi--week  { border-top:3px solid #43A047; }
.pd-kpi--critical { border-top-color:#E53935; box-shadow:0 2px 8px rgba(229,57,53,.25); }
.pd-kpi--critical .pd-kpi-num { color:#E53935; }

/* ── SHARED CARD ─────────────────────────────────────────────────────── */
.pd-card {
  background:#fff; border-radius:14px; margin:10px 12px 0;
  box-shadow:0 1px 6px rgba(0,0,0,.07), 0 0 0 1px rgba(0,0,0,.04);
  overflow:hidden;
}
.pd-card-hdr {
  display:flex; align-items:baseline; gap:8px;
  padding:13px 14px 6px;
}
.pd-card-title {
  font-size:13px; font-weight:800; color:#1A3A5C;
}
.pd-card-sub   {
  font-size:10px; color:#90A4AE; margin-left:auto;
}
.pd-chart-wrap { padding:0 4px 8px; }

/* Row with two half-cards */
.pd-row-2 { display:flex; gap:10px; margin:10px 12px 0; }
.pd-row-2 .pd-card { flex:1; margin:0; min-width:0; }

/* Skeleton loader */
.pd-chart-skeleton {
  background:linear-gradient(90deg, #EEF1F7 25%, #F5F7FA 50%, #EEF1F7 75%);
  background-size:200% 100%;
  animation:pd-shimmer 1.4s ease-in-out infinite;
  border-radius:8px; margin:8px 14px;
  display:flex; align-items:center; justify-content:center;
}
@keyframes pd-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.pd-sk-spin { --color:#B0BEC5; width:24px; height:24px; }

/* ── FUNNEL KPIs ──────────────────────────────────────────────────────── */
.pd-funnel-kpis {
  display:flex; border-top:1px solid #EEF1F7; padding:10px 14px;
}
.pd-fkpi { flex:1; text-align:center; }
.pd-fkpi:not(:last-child) { border-right:1px solid #EEF1F7; }
.pd-fkpi-val { display:block; font-size:18px; font-weight:900; color:#1A3A5C; line-height:1; }
.pd-fkpi-lbl { display:block; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#90A4AE; margin-top:3px; }
.pd-fkpi--warn   { color:#FF8F00; }
.pd-fkpi--danger { color:#E53935; }

/* ── WEEKLY REPORT ────────────────────────────────────────────────────── */
.pd-weekly-grid {
  display:grid; grid-template-columns:repeat(3,1fr);
  gap:1px; background:#EEF1F7;
  margin:0 14px 0; border-radius:8px; overflow:hidden;
}
.pd-wg-item {
  background:#fff; padding:10px 8px; text-align:center;
  display:flex; flex-direction:column; gap:3px;
}
.pd-wg-val { font-size:18px; font-weight:900; color:#1A3A5C; line-height:1; }
.pd-wg-lbl { font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#90A4AE; }
.pd-wg--sym   .pd-wg-val { color:#EF5350; }
.pd-wg--fever .pd-wg-val { color:#F4511E; }
.pd-wg--up    .pd-wg-val { color:#43A047; }
.pd-wg--down  .pd-wg-val { color:#E53935; }

/* Gender mini stacked bar */
.pd-gender-mini { padding:10px 14px; border-top:1px solid #EEF1F7; }
.pd-gm-bar { height:10px; border-radius:5px; overflow:hidden; display:flex; gap:1px; margin-bottom:6px; }
.pd-gm-seg { min-width:4px; border-radius:3px; }
.pd-gm--m  { background:#1E88E5; }
.pd-gm--f  { background:#E91E63; }
.pd-gm--o  { background:#9C27B0; }
.pd-gm-legend { display:flex; gap:12px; flex-wrap:wrap; }
.pd-gml { font-size:10px; font-weight:700; padding-left:10px; position:relative; }
.pd-gml::before { content:''; position:absolute; left:0; top:50%; transform:translateY(-50%); width:7px; height:7px; border-radius:50%; }
.pd-gml--m::before { background:#1E88E5; }
.pd-gml--f::before { background:#E91E63; }
.pd-gml--o::before { background:#9C27B0; }

/* ── DEVICE HEALTH ───────────────────────────────────────────────────── */
.pd-at-risk-badge {
  padding:2px 8px; border-radius:9999px; font-size:9px; font-weight:800;
  background:#FFEBEE; color:#C62828; border:1px solid rgba(198,40,40,.3);
  margin-left:auto;
}
.pd-device-list { border-top:1px solid #EEF1F7; }
.pd-device-row {
  display:flex; align-items:center; gap:10px;
  padding:9px 14px; border-bottom:1px solid #F5F7FA;
}
.pd-device-row:last-child { border-bottom:none; }
.pd-device-info { flex:1; min-width:0; }
.pd-device-id {
  display:block; font-size:11px; font-weight:700; color:#1A3A5C;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-family:monospace;
}
.pd-device-meta { display:block; font-size:10px; color:#90A4AE; }
.pd-device-right { display:flex; flex-direction:column; align-items:flex-end; gap:2px; }
.pd-device-status { font-size:9px; font-weight:800; padding:2px 6px; border-radius:4px; }
.pd-ds--healthy  { background:#E8F5E9; color:#2E7D32; }
.pd-ds--pending  { background:#FFF3E0; color:#E65100; }
.pd-ds--warning  { background:#FFEBEE; color:#C62828; }
.pd-ds--critical { background:#B71C1C; color:#fff; }
.pd-ds--silent   { background:#E8EAF6; color:#283593; }
.pd-ds--unknown  { background:#F5F5F5; color:#616161; }
.pd-device-unsynced { font-size:9px; font-weight:700; color:#E65100; }

.pd-device-row--critical .pd-device-id { color:#C62828; }

/* ── RESPONSIVE ─────────────────────────────────────────────────────── */
@media (min-width: 600px) {
  .pd-body { max-width:720px; margin:0 auto; }
  .pd-kpi-strip { border-radius:0; }
}
@media (min-width: 960px) {
  .pd-body { max-width:1080px; }
}
</style>
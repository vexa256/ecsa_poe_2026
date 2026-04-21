<template>
  <IonPage>
    <!-- ═══ HEADER ═════════════════════════════════════════════════════ -->
    <IonHeader class="pc-hdr" translucent>
      <div class="pc-hdr-bg">
        <div class="pc-hdr-top">
          <IonButtons slot="start">
            <button v-if="isMobile" class="pc-hdr-burger" @click="drawerOpen = true" aria-label="Open scope sidebar">
              <IonIcon :icon="menuOutline"/>
            </button>
            <IonBackButton default-href="/home" class="pc-back"/>
          </IonButtons>
          <div class="pc-hdr-title">
            <span class="pc-hdr-eye">Admin · Notification Routing</span>
            <span class="pc-hdr-h1">POE Contacts</span>
          </div>
          <IonButtons slot="end">
            <button class="pc-hdr-btn" :class="loading && 'pc-spin'" :disabled="loading" @click="load" aria-label="Refresh">
              <IonIcon :icon="refreshOutline"/>
            </button>
            <button v-if="isAdmin && canEditInCurrentScope" class="pc-hdr-btn pc-hdr-btn--primary" @click="openAdd" aria-label="Add contact">
              <IonIcon :icon="addOutline"/>
            </button>
          </IonButtons>
        </div>

        <!-- KPI strip -->
        <div class="pc-kpis">
          <div class="pc-kpi">
            <span class="pc-kpi-n">{{ summary.total }}</span>
            <span class="pc-kpi-l">Contacts</span>
          </div>
          <div class="pc-kpi pc-kpi--editable">
            <span class="pc-kpi-n">{{ summary.editable_here }}</span>
            <span class="pc-kpi-l">Editable by you</span>
          </div>
          <div class="pc-kpi">
            <span class="pc-kpi-n">{{ distinctCountries.length }}</span>
            <span class="pc-kpi-l">Countries</span>
          </div>
          <div class="pc-kpi">
            <span class="pc-kpi-n">{{ distinctPOEs.length }}</span>
            <span class="pc-kpi-l">POEs</span>
          </div>
        </div>
      </div>
    </IonHeader>

    <!-- ═══ CONTENT (two-pane on desktop, single-pane + drawer on mobile) ═══ -->
    <IonContent class="pc-content" :fullscreen="true">
      <div class="pc-layout">
        <!-- Sidebar — desktop inline / mobile drawer -->
        <aside :class="['pc-side', drawerOpen && 'pc-side--open']">
          <div class="pc-side-scroll">
            <!-- Close button (mobile only) -->
            <button v-if="isMobile" class="pc-side-close" @click="drawerOpen = false" aria-label="Close sidebar">×</button>

            <!-- Scope badge -->
            <div class="pc-me">
              <div class="pc-me-avatar">{{ roleInitials(auth.role_key) }}</div>
              <div class="pc-me-body">
                <span class="pc-me-role">{{ auth.role_key || 'UNKNOWN' }}</span>
                <span class="pc-me-scope">{{ scopeLabel }}</span>
              </div>
            </div>

            <!-- Search -->
            <div class="pc-search">
              <IonIcon :icon="searchOutline" class="pc-search-ic"/>
              <input v-model="searchQ" class="pc-search-inp" placeholder="Search name, email, phone…" type="search"/>
              <button v-if="searchQ" class="pc-search-x" @click="searchQ = ''" aria-label="Clear">×</button>
            </div>

            <!-- Levels -->
            <section class="pc-side-sec">
              <header class="pc-side-h"><span>Notification Level</span></header>
              <div class="pc-lvls">
                <button :class="['pc-lvl', levelFilter === null && 'pc-lvl--on']" @click="levelFilter = null">
                  <span class="pc-lvl-dot pc-lvl-dot--all"/>
                  <span class="pc-lvl-l">All levels</span>
                  <span class="pc-lvl-n">{{ summary.total }}</span>
                </button>
                <button v-for="l in LEVELS" :key="l"
                  :class="['pc-lvl', levelFilter === l && 'pc-lvl--on']"
                  @click="toggleLevel(l)">
                  <span :class="['pc-lvl-dot', `pc-lvl-dot--${l.toLowerCase()}`]"/>
                  <span class="pc-lvl-l">{{ l }}</span>
                  <span class="pc-lvl-n">{{ summary.by_level?.[l] || 0 }}</span>
                </button>
              </div>
            </section>

            <!-- Scope tree (Country → District → POE) -->
            <section class="pc-side-sec">
              <header class="pc-side-h">
                <span>Jurisdictions</span>
                <button v-if="activeScope.country || activeScope.district || activeScope.poe"
                  class="pc-side-clear" @click="clearScope">Clear</button>
              </header>

              <div class="pc-tree">
                <template v-for="country in tree" :key="country.code">
                  <div :class="['pc-tree-l1', activeScope.country === country.code && 'pc-tree--on']"
                    @click="selectCountry(country.code)">
                    <span class="pc-tree-icon">🌍</span>
                    <span class="pc-tree-label">{{ country.code || '—' }}</span>
                    <span class="pc-tree-count">{{ country.n }}</span>
                  </div>
                  <template v-if="activeScope.country === country.code || country.code === (auth.country_code || 'UG')">
                    <div v-for="district in country.districts" :key="district.code"
                      :class="['pc-tree-l2', activeScope.district === district.code && 'pc-tree--on']"
                      @click.stop="selectDistrict(country.code, district.code)">
                      <span class="pc-tree-icon">🏛️</span>
                      <span class="pc-tree-label">{{ district.code || '—' }}</span>
                      <span class="pc-tree-count">{{ district.n }}</span>
                    </div>
                    <template v-if="activeScope.district === district.code" v-for="district in country.districts" :key="'poe-' + district.code">
                      <div v-for="poe in district.poes" :key="poe.code"
                        :class="['pc-tree-l3', activeScope.poe === poe.code && 'pc-tree--on']"
                        @click.stop="selectPoe(country.code, district.code, poe.code)">
                        <span class="pc-tree-icon">📍</span>
                        <span class="pc-tree-label">{{ poe.code || '—' }}</span>
                        <span class="pc-tree-count">{{ poe.n }}</span>
                      </div>
                    </template>
                  </template>
                </template>
              </div>
            </section>

            <!-- Quick filters -->
            <section class="pc-side-sec">
              <header class="pc-side-h"><span>Subscription</span></header>
              <div class="pc-quicks">
                <button :class="['pc-quick', subFilter === 'tier1' && 'pc-quick--on']" @click="toggleSub('tier1')">IHR Tier 1</button>
                <button :class="['pc-quick', subFilter === 'tier2' && 'pc-quick--on']" @click="toggleSub('tier2')">IHR Tier 2</button>
                <button :class="['pc-quick', subFilter === 'critical' && 'pc-quick--on']" @click="toggleSub('critical')">Critical alerts</button>
                <button :class="['pc-quick', subFilter === 'breach' && 'pc-quick--on']" @click="toggleSub('breach')">7-1-7 breaches</button>
                <button :class="['pc-quick', subFilter === 'daily' && 'pc-quick--on']" @click="toggleSub('daily')">Daily report</button>
                <button :class="['pc-quick', subFilter === 'weekly' && 'pc-quick--on']" @click="toggleSub('weekly')">Weekly report</button>
              </div>
            </section>

            <!-- Legend -->
            <section class="pc-side-sec pc-side-sec--legend">
              <header class="pc-side-h"><span>Legend</span></header>
              <div class="pc-legend">
                <span class="pc-legend-row"><span class="pc-legend-sw pc-legend-sw--own"/>In your scope — editable</span>
                <span class="pc-legend-row"><span class="pc-legend-sw pc-legend-sw--other"/>Out of scope — read-only</span>
              </div>
            </section>
          </div>
        </aside>

        <!-- Backdrop for mobile drawer -->
        <div v-if="isMobile && drawerOpen" class="pc-backdrop" @click="drawerOpen = false"/>

        <!-- Main list -->
        <main class="pc-main">
          <!-- Active-filter breadcrumb -->
          <div v-if="hasAnyFilter" class="pc-crumbs">
            <button v-for="f in activeFilters" :key="f.k" class="pc-crumb" @click="clearFilter(f.k)">
              <span class="pc-crumb-l">{{ f.label }}</span>
              <span class="pc-crumb-x">×</span>
            </button>
            <button class="pc-crumbs-clear" @click="clearAllFilters">Clear all</button>
          </div>

          <div v-if="loading && contacts.length === 0" class="pc-loading">
            <div v-for="i in 3" :key="i" class="pc-skel">
              <div class="pc-skel-avatar"/>
              <div class="pc-skel-body">
                <div class="pc-skel-line pc-skel-line--t"/>
                <div class="pc-skel-line pc-skel-line--s"/>
              </div>
            </div>
          </div>

          <div v-else-if="visibleGroups.length === 0" class="pc-empty">
            <div class="pc-empty-ic">
              <svg viewBox="0 0 64 64" width="56" height="56" fill="none" stroke="currentColor" stroke-width="2"><circle cx="32" cy="22" r="10"/><path d="M12 52 a 20 20 0 0 1 40 0" stroke-linecap="round"/></svg>
            </div>
            <div class="pc-empty-t">No contacts match</div>
            <div class="pc-empty-s">
              <template v-if="hasAnyFilter">Clear the active filters or pick a different jurisdiction.</template>
              <template v-else>Add the first notification contact for your jurisdiction.</template>
            </div>
            <button v-if="isAdmin && !hasAnyFilter && canEditInCurrentScope" class="pc-empty-btn" @click="openAdd">+ Add contact</button>
          </div>

          <section v-for="g in visibleGroups" :key="g.key" class="pc-grp">
            <header class="pc-grp-h">
              <div class="pc-grp-meta">
                <span :class="['pc-grp-pill', `pc-grp-pill--${g.level.toLowerCase()}`]">{{ g.level }}</span>
                <span class="pc-grp-poe">{{ g.poe_code || '—' }}</span>
                <span v-if="g.district_code" class="pc-grp-district">{{ g.district_code }}</span>
              </div>
              <span class="pc-grp-n">{{ g.items.length }}</span>
            </header>

            <article v-for="(c, i) in g.items" :key="c.id"
              :class="['pc-c', !c.is_active && 'pc-c--off', c.can_edit_by_me && 'pc-c--mine']"
              :style="{ 'animation-delay': (i * 30) + 'ms' }">
              <div class="pc-c-avatar" :style="{ background: avatarBg(c.full_name) }">
                {{ avatarText(c.full_name) }}
                <span v-if="!c.can_edit_by_me" class="pc-c-lock" title="Out of your admin scope — read-only">🔒</span>
              </div>

              <div class="pc-c-main">
                <div class="pc-c-top">
                  <h3 class="pc-c-name">{{ c.full_name }}</h3>
                  <span class="pc-c-prio" v-if="c.priority_order > 1">#{{ c.priority_order }} backup</span>
                  <span :class="['pc-c-chan', `pc-c-chan--${c.preferred_channel.toLowerCase()}`]">{{ c.preferred_channel }}</span>
                </div>

                <div v-if="c.position || c.organisation" class="pc-c-pos">
                  {{ c.position }}{{ c.position && c.organisation ? ' · ' : '' }}{{ c.organisation }}
                </div>

                <div class="pc-c-rows">
                  <a v-if="c.email" :href="'mailto:' + c.email" class="pc-c-row">
                    <IonIcon :icon="mailOutline"/><span>{{ c.email }}</span>
                  </a>
                  <a v-if="c.phone" :href="'tel:' + c.phone" class="pc-c-row">
                    <IonIcon :icon="callOutline"/><span>{{ c.phone }}</span>
                  </a>
                  <div v-if="c.escalates_to_contact_id" class="pc-c-row pc-c-row--esc">
                    <IonIcon :icon="arrowForwardOutline"/>
                    <span>Escalates to <strong>{{ escalateName(c.escalates_to_contact_id) }}</strong></span>
                  </div>
                </div>

                <div class="pc-c-flags">
                  <span v-if="c.receives_critical == 1" class="pc-flag pc-flag--c">CRIT</span>
                  <span v-if="c.receives_high == 1" class="pc-flag pc-flag--h">HIGH</span>
                  <span v-if="c.receives_medium == 1" class="pc-flag">MED</span>
                  <span v-if="c.receives_low == 1" class="pc-flag">LOW</span>
                  <span v-if="c.receives_tier1 == 1" class="pc-flag pc-flag--t1">T1</span>
                  <span v-if="c.receives_tier2 == 1" class="pc-flag pc-flag--t2">T2</span>
                  <span v-if="c.receives_breach_alerts == 1" class="pc-flag pc-flag--b">7-1-7</span>
                  <span v-if="c.receives_daily_report == 1" class="pc-flag pc-flag--d">Daily</span>
                  <span v-if="c.receives_weekly_report == 1" class="pc-flag pc-flag--d">Weekly</span>
                  <span v-if="c.receives_followup_reminders == 1" class="pc-flag">Reminders</span>
                </div>

                <div v-if="c.can_edit_by_me" class="pc-c-actions">
                  <button class="pc-btn pc-btn--ghost" @click="openEdit(c)">Edit</button>
                  <button v-if="c.is_active == 1" class="pc-btn pc-btn--ghost" @click="toggle(c, false)">Deactivate</button>
                  <button v-else class="pc-btn pc-btn--ghost" @click="toggle(c, true)">Activate</button>
                  <button class="pc-btn pc-btn--danger" @click="remove(c)">Delete</button>
                </div>
                <div v-else class="pc-c-readonly">
                  <IonIcon :icon="lockClosedOutline"/>
                  <span>Read-only — outside your admin scope</span>
                </div>
              </div>
            </article>
          </section>

          <div style="height:64px"/>
        </main>
      </div>
    </IonContent>

    <!-- Add/Edit modal -->
    <IonModal :is-open="showForm" @didDismiss="showForm = false" :initial-breakpoint="1" :breakpoints="[0, 1]">
      <IonHeader>
        <IonToolbar class="pc-mtbar">
          <IonTitle class="pc-mtbar-t">{{ form.id ? 'Edit contact' : 'New contact' }}</IonTitle>
          <IonButtons slot="end"><IonButton fill="clear" @click="showForm = false">Close</IonButton></IonButtons>
        </IonToolbar>
      </IonHeader>
      <IonContent class="pc-modal">
        <div class="pc-mp">
          <section class="pc-m-sec">
            <header class="pc-m-h">
              <span class="pc-m-n">1</span>
              <span class="pc-m-t">Where</span>
            </header>
            <label class="pc-flbl">POE code</label>
            <input v-model.trim="form.poe_code" class="pc-inp" :disabled="!!form.id"/>

            <div class="pc-frow">
              <div class="pc-fhalf">
                <label class="pc-flbl">Level</label>
                <select v-model="form.level" class="pc-inp">
                  <option v-for="l in LEVELS" :key="l">{{ l }}</option>
                </select>
              </div>
              <div class="pc-fhalf">
                <label class="pc-flbl">Priority</label>
                <input v-model.number="form.priority_order" type="number" min="1" class="pc-inp"/>
              </div>
            </div>
          </section>

          <section class="pc-m-sec">
            <header class="pc-m-h">
              <span class="pc-m-n">2</span>
              <span class="pc-m-t">Who</span>
            </header>
            <label class="pc-flbl">Full name</label>
            <input v-model.trim="form.full_name" class="pc-inp" placeholder="Dr Jane Doe"/>

            <div class="pc-frow">
              <div class="pc-fhalf">
                <label class="pc-flbl">Position</label>
                <input v-model.trim="form.position" class="pc-inp" placeholder="District Health Officer"/>
              </div>
              <div class="pc-fhalf">
                <label class="pc-flbl">Organisation</label>
                <input v-model.trim="form.organisation" class="pc-inp" placeholder="Ministry of Health"/>
              </div>
            </div>
          </section>

          <section class="pc-m-sec">
            <header class="pc-m-h">
              <span class="pc-m-n">3</span>
              <span class="pc-m-t">How to reach them</span>
            </header>
            <div class="pc-frow">
              <div class="pc-fhalf">
                <label class="pc-flbl">Email</label>
                <input v-model.trim="form.email" class="pc-inp" type="email"/>
              </div>
              <div class="pc-fhalf">
                <label class="pc-flbl">Alt email</label>
                <input v-model.trim="form.alternate_email" class="pc-inp" type="email"/>
              </div>
            </div>
            <div class="pc-frow">
              <div class="pc-fhalf">
                <label class="pc-flbl">Phone</label>
                <input v-model.trim="form.phone" class="pc-inp"/>
              </div>
              <div class="pc-fhalf">
                <label class="pc-flbl">Alt phone</label>
                <input v-model.trim="form.alternate_phone" class="pc-inp"/>
              </div>
            </div>
            <label class="pc-flbl">Preferred channel</label>
            <select v-model="form.preferred_channel" class="pc-inp">
              <option>EMAIL</option><option>SMS</option><option>BOTH</option>
            </select>
            <label class="pc-flbl">Escalates to</label>
            <select v-model="form.escalates_to_contact_id" class="pc-inp">
              <option :value="null">— None (terminal) —</option>
              <option v-for="c in availableEscalations" :key="c.id" :value="c.id">
                {{ c.full_name }} · {{ c.level }} ({{ c.poe_code }})
              </option>
            </select>
          </section>

          <section class="pc-m-sec">
            <header class="pc-m-h">
              <span class="pc-m-n">4</span>
              <span class="pc-m-t">What they receive</span>
            </header>
            <label class="pc-sub-h">Risk levels</label>
            <div class="pc-flg-grid">
              <label class="pc-flg-c"><input type="checkbox" v-model="form.receives_critical"/><span>Critical</span></label>
              <label class="pc-flg-c"><input type="checkbox" v-model="form.receives_high"/><span>High</span></label>
              <label class="pc-flg-c"><input type="checkbox" v-model="form.receives_medium"/><span>Medium</span></label>
              <label class="pc-flg-c"><input type="checkbox" v-model="form.receives_low"/><span>Low</span></label>
            </div>
            <label class="pc-sub-h">IHR + operational</label>
            <div class="pc-flg-grid">
              <label class="pc-flg-c"><input type="checkbox" v-model="form.receives_tier1"/><span>IHR Tier 1</span></label>
              <label class="pc-flg-c"><input type="checkbox" v-model="form.receives_tier2"/><span>IHR Tier 2</span></label>
              <label class="pc-flg-c"><input type="checkbox" v-model="form.receives_breach_alerts"/><span>7-1-7 breaches</span></label>
              <label class="pc-flg-c"><input type="checkbox" v-model="form.receives_followup_reminders"/><span>Follow-ups</span></label>
              <label class="pc-flg-c"><input type="checkbox" v-model="form.receives_daily_report"/><span>Daily report</span></label>
              <label class="pc-flg-c"><input type="checkbox" v-model="form.receives_weekly_report"/><span>Weekly report</span></label>
            </div>
            <label class="pc-flbl">Notes</label>
            <textarea v-model.trim="form.notes" class="pc-inp" rows="2"/>
          </section>

          <div v-if="formErr" class="pc-err">{{ formErr }}</div>
          <div class="pc-mact">
            <button class="pc-btn pc-btn--ghost" @click="showForm = false">Cancel</button>
            <button class="pc-btn pc-btn--primary" :disabled="saving" @click="saveForm">{{ saving ? 'Saving…' : 'Save contact' }}</button>
          </div>
        </div>
        <div style="height:48px"/>
      </IonContent>
    </IonModal>

    <IonToast :is-open="toast.show" :message="toast.msg" :color="toast.color" :duration="2800" position="top" @didDismiss="toast.show = false"/>
  </IonPage>
</template>

<script setup>
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonBackButton, IonButton,
  IonIcon, IonModal, IonToast, onIonViewDidEnter,
} from '@ionic/vue'
import {
  refreshOutline, addOutline, searchOutline, menuOutline,
  mailOutline, callOutline, arrowForwardOutline, lockClosedOutline,
} from 'ionicons/icons'
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'

const LEVELS = ['POE', 'DISTRICT', 'PHEOC', 'NATIONAL', 'WHO']

function getAuth() { return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {} }
const auth = ref(getAuth())
const isAdmin = computed(() => ['NATIONAL_ADMIN', 'PHEOC_OFFICER', 'DISTRICT_SUPERVISOR', 'POE_ADMIN'].includes(auth.value?.role_key || ''))

// ── Responsive sidebar / drawer ─────────────────────────────────────────────
const viewport = ref({ w: typeof window !== 'undefined' ? window.innerWidth : 1024 })
const isMobile = computed(() => viewport.value.w < 900)
const drawerOpen = ref(false)
function onResize() { viewport.value = { w: window.innerWidth } }

// ── State ───────────────────────────────────────────────────────────────────
const contacts = ref([])
const summary  = ref({ total: 0, editable_here: 0, by_country: {}, by_district: {}, by_poe: {}, by_level: {} })
const loading  = ref(false)
const saving   = ref(false)
const searchQ  = ref('')
const levelFilter = ref(null)
const subFilter   = ref(null)       // tier1 | tier2 | critical | breach | daily | weekly | null
const activeScope = reactive({ country: null, district: null, poe: null })
const toast = reactive({ show: false, msg: '', color: 'success' })
const showForm = ref(false)
const formErr  = ref('')
const form = reactive(emptyForm())

function emptyForm() {
  return {
    id: null, poe_code: '', level: 'DISTRICT', priority_order: 1,
    full_name: '', position: '', organisation: '',
    phone: '', alternate_phone: '', email: '', alternate_email: '',
    preferred_channel: 'EMAIL', escalates_to_contact_id: null,
    receives_critical: true, receives_high: true, receives_medium: false, receives_low: false,
    receives_tier1: true, receives_tier2: true, receives_breach_alerts: true,
    receives_followup_reminders: true, receives_daily_report: false, receives_weekly_report: false,
    notes: '',
  }
}

// ── Computeds ───────────────────────────────────────────────────────────────
const distinctCountries = computed(() => Array.from(new Set(contacts.value.map(c => c.country_code).filter(Boolean))))
const distinctPOEs      = computed(() => Array.from(new Set(contacts.value.map(c => c.poe_code).filter(Boolean))))

const scopeLabel = computed(() => {
  const a = auth.value
  if (!a) return '—'
  if (a.role_key === 'NATIONAL_ADMIN') return `${a.country_code || 'Country'} — full authority`
  if (a.poe_code) return `${a.country_code} · ${a.district_code} · ${a.poe_code}`
  if (a.district_code) return `${a.country_code} · ${a.district_code}`
  return a.country_code || '—'
})

// Country → District → POE tree, with per-node counts
const tree = computed(() => {
  const byC = new Map()
  for (const c of contacts.value) {
    const cc = c.country_code || ''
    const dc = c.district_code || ''
    const pc = c.poe_code || ''
    if (!byC.has(cc)) byC.set(cc, { code: cc, n: 0, districts: new Map() })
    const country = byC.get(cc)
    country.n++
    if (!country.districts.has(dc)) country.districts.set(dc, { code: dc, n: 0, poes: new Map() })
    const district = country.districts.get(dc)
    district.n++
    if (!district.poes.has(pc)) district.poes.set(pc, { code: pc, n: 0 })
    district.poes.get(pc).n++
  }
  return Array.from(byC.values()).map(c => ({
    ...c,
    districts: Array.from(c.districts.values()).map(d => ({ ...d, poes: Array.from(d.poes.values()).sort((a, b) => a.code.localeCompare(b.code)) })).sort((a, b) => a.code.localeCompare(b.code)),
  })).sort((a, b) => a.code.localeCompare(b.code))
})

const activeFilters = computed(() => {
  const out = []
  if (searchQ.value) out.push({ k: 'search', label: `Search "${searchQ.value}"` })
  if (levelFilter.value) out.push({ k: 'level', label: `Level: ${levelFilter.value}` })
  if (subFilter.value)   out.push({ k: 'sub',   label: `Sub: ${subFilter.value}` })
  if (activeScope.country)  out.push({ k: 'country',  label: `🌍 ${activeScope.country}` })
  if (activeScope.district) out.push({ k: 'district', label: `🏛 ${activeScope.district}` })
  if (activeScope.poe)      out.push({ k: 'poe',      label: `📍 ${activeScope.poe}` })
  return out
})
const hasAnyFilter = computed(() => activeFilters.value.length > 0)
const canEditInCurrentScope = computed(() => {
  // If any filter narrows to a specific POE/district/country — is the admin authorised there?
  if (activeScope.poe)      return contacts.value.some(c => c.poe_code === activeScope.poe && c.can_edit_by_me)
  if (activeScope.district) return contacts.value.some(c => c.district_code === activeScope.district && c.can_edit_by_me)
  if (activeScope.country)  return contacts.value.some(c => c.country_code === activeScope.country && c.can_edit_by_me)
  return summary.value.editable_here > 0 || isAdmin.value
})

const availableEscalations = computed(() => contacts.value.filter(c => c.id !== form.id))

const visibleGroups = computed(() => {
  // Filter
  const q = searchQ.value.trim().toLowerCase()
  let src = contacts.value
  if (q) {
    src = src.filter(c =>
      (c.full_name || '').toLowerCase().includes(q) ||
      (c.email || '').toLowerCase().includes(q) ||
      (c.phone || '').toLowerCase().includes(q) ||
      (c.position || '').toLowerCase().includes(q) ||
      (c.organisation || '').toLowerCase().includes(q),
    )
  }
  if (levelFilter.value) src = src.filter(c => c.level === levelFilter.value)
  if (subFilter.value) {
    const key = {
      tier1: 'receives_tier1', tier2: 'receives_tier2', critical: 'receives_critical',
      breach: 'receives_breach_alerts', daily: 'receives_daily_report', weekly: 'receives_weekly_report',
    }[subFilter.value]
    if (key) src = src.filter(c => c[key] == 1)
  }
  if (activeScope.country)  src = src.filter(c => c.country_code === activeScope.country)
  if (activeScope.district) src = src.filter(c => c.district_code === activeScope.district)
  if (activeScope.poe)      src = src.filter(c => c.poe_code === activeScope.poe)

  // Group by (poe_code, level)
  const by = new Map()
  for (const c of src) {
    const key = `${c.poe_code}|${c.level}`
    if (!by.has(key)) by.set(key, { key, poe_code: c.poe_code, district_code: c.district_code, level: c.level, items: [] })
    by.get(key).items.push(c)
  }
  return Array.from(by.values()).sort((a, b) => {
    if (a.poe_code !== b.poe_code) return (a.poe_code || '').localeCompare(b.poe_code || '')
    return LEVELS.indexOf(a.level) - LEVELS.indexOf(b.level)
  })
})

function escalateName(id) {
  const c = contacts.value.find(x => x.id === id)
  return c ? `${c.full_name} (${c.level})` : `#${id}`
}

// Deterministic gradient avatar based on name hash
function avatarText(name) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  return (parts[0]?.[0] || '?').toUpperCase() + (parts[1]?.[0] || parts[0]?.[1] || '').toUpperCase()
}
function avatarBg(name) {
  if (!name) return 'linear-gradient(135deg,#94A3B8,#64748B)'
  let h = 0
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) & 0xFFFFFF
  const hue = h % 360
  return `linear-gradient(135deg, hsl(${hue}, 60%, 55%), hsl(${(hue + 40) % 360}, 70%, 40%))`
}
function roleInitials(role) {
  if (!role) return '?'
  const parts = role.split('_')
  return (parts[0]?.[0] || '?') + (parts[1]?.[0] || parts[0]?.[1] || '')
}

// ── Filter helpers ─────────────────────────────────────────────────────────
function toggleLevel(l) { levelFilter.value = levelFilter.value === l ? null : l }
function toggleSub(s)   { subFilter.value   = subFilter.value   === s ? null : s }
function selectCountry(c)  { activeScope.country = activeScope.country === c ? null : c; activeScope.district = null; activeScope.poe = null; if (isMobile.value) drawerOpen.value = false }
function selectDistrict(cc, d) { activeScope.country = cc; activeScope.district = activeScope.district === d ? null : d; activeScope.poe = null; if (isMobile.value) drawerOpen.value = false }
function selectPoe(cc, dc, p)  { activeScope.country = cc; activeScope.district = dc; activeScope.poe = activeScope.poe === p ? null : p; if (isMobile.value) drawerOpen.value = false }
function clearScope() { activeScope.country = null; activeScope.district = null; activeScope.poe = null }
function clearFilter(k) {
  if (k === 'search')   searchQ.value = ''
  else if (k === 'level')    levelFilter.value = null
  else if (k === 'sub')      subFilter.value = null
  else if (k === 'country')  { activeScope.country = null; activeScope.district = null; activeScope.poe = null }
  else if (k === 'district') { activeScope.district = null; activeScope.poe = null }
  else if (k === 'poe')      { activeScope.poe = null }
}
function clearAllFilters() {
  searchQ.value = ''; levelFilter.value = null; subFilter.value = null; clearScope()
}

// ── API ────────────────────────────────────────────────────────────────────
async function api(path, opts = {}) {
  const uid = auth.value?.id
  if (!uid) return null
  const sep = path.includes('?') ? '&' : '?'
  const url = `${window.SERVER_URL}${path}${sep}user_id=${uid}`
  try {
    const res = await fetch(url, {
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      ...opts,
    })
    const body = await res.json().catch(() => null)
    return { ok: res.ok, status: res.status, body }
  } catch (e) { return { ok: false, status: 0, error: e?.message } }
}

async function load() {
  loading.value = true
  try {
    const res = await api('/poe-contacts')
    if (res?.ok && res.body?.success) {
      contacts.value = res.body.data || []
      // meta present since backend v2
      const m = res.body.meta || {}
      summary.value = {
        total: m.total || contacts.value.length,
        editable_here: m.editable_here ?? contacts.value.filter(c => c.can_edit_by_me).length,
        by_country: m.by_country || {},
        by_district: m.by_district || {},
        by_poe: m.by_poe || {},
        by_level: m.by_level || {},
      }
    } else {
      showToast(res?.body?.message || 'Failed to load contacts', 'danger')
    }
  } finally { loading.value = false }
}

function openAdd() {
  const a = auth.value
  // Pre-populate with the admin's own scope when it makes sense
  Object.assign(form, emptyForm(), {
    poe_code: activeScope.poe || a?.poe_code || '',
    level: activeScope.poe ? 'POE' : (a?.role_key === 'POE_ADMIN' ? 'POE' : 'DISTRICT'),
  })
  formErr.value = ''
  showForm.value = true
}
function openEdit(c) {
  Object.assign(form, emptyForm(), c, {
    receives_critical:  c.receives_critical == 1,
    receives_high:      c.receives_high == 1,
    receives_medium:    c.receives_medium == 1,
    receives_low:       c.receives_low == 1,
    receives_tier1:     c.receives_tier1 == 1,
    receives_tier2:     c.receives_tier2 == 1,
    receives_breach_alerts:      c.receives_breach_alerts == 1,
    receives_followup_reminders: c.receives_followup_reminders == 1,
    receives_daily_report:       c.receives_daily_report == 1,
    receives_weekly_report:      c.receives_weekly_report == 1,
  })
  formErr.value = ''
  showForm.value = true
}

async function saveForm() {
  formErr.value = ''
  if (!form.poe_code)   return (formErr.value = 'POE code is required.')
  if (!form.level)      return (formErr.value = 'Level is required.')
  if (!form.full_name)  return (formErr.value = 'Full name is required.')
  if (!form.email && !form.phone) return (formErr.value = 'Provide at least one of email or phone.')

  saving.value = true
  try {
    const body = {
      user_id: auth.value?.id,
      poe_code: form.poe_code,
      district_code: auth.value?.district_code || '',
      country_code: auth.value?.country_code || 'UG',
      level: form.level,
      priority_order: form.priority_order || 1,
      full_name: form.full_name,
      position: form.position, organisation: form.organisation,
      phone: form.phone, alternate_phone: form.alternate_phone,
      email: form.email, alternate_email: form.alternate_email,
      preferred_channel: form.preferred_channel,
      escalates_to_contact_id: form.escalates_to_contact_id,
      receives_critical: form.receives_critical, receives_high: form.receives_high,
      receives_medium: form.receives_medium, receives_low: form.receives_low,
      receives_tier1: form.receives_tier1, receives_tier2: form.receives_tier2,
      receives_breach_alerts: form.receives_breach_alerts,
      receives_followup_reminders: form.receives_followup_reminders,
      receives_daily_report: form.receives_daily_report,
      receives_weekly_report: form.receives_weekly_report,
      notes: form.notes, is_active: true,
    }
    const res = form.id
      ? await api(`/poe-contacts/${form.id}`, { method: 'PATCH', body: JSON.stringify(body) })
      : await api('/poe-contacts',              { method: 'POST',  body: JSON.stringify(body) })
    if (res?.ok && res.body?.success) {
      showForm.value = false
      await load()
      showToast('Saved', 'success')
    } else {
      formErr.value = res?.body?.message || 'Save failed'
    }
  } finally { saving.value = false }
}

async function toggle(c, active) {
  const res = await api(`/poe-contacts/${c.id}`, { method: 'PATCH', body: JSON.stringify({ user_id: auth.value?.id, is_active: active }) })
  if (res?.ok && res.body?.success) { c.is_active = active ? 1 : 0; showToast(active ? 'Activated' : 'Deactivated', 'success') }
  else showToast(res?.body?.message || 'Toggle failed', 'danger')
}

async function remove(c) {
  if (!confirm(`Delete contact "${c.full_name}"?`)) return
  const res = await api(`/poe-contacts/${c.id}`, { method: 'DELETE', body: JSON.stringify({ user_id: auth.value?.id }) })
  if (res?.ok && res.body?.success) {
    contacts.value = contacts.value.filter(x => x.id !== c.id)
    summary.value.total = Math.max(0, summary.value.total - 1)
    showToast('Deleted', 'success')
  } else {
    showToast(res?.body?.message || 'Delete failed', 'danger')
  }
}

function showToast(msg, color = 'success') { toast.show = true; toast.msg = msg; toast.color = color }

onMounted(() => { onResize(); window.addEventListener('resize', onResize); load() })
onUnmounted(() => { window.removeEventListener('resize', onResize) })
onIonViewDidEnter(() => { auth.value = getAuth(); load() })
</script>

<style scoped>
* { box-sizing: border-box }

/* ═══ HEADER ═══════════════════════════════════════════════════════════ */
.pc-hdr { --background: transparent; border: none }
.pc-hdr-bg {
  background: linear-gradient(135deg, #0A1F3C 0%, #001D3D 35%, #003566 70%, #003F88 100%);
  padding: 0 0 14px;
  position: relative;
  overflow: hidden;
}
.pc-hdr-bg::before {
  content: ''; position: absolute; inset: 0;
  background:
    radial-gradient(1200px 280px at 80% -20%, rgba(96,165,250,.22), transparent 60%),
    radial-gradient(900px 240px at 10% 110%, rgba(139,92,246,.18), transparent 60%);
  pointer-events: none;
}
.pc-hdr-top { position: relative; display: flex; align-items: center; gap: 4px; padding: 8px 10px 0 }
.pc-back { --color: rgba(255,255,255,.85) }
.pc-hdr-burger { width: 32px; height: 32px; border-radius: 8px; border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.06); color: #fff; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 17px }
.pc-hdr-title { flex: 1; display: flex; flex-direction: column; min-width: 0 }
.pc-hdr-eye { font-size: 9px; font-weight: 800; color: rgba(255,255,255,.55); text-transform: uppercase; letter-spacing: 1.6px }
.pc-hdr-h1 { font-size: 19px; font-weight: 800; color: #fff; letter-spacing: -.3px }
.pc-hdr-btn { width: 32px; height: 32px; border-radius: 50%; border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.06); color: rgba(255,255,255,.85); display: inline-flex; align-items: center; justify-content: center; margin-right: 4px; cursor: pointer; font-size: 16px; transition: background .15s }
.pc-hdr-btn:active { transform: scale(.94) }
.pc-hdr-btn--primary { background: linear-gradient(135deg, #3B82F6, #1E40AF); border-color: rgba(96,165,250,.3); box-shadow: 0 4px 14px rgba(30,64,175,.45) }
.pc-hdr-btn--primary:hover { filter: brightness(1.08) }
.pc-hdr-btn:disabled { opacity: .4 }
.pc-spin { animation: pc-rotate 1s linear infinite }
@keyframes pc-rotate { to { transform: rotate(360deg) } }

/* KPI strip */
.pc-kpis { position: relative; display: flex; gap: 6px; padding: 10px 10px 0 }
.pc-kpi {
  flex: 1; min-width: 0; padding: 10px 8px; border-radius: 12px;
  background: rgba(255,255,255,.06); backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,.1);
  display: flex; flex-direction: column; gap: 2px; align-items: center;
  transition: background .2s, transform .15s;
}
.pc-kpi:hover { background: rgba(255,255,255,.1); transform: translateY(-1px) }
.pc-kpi--editable { background: linear-gradient(135deg, rgba(16,185,129,.18), rgba(5,150,105,.1)); border-color: rgba(110,231,183,.3) }
.pc-kpi-n { font-size: 19px; font-weight: 900; color: #fff; line-height: 1; font-variant-numeric: tabular-nums }
.pc-kpi--editable .pc-kpi-n { color: #6EE7B7 }
.pc-kpi-l { font-size: 9.5px; font-weight: 700; color: rgba(255,255,255,.6); text-transform: uppercase; letter-spacing: .3px; text-align: center }

/* ═══ CONTENT / LAYOUT ═════════════════════════════════════════════════ */
.pc-content { --background: #F0F4FA }
.pc-layout { display: flex; min-height: 100%; position: relative }

/* Sidebar */
.pc-side {
  width: 280px; flex-shrink: 0;
  background: linear-gradient(180deg, #0F172A 0%, #1E293B 100%);
  color: #E2E8F0;
  position: sticky; top: 0;
  height: calc(100vh - 64px);
  overflow: hidden;
  border-right: 1px solid rgba(148,163,184,.18);
  box-shadow: inset -1px 0 0 rgba(255,255,255,.03);
}
.pc-side-scroll { height: 100%; overflow-y: auto; padding: 16px 12px 24px }
.pc-side-scroll::-webkit-scrollbar { width: 6px }
.pc-side-scroll::-webkit-scrollbar-thumb { background: rgba(148,163,184,.2); border-radius: 3px }
.pc-side-close {
  position: absolute; top: 10px; right: 12px;
  width: 28px; height: 28px; border-radius: 50%;
  background: rgba(255,255,255,.08); border: none; color: #fff;
  font-size: 20px; line-height: 1; cursor: pointer; z-index: 2;
}

/* Me badge */
.pc-me {
  display: flex; gap: 10px; align-items: center;
  padding: 12px; margin-bottom: 16px;
  background: linear-gradient(135deg, rgba(59,130,246,.22), rgba(139,92,246,.15));
  border: 1px solid rgba(147,197,253,.2);
  border-radius: 12px;
}
.pc-me-avatar {
  width: 40px; height: 40px; border-radius: 50%;
  background: linear-gradient(135deg, #3B82F6, #1E40AF);
  color: #fff; font-size: 13px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 14px rgba(30,64,175,.4);
}
.pc-me-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px }
.pc-me-role { font-size: 10.5px; font-weight: 900; color: #BFDBFE; letter-spacing: .4px }
.pc-me-scope { font-size: 10px; color: rgba(226,232,240,.7); font-weight: 600; line-height: 1.3; word-break: break-word }

/* Search */
.pc-search {
  position: relative; margin-bottom: 14px;
}
.pc-search-ic {
  position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
  color: rgba(226,232,240,.5); font-size: 15px;
}
.pc-search-inp {
  width: 100%; padding: 9px 32px 9px 32px;
  background: rgba(15,23,42,.6); border: 1px solid rgba(148,163,184,.15);
  border-radius: 8px; color: #E2E8F0; font-size: 12.5px; font-family: inherit; outline: none;
  transition: border-color .15s, background .15s;
}
.pc-search-inp::placeholder { color: rgba(226,232,240,.4) }
.pc-search-inp:focus { border-color: #60A5FA; background: rgba(15,23,42,.9) }
.pc-search-x { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; border-radius: 50%; background: rgba(226,232,240,.12); border: none; color: #E2E8F0; font-size: 13px; line-height: 1; cursor: pointer }

/* Sections */
.pc-side-sec { margin-bottom: 18px }
.pc-side-h {
  display: flex; justify-content: space-between; align-items: center;
  font-size: 9.5px; font-weight: 900; letter-spacing: 1.5px;
  color: rgba(148,163,184,.8); text-transform: uppercase;
  padding: 0 4px; margin-bottom: 8px;
}
.pc-side-clear {
  font-size: 9.5px; color: #60A5FA; background: none; border: none; cursor: pointer; font-weight: 700; letter-spacing: .3px;
}

/* Levels */
.pc-lvls { display: flex; flex-direction: column; gap: 3px }
.pc-lvl {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 10px; background: transparent; border: none; border-radius: 8px;
  color: rgba(226,232,240,.75); cursor: pointer; font-size: 12px; font-weight: 600;
  text-align: left; transition: background .15s, color .15s;
  position: relative;
}
.pc-lvl:hover { background: rgba(148,163,184,.08); color: #F1F5F9 }
.pc-lvl--on {
  background: linear-gradient(90deg, rgba(59,130,246,.22), rgba(59,130,246,.08));
  color: #fff; font-weight: 800;
  box-shadow: inset 3px 0 0 #60A5FA;
}
.pc-lvl-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0 }
.pc-lvl-dot--all  { background: #94A3B8 }
.pc-lvl-dot--poe  { background: #3B82F6 }
.pc-lvl-dot--district { background: #10B981 }
.pc-lvl-dot--pheoc { background: #EC4899 }
.pc-lvl-dot--national { background: #9333EA }
.pc-lvl-dot--who { background: #F59E0B }
.pc-lvl-l { flex: 1 }
.pc-lvl-n { font-size: 10.5px; font-weight: 800; padding: 1px 7px; border-radius: 10px; background: rgba(148,163,184,.15); color: rgba(226,232,240,.7); font-variant-numeric: tabular-nums }
.pc-lvl--on .pc-lvl-n { background: rgba(96,165,250,.2); color: #BFDBFE }

/* Tree */
.pc-tree { display: flex; flex-direction: column; gap: 1px }
.pc-tree-l1, .pc-tree-l2, .pc-tree-l3 {
  display: flex; align-items: center; gap: 8px;
  padding: 7px 10px; border-radius: 6px; cursor: pointer;
  font-size: 11.5px; color: rgba(226,232,240,.8);
  transition: background .12s, color .12s;
}
.pc-tree-l2 { padding-left: 22px; font-size: 11px; color: rgba(226,232,240,.7) }
.pc-tree-l3 { padding-left: 34px; font-size: 10.5px; color: rgba(226,232,240,.6); font-family: ui-monospace, Menlo, monospace }
.pc-tree-l1:hover, .pc-tree-l2:hover, .pc-tree-l3:hover { background: rgba(148,163,184,.08); color: #F1F5F9 }
.pc-tree--on { background: rgba(96,165,250,.18) !important; color: #BFDBFE !important; font-weight: 700 }
.pc-tree-icon { font-size: 13px; flex-shrink: 0 }
.pc-tree-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap }
.pc-tree-count { font-size: 9.5px; padding: 1px 6px; border-radius: 8px; background: rgba(148,163,184,.15); color: rgba(226,232,240,.65); font-variant-numeric: tabular-nums; font-weight: 700 }

/* Quick sub filters */
.pc-quicks { display: flex; flex-wrap: wrap; gap: 4px }
.pc-quick {
  padding: 4px 10px; border-radius: 99px; border: 1px solid rgba(148,163,184,.18);
  background: transparent; color: rgba(226,232,240,.75); font-size: 10.5px; font-weight: 700; cursor: pointer;
  transition: all .15s;
}
.pc-quick:hover { border-color: #60A5FA; color: #BFDBFE }
.pc-quick--on { background: linear-gradient(135deg, #3B82F6, #1E40AF); border-color: transparent; color: #fff; box-shadow: 0 3px 8px rgba(30,64,175,.4) }

/* Legend */
.pc-side-sec--legend { margin-top: auto }
.pc-legend { display: flex; flex-direction: column; gap: 4px }
.pc-legend-row { display: flex; align-items: center; gap: 8px; font-size: 10px; color: rgba(226,232,240,.6); font-weight: 600 }
.pc-legend-sw { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0 }
.pc-legend-sw--own { background: #10B981; box-shadow: 0 0 6px #10B981 }
.pc-legend-sw--other { background: rgba(148,163,184,.6); border: 1px solid rgba(226,232,240,.25) }

/* ═══ MAIN ═════════════════════════════════════════════════════════════ */
.pc-main { flex: 1; min-width: 0; padding: 14px 14px 40px }
.pc-crumbs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px }
.pc-crumb {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 10px; border-radius: 99px;
  background: #fff; border: 1px solid #CBD5E1; color: #1E40AF;
  font-size: 11px; font-weight: 700; cursor: pointer;
}
.pc-crumb-x { color: #94A3B8; font-size: 13px; line-height: 1 }
.pc-crumbs-clear { font-size: 10.5px; color: #DC2626; background: none; border: none; cursor: pointer; font-weight: 700; padding: 0 6px }

.pc-loading { display: flex; flex-direction: column; gap: 8px }
.pc-skel { display: flex; gap: 10px; padding: 14px; background: #fff; border: 1px solid #E8EDF5; border-radius: 12px }
.pc-skel-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(90deg, #E2E8F0 25%, #F1F5F9 50%, #E2E8F0 75%); background-size: 200% 100%; animation: pc-sh 1.4s linear infinite }
.pc-skel-body { flex: 1; display: flex; flex-direction: column; gap: 6px; justify-content: center }
.pc-skel-line { height: 10px; border-radius: 3px; background: linear-gradient(90deg, #E2E8F0 25%, #F1F5F9 50%, #E2E8F0 75%); background-size: 200% 100%; animation: pc-sh 1.4s linear infinite }
.pc-skel-line--t { width: 60% }
.pc-skel-line--s { width: 30% }
@keyframes pc-sh { 0% { background-position: 200% 0 } 100% { background-position: -200% 0 } }

.pc-empty { padding: 60px 20px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px }
.pc-empty-ic { color: #CBD5E1 }
.pc-empty-t { font-size: 17px; font-weight: 800; color: #1A3A5C }
.pc-empty-s { font-size: 12px; color: #64748B; max-width: 300px; line-height: 1.5 }
.pc-empty-btn { margin-top: 6px; padding: 10px 18px; border-radius: 10px; background: #1E40AF; color: #fff; border: none; font-size: 12.5px; font-weight: 800; cursor: pointer }

/* Groups */
.pc-grp { margin-bottom: 14px }
.pc-grp-h {
  display: flex; justify-content: space-between; align-items: center;
  padding: 10px 14px; margin-bottom: 6px;
  background: linear-gradient(135deg, #1A3A5C, #003566); color: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 14px rgba(0,29,61,.18);
}
.pc-grp-meta { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0 }
.pc-grp-pill { font-size: 9.5px; font-weight: 900; padding: 3px 9px; border-radius: 4px; background: rgba(255,255,255,.15); letter-spacing: .4px; flex-shrink: 0 }
.pc-grp-pill--poe { background: #3B82F6 }
.pc-grp-pill--district { background: #10B981 }
.pc-grp-pill--pheoc { background: #EC4899 }
.pc-grp-pill--national { background: #9333EA }
.pc-grp-pill--who { background: #F59E0B }
.pc-grp-poe { font-size: 13px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
.pc-grp-district { font-size: 10.5px; color: rgba(255,255,255,.65); font-weight: 700 }
.pc-grp-n { font-size: 11px; font-weight: 800; padding: 2px 9px; border-radius: 10px; background: rgba(255,255,255,.15); flex-shrink: 0 }

/* Cards */
.pc-c {
  display: flex; gap: 12px; padding: 14px;
  background: #fff; border: 1px solid #E8EDF5; border-radius: 12px;
  margin-bottom: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,.04);
  opacity: 0; animation: pc-card-in .35s ease-out forwards;
  transition: border-color .15s, box-shadow .15s;
}
@keyframes pc-card-in { from { opacity: 0; transform: translateY(6px) } to { opacity: 1; transform: translateY(0) } }
.pc-c:hover { border-color: #CBD5E1; box-shadow: 0 4px 12px rgba(0,0,0,.06) }
.pc-c--off { opacity: .55; background: #F8FAFC }
.pc-c--mine { border-left: 3px solid #10B981 }

.pc-c-avatar {
  width: 44px; height: 44px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 14px; font-weight: 800;
  flex-shrink: 0; position: relative;
  box-shadow: 0 2px 8px rgba(0,0,0,.12);
}
.pc-c-lock {
  position: absolute; bottom: -4px; right: -4px;
  width: 20px; height: 20px; border-radius: 50%;
  background: #64748B; color: #fff;
  display: flex; align-items: center; justify-content: center; font-size: 10px;
  box-shadow: 0 2px 6px rgba(0,0,0,.2);
}

.pc-c-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px }
.pc-c-top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap }
.pc-c-name { font-size: 14px; font-weight: 800; color: #1A3A5C; margin: 0; flex: 1; min-width: 0 }
.pc-c-prio { font-size: 9.5px; font-weight: 800; padding: 2px 7px; border-radius: 4px; background: #FEF3C7; color: #854D0E }
.pc-c-chan { font-size: 9.5px; font-weight: 800; padding: 2px 7px; border-radius: 4px; letter-spacing: .3px }
.pc-c-chan--email { background: #DBEAFE; color: #1E40AF }
.pc-c-chan--sms { background: #FCE7F3; color: #9D174D }
.pc-c-chan--both { background: #EDE9FE; color: #6D28D9 }

.pc-c-pos { font-size: 11px; color: #64748B; font-weight: 600 }

.pc-c-rows { display: flex; flex-direction: column; gap: 3px }
.pc-c-row {
  display: inline-flex; align-items: center; gap: 8px;
  font-size: 11.5px; color: #475569; font-weight: 600;
  text-decoration: none;
}
.pc-c-row ion-icon { color: #1E40AF; font-size: 13px; flex-shrink: 0 }
a.pc-c-row:hover { color: #1E40AF }
.pc-c-row--esc ion-icon { color: #9333EA }
.pc-c-row strong { color: #1A3A5C; font-weight: 700 }

.pc-c-flags { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 2px }
.pc-flag { font-size: 9px; font-weight: 800; padding: 2px 7px; border-radius: 4px; background: #F1F5F9; color: #475569; letter-spacing: .3px }
.pc-flag--c  { background: #FEE2E2; color: #991B1B }
.pc-flag--h  { background: #FFEDD5; color: #9A3412 }
.pc-flag--t1 { background: #F3E8FF; color: #6B21A8 }
.pc-flag--t2 { background: #FEF2F2; color: #DC2626 }
.pc-flag--b  { background: #7F1D1D; color: #FEE2E2 }
.pc-flag--d  { background: #D1FAE5; color: #047857 }

.pc-c-actions { display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap }
.pc-btn { padding: 6px 12px; border-radius: 7px; font-size: 11px; font-weight: 800; cursor: pointer; border: none; transition: transform .1s }
.pc-btn:active { transform: scale(.96) }
.pc-btn--ghost { background: #fff; color: #475569; border: 1px solid #CBD5E1 }
.pc-btn--primary { background: linear-gradient(135deg, #3B82F6, #1E40AF); color: #fff; box-shadow: 0 3px 10px rgba(30,64,175,.35) }
.pc-btn--danger { background: #fff; color: #DC2626; border: 1px solid #FECACA; margin-left: auto }

.pc-c-readonly { display: inline-flex; align-items: center; gap: 6px; margin-top: 4px; padding: 5px 10px; background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 6px; font-size: 10.5px; color: #64748B; font-weight: 600 }
.pc-c-readonly ion-icon { font-size: 12px }

/* Mobile drawer */
.pc-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,.55); backdrop-filter: blur(2px); z-index: 49; animation: pc-fade .2s }
@keyframes pc-fade { from { opacity: 0 } to { opacity: 1 } }

@media (max-width: 899px) {
  .pc-side {
    position: fixed; top: 0; bottom: 0; left: 0; width: 88%; max-width: 320px;
    height: 100vh; transform: translateX(-100%);
    transition: transform .3s ease-out;
    z-index: 50; box-shadow: 6px 0 30px rgba(0,0,0,.35);
  }
  .pc-side--open { transform: translateX(0) }
  .pc-main { padding: 14px 10px 40px }
}
@media (min-width: 900px) {
  .pc-hdr-burger { display: none }
  .pc-side-close { display: none }
  .pc-main { padding: 18px 20px 40px }
}

/* ═══ MODAL ═══════════════════════════════════════════════════════════ */
.pc-mtbar { --background: #fff; --color: #1A3A5C; --border-color: #E8EDF5 }
.pc-mtbar-t { font-size: 15px; font-weight: 800 }
.pc-modal { --background: #F8FAFC }
.pc-mp { padding: 14px 14px 0 }
.pc-m-sec { background: #fff; border: 1px solid #E8EDF5; border-radius: 12px; padding: 14px; margin-bottom: 10px }
.pc-m-h { display: flex; align-items: center; gap: 8px; margin-bottom: 10px }
.pc-m-n { width: 22px; height: 22px; border-radius: 50%; background: #1E40AF; color: #fff; font-size: 11px; font-weight: 900; display: inline-flex; align-items: center; justify-content: center }
.pc-m-t { font-size: 13px; font-weight: 800; color: #1A3A5C }
.pc-flbl { display: block; font-size: 10px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: .4px; margin: 10px 0 4px }
.pc-sub-h { display: block; font-size: 10.5px; font-weight: 800; color: #1A3A5C; margin: 10px 0 6px }
.pc-inp { width: 100%; padding: 9px 11px; border: 1.5px solid #E8EDF5; border-radius: 8px; font-size: 12.5px; color: #1A3A5C; background: #fff; font-family: inherit; outline: none; transition: border-color .15s, box-shadow .15s }
.pc-inp:focus { border-color: #1E40AF; box-shadow: 0 0 0 3px rgba(30,64,175,.12) }
.pc-inp:disabled { background: #F8FAFC; color: #94A3B8 }
.pc-frow { display: flex; gap: 10px }
.pc-fhalf { flex: 1; min-width: 0 }
.pc-flg-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px }
.pc-flg-c { display: flex; align-items: center; gap: 6px; padding: 7px 10px; background: #F8FAFC; border: 1px solid #E8EDF5; border-radius: 7px; font-size: 11.5px; color: #1A3A5C; font-weight: 600; cursor: pointer }
.pc-flg-c:has(input:checked) { background: #EFF6FF; border-color: #BFDBFE; color: #1E40AF }
.pc-flg-c input { width: 15px; height: 15px; cursor: pointer; accent-color: #1E40AF }
.pc-err { margin: 8px 0; padding: 8px 10px; background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; border-radius: 6px; font-size: 11.5px; font-weight: 600 }
.pc-mact { display: flex; gap: 8px; margin: 14px 0 }
.pc-mact .pc-btn { flex: 1; padding: 12px; font-size: 13px }
</style>

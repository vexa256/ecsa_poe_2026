<template>
  <IonPage class="nq-page">

    <!-- ═══════════════════════════════════════════════════════════════════
         COMMAND HEADER — dark, data-dense, futuristic scan-line effect
    ══════════════════════════════════════════════════════════════════════ -->
    <IonHeader class="nq-header" :translucent="false">
      <div class="nq-hdr-scanlines" aria-hidden="true" />
      <div class="nq-hdr-glow" aria-hidden="true" />

      <!-- ── TOP BAR ──────────────────────────────────────────────────── -->
      <div class="nq-topbar">
        <IonButtons>
          <IonMenuButton menu="app-menu" class="nq-menu-btn" />
        </IonButtons>

        <div class="nq-title-block">
          <span class="nq-eyebrow">IHR Art.23 · POE Sentinel</span>
          <span class="nq-title">Referral Command</span>
        </div>

        <div class="nq-topbar-right">
          <div :class="['nq-conn', isOnline ? 'nq-conn--on' : 'nq-conn--off']">
            <span class="nq-conn-dot" />
            <span class="nq-conn-txt">{{ isOnline ? 'Live' : 'Offline' }}</span>
          </div>
          <button class="nq-icon-btn" @click="manualRefresh" :disabled="loading || syncing"
            aria-label="Refresh">
            <svg viewBox="0 0 18 18" fill="none" :stroke="syncing ? '#00D4AA' : 'rgba(255,255,255,0.6)'"
              stroke-width="2" stroke-linecap="round" :class="(loading || syncing) && 'nq-spin'">
              <path d="M16 3v5h-5M2 15v-5h5M2.5 9A7 7 0 0115 6.7M15.5 9A7 7 0 013 11.3"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- ── STATS COMMAND GRID ────────────────────────────────────────── -->
      <div class="nq-stats-grid">
        <button class="nq-stat" :class="activeTab==='OPEN' && 'nq-stat--active'"
          @click="setTab('OPEN')">
          <span class="nq-stat-n nq-stat--open-n">{{ idbOpenCount }}</span>
          <span class="nq-stat-l">Open</span>
        </button>
        <div class="nq-stat-sep" />
        <button class="nq-stat" :class="activeTab==='OPEN' && 'nq-stat--active'"
          @click="setTab('OPEN')">
          <span class="nq-stat-n nq-stat--crit-n">{{ idbCritCount }}</span>
          <span class="nq-stat-l">Critical</span>
        </button>
        <div class="nq-stat-sep" />
        <button class="nq-stat" :class="activeTab==='IN_PROGRESS' && 'nq-stat--active'"
          @click="setTab('IN_PROGRESS')">
          <span class="nq-stat-n nq-stat--prog-n">{{ idbProgressCount }}</span>
          <span class="nq-stat-l">In Progress</span>
        </button>
        <div class="nq-stat-sep" />
        <button class="nq-stat" :class="activeTab==='CLOSED' && 'nq-stat--active'"
          @click="setTab('CLOSED')">
          <span class="nq-stat-n nq-stat--closed-n">{{ idbClosedCount }}</span>
          <span class="nq-stat-l">Closed</span>
        </button>
        <div class="nq-stat-sep" />
        <button class="nq-stat" :class="['nq-stat--damaged-btn', activeTab==='DAMAGED' && 'nq-stat--active']"
          @click="setTab('DAMAGED')">
          <span class="nq-stat-n nq-stat--dmg-n">{{ damagedItems.length }}</span>
          <span class="nq-stat-l">⚠ Damaged</span>
        </button>
      </div>

      <!-- ── TAB STRIP ─────────────────────────────────────────────────── -->
      <div class="nq-tabs" role="tablist">
        <button v-for="tab in TABS" :key="tab.v"
          :class="['nq-tab', activeTab===tab.v && 'nq-tab--on']"
          role="tab" :aria-selected="activeTab===tab.v"
          @click="setTab(tab.v)">
          {{ tab.label }}
          <span v-if="tabBadge(tab.v)" :class="['nq-tab-badge', `nq-tb--${tab.v.toLowerCase().replace('_','-')}`]">
            {{ tabBadge(tab.v) }}
          </span>
        </button>
      </div>
    </IonHeader>

    <!-- ═══════════════════════════════════════════════════════════════════
         OPERATIONS BOARD — dark glass, list of all referral records
    ══════════════════════════════════════════════════════════════════════ -->
    <IonContent class="nq-content" :fullscreen="true">
      <IonRefresher slot="fixed"
        @ionRefresh="ev => { manualRefresh(); ev.target.complete() }">
        <IonRefresherContent pulling-text="Pull to sync" refreshing-spinner="crescent" />
      </IonRefresher>

      <!-- Offline badge (non-blocking, compact) -->
      <div v-if="!isOnline" class="nq-offline-badge">
        <span class="nq-offline-dot" />
        Offline — showing {{ allItems.length.toLocaleString() }} cached records
      </div>

      <!-- Global skeleton (first load only) -->
      <div v-if="loading && !allItems.length" class="nq-skeleton-wrap">
        <div v-for="i in 5" :key="i" class="nq-sk-card" />
      </div>

      <div v-else class="nq-board">

        <!-- ── QUARANTINE ZONE (DAMAGED tab or inline banner) ─────────── -->
        <div v-if="activeTab === 'DAMAGED'" class="nq-quarantine-zone">
          <div class="nq-qz-header">
            <div class="nq-qz-icon-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="#FF6B35" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" width="20">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
            </div>
            <div class="nq-qz-text">
              <span class="nq-qz-title">Quarantine — Damaged Records</span>
              <span class="nq-qz-sub">
                {{ damagedItems.length }} record{{ damagedItems.length !== 1 ? 's' : '' }}
                failed ≥{{ DAMAGE_THRESHOLD }} sync attempts. Investigate or retry.
              </span>
            </div>
          </div>

          <div v-if="!damagedItems.length" class="nq-qz-empty">
            <span class="nq-qz-clear">✓ No damaged records detected</span>
          </div>

          <div v-else class="nq-qz-list">
            <div v-for="dmg in damagedItems" :key="dmg.client_uuid" class="nq-dmg-card">
              <div class="nq-dmg-strip" />
              <div class="nq-dmg-body">
                <div class="nq-dmg-top">
                  <span class="nq-dmg-badge">SYNC FAILURE</span>
                  <span class="nq-dmg-attempts">{{ dmg.sync_attempt_count }} attempts</span>
                  <span class="nq-dmg-age">{{ fmtRelative(dmg.created_at) }}</span>
                </div>
                <div class="nq-dmg-uuid">{{ dmg.client_uuid }}</div>
                <div class="nq-dmg-error">
                  <span class="nq-dmg-err-label">Error:</span>
                  {{ dmg._damageReason || dmg.last_sync_error || 'Unknown failure' }}
                </div>
                <div class="nq-dmg-meta">
                  <span>{{ dmg.poe_code }}</span>
                  <span>·</span>
                  <span>Priority: {{ dmg.priority || '—' }}</span>
                  <span>·</span>
                  <span>Status: {{ dmg.status }}</span>
                </div>
                <div class="nq-dmg-actions">
                  <button class="nq-dmg-btn nq-dmg-btn--retry"
                    @click="retryDamaged(dmg)" :disabled="retryingUuid === dmg.client_uuid">
                    {{ retryingUuid === dmg.client_uuid ? 'Queuing…' : '↺ Retry Sync' }}
                  </button>
                  <button class="nq-dmg-btn nq-dmg-btn--open"
                    v-if="dmg.client_uuid"
                    @click="openCaseFromDamaged(dmg)">
                    Open Case →
                  </button>
                  <button class="nq-dmg-btn nq-dmg-btn--dismiss"
                    @click="dismissDamaged(dmg)">
                    Dismiss
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── CRITICAL ALARM ZONE (shown in ALL/OPEN tabs) ───────────── -->
        <div v-if="showAlarmZone && criticalAlarmItems.length" class="nq-alarm-zone">
          <div class="nq-alarm-hdr">
            <span class="nq-alarm-pulse" aria-hidden="true" />
            <span class="nq-alarm-title">
              🚨 {{ criticalAlarmItems.length }} CRITICAL
              Referral{{ criticalAlarmItems.length !== 1 ? 's' : '' }} Awaiting
            </span>
            <span class="nq-alarm-age" v-if="criticalAlarmItems[0]">
              Oldest: {{ fmtRelative(criticalAlarmItems[0].notification_created_at) }}
            </span>
          </div>
          <div v-for="item in criticalAlarmItems" :key="item.notification_uuid"
            class="nq-card nq-card--critical nq-card--alarm"
            @click="openCase(item)">
            <div class="nq-card-strip nq-strip--critical" />
            <div class="nq-card-body">
              <div class="nq-card-head">
                <span class="nq-priority-badge nq-pb--critical">
                  <span class="nq-pb-dot nq-pb-dot--critical" />CRITICAL
                </span>
                <span class="nq-card-age nq-age--critical">
                  {{ fmtRelative(item.notification_created_at) }}
                </span>
              </div>
              <div class="nq-card-traveler">
                <div class="nq-avatar nq-av--critical">{{ genderGlyph(item.gender) }}</div>
                <div class="nq-traveler-info">
                  <span class="nq-traveler-name">
                    {{ item.traveler_full_name || `${genderLabel(item.gender)} · Anonymous` }}
                  </span>
                  <span class="nq-traveler-meta">
                    {{ item.screener_name || 'Primary Officer' }} · {{ fmtTime(item.captured_at) }}
                  </span>
                </div>
                <div v-if="item.temperature_value != null"
                  :class="['nq-temp-chip', tempChipClass(item.temperature_value, item.temperature_unit)]">
                  {{ item.temperature_value }}°{{ item.temperature_unit || 'C' }}
                </div>
              </div>
              <div class="nq-card-actions">
                <button class="nq-btn-open nq-btn-open--critical"
                  @click.stop="openCase(item)">
                  Open Screening Case →
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- ── MAIN LIST ─────────────────────────────────────────────── -->
        <div v-if="activeTab !== 'DAMAGED'" class="nq-list">

          <!-- Group: OPEN HIGH -->
          <div v-if="groupedHigh.length && showAlarmZone" class="nq-group-hdr nq-grp--high">
            <span class="nq-grp-stripe nq-gs--high" />
            <span>HIGH PRIORITY</span>
            <span class="nq-grp-count">{{ groupedHigh.length }}</span>
          </div>
          <article v-for="item in groupedHigh" :key="item.notification_uuid"
            class="nq-card nq-card--high" @click="openCase(item)">
            <div class="nq-card-strip nq-strip--high" />
            <div class="nq-card-body">
              <div class="nq-card-head">
                <span class="nq-priority-badge nq-pb--high">
                  <span class="nq-pb-dot nq-pb-dot--high" />HIGH
                </span>
                <span class="nq-status-badge nq-sb--open">OPEN</span>
                <span class="nq-card-age">{{ fmtRelative(item.notification_created_at) }}</span>
              </div>
              <div class="nq-card-traveler">
                <div class="nq-avatar nq-av--high">{{ genderGlyph(item.gender) }}</div>
                <div class="nq-traveler-info">
                  <span class="nq-traveler-name">{{ item.traveler_full_name || `${genderLabel(item.gender)} · Anonymous` }}</span>
                  <span class="nq-traveler-meta">{{ item.screener_name || 'Primary Officer' }} · {{ item.poe_code }}</span>
                </div>
                <div v-if="item.temperature_value != null"
                  :class="['nq-temp-chip', tempChipClass(item.temperature_value, item.temperature_unit)]">
                  {{ item.temperature_value }}°{{ item.temperature_unit || 'C' }}
                </div>
              </div>
              <p v-if="item.reason_text" class="nq-card-reason">{{ item.reason_text }}</p>
              <div class="nq-card-actions" @click.stop>
                <button class="nq-btn-cancel" @click.stop="confirmCancel(item)"
                  :disabled="cancellingId === item.notification_id">Cancel</button>
                <button class="nq-btn-open" @click.stop="openCase(item)">Open Case →</button>
              </div>
            </div>
          </article>

          <!-- Group: OPEN NORMAL -->
          <div v-if="groupedNormal.length && (groupedHigh.length || groupedCritical.length)"
            class="nq-group-hdr nq-grp--normal">
            <span class="nq-grp-stripe nq-gs--normal" />
            <span>NORMAL PRIORITY</span>
            <span class="nq-grp-count">{{ groupedNormal.length }}</span>
          </div>
          <article v-for="item in groupedNormal" :key="item.notification_uuid"
            class="nq-card nq-card--normal" @click="openCase(item)">
            <div class="nq-card-strip nq-strip--normal" />
            <div class="nq-card-body">
              <div class="nq-card-head">
                <span class="nq-priority-badge nq-pb--normal">
                  <span class="nq-pb-dot nq-pb-dot--normal" />NORMAL
                </span>
                <span class="nq-status-badge nq-sb--open">OPEN</span>
                <span class="nq-card-age">{{ fmtRelative(item.notification_created_at) }}</span>
              </div>
              <div class="nq-card-traveler">
                <div class="nq-avatar nq-av--normal">{{ genderGlyph(item.gender) }}</div>
                <div class="nq-traveler-info">
                  <span class="nq-traveler-name">{{ item.traveler_full_name || `${genderLabel(item.gender)} · Anonymous` }}</span>
                  <span class="nq-traveler-meta">{{ item.screener_name || 'Primary Officer' }} · {{ item.poe_code }}</span>
                </div>
                <div v-if="item.temperature_value != null"
                  :class="['nq-temp-chip', tempChipClass(item.temperature_value, item.temperature_unit)]">
                  {{ item.temperature_value }}°{{ item.temperature_unit || 'C' }}
                </div>
              </div>
              <p v-if="item.reason_text" class="nq-card-reason">{{ item.reason_text }}</p>
              <div class="nq-card-actions" @click.stop>
                <button class="nq-btn-cancel" @click.stop="confirmCancel(item)"
                  :disabled="cancellingId === item.notification_id">Cancel</button>
                <button class="nq-btn-open" @click.stop="openCase(item)">Open Case →</button>
              </div>
            </div>
          </article>

          <!-- Group: IN PROGRESS -->
          <div v-if="groupedInProgress.length && (activeTab === 'ALL' || activeTab === 'IN_PROGRESS')"
            class="nq-group-hdr nq-grp--progress">
            <span class="nq-grp-stripe nq-gs--progress" />
            <span>IN PROGRESS</span>
            <span class="nq-grp-count">{{ groupedInProgress.length }}</span>
          </div>
          <article v-for="item in groupedInProgress" :key="item.notification_uuid"
            class="nq-card nq-card--progress" @click="openCase(item)">
            <div class="nq-card-strip nq-strip--progress" />
            <div class="nq-card-body">
              <div class="nq-card-head">
                <span class="nq-priority-badge nq-pb--progress">
                  <span class="nq-pb-dot nq-pb-dot--progress" />{{ item.priority || 'NORMAL' }}
                </span>
                <span class="nq-status-badge nq-sb--progress">IN PROGRESS</span>
                <span class="nq-card-age">{{ fmtRelative(item.notification_created_at) }}</span>
              </div>
              <div class="nq-card-traveler">
                <div class="nq-avatar nq-av--progress">{{ genderGlyph(item.gender) }}</div>
                <div class="nq-traveler-info">
                  <span class="nq-traveler-name">{{ item.traveler_full_name || `${genderLabel(item.gender)} · Anonymous` }}</span>
                  <span class="nq-traveler-meta">{{ item.screener_name || 'Primary Officer' }} · {{ item.poe_code }}</span>
                </div>
                <div v-if="item.temperature_value != null"
                  :class="['nq-temp-chip', tempChipClass(item.temperature_value, item.temperature_unit)]">
                  {{ item.temperature_value }}°{{ item.temperature_unit || 'C' }}
                </div>
              </div>
              <div class="nq-card-actions" @click.stop>
                <button class="nq-btn-open nq-btn-open--resume" @click.stop="openCase(item)">
                  Resume Case →
                </button>
              </div>
            </div>
          </article>

          <!-- Group: CLOSED (shown in ALL or CLOSED tab) -->
          <div v-if="groupedClosed.length && (activeTab === 'ALL' || activeTab === 'CLOSED')"
            class="nq-group-hdr nq-grp--closed">
            <span class="nq-grp-stripe nq-gs--closed" />
            <span>CLOSED</span>
            <span class="nq-grp-count">{{ groupedClosed.length }}</span>
            <button v-if="activeTab === 'ALL' && !showClosed" class="nq-grp-toggle"
              @click.stop="showClosed = true">Show</button>
            <button v-else-if="activeTab === 'ALL' && showClosed" class="nq-grp-toggle"
              @click.stop="showClosed = false">Hide</button>
          </div>
          <template v-if="activeTab === 'CLOSED' || (activeTab === 'ALL' && showClosed)">
            <article v-for="item in groupedClosed" :key="item.notification_uuid"
              class="nq-card nq-card--closed">
              <div class="nq-card-strip nq-strip--closed" />
              <div class="nq-card-body">
                <div class="nq-card-head">
                  <span class="nq-priority-badge nq-pb--closed">{{ item.priority || 'NORMAL' }}</span>
                  <span class="nq-status-badge nq-sb--closed">CLOSED</span>
                  <span class="nq-card-age">{{ fmtRelative(item.notification_created_at) }}</span>
                </div>
                <div class="nq-card-traveler">
                  <div class="nq-avatar nq-av--closed">{{ genderGlyph(item.gender) }}</div>
                  <div class="nq-traveler-info">
                    <span class="nq-traveler-name">{{ item.traveler_full_name || `${genderLabel(item.gender)} · Anonymous` }}</span>
                    <span class="nq-traveler-meta">{{ item.poe_code }} · {{ fmtTime(item.notification_created_at) }}</span>
                  </div>
                  <div v-if="item.temperature_value != null"
                    :class="['nq-temp-chip nq-temp-chip--closed']">
                    {{ item.temperature_value }}°{{ item.temperature_unit || 'C' }}
                  </div>
                </div>
              </div>
            </article>
          </template>

          <!-- Empty state -->
          <div v-if="displayItems.length === 0 && !loading" class="nq-empty">
            <div class="nq-empty-icon">
              <svg viewBox="0 0 64 64" fill="none" stroke="rgba(0,212,170,0.4)"
                stroke-width="1.5" stroke-linecap="round">
                <circle cx="32" cy="32" r="28" />
                <polyline points="20 32 28 40 44 24" />
              </svg>
            </div>
            <span class="nq-empty-title">
              {{ emptyLabel }}
            </span>
            <span class="nq-empty-sub">
              {{ emptySubLabel }}
            </span>
            <button v-if="activeTab !== 'ALL'" class="nq-empty-btn" @click="setTab('ALL')">
              Show All Records
            </button>
          </div>

          <!-- Load more -->
          <div v-if="hasMore" class="nq-load-more">
            <button class="nq-load-btn" :disabled="loadingMore" @click="loadMore">
              <svg v-if="loadingMore" viewBox="0 0 24 24" fill="none"
                stroke="#00D4AA" stroke-width="2" stroke-linecap="round"
                class="nq-spin" width="16">
                <path d="M21 12a9 9 0 11-6.219-8.56"/>
              </svg>
              <span>{{ loadingMore ? 'Loading…' : `Load More · ${remainingCount.toLocaleString()} remaining` }}</span>
            </button>
          </div>

        </div>
      </div>

      <div style="height:56px" aria-hidden="true" />
    </IonContent>

    <!-- Cancel confirm -->
    <IonAlert
      :is-open="showCancelAlert"
      header="Cancel This Referral?"
      :sub-header="cancelTarget
        ? `${genderLabel(cancelTarget.gender)} · ${cancelTarget.temperature_value ? cancelTarget.temperature_value + '°' + (cancelTarget.temperature_unit||'C') : 'No temp recorded'} · Priority: ${cancelTarget.priority}`
        : ''"
      message="This closes the notification. The primary screening record stays COMPLETED. This action is permanently logged in the audit trail."
      :buttons="cancelAlertButtons"
      @didDismiss="showCancelAlert = false"
    />

    <IonToast :is-open="toast.show" :message="toast.msg" :color="toast.color"
      :duration="3200" position="top" @didDismiss="toast.show=false" />
  </IonPage>
</template>

<script setup>
/**
 * NotificationsCenter.vue — ECSA-HC POE Sentinel
 * WHO/IHR 2005 · Article 23 · Referral Command Centre
 *
 * Shows ALL referral records by default (not OPEN only).
 * Architecture: IDB-first for millions of records.
 *
 * DAMAGE DETECTION:
 *   A notification is "damaged" if:
 *     sync_status === FAILED AND sync_attempt_count >= DAMAGE_THRESHOLD (3)
 *   Damaged records are loaded from IDB separately and surfaced in the
 *   QUARANTINE tab. Officer can retry or dismiss them.
 *
 * INCREMENTAL SYNC:
 *   localStorage('nq_last_sync') stores the ISO timestamp of the last
 *   successful server fetch. backgroundSync() sends ?updated_after=<ts>
 *   so only new/changed records are fetched, not the full dataset.
 *   At millions of records, this keeps syncs to <100 records per call.
 *
 * MEMORY WINDOW:
 *   MAX_WINDOW = 300 records in JS heap at any time.
 *   allItems never exceeds this. Older records are evicted as newer
 *   pages load. Stats come from IDB count queries (O(1)).
 */
import { ref, computed, reactive, onMounted, onUnmounted, toRaw } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonPage, IonHeader, IonButtons, IonMenuButton,
  IonContent, IonIcon, IonSpinner,
  IonRefresher, IonRefresherContent,
  IonAlert, IonToast,
  onIonViewDidEnter, onIonViewWillLeave,
} from '@ionic/vue'
import {
  dbGet, dbGetByIndex, dbPut, safeDbPut, dbCountIndex,
  isoNow, STORE, SYNC, APP,
} from '@/services/poeDB'

const router = useRouter()

// ─── AUTH ─────────────────────────────────────────────────────────────────────
function getAuth() { return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {} }
const auth = ref(getAuth())

// ─── CONSTANTS ────────────────────────────────────────────────────────────────
const TABS = [
  { v:'ALL',         label:'All'         },
  { v:'OPEN',        label:'Open'        },
  { v:'IN_PROGRESS', label:'In Progress' },
  { v:'CLOSED',      label:'Closed'      },
  { v:'DAMAGED',     label:'⚠ Damaged'  },
]
const GENDER_LABELS  = { MALE:'Male', FEMALE:'Female', OTHER:'Other', UNKNOWN:'Unknown' }
const GENDER_GLYPHS  = { MALE:'♂', FEMALE:'♀', OTHER:'⚥', UNKNOWN:'?' }
const PRIORITY_ORDER = { CRITICAL:0, HIGH:1, NORMAL:2 }
const DAMAGE_THRESHOLD = 3     // ≥ N failed attempts = damaged record
const MAX_WINDOW       = 300   // max records in JS heap
const IDB_PAGE_SIZE    = 100
const SERVER_PAGE_SIZE = 100
const POLL_INTERVAL    = 30_000
const LAST_SYNC_KEY    = 'nq_last_sync'

// ─── STATE ────────────────────────────────────────────────────────────────────
const allItems      = ref([])    // memory window, max MAX_WINDOW
const damagedItems  = ref([])    // quarantined records with ≥ DAMAGE_THRESHOLD failures

// IDB counts — O(1), read zero record bytes
const idbOpenCount     = ref(0)
const idbCritCount     = ref(0)
const idbProgressCount = ref(0)
const idbClosedCount   = ref(0)

// Pagination
const idbPageOffset = ref(0)
const serverPage    = ref(1)
const totalOnServer = ref(0)
const hasMoreIdb    = ref(true)
const hasMoreServer = ref(true)

const loading        = ref(true)
const loadingMore    = ref(false)
const syncing        = ref(false)
const isOnline       = ref(navigator.onLine)

const activeTab  = ref('ALL')   // default: ALL records
const showClosed = ref(false)   // collapsed by default in ALL tab

// Cancel state
const cancellingId    = ref(null)
const showCancelAlert = ref(false)
const cancelTarget    = ref(null)

// Retry state
const retryingUuid = ref(null)

const toast = reactive({ show:false, msg:'', color:'success' })

let pollTimer   = null
let bgDebounce  = null

// ─── COMPUTED — GROUPED DISPLAY ───────────────────────────────────────────────
// Filters applied to memory window.
// When online, server pre-filters; when offline, JS filter on window.
const displayItems = computed(() => {
  let items = allItems.value
  if (activeTab.value === 'OPEN')        items = items.filter(i => i.notification_status === 'OPEN')
  if (activeTab.value === 'IN_PROGRESS') items = items.filter(i => i.notification_status === 'IN_PROGRESS')
  if (activeTab.value === 'CLOSED')      items = items.filter(i => i.notification_status === 'CLOSED')
  return items
})

const criticalAlarmItems = computed(() =>
  allItems.value.filter(i => i.notification_status === 'OPEN' && i.priority === 'CRITICAL')
    .sort((a,b) => new Date(a.notification_created_at||0) - new Date(b.notification_created_at||0))
)
const groupedHigh = computed(() =>
  displayItems.value.filter(i => i.notification_status === 'OPEN' && i.priority === 'HIGH')
)
const groupedNormal = computed(() =>
  displayItems.value.filter(i => i.notification_status === 'OPEN' && i.priority === 'NORMAL')
)
const groupedInProgress = computed(() =>
  displayItems.value.filter(i => i.notification_status === 'IN_PROGRESS')
)
const groupedClosed = computed(() =>
  displayItems.value.filter(i => i.notification_status === 'CLOSED')
    .sort((a,b) => new Date(b.notification_created_at||0) - new Date(a.notification_created_at||0))
    .slice(0, 50) // cap closed at 50 in memory view
)

const showAlarmZone = computed(() => activeTab.value === 'ALL' || activeTab.value === 'OPEN')
const hasMore       = computed(() =>
  (hasMoreIdb.value && allItems.value.length < (totalOnServer.value || Infinity)) ||
  (hasMoreServer.value && isOnline.value)
)
const remainingCount = computed(() => Math.max(0, (totalOnServer.value || 0) - allItems.value.length))

const emptyLabel = computed(() => {
  if (activeTab.value === 'OPEN')        return 'No Open Referrals'
  if (activeTab.value === 'IN_PROGRESS') return 'No Cases In Progress'
  if (activeTab.value === 'CLOSED')      return 'No Closed Referrals'
  return 'No Records'
})
const emptySubLabel = computed(() => {
  if (activeTab.value === 'OPEN') return 'All symptomatic travelers have been attended to.'
  return 'Records will appear here once created.'
})

// Tab badge counts
function tabBadge(v) {
  if (v === 'OPEN')        return idbOpenCount.value || null
  if (v === 'IN_PROGRESS') return idbProgressCount.value || null
  if (v === 'DAMAGED')     return damagedItems.value.length || null
  return null
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function toPlain(v)  { return JSON.parse(JSON.stringify(toRaw(v))) }
function showToast(msg, color='success') { Object.assign(toast, { show:true, msg, color }) }
function genderLabel(g) { return GENDER_LABELS[g] || g || 'Unknown' }
function genderGlyph(g) { return GENDER_GLYPHS[g] || '?' }

function fmtRelative(dt) {
  if (!dt) return '—'
  try {
    const m = Math.floor((Date.now() - new Date(dt.replace(' ','T'))) / 60000)
    if (m < 1)  return 'Just now'
    if (m < 60) return `${m}m ago`
    const h = Math.floor(m/60)
    if (h < 24) return `${h}h ago`
    return `${Math.floor(h/24)}d ago`
  } catch { return '—' }
}
function fmtTime(dt) {
  if (!dt) return '—'
  try { return new Date(dt.replace(' ','T')).toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }) }
  catch { return '—' }
}
function tempChipClass(val, unit) {
  const c = unit === 'F' ? (val - 32) * 5/9 : val
  if (c >= 38.5) return 'nq-temp-chip--critical'
  if (c >= 37.5) return 'nq-temp-chip--high'
  return 'nq-temp-chip--normal'
}

// Damage reason decoder
function detectDamageReason(r) {
  const err = String(r.last_sync_error || '')
  if (err.includes('404'))        return 'Primary screening not found on server'
  if (err.includes('422'))        return 'Invalid data — schema or validation mismatch'
  if (err.includes('403'))        return 'Access denied — geographic scope violation'
  if (err.includes('409'))        return 'Conflict — duplicate record on server'
  if (/timeout|abort/i.test(err)) return 'Persistent network timeout — check connectivity'
  if ((r.sync_attempt_count ?? 0) >= 10) return `Exceeded ${r.sync_attempt_count} retry attempts`
  return err || 'Unknown sync failure'
}

// ─── IDB STATS — O(1) ─────────────────────────────────────────────────────────
async function refreshIdbStats() {
  const poeCode = auth.value?.poe_code || ''
  if (!poeCode) return
  try {
    // We use the `status` index on notifications for status counts.
    // Note: IDB stores the notification status in the `status` field.
    const [open, progress, closed, failedAll] = await Promise.all([
      dbCountIndex(STORE.NOTIFICATIONS, 'status', 'OPEN'),
      dbCountIndex(STORE.NOTIFICATIONS, 'status', 'IN_PROGRESS'),
      dbCountIndex(STORE.NOTIFICATIONS, 'status', 'CLOSED'),
      dbCountIndex(STORE.NOTIFICATIONS, 'sync_status', SYNC.FAILED),
    ])
    idbOpenCount.value     = open
    idbProgressCount.value = progress
    idbClosedCount.value   = closed
    // Critical: scan the open items in the window for critical count
    idbCritCount.value = allItems.value.filter(i =>
      i.notification_status === 'OPEN' && i.priority === 'CRITICAL'
    ).length
  } catch (e) { console.warn('[NQ] refreshIdbStats', e?.message) }
}

// ─── DAMAGED RECORDS DETECTION ────────────────────────────────────────────────
// Scans IDB for FAILED notifications with ≥ DAMAGE_THRESHOLD attempts.
// These are quarantined and require officer action.
async function loadDamagedFromIdb() {
  try {
    const failed = await dbGetByIndex(STORE.NOTIFICATIONS, 'sync_status', SYNC.FAILED)
    return failed
      .filter(r => !r.deleted_at && (r.sync_attempt_count ?? 0) >= DAMAGE_THRESHOLD)
      .map(r => ({
        client_uuid:         r.client_uuid,
        notification_id:     r.id ?? r.server_id ?? null,
        notification_uuid:   r.client_uuid,
        status:              r.status,
        priority:            r.priority || 'NORMAL',
        poe_code:            r.poe_code,
        sync_attempt_count:  r.sync_attempt_count ?? 0,
        last_sync_error:     r.last_sync_error || null,
        sync_status:         r.sync_status,
        created_at:          r.created_at,
        record_version:      r.record_version || 1,
        _damageReason:       detectDamageReason(r),
        _damaged:            true,
      }))
      .sort((a,b) => (b.sync_attempt_count ?? 0) - (a.sync_attempt_count ?? 0))
  } catch (e) { console.warn('[NQ] loadDamagedFromIdb', e?.message); return [] }
}

// ─── IDB PAGE READ ────────────────────────────────────────────────────────────
async function readIdbPage(offset = 0) {
  const poeCode = auth.value?.poe_code || ''
  if (!poeCode) return []
  try {
    const all = await dbGetByIndex(STORE.NOTIFICATIONS, 'poe_code', poeCode)
    const valid = all.filter(n =>
      n.notification_type === 'SECONDARY_REFERRAL' &&
      !n.deleted_at &&
      n.sync_status !== SYNC.FAILED  // damaged handled separately
    )
    // Sort: priority DESC, then created_at DESC
    valid.sort((a,b) => {
      const pd = (PRIORITY_ORDER[a.priority] ?? 99) - (PRIORITY_ORDER[b.priority] ?? 99)
      if (pd !== 0) return pd
      return new Date(b.created_at||0) - new Date(a.created_at||0)
    })
    return valid.slice(offset, offset + IDB_PAGE_SIZE).map(normaliseIdbRecord)
  } catch (e) { console.warn('[NQ] readIdbPage', e?.message); return [] }
}

function normaliseIdbRecord(n) {
  return {
    notification_id:        n.id ?? n.server_id ?? null,
    notification_uuid:      n.client_uuid,
    notification_status:    n.status || 'OPEN',
    priority:               n.priority || 'NORMAL',
    reason_code:            n.reason_code || null,
    reason_text:            n.reason_text || null,
    notification_created_at:n.created_at || null,
    screener_name:          n.screener_name || null,
    primary_screening_id:   n.primary_screening_id || null,
    primary_uuid:           n.primary_uuid || null,
    gender:                 n.gender || null,
    temperature_value:      n.temperature_value ?? null,
    temperature_unit:       n.temperature_unit || null,
    traveler_full_name:     n.traveler_full_name || null,
    captured_at:            n.captured_at || n.created_at || null,
    poe_code:               n.poe_code,
    district_code:          n.district_code || null,
    country_code:           n.country_code || null,
    province_code:          n.province_code || null,
    pheoc_code:             n.pheoc_code || null,
    sync_status:            n.sync_status || SYNC.UNSYNCED,
    sync_attempt_count:     n.sync_attempt_count || 0,
    last_sync_error:        n.last_sync_error || null,
    is_voided_primary:      !!n.is_voided_primary,
    _fromCache:             true,
  }
}

// ─── SERVER FETCH ─────────────────────────────────────────────────────────────
async function fetchFromServer(pg = 1, statusFilter = 'ALL', updatedAfter = null) {
  const userId = auth.value?.id
  if (!userId || !isOnline.value) return null
  const p = new URLSearchParams({
    user_id:  userId,
    status:   statusFilter,
    page:     pg,
    per_page: SERVER_PAGE_SIZE,
  })
  if (updatedAfter) p.set('updated_after', updatedAfter)
  const ctrl = new AbortController()
  const tid  = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
  try {
    const res = await fetch(`${window.SERVER_URL}/referral-queue?${p}`,
      { headers: { Accept: 'application/json' }, signal: ctrl.signal })
    clearTimeout(tid)
    if (!res.ok) return null
    const j = await res.json()
    return j.success ? j.data : null
  } catch { clearTimeout(tid); return null }
}

// ─── WRITE-THROUGH CACHE — ALL FIELDS ─────────────────────────────────────────
// Writes complete server notification records to IDB. On reconnect, offline
// mode has full data including traveler name, screener, temperature.
async function writeServerItemsToIdb(serverItems) {
  for (const item of serverItems) {
    if (!item.notification_uuid) continue
    try {
      const existing = await dbGet(STORE.NOTIFICATIONS, item.notification_uuid)
      const incomingVer = item.record_version ?? 1
      if (!existing) {
        await dbPut(STORE.NOTIFICATIONS, toPlain({
          client_uuid:            item.notification_uuid,
          id:                     item.notification_id,
          server_id:              item.notification_id,
          reference_data_version: APP.REFERENCE_DATA_VER,
          server_received_at:     null,
          country_code:           item.country_code   || '',
          province_code:          item.province_code  || null,
          pheoc_code:             item.pheoc_code     || null,
          district_code:          item.district_code  || '',
          poe_code:               item.poe_code       || auth.value?.poe_code || '',
          primary_screening_id:   item.primary_uuid   || String(item.primary_screening_id || ''),
          created_by_user_id:     null,
          notification_type:      'SECONDARY_REFERRAL',
          status:                 item.notification_status || 'OPEN',
          priority:               item.priority     || 'NORMAL',
          reason_code:            item.reason_code  || 'PRIMARY_SYMPTOMS_DETECTED',
          reason_text:            item.reason_text  || null,
          assigned_role_key:      'POE_SECONDARY',
          assigned_user_id:       null,
          opened_at:              item.opened_at    || null,
          closed_at:              item.closed_at    || null,
          device_id:              'SERVER',
          app_version:            null,
          platform:               'WEB',
          record_version:         incomingVer,
          deleted_at:             null,
          sync_status:            SYNC.SYNCED,
          synced_at:              isoNow(),
          sync_attempt_count:     0,
          last_sync_error:        null,
          created_at:             item.notification_created_at || isoNow(),
          updated_at:             isoNow(),
          // Enriched display fields (from server join)
          gender:                 item.gender          || null,
          temperature_value:      item.temperature_value ?? null,
          temperature_unit:       item.temperature_unit || null,
          traveler_full_name:     item.traveler_full_name || null,
          captured_at:            item.captured_at     || null,
          screener_name:          item.screener_name   || null,
          primary_uuid:           item.primary_uuid    || null,
          is_voided_primary:      !!item.is_voided_primary,
        }))
      } else if (incomingVer > (existing.record_version ?? 0)) {
        await safeDbPut(STORE.NOTIFICATIONS, toPlain({
          ...existing,
          id:                 item.notification_id,
          server_id:          item.notification_id,
          status:             item.notification_status || existing.status,
          priority:           item.priority            || existing.priority,
          reason_text:        item.reason_text         || existing.reason_text,
          opened_at:          item.opened_at           || existing.opened_at,
          closed_at:          item.closed_at           || existing.closed_at,
          gender:             item.gender              || existing.gender,
          temperature_value:  item.temperature_value   ?? existing.temperature_value,
          temperature_unit:   item.temperature_unit    || existing.temperature_unit,
          traveler_full_name: item.traveler_full_name  || existing.traveler_full_name,
          screener_name:      item.screener_name       || existing.screener_name,
          record_version:     incomingVer,
          sync_status:        SYNC.SYNCED,
          synced_at:          isoNow(),
          updated_at:         isoNow(),
        }))
      }
    } catch (e) { console.warn('[NQ] writeServerItemsToIdb', item.notification_uuid, e?.message) }
  }
}

// ─── MERGE INTO MEMORY WINDOW ─────────────────────────────────────────────────
function mergeIntoWindow(serverItems) {
  const byUuid = new Map(allItems.value.map(i => [i.notification_uuid, i]))
  for (const s of serverItems) {
    if (!s.notification_uuid) continue
    byUuid.set(s.notification_uuid, {
      notification_id:        s.notification_id,
      notification_uuid:      s.notification_uuid,
      notification_status:    s.notification_status,
      priority:               s.priority || 'NORMAL',
      reason_code:            s.reason_code,
      reason_text:            s.reason_text,
      notification_created_at:s.notification_created_at,
      screener_name:          s.screener_name,
      primary_screening_id:   s.primary_screening_id,
      primary_uuid:           s.primary_uuid,
      gender:                 s.gender,
      temperature_value:      s.temperature_value,
      temperature_unit:       s.temperature_unit,
      traveler_full_name:     s.traveler_full_name,
      captured_at:            s.captured_at,
      poe_code:               s.poe_code,
      district_code:          s.district_code,
      country_code:           s.country_code,
      province_code:          s.province_code,
      pheoc_code:             s.pheoc_code,
      is_voided_primary:      !!s.is_voided_primary,
      sync_status:            SYNC.SYNCED,
      sync_attempt_count:     0,
      last_sync_error:        null,
      _fromCache:             false,
    })
  }
  // Sort: priority ASC (CRITICAL first), then created_at DESC
  let sorted = Array.from(byUuid.values()).sort((a,b) => {
    const pd = (PRIORITY_ORDER[a.priority]??99) - (PRIORITY_ORDER[b.priority]??99)
    if (pd !== 0) return pd
    return new Date(b.notification_created_at||0) - new Date(a.notification_created_at||0)
  })
  if (sorted.length > MAX_WINDOW) sorted = sorted.slice(0, MAX_WINDOW)
  allItems.value = sorted
}

// ─── LOAD LIFECYCLE ───────────────────────────────────────────────────────────
async function load() {
  loading.value    = true
  idbPageOffset.value = 0
  serverPage.value    = 1
  hasMoreIdb.value    = true
  hasMoreServer.value = true

  try {
    // Phase 1: IDB stats + page 1 + damaged (instant, works offline)
    const [idbPage] = await Promise.all([
      readIdbPage(0),
      refreshIdbStats(),
    ])
    if (idbPage.length > 0) {
      allItems.value   = idbPage
      idbPageOffset.value = IDB_PAGE_SIZE
      hasMoreIdb.value    = idbPage.length === IDB_PAGE_SIZE
      loading.value = false
    }
    damagedItems.value = await loadDamagedFromIdb()

    // Phase 2: server page 1 (if online)
    if (isOnline.value) {
      const data = await fetchFromServer(1, 'ALL')
      if (data) {
        totalOnServer.value = data.total || 0
        hasMoreServer.value = (data.page ?? 1) < (data.pages ?? 1)
        serverPage.value    = 2
        writeServerItemsToIdb(data.items || []).catch(() => {})
        mergeIntoWindow(data.items || [])
        localStorage.setItem(LAST_SYNC_KEY, isoNow())
        refreshIdbStats().catch(() => {})
        damagedItems.value = await loadDamagedFromIdb()
      }
    }
  } finally { loading.value = false }
}

async function loadMore() {
  if (loadingMore.value) return
  loadingMore.value = true
  try {
    if (hasMoreIdb.value) {
      const idbPage = await readIdbPage(idbPageOffset.value)
      if (idbPage.length > 0) {
        const existing = new Set(allItems.value.map(i => i.notification_uuid))
        const fresh = idbPage.filter(i => !existing.has(i.notification_uuid))
        const combined = [...allItems.value, ...fresh]
          .sort((a,b) => {
            const pd = (PRIORITY_ORDER[a.priority]??99) - (PRIORITY_ORDER[b.priority]??99)
            if (pd !== 0) return pd
            return new Date(b.notification_created_at||0) - new Date(a.notification_created_at||0)
          })
          .slice(0, MAX_WINDOW)
        allItems.value      = combined
        idbPageOffset.value += IDB_PAGE_SIZE
        hasMoreIdb.value     = idbPage.length === IDB_PAGE_SIZE
        return
      }
      hasMoreIdb.value = false
    }
    if (hasMoreServer.value && isOnline.value) {
      const data = await fetchFromServer(serverPage.value, 'ALL')
      if (data) {
        totalOnServer.value = data.total || 0
        hasMoreServer.value = (data.page??1) < (data.pages??1)
        serverPage.value++
        writeServerItemsToIdb(data.items||[]).catch(()=>{})
        mergeIntoWindow(data.items||[])
        refreshIdbStats().catch(()=>{})
      }
    }
  } finally { loadingMore.value = false }
}

// ─── BACKGROUND INCREMENTAL SYNC ─────────────────────────────────────────────
async function backgroundSync(debounceMs = 500) {
  if (!isOnline.value || syncing.value) return
  if (bgDebounce) clearTimeout(bgDebounce)
  bgDebounce = setTimeout(async () => {
    bgDebounce = null
    syncing.value = true
    try {
      const lastSync = localStorage.getItem(LAST_SYNC_KEY) || null
      const data = await fetchFromServer(1, 'ALL', lastSync)
      if (!data?.items?.length) return
      await writeServerItemsToIdb(data.items)
      mergeIntoWindow(data.items)
      await refreshIdbStats()
      damagedItems.value = await loadDamagedFromIdb()
      localStorage.setItem(LAST_SYNC_KEY, isoNow())
    } catch (e) { console.warn('[NQ] backgroundSync', e?.message) }
    finally { syncing.value = false }
  }, debounceMs)
}

async function manualRefresh() {
  auth.value = getAuth()
  await load()
}

// ─── QUARANTINE ACTIONS ───────────────────────────────────────────────────────
async function retryDamaged(item) {
  retryingUuid.value = item.client_uuid
  try {
    const rec = await dbGet(STORE.NOTIFICATIONS, item.client_uuid)
    if (!rec) { showToast('Record not found in local cache.', 'warning'); return }
    await safeDbPut(STORE.NOTIFICATIONS, toPlain({
      ...rec,
      sync_status:        SYNC.UNSYNCED,
      sync_attempt_count: 0,
      last_sync_error:    null,
      record_version:     (rec.record_version || 1) + 1,
      updated_at:         isoNow(),
    }))
    damagedItems.value = damagedItems.value.filter(d => d.client_uuid !== item.client_uuid)
    await refreshIdbStats()
    showToast('Record reset and queued for sync retry.', 'success')
    // Trigger background sync immediately
    backgroundSync(0)
  } catch (e) {
    showToast(`Retry failed: ${e?.message || 'Unknown error'}`, 'danger')
  } finally { retryingUuid.value = null }
}

async function dismissDamaged(item) {
  try {
    const rec = await dbGet(STORE.NOTIFICATIONS, item.client_uuid)
    if (rec) {
      await safeDbPut(STORE.NOTIFICATIONS, toPlain({
        ...rec,
        deleted_at:     isoNow(),
        record_version: (rec.record_version || 1) + 1,
        updated_at:     isoNow(),
      }))
    }
    damagedItems.value = damagedItems.value.filter(d => d.client_uuid !== item.client_uuid)
    await refreshIdbStats()
    showToast('Damaged record dismissed and removed from queue.', 'warning')
  } catch (e) { showToast(`Dismiss failed: ${e?.message}`, 'danger') }
}

async function openCaseFromDamaged(dmg) {
  // Attempt to open even if damaged — officer may want to review
  if (!dmg.client_uuid) { showToast('No UUID on this record.', 'warning'); return }
  router.push('/secondary-screening/' + dmg.client_uuid)
}

// ─── OPEN CASE ────────────────────────────────────────────────────────────────
// Ensures notification is in IDB before navigating (fixes "Not Found" bug).
async function openCase(item) {
  if (document.activeElement instanceof HTMLElement) document.activeElement.blur()
  const notifUuid = item.notification_uuid
  if (!notifUuid) { showToast('Referral ID missing. Please refresh.', 'warning'); return }
  try {
    const existing = await dbGet(STORE.NOTIFICATIONS, notifUuid)
    if (!existing) {
      await dbPut(STORE.NOTIFICATIONS, toPlain({
        client_uuid:            notifUuid,
        id:                     item.notification_id     ?? null,
        server_id:              item.notification_id     ?? null,
        reference_data_version: APP.REFERENCE_DATA_VER,
        server_received_at:     null,
        country_code:           item.country_code        || '',
        province_code:          item.province_code       || null,
        pheoc_code:             item.pheoc_code          || null,
        district_code:          item.district_code       || '',
        poe_code:               item.poe_code            || auth.value?.poe_code || '',
        primary_screening_id:   item.primary_uuid        || String(item.primary_screening_id || ''),
        created_by_user_id:     null,
        notification_type:      'SECONDARY_REFERRAL',
        status:                 item.notification_status || 'OPEN',
        priority:               item.priority            || 'NORMAL',
        reason_code:            item.reason_code         || 'PRIMARY_SYMPTOMS_DETECTED',
        reason_text:            item.reason_text         || null,
        assigned_role_key:      'POE_SECONDARY',
        assigned_user_id:       null,
        opened_at:              null, closed_at: null,
        device_id:              'SERVER', app_version: null, platform: 'WEB',
        record_version:         1, deleted_at: null,
        sync_status:            SYNC.SYNCED, synced_at: isoNow(),
        sync_attempt_count:     0, last_sync_error: null, sync_note: null,
        created_at:             item.notification_created_at || isoNow(),
        updated_at:             isoNow(),
        gender:                 item.gender || null,
        temperature_value:      item.temperature_value ?? null,
        temperature_unit:       item.temperature_unit || null,
        traveler_full_name:     item.traveler_full_name || null,
        captured_at:            item.captured_at || null,
        screener_name:          item.screener_name || null,
        primary_uuid:           item.primary_uuid || null,
      }))
    }
  } catch (err) {
    console.error('[NQ] openCase IDB write failed', err)
  }
  router.push('/secondary-screening/' + notifUuid)
}

// ─── CANCEL REFERRAL ─────────────────────────────────────────────────────────
function confirmCancel(item) {
  if (item.notification_status === 'IN_PROGRESS') {
    showToast('Case in progress — secondary officer must close it from their screening view.', 'warning')
    return
  }
  cancelTarget.value    = item
  showCancelAlert.value = true
}

const cancelAlertButtons = [
  { text:'Keep Referral', role:'cancel', handler: () => { showCancelAlert.value = false; cancelTarget.value = null } },
  { text:'Cancel Referral', role:'destructive', handler: () => executeCancel() },
]

async function executeCancel() {
  const item = cancelTarget.value
  if (!item) return
  const notifId = item.notification_id
  cancellingId.value    = notifId
  showCancelAlert.value = false

  // Optimistic removal
  const idx = allItems.value.findIndex(i => i.notification_id === notifId)
  if (idx !== -1) allItems.value.splice(idx, 1)
  allItems.value = [...allItems.value]

  if (isOnline.value && Number.isInteger(Number(notifId)) && Number(notifId) > 0) {
    try {
      const res = await fetch(`${window.SERVER_URL}/referral-queue/${notifId}/cancel`, {
        method: 'PATCH',
        headers: { 'Content-Type':'application/json', Accept:'application/json' },
        body: JSON.stringify({ user_id: auth.value?.id, cancel_reason: 'Cancelled by officer from referral queue.' }),
      })
      if (!res.ok) {
        if (idx !== -1) { allItems.value.splice(idx, 0, item); allItems.value = [...allItems.value] }
        const ej = await res.json().catch(()=>({}))
        showToast(ej.message || 'Could not cancel referral.', 'danger')
      } else {
        showToast('Referral cancelled. Primary record preserved.', 'success')
        await refreshIdbStats()
      }
    } catch {
      if (idx !== -1) { allItems.value.splice(idx, 0, item); allItems.value = [...allItems.value] }
      showToast('Network error. Referral not cancelled — try again when connected.', 'danger')
    }
  } else if (!isOnline.value) {
    showToast('Offline: referral removed from local view. Will reappear on reconnect.', 'warning')
  } else {
    showToast('Referral not yet synced — no server record to cancel.', 'warning')
  }

  cancellingId.value = null
  cancelTarget.value = null
}

// ─── FILTER ───────────────────────────────────────────────────────────────────
function setTab(v) { activeTab.value = v }

// ─── CONNECTIVITY ─────────────────────────────────────────────────────────────
function onOnline()  { isOnline.value = true;  backgroundSync(300) }
function onOffline() { isOnline.value = false }

// ─── LIFECYCLE ─────────────────────────────────────────────────────────────────
onMounted(() => {
  auth.value = getAuth()
  window.addEventListener('online',  onOnline)
  window.addEventListener('offline', onOffline)
  load()
  pollTimer = setInterval(() => {
    if (isOnline.value && !loading.value) backgroundSync()
  }, POLL_INTERVAL)
})

onIonViewDidEnter(() => {
  auth.value = getAuth()
  backgroundSync(200)
  loadDamagedFromIdb().then(d => { damagedItems.value = d }).catch(()=>{})
})

onIonViewWillLeave(() => { /* keep timers — Ionic caches the page */ })

onUnmounted(() => {
  window.removeEventListener('online',  onOnline)
  window.removeEventListener('offline', onOffline)
  clearInterval(pollTimer)
  if (bgDebounce) clearTimeout(bgDebounce)
})
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════
   REFERRAL COMMAND CENTRE · nq-* namespace
   Aesthetic: deep-space command station with futuristic scan-line header.
   Dark theme: #030A14 base. Cyan/teal accent. Priority-coded urgency.
   Mobile-first: min touch 56px, min font 11px, no horizontal overflow.
   No position:absolute on content elements (only decorative).
═══════════════════════════════════════════════════════════════════════ */

/* ── PAGE ─────────────────────────────────────────────────────────────── */
.nq-page { --background: #030A14; }

/* ── HEADER ──────────────────────────────────────────────────────────── */
.nq-header {
  background: linear-gradient(180deg, #030A14 0%, #050E1E 60%, #071428 100%);
  position: relative;
  overflow: hidden;
}

/* Futuristic scan-line texture (decorative, pointer-events:none) */
.nq-hdr-scanlines {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: repeating-linear-gradient(
    0deg,
    transparent 0px,
    transparent 3px,
    rgba(0,212,170,0.012) 3px,
    rgba(0,212,170,0.012) 4px
  );
  z-index: 0;
}
.nq-hdr-glow {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background-image:
    radial-gradient(ellipse at 80% 0%, rgba(0,212,170,0.07) 0%, transparent 55%),
    radial-gradient(ellipse at 15% 80%, rgba(56,189,248,0.05) 0%, transparent 45%);
  z-index: 0;
}

/* ── TOP BAR ─────────────────────────────────────────────────────────── */
.nq-topbar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px 6px;
  position: relative;
  z-index: 2;
}
.nq-menu-btn { --color: rgba(255,255,255,0.65); flex-shrink: 0; }

.nq-title-block {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.nq-eyebrow {
  font-size: 8.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.6px;
  color: rgba(0,212,170,0.5);
  display: block;
}
.nq-title {
  font-size: 18px;
  font-weight: 900;
  color: #fff;
  letter-spacing: -.4px;
  line-height: 1.15;
}

.nq-topbar-right {
  display: flex;
  align-items: center;
  gap: 7px;
  flex-shrink: 0;
}
.nq-conn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 99px;
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .5px;
  border: 1px solid;
}
.nq-conn--on  { background: rgba(0,212,170,0.12); border-color: rgba(0,212,170,0.3); color: #00D4AA; }
.nq-conn--off { background: rgba(148,163,184,0.1); border-color: rgba(148,163,184,0.2); color: rgba(255,255,255,0.4); }
.nq-conn-dot  { width: 5px; height: 5px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
.nq-conn--on .nq-conn-dot { animation: nq-pulse 1.6s ease-in-out infinite; }
.nq-conn-txt  { display: none; }
@media (min-width: 390px) { .nq-conn-txt { display: block; } }

.nq-icon-btn {
  width: 32px; height: 32px;
  border-radius: 8px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.1);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0;
}
.nq-icon-btn svg { width: 16px; height: 16px; }
.nq-icon-btn:disabled { opacity: .4; }
.nq-spin { animation: nq-rotate .8s linear infinite; }

@keyframes nq-pulse  { 0%,100%{opacity:1} 50%{opacity:.2} }
@keyframes nq-rotate { to { transform: rotate(360deg); } }

/* ── STATS COMMAND GRID ──────────────────────────────────────────────── */
.nq-stats-grid {
  display: flex;
  align-items: center;
  padding: 8px 12px 6px;
  gap: 0;
  position: relative;
  z-index: 2;
  background: rgba(255,255,255,0.03);
  border-top: 1px solid rgba(255,255,255,0.06);
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.nq-stat {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 6px 4px;
  background: none;
  border: none;
  cursor: pointer;
  border-radius: 8px;
  transition: background .12s;
}
.nq-stat:active, .nq-stat--active { background: rgba(255,255,255,0.06); }
.nq-stat-sep {
  width: 1px;
  height: 28px;
  background: rgba(255,255,255,0.08);
  flex-shrink: 0;
}
.nq-stat-n {
  font-size: 22px;
  font-weight: 900;
  line-height: 1;
  color: #fff;
  font-variant-numeric: tabular-nums;
  letter-spacing: -.5px;
}
.nq-stat-l {
  font-size: 8px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .6px;
  color: rgba(255,255,255,0.35);
  white-space: nowrap;
}
.nq-stat--open-n     { color: #38BDF8; }
.nq-stat--crit-n     { color: #FF3D00; }
.nq-stat--prog-n     { color: #00D4AA; }
.nq-stat--closed-n   { color: rgba(255,255,255,0.4); }
.nq-stat--dmg-n      { color: #FF6B35; }
.nq-stat--damaged-btn .nq-stat-l { color: rgba(255,107,53,0.6); }

/* ── TAB STRIP ───────────────────────────────────────────────────────── */
.nq-tabs {
  display: flex;
  overflow-x: auto;
  scrollbar-width: none;
  padding: 0 8px;
  gap: 2px;
  background: rgba(0,0,0,0.2);
  border-bottom: 1px solid rgba(255,255,255,0.06);
  position: relative;
  z-index: 2;
}
.nq-tabs::-webkit-scrollbar { display: none; }
.nq-tab {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 9px 11px;
  border: none;
  background: none;
  border-bottom: 2px solid transparent;
  font-size: 11px;
  font-weight: 700;
  color: rgba(255,255,255,0.4);
  white-space: nowrap;
  cursor: pointer;
  transition: color .12s, border-color .12s;
  flex-shrink: 0;
}
.nq-tab:active { opacity: .7; }
.nq-tab--on { color: #00D4AA; border-bottom-color: #00D4AA; }
.nq-tab-badge {
  padding: 1px 6px;
  border-radius: 99px;
  font-size: 9px;
  font-weight: 900;
  min-width: 18px;
  text-align: center;
}
.nq-tb--open        { background: rgba(56,189,248,0.2); color: #38BDF8; }
.nq-tb--in-progress { background: rgba(0,212,170,0.2);  color: #00D4AA; }
.nq-tb--damaged     { background: rgba(255,107,53,0.25); color: #FF6B35; }

/* ── CONTENT ─────────────────────────────────────────────────────────── */
.nq-content { --background: #040C18; }
.nq-board { padding-bottom: 8px; }

/* Offline badge */
.nq-offline-badge {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 8px 14px;
  font-size: 11px;
  font-weight: 700;
  color: rgba(255,255,255,0.45);
  background: rgba(255,255,255,0.03);
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.nq-offline-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: rgba(255,255,255,0.3);
  flex-shrink: 0;
}

/* Skeleton */
.nq-skeleton-wrap { padding: 10px 12px; display: flex; flex-direction: column; gap: 8px; }
.nq-sk-card {
  height: 120px;
  border-radius: 14px;
  background: linear-gradient(90deg, rgba(255,255,255,0.04) 25%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.04) 75%);
  background-size: 200% 100%;
  animation: nq-shimmer 1.4s ease-in-out infinite;
}
@keyframes nq-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

/* ── QUARANTINE ZONE ─────────────────────────────────────────────────── */
.nq-quarantine-zone {
  margin: 10px 12px 0;
}
.nq-qz-header {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  background: rgba(255,107,53,0.08);
  border: 1px solid rgba(255,107,53,0.2);
  border-radius: 14px 14px 0 0;
}
.nq-qz-icon-wrap { flex-shrink: 0; padding-top: 2px; }
.nq-qz-text { display: flex; flex-direction: column; gap: 2px; }
.nq-qz-title {
  font-size: 13px;
  font-weight: 800;
  color: #FF6B35;
}
.nq-qz-sub { font-size: 10px; color: rgba(255,107,53,0.7); }
.nq-qz-empty {
  padding: 16px;
  text-align: center;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 0 0 14px 14px;
}
.nq-qz-clear { font-size: 12px; color: #00D4AA; font-weight: 700; }

.nq-qz-list {
  display: flex;
  flex-direction: column;
  gap: 1px;
  background: rgba(255,107,53,0.1);
  border: 1px solid rgba(255,107,53,0.2);
  border-top: none;
  border-radius: 0 0 14px 14px;
  overflow: hidden;
}
.nq-dmg-card {
  display: flex;
  align-items: stretch;
  background: rgba(255,107,53,0.06);
}
.nq-dmg-strip {
  width: 4px;
  background: #FF6B35;
  flex-shrink: 0;
}
.nq-dmg-body { flex: 1; padding: 12px 13px; display: flex; flex-direction: column; gap: 5px; }
.nq-dmg-top {
  display: flex;
  align-items: center;
  gap: 8px;
}
.nq-dmg-badge {
  font-size: 8px;
  font-weight: 900;
  padding: 2px 6px;
  border-radius: 3px;
  background: rgba(255,107,53,0.25);
  color: #FF6B35;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.nq-dmg-attempts {
  font-size: 10px;
  font-weight: 800;
  color: #FF6B35;
}
.nq-dmg-age { font-size: 9px; color: rgba(255,255,255,0.3); margin-left: auto; }
.nq-dmg-uuid {
  font-family: 'SF Mono', 'Fira Code', monospace;
  font-size: 9.5px;
  color: rgba(255,255,255,0.3);
  letter-spacing: .3px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.nq-dmg-error {
  font-size: 10px;
  color: rgba(255,107,53,0.8);
  line-height: 1.4;
}
.nq-dmg-err-label { font-weight: 800; }
.nq-dmg-meta {
  display: flex;
  gap: 6px;
  font-size: 9px;
  color: rgba(255,255,255,0.25);
}
.nq-dmg-actions {
  display: flex;
  gap: 6px;
  margin-top: 4px;
}
.nq-dmg-btn {
  padding: 7px 12px;
  border-radius: 7px;
  font-size: 10px;
  font-weight: 800;
  cursor: pointer;
  border: 1px solid;
  min-height: 34px;
  flex-shrink: 0;
}
.nq-dmg-btn--retry   { background: rgba(0,212,170,0.1);  border-color: rgba(0,212,170,0.3);  color: #00D4AA; }
.nq-dmg-btn--open    { background: rgba(56,189,248,0.1);  border-color: rgba(56,189,248,0.3);  color: #38BDF8; }
.nq-dmg-btn--dismiss { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: rgba(255,255,255,0.4); }
.nq-dmg-btn:disabled { opacity: .5; }

/* ── ALARM ZONE ──────────────────────────────────────────────────────── */
.nq-alarm-zone { margin: 10px 12px 0; }
.nq-alarm-hdr {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: rgba(255,61,0,0.15);
  border: 1px solid rgba(255,61,0,0.3);
  border-radius: 12px 12px 0 0;
}
.nq-alarm-pulse {
  width: 10px; height: 10px;
  border-radius: 50%;
  background: #FF3D00;
  flex-shrink: 0;
  box-shadow: 0 0 0 3px rgba(255,61,0,0.25);
  animation: nq-alarm-pulse 1s ease-in-out infinite;
}
@keyframes nq-alarm-pulse {
  0%,100%{ box-shadow: 0 0 0 3px rgba(255,61,0,0.25); }
  50%    { box-shadow: 0 0 0 8px rgba(255,61,0,0.05); }
}
.nq-alarm-title {
  font-size: 12px;
  font-weight: 900;
  color: #FF3D00;
  letter-spacing: .3px;
  flex: 1;
}
.nq-alarm-age { font-size: 9px; color: rgba(255,61,0,0.6); }

/* ── GROUP HEADERS ───────────────────────────────────────────────────── */
.nq-group-hdr {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px 5px;
  font-size: 9.5px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: rgba(255,255,255,0.35);
}
.nq-grp-stripe {
  width: 12px;
  height: 3px;
  border-radius: 2px;
  flex-shrink: 0;
}
.nq-gs--high     { background: #FFB300; }
.nq-gs--normal   { background: #00D4AA; }
.nq-gs--progress { background: #38BDF8; }
.nq-gs--closed   { background: rgba(255,255,255,0.2); }
.nq-grp-count {
  padding: 1px 6px;
  border-radius: 99px;
  background: rgba(255,255,255,0.07);
  color: rgba(255,255,255,0.4);
  font-size: 9px;
}
.nq-grp-toggle {
  margin-left: auto;
  font-size: 9px;
  font-weight: 800;
  color: #00D4AA;
  background: none;
  border: none;
  cursor: pointer;
  padding: 2px 6px;
}

/* ── REFERRAL CARDS ──────────────────────────────────────────────────── */
.nq-list {
  display: flex;
  flex-direction: column;
  padding: 0 12px;
  gap: 7px;
  margin-top: 4px;
}

.nq-card {
  display: flex;
  align-items: stretch;
  border-radius: 14px;
  overflow: hidden;
  cursor: pointer;
  border: 1px solid rgba(255,255,255,0.07);
  transition: border-color .12s, transform .1s;
  position: relative;
}
.nq-card:active { transform: scale(.98); }

/* Card backgrounds by type */
.nq-card--critical { background: rgba(255,61,0,0.06);  border-color: rgba(255,61,0,0.2); }
.nq-card--high     { background: rgba(255,179,0,0.05); border-color: rgba(255,179,0,0.15); }
.nq-card--normal   { background: rgba(255,255,255,0.03); }
.nq-card--progress { background: rgba(56,189,248,0.05); border-color: rgba(56,189,248,0.15); }
.nq-card--closed   { background: rgba(255,255,255,0.02); opacity: .65; }
.nq-card--alarm    { box-shadow: 0 0 20px rgba(255,61,0,0.15), inset 0 0 20px rgba(255,61,0,0.03); }

/* Priority strip */
.nq-card-strip { width: 4px; flex-shrink: 0; }
.nq-strip--critical { background: #FF3D00; animation: nq-strip-pulse 1.2s ease-in-out infinite; }
.nq-strip--high     { background: #FFB300; }
.nq-strip--normal   { background: #00D4AA; }
.nq-strip--progress { background: #38BDF8; }
.nq-strip--closed   { background: rgba(255,255,255,0.15); }
@keyframes nq-strip-pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

.nq-card-body { flex: 1; padding: 11px 12px 10px; display: flex; flex-direction: column; gap: 7px; min-width: 0; }

/* Card head row */
.nq-card-head { display: flex; align-items: center; gap: 6px; }
.nq-priority-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 7px;
  border-radius: 4px;
  font-size: 9px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.nq-pb--critical { background: rgba(255,61,0,0.2);   color: #FF3D00; }
.nq-pb--high     { background: rgba(255,179,0,0.2);  color: #FFB300; }
.nq-pb--normal   { background: rgba(0,212,170,0.15); color: #00D4AA; }
.nq-pb--progress { background: rgba(56,189,248,0.15);color: #38BDF8; }
.nq-pb--closed   { background: rgba(255,255,255,0.07);color: rgba(255,255,255,0.35); }
.nq-pb-dot {
  width: 5px; height: 5px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}
.nq-pb-dot--critical { animation: nq-pulse 1s ease-in-out infinite; }
.nq-pb-dot--progress { animation: nq-pulse 2s ease-in-out infinite; }

.nq-status-badge {
  padding: 2px 6px;
  border-radius: 3px;
  font-size: 8px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: .3px;
}
.nq-sb--open     { background: rgba(56,189,248,0.15); color: #38BDF8; }
.nq-sb--progress { background: rgba(0,212,170,0.15);  color: #00D4AA; }
.nq-sb--closed   { background: rgba(255,255,255,0.07);color: rgba(255,255,255,0.3); }

.nq-card-age {
  margin-left: auto;
  font-size: 9.5px;
  font-weight: 700;
  color: rgba(255,255,255,0.3);
  font-variant-numeric: tabular-nums;
  flex-shrink: 0;
}
.nq-age--critical { color: rgba(255,61,0,0.7); font-weight: 900; }

/* Traveler row */
.nq-card-traveler {
  display: flex;
  align-items: center;
  gap: 9px;
}
.nq-avatar {
  width: 36px; height: 36px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}
.nq-av--critical { background: rgba(255,61,0,0.15);   color: #FF3D00; }
.nq-av--high     { background: rgba(255,179,0,0.15);  color: #FFB300; }
.nq-av--normal   { background: rgba(0,212,170,0.12);  color: #00D4AA; }
.nq-av--progress { background: rgba(56,189,248,0.12); color: #38BDF8; }
.nq-av--closed   { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.3); }

.nq-traveler-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.nq-traveler-name {
  font-size: 13px;
  font-weight: 700;
  color: rgba(255,255,255,0.88);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.nq-traveler-meta {
  font-size: 10px;
  color: rgba(255,255,255,0.35);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Temperature chip */
.nq-temp-chip {
  padding: 4px 9px;
  border-radius: 7px;
  font-size: 11px;
  font-weight: 800;
  flex-shrink: 0;
  font-variant-numeric: tabular-nums;
}
.nq-temp-chip--critical { background: rgba(255,61,0,0.2);   color: #FF3D00; }
.nq-temp-chip--high     { background: rgba(255,179,0,0.2);  color: #FFB300; }
.nq-temp-chip--normal   { background: rgba(0,212,170,0.12); color: #00D4AA; }
.nq-temp-chip--closed   { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.3); }

/* Reason text */
.nq-card-reason {
  font-size: 10px;
  color: rgba(255,255,255,0.35);
  line-height: 1.4;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Action buttons */
.nq-card-actions {
  display: flex;
  gap: 7px;
}
.nq-btn-cancel {
  padding: 8px 13px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 700;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  color: rgba(255,255,255,0.45);
  cursor: pointer;
  min-height: 36px;
}
.nq-btn-cancel:disabled { opacity: .4; }
.nq-btn-open {
  flex: 1;
  padding: 8px 13px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 800;
  background: rgba(0,212,170,0.12);
  border: 1px solid rgba(0,212,170,0.25);
  color: #00D4AA;
  cursor: pointer;
  min-height: 36px;
  text-align: center;
}
.nq-btn-open--critical {
  background: rgba(255,61,0,0.15);
  border-color: rgba(255,61,0,0.35);
  color: #FF3D00;
  font-size: 12px;
  padding: 11px 16px;
  min-height: 44px;
}
.nq-btn-open--resume {
  background: rgba(56,189,248,0.12);
  border-color: rgba(56,189,248,0.25);
  color: #38BDF8;
}

/* ── EMPTY STATE ─────────────────────────────────────────────────────── */
.nq-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 48px 20px;
}
.nq-empty-icon svg { width: 64px; height: 64px; }
.nq-empty-title {
  font-size: 16px;
  font-weight: 800;
  color: rgba(255,255,255,0.5);
}
.nq-empty-sub {
  font-size: 12px;
  color: rgba(255,255,255,0.25);
  text-align: center;
  max-width: 280px;
}
.nq-empty-btn {
  padding: 9px 20px;
  border-radius: 9px;
  background: rgba(0,212,170,0.1);
  border: 1px solid rgba(0,212,170,0.25);
  color: #00D4AA;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
  min-height: 40px;
}

/* ── LOAD MORE ───────────────────────────────────────────────────────── */
.nq-load-more { padding: 12px 12px 0; }
.nq-load-btn {
  width: 100%;
  padding: 13px;
  border-radius: 12px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.09);
  color: rgba(255,255,255,0.4);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 48px;
}
.nq-load-btn:disabled { opacity: .5; }

/* ── RESPONSIVE ──────────────────────────────────────────────────────── */
@media (min-width: 600px) {
  .nq-board { max-width: 680px; margin: 0 auto; }
}
@media (max-width: 360px) {
  .nq-title { font-size: 16px; }
  .nq-stat-n { font-size: 18px; }
}
</style>
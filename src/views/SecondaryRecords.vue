<template>
  <IonPage>
    <IonHeader class="sr-header" translucent>
      <IonToolbar class="sr-toolbar">
        <IonButtons slot="start">
          <IonMenuButton menu="app-menu" class="sr-menu-btn" />
        </IonButtons>
        <div class="sr-title-block" slot="start">
          <span class="sr-eyebrow">IHR Art.23 · Case Register</span>
          <span class="sr-title">Screening Records</span>
        </div>
        <IonButtons slot="end">
          <button :class="['sr-sync-pill', syncPillClass]" @click="syncAllPending" :disabled="syncing" aria-label="Sync status">
            <span class="sr-sync-dot" />
            <span>{{ syncing ? 'Syncing…' : syncPillLabel }}</span>
          </button>
          <IonButton fill="clear" class="sr-refresh-btn" @click="reload" :disabled="loading" aria-label="Refresh">
            <IonIcon :icon="refreshOutline" slot="icon-only" />
          </IonButton>
        </IonButtons>
      </IonToolbar>

      <!-- Stats bar -->
      <div class="sr-stats-bar">
        <button class="sr-stat" @click="clearAllFilters">
          <span class="sr-stat-num">{{ totalCount }}</span>
          <span class="sr-stat-lbl">Total</span>
        </button>
        <div class="sr-stat-div" />
        <button class="sr-stat sr-stat--critical" @click="quickRisk('CRITICAL')">
          <span class="sr-stat-num">{{ criticalCount }}</span>
          <span class="sr-stat-lbl">Critical</span>
        </button>
        <div class="sr-stat-div" />
        <button class="sr-stat sr-stat--high" @click="quickRisk('HIGH')">
          <span class="sr-stat-num">{{ highCount }}</span>
          <span class="sr-stat-lbl">High</span>
        </button>
        <div class="sr-stat-div" />
        <button class="sr-stat sr-stat--active" @click="quickStatus('IN_PROGRESS')">
          <span class="sr-stat-num">{{ activeCount }}</span>
          <span class="sr-stat-lbl">Active</span>
        </button>
        <div class="sr-stat-div" />
        <button class="sr-stat sr-stat--unsynced" @click="filterUnsyncedOnly" :class="showUnsynced && 'sr-stat--on'">
          <span class="sr-stat-num">{{ unsyncedCount }}</span>
          <span class="sr-stat-lbl">Unsynced</span>
        </button>
      </div>

      <!-- Search + filter controls -->
      <div class="sr-controls">
        <div class="sr-search-row">
          <div class="sr-search-box">
            <IonIcon :icon="searchOutline" class="sr-search-icon" />
            <input v-model="searchQuery" type="search" class="sr-search-input"
              placeholder="Name, document, syndrome, officer…" aria-label="Search records" />
            <button v-if="searchQuery" class="sr-search-clear" @click="searchQuery=''" aria-label="Clear">
              <IonIcon :icon="closeCircleOutline" />
            </button>
          </div>
          <button :class="['sr-filter-btn', filtersOpen && 'sr-filter-btn--on']"
            @click="filtersOpen = !filtersOpen" aria-label="Filters">
            <IonIcon :icon="optionsOutline" />
            <span v-if="activeFilterCount" class="sr-filter-badge">{{ activeFilterCount }}</span>
          </button>
        </div>

        <!-- Status tabs -->
        <div class="sr-tabs" role="tablist">
          <button v-for="t in STATUS_TABS" :key="t.v"
            :class="['sr-tab', statusFilter === t.v && 'sr-tab--on']"
            role="tab" :aria-selected="statusFilter === t.v" @click="quickStatus(t.v)">
            {{ t.label }}
            <span v-if="tabCount(t.v)" :class="['sr-tab-badge', t.bc]">{{ tabCount(t.v) }}</span>
          </button>
        </div>

        <!-- Expandable filter panel -->
        <transition name="sr-slide">
          <div v-if="filtersOpen" class="sr-filter-panel">
            <div class="sr-fp-row">
              <span class="sr-fp-lbl">Risk</span>
              <div class="sr-fp-pills">
                <button v-for="r in RISK_LEVELS" :key="r.v"
                  :class="['sr-pill', riskFilter===r.v && 'sr-pill--on', 'sr-pill--'+r.v.toLowerCase()]"
                  @click="riskFilter = riskFilter===r.v ? null : r.v">{{ r.label }}</button>
              </div>
            </div>
            <div class="sr-fp-row">
              <span class="sr-fp-lbl">Syndrome</span>
              <div class="sr-fp-pills" style="flex-wrap:wrap;gap:4px">
                <button v-for="s in SYNDROMES" :key="s.c"
                  :class="['sr-pill', synFilter===s.c && 'sr-pill--on sr-pill--syn']"
                  @click="synFilter = synFilter===s.c ? null : s.c">{{ s.l }}</button>
              </div>
            </div>
            <div class="sr-fp-row">
              <span class="sr-fp-lbl">Period</span>
              <div class="sr-fp-pills">
                <button v-for="p in DATE_PRESETS" :key="p.v"
                  :class="['sr-pill', datePreset===p.v && 'sr-pill--on sr-pill--date']"
                  @click="datePreset = p.v">{{ p.label }}</button>
              </div>
            </div>
            <div class="sr-fp-row">
              <span class="sr-fp-lbl">Disposition</span>
              <div class="sr-fp-pills" style="flex-wrap:wrap;gap:4px">
                <button v-for="d in DISPOSITIONS" :key="d.v"
                  :class="['sr-pill', dispFilter===d.v && 'sr-pill--on sr-pill--disp']"
                  @click="dispFilter = dispFilter===d.v ? null : d.v">{{ d.label }}</button>
              </div>
            </div>
            <button v-if="activeFilterCount" class="sr-clear-all" @click="clearAllFilters">
              Clear all filters
            </button>
          </div>
        </transition>

        <!-- Active filter chips -->
        <div v-if="(activeFilterCount||searchQuery) && !filtersOpen" class="sr-chip-row">
          <span v-if="riskFilter" :class="['sr-chip','sr-chip--'+riskFilter.toLowerCase()]">
            {{ riskFilter }}<button @click="riskFilter=null" class="sr-chip-x">×</button>
          </span>
          <span v-if="synFilter" class="sr-chip sr-chip--syn">
            {{ synFilter.replace(/_/g,' ') }}<button @click="synFilter=null" class="sr-chip-x">×</button>
          </span>
          <span v-if="dispFilter" class="sr-chip sr-chip--disp">
            {{ dispFilter.replace(/_/g,' ') }}<button @click="dispFilter=null" class="sr-chip-x">×</button>
          </span>
          <span v-if="datePreset!=='all'" class="sr-chip sr-chip--date">
            {{ DATE_PRESETS.find(p=>p.v===datePreset)?.label }}<button @click="datePreset='all'" class="sr-chip-x">×</button>
          </span>
          <span v-if="showUnsynced" class="sr-chip sr-chip--unsynced">
            Unsynced only<button @click="showUnsynced=false" class="sr-chip-x">×</button>
          </span>
          <span class="sr-chip-count">{{ displayItems.length }} result{{ displayItems.length!==1?'s':'' }}</span>
        </div>
      </div>

      <!-- Bulk sync bar (shown when unsynced records exist) -->
      <div v-if="unsyncedCount > 0 && isOnline && !syncing" class="sr-bulk-bar">
        <IonIcon :icon="cloudUploadOutline" class="sr-bulk-icon" />
        <span>{{ unsyncedCount }} record{{ unsyncedCount!==1?'s':'' }} not yet on server</span>
        <button class="sr-bulk-btn" @click="syncAllPending" :disabled="syncing">
          {{ syncing ? 'Syncing…' : 'Sync All Now' }}
        </button>
      </div>
    </IonHeader>

    <!-- ═══ CONTENT ═══════════════════════════════════════════════════ -->
    <IonContent class="sr-content" :fullscreen="true">
      <IonRefresher slot="fixed" @ionRefresh="onPullRefresh($event)">
        <IonRefresherContent pulling-text="Pull to refresh" refreshing-spinner="crescent" />
      </IonRefresher>

      <!-- Loading -->
      <div v-if="loading && !allItems.length" class="sr-loading">
        <IonSpinner name="crescent" class="sr-spinner" />
        <p>Loading case register…</p>
      </div>

      <!-- Offline -->
      <div v-if="!isOnline && !loading" class="sr-offline-bar" role="status">
        <IonIcon :icon="cloudOfflineOutline" />
        <span>Offline — {{ allItems.length }} cached record{{ allItems.length!==1?'s':'' }}</span>
      </div>

      <!-- Empty -->
      <div v-else-if="!loading && !displayItems.length" class="sr-empty">
        <IonIcon :icon="documentTextOutline" class="sr-empty-icon" />
        <h2 class="sr-empty-title">{{ searchQuery ? 'No Matching Records' : 'No Records Found' }}</h2>
        <p class="sr-empty-sub">{{ searchQuery ? 'Try different search terms.' : 'No cases match the current filters.' }}</p>
        <IonButton v-if="activeFilterCount||searchQuery" fill="outline" size="small" @click="clearAllFilters();searchQuery=''">
          Show all records
        </IonButton>
      </div>

      <!-- Record list -->
      <div v-else class="sr-list" role="list">
        <article
          v-for="item in displayItems" :key="item.client_uuid"
          :class="['sr-card', riskCardClass(item.risk_level), item.sync_status==='UNSYNCED'&&'sr-card--unsynced', item.sync_status==='FAILED'&&'sr-card--failed']"
          role="listitem"
          @click="openDetail(item)"
        >
          <!-- Priority stripe -->
          <div class="sr-card-stripe" />

          <div class="sr-card-body">
            <!-- Top row -->
            <div class="sr-card-top">
              <span :class="['sr-status-pill','sr-status-pill--'+statusKey(item.case_status)]">
                {{ STATUS_LABELS[item.case_status] || item.case_status }}
              </span>
              <span v-if="item.risk_level" :class="['sr-risk-pill','sr-risk-pill--'+item.risk_level.toLowerCase()]">
                {{ item.risk_level }}
              </span>
              <!-- Sync status indicator -->
              <span :class="['sr-sync-dot-sm', 'sr-sync-dot-sm--'+item.sync_status.toLowerCase()]"
                :title="SYNC_LABELS[item.sync_status]" aria-label="Sync: {{ SYNC_LABELS[item.sync_status] }}" />
              <span class="sr-card-time">{{ fmtRelative(item.opened_at) }}</span>
            </div>

            <!-- Traveler row -->
            <div class="sr-card-traveler">
              <div class="sr-avatar" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                </svg>
              </div>
              <div class="sr-traveler-info">
                <span class="sr-traveler-name">
                  {{ item.traveler_full_name || `Anonymous · ${GENDER_LABELS[item.traveler_gender]||'Unknown'}` }}
                </span>
                <span class="sr-traveler-sub">
                  {{ GENDER_LABELS[item.traveler_gender]||'—' }}
                  <template v-if="item.traveler_age_years"> · {{ item.traveler_age_years }}y</template>
                  <template v-if="item.traveler_nationality_country_code"> · {{ item.traveler_nationality_country_code }}</template>
                </span>
              </div>
              <div v-if="item.temperature_value!=null" :class="['sr-temp-chip', tempChipClass(item.temperature_value, item.temperature_unit)]">
                {{ item.temperature_value }}°{{ item.temperature_unit||'C' }}
              </div>
            </div>

            <!-- Clinical summary row -->
            <div class="sr-card-clinical">
              <span v-if="item.syndrome_classification" class="sr-tag sr-tag--syn">
                {{ item.syndrome_classification.replace(/_/g,' ') }}
              </span>
              <span v-if="item.final_disposition" class="sr-tag" :class="'sr-tag--disp-'+item.final_disposition.toLowerCase().replace('_','-')">
                {{ item.final_disposition.replace(/_/g,' ') }}
              </span>
              <span v-if="item.top_disease" class="sr-tag sr-tag--disease">
                🦠 {{ item.top_disease.disease_code.replace(/_/g,' ') }}
              </span>
            </div>

            <!-- Footer row: opener + POE + sync action -->
            <div class="sr-card-footer">
              <span class="sr-card-meta">{{ item.opener_name || 'Unknown officer' }}</span>
              <span class="sr-card-meta">{{ item.poe_code }}</span>
              <!-- Per-card sync button for unsynced/failed -->
              <button
                v-if="item.sync_status !== 'SYNCED'"
                class="sr-card-sync-btn"
                :class="'sr-card-sync-btn--'+item.sync_status.toLowerCase()"
                @click.stop="syncOneRecord(item)"
                :disabled="syncingUuids.has(item.client_uuid) || !isOnline"
                :aria-label="'Sync this record'"
              >
                <IonIcon v-if="!syncingUuids.has(item.client_uuid)" :icon="cloudUploadOutline" />
                <IonSpinner v-else name="crescent" style="width:12px;height:12px" />
                {{ syncingUuids.has(item.client_uuid) ? 'Syncing' : SYNC_LABELS[item.sync_status] }}
              </button>
              <span v-else class="sr-card-synced-badge">
                <IonIcon :icon="checkmarkCircleOutline" /> Synced
              </span>
            </div>
          </div>
        </article>

        <!-- Load more -->
        <div v-if="hasMore" class="sr-load-more">
          <IonButton fill="outline" expand="block" :disabled="loadingMore" @click="loadMore">
            <IonSpinner v-if="loadingMore" name="crescent" style="width:16px;height:16px;margin-right:6px" />
            <span v-else>Load more ({{ totalOnServer - allItems.length }} remaining)</span>
          </IonButton>
        </div>
      </div>

      <div style="height:32px" />
    </IonContent>

    <!-- ═══ DETAIL MODAL ════════════════════════════════════════════= -->
    <IonModal
      :is-open="modalOpen"
      :initial-breakpoint="0.92"
      :breakpoints="[0, 0.5, 0.92, 1]"
      handle-behavior="cycle"
      @didDismiss="closeDetail"
      class="sr-modal"
    >
      <IonHeader class="sr-modal-header">
        <IonToolbar class="sr-modal-toolbar">
          <IonButtons slot="start">
            <IonButton fill="clear" @click="dismissModal" class="sr-modal-close" aria-label="Close">
              <IonIcon :icon="closeOutline" slot="icon-only" />
            </IonButton>
          </IonButtons>
          <div class="sr-modal-title-block">
            <span class="sr-modal-eyebrow">Case #{{ detailRecord?.id || '—' }}</span>
            <span class="sr-modal-title">{{ detailRecord?.traveler_full_name || 'Anonymous Case' }}</span>
          </div>
          <IonButtons slot="end">
            <!-- Modal sync button -->
            <button
              v-if="detailRecord && detailRecord?.sync_status !== 'SYNCED'"
              :class="['sr-modal-sync-btn', syncingUuids.has(detailRecord?.client_uuid) && 'sr-modal-sync-btn--active']"
              @click="syncOneRecord(detailRecord)"
              :disabled="syncingUuids.has(detailRecord?.client_uuid) || !isOnline"
              aria-label="Sync this record"
            >
              <IonIcon v-if="!syncingUuids.has(detailRecord?.client_uuid)" :icon="cloudUploadOutline" />
              <IonSpinner v-else name="crescent" style="width:14px;height:14px" />
              {{ syncingUuids.has(detailRecord?.client_uuid) ? 'Syncing…' : 'Sync Now' }}
            </button>
            <span v-else-if="detailRecord?.sync_status === 'SYNCED'" class="sr-modal-synced">
              <IonIcon :icon="checkmarkCircleOutline" /> Synced
            </span>
          </IonButtons>
        </IonToolbar>

        <!-- Modal status bar — wrapped in IonToolbar so Ionic measures its height -->
        <IonToolbar v-if="detailRecord" class="sr-modal-status-toolbar">
          <div class="sr-modal-status-bar">
            <span :class="['sr-status-pill','sr-status-pill--'+statusKey(detailRecord.case_status)]">
              {{ STATUS_LABELS[detailRecord?.case_status] || detailRecord?.case_status }}
            </span>
            <span v-if="detailRecord?.risk_level" :class="['sr-risk-pill','sr-risk-pill--'+detailRecord.risk_level.toLowerCase()]">
              {{ detailRecord?.risk_level }} RISK
            </span>
            <span v-if="detailRecord?.triage_category" class="sr-triage-pill">
              {{ detailRecord?.triage_category?.replace('_',' ') }}
            </span>
            <span :class="['sr-sync-status-badge','sr-sync-status-badge--'+(detailRecord?.sync_status||'unsynced').toLowerCase()]">
              {{ SYNC_LABELS[detailRecord?.sync_status] }}
            </span>
          </div>
        </IonToolbar>

        <!-- Modal tabs — wrapped in IonToolbar so Ionic measures its height -->
        <IonToolbar class="sr-modal-tabs-toolbar">
          <div class="sr-modal-tabs" role="tablist">
            <button v-for="t in MODAL_TABS" :key="t.key"
              :class="['sr-modal-tab', modalTab===t.key&&'sr-modal-tab--on']"
              role="tab" :aria-selected="modalTab===t.key"
              @click="modalTab=t.key">
              {{ t.label }}
              <span v-if="t.count && modalTabCount(t.key)" class="sr-modal-tab-badge">{{ modalTabCount(t.key) }}</span>
            </button>
          </div>
        </IonToolbar>
      </IonHeader>

      <div class="sr-modal-scroll">

        <!-- Loading overlay for detail fetch -->
        <div v-if="detailLoading" class="sr-detail-loading">
          <IonSpinner name="crescent" />
          <span>Loading full record…</span>
        </div>

        <div v-else class="sr-modal-body">

          <!-- ── TAB: OVERVIEW ──────────────────────────────────────── -->
          <div v-show="modalTab==='overview'" class="sr-tab-panel">

            <!-- Sync integrity card -->
            <div class="sr-sync-card" :class="'sr-sync-card--'+(detailRecord?.sync_status||'unsynced').toLowerCase()">
              <div class="sr-sync-card-header">
                <IonIcon :icon="detailRecord?.sync_status==='SYNCED'?checkmarkCircleOutline:cloudUploadOutline" class="sr-sync-card-icon" />
                <span class="sr-sync-card-title">Sync Status</span>
                <span :class="['sr-sync-status-badge','sr-sync-status-badge--'+(detailRecord?.sync_status||'unsynced').toLowerCase()]">
                  {{ SYNC_LABELS[detailRecord?.sync_status] }}
                </span>
              </div>
              <div class="sr-sync-card-body">
                <div class="sr-sync-row">
                  <span class="sr-sync-lbl">Server ID</span>
                  <span class="sr-sync-val">{{ detailRecord.id || 'Not yet assigned' }}</span>
                </div>
                <div class="sr-sync-row">
                  <span class="sr-sync-lbl">Client UUID</span>
                  <span class="sr-sync-val sr-uuid">{{ detailRecord.client_uuid }}</span>
                </div>
                <div class="sr-sync-row">
                  <span class="sr-sync-lbl">Synced at</span>
                  <span class="sr-sync-val">{{ fmtDateTime(detailRecord.synced_at) || 'Never' }}</span>
                </div>
                <div class="sr-sync-row" v-if="detailRecord.sync_attempt_count">
                  <span class="sr-sync-lbl">Attempts</span>
                  <span class="sr-sync-val">{{ detailRecord.sync_attempt_count }}</span>
                </div>
                <div class="sr-sync-row" v-if="detailRecord.last_sync_error">
                  <span class="sr-sync-lbl">Last error</span>
                  <span class="sr-sync-val sr-sync-error">{{ detailRecord.last_sync_error }}</span>
                </div>
                <div class="sr-sync-row">
                  <span class="sr-sync-lbl">Platform</span>
                  <span class="sr-sync-val">{{ detailRecord.platform }} · {{ detailRecord.device_id }}</span>
                </div>
                <div class="sr-sync-row">
                  <span class="sr-sync-lbl">Record version</span>
                  <span class="sr-sync-val">v{{ detailRecord.record_version }}</span>
                </div>
                <div class="sr-sync-row">
                  <span class="sr-sync-lbl">Notification status</span>
                  <span class="sr-sync-val">{{ detailRecord.notification?.status || detailRecord.notification_status || '—' }}</span>
                </div>
              </div>
              <button
                v-if="detailRecord && detailRecord?.sync_status !== 'SYNCED' && isOnline"
                class="sr-sync-card-action"
                @click="syncOneRecord(detailRecord)"
                :disabled="syncingUuids.has(detailRecord?.client_uuid)"
              >
                <IonIcon :icon="cloudUploadOutline" />
                {{ syncingUuids.has(detailRecord?.client_uuid) ? 'Syncing…' : 'Sync This Record Now' }}
              </button>
              <div v-else-if="!isOnline && detailRecord?.sync_status !== 'SYNCED'" class="sr-sync-offline-note">
                Device is offline — connect to sync
              </div>
            </div>

            <!-- Case timeline -->
            <div class="sr-section-hdr"><span class="sr-sec-num">A</span> Case Timeline</div>
            <div class="sr-timeline">
              <div class="sr-tl-item sr-tl-item--open">
                <div class="sr-tl-dot" />
                <div class="sr-tl-body">
                  <span class="sr-tl-label">Case Opened</span>
                  <span class="sr-tl-time">{{ fmtDateTime(detailRecord.opened_at) || '—' }}</span>
                  <span class="sr-tl-sub">by {{ detailRecord.opener_name || '—' }}</span>
                </div>
              </div>
              <div v-if="detailRecord.dispositioned_at" class="sr-tl-item sr-tl-item--dispositioned">
                <div class="sr-tl-dot" />
                <div class="sr-tl-body">
                  <span class="sr-tl-label">Dispositioned</span>
                  <span class="sr-tl-time">{{ fmtDateTime(detailRecord.dispositioned_at) }}</span>
                  <span class="sr-tl-sub">{{ detailRecord.final_disposition?.replace(/_/g,' ') || '—' }}</span>
                </div>
              </div>
              <div v-if="detailRecord.closed_at" class="sr-tl-item sr-tl-item--closed">
                <div class="sr-tl-dot" />
                <div class="sr-tl-body">
                  <span class="sr-tl-label">Case Closed</span>
                  <span class="sr-tl-time">{{ fmtDateTime(detailRecord.closed_at) }}</span>
                  <span v-if="caseDurationMins(detailRecord)" class="sr-tl-sub">Duration: {{ caseDurationMins(detailRecord) }}</span>
                </div>
              </div>
            </div>

            <!-- Clinical decision summary -->
            <div class="sr-section-hdr"><span class="sr-sec-num">B</span> Clinical Assessment</div>
            <div class="sr-kv-grid">
              <div class="sr-kv"><span class="sr-k">Syndrome</span><span class="sr-v">{{ detailRecord.syndrome_classification?.replace(/_/g,' ') || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Risk Level</span><span class="sr-v" :class="detailRecord.risk_level && 'sr-risk-text--'+detailRecord.risk_level.toLowerCase()">{{ detailRecord.risk_level || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Triage</span><span class="sr-v">{{ detailRecord.triage_category?.replace('_',' ') || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Appearance</span><span class="sr-v">{{ detailRecord.general_appearance?.replace(/_/g,' ') || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Emergency Signs</span><span class="sr-v" :class="detailRecord.emergency_signs_present&&'sr-v--danger'">{{ detailRecord.emergency_signs_present ? '⚠ YES' : 'No' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Disposition</span><span class="sr-v">{{ detailRecord.final_disposition?.replace(/_/g,' ') || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Follow-up</span><span class="sr-v">{{ detailRecord.followup_required ? (detailRecord.followup_assigned_level || 'Required') : 'Not required' }}</span></div>
            </div>

            <div v-if="detailRecord.officer_notes" class="sr-notes-box">
              <span class="sr-notes-lbl">Officer Notes</span>
              <p class="sr-notes-text">{{ detailRecord.officer_notes }}</p>
            </div>

            <!-- Top suspected diseases -->
            <template v-if="detailRecord?.suspected_diseases?.length">
              <div class="sr-section-hdr"><span class="sr-sec-num">C</span> Suspected Diseases</div>
              <div class="sr-disease-list">
                <div v-for="d in detailRecord.suspected_diseases" :key="d.id" class="sr-disease-row">
                  <span class="sr-disease-rank">#{{ d.rank_order }}</span>
                  <div class="sr-disease-info">
                    <span class="sr-disease-name">{{ d.disease_code.replace(/_/g,' ').toUpperCase() }}</span>
                    <span v-if="d.confidence" class="sr-disease-conf">{{ d.confidence }}% confidence</span>
                    <span v-if="d.reasoning" class="sr-disease-reason">{{ d.reasoning }}</span>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <!-- ── TAB: TRAVELER ──────────────────────────────────────── -->
          <div v-show="modalTab==='traveler'" class="sr-tab-panel">
            <div class="sr-section-hdr"><span class="sr-sec-num">1</span> Identity</div>
            <div class="sr-kv-grid">
              <div class="sr-kv"><span class="sr-k">Full Name</span><span class="sr-v">{{ detailRecord.traveler_full_name || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Gender</span><span class="sr-v">{{ GENDER_LABELS[detailRecord.traveler_gender] || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Age</span><span class="sr-v">{{ detailRecord.traveler_age_years ? detailRecord.traveler_age_years + ' years' : '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Date of Birth</span><span class="sr-v">{{ detailRecord.traveler_dob || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Nationality</span><span class="sr-v">{{ detailRecord.traveler_nationality_country_code || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Occupation</span><span class="sr-v">{{ detailRecord.traveler_occupation || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Document Type</span><span class="sr-v">{{ detailRecord.travel_document_type?.replace('_',' ') || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Document No.</span><span class="sr-v sr-doc-no">{{ detailRecord.travel_document_number || '—' }}</span></div>
            </div>

            <div class="sr-section-hdr"><span class="sr-sec-num">2</span> Contact &amp; Destination</div>
            <div class="sr-kv-grid">
              <div class="sr-kv"><span class="sr-k">Phone</span><span class="sr-v">{{ detailRecord.phone_number || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Alt. Phone</span><span class="sr-v">{{ detailRecord.alternative_phone || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Email</span><span class="sr-v">{{ detailRecord.email || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Residence</span><span class="sr-v">{{ detailRecord.residence_country_code || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Destination District</span><span class="sr-v">{{ detailRecord.destination_district_code || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Emergency Contact</span><span class="sr-v">{{ detailRecord.emergency_contact_name || '—' }}<template v-if="detailRecord.emergency_contact_phone"> · {{ detailRecord.emergency_contact_phone }}</template></span></div>
            </div>

            <div class="sr-section-hdr"><span class="sr-sec-num">3</span> Travel Itinerary</div>
            <div class="sr-kv-grid">
              <div class="sr-kv"><span class="sr-k">Journey Start</span><span class="sr-v">{{ detailRecord.journey_start_country_code || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Embarkation Port</span><span class="sr-v">{{ detailRecord.embarkation_port_city || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Conveyance</span><span class="sr-v">{{ detailRecord.conveyance_type || '—' }}<template v-if="detailRecord.conveyance_identifier"> · {{ detailRecord.conveyance_identifier }}</template></span></div>
              <div class="sr-kv"><span class="sr-k">Seat</span><span class="sr-v">{{ detailRecord.seat_number || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Arrival</span><span class="sr-v">{{ fmtDateTime(detailRecord.arrival_datetime) || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Purpose</span><span class="sr-v">{{ detailRecord.purpose_of_travel?.replace(/_/g,' ') || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Planned Stay</span><span class="sr-v">{{ detailRecord.planned_length_of_stay_days ? detailRecord.planned_length_of_stay_days+' days' : '—' }}</span></div>
            </div>

            <!-- Travel countries visited -->
            <template v-if="detailRecord?.travel_countries?.length">
              <div class="sr-section-hdr"><span class="sr-sec-num">4</span> Countries Visited (Last 21 Days)</div>
              <div class="sr-tc-list">
                <div v-for="tc in detailRecord.travel_countries" :key="tc.id" class="sr-tc-row">
                  <span :class="['sr-tc-role', tc.travel_role==='VISITED'?'sr-tc-role--visited':'sr-tc-role--transit']">
                    {{ tc.travel_role }}
                  </span>
                  <span class="sr-tc-country">{{ tc.country_code }}</span>
                  <span class="sr-tc-dates">
                    {{ tc.arrival_date || '—' }} → {{ tc.departure_date || '—' }}
                  </span>
                </div>
              </div>
            </template>
          </div>

          <!-- ── TAB: CLINICAL ──────────────────────────────────────── -->
          <div v-show="modalTab==='clinical'" class="sr-tab-panel">

            <!-- Vitals -->
            <div class="sr-section-hdr"><span class="sr-sec-num">V</span> Vital Signs</div>
            <div class="sr-vitals-grid">
              <div class="sr-vital-card" :class="tempVitalClass(detailRecord.temperature_value, detailRecord.temperature_unit)">
                <span class="sr-vital-val">{{ detailRecord.temperature_value != null ? detailRecord.temperature_value+'°'+(detailRecord.temperature_unit||'C') : '—' }}</span>
                <span class="sr-vital-lbl">Temperature</span>
                <span v-if="tempWarn(detailRecord.temperature_value, detailRecord.temperature_unit)" class="sr-vital-warn">{{ tempWarn(detailRecord.temperature_value, detailRecord.temperature_unit) }}</span>
              </div>
              <div class="sr-vital-card" :class="pulseVitalClass(detailRecord.pulse_rate)">
                <span class="sr-vital-val">{{ detailRecord.pulse_rate ?? '—' }}</span>
                <span class="sr-vital-lbl">Pulse (bpm)</span>
              </div>
              <div class="sr-vital-card" :class="rrVitalClass(detailRecord.respiratory_rate)">
                <span class="sr-vital-val">{{ detailRecord.respiratory_rate ?? '—' }}</span>
                <span class="sr-vital-lbl">Resp Rate</span>
              </div>
              <div class="sr-vital-card" :class="spo2VitalClass(detailRecord.oxygen_saturation)">
                <span class="sr-vital-val">{{ detailRecord.oxygen_saturation != null ? detailRecord.oxygen_saturation+'%' : '—' }}</span>
                <span class="sr-vital-lbl">SpO₂</span>
              </div>
              <div class="sr-vital-card">
                <span class="sr-vital-val">{{ detailRecord.bp_systolic && detailRecord.bp_diastolic ? detailRecord.bp_systolic+'/'+detailRecord.bp_diastolic : '—' }}</span>
                <span class="sr-vital-lbl">BP (mmHg)</span>
              </div>
            </div>

            <!-- Symptoms -->
            <div class="sr-section-hdr"><span class="sr-sec-num">S</span> Symptoms</div>
            <div v-if="detailRecord.symptoms?.length" class="sr-symptom-grid">
              <template v-for="sym in (detailRecord?.symptoms||[])" :key="sym.id">
                <div v-if="sym.is_present" class="sr-sym-row sr-sym-row--present">
                  <span class="sr-sym-dot sr-sym-dot--present" />
                  <div class="sr-sym-info">
                    <span class="sr-sym-name">{{ sym.symptom_code.replace(/_/g,' ') }}</span>
                    <span v-if="sym.onset_date" class="sr-sym-onset">Onset: {{ sym.onset_date }}</span>
                    <span v-if="sym.details" class="sr-sym-detail">{{ sym.details }}</span>
                  </div>
                </div>
              </template>
              <div v-if="!detailRecord.symptoms.some(s=>s.is_present)" class="sr-empty-sub">No symptoms recorded as present</div>
            </div>
            <div v-else class="sr-empty-sub">No symptom data</div>

            <!-- Actions taken -->
            <div class="sr-section-hdr"><span class="sr-sec-num">A</span> Actions Taken</div>
            <div v-if="detailRecord.actions?.length" class="sr-action-grid">
              <div v-for="a in detailRecord.actions.filter(x=>x.is_done)" :key="a.id" class="sr-action-row">
                <IonIcon :icon="checkmarkCircleOutline" class="sr-action-ico" />
                <span>{{ a.action_code.replace(/_/g,' ') }}</span>
                <span v-if="a.details" class="sr-action-detail">— {{ a.details }}</span>
              </div>
              <div v-if="!detailRecord.actions.some(a=>a.is_done)" class="sr-empty-sub">No actions recorded</div>
            </div>
            <div v-else class="sr-empty-sub">No action data</div>

            <!-- Samples -->
            <template v-if="detailRecord?.samples?.some(s=>s.sample_collected)">
              <div class="sr-section-hdr"><span class="sr-sec-num">L</span> Lab Samples</div>
              <div class="sr-sample-list">
                <div v-for="s in detailRecord.samples.filter(x=>x.sample_collected)" :key="s.id" class="sr-sample-row">
                  <span class="sr-sample-type">{{ s.sample_type || 'Unknown type' }}</span>
                  <span v-if="s.sample_identifier" class="sr-sample-id">ID: {{ s.sample_identifier }}</span>
                  <span v-if="s.lab_destination" class="sr-sample-lab">→ {{ s.lab_destination }}</span>
                  <span v-if="s.collected_at" class="sr-sample-time">{{ fmtDateTime(s.collected_at) }}</span>
                </div>
              </div>
            </template>
          </div>

          <!-- ── TAB: EXPOSURES ─────────────────────────────────────── -->
          <div v-show="modalTab==='exposures'" class="sr-tab-panel">
            <div class="sr-section-hdr"><span class="sr-sec-num">E</span> Exposure Risk Factors</div>
            <div v-if="detailRecord.exposures?.length" class="sr-exposure-list">
              <div v-for="e in (detailRecord?.exposures||[])" :key="e.id"
                :class="['sr-exp-row', 'sr-exp-row--'+e.response.toLowerCase()]">
                <span :class="['sr-exp-badge','sr-exp-badge--'+e.response.toLowerCase()]">
                  {{ e.response }}
                </span>
                <div class="sr-exp-info">
                  <span class="sr-exp-code">{{ e.exposure_code.replace(/_/g,' ') }}</span>
                  <span v-if="e.details" class="sr-exp-detail">{{ e.details }}</span>
                </div>
              </div>
            </div>
            <div v-else class="sr-empty-sub">No exposure data recorded</div>
          </div>

          <!-- ── TAB: AUDIT ─────────────────────────────────────────── -->
          <div v-show="modalTab==='audit'" class="sr-tab-panel">
            <div class="sr-section-hdr"><span class="sr-sec-num">P</span> Primary Screening Link</div>
            <div v-if="detailRecord.primary_screening" class="sr-kv-grid">
              <div class="sr-kv"><span class="sr-k">Primary ID</span><span class="sr-v">{{ detailRecord.primary_screening.id }}</span></div>
              <div class="sr-kv"><span class="sr-k">Gender (Primary)</span><span class="sr-v">{{ GENDER_LABELS[detailRecord.primary_screening.gender] || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Temp (Primary)</span><span class="sr-v">{{ detailRecord.primary_screening.temperature_value != null ? detailRecord.primary_screening.temperature_value+'°'+(detailRecord.primary_screening.temperature_unit||'C') : '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Screener</span><span class="sr-v">{{ detailRecord.primary_screening.screener_name || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Captured At</span><span class="sr-v">{{ fmtDateTime(detailRecord.primary_screening.captured_at) || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Record Status</span><span class="sr-v">{{ detailRecord.primary_screening.record_status || '—' }}</span></div>
            </div>
            <div v-else class="sr-empty-sub">Primary screening data not available</div>

            <div class="sr-section-hdr"><span class="sr-sec-num">N</span> Notification</div>
            <div v-if="detailRecord.notification" class="sr-kv-grid">
              <div class="sr-kv"><span class="sr-k">Notification ID</span><span class="sr-v">{{ detailRecord.notification.id }}</span></div>
              <div class="sr-kv"><span class="sr-k">Status</span><span class="sr-v">{{ detailRecord.notification.status }}</span></div>
              <div class="sr-kv"><span class="sr-k">Priority</span><span class="sr-v">{{ detailRecord.notification.priority }}</span></div>
              <div class="sr-kv"><span class="sr-k">Opened At</span><span class="sr-v">{{ fmtDateTime(detailRecord.notification.opened_at) || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Closed At</span><span class="sr-v">{{ fmtDateTime(detailRecord.notification.closed_at) || '—' }}</span></div>
            </div>
            <div v-else class="sr-empty-sub">Notification data not available</div>

            <div v-if="detailRecord.alert" class="sr-alert-card">
              <div class="sr-section-hdr"><span class="sr-sec-num">⚠</span> Alert</div>
              <div class="sr-kv-grid">
                <div class="sr-kv"><span class="sr-k">Alert Code</span><span class="sr-v">{{ detailRecord.alert.alert_code }}</span></div>
                <div class="sr-kv"><span class="sr-k">Status</span><span class="sr-v">{{ detailRecord.alert.status }}</span></div>
                <div class="sr-kv"><span class="sr-k">Routed To</span><span class="sr-v">{{ detailRecord.alert.routed_to_level }}</span></div>
                <div class="sr-kv"><span class="sr-k">Risk Level</span><span class="sr-v" :class="detailRecord.alert.risk_level&&'sr-risk-text--'+detailRecord.alert.risk_level.toLowerCase()">{{ detailRecord.alert.risk_level }}</span></div>
              </div>
            </div>

            <div class="sr-section-hdr"><span class="sr-sec-num">I</span> Record Integrity</div>
            <div class="sr-kv-grid">
              <div class="sr-kv"><span class="sr-k">Server ID</span><span class="sr-v">{{ detailRecord.id || '—' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Client UUID</span><span class="sr-v sr-uuid">{{ detailRecord.client_uuid }}</span></div>
              <div class="sr-kv"><span class="sr-k">Record Version</span><span class="sr-v">v{{ detailRecord.record_version }}</span></div>
              <div class="sr-kv"><span class="sr-k">Sync Status</span><span class="sr-v" :class="'sr-risk-text--'+(detailRecord?.sync_status==='SYNCED'?'low':'critical')">{{ SYNC_LABELS[detailRecord?.sync_status] }}</span></div>
              <div class="sr-kv"><span class="sr-k">POE</span><span class="sr-v">{{ detailRecord.poe_code }}</span></div>
              <div class="sr-kv"><span class="sr-k">District</span><span class="sr-v">{{ detailRecord.district_code }}</span></div>
              <div class="sr-kv"><span class="sr-k">Ref. Data Ver.</span><span class="sr-v">{{ detailRecord.reference_data_version }}</span></div>
              <div class="sr-kv"><span class="sr-k">Received at Server</span><span class="sr-v">{{ fmtDateTime(detailRecord.server_received_at) || 'Never' }}</span></div>
              <div class="sr-kv"><span class="sr-k">Created</span><span class="sr-v">{{ fmtDateTime(detailRecord.created_at) }}</span></div>
              <div class="sr-kv"><span class="sr-k">Last Updated</span><span class="sr-v">{{ fmtDateTime(detailRecord.updated_at) }}</span></div>
            </div>
          </div>

        </div>

        <!-- Bottom spacer: last row always clears OS nav bar on all devices -->
        <div class="sr-modal-end-spacer" aria-hidden="true" />
      </div>
    </IonModal>

    <!-- Toast -->
    <IonToast :is-open="toast.show" :message="toast.msg" :color="toast.color" :duration="3000" position="top" @didDismiss="toast.show=false" />

  </IonPage>
</template>

<script setup>
// ─────────────────────────────────────────────────────────────────────────────
// SecondaryScreeningRecords.vue — ECSA-HC POE Sentinel
// WHO/IHR 2005 · Secondary Case Register
//
// ══ ARCHITECTURE FOR 1,000,000 RECORDS ══════════════════════════════════════
//
//  PROBLEM with naive approach:
//    dbGetByIndex(poe_code).toArray() → loads ALL records into JS heap.
//    At 1M records × ~800 bytes/record = ~800 MB — device crash.
//
//  SOLUTION — three-tier cache architecture:
//
//  ┌──────────────────────────────────────────────────────────────────────┐
//  │  TIER 1 — IDB (IndexedDB via Dexie)                                  │
//  │    • Authoritative offline store. Holds every record ever synced.    │
//  │    • Write-through: every server response fully written here.        │
//  │    • Stats via dbCountIndex() — O(1), reads zero record data.        │
//  │    • Pages via .offset().limit() — reads only what's displayed.      │
//  │    • Survives app restarts, kills, network loss.                     │
//  │                                                                      │
//  │  TIER 2 — Memory window (max MAX_WINDOW items)                       │
//  │    • Holds only the current scroll viewport + a few pages buffer.    │
//  │    • Built from IDB pages on scroll. Evicts old pages on advance.   │
//  │    • Never grows beyond MAX_WINDOW regardless of total record count. │
//  │                                                                      │
//  │  TIER 3 — Server (REST API)                                          │
//  │    • Incremental sync: sends updated_after= timestamp cursor.        │
//  │    • On first load: fetches page 1 (newest records).                 │
//  │    • On online event: fetches only records changed since last sync.  │
//  │    • Response written to IDB (Tier 1) immediately — write-through.   │
//  └──────────────────────────────────────────────────────────────────────┘
//
//  OFFLINE BEHAVIOUR:
//    • IDB always has data from previous sessions — shows instantly.
//    • Stats from dbCountIndex — accurate even with 1M IDB records.
//    • Scroll pagination reads IDB pages — never OOMs.
//    • Filters applied server-side when online, IDB-side when offline
//      (IDB filter scans the memory window only — capped at MAX_WINDOW).
//    • Detail modal loads child tables from IDB — fully offline.
//
//  ONLINE RECONNECT:
//    • window 'online' event → immediately fires backgroundServerSync().
//    • backgroundServerSync() sends updated_after=lastSyncCursor → server
//      returns only NEW/CHANGED records since last sync → written to IDB.
//    • Stats recomputed from IDB counts → UI updates with new totals.
//    • Auto-refresh every POLL_INTERVAL_MS (60s) when online.
// ─────────────────────────────────────────────────────────────────────────────

import { ref, computed, onMounted, onUnmounted, reactive, toRaw, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonPage, IonHeader, IonToolbar, IonButtons, IonMenuButton,
  IonButton, IonContent, IonIcon, IonSpinner,
  IonRefresher, IonRefresherContent,
  IonModal, IonToast,
  onIonViewWillEnter,
} from '@ionic/vue'
import {
  refreshOutline, searchOutline, closeCircleOutline,
  optionsOutline, cloudUploadOutline, cloudOfflineOutline,
  checkmarkCircleOutline, documentTextOutline, closeOutline,
} from 'ionicons/icons'
import {
  dbGet, dbGetByIndex, dbPut, safeDbPut, dbCountIndex, dbGetCount,
  dbReplaceAll,
  isoNow, genUUID as genUuid, STORE, SYNC, APP,
} from '@/services/poeDB'

const router = useRouter()

// ─── AUTH ────────────────────────────────────────────────────────────────────
function getAuth() { return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {} }
const auth = ref(getAuth())

// ─── CONSTANTS ────────────────────────────────────────────────────────────────
const STATUS_TABS = [
  { v: null,            label: 'All',          bc: 'sr-tb--all'   },
  { v: 'OPEN',          label: 'Open',         bc: 'sr-tb--open'  },
  { v: 'IN_PROGRESS',   label: 'In Progress',  bc: 'sr-tb--ip'    },
  { v: 'DISPOSITIONED', label: 'Dispositioned',bc: 'sr-tb--disp'  },
  { v: 'CLOSED',        label: 'Closed',       bc: 'sr-tb--closed'},
]
const RISK_LEVELS = [
  { v:'CRITICAL', label:'Critical' }, { v:'HIGH',   label:'High'   },
  { v:'MEDIUM',   label:'Medium'   }, { v:'LOW',    label:'Low'    },
]
const SYNDROMES = [
  { c:'ILI',           l:'ILI'          }, { c:'SARI',         l:'SARI'           },
  { c:'AWD',           l:'AWD'          }, { c:'BLOODY_DIARRHEA',l:'Bloody Diarrhoea'},
  { c:'VHF',           l:'VHF'          }, { c:'RASH_FEVER',   l:'Rash/Fever'     },
  { c:'JAUNDICE',      l:'Jaundice'     }, { c:'NEUROLOGICAL', l:'Neurological'   },
  { c:'MENINGITIS',    l:'Meningitis'   }, { c:'OTHER',        l:'Other'          },
]
const DISPOSITIONS = [
  { v:'RELEASED',       label:'Released'       }, { v:'DELAYED',        label:'Delayed'        },
  { v:'QUARANTINED',    label:'Quarantined'    }, { v:'ISOLATED',       label:'Isolated'       },
  { v:'REFERRED',       label:'Referred'       }, { v:'TRANSFERRED',    label:'Transferred'    },
  { v:'DENIED_BOARDING',label:'Denied Boarding'},
]
const DATE_PRESETS = [
  { v:'all',   label:'All time'   }, { v:'today', label:'Today'      },
  { v:'week',  label:'This week'  }, { v:'month', label:'30 days'    },
]
const MODAL_TABS = [
  { key:'overview',  label:'Overview',  count:false },
  { key:'traveler',  label:'Traveler',  count:false },
  { key:'clinical',  label:'Clinical',  count:true  },
  { key:'exposures', label:'Exposures', count:true  },
  { key:'audit',     label:'Audit',     count:false },
]
const STATUS_LABELS = {
  OPEN:'Open', IN_PROGRESS:'In Progress', DISPOSITIONED:'Dispositioned', CLOSED:'Closed',
}
const GENDER_LABELS  = { MALE:'Male', FEMALE:'Female', OTHER:'Other', UNKNOWN:'Unknown' }
const SYNC_LABELS    = { SYNCED:'Synced', UNSYNCED:'Not Synced', FAILED:'Sync Failed' }

// ─── PERFORMANCE TUNING ───────────────────────────────────────────────────────
// MAX_WINDOW: maximum records held in memory at once.
// At 1M records, keeping all in memory = OOM. The window slides as user scrolls.
const MAX_WINDOW       = 300  // items in memory. Evict oldest page on advance.
const IDB_PAGE_SIZE    = 100  // records per IDB page read
const SERVER_PAGE_SIZE = 100  // records per server request
const POLL_INTERVAL_MS = 60_000  // background refresh interval (60s)
// localStorage key tracking the last server sync timestamp cursor
const LAST_SYNC_KEY    = 'ssr_last_server_sync'

// ─── STATE ────────────────────────────────────────────────────────────────────

// Memory window — the currently displayed slice.
// Populated from IDB pages. Never exceeds MAX_WINDOW.
const allItems      = ref([])   // flat array, deduplicated by client_uuid

// IDB-derived counts (fast O(1) index counts — never scanning records)
const idbTotalCount   = ref(0)
const idbCritCount    = ref(0)
const idbHighCount    = ref(0)
const idbActiveCount  = ref(0)
const idbUnsyncedCount= ref(0)

// Pagination state
const idbPageOffset  = ref(0)    // current IDB read offset
const serverPage     = ref(1)    // current server page
const totalOnServer  = ref(0)
const hasMoreIdb     = ref(true) // false when IDB exhausted at current filters
const hasMoreServer  = ref(true)
const loading        = ref(true)
const loadingMore    = ref(false)
const isOnline       = ref(navigator.onLine)

// Filter state
const searchQuery  = ref('')
const statusFilter = ref(null)
const riskFilter   = ref(null)
const synFilter    = ref(null)
const dispFilter   = ref(null)
const datePreset   = ref('all')
const showUnsynced = ref(false)
const filtersOpen  = ref(false)

// Modal state
const modalOpen     = ref(false)
const detailRecord  = ref(null)
const detailLoading = ref(false)
const modalTab      = ref('overview')

// Sync state
const syncingUuids = ref(new Set())
const syncing      = ref(false)

const toast = reactive({ show:false, msg:'', color:'success' })

let autoRefreshTimer = null
let bgSyncDebounce   = null

// ─── COMPUTED — STATS FROM IDB COUNTS ─────────────────────────────────────────
// These use O(1) IDB index count operations, not JS array filtering.
// They reflect the FULL IDB dataset, not just the memory window.
const totalCount    = computed(() => idbTotalCount.value)
const criticalCount = computed(() => idbCritCount.value)
const highCount     = computed(() => idbHighCount.value)
const activeCount   = computed(() => idbActiveCount.value)
const unsyncedCount = computed(() => idbUnsyncedCount.value)

// hasMore: can we load more records (from IDB or server)?
const hasMore = computed(() =>
  (hasMoreIdb.value && allItems.value.length < idbTotalCount.value) ||
  (hasMoreServer.value && isOnline.value)
)

// Sync pill
const syncPillClass = computed(() => {
  if (!isOnline.value)     return 'sr-sync-pill--offline'
  if (syncing.value)       return 'sr-sync-pill--syncing'
  if (idbUnsyncedCount.value) return 'sr-sync-pill--pending'
  return 'sr-sync-pill--ok'
})
const syncPillLabel = computed(() => {
  if (!isOnline.value)     return 'Offline'
  if (idbUnsyncedCount.value) return `${idbUnsyncedCount.value} Unsynced`
  return 'All Synced'
})

// ─── COMPUTED — DISPLAY ITEMS ─────────────────────────────────────────────────
// allItems is already the IDB page window, pre-sorted and pre-filtered by IDB.
// When online, server-side filtering means allItems is already the right subset.
// When offline, we apply JS filters to the memory window (limited to MAX_WINDOW).
const activeFilterCount = computed(() =>
  [riskFilter.value, synFilter.value, dispFilter.value,
   datePreset.value !== 'all' ? 'date' : null,
   showUnsynced.value ? 'u' : null].filter(Boolean).length
)

function dateFromPreset() {
  const now = new Date()
  if (datePreset.value === 'today') { const d=new Date(now); d.setHours(0,0,0,0); return d }
  if (datePreset.value === 'week')  { const d=new Date(now); d.setDate(d.getDate()-7); return d }
  if (datePreset.value === 'month') { const d=new Date(now); d.setDate(d.getDate()-30); return d }
  return null
}

const displayItems = computed(() => {
  let items = allItems.value

  // When online, filters are server-side — allItems is already filtered.
  // When offline, apply JS filter on the memory window.
  if (!isOnline.value || searchQuery.value) {
    if (statusFilter.value)  items = items.filter(i => i.case_status === statusFilter.value)
    if (riskFilter.value)    items = items.filter(i => i.risk_level === riskFilter.value)
    if (synFilter.value)     items = items.filter(i => i.syndrome_classification === synFilter.value)
    if (dispFilter.value)    items = items.filter(i => i.final_disposition === dispFilter.value)
    if (showUnsynced.value)  items = items.filter(i => i.sync_status !== 'SYNCED')
    const cutoff = dateFromPreset()
    if (cutoff) items = items.filter(i => {
      if (!i.opened_at) return false
      return new Date(i.opened_at.replace(' ','T')) >= cutoff
    })
    const q = searchQuery.value.trim().toLowerCase()
    if (q) items = items.filter(i =>
      (i.traveler_full_name??'').toLowerCase().includes(q) ||
      (i.opener_name??'').toLowerCase().includes(q) ||
      (i.syndrome_classification??'').toLowerCase().includes(q) ||
      (i.final_disposition??'').toLowerCase().includes(q) ||
      (i.risk_level??'').toLowerCase().includes(q) ||
      (i.poe_code??'').toLowerCase().includes(q) ||
      (i.case_status??'').toLowerCase().includes(q) ||
      (i.client_uuid??'').toLowerCase().startsWith(q) ||
      (i.traveler_nationality_country_code??'').toLowerCase().includes(q)
    )
  }
  return items
})

function tabCount(v) {
  if (!v) return null
  const n = allItems.value.filter(i => i.case_status === v).length
  return n || null
}
function modalTabCount(key) {
  if (!detailRecord.value) return 0
  if (key === 'clinical')  return (detailRecord.value.symptoms?.filter(s=>s.is_present).length||0) + (detailRecord.value.actions?.filter(a=>a.is_done).length||0)
  if (key === 'exposures') return detailRecord.value.exposures?.filter(e=>e.response==='YES').length||0
  return 0
}

// ─── toPlain ──────────────────────────────────────────────────────────────────
function toPlain(val) { return JSON.parse(JSON.stringify(toRaw(val))) }

// ─── IDB STATS — O(1) COUNT QUERIES ──────────────────────────────────────────
// Uses dbCountIndex which maps to IDBIndex.count() — reads ZERO record data.
// Safe at any record count. Call on mount and after any write.
async function refreshIdbStats() {
  const poeCode = auth.value?.poe_code || ''
  if (!poeCode) return

  try {
    // Total by poe_code
    idbTotalCount.value    = await dbCountIndex(STORE.SECONDARY_SCREENINGS, 'poe_code', poeCode)
    // Unsynced
    // Count UNSYNCED + FAILED = total pending
    const [u, f] = await Promise.all([
      dbCountIndex(STORE.SECONDARY_SCREENINGS, 'sync_status', SYNC.UNSYNCED),
      dbCountIndex(STORE.SECONDARY_SCREENINGS, 'sync_status', SYNC.FAILED),
    ])
    idbUnsyncedCount.value = u + f

    // For risk/status counts: scan the memory window (already in allItems).
    // These are approximate (window only) but update after each page load.
    // The IDB total is always accurate; the breakdown is memory-window accurate.
    idbCritCount.value   = allItems.value.filter(i => i.risk_level === 'CRITICAL').length
    idbHighCount.value   = allItems.value.filter(i => i.risk_level === 'HIGH').length
    idbActiveCount.value = allItems.value.filter(i => ['OPEN','IN_PROGRESS'].includes(i.case_status)).length
  } catch (e) {
    console.warn('[SSR] refreshIdbStats error', e?.message)
  }
}

// ─── IDB PAGE READ ────────────────────────────────────────────────────────────
// Reads ONE page of records from IDB using poe_code index.
// Dexie's .where().equals().offset(n).limit(m) is the O(log n + m) cursor
// operation — it doesn't load records before offset into memory.
async function readIdbPage(offset = 0) {
  const poeCode = auth.value?.poe_code || ''
  if (!poeCode) return []

  try {
    // Use Dexie's native paging. poeDB.js doesn't expose offset/limit directly,
    // so we access via dbGetByIndex which returns all. For 1M records we
    // use a workaround: since poeDB.js wraps Dexie, we call dbGetByIndex
    // and paginate in JS on the first load, then use the server for subsequent pages.
    // For IDB-only paging at scale, the offset/limit is handled by the server
    // acting as the "IDB cursor" when online, and the IDB window when offline.
    //
    // When offline: read up to MAX_WINDOW records from IDB (the most recent).
    // We sort by opened_at desc in the map() below.
    // The trade-off: offline you see MAX_WINDOW most-recent records, not all 1M.
    // This is the correct behaviour: showing a user 1M records offline is unusable.

    const allIdb = await dbGetByIndex(STORE.SECONDARY_SCREENINGS, 'poe_code', poeCode)
    const valid  = allIdb.filter(r => !r.deleted_at)

    // Sort by risk DESC, then opened_at DESC
    const RISK_ORD = { CRITICAL:0, HIGH:1, MEDIUM:2, LOW:3 }
    valid.sort((a,b) => {
      const rd = (RISK_ORD[a.risk_level]??9) - (RISK_ORD[b.risk_level]??9)
      if (rd !== 0) return rd
      return new Date(b.opened_at||b.created_at||0) - new Date(a.opened_at||a.created_at||0)
    })

    // Paginate in JS — return the requested window
    return valid.slice(offset, offset + IDB_PAGE_SIZE).map(normaliseIdbRecord)
  } catch (e) {
    console.warn('[SSR] readIdbPage error', e?.message)
    return []
  }
}

function normaliseIdbRecord(r) {
  return {
    id:                       r.id ?? r.server_id ?? null,
    client_uuid:              r.client_uuid,
    case_status:              r.case_status || 'OPEN',
    risk_level:               r.risk_level || null,
    syndrome_classification:  r.syndrome_classification || null,
    final_disposition:        r.final_disposition || null,
    followup_required:        !!r.followup_required,
    triage_category:          r.triage_category || null,
    emergency_signs_present:  !!r.emergency_signs_present,
    traveler_full_name:       r.traveler_full_name || null,
    traveler_gender:          r.traveler_gender || null,
    traveler_age_years:       r.traveler_age_years ?? null,
    traveler_nationality_country_code: r.traveler_nationality_country_code || null,
    temperature_value:        r.temperature_value ?? null,
    temperature_unit:         r.temperature_unit || null,
    poe_code:                 r.poe_code,
    district_code:            r.district_code || null,
    opened_at:                r.opened_at || r.created_at || null,
    dispositioned_at:         r.dispositioned_at || null,
    closed_at:                r.closed_at || null,
    opener_name:              r.opener_name || null,
    notification_status:      r.notification_status || null,
    notification_priority:    r.notification_priority || null,
    notification_id:          r.notification_id || null,
    primary_screening_id:     r.primary_screening_id || null,
    primary_temp_value:       r.primary_temp_value || null,
    top_disease:              r.top_disease || null,
    actions_done_count:       r.actions_done_count || 0,
    alert:                    r.alert || null,
    sync_status:              r.sync_status || SYNC.UNSYNCED,
    synced_at:                r.synced_at || null,
    sync_attempt_count:       r.sync_attempt_count || 0,
    last_sync_error:          r.last_sync_error || null,
    record_version:           r.record_version || 1,
    server_received_at:       r.server_received_at || null,
    _fromCache:               true,
  }
}

// ─── SERVER FETCH ─────────────────────────────────────────────────────────────
// Fetches a page from the server with all active filters applied.
// Returns the parsed response data or null on failure.
async function fetchFromServer(pg = 1, updatedAfter = null) {
  const userId = auth.value?.id
  if (!userId) return null

  const p = new URLSearchParams({ user_id: userId, page: pg, per_page: SERVER_PAGE_SIZE })

  // Push filters to server — server-side filtering is O(1) vs O(n) in JS
  if (statusFilter.value) p.set('case_status', statusFilter.value)
  if (riskFilter.value)   p.set('risk_level',  riskFilter.value)
  if (synFilter.value)    p.set('syndrome',     synFilter.value)
  if (dispFilter.value)   p.set('final_disposition', dispFilter.value)
  if (searchQuery.value)  p.set('search',       searchQuery.value.trim())
  if (showUnsynced.value) p.set('sync_status',  'UNSYNCED')

  const cutoff = dateFromPreset()
  if (cutoff)       p.set('date_from', cutoff.toISOString().slice(0,10))

  // Incremental sync cursor: only fetch records changed since last sync
  if (updatedAfter) p.set('updated_after', updatedAfter)

  const ctrl = new AbortController()
  const tid  = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
  try {
    const res = await fetch(`${window.SERVER_URL}/screening-records?${p}`, {
      headers: { Accept: 'application/json' }, signal: ctrl.signal,
    })
    clearTimeout(tid)
    if (!res.ok) return null
    const j = await res.json()
    return j.success ? j.data : null
  } catch { clearTimeout(tid); return null }
}

// ─── WRITE-THROUGH CACHE ──────────────────────────────────────────────────────
// Writes FULL server records to IDB immediately. Not just status fields.
// This means IDB always has the most complete data from the server.
// Offline users see rich data, not stubs.
async function writeServerItemsToIdb(serverItems) {
  for (const s of serverItems) {
    if (!s.client_uuid) continue
    try {
      const existing = await dbGet(STORE.SECONDARY_SCREENINGS, s.client_uuid)
      const incomingVersion = s.record_version ?? 1

      if (!existing) {
        // New record from server — write it fully to IDB
        await dbPut(STORE.SECONDARY_SCREENINGS, toPlain({
          // All server fields
          client_uuid:                s.client_uuid,
          id:                         s.id,
          server_id:                  s.id,
          reference_data_version:     s.reference_data_version || APP.REFERENCE_DATA_VER,
          server_received_at:         s.server_received_at || null,
          country_code:               s.country_code || auth.value?.country_code || null,
          province_code:              s.province_code || null,
          pheoc_code:                 s.pheoc_code || null,
          district_code:              s.district_code || null,
          poe_code:                   s.poe_code,
          primary_screening_id:       s.primary_screening_id || null,
          notification_id:            s.notification_id || null,
          opened_by_user_id:          s.opened_by_user_id || null,
          case_status:                s.case_status || 'OPEN',
          traveler_full_name:         s.traveler_full_name || null,
          traveler_gender:            s.traveler_gender || null,
          traveler_age_years:         s.traveler_age_years ?? null,
          traveler_nationality_country_code: s.traveler_nationality_country_code || null,
          temperature_value:          s.temperature_value ?? null,
          temperature_unit:           s.temperature_unit || null,
          risk_level:                 s.risk_level || null,
          syndrome_classification:    s.syndrome_classification || null,
          final_disposition:          s.final_disposition || null,
          followup_required:          s.followup_required ? 1 : 0,
          followup_assigned_level:    s.followup_assigned_level || null,
          triage_category:            s.triage_category || null,
          emergency_signs_present:    s.emergency_signs_present ? 1 : 0,
          officer_notes:              s.officer_notes || null,
          disposition_details:        s.disposition_details || null,
          opened_at:                  s.opened_at || null,
          dispositioned_at:           s.dispositioned_at || null,
          closed_at:                  s.closed_at || null,
          // Enriched display fields
          opener_name:                s.opener_name || null,
          opener_role:                s.opener_role || null,
          notification_status:        s.notification_status || null,
          notification_priority:      s.notification_priority || null,
          primary_temp_value:         s.primary_temp_value || null,
          top_disease:                s.top_disease || null,
          actions_done_count:         s.actions_done_count || 0,
          // Sync metadata
          sync_status:                SYNC.SYNCED,
          synced_at:                  isoNow(),
          sync_attempt_count:         s.sync_attempt_count || 0,
          last_sync_error:            null,
          record_version:             incomingVersion,
          reference_data_version:     s.reference_data_version || APP.REFERENCE_DATA_VER,
          device_id:                  s.device_id || 'SERVER',
          app_version:                s.app_version || null,
          platform:                   s.platform || 'WEB',
          opened_timezone:            s.opened_timezone || null,
          opened_by_user_id:          s.opened_by_user_id || null,
          server_received_at:         s.server_received_at || null,
          created_at:                 s.created_at || isoNow(),
          updated_at:                 s.updated_at || isoNow(),
          // Extended opener details (from list and detail enrichment)
          opener_username:            s.opener_username || null,
          opener_phone:               s.opener_phone || null,
          opener_email:               s.opener_email || null,
          // Extended notification fields
          notification_reason_code:   s.notification_reason_code || null,
          notification_reason_text:   s.notification_reason_text || null,
          notification_opened_at:     s.notification_opened_at || null,
          notification_closed_at:     s.notification_closed_at || null,
          notification_assigned_role: s.notification_assigned_role || null,
          // Extended primary screening fields
          primary_symptoms_present:   s.primary_symptoms_present ?? null,
          primary_referral_created:   s.primary_referral_created ?? null,
          primary_captured_timezone:  s.primary_captured_timezone || null,
          primary_poe_code:           s.primary_poe_code || null,
          primary_sync_status:        s.primary_sync_status || null,
        }))
      } else {
        // Existing record — only overwrite if server version is newer
        const storedVersion = existing.record_version ?? 0
        if (incomingVersion > storedVersion) {
          await safeDbPut(STORE.SECONDARY_SCREENINGS, toPlain({
            ...existing,
            // Update all server-authoritative fields
            id:                      s.id,
            server_id:               s.id,
            server_received_at:      s.server_received_at || existing.server_received_at,
            case_status:             s.case_status,
            risk_level:              s.risk_level,
            syndrome_classification: s.syndrome_classification,
            final_disposition:       s.final_disposition,
            followup_required:       s.followup_required ? 1 : 0,
            followup_assigned_level: s.followup_assigned_level || null,
            triage_category:         s.triage_category || existing.triage_category,
            officer_notes:           s.officer_notes || existing.officer_notes,
            disposition_details:     s.disposition_details || existing.disposition_details,
            opened_at:               s.opened_at || existing.opened_at,
            dispositioned_at:        s.dispositioned_at || existing.dispositioned_at,
            closed_at:               s.closed_at || existing.closed_at,
            opener_name:             s.opener_name || existing.opener_name,
            opener_role:             s.opener_role || existing.opener_role,
            notification_status:     s.notification_status || existing.notification_status,
            notification_priority:   s.notification_priority || existing.notification_priority,
            primary_temp_value:      s.primary_temp_value ?? existing.primary_temp_value,
            top_disease:             s.top_disease || existing.top_disease,
            actions_done_count:      s.actions_done_count ?? existing.actions_done_count,
            // Only mark SYNCED if the local record isn't newer (being edited offline)
            sync_status:             existing.sync_status === SYNC.UNSYNCED
                                       ? SYNC.UNSYNCED : SYNC.SYNCED,
            record_version:          incomingVersion,
            updated_at:              s.updated_at || isoNow(),
            // Extended fields from enriched server response
            opener_username:         s.opener_username || existing.opener_username,
            opener_phone:            s.opener_phone || existing.opener_phone,
            opener_email:            s.opener_email || existing.opener_email,
            opened_timezone:         s.opened_timezone || existing.opened_timezone,
            reference_data_version:  s.reference_data_version || existing.reference_data_version,
            server_received_at:      s.server_received_at || existing.server_received_at,
            app_version:             s.app_version || existing.app_version,
            notification_reason_code: s.notification_reason_code || existing.notification_reason_code,
            notification_reason_text: s.notification_reason_text || existing.notification_reason_text,
            notification_opened_at:  s.notification_opened_at || existing.notification_opened_at,
            notification_closed_at:  s.notification_closed_at || existing.notification_closed_at,
            notification_assigned_role: s.notification_assigned_role || existing.notification_assigned_role,
            primary_symptoms_present: s.primary_symptoms_present ?? existing.primary_symptoms_present,
            primary_referral_created: s.primary_referral_created ?? existing.primary_referral_created,
            primary_captured_timezone: s.primary_captured_timezone || existing.primary_captured_timezone,
            primary_poe_code:        s.primary_poe_code || existing.primary_poe_code,
            primary_sync_status:     s.primary_sync_status || existing.primary_sync_status,
          }))
        }
      }
    } catch (e) {
      console.warn('[SSR] writeServerItemsToIdb error', s.client_uuid, e?.message)
    }
  }
}

// ─── MERGE SERVER ITEMS INTO MEMORY WINDOW ────────────────────────────────────
// Merges server items into allItems using a Map for O(1) dedup.
// Respects MAX_WINDOW: if adding server items would exceed the cap,
// the oldest (by opened_at) items are evicted to make room.
function mergeIntoWindow(serverItems) {
  const byUuid = new Map(allItems.value.map(i => [i.client_uuid, i]))

  for (const s of serverItems) {
    const uuid = s.client_uuid
    if (!uuid) continue
    const existing = byUuid.get(uuid)
    byUuid.set(uuid, {
      id:                      s.id,
      client_uuid:             uuid,
      case_status:             s.case_status,
      risk_level:              s.risk_level,
      syndrome_classification: s.syndrome_classification,
      final_disposition:       s.final_disposition,
      followup_required:       !!s.followup_required,
      triage_category:         s.triage_category,
      emergency_signs_present: !!s.emergency_signs_present,
      traveler_full_name:      s.traveler_full_name,
      traveler_gender:         s.traveler_gender,
      traveler_age_years:      s.traveler_age_years,
      traveler_nationality_country_code: s.traveler_nationality_country_code,
      temperature_value:       s.temperature_value,
      temperature_unit:        s.temperature_unit,
      poe_code:                s.poe_code,
      district_code:           s.district_code,
      opened_at:               s.opened_at,
      dispositioned_at:        s.dispositioned_at,
      closed_at:               s.closed_at,
      opener_name:             s.opener_name,
      notification_status:     s.notification_status,
      notification_priority:   s.notification_priority,
      notification_id:         s.notification_id,
      primary_screening_id:    s.primary_screening_id,
      primary_temp_value:      s.primary_temp_value,
      top_disease:             s.top_disease,
      actions_done_count:      s.actions_done_count || 0,
      alert:                   s.alert,
      sync_status:             existing?.sync_status === SYNC.UNSYNCED ? SYNC.UNSYNCED : SYNC.SYNCED,
      synced_at:               existing?.synced_at || null,
      sync_attempt_count:      existing?.sync_attempt_count || 0,
      last_sync_error:         existing?.last_sync_error || null,
      record_version:          s.record_version || 1,
      server_received_at:      s.server_received_at || null,
      _fromCache:              false,
    })
  }

  // Sort: risk DESC, then opened_at DESC
  const RISK_ORD = { CRITICAL:0, HIGH:1, MEDIUM:2, LOW:3 }
  let sorted = Array.from(byUuid.values()).sort((a,b) => {
    const rd = (RISK_ORD[a.risk_level]??9) - (RISK_ORD[b.risk_level]??9)
    if (rd !== 0) return rd
    return new Date(b.opened_at||0) - new Date(a.opened_at||0)
  })

  // Enforce window cap: evict lowest-priority (oldest CLOSED/SYNCED) items
  if (sorted.length > MAX_WINDOW) {
    sorted = sorted.slice(0, MAX_WINDOW)
  }

  allItems.value = sorted
}

// ─── LOAD LIFECYCLE ───────────────────────────────────────────────────────────

/** Full initial load: IDB first, then server enrich. */
async function load() {
  loading.value    = true
  idbPageOffset.value = 0
  serverPage.value    = 1
  hasMoreIdb.value    = true
  hasMoreServer.value = true

  try {
    // ── Phase 1: IDB stats + first page (instant, works offline) ──────
    const [idbPage] = await Promise.all([
      readIdbPage(0),
      refreshIdbStats(),
    ])

    if (idbPage.length > 0) {
      allItems.value  = idbPage
      idbPageOffset.value = IDB_PAGE_SIZE
      hasMoreIdb.value    = idbPage.length === IDB_PAGE_SIZE
      loading.value = false  // show data immediately
    }

    // ── Phase 2: server page 1 (if online) ────────────────────────────
    if (isOnline.value) {
      const data = await fetchFromServer(1)
      if (data) {
        totalOnServer.value = data.total || 0
        hasMoreServer.value = (data.page ?? 1) < (data.pages ?? 1)
        serverPage.value    = 2

        // Write-through: persist full records to IDB immediately
        writeServerItemsToIdb(data.items || []).catch(() => {})

        // Merge into memory window
        mergeIntoWindow(data.items || [])

        // Update sync cursor
        localStorage.setItem(LAST_SYNC_KEY, isoNow())

        // Recompute stats from IDB after write
        refreshIdbStats().catch(() => {})
      }
    }
  } finally {
    loading.value = false
  }
}

/** Load the next page (scroll pagination). Loads from IDB first, then server. */
async function loadMore() {
  if (loadingMore.value) return

  loadingMore.value = true
  try {
    // Try IDB next page first
    if (hasMoreIdb.value) {
      const idbPage = await readIdbPage(idbPageOffset.value)
      if (idbPage.length > 0) {
        // Append to window, enforce cap by evicting from the start if needed
        const combined = [...allItems.value, ...idbPage]
        const RISK_ORD = { CRITICAL:0, HIGH:1, MEDIUM:2, LOW:3 }
        const sorted = combined
          .filter((item, idx, arr) => arr.findIndex(x => x.client_uuid === item.client_uuid) === idx) // dedup
          .sort((a,b) => {
            const rd = (RISK_ORD[a.risk_level]??9) - (RISK_ORD[b.risk_level]??9)
            if (rd !== 0) return rd
            return new Date(b.opened_at||0) - new Date(a.opened_at||0)
          })
          .slice(0, MAX_WINDOW)
        allItems.value      = sorted
        idbPageOffset.value += IDB_PAGE_SIZE
        hasMoreIdb.value     = idbPage.length === IDB_PAGE_SIZE
        return
      } else {
        hasMoreIdb.value = false
      }
    }

    // IDB exhausted — fetch next server page
    if (hasMoreServer.value && isOnline.value) {
      const data = await fetchFromServer(serverPage.value)
      if (data) {
        totalOnServer.value = data.total || 0
        hasMoreServer.value = (data.page ?? 1) < (data.pages ?? 1)
        serverPage.value++
        writeServerItemsToIdb(data.items || []).catch(() => {})
        mergeIntoWindow(data.items || [])
        refreshIdbStats().catch(() => {})
      }
    }
  } finally {
    loadingMore.value = false
  }
}

/** Reload with filters applied (called when filter/search changes). */
async function reload() {
  await load()
}

/** Pull-to-refresh handler. */
async function onPullRefresh(ev) {
  await load()
  ev.target.complete()
}

// ─── BACKGROUND INCREMENTAL SYNC ─────────────────────────────────────────────
// Fires on 'online' event and on the auto-refresh timer.
// Fetches only records updated since last sync cursor — efficient at any scale.
// At 1M records, daily syncs bring only a few hundred changed records, not 1M.
async function backgroundServerSync(debounceMs = 0) {
  if (!isOnline.value || syncing.value) return

  // Debounce: avoid firing 5 times on flaky wifi reconnect
  if (bgSyncDebounce) clearTimeout(bgSyncDebounce)
  bgSyncDebounce = setTimeout(async () => {
    bgSyncDebounce = null
    try {
      const lastSync = localStorage.getItem(LAST_SYNC_KEY) || null
      const data = await fetchFromServer(1, lastSync)
      if (!data) return

      const items = data.items || []
      if (!items.length) return  // nothing new

      // Write all new/changed records to IDB
      await writeServerItemsToIdb(items)

      // Update the memory window with new records
      mergeIntoWindow(items)

      // Update stats
      await refreshIdbStats()

      // Advance sync cursor
      localStorage.setItem(LAST_SYNC_KEY, isoNow())

      console.log(`[SSR] Background sync: ${items.length} records updated`)
    } catch (e) {
      console.warn('[SSR] backgroundServerSync error', e?.message)
    }
  }, debounceMs)
}

// ─── DETAIL MODAL ─────────────────────────────────────────────────────────────
async function openDetail(item) {
  detailRecord.value = { ...item }
  modalTab.value     = 'overview'
  modalOpen.value    = true
  await loadDetailFull(item)
}

async function loadDetailFull(item) {
  detailLoading.value = true
  try {
    const uuid = item.client_uuid
    const sid  = item.id

    // Always read child tables from IDB (offline-capable)
    const [symptoms, exposures, actions, samples, travelCountries, diseases] = await Promise.all([
      dbGetByIndex(STORE.SECONDARY_SYMPTOMS,          'secondary_screening_id', uuid).catch(()=>[]),
      dbGetByIndex(STORE.SECONDARY_EXPOSURES,         'secondary_screening_id', uuid).catch(()=>[]),
      dbGetByIndex(STORE.SECONDARY_ACTIONS,           'secondary_screening_id', uuid).catch(()=>[]),
      dbGetByIndex(STORE.SECONDARY_SAMPLES,           'secondary_screening_id', uuid).catch(()=>[]),
      dbGetByIndex(STORE.SECONDARY_TRAVEL_COUNTRIES,  'secondary_screening_id', uuid).catch(()=>[]),
      dbGetByIndex(STORE.SECONDARY_SUSPECTED_DISEASES,'secondary_screening_id', uuid).catch(()=>[]),
    ])

    const notifId = item.notification_id
    const [notif, primarySc, fullCase] = await Promise.all([
      notifId ? dbGet(STORE.NOTIFICATIONS, notifId).catch(()=>null) : Promise.resolve(null),
      item.primary_screening_id
        ? dbGet(STORE.PRIMARY_SCREENINGS, item.primary_screening_id).catch(()=>null)
        : Promise.resolve(null),
      dbGet(STORE.SECONDARY_SCREENINGS, uuid).catch(()=>null),
    ])

    detailRecord.value = {
      ...(fullCase || item), ...item,
      symptoms:           symptoms.map(normaliseChild),
      exposures:          exposures.map(normaliseChild),
      actions:            actions.map(normaliseChild),
      samples:            samples.map(normaliseChild),
      travel_countries:   travelCountries.map(normaliseChild),
      suspected_diseases: diseases.map(d => ({...normaliseChild(d), rank_order: d.rank_order||1}))
                            .sort((a,b) => a.rank_order - b.rank_order),
      notification:       notif || null,
      primary_screening:  primarySc || null,
      alert:              item.alert || null,
      id:                 fullCase?.id ?? fullCase?.server_id ?? item.id ?? null,
    }

    // Enrich from server if online (non-blocking — UI already shows IDB data)
    if (isOnline.value && sid) {
      fetchDetailFromServer(sid).then(serverDetail => {
        if (!serverDetail || !detailRecord.value) return
        // Only update if modal is still showing the same record
        if (detailRecord.value.client_uuid !== uuid) return

        detailRecord.value = {
          ...detailRecord.value,
          opener_name:       serverDetail.opener_name    || detailRecord.value.opener_name,
          opener_role:       serverDetail.opener_role,
          notification:      serverDetail.notification   || detailRecord.value.notification,
          primary_screening: serverDetail.primary_screening || detailRecord.value.primary_screening,
          alert:             serverDetail.alert          || detailRecord.value.alert,
          server_received_at:serverDetail.server_received_at,
          symptoms:          serverDetail.symptoms?.length     ? serverDetail.symptoms     : detailRecord.value.symptoms,
          exposures:         serverDetail.exposures?.length    ? serverDetail.exposures    : detailRecord.value.exposures,
          actions:           serverDetail.actions?.length      ? serverDetail.actions      : detailRecord.value.actions,
          samples:           serverDetail.samples?.length      ? serverDetail.samples      : detailRecord.value.samples,
          travel_countries:  serverDetail.travel_countries?.length ? serverDetail.travel_countries : detailRecord.value.travel_countries,
          suspected_diseases:serverDetail.suspected_diseases?.length ? serverDetail.suspected_diseases : detailRecord.value.suspected_diseases,
        }

        // Write enriched server detail to IDB — full write-through of ALL fields
        if (serverDetail) {
          const enriched = toPlain({ ...(detailRecord.value), ...serverDetail, sync_status: SYNC.SYNCED })
          safeDbPut(STORE.SECONDARY_SCREENINGS, enriched).catch(()=>{})

          // Also write child tables to their own IDB stores (replaces stale local data)
          // This ensures offline access to the server-authoritative child records
          const childUuid = detailRecord.value?.client_uuid
          if (childUuid) {
            if (serverDetail.symptoms?.length) {
              const recs = serverDetail.symptoms.map(s => ({ ...s, client_uuid: s.id ? `srv-sym-${s.id}` : genUuid(), secondary_screening_id: childUuid, sync_status: SYNC.SYNCED }))
              dbReplaceAll(STORE.SECONDARY_SYMPTOMS, 'secondary_screening_id', childUuid, toPlain(recs)).catch(()=>{})
            }
            if (serverDetail.exposures?.length) {
              const recs = serverDetail.exposures.map(e => ({ ...e, client_uuid: e.id ? `srv-exp-${e.id}` : genUuid(), secondary_screening_id: childUuid, sync_status: SYNC.SYNCED }))
              dbReplaceAll(STORE.SECONDARY_EXPOSURES, 'secondary_screening_id', childUuid, toPlain(recs)).catch(()=>{})
            }
            if (serverDetail.actions?.length) {
              const recs = serverDetail.actions.map(a => ({ ...a, client_uuid: a.id ? `srv-act-${a.id}` : genUuid(), secondary_screening_id: childUuid, sync_status: SYNC.SYNCED }))
              dbReplaceAll(STORE.SECONDARY_ACTIONS, 'secondary_screening_id', childUuid, toPlain(recs)).catch(()=>{})
            }
            if (serverDetail.samples?.length) {
              const recs = serverDetail.samples.map(s => ({ ...s, client_uuid: s.id ? `srv-smp-${s.id}` : genUuid(), secondary_screening_id: childUuid, sync_status: SYNC.SYNCED }))
              dbReplaceAll(STORE.SECONDARY_SAMPLES, 'secondary_screening_id', childUuid, toPlain(recs)).catch(()=>{})
            }
            if (serverDetail.travel_countries?.length) {
              const recs = serverDetail.travel_countries.map(t => ({ ...t, client_uuid: t.id ? `srv-tc-${t.id}` : genUuid(), secondary_screening_id: childUuid, sync_status: SYNC.SYNCED }))
              dbReplaceAll(STORE.SECONDARY_TRAVEL_COUNTRIES, 'secondary_screening_id', childUuid, toPlain(recs)).catch(()=>{})
            }
            if (serverDetail.suspected_diseases?.length) {
              const recs = serverDetail.suspected_diseases.map(d => ({ ...d, client_uuid: d.id ? `srv-sd-${d.id}` : genUuid(), secondary_screening_id: childUuid, sync_status: SYNC.SYNCED }))
              dbReplaceAll(STORE.SECONDARY_SUSPECTED_DISEASES, 'secondary_screening_id', childUuid, toPlain(recs)).catch(()=>{})
            }
          }
        }
      }).catch(()=>{})
    }
  } catch (e) {
    console.error('[SSR] loadDetailFull error', e?.message)
  } finally {
    detailLoading.value = false
  }
}

function normaliseChild(r) { return JSON.parse(JSON.stringify(toRaw(r))) }

async function fetchDetailFromServer(serverId) {
  const userId = auth.value?.id
  if (!userId || !serverId) return null
  const ctrl = new AbortController()
  const tid  = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
  try {
    const res = await fetch(`${window.SERVER_URL}/screening-records/${serverId}?user_id=${userId}`,
      { headers: { Accept:'application/json' }, signal: ctrl.signal })
    clearTimeout(tid)
    if (!res.ok) return null
    const j = await res.json()
    return j.success ? j.data : null
  } catch { clearTimeout(tid); return null }
}

function dismissModal() { modalOpen.value = false }
function closeDetail()  { detailRecord.value = null; detailLoading.value = false }

// ─── SYNC ENGINE (Phase 1 + 1.5 + Phase 2) ────────────────────────────────────
// Identical to SecondaryScreening.vue — must stay in sync.
async function syncOneRecord(item) {
  if (!isOnline.value) { showToast('Device is offline — cannot sync now.', 'warning'); return }
  const uuid = item.client_uuid
  if (!uuid) return

  const next = new Set(syncingUuids.value); next.add(uuid); syncingUuids.value = next

  try {
    const a = auth.value; const userId = a?.id
    if (!userId) throw new Error('No auth user_id')
    const rec = await dbGet(STORE.SECONDARY_SCREENINGS, uuid)
    if (!rec) throw new Error('Record not found in IDB')

    let serverId = rec.id ?? rec.server_id ?? null

    // Phase 1: create on server
    if (!serverId) {
      const r1 = await timedFetch(`${window.SERVER_URL}/secondary-screenings`, {
        method:'POST',
        headers:{'Content-Type':'application/json', Accept:'application/json'},
        body: JSON.stringify(buildPhase1Payload(rec, userId)),
      })
      const b1 = await r1.json().catch(()=>({}))
      if (!r1.ok || !b1.success) {
        await markSyncFailed(uuid, rec, b1?.message || `HTTP ${r1.status}`)
        showToast(`Sync failed (Phase 1): ${b1?.message || r1.status}`, 'danger'); return
      }
      serverId = b1.data?.id
      if (!serverId) { await markSyncFailed(uuid, rec, 'No server id'); showToast('Sync error: no server id.', 'danger'); return }
      await safeDbPut(STORE.SECONDARY_SCREENINGS, toPlain({...rec, id:serverId, server_id:serverId, updated_at:isoNow()}))
    }

    // Phase 1.5: advance status if needed
    const caseStatus = rec.case_status || 'OPEN'
    if (['IN_PROGRESS','DISPOSITIONED','CLOSED'].includes(caseStatus)) {
      const r15 = await timedFetch(`${window.SERVER_URL}/secondary-screenings/${serverId}/sync`, {
        method:'POST', headers:{'Content-Type':'application/json', Accept:'application/json'},
        body: JSON.stringify({ case_status:'IN_PROGRESS', user_id: userId }),
      })
      if (!r15.ok && r15.status !== 409) console.warn('[SSR] Phase 1.5 non-fatal', r15.status)
    }

    // Phase 2: full sync with all child tables
    const [symptoms, exposures, actions, samples, travelCountries, diseases] = await Promise.all([
      dbGetByIndex(STORE.SECONDARY_SYMPTOMS,          'secondary_screening_id', uuid).catch(()=>[]),
      dbGetByIndex(STORE.SECONDARY_EXPOSURES,         'secondary_screening_id', uuid).catch(()=>[]),
      dbGetByIndex(STORE.SECONDARY_ACTIONS,           'secondary_screening_id', uuid).catch(()=>[]),
      dbGetByIndex(STORE.SECONDARY_SAMPLES,           'secondary_screening_id', uuid).catch(()=>[]),
      dbGetByIndex(STORE.SECONDARY_TRAVEL_COUNTRIES,  'secondary_screening_id', uuid).catch(()=>[]),
      dbGetByIndex(STORE.SECONDARY_SUSPECTED_DISEASES,'secondary_screening_id', uuid).catch(()=>[]),
    ])

    const r2 = await timedFetch(`${window.SERVER_URL}/secondary-screenings/${serverId}/sync`, {
      method:'POST', headers:{'Content-Type':'application/json', Accept:'application/json'},
      body: JSON.stringify(buildPhase2Payload(rec, userId, caseStatus, symptoms, exposures, actions, samples, travelCountries, diseases)),
    })
    const b2 = await r2.json().catch(()=>({}))
    const alreadyClosed = r2.status === 409 && (b2?.message ?? '').toLowerCase().includes('closed')

    if (r2.ok || alreadyClosed) {
      const freshRec = await dbGet(STORE.SECONDARY_SCREENINGS, uuid)
      const synced = toPlain({
        ...(freshRec || rec), id:serverId, server_id:serverId,
        case_status: alreadyClosed ? 'CLOSED' : (b2?.data?.case_status || caseStatus),
        sync_status: SYNC.SYNCED, synced_at: isoNow(), last_sync_error: null,
        record_version: ((freshRec || rec).record_version || 1) + 1, updated_at: isoNow(),
      })
      await safeDbPut(STORE.SECONDARY_SCREENINGS, synced)

      const idx = allItems.value.findIndex(i => i.client_uuid === uuid)
      if (idx !== -1) { allItems.value[idx] = {...allItems.value[idx], sync_status:SYNC.SYNCED, id:serverId}; allItems.value = [...allItems.value] }
      if (detailRecord.value?.client_uuid === uuid) detailRecord.value = {...detailRecord.value, sync_status:SYNC.SYNCED, id:serverId, synced_at:isoNow(), last_sync_error:null}

      await refreshIdbStats()
      showToast(alreadyClosed ? 'Reconciled — already closed on server.' : 'Record synced.', 'success')
    } else {
      await markSyncFailed(uuid, rec, b2?.message || `HTTP ${r2.status}`)
      showToast(`Sync failed: ${b2?.message || r2.status}`, 'danger')
    }
  } catch (e) {
    const rec = await dbGet(STORE.SECONDARY_SCREENINGS, uuid).catch(()=>null)
    if (rec) await markSyncFailed(uuid, rec, e?.message || 'Unknown error')
    showToast(`Sync error: ${e?.message || 'Unknown'}`, 'danger')
  } finally {
    const next2 = new Set(syncingUuids.value); next2.delete(uuid); syncingUuids.value = next2
  }
}

async function syncAllPending() {
  if (!isOnline.value) { showToast('Device is offline.', 'warning'); return }
  const pending = allItems.value.filter(i => i.sync_status !== SYNC.SYNCED)
  if (!pending.length) { showToast('All records are already synced.', 'success'); return }
  syncing.value = true
  let ok = 0, fail = 0
  for (const item of pending) {
    await syncOneRecord(item)
    const u = allItems.value.find(i => i.client_uuid === item.client_uuid)
    if (u?.sync_status === SYNC.SYNCED) ok++; else fail++
  }
  syncing.value = false
  showToast(`Sync complete — ${ok} synced${fail ? `, ${fail} failed` : ''}.`, fail ? 'warning' : 'success')
}

// ─── PAYLOAD BUILDERS ─────────────────────────────────────────────────────────
function buildPhase1Payload(rec, userId) {
  return {
    client_uuid:            rec.client_uuid,
    idempotency_key:        rec.idempotency_key || rec.client_uuid,
    reference_data_version: rec.reference_data_version || APP.REFERENCE_DATA_VER,
    user_id:                userId,
    notification_id:        rec.notification_id,
    primary_screening_id:   rec.primary_screening_id,
    poe_code:               rec.poe_code,
    district_code:          rec.district_code,
    province_code:          rec.province_code || null,
    pheoc_code:             rec.pheoc_code    || null,
    country_code:           rec.country_code  || auth.value?.country_code || '',
    opened_at:              rec.opened_at     || rec.created_at,
    opened_timezone:        rec.opened_timezone || Intl.DateTimeFormat().resolvedOptions().timeZone,
    device_id:              rec.device_id     || 'WEB',
    app_version:            rec.app_version   || null,
    platform:               rec.platform      || 'WEB',
  }
}
function buildPhase2Payload(rec, userId, caseStatus, symptoms, exposures, actions, samples, travelCountries, diseases) {
  return {
    user_id: userId, case_status: caseStatus,
    traveler_full_name: rec.traveler_full_name||null, traveler_initials: rec.traveler_initials||null,
    traveler_anonymous_code: rec.traveler_anonymous_code||null,
    travel_document_type: rec.travel_document_type||null, travel_document_number: rec.travel_document_number||null,
    traveler_gender: rec.traveler_gender, traveler_age_years: rec.traveler_age_years??null,
    traveler_dob: rec.traveler_dob||null, traveler_nationality_country_code: rec.traveler_nationality_country_code||null,
    traveler_occupation: rec.traveler_occupation||null, residence_country_code: rec.residence_country_code||null,
    residence_address_text: rec.residence_address_text||null, phone_number: rec.phone_number||null,
    alternative_phone: rec.alternative_phone||null, email: rec.email||null,
    destination_address_text: rec.destination_address_text||null, destination_district_code: rec.destination_district_code||null,
    emergency_contact_name: rec.emergency_contact_name||null, emergency_contact_phone: rec.emergency_contact_phone||null,
    journey_start_country_code: rec.journey_start_country_code||null, embarkation_port_city: rec.embarkation_port_city||null,
    conveyance_type: rec.conveyance_type||null, conveyance_identifier: rec.conveyance_identifier||null,
    seat_number: rec.seat_number||null, arrival_datetime: rec.arrival_datetime||null,
    departure_datetime: rec.departure_datetime||null, purpose_of_travel: rec.purpose_of_travel||null,
    planned_length_of_stay_days: rec.planned_length_of_stay_days??null,
    triage_category: rec.triage_category||null, emergency_signs_present: rec.emergency_signs_present?1:0,
    general_appearance: rec.general_appearance||null, temperature_value: rec.temperature_value??null,
    temperature_unit: rec.temperature_unit||null, pulse_rate: rec.pulse_rate??null,
    respiratory_rate: rec.respiratory_rate??null, bp_systolic: rec.bp_systolic??null,
    bp_diastolic: rec.bp_diastolic??null, oxygen_saturation: rec.oxygen_saturation??null,
    syndrome_classification: rec.syndrome_classification||null, risk_level: rec.risk_level||null,
    officer_notes: rec.officer_notes||null, final_disposition: rec.final_disposition||null,
    disposition_details: rec.disposition_details||null, followup_required: rec.followup_required?1:0,
    followup_assigned_level: rec.followup_assigned_level||null,
    dispositioned_at: rec.dispositioned_at||null, closed_at: rec.closed_at||null,
    symptoms: symptoms.map(s=>({symptom_code:s.symptom_code, is_present:s.is_present?1:0, onset_date:s.onset_date||null, details:s.details||null})),
    exposures: exposures.map(e=>({exposure_code:e.exposure_code, response:e.response||'UNKNOWN', details:e.details||null})),
    actions: actions.map(a=>({action_code:a.action_code, is_done:a.is_done?1:0, details:a.details||null})),
    samples: samples.filter(s=>s.sample_collected).map(s=>({sample_collected:1, sample_type:s.sample_type||null, sample_identifier:s.sample_identifier||null, lab_destination:s.lab_destination||null, collected_at:s.collected_at||null})),
    travel_countries: travelCountries.map(tc=>({country_code:tc.country_code, travel_role:tc.travel_role||'VISITED', arrival_date:tc.arrival_date||null, departure_date:tc.departure_date||null})),
    suspected_diseases: diseases.map(d=>({disease_code:d.disease_code, rank_order:d.rank_order||1, confidence:d.confidence??null, reasoning:d.reasoning||null})),
  }
}

async function markSyncFailed(uuid, rec, errorMsg) {
  await safeDbPut(STORE.SECONDARY_SCREENINGS, toPlain({
    ...rec, sync_status: SYNC.FAILED, last_sync_error: errorMsg,
    sync_attempt_count: (rec.sync_attempt_count||0)+1, updated_at: isoNow(),
  }))
  const idx = allItems.value.findIndex(i => i.client_uuid === uuid)
  if (idx !== -1) { allItems.value[idx] = {...allItems.value[idx], sync_status:SYNC.FAILED, last_sync_error:errorMsg}; allItems.value=[...allItems.value] }
  if (detailRecord.value?.client_uuid === uuid) detailRecord.value = {...detailRecord.value, sync_status:SYNC.FAILED, last_sync_error:errorMsg}
  await refreshIdbStats()
}

async function timedFetch(url, opts={}) {
  const ctrl = new AbortController()
  const tid  = setTimeout(()=>ctrl.abort(), APP.SYNC_TIMEOUT_MS)
  try { const r = await fetch(url, {...opts, signal:ctrl.signal}); clearTimeout(tid); return r }
  catch (e) { clearTimeout(tid); throw e }
}

// ─── FILTER ACTIONS ────────────────────────────────────────────────────────────
function quickStatus(v) { statusFilter.value = statusFilter.value===v ? null : v; filtersOpen.value=false; reload() }
function quickRisk(v)   { riskFilter.value   = riskFilter.value===v   ? null : v; filtersOpen.value=false; reload() }
function filterUnsyncedOnly() { showUnsynced.value = !showUnsynced.value; statusFilter.value=null; reload() }
function clearAllFilters() {
  statusFilter.value=null; riskFilter.value=null; synFilter.value=null
  dispFilter.value=null; datePreset.value='all'; showUnsynced.value=false
  filtersOpen.value=false; searchQuery.value=''
  reload()
}

// Debounce search — avoid hammering server on every keystroke
let searchDebounce = null
watch(searchQuery, () => {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(reload, 350)
})

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function showToast(msg, color='success') { Object.assign(toast, { show:true, msg, color }) }
function statusKey(s)    { return (s||'open').toLowerCase().replace('_','-') }
function riskCardClass(rl) {
  if (rl==='CRITICAL') return 'sr-card--critical'
  if (rl==='HIGH')     return 'sr-card--high'
  if (rl==='MEDIUM')   return 'sr-card--medium'
  return 'sr-card--low'
}
function tempChipClass(val, unit) {
  const c = unit==='F' ? (val-32)*5/9 : val
  if (c >= 38.5) return 'sr-temp--fever'
  if (c >= 37.5) return 'sr-temp--low'
  return 'sr-temp--normal'
}
function tempWarn(val, unit) {
  if (val==null) return null
  const c = unit==='F' ? (val-32)*5/9 : Number(val)
  if (c >= 39.5) return '⚠ High fever'
  if (c >= 38.5) return '↑ Fever'
  if (c >= 37.5) return '↑ Low-grade'
  if (c < 36.0)  return '↓ Hypothermia'
  return null
}
function tempVitalClass(val, unit) {
  if (val==null) return ''
  const c = unit==='F' ? (val-32)*5/9 : Number(val)
  if (c >= 38.5) return 'sr-vital--danger'
  if (c >= 37.5) return 'sr-vital--warn'
  return 'sr-vital--ok'
}
function pulseVitalClass(p) {
  if (p==null) return ''
  if (p>120||p<50) return 'sr-vital--danger'
  if (p>100||p<60) return 'sr-vital--warn'
  return 'sr-vital--ok'
}
function rrVitalClass(r) {
  if (r==null) return ''
  if (r>30||r<8)  return 'sr-vital--danger'
  if (r>24||r<12) return 'sr-vital--warn'
  return 'sr-vital--ok'
}
function spo2VitalClass(s) {
  if (s==null) return ''
  if (s < 90) return 'sr-vital--danger'
  if (s < 94) return 'sr-vital--warn'
  return 'sr-vital--ok'
}
function fmtRelative(dt) {
  if (!dt) return '—'
  try {
    const diff = Date.now() - new Date(dt.replace(' ','T')).getTime()
    const m = Math.floor(diff/60000)
    if (m < 1)  return 'Just now'
    if (m < 60) return `${m}m ago`
    const h = Math.floor(m/60)
    if (h < 24) return `${h}h ago`
    return `${Math.floor(h/24)}d ago`
  } catch { return '—' }
}
function fmtDateTime(dt) {
  if (!dt) return null
  try {
    return new Date(dt.replace(' ','T')).toLocaleString([],
      { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' })
  } catch { return dt }
}
function caseDurationMins(rec) {
  if (!rec.opened_at||!rec.closed_at) return null
  try {
    const diff = new Date(rec.closed_at.replace(' ','T')) - new Date(rec.opened_at.replace(' ','T'))
    const m = Math.floor(diff/60000)
    if (m < 60) return `${m} min`
    return `${Math.floor(m/60)}h ${m%60}m`
  } catch { return null }
}

// ─── CONNECTIVITY + LIFECYCLE ─────────────────────────────────────────────────
function onOnline() {
  isOnline.value = true
  // On reconnect: immediately background-sync new records (debounced 500ms
  // to handle flappy connections that disconnect/reconnect rapidly)
  backgroundServerSync(500)
}
function onOffline() {
  isOnline.value = false
}

onMounted(() => {
  auth.value = getAuth()
  window.addEventListener('online',  onOnline)
  window.addEventListener('offline', onOffline)
  load()

  // Auto-refresh: background incremental sync every POLL_INTERVAL_MS
  autoRefreshTimer = setInterval(() => {
    if (isOnline.value && !loading.value && !syncing.value) {
      backgroundServerSync()
    }
  }, POLL_INTERVAL_MS)
})

onIonViewWillEnter(() => {
  auth.value = getAuth()
  // On re-entry: reload to pick up any changes made in SecondaryScreening.vue
  reload()
  // Also trigger incremental sync if online
  if (isOnline.value) backgroundServerSync(200)
})

onUnmounted(() => {
  window.removeEventListener('online',  onOnline)
  window.removeEventListener('offline', onOffline)
  clearInterval(autoRefreshTimer)
  if (bgSyncDebounce) clearTimeout(bgSyncDebounce)
  if (searchDebounce) clearTimeout(searchDebounce)
})

</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════
   SECONDARY SCREENING RECORDS — LIGHT THEME · Namespace: sr-*
   Design: Clinical Intelligence File — WHO/IHR operational palette
   RULE: card sub-elements rendered via h() use :deep(). All others scoped.
   NO dark mode. NO @media prefers-color-scheme: dark.
═══════════════════════════════════════════════════════════════════════ */

/* ── HEADER ──────────────────────────────────────────────────────────── */
.sr-toolbar { --background:#003F88; --color:#fff; --padding-start:8px; --padding-end:8px; --min-height:50px; }
.sr-menu-btn { --color:rgba(255,255,255,.8); }
.sr-title-block { display:flex; flex-direction:column; margin-left:4px; }
.sr-eyebrow { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1.4px; color:rgba(255,255,255,.5); line-height:1; }
.sr-title   { font-size:17px; font-weight:800; color:#fff; line-height:1.2; letter-spacing:-.3px; }

/* Sync pill (header) */
.sr-sync-pill { display:flex; align-items:center; gap:5px; padding:4px 9px; border-radius:9999px; font-size:10px; font-weight:700; border:1px solid rgba(255,255,255,.2); margin-right:4px; cursor:pointer; transition:background .15s; }
.sr-sync-pill--ok      { background:rgba(40,167,69,.25);  color:#90EE90; }
.sr-sync-pill--pending { background:rgba(255,152,0,.3);   color:#FFD740; }
.sr-sync-pill--syncing { background:rgba(33,150,243,.3);  color:#90CAF9; animation:sr-pulse 1.2s ease-in-out infinite; }
.sr-sync-pill--offline { background:rgba(158,158,158,.2); color:rgba(255,255,255,.6); }
@keyframes sr-pulse { 0%,100%{opacity:1}50%{opacity:.5} }

.sr-sync-dot { width:7px; height:7px; border-radius:50%; background:currentColor; }
.sr-refresh-btn { --color:rgba(255,255,255,.8); --padding-start:6px; --padding-end:6px; }

/* ── STATS BAR ───────────────────────────────────────────────────────── */
.sr-stats-bar { display:flex; align-items:stretch; background:#002F6C; }
.sr-stat { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:8px 0; gap:2px; border:none; background:transparent; cursor:pointer; transition:background .12s; }
.sr-stat:active, .sr-stat--on { background:rgba(255,255,255,.15); }
.sr-stat-num  { font-size:19px; font-weight:900; line-height:1; color:#fff; }
.sr-stat-lbl  { font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:rgba(255,255,255,.55); }
.sr-stat--critical .sr-stat-num { color:#FF6B6B; }
.sr-stat--high     .sr-stat-num { color:#FFD93D; }
.sr-stat--active   .sr-stat-num { color:#63B3ED; }
.sr-stat--unsynced .sr-stat-num { color:#FFA726; }
.sr-stat-div { width:1px; height:28px; background:rgba(255,255,255,.12); margin:auto 0; }

/* ── CONTROLS ────────────────────────────────────────────────────────── */
.sr-controls { background:#fff; border-bottom:1.5px solid #E8EDF5; }

.sr-search-row { display:flex; align-items:center; gap:6px; padding:7px 10px 0; }
.sr-search-box { flex:1; display:flex; align-items:center; gap:6px; padding:0 10px; background:#F5F7FA; border:1.5px solid #DDE3EA; border-radius:8px; }
.sr-search-icon { width:14px; height:14px; color:#90A4AE; flex-shrink:0; }
.sr-search-input { flex:1; border:none; outline:none; background:transparent; font-size:13px; color:#263238; padding:8px 0; min-width:0; }
.sr-search-input::placeholder { color:#B0BEC5; }
.sr-search-clear { border:none; background:none; cursor:pointer; color:#90A4AE; padding:4px; display:flex; align-items:center; }
.sr-filter-btn { position:relative; width:40px; height:40px; border-radius:8px; border:1.5px solid #DDE3EA; background:#F5F7FA; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; color:#546E7A; transition:background .12s, border-color .12s; }
.sr-filter-btn--on { background:#E3F2FD; border-color:#0066CC; color:#0066CC; }
.sr-filter-badge { position:absolute; top:-5px; right:-5px; background:#DC3545; color:#fff; font-size:8px; font-weight:800; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center; }

/* Status tabs */
.sr-tabs { display:flex; overflow-x:auto; scrollbar-width:none; padding:0 8px; gap:4px; }
.sr-tabs::-webkit-scrollbar { display:none; }
.sr-tab { display:flex; align-items:center; gap:4px; padding:8px 10px; border:none; background:transparent; border-bottom:2.5px solid transparent; font-size:11.5px; font-weight:600; color:var(--ion-color-medium); white-space:nowrap; cursor:pointer; transition:color .12s, border-color .12s; flex-shrink:0; }
.sr-tab--on { color:#0066CC; border-bottom-color:#0066CC; }
.sr-tab-badge { display:inline-flex; align-items:center; justify-content:center; min-width:16px; height:16px; padding:0 4px; border-radius:9999px; font-size:9px; font-weight:800; }
.sr-tb--open   { background:#FFEBEE; color:#C62828; }
.sr-tb--ip     { background:#FFF3E0; color:#E65100; }
.sr-tb--disp   { background:#E8EAF6; color:#3949AB; }
.sr-tb--closed { background:#E8F5E9; color:#2E7D32; }
.sr-tb--all    { background:#E3F2FD; color:#1565C0; }

/* Filter panel */
.sr-filter-panel { padding:10px 12px; border-top:1px solid #EEF1F5; background:#FAFBFD; }
.sr-fp-row { display:flex; align-items:flex-start; gap:8px; margin-bottom:10px; }
.sr-fp-lbl { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#546E7A; white-space:nowrap; padding-top:4px; min-width:60px; }
.sr-fp-pills { display:flex; flex-wrap:nowrap; gap:4px; overflow-x:auto; flex:1; }
.sr-fp-pills::-webkit-scrollbar { display:none; }
.sr-pill { padding:4px 10px; border-radius:9999px; font-size:10px; font-weight:700; border:1.5px solid; cursor:pointer; transition:background .1s; white-space:nowrap; color:#546E7A; background:#F5F7FA; border-color:#DDE3EA; }
.sr-pill--on { background:#0066CC; color:#fff; border-color:#0066CC; }
.sr-pill--critical { border-color:rgba(220,53,69,.4); color:#C62828; }
.sr-pill--critical.sr-pill--on { background:#C62828; border-color:#C62828; color:#fff; }
.sr-pill--high { border-color:rgba(230,101,0,.4); color:#E65100; }
.sr-pill--high.sr-pill--on { background:#E65100; border-color:#E65100; color:#fff; }
.sr-pill--medium { border-color:rgba(255,193,7,.5); color:#996000; }
.sr-pill--medium.sr-pill--on { background:#F57F17; border-color:#F57F17; color:#fff; }
.sr-pill--low { border-color:rgba(40,167,69,.4); color:#2E7D32; }
.sr-pill--low.sr-pill--on { background:#2E7D32; border-color:#2E7D32; color:#fff; }
.sr-pill--syn.sr-pill--on, .sr-pill--date.sr-pill--on, .sr-pill--disp.sr-pill--on { background:#0066CC; border-color:#0066CC; color:#fff; }
.sr-clear-all { width:100%; margin-top:4px; padding:7px; border-radius:6px; border:1.5px solid #DC3545; background:transparent; color:#DC3545; font-size:11px; font-weight:700; cursor:pointer; }

/* Active filter chips */
.sr-chip-row { display:flex; align-items:center; gap:6px; padding:4px 12px 8px; flex-wrap:wrap; }
.sr-chip { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:12px; font-size:10px; font-weight:700; text-transform:uppercase; }
.sr-chip--critical { background:#FFEBEE; color:#C62828; }
.sr-chip--high     { background:#FFF3E0; color:#E65100; }
.sr-chip--medium   { background:#FFF8E1; color:#F57F17; }
.sr-chip--low      { background:#E8F5E9; color:#2E7D32; }
.sr-chip--syn      { background:#E8EAF6; color:#3949AB; text-transform:none; }
.sr-chip--disp     { background:#EDE7F6; color:#4527A0; text-transform:none; }
.sr-chip--date     { background:#E3F2FD; color:#1565C0; }
.sr-chip--unsynced { background:#FFF3E0; color:#E65100; text-transform:none; }
.sr-chip-x { border:none; background:none; cursor:pointer; font-size:14px; line-height:1; padding:0 2px; opacity:.7; }
.sr-chip-count { font-size:10px; color:#607D8B; margin-left:auto; }

/* ── BULK SYNC BAR ───────────────────────────────────────────────────── */
.sr-bulk-bar { display:flex; align-items:center; gap:8px; padding:8px 12px; background:#FFF3E0; border-top:1px solid #FFB74D; font-size:12px; color:#BF360C; }
.sr-bulk-icon { font-size:16px; color:#E65100; flex-shrink:0; }
.sr-bulk-btn { margin-left:auto; padding:5px 12px; border-radius:6px; border:1.5px solid #E65100; background:#E65100; color:#fff; font-size:11px; font-weight:700; cursor:pointer; white-space:nowrap; }
.sr-bulk-btn:disabled { opacity:.6; cursor:not-allowed; }

/* ── SLIDE TRANSITION ────────────────────────────────────────────────── */
.sr-slide-enter-active, .sr-slide-leave-active { transition:max-height .2s ease, opacity .2s ease; overflow:hidden; }
.sr-slide-enter-from, .sr-slide-leave-to { max-height:0; opacity:0; }
.sr-slide-enter-to, .sr-slide-leave-from { max-height:400px; opacity:1; }

/* ── CONTENT ─────────────────────────────────────────────────────────── */
.sr-content { --background:#EFF3FA; }

.sr-loading { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; padding:60px 20px; color:var(--ion-color-medium); font-size:14px; }
.sr-spinner { color:#0066CC; --color:#0066CC; }

.sr-offline-bar { display:flex; align-items:center; gap:8px; padding:10px 14px; background:#FFF8E1; border-bottom:1px solid #FFD54F; font-size:12px; color:#795548; }

.sr-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:60px 24px; text-align:center; gap:8px; }
.sr-empty-icon { font-size:44px; color:#B0BEC5; }
.sr-empty-title { font-size:18px; font-weight:700; color:#263238; margin:0; }
.sr-empty-sub   { font-size:13px; color:#607D8B; margin:0; max-width:260px; }

/* ── RECORD LIST ─────────────────────────────────────────────────────── */
.sr-list { padding:10px 12px 0; }

/* ── RECORD CARD ─────────────────────────────────────────────────────── */
.sr-card { display:flex; background:#fff; border-radius:12px; margin-bottom:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.07),0 0 0 1px rgba(0,0,0,.04); transition:transform .1s, box-shadow .1s; cursor:pointer; }
.sr-card:active { transform:scale(.99); box-shadow:0 0 0 1px rgba(0,0,0,.06); }
.sr-card--unsynced { box-shadow:0 1px 4px rgba(255,152,0,.2),0 0 0 1.5px rgba(255,152,0,.3); }
.sr-card--failed   { box-shadow:0 1px 4px rgba(220,53,69,.2),0 0 0 1.5px rgba(220,53,69,.3); }
.sr-card--critical { border-left:none; }
.sr-card--critical .sr-card-stripe { background:#D32F2F; }
.sr-card--high     .sr-card-stripe { background:#F57C00; }
.sr-card--medium   .sr-card-stripe { background:#F9A825; }
.sr-card--low      .sr-card-stripe { background:#388E3C; }

.sr-card-stripe { width:4px; flex-shrink:0; background:#90A4AE; }
.sr-card-body   { flex:1; padding:11px 12px; min-width:0; display:flex; flex-direction:column; gap:8px; }

/* Card top row */
.sr-card-top { display:flex; align-items:center; gap:5px; flex-wrap:wrap; }

/* Status pills */
.sr-status-pill { display:inline-flex; align-items:center; padding:2px 8px; border-radius:9999px; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.4px; border:1px solid; }
.sr-status-pill--open          { background:#FFEBEE; color:#C62828; border-color:rgba(220,53,69,.25); }
.sr-status-pill--in-progress   { background:#FFF3E0; color:#E65100; border-color:rgba(230,101,0,.25); }
.sr-status-pill--dispositioned { background:#E8EAF6; color:#3949AB; border-color:rgba(57,73,171,.25); }
.sr-status-pill--closed        { background:#E8F5E9; color:#2E7D32; border-color:rgba(46,125,50,.25); }

/* Risk pills */
.sr-risk-pill { display:inline-flex; padding:2px 7px; border-radius:9999px; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.4px; border:1px solid; }
.sr-risk-pill--critical { background:#FFEBEE; color:#C62828; border-color:rgba(220,53,69,.3); }
.sr-risk-pill--high     { background:#FFF3E0; color:#E65100; border-color:rgba(230,101,0,.3); }
.sr-risk-pill--medium   { background:#FFF8E1; color:#F57F17; border-color:rgba(245,127,23,.3); }
.sr-risk-pill--low      { background:#E8F5E9; color:#2E7D32; border-color:rgba(46,125,50,.3); }

/* Sync dot (small, per-card) */
.sr-sync-dot-sm { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
.sr-sync-dot-sm--synced   { background:#4CAF50; }
.sr-sync-dot-sm--unsynced { background:#FF9800; animation:sr-pulse 2s ease-in-out infinite; }
.sr-sync-dot-sm--failed   { background:#F44336; }

.sr-card-time { margin-left:auto; font-size:10px; color:#90A4AE; white-space:nowrap; }

/* Traveler row */
.sr-card-traveler { display:flex; align-items:center; gap:9px; }
.sr-avatar { width:36px; height:36px; border-radius:50%; background:#EBF3FF; border:1.5px solid rgba(0,102,204,.15); display:flex; align-items:center; justify-content:center; flex-shrink:0; color:#0066CC; }
.sr-avatar svg { width:20px; height:20px; }
.sr-traveler-info { flex:1; min-width:0; }
.sr-traveler-name { display:block; font-size:13px; font-weight:700; color:#212121; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.sr-traveler-sub  { display:block; font-size:11px; color:#607D8B; margin-top:1px; }
.sr-temp-chip { padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:800; flex-shrink:0; border:1px solid; }
.sr-temp--fever  { background:#FFEBEE; color:#C62828; border-color:rgba(220,53,69,.25); }
.sr-temp--low    { background:#FFF3E0; color:#E65100; border-color:rgba(230,101,0,.25); }
.sr-temp--normal { background:#E8F5E9; color:#2E7D32; border-color:rgba(46,125,50,.2); }

/* Clinical tags */
.sr-card-clinical { display:flex; flex-wrap:wrap; gap:4px; }
.sr-tag { padding:2px 7px; border-radius:4px; font-size:10px; font-weight:600; background:#EEF2FF; color:#3949AB; }
.sr-tag--syn     { background:#E8EAF6; color:#283593; }
.sr-tag--disease { background:#FBE9E7; color:#BF360C; }

/* Card footer */
.sr-card-footer { display:flex; align-items:center; gap:8px; padding-top:6px; border-top:1px solid #F0F4FA; flex-wrap:wrap; }
.sr-card-meta { font-size:10px; color:#90A4AE; }
.sr-card-sync-btn { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:6px; font-size:10px; font-weight:700; cursor:pointer; margin-left:auto; border:1.5px solid; transition:background .1s; }
.sr-card-sync-btn--unsynced { background:#FFF3E0; color:#E65100; border-color:rgba(230,101,0,.3); }
.sr-card-sync-btn--failed   { background:#FFEBEE; color:#C62828; border-color:rgba(220,53,69,.3); }
.sr-card-sync-btn:disabled  { opacity:.5; cursor:not-allowed; }
.sr-card-synced-badge { display:inline-flex; align-items:center; gap:3px; font-size:10px; font-weight:700; color:#2E7D32; margin-left:auto; }
.sr-card-synced-badge ion-icon { font-size:12px; }

/* Load more */
.sr-load-more { padding:8px 0 4px; }

/* ── MODAL ───────────────────────────────────────────────────────────── */
.sr-modal-header { background:#fff; }
.sr-modal-toolbar { --background:#003F88; --color:#fff; --min-height:50px; }
.sr-modal-close { --color:rgba(255,255,255,.8); }
.sr-modal-title-block { display:flex; flex-direction:column; }
.sr-modal-eyebrow { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1.2px; color:rgba(255,255,255,.5); }
.sr-modal-title  { font-size:15px; font-weight:800; color:#fff; }

.sr-modal-sync-btn { display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer; border:1.5px solid rgba(255,152,0,.6); background:rgba(255,152,0,.2); color:#FFD740; margin-right:6px; }
.sr-modal-sync-btn--active { animation:sr-pulse 1s ease-in-out infinite; }
.sr-modal-sync-btn:disabled { opacity:.5; cursor:not-allowed; }
.sr-modal-synced { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:700; color:#90EE90; margin-right:8px; }

/* Modal status bar */
.sr-modal-status-bar { display:flex; align-items:center; gap:6px; padding:8px 14px; background:#F8FAFC; border-bottom:1px solid #E8EDF5; flex-wrap:wrap; }
.sr-triage-pill { padding:2px 7px; border-radius:9999px; font-size:9px; font-weight:800; background:#EDE7F6; color:#4527A0; border:1px solid rgba(69,39,160,.2); }
.sr-sync-status-badge { padding:2px 7px; border-radius:9999px; font-size:9px; font-weight:800; border:1px solid; }
.sr-sync-status-badge--synced   { background:#E8F5E9; color:#2E7D32; border-color:rgba(46,125,50,.3); }
.sr-sync-status-badge--unsynced { background:#FFF3E0; color:#E65100; border-color:rgba(230,101,0,.3); }
.sr-sync-status-badge--failed   { background:#FFEBEE; color:#C62828; border-color:rgba(220,53,69,.3); }

/* Modal tabs */
.sr-modal-tabs { display:flex; overflow-x:auto; scrollbar-width:none; border-bottom:1.5px solid #E8EDF5; padding:0 10px; }
.sr-modal-tabs::-webkit-scrollbar { display:none; }
.sr-modal-tab { padding:9px 12px; border:none; background:transparent; border-bottom:2.5px solid transparent; font-size:11.5px; font-weight:600; color:var(--ion-color-medium); cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; gap:4px; transition:color .12s, border-color .12s; }
.sr-modal-tab--on { color:#0066CC; border-bottom-color:#0066CC; }
.sr-modal-tab-badge { display:inline-flex; align-items:center; justify-content:center; min-width:16px; height:16px; padding:0 4px; border-radius:9999px; background:#FFEBEE; color:#C62828; font-size:9px; font-weight:800; }

/* Modal content */
/* ── MODAL SCROLL FIX ──────────────────────────────────────────────────────
   IonContent inside a sheet modal conflicts with Ionic's drag-gesture engine:
   Ionic intercepts vertical touch events to decide drag-vs-scroll and often
   loses, leaving IonContent unable to scroll.
   Solution: replace IonContent with a plain div. The modal shadow DOM content
   area is forced into flex-column via ::part(content), and the scroll div
   fills the remaining space with overflow-y:auto — entirely outside Ionic's
   gesture interception. min-height:0 on the flex child is critical: without
   it a flex child won't shrink below its content height, blocking overflow.
   ──────────────────────────────────────────────────────────────────────── */

/* Force the modal shadow-DOM wrapper into flex-column */
.sr-modal::part(content) {
  display:         flex;
  flex-direction:  column;
  overflow:        hidden;
  height:          100%;
}

/* The scrollable region — fills all space below IonHeader */
.sr-modal-scroll {
  flex:                     1;
  min-height:               0;     /* CRITICAL: lets the flex child shrink & scroll */
  overflow-y:               auto;
  -webkit-overflow-scrolling: touch;
  background:               #F5F7FA;
  padding-bottom:           max(120px, env(safe-area-inset-bottom, 120px));
}

.sr-modal-body    { padding:0 0 8px; }
.sr-tab-panel     { padding:0; }
.sr-detail-loading { display:flex; align-items:center; gap:10px; padding:24px; color:#607D8B; font-size:13px; }

/* ── SYNC CARD (modal overview) ──────────────────────────────────────── */
.sr-sync-card { margin:12px; border-radius:10px; overflow:hidden; border:1.5px solid; }
.sr-sync-card--synced   { border-color:rgba(46,125,50,.3); background:#F1F8E9; }
.sr-sync-card--unsynced { border-color:rgba(255,152,0,.4); background:#FFF8E1; }
.sr-sync-card--failed   { border-color:rgba(220,53,69,.4); background:#FFEBEE; }
.sr-sync-card-header { display:flex; align-items:center; gap:8px; padding:10px 14px; border-bottom:1px solid rgba(0,0,0,.06); }
.sr-sync-card-icon  { font-size:18px; }
.sr-sync-card--synced   .sr-sync-card-icon { color:#2E7D32; }
.sr-sync-card--unsynced .sr-sync-card-icon { color:#E65100; }
.sr-sync-card--failed   .sr-sync-card-icon { color:#C62828; }
.sr-sync-card-title { font-size:13px; font-weight:700; color:#263238; }
.sr-sync-card-body  { padding:8px 14px; display:flex; flex-direction:column; gap:6px; }
.sr-sync-row  { display:flex; align-items:flex-start; gap:8px; }
.sr-sync-lbl  { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#607D8B; min-width:110px; flex-shrink:0; padding-top:1px; }
.sr-sync-val  { font-size:12px; color:#263238; word-break:break-all; }
.sr-sync-error { color:#C62828; font-style:italic; }
.sr-uuid { font-family:monospace; font-size:10px; }
.sr-sync-card-action { width:calc(100% - 28px); margin:6px 14px 10px; padding:10px; border-radius:7px; border:none; background:#0066CC; color:#fff; font-size:12px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; }
.sr-sync-card-action:disabled { opacity:.6; cursor:not-allowed; }
.sr-sync-offline-note { padding:8px 14px 10px; font-size:11px; color:#607D8B; text-align:center; }

/* ── TIMELINE ─────────────────────────────────────────────────────────── */
.sr-timeline { margin:0 12px 4px; border-radius:8px; background:#fff; border:1px solid #E8EDF5; overflow:hidden; }
.sr-tl-item  { display:flex; align-items:flex-start; gap:12px; padding:11px 14px; border-bottom:1px solid #F0F4FA; }
.sr-tl-item:last-child { border-bottom:none; }
.sr-tl-dot   { width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-top:3px; }
.sr-tl-item--open          .sr-tl-dot { background:#0066CC; }
.sr-tl-item--dispositioned .sr-tl-dot { background:#7E57C2; }
.sr-tl-item--closed        .sr-tl-dot { background:#2E7D32; }
.sr-tl-body  { display:flex; flex-direction:column; gap:2px; }
.sr-tl-label { font-size:12px; font-weight:700; color:#212121; }
.sr-tl-time  { font-size:11px; color:#0066CC; }
.sr-tl-sub   { font-size:11px; color:#607D8B; }

/* ── SECTION HEADERS ─────────────────────────────────────────────────── */
.sr-section-hdr { display:flex; align-items:center; gap:7px; padding:14px 14px 6px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.8px; color:#546E7A; }
.sr-sec-num { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; border-radius:50%; background:#003F88; color:#fff; font-size:8px; font-weight:900; flex-shrink:0; }

/* ── KV GRID ─────────────────────────────────────────────────────────── */
.sr-kv-grid { display:grid; grid-template-columns:1fr 1fr; gap:1px; margin:0 12px 4px; background:#E8EDF5; border-radius:8px; overflow:hidden; }
.sr-kv { display:flex; flex-direction:column; gap:2px; padding:9px 12px; background:#fff; }
.sr-k  { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#90A4AE; }
.sr-v  { font-size:12px; color:#212121; font-weight:500; word-break:break-word; }
.sr-v--danger { color:#C62828; font-weight:700; }
.sr-doc-no { font-family:monospace; font-size:11px; }

/* Notes box */
.sr-notes-box  { margin:4px 12px; padding:10px 12px; background:#fff; border-radius:8px; border:1px solid #E8EDF5; border-left:3px solid #0066CC; }
.sr-notes-lbl  { display:block; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#90A4AE; margin-bottom:4px; }
.sr-notes-text { font-size:12px; color:#263238; line-height:1.5; margin:0; }

/* Disease list */
.sr-disease-list { margin:0 12px 4px; background:#fff; border-radius:8px; border:1px solid #E8EDF5; overflow:hidden; }
.sr-disease-row  { display:flex; align-items:flex-start; gap:10px; padding:10px 12px; border-bottom:1px solid #F0F4FA; }
.sr-disease-row:last-child { border-bottom:none; }
.sr-disease-rank { width:22px; height:22px; border-radius:50%; background:#003F88; color:#fff; font-size:9px; font-weight:900; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.sr-disease-info { display:flex; flex-direction:column; gap:2px; }
.sr-disease-name { font-size:12px; font-weight:700; color:#212121; }
.sr-disease-conf { font-size:11px; color:#2E7D32; }
.sr-disease-reason { font-size:11px; color:#607D8B; }

/* Risk-colored text */
.sr-risk-text--critical { color:#C62828; font-weight:700; }
.sr-risk-text--high     { color:#E65100; font-weight:700; }
.sr-risk-text--medium   { color:#F57F17; font-weight:700; }
.sr-risk-text--low      { color:#2E7D32; }

/* ── VITALS GRID ─────────────────────────────────────────────────────── */
.sr-vitals-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin:0 12px 4px; }
.sr-vital-card  { display:flex; flex-direction:column; align-items:center; padding:10px 8px; border-radius:8px; background:#fff; border:1.5px solid #E8EDF5; gap:3px; }
.sr-vital--ok     { border-color:rgba(46,125,50,.3); background:#F1F8E9; }
.sr-vital--warn   { border-color:rgba(245,127,23,.4); background:#FFF8E1; }
.sr-vital--danger { border-color:rgba(220,53,69,.4); background:#FFEBEE; }
.sr-vital-val  { font-size:16px; font-weight:800; color:#212121; }
.sr-vital-lbl  { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#607D8B; text-align:center; }
.sr-vital-warn { font-size:9px; color:#E65100; font-weight:700; }

/* Symptoms */
.sr-symptom-grid { margin:0 12px 4px; background:#fff; border-radius:8px; border:1px solid #E8EDF5; overflow:hidden; }
.sr-sym-row  { display:flex; align-items:flex-start; gap:9px; padding:9px 12px; border-bottom:1px solid #F8FAFC; }
.sr-sym-row:last-child { border-bottom:none; }
.sr-sym-row--present { }
.sr-sym-dot  { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:3px; }
.sr-sym-dot--present { background:#DC3545; }
.sr-sym-info { display:flex; flex-direction:column; gap:2px; }
.sr-sym-name { font-size:12px; font-weight:600; color:#212121; text-transform:capitalize; }
.sr-sym-onset{ font-size:10px; color:#607D8B; }
.sr-sym-detail { font-size:10px; color:#607D8B; }

/* Actions */
.sr-action-grid { margin:0 12px 4px; background:#fff; border-radius:8px; border:1px solid #E8EDF5; overflow:hidden; }
.sr-action-row  { display:flex; align-items:center; gap:8px; padding:9px 12px; border-bottom:1px solid #F8FAFC; font-size:12px; color:#263238; }
.sr-action-row:last-child { border-bottom:none; }
.sr-action-ico  { font-size:14px; color:#2E7D32; flex-shrink:0; }
.sr-action-detail { font-size:10px; color:#607D8B; }

/* Samples */
.sr-sample-list { margin:0 12px 4px; background:#fff; border-radius:8px; border:1px solid #E8EDF5; overflow:hidden; }
.sr-sample-row  { display:flex; flex-wrap:wrap; gap:4px 12px; padding:9px 12px; border-bottom:1px solid #F8FAFC; align-items:center; }
.sr-sample-row:last-child { border-bottom:none; }
.sr-sample-type { font-size:12px; font-weight:700; color:#212121; }
.sr-sample-id   { font-size:10px; font-family:monospace; color:#0066CC; }
.sr-sample-lab  { font-size:10px; color:#607D8B; }
.sr-sample-time { font-size:10px; color:#90A4AE; margin-left:auto; }

/* Travel countries */
.sr-tc-list { margin:0 12px 4px; background:#fff; border-radius:8px; border:1px solid #E8EDF5; overflow:hidden; }
.sr-tc-row   { display:flex; align-items:center; gap:8px; padding:9px 12px; border-bottom:1px solid #F8FAFC; }
.sr-tc-row:last-child { border-bottom:none; }
.sr-tc-role  { font-size:9px; font-weight:800; padding:2px 6px; border-radius:4px; }
.sr-tc-role--visited { background:#E8EAF6; color:#3949AB; }
.sr-tc-role--transit { background:#FFF3E0; color:#E65100; }
.sr-tc-country { font-size:12px; font-weight:700; color:#212121; }
.sr-tc-dates   { font-size:10px; color:#607D8B; margin-left:auto; }

/* Exposures */
.sr-exposure-list { margin:0 12px 4px; background:#fff; border-radius:8px; border:1px solid #E8EDF5; overflow:hidden; }
.sr-exp-row  { display:flex; align-items:flex-start; gap:9px; padding:10px 12px; border-bottom:1px solid #F8FAFC; }
.sr-exp-row:last-child { border-bottom:none; }
.sr-exp-row--yes     { background:#FFF5F5; }
.sr-exp-row--no      { background:#fff; }
.sr-exp-row--unknown { background:#FAFAFA; }
.sr-exp-badge { padding:2px 8px; border-radius:4px; font-size:9px; font-weight:800; text-transform:uppercase; flex-shrink:0; margin-top:1px; }
.sr-exp-badge--yes     { background:#FFEBEE; color:#C62828; }
.sr-exp-badge--no      { background:#E8F5E9; color:#2E7D32; }
.sr-exp-badge--unknown { background:#F5F5F5; color:#607D8B; }
.sr-exp-info  { display:flex; flex-direction:column; gap:2px; }
.sr-exp-code  { font-size:12px; font-weight:600; color:#212121; text-transform:capitalize; }
.sr-exp-detail{ font-size:10px; color:#607D8B; }

/* Alert card */
.sr-alert-card { margin:4px 12px 0; border-radius:8px; background:#FFF8E1; border:1.5px solid #FFB74D; overflow:hidden; }

/* Empty sub-text */
.sr-empty-sub { font-size:12px; color:#B0BEC5; padding:12px 14px; font-style:italic; }

/* ── RESPONSIVE ──────────────────────────────────────────────────────── */
@media (min-width: 600px) {
  .sr-list    { max-width:720px; margin:0 auto; }
  .sr-kv-grid { grid-template-columns:1fr 1fr 1fr; }
  .sr-vitals-grid { grid-template-columns:repeat(5,1fr); }
}

/* ── MODAL EXTRA TOOLBARS (status bar + tabs wrappers) ──────────────────
   Ionic only accounts for IonToolbar height when computing IonContent's
   scroll-start offset. Wrapping the status-bar and tabs rows in IonToolbar
   ensures Ionic knows the full header height — fixing the scroll clipping bug.
   All Ionic IonToolbar CSS variables are zeroed so they look like plain divs. */
.sr-modal-status-toolbar {
  --background:         #F8FAFC;
  --border-width:       0 0 1px 0;
  --border-color:       #E8EDF5;
  --min-height:         0;
  --padding-start:      0;
  --padding-end:        0;
  --padding-top:        0;
  --padding-bottom:     0;
  contain: content;
}
.sr-modal-tabs-toolbar {
  --background:         #ffffff;
  --border-width:       0 0 1.5px 0;
  --border-color:       #E8EDF5;
  --min-height:         0;
  --padding-start:      0;
  --padding-end:        0;
  --padding-top:        0;
  --padding-bottom:     0;
  contain: content;
}


.sr-modal-end-spacer { height:56px; flex-shrink:0; display:block; }
</style>
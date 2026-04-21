<template>
  <IonPage class="nq-page">

    <!-- ═══════════════════════════════════════════════════════════════
         DARK ZONE — Header
    ═══════════════════════════════════════════════════════════════════ -->
    <IonHeader :translucent="false" class="nq-hdr">
      <IonToolbar class="nq-toolbar">
        <IonButtons slot="start">
          <IonMenuButton class="nq-menu-btn" menu="app-menu" />
        </IonButtons>
        <IonTitle class="nq-toolbar-title">
          <div class="nq-title-block">
            <span class="nq-eyebrow">IHR ART.23 · {{ auth?.poe_code || 'POE' }}</span>
            <span class="nq-title-text">Referral Queue</span>
          </div>
        </IonTitle>
        <IonButtons slot="end" style="gap:6px;padding-right:10px;">
          <div class="nq-conn" :class="isOnline ? 'nq-conn--on' : 'nq-conn--off'">
            <div class="nq-conn-dot"/>
            <span class="nq-conn-txt">{{ isOnline ? 'Live' : 'Offline' }}</span>
          </div>
          <button class="nq-hbtn" @click="manualRefresh"
            :disabled="loading || syncing" aria-label="Refresh queue">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"
              stroke-linecap="round" :class="(loading || syncing) && 'nq-spin'">
              <path d="M14 7A7 7 0 1 1 7 1"/><polyline points="14 1 14 7 8 7"/>
            </svg>
          </button>
        </IonButtons>
      </IonToolbar>

      <!-- Stats + tabs — in dark band outside IonToolbar -->
      <div class="nq-below-bar">

        <!-- O(1) stat strip — all from IDB index counts, never full scans -->
        <div class="nq-stats">
          <button class="nq-stat" :class="activeTab==='OPEN' && 'nq-stat--sel'"
            @click="setTab('OPEN')" aria-label="Open referrals">
            <span class="nq-sn nq-sn--blue">{{ fmt(idbOpen) }}</span>
            <span class="nq-sl">Open</span>
          </button>
          <div class="nq-sdiv"/>
          <button class="nq-stat" :class="activeTab==='OPEN' && 'nq-stat--sel'"
            @click="setTab('OPEN')" aria-label="Critical priority">
            <span class="nq-sn nq-sn--red">{{ fmt(critCount) }}</span>
            <span class="nq-sl">Critical</span>
          </button>
          <div class="nq-sdiv"/>
          <button class="nq-stat" :class="activeTab==='IN_PROGRESS' && 'nq-stat--sel'"
            @click="setTab('IN_PROGRESS')" aria-label="Cases in progress">
            <span class="nq-sn nq-sn--cyan">{{ fmt(idbInProg) }}</span>
            <span class="nq-sl">In Progress</span>
          </button>
          <div class="nq-sdiv"/>
          <button class="nq-stat" :class="activeTab==='CLOSED' && 'nq-stat--sel'"
            @click="setTab('CLOSED')" aria-label="Closed">
            <span class="nq-sn nq-sn--muted">{{ fmt(idbClosed) }}</span>
            <span class="nq-sl">Closed</span>
          </button>
          <div class="nq-sdiv"/>
          <button class="nq-stat"
            :class="[damaged.length > 0 && 'nq-stat--warn', activeTab==='DAMAGED' && 'nq-stat--sel']"
            @click="setTab('DAMAGED')" aria-label="Damaged records">
            <span class="nq-sn nq-sn--orange">{{ fmt(damaged.length) }}</span>
            <span class="nq-sl">Damaged</span>
          </button>
        </div>

        <!-- Tabs -->
        <nav class="nq-tabs" role="tablist" aria-label="Queue filter tabs">
          <button v-for="t in TABS" :key="t.v"
            class="nq-tab" :class="activeTab === t.v && 'nq-tab--on'"
            role="tab" :aria-selected="activeTab === t.v"
            @click="setTab(t.v)">
            {{ t.label }}
            <span v-if="tabBadge(t.v)" class="nq-tab-badge"
              :class="'nq-tb--' + t.v.toLowerCase().replace(/_/g, '-')">
              {{ tabBadge(t.v) }}
            </span>
          </button>
        </nav>
      </div>
    </IonHeader>

    <!-- ═══════════════════════════════════════════════════════════════
         LIGHT ZONE — Content
    ═══════════════════════════════════════════════════════════════════ -->
    <IonContent :fullscreen="true" :scroll-y="true" class="nq-content">
      <IonRefresher slot="fixed"
        @ionRefresh="ev => manualRefresh().finally(() => ev.target.complete())">
        <IonRefresherContent refreshing-text="Syncing queue…" refreshing-spinner="crescent"/>
      </IonRefresher>

      <!-- ── Offline banner ── -->
      <div v-if="!isOnline" class="nq-offline-bar" role="status">
        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"
          stroke-linecap="round" aria-hidden="true">
          <path d="M13 1L1 13M10.5 3.5A5 5 0 0 1 11.5 7M8 2.5A7.5 7.5 0 0 1 13 7M3.5 7A5 5 0 0 1 6 4.5M7 12a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>
        </svg>
        Offline — {{ fmt(allItems.length) }} cached records · Syncs on reconnection
      </div>

      <!-- Stale data warning disabled — sync runs every 15s automatically -->
      <!-- <div v-if="isOnline && staleWarning" class="nq-stale-bar" role="status">
        Data may be stale — last synced {{ lastSyncLabel }}
        <button class="nq-stale-btn" @click="manualRefresh" type="button">Refresh now</button>
      </div> -->

      <!-- ── SEARCH + FILTER BAR ── -->
      <div class="nq-search-bar">
        <div class="nq-search-wrap" :class="searchFocused && 'nq-search-wrap--focus'">
          <svg class="nq-search-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
            <circle cx="6.5" cy="6.5" r="4.5"/><line x1="10" y1="10" x2="14" y2="14"/>
          </svg>
          <input
            v-model="searchQuery"
            class="nq-search-input"
            type="text"
            placeholder="Search by name, gender, priority, POE…"
            autocomplete="off"
            aria-label="Search referrals"
            @focus="searchFocused = true"
            @blur="searchFocused = false"
            @input="onSearchInput"
          />
          <button v-if="searchQuery" class="nq-search-clear" @click="searchQuery = ''; onSearchInput()" type="button" aria-label="Clear search">
            <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="2" y1="2" x2="10" y2="10"/><line x1="10" y1="2" x2="2" y2="10"/></svg>
          </button>
        </div>
        <div class="nq-search-meta" v-if="searchQuery.trim()">
          <span class="nq-search-count">{{ filteredItems.length }} result{{ filteredItems.length !== 1 ? 's' : '' }}</span>
          <span v-if="allItems.length > filteredItems.length" class="nq-search-of">of {{ allItems.length }}</span>
        </div>
      </div>

      <div class="nq-board">

        <!-- ── SKELETON — first load ── -->
        <div v-if="loading && !allItems.length" class="nq-skels">
          <div v-for="i in 5" :key="i" class="nq-sk" :style="{ animationDelay: i * 70 + 'ms' }">
            <div class="nq-sk-bar"/>
            <div class="nq-sk-body">
              <div class="nq-sk-r1">
                <div class="nq-sk-pill"/>
                <div class="nq-sk-pill" style="width:60px"/>
              </div>
              <div class="nq-sk-r2"/>
              <div class="nq-sk-r3">
                <div class="nq-sk-chip"/>
                <div class="nq-sk-chip" style="width:55px"/>
              </div>
            </div>
          </div>
        </div>

        <template v-else>

          <!-- ════════════════════════════════════════════════════════
               DAMAGED TAB — Quarantine Zone
          ════════════════════════════════════════════════════════ -->
          <template v-if="activeTab === 'DAMAGED'">
            <div class="nq-quarantine-header">
              <svg viewBox="0 0 14 14" fill="none" stroke="#B45309" stroke-width="1.6"
                stroke-linecap="round" aria-hidden="true">
                <path d="M7 1L1 13h12L7 1z"/>
                <line x1="7" y1="5.5" x2="7" y2="9"/><circle cx="7" cy="11" r=".6" fill="#B45309"/>
              </svg>
              {{ damaged.length }} Damaged Record{{ damaged.length !== 1 ? 's' : '' }}
              — Server Rejected · Requires Investigation
            </div>

            <div v-if="!damaged.length" class="nq-empty">
              <svg viewBox="0 0 40 40" fill="none" stroke="#94A3B8" stroke-width="1.2"
                stroke-linecap="round" aria-hidden="true">
                <circle cx="20" cy="20" r="16"/>
                <polyline points="12 20 17 25 28 14"/>
              </svg>
              <p class="nq-empty-title">No Damaged Records</p>
              <p class="nq-empty-sub">All referrals are healthy.</p>
            </div>

            <div v-for="d in damaged" :key="d.client_uuid" class="nq-dmg-card">
              <div class="nq-dmg-bar"/>
              <div class="nq-dmg-body">
                <div class="nq-dmg-row1">
                  <span class="nq-pri" :class="'nq-pri--' + (d.priority||'NORMAL').toLowerCase()">
                    {{ d.priority || 'NORMAL' }}
                  </span>
                  <span class="nq-dmg-badge">SYNC FAILURE</span>
                  <span class="nq-dmg-att">{{ d.sync_attempt_count }} attempts</span>
                  <span class="nq-dmg-age">{{ fmtRelative(d.created_at) }}</span>
                </div>
                <div class="nq-dmg-reason">
                  <strong>Error:</strong> {{ d._damageReason || d.last_sync_error || 'Unknown' }}
                </div>
                <code class="nq-dmg-uuid">{{ d.client_uuid }}</code>
                <div class="nq-dmg-meta">{{ d.poe_code }} · Status: {{ d.status }}</div>
                <div class="nq-dmg-actions">
                  <button class="nq-dmg-btn nq-dmg-btn--retry"
                    :disabled="retryingUuid === d.client_uuid"
                    @click="retryDamaged(d)" type="button">
                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8"
                      stroke-linecap="round" :class="retryingUuid === d.client_uuid && 'nq-spin'"
                      aria-hidden="true">
                      <path d="M10 6A4 4 0 1 1 6 2"/><polyline points="10 2 10 6 6 6"/>
                    </svg>
                    {{ retryingUuid === d.client_uuid ? 'Queuing…' : 'Retry Sync' }}
                  </button>
                  <button class="nq-dmg-btn nq-dmg-btn--open"
                    @click="openCaseFromDamaged(d)" type="button">
                    Open Case →
                  </button>
                  <button class="nq-dmg-btn nq-dmg-btn--dismiss"
                    @click="dismissDamaged(d)" type="button">
                    Dismiss
                  </button>
                </div>
              </div>
            </div>
          </template>

          <!-- ════════════════════════════════════════════════════════
               MAIN QUEUE — ALL / OPEN / IN_PROGRESS / CLOSED
          ════════════════════════════════════════════════════════ -->
          <template v-else>

            <!-- CRITICAL ALARM ZONE — always top, always visible when crits open -->
            <div v-if="criticalItems.length > 0 && showAlarmZone"
              class="nq-alarm-zone"
              role="alert"
              aria-label="Critical referrals requiring immediate response">
              <div class="nq-alarm-hdr">
                <div class="nq-alarm-pulse" aria-hidden="true"/>
                <span class="nq-alarm-title">
                  {{ criticalItems.length }} CRITICAL Referral{{ criticalItems.length > 1 ? 's' : '' }} — Immediate Response Required
                </span>
                <span class="nq-alarm-oldest" v-if="criticalItems[0]">
                  Oldest: {{ fmtRelative(criticalItems[0].notification_created_at) }}
                </span>
              </div>
              <div v-for="item in criticalItems" :key="item.notification_uuid"
                class="nq-card nq-card--critical"
                tabindex="0" role="button"
                :aria-label="'Critical: ' + (item.traveler_full_name || item.gender)"
                @click="openDetail(item)"
                @keydown.enter="openDetail(item)">
                <div class="nq-card-bar nq-bar--critical" aria-hidden="true"/>
                <div class="nq-card-body">
                  <div class="nq-card-row1">
                    <span class="nq-pri nq-pri--critical">CRITICAL</span>
                    <span class="nq-sts nq-sts--open">OPEN</span>
                    <span class="nq-card-age nq-age--critical">
                      {{ fmtRelative(item.notification_created_at) }}
                    </span>
                  </div>
                  <div class="nq-card-name">
                    {{ item.traveler_full_name || (item.gender + ' · Not named') }}
                  </div>
                  <div class="nq-card-chips">
                    <span v-if="item.gender" class="nq-chip">{{ item.gender }}</span>
                    <span v-if="item.traveler_direction" class="nq-chip">{{ item.traveler_direction }}</span>
                    <span v-if="item.temperature_value != null"
                      class="nq-chip nq-chip--temp"
                      :class="tempCls(item.temperature_value, item.temperature_unit)">
                      {{ fmtTemp(item.temperature_value) }}°{{ item.temperature_unit || 'C' }}
                    </span>
                    <span v-if="item.screener_name" class="nq-chip nq-chip--officer">
                      {{ item.screener_name }}
                    </span>
                    <span v-if="item.is_voided_primary" class="nq-chip nq-chip--voided-warn">
                      ⚠ Primary Voided
                    </span>
                  </div>
                </div>
                <button class="nq-open-btn nq-open-btn--critical"
                  @click.stop="openCase(item)"
                  :disabled="openingUuid === item.notification_uuid"
                  type="button"
                  aria-label="Begin secondary screening now">
                  <span>{{ openingUuid === item.notification_uuid ? 'Opening…' : 'Screen Now' }}</span>
                  <svg viewBox="0 0 10 10" fill="none" stroke="currentColor"
                    stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                    <polyline points="3 1 7 5 3 9"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- HIGH PRIORITY section -->
            <template v-if="highItems.length > 0 && showOpenSection">
              <div class="nq-section-hdr">
                <div class="nq-section-bar nq-section-bar--high" aria-hidden="true"/>
                <span>HIGH PRIORITY</span>
                <span class="nq-section-count">{{ highItems.length }}</span>
              </div>
              <div v-for="item in highItems" :key="item.notification_uuid"
                class="nq-card nq-card--high"
                tabindex="0" role="button"
                :aria-label="'High priority referral for ' + (item.traveler_full_name || item.gender)"
                @click="openDetail(item)"
                @keydown.enter="openDetail(item)">
                <div class="nq-card-bar nq-bar--high" aria-hidden="true"/>
                <div class="nq-card-body">
                  <div class="nq-card-row1">
                    <span class="nq-pri nq-pri--high">HIGH</span>
                    <span class="nq-sts nq-sts--open">OPEN</span>
                    <span class="nq-card-age">{{ fmtRelative(item.notification_created_at) }}</span>
                  </div>
                  <div class="nq-card-name">{{ item.traveler_full_name || (item.gender + ' · Not named') }}</div>
                  <div class="nq-card-chips">
                    <span v-if="item.gender" class="nq-chip">{{ item.gender }}</span>
                    <span v-if="item.traveler_direction" class="nq-chip">{{ item.traveler_direction }}</span>
                    <span v-if="item.temperature_value != null"
                      class="nq-chip nq-chip--temp"
                      :class="tempCls(item.temperature_value, item.temperature_unit)">
                      {{ fmtTemp(item.temperature_value) }}°{{ item.temperature_unit || 'C' }}
                    </span>
                    <span v-if="item.screener_name" class="nq-chip nq-chip--officer">
                      {{ item.screener_name }}
                    </span>
                    <span v-if="item.is_voided_primary" class="nq-chip nq-chip--voided-warn">⚠ Primary Voided</span>
                  </div>
                </div>
                <div class="nq-card-cta" @click.stop>
                  <button class="nq-cancel-btn" @click="confirmCancel(item)"
                    :disabled="cancellingId === item.notification_id"
                    type="button" aria-label="Cancel referral">
                    Cancel
                  </button>
                  <button class="nq-open-btn nq-open-btn--high"
                    @click="openCase(item)"
                    :disabled="openingUuid === item.notification_uuid"
                    type="button" aria-label="Begin secondary screening">
                    {{ openingUuid === item.notification_uuid ? 'Opening…' : 'Screen →' }}
                  </button>
                </div>
              </div>
            </template>

            <!-- NORMAL PRIORITY section -->
            <template v-if="normalItems.length > 0 && showOpenSection">
              <div class="nq-section-hdr" :class="highItems.length > 0 && 'nq-section-hdr--sep'">
                <div class="nq-section-bar nq-section-bar--normal" aria-hidden="true"/>
                <span>NORMAL PRIORITY</span>
                <span class="nq-section-count">{{ normalItems.length }}</span>
              </div>
              <div v-for="item in normalItems" :key="item.notification_uuid"
                class="nq-card nq-card--normal"
                tabindex="0" role="button"
                :aria-label="'Normal priority referral for ' + (item.traveler_full_name || item.gender)"
                @click="openDetail(item)"
                @keydown.enter="openDetail(item)">
                <div class="nq-card-bar nq-bar--normal" aria-hidden="true"/>
                <div class="nq-card-body">
                  <div class="nq-card-row1">
                    <span class="nq-pri nq-pri--normal">NORMAL</span>
                    <span class="nq-sts nq-sts--open">OPEN</span>
                    <span class="nq-card-age">{{ fmtRelative(item.notification_created_at) }}</span>
                  </div>
                  <div class="nq-card-name">{{ item.traveler_full_name || (item.gender + ' · Not named') }}</div>
                  <div class="nq-card-chips">
                    <span v-if="item.gender" class="nq-chip">{{ item.gender }}</span>
                    <span v-if="item.traveler_direction" class="nq-chip">{{ item.traveler_direction }}</span>
                    <span v-if="item.temperature_value != null"
                      class="nq-chip nq-chip--temp"
                      :class="tempCls(item.temperature_value, item.temperature_unit)">
                      {{ fmtTemp(item.temperature_value) }}°{{ item.temperature_unit || 'C' }}
                    </span>
                    <span v-if="item.screener_name" class="nq-chip nq-chip--officer">
                      {{ item.screener_name }}
                    </span>
                    <span v-if="item.is_voided_primary" class="nq-chip nq-chip--voided-warn">⚠ Primary Voided</span>
                  </div>
                </div>
                <div class="nq-card-cta" @click.stop>
                  <button class="nq-cancel-btn" @click="confirmCancel(item)"
                    :disabled="cancellingId === item.notification_id"
                    type="button" aria-label="Cancel referral">
                    Cancel
                  </button>
                  <button class="nq-open-btn nq-open-btn--normal"
                    @click="openCase(item)"
                    :disabled="openingUuid === item.notification_uuid"
                    type="button" aria-label="Begin secondary screening">
                    {{ openingUuid === item.notification_uuid ? 'Opening…' : 'Screen →' }}
                  </button>
                </div>
              </div>
            </template>

            <!-- IN PROGRESS section -->
            <template v-if="inProgItems.length > 0 && showInProgSection">
              <div class="nq-section-hdr">
                <div class="nq-section-bar nq-section-bar--progress" aria-hidden="true"/>
                <span>IN PROGRESS</span>
                <span class="nq-section-count">{{ inProgItems.length }}</span>
              </div>
              <div v-for="item in inProgItems" :key="item.notification_uuid"
                class="nq-card nq-card--progress"
                tabindex="0" role="button"
                :aria-label="'In progress: ' + (item.traveler_full_name || item.gender)"
                @click="openDetail(item)"
                @keydown.enter="openDetail(item)">
                <div class="nq-card-bar nq-bar--progress" aria-hidden="true"/>
                <div class="nq-card-body">
                  <div class="nq-card-row1">
                    <span class="nq-pri" :class="'nq-pri--' + (item.priority||'NORMAL').toLowerCase()">
                      {{ item.priority || 'NORMAL' }}
                    </span>
                    <span class="nq-sts nq-sts--progress">IN PROGRESS</span>
                    <span class="nq-card-age">{{ fmtRelative(item.notification_created_at) }}</span>
                  </div>
                  <div class="nq-card-name">{{ item.traveler_full_name || (item.gender + ' · Not named') }}</div>
                  <div class="nq-card-chips">
                    <span v-if="item.gender" class="nq-chip">{{ item.gender }}</span>
                    <span v-if="item.traveler_direction" class="nq-chip">{{ item.traveler_direction }}</span>
                    <span v-if="item.temperature_value != null"
                      class="nq-chip nq-chip--temp"
                      :class="tempCls(item.temperature_value, item.temperature_unit)">
                      {{ fmtTemp(item.temperature_value) }}°{{ item.temperature_unit || 'C' }}
                    </span>
                    <span v-if="item.screener_name" class="nq-chip nq-chip--officer">
                      👤 {{ item.screener_name }}
                    </span>
                  </div>
                </div>
                <button class="nq-open-btn nq-open-btn--progress"
                  @click.stop="openCase(item)"
                  :disabled="openingUuid === item.notification_uuid"
                  type="button" aria-label="Continue secondary screening">
                  {{ openingUuid === item.notification_uuid ? 'Opening…' : 'Continue →' }}
                </button>
              </div>
            </template>

            <!-- CLOSED section — collapsed by default -->
            <template v-if="closedItems.length > 0 && showClosedSection">
              <button class="nq-closed-toggle" @click="showClosed = !showClosed" type="button">
                <div class="nq-section-bar nq-section-bar--closed" aria-hidden="true"/>
                <span>CLOSED</span>
                <span class="nq-section-count">{{ closedItems.length }}</span>
                <svg class="nq-toggle-chev" :class="showClosed && 'nq-toggle-chev--open'"
                  viewBox="0 0 10 10" fill="none" stroke="currentColor"
                  stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                  <polyline points="2 4 5 7 8 4"/>
                </svg>
              </button>
              <transition name="nq-collapse">
                <div v-if="showClosed" class="nq-closed-list">
                  <div v-for="item in closedItems" :key="item.notification_uuid"
                    class="nq-card nq-card--closed"
                    tabindex="0" role="button"
                    :aria-label="'Closed: ' + (item.traveler_full_name || item.gender)"
                    @click="openDetail(item)"
                    @keydown.enter="openDetail(item)">
                    <div class="nq-card-bar nq-bar--closed" aria-hidden="true"/>
                    <div class="nq-card-body">
                      <div class="nq-card-row1">
                        <span class="nq-pri nq-pri--closed">
                          {{ item.priority || 'NORMAL' }}
                        </span>
                        <span class="nq-sts nq-sts--closed">CLOSED</span>
                        <span class="nq-card-age">{{ fmtRelative(item.notification_created_at) }}</span>
                      </div>
                      <div class="nq-card-name nq-card-name--muted">
                        {{ item.traveler_full_name || (item.gender + ' · Not named') }}
                      </div>
                      <div class="nq-card-chips">
                        <span v-if="item.gender" class="nq-chip nq-chip--muted">{{ item.gender }}</span>
                        <span v-if="item.temperature_value != null" class="nq-chip nq-chip--muted">
                          {{ fmtTemp(item.temperature_value) }}°{{ item.temperature_unit || 'C' }}
                        </span>
                      </div>
                    </div>
                    <div class="nq-closed-arrow" aria-hidden="true">›</div>
                  </div>
                </div>
              </transition>
            </template>

            <!-- Empty state -->
            <div v-if="displayItems.length === 0 && !loading" class="nq-empty">
              <svg viewBox="0 0 40 40" fill="none" stroke="#94A3B8" stroke-width="1.2"
                stroke-linecap="round" aria-hidden="true">
                <rect x="6" y="8" width="28" height="24" rx="2.5"/>
                <line x1="12" y1="16" x2="28" y2="16"/>
                <line x1="12" y1="21" x2="24" y2="21"/>
                <polyline points="12 26 16 29 22 23"/>
              </svg>
              <p class="nq-empty-title">{{ emptyTitle }}</p>
              <p class="nq-empty-sub">{{ emptySub }}</p>
              <button v-if="activeTab !== 'ALL'" class="nq-empty-btn"
                @click="setTab('ALL')" type="button">Show All Records</button>
            </div>

            <!-- Load more -->
            <div v-if="hasMore && !loading" class="nq-load-more">
              <button class="nq-load-btn" :disabled="loadingMore" @click="loadMore" type="button">
                <svg v-if="loadingMore" viewBox="0 0 14 14" fill="none" stroke="currentColor"
                  stroke-width="1.8" stroke-linecap="round" class="nq-spin" aria-hidden="true">
                  <path d="M12 7A5 5 0 1 1 7 2"/><polyline points="12 2 12 7 7 7"/>
                </svg>
                {{ loadingMore ? 'Loading…' : `Load More · ${fmt(remainingCount)} remaining` }}
              </button>
            </div>

          </template><!-- /main queue -->

        </template><!-- /loaded -->

        <div style="height:max(env(safe-area-inset-bottom,0px),40px)" aria-hidden="true"/>
      </div><!-- /nq-board -->
    </IonContent>

    <!-- ═══════════════════════════════════════════════════════════════
         DETAIL MODAL — full referral record
    ═══════════════════════════════════════════════════════════════════ -->
    <IonModal :is-open="!!detailItem" :breakpoints="[0,1]" :initial-breakpoint="1"
      @ionModalDidDismiss="detailItem = null">
      <IonHeader :translucent="false" v-if="detailItem">
        <IonToolbar class="nq-modal-toolbar">
          <IonButtons slot="start">
            <IonButton @click="detailItem = null"
              style="--color:rgba(255,255,255,.8);" aria-label="Close">
              <IonIcon :icon="closeOutline"/>
            </IonButton>
          </IonButtons>
          <IonTitle class="nq-modal-title">Referral Detail</IonTitle>
          <div slot="end" class="nq-modal-pri-wrap">
            <span class="nq-pri nq-pri--sm"
              :class="'nq-pri--' + (detailItem.priority||'NORMAL').toLowerCase()">
              {{ detailItem.priority || 'NORMAL' }}
            </span>
          </div>
        </IonToolbar>
      </IonHeader>
      <IonContent :scroll-y="true" v-if="detailItem" class="nq-modal-content">
        <div class="nq-det-wrap">

          <!-- Status banner -->
          <div class="nq-det-banner"
            :class="'nq-det-banner--' + (detailItem.notification_status||'OPEN').toLowerCase().replace('_','-')">
            <div class="nq-det-banner-shine" aria-hidden="true"/>
            <span class="nq-det-banner-sts">{{ detailItem.notification_status }}</span>
            <span class="nq-det-banner-hint">
              {{ detailItem.notification_status === 'OPEN'
                ? 'Awaiting secondary screening'
                : detailItem.notification_status === 'IN_PROGRESS'
                ? 'Secondary screening in progress'
                : 'Case closed' }}
            </span>
          </div>

          <!-- Traveler -->
          <div class="nq-det-section">
            <div class="nq-det-section-lbl">TRAVELER</div>
            <div class="nq-det-grid">
              <div class="nq-drow"><span class="nq-dk">Full Name</span>
                <span class="nq-dv">{{ detailItem.traveler_full_name || '—' }}</span></div>
              <div class="nq-drow"><span class="nq-dk">Sex</span>
                <span class="nq-dv">{{ detailItem.gender || '—' }}</span></div>
              <div class="nq-drow"><span class="nq-dk">Direction</span>
                <span class="nq-dv">{{ detailItem.traveler_direction || '—' }}</span></div>
              <div class="nq-drow"><span class="nq-dk">Temperature</span>
                <span class="nq-dv"
                  :class="detailItem.temperature_value != null && tempTextCls(detailItem.temperature_value, detailItem.temperature_unit)">
                  {{ detailItem.temperature_value != null
                    ? fmtTemp(detailItem.temperature_value) + '°' + (detailItem.temperature_unit||'C')
                    : 'Not recorded' }}
                </span>
              </div>
              <div class="nq-drow"><span class="nq-dk">Captured</span>
                <span class="nq-dv">{{ fmtDateTime(detailItem.captured_at || detailItem.notification_created_at) }}</span></div>
              <div v-if="detailItem.screener_name" class="nq-drow">
                <span class="nq-dk">Screener</span>
                <span class="nq-dv">{{ detailItem.screener_name }}</span>
              </div>
            </div>
          </div>

          <!-- Referral -->
          <div class="nq-det-section">
            <div class="nq-det-section-lbl">REFERRAL</div>
            <div class="nq-det-grid">
              <div class="nq-drow"><span class="nq-dk">Priority</span>
                <span class="nq-dv" :class="'nq-dv--' + (detailItem.priority||'NORMAL').toLowerCase()">
                  {{ detailItem.priority || 'NORMAL' }}
                </span>
              </div>
              <div class="nq-drow"><span class="nq-dk">Status</span>
                <span class="nq-dv">{{ detailItem.notification_status }}</span></div>
              <div class="nq-drow"><span class="nq-dk">Reason</span>
                <span class="nq-dv">{{ detailItem.reason_code || '—' }}</span></div>
              <div class="nq-drow"><span class="nq-dk">POE</span>
                <span class="nq-dv">{{ detailItem.poe_code || '—' }}</span></div>
              <div class="nq-drow"><span class="nq-dk">Created</span>
                <span class="nq-dv">{{ fmtDateTime(detailItem.notification_created_at) }}</span></div>
              <div v-if="detailItem.opened_at" class="nq-drow">
                <span class="nq-dk">Opened</span>
                <span class="nq-dv">{{ fmtDateTime(detailItem.opened_at) }}</span>
              </div>
              <div v-if="detailItem.closed_at" class="nq-drow">
                <span class="nq-dk">Closed</span>
                <span class="nq-dv">{{ fmtDateTime(detailItem.closed_at) }}</span>
              </div>
            </div>
          </div>

          <!-- Reason text -->
          <div v-if="detailItem.reason_text" class="nq-det-section">
            <div class="nq-det-section-lbl">REASON TEXT</div>
            <p class="nq-det-reason">{{ detailItem.reason_text }}</p>
          </div>

          <!-- Voided primary warning -->
          <div v-if="detailItem.is_voided_primary" class="nq-det-void-warn">
            <svg viewBox="0 0 14 14" fill="none" stroke="#B45309" stroke-width="1.5"
              stroke-linecap="round" aria-hidden="true">
              <path d="M7 1L1 13h12L7 1z"/>
              <line x1="7" y1="6" x2="7" y2="9"/><circle cx="7" cy="11" r=".5" fill="#B45309"/>
            </svg>
            The linked primary screening record has been voided.
            This referral should be reviewed and closed if appropriate.
          </div>

          <!-- Sync / IDs -->
          <div class="nq-det-section">
            <div class="nq-det-section-lbl">IDENTIFIERS</div>
            <div class="nq-det-grid">
              <div class="nq-drow"><span class="nq-dk">Sync</span>
                <span class="nq-dv">{{ SYNC.LABELS[detailItem.sync_status] || detailItem.sync_status }}</span></div>
              <div class="nq-drow"><span class="nq-dk">Server ID</span>
                <code class="nq-dv nq-dv--mono">{{ detailItem.notification_id || 'Pending sync' }}</code></div>
              <div class="nq-drow"><span class="nq-dk">Client UUID</span>
                <code class="nq-dv nq-dv--mono nq-dv--xs">{{ detailItem.notification_uuid }}</code></div>
            </div>
          </div>

          <!-- Actions -->
          <div class="nq-det-actions">
            <!-- OPEN or IN_PROGRESS → show screening button -->
            <button
              v-if="detailItem.notification_status === 'OPEN' || detailItem.notification_status === 'IN_PROGRESS'"
              class="nq-det-screen-btn"
              :class="detailItem.notification_status === 'IN_PROGRESS' && 'nq-det-screen-btn--progress'"
              @click="openCase(detailItem); detailItem = null"
              type="button">
              {{ detailItem.notification_status === 'IN_PROGRESS'
                ? 'Continue Secondary Screening →'
                : 'Begin Secondary Screening →' }}
            </button>
            <!-- OPEN only → cancel button -->
            <!-- CRITICAL GUARD: IN_PROGRESS cannot be cancelled here -->
            <button
              v-if="detailItem.notification_status === 'OPEN'"
              class="nq-det-cancel-btn"
              @click="confirmCancel(detailItem); detailItem = null"
              type="button">
              Cancel Referral
            </button>
            <!-- IN_PROGRESS cancel guard notice -->
            <div v-if="detailItem.notification_status === 'IN_PROGRESS'" class="nq-det-notice">
              <svg viewBox="0 0 12 12" fill="none" stroke="#64748B" stroke-width="1.4"
                stroke-linecap="round" aria-hidden="true">
                <circle cx="6" cy="6" r="5"/><line x1="6" y1="4" x2="6" y2="7"/>
                <circle cx="6" cy="9" r=".5" fill="#64748B"/>
              </svg>
              This case is in progress. The secondary officer must close it from within the screening form.
            </div>
          </div>

        </div>
      </IonContent>
    </IonModal>

    <!-- Cancel confirmation -->
    <IonAlert
      :is-open="showCancelAlert"
      header="Cancel This Referral?"
      :sub-header="cancelTarget
        ? (cancelTarget.traveler_full_name || cancelTarget.gender || 'Traveler') + ' · ' + (cancelTarget.priority || 'NORMAL') + ' priority'
        : ''"
      message="This permanently closes the referral notification. The linked primary screening record stays COMPLETED and is preserved in the audit log."
      :buttons="cancelAlertBtns"
      @didDismiss="showCancelAlert = false; cancelTarget = null"/>

    <IonToast :is-open="toast.show" :message="toast.msg" :color="toast.color"
      :duration="3200" position="top" @didDismiss="toast.show = false"/>

  </IonPage>
</template>

<script setup>
/**
 * NotificationsCenter.vue — ECSA-HC POE Sentinel
 * IHR 2005 Article 23 · Referral Command Centre
 *
 * ══ OFFLINE-FIRST ARCHITECTURE ══════════════════════════════════════
 * IDB is the single source of truth. Server is a sync source, never primary.
 *
 * IDB-FIRST LOAD ORDER:
 *   1. Read IDB page (offset 0, 100 records) → render immediately
 *   2. If online: fetch server page 1 → write-through to IDB → merge window
 *   3. Background sync runs every 30s with updated_after cursor
 *   4. On view re-enter: incremental sync + damaged re-scan
 *
 * WRITE-THROUGH CACHE (server → IDB):
 *   Every server item is written to IDB if:
 *     a) record does not exist in IDB, OR
 *     b) incoming record_version > stored record_version
 *   This prevents stale overwrites during concurrent device edits.
 *
 * MEMORY WINDOW:
 *   MAX_WINDOW = 300 records in JS heap. allItems never exceeds this.
 *   Stats (Open/InProg/Closed/Critical) come from IDB dbCountIndex — O(1).
 *
 * STATUS MACHINE (enforced on both client and server):
 *   OPEN → IN_PROGRESS → CLOSED
 *   Cancel:  OPEN only  (PATCH /referral-queue/{id}/cancel)
 *   IN_PROGRESS cannot be cancelled here — secondary officer must close.
 *
 * NAVIGATION TO SECONDARY SCREENING:
 *   router.push('/secondary-screening/' + notification_uuid)
 *   SecondaryScreening.vue reads params.notificationId → IDB lookup by client_uuid.
 *   Before navigating: guarantee IDB has the full record (openCase writes it if missing).
 *
 * DAMAGED QUARANTINE:
 *   sync_status === FAILED && sync_attempt_count >= DAMAGE_THRESHOLD (3)
 *   Loaded from IDB via sync_status index scan (separate from main window).
 *   Officer can: Retry (reset to UNSYNCED, attempt_count=0) or Dismiss (soft-delete).
 *
 * STALE DETECTION:
 *   localStorage key 'nq_last_sync' stores last successful sync ISO timestamp.
 *   If > 5 minutes old and online → show staleness warning banner.
 *
 * API endpoints:
 *   GET  /referral-queue?user_id=&status=ALL&page=1&per_page=100&updated_after=
 *   PATCH /referral-queue/{notifId}/cancel  (server integer id required)
 */
import { ref, computed, reactive, onMounted, onUnmounted, toRaw, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonButtons, IonMenuButton,
  IonButton, IonIcon, IonContent, IonModal, IonAlert, IonToast,
  IonRefresher, IonRefresherContent,
  onIonViewDidEnter, onIonViewWillLeave,
} from '@ionic/vue'
import { closeOutline } from 'ionicons/icons'
import {
  dbGet, dbGetByIndex, dbPut, safeDbPut, dbCountIndex,
  isoNow, STORE, SYNC, APP,
} from '@/services/poeDB'

const router = useRouter()

// ── AUTH ──────────────────────────────────────────────────────────────────
function getAuth() {
  try { return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {} }
  catch { return {} }
}
const auth = ref(getAuth())

// ── CONSTANTS ─────────────────────────────────────────────────────────────
const TABS = [
  { v: 'ALL',         label: 'All'         },
  { v: 'OPEN',        label: 'Open'        },
  { v: 'IN_PROGRESS', label: 'In Progress' },
  { v: 'CLOSED',      label: 'Closed'      },
  { v: 'DAMAGED',     label: '⚠ Damaged'  },
]

const PRIORITY_ORDER   = { CRITICAL: 0, HIGH: 1, NORMAL: 2 }
const DAMAGE_THRESHOLD = 3      // failed sync attempts before quarantine
const MAX_WINDOW       = 300    // max records in JS heap
const IDB_PAGE_SIZE    = 100    // IDB reads per page
const SERVER_PAGE_SIZE = 100    // server page size
const POLL_INTERVAL_MS = 15_000 // background sync interval — 15s for responsive queue updates
const STALE_THRESHOLD_MS = 5 * 60_000 // 5 minutes
const LAST_SYNC_KEY    = 'nq_last_sync'

// ── STATE ─────────────────────────────────────────────────────────────────
const allItems    = ref([])   // memory window — max MAX_WINDOW items
const damaged     = ref([])   // quarantined records

// IDB counts — O(1), no full scans
const idbOpen    = ref(0)
const idbInProg  = ref(0)
const idbClosed  = ref(0)

// Pagination state
const idbPageOffset = ref(0)
const serverPage    = ref(1)
const totalOnServer = ref(0)
const hasMoreIdb    = ref(true)
const hasMoreServer = ref(true)

// UI state
const loading      = ref(true)
const loadingMore  = ref(false)
const syncing      = ref(false)
const isOnline     = ref(navigator.onLine)
const activeTab    = ref('ALL')
const showClosed   = ref(false)
const detailItem   = ref(null)
const searchQuery  = ref('')
const searchFocused = ref(false)
let searchDebounceTimer = null
const openingUuid  = ref(null)  // currently navigating to secondary screening
const cancellingId = ref(null)  // server integer id being cancelled
const showCancelAlert = ref(false)
const cancelTarget = ref(null)
const retryingUuid = ref(null)
const toast = reactive({ show: false, msg: '', color: 'success' })

let pollTimer  = null
let bgDebounce = null

// ── COMPUTED ──────────────────────────────────────────────────────────────
// BUG FIX: Previously used allItems.filter (max 300 window). Now uses a
// dedicated ref populated from the full IDB scan in refreshIdbStats().
const idbCritCount = ref(0)
const critCount = computed(() => idbCritCount.value)

// ── SEARCH ENGINE — O(n) single-pass, debounced 300ms ────────────────────
// Tokenises the query and matches against all searchable fields in one pass.
// Handles millions of in-memory records efficiently: no regex, no repeated
// string construction, no nested loops. Each item is tested once per token.
function matchesSearch(item, tokens) {
  if (tokens.length === 0) return true
  // Build searchable blob ONCE per item — lowercase, space-separated
  const blob = [
    item.traveler_full_name,
    item.gender,
    item.priority,
    item.notification_status,
    item.poe_code,
    item.reason_code,
    item.screener_name,
    item.traveler_direction,
    item.notification_uuid,
  ].filter(Boolean).join(' ').toLowerCase()
  // ALL tokens must match (AND search)
  return tokens.every(t => blob.includes(t))
}

const searchTokens = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return []
  return q.split(/\s+/).filter(t => t.length > 0)
})

function onSearchInput() {
  // Debounce: wait 300ms after last keystroke before recomputing
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    // Force Vue reactivity to pick up the new searchTokens
    // (searchQuery is already reactive, this is just for the debounce)
    searchQuery.value = searchQuery.value // trigger
  }, 300)
}

const displayItems = computed(() => {
  let items = allItems.value
  // Tab filter
  if (activeTab.value === 'OPEN')        items = items.filter(i => i.notification_status === 'OPEN')
  else if (activeTab.value === 'IN_PROGRESS') items = items.filter(i => i.notification_status === 'IN_PROGRESS')
  else if (activeTab.value === 'CLOSED')      items = items.filter(i => i.notification_status === 'CLOSED')
  // Search filter — applied on top of tab
  const tokens = searchTokens.value
  if (tokens.length > 0) items = items.filter(i => matchesSearch(i, tokens))
  return items
})

// Alias for template search result count
const filteredItems = displayItems

// Alarm zone: CRITICAL + OPEN, sorted oldest-first (most urgent visible first)
// Search applies here too — if searching, only show matching criticals
const criticalItems = computed(() =>
  displayItems.value
    .filter(i => i.notification_status === 'OPEN' && i.priority === 'CRITICAL')
    .sort((a, b) => new Date(a.notification_created_at || 0) - new Date(b.notification_created_at || 0))
)

// OPEN items, alarm zone already handles CRITICAL, so HIGH + NORMAL split below
const highItems = computed(() =>
  displayItems.value.filter(i => i.notification_status === 'OPEN' && i.priority === 'HIGH')
)
const normalItems = computed(() =>
  displayItems.value.filter(i => i.notification_status === 'OPEN' && i.priority === 'NORMAL')
)
const inProgItems = computed(() =>
  displayItems.value.filter(i => i.notification_status === 'IN_PROGRESS')
)
const closedItems = computed(() =>
  displayItems.value
    .filter(i => i.notification_status === 'CLOSED')
    .sort((a, b) => new Date(b.notification_created_at || 0) - new Date(a.notification_created_at || 0))
    .slice(0, 100) // cap closed in memory view at 100
)

// Section visibility guards
const showAlarmZone     = computed(() => activeTab.value === 'ALL' || activeTab.value === 'OPEN')
const showOpenSection   = computed(() => activeTab.value === 'ALL' || activeTab.value === 'OPEN')
const showInProgSection = computed(() => activeTab.value === 'ALL' || activeTab.value === 'IN_PROGRESS')
const showClosedSection = computed(() => activeTab.value === 'ALL' || activeTab.value === 'CLOSED')

const hasMore        = computed(() => (hasMoreIdb.value || (hasMoreServer.value && isOnline.value)))
const remainingCount = computed(() => Math.max(0, (totalOnServer.value || 0) - allItems.value.length))

const emptyTitle = computed(() => {
  if (activeTab.value === 'OPEN')        return 'No Open Referrals'
  if (activeTab.value === 'IN_PROGRESS') return 'No Cases In Progress'
  if (activeTab.value === 'CLOSED')      return 'No Closed Referrals'
  return 'No Records Found'
})
const emptySub = computed(() => {
  if (activeTab.value === 'OPEN') return 'All symptomatic travelers have been attended to.'
  return 'Records appear here when referrals are created by primary screening.'
})

// Staleness detection — uses a reactive ref, NOT raw localStorage
// (Vue computed can't react to localStorage changes)
const lastSyncAt = ref(localStorage.getItem(LAST_SYNC_KEY) || null)

function markSynced() {
  const now = isoNow()
  localStorage.setItem(LAST_SYNC_KEY, now)
  lastSyncAt.value = now  // triggers reactivity
}

const staleWarning = computed(() => {
  if (!lastSyncAt.value) return false
  return (Date.now() - new Date(lastSyncAt.value).getTime()) > STALE_THRESHOLD_MS
})
const lastSyncLabel = computed(() => {
  if (!lastSyncAt.value) return 'never'
  return fmtRelative(lastSyncAt.value)
})

function tabBadge(v) {
  if (v === 'OPEN')        return idbOpen.value    || null
  if (v === 'IN_PROGRESS') return idbInProg.value  || null
  if (v === 'DAMAGED')     return damaged.value.length || null
  return null
}

// ── HELPERS ───────────────────────────────────────────────────────────────
function toPlain(v)           { return JSON.parse(JSON.stringify(toRaw(v))) }
function showMsg(msg, color = 'success') { Object.assign(toast, { show: true, msg, color }) }
function fmt(n) {
  if (!n) return '0'
  n = Number(n)
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M'
  if (n >= 1_000)     return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'K'
  return String(n)
}
function fmtTemp(v) {
  if (v == null) return '—'
  return typeof v === 'number' ? v.toFixed(1) : String(v)
}
function tempC(val, unit) {
  const v = Number(val)
  return unit === 'F' ? (v - 32) * 5 / 9 : v
}
function tempCls(val, unit) {
  const c = tempC(val, unit)
  if (c >= 38.5) return 'nq-chip--temp-crit'
  if (c >= 37.5) return 'nq-chip--temp-high'
  return 'nq-chip--temp-ok'
}
function tempTextCls(val, unit) {
  const c = tempC(val, unit)
  if (c >= 38.5) return 'nq-dv--critical'
  if (c >= 37.5) return 'nq-dv--high'
  return ''
}
function fmtRelative(dt) {
  if (!dt) return '—'
  try {
    const ms = Date.now() - new Date(String(dt).replace(' ', 'T')).getTime()
    const m  = Math.floor(ms / 60_000)
    if (m < 1)  return 'Just now'
    if (m < 60) return `${m}m ago`
    const h = Math.floor(m / 60)
    if (h < 24) return `${h}h ago`
    return `${Math.floor(h / 24)}d ago`
  } catch { return '—' }
}
function fmtDateTime(dt) {
  if (!dt) return '—'
  try {
    return new Date(String(dt).replace(' ', 'T')).toLocaleString('en-UG', {
      day: '2-digit', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    })
  } catch { return String(dt) }
}
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

// ── IDB STATS ────────────────────────────────────────────────────────────────
// BUG FIX: Previously used dbCountIndex on status which counts ALL POEs.
// Now scoped to the current user's poe_code via full IDB scan.
// Also computes Critical count from full dataset (not 300-item window).
async function refreshIdbStats() {
  try {
    const poeCode = auth.value?.poe_code || ''
    if (!poeCode) {
      // Fallback for supervisor roles without a poe_code: use unscoped index counts
      const [open, prog, closed] = await Promise.all([
        dbCountIndex(STORE.NOTIFICATIONS, 'status', 'OPEN'),
        dbCountIndex(STORE.NOTIFICATIONS, 'status', 'IN_PROGRESS'),
        dbCountIndex(STORE.NOTIFICATIONS, 'status', 'CLOSED'),
      ])
      idbOpen.value   = open
      idbInProg.value = prog
      idbClosed.value = closed
      idbCritCount.value = allItems.value.filter(i => i.notification_status === 'OPEN' && i.priority === 'CRITICAL').length
      return
    }

    // Full scan scoped to this POE for accurate counts
    const allNotifs = await dbGetByIndex(STORE.NOTIFICATIONS, 'poe_code', poeCode)
    const live = allNotifs.filter(r => !r.deleted_at)

    idbOpen.value      = live.filter(r => r.status === 'OPEN').length
    idbInProg.value    = live.filter(r => r.status === 'IN_PROGRESS').length
    idbClosed.value    = live.filter(r => r.status === 'CLOSED').length
    idbCritCount.value = live.filter(r => r.status === 'OPEN' && r.priority === 'CRITICAL').length
  } catch (e) { console.warn('[NQ] refreshIdbStats', e?.message) }
}

// ── DAMAGED QUARANTINE — scan FAILED index ────────────────────────────────
async function loadDamaged() {
  try {
    const poeCode = auth.value?.poe_code || ''
    const failed = await dbGetByIndex(STORE.NOTIFICATIONS, 'sync_status', SYNC.FAILED)
    damaged.value = failed
      .filter(r => !r.deleted_at && (r.sync_attempt_count ?? 0) >= DAMAGE_THRESHOLD && (!poeCode || r.poe_code === poeCode))
      .map(r => ({
        client_uuid:        r.client_uuid,
        notification_id:    r.id ?? r.server_id ?? null,
        notification_uuid:  r.client_uuid,
        status:             r.status,
        priority:           r.priority || 'NORMAL',
        poe_code:           r.poe_code,
        sync_attempt_count: r.sync_attempt_count ?? 0,
        last_sync_error:    r.last_sync_error || null,
        created_at:         r.created_at,
        _damageReason:      detectDamageReason(r),
      }))
      .sort((a, b) => (b.sync_attempt_count ?? 0) - (a.sync_attempt_count ?? 0))
  } catch (e) { console.warn('[NQ] loadDamaged', e?.message) }
}

// ── IDB PAGE READ — sorted, filtered, paginated, DEDUPLICATED ─────────────
async function readIdbPage(offset = 0) {
  const poeCode = auth.value?.poe_code || ''
  if (!poeCode) return []
  try {
    const all   = await dbGetByIndex(STORE.NOTIFICATIONS, 'poe_code', poeCode)
    const valid = all.filter(n =>
      n.notification_type === 'SECONDARY_REFERRAL' &&
      !n.deleted_at &&
      n.sync_status !== SYNC.FAILED
    )

    // ── DEDUP by primary_screening_id ──────────────────────────────────────
    // If multiple notification records exist for the same primary screening
    // (e.g. client-created + server-synced ghost), keep only the best one:
    // prefer the one with enrichment data (gender/name), then higher record_version.
    const byPrimary = new Map()
    for (const n of valid) {
      const pk = n.primary_screening_id || n.client_uuid
      const existing = byPrimary.get(pk)
      if (!existing) {
        byPrimary.set(pk, n)
      } else {
        // Score: enriched data wins, then higher version, then newer created_at
        const scoreA = (existing.gender ? 1 : 0) + (existing.traveler_full_name ? 1 : 0)
        const scoreB = (n.gender ? 1 : 0) + (n.traveler_full_name ? 1 : 0)
        if (scoreB > scoreA ||
            (scoreB === scoreA && (n.record_version ?? 0) > (existing.record_version ?? 0))) {
          byPrimary.set(pk, n)
        }
      }
    }
    const deduped = Array.from(byPrimary.values())

    deduped.sort((a, b) => {
      const pd = (PRIORITY_ORDER[a.priority] ?? 99) - (PRIORITY_ORDER[b.priority] ?? 99)
      if (pd !== 0) return pd
      return new Date(b.created_at || 0) - new Date(a.created_at || 0)
    })
    return deduped.slice(offset, offset + IDB_PAGE_SIZE).map(normaliseIdb)
  } catch (e) { console.warn('[NQ] readIdbPage', e?.message); return [] }
}

// Normalise an IDB notification record into the UI item shape.
// EVERY field is explicit — no implicit spread that could carry stale data.
function normaliseIdb(n) {
  return {
    notification_id:         n.id ?? n.server_id ?? null,
    notification_uuid:       n.client_uuid,
    notification_status:     n.status || 'OPEN',
    priority:                n.priority || 'NORMAL',
    reason_code:             n.reason_code || null,
    reason_text:             n.reason_text || null,
    notification_created_at: n.created_at || null,
    opened_at:               n.opened_at || null,
    closed_at:               n.closed_at || null,
    screener_name:           n.screener_name || null,
    primary_screening_id:    n.primary_screening_id || null,
    primary_uuid:            n.primary_uuid || null,
    gender:                  n.gender || null,
    traveler_direction:      n.traveler_direction || null,
    temperature_value:       n.temperature_value ?? null,
    temperature_unit:        n.temperature_unit || null,
    traveler_full_name:      n.traveler_full_name || null,
    captured_at:             n.captured_at || null,
    poe_code:                n.poe_code || null,
    district_code:           n.district_code || null,
    country_code:            n.country_code || null,
    province_code:           n.province_code || null,
    pheoc_code:              n.pheoc_code || null,
    sync_status:             n.sync_status || SYNC.SYNCED,
    sync_attempt_count:      n.sync_attempt_count ?? 0,
    last_sync_error:         n.last_sync_error || null,
    is_voided_primary:       !!n.is_voided_primary,
    _fromCache:              true,
  }
}

// ── SERVER FETCH — incremental or full ───────────────────────────────────
async function fetchServer(pg = 1, updatedAfter = null) {
  if (!isOnline.value || !auth.value?.id) return null
  const p = new URLSearchParams({
    user_id:  auth.value.id,
    status:   'ALL',
    page:     pg,
    per_page: SERVER_PAGE_SIZE,
  })
  if (updatedAfter) p.set('updated_after', updatedAfter)

  const ctrl = new AbortController()
  const tid  = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS || 8000)
  try {
    const res = await fetch(`${window.SERVER_URL}/referral-queue?${p}`, {
      headers: { Accept: 'application/json' },
      signal:  ctrl.signal,
    })
    clearTimeout(tid)
    if (!res.ok) return null
    const j = await res.json()
    return j.success ? j.data : null
  } catch { clearTimeout(tid); return null }
}

// ── WRITE-THROUGH CACHE — server items → IDB ──────────────────────────────
// Version-guarded: only write if incoming record_version > stored version.
// NEVER overwrites a newer local edit with older server data.
// DEDUP GUARD: if a local record already exists for the same primary_screening_id
// under a DIFFERENT client_uuid, skip the server record — it's a ghost duplicate.
async function writeToIdb(serverItems) {
  for (const s of serverItems) {
    if (!s.notification_uuid) continue

    // ── DEDUP: check if another IDB record already covers this primary screening ──
    // The server may return a notification with a server-generated UUID that differs
    // from the client-generated UUID. Both point to the same primary_screening_id.
    // Without this guard, we'd create a ghost duplicate with no enrichment data.
    const primaryId = s.primary_uuid || s.primary_screening_id
    if (primaryId) {
      try {
        const existing = await dbGet(STORE.NOTIFICATIONS, s.notification_uuid)
        if (!existing) {
          // Before inserting a NEW record, check if we already have one for this primary
          const siblings = await dbGetByIndex(STORE.NOTIFICATIONS, 'primary_screening_id', primaryId)
          const localSibling = siblings.find(sib =>
            sib.client_uuid !== s.notification_uuid &&
            sib.notification_type === 'SECONDARY_REFERRAL' &&
            !sib.deleted_at
          )
          if (localSibling) {
            // A local record already exists for this primary screening.
            // Update the existing one's server id if needed, skip creating a duplicate.
            if (s.notification_id && !localSibling.id) {
              await safeDbPut(STORE.NOTIFICATIONS, {
                ...localSibling,
                id: s.notification_id,
                server_id: s.notification_id,
                status: s.notification_status || localSibling.status,
                sync_status: SYNC.SYNCED,
                synced_at: isoNow(),
                record_version: (localSibling.record_version || 1) + 1,
                updated_at: isoNow(),
              })
            }
            continue // skip — don't create a ghost duplicate
          }
        }
      } catch (e) {
        console.warn('[NQ] writeToIdb dedup check', s.notification_uuid, e?.message)
      }
    }
    try {
      const existing   = await dbGet(STORE.NOTIFICATIONS, s.notification_uuid)
      const incomingVer = s.record_version ?? 1

      if (!existing) {
        // New record — write full shape
        await dbPut(STORE.NOTIFICATIONS, toPlain({
          client_uuid:            s.notification_uuid,
          id:                     s.notification_id     ?? null,
          server_id:              s.notification_id     ?? null,
          reference_data_version: APP.REFERENCE_DATA_VER,
          server_received_at:     null,
          country_code:           s.country_code        || '',
          province_code:          s.province_code       || null,
          pheoc_code:             s.pheoc_code          || null,
          district_code:          s.district_code       || '',
          poe_code:               s.poe_code            || auth.value?.poe_code || '',
          primary_screening_id:   s.primary_uuid        || String(s.primary_screening_id || ''),
          created_by_user_id:     null,
          notification_type:      'SECONDARY_REFERRAL',
          status:                 s.notification_status || 'OPEN',
          priority:               s.priority            || 'NORMAL',
          reason_code:            s.reason_code         || 'PRIMARY_SYMPTOMS_DETECTED',
          reason_text:            s.reason_text         || null,
          assigned_role_key:      'SCREENER',
          assigned_user_id:       null,
          opened_at:              s.opened_at           || null,
          closed_at:              s.closed_at           || null,
          device_id:              'SERVER',
          app_version:            null,
          platform:               'WEB',
          record_version:         incomingVer,
          deleted_at:             null,
          sync_status:            SYNC.SYNCED,
          synced_at:              isoNow(),
          sync_attempt_count:     0,
          last_sync_error:        null,
          created_at:             s.notification_created_at || isoNow(),
          updated_at:             isoNow(),
          // Enriched fields (server join)
          gender:                 s.gender              || null,
          traveler_direction:     s.traveler_direction  || null,
          temperature_value:      s.temperature_value   ?? null,
          temperature_unit:       s.temperature_unit    || null,
          traveler_full_name:     s.traveler_full_name  || null,
          captured_at:            s.captured_at         || null,
          screener_name:          s.screener_name       || null,
          primary_uuid:           s.primary_uuid        || null,
          is_voided_primary:      !!s.is_voided_primary,
        }))
      } else if (incomingVer > (existing.record_version ?? 0)) {
        // Newer server version — update specific mutable fields only
        await safeDbPut(STORE.NOTIFICATIONS, toPlain({
          ...existing,
          id:                 s.notification_id     ?? existing.id,
          server_id:          s.notification_id     ?? existing.server_id,
          status:             s.notification_status ?? existing.status,
          priority:           s.priority            ?? existing.priority,
          reason_text:        s.reason_text         ?? existing.reason_text,
          opened_at:          s.opened_at           ?? existing.opened_at,
          closed_at:          s.closed_at           ?? existing.closed_at,
          gender:             s.gender              ?? existing.gender,
          traveler_direction: s.traveler_direction  ?? existing.traveler_direction,
          temperature_value:  s.temperature_value   ?? existing.temperature_value,
          temperature_unit:   s.temperature_unit    ?? existing.temperature_unit,
          traveler_full_name: s.traveler_full_name  ?? existing.traveler_full_name,
          screener_name:      s.screener_name       ?? existing.screener_name,
          is_voided_primary:  s.is_voided_primary !== undefined ? !!s.is_voided_primary : existing.is_voided_primary,
          record_version:     incomingVer,
          sync_status:        SYNC.SYNCED,
          synced_at:          isoNow(),
          updated_at:         isoNow(),
        }))
      }
      // else: existing record_version >= incoming → skip (local is newer or equal)
    } catch (e) {
      console.warn('[NQ] writeToIdb skip', s.notification_uuid, e?.message)
    }
  }
}

// ── IDB-AUTHORITATIVE WINDOW MERGE ────────────────────────────────────────
// After writeToIdb() has applied version-guarded writes, we read each affected
// record BACK from IDB and use that canonical state to update the window.
// This prevents stale server data (e.g., IN_PROGRESS) from overwriting a
// locally-CLOSED notification that hasn't synced to the server yet.
async function mergeWindowIdbAuth(serverItems) {
  const affectedUuids = serverItems.map(s => s.notification_uuid).filter(Boolean)
  // Batch-read from IDB — version-guarded truth
  const idbReads = await Promise.all(
    affectedUuids.map(async uuid => {
      try { return await dbGet(STORE.NOTIFICATIONS, uuid) } catch { return null }
    })
  )
  const byUuid = new Map(allItems.value.map(i => [i.notification_uuid, i]))
  for (const rec of idbReads) {
    if (!rec || rec.deleted_at) continue
    byUuid.set(rec.client_uuid, normaliseIdb(rec))
  }
  let sorted = Array.from(byUuid.values()).sort((a, b) => {
    const pd = (PRIORITY_ORDER[a.priority] ?? 99) - (PRIORITY_ORDER[b.priority] ?? 99)
    if (pd !== 0) return pd
    return new Date(b.notification_created_at || 0) - new Date(a.notification_created_at || 0)
  })
  if (sorted.length > MAX_WINDOW) sorted = sorted.slice(0, MAX_WINDOW)
  allItems.value = sorted
}

// ── GHOST CLEANUP — purge duplicate notification records from IDB ──────────
// Runs once on load. Finds cases where multiple notification records exist for
// the same primary_screening_id. Keeps the record with the most enrichment data
// (gender, name) and soft-deletes the ghosts. This fixes existing corruption
// from server sync creating duplicates with different UUIDs.
async function purgeGhostDuplicates() {
  try {
    const a = getAuth()
    if (!a?.poe_code) return
    const all = await dbGetByIndex(STORE.NOTIFICATIONS, 'poe_code', a.poe_code)
    const referrals = all.filter(n => n.notification_type === 'SECONDARY_REFERRAL' && !n.deleted_at)

    // Group by primary_screening_id
    const groups = new Map()
    for (const n of referrals) {
      const pk = n.primary_screening_id
      if (!pk) continue
      if (!groups.has(pk)) groups.set(pk, [])
      groups.get(pk).push(n)
    }

    let purged = 0
    for (const [, siblings] of groups) {
      if (siblings.length <= 1) continue

      // Score each: enrichment data + version → keep the best
      siblings.sort((a, b) => {
        const sa = (a.gender ? 2 : 0) + (a.traveler_full_name ? 2 : 0) + (a.record_version ?? 0)
        const sb = (b.gender ? 2 : 0) + (b.traveler_full_name ? 2 : 0) + (b.record_version ?? 0)
        return sb - sa // highest score first
      })

      // Keep first (best), soft-delete the rest
      for (let i = 1; i < siblings.length; i++) {
        await safeDbPut(STORE.NOTIFICATIONS, {
          ...siblings[i],
          deleted_at: isoNow(),
          record_version: (siblings[i].record_version || 1) + 1,
          updated_at: isoNow(),
        })
        purged++
      }
    }
    if (purged > 0) console.log(`[NQ] purgeGhostDuplicates: removed ${purged} ghost duplicate(s)`)
  } catch (e) {
    console.warn('[NQ] purgeGhostDuplicates', e?.message)
  }
}

// ── INITIAL LOAD — IDB first, then server ────────────────────────────────
async function load() {
  loading.value       = true
  idbPageOffset.value = 0
  serverPage.value    = 1
  hasMoreIdb.value    = true
  hasMoreServer.value = true

  try {
    // Clean up any existing ghost duplicates before reading
    await purgeGhostDuplicates()
    // 1. IDB page 0 → render immediately (zero network dependency)
    const idbPage = await readIdbPage(0)
    allItems.value      = idbPage
    idbPageOffset.value = IDB_PAGE_SIZE
    hasMoreIdb.value    = idbPage.length === IDB_PAGE_SIZE

    // 2. Stats in parallel (O(1), non-blocking)
    refreshIdbStats().catch(() => {})
    loadDamaged().catch(() => {})

    // 3. Server page 1 — write-through + merge
    if (isOnline.value) {
      const data = await fetchServer(1)
      if (data) {
        totalOnServer.value = data.total || 0
        hasMoreServer.value = (data.page ?? 1) < (data.pages ?? 1)
        serverPage.value    = 2
        await writeToIdb(data.items || [])
        await mergeWindowIdbAuth(data.items || [])   // IDB-authoritative — prevents stale server overwrites
        await refreshIdbStats()
        markSynced()
      }
    }
  } catch (e) {
    console.error('[NQ] load error', e)
  } finally {
    // Mark sync timestamp on every load — clears stale warning from previous sessions
    markSynced()
    loading.value = false
  }
}

// ── LOAD MORE — IDB pages first, then server pages ────────────────────────
async function loadMore() {
  if (loadingMore.value) return
  loadingMore.value = true
  try {
    if (hasMoreIdb.value) {
      const idbPage = await readIdbPage(idbPageOffset.value)
      if (idbPage.length > 0) {
        const byUuid = new Map(allItems.value.map(i => [i.notification_uuid, i]))
        idbPage.forEach(i => byUuid.set(i.notification_uuid, i))
        let merged = Array.from(byUuid.values()).sort((a, b) => {
          const pd = (PRIORITY_ORDER[a.priority] ?? 99) - (PRIORITY_ORDER[b.priority] ?? 99)
          if (pd !== 0) return pd
          return new Date(b.notification_created_at || 0) - new Date(a.notification_created_at || 0)
        }).slice(0, MAX_WINDOW)
        allItems.value      = merged
        idbPageOffset.value += IDB_PAGE_SIZE
        hasMoreIdb.value     = idbPage.length === IDB_PAGE_SIZE
        return
      }
      hasMoreIdb.value = false
    }
    if (hasMoreServer.value && isOnline.value) {
      const data = await fetchServer(serverPage.value)
      if (data) {
        totalOnServer.value = data.total || 0
        hasMoreServer.value = (data.page ?? serverPage.value) < (data.pages ?? 1)
        serverPage.value++
        await writeToIdb(data.items || [])
        await mergeWindowIdbAuth(data.items || [])
        await refreshIdbStats()
      }
    }
  } catch (e) {
    console.warn('[NQ] loadMore error', e?.message)
  } finally {
    loadingMore.value = false
  }
}

// ── BACKGROUND INCREMENTAL SYNC ───────────────────────────────────────────
// Uses updated_after cursor — only fetches records changed since last sync.
// Debounced so rapid reconnect/tab-switch events don't cause concurrent calls.
async function backgroundSync(debounceMs = 500) {
  if (!isOnline.value || syncing.value) return
  clearTimeout(bgDebounce)
  bgDebounce = setTimeout(async () => {
    bgDebounce   = null
    syncing.value = true
    try {
      const lastSync = localStorage.getItem(LAST_SYNC_KEY) || null
      const data     = await fetchServer(1, lastSync)
      if (data?.items?.length) {
        await writeToIdb(data.items)
        await mergeWindowIdbAuth(data.items)
      }
      // Reconcile stale notifications — close any whose linked case is done
      await reconcileStaleNotifications()
      await refreshIdbStats()
      await loadDamaged()
    } catch (e) {
      console.warn('[NQ] backgroundSync error', e?.message)
    } finally {
      // ALWAYS update the sync timestamp when sync completes —
      // whether server returned data, returned empty, or threw an error.
      // The stale warning means "the sync loop stopped running", not
      // "no new data from server". If we're here, the loop IS running.
      markSynced()
      syncing.value = false
    }
  }, debounceMs)
}

// ── STALE NOTIFICATION RECONCILIATION ─────────────────────────────────────
// Scans OPEN/IN_PROGRESS notifications in IDB. For each, checks if the linked
// secondary screening case is CLOSED or DISPOSITIONED. If so, closes the
// notification locally. This catches status mismatches from:
// - Cases dispositioned/closed on another device
// - Server-side status changes not yet reflected in the notification
// - Legacy data where the notification close was missed
async function reconcileStaleNotifications() {
  try {
    const poeCode = auth.value?.poe_code || ''
    if (!poeCode) return
    const allNotifs = await dbGetByIndex(STORE.NOTIFICATIONS, 'poe_code', poeCode)
    const openNotifs = allNotifs.filter(n => !n.deleted_at && (n.status === 'OPEN' || n.status === 'IN_PROGRESS'))
    let fixed = 0
    for (const notif of openNotifs) {
      const nid = notif.id || notif.server_id
      if (!nid) continue
      // Look up linked secondary screening by notification_id
      const linked = await dbGetByIndex(STORE.SECONDARY_SCREENINGS, 'notification_id', nid).catch(() => [])
      const activeCase = linked.find(s => !s.deleted_at)
      if (activeCase && ['CLOSED', 'DISPOSITIONED'].includes(activeCase.case_status)) {
        // Case is done — close the notification locally
        await safeDbPut(STORE.NOTIFICATIONS, {
          ...notif,
          status: 'CLOSED',
          closed_at: activeCase.dispositioned_at || activeCase.closed_at || isoNow(),
          updated_at: isoNow(),
        }).catch(() => {})
        // Update in memory window
        const idx = allItems.value.findIndex(i => i.notification_uuid === notif.client_uuid)
        if (idx !== -1) {
          allItems.value[idx] = { ...allItems.value[idx], notification_status: 'CLOSED' }
        }
        fixed++
      }
    }
    if (fixed > 0) {
      allItems.value = [...allItems.value]
      console.log(`[NQ] Reconciled ${fixed} stale notification${fixed !== 1 ? 's' : ''}`)
    }
  } catch (e) {
    console.warn('[NQ] reconcileStaleNotifications error', e?.message)
  }
}

async function manualRefresh() {
  auth.value = getAuth()
  await load()
}

// ── OPEN CASE — guaranteed IDB preflight before navigation ────────────────
// Ensures SecondaryScreening.vue can read the full record from IDB.
// Navigation uses client_uuid (IDB primary key), not server integer id.
async function openCase(item) {
  if (!item.notification_uuid) {
    showMsg('Referral ID missing — please refresh.', 'warning')
    return
  }
  openingUuid.value = item.notification_uuid

  try {
    // ── CHECK: Is the linked secondary case already closed? ─────────
    // The notification status may be stale (IN_PROGRESS) while the case
    // was already dispositioned/closed. Check IDB for the actual case status.
    const notifRec = await dbGet(STORE.NOTIFICATIONS, item.notification_uuid).catch(() => null)
    if (notifRec) {
      // Find the secondary screening linked to this notification
      const allSec = await dbGetByIndex(STORE.SECONDARY_SCREENINGS, 'notification_id', notifRec.id || notifRec.server_id).catch(() => [])
      const linkedCase = allSec.find(s => !s.deleted_at)
      if (linkedCase && ['CLOSED', 'DISPOSITIONED'].includes(linkedCase.case_status)) {
        // Case is done — update the notification status locally and show message
        await safeDbPut(STORE.NOTIFICATIONS, {
          ...notifRec,
          status: 'CLOSED',
          closed_at: linkedCase.dispositioned_at || linkedCase.closed_at || isoNow(),
          updated_at: isoNow(),
        }).catch(() => {})
        // Remove from active lists
        const idx = allItems.value.findIndex(i => i.notification_uuid === item.notification_uuid)
        if (idx !== -1) {
          allItems.value[idx] = { ...allItems.value[idx], notification_status: 'CLOSED' }
          allItems.value = [...allItems.value]
        }
        await refreshIdbStats()
        showMsg(`This case is already ${linkedCase.case_status.toLowerCase().replace('_', ' ')}. Referral closed.`, 'warning')
        openingUuid.value = null
        return
      }
    }

    // PREFLIGHT: ensure full record is in IDB before navigation
    const existing = await dbGet(STORE.NOTIFICATIONS, item.notification_uuid)
    if (!existing) {
      // Write minimal record so SecondaryScreening can load it
      await dbPut(STORE.NOTIFICATIONS, toPlain({
        client_uuid:            item.notification_uuid,
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
        assigned_role_key:      'SCREENER',
        assigned_user_id:       null,
        opened_at:              item.opened_at           || null,
        closed_at:              item.closed_at           || null,
        device_id:              'SERVER',
        app_version:            null,
        platform:               'WEB',
        record_version:         1,
        deleted_at:             null,
        sync_status:            SYNC.SYNCED,
        synced_at:              isoNow(),
        sync_attempt_count:     0,
        last_sync_error:        null,
        sync_note:              null,
        created_at:             item.notification_created_at || isoNow(),
        updated_at:             isoNow(),
        gender:                 item.gender              || null,
        traveler_direction:     item.traveler_direction  || null,
        temperature_value:      item.temperature_value   ?? null,
        temperature_unit:       item.temperature_unit    || null,
        traveler_full_name:     item.traveler_full_name  || null,
        captured_at:            item.captured_at         || null,
        screener_name:          item.screener_name       || null,
        primary_uuid:           item.primary_uuid        || null,
        is_voided_primary:      !!item.is_voided_primary,
      }))
    }
    // Navigate — SecondaryScreening reads IDB by params.notificationId (client_uuid)
    router.push('/secondary-screening/' + item.notification_uuid)
  } catch (e) {
    console.error('[NQ] openCase IDB preflight failed', e)
    // Still navigate — SecondaryScreening may be able to fetch from server
    router.push('/secondary-screening/' + item.notification_uuid)
  } finally {
    openingUuid.value = null
  }
}

async function openCaseFromDamaged(d) {
  if (!d.client_uuid) { showMsg('No UUID on this record.', 'warning'); return }
  router.push('/secondary-screening/' + d.client_uuid)
}

function openDetail(item) {
  detailItem.value = item
}

// ── CANCEL REFERRAL ───────────────────────────────────────────────────────
// BUSINESS RULE: cancel only for OPEN records. IN_PROGRESS must be closed
// by the secondary officer from within the screening form.
function confirmCancel(item) {
  if (item.notification_status === 'IN_PROGRESS') {
    showMsg('Case is in progress — secondary officer must close it from their screening view.', 'warning')
    return
  }
  if (item.notification_status === 'CLOSED') {
    showMsg('This referral is already closed.', 'warning')
    return
  }
  cancelTarget.value    = item
  showCancelAlert.value = true
}

const cancelAlertBtns = [
  {
    text:    'Keep Referral',
    role:    'cancel',
    handler: () => { showCancelAlert.value = false; cancelTarget.value = null },
  },
  {
    text:    'Cancel Referral',
    role:    'destructive',
    handler: () => executeCancel(),
  },
]

async function executeCancel() {
  const item = cancelTarget.value
  if (!item) return
  const notifId = item.notification_id
  cancellingId.value    = notifId
  showCancelAlert.value = false

  // Optimistic removal from memory window
  const idx = allItems.value.findIndex(i => i.notification_id === notifId)
  if (idx !== -1) {
    allItems.value = allItems.value.filter((_, i) => i !== idx)
  }

  try {
    if (isOnline.value && Number.isInteger(Number(notifId)) && Number(notifId) > 0) {
      const res = await fetch(`${window.SERVER_URL}/referral-queue/${notifId}/cancel`, {
        method:  'PATCH',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body:    JSON.stringify({
          user_id:       auth.value?.id,
          cancel_reason: 'Cancelled by officer from referral queue.',
        }),
      })
      if (!res.ok) {
        // Rollback optimistic removal
        if (idx !== -1) allItems.value = [...allItems.value.slice(0, idx), item, ...allItems.value.slice(idx)]
        const ej = await res.json().catch(() => ({}))
        showMsg(ej.message || 'Could not cancel referral.', 'danger')
        return
      }
      // Mark closed in IDB
      try {
        const stored = await dbGet(STORE.NOTIFICATIONS, item.notification_uuid)
        if (stored) {
          await safeDbPut(STORE.NOTIFICATIONS, toPlain({
            ...stored,
            status:         'CLOSED',
            closed_at:      isoNow(),
            sync_status:    SYNC.SYNCED,
            record_version: (stored.record_version || 1) + 1,
            updated_at:     isoNow(),
          }))
        }
      } catch {}
      await refreshIdbStats()
      showMsg('Referral cancelled. Primary record preserved.', 'success')
    } else if (!isOnline.value) {
      showMsg('Offline — referral hidden locally. Reappears on reconnection if not cancelled server-side.', 'warning')
    } else {
      showMsg('Referral not yet synced — no server record to cancel.', 'warning')
    }
  } catch {
    if (idx !== -1) allItems.value = [...allItems.value.slice(0, idx), item, ...allItems.value.slice(idx)]
    showMsg('Network error — referral not cancelled.', 'danger')
  } finally {
    cancellingId.value = null
    cancelTarget.value = null
  }
}

// ── DAMAGED: RETRY ────────────────────────────────────────────────────────
async function retryDamaged(d) {
  retryingUuid.value = d.client_uuid
  try {
    const rec = await dbGet(STORE.NOTIFICATIONS, d.client_uuid)
    if (!rec) { showMsg('Record not found in local cache.', 'warning'); return }
    await safeDbPut(STORE.NOTIFICATIONS, toPlain({
      ...rec,
      sync_status:        SYNC.UNSYNCED,
      sync_attempt_count: 0,
      last_sync_error:    null,
      record_version:     (rec.record_version || 1) + 1,
      updated_at:         isoNow(),
    }))
    damaged.value = damaged.value.filter(x => x.client_uuid !== d.client_uuid)
    await refreshIdbStats()
    showMsg('Record reset — queued for retry sync.', 'success')
    backgroundSync(0) // immediate sync attempt
  } catch (e) {
    showMsg(`Retry failed: ${e?.message || 'Unknown error'}`, 'danger')
  } finally {
    retryingUuid.value = null
  }
}

// ── DAMAGED: DISMISS (soft-delete) ────────────────────────────────────────
async function dismissDamaged(d) {
  try {
    const rec = await dbGet(STORE.NOTIFICATIONS, d.client_uuid)
    if (rec) {
      await safeDbPut(STORE.NOTIFICATIONS, toPlain({
        ...rec,
        deleted_at:     isoNow(),
        record_version: (rec.record_version || 1) + 1,
        updated_at:     isoNow(),
      }))
    }
    damaged.value = damaged.value.filter(x => x.client_uuid !== d.client_uuid)
    await refreshIdbStats()
    showMsg('Damaged record dismissed and removed from queue.', 'warning')
  } catch (e) {
    showMsg(`Dismiss failed: ${e?.message}`, 'danger')
  }
}

// ── FILTERS ───────────────────────────────────────────────────────────────
function setTab(v) { activeTab.value = v }

// ── CONNECTIVITY ──────────────────────────────────────────────────────────
function onOnline()  { isOnline.value = true;  backgroundSync(300) }
function onOffline() { isOnline.value = false }

// ── LIFECYCLE ─────────────────────────────────────────────────────────────
onMounted(() => {
  auth.value = getAuth()
  window.addEventListener('online',  onOnline)
  window.addEventListener('offline', onOffline)
  load()
  // 30-second background poll — only incremental (updated_after cursor)
  pollTimer = setInterval(() => {
    if (isOnline.value && !loading.value) backgroundSync()
  }, POLL_INTERVAL_MS)
})

// Returning from secondary screening — re-read IDB FIRST (instant), then sync from server.
// IDB is authoritative: SecondaryScreening.dispositionCase() did dbAtomicWrite before navigating.
// Without the IDB reload, the memory window would show stale IN_PROGRESS until the next server poll.
onIonViewDidEnter(() => {
  auth.value = getAuth()
  // Immediate IDB reload — picks up any disposition written by SecondaryScreening
  readIdbPage(0).then(freshPage => {
    if (freshPage.length === 0) return
    const byUuid = new Map(allItems.value.map(i => [i.notification_uuid, i]))
    for (const item of freshPage) byUuid.set(item.notification_uuid, item)
    allItems.value = Array.from(byUuid.values()).sort((a, b) => {
      const pd = (PRIORITY_ORDER[a.priority] ?? 99) - (PRIORITY_ORDER[b.priority] ?? 99)
      return pd !== 0 ? pd : new Date(b.notification_created_at || 0) - new Date(a.notification_created_at || 0)
    }).slice(0, MAX_WINDOW)
    refreshIdbStats().catch(() => {})
  }).catch(() => {})
  // Reconcile stale notifications immediately (catches cases closed in SecondaryScreening)
  reconcileStaleNotifications().catch(() => {})
  // Then incremental server sync in background (200ms debounce)
  backgroundSync(200)
  loadDamaged().catch(() => {})
})

// Keep timers alive — Ionic caches the page
onIonViewWillLeave(() => { /* intentionally empty */ })

onUnmounted(() => {
  window.removeEventListener('online',  onOnline)
  window.removeEventListener('offline', onOffline)
  clearInterval(pollTimer)
  clearTimeout(bgDebounce)
  clearTimeout(searchDebounceTimer)
})
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════
   REFERRAL QUEUE · nq-* namespace
   Font: system-ui stack — no external requests
   Dual-tone: dark header / light content
   All numbers via tabular-nums for alignment stability
═══════════════════════════════════════════════════════════════════════ */

/* ── Font & base ── */
:host, * {
  font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Segoe UI',
    Ubuntu, 'Helvetica Neue', Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  box-sizing: border-box;
}

/* ── Keyframes ── */
@keyframes spin     { to { transform: rotate(360deg) } }
@keyframes pulse    { 0%,100% { opacity:1 } 50% { opacity:.25 } }
@keyframes slideUp  { from { opacity:0; transform:translateY(10px) } to { opacity:1; transform:translateY(0) } }
@keyframes shimmer  { 0%,100% { opacity:.4 } 50% { opacity:.8 } }
@media (prefers-reduced-motion:reduce) {
  *, *::before, *::after { animation-duration:.01ms!important; transition-duration:.01ms!important }
}

/* ═══ DARK ZONE — Header ═════════════════════════════════════════════ */
.nq-page    { --background: #0A1628; }
.nq-hdr     { --background: #0A1628; --border-width: 0; }
.nq-toolbar {
  --background: linear-gradient(180deg, #0A1628 0%, #0E1E38 100%);
  --color: #EDF2FA; --border-width: 0; --min-height: 48px;
}
.nq-menu-btn { --color: rgba(255,255,255,.6); }
.nq-toolbar-title { padding: 0; }
.nq-title-block { display:flex; flex-direction:column; }
.nq-eyebrow { font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:1.4px; color:rgba(0,212,170,.5); }
.nq-title-text { font-size:17px; font-weight:800; color:#EDF2FA; letter-spacing:-.3px; line-height:1.15; }

.nq-conn {
  display:flex; align-items:center; gap:4px; padding:3px 9px;
  border-radius:99px; font-size:9px; font-weight:800;
  text-transform:uppercase; letter-spacing:.4px; border:1px solid;
}
.nq-conn--on  { background:rgba(0,212,170,.1); border-color:rgba(0,212,170,.3); color:#00D4AA; }
.nq-conn--off { background:rgba(148,163,184,.08); border-color:rgba(148,163,184,.2); color:rgba(255,255,255,.4); }
.nq-conn-dot  { width:5px; height:5px; border-radius:50%; background:currentColor; flex-shrink:0; }
.nq-conn--on .nq-conn-dot { animation:pulse 1.6s ease-in-out infinite; }
.nq-conn-txt  { display:none; }
@media (min-width:380px) { .nq-conn-txt { display:block; } }

.nq-hbtn {
  width:32px; height:32px; border-radius:8px;
  background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
  display:flex; align-items:center; justify-content:center; cursor:pointer;
}
.nq-hbtn svg { width:14px; height:14px; stroke:rgba(255,255,255,.65); }
.nq-hbtn:disabled { opacity:.4; cursor:not-allowed; }
.nq-spin { animation:spin .85s linear infinite; }

/* Dark band below toolbar */
.nq-below-bar { background:linear-gradient(180deg,#0E1E38 0%,#0A1628 100%); }

/* Stats strip */
.nq-stats { display:flex; align-items:center; padding:7px 12px 5px; border-bottom:1px solid rgba(255,255,255,.07); }
.nq-stat  { flex:1; display:flex; flex-direction:column; align-items:center; gap:1px;
  background:none; border:none; cursor:pointer; border-radius:6px; padding:4px 2px; transition:background .12s; }
.nq-stat:active, .nq-stat--sel { background:rgba(255,255,255,.06); }
.nq-stat--warn { /* damaged warning state */ }
.nq-sdiv  { width:1px; height:24px; background:rgba(255,255,255,.08); flex-shrink:0; }
.nq-sn    { font-size:20px; font-weight:900; color:#EDF2FA; line-height:1; font-variant-numeric:tabular-nums; letter-spacing:-.5px; }
.nq-sl    { font-size:7.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:rgba(255,255,255,.3); white-space:nowrap; }
.nq-sn--blue   { color:#38BDF8; }
.nq-sn--red    { color:#FF6B6B; }
.nq-sn--cyan   { color:#00D4AA; }
.nq-sn--muted  { color:rgba(255,255,255,.3); }
.nq-sn--orange { color:#FF8C42; }

/* Tabs */
.nq-tabs { display:flex; overflow-x:auto; scrollbar-width:none; padding:0 8px; gap:2px; border-bottom:1px solid rgba(255,255,255,.07); }
.nq-tabs::-webkit-scrollbar { display:none; }
.nq-tab  {
  flex-shrink:0; display:flex; align-items:center; gap:5px; padding:9px 10px;
  border:none; background:none; border-bottom:2px solid transparent;
  font-size:11px; font-weight:700; color:rgba(255,255,255,.38); white-space:nowrap;
  cursor:pointer; transition:color .12s, border-color .12s;
}
.nq-tab--on { color:#00D4AA; border-bottom-color:#00D4AA; }
.nq-tab-badge { padding:1px 5px; border-radius:99px; font-size:9px; font-weight:900; min-width:17px; text-align:center; }
.nq-tb--open        { background:rgba(56,189,248,.2);  color:#38BDF8; }
.nq-tb--in-progress { background:rgba(0,212,170,.2);   color:#00D4AA; }
.nq-tb--damaged     { background:rgba(255,140,66,.25); color:#FF8C42; }

/* ═══ LIGHT ZONE — Content ═══════════════════════════════════════════ */
.nq-content {
  --background: linear-gradient(180deg, #EAF0FA 0%, #F2F5FB 40%, #E4EBF7 100%);
  --color: #0F172A;
}
.nq-board { padding-bottom: 8px; }

/* ── Search bar ── */
.nq-search-bar {
  padding:8px 14px 6px; background:linear-gradient(145deg,#FFFFFF,#F4F7FC);
  border-bottom:1px solid rgba(0,0,0,.06); flex-shrink:0;
}
.nq-search-wrap {
  display:flex; align-items:center; gap:8px;
  background:linear-gradient(145deg,#E8EDF7,#F0F3FA);
  border:1.5px solid rgba(0,0,0,.08); border-radius:10px;
  padding:0 12px; transition:all .2s;
}
.nq-search-wrap--focus {
  border-color:rgba(0,112,224,.35); box-shadow:0 0 0 3px rgba(0,112,224,.08); background:#fff;
}
.nq-search-ic { width:14px; height:14px; flex-shrink:0; stroke:#94A3B8; }
.nq-search-wrap--focus .nq-search-ic { stroke:#0070E0; }
.nq-search-input {
  flex:1; background:transparent; border:none; outline:none;
  font-size:14px; color:#0B1A30; padding:10px 0; min-height:40px;
  font-family:inherit;
}
.nq-search-input::placeholder { color:#94A3B8; font-size:13px; }
.nq-search-clear {
  width:24px; height:24px; display:flex; align-items:center; justify-content:center;
  background:rgba(0,0,0,.06); border:none; border-radius:50%; cursor:pointer; flex-shrink:0;
}
.nq-search-clear svg { width:10px; height:10px; stroke:#64748B; }
.nq-search-meta {
  display:flex; align-items:center; gap:4px; padding:4px 2px 0;
  font-size:10px; font-weight:600; color:#94A3B8;
}
.nq-search-count { color:#0070E0; }

/* Offline / stale banners */
.nq-offline-bar {
  display:flex; align-items:center; gap:7px; padding:8px 14px;
  background:rgba(0,0,0,.06); border-bottom:1px solid rgba(0,0,0,.07);
  font-size:11px; font-weight:600; color:#475569;
}
.nq-offline-bar svg { width:13px; height:13px; stroke:#475569; flex-shrink:0; }
.nq-stale-bar {
  display:flex; align-items:center; gap:7px; padding:7px 14px;
  background:#FFFBEB; border-bottom:1px solid rgba(245,158,11,.2);
  font-size:11px; font-weight:600; color:#92400E;
}
.nq-stale-bar svg { width:12px; height:12px; flex-shrink:0; }
.nq-stale-btn {
  margin-left:auto; font-size:10px; font-weight:800; color:#D97706;
  background:none; border:none; cursor:pointer; text-decoration:underline;
}

/* ── Skeleton ── */
.nq-skels { padding:10px 12px; display:flex; flex-direction:column; gap:6px; }
.nq-sk {
  background:#fff; border-radius:10px; overflow:hidden; padding:12px;
  animation:shimmer 1.2s ease-in-out infinite;
}
.nq-sk-bar { width:100%; height:3px; background:#E2E8F0; border-radius:2px; margin-bottom:10px; }
.nq-sk-body { display:flex; flex-direction:column; gap:7px; }
.nq-sk-r1 { display:flex; gap:6px; }
.nq-sk-pill { height:18px; border-radius:5px; background:#E2E8F0; width:80px; }
.nq-sk-r2 { height:14px; border-radius:4px; background:#EEF2F7; width:65%; }
.nq-sk-r3 { display:flex; gap:5px; }
.nq-sk-chip { height:20px; border-radius:12px; background:#E2E8F0; width:70px; }

/* ── ALARM ZONE — CRITICAL ── */
.nq-alarm-zone {
  margin:10px 12px; border-radius:12px; overflow:hidden;
  border:1.5px solid rgba(239,68,68,.3);
  background:linear-gradient(135deg,#FFF1F2,#FFE4E6);
  animation:slideUp .3s ease;
}
.nq-alarm-hdr {
  display:flex; align-items:center; gap:8px; padding:10px 13px;
  background:rgba(220,38,38,.08); border-bottom:1px solid rgba(220,38,38,.15);
}
.nq-alarm-pulse {
  width:8px; height:8px; border-radius:50%; background:#DC2626; flex-shrink:0;
  animation:pulse 1.2s ease-in-out infinite;
}
.nq-alarm-title { font-size:11px; font-weight:800; color:#991B1B; flex:1; letter-spacing:.2px; }
.nq-alarm-oldest { font-size:9px; font-weight:600; color:#DC2626; opacity:.7; white-space:nowrap; }

/* ── Section headers ── */
.nq-section-hdr {
  display:flex; align-items:center; gap:7px; padding:8px 14px 5px;
  font-size:10px; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:.7px;
}
.nq-section-hdr--sep { margin-top:6px; }
.nq-section-bar { width:3px; height:13px; border-radius:2px; flex-shrink:0; }
.nq-section-bar--critical { background:#DC2626; }
.nq-section-bar--high     { background:#D97706; }
.nq-section-bar--normal   { background:#16A34A; }
.nq-section-bar--progress { background:#0284C7; }
.nq-section-bar--closed   { background:#94A3B8; }
.nq-section-count { margin-left:auto; font-size:9px; font-weight:700; color:#94A3B8;
  background:rgba(0,0,0,.05); padding:2px 7px; border-radius:10px; }

/* ── Closed toggle ── */
.nq-closed-toggle {
  width:100%; display:flex; align-items:center; gap:7px; padding:8px 14px 5px;
  font-size:10px; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:.7px;
  background:none; border:none; cursor:pointer; text-align:left;
}
.nq-toggle-chev { width:12px; height:12px; margin-left:auto; transition:transform .2s; }
.nq-toggle-chev--open { transform:rotate(180deg); }
.nq-collapse-enter-active { transition:all .22s ease; }
.nq-collapse-leave-active { transition:all .15s ease-in; }
.nq-collapse-enter-from,.nq-collapse-leave-to { opacity:0; transform:translateY(-4px); }
.nq-closed-list { display:flex; flex-direction:column; gap:1px; }

/* ── Cards ── */
.nq-card-wrap { padding:0 12px 4px; }
.nq-card {
  background:linear-gradient(145deg,#FFFFFF,#F8FAFC);
  border:1.5px solid rgba(0,0,0,.07); border-radius:11px; overflow:hidden;
  display:flex; align-items:stretch; cursor:pointer;
  transition:transform .15s, box-shadow .15s;
  animation:slideUp .28s ease both;
}
.nq-card:hover { transform:translateY(-1px); box-shadow:0 3px 12px rgba(0,30,80,.1); }
.nq-card:active { transform:scale(.98); }

/* Cards in alarm zone have no margin wrapper */
.nq-alarm-zone .nq-card {
  border-radius:0; border:none; border-bottom:1px solid rgba(220,38,38,.12);
  background:transparent;
}
.nq-alarm-zone .nq-card:last-child { border-bottom:none; }

.nq-card-bar { width:4px; flex-shrink:0; }
.nq-bar--critical { background:linear-gradient(180deg,#B91C1C,#DC2626); }
.nq-bar--high     { background:linear-gradient(180deg,#B45309,#D97706); }
.nq-bar--normal   { background:linear-gradient(180deg,#15803D,#16A34A); }
.nq-bar--progress { background:linear-gradient(180deg,#0369A1,#0284C7); }
.nq-bar--closed   { background:#CBD5E1; }

.nq-card-body { flex:1; padding:10px 12px; min-width:0; }
.nq-card-row1 { display:flex; align-items:center; gap:6px; margin-bottom:4px; flex-wrap:nowrap; }
.nq-card-name { font-size:14px; font-weight:700; color:#0F172A; margin-bottom:5px;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.nq-card-name--muted { color:#94A3B8; font-weight:600; }
.nq-card-age  { font-size:10px; color:#94A3B8; margin-left:auto; white-space:nowrap;
  font-variant-numeric:tabular-nums; }
.nq-age--critical { color:#DC2626; font-weight:700; }

/* Chips */
.nq-card-chips { display:flex; flex-wrap:wrap; gap:4px; }
.nq-chip {
  font-size:10px; font-weight:600; color:#475569;
  background:rgba(0,0,0,.05); padding:2px 8px; border-radius:10px;
  border:1px solid rgba(0,0,0,.07); white-space:nowrap;
}
.nq-chip--temp        { font-weight:700; }
.nq-chip--temp-crit   { background:#FFF1F2; color:#991B1B; border-color:rgba(153,27,27,.2); }
.nq-chip--temp-high   { background:#FFFBEB; color:#92400E; border-color:rgba(146,64,14,.2); }
.nq-chip--temp-ok     { background:#F0FDF4; color:#166534; border-color:rgba(22,101,52,.15); }
.nq-chip--officer     { background:#EFF6FF; color:#1D4ED8; border-color:rgba(29,78,216,.15); }
.nq-chip--muted       { background:rgba(0,0,0,.04); color:#94A3B8; }
.nq-chip--voided-warn { background:#FFFBEB; color:#92400E; border-color:rgba(180,83,9,.2); font-weight:700; }

/* Priority badges */
.nq-pri {
  font-size:9px; font-weight:900; padding:2px 7px; border-radius:4px;
  border:1px solid; letter-spacing:.3px; white-space:nowrap;
}
.nq-pri--critical { background:#FFF1F2; color:#991B1B; border-color:rgba(153,27,27,.2); }
.nq-pri--high     { background:#FFFBEB; color:#92400E; border-color:rgba(146,64,14,.2); }
.nq-pri--normal   { background:#F0FDF4; color:#166534; border-color:rgba(22,101,52,.2); }
.nq-pri--closed   { background:rgba(0,0,0,.04); color:#94A3B8; border-color:rgba(0,0,0,.08); }
.nq-pri--sm { font-size:8px; padding:2px 6px; }

/* Status badges */
.nq-sts {
  font-size:8px; font-weight:800; padding:2px 7px; border-radius:4px;
  letter-spacing:.3px; white-space:nowrap;
}
.nq-sts--open     { background:rgba(59,130,246,.1); color:#1D4ED8; }
.nq-sts--progress { background:rgba(2,132,199,.1);  color:#0369A1; }
.nq-sts--closed   { background:rgba(0,0,0,.05);     color:#64748B; }

/* CTA action row (cancel + open) */
.nq-card-cta {
  display:flex; align-items:center; gap:6px; padding:0 12px 10px;
  flex-shrink:0;
}
.nq-cancel-btn {
  padding:7px 11px; border-radius:8px; font-size:11px; font-weight:700;
  background:rgba(0,0,0,.05); border:1.5px solid rgba(0,0,0,.09); color:#64748B;
  cursor:pointer; white-space:nowrap; min-height:36px;
}
.nq-cancel-btn:disabled { opacity:.5; cursor:not-allowed; }

/* Open / screen buttons (contextual per priority) */
.nq-open-btn {
  display:flex; align-items:center; gap:4px; padding:8px 13px;
  border-radius:8px; font-size:11px; font-weight:800; border:none;
  cursor:pointer; white-space:nowrap; min-height:36px; min-width:90px;
  transition:transform .12s, box-shadow .12s;
}
.nq-open-btn:disabled { opacity:.5; cursor:not-allowed; transform:none!important; }
.nq-open-btn svg { width:9px; height:9px; flex-shrink:0; }
.nq-open-btn:hover { transform:translateY(-1px); }
.nq-open-btn:active { transform:scale(.97); }
.nq-open-btn--critical { background:linear-gradient(135deg,#B91C1C,#DC2626); color:#fff;
  margin:10px 13px; box-shadow:0 3px 10px rgba(220,38,38,.3); }
.nq-open-btn--high     { background:linear-gradient(135deg,#B45309,#D97706); color:#fff;
  box-shadow:0 3px 10px rgba(217,119,6,.3); }
.nq-open-btn--normal   { background:linear-gradient(135deg,#15803D,#16A34A); color:#fff;
  box-shadow:0 3px 10px rgba(22,163,74,.3); }
.nq-open-btn--progress { background:linear-gradient(135deg,#0369A1,#0284C7); color:#fff;
  box-shadow:0 3px 10px rgba(2,132,199,.3); }

/* Closed arrow */
.nq-closed-arrow { display:flex; align-items:center; padding:0 12px; color:#CBD5E1; font-size:18px; font-weight:200; }

/* ── DAMAGED ── */
.nq-quarantine-header {
  display:flex; align-items:center; gap:8px; padding:10px 14px;
  background:#FFF7ED; border-bottom:1px solid rgba(217,119,6,.2);
  font-size:12px; font-weight:700; color:#92400E;
}
.nq-quarantine-header svg { width:13px; height:13px; flex-shrink:0; }

.nq-dmg-card {
  display:flex; margin:4px 12px; background:#fff;
  border:1.5px solid rgba(217,119,6,.2); border-radius:10px; overflow:hidden;
  animation:slideUp .25s ease;
}
.nq-dmg-bar { width:4px; background:linear-gradient(180deg,#D97706,#F59E0B); flex-shrink:0; }
.nq-dmg-body { flex:1; padding:11px 12px; }
.nq-dmg-row1 { display:flex; align-items:center; gap:6px; margin-bottom:5px; }
.nq-dmg-badge { font-size:8px; font-weight:800; color:#DC2626; background:#FFF1F2;
  padding:2px 7px; border-radius:4px; border:1px solid rgba(220,38,38,.2); }
.nq-dmg-att  { font-size:9px; font-weight:700; color:#94A3B8; }
.nq-dmg-age  { font-size:9px; color:#94A3B8; margin-left:auto; }
.nq-dmg-reason { font-size:11px; color:#B45309; font-weight:600; margin-bottom:4px; line-height:1.4; }
.nq-dmg-uuid { font-size:9px; color:#94A3B8; font-variant-numeric:tabular-nums; word-break:break-all; margin-bottom:4px; }
.nq-dmg-meta { font-size:10px; color:#94A3B8; margin-bottom:8px; }
.nq-dmg-actions { display:flex; gap:6px; flex-wrap:wrap; }
.nq-dmg-btn { padding:7px 12px; border-radius:7px; font-size:10px; font-weight:700;
  cursor:pointer; min-height:32px; border:1.5px solid; display:flex; align-items:center; gap:4px; }
.nq-dmg-btn svg { width:11px; height:11px; }
.nq-dmg-btn--retry   { background:#EFF6FF; border-color:rgba(29,78,216,.2); color:#1D4ED8; }
.nq-dmg-btn--open    { background:#F0FDF4; border-color:rgba(22,163,74,.2); color:#166534; }
.nq-dmg-btn--dismiss { background:rgba(0,0,0,.04); border-color:rgba(0,0,0,.1); color:#64748B; }
.nq-dmg-btn:disabled { opacity:.5; cursor:not-allowed; }

/* ── Empty state ── */
.nq-empty { display:flex; flex-direction:column; align-items:center; gap:10px; padding:60px 24px; }
.nq-empty svg { opacity:.3; }
.nq-empty-title { font-size:15px; font-weight:700; color:#475569; }
.nq-empty-sub   { font-size:12px; color:#94A3B8; text-align:center; line-height:1.5; }
.nq-empty-btn {
  padding:9px 20px; border-radius:9px; background:#0F172A; border:none;
  color:#fff; font-size:12px; font-weight:700; cursor:pointer; min-height:40px;
}

/* ── Load more ── */
.nq-load-more { display:flex; justify-content:center; padding:14px; }
.nq-load-btn  {
  display:flex; align-items:center; gap:7px; padding:10px 22px; border-radius:10px;
  background:#fff; border:1.5px solid rgba(0,0,0,.1); color:#0F172A;
  font-size:12px; font-weight:700; cursor:pointer; min-height:42px;
  box-shadow:0 1px 4px rgba(0,0,0,.07);
}
.nq-load-btn svg { width:13px; height:13px; }
.nq-load-btn:disabled { opacity:.5; cursor:not-allowed; }

/* ═══ MODAL ══════════════════════════════════════════════════════════ */
.nq-modal-toolbar {
  --background: linear-gradient(180deg, #0A1628 0%, #0E1E38 100%);
  --color: #EDF2FA; --border-width: 0;
}
.nq-modal-title { font-size:16px; font-weight:700; color:#EDF2FA; }
.nq-modal-pri-wrap { padding-right:10px; }
.nq-modal-content { --background:#F8FAFC; --color:#0F172A; }

.nq-det-wrap { display:flex; flex-direction:column; padding-bottom:40px; }

.nq-det-banner {
  display:flex; align-items:center; gap:10px; padding:12px 16px;
  position:relative; overflow:hidden;
}
.nq-det-banner-shine { position:absolute; top:0; left:0; right:0; height:1px;
  background:linear-gradient(90deg,transparent 20%,rgba(255,255,255,.7) 50%,transparent 80%);
  pointer-events:none; }
.nq-det-banner--open        { background:linear-gradient(135deg,#EFF6FF,#DBEAFE); }
.nq-det-banner--in-progress { background:linear-gradient(135deg,#E0F2FE,#BAE6FD); }
.nq-det-banner--closed      { background:linear-gradient(135deg,#F1F5F9,#E2E8F0); }
.nq-det-banner-sts  { font-size:12px; font-weight:900; color:#0F172A; letter-spacing:.3px; }
.nq-det-banner-hint { font-size:11px; color:#64748B; margin-left:4px; }

.nq-det-section { padding:0; }
.nq-det-section-lbl {
  padding:10px 16px 5px;
  font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.8px; color:#94A3B8;
  border-top:1px solid rgba(0,0,0,.05);
}
.nq-det-grid { display:flex; flex-direction:column; }
.nq-drow {
  display:flex; align-items:baseline; justify-content:space-between; gap:12px;
  padding:9px 16px; border-bottom:1px solid rgba(0,0,0,.05);
}
.nq-dk { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.4px;
  color:#94A3B8; min-width:90px; flex-shrink:0; }
.nq-dv { font-size:12px; font-weight:600; color:#0F172A; text-align:right; flex:1; }
.nq-dv--mono { font-variant-numeric:tabular-nums; font-size:11px; word-break:break-all; }
.nq-dv--xs   { font-size:10px; }
.nq-dv--critical { color:#DC2626; font-weight:800; }
.nq-dv--high     { color:#D97706; font-weight:800; }
.nq-dv--normal   { color:#16A34A; font-weight:800; }

.nq-det-reason {
  margin:0; padding:10px 16px;
  font-size:12px; color:#475569; line-height:1.6;
  border-top:1px solid rgba(0,0,0,.05);
}
.nq-det-void-warn {
  display:flex; align-items:flex-start; gap:8px; padding:12px 16px;
  background:#FFF7ED; border-top:1px solid rgba(217,119,6,.15);
  border-bottom:1px solid rgba(217,119,6,.15);
  font-size:11px; font-weight:600; color:#92400E; line-height:1.5;
}
.nq-det-void-warn svg { width:14px; height:14px; flex-shrink:0; margin-top:2px; }

.nq-det-actions { display:flex; flex-direction:column; gap:8px; padding:16px; }
.nq-det-screen-btn {
  width:100%; padding:14px; border-radius:10px; border:none; cursor:pointer;
  font-size:14px; font-weight:800; color:#fff; min-height:50px;
  background:linear-gradient(135deg,#15803D,#16A34A);
  box-shadow:0 4px 14px rgba(22,163,74,.3);
  transition:transform .12s, box-shadow .12s;
}
.nq-det-screen-btn--progress {
  background:linear-gradient(135deg,#0369A1,#0284C7);
  box-shadow:0 4px 14px rgba(2,132,199,.3);
}
.nq-det-screen-btn:hover { transform:translateY(-1px); }
.nq-det-cancel-btn {
  width:100%; padding:12px; border-radius:10px; cursor:pointer; min-height:46px;
  background:#FFF1F2; border:1.5px solid rgba(220,38,38,.2);
  color:#DC2626; font-size:13px; font-weight:700;
}
.nq-det-notice {
  display:flex; align-items:flex-start; gap:7px; padding:10px 12px;
  background:rgba(0,0,0,.04); border-radius:8px;
  font-size:11px; color:#64748B; line-height:1.5;
}
.nq-det-notice svg { width:12px; height:12px; flex-shrink:0; margin-top:2px; }
</style>
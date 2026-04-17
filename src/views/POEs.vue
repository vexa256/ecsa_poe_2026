<template>
  <IonPage>

    <!-- ══════════════════════════════════════════════════════════════════
         HEADER — Sentinel Design · Deep Blue Gradient
    ══════════════════════════════════════════════════════════════════ -->
    <IonHeader :translucent="false" class="poe-hdr">
      <IonToolbar class="poe-toolbar">

        <!-- Back button -->
        <IonButtons slot="start">
          <IonBackButton default-href="/dashboard" text="" class="poe-back-btn" />
        </IonButtons>

        <!-- Title block -->
        <div class="hdr-title-block" slot="default">
          <span class="hdr-eyebrow">ECSA-HC · IHR SURVEILLANCE</span>
          <IonTitle class="hdr-main-title">Points of Entry</IonTitle>
        </div>

        <!-- Total POE badge -->
        <IonButtons slot="end">
          <div class="hdr-count-badge" aria-label="Total POEs in reference data">
            <span class="hdr-count-num">{{ allPoes.length }}</span>
            <span class="hdr-count-lbl">POEs</span>
          </div>
        </IonButtons>
      </IonToolbar>

      <!-- ── Stats strip — 4 columns ── -->
      <div class="stats-strip" role="region" aria-label="Network statistics">
        <div class="ss-cell">
          <span class="ss-num">{{ allPoes.length }}</span>
          <span class="ss-lbl">Total</span>
        </div>
        <div class="ss-cell">
          <span class="ss-num ss-num--land">{{ landCount }}</span>
          <span class="ss-lbl">Land</span>
        </div>
        <div class="ss-cell">
          <span class="ss-num ss-num--air">{{ airCount }}</span>
          <span class="ss-lbl">Air</span>
        </div>
        <div class="ss-cell">
          <span class="ss-num ss-num--water">{{ waterCount }}</span>
          <span class="ss-lbl">Water</span>
        </div>
      </div>

      <!-- ── Search bar ── -->
      <div class="search-area">
        <div class="search-wrap" role="search">
          <svg class="search-ic" viewBox="0 0 18 18" fill="none" stroke="#7096C8" stroke-width="1.8"
               stroke-linecap="round" aria-hidden="true">
            <circle cx="7.5" cy="7.5" r="5.5"/>
            <line x1="12" y1="12" x2="16" y2="16"/>
          </svg>
          <input
            v-model="searchQuery"
            type="search"
            class="search-input"
            placeholder="Search POE name, district, province…"
            aria-label="Search points of entry"
            autocomplete="off"
            spellcheck="false"
          />
          <button
            v-if="searchQuery"
            class="search-clear-btn"
            @click="searchQuery = ''"
            aria-label="Clear search"
          >
            <svg viewBox="0 0 14 14" fill="none" stroke="#7096C8" stroke-width="2" stroke-linecap="round">
              <line x1="3" y1="3" x2="11" y2="11"/>
              <line x1="11" y1="3" x2="3" y2="11"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- ── Transport mode filter chips ── -->
      <div class="chip-row chip-row--modes" role="toolbar" aria-label="Filter by transport mode">
        <button
          v-for="m in modeFilters"
          :key="m.value"
          class="f-chip f-chip--mode"
          :class="{ 'f-chip--active': activeMode === m.value,
                    [`f-chip--${m.color}`]: true }"
          :aria-pressed="activeMode === m.value"
          @click="setMode(m.value)"
        >
          <!-- Inline SVG icons per mode -->
          <svg v-if="m.value === 'ALL'" class="chip-ic" viewBox="0 0 14 14" fill="none"
               stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
            <circle cx="7" cy="7" r="5.5"/>
            <line x1="1.5" y1="7" x2="12.5" y2="7"/>
            <path d="M7 1.5 C9.5 3 11 5 11 7 C11 9 9.5 11 7 12.5"/>
            <path d="M7 1.5 C4.5 3 3 5 3 7 C3 9 4.5 11 7 12.5"/>
          </svg>
          <svg v-else-if="m.value === 'land'" class="chip-ic" viewBox="0 0 14 14" fill="none"
               stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
            <rect x="1" y="4" width="12" height="7" rx="1.5"/>
            <path d="M1 7h12"/><circle cx="3.5" cy="11" r="1.5"/><circle cx="10.5" cy="11" r="1.5"/>
          </svg>
          <svg v-else-if="m.value === 'air'" class="chip-ic" viewBox="0 0 14 14" fill="none"
               stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
            <path d="M2 9 L6 7 L3 2 L5 2 L9 7 L12 6.5 C13.5 6.5 13.5 7.5 12 7.5 L9 7 L5.5 12 L3.5 12 L6 7"/>
          </svg>
          <svg v-else-if="m.value === 'water'" class="chip-ic" viewBox="0 0 14 14" fill="none"
               stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
            <path d="M1 10 C3 8.5 5 8.5 7 10 C9 11.5 11 11.5 13 10"/>
            <path d="M2 4 L5 4 L5 2 L9 2 L9 4 L12 4 L12 8 L2 8 Z"/>
          </svg>
          <span class="chip-lbl">{{ m.label }}</span>
        </button>
      </div>

      <!-- ── Special flag filters ── -->
      <div class="chip-row chip-row--flags" role="toolbar" aria-label="Filter by POE classification">
        <button
          v-for="fl in flagFilters"
          :key="fl.value"
          class="f-chip f-chip--flag"
          :class="{ 'f-chip--active': activeFlag === fl.value,
                    [`f-chip--${fl.color}`]: true }"
          :aria-pressed="activeFlag === fl.value"
          @click="setFlag(fl.value)"
        >
          <span class="chip-lbl">{{ fl.label }}</span>
          <span class="chip-count">{{ fl.count }}</span>
        </button>
      </div>

    </IonHeader>

    <!-- ══════════════════════════════════════════════════════════════════
         CONTENT — POE List
    ══════════════════════════════════════════════════════════════════ -->
    <IonContent :fullscreen="true" class="poe-content">
      <IonRefresher slot="fixed" @ionRefresh="handleRefresh($event)">
        <IonRefresherContent refreshing-spinner="crescent" />
      </IonRefresher>

      <!-- ── Empty state ── -->
      <div v-if="!filteredPoes.length" class="empty-state" role="status">
        <div class="empty-icon-wrap" aria-hidden="true">
          <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" class="empty-icon">
            <circle cx="32" cy="32" r="26" stroke="#BBDEFB" stroke-width="2"/>
            <path d="M32 6C32 6 20 16 20 32C20 48 32 58 32 58C32 58 44 48 44 32C44 16 32 6 32 6Z"
                  stroke="#BBDEFB" stroke-width="1.5" fill="none"/>
            <ellipse cx="32" cy="32" rx="26" ry="10" stroke="#BBDEFB" stroke-width="1.5" fill="none"/>
            <line x1="8" y1="32" x2="56" y2="32" stroke="#BBDEFB" stroke-width="1.5"/>
          </svg>
        </div>
        <p class="empty-title">No Points of Entry Found</p>
        <p class="empty-sub">
          No Uganda POEs match your current filters.<br>
          Try adjusting your search or clearing the filters.
        </p>
        <button v-if="hasActiveFilters" class="empty-clear-btn" @click="clearAllFilters">
          Clear All Filters
        </button>
      </div>

      <!-- ── POE list — grouped by province/admin_level_1 ── -->
      <div v-else class="poe-list">

        <!-- Section group per province/admin_level_1 -->
        <template v-for="group in groupedPoes" :key="group.admin_level_1">

          <!-- Section header -->
          <div class="section-hdr" role="separator">
            <div class="sh-left">
              <!-- PHEOC icon (Uganda) vs Province icon (Rwanda) -->
              <div class="sh-type-dot"
                   :class="group.type === 'PHEOC' ? 'sh-dot--pheoc' : 'sh-dot--province'"
                   :aria-label="group.type === 'PHEOC' ? 'RPHEOC region' : 'Province'"
              />
              <span class="sh-label">{{ group.admin_level_1 }}</span>
              <span v-if="group.type === 'PHEOC'" class="sh-type-tag sh-tag--pheoc">RPHEOC</span>
              <span v-else class="sh-type-tag sh-tag--province">Province</span>
            </div>
            <span class="sh-count">{{ group.poes.length }}</span>
          </div>

          <!-- POE cards within section -->
          <div
            v-for="poe in group.poes"
            :key="poe.id"
            class="poe-card"
            role="button"
            tabindex="0"
            :aria-label="`${poe.poe_name}, ${transportLabel(poe.transport_mode)}, ${poe.district}`"
            @click="openDetail(poe)"
            @keydown.enter="openDetail(poe)"
            @keydown.space.prevent="openDetail(poe)"
          >
            <!-- Left mode accent bar -->
            <div class="poe-card-accent"
                 :class="`accent--${poe.transport_mode || 'land'}`"
                 aria-hidden="true"
            />

            <!-- Card body -->
            <div class="poe-card-body">

              <!-- Row 1: Icon + Name + Flag badges -->
              <div class="pcb-row1">
                <!-- Transport mode icon -->
                <div class="poe-mode-icon"
                     :class="`mode-ic--${poe.transport_mode || 'land'}`"
                     :aria-label="transportLabel(poe.transport_mode)"
                >
                  <!-- LAND -->
                  <svg v-if="poe.transport_mode === 'land'" viewBox="0 0 18 18" fill="none"
                       stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                    <rect x="1.5" y="5" width="15" height="9" rx="1.5"/>
                    <path d="M1.5 9h15"/>
                    <circle cx="4.5" cy="14" r="1.8"/>
                    <circle cx="13.5" cy="14" r="1.8"/>
                  </svg>
                  <!-- AIR: airport / airstrip -->
                  <svg v-else-if="poe.transport_mode === 'air'" viewBox="0 0 18 18" fill="none"
                       stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                    <path d="M2 11.5L7.5 9 4 3 6.5 3 12 9 15.5 8.5C17.5 8.5 17.5 9.5 15.5 9.5L12 9 7 15.5 4.5 15.5 7.5 9"/>
                  </svg>
                  <!-- WATER: port / island -->
                  <svg v-else viewBox="0 0 18 18" fill="none"
                       stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                    <path d="M1.5 13 C4.5 11 7 11 9 13 C11 15 13.5 15 16.5 13"/>
                    <path d="M3 5.5 L6 5.5 L6 3 L12 3 L12 5.5 L15 5.5 L15 11 L3 11 Z"/>
                  </svg>
                </div>

                <!-- POE name -->
                <div class="poe-name-block">
                  <span class="poe-name">{{ poe.poe_name }}</span>
                  <span class="poe-type-lbl">{{ formatPoeType(poe.poe_type) }}</span>
                </div>

                <!-- Classification badges (right side) -->
                <div class="poe-badges" aria-label="Classifications">
                  <span v-if="poe.is_major_entry" class="poe-badge badge--major" aria-label="Major entry point">
                    MAJOR
                  </span>
                  <span v-if="poe.is_recommended_osbp" class="poe-badge badge--osbp" aria-label="One-Stop Border Post">
                    OSBP
                  </span>
                  <span v-if="poe.is_national_level" class="poe-badge badge--national" aria-label="National level POE">
                    NATIONAL
                  </span>
                </div>
              </div>

              <!-- Row 2: Geography -->
              <div class="pcb-row2">
                <svg viewBox="0 0 12 12" fill="none" stroke="#7096C8" stroke-width="1.5"
                     stroke-linecap="round" class="geo-ic" aria-hidden="true">
                  <path d="M6 1C3.8 1 2 2.8 2 5c0 3.3 4 6 4 6s4-2.7 4-6c0-2.2-1.8-4-4-4z"/>
                  <circle cx="6" cy="5" r="1.3"/>
                </svg>
                <span class="pcb-district">{{ poe.district }}</span>
                <span class="pcb-sep" aria-hidden="true">·</span>
                <span class="pcb-province">{{ poe.province }}</span>
              </div>

              <!-- Row 3: Code + RPHEOC + Border country -->
              <div class="pcb-row3">
                <span v-if="poe.poe_code" class="pcb-code" :aria-label="`POE code: ${poe.poe_code}`">
                  {{ poe.poe_code }}
                </span>

                <template v-if="poe.border_country">
                  <span class="pcb-sep" aria-hidden="true">·</span>
                  <svg viewBox="0 0 12 12" fill="none" stroke="#10B981" stroke-width="1.5"
                       stroke-linecap="round" class="border-ic" aria-hidden="true">
                    <path d="M1 6h10M8 3l3 3-3 3"/>
                  </svg>
                  <span class="pcb-border" :aria-label="`Border with ${poe.border_country}`">
                    {{ poe.border_country }}
                  </span>
                </template>
              </div>

            </div>

            <!-- Chevron indicator -->
            <div class="poe-card-chevron" aria-hidden="true">
              <svg viewBox="0 0 10 16" fill="none" stroke="#B0BEC5" stroke-width="1.8" stroke-linecap="round">
                <polyline points="2 2 8 8 2 14"/>
              </svg>
            </div>
          </div>

        </template>

        <!-- Bottom padding spacer -->
        <div class="list-bottom-pad" aria-hidden="true" />
      </div>
    </IonContent>

    <!-- ══════════════════════════════════════════════════════════════════
         DETAIL MODAL — Full POE Information Sheet
         READ-ONLY: All data from window.POE_MAIN (hardcoded reference data)
    ══════════════════════════════════════════════════════════════════ -->
    <IonModal
      :is-open="!!selectedPoe"
      :can-dismiss="true"
      :show-backdrop="true"
      :backdrop-dismiss="true"
      :initial-breakpoint="0.92"
      :breakpoints="[0, 0.92, 1]"
      handle-behavior="cycle"
      css-class="poe-detail-modal"
      @ionModalDidDismiss="selectedPoe = null"
    >
      <IonContent v-if="selectedPoe" class="modal-content" :scroll-y="true">

        <!-- Modal handle -->
        <div class="modal-handle" aria-hidden="true" />

        <!-- Modal header — blue gradient -->
        <div class="modal-hdr" :class="`modal-hdr--${selectedPoe.transport_mode || 'land'}`">
          <div class="mh-pattern" aria-hidden="true" />

          <!-- Top row -->
          <div class="mh-top">
            <button class="mh-close-btn" @click="selectedPoe = null" aria-label="Close detail">
              <svg viewBox="0 0 16 16" fill="none" stroke="rgba(255,255,255,.85)"
                   stroke-width="2" stroke-linecap="round">
                <line x1="3" y1="3" x2="13" y2="13"/>
                <line x1="13" y1="3" x2="3" y2="13"/>
              </svg>
            </button>
            <div class="mh-title-block">
              <span class="mh-eyebrow">
                🇺🇬 Uganda · {{ transportLabel(selectedPoe.transport_mode) }} POE
              </span>
              <h2 class="mh-title">{{ selectedPoe.poe_name }}</h2>
            </div>
            <!-- Read-only tag -->
            <div class="mh-readonly-tag" aria-label="This view is read-only">
              <svg viewBox="0 0 14 14" fill="none" stroke="rgba(255,255,255,.7)"
                   stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                <rect x="2" y="6" width="10" height="7" rx="1.5"/>
                <path d="M4.5 6V4a2.5 2.5 0 015 0v2"/>
              </svg>
              <span>Read-only</span>
            </div>
          </div>

          <!-- POE type + classification badges row -->
          <div class="mh-badge-row">
            <span class="mh-type-pill" :class="`type-pill--${selectedPoe.transport_mode || 'land'}`">
              {{ formatPoeType(selectedPoe.poe_type) }}
            </span>
            <span v-if="selectedPoe.is_major_entry" class="mh-badge badge--major">MAJOR ENTRY</span>
            <span v-if="selectedPoe.is_recommended_osbp" class="mh-badge badge--osbp">✦ OSBP</span>
            <span v-if="selectedPoe.is_national_level" class="mh-badge badge--national">NATIONAL</span>
          </div>
        </div>

        <!-- Modal body -->
        <div class="modal-body">

          <!-- ── Section 1: Geography ── -->
          <div class="modal-section-hdr">
            <div class="msh-num msh-blue">1</div>
            <span class="msh-title">Geographic Classification</span>
          </div>

          <div class="detail-card">
            <div class="dc-row">
              <div class="dc-ic dc-ic--blue" aria-hidden="true">
                <svg viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.6" stroke-linecap="round">
                  <circle cx="7" cy="7" r="5.5"/>
                  <line x1="1.5" y1="7" x2="12.5" y2="7"/>
                  <path d="M7 1.5 C9 3 10.5 5 10.5 7 C10.5 9 9 11 7 12.5"/>
                  <path d="M7 1.5 C5 3 3.5 5 3.5 7 C3.5 9 5 11 7 12.5"/>
                </svg>
              </div>
              <div class="dc-body">
                <span class="dc-lbl">Country</span>
                <span class="dc-val">🇺🇬 Uganda</span>
              </div>
            </div>

            <div class="dc-row">
              <div class="dc-ic dc-ic--purple" aria-hidden="true">
                <svg viewBox="0 0 14 14" fill="none" stroke="#6A1B9A" stroke-width="1.6" stroke-linecap="round">
                  <rect x="1.5" y="1.5" width="11" height="11" rx="2"/>
                  <path d="M1.5 5.5h11"/><path d="M5.5 1.5v4"/>
                </svg>
              </div>
              <div class="dc-body">
                <span class="dc-lbl">
                  {{ selectedPoe.admin_level_1_type === 'PHEOC' ? 'Province / RPHEOC' : 'Province' }}
                </span>
                <span class="dc-val">{{ selectedPoe.province }}</span>
              </div>
              <div v-if="selectedPoe.admin_level_1_type === 'PHEOC'" class="dc-right">
                <span class="dc-badge dc-badge--pheoc">RPHEOC</span>
              </div>
            </div>

            <div class="dc-row">
              <div class="dc-ic dc-ic--orange" aria-hidden="true">
                <svg viewBox="0 0 14 14" fill="none" stroke="#E65100" stroke-width="1.6" stroke-linecap="round">
                  <path d="M7 1C4.5 1 2.5 3 2.5 5.5c0 3.8 4.5 7.5 4.5 7.5s4.5-3.7 4.5-7.5C11.5 3 9.5 1 7 1z"/>
                  <circle cx="7" cy="5.5" r="1.5"/>
                </svg>
              </div>
              <div class="dc-body">
                <span class="dc-lbl">District</span>
                <span class="dc-val">{{ selectedPoe.district }}</span>
              </div>
            </div>

            <div v-if="selectedPoe.border_country" class="dc-row">
              <div class="dc-ic dc-ic--green" aria-hidden="true">
                <svg viewBox="0 0 14 14" fill="none" stroke="#2E7D32" stroke-width="1.6" stroke-linecap="round">
                  <path d="M1 7h12M9 3.5l3.5 3.5-3.5 3.5"/>
                </svg>
              </div>
              <div class="dc-body">
                <span class="dc-lbl">Bordering Country</span>
                <span class="dc-val dc-val--green">{{ selectedPoe.border_country }}</span>
              </div>
            </div>

            <div v-if="selectedPoe.regional_cluster_or_rpheoc" class="dc-row">
              <div class="dc-ic dc-ic--blue" aria-hidden="true">
                <svg viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.6" stroke-linecap="round">
                  <circle cx="7" cy="7" r="2.5"/>
                  <circle cx="2" cy="3" r="1.2"/><circle cx="12" cy="3" r="1.2"/>
                  <circle cx="2" cy="11" r="1.2"/><circle cx="12" cy="11" r="1.2"/>
                  <path d="M3 3.8l2.5 2M9 3.8l-2.5 2M3 10.2l2.5-2M9 10.2l-2.5-2"/>
                </svg>
              </div>
              <div class="dc-body">
                <span class="dc-lbl">RPHEOC Cluster</span>
                <span class="dc-val">{{ selectedPoe.regional_cluster_or_rpheoc }}</span>
              </div>
            </div>
          </div>

          <!-- ── Section 2: Classification ── -->
          <div class="modal-section-hdr">
            <div class="msh-num msh-green">2</div>
            <span class="msh-title">IHR Classification Flags</span>
          </div>

          <div class="flags-grid">
            <div class="flag-cell"
                 :class="selectedPoe.is_major_entry ? 'flag-cell--on flag-cell--major' : 'flag-cell--off'">
              <div class="flag-ic" aria-hidden="true">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                  <polygon points="1,1 15,8 1,15 4,8"/>
                </svg>
              </div>
              <span class="flag-lbl">Major Entry</span>
              <span class="flag-status">{{ selectedPoe.is_major_entry ? 'YES' : 'NO' }}</span>
            </div>

            <div class="flag-cell"
                 :class="selectedPoe.is_recommended_osbp ? 'flag-cell--on flag-cell--osbp' : 'flag-cell--off'">
              <div class="flag-ic" aria-hidden="true">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                  <path d="M8 1l1.8 5.5H15l-4.7 3.4 1.8 5.5L8 12l-4.1 3.4 1.8-5.5L1 6.5h5.2z"/>
                </svg>
              </div>
              <span class="flag-lbl">OSBP</span>
              <span class="flag-status">{{ selectedPoe.is_recommended_osbp ? 'YES' : 'NO' }}</span>
            </div>

            <div class="flag-cell"
                 :class="selectedPoe.is_national_level ? 'flag-cell--on flag-cell--national' : 'flag-cell--off'">
              <div class="flag-ic" aria-hidden="true">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                  <rect x="2" y="12" width="12" height="2" rx="1"/>
                  <rect x="6" y="2" width="4" height="10" rx="1"/>
                  <path d="M2 2h12v6H2z" rx="1"/>
                </svg>
              </div>
              <span class="flag-lbl">National Level</span>
              <span class="flag-status">{{ selectedPoe.is_national_level ? 'YES' : 'NO' }}</span>
            </div>
          </div>

          <!-- ── Section 3: Operational Details ── -->
          <div class="modal-section-hdr">
            <div class="msh-num msh-orange">3</div>
            <span class="msh-title">Operational Details</span>
          </div>

          <div class="detail-card">
            <div class="dc-row">
              <div class="dc-ic dc-ic--blue" aria-hidden="true">
                <svg viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.6" stroke-linecap="round">
                  <rect x="1.5" y="2.5" width="11" height="9" rx="1.5"/>
                  <path d="M1.5 6h11"/><path d="M5 2.5v3.5"/><path d="M9 2.5v3.5"/>
                </svg>
              </div>
              <div class="dc-body">
                <span class="dc-lbl">POE Type</span>
                <span class="dc-val">{{ formatPoeType(selectedPoe.poe_type) }}</span>
              </div>
            </div>

            <div class="dc-row">
              <div class="dc-ic" :class="`dc-ic--${selectedPoe.transport_mode || 'land'}`" aria-hidden="true">
                <!-- Reuse transport mode inline SVG -->
                <svg v-if="selectedPoe.transport_mode === 'land'" viewBox="0 0 14 14" fill="none"
                     stroke="#2E7D32" stroke-width="1.6" stroke-linecap="round">
                  <rect x="1" y="3.5" width="12" height="7" rx="1.2"/>
                  <path d="M1 7h12"/>
                  <circle cx="3.5" cy="10.5" r="1.4"/>
                  <circle cx="10.5" cy="10.5" r="1.4"/>
                </svg>
                <svg v-else-if="selectedPoe.transport_mode === 'air'" viewBox="0 0 14 14" fill="none"
                     stroke="#1565C0" stroke-width="1.6" stroke-linecap="round">
                  <path d="M1.5 9L5.5 7 3 2.5 5 2.5 9.5 7 12 6.5C13.5 6.5 13.5 7.5 12 7.5L9.5 7 5.5 12 3.5 12 5.5 7"/>
                </svg>
                <svg v-else viewBox="0 0 14 14" fill="none"
                     stroke="#0097A7" stroke-width="1.6" stroke-linecap="round">
                  <path d="M1 10.5 C3.5 8.5 5.5 8.5 7 10.5 C8.5 12.5 10.5 12.5 13 10.5"/>
                  <path d="M2.5 4.5 L5 4.5 L5 2.5 L9 2.5 L9 4.5 L11.5 4.5 L11.5 8.5 L2.5 8.5 Z"/>
                </svg>
              </div>
              <div class="dc-body">
                <span class="dc-lbl">Transport Mode</span>
                <span class="dc-val">{{ transportLabel(selectedPoe.transport_mode) }}</span>
              </div>
            </div>
          </div>

          <!-- Critical details panel (if present) -->
          <div v-if="selectedPoe.critical_details" class="critical-panel">
            <div class="cp-header" aria-hidden="true">
              <svg viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.6" stroke-linecap="round">
                <circle cx="7" cy="7" r="5.5"/>
                <line x1="7" y1="5" x2="7" y2="7.5"/>
                <circle cx="7" cy="10" r=".8" fill="#1565C0"/>
              </svg>
              <span class="cp-hdr-txt">Operational Notes</span>
            </div>
            <p class="cp-body">{{ selectedPoe.critical_details }}</p>
          </div>

          <!-- ── Section 4: Reference Data ── -->
          <div class="modal-section-hdr">
            <div class="msh-num msh-purple">4</div>
            <span class="msh-title">Reference Data Identifiers</span>
            <span class="msh-readonly-note">Reference data — app v{{ refDataVersion }}</span>
          </div>

          <div class="detail-card">
            <div class="dc-row">
              <div class="dc-ic dc-ic--grey" aria-hidden="true">
                <svg viewBox="0 0 14 14" fill="none" stroke="#78909C" stroke-width="1.6" stroke-linecap="round">
                  <rect x="1.5" y="1.5" width="11" height="11" rx="2"/>
                  <path d="M4 5h6M4 7.5h4M4 10h5"/>
                </svg>
              </div>
              <div class="dc-body">
                <span class="dc-lbl">Record ID (POES.js)</span>
                <span class="dc-val dc-val--mono">{{ selectedPoe.id }}</span>
              </div>
            </div>

            <div v-if="selectedPoe.poe_code" class="dc-row">
              <div class="dc-ic dc-ic--blue" aria-hidden="true">
                <svg viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.6" stroke-linecap="round">
                  <path d="M2 4l3-2.5 2 2 3-2.5L12 3"/><path d="M2 4v7l3 1.5 2-2 3 2 2-1.5V3"/>
                  <line x1="5" y1="1.5" x2="5" y2="12.5"/><line x1="7" y1="3.5" x2="7" y2="13.5"/>
                </svg>
              </div>
              <div class="dc-body">
                <span class="dc-lbl">POE Code (Database)</span>
                <span class="dc-val dc-val--mono dc-val--blue">{{ selectedPoe.poe_code }}</span>
              </div>
            </div>

            <div v-if="selectedPoe.source_url" class="dc-row">
              <div class="dc-ic dc-ic--teal" aria-hidden="true">
                <svg viewBox="0 0 14 14" fill="none" stroke="#0097A7" stroke-width="1.6" stroke-linecap="round">
                  <path d="M6 8L2 12"/><path d="M8 6l4-4"/>
                  <path d="M10 2h2v2"/><path d="M2 10v2h2"/>
                  <path d="M5 5l4 4"/>
                </svg>
              </div>
              <div class="dc-body">
                <span class="dc-lbl">Source Reference</span>
                <span class="dc-val dc-val--link dc-val--mono">{{ selectedPoe.source_url }}</span>
              </div>
            </div>

            <div class="dc-row">
              <div class="dc-ic dc-ic--grey" aria-hidden="true">
                <svg viewBox="0 0 14 14" fill="none" stroke="#78909C" stroke-width="1.6" stroke-linecap="round">
                  <circle cx="7" cy="7" r="5.5"/>
                  <polyline points="7 4 7 7.5 9.5 9"/>
                </svg>
              </div>
              <div class="dc-body">
                <span class="dc-lbl">Data Origin</span>
                <span class="dc-val">{{ selectedPoe.source_origin || 'POES.js Reference File' }}</span>
              </div>
            </div>
          </div>

          <!-- IHR notice -->
          <div class="ihr-notice" role="note" aria-label="IHR notice">
            <div class="ihr-notice-icon" aria-hidden="true">
              <svg viewBox="0 0 16 16" fill="none" stroke="#1565C0" stroke-width="1.6" stroke-linecap="round">
                <path d="M8 1L1.5 4.5v5C1.5 13 5 15.5 8 16.5c3-1 6.5-3.5 6.5-7v-5L8 1z"/>
                <polyline points="5.5 8 7 9.5 10.5 6"/>
              </svg>
            </div>
            <div class="ihr-notice-body">
              <span class="ihr-notice-title">WHO / IHR 2005 · Article 23</span>
              <span class="ihr-notice-sub">
                This POE is part of the national IHR surveillance network.
                Reference data is hardcoded in the app and does not require network access.
                Codes stored here match the poe_code column in all screening records.
              </span>
            </div>
          </div>

          <!-- Bottom spacer -->
          <div style="height: 32px;" aria-hidden="true" />
        </div>
      </IonContent>
    </IonModal>

  </IonPage>
</template>

<script setup>
/**
 * PoesView.vue — Points of Entry Reference View
 *
 * READ-ONLY. All data sourced from window.POE_MAIN (POES.js).
 * No poeDB interaction — this view has zero IndexedDB operations.
 * No TypeScript — pure JavaScript as per project standards.
 *
 * Data flow:
 *   window.POE_MAIN.poes  →  allPoes (computed)
 *                         →  filteredPoes (search + filters)
 *                         →  groupedPoes (grouped by admin_level_1)
 *
 * Filters:
 *   activeCountry: 'ALL' | 'Rwanda' | 'Uganda'
 *   activeMode:    'ALL' | 'land' | 'air' | 'water'
 *   activeFlag:    'ALL' | 'major' | 'osbp' | 'national'
 *   searchQuery:   free-text against poe_name, district, province, poe_code, id
 */

import { ref, computed } from 'vue'
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton,
  IonContent, IonRefresher, IonRefresherContent, IonModal,
} from '@ionic/vue'

// ─────────────────────────────────────────────────────────────────────────
// CONSTANTS
// ─────────────────────────────────────────────────────────────────────────

const refDataVersion = 'rda-2026-02-01'

// ─────────────────────────────────────────────────────────────────────────
// STATE
// ─────────────────────────────────────────────────────────────────────────

const searchQuery = ref('')
const activeMode  = ref('ALL')
const activeFlag  = ref('ALL')
const selectedPoe = ref(null)

// ─────────────────────────────────────────────────────────────────────────
// RAW DATA — window.POE_MAIN (POES.js)
// ─────────────────────────────────────────────────────────────────────────

/**
 * allPoes — Uganda POEs only, sourced from window.POE_MAIN (POES.js).
 * Hardcoded to Uganda: this app operates within the Uganda national hierarchy.
 * Safe accessor: returns [] if the global is not yet loaded.
 */
const allPoes = computed(() => {
  const data = window.POE_MAIN
  if (!data || !Array.isArray(data.poes)) return []
  return data.poes.filter(p => p.country === 'Uganda')
})

// ─────────────────────────────────────────────────────────────────────────
// AGGREGATE COUNTS (for stats strip + chip counts)
// ─────────────────────────────────────────────────────────────────────────

const landCount     = computed(() => allPoes.value.filter(p => p.transport_mode === 'land').length)
const airCount      = computed(() => allPoes.value.filter(p => p.transport_mode === 'air').length)
const waterCount    = computed(() => allPoes.value.filter(p => p.transport_mode === 'water').length)
const majorCount    = computed(() => allPoes.value.filter(p => p.is_major_entry).length)
const osbpCount     = computed(() => allPoes.value.filter(p => p.is_recommended_osbp).length)
const nationalCount = computed(() => allPoes.value.filter(p => p.is_national_level).length)

// ─────────────────────────────────────────────────────────────────────────
// FILTER DEFINITIONS
// ─────────────────────────────────────────────────────────────────────────

const modeFilters = [
  { value: 'ALL',   label: 'All Modes', color: 'all' },
  { value: 'land',  label: 'Land',      color: 'land' },
  { value: 'air',   label: 'Air',       color: 'air' },
  { value: 'water', label: 'Water',     color: 'water' },
]

const flagFilters = computed(() => [
  { value: 'ALL',      label: 'All Types', color: 'all',      count: allPoes.value.length },
  { value: 'major',    label: 'Major',     color: 'major',    count: majorCount.value },
  { value: 'osbp',     label: 'OSBP',      color: 'osbp',     count: osbpCount.value },
  { value: 'national', label: 'National',  color: 'national', count: nationalCount.value },
])

// ─────────────────────────────────────────────────────────────────────────
// FILTERED DATA
// ─────────────────────────────────────────────────────────────────────────

const filteredPoes = computed(() => {
  let list = allPoes.value

  // Transport mode filter
  if (activeMode.value !== 'ALL') {
    list = list.filter(p => p.transport_mode === activeMode.value)
  }

  // Special flag filter
  if (activeFlag.value === 'major') {
    list = list.filter(p => p.is_major_entry === true)
  } else if (activeFlag.value === 'osbp') {
    list = list.filter(p => p.is_recommended_osbp === true)
  } else if (activeFlag.value === 'national') {
    list = list.filter(p => p.is_national_level === true)
  }

  // Full-text search
  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    list = list.filter(p => {
      return (
        (p.poe_name     || '').toLowerCase().includes(q) ||
        (p.district     || '').toLowerCase().includes(q) ||
        (p.province     || '').toLowerCase().includes(q) ||
        (p.poe_code     || '').toLowerCase().includes(q) ||
        (p.id           || '').toLowerCase().includes(q) ||
        (p.border_country || '').toLowerCase().includes(q) ||
        (p.admin_level_1  || '').toLowerCase().includes(q) ||
        (p.regional_cluster_or_rpheoc || '').toLowerCase().includes(q)
      )
    })
  }

  return list
})

/**
 * groupedPoes — filtered list grouped by admin_level_1.
 * Returns array of { admin_level_1, type, country, poes[] } sorted by country then name.
 */
const groupedPoes = computed(() => {
  const groups = {}

  for (const poe of filteredPoes.value) {
    const key = poe.admin_level_1 || poe.province || 'Unknown'
    if (!groups[key]) {
      groups[key] = {
        admin_level_1: key,
        type: poe.admin_level_1_type || 'province',
        country: poe.country,
        poes: [],
      }
    }
    groups[key].poes.push(poe)
  }

  // Sort groups alphabetically by RPHEOC / province name
  return Object.values(groups).sort((a, b) =>
    a.admin_level_1.localeCompare(b.admin_level_1)
  )
})

// ─────────────────────────────────────────────────────────────────────────
// FILTER STATE
// ─────────────────────────────────────────────────────────────────────────

const hasActiveFilters = computed(() => {
  return activeMode.value !== 'ALL'    ||
         activeFlag.value !== 'ALL'    ||
         searchQuery.value.trim() !== ''
})

// ─────────────────────────────────────────────────────────────────────────
// ACTIONS
// ─────────────────────────────────────────────────────────────────────────

function setMode(val) {
  activeMode.value = val
}

function setFlag(val) {
  activeFlag.value = val
}

function clearAllFilters() {
  activeMode.value  = 'ALL'
  activeFlag.value  = 'ALL'
  searchQuery.value = ''
}

function openDetail(poe) {
  selectedPoe.value = poe
}

function handleRefresh(event) {
  // Reference data is hardcoded — nothing to fetch.
  // Reset filters to give the "refreshed" experience, then complete spinner.
  setTimeout(() => {
    event.target.complete()
  }, 400)
}

// ─────────────────────────────────────────────────────────────────────────
// DISPLAY HELPERS
// ─────────────────────────────────────────────────────────────────────────

function transportLabel(mode) {
  const labels = { land: 'Land', air: 'Air', water: 'Water', sea: 'Sea' }
  return labels[mode] || (mode ? String(mode).charAt(0).toUpperCase() + mode.slice(1) : 'Land')
}

function formatPoeType(type) {
  const labels = {
    airport:     'International Airport',
    airstrip:    'Airstrip',
    land_border: 'Land Border',
    port:        'Lake / River Port',
    island_entry: 'Island Entry',
    sea_port:    'Sea Port',
  }
  if (!type) return 'Border Post'
  return labels[type] || type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}
</script>

<style scoped>
/* ══════════════════════════════════════════════════════════════════════════
   SENTINEL DESIGN SYSTEM — Light Theme
   Palette: #0D47A1 / #1565C0 header · #EEF2FF bg · #FFFFFF cards · #E3EAF8 borders
   Fonts:   Syne (numbers / headings) · DM Sans (body)
   NO DARK MODE — light theming throughout
══════════════════════════════════════════════════════════════════════════ */

/* ── Toolbar / Header ── */
.poe-hdr {
  --background: transparent;
}

.poe-toolbar {
  --background: linear-gradient(180deg, #0D47A1 0%, #1565C0 100%);
  --border-width: 0;
  --min-height: 56px;
  position: relative;
  overflow: hidden;
}

.poe-toolbar::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse at 85% 20%, rgba(255,255,255,.07), transparent 55%),
    radial-gradient(ellipse at 10% 80%, rgba(255,255,255,.04), transparent 40%);
  pointer-events: none;
}

/* Back button */
.poe-back-btn {
  --color: rgba(255,255,255,.85);
  --icon-font-size: 22px;
}

/* Title block */
.hdr-title-block {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1px;
}

.hdr-eyebrow {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: rgba(255,255,255,.5);
  line-height: 1;
}

.hdr-main-title {
  --color: #ffffff;
  font-family: 'Syne', -apple-system, sans-serif;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: -.3px;
  line-height: 1.15;
  padding: 0;
}

/* POE count badge */
.hdr-count-badge {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 10px;
  padding: 5px 10px;
  margin-right: 4px;
}

.hdr-count-num {
  font-family: 'Syne', sans-serif;
  font-size: 18px;
  font-weight: 800;
  color: #fff;
  line-height: 1;
  letter-spacing: -.5px;
}

.hdr-count-lbl {
  font-size: 8px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: rgba(255,255,255,.55);
  margin-top: 1px;
}

/* ── Stats Strip ── */
.stats-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  background: rgba(255,255,255,.08);
  border-top: 1px solid rgba(255,255,255,.1);
  overflow: hidden;
}

.ss-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 4px 9px;
  position: relative;
}

.ss-cell:not(:last-child)::after {
  content: '';
  position: absolute;
  right: 0;
  top: 20%;
  height: 60%;
  width: 1px;
  background: rgba(255,255,255,.15);
}

.ss-num {
  font-family: 'Syne', sans-serif;
  font-size: 21px;
  font-weight: 800;
  color: #fff;
  line-height: 1;
  letter-spacing: -.5px;
}

.ss-num--land  { color: #81C784; }
.ss-num--air   { color: #90CAF9; }
.ss-num--water { color: #4DD0E1; }

.ss-lbl {
  font-size: 8.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .7px;
  color: rgba(255,255,255,.5);
  margin-top: 2px;
}

/* ── Search Area ── */
.search-area {
  background: #1565C0;
  padding: 8px 14px 6px;
}

.search-wrap {
  background: rgba(255,255,255,.13);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 13px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  height: 42px;
}

.search-ic {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  stroke: rgba(255,255,255,.6);
}

.search-input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  font-size: 14px;
  font-weight: 400;
  color: #fff;
  font-family: 'DM Sans', -apple-system, sans-serif;
  caret-color: rgba(255,255,255,.8);
}

.search-input::placeholder {
  color: rgba(255,255,255,.45);
}

.search-clear-btn {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: rgba(255,255,255,.15);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  flex-shrink: 0;
  cursor: pointer;
}

.search-clear-btn svg {
  width: 12px;
  height: 12px;
  stroke: rgba(255,255,255,.7);
}

/* ── Filter Chip Rows ── */
.chip-row {
  background: #1565C0;
  padding: 0 12px 8px;
  display: flex;
  gap: 6px;
  overflow-x: auto;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
}

.chip-row::-webkit-scrollbar { display: none; }
.chip-row--modes { padding-top: 0; }
.chip-row--flags { padding-bottom: 10px; }

/* Base chip */
.f-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 11px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .3px;
  white-space: nowrap;
  border: 1.5px solid;
  flex-shrink: 0;
  cursor: pointer;
  transition: all .12s ease;
  /* Default inactive state — subtle on the blue header */
  background: rgba(255,255,255,.08);
  border-color: rgba(255,255,255,.18);
  color: rgba(255,255,255,.65);
}

.chip-flag { font-size: 13px; line-height: 1; }

.chip-ic {
  width: 13px;
  height: 13px;
  flex-shrink: 0;
  stroke: currentColor;
}

.chip-lbl { line-height: 1; }

.chip-count {
  min-width: 18px;
  height: 16px;
  border-radius: 8px;
  font-size: 9px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
  background: rgba(255,255,255,.15);
  color: rgba(255,255,255,.75);
}

/* Active state — white filled */
.f-chip--active {
  background: rgba(255,255,255,.95) !important;
  border-color: #fff !important;
  color: #0D47A1 !important;
}

.f-chip--active .chip-count {
  background: #E3F2FD;
  color: #1565C0;
}

/* ── IonContent ── */
.poe-content {
  --background: #EEF2FF;
}

/* ── Empty State ── */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 64px 32px 48px;
  text-align: center;
}

.empty-icon-wrap {
  width: 80px;
  height: 80px;
  border-radius: 24px;
  background: #E3F2FD;
  border: 1px solid #BBDEFB;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
}

.empty-icon {
  width: 44px;
  height: 44px;
}

.empty-title {
  font-family: 'Syne', sans-serif;
  font-size: 18px;
  font-weight: 800;
  color: #0D1B3E;
  letter-spacing: -.3px;
  margin-bottom: 8px;
}

.empty-sub {
  font-size: 13px;
  color: #78909C;
  line-height: 1.5;
  max-width: 260px;
  margin-bottom: 24px;
}

.empty-clear-btn {
  height: 42px;
  padding: 0 22px;
  border-radius: 13px;
  background: #0D47A1;
  color: #fff;
  border: none;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: .2px;
  cursor: pointer;
}

/* ── POE List ── */
.poe-list {
  padding: 8px 0 0;
}

.list-bottom-pad {
  height: 40px;
}

/* ── Section Header ── */
.section-hdr {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px 5px;
}

.sh-left {
  display: flex;
  align-items: center;
  gap: 6px;
}

.sh-type-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.sh-dot--pheoc    { background: #6A1B9A; }
.sh-dot--province { background: #0D47A1; }

.sh-label {
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: #546E7A;
}

.sh-type-tag {
  font-size: 9px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 5px;
  text-transform: uppercase;
  letter-spacing: .5px;
}

.sh-tag--pheoc    { background: #F3E5F5; color: #6A1B9A; border: 1px solid #CE93D8; }
.sh-tag--province { background: #E3F2FD; color: #0D47A1; border: 1px solid #BBDEFB; }

.sh-count {
  font-size: 11px;
  font-weight: 700;
  color: #78909C;
  background: #E8EDF8;
  padding: 2px 8px;
  border-radius: 7px;
}

/* ── POE Card ── */
.poe-card {
  display: flex;
  align-items: center;
  gap: 0;
  background: #FFFFFF;
  border-radius: 16px;
  border: 1px solid #E3EAF8;
  margin: 0 12px 8px;
  overflow: hidden;
  cursor: pointer;
  transition: box-shadow .1s, transform .1s;
  position: relative;
  box-shadow: 0 1px 4px rgba(13, 28, 80, .05);
}

.poe-card:active {
  transform: scale(.985);
  box-shadow: 0 1px 2px rgba(13, 28, 80, .08);
}

/* Left mode accent bar */
.poe-card-accent {
  width: 4px;
  align-self: stretch;
  flex-shrink: 0;
  border-radius: 0 2px 2px 0;
}

.accent--land  { background: #2E7D32; }
.accent--air   { background: #1565C0; }
.accent--water { background: #0097A7; }

/* Card body */
.poe-card-body {
  flex: 1;
  padding: 11px 10px 11px 12px;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

/* Row 1 */
.pcb-row1 {
  display: flex;
  align-items: center;
  gap: 9px;
}

.poe-mode-icon {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.poe-mode-icon svg {
  width: 17px;
  height: 17px;
}

.mode-ic--land  { background: #E8F5E9; color: #2E7D32; }
.mode-ic--air   { background: #E3F2FD; color: #1565C0; }
.mode-ic--water { background: #E0F7FA; color: #0097A7; }

.poe-name-block {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.poe-name {
  font-family: 'Syne', sans-serif;
  font-size: 14.5px;
  font-weight: 700;
  color: #0D1B3E;
  letter-spacing: -.2px;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.poe-type-lbl {
  font-size: 9.5px;
  font-weight: 600;
  color: #78909C;
  letter-spacing: .1px;
}

.poe-badges {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
  flex-shrink: 0;
}

.poe-badge {
  font-size: 8.5px;
  font-weight: 800;
  letter-spacing: .5px;
  padding: 2px 6px;
  border-radius: 5px;
  white-space: nowrap;
  border: 1px solid;
}

.badge--major    { background: #FFFBEB; color: #B45309; border-color: #FDE68A; }
.badge--osbp     { background: #ECFDF5; color: #065F46; border-color: #A7F3D0; }
.badge--national { background: #FDF4FF; color: #7E22CE; border-color: #E9D5FF; }

/* Row 2: Geography */
.pcb-row2 {
  display: flex;
  align-items: center;
  gap: 4px;
}

.geo-ic {
  width: 11px;
  height: 11px;
  flex-shrink: 0;
}

.pcb-district {
  font-size: 11.5px;
  font-weight: 600;
  color: #37474F;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 130px;
}

.pcb-sep {
  color: #B0BEC5;
  font-size: 10px;
  line-height: 1;
  flex-shrink: 0;
}

.pcb-province {
  font-size: 11px;
  font-weight: 500;
  color: #78909C;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
}

/* Row 3: Country + code + border */
.pcb-row3 {
  display: flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
}

.pcb-code {
  font-size: 10px;
  font-weight: 800;
  font-family: 'SF Mono', 'Fira Code', monospace;
  letter-spacing: .5px;
  color: #0D47A1;
  background: #E3F2FD;
  border: 1px solid #BBDEFB;
  padding: 1px 6px;
  border-radius: 5px;
}

.border-ic {
  width: 11px;
  height: 11px;
  flex-shrink: 0;
}

.pcb-border {
  font-size: 10.5px;
  font-weight: 600;
  color: #2E7D32;
}

/* Chevron */
.poe-card-chevron {
  padding: 0 12px 0 6px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
}

.poe-card-chevron svg {
  width: 8px;
  height: 12px;
}

/* ══════════════════════════════════════════════════════════════════════════
   DETAIL MODAL
══════════════════════════════════════════════════════════════════════════ */

.modal-content {
  --background: #EEF2FF;
}

.modal-handle {
  width: 40px;
  height: 4px;
  background: #CBD5E0;
  border-radius: 2px;
  margin: 10px auto 0;
}

/* Modal header */
.modal-hdr {
  position: relative;
  overflow: hidden;
  padding-bottom: 14px;
}

.modal-hdr--land  { background: linear-gradient(180deg, #1B5E20 0%, #2E7D32 100%); }
.modal-hdr--air   { background: linear-gradient(180deg, #0D47A1 0%, #1565C0 100%); }
.modal-hdr--water { background: linear-gradient(180deg, #006064 0%, #0097A7 100%); }

.mh-pattern {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse at 85% 20%, rgba(255,255,255,.08), transparent 55%),
    radial-gradient(ellipse at 10% 80%, rgba(255,255,255,.05), transparent 40%);
  pointer-events: none;
}

.mh-top {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 14px 16px 10px;
  position: relative;
  z-index: 2;
}

.mh-close-btn {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.18);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  cursor: pointer;
}

.mh-close-btn svg {
  width: 14px;
  height: 14px;
}

.mh-title-block {
  flex: 1;
  min-width: 0;
}

.mh-eyebrow {
  display: block;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: rgba(255,255,255,.55);
  margin-bottom: 3px;
}

.mh-title {
  font-family: 'Syne', sans-serif;
  font-size: 20px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -.4px;
  line-height: 1.15;
  margin: 0;
}

.mh-readonly-tag {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  background: rgba(255,255,255,.1);
  border: 1px solid rgba(255,255,255,.16);
  border-radius: 8px;
  padding: 4px 8px;
  flex-shrink: 0;
}

.mh-readonly-tag svg {
  width: 12px;
  height: 12px;
}

.mh-readonly-tag span {
  font-size: 8px;
  font-weight: 700;
  letter-spacing: .5px;
  text-transform: uppercase;
  color: rgba(255,255,255,.6);
}

/* Badge row */
.mh-badge-row {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 16px;
  position: relative;
  z-index: 2;
  flex-wrap: wrap;
}

.mh-type-pill {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 10.5px;
  font-weight: 800;
  letter-spacing: .3px;
}

.type-pill--land  { background: rgba(255,255,255,.15); color: #fff; border: 1px solid rgba(255,255,255,.25); }
.type-pill--air   { background: rgba(255,255,255,.15); color: #fff; border: 1px solid rgba(255,255,255,.25); }
.type-pill--water { background: rgba(255,255,255,.15); color: #fff; border: 1px solid rgba(255,255,255,.25); }

.mh-badge {
  padding: 3px 10px;
  border-radius: 8px;
  font-size: 9.5px;
  font-weight: 800;
  letter-spacing: .5px;
  text-transform: uppercase;
}

/* Modal body */
.modal-body {
  padding: 12px 14px 0;
}

/* Section headers */
.modal-section-hdr {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 0 8px;
}

.msh-num {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 800;
  color: #fff;
  flex-shrink: 0;
}

.msh-blue   { background: #1565C0; }
.msh-green  { background: #2E7D32; }
.msh-orange { background: #E65100; }
.msh-purple { background: #6A1B9A; }

.msh-title {
  font-family: 'Syne', sans-serif;
  font-size: 13.5px;
  font-weight: 700;
  color: #0D1B3E;
  letter-spacing: -.1px;
  flex: 1;
}

.msh-readonly-note {
  font-size: 9px;
  font-weight: 600;
  color: #78909C;
  background: #ECEFF1;
  padding: 2px 7px;
  border-radius: 6px;
  white-space: nowrap;
}

/* Detail card */
.detail-card {
  background: #FFFFFF;
  border: 1px solid #E3EAF8;
  border-radius: 16px;
  overflow: hidden;
}

.dc-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 14px;
  border-bottom: 1px solid #F1F5FB;
}

.dc-row:last-child {
  border-bottom: none;
}

.dc-ic {
  width: 30px;
  height: 30px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.dc-ic svg { width: 14px; height: 14px; }

.dc-ic--blue   { background: #E3F2FD; }
.dc-ic--purple { background: #F3E5F5; }
.dc-ic--orange { background: #FBE9E7; }
.dc-ic--green  { background: #E8F5E9; }
.dc-ic--teal   { background: #E0F7FA; }
.dc-ic--grey   { background: #ECEFF1; }
.dc-ic--land   { background: #E8F5E9; }
.dc-ic--air    { background: #E3F2FD; }
.dc-ic--water  { background: #E0F7FA; }

.dc-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dc-lbl {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .6px;
  color: #90A4AE;
  display: block;
}

.dc-val {
  font-size: 13.5px;
  font-weight: 600;
  color: #0D1B3E;
  line-height: 1.2;
}

.dc-val--green { color: #2E7D32; }
.dc-val--blue  { color: #0D47A1; }
.dc-val--mono  { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 12px; letter-spacing: .3px; }
.dc-val--link  { color: #1565C0; font-size: 10.5px; word-break: break-all; }

.dc-right { flex-shrink: 0; }

.dc-badge {
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: .5px;
  text-transform: uppercase;
}

.dc-badge--pheoc { background: #F3E5F5; color: #6A1B9A; border: 1px solid #CE93D8; }

/* ── Flags grid ── */
.flags-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.flag-cell {
  border-radius: 14px;
  border: 1.5px solid;
  padding: 12px 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  text-align: center;
}

.flag-ic {
  width: 28px;
  height: 28px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.flag-ic svg { width: 14px; height: 14px; }

.flag-lbl {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  line-height: 1.2;
}

.flag-status {
  font-family: 'Syne', sans-serif;
  font-size: 13px;
  font-weight: 800;
}

/* OFF state */
.flag-cell--off {
  background: #FAFAFA;
  border-color: #ECEFF1;
}

.flag-cell--off .flag-ic { background: #ECEFF1; }
.flag-cell--off .flag-ic svg { stroke: #B0BEC5; }
.flag-cell--off .flag-lbl { color: #B0BEC5; }
.flag-cell--off .flag-status { color: #CFD8DC; }

/* MAJOR ON */
.flag-cell--major {
  background: linear-gradient(145deg, #FFFBEB, #FEF3C7);
  border-color: #FDE68A;
}

.flag-cell--major .flag-ic { background: #F59E0B; }
.flag-cell--major .flag-ic svg { stroke: #fff; }
.flag-cell--major .flag-lbl { color: #92400E; }
.flag-cell--major .flag-status { color: #B45309; }

/* OSBP ON */
.flag-cell--osbp {
  background: linear-gradient(145deg, #ECFDF5, #D1FAE5);
  border-color: #A7F3D0;
}

.flag-cell--osbp .flag-ic { background: #10B981; }
.flag-cell--osbp .flag-ic svg { stroke: #fff; }
.flag-cell--osbp .flag-lbl { color: #065F46; }
.flag-cell--osbp .flag-status { color: #047857; }

/* NATIONAL ON */
.flag-cell--national {
  background: linear-gradient(145deg, #FDF4FF, #F3E8FF);
  border-color: #E9D5FF;
}

.flag-cell--national .flag-ic { background: #7C3AED; }
.flag-cell--national .flag-ic svg { stroke: #fff; }
.flag-cell--national .flag-lbl { color: #4C1D95; }
.flag-cell--national .flag-status { color: #5B21B6; }

/* ── Critical details panel ── */
.critical-panel {
  background: #E3F2FD;
  border: 1px solid #BBDEFB;
  border-radius: 14px;
  padding: 11px 13px;
  margin-top: 8px;
}

.cp-header {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 7px;
}

.cp-header svg {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
}

.cp-hdr-txt {
  font-size: 9.5px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: #0D47A1;
}

.cp-body {
  font-size: 12.5px;
  font-weight: 500;
  color: #1565C0;
  line-height: 1.5;
}

/* ── IHR notice ── */
.ihr-notice {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: #F0F4FF;
  border: 1px solid #C7D7F5;
  border-radius: 14px;
  padding: 12px 13px;
  margin-top: 12px;
}

.ihr-notice-icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: #E3F2FD;
  border: 1px solid #BBDEFB;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.ihr-notice-icon svg {
  width: 16px;
  height: 16px;
}

.ihr-notice-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ihr-notice-title {
  font-size: 11px;
  font-weight: 800;
  color: #0D47A1;
}

.ihr-notice-sub {
  font-size: 10.5px;
  font-weight: 400;
  color: #546E7A;
  line-height: 1.5;
}

/* ══════════════════════════════════════════════════════════════════════════
   DEEP OVERRIDES — Ionic 8 internal parts
══════════════════════════════════════════════════════════════════════════ */

/* IonRefresher */
ion-refresher {
  --background: #EEF2FF;
  --color: #1565C0;
}

/* Modal sheet styling */
:global(.poe-detail-modal .modal-wrapper) {
  border-radius: 24px 24px 0 0 !important;
  overflow: hidden;
}

/* Ensure IonHeader background matches on iOS safe-area */
ion-header.poe-hdr {
  background: linear-gradient(180deg, #0D47A1 0%, #1565C0 100%);
}
</style>
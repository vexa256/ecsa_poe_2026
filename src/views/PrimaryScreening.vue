<template>
  <IonPage>

    <!-- ══════════════════════════════════════════════════════════════════════
         HEADER — Blue gradient, session stats, POE context, sync/queue badges
    ══════════════════════════════════════════════════════════════════════ -->
    <IonHeader class="ps-header" :translucent="false">
      <div class="ps-hdr-pattern" aria-hidden="true" />

      <!-- Top bar: back + title + upload button -->
      <div class="ps-hdr-top">
        <div class="ps-hdr-left">
          <button class="ps-back-btn" type="button" aria-label="Back to menu" @click="goBack">
            <svg viewBox="0 0 18 18" fill="none" stroke="rgba(255,255,255,0.85)" stroke-width="2.2" stroke-linecap="round">
              <polyline points="11 4 6 9 11 14"/>
            </svg>
          </button>
          <div class="ps-title-block">
            <span class="ps-eyebrow">{{ auth.poe_code ?? 'POE' }} · Primary</span>
            <div class="ps-page-title">Rapid Screening</div>
          </div>
        </div>

        <div class="ps-hdr-actions">
          <!-- Referral queue badge button -->
          <button
            class="ps-hact"
            type="button"
            :class="{ 'ps-hact--alert': openReferrals > 0 }"
            aria-label="View referral queue"
            @click="showTab = 'queue'"
          >
            <svg viewBox="0 0 15 15" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="1.7" stroke-linecap="round">
              <rect x="1" y="3" width="13" height="10" rx="2"/>
              <line x1="4" y1="7" x2="11" y2="7"/><line x1="4" y1="10" x2="8" y2="10"/>
            </svg>
            <span class="ps-hact-txt">Queue</span>
            <span v-if="openReferrals > 0" class="ps-hbadge" aria-label="{{ openReferrals }} open referrals">{{ openReferrals }}</span>
          </button>

          <!-- Upload / sync button -->
          <button
            class="ps-hact"
            type="button"
            :class="{ 'ps-hact--syncing': syncEngineRunning }"
            :disabled="syncEngineRunning"
            aria-label="Upload pending records"
            @click="manualSync"
          >
            <svg v-if="!syncEngineRunning" viewBox="0 0 15 15" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="1.7" stroke-linecap="round">
              <polyline points="11 5 7 1 3 5"/><line x1="7" y1="1" x2="7" y2="11"/><path d="M1 13h13"/>
            </svg>
            <svg v-else viewBox="0 0 15 15" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="1.7" stroke-linecap="round" class="ps-spin">
              <path d="M13 7A6 6 0 1 1 7 1"/>
            </svg>
            <span class="ps-hact-txt">{{ syncEngineRunning ? 'Syncing…' : 'Upload' }}</span>
            <span v-if="pendingSyncCount > 0 && !syncEngineRunning" class="ps-hbadge ps-hbadge--warn">{{ pendingSyncCount }}</span>
          </button>
        </div>
      </div>

      <!-- Session stats strip: Today / Symptomatic / Uploaded / Pending -->
      <div class="ps-session-strip">
        <div class="ps-ss-cell">
          <span class="ps-ss-n">{{ todayCount }}</span>
          <span class="ps-ss-l">Today</span>
        </div>
        <div class="ps-ss-cell">
          <span class="ps-ss-n ps-ss-n--symptom">{{ symptomaticCount }}</span>
          <span class="ps-ss-l">Symptomatic</span>
        </div>
        <div class="ps-ss-cell">
          <span class="ps-ss-n ps-ss-n--ok">{{ syncedCount }}</span>
          <span class="ps-ss-l">Uploaded</span>
        </div>
        <div class="ps-ss-cell">
          <span class="ps-ss-n" :class="pendingSyncCount > 0 ? 'ps-ss-n--warn' : 'ps-ss-n--ok'">{{ pendingSyncCount }}</span>
          <span class="ps-ss-l">Pending</span>
        </div>
      </div>

      <!-- POE context bar: name, district, connectivity pill -->
      <div class="ps-poe-bar">
        <div class="ps-poe-ic" aria-hidden="true">
          <svg viewBox="0 0 14 14" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="1.5" stroke-linecap="round">
            <path d="M7 1C4.2 1 2 3.2 2 6c0 4 5 7 5 7s5-3 5-7c0-2.8-2.2-5-5-5z"/><circle cx="7" cy="6" r="1.5"/>
          </svg>
        </div>
        <div class="ps-poe-info">
          <div class="ps-poe-title">{{ auth.poe_code ?? '—' }}</div>
          <div class="ps-poe-sub">{{ auth.district_code ?? '—' }} · {{ auth.pheoc_code ?? '—' }} · {{ auth.country_code ?? '—' }}</div>
        </div>
        <div class="ps-conn-pill" :class="isOnline ? 'ps-conn--online' : 'ps-conn--offline'">
          <div class="ps-cp-dot" :class="isOnline ? 'ps-cp-dot--on' : 'ps-cp-dot--off'" />
          <span class="ps-cp-txt">{{ isOnline ? 'Online' : 'Offline' }}</span>
        </div>
      </div>

      <!-- Tab selector: Capture | Records | Queue -->
      <div class="ps-tabs">
        <button class="ps-tab" :class="{ 'ps-tab--active': showTab === 'capture' }" @click="showTab = 'capture'" type="button">Capture</button>
        <button class="ps-tab" :class="{ 'ps-tab--active': showTab === 'records' }" @click="showTab = 'records'; loadRecords()" type="button">Records</button>
        <button class="ps-tab" :class="{ 'ps-tab--active': showTab === 'queue' }" @click="showTab = 'queue'; loadQueue()" type="button">
          Queue
          <span v-if="openReferrals > 0" class="ps-tab-badge">{{ openReferrals }}</span>
        </button>
      </div>
    </IonHeader>

    <IonContent class="ps-content" :scrollY="true">

      <!-- ══════════════════════════════════════════════════════════
           PERMISSION GUARD — shows if user cannot screen here
      ══════════════════════════════════════════════════════════ -->
      <div v-if="!canScreen" class="ps-guard-banner" role="alert">
        <div class="ps-guard-icon" aria-hidden="true">
          <svg viewBox="0 0 18 18" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round">
            <path d="M9 2L3 5v5c0 4 3.5 7 6 8 2.5-1 6-4 6-8V5L9 2z"/>
            <line x1="9" y1="7" x2="9" y2="10"/><circle cx="9" cy="12.5" r=".7" fill="#fff"/>
          </svg>
        </div>
        <div class="ps-guard-info">
          <div class="ps-guard-title">Access Restricted</div>
          <div class="ps-guard-sub">Your role ({{ auth.role_key ?? 'unknown' }}) does not have can_do_primary_screening. Contact your administrator.</div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════
           TAB: CAPTURE FORM — premium, animated, instant
      ══════════════════════════════════════════════════════════ -->
      <div v-show="showTab === 'capture'">

        <!-- ── GENDER SELECTOR — full-width glass cards ── -->
        <div class="pf-section">
          <div class="pf-label">Sex</div>
          <div class="pf-gender-row">
            <button
              v-for="g in GENDERS" :key="g.value"
              class="pf-gender-card"
              :class="{ 'pf-gender-card--male': g.value==='MALE', 'pf-gender-card--female': g.value==='FEMALE', 'pf-gender-card--active': form.gender === g.value }"
              type="button" :aria-pressed="form.gender === g.value" :aria-label="g.label"
              @click="form.gender = g.value; clearFieldError('gender')"
            >
              <!-- Animated selection ring -->
              <span class="pf-gc-ring" aria-hidden="true"></span>
              <span class="pf-gc-icon" aria-hidden="true" v-html="g.svg"></span>
              <span class="pf-gc-lbl">{{ g.label }}</span>
              <span v-if="form.gender === g.value" class="pf-gc-tick" aria-hidden="true">
                <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="2 7 5.5 10.5 12 3"/></svg>
              </span>
            </button>
          </div>
          <div v-if="fieldErrors.gender" class="pf-err" role="alert">{{ fieldErrors.gender }}</div>
        </div>

        <!-- ── TEMPERATURE — floating label input with live gauge ── -->
        <div class="pf-section">
          <div class="pf-label">Temperature <span class="pf-opt">optional</span></div>
          <div class="pf-temp-card" :class="{ 'pf-temp-card--focus': focusTemp, 'pf-temp-card--warn': tempWarningLevel==='warn', 'pf-temp-card--crit': tempWarningLevel==='danger' }">
            <div class="pf-temp-icon" aria-hidden="true">
              <svg viewBox="0 0 18 18" fill="none" stroke-width="1.6" stroke-linecap="round"
                :stroke="tempWarningLevel==='danger'?'#C62828':tempWarningLevel==='warn'?'#E65100':'#1565C0'">
                <path d="M11 11V3.5a2 2 0 00-4 0V11a4 4 0 104 0z"/>
              </svg>
            </div>
            <div class="pf-temp-body">
              <input
                ref="tempInputRef"
                v-model="form.temperature_raw"
                type="number" step="0.1"
                :min="tempUnit==='C'?25:77" :max="tempUnit==='C'?45:113"
                class="pf-temp-input-el"
                placeholder="—"
                aria-label="Temperature"
                @focus="focusTemp=true"
                @blur="focusTemp=false; validateTemp()"
                @input="validateTemp()"
              />
              <!-- Live warning text under input -->
              <span v-if="tempWarning" class="pf-temp-hint" :class="`pf-temp-hint--${tempWarningLevel}`">{{ tempWarning }}</span>
            </div>
            <!-- Unit toggle pill -->
            <div class="pf-unit-pill">
              <button class="pf-unit-btn" :class="{ 'pf-unit-btn--on': tempUnit==='C' }" type="button" @click="switchTempUnit('C')">°C</button>
              <button class="pf-unit-btn" :class="{ 'pf-unit-btn--on': tempUnit==='F' }" type="button" @click="switchTempUnit('F')">°F</button>
            </div>
            <button v-if="form.temperature_raw" class="pf-temp-x" type="button" aria-label="Clear temperature" @click="clearTemp">
              <svg viewBox="0 0 12 12" fill="none" stroke="#B0BEC5" stroke-width="2" stroke-linecap="round"><line x1="2" y1="2" x2="10" y2="10"/><line x1="10" y1="2" x2="2" y2="10"/></svg>
            </button>
          </div>
          <!-- Animated fever gauge bar — only when value entered -->
          <div v-if="form.temperature_raw && !fieldErrors.temperature" class="pf-gauge-wrap" aria-hidden="true">
            <div class="pf-gauge-track">
              <div class="pf-gauge-fill" :class="`pf-gauge-fill--${tempWarningLevel||'normal'}`" :style="{ width: tempScalePercent + '%' }"></div>
              <div class="pf-gauge-thumb" :class="`pf-gauge-thumb--${tempWarningLevel||'normal'}`" :style="{ left: tempScalePercent + '%' }"></div>
            </div>
          </div>
          <div v-if="fieldErrors.temperature" class="pf-err" role="alert">{{ fieldErrors.temperature }}</div>
        </div>

        <!-- ── SYMPTOMS — 2 hero tap targets ── -->
        <div class="pf-section pf-section--last">
          <div class="pf-label">Symptoms Present? <span class="pf-req">IHR Required</span></div>

          <!-- ── WHO SYMPTOM REFERENCE — tap to expand, no clutter ── -->
          <div class="pf-syref">
            <button class="pf-syref-toggle" type="button" @click="syrefOpen = !syrefOpen" :aria-expanded="syrefOpen">
              <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                <circle cx="7" cy="7" r="5.5"/>
                <line x1="7" y1="5" x2="7" y2="7.5"/><circle cx="7" cy="9.5" r=".5" fill="currentColor"/>
              </svg>
              <span>Does traveler report any of the following?</span>
              <svg class="pf-syref-chevron" :class="{ 'pf-syref-chevron--open': syrefOpen }"
                viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                <polyline points="2 3.5 5 6.5 8 3.5"/>
              </svg>
            </button>
            <transition name="pf-slide">
              <div v-if="syrefOpen" class="pf-syref-body">
                <span v-for="s in WHO_SYMPTOMS" :key="s" class="pf-syref-chip">{{ s }}</span>
              </div>
            </transition>
          </div>

          <div class="pf-sym-row">
            <!-- NO -->
            <button
              class="pf-sym-card pf-sym-card--no"
              :class="{ 'pf-sym-card--active': form.symptoms_present === 0 }"
              type="button" aria-label="No symptoms" :aria-pressed="form.symptoms_present === 0"
              @click="form.symptoms_present = 0; clearFieldError('symptoms')"
            >
              <span class="pf-sc-ring" aria-hidden="true"></span>
              <span class="pf-sc-icon" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"
                  :stroke="form.symptoms_present===0?'#fff':'#90A4AE'">
                  <polyline points="4 16 12 24 28 8"/>
                </svg>
              </span>
              <span class="pf-sc-lbl">Clear</span>
              <span class="pf-sc-sub">No symptoms</span>
            </button>
            <!-- YES -->
            <button
              class="pf-sym-card pf-sym-card--yes"
              :class="{ 'pf-sym-card--active': form.symptoms_present === 1 }"
              type="button" aria-label="Symptoms present — referral will be created" :aria-pressed="form.symptoms_present === 1"
              @click="form.symptoms_present = 1; clearFieldError('symptoms')"
            >
              <span class="pf-sc-ring" aria-hidden="true"></span>
              <span class="pf-sc-icon" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none" stroke-width="2.4" stroke-linecap="round"
                  :stroke="form.symptoms_present===1?'#fff':'#90A4AE'">
                  <circle cx="16" cy="16" r="11"/>
                  <line x1="16" y1="10" x2="16" y2="17"/><circle cx="16" cy="21" r="1.2" :fill="form.symptoms_present===1?'#fff':'#90A4AE'"/>
                </svg>
              </span>
              <span class="pf-sc-lbl">Refer</span>
              <span class="pf-sc-sub">→ Secondary</span>
            </button>
          </div>
          <div v-if="fieldErrors.symptoms" class="pf-err" role="alert">{{ fieldErrors.symptoms }}</div>

          <!-- ── TRAVELER NAME — slides in only when symptoms = YES ── -->
          <transition name="pf-slide">
            <div v-if="form.symptoms_present === 1" class="pf-name-wrap">
              <div class="pf-name-card" :class="{ 'pf-name-card--focus': focusName }">
                <svg viewBox="0 0 18 18" fill="none" stroke="#90A4AE" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                  <circle cx="9" cy="6" r="3.5"/><path d="M2 16c0-3.5 3-6 7-6s7 2.5 7 6"/>
                </svg>
                <input
                  v-model="form.traveler_full_name"
                  type="text" class="pf-name-input" maxlength="150"
                  placeholder="Traveler name (optional)"
                  autocomplete="off" spellcheck="false"
                  @focus="focusName=true"
                  @blur="focusName=false; form.traveler_full_name=form.traveler_full_name.trim()"
                />
              </div>
            </div>
          </transition>

          <!-- Referral preview — shown when symptoms = YES -->
          <div v-if="form.symptoms_present === 1" class="ps-referral-preview">
            <div class="ps-rp-header" :class="`ps-rp-header--${referralPriority.toLowerCase()}`">
              <svg viewBox="0 0 16 16" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                <path d="M8 1L2 4v4c0 3.5 2.5 6 6 7 3.5-1 6-3.5 6-7V4L8 1z"/><polyline points="5.5 8 7 9.5 10.5 6.5"/>
              </svg>
              <span class="ps-rp-h-title">Auto-Referral Will Be Created</span>
              <span class="ps-rp-h-badge" :class="`ps-rp-badge--${referralPriority.toLowerCase()}`">{{ referralPriority }}</span>
            </div>
            <div class="ps-rp-body">
              <div class="ps-rp-row"><span class="ps-rp-k">Assigned To</span><span class="ps-rp-v">POE Secondary Officer</span></div>
              <div class="ps-rp-row"><span class="ps-rp-k">Priority</span><span class="ps-rp-v" :class="`ps-rp-v--${referralPriority.toLowerCase()}`">{{ referralPriority }}</span></div>
              <div class="ps-rp-row"><span class="ps-rp-k">Reason</span><span class="ps-rp-v">PRIMARY_SYMPTOMS_DETECTED</span></div>
              <div v-if="form.temperature_raw" class="ps-rp-row">
                <span class="ps-rp-k">Temperature</span>
                <span class="ps-rp-v" :class="tempWarningLevel === 'danger' ? 'ps-rp-v--crit' : (tempWarningLevel === 'warn' ? 'ps-rp-v--high' : '')">
                  {{ form.temperature_raw }}°{{ tempUnit }}
                </span>
              </div>
              <div class="ps-rp-row"><span class="ps-rp-k">Gender</span><span class="ps-rp-v">{{ form.gender || 'Not yet selected' }}</span></div>
              <div v-if="form.traveler_full_name" class="ps-rp-row"><span class="ps-rp-k">Traveler</span><span class="ps-rp-v">{{ form.traveler_full_name }}</span></div>
            </div>
          </div>
        </div>

        <!-- ── CAPTURE BUTTON ── -->
        <div class="ps-capture-section">
          <button
            class="ps-capture-btn"
            :class="{
              'ps-capture-btn--symptomatic': form.symptoms_present === 1,
              'ps-capture-btn--loading': capturing,
              'ps-capture-btn--disabled': !canCapture || capturing
            }"
            type="button"
            :disabled="!canCapture || capturing"
            aria-label="Capture and save screening record"
            @click="captureScreening"
          >
            <div class="ps-cb-icon" aria-hidden="true">
              <svg v-if="!capturing" viewBox="0 0 20 20" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round">
                <circle cx="10" cy="10" r="8"/><polyline points="6 10 9 13 14 7"/>
              </svg>
              <svg v-else viewBox="0 0 20 20" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" class="ps-spin">
                <path d="M18 10A8 8 0 1 1 10 2"/>
              </svg>
            </div>
            <div class="ps-cb-text">
              <span class="ps-cb-main">{{ capturing ? 'Saving…' : (form.symptoms_present === 1 ? 'Capture & Refer' : 'Capture & Save') }}</span>
              <span class="ps-cb-sub">{{ capturing ? 'Writing to offline store' : 'Records offline immediately' }}</span>
            </div>
            <div class="ps-cb-shortcut" aria-hidden="true">⏎</div>
          </button>

          <!-- Capture disabled reason -->
          <div v-if="!canCapture && !capturing" class="ps-capture-hint" role="status">
            {{ captureHint }}
          </div>
        </div>

        <!-- ── SUCCESS / REFERRAL CONFIRMATION STATE ── -->
        <div v-if="lastResult" class="ps-success-area">
          <!-- Symptomatic success — referral created -->
          <div v-if="lastResult.symptoms_present === 1" class="ps-success-toast ps-success-toast--referral">
            <div class="ps-st-icon ps-st-icon--ref" aria-hidden="true">
              <svg viewBox="0 0 20 20" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round">
                <path d="M10 2L3 6v5c0 4 3 7 7 8 4-1 7-4 7-8V6L10 2z"/><polyline points="6 10 9 13 14 7"/>
              </svg>
            </div>
            <div class="ps-st-info">
              <div class="ps-st-title">Saved · Referral Created</div>
              <div class="ps-st-sub">
                Priority: <strong>{{ lastResult.notification?.priority ?? '—' }}</strong> ·
                Refer traveler to Secondary Screening ·
                <span class="ps-st-sync">{{ SYNC.LABELS[lastResult.sync_status] }}</span>
              </div>
            </div>
            <div class="ps-st-counter" aria-label="Today's total">
              <span class="ps-st-count-n">{{ todayCount }}</span>
              <span class="ps-st-count-l">Today</span>
            </div>
          </div>

          <!-- Asymptomatic success -->
          <div v-else class="ps-success-toast ps-success-toast--ok">
            <div class="ps-st-icon ps-st-icon--ok" aria-hidden="true">
              <svg viewBox="0 0 20 20" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"><polyline points="4 10 8 14 16 6"/></svg>
            </div>
            <div class="ps-st-info">
              <div class="ps-st-title">Saved · No Symptoms Detected</div>
              <div class="ps-st-sub">Traveler cleared · <span class="ps-st-sync">{{ SYNC.LABELS[lastResult.sync_status] }}</span></div>
            </div>
            <div class="ps-st-counter" aria-label="Today's total">
              <span class="ps-st-count-n">{{ todayCount }}</span>
              <span class="ps-st-count-l">Today</span>
            </div>
          </div>

          <!-- Post-capture action row -->
          <div class="ps-void-row">
            <button class="ps-void-btn" type="button" @click="promptVoid(lastResult)" aria-label="Void this record">
              <svg viewBox="0 0 14 14" fill="none" stroke="#90A4AE" stroke-width="1.5" stroke-linecap="round"><path d="M11 3H8.5L7.5 2h-3L3.5 3H1M12 3l-.6 8.4a1 1 0 01-1 .6H3.6a1 1 0 01-1-.6L2 3"/></svg>
              <span class="ps-void-btn-txt">Void Record</span>
            </button>
            <button class="ps-next-btn" type="button" @click="resetForm" aria-label="Start next traveler screening">
              <svg viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.8" stroke-linecap="round"><polyline points="5 3 10 7 5 11"/></svg>
              <span class="ps-next-btn-txt">Next Traveler →</span>
            </button>
          </div>
        </div>

        <!-- Sync engine error banner -->
        <div v-if="syncError" class="ps-sync-error" role="alert">
          <svg viewBox="0 0 16 16" fill="none" stroke="#C62828" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="8" r="6.5"/><line x1="8" y1="5" x2="8" y2="9"/><circle cx="8" cy="11.5" r=".7" fill="#C62828"/></svg>
          <div>
            <div class="ps-sync-error-title">Sync Error</div>
            <div class="ps-sync-error-sub">{{ syncError }}</div>
          </div>
          <button class="ps-sync-error-dismiss" type="button" aria-label="Dismiss error" @click="syncError = ''">✕</button>
        </div>

        <div style="height: 24px;" aria-hidden="true" />
      </div><!-- /capture tab -->

      <!-- ══════════════════════════════════════════════════════════
           TAB: RECORDS — paginated, date/filter modal
      ══════════════════════════════════════════════════════════ -->
      <div v-show="showTab === 'records'">
        <div class="ps-tab-toolbar">
          <!-- Filter button with current date label -->
          <button class="ps-filter-btn" type="button" @click="filterModalOpen = true" aria-label="Filter records">
            <svg viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.6" stroke-linecap="round"><path d="M1 2h12M3 7h8M5 12h4"/></svg>
            <span class="ps-filter-btn-txt">{{ filterDateLabel }}</span>
            <svg viewBox="0 0 10 10" fill="none" stroke="#1565C0" stroke-width="1.6" stroke-linecap="round"><polyline points="2 3 5 7 8 3"/></svg>
          </button>
          <span class="ps-records-count">{{ recordsTotal }} record{{ recordsTotal !== 1 ? 's' : '' }}</span>
          <button class="ps-refresh-btn" type="button" @click="loadRecords()" :disabled="recordsLoading" aria-label="Refresh">
            <svg :class="{ 'ps-spin': recordsLoading }" viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.8" stroke-linecap="round">
              <path d="M12 7A5 5 0 1 1 7 2"/><polyline points="12 2 12 7 7 7"/>
            </svg>
          </button>
        </div>

        <!-- Filter chips row -->
        <div class="ps-filter-chips">
          <button class="ps-chip" :class="{ 'ps-chip--active': filterSymptoms === 'ALL' }" @click="filterSymptoms = 'ALL'; loadRecords()" type="button">All</button>
          <button class="ps-chip ps-chip--sym" :class="{ 'ps-chip--active': filterSymptoms === 'YES' }" @click="filterSymptoms = 'YES'; loadRecords()" type="button">Symptomatic</button>
          <button class="ps-chip ps-chip--ok"  :class="{ 'ps-chip--active': filterSymptoms === 'NO' }"  @click="filterSymptoms = 'NO';  loadRecords()" type="button">Asymptomatic</button>
          <button class="ps-chip ps-chip--warn" :class="{ 'ps-chip--active': filterSync === 'UNSYNCED' }" @click="filterSync = filterSync === 'UNSYNCED' ? 'ALL' : 'UNSYNCED'; loadRecords()" type="button">Pending</button>
        </div>

        <div v-if="recordsLoading" class="ps-loading" role="status">
          <div class="ps-loading-dots"><div/><div/><div/></div>
          <span>Loading…</span>
        </div>

        <div v-else-if="records.length === 0" class="ps-empty" role="status">
          <svg viewBox="0 0 40 40" fill="none" stroke="#B0BEC5" stroke-width="1.5" stroke-linecap="round">
            <rect x="8" y="6" width="24" height="28" rx="3"/><line x1="13" y1="13" x2="27" y2="13"/><line x1="13" y1="18" x2="27" y2="18"/><line x1="13" y1="23" x2="22" y2="23"/>
          </svg>
          <div class="ps-empty-title">No records for {{ filterDateLabel }}</div>
          <div class="ps-empty-sub">Try a different date or filter</div>
        </div>

        <div v-else class="ps-records-list">
          <div
            v-for="rec in records"
            :key="rec.client_uuid"
            class="ps-record-card"
            :class="{
              'ps-record-card--symptomatic': rec.symptoms_present === 1,
              'ps-record-card--voided': rec.record_status === 'VOIDED'
            }"
          >
            <div class="ps-rc-left">
              <!-- Gender icon -->
              <div class="ps-rc-avatar" :class="`ps-rc-avatar--${(rec.gender || 'UNKNOWN').toLowerCase()}`">
                <svg v-if="rec.gender === 'MALE'" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="7" cy="7" r="4"/><line x1="10" y1="4" x2="14" y2="0"/><polyline points="11 0 14 0 14 3"/></svg>
                <svg v-else-if="rec.gender === 'FEMALE'" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="8" cy="6" r="4"/><line x1="8" y1="10" x2="8" y2="15"/><line x1="5.5" y1="13" x2="10.5" y2="13"/></svg>
                <svg v-else viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="8" cy="8" r="5"/></svg>
              </div>
            </div>
            <div class="ps-rc-body">
              <div class="ps-rc-row1">
                <span class="ps-rc-name">{{ rec.traveler_full_name || rec.gender || 'Unknown' }}</span>
                <span class="ps-rc-time">{{ formatTime(rec.captured_at) }}</span>
              </div>
              <div class="ps-rc-row2">
                <span class="ps-rc-pill" :class="rec.symptoms_present === 1 ? 'ps-rc-pill--sym' : 'ps-rc-pill--ok'">
                  {{ rec.symptoms_present === 1 ? 'Symptomatic' : 'Asymptomatic' }}
                </span>
                <span v-if="rec.temperature_value" class="ps-rc-temp">{{ rec.temperature_value }}°{{ rec.temperature_unit }}</span>
                <span v-if="rec.referral_created === 1 && rec.record_status !== 'VOIDED'" class="ps-rc-pill ps-rc-pill--ref">Referred</span>
                <span v-if="rec.record_status === 'VOIDED'" class="ps-rc-pill ps-rc-pill--void">Voided</span>
              </div>
              <div class="ps-rc-row3">
                <span class="ps-rc-sync" :class="`ps-rc-sync--${(rec.sync_status || 'UNSYNCED').toLowerCase()}`">
                  {{ SYNC.LABELS[rec.sync_status] ?? rec.sync_status }}
                </span>
                <span v-if="rec.last_sync_error" class="ps-rc-err-hint" :title="rec.last_sync_error">⚠ {{ truncate(rec.last_sync_error, 40) }}</span>
              </div>
            </div>
            <!-- Void action (only creator within 24h or admin) -->
            <button
              v-if="rec.record_status !== 'VOIDED' && canVoidRecord(rec)"
              class="ps-rc-void-btn"
              type="button"
              aria-label="Void this record"
              @click.stop="promptVoid(rec)"
            >
              <svg viewBox="0 0 14 14" fill="none" stroke="#90A4AE" stroke-width="1.5" stroke-linecap="round"><path d="M11 3H8.5L7.5 2h-3L3.5 3H1M12 3l-.6 8.4a1 1 0 01-1 .6H3.6a1 1 0 01-1-.6L2 3"/></svg>
            </button>
          </div>
        </div>

        <!-- Pagination bar -->
        <div v-if="recordsTotal > RECORDS_PER_PAGE" class="ps-pagination">
          <button class="ps-page-btn" type="button" :disabled="recordsPage === 0" @click="prevRecordsPage">← Prev</button>
          <span class="ps-page-info">{{ recordsPage + 1 }} / {{ Math.ceil(recordsTotal / RECORDS_PER_PAGE) }}</span>
          <button class="ps-page-btn" type="button" :disabled="(recordsPage + 1) * RECORDS_PER_PAGE >= recordsTotal" @click="nextRecordsPage">Next →</button>
        </div>

        <div style="height: 24px;" aria-hidden="true" />
      </div><!-- /records tab -->

      <!-- ══════════════════════════════════════════════════════════
           TAB: REFERRAL QUEUE — OPEN notifications at this POE
      ══════════════════════════════════════════════════════════ -->
      <div v-show="showTab === 'queue'">
        <div class="ps-tab-toolbar">
          <span class="ps-tab-toolbar-title">Referral Queue — {{ auth.poe_code }}</span>
          <!-- Queue status filter -->
          <div class="ps-queue-filter">
            <button class="ps-chip ps-chip--sm" :class="{ 'ps-chip--active': queueStatusFilter === 'OPEN' }" @click="queueStatusFilter = 'OPEN'; loadQueue()" type="button">Open</button>
            <button class="ps-chip ps-chip--sm" :class="{ 'ps-chip--active': queueStatusFilter === 'ALL' }"  @click="queueStatusFilter = 'ALL';  loadQueue()" type="button">All</button>
          </div>
          <button class="ps-refresh-btn" type="button" @click="loadQueue()" :disabled="queueLoading" aria-label="Refresh queue">
            <svg :class="{ 'ps-spin': queueLoading }" viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.8" stroke-linecap="round">
              <path d="M12 7A5 5 0 1 1 7 2"/><polyline points="12 2 12 7 7 7"/>
            </svg>
          </button>
        </div>

        <div v-if="queueLoading" class="ps-loading" role="status" aria-label="Loading queue">
          <div class="ps-loading-dots"><div/><div/><div/></div>
          <span>Loading referral queue…</span>
        </div>

        <div v-else-if="queueItems.length === 0" class="ps-empty" role="status">
          <svg viewBox="0 0 40 40" fill="none" stroke="#B0BEC5" stroke-width="1.5" stroke-linecap="round">
            <path d="M20 6L4 14v10c0 8 7 14 16 16 9-2 16-8 16-16V14L20 6z"/><polyline points="13 20 17 24 27 14"/>
          </svg>
          <div class="ps-empty-title">No open referrals</div>
          <div class="ps-empty-sub">All travelers cleared at this POE</div>
        </div>

        <div v-else class="ps-queue-list">
          <div
            v-for="item in queueItems"
            :key="item.notification_id"
            class="ps-queue-card"
            :class="`ps-queue-card--${(item.priority || 'NORMAL').toLowerCase()}`"
          >
            <!-- Priority indicator stripe -->
            <div class="ps-qc-stripe" :class="`ps-qc-stripe--${(item.priority || 'NORMAL').toLowerCase()}`" aria-hidden="true" />

            <div class="ps-qc-body">
              <div class="ps-qc-row1">
                <span class="ps-qc-priority-pill" :class="`ps-qcp--${(item.priority || 'NORMAL').toLowerCase()}`">{{ item.priority ?? 'NORMAL' }}</span>
                <span class="ps-qc-time">{{ formatTime(item.notification_created_at) }}</span>
              </div>
              <div class="ps-qc-row2">
                <span class="ps-qc-gender">{{ item.gender ?? '—' }}</span>
                <span v-if="item.temperature_value" class="ps-qc-temp">{{ item.temperature_value }}°{{ item.temperature_unit }}</span>
                <span v-if="item.traveler_full_name" class="ps-qc-name">· {{ item.traveler_full_name }}</span>
              </div>
              <div class="ps-qc-row3">
                <span class="ps-qc-meta">By: {{ item.screener_name ?? 'Officer' }}</span>
                <span class="ps-qc-meta">Status: {{ item.notification_status }}</span>
              </div>
              <div v-if="item.reason_text" class="ps-qc-reason">{{ item.reason_text }}</div>

              <!-- ── CANCELLED REFERRAL NOTE ──
                   Per business rule: cancelled referrals remain as COMPLETED primary
                   records with referral_created=1. The primary record is preserved.
                   This notification shows here as CLOSED — it was open, then cancelled.
                   The primary screening record remains in the records tab as COMPLETED,
                   proving the traveler WAS symptomatic and a referral WAS issued.
              -->
              <div v-if="item.notification_status === 'CLOSED'" class="ps-qc-cancelled-note">
                <svg viewBox="0 0 12 12" fill="none" stroke="#78909C" stroke-width="1.4" stroke-linecap="round"><circle cx="6" cy="6" r="4.5"/><line x1="6" y1="4" x2="6" y2="6.5"/><circle cx="6" cy="8.5" r=".5" fill="#78909C"/></svg>
                Referral cancelled — primary record preserved as COMPLETED (audit)
              </div>
            </div>

            <!-- Cancel button — only for OPEN referrals, only by POE-level user -->
            <div v-if="item.notification_status === 'OPEN' && canCancelReferral" class="ps-qc-actions">
              <button
                class="ps-qc-cancel-btn"
                type="button"
                :disabled="cancellingId === item.notification_id"
                aria-label="Cancel this referral"
                @click="promptCancelReferral(item)"
              >
                {{ cancellingId === item.notification_id ? 'Cancelling…' : 'Cancel Referral' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Queue API error -->
        <div v-if="queueError" class="ps-sync-error" role="alert">
          <svg viewBox="0 0 16 16" fill="none" stroke="#C62828" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="8" r="6.5"/><line x1="8" y1="5" x2="8" y2="9"/><circle cx="8" cy="11.5" r=".7" fill="#C62828"/></svg>
          <div><div class="ps-sync-error-title">Queue Error</div><div class="ps-sync-error-sub">{{ queueError }}</div></div>
          <button class="ps-sync-error-dismiss" type="button" @click="queueError = ''">✕</button>
        </div>

        <div style="height: 24px;" aria-hidden="true" />
      </div><!-- /queue tab -->

    </IonContent>

    <!-- ── VOID MODAL ── -->
    <IonModal :is-open="voidModalOpen" :can-dismiss="true" @didDismiss="voidModalOpen = false" class="ps-void-modal">
      <div class="ps-vm-content">
        <div class="ps-vm-header">
          <svg viewBox="0 0 18 18" fill="none" stroke="#C62828" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
            <path d="M15 5H12L11 4H7L6 5H3M16 5l-.75 10.5a1 1 0 01-1 .5H3.75a1 1 0 01-1-.5L2 5"/>
          </svg>
          <span class="ps-vm-title">Void Screening Record</span>
        </div>
        <p class="ps-vm-body">This action cannot be undone. The record will be marked VOIDED and any linked OPEN notification will be automatically closed. Linked IN_PROGRESS secondary cases must be closed manually by the secondary officer.</p>
        <div class="ps-vm-field">
          <label class="ps-vm-label" for="void-reason">Reason for Voiding <span style="color:#C62828">*</span></label>
          <textarea
            id="void-reason"
            v-model="voidReason"
            class="ps-vm-textarea"
            placeholder="Enter a clear reason (minimum 10 characters)…"
            rows="3"
            maxlength="255"
          />
          <div class="ps-vm-char-count">{{ voidReason.length }}/255</div>
        </div>
        <div v-if="voidError" class="ps-vm-error" role="alert">{{ voidError }}</div>
        <div class="ps-vm-actions">
          <button class="ps-vm-cancel" type="button" @click="voidModalOpen = false">Cancel</button>
          <button
            class="ps-vm-confirm"
            type="button"
            :disabled="voidReason.trim().length < 10 || voiding"
            @click="executeVoid"
          >
            {{ voiding ? 'Voiding…' : 'Confirm Void' }}
          </button>
        </div>
      </div>
    </IonModal>

    <!-- ── CANCEL REFERRAL MODAL ── -->
    <IonModal :is-open="cancelModalOpen" :can-dismiss="true" @didDismiss="cancelModalOpen = false" class="ps-void-modal">
      <div class="ps-vm-content">
        <div class="ps-vm-header">
          <svg viewBox="0 0 18 18" fill="none" stroke="#E65100" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
            <path d="M9 2L3 5v5c0 4 3.5 7 6 8 2.5-1 6-4 6-8V5L9 2z"/>
            <line x1="6" y1="6" x2="12" y2="12"/><line x1="12" y1="6" x2="6" y2="12"/>
          </svg>
          <span class="ps-vm-title">Cancel Referral</span>
        </div>
        <p class="ps-vm-body">
          <strong>Important:</strong> Cancelling this referral closes the notification only. The primary screening record remains COMPLETED with <code>referral_created=1</code> as an immutable audit record. The traveler was symptomatic — that fact is preserved permanently.
        </p>
        <div class="ps-vm-field">
          <label class="ps-vm-label" for="cancel-reason">Reason for Cancellation <span style="color:#E65100">*</span></label>
          <textarea
            id="cancel-reason"
            v-model="cancelReason"
            class="ps-vm-textarea"
            placeholder="Enter reason for cancelling this referral (minimum 5 characters)…"
            rows="3"
            maxlength="255"
          />
        </div>
        <div v-if="cancelError" class="ps-vm-error" role="alert">{{ cancelError }}</div>
        <div class="ps-vm-actions">
          <button class="ps-vm-cancel" type="button" @click="cancelModalOpen = false">Back</button>
          <button
            class="ps-vm-confirm ps-vm-confirm--orange"
            type="button"
            :disabled="cancelReason.trim().length < 5 || cancellingActive"
            @click="executeCancelReferral"
          >
            {{ cancellingActive ? 'Cancelling…' : 'Cancel Referral' }}
          </button>
        </div>
      </div>
    </IonModal>

    <!-- ── DATE FILTER MODAL ── -->
    <IonModal :is-open="filterModalOpen" :can-dismiss="true" @didDismiss="filterModalOpen = false" class="ps-void-modal">
      <div class="ps-vm-content">
        <div class="ps-vm-header">
          <svg viewBox="0 0 18 18" fill="none" stroke="#1565C0" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
            <rect x="2" y="3" width="14" height="13" rx="2"/><line x1="2" y1="8" x2="16" y2="8"/>
            <line x1="6" y1="1" x2="6" y2="5"/><line x1="12" y1="1" x2="12" y2="5"/>
          </svg>
          <span class="ps-vm-title">Filter Records</span>
        </div>

        <!-- Quick date presets -->
        <div class="ps-filter-presets">
          <button v-for="p in datePresets" :key="p.label"
            class="ps-preset-btn"
            :class="{ 'ps-preset-btn--active': filterDateLabel === p.label }"
            type="button"
            @click="applyFilter(p.date, p.label)">
            {{ p.label }}
          </button>
        </div>

        <!-- Custom date picker -->
        <div class="ps-vm-field" style="margin-top:14px">
          <label class="ps-vm-label" for="custom-date">Custom Date</label>
          <input
            id="custom-date"
            type="date"
            class="ps-date-input"
            :value="filterDate"
            :max="new Date().toISOString().slice(0,10)"
            @change="applyFilter($event.target.value, $event.target.value)"
          />
        </div>

        <!-- Symptoms filter -->
        <div class="ps-vm-field">
          <label class="ps-vm-label">Symptoms Filter</label>
          <div class="ps-filter-chips" style="margin:0">
            <button class="ps-chip" :class="{ 'ps-chip--active': filterSymptoms === 'ALL' }" @click="filterSymptoms = 'ALL'" type="button">All</button>
            <button class="ps-chip ps-chip--sym" :class="{ 'ps-chip--active': filterSymptoms === 'YES' }" @click="filterSymptoms = 'YES'" type="button">Symptomatic</button>
            <button class="ps-chip ps-chip--ok"  :class="{ 'ps-chip--active': filterSymptoms === 'NO' }"  @click="filterSymptoms = 'NO'"  type="button">Asymptomatic</button>
          </div>
        </div>

        <!-- Sync filter -->
        <div class="ps-vm-field">
          <label class="ps-vm-label">Sync Status</label>
          <div class="ps-filter-chips" style="margin:0">
            <button class="ps-chip" :class="{ 'ps-chip--active': filterSync === 'ALL' }"      @click="filterSync = 'ALL'"      type="button">All</button>
            <button class="ps-chip ps-chip--warn" :class="{ 'ps-chip--active': filterSync === 'UNSYNCED' }" @click="filterSync = 'UNSYNCED'" type="button">Pending</button>
            <button class="ps-chip ps-chip--ok"  :class="{ 'ps-chip--active': filterSync === 'SYNCED' }"   @click="filterSync = 'SYNCED'"   type="button">Uploaded</button>
          </div>
        </div>

        <div class="ps-vm-actions" style="margin-top:16px">
          <button class="ps-vm-cancel" type="button" @click="filterModalOpen = false">Close</button>
          <button class="ps-vm-confirm" type="button" style="background:#1565C0" @click="applyFilter(filterDate, filterDateLabel)">Apply Filters</button>
        </div>
      </div>
    </IonModal>

  </IonPage>
</template>

<script setup>
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  PrimaryScreeningView.vue                                                    ║
 * ║  ECSA-HC POE Sentinel · WHO/IHR 2005 Compliant                             ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Language: Pure JavaScript (.vue) — NO TypeScript, no type annotations     ║
 * ║  Route:    /PrimaryScreening                                                 ║
 * ║  DB:       poeDB.js (Dexie 4.3.x) — NEVER instantiate Dexie in this file  ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  DEVELOPER LAWS (all enforced here):                                         ║
 * ║    LAW 1 — Navigate with server integer id. Never UUID in route params.     ║
 * ║    LAW 2 — No Authorization Bearer headers. API is open.                   ║
 * ║    LAW 3 — Every IDB record carries id: serverInt as well as client_uuid.   ║
 * ║    LAW 4 — Normalise IDB reads: id = r.id ?? r.server_id ?? null           ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  BUSINESS RULES ENFORCED:                                                    ║
 * ║    • traveler_full_name is OPTIONAL at primary. Never required here.        ║
 * ║    • symptoms_present=1 → atomic dbAtomicWrite([primary, notification])     ║
 * ║    • referral_created=1 stamped only when notification write succeeds       ║
 * ║    • Priority: CRITICAL≥38.5°C, HIGH≥37.5°C, NORMAL=no/unmeasured temp    ║
 * ║    • Cancelled referral: notification→CLOSED, primary stays COMPLETED       ║
 * ║      referral_created=1 is IMMUTABLE — never reset on cancel or void        ║
 * ║    • Void: creator within 24h OR POE_ADMIN/NATIONAL_ADMIN anytime          ║
 * ║    • Void auto-closes linked OPEN notification (not IN_PROGRESS)            ║
 * ║    • Day counter: localStorage APP.DAY_COUNT_DAY_KEY / APP.DAY_COUNT_CNT_KEY║
 * ║    • Sync engine: activeSyncKeys prevents concurrent duplicate uploads      ║
 * ║    • All timers cleared in onUnmounted                                      ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonPage, IonHeader, IonContent, IonModal,
  toastController, alertController,
} from '@ionic/vue'
import {
  dbPut, dbGet, dbGetAll, safeDbPut,
  dbGetByIndex, dbGetRange, dbCountIndex,
  dbAtomicWrite, dbQuery,
  genUUID, isoNow, getDeviceId, createRecordBase,
  STORE, SYNC, APP,
} from '@/services/poeDB'

// ─── Router ──────────────────────────────────────────────────────────────────
const router = useRouter()

// ─── Auth — read FRESH inside every submit handler, never at module level ────
// The ref below is just for reactive display. The actual auth object is always
// re-read from sessionStorage inside submit/save handlers.
const auth = ref({})
const canScreen = computed(() => !!(
  auth.value?.is_active &&
  auth.value?._permissions?.can_do_primary_screening &&
  auth.value?.poe_code
))
const roleLabel = computed(() => ({
  POE_PRIMARY:         'Primary Officer',
  POE_SECONDARY:       'Secondary Officer',
  POE_DATA_OFFICER:    'Data Officer',
  POE_ADMIN:           'POE Admin',
  DISTRICT_SUPERVISOR: 'District Supervisor',
  PHEOC_OFFICER:       'PHEOC Officer',
  NATIONAL_ADMIN:      'National Admin',
}[auth.value?.role_key] ?? auth.value?.role_key ?? ''))

const canCancelReferral = computed(() => {
  const rk = auth.value?.role_key
  return ['POE_PRIMARY', 'POE_ADMIN', 'POE_SECONDARY', 'NATIONAL_ADMIN'].includes(rk)
})

// ─── Connectivity ─────────────────────────────────────────────────────────────
const isOnline = ref(navigator.onLine)

// ─── WHO/IHR symptom reference list (display only — not stored) ──────────────
const syrefOpen = ref(false)
const WHO_SYMPTOMS = [
  'Fever (history or measured)', 'Persistent fever', 'Cough',
  'Shortness of breath', 'Vomiting', 'Diarrhea', 'Skin rash',
  'Unexplained bleeding', 'Severe headache', 'Joint pain',
  'Muscle pain', 'General weakness / fatigue',
  'Altered consciousness or neurological signs', 'Jaundice',
]

// ─── Tab state ────────────────────────────────────────────────────────────────
const showTab = ref('capture')

// ─── Gender options — MALE/FEMALE only per operational requirement
// OTHER and UNKNOWN still valid DB values but not shown at primary capture
const GENDERS = [
  {
    value: 'MALE',
    label: 'Male',
    svg: `<svg viewBox="0 0 16 16" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round"><circle cx="7" cy="7" r="4"/><line x1="10" y1="4" x2="14" y2="0"/><polyline points="11 0 14 0 14 3"/></svg>`,
  },
  {
    value: 'FEMALE',
    label: 'Female',
    svg: `<svg viewBox="0 0 16 16" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round"><circle cx="8" cy="6" r="4"/><line x1="8" y1="10" x2="8" y2="15"/><line x1="5.5" y1="13" x2="10.5" y2="13"/></svg>`,
  },
]

// ─── Form state ───────────────────────────────────────────────────────────────
const defaultForm = () => ({
  gender:             null,     // 'MALE'|'FEMALE'|'OTHER'|'UNKNOWN'
  traveler_full_name: '',       // Optional at primary. Max 150 chars.
  temperature_raw:    '',       // Raw input string — parsed to float on save
  symptoms_present:   null,     // 0 or 1
})
const form       = ref(defaultForm())
const focusName  = ref(false)
const focusTemp  = ref(false)
const tempUnit   = ref('C')    // 'C' or 'F'
const fieldErrors = ref({})    // { gender, temperature, symptoms }

// ─── Temperature computed helpers ─────────────────────────────────────────────
const tempValueFloat = computed(() => {
  const v = parseFloat(form.value.temperature_raw)
  return isNaN(v) ? null : v
})

const tempInCelsius = computed(() => {
  if (tempValueFloat.value === null) return null
  return tempUnit.value === 'F'
    ? (tempValueFloat.value - 32) * 5 / 9
    : tempValueFloat.value
})

/** Level: 'normal' | 'warn' | 'danger' | '' */
const tempWarningLevel = computed(() => {
  const c = tempInCelsius.value
  if (c === null) return ''
  if (c >= 38.5) return 'danger'
  if (c >= 37.5) return 'warn'
  if (c >= 35.0 && c < 37.5) return 'normal'
  return 'warn' // below 35 also warn (hypothermia)
})

const tempWarning = computed(() => {
  const c = tempInCelsius.value
  const v = tempValueFloat.value
  if (c === null || v === null) return ''
  const t = `${v.toFixed(1)}°${tempUnit.value}`
  if (c >= 38.5) return `High fever (${t}) · Priority: CRITICAL · Referral will be CRITICAL priority`
  if (c >= 37.5) return `Fever (${t}) · Priority: HIGH · Referral will be HIGH priority if symptoms present`
  if (c >= 35.0) return `Normal range (${t}) · ${c.toFixed(1) >= 37.0 ? 'Low-grade — document' : 'Normal'}`
  return `Hypothermia risk (${t}) · Verify thermometer reading`
})

const tempWarningIcon = computed(() => {
  if (tempWarningLevel.value === 'danger')
    return `<svg viewBox="0 0 14 14" fill="none" stroke="#B71C1C" stroke-width="1.8" stroke-linecap="round"><path d="M7 1L1 12h12L7 1z"/><line x1="7" y1="5.5" x2="7" y2="8"/><circle cx="7" cy="10" r=".6" fill="#B71C1C"/></svg>`
  if (tempWarningLevel.value === 'warn')
    return `<svg viewBox="0 0 14 14" fill="none" stroke="#E65100" stroke-width="1.8" stroke-linecap="round"><path d="M7 1L1 12h12L7 1z"/><line x1="7" y1="5.5" x2="7" y2="8"/><circle cx="7" cy="10" r=".6" fill="#E65100"/></svg>`
  return `<svg viewBox="0 0 14 14" fill="none" stroke="#2E7D32" stroke-width="1.8" stroke-linecap="round"><polyline points="2 7 5.5 10.5 12 3"/></svg>`
})

/** Position of thumb on the temperature scale bar (0–100%) */
const tempScalePercent = computed(() => {
  const c = tempInCelsius.value
  if (c === null) return 50
  const MIN_C = 25, MAX_C = 45
  return Math.max(0, Math.min(100, ((c - MIN_C) / (MAX_C - MIN_C)) * 100))
})

/** Calculated notification priority based on temp+symptoms */
const referralPriority = computed(() => {
  if (form.value.symptoms_present !== 1) return 'NORMAL'
  const c = tempInCelsius.value
  if (c === null) return 'NORMAL'
  if (c >= 38.5) return 'CRITICAL'
  if (c >= 37.5) return 'HIGH'
  return 'NORMAL'
})

// ─── Capture readiness ────────────────────────────────────────────────────────
const canCapture = computed(() =>
  canScreen.value &&
  form.value.gender !== null &&
  form.value.symptoms_present !== null &&
  !fieldErrors.value.temperature
)

const captureHint = computed(() => {
  if (!canScreen.value) return 'You do not have permission to screen at this POE'
  if (form.value.gender === null) return 'Select a gender to continue'
  if (form.value.symptoms_present === null) return 'Confirm symptoms decision to continue'
  if (fieldErrors.value.temperature) return fieldErrors.value.temperature
  return ''
})

// ─── Filter state for records tab ────────────────────────────────────────────
const filterModalOpen  = ref(false)
const filterDate       = ref(new Date().toISOString().slice(0, 10))  // YYYY-MM-DD
const filterDateLabel  = ref('Today')
const recordsPage      = ref(0)       // current page index (0-based)
const RECORDS_PER_PAGE = 50           // virtual pagination — load 50 at a time
const recordsTotal     = ref(0)       // total count after filter
const filterSymptoms   = ref('ALL')   // 'ALL' | 'YES' | 'NO'
const filterSync       = ref('ALL')   // 'ALL' | 'UNSYNCED' | 'SYNCED' | 'FAILED'

// ─── Queue filter state ───────────────────────────────────────────────────────
const queueStatusFilter = ref('OPEN') // 'OPEN' | 'ALL' — default OPEN only

// ─── Stats ────────────────────────────────────────────────────────────────────
const todayCount       = ref(0)
const symptomaticCount = ref(0)
const syncedCount      = ref(0)
const pendingSyncCount = ref(0)
const openReferrals    = ref(0)

async function loadStats() {
  try {
    const today = new Date().toISOString().slice(0, 10)
    const all   = await dbGetByIndex(STORE.PRIMARY_SCREENINGS, 'poe_code', auth.value?.poe_code ?? '')

    const todayRecs = all.filter(r =>
      r.record_status !== 'VOIDED' &&
      (r.captured_at ?? r.created_at ?? '').startsWith(today)
    )
    todayCount.value       = todayRecs.length
    symptomaticCount.value = todayRecs.filter(r => r.symptoms_present === 1).length

    // ALL time (not just today) pending sync
    pendingSyncCount.value = all.filter(r => r.sync_status === SYNC.UNSYNCED).length
    syncedCount.value      = todayRecs.filter(r => r.sync_status === SYNC.SYNCED).length

    // Open referrals — from notifications store
    const notifs = await dbGetByIndex(STORE.NOTIFICATIONS, 'poe_code', auth.value?.poe_code ?? '')
    openReferrals.value = notifs.filter(n => n.status === 'OPEN').length
  } catch (e) {
    console.warn('[PrimaryScreening] loadStats error', e)
  }
}

// ─── Day counter (localStorage) ───────────────────────────────────────────────
function incrementDayCounter() {
  const today    = new Date().toISOString().slice(0, 10)
  const storedDay = localStorage.getItem(APP.DAY_COUNT_DAY_KEY)
  if (storedDay !== today) {
    localStorage.setItem(APP.DAY_COUNT_DAY_KEY, today)
    localStorage.setItem(APP.DAY_COUNT_CNT_KEY, '0')
  }
  const cnt = parseInt(localStorage.getItem(APP.DAY_COUNT_CNT_KEY) || '0') + 1
  localStorage.setItem(APP.DAY_COUNT_CNT_KEY, String(cnt))
}

// ─── Field validation ─────────────────────────────────────────────────────────
function clearFieldError(field) {
  const errs = { ...fieldErrors.value }
  delete errs[field]
  fieldErrors.value = errs
}

function validateTemp() {
  const v = parseFloat(form.value.temperature_raw)
  const errs = { ...fieldErrors.value }
  delete errs.temperature

  if (form.value.temperature_raw === '' || form.value.temperature_raw === null) {
    fieldErrors.value = errs
    return true
  }
  if (isNaN(v)) {
    errs.temperature = 'Temperature must be a valid number.'
    fieldErrors.value = errs
    return false
  }
  const minC = 25, maxC = 45, minF = 77, maxF = 113
  if (tempUnit.value === 'C' && (v < minC || v > maxC)) {
    errs.temperature = `Temperature out of clinical range (${minC}–${maxC}°C). Verify thermometer.`
    fieldErrors.value = errs
    return false
  }
  if (tempUnit.value === 'F' && (v < minF || v > maxF)) {
    errs.temperature = `Temperature out of clinical range (${minF}–${maxF}°F). Verify thermometer.`
    fieldErrors.value = errs
    return false
  }
  fieldErrors.value = errs
  return true
}

function validateForm() {
  const errs = {}
  if (!form.value.gender) {
    errs.gender = 'Gender is required — IHR Annex 1B requires all travelers to be counted.'
  }
  if (form.value.symptoms_present === null) {
    errs.symptoms = 'Symptoms decision is required — this is the IHR triage decision.'
  }
  // Temperature unit required if value provided
  const tempRaw = form.value.temperature_raw
  if (tempRaw !== '' && tempRaw !== null) {
    if (!validateTemp()) {
      // error already set in fieldErrors by validateTemp()
    }
  }
  fieldErrors.value = { ...fieldErrors.value, ...errs }
  return Object.keys(fieldErrors.value).length === 0
}

function switchTempUnit(unit) {
  if (tempUnit.value === unit) return
  const current = parseFloat(form.value.temperature_raw)
  if (!isNaN(current)) {
    if (unit === 'F') {
      form.value.temperature_raw = ((current * 9 / 5) + 32).toFixed(1)
    } else {
      form.value.temperature_raw = ((current - 32) * 5 / 9).toFixed(1)
    }
  }
  tempUnit.value = unit
  validateTemp()
}

function clearTemp() {
  form.value.temperature_raw = ''
  clearFieldError('temperature')
}

// ─── Capturing ────────────────────────────────────────────────────────────────
const capturing  = ref(false)
const lastResult = ref(null) // the just-saved primary screening record

async function captureScreening() {
  if (capturing.value || !canCapture.value) return

  // Validate
  if (!validateForm()) {
    // Scroll to first error
    const firstErr = document.querySelector('.ps-field-error')
    firstErr?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }

  // Re-read auth FRESH — never use module-level cache for record stamping
  const freshAuth = JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
  if (!freshAuth?.id || !freshAuth?.is_active) {
    const t = await toastController.create({ message: 'Session expired. Please log in again.', duration: 3000, color: 'danger' })
    void t.present()
    return
  }
  if (!freshAuth?._permissions?.can_do_primary_screening || !freshAuth?.poe_code) {
    const t = await toastController.create({ message: 'You do not have permission to screen at this POE.', duration: 3000, color: 'danger' })
    void t.present()
    return
  }

  capturing.value = true

  try {
    const now           = isoNow()
    const symptomatic   = form.value.symptoms_present === 1
    const tempValue     = form.value.temperature_raw !== '' ? parseFloat(form.value.temperature_raw) : null
    const tempUnitVal   = tempValue !== null ? tempUnit.value : null
    const travelerName  = form.value.traveler_full_name.trim() || null

    // ── Build primary screening record ──────────────────────────────────────
    const screeningUuid = genUUID()
    const screening = createRecordBase(freshAuth, {
      gender:                 form.value.gender,
      traveler_full_name:     travelerName,
      temperature_value:      tempValue,
      temperature_unit:       tempUnitVal,
      symptoms_present:       symptomatic ? 1 : 0,
      captured_at:            now,
      captured_timezone:      Intl.DateTimeFormat().resolvedOptions().timeZone,
      referral_created:       0,           // will be set to 1 atomically if symptomatic
      record_status:          'COMPLETED',
      void_reason:            null,
      deleted_at:             null,
    })
    // Dexie keyPath override — createRecordBase generates its own uuid but we
    // want a known uuid for the atomic write pair
    screening.client_uuid = screeningUuid

    if (!symptomatic) {
      // ── PATH A: ASYMPTOMATIC — single write ──────────────────────────────
      await dbPut(STORE.PRIMARY_SCREENINGS, screening)
      incrementDayCounter()
      lastResult.value = { ...screening }
      resetFormKeepStats()
      await loadStats()
      void attemptSync(screeningUuid)
      return
    }

    // ── PATH B: SYMPTOMATIC — atomic write (primary + notification) ─────────
    // Both must succeed or both roll back. Never a half-written state.

    const priority = referralPriority.value

    // Build the reason_text the secondary officer sees in their queue
    const tempSummary = tempValue !== null ? `${tempValue.toFixed(1)}°${tempUnitVal}` : 'Not measured'
    const reasonText  = [
      `Symptoms present.`,
      `Gender: ${form.value.gender}.`,
      `Temp: ${tempSummary}.`,
      `Priority: ${priority}.`,
      travelerName ? `Traveler: ${travelerName}.` : null,
      `POE: ${freshAuth.poe_code}.`,
      `District: ${freshAuth.district_code ?? '—'}.`,
      `PHEOC: ${freshAuth.pheoc_code ?? '—'}.`,
      `Officer: ${freshAuth.full_name ?? freshAuth.username ?? 'Officer'}.`,
    ].filter(Boolean).join(' ')

    const notifUuid = genUUID()
    const notification = {
      client_uuid:            notifUuid,
      server_id:              null,
      server_received_at:     null,
      idempotency_key:        null,
      reference_data_version: APP.REFERENCE_DATA_VER,
      country_code:           freshAuth.country_code  ?? null,
      province_code:          freshAuth.province_code ?? null,
      pheoc_code:             freshAuth.pheoc_code    ?? null,
      district_code:          freshAuth.district_code ?? null,
      poe_code:               freshAuth.poe_code      ?? null,
      primary_screening_id:   screeningUuid,          // client_uuid link (resolved to int after sync)
      created_by_user_id:     freshAuth.id,
      notification_type:      'SECONDARY_REFERRAL',
      status:                 'OPEN',
      priority:               priority,
      reason_code:            'PRIMARY_SYMPTOMS_DETECTED',
      reason_text:            reasonText,
      assigned_role_key:      'POE_SECONDARY',
      assigned_user_id:       null,
      opened_at:              null,
      closed_at:              null,
      device_id:              getDeviceId(),
      app_version:            APP.VERSION,
      platform:               screening.platform,
      record_version:         1,
      deleted_at:             null,
      sync_status:            SYNC.UNSYNCED,
      synced_at:              null,
      sync_attempt_count:     0,
      last_sync_error:        null,
      sync_note:              null,
      created_at:             now,
      updated_at:             now,
    }

    // Stamp referral_created BEFORE atomic write
    const screeningWithReferral = { ...screening, referral_created: 1 }

    // ── ATOMIC WRITE — both or neither ──────────────────────────────────────
    await dbAtomicWrite([
      { store: STORE.PRIMARY_SCREENINGS, record: screeningWithReferral },
      { store: STORE.NOTIFICATIONS,      record: notification },
    ])

    incrementDayCounter()
    lastResult.value = { ...screeningWithReferral, notification: { ...notification } }
    resetFormKeepStats()
    await loadStats()

    // Attempt to sync both together
    void attemptSyncPair(screeningUuid, notifUuid)

  } catch (err) {
    console.error('[PrimaryScreening] captureScreening error', err)
    const t = await toastController.create({
      message: `Save failed: ${err?.message ?? 'Unknown error'}. Record NOT saved. Try again.`,
      duration: 5000,
      color:    'danger',
    })
    void t.present()
    // Log full error for developer
    console.group('%c[PrimaryScreening] CAPTURE ERROR — FULL DETAIL', 'color:#DC3545;font-weight:700')
    console.error('Error class:', err?.constructor?.name)
    console.error('Message:', err?.message)
    console.error('Stack:', err?.stack)
    console.groupEnd()
  } finally {
    capturing.value = false
  }
}

function resetFormKeepStats() {
  form.value   = defaultForm()
  fieldErrors.value = {}
  focusName.value   = false
  focusTemp.value   = false
  // tempUnit stays — officer keeps their preferred unit across captures
}

function resetForm() {
  resetFormKeepStats()
  lastResult.value = null
}

// ─── SYNC ENGINE ─────────────────────────────────────────────────────────────
// activeSyncKeys prevents concurrent upload of the same record UUID.
// This is critical when auto-retry fires while manual sync is running.
const activeSyncKeys  = new Set()
const syncEngineRunning = ref(false)
const syncError       = ref('')
let   syncTimer       = null

async function attemptSync(uuid) {
  if (activeSyncKeys.has(uuid)) return
  activeSyncKeys.add(uuid)
  try {
    await _syncOne(STORE.PRIMARY_SCREENINGS, uuid, `${window.SERVER_URL}/primary-screenings`, 'primary')
  } finally {
    activeSyncKeys.delete(uuid)
    await loadStats()
  }
}

async function attemptSyncPair(screeningUuid, notifUuid) {
  // Both must be sent together so the server can resolve the FK
  if (activeSyncKeys.has(screeningUuid)) return
  activeSyncKeys.add(screeningUuid)
  activeSyncKeys.add(notifUuid)
  try {
    const res = await _syncOne(STORE.PRIMARY_SCREENINGS, screeningUuid, `${window.SERVER_URL}/primary-screenings`, 'primary')
    if (res?.success && res?.data) {
      // Update primary record with server id (LAW 3)
      const stored = await dbGet(STORE.PRIMARY_SCREENINGS, screeningUuid)
      if (stored) {
        await safeDbPut(STORE.PRIMARY_SCREENINGS, {
          ...stored,
          id:              res.data.id,           // LAW 3: server integer
          server_id:       res.data.id,
          sync_status:     SYNC.SYNCED,
          synced_at:       isoNow(),
          server_received_at: res.data.server_received_at ?? null,
          record_version:  (stored.record_version || 1) + 1,
          updated_at:      isoNow(),
        })
      }
      // Sync notification — no dedicated endpoint needed; server creates it atomically.
      // If server already created it via the primary endpoint, just mark as synced.
      const notifStored = await dbGet(STORE.NOTIFICATIONS, notifUuid)
      if (notifStored && res.data.notification) {
        await safeDbPut(STORE.NOTIFICATIONS, {
          ...notifStored,
          id:          res.data.notification.id,
          server_id:   res.data.notification.id,
          sync_status: SYNC.SYNCED,
          synced_at:   isoNow(),
          record_version: (notifStored.record_version || 1) + 1,
          updated_at:  isoNow(),
        })
      }
      // Update lastResult with server ids for display
      if (lastResult.value?.client_uuid === screeningUuid) {
        lastResult.value = {
          ...lastResult.value,
          id:          res.data.id,
          sync_status: SYNC.SYNCED,
          notification: res.data.notification
            ? { ...lastResult.value.notification, id: res.data.notification.id, sync_status: SYNC.SYNCED }
            : lastResult.value.notification,
        }
      }
    }
  } catch (e) {
    console.warn('[PrimaryScreening] sync pair error', e)
  } finally {
    activeSyncKeys.delete(screeningUuid)
    activeSyncKeys.delete(notifUuid)
    await loadStats()
  }
}

/**
 * Sync a single record from a store to a server endpoint.
 * Returns the parsed server response JSON or null on failure.
 * Full developer-visible error handling — no sugar coating.
 */
async function _syncOne(store, uuid, url, entityLabel) {
  const record = await dbGet(store, uuid)
  if (!record || record.sync_status === SYNC.SYNCED) return null

  // Increment attempt count BEFORE sending (crash-safe accounting)
  const working = {
    ...record,
    sync_attempt_count: (record.sync_attempt_count || 0) + 1,
    record_version:     (record.record_version     || 1) + 1,
    updated_at:         isoNow(),
  }
  await safeDbPut(store, working)

  const ctrl = new AbortController()
  const tid  = window.setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)

  let res, body
  try {
    res = await fetch(url, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body:    JSON.stringify(buildServerPayload(working)),
      signal:  ctrl.signal,
    })
    body = await res.json().catch(() => ({}))
  } catch (e) {
    const isTimeout = e?.name === 'AbortError'
    await safeDbPut(store, {
      ...working,
      sync_status:    SYNC.UNSYNCED,
      sync_note:      isTimeout ? 'Timed out — retrying.' : 'Network error — retrying.',
      last_sync_error: e?.message ?? 'Network unavailable',
      record_version: (working.record_version || 1) + 1,
      updated_at:     isoNow(),
    })
    scheduleRetry()
    return null
  } finally {
    clearTimeout(tid)
  }

  // ── Server responded ──────────────────────────────────────────────────────
  // Log full server response for developer diagnostics
  console.group(
    `%c[PrimaryScreening][SYNC ${entityLabel.toUpperCase()}] HTTP ${res.status} — ${res.ok ? 'OK' : 'ERROR'}`,
    `color:${res.ok ? '#28A745' : '#DC3545'};font-weight:700`
  )
  console.log('URL:', url)
  console.log('client_uuid:', uuid)
  console.log('success:', body?.success)
  console.log('message:', body?.message)
  if (body?.data) console.table(body.data)
  if (body?.error) console.error('server error detail:', body.error)
  if (body?.idempotent) console.log('%c↩ Idempotent re-submission — existing record returned', 'color:#17A2B8')
  console.groupEnd()

  if (res.ok && body?.success) {
    await safeDbPut(store, {
      ...working,
      id:          body.data?.id ?? null,           // LAW 3: server integer
      server_id:   body.data?.id ?? null,
      sync_status: SYNC.SYNCED,
      synced_at:   isoNow(),
      sync_note:   null,
      last_sync_error: null,
      server_received_at: body.data?.server_received_at ?? null,
      record_version: (working.record_version || 1) + 1,
      updated_at:  isoNow(),
    })
    return body
  }

  // Server error
  const retryable = res.status >= 500 || res.status === 429
  const errMsg    = body?.message || body?.error?.message || `HTTP ${res.status}`
  await safeDbPut(store, {
    ...working,
    sync_status:     retryable ? SYNC.UNSYNCED : SYNC.FAILED,
    sync_note:       retryable
      ? `Server busy (${res.status}) — retrying.`
      : `Rejected: ${errMsg}`,
    last_sync_error: errMsg,
    record_version:  (working.record_version || 1) + 1,
    updated_at:      isoNow(),
  })

  if (retryable) scheduleRetry()
  else {
    syncError.value = `[${entityLabel.toUpperCase()}] Server rejected record: ${errMsg}. Check error.* in console for full detail.`
  }
  return null
}

/** Build the payload that the server expects — LAW 2: no auth headers */
function buildServerPayload(record) {
  return {
    client_uuid:             record.client_uuid,
    reference_data_version:  record.reference_data_version,
    captured_by_user_id:     record.created_by_user_id,  // server uses this field name
    gender:                  record.gender,
    traveler_full_name:      record.traveler_full_name ?? null,
    temperature_value:       record.temperature_value ?? null,
    temperature_unit:        record.temperature_unit  ?? null,
    symptoms_present:        record.symptoms_present,
    captured_at:             record.captured_at,
    captured_timezone:       record.captured_timezone ?? null,
    device_id:               record.device_id,
    app_version:             record.app_version ?? null,
    platform:                record.platform    ?? 'ANDROID',
    record_version:          record.record_version,
    // Geographic scope is re-derived server-side from user assignment
    // but we send it for the record (server validates against assignment)
    country_code:            record.country_code,
    province_code:           record.province_code,
    pheoc_code:              record.pheoc_code,
    district_code:           record.district_code,
    poe_code:                record.poe_code,
  }
}

function scheduleRetry() {
  clearTimeout(syncTimer)
  syncTimer = window.setTimeout(async () => {
    if (!navigator.onLine) {
      scheduleRetry()
      return
    }
    await manualSync()
  }, APP.SYNC_RETRY_MS)
}

async function manualSync() {
  if (syncEngineRunning.value) return
  syncEngineRunning.value = true
  syncError.value         = ''

  try {
    const freshAuth = JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
    if (!freshAuth?.poe_code) return

    const unsynced = await dbQuery(
      STORE.PRIMARY_SCREENINGS, 'sync_status', SYNC.UNSYNCED,
      r => r.poe_code === freshAuth.poe_code
    )

    for (const rec of unsynced) {
      if (activeSyncKeys.has(rec.client_uuid)) continue
      activeSyncKeys.add(rec.client_uuid)
      try {
        const result = await _syncOne(STORE.PRIMARY_SCREENINGS, rec.client_uuid, `${window.SERVER_URL}/primary-screenings`, 'primary')
        if (result?.success && result?.data?.id && rec.referral_created === 1) {
          // Also sync linked notification
          const notifs = await dbGetByIndex(STORE.NOTIFICATIONS, 'primary_screening_id', rec.client_uuid)
          for (const n of notifs) {
            if (n.sync_status === SYNC.UNSYNCED && !activeSyncKeys.has(n.client_uuid)) {
              activeSyncKeys.add(n.client_uuid)
              try {
                // Update notification's primary_screening_id to the server integer
                // The notification is sent via the primary endpoint — server creates it.
                // If it already exists on server, mark synced based on server response.
                if (result.data.notification?.id) {
                  await safeDbPut(STORE.NOTIFICATIONS, {
                    ...n,
                    id:          result.data.notification.id,
                    server_id:   result.data.notification.id,
                    sync_status: SYNC.SYNCED,
                    synced_at:   isoNow(),
                    record_version: (n.record_version || 1) + 1,
                    updated_at:  isoNow(),
                  })
                }
              } finally {
                activeSyncKeys.delete(n.client_uuid)
              }
            }
          }
        }
      } finally {
        activeSyncKeys.delete(rec.client_uuid)
      }
    }

    await loadStats()
  } catch (e) {
    syncError.value = `Sync engine error: ${e?.message ?? 'Unknown'}. See console.`
    console.error('[PrimaryScreening] manualSync error', e)
  } finally {
    syncEngineRunning.value = false
  }
}

// ─── RECORDS LIST — paginated, filtered, handles millions of records ─────────
const records        = ref([])
const recordsLoading = ref(false)

async function loadRecords(resetPage = true) {
  recordsLoading.value = true
  if (resetPage) recordsPage.value = 0
  try {
    const freshAuth = JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
    if (!freshAuth?.poe_code) return

    // Load all records for this POE from IDB — Dexie index scan is fast even for millions
    const all = await dbGetByIndex(STORE.PRIMARY_SCREENINGS, 'poe_code', freshAuth.poe_code)

    // Apply date filter (filterDate = 'YYYY-MM-DD')
    let filtered = all.filter(r =>
      (r.captured_at ?? r.created_at ?? '').startsWith(filterDate.value)
    )

    // Apply symptoms filter
    if (filterSymptoms.value === 'YES') filtered = filtered.filter(r => r.symptoms_present === 1)
    if (filterSymptoms.value === 'NO')  filtered = filtered.filter(r => r.symptoms_present === 0)

    // Apply sync filter
    if (filterSync.value !== 'ALL') filtered = filtered.filter(r => r.sync_status === filterSync.value)

    // Sort newest first
    filtered.sort((a, b) => (b.captured_at ?? b.created_at ?? '').localeCompare(a.captured_at ?? a.created_at ?? ''))

    recordsTotal.value = filtered.length

    // Paginate — only load current page slice
    const start = recordsPage.value * RECORDS_PER_PAGE
    records.value = filtered
      .slice(start, start + RECORDS_PER_PAGE)
      .map(r => ({ ...r, id: r.id ?? r.server_id ?? null }))  // LAW 4

  } catch (e) {
    console.error('[PrimaryScreening] loadRecords error', e)
  } finally {
    recordsLoading.value = false
  }
}

function applyFilter(date, label) {
  filterDate.value      = date
  filterDateLabel.value = label
  filterModalOpen.value = false
  void loadRecords(true)
}

function prevRecordsPage() {
  if (recordsPage.value > 0) { recordsPage.value--; void loadRecords(false) }
}

function nextRecordsPage() {
  if ((recordsPage.value + 1) * RECORDS_PER_PAGE < recordsTotal.value) {
    recordsPage.value++; void loadRecords(false)
  }
}

// ─── REFERRAL QUEUE ───────────────────────────────────────────────────────────
const queueItems   = ref([])
const queueLoading = ref(false)
const queueError   = ref('')

async function loadQueue() {
  queueLoading.value = true
  queueError.value   = ''
  try {
    const freshAuth = JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
    if (!freshAuth?.poe_code) return

    // First load from IDB (instant offline data)
    const allNotifs = await dbGetByIndex(STORE.NOTIFICATIONS, 'poe_code', freshAuth.poe_code)

    // Cross-join with primary screenings for gender/temp context
    const enriched = []
    for (const n of allNotifs) {
      // Normalise id per LAW 4
      const notifNorm = { ...n, id: n.id ?? n.server_id ?? null }

      let primaryRec = null
      try {
        // Look up by client_uuid (IDB key) first, then server integer id
        primaryRec = await dbGet(STORE.PRIMARY_SCREENINGS, n.primary_screening_id)
        if (!primaryRec && n.primary_screening_id) {
          // Fallback: search by server integer id if client_uuid lookup missed
          const all = await dbGetByIndex(STORE.PRIMARY_SCREENINGS, 'poe_code', freshAuth.poe_code)
          primaryRec = all.find(r => r.id == n.primary_screening_id) ?? null
        }
      } catch { /* not found — proceed without */ }

      enriched.push({
        notification_id:          notifNorm.id,
        notification_uuid:        notifNorm.client_uuid,
        notification_status:      notifNorm.status,
        priority:                 notifNorm.priority,
        reason_code:              notifNorm.reason_code,
        reason_text:              notifNorm.reason_text,
        assigned_role_key:        notifNorm.assigned_role_key,
        assigned_user_id:         notifNorm.assigned_user_id,
        notification_created_at:  notifNorm.created_at,
        notification_opened_at:   notifNorm.opened_at,
        primary_screening_id:     notifNorm.primary_screening_id,
        primary_uuid:             primaryRec?.client_uuid ?? null,
        gender:                   primaryRec?.gender ?? null,
        temperature_value:        primaryRec?.temperature_value ?? null,
        temperature_unit:         primaryRec?.temperature_unit ?? null,
        traveler_full_name:       primaryRec?.traveler_full_name ?? null,
        symptoms_present:         primaryRec?.symptoms_present ?? 1,
        captured_at:              primaryRec?.captured_at ?? null,
        primary_record_status:    primaryRec?.record_status ?? null,
        screener_name:            null, // not stored locally
        poe_code:                 notifNorm.poe_code,
        is_voided_primary:        primaryRec?.record_status === 'VOIDED',
        _raw_notification:        notifNorm, // kept for cancel operations
      })
    }

    // Filter by status (default: OPEN only)
    const statusFiltered = queueStatusFilter.value === 'ALL'
      ? enriched
      : enriched.filter(e => e.notification_status === queueStatusFilter.value)

    // Sort: CRITICAL first, then HIGH, then NORMAL; oldest first within each
    const priorityOrder = { CRITICAL: 0, HIGH: 1, NORMAL: 2 }
    statusFiltered.sort((a, b) => {
      const pa = priorityOrder[a.priority] ?? 2
      const pb = priorityOrder[b.priority] ?? 2
      if (pa !== pb) return pa - pb
      return (a.notification_created_at ?? '').localeCompare(b.notification_created_at ?? '')
    })

    queueItems.value = statusFiltered

    // If online, also fetch from server for records created on other devices
    if (navigator.onLine && freshAuth.id) {
      try {
        const ctrl = new AbortController()
        const tid  = window.setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
        const res  = await fetch(
          `${window.SERVER_URL}/referral-queue?user_id=${freshAuth.id}&status=ALL`,
          { headers: { 'Accept': 'application/json' }, signal: ctrl.signal }
        )
        clearTimeout(tid)
        const body = await res.json().catch(() => ({}))

        console.group('%c[PrimaryScreening][QUEUE] Server response', `color:${res.ok ? '#0066CC' : '#DC3545'};font-weight:700`)
        console.log('success:', body?.success, '| total:', body?.data?.total)
        if (body?.error) console.error('error:', body.error)
        console.groupEnd()

        if (res.ok && body?.success && Array.isArray(body?.data?.items)) {
          // Write server items into IDB, then rebuild the display list in-place.
          // NEVER call loadQueue() here — that causes infinite recursion because
          // loadQueue() calls the server again, which re-enters this block forever.
          let didUpdate = false

          for (const si of body.data.items) {
            if (!si.notification_id) continue
            const local = allNotifs.find(n => n.id == si.notification_id || n.client_uuid === si.notification_uuid)
            if (local) {
              if (si.status !== local.status || local.sync_status !== SYNC.SYNCED) {
                await safeDbPut(STORE.NOTIFICATIONS, {
                  ...local,
                  id:          si.notification_id,
                  server_id:   si.notification_id,
                  status:      si.status,
                  sync_status: SYNC.SYNCED,
                  synced_at:   isoNow(),
                  record_version: (local.record_version || 1) + 1,
                  updated_at:  isoNow(),
                })
                didUpdate = true
              }
            } else {
              // Server has a notification not yet in IDB (created on another device).
              // Write it locally so it shows up.
              const freshAuth2 = JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
              await safeDbPut(STORE.NOTIFICATIONS, {
                client_uuid:            si.notification_uuid ?? `srv-${si.notification_id}`,
                id:                     si.notification_id,
                server_id:              si.notification_id,
                status:                 si.status,
                priority:               si.priority,
                reason_code:            si.reason_code,
                reason_text:            si.reason_text,
                assigned_role_key:      si.assigned_role_key,
                assigned_user_id:       si.assigned_user_id,
                notification_type:      'SECONDARY_REFERRAL',
                primary_screening_id:   si.primary_screening_id,
                created_by_user_id:     null,
                poe_code:               freshAuth2.poe_code ?? null,
                district_code:          freshAuth2.district_code ?? null,
                province_code:          freshAuth2.province_code ?? null,
                pheoc_code:             freshAuth2.pheoc_code ?? null,
                country_code:           freshAuth2.country_code ?? null,
                opened_at:              si.notification_opened_at ?? null,
                closed_at:              null,
                device_id:              'server',
                app_version:            null,
                platform:               'WEB',
                reference_data_version: APP.REFERENCE_DATA_VER,
                server_received_at:     null,
                sync_status:            SYNC.SYNCED,
                synced_at:              isoNow(),
                sync_attempt_count:     0,
                last_sync_error:        null,
                record_version:         1,
                created_at:             si.notification_created_at ?? isoNow(),
                updated_at:             isoNow(),
              })
              didUpdate = true
            }
          }

          // Always build display directly from server items — server is authoritative.
          // IDB writes above are background caching only.
          // Build enriched list directly from server response items.
          const allPS = await dbGetByIndex(STORE.PRIMARY_SCREENINGS, 'poe_code', freshAuth.poe_code)
          const serverEnriched = []
          for (const si of body.data.items) {
            // Find local primary record for context (gender/temp)
            let primaryRec2 = allPS.find(r =>
              r.client_uuid === si.primary_uuid ||
              r.id == si.primary_screening_id
            ) ?? null

            // Find the raw notification from IDB for cancel operations
            const rawNotif = allNotifs.find(n =>
              n.id == si.notification_id || n.client_uuid === si.notification_uuid
            ) ?? {
              client_uuid:  si.notification_uuid ?? `srv-${si.notification_id}`,
              id:           si.notification_id,
              status:       si.notification_status,
              priority:     si.priority,
              reason_code:  si.reason_code,
              reason_text:  si.reason_text,
              poe_code:     freshAuth.poe_code,
              primary_screening_id: si.primary_screening_id,
              record_version: 1,
            }

            serverEnriched.push({
              notification_id:         si.notification_id,
              notification_uuid:       si.notification_uuid,
              notification_status:     si.notification_status,
              priority:                si.priority,
              reason_code:             si.reason_code,
              reason_text:             si.reason_text,
              assigned_role_key:       si.assigned_role_key,
              assigned_user_id:        si.assigned_user_id,
              notification_created_at: si.notification_created_at,
              notification_opened_at:  si.notification_opened_at,
              primary_screening_id:    si.primary_screening_id,
              primary_uuid:            primaryRec2?.client_uuid ?? si.primary_uuid ?? null,
              gender:                  primaryRec2?.gender ?? si.gender ?? null,
              temperature_value:       primaryRec2?.temperature_value ?? si.temperature_value ?? null,
              temperature_unit:        primaryRec2?.temperature_unit ?? si.temperature_unit ?? null,
              traveler_full_name:      primaryRec2?.traveler_full_name ?? si.traveler_full_name ?? null,
              symptoms_present:        primaryRec2?.symptoms_present ?? 1,
              captured_at:             primaryRec2?.captured_at ?? si.captured_at ?? null,
              primary_record_status:   primaryRec2?.record_status ?? si.primary_record_status ?? 'COMPLETED',
              screener_name:           si.screener_name ?? null,
              poe_code:                freshAuth.poe_code,
              is_voided_primary:       (primaryRec2?.record_status ?? si.primary_record_status) === 'VOIDED',
              _raw_notification:       rawNotif,
            })
          }

          const sf2 = queueStatusFilter.value === 'ALL'
            ? serverEnriched
            : serverEnriched.filter(e => e.notification_status === queueStatusFilter.value)
          const po2 = { CRITICAL: 0, HIGH: 1, NORMAL: 2 }
          sf2.sort((a, b) => {
            const pa = po2[a.priority] ?? 2, pb = po2[b.priority] ?? 2
            if (pa !== pb) return pa - pb
            return (a.notification_created_at ?? '').localeCompare(b.notification_created_at ?? '')
          })
          queueItems.value = sf2
        }
      } catch (e) {
        if (e?.name !== 'AbortError') {
          queueError.value = `Queue server sync failed: ${e?.message ?? 'Network error'}. Showing local data only.`
        }
      }
    }
  } catch (e) {
    queueError.value = `Failed to load queue: ${e?.message ?? 'Unknown error'}`
    console.error('[PrimaryScreening] loadQueue error', e)
  } finally {
    queueLoading.value = false
  }
}

// ─── VOID ────────────────────────────────────────────────────────────────────
const voidModalOpen   = ref(false)
const voidReason      = ref('')
const voidError       = ref('')
const voiding         = ref(false)
const recordToVoid    = ref(null)

function canVoidRecord(rec) {
  const freshAuth = JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
  const rk = freshAuth?.role_key ?? ''
  if (['POE_ADMIN', 'NATIONAL_ADMIN'].includes(rk)) return true
  if (rec.created_by_user_id === freshAuth?.id) {
    const capturedTs = new Date(rec.captured_at ?? rec.created_at ?? 0).getTime()
    return Date.now() - capturedTs < 86400_000 // within 24 hours
  }
  return false
}

function promptVoid(rec) {
  recordToVoid.value = rec
  voidReason.value   = ''
  voidError.value    = ''
  voidModalOpen.value = true
}

async function executeVoid() {
  if (voiding.value || !recordToVoid.value) return
  if (voidReason.value.trim().length < 10) {
    voidError.value = 'Reason must be at least 10 characters.'
    return
  }

  voiding.value = true
  voidError.value = ''

  try {
    const rec     = recordToVoid.value
    const freshAuth = JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
    const now     = isoNow()

    // ── LOCAL VOID (offline-first) ─────────────────────────────────────────
    const local = await dbGet(STORE.PRIMARY_SCREENINGS, rec.client_uuid)
    if (local) {
      await safeDbPut(STORE.PRIMARY_SCREENINGS, {
        ...local,
        record_status:  'VOIDED',
        void_reason:    voidReason.value.trim(),
        sync_status:    SYNC.UNSYNCED, // needs to sync void to server
        record_version: (local.record_version || 1) + 1,
        updated_at:     now,
      })
    }

    // Auto-close OPEN linked notification (not IN_PROGRESS — secondary officer handles those)
    if (rec.referral_created === 1) {
      const notifs = await dbGetByIndex(STORE.NOTIFICATIONS, 'primary_screening_id', rec.client_uuid)
      for (const n of notifs) {
        if (n.status === 'OPEN') {
          await safeDbPut(STORE.NOTIFICATIONS, {
            ...n,
            status:         'CLOSED',
            closed_at:      now,
            reason_text:    `Primary screening voided: ${voidReason.value.trim()}`,
            sync_status:    SYNC.UNSYNCED,
            record_version: (n.record_version || 1) + 1,
            updated_at:     now,
          })
        }
        // IN_PROGRESS notifications left for secondary officer — NOT auto-closed
      }
    }

    // ── SERVER VOID (if online and record has server id) ───────────────────
    const serverId = rec.id ?? rec.server_id ?? null
    if (navigator.onLine && serverId && Number.isInteger(Number(serverId)) && Number(serverId) > 0) {
      try {
        const ctrl = new AbortController()
        const tid  = window.setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
        const res  = await fetch(`${window.SERVER_URL}/primary-screenings/${serverId}/void`, {
          method:  'PATCH',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body:    JSON.stringify({
            user_id:      freshAuth.id,
            void_reason:  voidReason.value.trim(),
          }),
          signal: ctrl.signal,
        })
        clearTimeout(tid)
        const body = await res.json().catch(() => ({}))

        console.group(`%c[PrimaryScreening][VOID] HTTP ${res.status}`, `color:${res.ok ? '#28A745' : '#DC3545'};font-weight:700`)
        console.log('success:', body?.success, '| message:', body?.message)
        if (body?.error) console.error('void error detail:', body.error)
        console.groupEnd()

        if (res.ok && body?.success) {
          // Mark local record as synced on void
          const updated = await dbGet(STORE.PRIMARY_SCREENINGS, rec.client_uuid)
          if (updated) {
            await safeDbPut(STORE.PRIMARY_SCREENINGS, {
              ...updated,
              sync_status: SYNC.SYNCED,
              synced_at:   isoNow(),
              record_version: (updated.record_version || 1) + 1,
              updated_at:  isoNow(),
            })
          }
        } else if (!res.ok) {
          // Not fatal — local void is already done. Server void will retry on next sync.
          console.warn('[PrimaryScreening] Server void failed — will retry on next sync:', body?.message)
        }
      } catch (e) {
        if (e?.name !== 'AbortError') console.warn('[PrimaryScreening] Void server call failed:', e?.message)
        // Non-fatal — local void committed, sync will pick it up later
      }
    }

    voidModalOpen.value = false
    // Clear lastResult if it was this record
    if (lastResult.value?.client_uuid === rec.client_uuid) lastResult.value = null
    await loadStats()
    await loadRecords()

    const t = await toastController.create({ message: 'Record voided.', duration: 2500, color: 'warning' })
    void t.present()
  } catch (e) {
    voidError.value = `Void failed: ${e?.message ?? 'Unknown error'}`
    console.error('[PrimaryScreening] executeVoid error', e)
  } finally {
    voiding.value = false
  }
}

// ─── CANCEL REFERRAL ─────────────────────────────────────────────────────────
// BUSINESS RULE: Cancelling a referral closes the notification ONLY.
// The primary screening record remains COMPLETED with referral_created=1.
// This is an immutable audit record — the referral was issued and then cancelled.
// Both facts are preserved. The primary record is NOT voided.
const cancelModalOpen   = ref(false)
const cancelReason      = ref('')
const cancelError       = ref('')
const cancellingActive  = ref(false)
const cancellingId      = ref(null)   // notification_id being cancelled (for UI state)
const itemToCancel      = ref(null)

function promptCancelReferral(item) {
  itemToCancel.value  = item
  cancelReason.value  = ''
  cancelError.value   = ''
  cancelModalOpen.value = true
}

async function executeCancelReferral() {
  if (cancellingActive.value || !itemToCancel.value) return
  if (cancelReason.value.trim().length < 5) {
    cancelError.value = 'Reason must be at least 5 characters.'
    return
  }

  cancellingActive.value = true
  cancelError.value      = ''
  const item             = itemToCancel.value
  cancellingId.value     = item.notification_id

  try {
    const freshAuth = JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
    const now       = isoNow()

    // ── LOCAL CANCEL — close notification, primary record UNTOUCHED ─────────
    const notif = item._raw_notification
    if (notif) {
      await safeDbPut(STORE.NOTIFICATIONS, {
        ...notif,
        status:         'CLOSED',
        closed_at:      now,
        reason_text:    `Referral cancelled by ${freshAuth.full_name ?? 'Officer'}: ${cancelReason.value.trim()}`,
        sync_status:    SYNC.UNSYNCED,
        record_version: (notif.record_version || 1) + 1,
        updated_at:     now,
      })
      // Primary record: DO NOT TOUCH. referral_created stays 1. record_status stays COMPLETED.
      // This is the business rule. Log it explicitly.
      console.log(
        '%c[PrimaryScreening][CANCEL_REFERRAL] Notification closed. Primary record UNCHANGED — referral_created=1 preserved.',
        'color:#17A2B8;font-weight:700'
      )
    }

    // ── SERVER CANCEL ────────────────────────────────────────────────────────
    const serverId = item.notification_id
    if (navigator.onLine && serverId && Number.isInteger(Number(serverId)) && Number(serverId) > 0) {
      try {
        const ctrl = new AbortController()
        const tid  = window.setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
        const res  = await fetch(`${window.SERVER_URL}/referral-queue/${serverId}/cancel`, {
          method:  'PATCH',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body:    JSON.stringify({ user_id: freshAuth.id, cancel_reason: cancelReason.value.trim() }),
          signal:  ctrl.signal,
        })
        clearTimeout(tid)
        const body = await res.json().catch(() => ({}))

        console.group(`%c[PrimaryScreening][CANCEL_REFERRAL SERVER] HTTP ${res.status}`, `color:${res.ok ? '#28A745' : '#DC3545'};font-weight:700`)
        console.log('success:', body?.success, '| message:', body?.message)
        if (body?.error) console.error('cancel error detail:', body.error)
        if (body?.data?.audit_note) console.log('audit note:', body.data.audit_note)
        if (body?.data?.primary_screening) {
          console.log('%c Primary record (server confirms UNCHANGED):', 'color:#17A2B8;font-weight:600', {
            id:               body.data.primary_screening.id,
            record_status:    body.data.primary_screening.record_status,
            referral_created: body.data.primary_screening.referral_created,
          })
        }
        console.groupEnd()

        // Handle 409 — secondary case already open
        if (res.status === 409) {
          const errDetail = body?.error
          cancelError.value = body?.message ?? 'Cannot cancel — secondary case already opened.'
          if (errDetail?.secondary_case_id) {
            cancelError.value += ` Secondary case ID: ${errDetail.secondary_case_id}. The secondary officer must close it.`
          }
          // Revert local cancel since server rejected it
          if (notif) {
            await safeDbPut(STORE.NOTIFICATIONS, {
              ...notif,
              status:         'OPEN',   // revert
              closed_at:      null,
              record_version: (notif.record_version || 1) + 1,
              updated_at:     isoNow(),
            })
          }
          return
        }

        if (res.ok && body?.success && notif) {
          await safeDbPut(STORE.NOTIFICATIONS, {
            ...notif,
            id:          serverId,
            server_id:   serverId,
            status:      'CLOSED',
            closed_at:   now,
            sync_status: SYNC.SYNCED,
            synced_at:   isoNow(),
            reason_text: `Referral cancelled: ${cancelReason.value.trim()}`,
            record_version: (notif.record_version || 1) + 1,
            updated_at:  isoNow(),
          })
        }
      } catch (e) {
        if (e?.name !== 'AbortError') {
          console.warn('[PrimaryScreening] Cancel server call failed (will sync later):', e?.message)
        }
      }
    }

    cancelModalOpen.value  = false
    await loadStats()
    await loadQueue()

    const t = await toastController.create({ message: 'Referral cancelled. Primary record preserved.', duration: 3000, color: 'warning' })
    void t.present()
  } catch (e) {
    cancelError.value = `Cancel failed: ${e?.message ?? 'Unknown error'}`
    console.error('[PrimaryScreening] executeCancelReferral error', e)
  } finally {
    cancellingActive.value = false
    cancellingId.value     = null
  }
}

// ─── UTILITY FORMATTERS ───────────────────────────────────────────────────────
function formatTime(dt) {
  if (!dt) return '—'
  try {
    return new Date(dt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  } catch { return dt }
}

function truncate(str, len) {
  if (!str) return ''
  return str.length > len ? str.slice(0, len) + '…' : str
}

function goBack() {
  router.back()
}

// ─── Date presets for filter modal ───────────────────────────────────────────
const datePresets = computed(() => {
  const fmt = (d) => d.toISOString().slice(0, 10)
  const today = new Date()
  const presets = []
  for (let i = 0; i < 7; i++) {
    const d = new Date(today)
    d.setDate(today.getDate() - i)
    const iso = fmt(d)
    presets.push({
      label: i === 0 ? 'Today' : i === 1 ? 'Yesterday' : iso,
      date:  iso,
    })
  }
  const ms = new Date(today.getFullYear(), today.getMonth(), 1)
  presets.push({ label: `Month start (${fmt(ms)})`, date: fmt(ms) })
  const ys = new Date(today.getFullYear(), 0, 1)
  presets.push({ label: `Year start (${fmt(ys)})`, date: fmt(ys) })
  return presets
})

// ─── LIFECYCLE ───────────────────────────────────────────────────────────────
let autoStatsTimer = null
let onOnline, onOffline

onMounted(() => {
  // Read auth fresh
  try {
    auth.value = JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
  } catch {
    auth.value = {}
  }

  // Network listeners
  onOnline  = () => { isOnline.value = true;  void manualSync() }
  onOffline = () => { isOnline.value = false }
  window.addEventListener('online',  onOnline)
  window.addEventListener('offline', onOffline)

  // Initial load — also load queue so badge count is accurate
  void loadStats()
  void loadQueue()
  void loadRecords()

  // Auto-refresh stats every 30 seconds
  autoStatsTimer = window.setInterval(() => {
    void loadStats()
  }, 30_000)

  // Log developer context
  console.group('%c[PrimaryScreeningView] MOUNTED', 'color:#0066CC;font-weight:700;font-size:13px')
  console.table({
    poe_code:      auth.value?.poe_code,
    district_code: auth.value?.district_code,
    role_key:      auth.value?.role_key,
    can_screen:    auth.value?._permissions?.can_do_primary_screening,
    device_id:     auth.value?.device_id,
  })
  console.log('SERVER_URL:', window.SERVER_URL)
  console.groupEnd()
})

onUnmounted(() => {
  // CRITICAL: Clear ALL timers and remove ALL event listeners.
  // Failure to do this causes memory leaks and phantom syncs.
  clearTimeout(syncTimer)
  clearInterval(autoStatsTimer)
  window.removeEventListener('online',  onOnline)
  window.removeEventListener('offline', onOffline)
})
</script>

<style scoped>
/* ════════════════════════════════════════════════════════════════════════════
   PRIMARY SCREENING VIEW — Light Material Navy Design System
   #0D47A1 Blue header · #2E7D32 Green OK · #C62828 Red symptomatic
   #E65100 Orange HIGH priority · #EEF2FF cool content background
   NO DARK MODE — light only
════════════════════════════════════════════════════════════════════════════ */

/* ── HEADER ── */
.ps-header {
  --background: transparent;
  background: linear-gradient(180deg, #0D47A1 0%, #1565C0 100%);
  position: relative;
  overflow: hidden;
}
.ps-hdr-pattern {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    radial-gradient(circle at 80% 20%, rgba(255,255,255,0.07) 0%, transparent 50%),
    radial-gradient(circle at 10% 80%, rgba(255,255,255,0.05) 0%, transparent 40%);
}

/* Top bar */
.ps-hdr-top {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px 14px; position: relative; z-index: 2;
}
.ps-hdr-left { display: flex; align-items: center; gap: 10px; }
.ps-back-btn {
  width: 36px; height: 36px; border-radius: 50%;
  background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.18);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0;
}
.ps-back-btn svg { width: 18px; height: 18px; }
.ps-eyebrow {
  font-size: 9px; font-weight: 700; letter-spacing: 2.5px;
  text-transform: uppercase; color: rgba(255,255,255,0.55); display: block;
}
.ps-page-title {
  font-size: 19px; font-weight: 800; color: #fff;
  letter-spacing: -.3px; line-height: 1.15;
}
.ps-hdr-actions { display: flex; align-items: center; gap: 7px; }
.ps-hact {
  height: 36px; border-radius: 18px;
  background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.18);
  display: flex; align-items: center; gap: 6px; padding: 0 12px;
  position: relative; cursor: pointer;
}
.ps-hact svg { width: 15px; height: 15px; }
.ps-hact-txt { font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.9); }
.ps-hact--alert { background: rgba(245,158,11,0.25); border-color: rgba(245,158,11,0.5); }
.ps-hact--syncing { opacity: 0.7; }
.ps-hbadge {
  position: absolute; top: -5px; right: -5px;
  background: #F59E0B; color: #000;
  font-size: 9px; font-weight: 900;
  min-width: 16px; height: 16px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  padding: 0 3px; border: 2px solid #1565C0;
}
.ps-hbadge--warn { background: #F59E0B; }

/* Session stats strip */
.ps-session-strip {
  display: grid; grid-template-columns: repeat(4,1fr);
  margin: 0 16px 14px;
  background: rgba(255,255,255,0.1);
  border-radius: 14px; border: 1px solid rgba(255,255,255,0.15);
  overflow: hidden; position: relative; z-index: 2;
}
.ps-ss-cell {
  padding: 9px 4px 8px; text-align: center; position: relative;
}
.ps-ss-cell:not(:last-child)::after {
  content: ''; position: absolute; right: 0; top: 20%; height: 60%; width: 1px;
  background: rgba(255,255,255,0.15);
}
.ps-ss-n {
  display: block; font-size: 22px; font-weight: 800; color: #fff;
  line-height: 1; letter-spacing: -.5px;
}
.ps-ss-l {
  display: block; font-size: 8.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .8px;
  color: rgba(255,255,255,0.5); margin-top: 2px;
}
.ps-ss-n--symptom { color: #FFB74D; }
.ps-ss-n--ok      { color: #81C784; }
.ps-ss-n--warn    { color: #FFF176; }

/* POE context bar */
.ps-poe-bar {
  display: flex; align-items: center; gap: 8px;
  padding: 9px 16px 10px; position: relative; z-index: 2;
}
.ps-poe-ic {
  width: 28px; height: 28px; border-radius: 8px;
  background: rgba(255,255,255,0.12);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ps-poe-ic svg { width: 14px; height: 14px; }
.ps-poe-info { flex: 1; }
.ps-poe-title { font-size: 12.5px; font-weight: 700; color: #fff; }
.ps-poe-sub   { font-size: 10px; color: rgba(255,255,255,0.55); margin-top: 1px; }
.ps-conn-pill {
  display: flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: 20px; border: 1px solid;
}
.ps-conn--online  { background: rgba(129,199,132,0.15); border-color: rgba(129,199,132,0.3); }
.ps-conn--offline { background: rgba(158,158,158,0.15); border-color: rgba(158,158,158,0.3); }
.ps-cp-dot { width: 6px; height: 6px; border-radius: 50%; }
.ps-cp-dot--on  { background: #81C784; animation: ps-cpulse 2s infinite; }
.ps-cp-dot--off { background: #9E9E9E; }
@keyframes ps-cpulse { 0%,100%{opacity:1} 50%{opacity:.4} }
.ps-cp-txt { font-size: 10px; font-weight: 700; letter-spacing: .3px; color: #81C784; }
.ps-conn--offline .ps-cp-txt { color: #9E9E9E; }

/* Tabs */
.ps-tabs {
  display: flex; border-top: 1px solid rgba(255,255,255,0.15);
  position: relative; z-index: 2;
}
.ps-tab {
  flex: 1; height: 40px; border: none; background: transparent;
  font-size: 12px; font-weight: 700; color: rgba(255,255,255,0.6);
  letter-spacing: .3px; cursor: pointer; position: relative;
  display: flex; align-items: center; justify-content: center; gap: 5px;
}
.ps-tab--active { color: #fff; }
.ps-tab--active::after {
  content: ''; position: absolute; bottom: 0; left: 15%; right: 15%;
  height: 2px; background: #fff; border-radius: 2px 2px 0 0;
}
.ps-tab-badge {
  background: #F59E0B; color: #000;
  font-size: 9px; font-weight: 900;
  min-width: 16px; height: 16px; border-radius: 8px;
  display: inline-flex; align-items: center; justify-content: center;
  padding: 0 3px;
}

/* ── CONTENT AREA ── */
.ps-content { --background: #EEF2FF; }

/* ── PERMISSION GUARD ── */
.ps-guard-banner {
  margin: 14px; border-radius: 14px;
  background: linear-gradient(135deg, #FFEBEE, #FFCDD2);
  border: 1px solid #FFCDD2;
  padding: 14px; display: flex; align-items: flex-start; gap: 12px;
}
.ps-guard-icon {
  width: 36px; height: 36px; border-radius: 10px; background: #C62828;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ps-guard-icon svg { width: 18px; height: 18px; }
.ps-guard-title { font-size: 13px; font-weight: 800; color: #B71C1C; margin-bottom: 2px; }
.ps-guard-sub   { font-size: 11px; color: #C62828; }

/* ── AUTH STRIP ── */
.ps-auth-strip {
  margin: 14px 14px 0;
  background: linear-gradient(135deg, #E3F2FD, #EDE7F6);
  border: 1px solid #BBDEFB; border-radius: 16px;
  padding: 11px 14px; display: flex; align-items: center; gap: 10px;
}
.ps-auth-icon {
  width: 36px; height: 36px; border-radius: 12px; background: #1565C0;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ps-auth-icon svg { width: 18px; height: 18px; }
.ps-auth-info { flex: 1; }
.ps-auth-title { font-size: 12px; font-weight: 700; color: #0D1B3E; }
.ps-auth-sub   { font-size: 10.5px; color: #546E7A; margin-top: 1px; }
.ps-auth-check {
  width: 28px; height: 28px; border-radius: 50%;
  background: #E8F5E9; border: 2px solid #A5D6A7;
  display: flex; align-items: center; justify-content: center;
}
.ps-auth-check svg { width: 13px; height: 13px; }

/* ── SECTION HEADERS ── */
.ps-sec-hdr {
  display: flex; align-items: center; gap: 8px;
  padding: 16px 14px 8px;
}
.ps-sec-num {
  width: 22px; height: 22px; border-radius: 50%; background: #1565C0;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 800; color: #fff; flex-shrink: 0;
}
.ps-sec-num--opt  { background: #78909C; }
.ps-sec-num--crit { background: #C62828; }
.ps-sec-title { font-size: 14px; font-weight: 700; color: #0D1B3E; letter-spacing: -.1px; }
.ps-sec-title--crit { color: #B71C1C; }
.ps-sec-badge {
  font-size: 10px; font-weight: 700; padding: 2px 7px;
  border-radius: 6px; margin-left: auto; border: 1px solid;
}
.ps-sec-badge--req  { color: #E53935; background: #FFEBEE; border-color: #FFCDD2; }
.ps-sec-badge--opt  { color: #78909C; background: #ECEFF1; border-color: #CFD8DC; }
.ps-sec-badge--crit { color: #B71C1C; background: #FFEBEE; border-color: #FFCDD2; }

/* ── GENDER BUTTONS ── */
.ps-gender-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 8px; margin: 0 14px;
}
.ps-gender-btn {
  height: 72px; border-radius: 18px; border: 2px solid #E0E0E0;
  background: #FAFAFA;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 5px; cursor: pointer; position: relative; overflow: hidden;
  transition: all .15s; -webkit-tap-highlight-color: transparent;
}
.ps-gender-btn:active { transform: scale(.97); }
.ps-gb-icon {
  width: 26px; height: 26px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
}
.ps-gb-icon :deep(svg) { width: 16px; height: 16px; }
.ps-gb-lbl { font-size: 12px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; }
.ps-gb-check { position: absolute; top: 6px; right: 9px; font-size: 13px; font-weight: 900; }

/* Male */
.ps-gb--male                 { background: #FAFAFA; border-color: #E0E0E0; }
.ps-gb--male .ps-gb-icon     { background: #E3F2FD; }
.ps-gb--male .ps-gb-lbl      { color: #1565C0; }
.ps-gb--male.ps-gb--selected { background: linear-gradient(145deg,#E3F2FD,#BBDEFB); border-color: #1565C0; box-shadow: 0 0 0 3px rgba(21,101,192,0.15); }
.ps-gb--male.ps-gb--selected .ps-gb-icon { background: #1565C0; }
.ps-gb--male.ps-gb--selected .ps-gb-check { color: #1565C0; }

/* Female */
.ps-gb--female                 { background: #FAFAFA; border-color: #E0E0E0; }
.ps-gb--female .ps-gb-icon     { background: #F3E5F5; }
.ps-gb--female .ps-gb-lbl      { color: #6A1B9A; }
.ps-gb--female.ps-gb--selected { background: linear-gradient(145deg,#F3E5F5,#E1BEE7); border-color: #7B1FA2; box-shadow: 0 0 0 3px rgba(123,31,162,0.12); }
.ps-gb--female.ps-gb--selected .ps-gb-icon { background: #7B1FA2; }
.ps-gb--female.ps-gb--selected .ps-gb-check { color: #7B1FA2; }

/* Other */
.ps-gb--other                 { background: #FAFAFA; border-color: #E0E0E0; }
.ps-gb--other .ps-gb-icon     { background: #FFF3E0; }
.ps-gb--other .ps-gb-lbl      { color: #BF360C; }
.ps-gb--other.ps-gb--selected { background: linear-gradient(145deg,#FFF3E0,#FFE0B2); border-color: #E65100; box-shadow: 0 0 0 3px rgba(230,81,0,0.12); }
.ps-gb--other.ps-gb--selected .ps-gb-icon { background: #E65100; }
.ps-gb--other.ps-gb--selected .ps-gb-check { color: #E65100; }

/* Unknown */
.ps-gb--unknown                 { background: #FAFAFA; border-color: #E0E0E0; }
.ps-gb--unknown .ps-gb-icon     { background: #ECEFF1; }
.ps-gb--unknown .ps-gb-lbl      { color: #546E7A; }
.ps-gb--unknown.ps-gb--selected { background: linear-gradient(145deg,#ECEFF1,#CFD8DC); border-color: #546E7A; box-shadow: 0 0 0 3px rgba(84,110,122,0.12); }
.ps-gb--unknown.ps-gb--selected .ps-gb-icon { background: #546E7A; }
.ps-gb--unknown.ps-gb--selected .ps-gb-check { color: #546E7A; }

/* IHR note */
.ps-ihr-note {
  margin: 7px 14px 0;
  background: #EDE7F6; border: 1px solid #CE93D8; border-radius: 10px;
  padding: 6px 10px; display: flex; align-items: center; gap: 6px;
}
.ps-ihr-note svg { width: 12px; height: 12px; flex-shrink: 0; }
.ps-ihr-txt { font-size: 10px; font-weight: 500; color: #4A148C; }

/* Field error */
.ps-field-error {
  display: flex; align-items: center; gap: 6px;
  margin: 5px 14px 0;
  font-size: 11px; font-weight: 600; color: #C62828;
}
.ps-field-error svg { width: 12px; height: 12px; flex-shrink: 0; }

/* ── NAME INPUT ── */
.ps-input-row { margin: 0 14px; }
.ps-input-wrap {
  background: #fff; border: 1.5px solid #CFD8E8;
  border-radius: 14px; display: flex; align-items: center;
  gap: 10px; padding: 0 14px; height: 52px;
  transition: border-color .2s;
}
.ps-input-wrap--active { border-color: #1565C0; box-shadow: 0 0 0 3px rgba(21,101,192,0.08); }
.ps-input-wrap svg { width: 17px; height: 17px; flex-shrink: 0; }
.ps-input {
  flex: 1; border: none; outline: none; background: transparent;
  font-size: 14.5px; color: #0D1B3E; font-weight: 400;
}
.ps-input::placeholder { color: #90A4AE; }
.ps-input-count { font-size: 11px; font-weight: 600; color: #B0BEC5; }

/* ── TEMPERATURE ── */
.ps-temp-row { display: grid; grid-template-columns: 1fr auto auto; gap: 8px; margin: 0 14px; }
.ps-temp-input {
  background: #fff; border: 1.5px solid #BBDEFB;
  border-radius: 14px; display: flex; align-items: center;
  gap: 10px; padding: 0 14px; height: 52px;
  transition: border-color .2s, box-shadow .2s;
}
.ps-temp-input--active { border-color: #1565C0; box-shadow: 0 0 0 3px rgba(21,101,192,0.08); }
.ps-temp-input--warn   { border-color: #E65100; }
.ps-temp-input--danger { border-color: #C62828; }
.ps-temp-input svg { width: 17px; height: 17px; flex-shrink: 0; }
.ps-temp-val {
  flex: 1; border: none; outline: none; background: transparent;
  font-size: 24px; font-weight: 800; color: #0D47A1;
  width: 100%;
}
.ps-temp-val::placeholder { color: #B0BEC5; font-size: 20px; }
/* Remove number input spinner */
.ps-temp-val::-webkit-inner-spin-button,
.ps-temp-val::-webkit-outer-spin-button { -webkit-appearance: none; }
.ps-temp-val { -moz-appearance: textfield; }

.ps-temp-unit { display: flex; flex-direction: column; gap: 4px; }
.ps-tut-btn {
  width: 46px; height: 24px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 11.5px; font-weight: 800; letter-spacing: .3px; cursor: pointer;
  border: none;
}
.ps-tut--active   { background: #1565C0; color: #fff; }
.ps-tut--inactive { background: #E3F2FD; color: #90A4AE; border: 1px solid #BBDEFB; }

.ps-temp-clear {
  width: 52px; height: 52px; border-radius: 14px;
  background: #ECEFF1; border: 1.5px solid #CFD8DC;
  display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.ps-temp-clear svg { width: 14px; height: 14px; }

/* Temp warning */
.ps-temp-warning {
  margin: 6px 14px 0; border-radius: 10px; padding: 7px 11px;
  display: flex; align-items: center; gap: 7px;
}
.ps-tw--normal { background: #E8F5E9; border: 1px solid #A5D6A7; }
.ps-tw--warn   { background: #FFF8E1; border: 1px solid #FFE082; }
.ps-tw--danger { background: #FFEBEE; border: 1px solid #FFCDD2; }
.ps-tw-icon { width: 14px; height: 14px; flex-shrink: 0; }
.ps-tw-icon :deep(svg) { width: 14px; height: 14px; }
.ps-tw-txt { font-size: 11px; font-weight: 600; }
.ps-tw--normal .ps-tw-txt { color: #1B5E20; }
.ps-tw--warn   .ps-tw-txt { color: #E65100; }
.ps-tw--danger .ps-tw-txt { color: #B71C1C; }

/* Temp scale */
.ps-temp-scale {
  margin: 7px 14px 0;
  background: #fff; border: 1px solid #E3EAF8; border-radius: 12px;
  padding: 10px 12px;
}
.ps-ts-label { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #90A4AE; margin-bottom: 7px; }
.ps-ts-track {
  height: 8px; border-radius: 4px;
  background: linear-gradient(90deg, #90CAF9 0%, #42A5F5 25%, #1E88E5 40%, #43A047 50%, #FFA726 70%, #EF5350 85%, #B71C1C 100%);
  position: relative; overflow: visible;
}
.ps-ts-thumb {
  position: absolute; top: 50%; transform: translate(-50%, -50%);
  width: 14px; height: 14px; border-radius: 50%;
  background: #1565C0; border: 2.5px solid #fff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.25);
  transition: left .2s;
}
.ps-ts-labels { display: flex; justify-content: space-between; margin-top: 5px; }
.ps-ts-lbl { font-size: 8.5px; color: #B0BEC5; font-weight: 600; }

/* Divider */
.ps-divider { height: 1px; background: #E3EAF8; margin: 12px 14px 0; }

/* ── SYMPTOMS SECTION ── */
.ps-symptom-section { margin: 0 14px; }
.ps-symptom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.ps-sym-btn {
  border-radius: 22px; padding: 20px 12px 18px;
  display: flex; flex-direction: column; align-items: center;
  gap: 10px; border: 2px solid; cursor: pointer;
  position: relative; overflow: hidden;
  -webkit-tap-highlight-color: transparent;
  transition: all .15s;
}
.ps-sym-btn:active { transform: scale(.97); }
.ps-sym-btn::before {
  content: ''; position: absolute; inset: 0; border-radius: 22px;
  background: linear-gradient(145deg, rgba(255,255,255,0.3) 0%, transparent 60%);
  pointer-events: none;
}
.ps-sym-icon {
  width: 52px; height: 52px; border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
}
.ps-sym-icon svg { width: 28px; height: 28px; }
.ps-sym-lbl  { font-size: 16px; font-weight: 800; letter-spacing: -.2px; }
.ps-sym-sub  { font-size: 10px; font-weight: 500; text-align: center; line-height: 1.3; letter-spacing: .1px; }
.ps-sym-check { position: absolute; top: 10px; right: 12px; font-size: 16px; font-weight: 900; color: #2E7D32; }

/* NO — not selected */
.ps-sym--no-unsel { background: #FAFAFA; border-color: #E0E0E0; }
.ps-sym--no-unsel .ps-sym-icon { background: #ECEFF1; }
/* NO — selected */
.ps-sym--no-sel { background: linear-gradient(145deg,#E8F5E9,#C8E6C9); border-color: #2E7D32; box-shadow: 0 4px 16px rgba(46,125,50,0.2); }
.ps-sym--no-sel .ps-sym-icon--ok { background: #2E7D32; }
/* YES — not selected */
.ps-sym--yes-unsel { background: #FAFAFA; border-color: #E0E0E0; }
.ps-sym--yes-unsel .ps-sym-icon { background: #ECEFF1; }
/* YES — selected */
.ps-sym--yes-sel { background: linear-gradient(145deg,#FFEBEE,#FFCDD2); border-color: #C62828; box-shadow: 0 4px 16px rgba(198,40,40,0.2); }
.ps-sym--yes-sel .ps-sym-icon--red { background: #C62828; }
/* Dim icons for unselected */
.ps-sym-icon--ok-dim  { background: #E8F5E9; }
.ps-sym-icon--red-dim { background: #FFEBEE; }

/* IHR symptom note */
.ps-sym-ihr-note {
  margin-top: 8px;
  background: #FFF8E1; border: 1px solid #FFE082; border-radius: 12px;
  padding: 9px 12px; display: flex; align-items: flex-start; gap: 8px;
}
.ps-sin-icon {
  width: 28px; height: 28px; border-radius: 8px;
  background: #FFF3E0; border: 1px solid #FFCC02;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;
}
.ps-sin-icon svg { width: 14px; height: 14px; }
.ps-sin-title { font-size: 11.5px; font-weight: 700; color: #E65100; }
.ps-sin-sub   { font-size: 10px; color: #F57C00; margin-top: 2px; line-height: 1.4; }

/* Referral preview */
.ps-referral-preview {
  margin-top: 8px; background: #fff;
  border: 1.5px solid #FFCDD2; border-radius: 16px; overflow: hidden;
}
.ps-rp-header {
  padding: 11px 14px; display: flex; align-items: center; gap: 8px;
}
.ps-rp-header--normal   { background: linear-gradient(135deg,#1565C0,#1976D2); }
.ps-rp-header--high     { background: linear-gradient(135deg,#E65100,#F57C00); }
.ps-rp-header--critical { background: linear-gradient(135deg,#C62828,#D32F2F); }
.ps-rp-header svg { width: 16px; height: 16px; }
.ps-rp-h-title { font-size: 12px; font-weight: 800; color: #fff; letter-spacing: .2px; }
.ps-rp-h-badge {
  margin-left: auto; padding: 3px 9px; border-radius: 6px;
  background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);
  font-size: 9px; font-weight: 800; color: #fff; letter-spacing: .5px; text-transform: uppercase;
}
.ps-rp-badge--normal   { background: rgba(255,255,255,0.15); }
.ps-rp-badge--high     { background: rgba(255,213,79,0.3); color: #FFF9C4; }
.ps-rp-badge--critical { background: rgba(255,205,210,0.3); color: #FFCDD2; }
.ps-rp-body { padding: 11px 14px; }
.ps-rp-row  { display: flex; align-items: center; justify-content: space-between; margin-bottom: 7px; }
.ps-rp-row:last-child { margin-bottom: 0; }
.ps-rp-k { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #90A4AE; }
.ps-rp-v { font-size: 12px; font-weight: 700; color: #0D1B3E; }
.ps-rp-v--crit { color: #B71C1C; }
.ps-rp-v--high { color: #E65100; }

/* ── CAPTURE BUTTON ── */
.ps-capture-section { margin: 12px 14px 0; }
.ps-capture-btn {
  width: 100%; height: 64px;
  background: linear-gradient(135deg, #0D47A1 0%, #1565C0 40%, #1976D2 100%);
  border: none; border-radius: 20px;
  display: flex; align-items: center; justify-content: center; gap: 12px;
  cursor: pointer; position: relative; overflow: hidden;
  box-shadow: 0 8px 24px rgba(13,71,161,0.35), 0 3px 8px rgba(0,0,0,0.15);
  transition: transform .15s, box-shadow .15s;
  -webkit-tap-highlight-color: transparent;
}
.ps-capture-btn::before {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(180deg, rgba(255,255,255,0.12) 0%, transparent 60%);
}
.ps-capture-btn:not(.ps-capture-btn--disabled):active { transform: scale(.98); }
.ps-capture-btn--symptomatic {
  background: linear-gradient(135deg, #C62828 0%, #D32F2F 50%, #E53935 100%);
  box-shadow: 0 8px 24px rgba(198,40,40,0.4), 0 3px 8px rgba(0,0,0,0.15);
}
.ps-capture-btn--disabled { opacity: .5; cursor: not-allowed; }
.ps-capture-btn--loading  { opacity: .85; cursor: wait; }
.ps-cb-icon {
  width: 36px; height: 36px; border-radius: 12px;
  background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  position: relative; z-index: 1;
}
.ps-cb-icon svg { width: 20px; height: 20px; }
.ps-cb-text { position: relative; z-index: 1; text-align: left; }
.ps-cb-main {
  font-size: 17px; font-weight: 800; color: #fff;
  letter-spacing: .2px; display: block;
}
.ps-cb-sub {
  font-size: 10px; font-weight: 500; color: rgba(255,255,255,0.65);
  display: block; margin-top: 1px; letter-spacing: .5px; text-transform: uppercase;
}
.ps-cb-shortcut {
  margin-left: auto; position: relative; z-index: 1;
  background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2);
  border-radius: 8px; padding: 4px 8px;
  font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.7);
}
.ps-capture-hint {
  text-align: center; margin-top: 7px;
  font-size: 11px; font-weight: 600; color: #90A4AE;
}

/* ── SUCCESS TOAST ── */
.ps-success-area { margin: 10px 14px 0; }
.ps-success-toast {
  border-radius: 16px; padding: 13px 14px;
  display: flex; align-items: center; gap: 10px;
  border: 1.5px solid;
}
.ps-success-toast--ok      { background: linear-gradient(135deg,#E8F5E9,#C8E6C9); border-color: #81C784; }
.ps-success-toast--referral { background: linear-gradient(135deg,#FFF8E1,#FFE082); border-color: #FFB74D; }
.ps-st-icon {
  width: 36px; height: 36px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ps-st-icon svg    { width: 20px; height: 20px; }
.ps-st-icon--ok    { background: #2E7D32; }
.ps-st-icon--ref   { background: #E65100; }
.ps-st-info { flex: 1; }
.ps-st-title { font-size: 13px; font-weight: 800; color: #1B5E20; }
.ps-success-toast--referral .ps-st-title { color: #E65100; }
.ps-st-sub   { font-size: 10.5px; color: #388E3C; margin-top: 2px; }
.ps-success-toast--referral .ps-st-sub { color: #F57C00; }
.ps-st-sync  { font-weight: 800; }
.ps-st-counter {
  margin-left: auto; text-align: center;
  background: rgba(255,255,255,0.6); border-radius: 10px; padding: 5px 10px;
}
.ps-st-count-n { font-size: 22px; font-weight: 800; color: #1B5E20; display: block; line-height: 1; }
.ps-success-toast--referral .ps-st-count-n { color: #E65100; }
.ps-st-count-l { font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #388E3C; }
.ps-success-toast--referral .ps-st-count-l { color: #F57C00; }

/* Post-capture action row */
.ps-void-row { display: flex; gap: 8px; margin: 8px 0 0; }
.ps-void-btn {
  flex: 1; height: 40px; border-radius: 12px;
  background: #FAFAFA; border: 1.5px solid #ECEFF1;
  display: flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer;
}
.ps-void-btn svg { width: 14px; height: 14px; }
.ps-void-btn-txt { font-size: 12px; font-weight: 700; color: #546E7A; }
.ps-next-btn {
  flex: 1; height: 40px; border-radius: 12px;
  background: #E3F2FD; border: 1.5px solid #BBDEFB;
  display: flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer;
}
.ps-next-btn svg { width: 14px; height: 14px; }
.ps-next-btn-txt { font-size: 12px; font-weight: 700; color: #1565C0; }

/* Sync error */
.ps-sync-error {
  margin: 10px 14px 0;
  background: #FFEBEE; border: 1px solid #FFCDD2; border-radius: 12px;
  padding: 10px 14px; display: flex; align-items: flex-start; gap: 10px;
}
.ps-sync-error svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }
.ps-sync-error-title { font-size: 12px; font-weight: 700; color: #B71C1C; }
.ps-sync-error-sub   { font-size: 10.5px; color: #C62828; margin-top: 2px; line-height: 1.4; }
.ps-sync-error-dismiss {
  margin-left: auto; background: none; border: none;
  font-size: 14px; color: #90A4AE; cursor: pointer; flex-shrink: 0;
}

/* ── TAB TOOLBAR ── */
.ps-tab-toolbar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 14px 6px;
}
.ps-tab-toolbar-title { font-size: 12px; font-weight: 700; color: #546E7A; }
.ps-refresh-btn {
  width: 32px; height: 32px; border-radius: 10px;
  background: #E3F2FD; border: 1px solid #BBDEFB;
  display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.ps-refresh-btn svg { width: 14px; height: 14px; }
.ps-refresh-btn:disabled { opacity: .5; cursor: not-allowed; }

/* Loading */
.ps-loading {
  display: flex; flex-direction: column; align-items: center;
  gap: 12px; padding: 40px 20px; color: #90A4AE; font-size: 13px;
}
.ps-loading-dots { display: flex; gap: 6px; }
.ps-loading-dots div {
  width: 8px; height: 8px; border-radius: 50%; background: #1565C0;
  animation: ps-ldot 1.2s ease-in-out infinite;
}
.ps-loading-dots div:nth-child(2) { animation-delay: .2s; }
.ps-loading-dots div:nth-child(3) { animation-delay: .4s; }
@keyframes ps-ldot { 0%,80%,100%{transform:scale(.5);opacity:.3} 40%{transform:scale(1);opacity:1} }

/* Empty state */
.ps-empty {
  display: flex; flex-direction: column; align-items: center;
  gap: 8px; padding: 40px 20px; text-align: center;
}
.ps-empty svg { width: 40px; height: 40px; }
.ps-empty-title { font-size: 14px; font-weight: 700; color: #546E7A; }
.ps-empty-sub   { font-size: 12px; color: #90A4AE; }

/* ── RECORDS LIST ── */
.ps-records-list { padding: 0 14px; display: flex; flex-direction: column; gap: 8px; margin-top: 4px; }
.ps-record-card {
  background: #fff; border-radius: 14px;
  border: 1.5px solid #E3EAF8;
  padding: 11px 12px 11px 12px;
  display: flex; align-items: center; gap: 10px;
  transition: border-color .15s;
}
.ps-record-card--symptomatic { border-color: #FFCDD2; background: #FFFDE7; }
.ps-record-card--voided      { opacity: .6; background: #FAFAFA; }
.ps-rc-left { flex-shrink: 0; }
.ps-rc-avatar {
  width: 36px; height: 36px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
}
.ps-rc-avatar svg { width: 18px; height: 18px; }
.ps-rc-avatar--male    { background: #E3F2FD; color: #1565C0; }
.ps-rc-avatar--female  { background: #F3E5F5; color: #7B1FA2; }
.ps-rc-avatar--other   { background: #FFF3E0; color: #E65100; }
.ps-rc-avatar--unknown { background: #ECEFF1; color: #546E7A; }
.ps-rc-body { flex: 1; min-width: 0; }
.ps-rc-row1 { display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px; }
.ps-rc-name { font-size: 13px; font-weight: 700; color: #0D1B3E; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ps-rc-time { font-size: 11px; color: #90A4AE; flex-shrink: 0; margin-left: 8px; }
.ps-rc-row2 { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; margin-bottom: 3px; }
.ps-rc-row3 { display: flex; align-items: center; gap: 6px; }
.ps-rc-temp { font-size: 11px; font-weight: 700; color: #1565C0; }
.ps-rc-pill {
  font-size: 9.5px; font-weight: 800; padding: 2px 7px;
  border-radius: 6px; border: 1px solid;
}
.ps-rc-pill--ok   { background: #E8F5E9; color: #2E7D32; border-color: #A5D6A7; }
.ps-rc-pill--sym  { background: #FFEBEE; color: #C62828; border-color: #FFCDD2; }
.ps-rc-pill--ref  { background: #E3F2FD; color: #1565C0; border-color: #BBDEFB; }
.ps-rc-pill--void { background: #ECEFF1; color: #546E7A; border-color: #CFD8DC; }
.ps-rc-sync {
  font-size: 9.5px; font-weight: 700; padding: 2px 7px;
  border-radius: 6px; border: 1px solid;
}
.ps-rc-sync--unsynced { background: #FFF8E1; color: #E65100; border-color: #FFE082; }
.ps-rc-sync--synced   { background: #E8F5E9; color: #2E7D32; border-color: #A5D6A7; }
.ps-rc-sync--failed   { background: #FFEBEE; color: #C62828; border-color: #FFCDD2; }
.ps-rc-err-hint { font-size: 9.5px; color: #E65100; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 160px; }
.ps-rc-void-btn {
  flex-shrink: 0; width: 32px; height: 32px;
  background: #FAFAFA; border: 1px solid #ECEFF1; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.ps-rc-void-btn svg { width: 14px; height: 14px; }

/* ── QUEUE LIST ── */
.ps-queue-list { padding: 0 14px; display: flex; flex-direction: column; gap: 10px; margin-top: 4px; }
.ps-queue-card {
  background: #fff; border-radius: 16px;
  border: 1.5px solid #E3EAF8;
  overflow: hidden; display: flex;
}
.ps-queue-card--critical { border-color: #FFCDD2; }
.ps-queue-card--high     { border-color: #FFE082; }
.ps-queue-card--normal   { border-color: #BBDEFB; }
.ps-qc-stripe { width: 5px; flex-shrink: 0; }
.ps-qc-stripe--critical { background: #C62828; }
.ps-qc-stripe--high     { background: #E65100; }
.ps-qc-stripe--normal   { background: #1565C0; }
.ps-qc-body { flex: 1; padding: 11px 12px; }
.ps-qc-row1 { display: flex; align-items: center; gap: 7px; margin-bottom: 5px; }
.ps-qc-row2 { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap; }
.ps-qc-row3 { display: flex; align-items: center; gap: 10px; }
.ps-qc-priority-pill {
  font-size: 9.5px; font-weight: 800; padding: 3px 9px;
  border-radius: 7px; border: 1px solid; text-transform: uppercase; letter-spacing: .5px;
}
.ps-qcp--critical { background: #FFEBEE; color: #C62828; border-color: #FFCDD2; }
.ps-qcp--high     { background: #FFF3E0; color: #E65100; border-color: #FFCC80; }
.ps-qcp--normal   { background: #E3F2FD; color: #1565C0; border-color: #BBDEFB; }
.ps-qc-time   { font-size: 11px; color: #90A4AE; margin-left: auto; }
.ps-qc-gender { font-size: 12px; font-weight: 700; color: #0D1B3E; }
.ps-qc-temp   { font-size: 12px; font-weight: 700; color: #C62828; }
.ps-qc-name   { font-size: 12px; color: #546E7A; }
.ps-qc-meta   { font-size: 10.5px; color: #90A4AE; }
.ps-qc-reason {
  margin-top: 6px; font-size: 10.5px; color: #546E7A;
  background: #F5F9FF; border-radius: 8px; padding: 6px 9px;
  border: 1px solid #E3EAF8; line-height: 1.4;
}
.ps-qc-cancelled-note {
  margin-top: 6px; font-size: 10px; color: #78909C;
  display: flex; align-items: center; gap: 5px;
}
.ps-qc-cancelled-note svg { width: 12px; height: 12px; flex-shrink: 0; }
.ps-qc-actions { padding: 11px 12px 11px 0; display: flex; align-items: center; }
.ps-qc-cancel-btn {
  font-size: 11px; font-weight: 700; color: #C62828;
  background: #FFEBEE; border: 1.5px solid #FFCDD2; border-radius: 10px;
  padding: 6px 12px; cursor: pointer; white-space: nowrap;
}
.ps-qc-cancel-btn:disabled { opacity: .5; cursor: not-allowed; }

/* ── VOID / CANCEL MODALS ── */
.ps-void-modal {
  --width: 92%; --height: auto; --border-radius: 20px;
  --box-shadow: 0 16px 48px rgba(0,0,0,0.2);
}
.ps-vm-content {
  padding: 20px; background: #fff; border-radius: 20px;
}
.ps-vm-header {
  display: flex; align-items: center; gap: 10px; margin-bottom: 12px;
}
.ps-vm-header svg { width: 18px; height: 18px; flex-shrink: 0; }
.ps-vm-title { font-size: 15px; font-weight: 800; color: #0D1B3E; }
.ps-vm-body  { font-size: 12.5px; color: #546E7A; line-height: 1.5; margin-bottom: 16px; }
.ps-vm-body strong { font-weight: 700; color: #0D1B3E; }
.ps-vm-body code   { background: #EEF2FF; padding: 1px 5px; border-radius: 4px; font-size: 11px; }
.ps-vm-field { margin-bottom: 12px; }
.ps-vm-label { font-size: 11px; font-weight: 700; color: #546E7A; letter-spacing: .5px; display: block; margin-bottom: 6px; }
.ps-vm-textarea {
  width: 100%; border: 1.5px solid #CFD8E8; border-radius: 12px;
  padding: 10px 12px; font-size: 13.5px; color: #0D1B3E;
  resize: none; outline: none; font-family: inherit;
  background: #FAFAFA;
}
.ps-vm-textarea:focus { border-color: #1565C0; background: #fff; }
.ps-vm-char-count { font-size: 10px; color: #B0BEC5; text-align: right; margin-top: 3px; }
.ps-vm-error { font-size: 11.5px; font-weight: 600; color: #C62828; margin-bottom: 10px; }
.ps-vm-actions { display: flex; gap: 10px; }
.ps-vm-cancel {
  flex: 1; height: 44px; border-radius: 12px;
  background: #F5F5F5; border: 1.5px solid #E0E0E0;
  font-size: 13px; font-weight: 700; color: #546E7A; cursor: pointer;
}
.ps-vm-confirm {
  flex: 2; height: 44px; border-radius: 12px;
  background: #C62828; border: none;
  font-size: 13px; font-weight: 700; color: #fff; cursor: pointer;
}
.ps-vm-confirm--orange { background: #E65100; }
.ps-vm-confirm:disabled { opacity: .4; cursor: not-allowed; }

/* ── SPIN ANIMATION ── */
.ps-spin { animation: ps-spin 0.8s linear infinite; }
@keyframes ps-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ── WHO SYMPTOM REFERENCE PANEL ── */
.pf-syref {
  margin-bottom: 10px;
  border-radius: 12px;
  background: #F0F6FF;
  border: 1px solid #DDEAFF;
  overflow: hidden;
}
.pf-syref-toggle {
  width: 100%; display: flex; align-items: center; gap: 7px;
  padding: 9px 12px; background: transparent; border: none;
  cursor: pointer; text-align: left;
  font-size: 11px; font-weight: 700; color: #1565C0;
  -webkit-tap-highlight-color: transparent;
}
.pf-syref-toggle svg:first-child { width: 14px; height: 14px; flex-shrink: 0; }
.pf-syref-toggle span { flex: 1; }
.pf-syref-chevron {
  width: 10px; height: 10px; flex-shrink: 0;
  transition: transform .2s ease;
}
.pf-syref-chevron--open { transform: rotate(180deg); }
.pf-syref-body {
  display: flex; flex-wrap: wrap; gap: 5px;
  padding: 0 10px 10px;
}
.pf-syref-chip {
  font-size: 10.5px; font-weight: 600; color: #1565C0;
  background: #fff; border: 1px solid #BBDEFB;
  border-radius: 8px; padding: 3px 9px;
  white-space: nowrap;
}

/* ══════════════════════════════════════════════════════════════
   PREMIUM CAPTURE FORM — pf-* namespace
   Light material navy · animated · tactile
══════════════════════════════════════════════════════════════ */

/* Section wrapper */
.pf-section {
  padding: 14px 14px 0;
}
.pf-section--last { padding-bottom: 0; }
.pf-label {
  font-size: 10px; font-weight: 800; letter-spacing: 1.2px;
  text-transform: uppercase; color: #78909C;
  margin-bottom: 8px; display: flex; align-items: center; gap: 7px;
}
.pf-opt { font-size: 9px; font-weight: 600; letter-spacing: .3px; text-transform: none; color: #B0BEC5; }
.pf-req {
  font-size: 9px; font-weight: 700; letter-spacing: .3px; text-transform: none;
  color: #C62828; background: #FFEBEE; padding: 1px 6px; border-radius: 5px; border: 1px solid #FFCDD2;
}
.pf-err {
  margin: 5px 0 0; font-size: 11px; font-weight: 600; color: #C62828;
  display: flex; align-items: center; gap: 5px;
}

/* ── GENDER CARDS ── */
.pf-gender-row {
  display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
}
.pf-gender-card {
  position: relative; overflow: hidden;
  height: 82px; border-radius: 20px;
  background: #F4F7FF;
  border: 2px solid #E3EAF8;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;
  cursor: pointer;
  transition: transform .12s cubic-bezier(.34,1.56,.64,1), border-color .15s, background .15s, box-shadow .15s;
  -webkit-tap-highlight-color: transparent;
}
.pf-gender-card:active { transform: scale(.95); }

/* Selection ring pulse animation */
.pf-gc-ring {
  position: absolute; inset: -2px; border-radius: 22px;
  border: 2.5px solid transparent; pointer-events: none;
  transition: border-color .2s, box-shadow .2s;
}

/* Male inactive */
.pf-gender-card--male { }
.pf-gender-card--male .pf-gc-icon :deep(svg) { stroke: #1565C0; }

/* Female inactive */
.pf-gender-card--female .pf-gc-icon :deep(svg) { stroke: #7B1FA2; }

/* Active state — male */
.pf-gender-card--male.pf-gender-card--active {
  background: linear-gradient(145deg, #1D4ED8 0%, #1565C0 60%, #0D47A1 100%);
  border-color: #0D47A1;
  box-shadow: 0 6px 20px rgba(13,71,161,.35), 0 2px 8px rgba(13,71,161,.2);
  transform: translateY(-2px) scale(1.01);
}
.pf-gender-card--male.pf-gender-card--active .pf-gc-ring {
  border-color: rgba(96,165,250,.5);
  box-shadow: 0 0 0 4px rgba(13,71,161,.12);
}
.pf-gender-card--male.pf-gender-card--active .pf-gc-icon :deep(svg) { stroke: #fff; }
.pf-gender-card--male.pf-gender-card--active .pf-gc-lbl { color: #fff; }

/* Active state — female */
.pf-gender-card--female.pf-gender-card--active {
  background: linear-gradient(145deg, #7B1FA2 0%, #8E24AA 60%, #6A1B9A 100%);
  border-color: #6A1B9A;
  box-shadow: 0 6px 20px rgba(123,31,162,.32), 0 2px 8px rgba(123,31,162,.18);
  transform: translateY(-2px) scale(1.01);
}
.pf-gender-card--female.pf-gender-card--active .pf-gc-ring {
  border-color: rgba(206,147,216,.5);
  box-shadow: 0 0 0 4px rgba(123,31,162,.1);
}
.pf-gender-card--female.pf-gender-card--active .pf-gc-icon :deep(svg) { stroke: #fff; }
.pf-gender-card--female.pf-gender-card--active .pf-gc-lbl { color: #fff; }

.pf-gc-icon {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
}
.pf-gc-icon :deep(svg) { width: 22px; height: 22px; transition: stroke .15s; }
.pf-gc-lbl {
  font-size: 13px; font-weight: 800; letter-spacing: .3px;
  color: #546E7A; transition: color .15s;
}
.pf-gc-tick {
  position: absolute; top: 7px; right: 9px;
  width: 18px; height: 18px; border-radius: 50%;
  background: rgba(255,255,255,.25);
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  animation: pf-tick-pop .2s cubic-bezier(.34,1.56,.64,1) both;
}
.pf-gc-tick svg { width: 11px; height: 11px; }
@keyframes pf-tick-pop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }

/* ── TEMPERATURE CARD ── */
.pf-temp-card {
  display: flex; align-items: center; gap: 10px;
  background: #fff; border: 2px solid #E3EAF8; border-radius: 18px;
  padding: 0 10px 0 14px; min-height: 58px;
  transition: border-color .2s, box-shadow .2s;
}
.pf-temp-card--focus {
  border-color: #1565C0;
  box-shadow: 0 0 0 4px rgba(21,101,192,.08), 0 4px 16px rgba(21,101,192,.1);
}
.pf-temp-card--warn {
  border-color: #E65100;
  box-shadow: 0 0 0 4px rgba(230,81,0,.07);
}
.pf-temp-card--crit {
  border-color: #C62828;
  box-shadow: 0 0 0 4px rgba(198,40,40,.08);
  animation: pf-crit-pulse 1.5s ease-in-out infinite;
}
@keyframes pf-crit-pulse {
  0%,100%{ box-shadow: 0 0 0 4px rgba(198,40,40,.08); }
  50%{ box-shadow: 0 0 0 6px rgba(198,40,40,.18), 0 4px 20px rgba(198,40,40,.15); }
}
.pf-temp-icon { flex-shrink: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; }
.pf-temp-icon svg { width: 18px; height: 18px; }
.pf-temp-body { flex: 1; display: flex; flex-direction: column; justify-content: center; }
.pf-temp-input-el {
  border: none; outline: none; background: transparent;
  font-size: 28px; font-weight: 800; color: #0D1B3E;
  width: 100%; padding: 0; line-height: 1;
}
.pf-temp-input-el::placeholder { color: #CFD8DC; font-size: 20px; font-weight: 400; }
.pf-temp-input-el::-webkit-outer-spin-button,
.pf-temp-input-el::-webkit-inner-spin-button { -webkit-appearance: none; }
.pf-temp-input-el { -moz-appearance: textfield; }
.pf-temp-hint {
  font-size: 10.5px; font-weight: 600; margin-top: 1px; line-height: 1.2;
  animation: pf-hint-in .15s ease-out;
}
@keyframes pf-hint-in { from { opacity: 0; transform: translateY(-3px); } to { opacity: 1; } }
.pf-temp-hint--normal { color: #2E7D32; }
.pf-temp-hint--warn   { color: #E65100; }
.pf-temp-hint--danger { color: #C62828; font-weight: 700; }
.pf-unit-pill {
  display: flex; flex-direction: column; gap: 3px; flex-shrink: 0;
}
.pf-unit-btn {
  width: 40px; height: 22px; border-radius: 7px; border: none;
  font-size: 11px; font-weight: 800; cursor: pointer;
  transition: background .15s, color .15s;
}
.pf-unit-btn--on  { background: #1565C0; color: #fff; }
.pf-unit-btn:not(.pf-unit-btn--on) { background: #EEF2FF; color: #90A4AE; }
.pf-temp-x {
  width: 32px; height: 32px; border-radius: 50%; background: #ECEFF1; border: none;
  display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0;
}
.pf-temp-x svg { width: 12px; height: 12px; }

/* Animated fever gauge */
.pf-gauge-wrap { padding: 6px 2px 0; }
.pf-gauge-track {
  height: 5px; border-radius: 3px; position: relative;
  background: linear-gradient(90deg,
    #90CAF9 0%, #42A5F5 25%, #43A047 45%,
    #FFA726 65%, #EF5350 80%, #B71C1C 100%);
}
.pf-gauge-fill {
  position: absolute; top: 0; left: 0; bottom: 0; border-radius: 3px;
  background: transparent; pointer-events: none;
}
.pf-gauge-thumb {
  position: absolute; top: 50%; transform: translate(-50%,-50%);
  width: 13px; height: 13px; border-radius: 50%; border: 2.5px solid #fff;
  box-shadow: 0 1px 6px rgba(0,0,0,.25);
  transition: left .3s cubic-bezier(.34,1.56,.64,1);
}
.pf-gauge-thumb--normal { background: #1565C0; }
.pf-gauge-thumb--warn   { background: #E65100; }
.pf-gauge-thumb--danger { background: #C62828; animation: pf-thumb-pulse .8s ease-in-out infinite; }
@keyframes pf-thumb-pulse { 0%,100%{ box-shadow:0 1px 6px rgba(0,0,0,.25); } 50%{ box-shadow:0 0 0 5px rgba(198,40,40,.2),0 1px 6px rgba(0,0,0,.25); } }

/* ── SYMPTOMS CARDS ── */
.pf-sym-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.pf-sym-card {
  position: relative; overflow: hidden;
  height: 88px; border-radius: 20px;
  background: #F4F7FF; border: 2px solid #E3EAF8;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
  cursor: pointer;
  transition: transform .12s cubic-bezier(.34,1.56,.64,1), border-color .15s, background .15s, box-shadow .15s;
  -webkit-tap-highlight-color: transparent;
}
.pf-sym-card:active { transform: scale(.95); }
.pf-sc-ring {
  position: absolute; inset: -2px; border-radius: 22px;
  border: 2.5px solid transparent; pointer-events: none;
  transition: border-color .2s;
}
.pf-sc-icon { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; }
.pf-sc-icon svg { width: 26px; height: 26px; transition: stroke .15s; }
.pf-sc-lbl {
  font-size: 14px; font-weight: 800; letter-spacing: -.1px; color: #546E7A;
  transition: color .15s;
}
.pf-sc-sub { font-size: 10px; font-weight: 600; color: #90A4AE; letter-spacing: .2px; transition: color .15s; }

/* NO — active */
.pf-sym-card--no.pf-sym-card--active {
  background: linear-gradient(145deg, #1B5E20 0%, #2E7D32 50%, #388E3C 100%);
  border-color: #1B5E20;
  box-shadow: 0 6px 20px rgba(46,125,50,.35), 0 2px 8px rgba(46,125,50,.2);
  transform: translateY(-2px) scale(1.01);
}
.pf-sym-card--no.pf-sym-card--active .pf-sc-ring { border-color: rgba(129,199,132,.5); }
.pf-sym-card--no.pf-sym-card--active .pf-sc-lbl,
.pf-sym-card--no.pf-sym-card--active .pf-sc-sub { color: #fff; }

/* YES — active */
.pf-sym-card--yes.pf-sym-card--active {
  background: linear-gradient(145deg, #B71C1C 0%, #C62828 50%, #D32F2F 100%);
  border-color: #B71C1C;
  box-shadow: 0 6px 20px rgba(198,40,40,.35), 0 2px 8px rgba(198,40,40,.2);
  transform: translateY(-2px) scale(1.01);
  animation: pf-sym-alert .6s cubic-bezier(.34,1.56,.64,1);
}
@keyframes pf-sym-alert {
  0%  { transform: translateY(-2px) scale(1.01); }
  30% { transform: translateY(-2px) scale(1.04) rotate(-.5deg); }
  60% { transform: translateY(-2px) scale(1.02) rotate(.3deg); }
  100%{ transform: translateY(-2px) scale(1.01); }
}
.pf-sym-card--yes.pf-sym-card--active .pf-sc-ring { border-color: rgba(239,154,154,.5); }
.pf-sym-card--yes.pf-sym-card--active .pf-sc-lbl,
.pf-sym-card--yes.pf-sym-card--active .pf-sc-sub { color: #fff; }

/* Traveler name slide-in */
.pf-slide-enter-active { transition: all .22s cubic-bezier(.34,1.4,.64,1); }
.pf-slide-leave-active { transition: all .15s ease-in; }
.pf-slide-enter-from  { opacity: 0; transform: translateY(-8px) scaleY(.9); }
.pf-slide-leave-to    { opacity: 0; transform: translateY(-4px) scaleY(.95); }
.pf-name-wrap { margin-top: 10px; }
.pf-name-card {
  display: flex; align-items: center; gap: 10px;
  background: #FFF8E1; border: 2px solid #FFE082; border-radius: 16px;
  padding: 0 14px; height: 52px;
  transition: border-color .2s, box-shadow .2s;
}
.pf-name-card--focus { border-color: #E65100; box-shadow: 0 0 0 4px rgba(230,81,0,.07); }
.pf-name-card svg { width: 18px; height: 18px; flex-shrink: 0; }
.pf-name-input {
  flex: 1; border: none; outline: none; background: transparent;
  font-size: 14.5px; color: #0D1B3E; font-weight: 500;
}
.pf-name-input::placeholder { color: #BCAAA4; }

/* ── TEMP INLINE BADGE (kept for backward compat, now unused) ── */
.ps-temp-badge { font-size: 9px; font-weight: 900; padding: 2px 6px; border-radius: 5px; }
.ps-temp-badge--crit { background: #FFEBEE; color: #C62828; }
.ps-temp-badge--high { background: #FFF3E0; color: #E65100; }
.ps-field-error--compact { margin: 3px 14px 0; font-size: 11px; font-weight: 600; color: #C62828; }
.ps-name-appear { margin: 8px 14px 0; }

/* ── FILTER CHIPS ROW ── */
.ps-filter-chips {
  display: flex; align-items: center; gap: 6px;
  padding: 0 14px 8px; flex-wrap: wrap;
}
.ps-chip {
  height: 28px; padding: 0 11px; border-radius: 14px;
  background: #ECEFF1; border: 1.5px solid #CFD8DC;
  font-size: 11px; font-weight: 700; color: #546E7A;
  cursor: pointer; white-space: nowrap; transition: all .1s;
}
.ps-chip:active { transform: scale(.96); }
.ps-chip--active { background: #1565C0; border-color: #0D47A1; color: #fff; }
.ps-chip--sm { height: 24px; padding: 0 9px; font-size: 10px; }
.ps-chip--sym.ps-chip--active  { background: #C62828; border-color: #B71C1C; }
.ps-chip--ok.ps-chip--active   { background: #2E7D32; border-color: #1B5E20; }
.ps-chip--warn.ps-chip--active { background: #E65100; border-color: #BF360C; }

/* ── FILTER BUTTON ── */
.ps-filter-btn {
  display: flex; align-items: center; gap: 5px;
  height: 32px; padding: 0 10px; border-radius: 10px;
  background: #E3F2FD; border: 1.5px solid #BBDEFB;
  font-size: 12px; font-weight: 700; color: #1565C0; cursor: pointer;
}
.ps-filter-btn svg { width: 13px; height: 13px; }
.ps-filter-btn-txt { max-width: 90px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ps-records-count { font-size: 11px; color: #90A4AE; margin-left: auto; }

/* ── QUEUE FILTER ── */
.ps-queue-filter { display: flex; gap: 5px; margin-left: auto; }

/* ── PAGINATION ── */
.ps-pagination {
  display: flex; align-items: center; justify-content: center;
  gap: 16px; padding: 12px 14px;
  border-top: 1px solid #E3EAF8; margin-top: 4px;
}
.ps-page-btn {
  height: 34px; padding: 0 14px; border-radius: 10px;
  background: #E3F2FD; border: 1.5px solid #BBDEFB;
  font-size: 12px; font-weight: 700; color: #1565C0; cursor: pointer;
}
.ps-page-btn:disabled { opacity: .4; cursor: not-allowed; }
.ps-page-info { font-size: 12px; font-weight: 700; color: #546E7A; }

/* ── FILTER PRESETS ── */
.ps-filter-presets {
  display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 4px;
}
.ps-preset-btn {
  height: 30px; padding: 0 12px; border-radius: 10px;
  background: #ECEFF1; border: 1.5px solid #CFD8DC;
  font-size: 11px; font-weight: 700; color: #546E7A;
  cursor: pointer;
}
.ps-preset-btn--active { background: #1565C0; border-color: #0D47A1; color: #fff; }

/* ── DATE INPUT ── */
.ps-date-input {
  width: 100%; height: 44px; padding: 0 12px;
  border: 1.5px solid #CFD8E8; border-radius: 12px;
  font-size: 14px; color: #0D1B3E; background: #FAFAFA;
  outline: none; font-family: inherit;
}
.ps-date-input:focus { border-color: #1565C0; background: #fff; }

/* Remove old auth-strip and sec-hdr styles (still in CSS but unused — safe to keep) */
/* Gender grid: 2 cols only now */
.ps-gender-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 8px; margin: 12px 14px 6px;
}
</style>
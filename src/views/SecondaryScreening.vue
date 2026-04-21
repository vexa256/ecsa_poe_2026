<template>
  <IonPage>

    <!-- ══════════════════════════════════════════════════════════════════
         HEADER — Case context, progress stepper, sync badge
    ══════════════════════════════════════════════════════════════════ -->
    <IonHeader class="sc-header" :translucent="false">
      <div class="sc-hdr-pattern" aria-hidden="true" />

      <!-- Top bar: back + title + sync badge -->
      <div class="sc-hdr-top">
        <button class="sc-back-btn" type="button" aria-label="Back to referral queue" @click="goBackToQueue">
          <svg viewBox="0 0 18 18" fill="none" stroke="rgba(255,255,255,0.85)" stroke-width="2.2" stroke-linecap="round">
            <polyline points="11 4 6 9 11 14"/>
          </svg>
        </button>
        <div class="sc-title-block">
          <span class="sc-eyebrow">Secondary Screening · IHR Art. 23</span>
          <div class="sc-page-title">{{ caseRecord ? 'Case in Progress' : 'Open Case' }}</div>
        </div>
        <div class="sc-hdr-right">
          <div class="sc-sync-pill" :class="syncPillClass" aria-live="polite" @click="isAdmin ? _debugTap() : null" style="cursor:default">
            <span class="sc-sync-dot" />
            <span class="sc-sync-txt">{{ syncPillLabel }}</span>
          </div>
        </div>
      </div>

      <!-- Case summary strip — only when case is loaded -->
      <div v-if="notification" class="sc-case-strip">
        <div class="sc-case-ic" aria-hidden="true">
          <svg viewBox="0 0 14 14" fill="none" stroke="rgba(255,255,255,.8)" stroke-width="1.5" stroke-linecap="round">
            <circle cx="7" cy="5" r="3"/><path d="M2 13c0-2.8 2.2-5 5-5s5 2.2 5 5"/>
          </svg>
        </div>
        <div class="sc-case-info">
          <div class="sc-case-name">{{ primaryScreening?.traveler_full_name || 'Anonymous Traveler' }}</div>
          <div class="sc-case-meta">
            {{ genderLabel(notification.gender ?? primaryScreening?.gender) }}
            <span v-if="primaryScreening?.temperature_value"> · {{ primaryScreening.temperature_value }}°{{ primaryScreening.temperature_unit || 'C' }}</span>
            · {{ auth.poe_code ?? '—' }}
          </div>
        </div>
        <div v-if="primaryScreening?.traveler_direction" class="sc-dir-badge" :class="'sc-dir--'+(primaryScreening.traveler_direction||'').toLowerCase()">
          {{ primaryScreening.traveler_direction }}
        </div>
        <div class="sc-prio-pill" :class="priorityClass">{{ notification.priority || 'NORMAL' }}</div>
      </div>

      <!-- 4-step progress bar -->
      <div class="sc-stepper" role="progressbar" :aria-valuenow="step" aria-valuemin="1" aria-valuemax="4">
        <div v-for="(s, i) in STEPS" :key="s.key" class="sc-step-wrap">
          <button
            class="sc-step"
            :class="{
              'sc-step--done':   step > i + 1,
              'sc-step--active': step === i + 1,
              'sc-step--future': step < i + 1,
            }"
            type="button"
            :aria-label="'Step ' + (i+1) + ': ' + s.label"
            @click="jumpToStep(i + 1)"
          >
            <span class="sc-step-node">
              <svg v-if="step > i + 1" viewBox="0 0 12 12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"><polyline points="2 6 5 9 10 3"/></svg>
              <span v-else class="sc-step-num">{{ i + 1 }}</span>
            </span>
            <span class="sc-step-lbl">{{ s.label }}</span>
          </button>
          <div v-if="i < STEPS.length - 1" class="sc-step-line" :class="{ 'sc-step-line--done': step > i + 1 }" aria-hidden="true" />
        </div>
      </div>
    </IonHeader>

    <!-- ══════════════════════════════════════════════════════════════════
         CONTENT
    ══════════════════════════════════════════════════════════════════ -->
    <IonContent class="sc-content" :scrollY="true">

      <!-- LOADING -->
      <div v-if="loading" class="sc-loading" aria-live="polite" aria-busy="true">
        <div class="sc-spinner" aria-hidden="true" />
        <div class="sc-loading-txt">Loading case…</div>
      </div>

      <!-- NOT FOUND -->
      <div v-else-if="notFound" class="sc-guard sc-guard--warn" role="alert">
        <svg viewBox="0 0 20 20" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round">
          <circle cx="10" cy="10" r="8"/><line x1="10" y1="6" x2="10" y2="11"/><circle cx="10" cy="14" r=".8" fill="#fff"/>
        </svg>
        <div>
          <div class="sc-guard-title">Notification Not Found</div>
          <div class="sc-guard-sub">The referral could not be located in local storage. It may not have synced to this device yet.</div>
        </div>
      </div>

      <!-- ── WIZARD BODY ── -->
      <div v-else class="sc-body">

        <!-- POE mismatch advisory — non-blocking, supervisor override scenario -->
        <div v-if="poeMismatch" class="sc-poe-warn" role="status">
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
            <circle cx="7" cy="7" r="5"/><line x1="7" y1="4.5" x2="7" y2="7.5"/><circle cx="7" cy="9.5" r=".5" fill="currentColor"/>
          </svg>
          <span>This referral belongs to <strong>{{ notification?.poe_code }}</strong>. You are logged in at <strong>{{ auth.poe_code }}</strong>. Proceed only if authorised.</span>
        </div>

        <!-- ════════════════════════════════════════════════════
             STEP 1 — TRAVELER PROFILE & TRAVEL
        ════════════════════════════════════════════════════ -->
        <div v-show="step === 1">

          <!-- Section: Traveler Identity -->
          <div class="sc-section-hdr">
            <span class="sc-sec-num sc-sec-num--blue">1</span>
            <span class="sc-sec-title">Traveler Identity</span>
            <span class="sc-sec-badge sc-sec-badge--opt">Optional</span>
          </div>

          <div class="sc-card">
            <!-- Full name -->
            <div class="sc-field-row">
              <div class="sc-field-ic">
                <svg viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.5" stroke-linecap="round"><circle cx="7" cy="5" r="3"/><path d="M2 13c0-2.8 2.2-5 5-5s5 2.2 5 5"/></svg>
              </div>
              <div class="sc-field-body">
                <label class="sc-field-lbl" for="sc-traveler-name">Full Name</label>
                <input
                  id="sc-traveler-name" class="sc-field-input"
                  type="text" maxlength="150" placeholder="Enter traveler name…"
                  v-model.trim="profile.traveler_full_name"
                  autocomplete="off"
                />
              </div>
            </div>

            <!-- Gender (pre-filled, editable) -->
            <div class="sc-field-row">
              <div class="sc-field-ic">
                <svg viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.5" stroke-linecap="round"><circle cx="7" cy="7" r="5"/><line x1="7" y1="4" x2="7" y2="10"/><line x1="4" y1="7" x2="10" y2="7"/></svg>
              </div>
              <div class="sc-field-body">
                <label class="sc-field-lbl">Gender</label>
                <div class="sc-gender-row">
                  <button
                    v-for="g in GENDERS" :key="g.value"
                    class="sc-gender-btn"
                    :class="{ 'sc-gender-btn--active': profile.traveler_gender === g.value }"
                    type="button" @click="profile.traveler_gender = g.value"
                  >{{ g.label }}</button>
                </div>
              </div>
            </div>

            <!-- Age -->
            <div class="sc-field-row">
              <div class="sc-field-ic">
                <svg viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="3" width="10" height="9" rx="2"/><line x1="5" y1="1" x2="5" y2="5"/><line x1="9" y1="1" x2="9" y2="5"/></svg>
              </div>
              <div class="sc-field-body">
                <label class="sc-field-lbl" for="sc-age">Age (years)</label>
                <input
                  id="sc-age" class="sc-field-input sc-field-input--short"
                  type="number" min="0" max="120" placeholder="e.g. 34"
                  v-model.number="profile.traveler_age_years"
                />
              </div>
            </div>

            <!-- Nationality -->
            <div class="sc-field-row">
              <div class="sc-field-ic">
                <svg viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.5" stroke-linecap="round"><circle cx="7" cy="7" r="5"/><path d="M2 7h10M7 2c-1.5 2-2 3.3-2 5s.5 3 2 5M7 2c1.5 2 2 3.3 2 5s-.5 3-2 5"/></svg>
              </div>
              <div class="sc-field-body">
                <label class="sc-field-lbl" for="sc-nationality">Nationality</label>
                <select id="sc-nationality" class="sc-field-select" v-model="profile.traveler_nationality_country_code">
                  <option value="">— Select country —</option>
                  <option v-for="c in COUNTRY_LIST" :key="c.code2" :value="c.code2">{{ c.name }}</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Section: Travel Document -->
          <div class="sc-section-hdr" style="margin-top:16px">
            <span class="sc-sec-num sc-sec-num--purple">2</span>
            <span class="sc-sec-title">Travel Document</span>
            <span class="sc-sec-badge sc-sec-badge--opt">Optional</span>
          </div>

          <div class="sc-card">
            <!-- Doc type -->
            <div class="sc-field-row">
              <div class="sc-field-ic">
                <svg viewBox="0 0 14 14" fill="none" stroke="#6A1B9A" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="1" width="10" height="12" rx="2"/><line x1="5" y1="5" x2="9" y2="5"/><line x1="5" y1="8" x2="9" y2="8"/></svg>
              </div>
              <div class="sc-field-body">
                <label class="sc-field-lbl">Document Type</label>
                <div class="sc-chip-row">
                  <button
                    v-for="dt in DOC_TYPES" :key="dt.value"
                    class="sc-chip-btn"
                    :class="{ 'sc-chip-btn--active': profile.travel_document_type === dt.value }"
                    type="button" @click="profile.travel_document_type = dt.value"
                  >{{ dt.label }}</button>
                </div>
              </div>
            </div>

            <!-- Doc number -->
            <div class="sc-field-row sc-field-row--last">
              <div class="sc-field-ic">
                <svg viewBox="0 0 14 14" fill="none" stroke="#6A1B9A" stroke-width="1.5" stroke-linecap="round"><circle cx="7" cy="7" r="5"/><text x="4" y="10" font-size="5" fill="#6A1B9A">#</text></svg>
              </div>
              <div class="sc-field-body">
                <label class="sc-field-lbl" for="sc-docnum">Document Number</label>
                <input
                  id="sc-docnum" class="sc-field-input"
                  type="text" maxlength="60" placeholder="Passport / ID number…"
                  v-model.trim="profile.travel_document_number"
                  autocomplete="off"
                />
              </div>
            </div>
          </div>

          <!-- Section: Travel Details -->
          <div class="sc-section-hdr" style="margin-top:16px">
            <span class="sc-sec-num sc-sec-num--orange">3</span>
            <span class="sc-sec-title">Journey Information</span>
            <span class="sc-sec-badge sc-sec-badge--opt">Optional</span>
          </div>

          <div class="sc-card">
            <!-- Origin country -->
            <div class="sc-field-row">
              <div class="sc-field-ic">
                <svg viewBox="0 0 14 14" fill="none" stroke="#E65100" stroke-width="1.5" stroke-linecap="round"><path d="M2 12L5 2l4 8 3-4 2 6"/></svg>
              </div>
              <div class="sc-field-body">
                <label class="sc-field-lbl" for="sc-origin">Journey Origin Country</label>
                <select id="sc-origin" class="sc-field-select" v-model="profile.journey_start_country_code">
                  <option value="">— Where did journey begin? —</option>
                  <option v-for="c in COUNTRY_LIST" :key="c.code2" :value="c.code2">{{ c.name }}</option>
                </select>
              </div>
            </div>

            <!-- Conveyance type -->
            <div class="sc-field-row">
              <div class="sc-field-ic">
                <svg viewBox="0 0 14 14" fill="none" stroke="#E65100" stroke-width="1.5" stroke-linecap="round"><path d="M1 11h12M3 11V7l4-5 4 5v4"/></svg>
              </div>
              <div class="sc-field-body">
                <label class="sc-field-lbl">Mode of Transport</label>
                <div class="sc-chip-row">
                  <button
                    v-for="ct in CONVEYANCE_TYPES" :key="ct.value"
                    class="sc-chip-btn"
                    :class="{ 'sc-chip-btn--active': profile.conveyance_type === ct.value }"
                    type="button" @click="profile.conveyance_type = ct.value"
                  >{{ ct.label }}</button>
                </div>
              </div>
            </div>

            <!-- Flight/vessel ID (only for AIR/SEA) -->
            <div v-if="profile.conveyance_type === 'AIR' || profile.conveyance_type === 'SEA'" class="sc-field-row">
              <div class="sc-field-ic">
                <svg viewBox="0 0 14 14" fill="none" stroke="#E65100" stroke-width="1.5" stroke-linecap="round"><line x1="2" y1="7" x2="12" y2="7"/><line x1="9" y1="4" x2="12" y2="7"/><line x1="9" y1="10" x2="12" y2="7"/></svg>
              </div>
              <div class="sc-field-body">
                <label class="sc-field-lbl" for="sc-convid">
                  {{ profile.conveyance_type === 'AIR' ? 'Flight Number' : 'Vessel Name' }}
                </label>
                <input
                  id="sc-convid" class="sc-field-input"
                  type="text" maxlength="80"
                  :placeholder="profile.conveyance_type === 'AIR' ? 'e.g. KQ101' : 'e.g. MV Victoria'"
                  v-model.trim="profile.conveyance_identifier"
                />
              </div>
            </div>

            <!-- Arrival datetime -->
            <div class="sc-field-row sc-field-row--last">
              <div class="sc-field-ic">
                <svg viewBox="0 0 14 14" fill="none" stroke="#E65100" stroke-width="1.5" stroke-linecap="round"><circle cx="7" cy="7" r="5"/><polyline points="7 4 7 7 9 9"/></svg>
              </div>
              <div class="sc-field-body">
                <label class="sc-field-lbl" for="sc-arrival">Arrival Date/Time</label>
                <input
                  id="sc-arrival" class="sc-field-input"
                  type="datetime-local"
                  v-model="profile.arrival_datetime_input"
                />
              </div>
            </div>
          </div>

          <!-- Section: Countries Visited (21-day IHR lookback) -->
          <div class="sc-section-hdr" style="margin-top:16px">
            <span class="sc-sec-num sc-sec-num--green">4</span>
            <span class="sc-sec-title">Countries Visited (Last 21 Days)</span>
            <span class="sc-sec-badge sc-sec-badge--req">IHR Lookback</span>
          </div>

          <div v-if="travelCountries.length === 0" class="sc-empty-travel">
            <svg viewBox="0 0 20 20" fill="none" stroke="#B0BEC5" stroke-width="1.4" stroke-linecap="round"><circle cx="10" cy="10" r="8"/><path d="M2 10h16M10 2c-2 2.5-3 5-3 8s1 5.5 3 8M10 2c2 2.5 3 5 3 8s-1 5.5-3 8"/></svg>
            <span>No countries added yet</span>
          </div>

          <div v-for="(tc, idx) in travelCountries" :key="idx" class="sc-tc-row">
            <select class="sc-tc-select" v-model="tc.country_code" :aria-label="'Country ' + (idx+1)">
              <option value="">— Country —</option>
              <option v-for="c in COUNTRY_LIST" :key="c.code2" :value="c.code2">{{ c.name }}</option>
            </select>
            <select class="sc-tc-role" v-model="tc.travel_role" :aria-label="'Role for country ' + (idx+1)">
              <option value="VISITED">Visited</option>
              <option value="TRANSIT">Transit</option>
            </select>
            <button class="sc-tc-remove" type="button" :aria-label="'Remove country ' + (idx+1)" @click="removeTravelCountry(idx)">
              <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="2" y1="2" x2="10" y2="10"/><line x1="10" y1="2" x2="2" y2="10"/></svg>
            </button>
          </div>

          <button class="sc-add-country-btn" type="button" @click="addTravelCountry">
            <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="7" y1="2" x2="7" y2="12"/><line x1="2" y1="7" x2="12" y2="7"/></svg>
            Add Country
          </button>

          <!-- Contact info -->
          <div class="sc-section-hdr" style="margin-top:16px">
            <span class="sc-sec-num sc-sec-num--blue">5</span>
            <span class="sc-sec-title">Contact Information</span>
            <span class="sc-sec-badge sc-sec-badge--opt">Optional</span>
          </div>

          <div class="sc-card">
            <div class="sc-field-row">
              <div class="sc-field-ic">
                <svg viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.5" stroke-linecap="round"><path d="M2 2h3l1.5 3.5L5 7s1 2 4 4l1.5-1.5L14 11v3s-1.2 1-3 0C5 11 2 5 2 2z"/></svg>
              </div>
              <div class="sc-field-body">
                <label class="sc-field-lbl" for="sc-phone">Phone Number</label>
                <input
                  id="sc-phone" class="sc-field-input"
                  type="tel" maxlength="40" placeholder="e.g. 25678927376"
                  v-model.trim="profile.phone_number"
                />
              </div>
            </div>
            <div class="sc-field-row sc-field-row--last">
              <div class="sc-field-ic">
                <svg viewBox="0 0 14 14" fill="none" stroke="#1565C0" stroke-width="1.5" stroke-linecap="round"><path d="M7 1C4.2 1 2 3.2 2 6c0 4 5 7 5 7s5-3 5-7c0-2.8-2.2-5-5-5z"/><circle cx="7" cy="6" r="1.5"/></svg>
              </div>
              <div class="sc-field-body">
                <label class="sc-field-lbl" for="sc-dest-district">Destination District</label>
                <input
                  id="sc-dest-district" class="sc-field-input"
                  type="text" maxlength="30" placeholder="e.g. Kampala District"
                  v-model.trim="profile.destination_district_code"
                />
              </div>
            </div>
          </div>

          <div style="height:8px"/>
        </div>
        <!-- /STEP 1 -->


        <!-- ════════════════════════════════════════════════════
             STEP 2 — SYMPTOMS
        ════════════════════════════════════════════════════ -->
        <div v-show="step === 2">

          <!-- Header + count -->
          <div class="sc-section-hdr">
            <span class="sc-sec-num sc-sec-num--orange">S</span>
            <span class="sc-sec-title">Symptom Checklist</span>
            <span class="sc-sym-count" aria-live="polite">
              {{ presentSymptomCount }} present
            </span>
          </div>

          <div class="sc-sym-intro">
            Tap each symptom the traveler is currently experiencing. Unknown or absent symptoms should remain off.
          </div>

          <!-- Symptom groups -->
          <div v-for="grp in SYMPTOM_GROUPS" :key="grp.key" class="sc-sym-group">
            <div class="sc-sym-group-hdr">
              <span class="sc-sym-group-dot" :style="{ background: grp.color }" aria-hidden="true" />
              {{ grp.label }}
            </div>
            <div class="sc-sym-grid">
              <button
                v-for="sym in grp.symptoms" :key="sym.code"
                class="sc-sym-card"
                :class="{ 'sc-sym-card--on': symState(sym.code) === 1, 'sc-sym-card--off': symState(sym.code) === 0 }"
                type="button"
                :aria-pressed="symState(sym.code) === 1"
                :aria-label="sym.label"
                @click="toggleSymptom(sym.code)"
              >
                <span class="sc-sym-indicator" aria-hidden="true">
                  <svg v-if="symState(sym.code) === 1" viewBox="0 0 10 10" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"><polyline points="1 5 4 8 9 2"/></svg>
                </span>
                <span class="sc-sym-name" :class="{ 'sc-sym-name--on': symState(sym.code) === 1 }">{{ sym.label }}</span>
              </button>
            </div>

            <!-- Onset date inputs for present symptoms that require it -->
            <div v-for="sym in grp.symptoms.filter(s => s.requiresOnset && symState(s.code) === 1)" :key="'onset-' + sym.code" class="sc-onset-row">
              <div class="sc-onset-ic" aria-hidden="true">
                <svg viewBox="0 0 12 12" fill="none" stroke="#E65100" stroke-width="1.5" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><polyline points="6 3.5 6 6 7.5 7.5"/></svg>
              </div>
              <div class="sc-onset-body">
                <label class="sc-onset-lbl" :for="'onset-' + sym.code">{{ sym.label }} — onset date</label>
                <input
                  :id="'onset-' + sym.code"
                  class="sc-onset-input"
                  type="date"
                  :max="todayDate"
                  v-model="getSymptomRecord(sym.code).onset_date"
                />
              </div>
            </div>
          </div>

          <!-- ── Optional Clinical Data (toggleable) ── -->
          <div class="sc-vitals-toggle-hdr" @click="showVitals = !showVitals" role="button" :aria-expanded="showVitals">
            <div class="sc-vitals-toggle-left">
              <div class="sc-vitals-toggle-ic" aria-hidden="true">
                <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 8h2l2-6 3 10 2-6 1 2h2"/></svg>
              </div>
              <span class="sc-vitals-toggle-lbl">Clinical Vitals &amp; Triage</span>
              <span class="sc-vitals-badge">Optional</span>
            </div>
            <svg class="sc-vitals-chevron" :class="{ 'sc-vitals-chevron--open': showVitals }" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
              <polyline points="2 4 6 8 10 4"/>
            </svg>
          </div>

          <div v-show="showVitals" class="sc-vitals-panel">
            <div class="sc-vitals-note">Complete only if measurement equipment is available at this POE.</div>

            <!-- Temperature -->
            <div class="sc-vt-row">
              <label class="sc-vt-lbl">Temperature</label>
              <div class="sc-vt-inputs">
                <input
                  class="sc-vt-num" type="number" step="0.1"
                  :min="vitals.temperature_unit === 'F' ? 77 : 25"
                  :max="vitals.temperature_unit === 'F' ? 113 : 45"
                  placeholder="e.g. 38.2"
                  v-model.number="vitals.temperature_value"
                />
                <select class="sc-vt-unit" v-model="vitals.temperature_unit" aria-label="Temperature unit">
                  <option value="C">°C</option>
                  <option value="F">°F</option>
                </select>
              </div>
              <span v-if="tempWarning" class="sc-vt-warn" :class="tempWarnClass" role="alert">{{ tempWarning }}</span>
            </div>

            <!-- Pulse + RR -->
            <div class="sc-vt-pair">
              <div class="sc-vt-row sc-vt-row--half">
                <label class="sc-vt-lbl">Pulse (bpm)</label>
                <input class="sc-vt-num" type="number" min="20" max="250" placeholder="e.g. 88" v-model.number="vitals.pulse_rate" />
                <span v-if="pulseWarning" class="sc-vt-warn sc-vt-warn--sm" role="alert">{{ pulseWarning }}</span>
              </div>
              <div class="sc-vt-row sc-vt-row--half">
                <label class="sc-vt-lbl">Resp. Rate (/min)</label>
                <input class="sc-vt-num" type="number" min="5" max="60" placeholder="e.g. 18" v-model.number="vitals.respiratory_rate" />
                <span v-if="rrWarning" class="sc-vt-warn sc-vt-warn--sm" role="alert">{{ rrWarning }}</span>
              </div>
            </div>

            <!-- BP -->
            <div class="sc-vt-pair">
              <div class="sc-vt-row sc-vt-row--half">
                <label class="sc-vt-lbl">BP Systolic (mmHg)</label>
                <input class="sc-vt-num" type="number" min="40" max="300" placeholder="e.g. 120" v-model.number="vitals.bp_systolic" />
              </div>
              <div class="sc-vt-row sc-vt-row--half">
                <label class="sc-vt-lbl">BP Diastolic</label>
                <input class="sc-vt-num" type="number" min="20" max="200" placeholder="e.g. 80" v-model.number="vitals.bp_diastolic" />
              </div>
            </div>

            <!-- SpO2 -->
            <div class="sc-vt-row">
              <label class="sc-vt-lbl">SpO₂ (%)</label>
              <input class="sc-vt-num sc-vt-num--short" type="number" min="50" max="100" step="0.5" placeholder="e.g. 97.5" v-model.number="vitals.oxygen_saturation" />
              <span v-if="spo2Warning" class="sc-vt-warn" :class="spo2WarnClass" role="alert">{{ spo2Warning }}</span>
            </div>

            <!-- Triage category -->
            <div class="sc-vt-row">
              <label class="sc-vt-lbl">Triage Category</label>
              <div class="sc-triage-row">
                <button
                  v-for="tr in TRIAGE_CATS" :key="tr.value"
                  class="sc-triage-btn"
                  :class="['sc-triage-btn--' + tr.value.toLowerCase(), vitals.triage_category === tr.value && 'sc-triage-btn--active']"
                  type="button"
                  @click="vitals.triage_category = tr.value"
                >
                  <span class="sc-triage-lbl">{{ tr.label }}</span>
                  <span class="sc-triage-sub">{{ tr.sub }}</span>
                </button>
              </div>
            </div>

            <!-- Emergency signs + General appearance -->
            <div class="sc-vt-pair">
              <div class="sc-vt-row sc-vt-row--half">
                <label class="sc-vt-lbl">Emergency Signs</label>
                <div class="sc-bool-row">
                  <button class="sc-bool-btn" :class="{ 'sc-bool-btn--yes': vitals.emergency_signs_present === 1 }" type="button" @click="vitals.emergency_signs_present = vitals.emergency_signs_present === 1 ? 0 : 1">{{ vitals.emergency_signs_present === 1 ? 'YES ✓' : 'No' }}</button>
                </div>
                <span v-if="vitals.emergency_signs_present === 1" class="sc-vt-warn sc-vt-warn--sm sc-vt-warn--crit">Requires EMERGENCY triage</span>
              </div>
              <div class="sc-vt-row sc-vt-row--half">
                <label class="sc-vt-lbl">General Appearance</label>
                <select class="sc-field-select" v-model="vitals.general_appearance" aria-label="General appearance">
                  <option value="">— Select —</option>
                  <option value="WELL">Well</option>
                  <option value="UNWELL">Unwell</option>
                  <option value="SEVERELY_ILL">Severely Ill</option>
                </select>
              </div>
            </div>

          </div>
          <!-- /vitals panel -->

          <div style="height:8px"/>
        </div>
        <!-- /STEP 2 -->


        <!-- ════════════════════════════════════════════════════
             STEP 3 — STRUCTURED EXPOSURE QUESTIONNAIRE
        ════════════════════════════════════════════════════ -->
        <div v-show="step === 3">

          <div class="sc-section-hdr">
            <span class="sc-sec-num sc-sec-num--red">E</span>
            <span class="sc-sec-title">Structured Exposure Questionnaire</span>
          </div>

          <div class="sc-exposure-intro">
            Ask the traveler each question. Select the most accurate response.
            <strong>Unknown</strong> is the default — only change to Yes or No when certain.
          </div>

          <!-- HIGH-RISK signal banner -->
          <div v-if="highRiskSignals.length > 0" class="sc-exp-hisig" role="alert" aria-live="polite">
            <svg viewBox="0 0 14 14" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round"><path d="M7 1L1 12h12L7 1z"/><line x1="7" y1="5" x2="7" y2="8.5"/><circle cx="7" cy="10.5" r=".6" fill="#fff"/></svg>
            <div>
              <div class="sc-exp-hisig-title">{{ highRiskSignals.length }} HIGH-RISK Exposure{{ highRiskSignals.length > 1 ? 's' : '' }} Confirmed</div>
              <div v-for="sig in highRiskSignals" :key="sig.code" class="sc-exp-hisig-row">
                <span class="sc-exp-hisig-lbl">{{ sig.label }}</span>
                <span v-if="sig.critical_message" class="sc-exp-hisig-note">{{ sig.critical_message }}</span>
              </div>
            </div>
          </div>

          <!-- Exposures grouped by category from window.EXPOSURES -->
          <div v-for="cat in exposureCategoryKeys" :key="cat" class="sc-exp-cat">
            <div class="sc-exp-cat-hdr">{{ EXPOSURE_CATEGORY_LABELS[cat] || cat }}</div>
            <div v-for="exp in exposuresByCategory[cat]" :key="exp.code" class="sc-exp-card"
              :class="{ 'sc-exp-card--yes': exposuresMap[exp.code]?.response === 'YES', 'sc-exp-card--high': exp.risk_level === 'VERY_HIGH' || exp.risk_level === 'HIGH' }">
              <div class="sc-exp-body">
                <div class="sc-exp-header-row">
                  <p class="sc-exp-question">{{ exp.label }}</p>
                  <span v-if="exp.risk_level === 'VERY_HIGH'" class="sc-exp-risk sc-exp-risk--vhigh">VERY HIGH RISK</span>
                  <span v-else-if="exp.risk_level === 'HIGH'" class="sc-exp-risk sc-exp-risk--high">HIGH RISK</span>
                </div>
                <p class="sc-exp-desc">{{ exp.description }}</p>
                <div class="sc-exp-btns" role="group" :aria-label="'Answer for: ' + exp.label">
                  <button class="sc-exp-btn sc-exp-btn--yes" :class="{ 'sc-exp-btn--active': exposuresMap[exp.code]?.response === 'YES' }"
                    type="button" @click="setExposureResponse(exp.code, 'YES')" :aria-pressed="exposuresMap[exp.code]?.response === 'YES'">Yes</button>
                  <button class="sc-exp-btn sc-exp-btn--no"  :class="{ 'sc-exp-btn--active': exposuresMap[exp.code]?.response === 'NO' }"
                    type="button" @click="setExposureResponse(exp.code, 'NO')"  :aria-pressed="exposuresMap[exp.code]?.response === 'NO'">No</button>
                  <button class="sc-exp-btn sc-exp-btn--unk" :class="{ 'sc-exp-btn--active': !exposuresMap[exp.code] || exposuresMap[exp.code]?.response === 'UNKNOWN' }"
                    type="button" @click="setExposureResponse(exp.code, 'UNKNOWN')">Unknown</button>
                </div>
                <!-- Critical flag warning when YES -->
                <div v-if="exp.critical_flag && exposuresMap[exp.code]?.response === 'YES'" class="sc-exp-critical" role="alert">
                  ⚠ {{ exp.critical_message }}
                </div>
              </div>
            </div>
          </div>

          <!-- Summary -->
          <div v-if="yesExposureCount > 0" class="sc-exp-summary" role="status" aria-live="polite">
            <svg viewBox="0 0 14 14" fill="none" stroke="#E65100" stroke-width="1.5" stroke-linecap="round"><path d="M7 1L1 12h12L7 1z"/><line x1="7" y1="5.5" x2="7" y2="8.5"/><circle cx="7" cy="10.5" r=".6" fill="#E65100"/></svg>
            <span><strong>{{ yesExposureCount }}</strong> exposure{{ yesExposureCount > 1 ? 's' : '' }} confirmed YES — {{ engineExposureCodes.length }} engine signals activated</span>
          </div>

          <div style="height:8px"/>
        </div>
        <!-- /STEP 3 -->


        <!-- ════════════════════════════════════════════════════
             STEP 4 — CLINICAL ANALYSIS & DISPOSITION
        ════════════════════════════════════════════════════ -->
        <div v-show="step === 4">

          <!-- ── Analysis Results ── -->
          <div class="sc-section-hdr">
            <span class="sc-sec-num sc-sec-num--red">A</span>
            <span class="sc-sec-title">Disease Intelligence Analysis</span>
            <span class="sc-sec-badge sc-sec-badge--warn">AI-Assisted</span>
          </div>

          <!-- Insufficient data warning -->
          <div v-if="analysisResult && analysisResult.global_flags.includes('INSUFFICIENT_DATA')" class="sc-insuff-warn" role="alert">
            <svg viewBox="0 0 14 14" fill="none" stroke="#E65100" stroke-width="1.5" stroke-linecap="round"><circle cx="7" cy="7" r="5"/><line x1="7" y1="4" x2="7" y2="7.5"/><circle cx="7" cy="9.5" r=".5" fill="#E65100"/></svg>
            <span>Insufficient data — fewer than 2 symptoms confirmed. Analysis confidence is very low. Gather more clinical information.</span>
          </div>

          <!-- VHF / critical override banners -->
          <div
            v-for="flag in criticalFlags"
            :key="flag"
            class="sc-flag-banner"
            role="alert"
            aria-live="assertive"
          >
            <svg viewBox="0 0 14 14" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round"><path d="M7 1L1 12h12L7 1z"/><line x1="7" y1="5" x2="7" y2="8.5"/><circle cx="7" cy="10.5" r=".7" fill="#fff"/></svg>
            <span class="sc-flag-txt">{{ FLAG_MESSAGES[flag] || flag }}</span>
          </div>

          <!-- NON-CASE VERDICT BANNER -->
          <div v-if="analysisResult?.is_non_case" class="sc-noncase-banner" role="alert">
            <div class="sc-nc-hdr">
              <svg viewBox="0 0 16 16" fill="none" stroke="#00C853" stroke-width="1.8" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><polyline points="5 8 7 10 11 6"/></svg>
              <span>NON-CASE — No Clinical Indicators</span>
            </div>
            <div v-for="reason in analysisResult.non_case.reasons" :key="reason" class="sc-nc-reason">{{ reason }}</div>
            <div class="sc-nc-action">Recommended: Syndrome = NONE · Disposition = RELEASED</div>
            <button class="sc-nc-override-btn" type="button" @click="officerOverride.overrideNonCase = !officerOverride.overrideNonCase">
              {{ officerOverride.overrideNonCase ? '✓ Override Active' : 'Officer Override — I disagree' }}
            </button>
            <div v-if="officerOverride.overrideNonCase" class="sc-nc-override-note">
              <span class="sc-nc-override-lbl">Mandatory: Document clinical justification for overriding the non-case classification:</span>
              <textarea class="sc-override-input" rows="2" v-model="officerOverride.overrideNote" placeholder="e.g. Traveller has rash not captured in symptom list. Clinical assessment suggests infectious illness…" />
            </div>
          </div>

          <!-- OUTBREAK CONTEXT — countries that triggered endemic bonuses -->
          <div v-if="(analysisResult?.outbreak_context_used?.length || 0) > 0 && !analysisResult?.is_non_case" class="sc-outbreak-ctx">
            <div class="sc-oc-hdr">
              <svg viewBox="0 0 14 14" fill="none" stroke="#FF6D00" stroke-width="1.5" stroke-linecap="round"><circle cx="7" cy="7" r="5"/><line x1="7" y1="4" x2="7" y2="7.5"/><circle cx="7" cy="9.5" r=".5" fill="#FF6D00"/></svg>
              <span>Travel History Matches {{ analysisResult.outbreak_context_used.length }} Endemic Disease Zone{{ analysisResult.outbreak_context_used.length > 1 ? 's' : '' }}</span>
            </div>
            <div class="sc-oc-chips">
              <span v-for="diseaseId in analysisResult.outbreak_context_used.slice(0,8)" :key="diseaseId"
                class="sc-oc-chip" :title="diseaseId">{{ diseaseName(diseaseId) }}</span>
              <span v-if="analysisResult.outbreak_context_used.length > 8" class="sc-oc-chip sc-oc-chip--more">+{{ analysisResult.outbreak_context_used.length - 8 }} more</span>
            </div>
          </div>

          <!-- IHR RISK ASSESSMENT from engine -->
          <div v-if="analysisResult?.ihr_risk && !analysisResult?.is_non_case" class="sc-ihr-result" :class="'sc-ihr--'+analysisResult.ihr_risk.risk_level.toLowerCase()">
            <div class="sc-ihr-hdr">
              <span class="sc-ihr-level">{{ analysisResult.ihr_risk.risk_level }}</span>
              <span class="sc-ihr-routing" v-if="analysisResult.ihr_risk.routing_level">→ {{ analysisResult.ihr_risk.routing_level }}</span>
              <span v-if="analysisResult.ihr_risk.ihr_alert_required" class="sc-ihr-alert-badge">IHR NOTIFICATION REQUIRED</span>
            </div>
            <div v-for="r in (analysisResult.ihr_risk.reasoning || []).slice(0,3)" :key="r" class="sc-ihr-reason">{{ r }}</div>
          </div>

          <!-- Top disease cards with WHO case definitions -->
          <div v-if="analysisResult && analysisResult.top_diagnoses.length > 0 && !analysisResult.is_non_case" class="sc-disease-list">
            <div v-for="(d, idx) in analysisResult.top_diagnoses.slice(0, 5)" :key="d.disease_id"
              class="sc-disease-card" :class="'sc-disease-card--' + (d.confidence_band || 'low')"
              @click="openDiseaseModal(d)" role="button" tabindex="0" style="cursor:pointer;">
              <div class="sc-dc-rank" :class="idx === 0 ? 'sc-dc-rank--top' : ''">{{ idx + 1 }}</div>
              <div class="sc-dc-body">
                <div class="sc-dc-name-row">
                  <span class="sc-dc-name">{{ d.name || diseaseName(d.disease_id) }}</span>
                  <span v-if="d.syndrome_matched" class="sc-dc-syn-match">{{ d.syndrome_matched }} ✓</span>
                </div>
                <div class="sc-dc-meta">
                  <span class="sc-dc-score">{{ d.final_score }}pts</span>
                  <span class="sc-dc-band" :class="'sc-dc-band--' + (d.confidence_band || 'low')">{{ d.confidence_band }}</span>
                  <span v-if="d.ihr_category" class="sc-dc-ihr">{{ d.ihr_category }}</span>
                  <span v-if="d.cfr_pct" class="sc-dc-cfr">CFR {{ d.cfr_pct }}%</span>
                </div>
                <div v-if="d.matched_hallmarks.length > 0" class="sc-dc-hallmarks">
                  <span v-for="h in d.matched_hallmarks" :key="h" class="sc-dc-htag">{{ h.replace(/_/g,' ') }}</span>
                </div>
                <!-- WHO Case Definition (expandable) -->
                <button class="sc-dc-def-toggle" type="button" @click.stop="openDiseaseModal(d)" aria-label="View full disease detail">
                  Full Detail &amp; WHO Definition →
                </button>
                <!-- WHO case definition shown in disease detail modal — tap card for full modal -->
              </div><!-- /sc-dc-body -->
              <div class="sc-dc-pct" :class="'sc-dc-pct--' + (d.confidence_band || 'low')">
                {{ d.probability_like_percent != null ? d.probability_like_percent + '%' : '' }}
              </div>
            </div>
          </div>

          <!-- Officer override — Suspected Diseases -->
          <div class="sc-override-section">
            <div class="sc-section-hdr" style="margin-top:12px">
              <span class="sc-sec-num sc-sec-num--purple">O</span>
              <span class="sc-sec-title">Officer Override — Suspected Disease</span>
              <span class="sc-sec-badge sc-sec-badge--opt">Optional</span>
            </div>
            <p class="sc-override-hint">The algorithm has suggested the diseases above. If you clinically suspect a different disease, select it from the WHO disease catalog below.</p>
            <div class="sc-override-add-row">
              <select v-model="officerOverride.customDiseaseInput" class="sc-override-disease-select" aria-label="Select suspected disease">
                <option value="">— Select disease —</option>
                <option v-for="d in availableOverrideDiseases" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
              <button class="sc-override-add-btn" type="button" @click="addOfficerSuspectedDisease" :disabled="!officerOverride.customDiseaseInput">Add</button>
            </div>
            <div v-if="officerOverride.addedDiseases.length > 0" class="sc-override-added">
              <span v-for="(d, i) in officerOverride.addedDiseases" :key="i" class="sc-override-tag">
                {{ diseaseName(d) }}
                <button type="button" class="sc-override-rm" @click="officerOverride.addedDiseases.splice(i,1)">&times;</button>
              </span>
            </div>
          </div>

          <!-- No analysis yet -->
          <div v-if="!analysisResult" class="sc-empty-analysis" role="status">
            <svg viewBox="0 0 20 20" fill="none" stroke="#B0BEC5" stroke-width="1.4" stroke-linecap="round"><circle cx="10" cy="10" r="8"/><path d="M6 10h8M10 6v8"/></svg>
            <span>Analysis not yet run. Go back to step 3 and tap "Analyse →"</span>
          </div>

          <!-- ── Syndrome Classification ── -->
          <div class="sc-section-hdr" style="margin-top:16px">
            <span class="sc-sec-num sc-sec-num--blue">1</span>
            <span class="sc-sec-title">Syndrome Classification</span>
            <span v-if="autoSyndromeApplied && caseDecision.syndrome_classification" class="sc-sec-badge sc-sec-badge--auto">Auto-set ✓</span>
            <span v-else class="sc-sec-badge sc-sec-badge--req">Required</span>
          </div>
          <!-- Engine syndrome suggestion -->
          <div v-if="analysisResult?.syndrome" class="sc-syn-engine-hint">
            <svg viewBox="0 0 12 12" fill="none" stroke="#1565C0" stroke-width="1.6" stroke-linecap="round" style="width:11px;height:11px;flex-shrink:0"><circle cx="6" cy="6" r="4.5"/><line x1="6" y1="4" x2="6" y2="6.5"/><circle cx="6" cy="8.5" r=".5" fill="#1565C0"/></svg>
            <span>Engine suggests: <strong>{{ analysisResult.syndrome.syndrome }}</strong> ({{ analysisResult.syndrome.confidence }}) — {{ analysisResult.syndrome.reasoning }}</span>
          </div>
          <div v-if="autoSyndromeApplied" class="sc-auto-hint">
            <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" style="width:10px;height:10px;flex-shrink:0"><circle cx="6" cy="6" r="4.5"/><polyline points="4 6 5.5 7.5 8 4.5"/></svg>
            Syndrome auto-classified from symptoms. Tap another button to override.
          </div>
          <div v-if="fieldErrors.syndrome_classification" class="sc-field-err" role="alert">{{ fieldErrors.syndrome_classification }}</div>
          <div class="sc-syndrome-grid">
            <button
              v-for="syn in SYNDROMES" :key="syn.code"
              class="sc-syn-btn"
              :class="{
                'sc-syn-btn--active': caseDecision.syndrome_classification === syn.code,
                'sc-syn-btn--danger': syn.danger,
              }"
              type="button"
              @click="caseDecision.syndrome_classification = syn.code; autoSyndromeApplied = false; officerOverride.syndromeOverridden = true"
              :aria-pressed="caseDecision.syndrome_classification === syn.code"
            >
              <span class="sc-syn-code">{{ syn.code }}</span>
              <span class="sc-syn-name">{{ syn.name }}</span>
            </button>
          </div>

          <!-- ── Risk Level ── -->
          <div class="sc-section-hdr" style="margin-top:16px">
            <span class="sc-sec-num sc-sec-num--red">2</span>
            <span class="sc-sec-title">Risk Level Assessment</span>
            <span class="sc-sec-badge sc-sec-badge--req">Required</span>
          </div>
          <div v-if="analysisResult?.ihr_risk && !analysisResult?.is_non_case" class="sc-risk-engine-suggest">
            <svg viewBox="0 0 12 12" fill="none" stroke="#1565C0" stroke-width="1.6" stroke-linecap="round" style="width:11px;height:11px;flex-shrink:0"><circle cx="6" cy="6" r="4.5"/><line x1="6" y1="4" x2="6" y2="6.5"/><circle cx="6" cy="8.5" r=".5" fill="#1565C0"/></svg>
            <span>IHR Algorithm suggests: <strong>{{ analysisResult.ihr_risk.risk_level }}</strong> — Route to <strong>{{ analysisResult.ihr_risk.routing_level }}</strong></span>
            <button v-if="analysisResult.ihr_risk.risk_level !== caseDecision.risk_level" class="sc-risk-apply-btn" type="button"
              @click="caseDecision.risk_level = analysisResult.ihr_risk.risk_level; officerOverride.riskOverridden = false">Apply</button>
          </div>
          <div v-if="fieldErrors.risk_level" class="sc-field-err" role="alert">{{ fieldErrors.risk_level }}</div>
          <div class="sc-risk-row">
            <button v-for="rl in RISK_LEVELS" :key="rl.value"
              class="sc-risk-btn"
              :class="['sc-risk-btn--' + rl.value.toLowerCase(), caseDecision.risk_level === rl.value && 'sc-risk-btn--active']"
              type="button"
              @click="caseDecision.risk_level = rl.value; officerOverride.riskOverridden = true"
              :aria-pressed="caseDecision.risk_level === rl.value"
            >
              <span class="sc-risk-lbl">{{ rl.label }}</span>
              <span class="sc-risk-sub">{{ rl.sub }}</span>
            </button>
          </div>

          <!-- ── Alert Preview (shows only when triggered) ── -->
          <div v-if="alertPreview" class="sc-alert-preview" role="alert" aria-live="polite">
            <div class="sc-ap-hdr">
              <svg viewBox="0 0 16 16" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round"><path d="M8 1L1 14h14L8 1z"/><line x1="8" y1="5.5" x2="8" y2="9"/><circle cx="8" cy="11.5" r=".7" fill="#fff"/></svg>
              <span class="sc-ap-title">Alert Auto-Triggered</span>
              <span class="sc-ap-badge">IHR Rule-Based</span>
            </div>
            <div class="sc-ap-body">
              <div class="sc-ap-row"><span class="sc-ap-k">Code</span><span class="sc-ap-v sc-ap-v--warn">{{ alertPreview.alertCode }}</span></div>
              <div class="sc-ap-row"><span class="sc-ap-k">Risk Level</span><span class="sc-ap-v">{{ alertPreview.riskLevel }}</span></div>
              <div class="sc-ap-row">
                <span class="sc-ap-k">Route To</span>
                <span class="sc-ap-v">
                  <span class="sc-ap-target" :class="'sc-ap-target--' + alertPreview.routedTo.toLowerCase()">{{ alertPreview.routedTo }} ★</span>
                </span>
              </div>
            </div>
          </div>

          <!-- ── Actions Taken ── -->
          <div class="sc-section-hdr" style="margin-top:16px">
            <span class="sc-sec-num sc-sec-num--green">3</span>
            <span class="sc-sec-title">Actions Taken</span>
            <span class="sc-sec-badge sc-sec-badge--req">At least 1 required</span>
          </div>
          <div v-if="fieldErrors.actions" class="sc-field-err" role="alert">{{ fieldErrors.actions }}</div>
          <div class="sc-actions-grid">
            <button
              v-for="ac in ACTIONS" :key="ac.code"
              class="sc-action-btn"
              :class="{ 'sc-action-btn--active': isActionDone(ac.code) }"
              type="button"
              :aria-pressed="isActionDone(ac.code)"
              @click="toggleAction(ac.code)"
            >
              <span class="sc-action-ic" aria-hidden="true">
                <svg v-if="isActionDone(ac.code)" viewBox="0 0 10 10" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"><polyline points="1 5 4 8 9 2"/></svg>
                <span v-else class="sc-action-dot" />
              </span>
              {{ ac.label }}
            </button>
          </div>

          <!-- HIGH/CRITICAL enforcement -->
          <div
            v-if="(caseDecision.risk_level === 'HIGH' || caseDecision.risk_level === 'CRITICAL') && !highRiskActionDone"
            class="sc-enforce-warn"
            role="alert"
          >
            <svg viewBox="0 0 14 14" fill="none" stroke="#C62828" stroke-width="1.5" stroke-linecap="round"><path d="M7 1L1 12h12L7 1z"/><line x1="7" y1="5" x2="7" y2="8.5"/><circle cx="7" cy="10.5" r=".6" fill="#C62828"/></svg>
            <span>Risk {{ caseDecision.risk_level }} requires <strong>ISOLATED</strong> or <strong>REFERRED_HOSPITAL</strong> action before disposition.</span>
          </div>

          <!-- ── Final Disposition ── -->
          <div class="sc-section-hdr" style="margin-top:16px">
            <span class="sc-sec-num sc-sec-num--green">4</span>
            <span class="sc-sec-title">Final Disposition</span>
            <span class="sc-sec-badge sc-sec-badge--req">Required</span>
          </div>
          <div v-if="fieldErrors.final_disposition" class="sc-field-err" role="alert">{{ fieldErrors.final_disposition }}</div>
          <div class="sc-disp-grid">
            <button
              v-for="dp in DISPOSITIONS" :key="dp.value"
              class="sc-disp-btn"
              :class="{ 'sc-disp-btn--active': caseDecision.final_disposition === dp.value }"
              type="button"
              :aria-pressed="caseDecision.final_disposition === dp.value"
              @click="caseDecision.final_disposition = dp.value"
            >
              <span class="sc-disp-ic" v-html="dp.icon" aria-hidden="true" />
              <span class="sc-disp-lbl">{{ dp.label }}</span>
            </button>
          </div>

          <!-- ── Officer Notes ── -->
          <div class="sc-section-hdr" style="margin-top:16px">
            <span class="sc-sec-num sc-sec-num--blue">5</span>
            <span class="sc-sec-title">Officer Notes</span>
            <span class="sc-sec-badge sc-sec-badge--opt">Optional</span>
          </div>
          <div class="sc-notes-wrap">
            <textarea
              class="sc-notes-input"
              rows="4"
              maxlength="5000"
              placeholder="Clinical observations, context, differential reasoning…"
              v-model="caseDecision.officer_notes"
              aria-label="Officer notes"
            />
          </div>

          <!-- ── Follow-up ── -->
          <div class="sc-followup-row">
            <button
              class="sc-followup-toggle"
              :class="{ 'sc-followup-toggle--on': caseDecision.followup_required }"
              type="button"
              @click="caseDecision.followup_required = !caseDecision.followup_required"
              :aria-pressed="caseDecision.followup_required"
            >
              <span class="sc-ft-indicator" aria-hidden="true" />
              <span class="sc-ft-lbl">Follow-up Required</span>
            </button>
            <select
              v-if="caseDecision.followup_required"
              class="sc-followup-level"
              v-model="caseDecision.followup_assigned_level"
              aria-label="Follow-up level"
            >
              <option value="">— Assign level —</option>
              <option value="POE">POE</option>
              <option value="DISTRICT">District</option>
              <option value="PHEOC">PHEOC</option>
              <option value="NATIONAL">National</option>
            </select>
          </div>

          <!-- Suspected diseases (read-only review) -->
          <div v-if="suspectedDiseases.length > 0" class="sc-section-hdr" style="margin-top:16px">
            <span class="sc-sec-num sc-sec-num--purple">6</span>
            <span class="sc-sec-title">Suspected Diseases (to be saved)</span>
            <span class="sc-sec-badge sc-sec-badge--opt">Auto-populated</span>
          </div>
          <div v-if="suspectedDiseases.length > 0" class="sc-sus-list">
            <div v-for="sd in suspectedDiseases" :key="sd.disease_code" class="sc-sus-row">
              <span class="sc-sus-rank">{{ sd.rank_order }}</span>
              <span class="sc-sus-name">{{ sd.display_name || diseaseName(sd.disease_code) }}</span>
              <span v-if="sd.confidence" class="sc-sus-conf">{{ sd.confidence }}%</span>
            </div>
          </div>

          <div style="height:8px"/>
        </div>
        <!-- /STEP 4 -->

        <!-- ════════════════════════════════════════════════════
             NOTIFICATION STATE VERIFICATION PANEL
             Always visible in step 4. Shows IDB + server state.
        ════════════════════════════════════════════════════ -->
        <div v-if="isAdmin && debugPanelOpen && step === 4" class="sc-verify-panel">
          <div class="sc-verify-header">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" style="width:14px;height:14px;flex-shrink:0">
              <circle cx="8" cy="8" r="6"/><polyline points="5 8 7.5 10.5 11 6"/>
            </svg>
            <span class="sc-verify-title">Notification &amp; Sync Integrity Test</span>
            <button class="sc-verify-btn sc-verify-btn--sync" :disabled="syncStatus.running" @click.prevent="syncCaseToServer(getAuth())">
              {{ syncStatus.running ? '⏳ Syncing…' : '⬆ Force Sync' }}
            </button>
            <button class="sc-verify-btn" :disabled="notifVerify.running" @click.prevent="verifyNotificationState">
              {{ notifVerify.running ? 'Checking…' : notifVerify.ran ? 'Re-verify' : 'Run Test' }}
            </button>
          </div>

          <!-- Sync result rows -->
          <div v-if="syncStatus.lastRunAt" class="sc-sync-result-block">
            <div class="sc-srb-title">Last sync attempt: {{ syncStatus.lastRunAt }}</div>
            <div class="sc-srb-row" :class="'sc-srb-row--' + (syncStatus.phase1 ?? 'pending')">
              <span class="sc-srb-phase">Phase 1 — Create</span>
              <span class="sc-srb-status">{{ syncStatus.phase1 ?? '—' }}</span>
              <span class="sc-srb-msg">{{ syncStatus.phase1Msg }}</span>
            </div>
            <div class="sc-srb-row" :class="'sc-srb-row--' + (syncStatus.phase2 ?? 'pending')">
              <span class="sc-srb-phase">Phase 2 — FullSync</span>
              <span class="sc-srb-status">{{ syncStatus.phase2 ?? '—' }}</span>
              <span class="sc-srb-msg">{{ syncStatus.phase2Msg }}</span>
            </div>
            <div v-if="syncStatus.error" class="sc-srb-error">⚠ {{ syncStatus.error }}</div>
            <details v-if="syncStatus.phase1Resp" class="sc-verify-raw">
              <summary>Phase 1 server response</summary>
              <pre>{{ JSON.stringify(syncStatus.phase1Resp, null, 2) }}</pre>
            </details>
            <details v-if="syncStatus.phase2Resp" class="sc-verify-raw">
              <summary>Phase 2 server response</summary>
              <pre>{{ JSON.stringify(syncStatus.phase2Resp, null, 2) }}</pre>
            </details>
            <details v-if="syncStatus.phase1Payload" class="sc-verify-raw">
              <summary>Phase 1 payload sent</summary>
              <pre>{{ JSON.stringify(syncStatus.phase1Payload, null, 2) }}</pre>
            </details>
          </div>

          <!-- Not yet run -->
          <div v-if="!notifVerify.ran && !notifVerify.running && !notifVerify.error" class="sc-verify-idle">
            Tap "Run Test" to verify notification and sync state end-to-end.
          </div>

          <!-- Error -->
          <div v-if="notifVerify.error" class="sc-verify-error">⚠ {{ notifVerify.error }}</div>

          <!-- Results -->
          <div v-if="notifVerify.ran" class="sc-verify-results">
            <div
              v-for="(chk, i) in notifVerify.checks"
              :key="i"
              class="sc-verify-row"
              :class="chk.pass ? 'sc-verify-row--pass' : 'sc-verify-row--fail'"
            >
              <span class="sc-verify-icon">{{ chk.pass ? '✓' : '✖' }}</span>
              <div class="sc-verify-body">
                <div class="sc-verify-label">{{ chk.label }}</div>
                <div class="sc-verify-detail">{{ chk.detail }}</div>
              </div>
            </div>

            <!-- Summary -->
            <div class="sc-verify-summary" :class="notifVerify.checks.every(c=>c.pass) ? 'sc-verify-summary--ok' : 'sc-verify-summary--warn'">
              {{ notifVerify.checks.filter(c=>c.pass).length }} / {{ notifVerify.checks.length }} checks passed
              <template v-if="!notifVerify.checks.every(c=>c.pass)">
              — {{ isOnline ? 'Tap Re-verify after saving or syncing' : 'Device is offline — IDB checks only' }}
              </template>
            </div>

            <!-- IDB raw dump -->
            <details class="sc-verify-raw" v-if="notifVerify.idb">
              <summary>IDB raw notification record</summary>
              <pre>{{ JSON.stringify(notifVerify.idb, null, 2) }}</pre>
            </details>
            <details class="sc-verify-raw" v-if="notifVerify.server">
              <summary>Server raw notification record</summary>
              <pre>{{ JSON.stringify(notifVerify.server, null, 2) }}</pre>
            </details>
          </div>
        </div>

      </div>
      <!-- /wizard body -->

    </IonContent>

    <!-- ══════════════════════════════════════════════════════════════════
         FOOTER — Navigation buttons
    ══════════════════════════════════════════════════════════════════ -->
    <IonFooter v-if="!loading && !notFound" class="sc-footer">
      <div class="sc-footer-inner">
        <!-- Back button (steps 2-4) -->
        <button
          v-if="step > 1"
          class="sc-nav-btn sc-nav-btn--back"
          type="button"
          @click="goBackStep"
          :disabled="saving"
        >
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 3 5 7 9 11"/></svg>
          Back
        </button>
        <div v-else class="sc-nav-spacer" />

        <!-- Step 1 → 2 -->
        <button
          v-if="step === 1"
          class="sc-nav-btn sc-nav-btn--next"
          type="button"
          @click="saveStep1AndNext"
          :disabled="saving"
        >
          {{ saving ? 'Saving…' : 'Next' }}
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="5 3 9 7 5 11"/></svg>
        </button>

        <!-- Step 2 → 3 -->
        <button
          v-if="step === 2"
          class="sc-nav-btn sc-nav-btn--next"
          type="button"
          @click="saveStep2AndNext"
          :disabled="saving"
        >
          {{ saving ? 'Saving…' : 'Next' }}
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="5 3 9 7 5 11"/></svg>
        </button>

        <!-- Step 3 → 4 (triggers analysis) -->
        <button
          v-if="step === 3"
          class="sc-nav-btn sc-nav-btn--analyse"
          type="button"
          @click="saveStep3AndAnalyse"
          :disabled="saving"
        >
          {{ saving ? 'Analysing…' : 'Analyse →' }}
        </button>

        <!-- Step 4 — Disposition -->
        <button
          v-if="step === 4"
          class="sc-nav-btn sc-nav-btn--disposition"
          type="button"
          @click="dispositionCase"
          :disabled="saving || !canDisposition"
        >
          {{ saving ? 'Saving…' : 'Save & Disposition' }}
        </button>
      </div>
    </IonFooter>



  <!-- ═══════════════════════════════════════════════════════════════
       DISEASE DETAIL MODAL — WHO case definition + intelligence scores
  ═══════════════════════════════════════════════════════════════════ -->
  <IonModal :is-open="!!selectedDiseaseModal"
    :breakpoints="[0, 1]" :initial-breakpoint="1"
    @ionModalDidDismiss="selectedDiseaseModal = null" handle-behavior="cycle">
    <IonHeader :translucent="false" v-if="selectedDiseaseModal">
      <IonToolbar style="--background:linear-gradient(180deg,#070E1B,#0E1A2E);--color:#EDF2FA;--border-width:0;--min-height:48px;">
        <IonButtons slot="start">
          <IonButton @click="selectedDiseaseModal = null"
            style="--color:rgba(255,255,255,.75);" aria-label="Close modal">
            <IonIcon :icon="closeOutline"/>
          </IonButton>
        </IonButtons>
        <IonTitle style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-weight:800;color:#EDF2FA;font-size:16px;">
          Disease Intelligence
        </IonTitle>
        <div slot="end" style="padding-right:12px;">
          <span v-if="selectedDiseaseModal.confidence_band" class="sc-dm-band"
            :class="'sc-dm-band--' + selectedDiseaseModal.confidence_band">
            {{ selectedDiseaseModal.confidence_band.replace(/_/g,' ').toUpperCase() }}
          </span>
        </div>
      </IonToolbar>
    </IonHeader>

    <IonContent :scroll-y="true" v-if="selectedDiseaseModal"
      style="--background:linear-gradient(180deg,#EEF2FA 0%,#FFFFFF 50%,#F4F7FC 100%);--color:#0B1A30;">
      <div class="sc-dm-wrap">

        <!-- Hero — name, ID, scores -->
        <div class="sc-dm-hero">
          <div class="sc-dm-name">{{ selectedDiseaseModal.name }}</div>
          <div class="sc-dm-id">{{ selectedDiseaseModal.disease_id }}</div>
          <div class="sc-dm-metric-row">
            <div v-if="selectedDiseaseModal.final_score != null" class="sc-dm-metric">
              <span class="sc-dm-metric-val"
                :class="selectedDiseaseModal.final_score >= 60 ? 'sc-dm-mv--high' : selectedDiseaseModal.final_score >= 35 ? 'sc-dm-mv--med' : 'sc-dm-mv--low'">
                {{ selectedDiseaseModal.final_score }}
              </span>
              <span class="sc-dm-metric-lbl">Score /100</span>
            </div>
            <div v-if="selectedDiseaseModal.probability_like_percent != null" class="sc-dm-metric">
              <span class="sc-dm-metric-val sc-dm-mv--med">{{ selectedDiseaseModal.probability_like_percent }}%</span>
              <span class="sc-dm-metric-lbl">Probability</span>
            </div>
            <div v-if="selectedDiseaseModal.cfr_pct != null" class="sc-dm-metric">
              <span class="sc-dm-metric-val sc-dm-mv--warn">{{ selectedDiseaseModal.cfr_pct }}%</span>
              <span class="sc-dm-metric-lbl">Case Fatality</span>
            </div>
          </div>
          <div v-if="selectedDiseaseModal.final_score != null" class="sc-dm-bar-track" aria-hidden="true">
            <div class="sc-dm-bar-fill" :style="{ width: Math.min(100, selectedDiseaseModal.final_score) + '%' }"
              :class="selectedDiseaseModal.final_score >= 60 ? 'sc-dm-bar--high'
                    : selectedDiseaseModal.final_score >= 35 ? 'sc-dm-bar--med' : 'sc-dm-bar--low'"/>
          </div>
        </div>

        <!-- IHR classification + syndrome -->
        <div v-if="selectedDiseaseModal.ihr_category || selectedDiseaseModal.syndrome_matched" class="sc-dm-section">
          <div class="sc-dm-section-lbl">CLASSIFICATION</div>
          <div class="sc-dm-chips-row">
            <span v-if="selectedDiseaseModal.ihr_category" class="sc-dm-ihr-chip">
              {{ selectedDiseaseModal.ihr_category }}
            </span>
            <span v-if="selectedDiseaseModal.syndrome_matched" class="sc-dm-syn-chip">
              <svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="2.2"
                stroke-linecap="round" aria-hidden="true">
                <polyline points="1 5 4 8 9 2"/>
              </svg>
              {{ selectedDiseaseModal.syndrome_matched }} matched
            </span>
          </div>
        </div>

        <!-- Matched hallmarks -->
        <div v-if="selectedDiseaseModal.matched_hallmarks?.length > 0" class="sc-dm-section">
          <div class="sc-dm-section-lbl">MATCHED HALLMARK SYMPTOMS ({{ selectedDiseaseModal.matched_hallmarks.length }})</div>
          <div class="sc-dm-hallmarks">
            <span v-for="h in selectedDiseaseModal.matched_hallmarks" :key="h" class="sc-dm-htag">
              {{ h.replace(/_/g, ' ') }}
            </span>
          </div>
        </div>

        <!-- Score breakdown -->
        <div v-if="selectedDiseaseModal.score_breakdown && Object.keys(selectedDiseaseModal.score_breakdown).length > 0"
          class="sc-dm-section">
          <div class="sc-dm-section-lbl">SCORE BREAKDOWN</div>
          <div class="sc-dm-breakdown">
            <div v-for="(val, key) in selectedDiseaseModal.score_breakdown" :key="key"
              class="sc-dm-bd-row">
              <span class="sc-dm-bd-k">{{ String(key).replace(/_/g,' ') }}</span>
              <span class="sc-dm-bd-v"
                :class="Number(val) > 0 ? 'sc-dm-bd-pos' : Number(val) < 0 ? 'sc-dm-bd-neg' : ''">
                {{ Number(val) > 0 ? '+' : '' }}{{ val }}
              </span>
            </div>
          </div>
        </div>

        <!-- WHO Case Definitions -->
        <template v-if="selectedDiseaseModal.who_def">
          <div class="sc-dm-section sc-dm-section--who">
            <div class="sc-dm-section-lbl">SUSPECTED CASE</div>
            <p class="sc-dm-def-text">{{ selectedDiseaseModal.who_def.suspected }}</p>
          </div>
          <div v-if="selectedDiseaseModal.who_def.probable" class="sc-dm-section sc-dm-section--who">
            <div class="sc-dm-section-lbl">PROBABLE CASE</div>
            <p class="sc-dm-def-text">{{ selectedDiseaseModal.who_def.probable }}</p>
          </div>
          <div v-if="selectedDiseaseModal.who_def.confirmed" class="sc-dm-section sc-dm-section--who">
            <div class="sc-dm-section-lbl">CONFIRMED CASE</div>
            <p class="sc-dm-def-text">{{ selectedDiseaseModal.who_def.confirmed }}</p>
          </div>
          <div v-if="selectedDiseaseModal.who_def.poe_action" class="sc-dm-section">
            <div class="sc-dm-section-lbl">POE ACTION REQUIRED</div>
            <div class="sc-dm-poe-action">{{ selectedDiseaseModal.who_def.poe_action }}</div>
          </div>
          <div v-if="selectedDiseaseModal.who_def.source" class="sc-dm-source">
            Source: {{ selectedDiseaseModal.who_def.source }}
          </div>
        </template>
        <div v-else class="sc-dm-section">
          <div class="sc-dm-section-lbl">WHO CASE DEFINITION</div>
          <p class="sc-dm-def-text sc-dm-def-text--muted">
            No WHO case definition available in the intelligence layer for this code.
          </p>
        </div>

        <div style="height:48px" aria-hidden="true"/>
      </div>
    </IonContent>
  </IonModal>

  </IonPage>
</template>


<script setup>
// ═══════════════════════════════════════════════════════════════════════════
// SecondaryScreening.vue
// Route:  /secondary-screening/:notificationUuid
// Roles:  POE_SECONDARY, POE_ADMIN
// Law:    poeDB.js is the ONLY data layer. No Dexie instantiation here.
//         API has NO auth middleware — NO Authorization header ever.
//         Navigate back using server integer id where applicable.
// ═══════════════════════════════════════════════════════════════════════════

import { ref, computed, reactive, watch, nextTick, onMounted, toRaw } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonFooter,
  IonModal, IonButtons, IonButton, IonIcon,
  onIonViewDidEnter,
} from '@ionic/vue'
import { closeOutline } from 'ionicons/icons'

import {
  dbPut, dbGet, safeDbPut,
  dbGetByIndex, dbReplaceAll, dbAtomicWrite,
  genUUID, isoNow, createRecordBase,
  STORE, SYNC, APP,
} from '@/services/poeDB'

// ─── IDB SERIALISATION HELPER ────────────────────────────────────────────
// IDB uses the structured clone algorithm which CANNOT clone Vue Proxy objects.
// Any value that came from ref(), reactive(), or computed() must be stripped
// of its Proxy wrapper before being passed to dbPut / dbReplaceAll / dbAtomicWrite.
// toPlain() handles this in ONE operation: toRaw strips the outer proxy,
// JSON round-trip deep-clones nested reactive objects into plain JS values.
// ALWAYS wrap every record or array passed to an IDB write with toPlain().
function toPlain(val) {
  return JSON.parse(JSON.stringify(toRaw(val)))
}
const route  = useRoute()
const router = useRouter()

// notificationUuid — the IDB primary key (client_uuid) of the notification.
// Read fresh inside _doInitPage(), never captured at setup scope.
const notificationUuid = ref('')

// ─── AUTH ─────────────────────────────────────────────────────────────────
// Auth is read fresh inside every write handler — NEVER cached at module level
function getAuth() {
  return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
}

const auth = reactive(getAuth())



// ─── STATE ────────────────────────────────────────────────────────────────
const loading        = ref(true)
const notFound       = ref(false)
const poeMismatch    = ref(false)   // user's POE does not match the notification's POE
const saving         = ref(false)
const step           = ref(1)

const notification      = ref(null)   // notifications record (IDB)
const primaryScreening  = ref(null)   // primary_screenings record (IDB)
const caseRecord        = ref(null)   // secondary_screenings record (IDB)
const caseUuid          = ref(null)   // client_uuid of the secondary case

// ─── STEP 1: PROFILE & TRAVEL ─────────────────────────────────────────────
const profile = reactive({
  traveler_full_name:                '',
  traveler_gender:                   '',
  traveler_age_years:                null,
  travel_document_type:              '',
  travel_document_number:            '',
  traveler_nationality_country_code: '',
  residence_country_code:            '',
  phone_number:                      '',
  journey_start_country_code:        '',
  conveyance_type:                   '',
  conveyance_identifier:             '',
  arrival_datetime_input:            '',   // datetime-local input → converted on save
  purpose_of_travel:                 '',
  destination_district_code:         '',
})

const travelCountries = ref([])  // array of { client_uuid, country_code, travel_role, arrival_date, departure_date }

// ─── STEP 2: SYMPTOMS ─────────────────────────────────────────────────────
// Full symptom inventory — all toggled YES/NO
// Initialised in initSymptoms() from SYMPTOM_GROUPS
const symptomsMap = reactive({})  // code → { symptom_code, is_present, onset_date, details }

const showVitals = ref(false)

const vitals = reactive({
  temperature_value:      null,
  temperature_unit:       'C',
  pulse_rate:             null,
  respiratory_rate:       null,
  bp_systolic:            null,
  bp_diastolic:           null,
  oxygen_saturation:      null,
  triage_category:        '',
  emergency_signs_present: 0,
  general_appearance:     '',
  syndrome_classification: '',
})

// ─── STEP 3: EXPOSURES ─────────────────────────────────────────────────────
// Driven by window.EXPOSURES catalog (exposures.js). Each exposure entry has:
//   exposure_code — the DB code (PK in secondary_exposures.exposure_code)
//   response      — YES / NO / UNKNOWN
//   details       — free text from officer
// window.EXPOSURES.mapToEngineCodes() translates these to engine codes
// for scoreDiseases(). No mapping logic in this Vue file.
const exposuresMap = reactive({})  // keyed by exposure_code

function initExposuresFromCatalog() {
  const catalog = window.EXPOSURES?.getAll() || []
  for (const exp of catalog) {
    if (!exposuresMap[exp.code]) {
      exposuresMap[exp.code] = {
        exposure_code: exp.code,
        response:      'UNKNOWN',
        details:       null,
      }
    }
  }
  L.info('initExposuresFromCatalog: ' + catalog.length + ' exposures initialized')
}

function setExposureResponse(code, response) {
  if (!exposuresMap[code]) {
    exposuresMap[code] = { exposure_code: code, response: 'UNKNOWN', details: null }
  }
  // Toggle: if already active, switch to UNKNOWN
  exposuresMap[code].response = exposuresMap[code].response === response ? 'UNKNOWN' : response
}

const allExposures = computed(() => window.EXPOSURES?.getAll() || [])
const exposuresByCategory = computed(() => window.EXPOSURES?.getCategoryGroups() || {})
const exposureCategoryKeys = computed(() => Object.keys(exposuresByCategory.value))
const EXPOSURE_CATEGORY_LABELS = window.EXPOSURES?.CATEGORY_LABELS || {}

const yesExposureCount = computed(() =>
  Object.values(exposuresMap).filter(e => e.response === 'YES').length
)

const engineExposureCodes = computed(() => {
  const records = Object.values(exposuresMap)
  return window.EXPOSURES?.mapToEngineCodes(records) || []
})

const highRiskSignals = computed(() => {
  const records = Object.values(exposuresMap)
  return window.EXPOSURES?.getHighRiskSignals(records) || []
})


// ─── STEP 4: ANALYSIS & DISPOSITION ──────────────────────────────────────
const analysisResult   = ref(null)  // result from window.DISEASES.scoreDiseases()
const clinicalReport  = ref(null) // structured clinical report
const reportExpanded  = ref(null) // expanded section
const suspectedDiseases = ref([])   // built from top_diagnoses → secondary_suspected_diseases rows

const caseDecision = reactive({
  syndrome_classification:   '',
  risk_level:                '',
  final_disposition:         '',
  officer_notes:             '',
  followup_required:         false,
  followup_assigned_level:   '',
})

const actions = ref([])  // array of { client_uuid, secondary_screening_id, action_code, is_done, details }

const fieldErrors = reactive({
  syndrome_classification: '',
  risk_level:              '',
  final_disposition:       '',
  actions:                 '',
})

// ─── REFERENCE DATA ───────────────────────────────────────────────────────
const COUNTRY_LIST = computed(() => {
  try {
    const raw = window.COUNTRIES?.[0] ?? window.COUNTRIES ?? []
    return raw.map(c => ({ code2: c.code2, name: c.name }))
              .sort((a, b) => a.name.localeCompare(b.name))
  } catch {
    return []
  }
})

const STEPS = [
  { key: 'profile',   label: 'Profile' },
  { key: 'symptoms',  label: 'Symptoms' },
  { key: 'exposure',  label: 'Exposures' },
  { key: 'analysis',  label: 'Analysis' },
]

const GENDERS = [
  { value: 'MALE',    label: 'Male' },
  { value: 'FEMALE',  label: 'Female' },
]

const DOC_TYPES = [
  { value: 'PASSPORT',       label: 'Passport' },
  { value: 'NATIONAL_ID',    label: 'National ID' },
  { value: 'LAISSEZ_PASSER', label: 'Laissez-Passer' },
  { value: 'OTHER',          label: 'Other' },
]

const CONVEYANCE_TYPES = [
  { value: 'LAND', label: 'Land' },
  { value: 'AIR',  label: 'Air' },
  { value: 'SEA',  label: 'Sea' },
  { value: 'OTHER', label: 'Other' },
]

const TRIAGE_CATS = [
  { value: 'NON_URGENT', label: 'Non-Urgent', sub: 'Stable' },
  { value: 'URGENT',     label: 'Urgent',     sub: 'Needs care' },
  { value: 'EMERGENCY',  label: 'Emergency',  sub: 'Immediate' },
]

// Syndrome codes — must match DB ENUM values exactly
const SYNDROMES = [
  { code: 'ILI',              name: 'Influenza-Like Illness', danger: false },
  { code: 'SARI',             name: 'Severe Acute Resp.',     danger: false },
  { code: 'AWD',              name: 'Acute Watery Diarr.',    danger: false },
  { code: 'BLOODY_DIARRHEA',  name: 'Bloody Diarrhoea',       danger: false },
  { code: 'VHF',              name: 'Viral Haem. Fever',      danger: true  },
  { code: 'RASH_FEVER',       name: 'Febrile Rash',           danger: false },
  { code: 'JAUNDICE',         name: 'Jaundice Syndrome',      danger: false },
  { code: 'NEUROLOGICAL',     name: 'Neurological Synd.',     danger: true  },
  { code: 'MENINGITIS',       name: 'Meningitis Syndrome',    danger: true  },
  { code: 'OTHER',            name: 'Other Syndrome',         danger: false },
  { code: 'NONE',             name: 'No Syndrome (FP)',       danger: false },
]

const RISK_LEVELS = [
  { value: 'LOW',      label: 'Low',      sub: 'Monitor' },
  { value: 'MEDIUM',   label: 'Medium',   sub: 'Investigate' },
  { value: 'HIGH',     label: 'High',     sub: 'Isolate' },
  { value: 'CRITICAL', label: 'Critical', sub: 'Emergency' },
]

const DISPOSITIONS = [
  { value: 'RELEASED',         label: 'Released',         icon: '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><polyline points="2 7 5.5 10.5 12 4"/></svg>' },
  { value: 'DELAYED',          label: 'Delayed',          icon: '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="7" cy="7" r="5"/><polyline points="7 4 7 7 9 9"/></svg>' },
  { value: 'QUARANTINED',      label: 'Quarantined',      icon: '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="2" y="2" width="10" height="10" rx="2"/><line x1="7" y1="2" x2="7" y2="12"/><line x1="2" y1="7" x2="12" y2="7"/></svg>' },
  { value: 'ISOLATED',         label: 'Isolated',         icon: '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="7" cy="7" r="5"/><line x1="7" y1="4" x2="7" y2="10"/></svg>' },
  { value: 'REFERRED',         label: 'Referred',         icon: '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M1 7h10M7 3l4 4-4 4"/></svg>' },
  { value: 'TRANSFERRED',      label: 'Transferred',      icon: '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M1 4h12M1 10h12M8 1l3 3-3 3M6 7l-3 3 3 3"/></svg>' },
  { value: 'DENIED_BOARDING',  label: 'Denied Entry',     icon: '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="7" cy="7" r="5"/><line x1="4" y1="4" x2="10" y2="10"/></svg>' },
  { value: 'OTHER',            label: 'Other',            icon: '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="7" cy="7" r="1"/><circle cx="3" cy="7" r="1"/><circle cx="11" cy="7" r="1"/></svg>' },
]

const ACTIONS = [
  { code: 'ISOLATED',                   label: 'Isolated' },
  { code: 'MASK_GIVEN',                 label: 'Mask Given' },
  { code: 'PPE_USED',                   label: 'PPE Used' },
  { code: 'SEPARATE_INTERVIEW_ROOM',    label: 'Separate Room' },
  { code: 'REFERRED_CLINIC',            label: 'Referred — Clinic' },
  { code: 'REFERRED_HOSPITAL',          label: 'Referred — Hospital' },
  { code: 'QUARANTINE_RECOMMENDED',     label: 'Quarantine Rec.' },
  { code: 'SAMPLE_COLLECTED',           label: 'Sample Collected' },
  { code: 'ALLOWED_CONTINUE',           label: 'Allowed to Continue' },
  { code: 'CONTACT_TRACING_INITIATED',  label: 'Contact Tracing' },
  { code: 'ALERT_ISSUED',               label: 'Alert Issued' },
  { code: 'FOLLOWUP_SCHEDULED',         label: 'Follow-up Scheduled' },
]

// Symptom groups for Step 2 UI — codes must match Diseases.js symptom IDs
const SYMPTOM_GROUPS = [
  {
    key: 'fever_systemic', label: 'Fever & Systemic', color: '#C62828',
    symptoms: [
      { code: 'fever',               label: 'Fever',                requiresOnset: true  },
      { code: 'high_fever',          label: 'High Fever (≥39°C)',    requiresOnset: true  },
      { code: 'sudden_onset_fever',  label: 'Sudden-onset Fever',    requiresOnset: true  },
      { code: 'low_grade_fever',     label: 'Low-grade Fever',       requiresOnset: true  },
      { code: 'chills',              label: 'Chills / Rigors',       requiresOnset: false },
      { code: 'fatigue',             label: 'Fatigue',               requiresOnset: false },
      { code: 'severe_fatigue',      label: 'Severe Fatigue',        requiresOnset: false },
      { code: 'weakness',            label: 'Weakness / Malaise',    requiresOnset: false },
    ],
  },
  {
    key: 'respiratory', label: 'Respiratory', color: '#1565C0',
    symptoms: [
      { code: 'cough',               label: 'Cough',                 requiresOnset: true  },
      { code: 'dry_cough',           label: 'Dry Cough',             requiresOnset: true  },
      { code: 'shortness_of_breath', label: 'Shortness of Breath',   requiresOnset: false },
      { code: 'difficulty_breathing',label: 'Difficulty Breathing',  requiresOnset: false },
      { code: 'sore_throat',         label: 'Sore Throat',           requiresOnset: false },
      { code: 'coryza',              label: 'Runny Nose / Coryza',   requiresOnset: false },
    ],
  },
  {
    key: 'gastrointestinal', label: 'Gastrointestinal', color: '#E65100',
    symptoms: [
      { code: 'nausea',              label: 'Nausea',                requiresOnset: false },
      { code: 'vomiting',            label: 'Vomiting',              requiresOnset: true  },
      { code: 'diarrhea',            label: 'Diarrhoea (general)',   requiresOnset: true  },
      { code: 'watery_diarrhea',     label: 'Profuse Watery Diarr.', requiresOnset: true  },
      { code: 'rice_water_diarrhea', label: 'Rice-water Diarr.',     requiresOnset: true  },
      { code: 'bloody_diarrhea',     label: 'Bloody Diarrhoea',      requiresOnset: true  },
      { code: 'abdominal_pain',      label: 'Abdominal Pain',        requiresOnset: false },
      { code: 'severe_dehydration',  label: 'Severe Dehydration',    requiresOnset: false },
    ],
  },
  {
    key: 'jaundice_hepatic', label: 'Jaundice & Hepatic', color: '#F9A825',
    symptoms: [
      { code: 'jaundice',            label: 'Jaundice (Yellow Eyes/Skin)', requiresOnset: false },
      { code: 'dark_urine',          label: 'Dark / Tea-coloured Urine',   requiresOnset: false },
      { code: 'anorexia',            label: 'Loss of Appetite',            requiresOnset: false },
      { code: 'hepatomegaly',        label: 'Enlarged Liver',              requiresOnset: false },
    ],
  },
  {
    key: 'rash_skin', label: 'Rash & Skin', color: '#6A1B9A',
    symptoms: [
      { code: 'rash_maculopapular',      label: 'Maculopapular Rash',      requiresOnset: true  },
      { code: 'rash_vesicular_pustular', label: 'Vesicular / Pustular Rash', requiresOnset: true  },
      { code: 'rash_face_first',         label: 'Rash Starting on Face',   requiresOnset: true  },
      { code: 'petechial_or_purpuric_rash', label: 'Petechial / Purpuric', requiresOnset: false },
      { code: 'painful_rash',            label: 'Painful Rash',            requiresOnset: false },
      { code: 'skin_eschar',             label: 'Skin Eschar (Black Sore)',  requiresOnset: false },
      { code: 'mucosal_lesions',         label: 'Mouth / Mucosal Lesions',  requiresOnset: false },
    ],
  },
  {
    key: 'hemorrhagic', label: 'Haemorrhagic Signs', color: '#B71C1C',
    symptoms: [
      { code: 'bleeding',              label: 'Bleeding (general)',      requiresOnset: false },
      { code: 'bleeding_gums_or_nose', label: 'Bleeding Gums / Nose',    requiresOnset: false },
      { code: 'bloody_sputum',         label: 'Blood in Sputum / Cough', requiresOnset: false },
    ],
  },
  {
    key: 'neurological', label: 'Neurological', color: '#1B5E20',
    symptoms: [
      { code: 'headache',               label: 'Headache',                requiresOnset: false },
      { code: 'stiff_neck',             label: 'Stiff Neck',              requiresOnset: false },
      { code: 'altered_consciousness',  label: 'Altered Consciousness',   requiresOnset: false },
      { code: 'paralysis_acute_flaccid',label: 'Sudden Paralysis (AFP)',  requiresOnset: false },
      { code: 'seizures',               label: 'Seizures',                requiresOnset: false },
      { code: 'hydrophobia',            label: 'Fear of Water',           requiresOnset: false },
      { code: 'aerophobia',             label: 'Fear of Air',             requiresOnset: false },
    ],
  },
  {
    key: 'other', label: 'Other Signs', color: '#546E7A',
    symptoms: [
      { code: 'muscle_pain',             label: 'Muscle / Body Pain',      requiresOnset: false },
      { code: 'joint_pain',              label: 'Joint Pain',              requiresOnset: false },
      { code: 'severe_joint_pain',       label: 'Severe Joint Pain',       requiresOnset: false },
      { code: 'swollen_lymph_nodes',     label: 'Swollen Lymph Nodes',     requiresOnset: false },
      { code: 'retroauricular_lymph_nodes', label: 'Swollen Neck/Ear Nodes', requiresOnset: false },
      { code: 'conjunctivitis',          label: 'Red Eyes / Conjunctivitis', requiresOnset: false },
      { code: 'loss_of_taste_smell',     label: 'Loss of Taste / Smell',   requiresOnset: false },
    ],
  },
]

// ─── FLAG MESSAGES ────────────────────────────────────────────────────────
const FLAG_MESSAGES = {
  NEEDS_IMMEDIATE_ISOLATION:       '⚠ IMMEDIATE ISOLATION REQUIRED',
  NEEDS_IHR_NOTIFICATION:          '⚠ IHR NOTIFICATION REQUIRED',
  NEEDS_EMERGENCY_REFERRAL:        '⚠ EMERGENCY REFERRAL — DO NOT DELAY',
  NEEDS_PUBLIC_HEALTH_NOTIFICATION: '⚠ PUBLIC HEALTH NOTIFICATION REQUIRED',
  VHF_PROTOCOL_ACTIVATED:          '🔴 VHF PROTOCOL ACTIVATED — Full PPE & Isolation',
  AFP_SURVEILLANCE_ACTIVATED:      '⚠ AFP SURVEILLANCE ACTIVATED — Stool specimens × 2',
  CHOLERA_PROTOCOL_ACTIVATED:      '⚠ CHOLERA PROTOCOL — Aggressive rehydration',
  RABIES_PROTOCOL_ACTIVATED:       '🔴 RABIES PROTOCOL — Emergency referral immediately',
  BIOTERRORISM_PROTOCOL_ACTIVATED: '🔴 BIOTERRORISM PROTOCOL — Maximum isolation + WHO',
  PREGNANCY_RISK_FLAG:             '⚠ PREGNANCY RISK — Immediate referral if pregnant',
}

// ─── COMPUTED ──────────────────────────────────────────────────────────────
const todayDate = computed(() => new Date().toISOString().slice(0, 10))

const priorityClass = computed(() => {
  const p = notification.value?.priority
  if (p === 'CRITICAL') return 'sc-prio-pill--critical'
  if (p === 'HIGH')     return 'sc-prio-pill--high'
  return 'sc-prio-pill--normal'
})

// Expose navigator.onLine to template
const isOnline = computed(() => navigator.onLine)

// Admin gate — only NATIONAL_ADMIN and POE_ADMIN can open the debug panel
const isAdmin = computed(() => {
  const role = getAuth()?.role_key ?? ''
  return role === 'NATIONAL_ADMIN' || role === 'POE_ADMIN'
})

// Debug panel — hidden by default, unlocked by 5 rapid taps on the sync pill (admin only)
const debugPanelOpen = ref(false)
let _debugTapCount = 0
let _debugTapTimer = null
function _debugTap() {
  _debugTapCount++
  clearTimeout(_debugTapTimer)
  _debugTapTimer = setTimeout(() => { _debugTapCount = 0 }, 2000)
  if (_debugTapCount >= 5) {
    _debugTapCount = 0
    debugPanelOpen.value = !debugPanelOpen.value
    L.info(`Admin debug panel ${debugPanelOpen.value ? 'OPENED' : 'CLOSED'}`)
  }
}

// Track whether auto-syndrome was applied this session (for the "Auto" badge in UI)
const autoSyndromeApplied = ref(false)

// Officer override state — allows officer to disagree with algorithm
const officerOverride = reactive({
  syndromeOverridden: false,   // officer manually changed syndrome
  riskOverridden:     false,   // officer manually changed risk level
  overrideNonCase:    false,   // officer disagrees with non-case verdict
  overrideNote:       '',      // mandatory justification text
  customDiseaseInput: '',      // disease id selected from catalog dropdown
  addedDiseases:      [],      // officer-added suspected diseases
})

// Expanded disease definition panel
const expandedDiseaseId = ref(null)

// Helper to get WHO case definition from intelligence layer
function getDiseaseDefinition(diseaseId) {
  return window.DISEASES?.getWHOCaseDefinition?.(diseaseId) || null
}

// ─── DISEASE NAME RESOLVER ────────────────────────────────────────────────────
// top_diagnoses.name comes from the engine at analysis time.
// For IDB-resumed records (only disease_code stored), look up via DISEASES array.
function diseaseName(id) {
  if (!id) return '—'
  const d = window.DISEASES?.diseases?.find(x => x.id === id)
  if (d?.name) return d.name
  return id.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

// ─── DISEASE DETAIL MODAL ─────────────────────────────────────────────────────
const selectedDiseaseModal = ref(null)

function openDiseaseModal(d) {
  if (!d) return
  selectedDiseaseModal.value = {
    disease_id:               d.disease_id        || d.disease_code  || '',
    name:                     d.name              || d.display_name  || diseaseName(d.disease_id || d.disease_code || ''),
    final_score:              d.final_score        ?? null,
    confidence_band:          d.confidence_band   || null,
    ihr_category:             d.ihr_category      || null,
    cfr_pct:                  d.cfr_pct           ?? null,
    probability_like_percent: d.probability_like_percent ?? null,
    matched_hallmarks:        d.matched_hallmarks  || [],
    score_breakdown:          d.score_breakdown    || null,
    syndrome_matched:         d.syndrome_matched   || null,
    who_def:                  getDiseaseDefinition(d.disease_id || d.disease_code || ''),
  }
}

// All diseases from the catalog, excluding those already added by the officer
const availableOverrideDiseases = computed(() => {
  const all = window.DISEASES?.diseases || []
  const added = new Set(officerOverride.addedDiseases)
  return all.filter(d => !added.has(d.id)).sort((a, b) => a.name.localeCompare(b.name))
})

// Officer-added disease — uses disease ID from catalog dropdown (not free text)
function addOfficerSuspectedDisease() {
  const id = officerOverride.customDiseaseInput.trim()
  if (!id) return
  // Validate against the catalog — only catalog diseases are accepted
  const exists = (window.DISEASES?.diseases || []).some(d => d.id === id)
  if (!exists) return
  if (!officerOverride.addedDiseases.includes(id)) {
    officerOverride.addedDiseases.push(id)
  }
  officerOverride.customDiseaseInput = ''
}

const syncPillClass = computed(() => {
  if (!caseRecord.value) return 'sc-sync-pill--offline'
  return caseRecord.value.sync_status === SYNC.SYNCED ? 'sc-sync-pill--ok' : 'sc-sync-pill--pending'
})
const syncPillLabel = computed(() => {
  if (!caseRecord.value) return 'New'
  return SYNC.LABELS[caseRecord.value.sync_status] || caseRecord.value.sync_status
})

const presentSymptomCount = computed(() =>
  Object.values(symptomsMap).filter(s => s.is_present === 1).length
)


const criticalFlags = computed(() => {
  if (!analysisResult.value) return []
  return analysisResult.value.global_flags.filter(f =>
    ['VHF_PROTOCOL_ACTIVATED','AFP_SURVEILLANCE_ACTIVATED','CHOLERA_PROTOCOL_ACTIVATED',
     'RABIES_PROTOCOL_ACTIVATED','BIOTERRORISM_PROTOCOL_ACTIVATED',
     'NEEDS_IMMEDIATE_ISOLATION','NEEDS_IHR_NOTIFICATION','NEEDS_EMERGENCY_REFERRAL'].includes(f)
  )
})

// Alert preview — computed reactively from risk_level + syndrome
const alertPreview = computed(() => {
  const rl  = caseDecision.risk_level
  const syn = caseDecision.syndrome_classification
  if (!rl) return null

  const PRIORITY1 = [
    'cholera','pneumonic_plague','bubonic_plague',
    'ebola_virus_disease','marburg_virus_disease','lassa_fever',
    'cchf','yellow_fever','mpox','smallpox','rift_valley_fever',
  ]
  const NATIONAL_DISEASES = [
    'ebola_virus_disease','marburg_virus_disease','pneumonic_plague','smallpox',
  ]

  // Use the intelligence layer result when available (FIX — was hardcoded)
  const ihrRisk = analysisResult.value?.ihr_risk
  if (ihrRisk?.ihr_alert_required) {
    const topRule = ihrRisk.triggered_rules?.[0] || null
    return {
      alertCode:  topRule || ('CASE_' + rl),
      routedTo:   ihrRisk.routing_level || 'PHEOC',
      riskLevel:  rl,
      ihrTier:    ihrRisk.ihr_tier,
    }
  }

  // Fallback: legacy hardcoded alert preview when intelligence result not present
  const topDisease  = suspectedDiseases.value[0]?.disease_code ?? null
  const isPriority1 = PRIORITY1.includes(topDisease)
  const isNational  = NATIONAL_DISEASES.includes(topDisease)

  let triggered  = false
  let alertCode  = ''
  let routedTo   = 'DISTRICT'

  if (rl === 'CRITICAL') {
    triggered = true; alertCode = 'CRITICAL_RISK_CASE'; routedTo = 'PHEOC'
  } else if (rl === 'HIGH' && (syn === 'VHF' || syn === 'MENINGITIS')) {
    triggered = true; alertCode = 'HIGH_RISK_' + syn; routedTo = 'PHEOC'
  } else if (isPriority1 && rl === 'HIGH') {
    triggered = true; alertCode = 'PRIORITY1_' + (topDisease || '').toUpperCase(); routedTo = 'PHEOC'
  } else if (rl === 'HIGH') {
    triggered = true; alertCode = 'HIGH_RISK_' + (syn || 'CASE'); routedTo = 'DISTRICT'
  }

  if (isNational && triggered) routedTo = 'NATIONAL'
  if (!triggered) return null

  return { alertCode, routedTo, riskLevel: rl }
})

const highRiskActionDone = computed(() => {
  const rl = caseDecision.risk_level
  if (rl !== 'HIGH' && rl !== 'CRITICAL') return true
  return isActionDone('ISOLATED') || isActionDone('REFERRED_HOSPITAL')
})

const canDisposition = computed(() =>
  !!caseDecision.syndrome_classification &&
  !!caseDecision.risk_level &&
  !!caseDecision.final_disposition &&
  actions.value.filter(a => a.is_done === 1).length > 0 &&
  highRiskActionDone.value
)

// Vital sign warnings
const tempWarning = computed(() => {
  const v = vitals.temperature_value
  const u = vitals.temperature_unit
  if (v == null) return ''
  const c = u === 'F' ? (v - 32) * 5 / 9 : v
  if (c < 35)  return '❄ Hypothermia — verify reading'
  if (c >= 40) return '🔴 Dangerous fever — consider EMERGENCY'
  if (c >= 39) return '⚠ High fever — consider URGENT/EMERGENCY'
  if (c >= 38) return '⚠ Fever'
  if (c >= 37.5) return 'Low-grade fever — document'
  return ''
})
const tempWarnClass = computed(() => {
  const v = vitals.temperature_value
  const u = vitals.temperature_unit
  if (v == null) return ''
  const c = u === 'F' ? (v - 32) * 5 / 9 : v
  if (c >= 39 || c < 35) return 'sc-vt-warn--crit'
  if (c >= 37.5) return 'sc-vt-warn--warn'
  return ''
})
const pulseWarning = computed(() => {
  const p = vitals.pulse_rate
  if (!p) return ''
  if (p < 40) return '🔴 Critically low — verify'
  if (p >= 150) return '🔴 Severe tachycardia — EMERGENCY'
  if (p >= 101) return '⚠ Tachycardia'
  if (p < 60) return '⚠ Bradycardia'
  return ''
})
const rrWarning = computed(() => {
  const r = vitals.respiratory_rate
  if (!r) return ''
  if (r < 10) return '⚠ Abnormally low — verify'
  if (r >= 30) return '🔴 Severe — consider EMERGENCY'
  if (r >= 21) return '⚠ Elevated'
  return ''
})
const spo2Warning = computed(() => {
  const s = vitals.oxygen_saturation
  if (!s) return ''
  if (s < 90) return '🔴 Critically low SpO₂ — EMERGENCY'
  if (s < 95) return '⚠ Low SpO₂ — supplemental oxygen recommended'
  return ''
})
const spo2WarnClass = computed(() => {
  const s = vitals.oxygen_saturation
  if (!s) return ''
  if (s < 90) return 'sc-vt-warn--crit'
  if (s < 95) return 'sc-vt-warn--warn'
  return ''
})

// ─── SYMPTOM HELPERS ──────────────────────────────────────────────────────
function initSymptoms() {
  for (const grp of SYMPTOM_GROUPS) {
    for (const sym of grp.symptoms) {
      if (!symptomsMap[sym.code]) {
        symptomsMap[sym.code] = {
          client_uuid:            genUUID(),
          secondary_screening_id: caseUuid.value,
          symptom_code:           sym.code,
          is_present:             null,   // null = not yet assessed (stored as 0 on save)
          onset_date:             null,
          details:                null,
          sync_status:            SYNC.UNSYNCED,
        }
      }
    }
  }
}

function symState(code) {
  return symptomsMap[code]?.is_present ?? null
}

function toggleSymptom(code) {
  if (!symptomsMap[code]) return
  const current = symptomsMap[code].is_present
  // null → 1 → 0 → null (cycle: unassessed → present → absent → unassessed)
  if (current === null) symptomsMap[code].is_present = 1
  else if (current === 1) symptomsMap[code].is_present = 0
  else symptomsMap[code].is_present = null
}

function getSymptomRecord(code) {
  return symptomsMap[code] || {}
}

function buildSymptomRecords() {
  return Object.values(symptomsMap)
    .filter(s => s.is_present !== null)
    .map(s => ({
      ...s,
      secondary_screening_id: caseUuid.value,
      sync_status:            SYNC.UNSYNCED,
    }))
}

// ─── TRAVEL COUNTRIES HELPERS ─────────────────────────────────────────────
function addTravelCountry() {
  travelCountries.value.push({
    client_uuid:            genUUID(),
    secondary_screening_id: caseUuid.value,
    country_code:           '',
    travel_role:            'VISITED',
    arrival_date:           null,
    departure_date:         null,
    sync_status:            SYNC.UNSYNCED,
  })
}

function removeTravelCountry(idx) {
  travelCountries.value.splice(idx, 1)
}

// ─── ACTIONS HELPERS ──────────────────────────────────────────────────────
function isActionDone(code) {
  return actions.value.some(a => a.action_code === code && a.is_done === 1)
}

function toggleAction(code) {
  const idx = actions.value.findIndex(a => a.action_code === code)
  if (idx === -1) {
    actions.value.push({
      client_uuid:            genUUID(),
      secondary_screening_id: caseUuid.value,
      action_code:            code,
      is_done:                1,
      details:                null,
      sync_status:            SYNC.UNSYNCED,
    })
  } else {
    actions.value[idx].is_done = actions.value[idx].is_done === 1 ? 0 : 1
  }
}

// ─── GENDER LABEL ─────────────────────────────────────────────────────────
function genderLabel(code) {
  // Legacy values shown as '—' for any non-MALE/FEMALE legacy data
  return { MALE: 'Male', FEMALE: 'Female', OTHER: '—', UNKNOWN: '—' }[code] || '—'
}

// ─── STEP NAVIGATION ──────────────────────────────────────────────────────
function jumpToStep(target) {
  // Only allow jumping to completed steps or current step
  if (target <= step.value) step.value = target
}

async function goBackStep() {
  if (step.value > 1) step.value--
}

function goBackToQueue() {
  // Blur THEN navigate, deferred to the next event-loop turn.
  // Root cause of the aria-hidden warning:
  //   1. User taps back button
  //   2. click fires → goBackToQueue runs → blur() called
  //   3. BUT the browser's focus event for the tap fires AFTER click,
  //      so blur() hit no focused element — the button focuses right after
  //   4. router.back() fires → Ionic starts hiding the page
  //   5. Ionic sets aria-hidden on the outgoing page while the button
  //      still holds focus → accessibility violation logged
  // Wrapping in setTimeout(0) defers past both the click and the focus event,
  // so the button is focused THEN immediately blurred THEN the page leaves.
  setTimeout(() => {
    if (document.activeElement instanceof HTMLElement) {
      document.activeElement.blur()
    }
    router.back()
  }, 0)
}

// ─── STEP 1 SAVE ──────────────────────────────────────────────────────────
async function saveStep1AndNext() {
  const localAuth = getAuth()
  if (!localAuth?.id || !localAuth?.is_active) {
    alert('Session expired. Please log in again.')
    return
  }

  // Gender validation — only MALE or FEMALE allowed
  if (profile.traveler_gender !== 'MALE' && profile.traveler_gender !== 'FEMALE') {
    alert('Please select Male or Female for the traveler gender.')
    return
  }

  saving.value = true
  try {
    // Ensure case exists (create if first save)
    if (!caseRecord.value) {
      await openCase(localAuth)
    }

    // Build updated case record
    const now     = isoNow()
    const updated = {
      ...caseRecord.value,
      // Profile fields
      traveler_full_name:                profile.traveler_full_name   || null,
      traveler_gender:                   profile.traveler_gender,
      traveler_age_years:                profile.traveler_age_years   || null,
      travel_document_type:              profile.travel_document_type || null,
      travel_document_number:            profile.travel_document_number || null,
      traveler_nationality_country_code: profile.traveler_nationality_country_code || null,
      residence_country_code:            profile.residence_country_code || null,
      phone_number:                      profile.phone_number         || null,
      journey_start_country_code:        profile.journey_start_country_code || null,
      conveyance_type:                   profile.conveyance_type      || null,
      conveyance_identifier:             profile.conveyance_identifier || null,
      arrival_datetime:                  profile.arrival_datetime_input
                                           ? profile.arrival_datetime_input.replace('T', ' ') + ':00'
                                           : null,
      purpose_of_travel:                 profile.purpose_of_travel    || null,
      destination_district_code:         profile.destination_district_code || null,
      case_status:                       'IN_PROGRESS',
      record_version:                    (caseRecord.value.record_version || 1) + 1,
      updated_at:                        now,
    }

    // Travel countries: assign correct secondary_screening_id
    const tcRecords = travelCountries.value
      .filter(tc => tc.country_code)
      .map(tc => ({
        ...tc,
        secondary_screening_id: caseUuid.value,
        sync_status:            SYNC.UNSYNCED,
      }))

    // Write atomically: case update + travel countries replace-all
    await safeDbPut(STORE.SECONDARY_SCREENINGS, toPlain(updated))
    await dbReplaceAll(
      STORE.SECONDARY_TRAVEL_COUNTRIES,
      'secondary_screening_id',
      caseUuid.value,
      toPlain(tcRecords)
    )

    caseRecord.value = updated
    step.value = 2
    // Sync to server in background — IDB write already committed above
    const localAuthStep1 = getAuth()
    syncCaseToServer(localAuthStep1).catch(e => L.warn('saveStep1: syncCaseToServer threw', e?.message ?? e))
  } catch (err) {
    console.error('[SecondaryScreening] saveStep1AndNext error:', err)
    alert('Failed to save. Please try again.')
  } finally {
    saving.value = false
  }
}

// ─── STEP 2 SAVE ──────────────────────────────────────────────────────────
async function saveStep2AndNext() {
  const localAuth = getAuth()
  if (!localAuth?.id || !localAuth?.is_active) {
    alert('Session expired. Please log in again.')
    return
  }

  saving.value = true
  try {
    const now     = isoNow()
    const updated = {
      ...caseRecord.value,
      // Vitals (only if showVitals and values entered)
      temperature_value:       showVitals.value ? vitals.temperature_value : caseRecord.value.temperature_value,
      temperature_unit:        showVitals.value ? (vitals.temperature_value ? vitals.temperature_unit : null) : caseRecord.value.temperature_unit,
      pulse_rate:              showVitals.value ? vitals.pulse_rate         : caseRecord.value.pulse_rate,
      respiratory_rate:        showVitals.value ? vitals.respiratory_rate   : caseRecord.value.respiratory_rate,
      bp_systolic:             showVitals.value ? vitals.bp_systolic        : caseRecord.value.bp_systolic,
      bp_diastolic:            showVitals.value ? vitals.bp_diastolic       : caseRecord.value.bp_diastolic,
      oxygen_saturation:       showVitals.value ? vitals.oxygen_saturation  : caseRecord.value.oxygen_saturation,
      triage_category:         showVitals.value ? (vitals.triage_category || null) : caseRecord.value.triage_category,
      emergency_signs_present: showVitals.value ? vitals.emergency_signs_present : caseRecord.value.emergency_signs_present,
      general_appearance:      showVitals.value ? (vitals.general_appearance || null) : caseRecord.value.general_appearance,
      case_status:             'IN_PROGRESS',
      record_version:          (caseRecord.value.record_version || 1) + 1,
      updated_at:              now,
    }

    // Symptom records for child table
    const symptomRecords = buildSymptomRecords()

    await safeDbPut(STORE.SECONDARY_SCREENINGS, toPlain(updated))
    await dbReplaceAll(
      STORE.SECONDARY_SYMPTOMS,
      'secondary_screening_id',
      caseUuid.value,
      toPlain(symptomRecords)
    )

    caseRecord.value = updated
    step.value = 3
    // Sync to server in background — IDB write already committed above
    const localAuthStep2 = getAuth()
    syncCaseToServer(localAuthStep2).catch(e => L.warn('saveStep2: syncCaseToServer threw', e?.message ?? e))
  } catch (err) {
    console.error('[SecondaryScreening] saveStep2AndNext error:', err)
    alert('Failed to save symptoms. Please try again.')
  } finally {
    saving.value = false
  }
}

// ─── STEP 3 SAVE + ANALYSE ────────────────────────────────────────────────
async function saveStep3AndAnalyse() {
  const localAuth = getAuth()
  if (!localAuth?.id || !localAuth?.is_active) {
    alert('Session expired. Please log in again.')
    return
  }

  // ── EXPOSURE VALIDATION ─────────────────────────────────────────────────
  // YES, NO, and UNKNOWN are all valid clinical responses.
  // UNKNOWN means the traveler genuinely cannot recall or a language barrier
  // prevented assessment — this is a legitimate answer, not "skipped".
  // The AI engine treats UNKNOWN as an uncertainty factor in risk scoring.

  saving.value = true
  try {
    // ── Build exposure records from window.EXPOSURES catalog ───────────────
    // exposuresMap is keyed by exposure code — get all that have a response
    const exposureRecords = Object.values(exposuresMap).map(exp => ({
      client_uuid:            genUUID(),
      secondary_screening_id: caseUuid.value,
      exposure_code:          exp.exposure_code,
      response:               exp.response || 'UNKNOWN',
      details:                exp.details || null,
      sync_status:            SYNC.UNSYNCED,
    }))

    // Save exposures to IDB
    await dbReplaceAll(
      STORE.SECONDARY_EXPOSURES,
      'secondary_screening_id',
      caseUuid.value,
      toPlain(exposureRecords)
    )

    // ── INTELLIGENCE LAYER — single call does everything ─────────────────────
    // FIX: uses window.DISEASES.getEnhancedScoreResult() which:
    //   1. Builds outbreak_context from travel countries (endemic oracle)
    //   2. Derives WHO syndrome from symptoms
    //   3. Runs scoreDiseases() with syndrome_bonus active
    //   4. Computes IHR risk level per Annex 2
    //   5. Evaluates non-case verdict
    //   6. Validates clinical data
    // No clinical logic in this Vue file.
    const presentSymptoms = Object.values(symptomsMap).filter(s => s.is_present === 1).map(s => s.symptom_code)
    const absentSymptoms  = Object.values(symptomsMap).filter(s => s.is_present === 0).map(s => s.symptom_code)

    // Translate DB exposure codes → engine codes via exposures.js
    const engineExposureCodes = window.EXPOSURES?.mapToEngineCodes(exposureRecords) || []

    // Vitals for engine (convert F→C)
    const tc = vitals.temperature_value
      ? (vitals.temperature_unit === 'F' ? (vitals.temperature_value - 32) * 5 / 9 : vitals.temperature_value)
      : null
    const vitalsForEng = {
      temperature_c:     tc,
      oxygen_saturation: vitals.oxygen_saturation || undefined,
      pulse_rate:        vitals.pulse_rate         || undefined,
      respiratory_rate:  vitals.respiratory_rate   || undefined,
      bp_systolic:       vitals.bp_systolic         || undefined,
    }

    let enhanced = null
    try {
      if (window.DISEASES?.getEnhancedScoreResult) {
        enhanced = window.DISEASES.getEnhancedScoreResult(
          presentSymptoms,
          absentSymptoms,
          engineExposureCodes,
          travelCountries.value.map(tc => ({ country_code: tc.country_code, travel_role: tc.travel_role || 'VISITED' })),
          vitalsForEng
        )
        L.ok('getEnhancedScoreResult OK', {
          syndrome: enhanced.syndrome.syndrome,
          is_non_case: enhanced.is_non_case,
          risk: enhanced.ihr_risk.risk_level,
          top_disease: enhanced.top_disease_id,
          outbreak_context: enhanced.outbreak_context_used.length,
        })
      } else {
        // Fallback if intelligence layer not loaded
        const fallback = window.DISEASES?.scoreDiseases?.(presentSymptoms, absentSymptoms, engineExposureCodes, {}) || { top_diagnoses: [], all_reportable: [], global_flags: [], overrides_fired: [], input_summary: {} }
        enhanced = {
          ...fallback,
          syndrome: { syndrome: 'OTHER', confidence: 'LOW', reasoning: 'Intelligence layer not loaded', who_criteria_met: [] },
          ihr_risk: { risk_level: 'MEDIUM', routing_level: 'DISTRICT', ihr_alert_required: false, ihr_tier: null, triggered_rules: [], reasoning: ['Fallback mode'] },
          non_case: { isNonCase: false, reasons: [], recommended_syndrome: null, recommended_disposition: null },
          clinical_validation: { vital_alerts: {}, critical_flags: [], clinical_warnings: [], needs_emergency_triage: false },
          outbreak_context_used: [],
          is_non_case: false,
          show_emergency_banner: false,
        }
        L.warn('getEnhancedScoreResult not available — using fallback scoreDiseases')
      }
    } catch (scoreErr) {
      L.warn('saveStep3AndAnalyse: intelligence call threw', scoreErr?.message ?? scoreErr)
      enhanced = { top_diagnoses: [], all_reportable: [], global_flags: ['ANALYSIS_ERROR'], overrides_fired: [], input_summary: {}, syndrome: { syndrome: 'OTHER', confidence: 'LOW' }, ihr_risk: { risk_level: 'MEDIUM', routing_level: 'DISTRICT', ihr_alert_required: false }, non_case: { isNonCase: false }, clinical_validation: { vital_alerts: {}, critical_flags: [], clinical_warnings: [], needs_emergency_triage: false }, outbreak_context_used: [], is_non_case: false, show_emergency_banner: false }
    }

    analysisResult.value = enhanced

    // ── GENERATE CLINICAL REPORT ───────────────────────────────────────────
    // The report explains every decision the engine made in plain language,
    // covering 10 sections: executive summary, clinical presentation, scoring
    // breakdown, travel/epidemiology, exposure analysis, IHR framework,
    // differential diagnosis, required actions, confidence assessment, overrides.
    try {
      if (window.DISEASES?.generateClinicalReport) {
        const tc = vitals.temperature_value
        const tcC = tc ? (vitals.temperature_unit === 'F' ? (tc - 32) * 5 / 9 : tc) : null
        clinicalReport.value = window.DISEASES.generateClinicalReport(enhanced, {
          vitals: {
            temperature_c:     tcC,
            oxygen_saturation: vitals.oxygen_saturation  || null,
            pulse_rate:        vitals.pulse_rate          || null,
            respiratory_rate:  vitals.respiratory_rate   || null,
            bp_systolic:       vitals.bp_systolic         || null,
          },
          presentSymptoms:   presentSymptoms,
          absentSymptoms:    absentSymptoms,
          visitedCountries:  travelCountries.value.map(tc => ({
            country_code: tc.country_code, travel_role: tc.travel_role || 'VISITED'
          })),
          exposures:         Object.values(exposuresMap),
          travelerDirection: primaryScreening.value?.traveler_direction || null,
          poeCode:           caseRecord.value?.poe_code || '',
          travelerGender:    profile.traveler_gender || caseRecord.value?.traveler_gender || 'UNKNOWN',
          travelerAge:       profile.traveler_age_years || null,
          officerOverride:   { ...officerOverride },
        })
        L.ok('generateClinicalReport: OK — verdict=' + clinicalReport.value.verdict)
      }
    } catch (reportErr) {
      L.warn('generateClinicalReport threw', reportErr?.message ?? reportErr)
      clinicalReport.value = null
    }

    // ── AUTO-SET syndrome + risk from engine (officer can override in Step 4) ──
    if (!caseDecision.syndrome_classification || !officerOverride.syndromeOverridden) {
      caseDecision.syndrome_classification = enhanced.syndrome.syndrome
      autoSyndromeApplied.value = true
      L.ok('Auto-syndrome set to "' + enhanced.syndrome.syndrome + '" (confidence=' + enhanced.syndrome.confidence + ')')
    }

    // Auto-set risk level suggestion (officer can still change it)
    if (!officerOverride.riskOverridden && enhanced.ihr_risk?.risk_level) {
      caseDecision.risk_level = enhanced.ihr_risk.risk_level
    }

    // NON-CASE auto-routing
    if (enhanced.is_non_case && !officerOverride.overrideNonCase) {
      caseDecision.syndrome_classification = 'NONE'
      caseDecision.risk_level              = 'LOW'
      caseDecision.final_disposition       = 'RELEASED'
      L.ok('Non-case verdict applied — syndrome=NONE, risk=LOW, disposition=RELEASED')
    }

    // ── Build suspected disease records for DB (merge algorithm + officer additions) ──
    const algorithmDiseases = enhanced.top_diagnoses.slice(0, 5).map((d, i) => ({
      client_uuid:            genUUID(),
      secondary_screening_id: caseUuid.value,
      disease_code:           d.disease_id,
      display_name:           d.name || diseaseName(d.disease_id),
      rank_order:             i + 1,
      confidence:             d.probability_like_percent ?? null,
      confidence_band:        d.confidence_band  || null,
      final_score:            d.final_score      ?? null,
      ihr_category:           d.ihr_category     || null,
      reasoning:              (d.matched_hallmarks || []).slice(0, 3).join(', ') || null,
      sync_status:            SYNC.UNSYNCED,
    }))

    // Officer-added diseases at the end
    const officerDiseases = officerOverride.addedDiseases.map((name, i) => ({
      client_uuid:            genUUID(),
      secondary_screening_id: caseUuid.value,
      disease_code:           name.toUpperCase().replace(/\s+/g, '_').slice(0, 80),
      rank_order:             algorithmDiseases.length + i + 1,
      confidence:             null,
      reasoning:              'OFFICER_CLINICAL_OVERRIDE',
      sync_status:            SYNC.UNSYNCED,
    }))

    suspectedDiseases.value = [...algorithmDiseases, ...officerDiseases]

    step.value = 4
    // Sync exposures + current case to server in background
    const localAuthStep3 = getAuth()
    syncCaseToServer(localAuthStep3).catch(e => L.warn('saveStep3: syncCaseToServer threw', e?.message ?? e))
  } catch (err) {
    console.error('[SecondaryScreening] saveStep3AndAnalyse error:', err)
    alert('Failed to save exposures. Please try again.')
  } finally {
    saving.value = false
  }
}

// ─── STEP 4 DISPOSITION ───────────────────────────────────────────────────
async function dispositionCase() {
  const localAuth = getAuth()
  if (!localAuth?.id || !localAuth?.is_active) {
    alert('Session expired. Please log in again.')
    return
  }

  // Clear previous errors
  fieldErrors.syndrome_classification = ''
  fieldErrors.risk_level              = ''
  fieldErrors.final_disposition       = ''
  fieldErrors.actions                 = ''

  // Validate
  let valid = true
  if (!caseDecision.syndrome_classification) {
    fieldErrors.syndrome_classification = 'Syndrome classification is required.'
    valid = false
  }
  if (!caseDecision.risk_level) {
    fieldErrors.risk_level = 'Risk level is required.'
    valid = false
  }
  if (!caseDecision.final_disposition) {
    fieldErrors.final_disposition = 'Final disposition is required.'
    valid = false
  }
  if (actions.value.filter(a => a.is_done === 1).length === 0) {
    fieldErrors.actions = 'At least one action must be recorded.'
    valid = false
  }
  if (!highRiskActionDone.value) {
    fieldErrors.actions = 'Risk level HIGH/CRITICAL requires ISOLATED or REFERRED_HOSPITAL action.'
    valid = false
  }
  if (!valid) return

  saving.value = true
  try {
    const now         = isoNow()
    const doneActions = actions.value.filter(a => a.is_done === 1)

    // Determine final case status:
    //   CLOSED       — no follow-up required. Server closes notification atomically.
    //   DISPOSITIONED — follow-up required. Supervisor closes it separately.
    // The server's fullSync only runs the notification→CLOSED transition when
    // case_status = 'CLOSED'. Sending 'DISPOSITIONED' leaves notification IN_PROGRESS.
    const needsFollowup   = !!caseDecision.followup_required
    const finalCaseStatus = needsFollowup ? 'DISPOSITIONED' : 'CLOSED'

    const updatedCase = {
      ...caseRecord.value,
      syndrome_classification:  caseDecision.syndrome_classification,
      risk_level:               caseDecision.risk_level,
      final_disposition:        caseDecision.final_disposition,
      officer_notes:            caseDecision.officer_notes || null,
      followup_required:        needsFollowup ? 1 : 0,
      followup_assigned_level:  needsFollowup ? (caseDecision.followup_assigned_level || null) : null,
      case_status:              finalCaseStatus,
      dispositioned_at:         now,
      closed_at:                needsFollowup ? null : now,
      record_version:           (caseRecord.value.record_version || 1) + 1,
      updated_at:               now,
    }

    // Update notification in IDB:
    //   ALWAYS close the notification when the case is dispositioned.
    //   The referral is resolved once the officer has made their clinical
    //   decision. Follow-up is a separate operational process that does not
    //   keep the referral queue item open.
    const updatedNotif = {
      ...notification.value,
      status:         'CLOSED',
      closed_at:      now,
      sync_status:    SYNC.UNSYNCED,
      record_version: (notification.value.record_version || 1) + 1,
      updated_at:     now,
    }

    // All writes: case + actions + suspected diseases + notification
    // ── FIX: ATOMIC WRITE — case + notification in one transaction ──────────
    // Previously: two separate safeDbPut calls. If the second failed or the
    // app crashed between them, the case showed CLOSED but the notification
    // stayed IN_PROGRESS — causing data corruption in NotificationsCenter.
    // Fix: dbAtomicWrite guarantees both writes succeed or neither does.
    await dbAtomicWrite([
      { store: STORE.SECONDARY_SCREENINGS, record: toPlain(updatedCase) },
      { store: STORE.NOTIFICATIONS,        record: toPlain(updatedNotif) },
    ])

    // Child tables (non-critical — can fail without corrupting the primary state)
    await dbReplaceAll(
      STORE.SECONDARY_ACTIONS,
      'secondary_screening_id',
      caseUuid.value,
      toPlain(doneActions.map(a => ({ ...a, secondary_screening_id: caseUuid.value })))
    )

    await dbReplaceAll(
      STORE.SECONDARY_SUSPECTED_DISEASES,
      'secondary_screening_id',
      caseUuid.value,
      toPlain(suspectedDiseases.value)
    )

    // ── IHR Alert creation ─────────────────────────────────────────────────
    // Use ihr_alert_required from engine result when available, fall back to
    // legacy alertPreview computed for backward compatibility.
    const ihrAlertNeeded = analysisResult.value?.ihr_notification_required || alertPreview.value
    if (ihrAlertNeeded) {
      // ── IDEMPOTENCY: check if an alert already exists for this case ──
      // The case can be re-dispositioned (e.g., follow-up disposition).
      // We must NOT create a duplicate alert each time. The first alert
      // for a case is the canonical one; subsequent dispositions update
      // the case but do not create new alerts.
      const existingAlerts = await dbGetByIndex(STORE.ALERTS, 'secondary_screening_id', caseUuid.value).catch(() => [])
      const liveAlerts = existingAlerts.filter(a => !a.deleted_at)
      if (liveAlerts.length > 0) {
        L.info('dispositionCase: alert already exists for this case, skipping creation', {
          caseUuid: caseUuid.value,
          existingAlertCount: liveAlerts.length,
          firstAlertUuid: liveAlerts[0].client_uuid,
        })
      } else {
        const alertCode = analysisResult.value?.ihr_risk?.triggered_rules?.[0]
          || alertPreview.value?.alertCode
          || ('CASE_' + (caseDecision.risk_level || 'HIGH'))
        const routedTo  = analysisResult.value?.ihr_risk?.routing_level
          || alertPreview.value?.routedTo
          || 'PHEOC'
        const alertRecord = createRecordBase(localAuth, {
          secondary_screening_id:    caseUuid.value,
          generated_from:            'RULE_BASED',
          risk_level:                caseDecision.risk_level,
          alert_code:                alertCode,
          alert_title:               alertCode.replace(/_/g, ' '),
          alert_details:             caseDecision.officer_notes || null,
          routed_to_level:           routedTo,
          ihr_tier:                  analysisResult.value?.ihr_risk?.ihr_tier || null,
          status:                    'OPEN',
          acknowledged_by_user_id:   null,
          acknowledged_at:           null,
          closed_at:                 null,
        })
        await dbPut(STORE.ALERTS, toPlain(alertRecord))
        L.ok('dispositionCase: alert record created', { alertCode, routedTo })
      }
    }

    caseRecord.value = updatedCase
    notification.value = updatedNotif

    // Await sync before navigating — disposition MUST reach the server.
    // If offline this returns quickly (navigator.onLine check). Data is in IDB.
    try { await syncCaseToServer(localAuth) } catch (e) {
      L.warn('dispositionCase: syncCaseToServer threw — navigating anyway, data safe in IDB', e?.message ?? e)
    }

    router.replace('/NotificationsCenter')
  } catch (err) {
    console.error('[SecondaryScreening] dispositionCase error:', err)
    alert('Failed to save disposition. Please try again.')
  } finally {
    saving.value = false
  }
}

// ─── CASE OPENING ─────────────────────────────────────────────────────────
// Creates a new secondary case and transitions the notification OPEN→IN_PROGRESS.
// openCaseLock prevents two concurrent _doInitPage() calls (onMounted + onIonViewDidEnter)
// from each trying to create a case before either has written to IDB.
async function openCase(localAuth) {
  if (openCaseLock) {
    L.warn('openCase() called while openCaseLock=true — skipping (duplicate concurrent call)')
    return
  }
  openCaseLock = true
  L.info('openCase() START', { user_id: localAuth?.id, notif_status: notification.value?.status })
  try {
    if (!localAuth?.id || !localAuth?.is_active) throw new Error('No auth')
    if (!notification.value) throw new Error('notification.value is null — _doInitPage() must set it before calling openCase')

    const now    = isoNow()
    const gender = primaryScreening.value?.gender || 'UNKNOWN'

    const newCase = createRecordBase(localAuth, {
      primary_screening_id: notification.value.primary_screening_id,
      notification_id:      notificationUuid.value,
      opened_by_user_id:    localAuth.id,
      case_status:          'IN_PROGRESS',
      traveler_gender:      gender,
      opened_at:            now,
      opened_timezone:      Intl.DateTimeFormat().resolvedOptions().timeZone || null,
      dispositioned_at:     null,
      closed_at:            null,
    })

    const updatedNotif = {
      ...notification.value,
      status:           'IN_PROGRESS',
      opened_at:        now,
      assigned_user_id: localAuth.id,
      sync_status:      SYNC.UNSYNCED,
      record_version:   (notification.value.record_version || 1) + 1,
      updated_at:       now,
    }

    await dbAtomicWrite([
      { store: STORE.SECONDARY_SCREENINGS, record: toPlain(newCase)       },
      { store: STORE.NOTIFICATIONS,        record: toPlain(updatedNotif)  },
    ])

    caseUuid.value     = newCase.client_uuid
    caseRecord.value   = newCase
    notification.value = updatedNotif

    initSymptoms()
    initExposuresFromCatalog()
    L.ok('openCase() complete', { caseUuid: newCase.client_uuid, notif_status: updatedNotif.status })

    // Attempt server sync in background — non-blocking, data is safe in IDB
    syncCaseToServer(localAuth).catch(e => L.warn('openCase: syncCaseToServer threw', e?.message ?? e))
  } catch (err) {
    L.err('openCase() FAILED', err)
    throw err   // re-throw so _doInitPage catch block fires
  } finally {
    openCaseLock = false
  }
}

// deriveAutoSyndrome() REMOVED.
// Replaced by window.DISEASES.deriveWHOSyndrome() in the intelligence layer.
// The Vue calls window.DISEASES.getEnhancedScoreResult() which internally
// calls deriveWHOSyndrome() and returns the result — no clinical logic here.

// ─── NOTIFICATION VERIFICATION ────────────────────────────────────────────
const notifVerify = reactive({
  running: false, ran: false,
  idb: null, server: null, error: null, checks: [],
  _serverCase: null, _serverFetchStatus: null,
})

async function verifyNotificationState() {
  notifVerify.running = true
  notifVerify.ran     = false
  notifVerify.error   = null
  notifVerify.checks  = []
  notifVerify.idb     = null
  notifVerify.server  = null
  notifVerify._serverCase        = null
  notifVerify._serverFetchStatus = null

  const uuid = notificationUuid.value
  if (!uuid) {
    notifVerify.error = 'No notification UUID — page not fully loaded'
    notifVerify.running = false; return
  }

  // 1. Read IDB
  try {
    const rec = await dbGet(STORE.NOTIFICATIONS, uuid)
    notifVerify.idb = rec ? JSON.parse(JSON.stringify(rec)) : null
  } catch (e) {
    notifVerify.error = `IDB read failed: ${e?.message ?? e}`
    notifVerify.running = false; return
  }

  // 2. Fetch from server via the secondary screening show endpoint.
  //    GET /secondary-screenings/{id}?user_id={userId} returns the full case
  //    including the embedded notification object. This is the only endpoint
  //    that exposes notification state — there is no standalone GET /notifications/{id}.
  const caseServerId = caseRecord.value?.id ?? caseRecord.value?.server_id ?? null
  const userId       = getAuth()?.id ?? null
  if (caseServerId && userId && navigator.onLine) {
    try {
      const ctrl = new AbortController()
      const tid  = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
      const res  = await fetch(
        `${window.SERVER_URL}/secondary-screenings/${caseServerId}?user_id=${userId}`,
        { headers: { Accept: 'application/json' }, signal: ctrl.signal }
      )
      clearTimeout(tid)
      if (res.ok) {
        const body = await res.json().catch(() => ({}))
        // body.data.notification is the embedded notification row
        notifVerify.server = body?.data?.notification ?? null
        // Also store the server case status for additional checks
        notifVerify._serverCase = body?.data ?? null
      } else {
        notifVerify._serverFetchStatus = res.status
      }
    } catch { /* non-critical */ }
  }

  // 3. Run checks
  const checks = []
  const idb = notifVerify.idb
  const srv = notifVerify.server
  const cr  = caseRecord.value

  checks.push({
    label: 'Notification exists in local IDB',
    pass:  !!idb,
    detail: idb ? `status=${idb.status} sync=${idb.sync_status}` : 'NOT FOUND in IDB',
  })
  checks.push({
    label: 'Notification status = IN_PROGRESS in IDB',
    pass:  idb?.status === 'IN_PROGRESS',
    detail: idb ? `"${idb.status}"` : 'n/a',
  })
  checks.push({
    label: 'Notification has server integer id in IDB',
    pass:  !!(idb?.id > 0),
    detail: idb ? `id=${idb.id ?? 'NULL'}` : 'n/a',
  })
  checks.push({
    label: 'Notification IDB sync_status = SYNCED',
    pass:  idb?.sync_status === SYNC.SYNCED,
    detail: idb ? `"${idb.sync_status}"` : 'n/a',
  })
  checks.push({
    label: 'Secondary case has server id',
    pass:  !!(cr?.id > 0),
    detail: cr ? `case.id=${cr.id ?? 'NULL'} sync="${cr.sync_status}"` : 'no case record',
  })
  checks.push({
    label: 'Secondary case sync_status = SYNCED',
    pass:  cr?.sync_status === SYNC.SYNCED,
    detail: cr ? `"${cr.sync_status}"` : 'n/a',
  })
  if (srv) {
    checks.push({
      label: 'Server notification status = IN_PROGRESS or CLOSED',
      pass:  srv.status === 'IN_PROGRESS' || srv.status === 'CLOSED',
      detail: `server_notification.status="${srv.status}"`,
    })
    checks.push({
      label: 'IDB notification status matches server',
      pass:  srv.status === idb?.status,
      detail: `server="${srv.status}" idb="${idb?.status}"`,
    })
  }
  // Server case status check (from the embedded secondary case fetch)
  if (notifVerify._serverCase) {
    checks.push({
      label: 'Server case status matches local case status',
      pass:  notifVerify._serverCase.case_status === cr?.case_status,
      detail: `server="${notifVerify._serverCase.case_status}" local="${cr?.case_status}"`,
    })
    checks.push({
      label: 'Server case sync_status = SYNCED',
      pass:  notifVerify._serverCase.sync_status === SYNC.SYNCED,
      detail: `server_case.sync_status="${notifVerify._serverCase.sync_status}"`,
    })
  } else if (navigator.onLine) {
    const hint = !caseServerId
      ? 'Case has no server id yet — run Force Sync first'
      : `GET /secondary-screenings/${caseServerId} returned HTTP ${notifVerify._serverFetchStatus ?? 'error'}`
    checks.push({
      label: 'Server case + notification fetch',
      pass:  false,
      detail: hint,
    })
  }

  notifVerify.checks  = checks
  notifVerify.ran     = true
  notifVerify.running = false
}

// ─── SERVER SYNC ENGINE ───────────────────────────────────────────────────
// syncStatus: reactive object powering the on-screen sync test panel.
// Every phase writes here so the officer sees what the server actually said.
const syncStatus = reactive({
  running:       false,
  lastRunAt:     null,   // ISO string
  phase1:        null,   // 'ok' | 'fail' | 'skip' | null
  phase1Msg:     '',
  phase1Payload: null,   // last payload sent (shown in detail panel)
  phase1Resp:    null,   // last server response body
  phase2:        null,   // 'ok' | 'fail' | 'skip' | null
  phase2Msg:     '',
  phase2Payload: null,
  phase2Resp:    null,
  error:         null,   // unhandled error message
})

async function syncCaseToServer(localAuth) {
  syncStatus.running = true
  syncStatus.error   = null
  L.info('syncCaseToServer: START', {
    online:      navigator.onLine,
    caseUuid:    caseUuid.value,
    caseId:      caseRecord.value?.id ?? null,
    caseSyncSt:  caseRecord.value?.sync_status,
    userId:      localAuth?.id,
    SERVER_URL:  window.SERVER_URL,
  })

  try {
    if (!navigator.onLine) {
      syncStatus.phase1 = 'skip'; syncStatus.phase1Msg = 'Device offline'
      syncStatus.phase2 = 'skip'; syncStatus.phase2Msg = 'Device offline'
      L.info('syncCaseToServer: offline — skipping'); return
    }
    if (!caseRecord.value || !caseUuid.value) {
      syncStatus.error = 'No case record loaded'
      L.warn('syncCaseToServer: no caseRecord'); return
    }
    const userId = localAuth?.id
    if (!userId) {
      syncStatus.error = 'No auth user id'
      L.warn('syncCaseToServer: no auth id'); return
    }

    // ── Phase 1: POST /secondary-screenings ─────────────────────────────
    let serverId = caseRecord.value.id ?? caseRecord.value.server_id ?? null

    if (!serverId) {
      syncStatus.phase1 = null; syncStatus.phase1Msg = 'Sending…'
      const notifServerId   = notification.value?.id ?? notification.value?.server_id ?? null
      const primaryServerId = primaryScreening.value?.id ?? primaryScreening.value?.server_id ?? null

      const p1 = {
        opened_by_user_id:      userId,
        client_uuid:            caseRecord.value.client_uuid,
        reference_data_version: caseRecord.value.reference_data_version ?? APP.REFERENCE_DATA_VER,
        notification_id:        notifServerId    ?? notification.value?.client_uuid   ?? caseRecord.value.notification_id,
        primary_screening_id:   primaryServerId  ?? primaryScreening.value?.client_uuid ?? caseRecord.value.primary_screening_id,
        device_id:              caseRecord.value.device_id,
        app_version:            caseRecord.value.app_version ?? APP.VERSION,
        platform:               caseRecord.value.platform    ?? 'WEB',
        traveler_gender:        caseRecord.value.traveler_gender ?? 'UNKNOWN',
        opened_at:              caseRecord.value.opened_at   ?? isoNow(),
        opened_timezone:        caseRecord.value.opened_timezone ?? null,
        record_version:         caseRecord.value.record_version ?? 1,
      }
      syncStatus.phase1Payload = p1
      L.info('syncCaseToServer: Phase 1 payload', p1)

      const ctrl = new AbortController()
      const tid  = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
      try {
        const res  = await fetch(`${window.SERVER_URL}/secondary-screenings`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body:   JSON.stringify(p1),
          signal: ctrl.signal,
        })
        clearTimeout(tid)
        const body = await res.json().catch(() => ({}))
        syncStatus.phase1Resp = body
        L.info('syncCaseToServer: Phase 1 response', { http: res.status, success: body?.success, data: body?.data, error: body?.error })

        if (res.ok && body?.success) {
          serverId = body.data?.id ?? null
          if (serverId) {
            const updated = {
              ...toPlain(caseRecord.value),
              id: serverId, server_id: serverId,
              sync_status: SYNC.SYNCED, synced_at: isoNow(),
              record_version: (caseRecord.value.record_version || 1) + 1,
              updated_at: isoNow(),
            }
            await safeDbPut(STORE.SECONDARY_SCREENINGS, updated)
            caseRecord.value = updated
            syncStatus.phase1 = 'ok'
            syncStatus.phase1Msg = `Created — server_id=${serverId}`
            L.ok(`syncCaseToServer: Phase 1 OK — server_id=${serverId}`)
          } else {
            syncStatus.phase1 = 'fail'
            syncStatus.phase1Msg = 'Server returned success=true but no id in data'
            L.warn('syncCaseToServer: Phase 1 — missing id', body); return
          }
        } else {
          syncStatus.phase1 = 'fail'
          syncStatus.phase1Msg = `HTTP ${res.status}: ${body?.message ?? 'Unknown error'}`
          if (body?.error) syncStatus.phase1Msg += ' | ' + JSON.stringify(body.error)
          L.warn('syncCaseToServer: Phase 1 rejected', { status: res.status, body }); return
        }
      } catch (e) {
        clearTimeout(tid)
        syncStatus.phase1 = 'fail'
        syncStatus.phase1Msg = e?.name === 'AbortError' ? 'Timed out' : `Network: ${e?.message ?? e}`
        L.warn('syncCaseToServer: Phase 1 exception', e?.message ?? e); return
      }
    } else {
      syncStatus.phase1 = 'skip'
      syncStatus.phase1Msg = `Already on server — id=${serverId}`
      L.info(`syncCaseToServer: Phase 1 skipped — case already has server_id=${serverId}`)
    }

    if (!serverId) { syncStatus.error = 'No server id after Phase 1'; return }

    // ── Phase 1.5: State machine bridge ────────────────────────────────────
    // store() always creates the case with case_status='OPEN' on the server.
    // If Phase 1 just ran (phase1 === 'ok', meaning a NEW case was created),
    // the server is now OPEN. The server's state machine only allows:
    //   OPEN → IN_PROGRESS → DISPOSITIONED | CLOSED
    // If our local case is DISPOSITIONED or CLOSED, sending that status directly
    // to a freshly-created OPEN case produces a 409 Conflict.
    // Fix: advance the server to IN_PROGRESS first, then Phase 2 sends the real status.
    if (syncStatus.phase1 === 'ok') {
      const localStatus = caseRecord.value.case_status
      if (localStatus === 'DISPOSITIONED' || localStatus === 'CLOSED') {
        L.info(`syncCaseToServer: Phase 1.5 — advancing server OPEN→IN_PROGRESS (local="${localStatus}")`)
        try {
          const ctrl15 = new AbortController()
          const tid15  = setTimeout(() => ctrl15.abort(), APP.SYNC_TIMEOUT_MS)
          const res15  = await fetch(`${window.SERVER_URL}/secondary-screenings/${serverId}/sync`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body:    JSON.stringify({ user_id: userId, case_status: 'IN_PROGRESS', record_version: 0 }),
            signal:  ctrl15.signal,
          })
          clearTimeout(tid15)
          const b15 = await res15.json().catch(() => ({}))
          if (res15.ok && b15?.success) {
            L.ok('syncCaseToServer: Phase 1.5 — server advanced to IN_PROGRESS')
          } else {
            L.warn('syncCaseToServer: Phase 1.5 — advance failed (Phase 2 will still attempt)', { status: res15.status, body: b15 })
          }
        } catch (e15) {
          L.warn('syncCaseToServer: Phase 1.5 — network error (Phase 2 will still attempt)', e15?.message ?? e15)
        }
      }
    }

    // ── Phase 2: POST /secondary-screenings/{id}/sync ───────────────────
    syncStatus.phase2 = null; syncStatus.phase2Msg = 'Sending…'
    L.info(`syncCaseToServer: Phase 2 — POST /secondary-screenings/${serverId}/sync`)

    let idbSymptoms = [], idbExposures = [], idbActions = [], idbTc = [], idbDiseases = []
    try {
      const sid = caseUuid.value;
      [idbSymptoms, idbExposures, idbActions, idbTc, idbDiseases] = await Promise.all([
        dbGetByIndex(STORE.SECONDARY_SYMPTOMS,           'secondary_screening_id', sid),
        dbGetByIndex(STORE.SECONDARY_EXPOSURES,          'secondary_screening_id', sid),
        dbGetByIndex(STORE.SECONDARY_ACTIONS,            'secondary_screening_id', sid),
        dbGetByIndex(STORE.SECONDARY_TRAVEL_COUNTRIES,   'secondary_screening_id', sid),
        dbGetByIndex(STORE.SECONDARY_SUSPECTED_DISEASES, 'secondary_screening_id', sid),
      ])
      L.info('syncCaseToServer: IDB child reads', {
        symptoms: idbSymptoms.length, exposures: idbExposures.length,
        actions: idbActions.length, tc: idbTc.length, diseases: idbDiseases.length,
      })
    } catch (idbErr) {
      syncStatus.phase2 = 'fail'
      syncStatus.phase2Msg = `IDB child read failed: ${idbErr?.message ?? idbErr}`
      L.warn('syncCaseToServer: Phase 2 IDB read error', idbErr); return
    }

    const cr = caseRecord.value
    const p2 = {
      user_id:                           userId,
      record_version:                    cr.record_version ?? 1,
      case_status:                       cr.case_status,
      traveler_full_name:                cr.traveler_full_name                ?? null,
      traveler_gender:                   cr.traveler_gender                   ?? 'UNKNOWN',
      traveler_age_years:                cr.traveler_age_years                ?? null,
      travel_document_type:              cr.travel_document_type              ?? null,
      travel_document_number:            cr.travel_document_number            ?? null,
      traveler_nationality_country_code: cr.traveler_nationality_country_code ?? null,
      residence_country_code:            cr.residence_country_code            ?? null,
      phone_number:                      cr.phone_number                      ?? null,
      journey_start_country_code:        cr.journey_start_country_code        ?? null,
      conveyance_type:                   cr.conveyance_type                   ?? null,
      conveyance_identifier:             cr.conveyance_identifier             ?? null,
      arrival_datetime:                  cr.arrival_datetime                  ?? null,
      purpose_of_travel:                 cr.purpose_of_travel                 ?? null,
      destination_district_code:         cr.destination_district_code         ?? null,
      temperature_value:                 cr.temperature_value                 ?? null,
      temperature_unit:                  cr.temperature_unit                  ?? null,
      pulse_rate:                        cr.pulse_rate                        ?? null,
      respiratory_rate:                  cr.respiratory_rate                  ?? null,
      bp_systolic:                       cr.bp_systolic                       ?? null,
      bp_diastolic:                      cr.bp_diastolic                      ?? null,
      oxygen_saturation:                 cr.oxygen_saturation                 ?? null,
      triage_category:                   cr.triage_category                   ?? null,
      emergency_signs_present:           cr.emergency_signs_present           ?? 0,
      general_appearance:                cr.general_appearance                ?? null,
      syndrome_classification:           cr.syndrome_classification           ?? null,
      risk_level:                        cr.risk_level                        ?? null,
      officer_notes:                     cr.officer_notes                     ?? null,
      final_disposition:                 cr.final_disposition                 ?? null,
      followup_required:                 cr.followup_required                 ?? 0,
      followup_assigned_level:           cr.followup_assigned_level           ?? null,
      dispositioned_at:                  cr.dispositioned_at                  ?? null,
      closed_at:                         cr.closed_at                         ?? null,
      symptoms:          idbSymptoms.map(s  => ({ symptom_code:  s.symptom_code,  is_present: s.is_present, onset_date: s.onset_date ?? null, details: s.details ?? null })),
      exposures:         idbExposures.map(e  => ({ exposure_code: e.exposure_code, response: e.response,   details: e.details ?? null })),
      actions:           idbActions.map(a   => ({ action_code:   a.action_code,   is_done: a.is_done,     details: a.details ?? null })),
      travel_countries:  idbTc.map(t        => ({ country_code:  t.country_code,  travel_role: t.travel_role, arrival_date: t.arrival_date ?? null, departure_date: t.departure_date ?? null })),
      suspected_diseases:idbDiseases.map(d  => ({ disease_code:  d.disease_code,  rank_order: d.rank_order, confidence: d.confidence ?? null, reasoning: d.reasoning ?? null })),
    }
    syncStatus.phase2Payload = { ...p2, symptoms: `[${p2.symptoms.length} items]`, exposures: `[${p2.exposures.length}]`, actions: `[${p2.actions.length}]` }
    L.info('syncCaseToServer: Phase 2 payload summary', {
      case_status: p2.case_status, user_id: p2.user_id,
      symptoms: p2.symptoms.length, exposures: p2.exposures.length,
      actions: p2.actions.length, tc: p2.travel_countries.length,
      url: `${window.SERVER_URL}/secondary-screenings/${serverId}/sync`,
    })

    const ctrl2 = new AbortController()
    const tid2  = setTimeout(() => ctrl2.abort(), APP.SYNC_TIMEOUT_MS)
    try {
      const res2 = await fetch(`${window.SERVER_URL}/secondary-screenings/${serverId}/sync`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body:   JSON.stringify(p2),
        signal: ctrl2.signal,
      })
      clearTimeout(tid2)
      const body2 = await res2.json().catch(() => ({}))
      syncStatus.phase2Resp = body2
      L.info('syncCaseToServer: Phase 2 response', { http: res2.status, success: body2?.success, meta: body2?.meta, error: body2?.error })

      if (res2.ok && body2?.success) {
        const sc = body2.data ?? {}
        const synced = {
          ...toPlain(caseRecord.value),
          id: sc.id ?? serverId, server_id: sc.id ?? serverId,
          sync_status: SYNC.SYNCED, synced_at: isoNow(),
          record_version: (caseRecord.value.record_version || 1) + 1,
          updated_at: isoNow(),
        }
        await safeDbPut(STORE.SECONDARY_SCREENINGS, synced)
        caseRecord.value = synced
        syncStatus.phase2 = 'ok'
        syncStatus.phase2Msg = `Synced — status=${sc.case_status} child_tables=${JSON.stringify(body2.meta?.child_tables_sync ?? {})}`
        syncStatus.lastRunAt = isoNow()
        L.ok('syncCaseToServer: Phase 2 OK', body2.meta)
      } else {
        // ── 409 Conflict: check if case is already CLOSED on the server ──
        // The server permanently rejects ANY sync to a CLOSED case with 409.
        // This happens when a previous sync successfully closed the case but
        // the local IDB record was not updated (e.g. network cut after server
        // committed but before the response reached the client).
        // Treatment: the case is already done — reconcile local IDB to CLOSED/SYNCED.
        const alreadyClosed = res2.status === 409 &&
          (body2?.message ?? '').toLowerCase().includes('closed')

        if (alreadyClosed) {
          L.ok('syncCaseToServer: Phase 2 — server case already CLOSED (idempotent). Reconciling local IDB.')
          const reconciled = {
            ...toPlain(caseRecord.value),
            id:           serverId, server_id: serverId,
            case_status:  'CLOSED',
            closed_at:    caseRecord.value.closed_at ?? isoNow(),
            sync_status:  SYNC.SYNCED,
            synced_at:    isoNow(),
            last_sync_error: null,
            record_version: (caseRecord.value.record_version || 1) + 1,
            updated_at:   isoNow(),
          }
          await safeDbPut(STORE.SECONDARY_SCREENINGS, reconciled)
          caseRecord.value = reconciled
          // Also close the notification in IDB if still open
          if (notification.value && notification.value.status !== 'CLOSED') {
            const closedNotif = {
              ...toPlain(notification.value),
              status:    'CLOSED',
              closed_at: reconciled.closed_at,
              record_version: (notification.value.record_version || 1) + 1,
              updated_at: isoNow(),
            }
            await safeDbPut(STORE.NOTIFICATIONS, closedNotif)
            notification.value = closedNotif
          }
          syncStatus.phase2 = 'ok'
          syncStatus.phase2Msg = 'Server case was already CLOSED — local IDB reconciled'
          syncStatus.lastRunAt = isoNow()
        } else {
          syncStatus.phase2 = 'fail'
          syncStatus.phase2Msg = `HTTP ${res2.status}: ${body2?.message ?? 'Unknown'}`
          if (body2?.error) syncStatus.phase2Msg += ' | ' + JSON.stringify(body2.error)
          await safeDbPut(STORE.SECONDARY_SCREENINGS, {
            ...toPlain(caseRecord.value),
            sync_status: SYNC.FAILED,
            last_sync_error: body2?.message ?? `HTTP ${res2.status}`,
            record_version: (caseRecord.value.record_version || 1) + 1,
            updated_at: isoNow(),
          })
          L.warn('syncCaseToServer: Phase 2 rejected', { status: res2.status, body: body2 })
        }
      }   // end if (res2.ok) / else
    } catch (e) {
      clearTimeout(tid2)
      syncStatus.phase2 = 'fail'
      syncStatus.phase2Msg = e?.name === 'AbortError' ? 'Timed out' : `Network: ${e?.message ?? e}`
      L.warn('syncCaseToServer: Phase 2 exception', e?.message ?? e)
    }
  } catch (outerErr) {
    syncStatus.error = outerErr?.message ?? String(outerErr)
    L.err('syncCaseToServer: OUTER UNCAUGHT ERROR', outerErr)
  } finally {
    syncStatus.running  = false
    syncStatus.lastRunAt = syncStatus.lastRunAt ?? isoNow()
  }
}


// THE BUG THAT WAS CAUSING "LOADING FOREVER":
//   initPageRunning was a plain JS boolean lock. When onIonViewWillEnter held
//   the lock and onIonViewDidEnter arrived while _doInitPage() was mid-await,
//   onIonViewDidEnter hit `if (initPageRunning) return` and was permanently
//   discarded. If that first run was a no-op empty-uuid early-return, loading
//   was never cleared. Final fix: no global lock, two clean triggers only.
//
// TWO TRIGGERS:
//   onMounted + nextTick  — initial render AND Vite HMR (HMR fully remounts
//                           <script setup>, so onMounted re-fires with fresh state).
//
//   onIonViewDidEnter     — every subsequent Ionic page-enter (back navigation,
//                           re-entry). Fires AFTER page animation — route.params
//                           is GUARANTEED populated at this point.
//                           onMounted does NOT fire on re-entry (keep-alive).
//
// onIonViewWillEnter intentionally REMOVED — fires before Ionic commits the
// route, route.params may still be previous page's params at that moment.
//
// No outer lock. Idempotency check inside _doInitPage() prevents double work
// once data is loaded. openCaseLock below prevents the only real race: two
// concurrent calls creating two IDB case records before either commits.

let openCaseLock = false

// ─── DIAGNOSTIC LOGGER ────────────────────────────────────────────────────
const L = {
  _ts() { return new Date().toISOString().slice(11,23) },
  info(msg,  data) { console.log(   `%c[SS][${L._ts()}] ℹ ${msg}`,  'color:#1565C0;font-weight:700', ...(data!==undefined?[data]:[]) ) },
  ok(  msg,  data) { console.log(   `%c[SS][${L._ts()}] ✓ ${msg}`,  'color:#2E7D32;font-weight:700', ...(data!==undefined?[data]:[]) ) },
  warn(msg,  data) { console.warn(  `%c[SS][${L._ts()}] ⚠ ${msg}`,  'color:#E65100;font-weight:700', ...(data!==undefined?[data]:[]) ) },
  err( msg,  data) { console.error( `%c[SS][${L._ts()}] ✖ ${msg}`,  'color:#C62828;font-weight:700', ...(data!==undefined?[data]:[]) ) },
}

async function _doInitPage(source) {
  // Router param name varies across router file versions — read both and use whichever is set
  const uuid = String(route.params.notificationId || route.params.notificationUuid || '').trim()

  // [debug removed — admin panel available for diagnostics]

  if (!uuid) {
    L.warn(`[${source}] uuid EMPTY — route params not committed yet. Spinner stays until next trigger.`, {
      hint: 'Check that route /secondary-screening/:notificationUuid is matched correctly.',
    })
    return
  }

  if (notificationUuid.value === uuid && caseRecord.value) {
    L.ok(`[${source}] IDEMPOTENT — uuid="${uuid}" already loaded, caseRecord exists. Skipping.`)
    return
  }

  L.info(`[${source}] Starting full load for uuid="${uuid}"`)
  notificationUuid.value = uuid
  loading.value          = true
  notFound.value         = false
  poeMismatch.value      = false

  const localAuth = getAuth()
  if (!localAuth?.id || !localAuth?.is_active) {
    L.err(`[${source}] AUTH FAILED — id=${localAuth?.id} is_active=${localAuth?.is_active}. Aborting.`)
    loading.value = false
    return
  }
  L.ok(`[${source}] Auth OK — user id=${localAuth.id} poe=${localAuth.poe_code}`)

  try {
    L.info(`[${source}] IDB: dbGet NOTIFICATIONS "${uuid}"`)
    const notif = await dbGet(STORE.NOTIFICATIONS, uuid)
    if (!notif) {
      L.err(`[${source}] NOTIFICATION NOT FOUND in IDB for uuid="${uuid}"`, {
        hint: 'Primary screening may not have synced its notification to this device yet.',
        store: STORE.NOTIFICATIONS,
      })
      notFound.value = true
      return
    }
    L.ok(`[${source}] Notification loaded`, { status: notif.status, priority: notif.priority, poe_code: notif.poe_code })
    notification.value = { ...notif, id: notif.id ?? notif.server_id ?? null }

    const userPoe  = auth?.poe_code  ?? ''
    const notifPoe = notif.poe_code  ?? ''
    if (userPoe && notifPoe && userPoe !== notifPoe) {
      L.warn(`POE mismatch — user:${userPoe} notif:${notifPoe}`)
      poeMismatch.value = true
    }

    if (notif.primary_screening_id) {
      L.info(`[${source}] IDB: dbGet PRIMARY_SCREENINGS "${notif.primary_screening_id}"`)
      const ps = await dbGet(STORE.PRIMARY_SCREENINGS, notif.primary_screening_id)
      primaryScreening.value = ps ?? null
      if (ps) {
        L.ok(`[${source}] Primary screening loaded — gender=${ps.gender}`)
        if (ps.gender)             profile.traveler_gender    = ps.gender
        if (ps.traveler_full_name) profile.traveler_full_name = ps.traveler_full_name
      } else {
        L.warn(`[${source}] Primary screening NOT FOUND for id="${notif.primary_screening_id}" — proceeding without it`)
      }
    } else {
      L.warn(`[${source}] Notification has no primary_screening_id — this is unexpected`)
    }

    L.info(`[${source}] IDB: dbGetByIndex SECONDARY_SCREENINGS notification_id="${uuid}"`)
    const existingCases = await dbGetByIndex(
      STORE.SECONDARY_SCREENINGS, 'notification_id', uuid
    )
    L.info(`[${source}] Existing cases found: ${existingCases.length}`)

    if (existingCases.length > 0) {
      const existing  = existingCases[0]
      L.ok(`[${source}] RESUMING case — uuid="${existing.client_uuid}" status="${existing.case_status}"`)
      caseUuid.value   = existing.client_uuid
      caseRecord.value = { ...existing, id: existing.id ?? existing.server_id ?? null }

      Object.assign(profile, {
        traveler_full_name:                existing.traveler_full_name                || '',
        traveler_gender:                   existing.traveler_gender                   || profile.traveler_gender,
        traveler_age_years:                existing.traveler_age_years                || null,
        travel_document_type:              existing.travel_document_type              || '',
        travel_document_number:            existing.travel_document_number            || '',
        traveler_nationality_country_code: existing.traveler_nationality_country_code || '',
        residence_country_code:            existing.residence_country_code            || '',
        phone_number:                      existing.phone_number                      || '',
        journey_start_country_code:        existing.journey_start_country_code        || '',
        conveyance_type:                   existing.conveyance_type                   || '',
        conveyance_identifier:             existing.conveyance_identifier             || '',
        arrival_datetime_input:            existing.arrival_datetime
                                             ? existing.arrival_datetime.slice(0, 16) : '',
        purpose_of_travel:                 existing.purpose_of_travel                || '',
        destination_district_code:         existing.destination_district_code        || '',
      })

      if (existing.temperature_value != null) {
        showVitals.value = true
        Object.assign(vitals, {
          temperature_value:       existing.temperature_value,
          temperature_unit:        existing.temperature_unit        || 'C',
          pulse_rate:              existing.pulse_rate,
          respiratory_rate:        existing.respiratory_rate,
          bp_systolic:             existing.bp_systolic,
          bp_diastolic:            existing.bp_diastolic,
          oxygen_saturation:       existing.oxygen_saturation,
          triage_category:         existing.triage_category         || '',
          emergency_signs_present: existing.emergency_signs_present || 0,
          general_appearance:      existing.general_appearance       || '',
        })
      }

      Object.assign(caseDecision, {
        syndrome_classification:  existing.syndrome_classification  || '',
        risk_level:               existing.risk_level               || '',
        final_disposition:        existing.final_disposition        || '',
        officer_notes:            existing.officer_notes            || '',
        followup_required:        !!existing.followup_required,
        followup_assigned_level:  existing.followup_assigned_level  || '',
      })

      const sid = existing.client_uuid
      travelCountries.value  = await dbGetByIndex(STORE.SECONDARY_TRAVEL_COUNTRIES,   'secondary_screening_id', sid) || []
      initSymptoms()
      initExposuresFromCatalog()
      const savedSymptoms    = await dbGetByIndex(STORE.SECONDARY_SYMPTOMS,            'secondary_screening_id', sid)
      for (const s of savedSymptoms) {
        if (symptomsMap[s.symptom_code]) {
          symptomsMap[s.symptom_code].is_present = s.is_present
          symptomsMap[s.symptom_code].onset_date  = s.onset_date
          symptomsMap[s.symptom_code].details     = s.details
          symptomsMap[s.symptom_code].client_uuid = s.client_uuid
        }
      }
      const savedExposures   = await dbGetByIndex(STORE.SECONDARY_EXPOSURES,           'secondary_screening_id', sid)
      // FIX BUG: 'exposures' was undefined. Restore directly into exposuresMap.
      for (const se of savedExposures) {
        if (exposuresMap[se.exposure_code] !== undefined) {
          exposuresMap[se.exposure_code].response = se.response
          if (se.details) exposuresMap[se.exposure_code].details = se.details
        } else {
          exposuresMap[se.exposure_code] = {
            exposure_code: se.exposure_code,
            response:      se.response || 'UNKNOWN',
            details:       se.details  || null,
          }
        }
      }
      actions.value           = await dbGetByIndex(STORE.SECONDARY_ACTIONS,            'secondary_screening_id', sid) || []
      suspectedDiseases.value = await dbGetByIndex(STORE.SECONDARY_SUSPECTED_DISEASES, 'secondary_screening_id', sid) || []

      if (['DISPOSITIONED', 'CLOSED'].includes(existing.case_status)) step.value = 4

      // ── AUTO-RERUN ANALYSIS ON RESUME ─────────────────────────────────────
      // analysisResult lives only in memory. After a page reload or back-nav,
      // step 4 would show a blank panel. Re-run the engine from saved IDB data
      // so the officer gets the full intelligence view without re-entering exposures.
      const hasEnoughForAnalysis = savedSymptoms.length >= 1 && existing.syndrome_classification
      if (hasEnoughForAnalysis && window.DISEASES?.getEnhancedScoreResult) {
        try {
          const _pSym = savedSymptoms.filter(s => s.is_present === 1).map(s => s.symptom_code)
          const _aSym = savedSymptoms.filter(s => s.is_present === 0).map(s => s.symptom_code)
          const _expRec = savedExposures.map(e => ({ exposure_code: e.exposure_code, response: e.response }))
          const _engCodes = window.EXPOSURES?.mapToEngineCodes(_expRec) || []
          const _rawTemp = existing.temperature_value
          const _tempC   = _rawTemp ? (existing.temperature_unit === 'F' ? (_rawTemp - 32) * 5 / 9 : _rawTemp) : null
          const _vitals  = {
            temperature_c:     _tempC,
            oxygen_saturation: existing.oxygen_saturation || undefined,
            pulse_rate:        existing.pulse_rate        || undefined,
            respiratory_rate:  existing.respiratory_rate  || undefined,
            bp_systolic:       existing.bp_systolic        || undefined,
          }
          const _visited = (travelCountries.value || []).map(t => ({
            country_code: t.country_code, travel_role: t.travel_role || 'VISITED'
          }))
          const _result = window.DISEASES.getEnhancedScoreResult(_pSym, _aSym, _engCodes, _visited, _vitals)
          analysisResult.value = _result
          L.ok(`[${source}] Auto-rerun analysis OK — syndrome=${_result.syndrome?.syndrome} risk=${_result.ihr_risk?.risk_level} non_case=${_result.is_non_case}`)
        } catch (_reErr) {
          L.warn(`[${source}] Auto-rerun analysis non-fatal error`, _reErr?.message)
        }
      }

      L.ok(`[${source}] Case resume complete. step=${step.value}`)

    } else {
      L.info(`[${source}] NEW case — notification.status="${notif.status}"`)
      if (notif.status === 'OPEN' || notif.status === 'IN_PROGRESS') {
        L.info(`[${source}] Calling openCase()`)
        await openCase(localAuth)
        L.ok(`[${source}] openCase() complete — caseUuid="${caseUuid.value}"`)
      } else {
        L.warn(`[${source}] Notification status="${notif.status}" is not OPEN/IN_PROGRESS — openCase skipped`)
      }
    }

  } catch (err) {
    L.err(`[${source}] UNHANDLED ERROR in _doInitPage`, err)
    console.error('[SecondaryScreening] Full error:', err)
    notFound.value = true
  } finally {
    L.info(`[${source}] finally — setting loading=false. notFound=${notFound.value} caseRecord=${!!caseRecord.value}`)
    loading.value = false
  }
}

// ── Trigger 1: route-param watcher (PRIMARY — handles every scenario) ────
// { immediate: true } fires synchronously during setup IF params are already
// set. If params are empty (direct URL load, Ionic pre-create), it fires again
// the moment Vue Router commits the route and sets the param. This is the only
// trigger that reliably works on direct browser URL entry / page refresh.
watch(
  () => route.params.notificationId || route.params.notificationUuid,
  (newUuid) => {
    const uuid = String(newUuid || '').trim()
    if (uuid) void _doInitPage('routeParamWatch')
  },
  { immediate: true }
)

// ── Trigger 2: onMounted (Vite HMR recovery + belt-and-suspenders) ────────
// Vue 3 HMR fully remounts <script setup> components, so onMounted re-fires.
// nextTick ensures Vue Router has had one render cycle to commit params.
onMounted(async () => {
  await nextTick()
  void _doInitPage('onMounted')
})

// ── Trigger 3: onIonViewDidEnter (in-app navigation, back-nav) ───────────
// Fires AFTER Ionic page animation — only on navigations within the running app.
// Does NOT fire on direct URL load (which is why the route-param watch is needed).
onIonViewDidEnter(async () => {
  await nextTick()
  void _doInitPage('onIonViewDidEnter')
})
</script>


<style scoped>
/* ═══════════════════════════════════════════════════════════════
   SecondaryScreening.vue — Premium Scoped Styles
   Namespace: sc-*
   Theme: Command Centre Dual-Tone (Dark header + Light content)
   Design: ECSA-HC POE SENTINEL v5.0 — Aligned to readme.txt
   Fonts: DM Sans · Syne (display) · JetBrains Mono (codes)
   Grid: 8-point · WCAG AA · 44px touch targets
   NO dark mode. NO @media prefers-color-scheme.
═══════════════════════════════════════════════════════════════ */

/* ── ANIMATIONS ──────────────────────────────────────────────── */
@keyframes sc-slideUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes sc-dataStream {
  0%   { transform: translateX(-100%); }
  100% { transform: translateX(350%); }
}
@keyframes sc-spin { to { transform: rotate(360deg); } }
@keyframes sc-node-pulse {
  0%,100% { box-shadow: 0 0 0 0 rgba(0,180,255,.4); }
  50%     { box-shadow: 0 0 0 6px rgba(0,180,255,0); }
}
@keyframes sc-dotPulse {
  0%,100% { box-shadow: 0 0 6px rgba(224,32,80,.3); }
  50%     { box-shadow: 0 0 14px rgba(224,32,80,.6); }
}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}

/* ── HEADER — DARK ZONE ──────────────────────────────────────── */
.sc-header {
  --background: transparent;
  background: linear-gradient(180deg, #070E1B 0%, #0E1A2E 100%);
  position: relative;
}
.sc-hdr-pattern {
  position: absolute; inset: 0;
  background-image:
    linear-gradient(rgba(0,180,255,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,180,255,0.03) 1px, transparent 1px);
  background-size: 36px 36px;
  mask-image: linear-gradient(180deg, black 60%, transparent 100%);
  -webkit-mask-image: linear-gradient(180deg, black 60%, transparent 100%);
  pointer-events: none;
}

.sc-hdr-top {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px 0; position: relative; z-index: 2;
}
.sc-back-btn {
  width: 44px; height: 44px; border-radius: 50%;
  background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  cursor: pointer; -webkit-tap-highlight-color: transparent;
  transition: background .15s cubic-bezier(.16,1,.3,1);
}
.sc-back-btn:active { background: rgba(255,255,255,.18); }
.sc-back-btn svg { width: 16px; height: 16px; }

.sc-title-block { flex: 1; }
.sc-eyebrow {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Ubuntu, 'Helvetica Neue', Arial, sans-serif;
  font-size: 9px; font-weight: 700; letter-spacing: 1.2px;
  text-transform: uppercase; color: #7E92AB; display: block;
}
.sc-page-title {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Ubuntu, 'Helvetica Neue', Arial, sans-serif;
  font-size: 18px; font-weight: 700; color: #EDF2FA; letter-spacing: -.25px; line-height: 1.3;
}

.sc-hdr-right { flex-shrink: 0; }
.sc-sync-pill {
  display: flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: 10px; border: 1px solid;
  font-size: 10px; font-weight: 700;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Ubuntu, 'Helvetica Neue', Arial, sans-serif;
}
.sc-sync-dot { width: 6px; height: 6px; border-radius: 50%; }
.sc-sync-pill--ok      { background: rgba(0,168,107,.2);  border-color: rgba(0,168,107,.35); color: #00E676; }
.sc-sync-pill--ok .sc-sync-dot      { background: #00E676; box-shadow: 0 0 8px rgba(0,230,118,.4); }
.sc-sync-pill--pending { background: rgba(255,179,0,.15); border-color: rgba(255,179,0,.3);  color: #FFB300; }
.sc-sync-pill--pending .sc-sync-dot { background: #FFB300; box-shadow: 0 0 8px rgba(255,179,0,.4); }
.sc-sync-pill--offline { background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.1); color: rgba(255,255,255,.5); }
.sc-sync-pill--offline .sc-sync-dot { background: rgba(255,255,255,.4); }

/* Case summary strip */
.sc-case-strip {
  display: flex; align-items: center; gap: 10px;
  margin: 8px 14px 0;
  background: linear-gradient(180deg, #0E1A2E 0%, #142640 100%);
  border: 1px solid rgba(255,255,255,.08); border-radius: 14px;
  padding: 10px 12px; position: relative; z-index: 2;
}
.sc-case-ic {
  width: 32px; height: 32px; border-radius: 8px;
  background: rgba(0,180,255,.12); border: 1px solid rgba(0,180,255,.2);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sc-case-ic svg { width: 14px; height: 14px; }
.sc-case-info { flex: 1; min-width: 0; }
.sc-case-name {
  font-family: inherit;
  font-size: 13px; font-weight: 700; color: #EDF2FA;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sc-case-meta {
  font-family: inherit;
  font-size: 10px; color: #7E92AB; margin-top: 1px;
}
.sc-prio-pill {
  padding: 3px 9px; border-radius: 5px; font-size: 9px; font-weight: 800;
  letter-spacing: .6px; text-transform: uppercase; flex-shrink: 0;
  font-family: inherit;
}
.sc-prio-pill--critical { background: rgba(224,32,80,.2); color: #FF3D71; border: 1px solid rgba(224,32,80,.35); }
.sc-prio-pill--high     { background: rgba(255,179,0,.18); color: #FFB300; border: 1px solid rgba(255,179,0,.3); }
.sc-prio-pill--normal   { background: rgba(255,255,255,.08); color: #7E92AB; border: 1px solid rgba(255,255,255,.12); }

/* 4-step progress bar */
.sc-stepper {
  display: flex; align-items: center;
  padding: 10px 14px 12px; position: relative; z-index: 2;
}
.sc-step-wrap { display: flex; align-items: center; flex: 1; }
.sc-step-wrap:last-child { flex: none; }
.sc-step {
  display: flex; flex-direction: column; align-items: center; gap: 3px;
  background: none; border: none; cursor: pointer; padding: 4px;
  min-width: 44px; min-height: 44px; flex-shrink: 0;
  -webkit-tap-highlight-color: transparent;
}
.sc-step-node {
  width: 24px; height: 24px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  transition: all .25s cubic-bezier(.16,1,.3,1);
}
.sc-step--done   .sc-step-node { background: #00A86B; border: none; box-shadow: 0 0 8px rgba(0,168,107,.35); }
.sc-step--active .sc-step-node { background: #00B4FF; border: 2px solid #00B4FF; box-shadow: 0 0 12px rgba(0,180,255,.3); }
.sc-step--future .sc-step-node { background: rgba(255,255,255,.06); border: 1.5px solid rgba(255,255,255,.2); }
.sc-step--active .sc-step-node { animation: sc-node-pulse 2s ease-in-out infinite; }
.sc-step-num  { font-size: 10px; font-weight: 800; font-family: inherit; }
.sc-step--done   .sc-step-num   { color: #fff; }
.sc-step--active .sc-step-num   { color: #fff; }
.sc-step--future .sc-step-num   { color: rgba(255,255,255,.4); }
.sc-step-lbl {
  font-family: inherit;
  font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
  white-space: nowrap;
}
.sc-step--done   .sc-step-lbl   { color: #00E676; }
.sc-step--active .sc-step-lbl   { color: #00B4FF; text-shadow: 0 0 20px rgba(0,180,255,.25); }
.sc-step--future .sc-step-lbl   { color: rgba(255,255,255,.3); }
.sc-step-line { flex: 1; height: 1.5px; background: rgba(255,255,255,.1); margin: 0 4px; position: relative; top: -7px; }
.sc-step-line--done { background: rgba(0,168,107,.5); }

/* ── CONTENT — LIGHT ZONE ────────────────────────────────────── */
.sc-content {
  --background: linear-gradient(180deg, #EAF0FA 0%, #F2F5FB 40%, #E4EBF7 100%);
  --color: #0B1A30;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Ubuntu, 'Helvetica Neue', Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-rendering: optimizeLegibility;
}
.sc-body {
  padding: 12px 16px 24px;
  display: flex; flex-direction: column; gap: 0;
  animation: sc-slideUp .5s cubic-bezier(.16,1,.3,1) both;
}

/* Guards */
.sc-guard {
  display: flex; align-items: flex-start; gap: 12px;
  margin: 16px; padding: 16px; border-radius: 16px;
  background: linear-gradient(135deg, #B01840, #E02050);
  box-shadow: 0 4px 16px rgba(224,32,80,.2);
}
.sc-guard--warn { background: linear-gradient(135deg, #CC8800 0%, #E6A000 100%); box-shadow: 0 4px 16px rgba(204,136,0,.2); }
.sc-guard svg { width: 22px; height: 22px; flex-shrink: 0; margin-top: 1px; }
.sc-guard-title { font-size: 14px; font-weight: 700; color: #fff; }
.sc-guard-sub   { font-size: 12px; color: rgba(255,255,255,.85); margin-top: 3px; line-height: 1.4; }

/* POE mismatch advisory */
.sc-poe-warn {
  display: flex; align-items: flex-start; gap: 9px;
  padding: 10px 12px; border-radius: 12px; margin-bottom: 8px;
  background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
  border: 1.5px solid rgba(204,136,0,.12);
  font-size: 12px; color: #0B1A30; line-height: 1.4;
}
.sc-poe-warn svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; stroke: #CC8800; }

/* Loading */
.sc-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 200px; gap: 12px; }
.sc-spinner { width: 32px; height: 32px; border: 3px solid rgba(0,112,224,.15); border-top-color: #0070E0; border-radius: 50%; animation: sc-spin .8s linear infinite; }
.sc-loading-txt { font-size: 13px; color: #475569; font-weight: 600; }

/* ── SECTION HEADERS ──────────────────────────────────────────── */
.sc-section-hdr {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 0 4px;
  margin: 14px 0 8px;
}
.sc-sec-num {
  width: 22px; height: 22px; border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 900; color: #fff; flex-shrink: 0;
}
.sc-sec-num--blue   { background: linear-gradient(135deg, #0055CC, #0070E0); }
.sc-sec-num--orange { background: linear-gradient(135deg, #CC8800, #E6A000); }
.sc-sec-num--red    { background: linear-gradient(135deg, #B01840, #E02050); }
.sc-sec-num--green  { background: linear-gradient(135deg, #007A50, #00A86B); }
.sc-sec-num--purple { background: linear-gradient(135deg, #5B20B6, #7B40D8); }
.sc-sec-title {
  font-family: inherit;
  font-size: 14px; font-weight: 600; color: #0B1A30; letter-spacing: -.25px; flex: 1;
  line-height: 1.35;
}
.sc-sec-badge {
  padding: 3px 9px; border-radius: 5px; font-size: 9px;
  font-weight: 700; letter-spacing: .6px; text-transform: uppercase;
  border: 1px solid;
  font-family: inherit;
}
.sc-sec-badge--req  { background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); color: #E02050; border-color: rgba(224,32,80,.1); }
.sc-sec-badge--opt  { background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%); color: #94A3B8; border-color: rgba(0,0,0,.06); }
.sc-sec-badge--warn { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); color: #CC8800; border-color: rgba(204,136,0,.12); }

/* ── FIELD CARD — sentinel-card pattern ──────────────────────── */
.sc-card {
  background: linear-gradient(145deg, #FFFFFF 0%, #F4F7FC 100%);
  border: 1.5px solid rgba(0,0,0,.06);
  border-radius: 14px;
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 20px rgba(0,30,80,.06);
  overflow: hidden;
  position: relative;
  transition: all .25s cubic-bezier(.16,1,.3,1);
}
.sc-card::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent 20%, rgba(255,255,255,.8) 50%, transparent 80%);
  z-index: 1;
}
.sc-card::after {
  content: ''; position: absolute; top: 0; bottom: 0; width: 40%; left: 0;
  background: linear-gradient(90deg, transparent, rgba(0,112,224,.02), transparent);
  animation: sc-dataStream 6s ease-in-out infinite; pointer-events: none;
}

.sc-field-row {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(0,0,0,.04);
  position: relative; z-index: 1;
}
.sc-field-row--last { border-bottom: none; }
.sc-field-ic {
  width: 32px; height: 32px; border-radius: 8px;
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
  border: 1px solid rgba(0,112,224,.15);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; margin-top: 1px;
}
.sc-field-ic svg { width: 14px; height: 14px; }
.sc-field-body { flex: 1; min-width: 0; }
.sc-field-lbl {
  font-family: inherit;
  font-size: 9px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1.2px; color: #94A3B8; display: block; margin-bottom: 4px;
}
.sc-field-input {
  width: 100%; border: 1.5px solid rgba(0,0,0,.08);
  border-radius: 10px;
  padding: 10px 12px; font-size: 16px; font-weight: 400; color: #0B1A30;
  background: linear-gradient(145deg, #E8EDF7 0%, #F0F3FA 100%);
  outline: none;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Ubuntu, 'Helvetica Neue', Arial, sans-serif;
  transition: all .25s cubic-bezier(.16,1,.3,1);
  min-height: 48px; box-sizing: border-box;
}
.sc-field-input--short { max-width: 120px; }
.sc-field-input:focus {
  border-color: rgba(0,112,224,.35);
  box-shadow: 0 0 0 3px rgba(0,112,224,.08);
  background: #fff;
}
.sc-field-input::placeholder { color: #94A3B8; }
.sc-field-select {
  width: 100%; border: 1.5px solid rgba(0,0,0,.08);
  border-radius: 10px;
  padding: 10px 12px; font-size: 16px; color: #0B1A30;
  background: linear-gradient(145deg, #E8EDF7 0%, #F0F3FA 100%);
  outline: none;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Ubuntu, 'Helvetica Neue', Arial, sans-serif;
  -webkit-appearance: none;
  min-height: 48px; box-sizing: border-box;
  transition: all .25s;
}
.sc-field-select:focus {
  border-color: rgba(0,112,224,.35);
  box-shadow: 0 0 0 3px rgba(0,112,224,.08);
  background: #fff;
}
.sc-field-err {
  font-size: 11.5px; font-weight: 700; color: #E02050;
  background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%);
  border: 1px solid rgba(224,32,80,.1);
  border-radius: 8px; padding: 8px 12px; margin-bottom: 8px;
}

/* Gender buttons */
.sc-gender-row { display: flex; gap: 6px; flex-wrap: wrap; }
.sc-gender-btn {
  padding: 8px 16px; border-radius: 10px;
  border: 1.5px solid rgba(0,0,0,.06);
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  font-size: 13px; font-weight: 600; color: #475569;
  font-family: inherit;
  cursor: pointer; transition: all .15s cubic-bezier(.16,1,.3,1);
  min-height: 44px; box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
  box-shadow: 0 1px 3px rgba(0,0,0,.03);
}
.sc-gender-btn--active {
  background: linear-gradient(135deg, #0055CC 0%, #0070E0 50%, #3399FF 100%);
  border-color: transparent; color: #fff;
  box-shadow: 0 4px 16px rgba(0,112,224,.25);
}
.sc-gender-btn:active { transform: scale(.96); }

/* Chip buttons (doc type, conveyance) */
.sc-chip-row { display: flex; gap: 6px; flex-wrap: wrap; }
.sc-chip-btn {
  padding: 7px 14px; border-radius: 22px;
  border: 1.5px solid rgba(0,0,0,.06);
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  font-size: 12px; font-weight: 600; color: #475569;
  font-family: inherit;
  cursor: pointer; transition: all .15s cubic-bezier(.16,1,.3,1);
  min-height: 44px; box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
  box-shadow: 0 1px 3px rgba(0,0,0,.03);
}
.sc-chip-btn--active {
  background: linear-gradient(135deg, #E0ECFF 0%, #D0E0FF 100%);
  border-color: rgba(0,112,224,.3); color: #0070E0;
  box-shadow: 0 2px 8px rgba(0,112,224,.1);
}
.sc-chip-btn:active { transform: scale(.96); }

/* ── TRAVEL COUNTRIES ─────────────────────────────────────────── */
.sc-empty-travel {
  display: flex; align-items: center; gap: 8px;
  padding: 14px 16px;
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px dashed rgba(0,0,0,.1); border-radius: 12px;
  font-size: 12px; color: #94A3B8; font-weight: 600;
  box-shadow: 0 1px 3px rgba(0,0,0,.03);
}
.sc-empty-travel svg { width: 18px; height: 18px; flex-shrink: 0; }
.sc-tc-row {
  display: flex; gap: 6px; align-items: center;
  margin-bottom: 8px;
}
.sc-tc-select {
  flex: 1; border: 1.5px solid rgba(0,0,0,.08); border-radius: 10px;
  padding: 10px 12px; font-size: 14px; color: #0B1A30;
  background: linear-gradient(145deg, #E8EDF7, #F0F3FA);
  outline: none; font-family: inherit;
  -webkit-appearance: none; min-height: 48px; box-sizing: border-box;
  transition: all .25s;
}
.sc-tc-select:focus { border-color: rgba(0,112,224,.35); box-shadow: 0 0 0 3px rgba(0,112,224,.08); background: #fff; }
.sc-tc-role {
  width: 96px; border: 1.5px solid rgba(0,0,0,.08); border-radius: 10px;
  padding: 10px 8px; font-size: 12px; color: #475569;
  background: linear-gradient(145deg, #E8EDF7, #F0F3FA);
  outline: none; font-family: inherit;
  -webkit-appearance: none; flex-shrink: 0;
  min-height: 48px; box-sizing: border-box;
}
.sc-tc-remove {
  width: 44px; height: 44px; border-radius: 10px;
  background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%);
  border: 1px solid rgba(224,32,80,.1);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0; color: #E02050;
  -webkit-tap-highlight-color: transparent;
  transition: all .15s;
}
.sc-tc-remove:active { transform: scale(.95); }
.sc-tc-remove svg { width: 12px; height: 12px; }
.sc-add-country-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  width: 100%; padding: 12px 16px; border-radius: 12px;
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
  border: 1.5px dashed rgba(0,112,224,.3);
  font-size: 13px; font-weight: 600; color: #0070E0;
  font-family: inherit;
  cursor: pointer; margin-top: 4px;
  min-height: 48px; box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
  transition: all .15s cubic-bezier(.16,1,.3,1);
}
.sc-add-country-btn svg { width: 14px; height: 14px; }
.sc-add-country-btn:active { transform: scale(.98); background: linear-gradient(135deg, #D0DCEF 0%, #BCD4FF 100%); }

/* ── SYMPTOM CHECKLIST ────────────────────────────────────────── */
.sc-sym-count {
  margin-left: auto; font-size: 11px; font-weight: 700;
  color: #0070E0;
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
  border: 1px solid rgba(0,112,224,.12);
  padding: 3px 9px; border-radius: 5px;
  font-family: inherit;
}
.sc-sym-intro {
  font-size: 12px; color: #475569; line-height: 1.5;
  margin-bottom: 10px;
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border-radius: 12px;
  padding: 10px 14px;
  border: 1.5px solid rgba(0,0,0,.06);
  box-shadow: 0 1px 3px rgba(0,0,0,.03);
}
.sc-sym-group { margin-bottom: 12px; }
.sc-sym-group-hdr {
  display: flex; align-items: center; gap: 7px;
  font-family: inherit;
  font-size: 9px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1.2px; color: #94A3B8; margin-bottom: 8px;
}
.sc-sym-group-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.sc-sym-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.sc-sym-card {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 10px; border-radius: 12px;
  border: 1.5px solid rgba(0,0,0,.06);
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  cursor: pointer; -webkit-tap-highlight-color: transparent;
  transition: all .15s cubic-bezier(.16,1,.3,1);
  min-height: 44px; box-sizing: border-box;
  box-shadow: 0 1px 3px rgba(0,0,0,.03);
  position: relative; overflow: hidden;
}
.sc-sym-card:active { transform: scale(.96); }
.sc-sym-card--on {
  background: linear-gradient(135deg, #0055CC 0%, #0070E0 50%, #3399FF 100%);
  border-color: transparent;
  box-shadow: 0 4px 16px rgba(0,112,224,.25);
}
.sc-sym-card--off {
  background: linear-gradient(145deg, #F8FAFC, #F1F5F9);
  border-color: rgba(0,0,0,.04);
}
.sc-sym-indicator {
  width: 20px; height: 20px; border-radius: 6px; flex-shrink: 0;
  border: 1.5px solid rgba(0,0,0,.1);
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  display: flex; align-items: center; justify-content: center;
  transition: all .15s;
}
.sc-sym-card--on .sc-sym-indicator { background: rgba(255,255,255,.25); border-color: rgba(255,255,255,.5); }
.sc-sym-indicator svg { width: 11px; height: 11px; }
.sc-sym-name {
  font-family: inherit;
  font-size: 11.5px; font-weight: 600; color: #475569;
  line-height: 1.25; flex: 1; text-align: left;
}
.sc-sym-name--on { color: #fff; font-weight: 700; }
.sc-sym-card--off .sc-sym-name { color: #94A3B8; }

/* Onset date inputs */
.sc-onset-row {
  display: flex; align-items: center; gap: 9px;
  padding: 8px 12px; margin-top: 4px;
  background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
  border-radius: 10px; border: 1px solid rgba(204,136,0,.12);
}
.sc-onset-ic { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sc-onset-ic svg { width: 14px; height: 14px; }
.sc-onset-body { flex: 1; }
.sc-onset-lbl { font-size: 9px; font-weight: 700; color: #CC8800; letter-spacing: .8px; text-transform: uppercase; display: block; margin-bottom: 2px; }
.sc-onset-input {
  border: 1.5px solid rgba(204,136,0,.2); border-radius: 10px;
  padding: 8px 10px; font-size: 16px; color: #0B1A30;
  background: #fff; outline: none;
  font-family: inherit;
  min-height: 44px; box-sizing: border-box;
  transition: all .25s;
}
.sc-onset-input:focus { border-color: rgba(204,136,0,.5); box-shadow: 0 0 0 3px rgba(204,136,0,.08); }

/* ── VITALS TOGGLE ────────────────────────────────────────────── */
.sc-vitals-toggle-hdr {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px; margin-top: 10px;
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px solid rgba(0,0,0,.06);
  border-radius: 14px;
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 20px rgba(0,30,80,.06);
  cursor: pointer; -webkit-tap-highlight-color: transparent;
  transition: all .2s cubic-bezier(.16,1,.3,1);
  min-height: 48px; box-sizing: border-box;
}
.sc-vitals-toggle-hdr:active { transform: scale(.99); }
.sc-vitals-toggle-left { display: flex; align-items: center; gap: 8px; }
.sc-vitals-toggle-ic {
  width: 32px; height: 32px; border-radius: 8px;
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
  border: 1px solid rgba(0,112,224,.15);
  display: flex; align-items: center; justify-content: center;
}
.sc-vitals-toggle-ic svg { width: 14px; height: 14px; stroke: #0070E0; }
.sc-vitals-toggle-lbl { font-size: 14px; font-weight: 600; color: #0B1A30; font-family: inherit; }
.sc-vitals-badge {
  padding: 3px 9px; border-radius: 5px; font-size: 9px; font-weight: 700;
  background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%);
  color: #94A3B8; border: 1px solid rgba(0,0,0,.06);
  font-family: inherit;
}
.sc-vitals-chevron { width: 16px; height: 16px; stroke: #94A3B8; transition: transform .25s cubic-bezier(.16,1,.3,1); }
.sc-vitals-chevron--open { transform: rotate(180deg); }

.sc-vitals-panel {
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px solid rgba(0,0,0,.06);
  border-radius: 14px;
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 20px rgba(0,30,80,.06);
  padding: 16px; margin-top: 6px;
  display: flex; flex-direction: column; gap: 12px;
  position: relative; overflow: hidden;
}
.sc-vitals-panel::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent 20%, rgba(255,255,255,.8) 50%, transparent 80%);
}
.sc-vitals-note {
  font-size: 11px; color: #94A3B8;
  background: linear-gradient(180deg, #E4EBF7 0%, #EAF0FA 100%);
  border-radius: 8px; padding: 8px 12px;
  border: 1px solid rgba(0,0,0,.04);
}

/* Vitals inputs */
.sc-vt-row { display: flex; flex-direction: column; gap: 4px; }
.sc-vt-row--half { flex: 1; }
.sc-vt-pair { display: flex; gap: 10px; }
.sc-vt-lbl {
  font-family: inherit;
  font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: #94A3B8;
}
.sc-vt-inputs { display: flex; gap: 6px; }
.sc-vt-num {
  border: 1.5px solid rgba(0,0,0,.08); border-radius: 10px;
  padding: 10px 12px; font-size: 16px; font-weight: 600; color: #0B1A30;
  background: linear-gradient(145deg, #E8EDF7, #F0F3FA);
  outline: none; font-family: inherit; width: 100%;
  min-height: 48px; box-sizing: border-box;
  transition: all .25s;
}
.sc-vt-num--short { max-width: 100px; }
.sc-vt-num:focus { border-color: rgba(0,112,224,.35); box-shadow: 0 0 0 3px rgba(0,112,224,.08); background: #fff; }
.sc-vt-unit {
  border: 1.5px solid rgba(0,0,0,.08); border-radius: 10px; padding: 10px 8px;
  font-size: 14px; color: #475569;
  background: linear-gradient(145deg, #E8EDF7, #F0F3FA);
  outline: none; font-family: inherit; width: 64px; flex-shrink: 0;
  -webkit-appearance: none; min-height: 48px; box-sizing: border-box;
}
.sc-vt-warn {
  font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px;
  font-family: inherit;
}
.sc-vt-warn--sm   { font-size: 10px; }
.sc-vt-warn--warn { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); color: #CC8800; border: 1px solid rgba(204,136,0,.12); }
.sc-vt-warn--crit { background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); color: #E02050; border: 1px solid rgba(224,32,80,.1); }

/* Triage */
.sc-triage-row { display: flex; gap: 6px; }
.sc-triage-btn {
  flex: 1; padding: 10px 6px; border-radius: 12px;
  display: flex; flex-direction: column; align-items: center; gap: 3px;
  border: 1.5px solid; cursor: pointer;
  min-height: 48px; box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
  transition: all .15s cubic-bezier(.16,1,.3,1);
}
.sc-triage-btn--non_urgent { background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%); border-color: rgba(0,112,224,.15); }
.sc-triage-btn--non_urgent .sc-triage-lbl { color: #0070E0; }
.sc-triage-btn--urgent     { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); border-color: rgba(204,136,0,.15); }
.sc-triage-btn--urgent .sc-triage-lbl     { color: #CC8800; }
.sc-triage-btn--emergency  { background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); border-color: rgba(224,32,80,.15); }
.sc-triage-btn--emergency .sc-triage-lbl  { color: #E02050; }
.sc-triage-btn--active     { box-shadow: 0 0 0 3px rgba(0,112,224,.12); transform: translateY(-1px); }
.sc-triage-lbl { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; font-family: inherit; }
.sc-triage-sub { font-size: 8.5px; color: #94A3B8; }

/* Bool toggle */
.sc-bool-row { display: flex; gap: 6px; }
.sc-bool-btn {
  padding: 8px 14px; border-radius: 10px;
  border: 1.5px solid rgba(0,0,0,.06);
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  font-size: 13px; font-weight: 600; color: #475569;
  font-family: inherit;
  cursor: pointer; transition: all .15s;
  min-height: 44px; box-sizing: border-box;
}
.sc-bool-btn--yes {
  background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%);
  border-color: rgba(224,32,80,.1); color: #E02050;
}

/* ── EXPOSURES ────────────────────────────────────────────────── */
.sc-exposure-intro {
  font-size: 12px; color: #475569; line-height: 1.5;
  background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
  border: 1px solid rgba(204,136,0,.12);
  border-radius: 12px; padding: 12px 14px; margin-bottom: 10px;
  font-family: inherit;
}
.sc-exposure-list { display: flex; flex-direction: column; gap: 10px; }

.sc-exp-card {
  display: flex; gap: 12px; align-items: flex-start;
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px solid rgba(0,0,0,.06);
  border-radius: 14px; padding: 14px 16px;
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 20px rgba(0,30,80,.06);
  position: relative; overflow: hidden;
  transition: all .25s cubic-bezier(.16,1,.3,1);
}
.sc-exp-card::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent 20%, rgba(255,255,255,.8) 50%, transparent 80%);
}
.sc-exp-num {
  width: 24px; height: 24px; border-radius: 50%;
  background: linear-gradient(135deg, #0055CC, #0070E0);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 900; color: #fff; flex-shrink: 0; margin-top: 1px;
}
.sc-exp-body { flex: 1; position: relative; z-index: 1; }
.sc-exp-question {
  font-family: inherit;
  font-size: 13.5px; font-weight: 600; color: #0B1A30;
  line-height: 1.45; margin-bottom: 10px;
}
.sc-exp-btns { display: flex; gap: 8px; }
.sc-exp-btn {
  flex: 1; padding: 10px 6px; border-radius: 10px;
  font-size: 13px; font-weight: 600; border: 1.5px solid;
  cursor: pointer; text-align: center;
  font-family: inherit;
  min-height: 44px; box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
  transition: all .15s cubic-bezier(.16,1,.3,1);
  position: relative; overflow: hidden;
}
.sc-exp-btn::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent 20%, rgba(255,255,255,.25) 50%, transparent 80%);
}
.sc-exp-btn:active { transform: scale(.95); }

.sc-exp-btn--yes    { background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%); border-color: rgba(0,168,107,.12); color: #00A86B; }
.sc-exp-btn--yes.sc-exp-btn--active {
  background: linear-gradient(135deg, #007A50 0%, #00A86B 100%);
  border-color: transparent; color: #fff;
  box-shadow: 0 4px 16px rgba(0,168,107,.25);
}

.sc-exp-btn--no     { background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); border-color: rgba(224,32,80,.1); color: #E02050; }
.sc-exp-btn--no.sc-exp-btn--active {
  background: linear-gradient(135deg, #B01840 0%, #E02050 100%);
  border-color: transparent; color: #fff;
  box-shadow: 0 4px 16px rgba(224,32,80,.2);
}

.sc-exp-btn--unk    { background: linear-gradient(145deg, #F8FAFC, #F1F5F9); border-color: rgba(0,0,0,.06); color: #475569; }
.sc-exp-btn--unk.sc-exp-btn--active {
  background: linear-gradient(135deg, #475569, #64748B); border-color: transparent; color: #fff;
}

.sc-exp-summary {
  display: flex; align-items: center; gap: 8px;
  margin-top: 10px; padding: 10px 14px; border-radius: 12px;
  background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
  border: 1px solid rgba(204,136,0,.12);
  font-size: 12px; color: #CC8800;
  font-family: inherit;
}
.sc-exp-summary svg { width: 14px; height: 14px; flex-shrink: 0; }

/* ── ANALYSIS ─────────────────────────────────────────────────── */
.sc-insuff-warn {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 14px; border-radius: 12px;
  background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
  border: 1px solid rgba(204,136,0,.12);
  font-size: 12px; color: #CC8800; margin-bottom: 8px;
}
.sc-insuff-warn svg { width: 14px; height: 14px; flex-shrink: 0; }

.sc-flag-banner {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px; border-radius: 12px; margin-bottom: 6px;
  background: linear-gradient(135deg, #B01840 0%, #E02050 100%);
  box-shadow: 0 4px 16px rgba(224,32,80,.2);
}
.sc-flag-banner svg { width: 16px; height: 16px; flex-shrink: 0; }
.sc-flag-txt { font-size: 12px; font-weight: 700; color: #fff; font-family: inherit; }

.sc-empty-analysis {
  display: flex; align-items: center; gap: 10px;
  padding: 16px;
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px dashed rgba(0,0,0,.1); border-radius: 14px;
  font-size: 12px; color: #94A3B8;
  box-shadow: 0 1px 3px rgba(0,0,0,.03);
}
.sc-empty-analysis svg { width: 22px; height: 22px; flex-shrink: 0; }

/* Disease cards */
.sc-disease-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 4px; }
.sc-disease-card {
  display: flex; align-items: center; gap: 10px;
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px solid rgba(0,0,0,.06);
  border-radius: 14px; padding: 12px 14px;
  border-left: 4px solid #94A3B8;
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 20px rgba(0,30,80,.06);
  position: relative; overflow: hidden;
  cursor: pointer;
  transition: all .25s cubic-bezier(.16,1,.3,1);
}
.sc-disease-card::before {
  content: ''; position: absolute; top: 0; left: 4px; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent 20%, rgba(255,255,255,.8) 50%, transparent 80%);
}
.sc-disease-card:active { transform: scale(.98); }
.sc-disease-card--very_high { border-left-color: #E02050; box-shadow: 0 0 8px rgba(224,32,80,.15), 0 1px 3px rgba(0,0,0,.04); }
.sc-disease-card--high      { border-left-color: #CC8800; box-shadow: 0 0 8px rgba(204,136,0,.15), 0 1px 3px rgba(0,0,0,.04); }
.sc-disease-card--moderate  { border-left-color: #CC8800; }
.sc-disease-card--low       { border-left-color: #0070E0; }
.sc-disease-card--very_low  { border-left-color: #94A3B8; }

.sc-dc-rank {
  width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0;
  background: linear-gradient(135deg, #F8FAFC, #F1F5F9);
  border: 1px solid rgba(0,0,0,.06);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 900; color: #475569;
  font-family: inherit;
}
.sc-dc-rank--top { background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); border-color: rgba(224,32,80,.1); color: #E02050; }
.sc-dc-body { flex: 1; min-width: 0; }
.sc-dc-name { font-family: inherit; font-size: 13px; font-weight: 700; color: #0B1A30; }
.sc-dc-meta { display: flex; align-items: center; gap: 6px; margin-top: 3px; flex-wrap: wrap; }
.sc-dc-score { font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace; font-size: 10px; font-weight: 500; color: #475569; letter-spacing: .3px; }
.sc-dc-band {
  font-family: inherit;
  font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
  padding: 2px 7px; border-radius: 5px; border: 1px solid;
}
.sc-dc-band--very_high { background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); color: #E02050; border-color: rgba(224,32,80,.1); }
.sc-dc-band--high      { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); color: #CC8800; border-color: rgba(204,136,0,.12); }
.sc-dc-band--moderate  { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); color: #CC8800; border-color: rgba(204,136,0,.12); }
.sc-dc-band--low       { background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%); color: #0070E0; border-color: rgba(0,112,224,.12); }
.sc-dc-band--very_low  { background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%); color: #94A3B8; border-color: rgba(0,0,0,.06); }
.sc-dc-ihr  {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 9px; font-weight: 500; color: #7B40D8;
  background: linear-gradient(135deg, #F5F3FF 0%, #EDE9FE 100%);
  border: 1px solid rgba(123,64,216,.12);
  padding: 2px 7px; border-radius: 5px;
}
.sc-dc-hallmarks { margin-top: 4px; display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.sc-dc-hlbl { font-size: 9px; font-weight: 700; color: #94A3B8; }
.sc-dc-htag {
  font-size: 9px;
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
  color: #0070E0; padding: 2px 6px; border-radius: 5px; font-weight: 700;
  border: 1px solid rgba(0,112,224,.12);
}
.sc-dc-pct {
  font-family: inherit;
  font-size: 18px; font-weight: 800; flex-shrink: 0; letter-spacing: -1px;
  line-height: 1;
}
.sc-dc-pct--very_high { color: #E02050; }
.sc-dc-pct--high      { color: #CC8800; }
.sc-dc-pct--moderate  { color: #CC8800; }
.sc-dc-pct--low       { color: #0070E0; }
.sc-dc-pct--very_low  { color: #94A3B8; }

/* Syndrome grid */
.sc-syndrome-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.sc-syn-btn {
  padding: 10px 4px; border-radius: 12px;
  border: 1.5px solid rgba(0,0,0,.06);
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  text-align: center; cursor: pointer;
  min-height: 48px; box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
  transition: all .15s cubic-bezier(.16,1,.3,1);
  box-shadow: 0 1px 3px rgba(0,0,0,.03);
}
.sc-syn-btn:active { transform: scale(.95); }
.sc-syn-code {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 10px; font-weight: 600; letter-spacing: .3px; display: block; color: #475569;
}
.sc-syn-name { font-family: inherit; font-size: 8.5px; font-weight: 500; display: block; margin-top: 1px; color: #94A3B8; line-height: 1.2; }
.sc-syn-btn--active {
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
  border-color: rgba(0,112,224,.3);
  box-shadow: 0 2px 8px rgba(0,112,224,.1);
}
.sc-syn-btn--active .sc-syn-code { color: #0070E0; }
.sc-syn-btn--active .sc-syn-name { color: #0070E0; }
.sc-syn-btn--danger { border-color: rgba(224,32,80,.15); }
.sc-syn-btn--danger .sc-syn-code { color: #E02050; }
.sc-syn-btn--danger.sc-syn-btn--active { background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); border-color: rgba(224,32,80,.2); }

/* Risk level */
.sc-risk-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
.sc-risk-btn {
  padding: 10px 4px; border-radius: 12px; border: 1.5px solid;
  text-align: center; cursor: pointer;
  min-height: 48px; box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
  transition: all .15s cubic-bezier(.16,1,.3,1);
}
.sc-risk-btn:active { transform: scale(.95); }
.sc-risk-lbl { font-family: inherit; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; display: block; }
.sc-risk-sub { font-size: 8.5px; font-weight: 500; display: block; margin-top: 1px; }
.sc-risk-btn--low      { background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%); border-color: rgba(0,168,107,.12); }
.sc-risk-btn--low .sc-risk-lbl      { color: #00A86B; }
.sc-risk-btn--low .sc-risk-sub      { color: #00A86B; opacity: .6; }
.sc-risk-btn--medium   { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); border-color: rgba(204,136,0,.12); }
.sc-risk-btn--medium .sc-risk-lbl   { color: #CC8800; }
.sc-risk-btn--medium .sc-risk-sub   { color: #CC8800; opacity: .6; }
.sc-risk-btn--high     { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); border-color: rgba(204,136,0,.15); }
.sc-risk-btn--high .sc-risk-lbl     { color: #CC8800; }
.sc-risk-btn--high .sc-risk-sub     { color: #CC8800; opacity: .6; }
.sc-risk-btn--critical { background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); border-color: rgba(224,32,80,.12); }
.sc-risk-btn--critical .sc-risk-lbl { color: #E02050; }
.sc-risk-btn--critical .sc-risk-sub { color: #E02050; opacity: .6; }
.sc-risk-btn--active   { box-shadow: 0 0 0 3px rgba(0,112,224,.1); transform: translateY(-1px); }

/* Alert preview */
.sc-alert-preview {
  background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
  border: 2px solid rgba(204,136,0,.25); border-radius: 16px; overflow: hidden; margin-top: 8px;
  box-shadow: 0 4px 20px rgba(204,136,0,.1);
}
.sc-ap-hdr {
  background: linear-gradient(135deg, #CC8800 0%, #E6A000 100%);
  padding: 12px 16px; display: flex; align-items: center; gap: 8px;
}
.sc-ap-hdr svg { width: 16px; height: 16px; flex-shrink: 0; }
.sc-ap-title { font-family: inherit; font-size: 12px; font-weight: 700; color: #fff; flex: 1; }

/* ── Notification verification panel ──────────────────────────── */
.sc-sec-badge--auto {
  background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
  color: #00A86B; border-color: rgba(0,168,107,.12);
}
.sc-auto-hint {
  display: flex; align-items: center; gap: 5px;
  font-size: 11px; color: #00A86B;
  background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
  border: 1px solid rgba(0,168,107,.12); border-radius: 8px;
  padding: 8px 12px; margin-bottom: 8px;
}
.sc-verify-panel {
  margin: 16px 0 8px;
  border: 1.5px solid rgba(0,112,224,.3);
  border-radius: 14px;
  overflow: hidden;
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 20px rgba(0,30,80,.06);
}
.sc-verify-header {
  display: flex; align-items: center; gap: 8px;
  background: linear-gradient(135deg, #0055CC 0%, #0070E0 100%);
  color: #fff; padding: 10px 14px;
}
.sc-verify-title { font-family: inherit; font-size: 12px; font-weight: 700; flex: 1; }
.sc-verify-btn {
  background: rgba(255,255,255,0.15); color: #fff;
  border: 1px solid rgba(255,255,255,.25); border-radius: 8px;
  padding: 6px 14px; font-size: 11px; font-weight: 600; cursor: pointer;
  font-family: inherit;
  min-height: 36px;
  transition: all .15s;
}
.sc-verify-btn:disabled { opacity: 0.5; cursor: default; }
.sc-verify-btn--sync { background: rgba(255,255,255,0.25); }

/* Sync result block */
.sc-sync-result-block { border-top: 1px solid rgba(0,0,0,.04); padding: 0 0 4px; }
.sc-srb-title { font-family: inherit; font-size: 10px; color: #94A3B8; padding: 6px 14px 2px; }
.sc-srb-row { display: flex; align-items: baseline; gap: 6px; padding: 6px 14px; border-bottom: 1px solid rgba(0,0,0,.04); font-size: 11px; }
.sc-srb-row--ok      { background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 50%); }
.sc-srb-row--fail    { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 50%); }
.sc-srb-row--skip    { background: #F8FAFC; }
.sc-srb-row--pending { background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 50%); }
.sc-srb-phase  { font-weight: 700; color: #0B1A30; min-width: 110px; flex-shrink: 0; font-family: inherit; }
.sc-srb-status { font-weight: 700; min-width: 36px; text-transform: uppercase; font-size: 10px; font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace; }
.sc-srb-row--ok   .sc-srb-status { color: #00A86B; }
.sc-srb-row--fail .sc-srb-status { color: #CC8800; }
.sc-srb-row--skip .sc-srb-status { color: #94A3B8; }
.sc-srb-msg    { font-size: 10.5px; color: #475569; flex: 1; word-break: break-all; }
.sc-srb-error  { padding: 6px 14px; font-size: 11px; color: #E02050; background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); font-weight: 600; }
.sc-verify-idle { padding: 14px; font-size: 12px; color: #94A3B8; text-align: center; }
.sc-verify-error { padding: 10px 14px; font-size: 12px; color: #E02050; background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); }
.sc-verify-results { padding: 8px 0 0; }
.sc-verify-row {
  display: flex; align-items: flex-start; gap: 8px;
  padding: 8px 14px; border-bottom: 1px solid rgba(0,0,0,.04);
}
.sc-verify-row--pass { background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 50%); }
.sc-verify-row--fail { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 50%); }
.sc-verify-icon { font-size: 13px; font-weight: 800; min-width: 14px; margin-top: 1px; }
.sc-verify-row--pass .sc-verify-icon { color: #00A86B; }
.sc-verify-row--fail .sc-verify-icon { color: #CC8800; }
.sc-verify-body { flex: 1; min-width: 0; }
.sc-verify-label { font-family: inherit; font-size: 11.5px; font-weight: 600; color: #0B1A30; line-height: 1.3; }
.sc-verify-detail { font-size: 10.5px; color: #475569; margin-top: 2px; word-break: break-all; }
.sc-verify-summary {
  margin: 8px 14px 10px; padding: 8px 12px; border-radius: 8px;
  font-size: 12px; font-weight: 700; text-align: center;
  font-family: inherit;
}
.sc-verify-summary--ok  { background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%); color: #007A50; }
.sc-verify-summary--warn { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); color: #CC8800; }
.sc-verify-raw {
  margin: 4px 14px 10px; border: 1px solid rgba(0,0,0,.06); border-radius: 8px; overflow: hidden;
}
.sc-verify-raw summary {
  padding: 6px 10px; font-size: 11px; font-weight: 600; color: #475569;
  background: linear-gradient(180deg, #E4EBF7 0%, #EAF0FA 100%);
  cursor: pointer; user-select: none;
}
.sc-verify-raw pre {
  margin: 0; padding: 10px;
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 10px; color: #0B1A30;
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  overflow-x: auto; max-height: 200px; overflow-y: auto;
  white-space: pre-wrap; word-break: break-all;
}
.sc-ap-badge {
  padding: 3px 9px; border-radius: 5px; background: rgba(255,255,255,.2);
  border: 1px solid rgba(255,255,255,.3); font-size: 9px; font-weight: 700;
  color: #fff; text-transform: uppercase; letter-spacing: .6px;
  font-family: inherit;
}
.sc-ap-body { padding: 12px 16px; display: flex; flex-direction: column; gap: 8px; }
.sc-ap-row { display: flex; align-items: center; justify-content: space-between; }
.sc-ap-k { font-family: inherit; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: #94A3B8; }
.sc-ap-v { font-family: inherit; font-size: 12px; font-weight: 700; color: #0B1A30; }
.sc-ap-v--warn { color: #CC8800; }
.sc-ap-target {
  padding: 3px 9px; border-radius: 5px; font-size: 10.5px; font-weight: 700; border: 1px solid;
  font-family: inherit;
}
.sc-ap-target--district  { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); color: #CC8800; border-color: rgba(204,136,0,.12); }
.sc-ap-target--pheoc     { background: linear-gradient(135deg, #F5F3FF 0%, #EDE9FE 100%); color: #7B40D8; border-color: rgba(123,64,216,.12); }
.sc-ap-target--national  { background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); color: #E02050; border-color: rgba(224,32,80,.1); }

/* Actions */
.sc-actions-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.sc-action-btn {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 12px; border-radius: 12px;
  border: 1.5px solid rgba(0,0,0,.06);
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  font-family: inherit;
  font-size: 12px; font-weight: 600; color: #475569;
  cursor: pointer; text-align: left;
  min-height: 44px; box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
  transition: all .15s cubic-bezier(.16,1,.3,1);
  box-shadow: 0 1px 3px rgba(0,0,0,.03);
}
.sc-action-btn:active { transform: scale(.96); }
.sc-action-btn--active {
  background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
  border-color: rgba(0,168,107,.12); color: #007A50; font-weight: 700;
}
.sc-action-ic {
  width: 20px; height: 20px; border-radius: 6px;
  border: 1.5px solid rgba(0,0,0,.1);
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sc-action-btn--active .sc-action-ic {
  background: linear-gradient(135deg, #007A50, #00A86B);
  border-color: transparent;
  box-shadow: 0 0 8px rgba(0,168,107,.35);
}
.sc-action-dot { width: 6px; height: 6px; border-radius: 50%; background: rgba(0,0,0,.1); }
.sc-action-ic svg { width: 11px; height: 11px; }

/* Enforce warn */
.sc-enforce-warn {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 14px; border-radius: 12px; margin-top: 8px;
  background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%);
  border: 1.5px solid rgba(224,32,80,.1);
  font-size: 12px; color: #E02050;
  font-family: inherit;
}
.sc-enforce-warn svg { width: 14px; height: 14px; flex-shrink: 0; }

/* Disposition */
.sc-disp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.sc-disp-btn {
  display: flex; flex-direction: column; align-items: center; gap: 5px;
  padding: 12px 8px; border-radius: 14px;
  border: 1.5px solid rgba(0,0,0,.06);
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  cursor: pointer; text-align: center;
  min-height: 56px; box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
  transition: all .15s cubic-bezier(.16,1,.3,1);
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 20px rgba(0,30,80,.06);
  position: relative; overflow: hidden;
}
.sc-disp-btn::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent 20%, rgba(255,255,255,.8) 50%, transparent 80%);
}
.sc-disp-btn:active { transform: scale(.96); }
.sc-disp-btn--active {
  background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
  border-color: rgba(0,168,107,.2);
  box-shadow: 0 2px 8px rgba(0,168,107,.15);
}
.sc-disp-ic {
  width: 32px; height: 32px; border-radius: 8px;
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
  border: 1px solid rgba(0,112,224,.15);
  display: flex; align-items: center; justify-content: center;
}
.sc-disp-ic svg { width: 14px; height: 14px; stroke: #475569; }
.sc-disp-btn--active .sc-disp-ic {
  background: linear-gradient(135deg, #007A50 0%, #00A86B 100%);
  border-color: transparent;
}
.sc-disp-btn--active .sc-disp-ic svg { stroke: #fff; }
.sc-disp-lbl { font-family: inherit; font-size: 11px; font-weight: 600; color: #475569; }
.sc-disp-btn--active .sc-disp-lbl { color: #007A50; font-weight: 700; }

/* Notes */
.sc-notes-wrap {
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px solid rgba(0,0,0,.06);
  border-radius: 14px; overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 20px rgba(0,30,80,.06);
  position: relative;
}
.sc-notes-wrap::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent 20%, rgba(255,255,255,.8) 50%, transparent 80%);
  z-index: 1;
}
.sc-notes-input {
  width: 100%; padding: 14px 16px; border: none; outline: none;
  font-size: 14px; color: #0B1A30;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Ubuntu, 'Helvetica Neue', Arial, sans-serif;
  line-height: 1.5; resize: vertical; min-height: 100px; background: transparent;
}
.sc-notes-input::placeholder { color: #94A3B8; }

/* Follow-up */
.sc-followup-row { display: flex; align-items: center; gap: 10px; margin-top: 8px; flex-wrap: wrap; }
.sc-followup-toggle {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 16px; border-radius: 12px;
  border: 1.5px solid rgba(0,0,0,.06);
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  font-family: inherit;
  font-size: 13px; font-weight: 600; color: #475569;
  cursor: pointer; -webkit-tap-highlight-color: transparent;
  transition: all .15s cubic-bezier(.16,1,.3,1);
  min-height: 48px; box-sizing: border-box;
  box-shadow: 0 1px 3px rgba(0,0,0,.03);
}
.sc-followup-toggle--on {
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
  border-color: rgba(0,112,224,.3); color: #0070E0;
}
.sc-ft-indicator {
  width: 18px; height: 18px; border-radius: 50%;
  border: 2px solid rgba(0,0,0,.1); background: #fff; flex-shrink: 0;
  transition: all .15s;
}
.sc-followup-toggle--on .sc-ft-indicator {
  background: #0070E0; border-color: #0055CC;
  box-shadow: 0 0 8px rgba(0,112,224,.35);
}
.sc-ft-lbl { font-size: 12.5px; }
.sc-followup-level {
  border: 1.5px solid rgba(0,0,0,.08); border-radius: 10px;
  padding: 10px 12px; font-size: 14px; color: #0B1A30;
  background: linear-gradient(145deg, #E8EDF7, #F0F3FA);
  outline: none; font-family: inherit;
  -webkit-appearance: none;
  min-height: 48px; box-sizing: border-box;
}

/* Suspected diseases list */
.sc-sus-list {
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px solid rgba(0,0,0,.06);
  border-radius: 14px; overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 20px rgba(0,30,80,.06);
}
.sc-sus-row {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px; border-bottom: 1px solid rgba(0,0,0,.04);
}
.sc-sus-row:last-child { border-bottom: none; }
.sc-sus-rank {
  width: 22px; height: 22px; border-radius: 6px;
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
  border: 1px solid rgba(0,112,224,.12);
  display: flex; align-items: center; justify-content: center;
  font-family: inherit;
  font-size: 10px; font-weight: 800; color: #0070E0; flex-shrink: 0;
}
.sc-sus-name { flex: 1; font-family: inherit; font-size: 13px; font-weight: 600; color: #0B1A30; text-transform: capitalize; }
.sc-sus-conf {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 12px; font-weight: 500; color: #0070E0;
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
  border: 1px solid rgba(0,112,224,.12);
  padding: 2px 8px; border-radius: 5px;
}

/* ── FOOTER — Premium gradient ───────────────────────────────── */
.sc-footer {
  background: linear-gradient(180deg, #F4F7FC 0%, #FFFFFF 100%);
  border-top: 1px solid rgba(0,0,0,.06);
  box-shadow: 0 -2px 12px rgba(0,30,80,.06);
}
.sc-footer-inner {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 16px 10px; gap: 10px;
  padding-bottom: max(10px, env(safe-area-inset-bottom));
}
.sc-nav-spacer { flex: 1; }

.sc-nav-btn {
  display: flex; align-items: center; gap: 6px;
  height: 48px; border-radius: 12px; font-size: 14px; font-weight: 600;
  font-family: inherit;
  letter-spacing: .5px;
  cursor: pointer; border: none; padding: 0 20px;
  -webkit-tap-highlight-color: transparent;
  transition: all .15s cubic-bezier(.16,1,.3,1);
  position: relative; overflow: hidden;
}
.sc-nav-btn::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent 20%, rgba(255,255,255,.25) 50%, transparent 80%);
}
.sc-nav-btn:active { transform: scale(.97); }
.sc-nav-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }
.sc-nav-btn svg { width: 14px; height: 14px; flex-shrink: 0; }

.sc-nav-btn--back {
  background: linear-gradient(145deg, #FFFFFF, #F4F7FC);
  border: 1.5px solid rgba(0,112,224,.25);
  color: #0070E0;
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 20px rgba(0,30,80,.06);
}
.sc-nav-btn--back svg { stroke: #0070E0; }

.sc-nav-btn--next {
  flex: 1; justify-content: center;
  background: linear-gradient(135deg, #0055CC 0%, #0070E0 50%, #3399FF 100%);
  color: #fff;
  box-shadow: 0 4px 16px rgba(0,112,224,.25);
}
.sc-nav-btn--next svg { stroke: #fff; }

.sc-nav-btn--analyse {
  flex: 1; justify-content: center;
  background: linear-gradient(135deg, #CC8800 0%, #E6A000 100%);
  color: #fff;
  box-shadow: 0 4px 16px rgba(204,136,0,.25);
}

.sc-nav-btn--disposition {
  flex: 1; justify-content: center;
  background: linear-gradient(135deg, #007A50 0%, #00A86B 100%);
  color: #fff;
  box-shadow: 0 4px 16px rgba(0,168,107,.25);
}
.sc-nav-btn--disposition:disabled {
  background: linear-gradient(145deg, #F8FAFC, #F1F5F9);
  color: #94A3B8; box-shadow: none; border: 1px solid rgba(0,0,0,.06);
}

/* ── TRAVELER DIRECTION BADGE ─────────────────────────────── */
.sc-dir-badge {
  padding: 3px 9px;
  border-radius: 5px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: .8px;
  text-transform: uppercase;
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  flex-shrink: 0;
  border: 1px solid;
}
.sc-dir--entry   { background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%); color: #0070E0; border-color: rgba(0,112,224,.15); }
.sc-dir--exit    { background: linear-gradient(135deg, #F5F3FF 0%, #EDE9FE 100%); color: #7B40D8; border-color: rgba(123,64,216,.15); }
.sc-dir--transit { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); color: #CC8800; border-color: rgba(204,136,0,.15); }

/* ── NON-CASE BANNER ──────────────────────────────────────── */
.sc-noncase-banner {
  background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
  border: 1.5px solid rgba(0,168,107,.12); border-radius: 14px;
  padding: 14px 16px; margin-bottom: 12px;
  display: flex; flex-direction: column; gap: 6px;
  box-shadow: 0 1px 3px rgba(0,0,0,.03);
}
.sc-nc-hdr {
  display: flex; align-items: center; gap: 8px;
  font-family: inherit;
  font-size: 13px; font-weight: 700; color: #007A50;
}
.sc-nc-reason { font-family: inherit; font-size: 11px; color: #00A86B; font-weight: 600; padding-left: 24px; }
.sc-nc-action {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 11px; color: #00A86B; font-weight: 500; padding-left: 24px;
}
.sc-nc-override-btn {
  align-self: flex-start; margin-top: 4px;
  padding: 8px 14px; border-radius: 8px;
  font-size: 11px; font-weight: 600; cursor: pointer;
  font-family: inherit;
  background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
  border: 1px solid rgba(204,136,0,.2); color: #CC8800;
  min-height: 40px; box-sizing: border-box;
  transition: all .15s;
}
.sc-nc-override-note { display: flex; flex-direction: column; gap: 4px; }
.sc-nc-override-lbl { font-family: inherit; font-size: 10px; font-weight: 700; color: #CC8800; letter-spacing: .5px; }
.sc-override-input {
  width: 100%; padding: 10px 12px; border: 1.5px solid rgba(204,136,0,.2); border-radius: 10px;
  font-size: 13px; background: #fff;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Ubuntu, 'Helvetica Neue', Arial, sans-serif;
  color: #0B1A30; resize: vertical; min-height: 56px;
  transition: all .25s;
}
.sc-override-input:focus { border-color: rgba(204,136,0,.5); box-shadow: 0 0 0 3px rgba(204,136,0,.08); }

/* ── OUTBREAK CONTEXT ─────────────────────────────────────── */
.sc-outbreak-ctx {
  background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
  border: 1.5px solid rgba(204,136,0,.12); border-radius: 14px;
  padding: 12px 16px; margin-bottom: 12px;
  display: flex; flex-direction: column; gap: 6px;
  box-shadow: 0 1px 3px rgba(0,0,0,.03);
}
.sc-oc-hdr { display: flex; align-items: center; gap: 8px; font-family: inherit; font-size: 12px; font-weight: 700; color: #CC8800; }
.sc-oc-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.sc-oc-chip {
  padding: 3px 9px;
  background: rgba(204,136,0,.08);
  border: 1px solid rgba(204,136,0,.15);
  border-radius: 5px;
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 9.5px; font-weight: 500; color: #CC8800;
  text-transform: capitalize;
}
.sc-oc-chip--more { background: rgba(0,0,0,.04); color: #94A3B8; border-color: rgba(0,0,0,.08); }

/* ── IHR RISK RESULT ──────────────────────────────────────── */
.sc-ihr-result {
  padding: 12px 16px; border-radius: 14px; margin-bottom: 12px;
  display: flex; flex-direction: column; gap: 5px; border: 1.5px solid;
  box-shadow: 0 1px 3px rgba(0,0,0,.03);
}
.sc-ihr--low      { background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%); border-color: rgba(0,168,107,.12); }
.sc-ihr--medium   { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); border-color: rgba(204,136,0,.12); }
.sc-ihr--high     { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); border-color: rgba(204,136,0,.15); }
.sc-ihr--critical { background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); border-color: rgba(224,32,80,.12); }
.sc-ihr-hdr { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.sc-ihr-level {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 14px; font-weight: 600; letter-spacing: .3px;
}
.sc-ihr--low      .sc-ihr-level { color: #00A86B; }
.sc-ihr--medium   .sc-ihr-level { color: #CC8800; }
.sc-ihr--high     .sc-ihr-level { color: #CC8800; }
.sc-ihr--critical .sc-ihr-level { color: #E02050; }
.sc-ihr-routing {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 10px; font-weight: 500; color: #94A3B8;
}
.sc-ihr-alert-badge {
  padding: 3px 9px; border-radius: 5px; font-size: 9px; font-weight: 700; letter-spacing: .8px;
  background: linear-gradient(135deg, #B01840, #E02050); color: #fff;
  text-transform: uppercase;
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  box-shadow: 0 0 8px rgba(224,32,80,.35);
  animation: sc-dotPulse 1.5s ease-in-out infinite;
}
.sc-ihr-reason { font-family: inherit; font-size: 10px; color: #475569; font-weight: 600; padding-left: 8px; }

/* ── ENGINE SYNDROME HINT ─────────────────────────────────── */
.sc-syn-engine-hint, .sc-risk-engine-suggest {
  display: flex; align-items: flex-start; gap: 6px; padding: 10px 12px;
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
  border: 1px solid rgba(0,112,224,.12); border-radius: 10px;
  font-family: inherit;
  font-size: 11px; color: #0070E0; font-weight: 600; margin-bottom: 8px;
}
.sc-risk-apply-btn {
  margin-left: auto; padding: 5px 12px; border-radius: 8px; flex-shrink: 0;
  font-size: 10px; font-weight: 700; cursor: pointer;
  font-family: inherit;
  background: linear-gradient(135deg, #0055CC 0%, #0070E0 100%);
  color: #fff; border: none;
  box-shadow: 0 2px 8px rgba(0,112,224,.2);
  min-height: 32px;
  transition: all .15s;
}

/* ── DISEASE CARD — enhanced ──────────────────────────────── */
.sc-dc-name-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.sc-dc-syn-match {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 9px; font-weight: 500; padding: 2px 7px; border-radius: 5px;
  background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
  color: #00A86B; border: 1px solid rgba(0,168,107,.12);
}
.sc-dc-cfr {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 9px; padding: 2px 7px; border-radius: 5px;
  background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%);
  color: #E02050; font-weight: 500;
  border: 1px solid rgba(224,32,80,.1);
}
.sc-dc-def-toggle {
  font-family: inherit;
  font-size: 10px; color: #0070E0; font-weight: 700; background: none; border: none;
  cursor: pointer; padding: 4px 0; text-decoration: underline; text-align: left;
}
.sc-dc-casedefs {
  background: linear-gradient(180deg, #E4EBF7 0%, #EAF0FA 100%);
  border-radius: 8px; padding: 10px 12px; margin-top: 4px;
  display: flex; flex-direction: column; gap: 6px;
}
.sc-dc-def-row { display: flex; gap: 8px; align-items: flex-start; }
.sc-dc-def-k {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 8.5px; font-weight: 600; letter-spacing: .8px; text-transform: uppercase;
  flex-shrink: 0; width: 60px; padding-top: 1px;
}
.sc-dc-def-v { font-family: inherit; font-size: 10px; color: #475569; font-weight: 600; line-height: 1.4; }
.sc-dc-def--suspected .sc-dc-def-k { color: #CC8800; }
.sc-dc-def--confirmed  .sc-dc-def-k { color: #00A86B; }
.sc-dc-def--ihr        .sc-dc-def-k { color: #0070E0; }
.sc-dc-def--action     .sc-dc-def-k { color: #E02050; }
.sc-dc-def--action { background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); border-radius: 6px; padding: 6px 8px; }

/* ── OFFICER OVERRIDE ─────────────────────────────────────── */
.sc-override-section {}
.sc-override-hint { font-family: inherit; font-size: 11px; color: #475569; font-weight: 600; margin: 0 0 8px; line-height: 1.4; }
.sc-override-add-row { display: flex; gap: 8px; }
.sc-override-disease-select {
  flex: 1; padding: 10px 12px;
  border: 1.5px solid rgba(0,0,0,.08); border-radius: 10px;
  font-size: 14px;
  background: linear-gradient(145deg, #E8EDF7, #F0F3FA);
  color: #0B1A30; min-width: 0;
  font-family: inherit;
  min-height: 48px; box-sizing: border-box;
  transition: all .25s;
  appearance: none;
  -webkit-appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M2 4l4 4 4-4' fill='none' stroke='%23475569' stroke-width='1.5'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 32px;
}
.sc-override-disease-select:focus { border-color: rgba(0,112,224,.35); box-shadow: 0 0 0 3px rgba(0,112,224,.08); background-color: #fff; }
.sc-override-add-btn {
  padding: 10px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer;
  font-family: inherit;
  background: linear-gradient(135deg, #0055CC 0%, #0070E0 100%);
  color: #fff; border: none; flex-shrink: 0;
  box-shadow: 0 4px 16px rgba(0,112,224,.25);
  min-height: 48px; box-sizing: border-box;
  transition: all .15s;
  position: relative; overflow: hidden;
}
.sc-override-add-btn::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent 20%, rgba(255,255,255,.25) 50%, transparent 80%);
}
.sc-override-add-btn:disabled { opacity: .4; }
.sc-override-added { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.sc-override-tag {
  display: flex; align-items: center; gap: 4px; padding: 5px 12px;
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
  border: 1px solid rgba(0,112,224,.12);
  border-radius: 5px;
  font-family: inherit;
  font-size: 11px; font-weight: 700; color: #0070E0;
}
.sc-override-rm { background: none; border: none; cursor: pointer; font-size: 14px; color: #0070E0; padding: 0 2px; line-height: 1; min-width: 24px; min-height: 24px; display: flex; align-items: center; justify-content: center; }

/* ── NEW EXPOSURE UI ──────────────────────────────────────── */
.sc-exp-cat { margin-bottom: 16px; }
.sc-exp-cat-hdr {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 9.5px; font-weight: 500; letter-spacing: 1.5px; text-transform: uppercase;
  color: #94A3B8; padding: 8px 0 4px;
  border-bottom: 1px solid rgba(0,0,0,.04); margin-bottom: 8px;
}
.sc-exp-header-row { display: flex; align-items: flex-start; gap: 8px; flex-wrap: wrap; }
.sc-exp-risk {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 8.5px; font-weight: 600; padding: 2px 7px; border-radius: 5px; flex-shrink: 0; letter-spacing: .3px;
  border: 1px solid;
}
.sc-exp-risk--vhigh { background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%); color: #E02050; border-color: rgba(224,32,80,.1); }
.sc-exp-risk--high  { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); color: #CC8800; border-color: rgba(204,136,0,.12); }
.sc-exp-desc { font-family: inherit; font-size: 10.5px; color: #94A3B8; font-weight: 400; line-height: 1.4; margin: 2px 0 8px; }
.sc-exp-card--yes {
  border-color: rgba(224,32,80,.15);
  background: linear-gradient(135deg, #FEF2F2 0%, #FFF5F5 100%);
}
.sc-exp-card--high { border-left: 3px solid #CC8800; }
.sc-exp-critical {
  font-family: inherit;
  font-size: 10px; font-weight: 700; color: #E02050;
  background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%);
  border-radius: 6px; padding: 6px 10px; margin-top: 6px; display: flex; gap: 6px;
}
.sc-exp-hisig {
  display: flex; gap: 10px; align-items: flex-start; padding: 14px 16px;
  background: linear-gradient(135deg, #B01840 0%, #E02050 100%);
  color: #fff; border-radius: 14px; margin-bottom: 12px;
  box-shadow: 0 4px 16px rgba(224,32,80,.2);
}
.sc-exp-hisig-title { font-family: inherit; font-size: 12px; font-weight: 700; margin-bottom: 4px; }
.sc-exp-hisig-row   { font-size: 10px; font-weight: 600; }
.sc-exp-hisig-note  { font-size: 9.5px; color: rgba(255,255,255,0.8); font-weight: 500; }


/* ─── DISEASE DETAIL MODAL ──────────────────────────────────────── */
.sc-dm-wrap {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Ubuntu, 'Helvetica Neue', Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-rendering: optimizeLegibility;
  display: flex; flex-direction: column;
}
.sc-dm-hero {
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 60%, #EAF0FA 100%);
  padding: 18px 18px 14px;
  border-bottom: 1px solid rgba(0,112,224,.1);
}
.sc-dm-name {
  font-family: inherit;
  font-size: 20px; font-weight: 700; color: #0B1A30;
  line-height: 1.2; margin-bottom: 3px; letter-spacing: -.5px;
}
.sc-dm-id {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 10px; font-weight: 500; color: #94A3B8;
  text-transform: uppercase; letter-spacing: .8px; margin-bottom: 12px;
}
.sc-dm-metric-row {
  display: flex; gap: 16px; margin-bottom: 10px;
}
.sc-dm-metric {
  display: flex; flex-direction: column; gap: 2px;
}
.sc-dm-metric-val {
  font-family: inherit;
  font-size: 30px; font-weight: 800; line-height: 1;
  font-variant-numeric: tabular-nums;
}
.sc-dm-metric-lbl {
  font-family: inherit;
  font-size: 9px; font-weight: 700; color: #94A3B8;
  text-transform: uppercase; letter-spacing: 1.2px;
}
.sc-dm-mv--high { color: #E02050; }
.sc-dm-mv--med  { color: #CC8800; }
.sc-dm-mv--low  { color: #00A86B; }
.sc-dm-mv--warn { color: #E02050; }
.sc-dm-bar-track {
  height: 6px; background: rgba(0,0,0,.06); border-radius: 4px; overflow: hidden;
}
.sc-dm-bar-fill {
  height: 100%; border-radius: 4px;
  transition: width .6s cubic-bezier(.4,0,.2,1);
}
.sc-dm-bar--high { background: linear-gradient(90deg, #B01840, #E02050); }
.sc-dm-bar--med  { background: linear-gradient(90deg, #CC8800, #E6A000); }
.sc-dm-bar--low  { background: linear-gradient(90deg, #007A50, #00A86B); }

.sc-dm-band {
  font-family: inherit;
  font-size: 9px; font-weight: 700; padding: 3px 9px; border-radius: 5px;
  border: 1px solid; letter-spacing: .6px; text-transform: uppercase;
}
.sc-dm-band--very_high { background: linear-gradient(135deg, #FEF2F2, #FECACA); border-color: rgba(224,32,80,.15); color: #E02050; }
.sc-dm-band--high      { background: linear-gradient(135deg, #FEF2F2, #FECACA); border-color: rgba(224,32,80,.1); color: #E02050; }
.sc-dm-band--moderate  { background: linear-gradient(135deg, #FFFBEB, #FEF3C7); border-color: rgba(204,136,0,.15); color: #CC8800; }
.sc-dm-band--low       { background: linear-gradient(135deg, #F8FAFC, #F1F5F9); border-color: rgba(0,0,0,.06); color: #94A3B8; }

.sc-dm-section {
  padding: 12px 18px;
  border-bottom: 1px solid rgba(0,0,0,.04);
}
.sc-dm-section--who {
  background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
  border-left: 3px solid rgba(204,136,0,.25);
}
.sc-dm-section-lbl {
  font-family: inherit;
  font-size: 9px; font-weight: 700; color: #94A3B8;
  text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 6px;
}
.sc-dm-chips-row { display: flex; flex-wrap: wrap; gap: 6px; }
.sc-dm-ihr-chip {
  display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 5px;
  font-family: inherit;
  font-size: 10px; font-weight: 700; color: #0070E0;
  background: linear-gradient(135deg, #E0ECFF 0%, #CCE0FF 100%);
  border: 1px solid rgba(0,112,224,.12);
}
.sc-dm-syn-chip {
  display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 5px;
  font-family: inherit;
  font-size: 10px; font-weight: 700; color: #00A86B;
  background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
  border: 1px solid rgba(0,168,107,.12);
}
.sc-dm-syn-chip svg { width: 10px; height: 10px; }
.sc-dm-hallmarks { display: flex; flex-wrap: wrap; gap: 5px; }
.sc-dm-htag {
  padding: 3px 9px; border-radius: 5px; font-size: 10px; font-weight: 700;
  font-family: inherit;
  background: linear-gradient(135deg, #F8FAFC, #F1F5F9);
  color: #475569; border: 1px solid rgba(0,0,0,.06);
}
.sc-dm-breakdown { display: flex; flex-direction: column; }
.sc-dm-bd-row {
  display: flex; justify-content: space-between; align-items: baseline;
  padding: 6px 0; border-bottom: 1px solid rgba(0,0,0,.04);
}
.sc-dm-bd-row:last-child { border-bottom: none; }
.sc-dm-bd-k { font-family: inherit; font-size: 11px; font-weight: 600; color: #475569; text-transform: capitalize; }
.sc-dm-bd-v { font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; font-weight: 600; font-variant-numeric: tabular-nums; color: #94A3B8; }
.sc-dm-bd-pos { color: #00A86B; }
.sc-dm-bd-neg { color: #E02050; }
.sc-dm-def-text {
  font-family: inherit;
  font-size: 12px; line-height: 1.65; color: #0B1A30; margin: 0;
}
.sc-dm-def-text--muted { color: #94A3B8; font-style: italic; }
.sc-dm-poe-action {
  font-family: inherit;
  font-size: 12px; font-weight: 700; color: #CC8800;
  background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
  border-radius: 10px; padding: 10px 14px;
  border: 1px solid rgba(204,136,0,.12); line-height: 1.5;
}
.sc-dm-source {
  padding: 8px 18px; font-size: 9px; color: #94A3B8;
  font-family: inherit;
  font-style: italic; border-top: 1px solid rgba(0,0,0,.04);
}
</style>
<template>
  <IonPage>
    <IonHeader class="ag-header ion-no-border">
      <IonToolbar class="ag-toolbar">
        <IonButtons slot="start"><IonBackButton default-href="/home" class="ag-back" /></IonButtons>
        <IonTitle class="ag-title">Aggregated Submission</IonTitle>
        <IonButtons slot="end">
          <span class="ag-sync-pill" :class="syncPillClass">{{ SYNC.LABELS[syncStatus] || syncStatus }}</span>
        </IonButtons>
      </IonToolbar>
    </IonHeader>

    <IonContent class="ag-content">
      <div class="ag-card">

        <!-- Period selection -->
        <div class="ag-section-hdr">
          <span class="ag-sec-num">1</span>
          <span class="ag-sec-title">Reporting Period</span>
        </div>
        <div class="ag-period-row">
          <div class="ag-date-field">
            <label class="ag-label">From *</label>
            <input type="date" class="ag-input" v-model="form.period_start" :max="todayStr" />
          </div>
          <div class="ag-date-field">
            <label class="ag-label">To *</label>
            <input type="date" class="ag-input" v-model="form.period_end" :max="todayStr" />
          </div>
        </div>
        <div v-if="periodDays > 30" class="ag-warn">⚠ Period is {{ periodDays }} days — WHO recommends weekly submissions. Verify this range is correct.</div>
        <div v-if="fieldErrors.period" class="ag-field-err">{{ fieldErrors.period }}</div>

        <!-- Auto-calculate -->
        <button class="ag-auto-btn" @click="autoCalculate" :disabled="calculating">
          <span class="ag-auto-ic">🧮</span>
          {{ calculating ? 'Calculating from records…' : 'Auto-Calculate from Local Records' }}
        </button>
        <div v-if="autoMsg" class="ag-auto-msg">{{ autoMsg }}</div>

        <!-- Primary Screening Summary -->
        <div class="ag-section-hdr" style="margin-top:20px">
          <span class="ag-sec-num">2</span>
          <span class="ag-sec-title">Primary Screening Counts</span>
        </div>

        <div class="ag-count-grid">
          <div class="ag-count-field">
            <label class="ag-label">Total Screened *</label>
            <input type="number" inputmode="numeric" class="ag-count-input ag-count-input--primary" min="0" v-model.number="form.total_screened" />
          </div>
          <div class="ag-count-field">
            <label class="ag-label">Male</label>
            <input type="number" inputmode="numeric" class="ag-count-input" min="0" v-model.number="form.total_male" />
          </div>
          <div class="ag-count-field">
            <label class="ag-label">Female</label>
            <input type="number" inputmode="numeric" class="ag-count-input" min="0" v-model.number="form.total_female" />
          </div>
          <div class="ag-count-field">
            <label class="ag-label">Other</label>
            <input type="number" inputmode="numeric" class="ag-count-input" min="0" v-model.number="form.total_other" />
          </div>
          <div class="ag-count-field">
            <label class="ag-label">Unknown Gender</label>
            <input type="number" inputmode="numeric" class="ag-count-input" min="0" v-model.number="form.total_unknown_gender" />
          </div>
          <div class="ag-count-field">
            <label class="ag-label">Symptomatic</label>
            <input type="number" inputmode="numeric" class="ag-count-input ag-count-input--warn" min="0" v-model.number="form.total_symptomatic" />
          </div>
          <div class="ag-count-field">
            <label class="ag-label">Asymptomatic</label>
            <input type="number" inputmode="numeric" class="ag-count-input" min="0" v-model.number="form.total_asymptomatic" />
          </div>
        </div>

        <!-- Validation pills -->
        <div class="ag-validation-row">
          <span class="ag-val-pill" :class="genderOk ? 'ag-val-pill--ok' : 'ag-val-pill--warn'">
            Gender sum: {{ genderSum }} / {{ form.total_screened }} {{ genderOk ? '✓' : '≠' }}
          </span>
          <span class="ag-val-pill" :class="symptomOk ? 'ag-val-pill--ok' : 'ag-val-pill--warn'">
            Symptom sum: {{ symptomSum }} / {{ form.total_screened }} {{ symptomOk ? '✓' : '≠' }}
          </span>
        </div>

        <!-- Notes -->
        <div class="ag-section-hdr" style="margin-top:16px">
          <span class="ag-sec-num">3</span>
          <span class="ag-sec-title">Notes</span>
          <span class="ag-sec-opt">Optional</span>
        </div>
        <textarea class="ag-notes" rows="3" maxlength="255" placeholder="Context, exceptional events, retrospective counts…" v-model="form.notes" />

        <!-- Submit -->
        <div v-if="submitError" class="ag-submit-err">{{ submitError }}</div>
        <button class="ag-submit-btn" @click="submit" :disabled="submitting || !canSubmit">
          {{ submitting ? 'Saving…' : 'Save & Queue Submission' }}
        </button>

        <!-- Success -->
        <div v-if="submitted" class="ag-success">
          <span class="ag-success-icon">✅</span>
          <div>
            <div class="ag-success-title">Submission Saved</div>
            <div class="ag-success-body">Queued for upload. Will sync automatically when online.</div>
          </div>
        </div>

        <!-- Past submissions -->
        <div class="ag-section-hdr" style="margin-top:24px">
          <span class="ag-sec-num">4</span>
          <span class="ag-sec-title">Recent Submissions</span>
        </div>
        <div v-if="pastSubs.length === 0" class="ag-past-empty">No past submissions on this device.</div>
        <div v-for="s in pastSubs" :key="s.client_uuid" class="ag-past-row">
          <div class="ag-past-period">{{ formatDate(s.period_start) }} — {{ formatDate(s.period_end) }}</div>
          <div class="ag-past-total">{{ s.total_screened }} screened ({{ s.total_symptomatic }} symptomatic)</div>
          <span class="ag-past-sync" :class="s.sync_status === 'SYNCED' ? 'ag-past-sync--ok' : 'ag-past-sync--pending'">
            {{ SYNC.LABELS[s.sync_status] }}
          </span>
        </div>

      </div>
    </IonContent>
  </IonPage>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonBackButton,
} from '@ionic/vue'
import { onIonViewDidEnter } from '@ionic/vue'
import {
  dbPut, dbGetAll, dbGetByIndex, dbQuery,
  genUUID, isoNow, createRecordBase,
  STORE, SYNC,
} from '@/services/poeDB'

function getAuth() {
  return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {}
}

const todayStr = new Date().toISOString().slice(0, 10)

const form = reactive({
  period_start:        todayStr,
  period_end:          todayStr,
  total_screened:      0,
  total_male:          0,
  total_female:        0,
  total_other:         0,
  total_unknown_gender:0,
  total_symptomatic:   0,
  total_asymptomatic:  0,
  notes:               '',
})

const submitting  = ref(false)
const submitted   = ref(false)
const submitError = ref(null)
const calculating = ref(false)
const autoMsg     = ref('')
const syncStatus  = ref(SYNC.UNSYNCED)
const pastSubs    = ref([])
const fieldErrors = reactive({})

const periodDays = computed(() => {
  if (!form.period_start || !form.period_end) return 0
  return Math.round((new Date(form.period_end) - new Date(form.period_start)) / 86400000)
})
const genderSum  = computed(() => (form.total_male || 0) + (form.total_female || 0) + (form.total_other || 0) + (form.total_unknown_gender || 0))
const symptomSum = computed(() => (form.total_symptomatic || 0) + (form.total_asymptomatic || 0))
const genderOk   = computed(() => genderSum.value === (form.total_screened || 0))
const symptomOk  = computed(() => symptomSum.value === (form.total_screened || 0))
const syncPillClass = computed(() => syncStatus.value === SYNC.SYNCED ? 'ag-sync--ok' : 'ag-sync--pending')

const canSubmit = computed(() => form.period_start && form.period_end && !submitted.value)

async function autoCalculate() {
  const auth = getAuth()
  if (!auth?.poe_code) { autoMsg.value = 'No POE assigned.'; return }

  calculating.value = true
  autoMsg.value     = ''
  try {
    const start = form.period_start + ' 00:00:00'
    const end   = form.period_end   + ' 23:59:59'
    const records = await dbGetByIndex(STORE.PRIMARY_SCREENINGS, 'poe_code', auth.poe_code)
    const inRange = records.filter(r =>
      r.captured_at >= start && r.captured_at <= end &&
      r.record_status === 'COMPLETED' && !r.deleted_at
    )

    form.total_screened       = inRange.length
    form.total_male           = inRange.filter(r => r.gender === 'MALE').length
    form.total_female         = inRange.filter(r => r.gender === 'FEMALE').length
    form.total_other          = inRange.filter(r => r.gender === 'OTHER').length
    form.total_unknown_gender = inRange.filter(r => r.gender === 'UNKNOWN').length
    form.total_symptomatic    = inRange.filter(r => r.symptoms_present === 1).length
    form.total_asymptomatic   = inRange.filter(r => r.symptoms_present === 0).length

    autoMsg.value = `✓ Calculated from ${inRange.length} primary screening records in selected period.`
  } catch (e) {
    autoMsg.value = `Calculation failed: ${e.message}`
  } finally {
    calculating.value = false
  }
}

function validate() {
  const errors = {}
  if (!form.period_start || !form.period_end) errors.period = 'Both period dates are required.'
  else if (new Date(form.period_start) > new Date(form.period_end)) errors.period = 'Period start must be before period end.'
  Object.assign(fieldErrors, errors)
  return Object.keys(errors).length === 0
}

async function submit() {
  submitError.value = null
  submitted.value   = false
  if (!validate()) return

  const auth = getAuth()
  if (!auth?.id || !auth?.is_active) { submitError.value = 'Session expired. Log in again.'; return }

  submitting.value = true
  try {
    const record = createRecordBase(auth, {
      submitted_by_user_id:    auth.id,
      period_start:            form.period_start + ' 00:00:00',
      period_end:              form.period_end   + ' 23:59:59',
      total_screened:          form.total_screened      || 0,
      total_male:              form.total_male          || 0,
      total_female:            form.total_female        || 0,
      total_other:             form.total_other         || 0,
      total_unknown_gender:    form.total_unknown_gender|| 0,
      total_symptomatic:       form.total_symptomatic   || 0,
      total_asymptomatic:      form.total_asymptomatic  || 0,
      notes:                   form.notes?.trim() || null,
    })

    await dbPut(STORE.AGGREGATED_SUBMISSIONS, record)
    syncStatus.value = SYNC.UNSYNCED
    submitted.value  = true
    await loadPast()
    syncToServer(auth, record)
  } catch (e) {
    submitError.value = `Save failed: ${e.message}`
  } finally {
    submitting.value = false
  }
}

async function syncToServer(auth, record) {
  if (!navigator.onLine) return
  try {
    const res  = await fetch(`${window.SERVER_URL}/aggregated`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body:    JSON.stringify({
        ...record,
        submitted_by_user_id: auth.id,
        reference_data_version: record.reference_data_version,
      }),
    })
    const body = await res.json().catch(() => ({}))
    if (res.ok && body.success) {
      await dbPut(STORE.AGGREGATED_SUBMISSIONS, {
        ...record,
        id: body.data?.id ?? null,
        server_id: body.data?.id ?? null,
        sync_status: SYNC.SYNCED,
        synced_at: isoNow(),
        record_version: (record.record_version || 1) + 1,
        updated_at: isoNow(),
      })
      syncStatus.value = SYNC.SYNCED
      await loadPast()
    }
  } catch (_) { /* offline — stay UNSYNCED */ }
}

async function loadPast() {
  try {
    const auth = getAuth()
    if (!auth?.poe_code) return
    const all = await dbGetByIndex(STORE.AGGREGATED_SUBMISSIONS, 'poe_code', auth.poe_code)
    pastSubs.value = all.sort((a, b) => (b.period_start || '').localeCompare(a.period_start || '')).slice(0, 10)
  } catch (_) {}
}

function formatDate(dt) {
  if (!dt) return ''
  return dt.slice(0, 10)
}

onMounted(loadPast)
onIonViewDidEnter(loadPast)
</script>

<style scoped>
.ag-header, .ag-toolbar { --background: #F8F9FA; background: #F8F9FA; }
.ag-toolbar { --border-color: #E0E0E0; }
.ag-back { color: #1565C0; }
.ag-title { font-size: 17px; font-weight: 800; color: #212121; }
.ag-sync-pill { font-size: 9px; font-weight: 800; padding: 3px 9px; border-radius: 10px; font-family: monospace; }
.ag-sync--ok      { background: #E8F5E9; color: #2E7D32; }
.ag-sync--pending { background: #FFF3E0; color: #E65100; }

.ag-content { --background: #F0F2F5; }
.ag-card { margin: 12px; background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }

.ag-section-hdr { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.ag-sec-num { width: 22px; height: 22px; border-radius: 50%; background: #1565C0; color: #fff; font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.ag-sec-title { font-size: 13px; font-weight: 800; color: #212121; }
.ag-sec-opt { font-size: 10px; color: rgba(0,0,0,0.4); font-weight: 600; }

.ag-period-row { display: flex; gap: 12px; }
.ag-date-field { flex: 1; display: flex; flex-direction: column; gap: 4px; }
.ag-label { font-size: 10px; font-weight: 700; color: rgba(0,0,0,0.5); text-transform: uppercase; letter-spacing: .5px; }
.ag-input { padding: 10px 12px; border: 1.5px solid #E0E0E0; border-radius: 8px; font-size: 14px; color: #212121; background: #fff; width: 100%; }
.ag-warn { font-size: 11px; font-weight: 700; color: #E65100; background: #FFF3E0; border-radius: 6px; padding: 6px 10px; margin-top: 8px; }
.ag-field-err { font-size: 11px; color: #D32F2F; font-weight: 700; margin-top: 4px; }

.ag-auto-btn {
  display: flex; align-items: center; gap: 8px; padding: 10px 16px; margin-top: 14px;
  border: 1.5px solid #1565C0; border-radius: 8px; background: #E3F2FD; color: #1565C0;
  font-size: 12px; font-weight: 800; cursor: pointer; width: 100%; justify-content: center;
}
.ag-auto-btn:disabled { opacity: .5; }
.ag-auto-msg { font-size: 11px; color: #2E7D32; font-weight: 700; margin-top: 6px; }

.ag-count-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.ag-count-field { display: flex; flex-direction: column; gap: 4px; }
.ag-count-input {
  padding: 10px 12px; border: 1.5px solid #E0E0E0; border-radius: 8px;
  font-size: 20px; font-weight: 800; color: #212121; background: #fff;
  font-family: monospace; text-align: right; width: 100%;
}
.ag-count-input--primary { border-color: #1565C0; background: #E3F2FD; }
.ag-count-input--warn    { border-color: #E65100; }

.ag-validation-row { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
.ag-val-pill { padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; font-family: monospace; }
.ag-val-pill--ok   { background: #E8F5E9; color: #2E7D32; }
.ag-val-pill--warn { background: #FFF3E0; color: #E65100; }

.ag-notes { width: 100%; padding: 10px 12px; border: 1.5px solid #E0E0E0; border-radius: 8px; font-size: 13px; color: #212121; resize: vertical; font-family: system-ui; }

.ag-submit-btn {
  width: 100%; margin-top: 20px; padding: 14px; border-radius: 10px;
  background: #1565C0; color: #fff; font-size: 14px; font-weight: 800;
  border: none; cursor: pointer;
}
.ag-submit-btn:disabled { opacity: .45; }
.ag-submit-err { font-size: 11px; color: #D32F2F; font-weight: 700; margin-top: 8px; }

.ag-success { display: flex; align-items: flex-start; gap: 12px; padding: 14px; background: #E8F5E9; border-radius: 10px; margin-top: 16px; }
.ag-success-icon  { font-size: 28px; flex-shrink: 0; }
.ag-success-title { font-size: 14px; font-weight: 800; color: #1B5E20; }
.ag-success-body  { font-size: 12px; color: #2E7D32; font-weight: 600; }

.ag-past-empty { font-size: 12px; color: rgba(0,0,0,0.4); font-weight: 600; padding: 8px 0; }
.ag-past-row {
  display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
  padding: 10px 0; border-bottom: 1px solid #F0F2F5;
}
.ag-past-period { font-size: 12px; font-weight: 700; color: #212121; flex: 1; font-family: monospace; }
.ag-past-total  { font-size: 11px; color: rgba(0,0,0,0.5); font-weight: 600; }
.ag-past-sync   { font-size: 9px; font-weight: 800; padding: 2px 7px; border-radius: 4px; font-family: monospace; }
.ag-past-sync--ok      { background: #E8F5E9; color: #2E7D32; }
.ag-past-sync--pending { background: #FFF3E0; color: #E65100; }
</style>
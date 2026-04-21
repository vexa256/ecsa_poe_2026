<template>
  <IonPage>
    <!-- ═══ HEADER ═════════════════════════════════════════════════════ -->
    <IonHeader class="ih-hdr" translucent>
      <div class="ih-hdr-bg">
        <div class="ih-hdr-top">
          <IonButtons slot="start"><IonMenuButton menu="app-menu" class="ih-menu"/></IonButtons>
          <div class="ih-hdr-title">
            <span class="ih-hdr-eye">IHR · Alert Intelligence Hub</span>
            <span class="ih-hdr-h1">{{ scope.label }} · {{ scope.code || '—' }}</span>
          </div>
          <IonButtons slot="end">
            <button class="ih-ref" :class="loading && 'ih-spin'" @click="loadAlerts(true)" :disabled="loading">
              <IonIcon :icon="refreshOutline" slot="icon-only"/>
            </button>
          </IonButtons>
        </div>

        <!-- KPI strip -->
        <div class="ih-kpis">
          <button :class="['ih-kpi', !quickFilter && 'ih-kpi--on']" @click="setQuickFilter(null)">
            <span class="ih-kpi-n">{{ counts.total }}</span><span class="ih-kpi-l">Total</span>
          </button>
          <button :class="['ih-kpi','ih-kpi--crit', quickFilter==='CRITICAL' && 'ih-kpi--on']" @click="setQuickFilter('CRITICAL')">
            <span class="ih-kpi-n">{{ counts.critical }}</span><span class="ih-kpi-l">Critical</span>
          </button>
          <button :class="['ih-kpi','ih-kpi--high', quickFilter==='HIGH' && 'ih-kpi--on']" @click="setQuickFilter('HIGH')">
            <span class="ih-kpi-n">{{ counts.high }}</span><span class="ih-kpi-l">High</span>
          </button>
          <button :class="['ih-kpi','ih-kpi--over', quickFilter==='OVERDUE' && 'ih-kpi--on']" @click="setQuickFilter('OVERDUE')">
            <span class="ih-kpi-n">{{ counts.overdue }}</span><span class="ih-kpi-l">Breach</span>
          </button>
          <button :class="['ih-kpi','ih-kpi--t1', quickFilter==='TIER1' && 'ih-kpi--on']" @click="setQuickFilter('TIER1')">
            <span class="ih-kpi-n">{{ counts.tier1 }}</span><span class="ih-kpi-l">Tier 1</span>
          </button>
        </div>

        <!-- Tabs: Feed + Analytics. Intelligence & Matrix moved to dedicated views. -->
        <div class="ih-tabs">
          <button v-for="t in TABS" :key="t.v"
            :class="['ih-tab', tab === t.v && 'ih-tab--on']"
            @click="setTab(t.v)">
            <IonIcon :icon="t.icon"/>
            <span>{{ t.label }}</span>
            <span v-if="t.v==='feed' && counts.openAll > 0" class="ih-tab-badge">{{ counts.openAll }}</span>
          </button>
          <button class="ih-tab ih-tab--nav" @click="gotoIntelligence" aria-label="Open Alert Intelligence">
            <IonIcon :icon="bookOutline"/><span>Intel</span>
            <span v-if="insights.length > 0" class="ih-tab-badge">{{ insights.length }}</span>
          </button>
          <button class="ih-tab ih-tab--nav" @click="gotoMatrix" aria-label="Open WHO Matrix">
            <IonIcon :icon="gridOutline"/><span>Matrix</span>
          </button>
          <button class="ih-tab ih-tab--nav" @click="gotoHistory" aria-label="Open Alert History">
            <IonIcon :icon="archiveOutline"/><span>History</span>
          </button>
        </div>
      </div>
    </IonHeader>

    <IonContent class="ih-content" :fullscreen="true">
      <IonRefresher slot="fixed" @ionRefresh="pullRefresh($event)"><IonRefresherContent/></IonRefresher>

      <div v-if="!isOnline" class="ih-offline">Offline — showing cached alerts</div>

      <!-- ═══ FEED TAB ═════════════════════════════════════════════════ -->
      <section v-show="tab === 'feed'" class="ih-section">
        <!-- Filter row -->
        <div class="ih-frow">
          <div class="ih-fs">
            <button v-for="f in STATUS_FILTERS" :key="f.v"
              :class="['ih-fp', statusFilter === f.v && 'ih-fp--on']"
              @click="statusFilter = f.v; loadAlerts(true)">{{ f.l }}</button>
          </div>
          <div class="ih-fs">
            <button v-for="f in ROUTE_FILTERS" :key="f.v"
              :class="['ih-fp', routeFilter === f.v && 'ih-fp--on']"
              @click="routeFilter = f.v; loadAlerts(true)">{{ f.l }}</button>
          </div>
        </div>

        <!-- Skeletons -->
        <div v-if="loading && alerts.length === 0" class="ih-skels">
          <div v-for="i in 3" :key="i" class="ih-skel"><div class="ih-sk ih-sk--1"/><div class="ih-sk ih-sk--2"/><div class="ih-sk ih-sk--3"/></div>
        </div>

        <!-- Empty -->
        <div v-else-if="!loading && filteredAlerts.length === 0" class="ih-empty">
          <div class="ih-empty-ic">
            <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" width="56" height="56">
              <circle cx="32" cy="32" r="28"/><polyline points="22 32 29 39 42 26" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div class="ih-empty-t">All clear</div>
          <div class="ih-empty-s">{{ quickFilter ? `No ${quickFilter} alerts in your scope.` : 'No active alerts at this scope.' }}</div>
        </div>

        <!-- Card list -->
        <div v-else class="ih-list">
          <article v-for="a in filteredAlerts" :key="a.id"
            :class="['ih-card', 'ih-card--'+a.risk_level.toLowerCase(), a.overdue_24h && 'ih-card--overdue', a.status==='CLOSED' && 'ih-card--closed', a.status==='ACKNOWLEDGED' && 'ih-card--acked']"
            @click="openDetail(a)">
            <div class="ih-card-stripe" :class="'ih-stripe--'+a.risk_level.toLowerCase()"/>
            <div class="ih-card-body">
              <div class="ih-card-top">
                <span :class="['ih-risk','ih-risk--'+a.risk_level.toLowerCase()]">{{ a.risk_level }}</span>
                <span :class="['ih-route','ih-route--'+a.routed_to_level.toLowerCase()]">{{ a.routed_to_level }}</span>
                <span :class="['ih-st','ih-st--'+a.status.toLowerCase()]">{{ a.status }}</span>
                <span v-if="tierOf(a).tier === 1" class="ih-tier ih-tier--1">TIER 1</span>
                <span v-else-if="tierOf(a).tier === 2" class="ih-tier ih-tier--2">TIER 2</span>
                <span v-if="scorecardOf(a).overall === 'BREACH'" class="ih-717 ih-717--b">7-1-7 BREACH</span>
                <span v-else-if="scorecardOf(a).overall === 'AT_RISK'" class="ih-717 ih-717--r">7-1-7 AT RISK</span>
                <span class="ih-age" :class="a.overdue_24h && 'ih-age--o'">{{ fmtAge(a.hours_since_creation) }}</span>
              </div>
              <div class="ih-card-title">{{ a.alert_title || a.alert_code }}</div>
              <div class="ih-card-code">{{ a.alert_code }}</div>
              <div class="ih-card-meta">
                <span v-if="a.syndrome" class="ih-chip">{{ a.syndrome.replace(/_/g,' ') }}</span>
                <span class="ih-chip ih-chip--poe">{{ a.poe_code }}</span>
                <span class="ih-chip" :class="a.generated_from==='OFFICER' ? 'ih-chip--off' : 'ih-chip--auto'">
                  {{ a.generated_from === 'OFFICER' ? 'Officer' : 'Rule' }}
                </span>
              </div>
              <div v-if="cleanDetails(a.alert_details)" class="ih-card-det">{{ truncate(cleanDetails(a.alert_details), 140) }}</div>
              <div v-if="a.acknowledged_by_name" class="ih-card-ack">Ack'd by {{ a.acknowledged_by_name }} · {{ fmtDate(a.acknowledged_at) }}</div>
            </div>
          </article>
          <div v-if="hasMore" class="ih-more">
            <button class="ih-more-btn" :disabled="loadingMore" @click="loadMore">{{ loadingMore ? 'Loading…' : 'Load More' }}</button>
          </div>
        </div>
      </section>

      <!-- ═══ INTELLIGENCE TAB (removed — see /alerts/intelligence) ═══ -->
      <section v-if="false" class="ih-section">
        <div v-if="insights.length === 0" class="ih-empty">
          <div class="ih-empty-t">No insights yet</div>
          <div class="ih-empty-s">Insights generate from your live alert data. Pull to refresh or switch to the Feed tab.</div>
        </div>

        <div v-else>
          <div v-for="ins in insights" :key="ins.id" :class="['ih-ins', 'ih-ins--'+ins.level]">
            <div class="ih-ins-hdr">
              <span class="ih-ins-ic">{{ levelIcon(ins.level) }}</span>
              <span class="ih-ins-t">{{ ins.title }}</span>
            </div>
            <p class="ih-ins-body">{{ ins.body }}</p>
            <ul v-if="ins.actions && ins.actions.length" class="ih-ins-acts">
              <li v-for="(act, i) in ins.actions" :key="i">{{ act }}</li>
            </ul>
            <div v-if="ins.cite" class="ih-ins-cite">{{ ins.cite }}</div>
          </div>
        </div>

        <!-- 7-1-7 Breach Ledger -->
        <h3 class="ih-sh">7-1-7 Performance Ledger</h3>
        <div v-if="breaches717.length === 0" class="ih-empty-s ih-pad">No breaches — all alerts within target.</div>
        <div v-else>
          <article v-for="a in breaches717" :key="a.id" class="ih-717-row" @click="openDetail(a)">
            <div class="ih-717-top">
              <span class="ih-717-code">{{ a.alert_code }}</span>
              <span :class="['ih-717-bn', 'ih-717-bn--'+(scorecardOf(a).bottleneck||'').toLowerCase()]">
                {{ scorecardOf(a).bottleneck || 'ON_TARGET' }}
              </span>
            </div>
            <div class="ih-717-title">{{ a.alert_title || a.alert_code }}</div>
            <div class="ih-717-bars">
              <div class="ih-717-bar"><span class="ih-717-bl">Detect</span><div class="ih-717-bt"><div class="ih-717-bf" :class="scorecardOf(a).detect.on_target ? 'ok' : 'bad'" :style="{ width: pctBar(scorecardOf(a).detect.hrs, scorecardOf(a).detect.target) + '%' }"/></div><span class="ih-717-br">{{ fmtHrs(scorecardOf(a).detect.hrs) }} / {{ scorecardOf(a).detect.target }}h</span></div>
              <div class="ih-717-bar"><span class="ih-717-bl">Notify</span><div class="ih-717-bt"><div class="ih-717-bf" :class="scorecardOf(a).notify.on_target ? 'ok' : 'bad'" :style="{ width: pctBar(scorecardOf(a).notify.hrs, scorecardOf(a).notify.target) + '%' }"/></div><span class="ih-717-br">{{ fmtHrs(scorecardOf(a).notify.hrs) }} / {{ scorecardOf(a).notify.target }}h</span></div>
              <div class="ih-717-bar"><span class="ih-717-bl">Respond</span><div class="ih-717-bt"><div class="ih-717-bf" :class="scorecardOf(a).respond.on_target === false ? 'bad' : scorecardOf(a).respond.on_target ? 'ok' : 'pending'" :style="{ width: pctBar(scorecardOf(a).respond.hrs, scorecardOf(a).respond.target) + '%' }"/></div><span class="ih-717-br">{{ scorecardOf(a).respond.on_target === null ? 'open' : (fmtHrs(scorecardOf(a).respond.hrs) + ' / ' + scorecardOf(a).respond.target + 'h') }}</span></div>
            </div>
          </article>
        </div>
      </section>

      <!-- ═══ ANALYTICS TAB ═════════════════════════════════════════════ -->
      <section v-show="tab === 'analytics'" class="ih-section">
        <h3 class="ih-sh">Risk distribution</h3>
        <div class="ih-risk">
          <div v-for="(n, k) in riskDist" :key="k" :class="['ih-risk-row', 'ih-risk-row--'+k.toLowerCase()]">
            <span class="ih-risk-l">{{ k }}</span>
            <div class="ih-risk-t"><div class="ih-risk-f" :style="{ width: pctBar(n, riskDistMax) + '%' }"/></div>
            <span class="ih-risk-n">{{ n }}</span>
          </div>
        </div>

        <h3 class="ih-sh">Top syndromes</h3>
        <div v-if="syndromes.length === 0" class="ih-empty-s ih-pad">No syndrome data.</div>
        <div v-else class="ih-syn">
          <div v-for="s in syndromes.slice(0, 8)" :key="s.label" class="ih-syn-row">
            <span class="ih-syn-l">{{ s.label }}</span>
            <div class="ih-syn-t"><div class="ih-syn-f" :style="{ width: pctBar(s.count, syndromes[0].count) + '%' }"/></div>
            <span class="ih-syn-n">{{ s.count }}</span>
          </div>
        </div>

        <h3 class="ih-sh">{{ scope.kind === 'POE' ? 'Alerts over time' : 'Alert hotspots' }}</h3>
        <div v-if="concentrations.length === 0" class="ih-empty-s ih-pad">No geographic data.</div>
        <div v-else class="ih-conc">
          <div v-for="(c, i) in concentrations" :key="c.label" class="ih-conc-row">
            <span class="ih-conc-rank">{{ i + 1 }}</span>
            <span class="ih-conc-l">{{ c.label }}</span>
            <span class="ih-conc-n">{{ c.count }}</span>
          </div>
        </div>

        <h3 class="ih-sh">Acknowledgement time</h3>
        <div v-if="totalAcknowledged === 0" class="ih-empty-s ih-pad">No acknowledged alerts yet.</div>
        <div v-else class="ih-hist">
          <div v-for="b in histogram" :key="b.label" class="ih-hist-col">
            <div class="ih-hist-bar"><div class="ih-hist-f" :style="{ height: pctBar(b.count, histMax) + '%' }"/></div>
            <span class="ih-hist-n">{{ b.count }}</span>
            <span class="ih-hist-l">{{ b.label }}</span>
          </div>
        </div>

        <h3 class="ih-sh">Escalation funnel</h3>
        <div class="ih-funnel">
          <div v-for="(r, level) in funnel" :key="level" class="ih-funnel-row">
            <span class="ih-funnel-l">{{ level }}</span>
            <div class="ih-funnel-bars">
              <div class="ih-fb ih-fb--open" :style="{ width: pctBar(r.open, funnelMax) + '%' }" :title="`${r.open} open`"><span v-if="r.open">{{ r.open }}</span></div>
              <div class="ih-fb ih-fb--ack" :style="{ width: pctBar(r.acked, funnelMax) + '%' }" :title="`${r.acked} ack'd`"><span v-if="r.acked">{{ r.acked }}</span></div>
              <div class="ih-fb ih-fb--closed" :style="{ width: pctBar(r.closed, funnelMax) + '%' }" :title="`${r.closed} closed`"><span v-if="r.closed">{{ r.closed }}</span></div>
            </div>
          </div>
        </div>
        <div class="ih-legend">
          <span><span class="ih-legend-sw ih-fb--open"/>Open</span>
          <span><span class="ih-legend-sw ih-fb--ack"/>Acknowledged</span>
          <span><span class="ih-legend-sw ih-fb--closed"/>Closed</span>
        </div>
      </section>

      <!-- ═══ MATRIX TAB (removed — see /alerts/matrix) ═════════════════ -->
      <section v-if="false" class="ih-section">
        <div class="ih-ref ih-ref--t1">
          <div class="ih-ref-hdr">
            <span class="ih-ref-tag">TIER 1</span>
            <span class="ih-ref-title">Always Notifiable</span>
          </div>
          <p class="ih-ref-body">A single confirmed or probable case of any of the following triggers <strong>mandatory WHO notification within 24 hours</strong>, regardless of the Annex 2 4-criteria result.</p>
          <ul class="ih-ref-list">
            <li v-for="n in engine.IHR_TIER1.names" :key="n">{{ n }}</li>
          </ul>
          <div class="ih-ref-cite">Source: IHR 2005 Annex 2.</div>
        </div>

        <div class="ih-ref ih-ref--t2">
          <div class="ih-ref-hdr"><span class="ih-ref-tag">TIER 2</span><span class="ih-ref-title">Annex 2 Assessment Required</span></div>
          <p class="ih-ref-body">Events involving these diseases ALWAYS run through the 4-criteria Annex 2 decision instrument. If <strong>2 of 4 are YES</strong> → notify WHO within 24 hours.</p>
          <ul class="ih-ref-list">
            <li v-for="n in engine.IHR_TIER2.names" :key="n">{{ n }}</li>
          </ul>
          <div class="ih-ref-cite">Source: IHR 2005 Annex 2 decision tree.</div>
        </div>

        <div class="ih-ref ih-ref--annex">
          <div class="ih-ref-hdr"><span class="ih-ref-tag">ANNEX 2</span><span class="ih-ref-title">4-Criteria Decision Instrument</span></div>
          <ol class="ih-ref-crit">
            <li><strong>Serious</strong> — Is the public health impact serious?</li>
            <li><strong>Unusual</strong> — Is the event unusual or unexpected?</li>
            <li><strong>Spread</strong> — Is there significant risk of international spread?</li>
            <li><strong>Trade</strong> — Is there significant risk of international travel or trade restrictions?</li>
          </ol>
          <p class="ih-ref-body"><strong>Rule:</strong> ANY 2 of 4 = YES → State Party MUST notify WHO within 24 hours via the National IHR Focal Point (IHR Art. 6).</p>
          <div class="ih-ref-cite">Source: IHR 2005 Third Edition, Annex 2.</div>
        </div>

        <div class="ih-ref ih-ref--717">
          <div class="ih-ref-hdr"><span class="ih-ref-tag">7-1-7</span><span class="ih-ref-title">Pandemic Preparedness Target</span></div>
          <div class="ih-717-targets">
            <div class="ih-717-t"><span class="ih-717-tn">7</span><span class="ih-717-tl">Days to detect</span><span class="ih-717-td">Emergence → detection</span></div>
            <div class="ih-717-t"><span class="ih-717-tn">1</span><span class="ih-717-tl">Day to notify</span><span class="ih-717-td">Detection → notification + investigation</span></div>
            <div class="ih-717-t"><span class="ih-717-tn">7</span><span class="ih-717-tl">Days to respond</span><span class="ih-717-td">Detection → early response actions</span></div>
          </div>
          <p class="ih-ref-body">A missed target is a <strong>bottleneck</strong>; root-cause analysis is required. 14 RTSL-defined early response actions must be completed within the 7-day window.</p>
          <div class="ih-ref-cite">Frieden TR et al., Lancet 2021; 398:638-640 · Resolve to Save Lives / WHO</div>
        </div>

        <div class="ih-ref ih-ref--esc">
          <div class="ih-ref-hdr"><span class="ih-ref-tag">ESCALATION</span><span class="ih-ref-title">Notification Ladder</span></div>
          <div class="ih-ladder">
            <div class="ih-ladder-step"><span class="ih-ladder-lv">POE</span><span class="ih-ladder-arr">→</span><span class="ih-ladder-lv">District</span><span class="ih-ladder-t">≤ 2h (phone, notifiable)</span></div>
            <div class="ih-ladder-step"><span class="ih-ladder-lv">District</span><span class="ih-ladder-arr">→</span><span class="ih-ladder-lv">National PHEOC</span><span class="ih-ladder-t">≤ 24h (IDSR)</span></div>
            <div class="ih-ladder-step"><span class="ih-ladder-lv">National</span><span class="ih-ladder-arr">→</span><span class="ih-ladder-lv">WHO (via IHR FP)</span><span class="ih-ladder-t">≤ 24h (IHR Art. 6)</span></div>
          </div>
          <div class="ih-ref-cite">Uganda MoH IDSR Technical Guidelines 3rd Ed. (2021) · WHO AFRO IDSR 2019.</div>
        </div>

        <div class="ih-ref ih-ref--pheic">
          <div class="ih-ref-hdr"><span class="ih-ref-tag">PHEIC</span><span class="ih-ref-title">Art. 12 Criteria</span></div>
          <p class="ih-ref-body">A Public Health Emergency of International Concern is declared by the WHO Director-General when an event is:</p>
          <ul class="ih-ref-list">
            <li v-for="c in engine.PHEIC_CRITERIA" :key="c.key">{{ c.label }}</li>
          </ul>
          <div class="ih-ref-cite">Historical PHEICs: H1N1 (2009), Polio (2014), Ebola (2014, 2019), Zika (2016), COVID-19 (2020), Mpox (2022, 2024).</div>
        </div>
      </section>

      <div style="height:64px"/>
    </IonContent>

    <!-- ═══ DETAIL MODAL ══════════════════════════════════════════════ -->
    <IonModal :is-open="!!detail" @didDismiss="closeDetail" :initial-breakpoint="1" :breakpoints="[0,1]" class="ih-modal">
      <IonHeader>
        <IonToolbar class="ih-mt">
          <div slot="start" class="ih-handle"/>
          <IonButtons slot="end"><IonButton fill="clear" @click="closeDetail">Close</IonButton></IonButtons>
        </IonToolbar>
      </IonHeader>
      <IonContent class="ih-mc" v-if="detail">
        <div class="ih-mp">
          <!-- Hero -->
          <div :class="['ih-hero', 'ih-hero--'+detail.risk_level.toLowerCase()]">
            <span class="ih-hero-cat">{{ detail.routed_to_level }} · {{ detail.risk_level }}</span>
            <span class="ih-hero-title">{{ detail.alert_title || detail.alert_code }}</span>
            <span class="ih-hero-code">{{ detail.alert_code }}</span>
          </div>

          <!-- Modal tab bar -->
          <div class="ih-mtabs">
            <button v-for="m in MODAL_TABS" :key="m.v"
              :class="['ih-mtab', modalTab === m.v && 'ih-mtab--on']"
              @click="modalTab = m.v">{{ m.l }}</button>
          </div>

          <!-- Overview -->
          <div v-show="modalTab === 'overview'">
            <h4 class="ih-mh">Alert metadata</h4>
            <div class="ih-mg">
              <div class="ih-mr"><span class="ih-mk">Status</span><span :class="['ih-mv','ih-mv--'+detail.status.toLowerCase()]">{{ detail.status }}</span></div>
              <div class="ih-mr"><span class="ih-mk">Risk</span><span :class="['ih-mv','ih-mv--'+detail.risk_level.toLowerCase()]">{{ detail.risk_level }}</span></div>
              <div class="ih-mr"><span class="ih-mk">Routed to</span><span class="ih-mv">{{ detail.routed_to_level }}</span></div>
              <div class="ih-mr"><span class="ih-mk">Generated</span><span class="ih-mv">{{ detail.generated_from }}</span></div>
              <div class="ih-mr"><span class="ih-mk">Created</span><span class="ih-mv">{{ fmtDateTime(detail.created_at) }}</span></div>
              <div class="ih-mr"><span class="ih-mk">Age</span><span :class="['ih-mv', detail.overdue_24h && 'ih-mv--r']">{{ fmtAge(detail.hours_since_creation) }}</span></div>
              <div v-if="detail.acknowledged_at" class="ih-mr"><span class="ih-mk">Acknowledged</span><span class="ih-mv">{{ fmtDateTime(detail.acknowledged_at) }}</span></div>
              <div v-if="detail.acknowledged_by_name" class="ih-mr"><span class="ih-mk">Ack'd by</span><span class="ih-mv">{{ detail.acknowledged_by_name }}</span></div>
              <div v-if="detail.closed_at" class="ih-mr"><span class="ih-mk">Closed</span><span class="ih-mv">{{ fmtDateTime(detail.closed_at) }}</span></div>
            </div>

            <h4 class="ih-mh">Details</h4>
            <div class="ih-details">
              <p v-if="cleanDetails(detail.alert_details)">{{ cleanDetails(detail.alert_details) }}</p>
              <p v-else class="ih-none">No additional details provided.</p>
            </div>

            <h4 class="ih-mh">Geographic scope</h4>
            <div class="ih-mg">
              <div class="ih-mr"><span class="ih-mk">POE</span><span class="ih-mv">{{ detail.poe_code }}</span></div>
              <div class="ih-mr"><span class="ih-mk">District</span><span class="ih-mv">{{ detail.district_code }}</span></div>
              <div class="ih-mr"><span class="ih-mk">PHEOC</span><span class="ih-mv">{{ detail.pheoc_code || '—' }}</span></div>
              <div class="ih-mr"><span class="ih-mk">Country</span><span class="ih-mv">{{ detail.country_code }}</span></div>
            </div>

            <template v-if="detail.secondary_case">
              <h4 class="ih-mh">Linked case #{{ detail.secondary_case.id }}</h4>
              <div class="ih-mg">
                <div class="ih-mr"><span class="ih-mk">Traveler</span><span class="ih-mv">{{ detail.secondary_case.traveler_full_name || 'Anonymous' }}</span></div>
                <div class="ih-mr"><span class="ih-mk">Gender</span><span class="ih-mv">{{ detail.secondary_case.traveler_gender || '—' }}</span></div>
                <div class="ih-mr"><span class="ih-mk">Case status</span><span class="ih-mv">{{ detail.secondary_case.case_status || '—' }}</span></div>
                <div class="ih-mr"><span class="ih-mk">Syndrome</span><span class="ih-mv">{{ detail.secondary_case.syndrome_classification?.replace(/_/g,' ') || '—' }}</span></div>
                <div class="ih-mr"><span class="ih-mk">Case risk</span><span class="ih-mv">{{ detail.secondary_case.risk_level || '—' }}</span></div>
                <div class="ih-mr"><span class="ih-mk">Disposition</span><span class="ih-mv">{{ detail.secondary_case.final_disposition?.replace(/_/g,' ') || 'Pending' }}</span></div>
              </div>
            </template>

            <template v-if="detail.top_suspected_disease">
              <h4 class="ih-mh">Top suspected disease</h4>
              <div class="ih-disease">
                <span class="ih-dn">{{ detail.top_suspected_disease.disease_name || detail.top_suspected_disease.disease_code }}</span>
                <span class="ih-dc">{{ detail.top_suspected_disease.disease_code }}</span>
              </div>
            </template>
          </div>

          <!-- Intelligence -->
          <div v-show="modalTab === 'intel'">
            <!-- IHR classification -->
            <h4 class="ih-mh">IHR classification</h4>
            <div :class="['ih-cls', tierOf(detail).tier ? 'ih-cls--t'+tierOf(detail).tier : 'ih-cls--none']">
              <div class="ih-cls-hdr">
                <span class="ih-cls-tag">{{ tierOf(detail).tier ? 'TIER ' + tierOf(detail).tier : 'UNCLASSIFIED' }}</span>
                <span class="ih-cls-name">{{ tierOf(detail).name || '—' }}</span>
              </div>
              <p class="ih-cls-body">{{ tierOf(detail).reason || 'This alert does not match IHR Tier 1 or Tier 2 criteria. Continue routine monitoring.' }}</p>
            </div>

            <!-- Annex 2 scorecard -->
            <h4 class="ih-mh">Annex 2 decision instrument</h4>
            <div :class="['ih-annex', annex2Of(detail).meetsThreshold && 'ih-annex--hit']">
              <div class="ih-annex-top">
                <span class="ih-annex-score">{{ annex2Of(detail).yes }} / {{ annex2Of(detail).total }}</span>
                <span class="ih-annex-note">{{ annex2Of(detail).meetsThreshold ? 'Threshold met — notify WHO' : 'Below threshold' }}</span>
              </div>
              <div v-for="c in annex2Of(detail).details" :key="c.key" class="ih-annex-row">
                <span :class="['ih-annex-badge', c.yes ? 'ih-annex-badge--y' : 'ih-annex-badge--n']">{{ c.yes ? 'YES' : 'NO' }}</span>
                <div class="ih-annex-body">
                  <span class="ih-annex-lbl">{{ c.label }}</span>
                  <span class="ih-annex-basis">{{ c.basis }}</span>
                </div>
              </div>
              <p class="ih-annex-summary">{{ annex2Of(detail).summary }}</p>
            </div>

            <!-- 7-1-7 scorecard -->
            <h4 class="ih-mh">7-1-7 performance</h4>
            <div :class="['ih-sc', 'ih-sc--'+scorecardOf(detail).overall.toLowerCase()]">
              <div class="ih-sc-top">
                <span class="ih-sc-ov">{{ scorecardOf(detail).overall.replace('_', ' ') }}</span>
                <span v-if="scorecardOf(detail).bottleneck" class="ih-sc-bn">Bottleneck: {{ scorecardOf(detail).bottleneck }}</span>
              </div>
              <div class="ih-sc-rows">
                <div class="ih-sc-row">
                  <div class="ih-sc-meta"><span class="ih-sc-l">Detect</span><span class="ih-sc-h">{{ fmtHrs(scorecardOf(detail).detect.hrs) }} / {{ scorecardOf(detail).detect.target }}h</span></div>
                  <div class="ih-sc-bar"><div :class="['ih-sc-f', scorecardOf(detail).detect.on_target ? 'ok' : 'bad']" :style="{ width: pctBar(scorecardOf(detail).detect.hrs, scorecardOf(detail).detect.target) + '%' }"/></div>
                </div>
                <div class="ih-sc-row">
                  <div class="ih-sc-meta"><span class="ih-sc-l">Notify</span><span class="ih-sc-h">{{ fmtHrs(scorecardOf(detail).notify.hrs) }} / {{ scorecardOf(detail).notify.target }}h</span></div>
                  <div class="ih-sc-bar"><div :class="['ih-sc-f', scorecardOf(detail).notify.on_target ? 'ok' : 'bad']" :style="{ width: pctBar(scorecardOf(detail).notify.hrs, scorecardOf(detail).notify.target) + '%' }"/></div>
                </div>
                <div class="ih-sc-row">
                  <div class="ih-sc-meta"><span class="ih-sc-l">Respond</span><span class="ih-sc-h">{{ scorecardOf(detail).respond.on_target === null ? '— open' : fmtHrs(scorecardOf(detail).respond.hrs) + ' / ' + scorecardOf(detail).respond.target + 'h' }}</span></div>
                  <div class="ih-sc-bar"><div :class="['ih-sc-f', scorecardOf(detail).respond.on_target === false ? 'bad' : scorecardOf(detail).respond.on_target ? 'ok' : 'pending']" :style="{ width: pctBar(scorecardOf(detail).respond.hrs, scorecardOf(detail).respond.target) + '%' }"/></div>
                </div>
              </div>
            </div>

            <!-- Next escalation -->
            <h4 class="ih-mh">Recommended next step</h4>
            <div class="ih-nxt">
              <div class="ih-nxt-row">
                <span class="ih-nxt-tag">NEXT</span>
                <span class="ih-nxt-target">{{ nextStep(detail).target || 'None' }}</span>
                <span v-if="nextStep(detail).within_hrs != null" class="ih-nxt-hrs">within {{ nextStep(detail).within_hrs }}h</span>
              </div>
              <p class="ih-nxt-note">{{ nextStep(detail).note }}</p>
            </div>
          </div>

          <!-- Actions -->
          <div v-show="modalTab === 'actions'">
            <h4 class="ih-mh">Recommended response</h4>
            <div :class="['ih-rec', 'ih-rec--'+detail.risk_level.toLowerCase()]">
              <span class="ih-rec-hdr">ACTION</span>
              <p class="ih-rec-body">{{ recommendedAction(detail) }}</p>
            </div>

            <!-- Authorization notice -->
            <div v-if="!canAct(detail) && detail.status !== 'CLOSED'" class="ih-ro">
              <span class="ih-ro-ic">{{ '\u{1F512}' }}</span>
              <div>
                <strong>View-only.</strong>
                Acknowledge / close authority: {{ (engine.ACK_ROLES[detail.routed_to_level] || []).join(' or ') }}.
                Your role ({{ auth.role_key || 'UNKNOWN' }}) can monitor this alert but cannot alter its status.
              </div>
            </div>

            <!-- Action buttons -->
            <div v-if="detail.status !== 'CLOSED' && canAct(detail)" class="ih-mact">
              <button v-if="detail.status === 'OPEN'" class="ih-mact-btn ih-mact-btn--ack" :disabled="acting" @click="acknowledge(detail)">
                {{ acting === 'ack' ? 'Acknowledging…' : 'Acknowledge' }}
              </button>
              <button class="ih-mact-btn ih-mact-btn--close" :disabled="acting" @click="promptClose(detail)">
                Close Alert
              </button>
            </div>

            <div v-if="detail.secondary_screening_id || detail.secondary_case" class="ih-mact ih-mact--view">
              <button class="ih-mact-btn ih-mact-btn--view" @click="openRecord(detail)">
                <IonIcon :icon="documentTextOutline"/>
                View screening record
              </button>
            </div>
          </div>
        </div>
        <div style="height:64px"/>
      </IonContent>
    </IonModal>

    <!-- ═══ CLOSE REASON MODAL ══════════════════════════════════════════ -->
    <IonModal :is-open="closeModal.show" @didDismiss="cancelClose" :initial-breakpoint="0.55" :breakpoints="[0,0.55]">
      <IonContent class="ih-closemod">
        <div class="ih-closepad">
          <h2 class="ih-closet">Close alert</h2>
          <p class="ih-closes">Provide a reason. Recorded in the audit log.</p>
          <textarea v-model="closeModal.reason" rows="4" class="ih-closetx"
            placeholder="e.g. Response completed, case dispositioned, duplicate alert, false positive…"/>
          <div v-if="closeModal.error" class="ih-closeerr">{{ closeModal.error }}</div>
          <div class="ih-closeacts">
            <button class="ih-closecancel" @click="cancelClose" :disabled="closeModal.submitting">Cancel</button>
            <button class="ih-closeconfirm" @click="confirmClose"
              :disabled="closeModal.submitting || closeModal.reason.trim().length < 5">
              {{ closeModal.submitting ? 'Closing…' : 'Confirm close' }}
            </button>
          </div>
        </div>
      </IonContent>
    </IonModal>

    <IonToast :is-open="toast.show" :message="toast.msg" :color="toast.color" :duration="3000" position="top" @didDismiss="toast.show = false"/>
  </IonPage>
</template>

<script setup>
import {
  IonPage, IonHeader, IonToolbar, IonButtons, IonMenuButton, IonButton,
  IonContent, IonIcon, IonModal, IonToast, IonRefresher, IonRefresherContent,
  onIonViewWillEnter, onIonViewDidEnter,
} from '@ionic/vue'
import {
  refreshOutline, pulseOutline, analyticsOutline, gridOutline, bookOutline,
  documentTextOutline, archiveOutline,
} from 'ionicons/icons'
import { ref, computed, reactive, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { APP, STORE, dbGet, dbGetByIndex, dbGetAll } from '@/services/poeDB'
import engine, {
  classifyIHRTier, assessAnnex2, evaluate717, nextEscalation, canActOnAlert, userScope,
  generateIntelligenceInsights, riskDistribution, syndromeCloud, concentrationByGeo,
  responseTimeHistogram, escalationFunnel,
} from '@/composables/useAlertIntelligence'

const router = useRouter()

function getAuth() { return JSON.parse(sessionStorage.getItem('AUTH_DATA') ?? 'null') ?? {} }
const auth = ref(getAuth())
const scope = computed(() => userScope(auth.value))

// ── Tabs (Intelligence and Matrix moved to dedicated views) ────────────────
const TABS = [
  { v: 'feed',      label: 'Feed',      icon: pulseOutline    },
  { v: 'analytics', label: 'Analytics', icon: analyticsOutline },
]
const tab = ref('feed')
function setTab(v) { tab.value = v }
function gotoIntelligence() { router.push('/alerts/intelligence') }
function gotoMatrix()       { router.push('/alerts/matrix') }
function gotoHistory()      { router.push('/alerts/history') }

const MODAL_TABS = [
  { v: 'overview', l: 'Overview' },
  { v: 'intel',    l: 'Intelligence' },
  { v: 'actions',  l: 'Actions' },
]
const modalTab = ref('overview')

// Filters
const STATUS_FILTERS = [
  { v: null, l: 'All' }, { v: 'OPEN', l: 'Open' }, { v: 'ACKNOWLEDGED', l: "Ack'd" }, { v: 'CLOSED', l: 'Closed' },
]
const ROUTE_FILTERS = [
  { v: null, l: 'All Levels' }, { v: 'DISTRICT', l: 'District' }, { v: 'PHEOC', l: 'PHEOC' }, { v: 'NATIONAL', l: 'National' },
]

// State
const alerts     = ref([])
const loading    = ref(false)
const loadingMore = ref(false)
const page       = ref(1)
const hasMore    = ref(false)
const quickFilter = ref(null)
const statusFilter = ref(null)
const routeFilter  = ref(null)
const detail     = ref(null)
const acting     = ref(null)
const isOnline   = ref(navigator.onLine)
const toast      = reactive({ show: false, msg: '', color: 'success' })
const closeModal = reactive({ show: false, alert: null, reason: '', submitting: false, error: null })
let pollTimer = null

// ── Counts ──────────────────────────────────────────────────────────────────
const counts = computed(() => {
  const a = alerts.value
  const openAll = a.filter(x => x.status !== 'CLOSED').length
  return {
    total: a.length,
    openAll,
    critical: a.filter(x => x.risk_level === 'CRITICAL' && x.status !== 'CLOSED').length,
    high: a.filter(x => x.risk_level === 'HIGH' && x.status !== 'CLOSED').length,
    overdue: a.filter(x => x.overdue_24h && x.status !== 'CLOSED').length,
    tier1: a.filter(x => classifyIHRTier(x).tier === 1 && x.status !== 'CLOSED').length,
  }
})

function setQuickFilter(f) { quickFilter.value = quickFilter.value === f ? null : f }

const filteredAlerts = computed(() => {
  const a = alerts.value
  if (!quickFilter.value) return a
  if (quickFilter.value === 'CRITICAL') return a.filter(x => x.risk_level === 'CRITICAL' && x.status !== 'CLOSED')
  if (quickFilter.value === 'HIGH')     return a.filter(x => x.risk_level === 'HIGH' && x.status !== 'CLOSED')
  if (quickFilter.value === 'OVERDUE')  return a.filter(x => x.overdue_24h && x.status !== 'CLOSED')
  if (quickFilter.value === 'TIER1')    return a.filter(x => classifyIHRTier(x).tier === 1 && x.status !== 'CLOSED')
  return a
})

// ── Memoised intelligence lookups (per render) ──────────────────────────────
const tierCache = new WeakMap()
function tierOf(a) {
  if (!a) return { tier: null, name: null, reason: null }
  if (tierCache.has(a)) return tierCache.get(a)
  const r = classifyIHRTier(a); tierCache.set(a, r); return r
}
const annex2Cache = new WeakMap()
function annex2Of(a) {
  if (!a) return { yes: 0, total: 4, details: [], meetsThreshold: false, summary: '' }
  if (annex2Cache.has(a)) return annex2Cache.get(a)
  const r = assessAnnex2(a); annex2Cache.set(a, r); return r
}
const scCache = new WeakMap()
function scorecardOf(a) {
  if (!a) return { detect:{hrs:null,target:168,on_target:true,label:''}, notify:{hrs:null,target:24,on_target:true,label:''}, respond:{hrs:null,target:168,on_target:null,label:''}, bottleneck:null, overall:'ON_TARGET' }
  if (scCache.has(a)) return scCache.get(a)
  const r = evaluate717(a); scCache.set(a, r); return r
}
function nextStep(a) { return nextEscalation(a) }
function canAct(a)   { return canActOnAlert(auth.value, a) }

// ── Intelligence feed ──────────────────────────────────────────────────────
const insights = computed(() => generateIntelligenceInsights(alerts.value, auth.value))
const breaches717 = computed(() => alerts.value.filter(a => evaluate717(a).overall === 'BREACH' && a.status !== 'CLOSED'))

// ── Analytics ──────────────────────────────────────────────────────────────
const riskDist = computed(() => riskDistribution(alerts.value))
const riskDistMax = computed(() => Math.max(1, ...Object.values(riskDist.value)))
const syndromes = computed(() => syndromeCloud(alerts.value))
const concentrations = computed(() => {
  // POE users already only see their POE — show district concentration instead
  const key = scope.value.kind === 'POE' ? 'district_code'
           : scope.value.kind === 'DISTRICT' ? 'poe_code'
           : scope.value.kind === 'PHEOC' ? 'district_code'
           : 'poe_code'
  return concentrationByGeo(alerts.value, key)
})
const histogram = computed(() => responseTimeHistogram(alerts.value))
const histMax = computed(() => Math.max(1, ...histogram.value.map(b => b.count)))
const totalAcknowledged = computed(() => alerts.value.filter(a => a.acknowledged_at).length)
const funnel = computed(() => escalationFunnel(alerts.value))
const funnelMax = computed(() => {
  let max = 1
  for (const r of Object.values(funnel.value)) max = Math.max(max, r.open + r.acked + r.closed)
  return max
})

// ── API ─────────────────────────────────────────────────────────────────────
async function api(path, opts = {}) {
  const uid = auth.value?.id
  if (!uid) return null
  const sep = path.includes('?') ? '&' : '?'
  const url = `${window.SERVER_URL}${path}${sep}user_id=${uid}`
  const ctrl = new AbortController()
  const tid = setTimeout(() => ctrl.abort(), APP.SYNC_TIMEOUT_MS)
  try {
    const res = await fetch(url, {
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      signal: ctrl.signal, ...opts,
    })
    clearTimeout(tid)
    const j = await res.json().catch(() => null)
    return { ok: res.ok, status: res.status, body: j }
  } catch (e) { clearTimeout(tid); return { ok: false, status: 0, body: null, error: e?.message } }
}

async function loadAlerts(reset = false) {
  if (loading.value) return
  loading.value = true
  // Reset intelligence caches
  if (reset) { page.value = 1; alerts.value = [] }
  try {
    let path = `/alerts?per_page=100&page=${page.value}`
    if (statusFilter.value) path += `&status=${statusFilter.value}`
    if (routeFilter.value)  path += `&routed_to_level=${routeFilter.value}`
    const res = await api(path)
    if (!res?.ok || !res.body?.success) { isOnline.value = false; return }
    isOnline.value = true
    const d = res.body.data
    if (reset) alerts.value = d.items || []
    else alerts.value = [...alerts.value, ...(d.items || [])]
    hasMore.value = (d.page ?? 1) < (d.pages ?? 1)
  } finally { loading.value = false }
}

async function loadMore() {
  if (loadingMore.value || !hasMore.value) return
  loadingMore.value = true; page.value++
  try { await loadAlerts(false) } finally { loadingMore.value = false }
}

async function pullRefresh(ev) { await loadAlerts(true); ev.target.complete() }

// ── Actions ─────────────────────────────────────────────────────────────────
async function acknowledge(a) {
  if (!canActOnAlert(auth.value, a)) { showToast('Your role cannot acknowledge this alert.', 'warning'); return }
  acting.value = 'ack'
  try {
    const res = await api(`/alerts/${a.id}/acknowledge`, { method: 'PATCH', body: JSON.stringify({ user_id: auth.value?.id }) })
    if (res?.ok && res.body?.success) {
      applyUpdate(a.id, res.body.data)
      showToast('Alert acknowledged.', 'success')
    } else {
      showToast(res?.body?.message || 'Failed to acknowledge.', 'danger')
    }
  } finally { acting.value = null }
}

function promptClose(a) {
  if (!canActOnAlert(auth.value, a)) { showToast('Your role cannot close this alert.', 'warning'); return }
  closeModal.show = true; closeModal.alert = a
  closeModal.reason = ''; closeModal.error = null; closeModal.submitting = false
}
function cancelClose() {
  closeModal.show = false; closeModal.alert = null
  closeModal.reason = ''; closeModal.error = null
}
async function confirmClose() {
  if (!closeModal.alert) return
  const reason = closeModal.reason.trim()
  if (reason.length < 5) { closeModal.error = 'Reason must be at least 5 characters.'; return }
  closeModal.submitting = true; closeModal.error = null
  try {
    const res = await api(`/alerts/${closeModal.alert.id}/close`, {
      method: 'PATCH',
      body: JSON.stringify({ user_id: auth.value?.id, close_reason: reason }),
    })
    if (res?.ok && res.body?.success) {
      applyUpdate(closeModal.alert.id, res.body.data)
      showToast('Alert closed.', 'success')
      cancelClose()
    } else {
      closeModal.error = res?.body?.message || 'Failed to close.'
    }
  } finally { closeModal.submitting = false }
}

function applyUpdate(id, updated) {
  const idx = alerts.value.findIndex(x => x.id === id)
  if (idx !== -1) { alerts.value[idx] = updated; alerts.value = [...alerts.value] }
  if (detail.value?.id === id) detail.value = { ...detail.value, ...updated }
}

async function openDetail(a) {
  detail.value = { ...a }
  modalTab.value = 'overview'
  const res = await api(`/alerts/${a.id}`)
  if (res?.ok && res.body?.success && detail.value?.id === a.id) {
    detail.value = res.body.data
  }
}
function closeDetail() { detail.value = null; modalTab.value = 'overview' }

// ── View record — opens the READ-ONLY screening record, not the form ────────
async function openRecord(a) {
  if (!a.secondary_screening_id && !a.secondary_case) return
  try {
    let caseUuid = a.secondary_case?.client_uuid || detail.value?.secondary_case?.client_uuid || null
    if (!caseUuid) {
      const res = await api(`/alerts/${a.id}`)
      if (res?.ok && res.body?.success) caseUuid = res.body.data?.secondary_case?.client_uuid || null
    }
    if (!caseUuid) {
      const allSec = await dbGetAll(STORE.SECONDARY_SCREENINGS).catch(() => [])
      const match = allSec.find(s =>
        ((s.id && Number(s.id) === Number(a.secondary_screening_id))
          || (s.server_id && Number(s.server_id) === Number(a.secondary_screening_id)))
        && !s.deleted_at,
      )
      caseUuid = match?.client_uuid || null
    }
    if (caseUuid) {
      closeDetail()
      // Navigate to the secondary records list with the specific record auto-opened.
      router.push({ path: '/secondary-screening/records', query: { open: caseUuid } })
      return
    }
    showToast('Case record not cached locally yet. Open Secondary Records to sync first.', 'warning')
  } catch (e) {
    showToast('Unable to open record.', 'danger')
  }
}

// ── Helpers ─────────────────────────────────────────────────────────────────
function showToast(msg, color = 'success') { toast.show = true; toast.msg = msg; toast.color = color }
function cleanDetails(d) { return (d || '').replace(/\[CLOSED:[^\]]+\]/g, '').trim() }
function truncate(s, n) { return (s && s.length > n) ? s.slice(0, n) + '...' : s }
function fmtAge(h) {
  if (h == null) return '—'
  if (h < 1)  return Math.round(h * 60) + 'm'
  if (h < 24) return Math.round(h * 10) / 10 + 'h'
  return Math.round(h / 24 * 10) / 10 + 'd'
}
function fmtHrs(h) {
  if (h == null) return '—'
  if (h < 1) return Math.round(h * 60) + 'm'
  if (h < 24) return (Math.round(h * 10) / 10) + 'h'
  return (Math.round(h / 24 * 10) / 10) + 'd'
}
function fmtDate(dt) { if (!dt) return ''; try { return new Date(String(dt).replace(' ', 'T')).toLocaleDateString([], { day: '2-digit', month: 'short' }) } catch { return dt } }
function fmtDateTime(dt) { if (!dt) return ''; try { return new Date(String(dt).replace(' ', 'T')).toLocaleString([], { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) } catch { return dt } }
function pctBar(part, total) {
  if (part == null || total == null || total <= 0) return 0
  return Math.max(0, Math.min(100, Math.round(part / total * 100)))
}
function levelIcon(level) {
  return level === 'critical' ? '!!' : level === 'high' ? '!' : level === 'medium' ? 'i' : '•'
}

function recommendedAction(a) {
  if (!a) return ''
  const r = a.risk_level, s = a.status, rt = a.routed_to_level
  const tier = tierOf(a).tier
  const annex = annex2Of(a)
  if (s === 'CLOSED') return 'Closed. No further action required. Record archived for audit.'
  if (tier === 1) return `IHR Tier 1 event — single case requires WHO notification within 24 hours via the National IHR Focal Point. ${s === 'OPEN' ? 'Acknowledge and activate full response NOW.' : 'Verify chain of notification is active.'}`
  if (tier === 2 && annex.meetsThreshold) return `IHR Tier 2 event meeting ${annex.yes}/4 Annex 2 criteria — WHO notification required within 24 hours. ${s === 'OPEN' ? 'Acknowledge and coordinate NOW.' : 'Ensure response is underway.'}`
  if (r === 'CRITICAL') return `CRITICAL alert. Immediate clinical response and contact tracing required. ${rt === 'NATIONAL' ? 'National emergency coordination active.' : rt === 'PHEOC' ? 'PHEOC escalation in progress.' : 'District health officer must be engaged.'} ${s === 'OPEN' ? 'Acknowledge NOW.' : 'Verify response is underway.'}`
  if (r === 'HIGH' && a.overdue_24h) return `HIGH alert overdue (>24h). Escalate to ${rt === 'DISTRICT' ? 'PHEOC' : rt === 'PHEOC' ? 'national level' : 'supervisor'}. Document delay in audit log.`
  if (r === 'HIGH') return `HIGH alert. Acknowledge within 4 hours. Coordinate with ${rt.toLowerCase()} health authorities. Review linked case disposition.`
  if (r === 'MEDIUM') return 'MEDIUM alert. Acknowledge within 24 hours. Standard surveillance reporting applies.'
  return 'Acknowledge during routine review. No immediate escalation required.'
}

// ── Connectivity ────────────────────────────────────────────────────────────
function onOnline()  { isOnline.value = true; loadAlerts(true) }
function onOffline() { isOnline.value = false }

onMounted(() => {
  auth.value = getAuth()
  window.addEventListener('online',  onOnline)
  window.addEventListener('offline', onOffline)
  loadAlerts(true)
  pollTimer = setInterval(() => {
    if (isOnline.value && !loading.value) loadAlerts(true)
  }, 30_000)
})
// Every view entry triggers a fresh sync
onIonViewWillEnter(() => { auth.value = getAuth() })
onIonViewDidEnter(() => { if (isOnline.value) loadAlerts(true) })
onUnmounted(() => {
  window.removeEventListener('online', onOnline)
  window.removeEventListener('offline', onOffline)
  clearInterval(pollTimer)
})
</script>

<style scoped>
*{box-sizing:border-box}

/* ═ HEADER ═ */
.ih-hdr{--background:transparent;border:none}
.ih-hdr-bg{background:linear-gradient(135deg,#001D3D,#003566,#003F88);padding:0 0 0}
.ih-hdr-top{display:flex;align-items:center;gap:4px;padding:8px 8px 0}
.ih-menu{--color:rgba(255,255,255,.75)}
.ih-hdr-title{flex:1;display:flex;flex-direction:column;min-width:0}
.ih-hdr-eye{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1.4px;color:rgba(255,255,255,.45)}
.ih-hdr-h1{font-size:16px;font-weight:800;color:#fff;letter-spacing:-.2px}
.ih-ref{width:32px;height:32px;border-radius:50%;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.06);color:rgba(255,255,255,.75);cursor:pointer;display:flex;align-items:center;justify-content:center}
.ih-ref:disabled{opacity:.4}
.ih-spin{animation:ih-rotate 1s linear infinite}
@keyframes ih-rotate{to{transform:rotate(360deg)}}

/* KPI */
.ih-kpis{display:flex;gap:4px;padding:8px 8px 6px}
.ih-kpi{flex:1;padding:8px 4px;border-radius:8px;border:none;background:rgba(255,255,255,.06);color:rgba(255,255,255,.78);cursor:pointer;display:flex;flex-direction:column;gap:1px;align-items:center;min-width:0;transition:all .15s}
.ih-kpi--on{background:rgba(255,255,255,.22);color:#fff}
.ih-kpi-n{font-size:18px;font-weight:900;line-height:1}
.ih-kpi-l{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;opacity:.75}
.ih-kpi--crit.ih-kpi--on{background:rgba(229,57,53,.35)}
.ih-kpi--crit .ih-kpi-n{color:#FF8A80}
.ih-kpi--high.ih-kpi--on{background:rgba(255,152,0,.35)}
.ih-kpi--high .ih-kpi-n{color:#FFB74D}
.ih-kpi--over.ih-kpi--on{background:rgba(229,57,53,.35)}
.ih-kpi--over .ih-kpi-n{color:#FF8A80}
.ih-kpi--t1.ih-kpi--on{background:rgba(147,51,234,.38)}
.ih-kpi--t1 .ih-kpi-n{color:#CE93D8}

/* Tabs */
.ih-tabs{display:flex;padding:4px 4px 0;overflow-x:auto;scrollbar-width:none}
.ih-tabs::-webkit-scrollbar{display:none}
.ih-tab{flex:1;min-width:0;padding:10px 6px 12px;background:transparent;border:none;border-bottom:2px solid transparent;color:rgba(255,255,255,.55);font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:4px;position:relative;white-space:nowrap}
.ih-tab ion-icon{font-size:14px}
.ih-tab--on{color:#fff;border-bottom-color:#60A5FA}
.ih-tab--nav{color:rgba(255,255,255,.7)}
.ih-tab--nav:hover{color:#fff}
.ih-tab-badge{background:#DC2626;color:#fff;font-size:9px;font-weight:900;border-radius:10px;padding:1px 6px;margin-left:2px}

/* CONTENT */
.ih-content{--background:#F0F4FA}
.ih-offline{padding:8px 12px;background:#FFF3E0;border-bottom:1px solid #FFB74D;font-size:11px;color:#BF360C;text-align:center;font-weight:600}
.ih-section{padding:6px 0 0}
.ih-sh{font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:1px;margin:16px 14px 8px}
.ih-pad{padding:0 14px}

/* Filter row */
.ih-frow{padding:6px 8px 2px;display:flex;flex-direction:column;gap:4px}
.ih-fs{display:flex;gap:4px;overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch}.ih-fs::-webkit-scrollbar{display:none}
.ih-fp{padding:5px 11px;border-radius:99px;border:1px solid #CBD5E1;background:#fff;color:#475569;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0}
.ih-fp--on{background:#1E40AF;border-color:#1E40AF;color:#fff}

/* Skeletons */
.ih-skels{padding:8px 10px}
.ih-skel{background:#fff;border-radius:10px;padding:12px;margin-bottom:8px;border:1px solid #E8EDF5}
.ih-sk{height:10px;background:linear-gradient(90deg,#E2E8F0 25%,#F1F5F9 50%,#E2E8F0 75%);background-size:200% 100%;animation:ih-sh 1.4s linear infinite;border-radius:4px;margin-bottom:6px}
.ih-sk--1{width:60%}.ih-sk--2{width:80%}.ih-sk--3{width:40%}
@keyframes ih-sh{0%{background-position:200% 0}100%{background-position:-200% 0}}

/* Empty */
.ih-empty{display:flex;flex-direction:column;align-items:center;padding:50px 20px;gap:8px}
.ih-empty-ic{color:#10B981}
.ih-empty-t{font-size:16px;font-weight:800;color:#1A3A5C}
.ih-empty-s{font-size:12px;color:#64748B;text-align:center;max-width:280px}

/* Alert cards */
.ih-list{padding:8px 10px 0}
.ih-card{display:flex;background:#fff;border-radius:10px;border:1px solid #E8EDF5;overflow:hidden;margin-bottom:8px;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,.04);transition:transform .12s}
.ih-card:active{transform:scale(.995)}
.ih-card--overdue{box-shadow:0 0 0 1.5px rgba(220,38,38,.4),0 1px 3px rgba(0,0,0,.06)}
.ih-card--acked{opacity:.85}.ih-card--closed{opacity:.6}
.ih-card-stripe{width:4px;flex-shrink:0}
.ih-stripe--critical{background:linear-gradient(180deg,#DC2626,#991B1B)}
.ih-stripe--high{background:linear-gradient(180deg,#EA580C,#C2410C)}
.ih-stripe--medium{background:linear-gradient(180deg,#CA8A04,#A16207)}
.ih-stripe--low{background:#10B981}
.ih-card-body{flex:1;min-width:0;padding:10px 12px}
.ih-card-top{display:flex;align-items:center;gap:4px;flex-wrap:wrap;margin-bottom:6px}
.ih-risk{font-size:9px;font-weight:800;padding:2px 6px;border-radius:4px;letter-spacing:.3px}
.ih-risk--critical{background:#FEE2E2;color:#991B1B}.ih-risk--high{background:#FFEDD5;color:#9A3412}.ih-risk--medium{background:#FEF3C7;color:#854D0E}.ih-risk--low{background:#D1FAE5;color:#047857}
.ih-route{font-size:9px;font-weight:700;padding:2px 5px;border-radius:4px;background:#F1F5F9;color:#475569}
.ih-route--national{background:#E0E7FF;color:#3730A3}.ih-route--pheoc{background:#FCE7F3;color:#9D174D}.ih-route--district{background:#DCFCE7;color:#166534}
.ih-st{font-size:9px;font-weight:700;padding:2px 5px;border-radius:4px}
.ih-st--open{background:#FEE2E2;color:#991B1B}.ih-st--acknowledged{background:#DBEAFE;color:#1E40AF}.ih-st--closed{background:#F1F5F9;color:#64748B}
.ih-tier{font-size:9px;font-weight:800;padding:2px 5px;border-radius:4px;letter-spacing:.3px}
.ih-tier--1{background:#F3E8FF;color:#6B21A8;border:1px solid #D8B4FE}
.ih-tier--2{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA}
.ih-717{font-size:9px;font-weight:800;padding:2px 5px;border-radius:4px;letter-spacing:.3px}
.ih-717--b{background:#7F1D1D;color:#FEE2E2}
.ih-717--r{background:#FEF3C7;color:#854D0E;border:1px solid #FDE68A}
.ih-age{margin-left:auto;font-size:10px;color:#94A3B8;font-weight:700;white-space:nowrap}
.ih-age--o{color:#DC2626;font-weight:800}
.ih-card-title{font-size:14px;font-weight:800;color:#1A3A5C;line-height:1.3;margin-bottom:2px}
.ih-card-code{font-size:10px;color:#94A3B8;font-family:ui-monospace,monospace;margin-bottom:6px}
.ih-card-meta{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:6px}
.ih-chip{font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;background:#F1F5F9;color:#475569}
.ih-chip--poe{background:#DBEAFE;color:#1E40AF}
.ih-chip--off{background:#EDE9FE;color:#6D28D9}
.ih-chip--auto{background:#E0F2FE;color:#0369A1}
.ih-card-det{font-size:11px;color:#475569;line-height:1.45;margin-bottom:6px}
.ih-card-ack{font-size:10px;color:#059669;font-weight:600}
.ih-more{display:flex;justify-content:center;padding:10px 0}
.ih-more-btn{padding:8px 20px;border-radius:99px;border:1px solid #CBD5E1;background:#fff;color:#475569;font-size:12px;font-weight:700;cursor:pointer}
.ih-more-btn:disabled{opacity:.4}

/* INTELLIGENCE */
.ih-ins{margin:8px 10px 0;background:#fff;border-left:3px solid #E5E7EB;border:1px solid #E8EDF5;border-radius:10px;padding:12px 14px;overflow:hidden;position:relative}
.ih-ins::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px}
.ih-ins--critical::before{background:#DC2626}
.ih-ins--high::before{background:#EA580C}
.ih-ins--medium::before{background:#CA8A04}
.ih-ins--info::before{background:#16A34A}
.ih-ins-hdr{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.ih-ins-ic{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:900;color:#fff;flex-shrink:0}
.ih-ins--critical .ih-ins-ic{background:#DC2626}
.ih-ins--high .ih-ins-ic{background:#EA580C}
.ih-ins--medium .ih-ins-ic{background:#CA8A04}
.ih-ins--info .ih-ins-ic{background:#16A34A}
.ih-ins-t{font-size:13px;font-weight:800;color:#1A3A5C;flex:1;line-height:1.3}
.ih-ins-body{font-size:12px;color:#475569;line-height:1.5;margin:4px 0 6px}
.ih-ins-acts{font-size:11.5px;color:#1A3A5C;padding-left:18px;margin:6px 0 6px;line-height:1.5}
.ih-ins-acts li{margin-bottom:2px}
.ih-ins-cite{font-size:10px;color:#94A3B8;font-style:italic;margin-top:6px}

/* 7-1-7 ledger */
.ih-717-row{margin:8px 10px 0;background:#fff;border:1px solid #FECACA;border-radius:10px;padding:10px 12px;cursor:pointer}
.ih-717-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}
.ih-717-code{font-size:10px;font-family:ui-monospace,monospace;color:#94A3B8}
.ih-717-bn{font-size:10px;font-weight:800;padding:2px 6px;border-radius:4px;background:#FEE2E2;color:#991B1B}
.ih-717-bn--detect{background:#F3E8FF;color:#6B21A8}
.ih-717-bn--notify{background:#FEE2E2;color:#991B1B}
.ih-717-bn--respond{background:#FFEDD5;color:#9A3412}
.ih-717-title{font-size:13px;font-weight:800;color:#1A3A5C;margin-bottom:8px}
.ih-717-bars{display:flex;flex-direction:column;gap:6px}
.ih-717-bar{display:flex;align-items:center;gap:8px;font-size:11px}
.ih-717-bl{width:62px;color:#64748B;font-weight:700;flex-shrink:0}
.ih-717-bt{flex:1;height:6px;background:#F1F5F9;border-radius:3px;overflow:hidden}
.ih-717-bf{height:100%;transition:width .3s}
.ih-717-bf.ok{background:#10B981}
.ih-717-bf.bad{background:#DC2626}
.ih-717-bf.pending{background:#CBD5E1}
.ih-717-br{width:80px;text-align:right;color:#1A3A5C;font-weight:700;font-variant-numeric:tabular-nums;font-size:10.5px;flex-shrink:0}

/* ANALYTICS */
.ih-risk{margin:0 10px;display:flex;flex-direction:column;gap:8px}
.ih-risk-row{display:flex;align-items:center;gap:10px;padding:10px 12px;background:#fff;border:1px solid #E8EDF5;border-radius:10px}
.ih-risk-l{width:72px;font-size:11px;font-weight:800;color:#1A3A5C;flex-shrink:0}
.ih-risk-t{flex:1;height:8px;background:#F1F5F9;border-radius:4px;overflow:hidden}
.ih-risk-f{height:100%;transition:width .3s}
.ih-risk-row--critical .ih-risk-f{background:linear-gradient(90deg,#DC2626,#991B1B)}
.ih-risk-row--high .ih-risk-f{background:linear-gradient(90deg,#EA580C,#C2410C)}
.ih-risk-row--medium .ih-risk-f{background:linear-gradient(90deg,#CA8A04,#A16207)}
.ih-risk-row--low .ih-risk-f{background:#10B981}
.ih-risk-n{width:36px;text-align:right;font-size:12px;font-weight:800;color:#1A3A5C;font-variant-numeric:tabular-nums;flex-shrink:0}

.ih-syn{margin:0 10px;display:flex;flex-direction:column;gap:6px}
.ih-syn-row{display:flex;align-items:center;gap:10px;padding:8px 12px;background:#fff;border:1px solid #E8EDF5;border-radius:8px}
.ih-syn-l{flex:1;min-width:0;font-size:11.5px;font-weight:700;color:#1A3A5C;text-transform:capitalize;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ih-syn-t{width:80px;height:6px;background:#F1F5F9;border-radius:3px;overflow:hidden;flex-shrink:0}
.ih-syn-f{height:100%;background:linear-gradient(90deg,#1E40AF,#3B82F6);transition:width .3s}
.ih-syn-n{width:30px;text-align:right;font-size:11px;font-weight:800;color:#1A3A5C;font-variant-numeric:tabular-nums;flex-shrink:0}

.ih-conc{margin:0 10px;display:flex;flex-direction:column;gap:4px}
.ih-conc-row{display:flex;align-items:center;gap:10px;padding:9px 12px;background:#fff;border:1px solid #E8EDF5;border-radius:8px}
.ih-conc-rank{width:20px;height:20px;border-radius:50%;background:#DBEAFE;color:#1E40AF;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ih-conc-l{flex:1;font-size:12px;font-weight:700;color:#1A3A5C;font-family:ui-monospace,monospace}
.ih-conc-n{font-size:13px;font-weight:800;color:#1E40AF;font-variant-numeric:tabular-nums}

.ih-hist{margin:0 10px;display:flex;align-items:flex-end;gap:6px;padding:14px;background:#fff;border:1px solid #E8EDF5;border-radius:10px;height:160px}
.ih-hist-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;min-width:0}
.ih-hist-bar{width:100%;flex:1;display:flex;align-items:flex-end;background:#F1F5F9;border-radius:3px;overflow:hidden}
.ih-hist-f{width:100%;background:linear-gradient(180deg,#60A5FA,#1E40AF);transition:height .3s}
.ih-hist-n{font-size:11px;font-weight:800;color:#1A3A5C;font-variant-numeric:tabular-nums}
.ih-hist-l{font-size:9px;font-weight:700;color:#64748B;text-align:center}

.ih-funnel{margin:0 10px;display:flex;flex-direction:column;gap:8px}
.ih-funnel-row{display:flex;align-items:center;gap:10px;padding:10px 12px;background:#fff;border:1px solid #E8EDF5;border-radius:10px}
.ih-funnel-l{width:76px;font-size:11px;font-weight:800;color:#1A3A5C;flex-shrink:0}
.ih-funnel-bars{flex:1;display:flex;gap:2px;height:20px}
.ih-fb{background:#E2E8F0;color:#fff;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;min-width:0;border-radius:3px;font-variant-numeric:tabular-nums;transition:width .3s}
.ih-fb--open{background:#DC2626}
.ih-fb--ack{background:#3B82F6}
.ih-fb--closed{background:#64748B}
.ih-legend{display:flex;gap:14px;justify-content:center;padding:10px;font-size:10px;color:#475569;font-weight:600}
.ih-legend span{display:flex;align-items:center;gap:4px}
.ih-legend-sw{width:10px;height:10px;border-radius:2px;display:inline-block}

/* MATRIX reference cards */
.ih-ref{margin:10px 10px 0;background:#fff;border:1px solid #E8EDF5;border-radius:12px;padding:14px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.ih-ref--t1{border-left:4px solid #9333EA}
.ih-ref--t2{border-left:4px solid #DC2626}
.ih-ref--annex{border-left:4px solid #1E40AF}
.ih-ref--717{border-left:4px solid #059669}
.ih-ref--esc{border-left:4px solid #CA8A04}
.ih-ref--pheic{border-left:4px solid #0F172A}
.ih-ref-hdr{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.ih-ref-tag{font-size:10px;font-weight:900;padding:3px 8px;border-radius:4px;background:#1E40AF;color:#fff;letter-spacing:.4px}
.ih-ref--t1 .ih-ref-tag{background:#9333EA}
.ih-ref--t2 .ih-ref-tag{background:#DC2626}
.ih-ref--717 .ih-ref-tag{background:#059669}
.ih-ref--esc .ih-ref-tag{background:#CA8A04}
.ih-ref--pheic .ih-ref-tag{background:#0F172A}
.ih-ref-title{font-size:14px;font-weight:800;color:#1A3A5C}
.ih-ref-body{font-size:12px;color:#475569;line-height:1.5;margin:4px 0 8px}
.ih-ref-list{font-size:12px;color:#1A3A5C;font-weight:600;padding-left:18px;margin:6px 0 8px;line-height:1.6}
.ih-ref-crit{font-size:12px;color:#1A3A5C;padding-left:18px;margin:6px 0 8px;line-height:1.6}
.ih-ref-crit strong{color:#1E40AF}
.ih-ref-cite{font-size:10px;color:#94A3B8;font-style:italic;margin-top:6px;padding-top:6px;border-top:1px dashed #E8EDF5}

.ih-717-targets{display:flex;gap:8px;margin:8px 0}
.ih-717-t{flex:1;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;padding:10px 8px;display:flex;flex-direction:column;gap:3px;align-items:center;text-align:center}
.ih-717-tn{font-size:26px;font-weight:900;color:#059669;line-height:1}
.ih-717-tl{font-size:10.5px;font-weight:800;color:#14532D;letter-spacing:.2px}
.ih-717-td{font-size:9.5px;color:#059669;font-weight:600}

.ih-ladder{display:flex;flex-direction:column;gap:6px;margin:4px 0}
.ih-ladder-step{display:flex;align-items:center;gap:6px;padding:8px 10px;background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;font-size:11px;flex-wrap:wrap}
.ih-ladder-lv{font-weight:800;color:#1A3A5C;padding:2px 7px;border-radius:4px;background:#fff;border:1px solid #E8EDF5}
.ih-ladder-arr{color:#CA8A04;font-weight:900}
.ih-ladder-t{margin-left:auto;font-size:10px;color:#854D0E;font-weight:700;font-variant-numeric:tabular-nums}

/* MODAL */
.ih-modal::part(content){border-radius:14px 14px 0 0}
.ih-mt{--background:#fff;--border-width:0 0 1px 0;--border-color:#E8EDF5;--min-height:38px}
.ih-handle{width:34px;height:3px;border-radius:2px;background:#DDE3EA;margin:0 auto}
.ih-mc{--background:#fff}
.ih-mp{padding:14px 14px 0}

.ih-hero{padding:14px;border-radius:10px;margin-bottom:12px}
.ih-hero--critical{background:#FEF2F2;border:1px solid #FECACA}
.ih-hero--high{background:#FFF7ED;border:1px solid #FED7AA}
.ih-hero--medium{background:#FEFCE8;border:1px solid #FDE68A}
.ih-hero--low{background:#F0FDF4;border:1px solid #BBF7D0}
.ih-hero-cat{display:block;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#94A3B8;margin-bottom:4px}
.ih-hero-title{display:block;font-size:16px;font-weight:800;color:#1A3A5C;line-height:1.3}
.ih-hero-code{display:block;font-size:10px;color:#94A3B8;margin-top:4px;font-family:ui-monospace,monospace}

.ih-mtabs{display:flex;gap:4px;margin-bottom:12px;background:#F1F5F9;padding:3px;border-radius:8px}
.ih-mtab{flex:1;padding:8px;background:transparent;border:none;border-radius:6px;color:#64748B;font-size:11.5px;font-weight:700;cursor:pointer;transition:all .15s}
.ih-mtab--on{background:#fff;color:#1E40AF;box-shadow:0 1px 3px rgba(0,0,0,.08)}

.ih-mh{font-size:12px;font-weight:800;color:#1A3A5C;margin:14px 0 6px;text-transform:uppercase;letter-spacing:.5px}
.ih-mg{display:flex;flex-direction:column;background:#F8FAFC;border-radius:8px;overflow:hidden;border:1px solid #E8EDF5}
.ih-mr{display:flex;justify-content:space-between;align-items:center;padding:7px 12px;border-top:1px solid #E8EDF5}
.ih-mr:first-child{border-top:none}
.ih-mk{font-size:11px;color:#64748B;font-weight:600}
.ih-mv{font-size:12px;font-weight:700;color:#1A3A5C}
.ih-mv--critical,.ih-mv--r{color:#DC2626!important}
.ih-mv--high{color:#EA580C}
.ih-mv--open{color:#991B1B}
.ih-mv--acknowledged{color:#1E40AF}
.ih-mv--closed{color:#64748B}

.ih-details{background:#F8FAFC;border-radius:8px;padding:12px;border:1px solid #E8EDF5;font-size:12px;color:#475569;line-height:1.5}
.ih-details p{margin:0}
.ih-none{color:#94A3B8;font-style:italic}

.ih-disease{background:#F8FAFC;border-radius:8px;padding:12px;border:1px solid #E8EDF5;display:flex;flex-direction:column;gap:2px}
.ih-dn{font-size:14px;font-weight:800;color:#1A3A5C;text-transform:capitalize}
.ih-dc{font-size:10px;color:#94A3B8;font-family:ui-monospace,monospace}

/* IHR classification card */
.ih-cls{border-radius:10px;padding:12px;border:1px solid}
.ih-cls--t1{background:#F3E8FF;border-color:#D8B4FE}
.ih-cls--t2{background:#FEF2F2;border-color:#FECACA}
.ih-cls--none{background:#F8FAFC;border-color:#E8EDF5}
.ih-cls-hdr{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.ih-cls-tag{font-size:9px;font-weight:900;padding:2px 7px;border-radius:4px;background:#fff;letter-spacing:.4px}
.ih-cls--t1 .ih-cls-tag{color:#6B21A8}
.ih-cls--t2 .ih-cls-tag{color:#991B1B}
.ih-cls-name{font-size:13px;font-weight:800;color:#1A3A5C}
.ih-cls-body{font-size:11.5px;color:#475569;line-height:1.5;margin:0}

/* Annex 2 */
.ih-annex{background:#fff;border:1px solid #E8EDF5;border-radius:10px;padding:12px}
.ih-annex--hit{border-color:#DC2626;background:#FEF2F2}
.ih-annex-top{display:flex;align-items:center;gap:10px;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #E8EDF5}
.ih-annex--hit .ih-annex-top{border-bottom-color:#FECACA}
.ih-annex-score{font-size:20px;font-weight:900;color:#1A3A5C;font-variant-numeric:tabular-nums}
.ih-annex--hit .ih-annex-score{color:#DC2626}
.ih-annex-note{font-size:11px;font-weight:700;color:#475569}
.ih-annex--hit .ih-annex-note{color:#991B1B;font-weight:800}
.ih-annex-row{display:flex;gap:10px;padding:8px 0;border-top:1px solid #F0F4FA;align-items:flex-start}
.ih-annex-row:first-of-type{border-top:none;padding-top:0}
.ih-annex-badge{flex-shrink:0;font-size:9.5px;font-weight:900;padding:3px 8px;border-radius:4px;letter-spacing:.3px;margin-top:1px}
.ih-annex-badge--y{background:#DC2626;color:#fff}
.ih-annex-badge--n{background:#E5E7EB;color:#475569}
.ih-annex-body{flex:1;min-width:0}
.ih-annex-lbl{display:block;font-size:12px;font-weight:800;color:#1A3A5C;line-height:1.3}
.ih-annex-basis{display:block;font-size:10.5px;color:#64748B;margin-top:2px;line-height:1.4}
.ih-annex-summary{margin:10px 0 0;padding-top:8px;border-top:1px solid #E8EDF5;font-size:11.5px;color:#1A3A5C;font-weight:700;line-height:1.4}
.ih-annex--hit .ih-annex-summary{border-top-color:#FECACA;color:#991B1B}

/* 7-1-7 scorecard (modal) */
.ih-sc{background:#fff;border:1px solid #E8EDF5;border-radius:10px;padding:12px}
.ih-sc--breach{border-color:#DC2626;background:#FEF2F2}
.ih-sc--at_risk{border-color:#F59E0B;background:#FFFBEB}
.ih-sc-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.ih-sc-ov{font-size:13px;font-weight:900;letter-spacing:.3px;color:#1A3A5C}
.ih-sc--breach .ih-sc-ov{color:#DC2626}
.ih-sc--at_risk .ih-sc-ov{color:#B45309}
.ih-sc--on_target .ih-sc-ov{color:#059669}
.ih-sc-bn{font-size:10px;font-weight:800;padding:2px 7px;border-radius:4px;background:#FEE2E2;color:#991B1B}
.ih-sc-rows{display:flex;flex-direction:column;gap:8px}
.ih-sc-row{display:flex;flex-direction:column;gap:3px}
.ih-sc-meta{display:flex;justify-content:space-between;font-size:11px}
.ih-sc-l{font-weight:800;color:#1A3A5C}
.ih-sc-h{color:#64748B;font-weight:700;font-variant-numeric:tabular-nums}
.ih-sc-bar{height:6px;background:#F1F5F9;border-radius:3px;overflow:hidden}
.ih-sc-f{height:100%;transition:width .3s}
.ih-sc-f.ok{background:#10B981}.ih-sc-f.bad{background:#DC2626}.ih-sc-f.pending{background:#CBD5E1}

/* Next escalation */
.ih-nxt{background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:12px}
.ih-nxt-row{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.ih-nxt-tag{font-size:9px;font-weight:900;padding:2px 7px;border-radius:4px;background:#1E40AF;color:#fff;letter-spacing:.4px}
.ih-nxt-target{font-size:13px;font-weight:800;color:#1E40AF}
.ih-nxt-hrs{font-size:10.5px;color:#1E40AF;font-weight:700;font-variant-numeric:tabular-nums;margin-left:auto}
.ih-nxt-note{font-size:11.5px;color:#1E40AF;margin:0;line-height:1.5;font-weight:600}

/* Recommended action */
.ih-rec{border-radius:10px;padding:12px;border:1px solid}
.ih-rec--critical{background:#FEF2F2;border-color:#FECACA}
.ih-rec--high{background:#FFF7ED;border-color:#FED7AA}
.ih-rec--medium{background:#FEFCE8;border-color:#FDE68A}
.ih-rec--low{background:#F0FDF4;border-color:#BBF7D0}
.ih-rec-hdr{display:block;font-size:9.5px;font-weight:900;text-transform:uppercase;letter-spacing:.6px;color:#991B1B;margin-bottom:6px}
.ih-rec--high .ih-rec-hdr{color:#9A3412}.ih-rec--medium .ih-rec-hdr{color:#854D0E}.ih-rec--low .ih-rec-hdr{color:#047857}
.ih-rec-body{font-size:12px;color:#1A3A5C;margin:0;line-height:1.5;font-weight:600}

.ih-ro{display:flex;gap:10px;margin-top:14px;padding:12px;background:#F1F5F9;border:1px solid #CBD5E1;border-radius:8px;font-size:11.5px;color:#475569;line-height:1.4}
.ih-ro-ic{flex-shrink:0;font-size:16px}
.ih-ro strong{color:#1A3A5C;font-weight:800}

.ih-mact{display:flex;gap:8px;margin-top:16px}
.ih-mact--view{margin-top:10px}
.ih-mact-btn{flex:1;padding:12px;border-radius:8px;border:none;font-size:13px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px}
.ih-mact-btn:disabled{opacity:.4;cursor:not-allowed}
.ih-mact-btn--ack{background:#1E40AF;color:#fff}
.ih-mact-btn--close{background:#fff;color:#475569;border:1px solid #CBD5E1}
.ih-mact-btn--view{background:#1A3A5C;color:#fff}
.ih-mact-btn--view ion-icon{font-size:15px}

/* Close reason modal */
.ih-closemod{--background:#fff}
.ih-closepad{padding:20px 16px}
.ih-closet{font-size:17px;font-weight:800;color:#1A3A5C;margin:0 0 8px}
.ih-closes{font-size:12px;color:#64748B;margin:0 0 12px;line-height:1.4}
.ih-closetx{width:100%;padding:10px;border:1.5px solid #E8EDF5;border-radius:8px;font-size:13px;color:#1A3A5C;font-family:inherit;resize:vertical;outline:none}
.ih-closetx:focus{border-color:#1E40AF;box-shadow:0 0 0 3px rgba(30,64,175,.1)}
.ih-closeerr{color:#DC2626;font-size:11px;font-weight:600;margin-top:6px}
.ih-closeacts{display:flex;gap:8px;margin-top:14px}
.ih-closecancel{flex:1;padding:11px;border-radius:7px;border:1px solid #CBD5E1;background:#fff;color:#475569;font-size:13px;font-weight:700;cursor:pointer}
.ih-closeconfirm{flex:1;padding:11px;border-radius:7px;border:none;background:#DC2626;color:#fff;font-size:13px;font-weight:700;cursor:pointer}
.ih-closeconfirm:disabled{opacity:.4;cursor:not-allowed}

@media(min-width:500px){
  .ih-list,.ih-ins,.ih-717-row,.ih-risk,.ih-syn,.ih-conc,.ih-hist,.ih-funnel,.ih-ref{max-width:480px;margin-left:auto;margin-right:auto}
}
</style>

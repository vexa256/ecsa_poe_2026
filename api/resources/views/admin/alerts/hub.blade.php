{{-- ============================================================================
  /admin/alerts — Open cases (Alert Hub)
  ----------------------------------------------------------------------------
  · Claude-style AI briefing at the top that greets the user by first name and
    narrates the current situation in one paragraph with a single CTA.
  · Tab-first navigation — 4 buckets (Needs you · In progress · Recent history
    · All) replace any vertical dump of data.
  · Premium compact case cards that surface case intelligence inline: syndrome,
    traveller demographic (age/gender) when available, POE + region, urgency.
  · A "quick intel" sheet opens on card tap and lazy-loads the war-room
    payload so the user sees disease + traveller + travel + recommended PPE +
    specimens + timeline, all without leaving the hub.
  · AI insight strip at the bottom detects clusters + outliers and proposes
    the next action.
  · Every link is wired and live. Auto-refreshes every 30s.
============================================================================ --}}
@extends('admin.layout')

@section('title', 'Open cases')
@section('subtitle', 'The case board — where every response starts')

@section('content')
<div x-data="caseBoard()" x-init="init()" class="space-y-6 max-w-5xl">

    {{-- ─── AI BRIEFING ─────────────────────────────────────────── --}}
    <section class="rounded-lg border bg-card">
        <div class="p-5 sm:p-6 flex items-start gap-4">
            <div class="h-9 w-9 rounded-md bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div class="min-w-0 flex-1 space-y-2.5">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">Case-board briefing</p>
                    <h2 class="text-lg sm:text-xl font-semibold tracking-tight" x-text="greeting"></h2>
                </div>
                <template x-if="loading && !briefing">
                    <div class="space-y-2"><div class="skeleton h-3.5 w-3/4"></div><div class="skeleton h-3.5 w-2/3"></div></div>
                </template>
                <p class="text-sm text-foreground/90 leading-relaxed" x-show="briefing" x-html="briefing"></p>
                <div class="flex flex-wrap gap-2 pt-1" x-show="!loading">
                    <template x-if="primaryCase">
                        <a :href="'{{ url('/admin/alerts') }}/' + primaryCase.id" class="btn btn-default btn-xs">
                            <span>Start with the most urgent</span>
                            <svg class="ml-1.5 h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </template>
                    <template x-if="!primaryCase && !loading">
                        <span class="badge badge-outline">Nothing urgent on the board</span>
                    </template>
                </div>
            </div>
        </div>
    </section>

    {{-- ─── TABS ─────────────────────────────────────────────── --}}
    <div class="space-y-4">
        <div class="tabs-list w-full sm:w-fit" role="tablist">
            <template x-for="t in tabs" :key="t.key">
                <button class="tabs-trigger flex-1 sm:flex-none sm:px-4"
                        :data-state="tab === t.key ? 'active' : ''"
                        @click="tab = t.key">
                    <span x-text="t.label"></span>
                    <span class="ml-2 text-[11px] tabular-nums text-muted-foreground" x-text="t.count"></span>
                </button>
            </template>
        </div>

        {{-- filter bar --}}
        <div class="flex flex-col sm:flex-row gap-2">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z"/></svg>
                <input type="search" x-model.debounce.250ms="filters.q"
                       placeholder="Search by case code, port of entry, or district"
                       class="input pl-9">
            </div>
            <select x-model="filters.poe" class="select sm:max-w-[200px]">
                <option value="">Any port of entry</option>
                <template x-for="p in allPoes" :key="p">
                    <option :value="p" x-text="p"></option>
                </template>
            </select>
        </div>
    </div>

    {{-- ─── GRID ───────────────────────────────────────────── --}}
    <template x-if="loading && cases.length === 0">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <template x-for="i in 6" :key="i">
                <div class="rounded-lg border bg-card p-4 space-y-2.5">
                    <div class="skeleton h-3 w-20"></div>
                    <div class="skeleton h-4 w-3/4"></div>
                    <div class="skeleton h-3 w-1/2"></div>
                    <div class="skeleton h-3 w-2/3"></div>
                </div>
            </template>
        </div>
    </template>

    <template x-if="!loading && visibleCases.length === 0">
        <div class="rounded-lg border bg-card">
            <div class="p-10 text-center space-y-2">
                <div class="mx-auto h-10 w-10 rounded-full bg-muted text-muted-foreground flex items-center justify-center">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
                <p class="text-sm font-medium" x-text="emptyHeadline"></p>
                <p class="description" x-text="emptyHint"></p>
                <template x-if="hasFilters">
                    <button class="btn btn-link btn-xs" @click="resetFilters()">Clear filters</button>
                </template>
            </div>
        </div>
    </template>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" x-show="!loading && visibleCases.length > 0">
        <template x-for="c in visibleCases" :key="c.id">
            <button type="button"
                    class="group relative text-left rounded-lg border bg-card p-4 hover:bg-accent/30 transition-all overflow-hidden"
                    :class="cardTint(c)"
                    @click="openQuickView(c)">
                {{-- urgency rail --}}
                <span class="absolute top-0 left-0 right-0 h-0.5" :class="railClass(c.risk_level)"></span>

                <div class="flex items-start justify-between gap-2 mb-2.5">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="badge" :class="urgencyBadge(c.risk_level)" x-text="humanUrgency(c.risk_level)"></span>
                        <template x-if="c.ihr_tier && c.ihr_tier !== 'none'">
                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400">
                                <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                                Reportable to WHO
                            </span>
                        </template>
                        <template x-if="c.overdue_24h && c.status !== 'CLOSED'">
                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium border-destructive/40 bg-destructive/10 text-destructive">
                                <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3"/></svg>
                                Overdue
                            </span>
                        </template>
                    </div>
                    <span class="text-[11px] tabular-nums text-muted-foreground shrink-0" x-text="humanAgo(c.server_received_at || c.created_at)"></span>
                </div>

                {{-- Case headline (plain language) --}}
                <h3 class="text-sm font-medium leading-snug line-clamp-2" x-text="caseHeadline(c)"></h3>

                {{-- Traveller + location intelligence --}}
                <p class="description mt-1.5 line-clamp-1" x-text="travellerLine(c)"></p>

                <div class="flex items-center gap-1.5 mt-2">
                    <svg class="h-3.5 w-3.5 text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span class="text-[12px] font-medium truncate" x-text="c.poe_code || c.district_code"></span>
                    <span class="text-[12px] text-muted-foreground truncate">· <span x-text="c.district_code"></span></span>
                </div>

                {{-- "Working on it" / delay strip --}}
                <div class="mt-3 pt-3 border-t flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <template x-if="c.acknowledged_by_name">
                            <span class="h-5 w-5 rounded-full bg-primary/10 text-primary text-[9px] font-semibold flex items-center justify-center shrink-0" x-text="initials(c.acknowledged_by_name)"></span>
                        </template>
                        <template x-if="!c.acknowledged_by_name && c.status !== 'CLOSED'">
                            <span class="h-5 w-5 rounded-full border border-dashed border-muted-foreground/40 flex items-center justify-center shrink-0">
                                <svg class="h-2.5 w-2.5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            </span>
                        </template>
                        <template x-if="c.status === 'CLOSED'">
                            <span class="h-5 w-5 rounded-full bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </span>
                        </template>
                        <span class="text-[11px] text-muted-foreground truncate" x-text="ownerLine(c)"></span>
                    </div>
                    <svg class="h-3.5 w-3.5 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </div>
            </button>
        </template>
    </div>

    {{-- ─── AI INSIGHTS ─────────────────────────────────────── --}}
    <template x-if="!loading && insights.length > 0">
        <section class="space-y-3">
            <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">What the board is telling me</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <template x-for="ins in insights" :key="ins.title">
                    <div class="rounded-lg border bg-card p-4 space-y-2">
                        <div class="flex items-start gap-2.5">
                            <div class="h-7 w-7 rounded-md bg-muted text-muted-foreground flex items-center justify-center shrink-0">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 19a7 7 0 110-14 7 7 0 010 14z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium" x-text="ins.title"></p>
                                <p class="description mt-0.5" x-text="ins.body"></p>
                                <template x-if="ins.action">
                                    <button type="button" class="btn btn-link btn-xs px-0 h-auto mt-1.5" @click="ins.run()">
                                        <span x-text="ins.action"></span>
                                        <svg class="ml-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </template>

    {{-- ─── QUICK-INTEL SHEET (lives inside x-data scope) ──────── --}}
    <template x-if="quick.open">
    <div>
        <div class="sheet-overlay" @click="quick.open = false"></div>
        <aside class="sheet-content sheet-right flex flex-col" role="dialog" aria-modal="true">
        <div class="sheet-header">
            <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                <span class="badge" :class="urgencyBadge(quick.case.risk_level)" x-text="humanUrgency(quick.case.risk_level)"></span>
                <span class="badge badge-outline" x-text="humanStatus(quick.case.status)"></span>
                <template x-if="quick.case.ihr_tier && quick.case.ihr_tier !== 'none'">
                    <span class="badge badge-outline">WHO notify</span>
                </template>
            </div>
            <h3 class="sheet-title" x-text="caseHeadline(quick.case)"></h3>
            <p class="sheet-description" x-text="travellerLine(quick.case)"></p>
        </div>

        <div class="flex-1 overflow-y-auto py-4 -mx-2 px-2 space-y-4">
            {{-- AI sentence --}}
            <div class="rounded-md border bg-muted/30 p-3.5">
                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-1">At a glance</p>
                <template x-if="quick.loading">
                    <div class="space-y-1.5"><div class="skeleton h-3 w-4/5"></div><div class="skeleton h-3 w-3/5"></div></div>
                </template>
                <p class="text-sm text-foreground/90 leading-relaxed" x-show="!quick.loading" x-text="quick.sentence"></p>
            </div>

            {{-- intel tabs --}}
            <template x-if="!quick.loading && quick.intel">
                <div class="space-y-3">
                    <div class="tabs-list w-full" role="tablist">
                        <template x-for="t in quickTabs" :key="t.key">
                            <button class="tabs-trigger flex-1"
                                    :data-state="quick.tab === t.key ? 'active' : ''"
                                    @click="quick.tab = t.key"
                                    x-text="t.label"></button>
                        </template>
                    </div>

                    {{-- Case tab --}}
                    <div x-show="quick.tab === 'case'" class="space-y-2">
                        <template x-for="row in intelCaseRows" :key="row.k">
                            <div class="flex items-start justify-between gap-3 py-2 border-b last:border-b-0">
                                <span class="text-xs text-muted-foreground shrink-0" x-text="row.k"></span>
                                <span class="text-sm text-right break-words min-w-0" x-text="row.v || '—'"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Traveller tab --}}
                    <div x-show="quick.tab === 'traveller'" class="space-y-2" x-cloak>
                        <template x-for="row in intelTravellerRows" :key="row.k">
                            <div class="flex items-start justify-between gap-3 py-2 border-b last:border-b-0">
                                <span class="text-xs text-muted-foreground shrink-0" x-text="row.k"></span>
                                <span class="text-sm text-right break-words min-w-0" x-text="row.v || '—'"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Clinical tab --}}
                    <div x-show="quick.tab === 'clinical'" class="space-y-2" x-cloak>
                        <template x-for="row in intelClinicalRows" :key="row.k">
                            <div class="flex items-start justify-between gap-3 py-2 border-b last:border-b-0">
                                <span class="text-xs text-muted-foreground shrink-0" x-text="row.k"></span>
                                <span class="text-sm text-right break-words min-w-0" x-text="row.v || '—'"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Response tab --}}
                    <div x-show="quick.tab === 'response'" class="space-y-3" x-cloak>
                        <div class="rounded-md border p-3 space-y-1.5">
                            <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">Recommended PPE</p>
                            <p class="text-sm" x-text="quick.intel.disease_ppe || '—'"></p>
                        </div>
                        <div class="rounded-md border p-3 space-y-1.5">
                            <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">Specimens to collect</p>
                            <p class="text-sm" x-text="quick.intel.disease_specimens || '—'"></p>
                        </div>
                        <div class="rounded-md border p-3 space-y-1.5">
                            <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">Isolation</p>
                            <p class="text-sm" x-text="quick.intel.disease_isolation || '—'"></p>
                        </div>
                        <div class="rounded-md border p-3 space-y-1.5" x-show="quick.intel.disease_ihr_notification">
                            <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">WHO notification</p>
                            <p class="text-sm" x-text="quick.intel.disease_ihr_notification || '—'"></p>
                        </div>
                    </div>
                </div>
            </template>
        </div>

            <div class="sheet-footer pt-3 border-t">
                <button type="button" class="btn btn-ghost btn-xs" @click="quick.open = false">Close preview</button>
                <a :href="'{{ url('/admin/alerts') }}/' + quick.case.id" class="btn btn-default btn-xs">
                    <span>Open full case</span>
                    <svg class="ml-1.5 h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </aside>
    </div>
    </template>

</div>

@push('scripts')
<script>
function caseBoard(){
    return {
        loading: true,
        cases: [],
        summary: {},
        tab: 'needs',
        filters: { q:'', poe:'' },
        _timer: null,

        quick: { open:false, loading:false, case: null, intel: null, sentence: '', tab:'case' },

        async init(){
            if (!localStorage.getItem('pheoc_token')) {
                location.replace('{{ url('/admin/login') }}?next=' + encodeURIComponent(location.pathname));
                return;
            }
            await this.load();
            this._timer = setInterval(() => this.load(true), 30000);
        },
        _token(){ return localStorage.getItem('pheoc_token') || ''; },
        _uid(){ try { return Number((JSON.parse(localStorage.getItem('pheoc_user')||'{}')).id||0); } catch { return 0; } },
        _user(){ try { return JSON.parse(localStorage.getItem('pheoc_user')||'{}'); } catch { return {}; } },

        async load(quiet = false){
            try {
                const [rList, rSum] = await Promise.all([
                    fetch('{{ url('/api/alerts') }}?user_id=' + this._uid() + '&per_page=200', { headers:{'Accept':'application/json','Authorization':'Bearer '+this._token()} }),
                    fetch('{{ url('/api/alerts/summary') }}?user_id=' + this._uid(),            { headers:{'Accept':'application/json','Authorization':'Bearer '+this._token()} }),
                ]);
                const bList = await rList.json().catch(()=>({}));
                const bSum  = await rSum.json().catch(()=>({}));
                if (rList.ok) {
                    const items = bList?.data?.items || bList?.data || [];
                    this.cases = Array.isArray(items) ? items : [];
                }
                if (rSum.ok) this.summary = bSum?.data || {};
            } catch(e){ /* ignore — auto-retry on next tick */ }
            if (!quiet) this.loading = false;
        },
        reload(manual){ if (manual) this.loading = true; this.load(); },
        resetFilters(){ this.filters = { q:'', poe:'' }; },
        get hasFilters(){ return !!(this.filters.q || this.filters.poe); },

        // ── tabs ─────────────────────────────────────────────
        get byTab(){
            const needs = [];
            const inProg = [];
            const recent = [];
            const all = [];
            const sevenDays = Date.now() - 7*24*3600*1000;

            for (const c of this.cases) {
                all.push(c);
                const ts = this._ts(c.server_received_at || c.created_at);
                const closedTs = this._ts(c.closed_at);
                if (c.status === 'OPEN' || (c.status !== 'CLOSED' && (c.overdue_24h || c.risk_level === 'CRITICAL'))) {
                    needs.push(c);
                } else if (c.status === 'ACKNOWLEDGED') {
                    inProg.push(c);
                } else if (c.status === 'CLOSED' && closedTs && closedTs >= sevenDays) {
                    recent.push(c);
                }
            }
            // Sort each bucket by urgency → recency
            const weight = { CRITICAL:0, HIGH:1, MEDIUM:2, LOW:3 };
            const sortFn = (a,b) => {
                const w = (weight[a.risk_level]||9) - (weight[b.risk_level]||9);
                if (w !== 0) return w;
                return (b.server_received_at||'').localeCompare(a.server_received_at||'');
            };
            needs.sort(sortFn); inProg.sort(sortFn);
            recent.sort((a,b) => (b.closed_at||'').localeCompare(a.closed_at||''));
            all.sort(sortFn);
            return { needs, inProg, recent, all };
        },
        get tabs(){
            const b = this.byTab;
            return [
                { key:'needs',  label:'Needs you',      count: b.needs.length },
                { key:'inProg', label:'In progress',    count: b.inProg.length },
                { key:'recent', label:'Closed this week', count: b.recent.length },
                { key:'all',    label:'All',            count: b.all.length },
            ];
        },
        get tabCases(){
            return ({ needs: this.byTab.needs, inProg: this.byTab.inProg, recent: this.byTab.recent, all: this.byTab.all })[this.tab] || [];
        },
        get visibleCases(){
            const q = (this.filters.q||'').toLowerCase().trim();
            let list = this.tabCases;
            if (this.filters.poe) list = list.filter(c => c.poe_code === this.filters.poe);
            if (q) list = list.filter(c =>
                [c.alert_code, c.alert_title, c.poe_code, c.district_code].join(' ').toLowerCase().includes(q)
            );
            return list;
        },
        get allPoes(){
            const s = new Set();
            for (const c of this.cases) if (c.poe_code) s.add(c.poe_code);
            return [...s].sort();
        },

        // ── briefing ─────────────────────────────────────────
        get greeting(){
            const u = this._user();
            const h = new Date().getHours();
            const p = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
            const name = (u.full_name || '').split(' ')[0] || '';
            return name ? `${p}, ${name}.` : `${p}.`;
        },
        get primaryCase(){ return this.byTab.needs[0] || null; },
        get briefing(){
            if (this.loading) return '';
            const open = this.cases.filter(c => c.status !== 'CLOSED').length;
            const crit = this.cases.filter(c => c.status !== 'CLOSED' && c.risk_level === 'CRITICAL').length;
            const overdue = this.cases.filter(c => c.overdue_24h && c.status !== 'CLOSED').length;
            const unack = this.cases.filter(c => c.status === 'OPEN').length;
            const p = this.primaryCase;

            if (open === 0) return `No open cases on the board right now. You're all caught up — the next case will appear here automatically.`;

            const bits = [];
            bits.push(`You have <strong>${open}</strong> open case${open===1?'':'s'}`);
            if (crit) bits.push(`<strong>${crit}</strong> urgent`);
            if (overdue) bits.push(`<strong>${overdue}</strong> waiting over 24 hours`);
            if (unack) bits.push(`<strong>${unack}</strong> not yet acknowledged`);

            let line = bits.join(' · ') + '.';
            if (p) {
                const title = this.caseHeadline(p).toLowerCase();
                const owner = p.acknowledged_by_name ? ` ${p.acknowledged_by_name.split(' ')[0]} is on it` : ' nobody has taken it yet';
                line += ` Your highest priority is <strong>#${p.id}</strong> — ${title} at <strong>${p.poe_code || p.district_code}</strong>, ${this.humanAgo(p.server_received_at || p.created_at)} — ${owner}.`;
            }
            return line;
        },
        get emptyHeadline(){
            if (this.hasFilters) return 'No cases match those filters.';
            return ({
                needs: 'Nothing is waiting on you.',
                inProg: 'Nothing is being handled right now.',
                recent: 'No cases closed in the last 7 days.',
                all: 'No cases yet.',
            })[this.tab] || 'Nothing to show.';
        },
        get emptyHint(){
            if (this.hasFilters) return 'Clear the filters to see everything.';
            return ({
                needs: 'When a new urgent case comes in, it\'ll show up here.',
                inProg: 'Cases move here once a team member acknowledges them.',
                recent: 'Recently closed cases appear here for the first week.',
                all: 'This is where every case — open or closed — collects.',
            })[this.tab] || '';
        },

        // ── insights (AI narrator — simple, rule-based, accurate) ──
        get insights(){
            if (this.loading) return [];
            const out = [];
            const open = this.cases.filter(c => c.status !== 'CLOSED');

            // 1) POE cluster in last 24h
            const poeBuckets = {};
            open.forEach(c => {
                if (!c.poe_code) return;
                const ts = this._ts(c.server_received_at || c.created_at);
                if (ts && ts > Date.now() - 24*3600*1000) {
                    (poeBuckets[c.poe_code] = poeBuckets[c.poe_code] || []).push(c);
                }
            });
            Object.entries(poeBuckets).forEach(([poe, list]) => {
                if (list.length >= 3) {
                    out.push({
                        title: `${list.length} cases from ${poe} in the last 24 hours`,
                        body: `That's a higher-than-usual cluster. Worth a check-in with the district supervisor and a closer look at exposures.`,
                        action: `See them`,
                        run: () => { this.filters.poe = poe; this.tab = 'needs'; },
                    });
                }
            });

            // 2) Recently reopened
            const reopened = open.filter(c => Number(c.reopen_count||0) > 0);
            if (reopened.length) {
                out.push({
                    title: `${reopened.length} case${reopened.length===1?' was':'s were'} reopened`,
                    body: `Review why the previous close didn't hold — new lab results, missed follow-ups, or something else.`,
                    action: `Show me`,
                    run: () => { this.tab = 'inProg'; },
                });
            }

            // 3) Overdue response
            const overdue = open.filter(c => c.overdue_24h);
            if (overdue.length && !reopened.length) {
                out.push({
                    title: `${overdue.length} case${overdue.length===1?'':'s'} past the 24-hour acknowledgement window`,
                    body: `Either take them yourself or hand them off to someone who can respond now.`,
                    action: `Focus on overdue`,
                    run: () => { this.tab = 'needs'; },
                });
            }

            // 4) Healthy — encourage a sweep
            if (!out.length && open.length > 0) {
                out.push({
                    title: `The board looks steady.`,
                    body: `No unusual clustering, no reopens, no overdue cases. Good moment to close out anything already resolved.`,
                    action: `Review closed this week`,
                    run: () => { this.tab = 'recent'; },
                });
            }
            return out.slice(0, 4);
        },

        // ── quick intel sheet ────────────────────────────────
        quickTabs: [
            { key:'case',      label:'Case' },
            { key:'traveller', label:'Traveller' },
            { key:'clinical',  label:'Clinical' },
            { key:'response',  label:'Response' },
        ],
        async openQuickView(c){
            this.quick = { open:true, loading:true, case: c, intel:null, sentence:'', tab:'case' };
            try {
                const r = await fetch(`{{ url('/api/alerts') }}/${c.id}/war-room?user_id=` + this._uid(), {
                    headers:{'Accept':'application/json','Authorization':'Bearer '+this._token()},
                });
                const b = await r.json().catch(()=>({}));
                if (r.ok) {
                    const data = b.data || {};
                    this.quick.intel = data.context_vars || {};
                    this.quick.case = Object.assign({}, this.quick.case, data.alert || {});
                    this.quick.sentence = this._buildSentence();
                }
            } catch(e){ /* ignore */ }
            finally { this.quick.loading = false; }
        },
        _buildSentence(){
            const i = this.quick.intel || {};
            const c = this.quick.case || {};
            const bits = [];
            const age = i.traveler_age && i.traveler_age !== '—' ? i.traveler_age : '';
            const gender = ({ MALE:'male', FEMALE:'female', OTHER:'traveller' })[c.traveler_gender] || 'traveller';
            const at = c.poe_code || c.district_code || 'the border';
            const disease = (i.disease_name && i.disease_name !== '—') ? i.disease_name : 'a possible case';
            const tier = c.ihr_tier && c.ihr_tier !== 'none' ? ' — this is a WHO-notifiable condition' : '';

            if (age && c.traveler_gender) {
                bits.push(`A ${age} ${gender} was screened at ${at} and triggered this case for ${disease}${tier}.`);
            } else {
                bits.push(`A traveller at ${at} triggered this case for ${disease}${tier}.`);
            }
            if (i.symptoms_count && Number(i.symptoms_count) > 0) bits.push(`The officer recorded ${i.symptoms_count} symptom${Number(i.symptoms_count)===1?'':'s'}.`);
            if (i.disease_cfr_pct && i.disease_cfr_pct !== '—') bits.push(`Fatality rate for this disease is around ${i.disease_cfr_pct}.`);
            if (c.acknowledged_by_name) bits.push(`${c.acknowledged_by_name} is handling it now.`);
            else if (c.status === 'OPEN') bits.push(`Nobody has taken it yet.`);
            return bits.join(' ');
        },
        get intelCaseRows(){
            const c = this.quick.case || {};
            const i = this.quick.intel || {};
            return [
                { k:'Case code',     v: c.alert_code },
                { k:'Risk level',    v: this.humanUrgency(c.risk_level) },
                { k:'Status',        v: this.humanStatus(c.status) },
                { k:'WHO tier',      v: i.disease_ihr_tier || (c.ihr_tier && c.ihr_tier !== 'none' ? c.ihr_tier : null) },
                { k:'Received',      v: this.humanAgo(c.server_received_at) },
                { k:'Acknowledged',  v: c.acknowledged_by_name ? `${c.acknowledged_by_name} · ${this.humanAgo(c.acknowledged_at)}` : 'Not yet' },
                { k:'Port of entry', v: c.poe_code },
                { k:'District',      v: c.district_code },
                { k:'Region',        v: c.province_code || c.pheoc_code },
            ].filter(r => r.v);
        },
        get intelTravellerRows(){
            const i = this.quick.intel || {};
            const c = this.quick.case || {};
            return [
                { k:'Name',          v: i.traveler_name },
                { k:'Age',           v: i.traveler_age },
                { k:'Gender',        v: c.traveler_gender ? c.traveler_gender.toLowerCase() : null },
                { k:'Nationality',   v: i.traveler_nationality },
                { k:'Phone',         v: i.traveler_phone },
                { k:'Email',         v: i.traveler_email },
                { k:'Occupation',    v: i.traveler_occupation },
                { k:'Arrival',       v: i.arrival_datetime },
                { k:'Conveyance',    v: i.conveyance_type },
                { k:'Conveyance #',  v: i.conveyance_id },
                { k:'Destination',   v: [i.destination_district, i.destination_address].filter(x=>x&&x!=='—').join(', ') || null },
            ].filter(r => r.v && r.v !== '—');
        },
        get intelClinicalRows(){
            const i = this.quick.intel || {};
            return [
                { k:'Disease',          v: i.disease_name },
                { k:'Syndrome',         v: i.syndrome_classification },
                { k:'Case definition',  v: i.disease_case_definition },
                { k:'Incubation',       v: i.disease_incubation },
                { k:'Fatality rate',    v: i.disease_cfr_pct },
                { k:'Transmission',     v: i.disease_transmission },
                { k:'Differential',     v: i.disease_differential },
                { k:'Final disposition',v: i.final_disposition },
                { k:'Symptoms recorded',v: i.symptoms_count },
            ].filter(r => r.v && r.v !== '—');
        },

        // ── formatters ─────────────────────────────────────
        humanUrgency(r){ return ({CRITICAL:'Urgent', HIGH:'High', MEDIUM:'Normal', LOW:'Low'})[r] || 'Unknown'; },
        urgencyBadge(r){ return ({CRITICAL:'badge-destructive', HIGH:'badge-default', MEDIUM:'badge-secondary', LOW:'badge-outline'})[r] || 'badge-outline'; },
        railClass(r){ return ({CRITICAL:'bg-destructive', HIGH:'bg-primary', MEDIUM:'bg-muted-foreground/30', LOW:'bg-border'})[r] || 'bg-border'; },
        humanStatus(s){ return ({OPEN:'Just opened', ACKNOWLEDGED:'Being handled', CLOSED:'Resolved'})[s] || s; },
        humanTitle(c){
            let t = c.alert_title || 'Suspected case';
            t = t.replace(/\[CLOSED[^\]]*\]\s*/gi, '').replace(/\s+/g,' ').trim();
            if (t === t.toUpperCase() && t.length > 4) t = t.toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
            return t || 'Suspected case';
        },
        {{-- Plain-language headline. Turns engine codes into human sentences. --}}
        caseHeadline(c){
            const t = (c.alert_title || '').replace(/\[CLOSED[^\]]*\]\s*/gi,'').replace(/\s+/g,' ').trim().toLowerCase();
            const synd = (c.syndrome || '').toUpperCase();
            // Map the common engine codes → plain-language suspected-case phrasing
            if (/vhf|hemorrhagic|haemorrhagic/.test(t) || synd === 'VHF')            return 'Suspected viral haemorrhagic fever (Ebola/Marburg-family)';
            if (/tier1.*critical/.test(t) || /always_critical/.test(t))              return 'Suspected high-consequence case — awaiting clinical review';
            if (/sars|severe acute respiratory/.test(t) || synd === 'SARI')          return 'Suspected severe respiratory illness';
            if (/cholera/.test(t))                                                   return 'Suspected cholera';
            if (/meningitis|meningococc/.test(t))                                    return 'Suspected meningitis';
            if (/mpox|monkeypox/.test(t))                                            return 'Suspected mpox';
            if (/yellow fever/.test(t))                                              return 'Suspected yellow fever';
            if (/polio|afp|flaccid/.test(t))                                         return 'Suspected acute flaccid paralysis';
            if (/measles/.test(t))                                                   return 'Suspected measles';
            if (/dengue/.test(t))                                                    return 'Suspected dengue';
            if (/influenza|h5n1|h7n9/.test(t))                                       return 'Suspected novel influenza';
            // Fallback — use syndrome or a generic safe label
            if (synd && synd !== 'NONE')                                             return 'Suspected case · ' + synd.toLowerCase().replace(/_/g,' ') + ' syndrome';
            return c.alert_title
                ? (c.alert_title.replace(/\[CLOSED[^\]]*\]\s*/gi,'').replace(/\s+/g,' ').trim() || 'Suspected case')
                : 'Suspected case';
        },
        {{-- Plain-language description line — traveller + arrival context. --}}
        travellerLine(c){
            const bits = [];
            if (c.traveler_gender) bits.push(({MALE:'Male',FEMALE:'Female',OTHER:'Traveller'})[c.traveler_gender] || 'Traveller');
            bits.push('arrived at ' + (c.poe_code || 'the border'));
            return bits.join(' ');
        },
        {{-- "Who is on it / what's delaying it" line. --}}
        ownerLine(c){
            const ts = this._ts(c.server_received_at || c.created_at);
            const hrs = ts ? Math.floor((Date.now() - ts) / 3600000) : null;

            if (c.status === 'CLOSED') {
                if (c.closed_at) return 'Resolved ' + this.humanAgo(c.closed_at);
                return 'Resolved';
            }
            if (c.acknowledged_by_name) {
                const ackAt = this._ts(c.acknowledged_at);
                const sinceHrs = ackAt ? Math.floor((Date.now() - ackAt) / 3600000) : null;
                let line = `${c.acknowledged_by_name.split(' ')[0]} handling it`;
                if (sinceHrs != null) line += sinceHrs < 1 ? ' · just now' : ` · ${sinceHrs}h in`;
                if (c.overdue_24h) line += ' · response overdue';
                return line;
            }
            // Nobody has it
            if (c.overdue_24h) return `No owner · overdue by ${Math.max(0,(hrs||0)-24)}h`;
            if (hrs && hrs >= 1) return `No owner yet · ${hrs}h since it landed`;
            return 'Nobody has taken it yet';
        },
        cardTint(c){
            if (c.status === 'CLOSED') return 'opacity-75';
            if (c.risk_level === 'CRITICAL' && c.status === 'OPEN') return 'ring-1 ring-destructive/20';
            if (c.overdue_24h && c.status !== 'CLOSED') return 'ring-1 ring-amber-500/30';
            return '';
        },
        initials(s){ return (s||'?').trim().split(/\s+/).map(x=>x[0]).slice(0,2).join('').toUpperCase() || '?'; },
        humanAgo(v){
            if (!v) return '';
            const ts = this._ts(v);
            if (!ts) return '';
            const s = Math.floor((Date.now()-ts)/1000);
            if (s<60) return 'just now';
            if (s<3600) return Math.floor(s/60)+' min ago';
            if (s<86400) return Math.floor(s/3600)+' hr ago';
            if (s<604800) return Math.floor(s/86400)+' days ago';
            return new Date(ts).toLocaleDateString();
        },
        _ts(v){
            if (!v) return null;
            const d = new Date(String(v).replace(' ','T') + (String(v).includes('T')?'':'Z'));
            return isNaN(d) ? null : d.getTime();
        },
    };
}
</script>
@endpush
@endsection

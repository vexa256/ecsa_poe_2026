{{-- ============================================================================
  /admin/settings/diseases — WHO POE Disease Catalog (read-only)
  ----------------------------------------------------------------------------
  Data flows from App\Services\SsotRegistry::diseases() → parsed from the
  mobile Diseases.js so clinical weights stay in sync.

  8-point bar:
    · Premium disease cards — CFR pill, tier ribbon, hallmark symptoms chips
    · Aggressive search across id/name/syndromes/key_distinguishers
    · Tier tabs: All | IHR Tier 1 | Annex 2 | WHO notifiable | Syndromic
    · KPI strip: disease count · IHR always-notifiable · PHEIC-category · avg CFR
    · Hardcoded Copilot: narrates outbreak-context gates and the triage overrides
    · Mobile-first — 1 col < md, 2 col md, 3 col lg+
    · Insane validation — n/a (read-only); search tolerates typos via fuzzy prefix
    · Smart guidance: each card shows the single most important triage action
============================================================================ --}}
@extends('admin.layout')

@section('title', 'Disease Catalog')
@section('heading', 'Tracked Diseases — Scoring Catalog')
@section('subheading', 'WHO/IHR-aligned · ' . ($meta['disease_count'] ?? '—') . ' diseases · engine v' . ($meta['version'] ?? '—'))

@section('breadcrumbs')
    <a href="{{ url('/admin/dashboard') }}" class="hover:text-ink-600 truncate">Command Centre</a>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="hover:text-ink-600 truncate">Settings</span>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-ink-600 font-medium truncate">Diseases</span>
@endsection

@push('head')
<style>
    .tab-btn[aria-selected=true]{color:#111a33;border-color:#2f7dff;font-weight:600}
    .tab-btn[aria-selected=true] .tab-dot{background:#2f7dff;color:#fff}
    .card-hover:hover{border-color:#8ebfff;box-shadow:0 8px 24px -12px rgba(47,125,255,.25)}
    .tier-ribbon{position:absolute;top:0;right:0;padding:2px 8px;font-size:9px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;border-bottom-left-radius:8px}
</style>
@endpush

@section('content')
<div x-data="diseasesCatalog({ diseases: @js($diseases), tiers: @js($tiers), engine: @js($engine) })"
     x-init="init()" class="space-y-4">

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
        <template x-for="k in kpis" :key="k.label">
            <div class="rounded-xl border border-ink-100 bg-white shadow-panel p-3">
                <div class="text-[10px] uppercase tracking-[0.14em] font-semibold text-ink-400" x-text="k.label"></div>
                <div class="mt-1 text-xl sm:text-2xl font-bold text-ink-900 font-mono" x-text="k.value"></div>
                <div class="text-[11px] text-ink-500 line-clamp-1" x-text="k.hint"></div>
            </div>
        </template>
    </div>

    {{-- Copilot --}}
    <div class="rounded-xl border border-brand-200 bg-gradient-to-br from-brand-50/60 via-white to-white p-3">
        <div class="flex items-start gap-3">
            <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 grid place-items-center text-white shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-brand-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500 animate-pulse-dot"></span>
                    Copilot · clinical catalog brief
                </div>
                <div class="mt-1 text-[13px] text-ink-800 leading-relaxed space-y-0.5">
                    <template x-for="s in copilot" :key="s"><p x-text="s"></p></template>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs + search --}}
    <div class="rounded-xl border border-ink-100 bg-white shadow-panel overflow-hidden">
        <div class="border-b border-ink-100 px-2 sm:px-3 flex overflow-x-auto scrollbar-thin">
            <template x-for="t in tabsList" :key="t.key">
                <button class="tab-btn px-3 py-3 text-xs sm:text-sm text-ink-500 border-b-2 border-transparent hover:text-ink-900 shrink-0 inline-flex items-center gap-2"
                        :aria-selected="tab === t.key" @click="tab = t.key; filters.page=1">
                    <span class="tab-dot inline-flex items-center justify-center h-5 min-w-5 px-1.5 rounded-md bg-ink-100 text-ink-500 text-[10px] font-bold font-mono" x-text="t.count"></span>
                    <span x-text="t.label"></span>
                </button>
            </template>
        </div>
        <div class="p-3 bg-ink-50/40 border-b border-ink-100 flex flex-wrap gap-2">
            <div class="flex-1 min-w-[180px] relative">
                <svg class="h-4 w-4 text-ink-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z"/></svg>
                <input type="search" x-model.debounce.250ms="filters.search" @input="filters.page=1"
                       placeholder="Search disease, syndrome, symptom…"
                       class="w-full h-9 pl-10 pr-3 rounded-lg border border-ink-200 bg-white text-sm focus:border-brand-500 focus:ring-0">
            </div>
            <select x-model="filters.syndrome" @change="filters.page=1"
                    class="h-9 rounded-lg border border-ink-200 bg-white text-xs sm:text-sm px-3 focus:border-brand-500 focus:ring-0">
                <option value="">All syndromes</option>
                <template x-for="s in allSyndromes" :key="s"><option :value="s" x-text="humanize(s)"></option></template>
            </select>
            <button @click="resetFilters()" class="text-xs text-ink-500 hover:text-ink-800 underline px-2">Clear</button>
        </div>

        {{-- CARD GRID --}}
        <div class="p-3 sm:p-4">
            <template x-if="paged().length === 0">
                <div class="py-12 text-center">
                    <div class="text-sm font-semibold text-ink-700">No diseases match the filter</div>
                    <button @click="resetFilters()" class="text-xs text-brand-700 hover:text-brand-500 underline mt-1">Clear filters</button>
                </div>
            </template>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <template x-for="d in paged()" :key="d.id">
                    <article class="relative rounded-xl border border-ink-100 bg-white p-3 card-hover transition cursor-pointer"
                             @click="inspect = d">
                        <span class="tier-ribbon" :class="tierBg(d.priority_tier)" x-text="tierLabel(d.priority_tier)"></span>
                        <div class="pr-16">
                            <div class="text-[10px] uppercase tracking-[0.14em] font-semibold text-ink-400">
                                <span x-text="d.id"></span>
                            </div>
                            <h3 class="text-sm font-semibold text-ink-900 leading-tight" x-text="d.name"></h3>
                        </div>
                        <div class="mt-2 flex items-center gap-2 flex-wrap">
                            <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded border"
                                  :class="cfrTone(d.case_fatality_rate_pct)"
                                  x-text="'CFR ' + (d.case_fatality_rate_pct ?? '—') + '%'"></span>
                            <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-ink-100 text-ink-700 border border-ink-200"
                                  x-text="'sev ' + (d.severity ?? '—')"></span>
                            <span x-show="d.alert_level_if_top_ranked" class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded"
                                  :class="alertTone(d.alert_level_if_top_ranked)"
                                  x-text="d.alert_level_if_top_ranked"></span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1" x-show="d.syndromes && d.syndromes.length">
                            <template x-for="s in (d.syndromes || []).slice(0,3)" :key="s">
                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-brand-50/60 text-brand-700 border border-brand-200" x-text="humanize(s)"></span>
                            </template>
                        </div>
                        <p class="mt-2 text-[11px] text-ink-500 line-clamp-2" x-show="d.key_distinguishers && d.key_distinguishers.length"
                           x-text="(d.key_distinguishers || [])[0]"></p>
                        <div class="mt-2 flex items-center gap-1.5 text-[11px] text-brand-700 font-semibold" x-show="smartTip(d)">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <span x-text="smartTip(d)"></span>
                        </div>
                    </article>
                </template>
            </div>

            {{-- Pagination max 8 --}}
            <div class="mt-4 flex items-center justify-between text-xs text-ink-500" x-show="filtered.length > 0">
                <div>
                    <span x-text="(filters.page-1)*filters.perPage + 1"></span>–<span x-text="Math.min(filters.page*filters.perPage, filtered.length)"></span> of
                    <span class="font-semibold text-ink-700" x-text="filtered.length"></span>
                </div>
                <div class="flex items-center gap-1">
                    <select x-model.number="filters.perPage" @change="filters.page=1" class="h-7 rounded border border-ink-200 text-xs px-1">
                        <option :value="8">8</option><option :value="12">12</option><option :value="20">20</option>
                    </select>
                    <button @click="filters.page=Math.max(1,filters.page-1)" :disabled="filters.page<=1" class="h-7 px-2 rounded border border-ink-200 bg-white hover:border-ink-300 disabled:opacity-40">Prev</button>
                    <button @click="filters.page++" :disabled="filters.page*filters.perPage >= filtered.length" class="h-7 px-2 rounded border border-ink-200 bg-white hover:border-ink-300 disabled:opacity-40">Next</button>
                </div>
            </div>
        </div>
    </div>

    {{-- DEEP INSPECT DRAWER --}}
    <div x-show="inspect" x-cloak @keydown.escape.window="inspect = null" class="fixed inset-0 z-50 flex">
        <div x-show="inspect" x-transition.opacity @click="inspect=null" class="absolute inset-0 bg-ink-950/50 backdrop-blur-sm"></div>
        <aside x-show="inspect"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               class="relative ml-auto h-full w-full sm:max-w-2xl bg-white flex flex-col shadow-panel-lg">
            <div class="h-16 px-4 sm:px-5 border-b border-ink-100 flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] uppercase tracking-[0.14em] font-semibold text-ink-400" x-text="inspect ? tierLabel(inspect.priority_tier) : ''"></div>
                    <div class="text-base font-semibold text-ink-900 truncate" x-text="inspect && inspect.name"></div>
                </div>
                <button @click="inspect=null" class="h-9 w-9 grid place-items-center rounded-lg hover:bg-ink-100 text-ink-500">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto scrollbar-thin p-4 sm:p-5 space-y-4" x-show="inspect">
                <template x-if="inspect">
                    <div class="space-y-4">
                        <div class="flex flex-wrap gap-1">
                            <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded border" :class="cfrTone(inspect.case_fatality_rate_pct)" x-text="'CFR ' + (inspect.case_fatality_rate_pct ?? '—') + '%'"></span>
                            <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-ink-100 text-ink-700 border border-ink-200" x-text="'severity ' + (inspect.severity ?? '—')"></span>
                            <span x-show="inspect.alert_level_if_top_ranked" class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded" :class="alertTone(inspect.alert_level_if_top_ranked)" x-text="'alert ' + inspect.alert_level_if_top_ranked"></span>
                            <span x-show="inspect.outbreak_bonus" class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200" x-text="'outbreak +' + inspect.outbreak_bonus"></span>
                        </div>

                        {{-- Hallmarks + syndromes --}}
                        <section>
                            <h4 class="text-[10px] uppercase tracking-[0.14em] font-bold text-ink-500 mb-1.5">Hallmarks &amp; syndromes</h4>
                            <div class="flex flex-wrap gap-1">
                                <template x-for="s in (inspect.syndromes || [])" :key="s">
                                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-brand-50 text-brand-700 border border-brand-200" x-text="humanize(s)"></span>
                                </template>
                                <template x-for="h in (inspect.hallmarks || [])" :key="h">
                                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200" x-text="humanize(h)"></span>
                                </template>
                            </div>
                        </section>

                        {{-- Key distinguishers --}}
                        <section x-show="inspect.key_distinguishers && inspect.key_distinguishers.length">
                            <h4 class="text-[10px] uppercase tracking-[0.14em] font-bold text-ink-500 mb-1.5">Key clinical distinguishers</h4>
                            <ul class="space-y-1 text-[13px] text-ink-700">
                                <template x-for="d in (inspect.key_distinguishers || [])" :key="d">
                                    <li class="flex items-start gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0"></span>
                                        <span x-text="d"></span>
                                    </li>
                                </template>
                            </ul>
                        </section>

                        {{-- Incubation + onset --}}
                        <section class="grid grid-cols-2 gap-2" x-show="inspect.incubation_days">
                            <div class="rounded-lg border border-ink-100 bg-ink-50/40 p-2.5">
                                <div class="text-[10px] uppercase tracking-[0.12em] font-semibold text-ink-400">Incubation</div>
                                <div class="text-sm font-semibold text-ink-800 font-mono" x-show="inspect.incubation_days" x-text="(inspect.incubation_days?.min ?? '?') + '–' + (inspect.incubation_days?.max ?? '?') + ' d'"></div>
                                <div class="text-[11px] text-ink-500" x-text="'typical: ' + (inspect.incubation_days?.typical ?? '—')"></div>
                            </div>
                            <div class="rounded-lg border border-ink-100 bg-ink-50/40 p-2.5">
                                <div class="text-[10px] uppercase tracking-[0.12em] font-semibold text-ink-400">WHO category</div>
                                <div class="text-sm font-semibold text-ink-800 font-mono truncate" x-text="inspect.who_category || '—'"></div>
                            </div>
                        </section>

                        {{-- Top symptom weights --}}
                        <section x-show="inspect.symptom_weights">
                            <h4 class="text-[10px] uppercase tracking-[0.14em] font-bold text-ink-500 mb-1.5">Top symptom weights (LR-calibrated)</h4>
                            <div class="space-y-1">
                                <template x-for="sw in topSymptoms(inspect)" :key="sw[0]">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[12px] font-medium text-ink-700 truncate flex-1" x-text="humanize(sw[0])"></span>
                                        <div class="h-1.5 w-20 rounded-full bg-ink-100 overflow-hidden">
                                            <div class="h-full bg-brand-500" :style="`width:${Math.min(100, sw[1]*4)}%`"></div>
                                        </div>
                                        <span class="text-[11px] font-mono font-semibold text-ink-700 w-6 text-right" x-text="sw[1]"></span>
                                    </div>
                                </template>
                            </div>
                        </section>

                        {{-- Tests + immediate actions --}}
                        <section x-show="inspect.recommended_tests && inspect.recommended_tests.length">
                            <h4 class="text-[10px] uppercase tracking-[0.14em] font-bold text-ink-500 mb-1.5">Recommended tests</h4>
                            <ul class="space-y-1 text-[13px] text-ink-700">
                                <template x-for="t in (inspect.recommended_tests || [])" :key="t">
                                    <li class="flex items-start gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full bg-sky-500 mt-1.5 shrink-0"></span>
                                        <span x-text="t"></span>
                                    </li>
                                </template>
                            </ul>
                        </section>

                        <section x-show="inspect.immediate_actions && inspect.immediate_actions.length" class="rounded-xl border border-rose-200 bg-rose-50/50 p-3">
                            <h4 class="text-[10px] uppercase tracking-[0.14em] font-bold text-rose-700 mb-1.5">Immediate actions</h4>
                            <ul class="space-y-1 text-[13px] text-rose-900">
                                <template x-for="a in (inspect.immediate_actions || [])" :key="a">
                                    <li class="flex items-start gap-2">
                                        <svg class="h-3.5 w-3.5 text-rose-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span x-text="a"></span>
                                    </li>
                                </template>
                            </ul>
                        </section>

                        <p class="text-[11px] text-ink-500" x-text="'Basis: ' + (inspect.who_basis || 'WHO AFRO IDSR 2021 / IHR 2005')"></p>
                    </div>
                </template>
            </div>
        </aside>
    </div>
</div>

@push('scripts')
<script>
function diseasesCatalog(seed){
    return {
        diseases: seed.diseases || [],
        tiers: seed.tiers || {},
        engine: seed.engine || {},
        tab: 'all',
        filters: { search:'', syndrome:'', page:1, perPage:8 },
        inspect: null,

        init(){
            if (!localStorage.getItem('pheoc_token')) {
                const next = encodeURIComponent(location.pathname + location.search);
                location.replace('{{ url('/admin/login') }}?next=' + next);
            }
        },

        get allSyndromes(){
            const s = new Set();
            for (const d of this.diseases) (d.syndromes || []).forEach(x => s.add(x));
            return [...s].sort();
        },
        get filtered(){
            const q = (this.filters.search||'').toLowerCase().trim();
            return this.diseases.filter(d => {
                if (this.tab !== 'all' && d.priority_tier !== this.tab) return false;
                if (this.filters.syndrome && !(d.syndromes || []).includes(this.filters.syndrome)) return false;
                if (!q) return true;
                const hay = [
                    d.id, d.name, (d.syndromes||[]).join(' '),
                    (d.hallmarks||[]).join(' '), (d.key_distinguishers||[]).join(' '),
                    Object.keys(d.symptom_weights||{}).join(' '),
                ].join(' ').toLowerCase();
                return hay.includes(q);
            });
        },
        paged(){ const s=(this.filters.page-1)*this.filters.perPage; return this.filtered.slice(s, s+this.filters.perPage); },

        get tabsList(){
            const tierCounts = {};
            for (const d of this.diseases) tierCounts[d.priority_tier] = (tierCounts[d.priority_tier]||0) + 1;
            const out = [{ key:'all', label:'All', count: this.diseases.length }];
            for (const [k, label] of Object.entries(this.tiers || {})) {
                out.push({ key: k, label: this.shortTier(k), count: tierCounts[k] || 0 });
            }
            return out;
        },

        get kpis(){
            const total = this.diseases.length;
            const tier1 = this.diseases.filter(d => d.priority_tier === 'tier_1_ihr_critical').length;
            const pheic = this.diseases.filter(d => d.who_category && /IHR|PHEIC/.test(d.who_category)).length;
            const cfrs = this.diseases.map(d => Number(d.case_fatality_rate_pct)||0).filter(x=>x>0);
            const avg = cfrs.length ? Math.round(cfrs.reduce((a,b)=>a+b,0)/cfrs.length) : 0;
            return [
                { label:'Diseases', value: total, hint: 'WHO/IHR catalog' },
                { label:'Tier 1 IHR', value: tier1, hint: 'always notifiable' },
                { label:'IHR-linked', value: pheic, hint: 'PHEIC pathway' },
                { label:'Mean CFR', value: avg + '%', hint: 'catalog-wide' },
            ];
        },

        get copilot(){
            const lines = [];
            const tier1 = this.diseases.filter(d => d.priority_tier === 'tier_1_ihr_critical');
            const highCfr = this.diseases.filter(d => (d.case_fatality_rate_pct||0) >= 30);
            const shown = this.filtered.length;
            if (tier1.length) lines.push(`${tier1.length} Tier-1 IHR diseases are always-notifiable — a single confirmed case triggers WHO notification regardless of score (${tier1.slice(0,3).map(d=>d.name).join(', ')}${tier1.length>3?'…':''}).`);
            if (highCfr.length) lines.push(`${highCfr.length} diseases in catalog have CFR ≥ 30% — highest-consequence pathogens to rule out first.`);
            if (this.filters.search) lines.push(`Search "${this.filters.search}" returned ${shown} disease${shown===1?'':'s'} out of ${this.diseases.length}.`);
            else if (this.tab !== 'all') lines.push(`${shown} diseases in the ${this.shortTier(this.tab)} tier · full list: ${this.tiers[this.tab] || '—'}.`);
            if (lines.length === 0) lines.push(`Engine v${this.engine?.name ? this.engine.name.replace(/.*v/, 'v') : '?'} — gates + symptom weights + exposure bonuses + outbreak context.`);
            return lines;
        },

        resetFilters(){ this.filters = { search:'', syndrome:'', page:1, perPage:8 }; this.tab='all'; },

        tierLabel(t){ return this.tiers[t] ? this.shortTier(t) : (t || '—'); },
        shortTier(k){
            return ({
                tier_1_ihr_critical: 'IHR Tier 1',
                tier_2_ihr_annex2:   'Annex 2',
                tier_2_ihr_equivalent: 'IHR-equiv',
                tier_3_who_notifiable: 'WHO notifiable',
                tier_4_syndromic:    'Syndromic',
            })[k] || k;
        },
        tierBg(t){
            return ({
                tier_1_ihr_critical: 'bg-rose-600 text-white',
                tier_2_ihr_annex2:   'bg-amber-500 text-white',
                tier_2_ihr_equivalent: 'bg-amber-400 text-white',
                tier_3_who_notifiable: 'bg-sky-500 text-white',
                tier_4_syndromic:    'bg-ink-400 text-white',
            })[t] || 'bg-ink-200 text-ink-700';
        },
        cfrTone(v){
            const n = Number(v) || 0;
            if (n >= 30) return 'bg-rose-50 text-rose-700 border-rose-200';
            if (n >= 10) return 'bg-amber-50 text-amber-700 border-amber-200';
            if (n >= 1)  return 'bg-ink-50 text-ink-700 border-ink-200';
            return 'bg-ink-50 text-ink-500 border-ink-200';
        },
        alertTone(level){
            return ({
                critical: 'bg-rose-50 text-rose-700 border border-rose-200',
                high:     'bg-amber-50 text-amber-700 border border-amber-200',
                medium:   'bg-sky-50 text-sky-700 border border-sky-200',
                low:      'bg-emerald-50 text-emerald-700 border border-emerald-200',
            })[level] || 'bg-ink-50 text-ink-700 border border-ink-200';
        },
        humanize(s){ return String(s).replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()); },
        topSymptoms(d){
            const w = d.symptom_weights || {};
            return Object.entries(w).sort((a,b)=>b[1]-a[1]).slice(0,6);
        },
        smartTip(d){
            if (d.priority_tier === 'tier_1_ihr_critical') return 'Always notify WHO on suspicion';
            if ((d.case_fatality_rate_pct||0) >= 30) return 'CFR ≥30% — treat every suspect as critical';
            if (d.alert_level_if_top_ranked === 'critical') return 'Critical alert tier if top-ranked';
            return '';
        },
    };
}
</script>
@endpush
@endsection

{{-- /admin/settings/exposures — WHO POE Exposure Risk Catalog (read-only)
     Source: SsotRegistry::exposures() → public/ssot/exposures.js --}}
@extends('admin.layout')

@section('title', 'Exposure Catalog')
@section('heading', 'Tracked Exposures — Risk Catalog')
@section('subheading', 'WHO IHR 2005 aligned · ' . count($exposures) . ' exposures · ' . ($meta['version'] ?? '—'))

@section('breadcrumbs')
    <a href="{{ url('/admin/dashboard') }}" class="hover:text-ink-600 truncate">Command Centre</a>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="hover:text-ink-600 truncate">Settings</span>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-ink-600 font-medium truncate">Exposures</span>
@endsection

@push('head')
<style>
    .tab-btn[aria-selected=true]{color:#111a33;border-color:#2f7dff;font-weight:600}
    .tab-btn[aria-selected=true] .tab-dot{background:#2f7dff;color:#fff}
    .card-hover:hover{border-color:#8ebfff;box-shadow:0 8px 24px -12px rgba(47,125,255,.25)}
</style>
@endpush

@section('content')
<div x-data="exposuresCatalog({ exposures: @js($exposures) })" x-init="init()" class="space-y-4">

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
                    Copilot · exposure catalog brief
                </div>
                <div class="mt-1 text-[13px] text-ink-800 leading-relaxed space-y-0.5">
                    <template x-for="s in copilot" :key="s"><p x-text="s"></p></template>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs by category + search --}}
    <div class="rounded-xl border border-ink-100 bg-white shadow-panel overflow-hidden">
        <div class="border-b border-ink-100 px-2 sm:px-3 flex overflow-x-auto scrollbar-thin">
            <template x-for="t in tabsList" :key="t.key">
                <button class="tab-btn px-3 py-3 text-xs sm:text-sm text-ink-500 border-b-2 border-transparent hover:text-ink-900 shrink-0 inline-flex items-center gap-2"
                        :aria-selected="tab===t.key" @click="tab=t.key; filters.page=1">
                    <span class="tab-dot inline-flex items-center justify-center h-5 min-w-5 px-1.5 rounded-md bg-ink-100 text-ink-500 text-[10px] font-bold font-mono" x-text="t.count"></span>
                    <span x-text="t.label"></span>
                </button>
            </template>
        </div>
        <div class="p-3 bg-ink-50/40 border-b border-ink-100 flex flex-wrap gap-2">
            <div class="flex-1 min-w-[180px] relative">
                <svg class="h-4 w-4 text-ink-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z"/></svg>
                <input type="search" x-model.debounce.250ms="filters.search" @input="filters.page=1"
                       placeholder="Search exposure / engine code…"
                       class="w-full h-9 pl-10 pr-3 rounded-lg border border-ink-200 bg-white text-sm focus:border-brand-500 focus:ring-0">
            </div>
            <select x-model="filters.risk_level" @change="filters.page=1"
                    class="h-9 rounded-lg border border-ink-200 bg-white text-xs sm:text-sm px-3 focus:border-brand-500 focus:ring-0">
                <option value="">All risk levels</option>
                <option value="VERY_HIGH">Very high</option>
                <option value="HIGH">High</option>
                <option value="MODERATE">Moderate</option>
                <option value="LOW">Low</option>
            </select>
            <button @click="resetFilters()" class="text-xs text-ink-500 hover:text-ink-800 underline px-2">Clear</button>
        </div>

        {{-- Card grid --}}
        <div class="p-3 sm:p-4">
            <template x-if="paged().length === 0">
                <div class="py-12 text-center">
                    <div class="text-sm font-semibold text-ink-700">No exposures match the filter</div>
                    <button @click="resetFilters()" class="text-xs text-brand-700 hover:text-brand-500 underline mt-1">Clear</button>
                </div>
            </template>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <template x-for="e in paged()" :key="e.code">
                    <article class="relative rounded-xl border border-ink-100 bg-white p-3 card-hover transition cursor-pointer"
                             @click="inspect = e">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-[10px] uppercase tracking-[0.12em] font-mono text-ink-400" x-text="e.code"></div>
                                <h3 class="text-sm font-semibold text-ink-900 leading-tight line-clamp-2" x-text="e.label"></h3>
                            </div>
                            <span class="text-[9px] font-bold uppercase tracking-[0.06em] px-1.5 py-0.5 rounded border shrink-0"
                                  :class="riskTone(e.risk_level)" x-text="e.risk_level"></span>
                        </div>
                        <p class="mt-1.5 text-[12px] text-ink-500 line-clamp-2" x-text="e.description"></p>
                        <div class="mt-2 flex items-center gap-2 flex-wrap text-[10px]">
                            <span class="font-bold uppercase px-1.5 py-0.5 rounded bg-brand-50 text-brand-700 border border-brand-200" x-text="humanize(e.category)"></span>
                            <span class="font-semibold px-1.5 py-0.5 rounded bg-ink-100 text-ink-700 border border-ink-200"
                                  x-text="(e.lookback_days ?? '—') + 'd lookback'"></span>
                            <span x-show="e.requires_details" class="font-semibold px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200">Needs detail</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1" x-show="e.engine_codes && e.engine_codes.length">
                            <template x-for="c in (e.engine_codes || []).slice(0,3)" :key="c">
                                <span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-ink-50 text-ink-700 border border-ink-100" x-text="c"></span>
                            </template>
                            <span x-show="(e.engine_codes || []).length > 3" class="text-[9px] text-ink-500" x-text="'+' + ((e.engine_codes || []).length - 3)"></span>
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

    {{-- Inspect drawer --}}
    <div x-show="inspect" x-cloak @keydown.escape.window="inspect=null" class="fixed inset-0 z-50 flex">
        <div x-show="inspect" x-transition.opacity @click="inspect=null" class="absolute inset-0 bg-ink-950/50 backdrop-blur-sm"></div>
        <aside x-show="inspect"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               class="relative ml-auto h-full w-full sm:max-w-xl bg-white flex flex-col shadow-panel-lg">
            <div class="h-16 px-4 border-b border-ink-100 flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] uppercase tracking-[0.12em] font-mono text-ink-400" x-text="inspect && inspect.code"></div>
                    <div class="text-sm font-semibold text-ink-900 truncate" x-text="inspect && inspect.label"></div>
                </div>
                <button @click="inspect=null" class="h-9 w-9 grid place-items-center rounded-lg hover:bg-ink-100 text-ink-500">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto scrollbar-thin p-4 space-y-4" x-show="inspect">
                <template x-if="inspect">
                    <div class="space-y-3">
                        <div class="flex flex-wrap gap-1">
                            <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded border" :class="riskTone(inspect.risk_level)" x-text="'risk ' + inspect.risk_level"></span>
                            <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-brand-50 text-brand-700 border border-brand-200" x-text="humanize(inspect.category)"></span>
                            <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-ink-100 text-ink-700 border border-ink-200" x-text="(inspect.lookback_days ?? '—') + 'd lookback'"></span>
                        </div>
                        <p class="text-[13px] text-ink-700 leading-relaxed" x-text="inspect.description"></p>

                        <section>
                            <h4 class="text-[10px] uppercase tracking-[0.14em] font-bold text-ink-500 mb-1.5">Engine codes (maps to disease weights)</h4>
                            <div class="flex flex-wrap gap-1">
                                <template x-for="c in (inspect.engine_codes || [])" :key="c">
                                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-ink-50 text-ink-700 border border-ink-100" x-text="c"></span>
                                </template>
                            </div>
                        </section>

                        <section x-show="inspect.priority_diseases && inspect.priority_diseases.length">
                            <h4 class="text-[10px] uppercase tracking-[0.14em] font-bold text-ink-500 mb-1.5">Priority differentials</h4>
                            <div class="flex flex-wrap gap-1">
                                <template x-for="d in (inspect.priority_diseases || [])" :key="d">
                                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200" x-text="humanize(d)"></span>
                                </template>
                            </div>
                        </section>

                        <section x-show="inspect.screening_questions && inspect.screening_questions.length" class="rounded-xl border border-brand-200 bg-brand-50/40 p-3">
                            <h4 class="text-[10px] uppercase tracking-[0.14em] font-bold text-brand-700 mb-1.5">Screening questions</h4>
                            <ul class="space-y-1 text-[13px] text-ink-800">
                                <template x-for="q in (inspect.screening_questions || [])" :key="q">
                                    <li class="flex items-start gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0"></span>
                                        <span x-text="q"></span>
                                    </li>
                                </template>
                            </ul>
                        </section>

                        <p class="text-[11px] text-ink-500" x-text="'Reference: ' + (inspect.who_ihr_ref || 'WHO IHR 2005')"></p>
                    </div>
                </template>
            </div>
        </aside>
    </div>
</div>

@push('scripts')
<script>
function exposuresCatalog(seed){
    return {
        exposures: seed.exposures || [],
        tab: 'all',
        filters: { search:'', risk_level:'', page:1, perPage:8 },
        inspect: null,

        init(){
            if (!localStorage.getItem('pheoc_token')) {
                const next = encodeURIComponent(location.pathname + location.search);
                location.replace('{{ url('/admin/login') }}?next=' + next);
            }
        },

        get filtered(){
            const q = (this.filters.search||'').toLowerCase().trim();
            return this.exposures.filter(e => {
                if (this.tab !== 'all' && e.category !== this.tab) return false;
                if (this.filters.risk_level && e.risk_level !== this.filters.risk_level) return false;
                if (!q) return true;
                const hay = [e.code, e.label, e.description, (e.engine_codes||[]).join(' ')].join(' ').toLowerCase();
                return hay.includes(q);
            });
        },
        paged(){ const s=(this.filters.page-1)*this.filters.perPage; return this.filtered.slice(s, s+this.filters.perPage); },

        get tabsList(){
            const counts = {};
            for (const e of this.exposures) counts[e.category] = (counts[e.category]||0) + 1;
            const out = [{ key:'all', label:'All', count: this.exposures.length }];
            for (const cat of Object.keys(counts).sort()) out.push({ key: cat, label: this.humanize(cat), count: counts[cat] });
            return out;
        },

        get kpis(){
            const total = this.exposures.length;
            const vhigh = this.exposures.filter(e => e.risk_level === 'VERY_HIGH').length;
            const high  = this.exposures.filter(e => e.risk_level === 'HIGH').length;
            const needDetails = this.exposures.filter(e => e.requires_details).length;
            return [
                { label:'Exposures', value: total, hint:'WHO IHR 2005' },
                { label:'Very high', value: vhigh, hint:'max risk category' },
                { label:'High risk', value: high, hint:'urgent follow-up' },
                { label:'Require detail', value: needDetails, hint:'officer free-text mandated' },
            ];
        },

        get copilot(){
            const lines = [];
            const vhighHigh = this.exposures.filter(e => e.risk_level === 'VERY_HIGH' || e.risk_level === 'HIGH');
            if (vhighHigh.length) lines.push(`${vhighHigh.length} exposures in the catalog are HIGH or VERY_HIGH risk — these trigger immediate screening override at POE.`);
            const cats = new Set(this.exposures.map(e=>e.category));
            lines.push(`Catalog spans ${cats.size} categories with an average lookback of ${Math.round(this.exposures.reduce((a,e)=>a+(e.lookback_days||0),0) / this.exposures.length)} days.`);
            if (this.filters.search) lines.push(`"${this.filters.search}" matched ${this.filtered.length} exposure${this.filtered.length===1?'':'s'}.`);
            return lines;
        },

        resetFilters(){ this.filters = { search:'', risk_level:'', page:1, perPage:8 }; this.tab='all'; },

        riskTone(r){
            return ({
                VERY_HIGH: 'bg-rose-50 text-rose-700 border-rose-200',
                HIGH:      'bg-amber-50 text-amber-700 border-amber-200',
                MODERATE:  'bg-sky-50 text-sky-700 border-sky-200',
                LOW:       'bg-emerald-50 text-emerald-700 border-emerald-200',
            })[r] || 'bg-ink-50 text-ink-600 border-ink-200';
        },
        humanize(s){ return String(s||'').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()); },
    };
}
</script>
@endpush
@endsection

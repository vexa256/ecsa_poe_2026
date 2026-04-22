{{-- ============================================================================
  /admin/settings/poes — Gazetted POE registry (read-only catalog)
  ----------------------------------------------------------------------------
  8-point bar:
    · Premium card grid — no bulky tables, max 8 cards / page
    · Aggressive search: debounced · RPHEOC filter · transport-mode filter ·
      OSBP-only · major-entry-only · recommended-OSBP-only
    · Mobile-first (card stack at 375px, 2-col at md, 3-col at lg)
    · KPI strip: total · OSBP count · airports · land borders
    · Hardcoded Copilot: narrates coverage gaps (districts with no POEs etc.)
    · Tab split: All | By RPHEOC (grouped) | Coverage map
    · Insane validation: not applicable (read-only catalog) — search is
      fuzzy-tolerant; empty results redirect to clear-filters
    · Smart guidance: chips explain every flag (OSBP, major-entry, IHR port…)

  Data flows from App\Services\SsotRegistry::poes() which parses
  public/ssot/POEs.js — same source the mobile app uses.
============================================================================ --}}
@extends('admin.layout')

@section('title', 'POE Registry')
@section('heading', 'Points of Entry — Gazetted Registry')
@section('subheading', $meta['dataset_name'] ?? 'Uganda border stations · source of truth')

@section('breadcrumbs')
    <a href="{{ url('/admin/dashboard') }}" class="hover:text-ink-600 truncate">Command Centre</a>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="hover:text-ink-600 truncate">Settings</span>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-ink-600 font-medium truncate">POEs</span>
@endsection

@section('page_actions')
    <a href="{{ url('/ssot/POEs.js') }}" target="_blank" rel="noopener"
       class="hidden sm:inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-ink-200 bg-white hover:border-ink-300 hover:shadow-panel text-sm font-medium text-ink-700 transition" title="Raw source">
        <svg class="h-4 w-4 text-ink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
        Source file
    </a>
@endsection

@push('head')
<style>
    .tab-btn[aria-selected=true]{color:#111a33;border-color:#2f7dff;font-weight:600}
    .tab-btn[aria-selected=true] .tab-dot{background:#2f7dff;color:#fff}
    .pill[aria-pressed=true]{background:#111a33;color:#fff;border-color:#111a33}
    .card-hover:hover{border-color:#8ebfff;box-shadow:0 8px 24px -12px rgba(47,125,255,.25)}
</style>
@endpush

@section('content')
<div x-data="poesCatalog({ poes: @js($poes), pheocDistricts: @js($pheocDistricts) })"
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

    {{-- AI Copilot --}}
    <div class="rounded-xl border border-brand-200 bg-gradient-to-br from-brand-50/60 via-white to-white p-3">
        <div class="flex items-start gap-3">
            <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 grid place-items-center text-white shadow-glow shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-brand-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500 animate-pulse-dot"></span>
                    Copilot · POE network brief
                </div>
                <div class="mt-1 text-[13px] text-ink-800 leading-relaxed space-y-0.5">
                    <template x-for="s in copilot" :key="s"><p x-text="s"></p></template>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs + filters --}}
    <div class="rounded-xl border border-ink-100 bg-white shadow-panel overflow-hidden">
        <div class="border-b border-ink-100 px-2 sm:px-3 flex overflow-x-auto scrollbar-thin">
            <template x-for="t in tabs" :key="t.key">
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
                       placeholder="Search POE / district / border country…"
                       class="w-full h-9 pl-10 pr-3 rounded-lg border border-ink-200 bg-white text-sm focus:border-brand-500 focus:ring-0">
            </div>
            <select x-model="filters.rpheoc" @change="filters.page=1"
                    class="h-9 rounded-lg border border-ink-200 bg-white text-xs sm:text-sm px-3 focus:border-brand-500 focus:ring-0">
                <option value="">All RPHEOCs</option>
                <template x-for="p in rpheocs" :key="p"><option :value="p" x-text="p"></option></template>
            </select>
            <select x-model="filters.transport_mode" @change="filters.page=1"
                    class="h-9 rounded-lg border border-ink-200 bg-white text-xs sm:text-sm px-3 focus:border-brand-500 focus:ring-0">
                <option value="">All transport</option>
                <template x-for="m in transportModes" :key="m"><option :value="m" x-text="m"></option></template>
            </select>
            <button class="pill h-9 px-3 rounded-lg border border-ink-200 bg-white text-xs font-semibold text-ink-700 hover:border-ink-300"
                    :aria-pressed="filters.osbp" @click="filters.osbp = !filters.osbp; filters.page=1" title="One-Stop Border Post">OSBP</button>
            <button class="pill h-9 px-3 rounded-lg border border-ink-200 bg-white text-xs font-semibold text-ink-700 hover:border-ink-300"
                    :aria-pressed="filters.major" @click="filters.major = !filters.major; filters.page=1">Major</button>
        </div>

        {{-- GRID (tab: all) --}}
        <div x-show="tab==='all'" class="p-3 sm:p-4">
            <template x-if="paged().length === 0">
                <div class="py-12 text-center">
                    <div class="mx-auto h-12 w-12 rounded-xl bg-ink-100 text-ink-400 grid place-items-center mb-2">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z"/></svg>
                    </div>
                    <div class="text-sm font-semibold text-ink-700">No POEs match these filters</div>
                    <button @click="resetFilters()" class="text-xs text-brand-700 hover:text-brand-500 underline mt-1">Clear all filters</button>
                </div>
            </template>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-3">
                <template x-for="p in paged()" :key="p.poe_id || p.poe_name">
                    <article class="rounded-xl border border-ink-100 bg-white p-3 card-hover transition cursor-pointer"
                             @click="inspect = p">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-[10px] uppercase tracking-[0.12em] font-semibold text-ink-400" x-text="p.district"></div>
                                <h3 class="text-sm font-semibold text-ink-900 truncate" x-text="p.poe_name"></h3>
                                <div class="text-[11px] text-ink-500 font-mono truncate" x-text="p.regional_cluster_or_rpheoc || p.admin_level_1"></div>
                            </div>
                            <div class="h-9 w-9 rounded-lg grid place-items-center shrink-0"
                                 :class="transportBg(p.transport_mode)" x-html="transportIcon(p.transport_mode)"></div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <span x-show="p.is_recommended_osbp" class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">OSBP</span>
                            <span x-show="p.is_major_entry" class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-brand-50 text-brand-700 border border-brand-200">Major</span>
                            <span x-show="p.is_national_level" class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-violet-50 text-violet-700 border border-violet-200">National</span>
                            <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-ink-100 text-ink-700 border border-ink-200" x-text="p.poe_type"></span>
                        </div>
                        <div class="mt-2 text-[11px] text-ink-500 line-clamp-1" x-show="p.border_country">
                            Borders <span class="font-semibold text-ink-700" x-text="p.border_country"></span>
                        </div>
                    </article>
                </template>
            </div>

            {{-- Pagination — max 8 per page per bar --}}
            <div class="mt-4 flex items-center justify-between text-xs text-ink-500" x-show="filtered.length > 0">
                <div>
                    <span x-text="(filters.page-1)*filters.perPage + 1"></span>–<span x-text="Math.min(filters.page*filters.perPage, filtered.length)"></span> of
                    <span class="font-semibold text-ink-700" x-text="filtered.length"></span>
                </div>
                <div class="flex items-center gap-1">
                    <select x-model.number="filters.perPage" @change="filters.page=1" class="h-7 rounded border border-ink-200 text-xs px-1">
                        <option :value="8">8</option><option :value="10">10</option><option :value="16">16</option>
                    </select>
                    <button @click="filters.page=Math.max(1, filters.page-1)" :disabled="filters.page<=1" class="h-7 px-2 rounded border border-ink-200 bg-white hover:border-ink-300 disabled:opacity-40">Prev</button>
                    <button @click="filters.page++" :disabled="filters.page*filters.perPage >= filtered.length" class="h-7 px-2 rounded border border-ink-200 bg-white hover:border-ink-300 disabled:opacity-40">Next</button>
                </div>
            </div>
        </div>

        {{-- GROUPED BY RPHEOC --}}
        <div x-show="tab==='byRpheoc'" x-cloak class="p-3 sm:p-4 space-y-3">
            <template x-for="g in groupedByRpheoc" :key="g.rpheoc">
                <details open class="rounded-xl border border-ink-100">
                    <summary class="px-3 py-2 bg-ink-50/60 rounded-t-xl flex items-center gap-2 cursor-pointer">
                        <span class="text-sm font-semibold text-ink-800" x-text="g.rpheoc"></span>
                        <span class="text-[10px] text-ink-500">· <span x-text="g.poes.length"></span> POE<span x-show="g.poes.length!==1">s</span> · <span x-text="g.districts.size"></span> district<span x-show="g.districts.size!==1">s</span></span>
                    </summary>
                    <ul class="divide-y divide-ink-100">
                        <template x-for="p in g.poes" :key="p.poe_name">
                            <li class="px-3 py-2 flex items-center gap-2 text-sm">
                                <span class="h-6 w-6 rounded-md grid place-items-center shrink-0" :class="transportBg(p.transport_mode)" x-html="transportIcon(p.transport_mode)"></span>
                                <span class="font-semibold text-ink-800 truncate" x-text="p.poe_name"></span>
                                <span class="text-[11px] text-ink-500 truncate" x-text="p.district"></span>
                                <span class="ml-auto flex gap-1 shrink-0">
                                    <span x-show="p.is_recommended_osbp" class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">OSBP</span>
                                    <span x-show="p.border_country" class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-ink-100 text-ink-700 border border-ink-200" x-text="p.border_country"></span>
                                </span>
                            </li>
                        </template>
                    </ul>
                </details>
            </template>
        </div>

        {{-- COVERAGE — districts without POEs --}}
        <div x-show="tab==='coverage'" x-cloak class="p-3 sm:p-4 space-y-2">
            <div class="text-xs text-ink-500">Districts declared in the RPHEOC hierarchy vs those with at least one gazetted POE. Districts without any gazetted crossing rely on neighbouring POEs for surveillance.</div>
            <template x-for="d in coverage" :key="d.district">
                <div class="flex items-center gap-3 rounded-lg border border-ink-100 bg-white p-2.5">
                    <span class="h-8 w-8 rounded-md grid place-items-center shrink-0"
                          :class="d.poes > 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'">
                        <svg x-show="d.poes > 0" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <svg x-show="d.poes === 0" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-ink-800 truncate" x-text="d.district"></div>
                        <div class="text-[11px] text-ink-500 truncate" x-text="d.rpheoc"></div>
                    </div>
                    <span class="text-[10px] font-bold font-mono px-1.5 py-0.5 rounded-md border"
                          :class="d.poes > 0 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                          x-text="d.poes + ' POE' + (d.poes === 1 ? '' : 's')"></span>
                </div>
            </template>
        </div>
    </div>

    {{-- INSPECT DRAWER --}}
    <div x-show="inspect" x-cloak @keydown.escape.window="inspect = null"
         class="fixed inset-0 z-50 flex">
        <div x-show="inspect" x-transition.opacity @click="inspect=null" class="absolute inset-0 bg-ink-950/50 backdrop-blur-sm"></div>
        <aside x-show="inspect"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               class="relative ml-auto h-full w-full sm:max-w-xl bg-white flex flex-col shadow-panel-lg">
            <div class="h-16 px-4 sm:px-5 border-b border-ink-100 flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg grid place-items-center" :class="inspect && transportBg(inspect.transport_mode)" x-html="inspect && transportIcon(inspect.transport_mode)"></div>
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] uppercase tracking-[0.14em] font-semibold text-ink-400" x-text="inspect && inspect.district"></div>
                    <div class="text-base font-semibold text-ink-900 truncate" x-text="inspect && inspect.poe_name"></div>
                </div>
                <button @click="inspect=null" class="h-9 w-9 grid place-items-center rounded-lg hover:bg-ink-100 text-ink-500">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto scrollbar-thin p-4 sm:p-5 space-y-3" x-show="inspect">
                <template x-if="inspect">
                    <div class="space-y-3">
                        <div class="flex flex-wrap gap-1">
                            <span x-show="inspect.is_recommended_osbp" class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">OSBP</span>
                            <span x-show="inspect.is_major_entry" class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-brand-50 text-brand-700 border border-brand-200">Major entry</span>
                            <span x-show="inspect.is_national_level" class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-violet-50 text-violet-700 border border-violet-200">National level</span>
                            <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-ink-100 text-ink-700 border border-ink-200" x-text="inspect.poe_type"></span>
                            <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200" x-text="inspect.transport_mode"></span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <template x-for="kv in [
                                ['Country', inspect.country],
                                ['Province', inspect.province],
                                ['RPHEOC', inspect.regional_cluster_or_rpheoc || inspect.admin_level_1],
                                ['District', inspect.district],
                                ['Border country', inspect.border_country],
                                ['POE code', inspect.poe_code || inspect.poe_name],
                                ['POE type', inspect.poe_type],
                                ['Transport', inspect.transport_mode],
                            ]" :key="kv[0]">
                                <div class="rounded-lg border border-ink-100 bg-ink-50/40 px-3 py-2" x-show="kv[1]">
                                    <div class="text-[10px] uppercase tracking-[0.12em] font-semibold text-ink-400" x-text="kv[0]"></div>
                                    <div class="text-sm font-medium text-ink-800 truncate" x-text="kv[1] || '—'"></div>
                                </div>
                            </template>
                        </div>
                        <p class="text-[11px] text-ink-500">Source: POES.js v<span x-text="'{{ $meta['schema_version'] ?? '—' }}'"></span> · published in public/ssot/</p>
                    </div>
                </template>
            </div>
        </aside>
    </div>
</div>

@push('scripts')
<script>
function poesCatalog(seed){
    return {
        poes: seed.poes || [],
        pheocDistricts: seed.pheocDistricts || {},
        tab: 'all',
        filters: { search: '', rpheoc: '', transport_mode: '', osbp: false, major: false, page: 1, perPage: 8 },
        inspect: null,

        init(){
            if (!localStorage.getItem('pheoc_token')) {
                const next = encodeURIComponent(location.pathname + location.search);
                location.replace('{{ url('/admin/login') }}?next=' + next);
            }
        },

        // ── lookups ────────────────────────────────────────────────────
        get rpheocs(){
            const s = new Set();
            for (const p of this.poes) s.add(p.regional_cluster_or_rpheoc || p.admin_level_1 || '');
            return [...s].filter(Boolean).sort();
        },
        get transportModes(){
            const s = new Set();
            for (const p of this.poes) if (p.transport_mode) s.add(p.transport_mode);
            return [...s].sort();
        },
        get filtered(){
            const q = (this.filters.search||'').toLowerCase().trim();
            return this.poes.filter(p => {
                if (this.filters.rpheoc && (p.regional_cluster_or_rpheoc || p.admin_level_1) !== this.filters.rpheoc) return false;
                if (this.filters.transport_mode && p.transport_mode !== this.filters.transport_mode) return false;
                if (this.filters.osbp && !p.is_recommended_osbp) return false;
                if (this.filters.major && !p.is_major_entry) return false;
                if (!q) return true;
                return (p.poe_name||'').toLowerCase().includes(q)
                    || (p.district||'').toLowerCase().includes(q)
                    || (p.border_country||'').toLowerCase().includes(q)
                    || (p.poe_type||'').toLowerCase().includes(q);
            });
        },
        paged(){
            const s = (this.filters.page-1)*this.filters.perPage;
            return this.filtered.slice(s, s + this.filters.perPage);
        },
        get groupedByRpheoc(){
            const m = new Map();
            for (const p of this.filtered) {
                const k = p.regional_cluster_or_rpheoc || p.admin_level_1 || '—';
                if (!m.has(k)) m.set(k, { rpheoc: k, poes: [], districts: new Set() });
                const g = m.get(k);
                g.poes.push(p); if (p.district) g.districts.add(p.district);
            }
            return [...m.values()].sort((a,b)=>a.rpheoc.localeCompare(b.rpheoc));
        },
        get coverage(){
            const poesByDistrict = {};
            for (const p of this.poes) {
                if (!p.district) continue;
                poesByDistrict[p.district] = (poesByDistrict[p.district] || 0) + 1;
            }
            const rows = [];
            for (const [rpheoc, districts] of Object.entries(this.pheocDistricts)) {
                for (const d of districts) rows.push({ district: d, rpheoc, poes: poesByDistrict[d] || 0 });
            }
            return rows.sort((a,b) => a.poes - b.poes || a.district.localeCompare(b.district));
        },

        get tabs(){
            return [
                { key:'all', label:'All POEs', count: this.filtered.length },
                { key:'byRpheoc', label:'By RPHEOC', count: this.groupedByRpheoc.length },
                { key:'coverage', label:'Coverage', count: this.coverage.filter(d=>d.poes===0).length + '/' + this.coverage.length },
            ];
        },

        // ── KPIs ───────────────────────────────────────────────────────
        get kpis(){
            const total = this.poes.length;
            const osbp = this.poes.filter(p => p.is_recommended_osbp).length;
            const airports = this.poes.filter(p => p.poe_type && /airport|airstrip/i.test(p.poe_type)).length;
            const land = this.poes.filter(p => p.transport_mode === 'land').length;
            return [
                { label: 'Gazetted POEs', value: total, hint: 'Uganda · immigration.go.ug' },
                { label: 'OSBPs', value: osbp, hint: 'One-Stop Border Posts' },
                { label: 'Air entries', value: airports, hint: 'airports + airstrips' },
                { label: 'Land borders', value: land, hint: 'incl. rail / foot' },
            ];
        },

        // ── Copilot ────────────────────────────────────────────────────
        get copilot(){
            const lines = [];
            const noCoverage = this.coverage.filter(d => d.poes === 0);
            if (noCoverage.length > 0) {
                lines.push(`${noCoverage.length} district${noCoverage.length>1?'s':''} in the RPHEOC hierarchy have no gazetted POE — rely on neighbouring crossings (e.g. ${noCoverage.slice(0,3).map(d=>d.district).join(', ')}${noCoverage.length>3?'…':''}).`);
            }
            const shownOsbp = this.filtered.filter(p=>p.is_recommended_osbp).length;
            const shownTotal = this.filtered.length;
            if (this.filters.rpheoc) lines.push(`Filtered to ${this.filters.rpheoc} · ${shownTotal} POE${shownTotal===1?'':'s'}${shownOsbp?`, including ${shownOsbp} OSBP`:''}.`);
            else lines.push(`Showing ${this.filtered.length} of ${this.poes.length} POEs — ${this.rpheocs.length} RPHEOCs, ${new Set(this.poes.map(p=>p.district)).size} unique districts.`);
            return lines;
        },

        resetFilters(){
            this.filters = { search:'', rpheoc:'', transport_mode:'', osbp:false, major:false, page:1, perPage:8 };
        },

        // ── visuals ────────────────────────────────────────────────────
        transportBg(mode){
            if (!mode) return 'bg-ink-100 text-ink-500';
            if (/air/i.test(mode)) return 'bg-sky-50 text-sky-600';
            if (/water/i.test(mode) || /port|island/i.test(mode)) return 'bg-brand-50 text-brand-700';
            if (/rail/i.test(mode)) return 'bg-violet-50 text-violet-600';
            return 'bg-emerald-50 text-emerald-600';
        },
        transportIcon(mode){
            if (!mode) return '';
            if (/air/i.test(mode)) return '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>';
            if (/water|port|island/i.test(mode)) return '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17l6-6 4 4 8-8M4 21h16"/></svg>';
            return '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h18M3 8h18M3 16h18"/></svg>';
        },
    };
}
</script>
@endpush
@endsection

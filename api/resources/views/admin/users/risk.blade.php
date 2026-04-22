{{-- ============================================================================
  /admin/users/risk — Risk Registry
  ----------------------------------------------------------------------------
  8-point view bar:
    · Neat, dense list — two-line rows, card stacks < sm:
    · Aggressive search (debounced) + role filter + score-floor slider + pagination
    · Mobile-first (designed at 375px)
    · KPI strip: avg risk / high-risk count / MFA coverage / open flags
    · Hardcoded AI "Copilot" hint block (rules-based narrator)
    · Tabs: Risk-scored users | MFA adoption | Open anomaly flags
    · Insane validation: rescan-all confirmation, per-row action menu
    · Smart guidance: tip chips per user (e.g. "NATIONAL_ADMIN without MFA")

  API (all under /api/v2/admin/users):
    GET  /report/risk?min=N         { users: [...] }
    GET  /report/mfa                { mfa_by_role: [...] }
    GET  /{id}/flags                { flags: [...] }
    POST /{id}/force-mfa-reset
    POST /{id}/rescan
    POST /scan-all
    POST /{id}/flags/{flagId}/clear  { note }
============================================================================ --}}
@extends('admin.layout')

@section('title', 'Risk Registry')
@section('heading', 'Risk Registry')
@section('subheading', 'Anomaly scoring · MFA adoption · priority remediation queue')

@section('breadcrumbs')
    <a href="{{ url('/admin/dashboard') }}" class="hover:text-ink-600 truncate">Command Centre</a>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <a href="{{ url('/admin/users') }}" class="hover:text-ink-600 truncate">Users</a>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-ink-600 font-medium truncate">Risk</span>
@endsection

@section('page_actions')
    <button @click="$dispatch('risk:scanall')"
            class="inline-flex items-center gap-2 h-9 px-3 rounded-lg bg-ink-900 hover:bg-ink-800 text-white text-sm font-semibold shadow-panel transition">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        Scan all
    </button>
@endsection

@push('head')
<style>
    .row-hover:hover{background:rgb(245 247 251/.6)}
    .tab-btn[aria-selected=true]{color:#111a33;border-color:#2f7dff;font-weight:600}
    .tab-btn[aria-selected=true] .tab-dot{background:#2f7dff;color:#fff}
    details > summary{list-style:none;cursor:pointer}
    details > summary::-webkit-details-marker{display:none}
</style>
@endpush

@section('content')
<div
    x-data="riskPage({
        actorScope: @js($actorScope),
        roles: @js($roles),
        api: '{{ url('/api/v2/admin/users') }}',
    })"
    x-init="init()"
    @risk:scanall.window="confirmScanAll()"
    class="space-y-4">

    {{-- ═══ KPI STRIP ══════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
        <template x-for="k in kpis()" :key="k.label">
            <div class="rounded-xl border border-ink-100 bg-white shadow-panel p-3">
                <div class="flex items-center justify-between">
                    <div class="text-[10px] uppercase tracking-[0.14em] font-semibold text-ink-400" x-text="k.label"></div>
                    <span class="text-[10px] font-bold font-mono px-1.5 py-0.5 rounded-md border"
                          :class="{
                            'bg-emerald-50 text-emerald-600 border-emerald-200': k.tone==='emerald',
                            'bg-rose-50 text-rose-600 border-rose-200': k.tone==='rose',
                            'bg-amber-50 text-amber-600 border-amber-200': k.tone==='amber',
                            'bg-brand-50 text-brand-700 border-brand-200': k.tone==='brand',
                          }" x-text="k.delta"></span>
                </div>
                <div class="mt-1.5 text-xl sm:text-2xl leading-none font-bold text-ink-900 tracking-tight font-mono" x-text="k.value"></div>
                <div class="mt-1 text-[11px] text-ink-500 line-clamp-1" x-text="k.hint"></div>
            </div>
        </template>
    </div>

    {{-- ═══ AI COPILOT HINT ════════════════════════════════════════════ --}}
    <div class="rounded-xl border border-brand-200 bg-gradient-to-br from-brand-50/60 via-white to-white p-3 sm:p-4"
         x-show="copilot.sentences.length">
        <div class="flex items-start gap-3">
            <div class="h-9 w-9 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 grid place-items-center text-white shadow-glow shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-brand-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500 animate-pulse-dot"></span>
                    PHEOC Copilot · risk brief
                </div>
                <div class="mt-1 text-sm text-ink-800 leading-relaxed space-y-1">
                    <template x-for="s in copilot.sentences" :key="s">
                        <p x-text="s"></p>
                    </template>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5" x-show="copilot.actions.length">
                    <template x-for="a in copilot.actions" :key="a.label">
                        <button @click="a.run()" class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-1 rounded-md bg-brand-600 hover:bg-brand-700 text-white" x-text="a.label"></button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ TABS ═══════════════════════════════════════════════════════ --}}
    <div class="rounded-xl border border-ink-100 bg-white shadow-panel overflow-hidden">
        <div class="border-b border-ink-100 px-2 sm:px-3 flex overflow-x-auto scrollbar-thin">
            <template x-for="t in tabs" :key="t.key">
                <button class="tab-btn px-3 py-3 text-xs sm:text-sm text-ink-500 border-b-2 border-transparent hover:text-ink-900 shrink-0 inline-flex items-center gap-2"
                        :aria-selected="tab === t.key"
                        @click="tab = t.key">
                    <span class="tab-dot inline-flex items-center justify-center h-5 min-w-5 px-1.5 rounded-md bg-ink-100 text-ink-500 text-[10px] font-bold font-mono" x-text="t.count"></span>
                    <span x-text="t.label"></span>
                </button>
            </template>
        </div>

        {{-- Filters (shared across tabs where relevant) --}}
        <div class="p-3 border-b border-ink-100 flex flex-wrap gap-2 items-center bg-ink-50/30">
            <div class="flex-1 min-w-[200px] relative" x-show="tab === 'risk' || tab === 'flags'">
                <svg class="h-4 w-4 text-ink-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z"/></svg>
                <input type="search" x-model.debounce.300ms="filters.search"
                       placeholder="Search name, email, username…"
                       class="w-full h-9 pl-10 pr-3 rounded-lg border border-ink-200 bg-white text-sm placeholder-ink-400 focus:border-brand-500 focus:ring-0">
            </div>
            <select x-show="tab === 'risk'" x-model="filters.role_key"
                    class="h-9 rounded-lg border border-ink-200 bg-white text-xs sm:text-sm focus:border-brand-500 focus:ring-0 px-3">
                <option value="">All roles</option>
                <template x-for="r in roles" :key="r.role_key">
                    <option :value="r.role_key" x-text="r.display_name"></option>
                </template>
            </select>
            <div x-show="tab === 'risk'" class="flex items-center gap-2 text-xs text-ink-500 px-2">
                <span>Min score</span>
                <input type="range" min="0" max="100" step="5" x-model.number="filters.min" @change="reloadRisk()"
                       class="w-24 sm:w-32 accent-brand-600">
                <span class="font-mono font-bold text-ink-800 w-8 text-right" x-text="filters.min"></span>
            </div>
            <button x-show="tab==='risk'" @click="reloadRisk()" class="h-9 w-9 grid place-items-center rounded-lg border border-ink-200 bg-white hover:border-ink-300 text-ink-600" title="Refresh">
                <svg class="h-4 w-4" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
        </div>

        {{-- ─── TAB: RISK-SCORED USERS ────────────────────────────────── --}}
        <div x-show="tab === 'risk'" x-cloak>
            <template x-if="loading && filteredRisk.length === 0">
                <div class="p-6 space-y-2">
                    <template x-for="i in 5" :key="i">
                        <div class="h-12 bg-ink-50 rounded shimmer"></div>
                    </template>
                </div>
            </template>
            <template x-if="!loading && filteredRisk.length === 0">
                <div class="p-10 text-center">
                    <div class="mx-auto h-12 w-12 rounded-xl bg-emerald-50 text-emerald-500 grid place-items-center mb-2">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="text-sm font-semibold text-ink-700">Clean slate — no users at or above the current floor</div>
                    <div class="text-xs text-ink-500 mt-1">Drop the min-score slider to inspect lower-risk accounts.</div>
                </div>
            </template>

            <ul class="divide-y divide-ink-100" x-show="!loading && filteredRisk.length > 0">
                <template x-for="u in paged(filteredRisk)" :key="u.id">
                    <li class="px-3 sm:px-4 py-2.5 row-hover">
                        <div class="flex items-start gap-3">
                            <div class="h-9 w-9 rounded-lg grid place-items-center text-white text-xs font-bold shrink-0"
                                 :class="scoreBg(u.risk_score)" x-text="initials(u.full_name || u.email)"></div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <div class="text-sm font-semibold text-ink-900 truncate" x-text="u.full_name || u.email"></div>
                                    <span x-show="!u.two_factor_confirmed_at" class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200">No MFA</span>
                                    <span x-show="!Number(u.is_active)" class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-ink-100 text-ink-600 border border-ink-200">Inactive</span>
                                </div>
                                <div class="text-[11px] text-ink-500 font-mono truncate" x-text="u.email || u.username"></div>
                                <div class="mt-1 flex flex-wrap gap-1 items-center">
                                    <span class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-ink-100 text-ink-700 border border-ink-200" x-text="u.role_key"></span>
                                    <template x-for="f in parseFlags(u.risk_flags_json)" :key="f">
                                        <span class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-rose-50/70 text-rose-700 border border-rose-200" x-text="humanFlag(f)"></span>
                                    </template>
                                </div>
                                <div class="mt-1 text-[10px] text-ink-400">
                                    Last login <span x-text="relTime(u.last_login_at) || 'never'"></span>
                                    <template x-if="smartTip(u)">
                                        <span class="ml-2 inline-flex items-center gap-1 text-brand-700 font-semibold">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                            <span x-text="smartTip(u)"></span>
                                        </span>
                                    </template>
                                </div>
                            </div>
                            {{-- Score --}}
                            <div class="shrink-0 text-right">
                                <div class="flex items-center gap-1.5">
                                    <div class="h-1.5 w-12 rounded-full bg-ink-100 overflow-hidden">
                                        <div class="h-full rounded-full" :class="scoreBg(u.risk_score)"
                                             :style="`width:${Math.min(100,(u.risk_score||0))}%`"></div>
                                    </div>
                                    <span class="text-sm font-bold font-mono" :class="scoreText(u.risk_score)" x-text="u.risk_score ?? 0"></span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-1 justify-end">
                                    <button @click="doAction(u.id, 'rescan')" class="text-[10px] px-1.5 py-0.5 rounded border border-ink-200 bg-white hover:bg-ink-50 text-ink-700 font-semibold" title="Re-run risk scan">Rescan</button>
                                    <button x-show="u.two_factor_confirmed_at" @click="doAction(u.id, 'force-mfa-reset')" class="text-[10px] px-1.5 py-0.5 rounded border border-sky-200 bg-sky-50 hover:bg-sky-100 text-sky-700 font-semibold" title="Force 2FA re-enrolment">Reset 2FA</button>
                                    <button @click="confirmSuspend(u)" class="text-[10px] px-1.5 py-0.5 rounded border border-amber-200 bg-amber-50 hover:bg-amber-100 text-amber-700 font-semibold">Suspend</button>
                                    <a :href="'{{ url('/admin/users') }}?focus=' + u.id" class="text-[10px] px-1.5 py-0.5 rounded border border-ink-200 bg-white hover:bg-ink-50 text-ink-700 font-semibold">Open</a>
                                </div>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>

            {{-- Pagination --}}
            <div class="border-t border-ink-100 px-3 sm:px-4 py-2 flex items-center justify-between text-xs text-ink-500"
                 x-show="filteredRisk.length > 0">
                <div>
                    <span x-text="(filters.page-1)*filters.perPage + 1"></span>–<span x-text="Math.min(filters.page*filters.perPage, filteredRisk.length)"></span>
                    of <span class="font-semibold text-ink-700" x-text="filteredRisk.length"></span>
                </div>
                <div class="flex items-center gap-1">
                    <select x-model.number="filters.perPage" class="h-7 rounded border border-ink-200 text-xs px-1">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                    <button @click="filters.page = Math.max(1, filters.page-1)" :disabled="filters.page <= 1" class="h-7 px-2 rounded border border-ink-200 bg-white hover:border-ink-300 disabled:opacity-40">Prev</button>
                    <button @click="filters.page++" :disabled="filters.page * filters.perPage >= filteredRisk.length" class="h-7 px-2 rounded border border-ink-200 bg-white hover:border-ink-300 disabled:opacity-40">Next</button>
                </div>
            </div>
        </div>

        {{-- ─── TAB: MFA ADOPTION ────────────────────────────────────── --}}
        <div x-show="tab === 'mfa'" x-cloak class="p-3 sm:p-4 space-y-3">
            <template x-for="m in mfa" :key="m.role_key">
                <div class="rounded-lg border border-ink-100 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-ink-800" x-text="m.role_key"></div>
                            <div class="text-[11px] text-ink-500">
                                <span class="font-mono font-bold" x-text="m.with_mfa"></span> of <span class="font-mono font-bold" x-text="m.total"></span> with MFA
                            </div>
                        </div>
                        <span class="text-lg font-bold font-mono"
                              :class="Number(m.mfa_pct) >= 80 ? 'text-emerald-600' : (Number(m.mfa_pct) >= 40 ? 'text-amber-600' : 'text-rose-600')"
                              x-text="Number(m.mfa_pct).toFixed(0) + '%'"></span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-ink-100 overflow-hidden">
                        <div class="h-full"
                             :class="Number(m.mfa_pct) >= 80 ? 'bg-emerald-500' : (Number(m.mfa_pct) >= 40 ? 'bg-amber-500' : 'bg-rose-500')"
                             :style="`width:${Math.min(100, Number(m.mfa_pct))}%`"></div>
                    </div>
                </div>
            </template>
            <template x-if="!mfa || mfa.length === 0">
                <div class="text-sm text-ink-500 italic">No MFA data yet.</div>
            </template>
        </div>

        {{-- ─── TAB: OPEN FLAGS ──────────────────────────────────────── --}}
        <div x-show="tab === 'flags'" x-cloak>
            <ul class="divide-y divide-ink-100">
                <template x-if="openFlags.length === 0">
                    <div class="p-10 text-center text-sm text-ink-500 italic">No open anomaly flags · everything is clean.</div>
                </template>
                <template x-for="f in openFlags" :key="f.id">
                    <li class="px-3 sm:px-4 py-2.5">
                        <div class="flex items-start gap-3">
                            <div class="h-8 w-8 rounded-lg bg-rose-50 text-rose-600 grid place-items-center shrink-0">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-sm font-semibold text-ink-900" x-text="f.full_name || ('user#' + f.user_id)"></span>
                                    <span class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200" x-text="f.flag_code"></span>
                                    <span class="text-[9px] font-bold uppercase px-1 py-0.5 rounded"
                                          :class="f.severity === 'HIGH' ? 'bg-rose-50 text-rose-700 border border-rose-200' : (f.severity === 'MEDIUM' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-ink-100 text-ink-600 border border-ink-200')"
                                          x-text="f.severity"></span>
                                </div>
                                <div class="text-[11px] text-ink-500 font-mono truncate mt-0.5" x-text="f.evidence_json"></div>
                                <div class="text-[10px] text-ink-400 mt-0.5">
                                    first seen <span x-text="relTime(f.first_seen_at)"></span> · last <span x-text="relTime(f.last_seen_at)"></span>
                                </div>
                            </div>
                            <button @click="clearFlag(f)" class="text-[11px] px-2 py-1 rounded border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-semibold shrink-0">Clear</button>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    {{-- Confirm modal --}}
    <div x-show="modal.open" x-cloak x-transition.opacity
         class="fixed inset-0 z-[60] grid place-items-center p-4 bg-ink-950/60 backdrop-blur-sm"
         @click.self="modal.open=false" @keydown.escape.window="modal.open=false">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-panel-lg p-5">
            <h4 class="text-base font-semibold text-ink-900" x-text="modal.title"></h4>
            <p class="mt-1 text-sm text-ink-600" x-text="modal.body"></p>
            <template x-if="modal.needReason">
                <textarea x-model="modal.reason" rows="3" maxlength="500" placeholder="Reason (required)"
                          class="mt-3 w-full rounded-lg border border-ink-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-0"></textarea>
            </template>
            <div class="mt-4 flex items-center justify-end gap-2">
                <button @click="modal.open=false" class="h-9 px-3 rounded-lg border border-ink-200 bg-white hover:border-ink-300 text-sm font-semibold text-ink-700">Cancel</button>
                <button @click="modal.confirm && modal.confirm()" :class="modal.danger ? 'bg-rose-600 hover:bg-rose-700' : 'bg-ink-900 hover:bg-ink-800'"
                        class="h-9 px-3 rounded-lg text-white text-sm font-semibold" x-text="modal.confirmLabel || 'Confirm'"></button>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div x-show="toast.open" x-cloak x-transition
         class="fixed bottom-4 right-4 z-[70] max-w-sm rounded-xl shadow-panel-lg px-4 py-3 text-sm font-semibold"
         :class="toast.tone === 'error' ? 'bg-rose-600 text-white' : 'bg-ink-900 text-white'"
         x-text="toast.text"></div>
</div>

@push('scripts')
<script>
function riskPage(seed){
    return {
        actorScope: seed.actorScope,
        roles: seed.roles,
        api: seed.api,

        tab: 'risk',
        loading: false,
        risk: [],
        mfa: [],
        openFlags: [],
        filters: { search: '', role_key: '', min: 1, page: 1, perPage: 25 },

        modal: { open:false, title:'', body:'', needReason:false, reason:'', confirmLabel:'Confirm', danger:false, confirm:null },
        toast: { open:false, text:'', tone:'info', _t:null },

        get tabs(){
            return [
                { key: 'risk',  label: 'Risk-scored', count: this.filteredRisk.length },
                { key: 'mfa',   label: 'MFA adoption', count: this.mfa.length },
                { key: 'flags', label: 'Open flags',   count: this.openFlags.length },
            ];
        },

        async init(){
            if (!localStorage.getItem('pheoc_token')) { this._bounceToLogin(); return; }
            await Promise.all([ this.reloadRisk(), this.reloadMfa(), this.reloadFlags() ]);
        },

        // ── api plumbing ───────────────────────────────────────────────
        _token(){ return localStorage.getItem('pheoc_token') || ''; },
        _bounceToLogin(){
            const next = encodeURIComponent(location.pathname + location.search);
            localStorage.removeItem('pheoc_token'); localStorage.removeItem('pheoc_user');
            location.replace('{{ url('/admin/login') }}?next=' + next);
        },
        async _req(path, opts = {}){
            const t = this._token();
            if (!t) { this._bounceToLogin(); return { status:401, body:{ok:false} }; }
            const res = await fetch(this.api + path, {
                method: opts.method || 'GET',
                headers: Object.assign({
                    'Accept':'application/json','Content-Type':'application/json',
                    'Authorization':'Bearer '+t,
                }, opts.headers||{}),
                body: opts.body != null ? JSON.stringify(opts.body) : undefined,
            });
            const ct = res.headers.get('content-type')||'';
            const body = ct.includes('json') ? await res.json() : { ok:false };
            if (res.status === 401) this._bounceToLogin();
            return { status: res.status, body };
        },

        async reloadRisk(){
            this.loading = true;
            const { status, body } = await this._req('/report/risk?min=' + (this.filters.min || 1));
            this.loading = false;
            if (status === 200 && body.ok) this.risk = body.data.users || [];
        },
        async reloadMfa(){
            const { status, body } = await this._req('/report/mfa');
            if (status === 200 && body.ok) this.mfa = body.data.mfa_by_role || [];
        },
        async reloadFlags(){
            // Use `GET /users?risk_min=40` then collect flags — simpler: hit the
            // anomaly flags directly via a scan-all snapshot. Since there's no
            // bulk "all open flags" endpoint, we aggregate per user on the risk list.
            const { status, body } = await this._req('/report/risk?min=1');
            if (status !== 200 || !body.ok) return;
            const users = body.data.users || [];
            const out = [];
            for (const u of users.slice(0, 50)) {
                const f = await this._req('/' + u.id + '/flags');
                if (f.status === 200 && f.body.ok) {
                    for (const flag of (f.body.data.flags || [])) {
                        if (!flag.cleared_at) out.push(Object.assign({}, flag, { full_name: u.full_name, user_id: u.id }));
                    }
                }
            }
            this.openFlags = out.sort((a,b) => (b.severity > a.severity ? 1 : -1));
        },

        // ── computed ───────────────────────────────────────────────────
        get filteredRisk(){
            const q = (this.filters.search || '').toLowerCase().trim();
            return this.risk.filter(u => {
                if (this.filters.role_key && u.role_key !== this.filters.role_key) return false;
                if (!q) return true;
                return (u.full_name||'').toLowerCase().includes(q)
                    || (u.email||'').toLowerCase().includes(q)
                    || (u.username||'').toLowerCase().includes(q);
            });
        },
        paged(list){
            const start = (this.filters.page - 1) * this.filters.perPage;
            return list.slice(start, start + this.filters.perPage);
        },

        kpis(){
            const total = this.risk.length;
            const avg = total ? Math.round(this.risk.reduce((a,u)=>a+(u.risk_score||0),0) / total) : 0;
            const high = this.risk.filter(u => (u.risk_score||0) >= 70).length;
            const totalMfa = this.mfa.reduce((a,m)=>a+Number(m.with_mfa||0), 0);
            const totalUsers = this.mfa.reduce((a,m)=>a+Number(m.total||0), 0);
            const mfaPct = totalUsers ? Math.round(100 * totalMfa / totalUsers) : 0;
            return [
                { label: 'Users at risk ≥ 1', value: total, delta: '', hint: 'scored anomaly engine', tone: 'brand' },
                { label: 'High risk (≥70)', value: high, delta: high > 0 ? 'action' : 'clear',
                  hint: high > 0 ? 'review immediately' : 'no high-risk accounts',
                  tone: high > 0 ? 'rose' : 'emerald' },
                { label: 'Avg score', value: avg, delta: avg >= 40 ? 'high' : 'ok',
                  hint: 'across filtered pool', tone: avg >= 40 ? 'amber' : 'emerald' },
                { label: 'MFA coverage', value: mfaPct + '%', delta: mfaPct >= 80 ? 'ok' : 'gap',
                  hint: totalMfa + ' / ' + totalUsers + ' accounts', tone: mfaPct >= 80 ? 'emerald' : 'rose' },
            ];
        },

        // ── hardcoded AI narrator (rules-based) ────────────────────────
        get copilot(){
            const sents = [];
            const actions = [];
            if (this.risk.length === 0) {
                return { sentences: ['Anomaly engine reports no users above the current score floor — raise the floor or rescan to refresh.'], actions: [] };
            }
            const high = this.risk.filter(u => (u.risk_score||0) >= 70);
            const adminsNoMfa = this.risk.filter(u => ['NATIONAL_ADMIN','PHEOC_OFFICER','DISTRICT_SUPERVISOR','POE_ADMIN'].includes(u.role_key) && !u.two_factor_confirmed_at);
            const failLoginFlag = this.risk.filter(u => (u.risk_flags_json||'').includes('FREQUENT_FAILED_LOGINS'));
            const dormantHigh = this.risk.filter(u => !u.last_login_at || (Date.now() - Date.parse(String(u.last_login_at).replace(' ','T')+'Z')) > 30*86400000);

            if (high.length > 0) sents.push(`${high.length} user${high.length>1?'s':''} at or above score 70 — recommend immediate review or suspension until credentials rotate.`);
            if (adminsNoMfa.length > 0) {
                sents.push(`${adminsNoMfa.length} privileged account${adminsNoMfa.length>1?'s':''} (admin/officer/supervisor) with 2FA disabled — policy requires MFA on every non-screener role.`);
                actions.push({ label: 'Force MFA on all admins', run: () => this.bulkForceMfa(adminsNoMfa.map(u=>u.id)) });
            }
            if (failLoginFlag.length > 0) sents.push(`${failLoginFlag.length} account${failLoginFlag.length>1?'s':''} flagged FREQUENT_FAILED_LOGINS — potential credential-stuffing in progress.`);
            if (dormantHigh.length > 0 && dormantHigh.length !== this.risk.length) sents.push(`${dormantHigh.length} risky account${dormantHigh.length>1?'s':''} haven't been used in 30+ days — consider suspension as cheap risk reduction.`);
            if (sents.length === 0) sents.push('No acute risk patterns detected in the current filter. Keep the weekly scan-all cadence.');
            return { sentences: sents, actions };
        },

        // ── actions ────────────────────────────────────────────────────
        async doAction(id, action){
            const { status, body } = await this._req('/' + id + '/' + action, { method:'POST' });
            if (status === 200 && body.ok !== false) {
                this.showToast(action + ' · done');
                await this.reloadRisk();
            } else {
                this.showToast(body.error || 'Action failed', 'error');
            }
        },
        confirmSuspend(u){
            this.modal = {
                open:true, title:'Suspend user?',
                body:`Suspends "${u.full_name||u.username}" and notifies the audit trail. Tokens and trusted devices will be revoked.`,
                needReason:true, reason:'', danger:false, confirmLabel:'Suspend',
                confirm: async () => {
                    if (!this.modal.reason || this.modal.reason.trim().length < 3) return;
                    this.modal.open = false;
                    const { status, body } = await this._req('/' + u.id + '/suspend', { method:'POST', body:{ reason: this.modal.reason.trim() } });
                    if (status === 200 && body.ok) { this.showToast('Suspended'); this.reloadRisk(); }
                    else this.showToast(body.error || 'Failed', 'error');
                },
            };
        },
        confirmScanAll(){
            this.modal = {
                open:true, title:'Re-scan every user?',
                body:'Runs the anomaly engine against every user in the database. Safe but slow — typically ~1s per 50 users.',
                needReason:false, danger:false, confirmLabel:'Run scan',
                confirm: async () => {
                    this.modal.open = false;
                    this.showToast('Scan running…');
                    const { status, body } = await this._req('/scan-all', { method:'POST' });
                    if (status === 200 && body.ok !== false) {
                        this.showToast('Scan complete');
                        await this.reloadRisk();
                        await this.reloadFlags();
                    } else this.showToast(body.error || 'Scan failed', 'error');
                },
            };
        },
        async bulkForceMfa(ids){
            if (ids.length === 0) return;
            this.modal = {
                open:true, title:`Force 2FA reset on ${ids.length} admin(s)?`,
                body:'Clears their 2FA secret and forces re-enrolment on next login. Users will be emailed.',
                needReason:false, danger:false, confirmLabel:'Force reset',
                confirm: async () => {
                    this.modal.open = false;
                    const { status, body } = await this._req('/bulk', { method:'POST', body:{ ids, action:'force_mfa_reset' } });
                    if (status === 200 && body.ok) {
                        this.showToast('Affected: ' + (body.data.affected || 0));
                        this.reloadRisk(); this.reloadMfa();
                    } else this.showToast(body.error || 'Bulk failed', 'error');
                },
            };
        },
        async clearFlag(f){
            const { status, body } = await this._req('/' + f.user_id + '/flags/' + f.id + '/clear', { method:'POST', body:{ note:'Cleared from Risk Registry' } });
            if (status === 200 && body.ok) {
                this.showToast('Flag cleared');
                this.openFlags = this.openFlags.filter(x => x.id !== f.id);
                this.reloadRisk();
            } else this.showToast(body.error || 'Failed', 'error');
        },

        // ── formatters ─────────────────────────────────────────────────
        initials(s){ return (s||'?').trim().split(/\s+/).map(x=>x[0]).slice(0,2).join('').toUpperCase()||'?'; },
        relTime(v){
            if (!v) return null;
            const d = new Date(String(v).replace(' ','T') + (String(v).includes('T') ? '' : 'Z'));
            if (isNaN(d)) return null;
            const s = Math.floor((Date.now()-d.getTime())/1000);
            if (s<60) return 'just now'; if (s<3600) return Math.floor(s/60)+'m ago';
            if (s<86400) return Math.floor(s/3600)+'h ago'; if (s<2592000) return Math.floor(s/86400)+'d ago';
            return d.toLocaleDateString();
        },
        scoreBg(s){ s=Number(s||0); return s>=70?'bg-rose-600':s>=40?'bg-amber-500':'bg-emerald-500'; },
        scoreText(s){ s=Number(s||0); return s>=70?'text-rose-600':s>=40?'text-amber-600':'text-ink-700'; },
        parseFlags(j){
            if (!j) return [];
            try { const a = typeof j==='string' ? JSON.parse(j) : j; return Array.isArray(a) ? a : []; } catch { return []; }
        },
        humanFlag(f){ return String(f).replace(/_/g,' ').toLowerCase(); },
        smartTip(u){
            if (['NATIONAL_ADMIN','PHEOC_OFFICER','POE_ADMIN','DISTRICT_SUPERVISOR'].includes(u.role_key) && !u.two_factor_confirmed_at) return 'Privileged role without MFA — force reset';
            if ((u.risk_score||0) >= 80 && Number(u.is_active)) return 'Very high score — consider interim suspension';
            if ((this.parseFlags(u.risk_flags_json)).includes('FREQUENT_FAILED_LOGINS')) return 'Possible credential stuffing — check IP diversity';
            return '';
        },
        showToast(text, tone='info'){
            this.toast.text=text; this.toast.tone=tone; this.toast.open=true;
            clearTimeout(this.toast._t);
            this.toast._t = setTimeout(()=>this.toast.open=false, 3500);
        },
    };
}
</script>
@endpush
@endsection

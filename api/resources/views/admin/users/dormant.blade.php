{{-- ============================================================================
  /admin/users/dormant — Dormant Accounts
  ----------------------------------------------------------------------------
  8-point bar applied:
    · Neat two-line list, card stacks below sm:
    · Aggressive search + role filter + debounced reload
    · Mobile-first
    · KPI strip: 14d / 30d / 90d / Never-active
    · Hardcoded AI: "12 SCREENERs dormant 30d — POE staffing gap at X/Y"
    · Tabs: Dormant (threshold) | Never accepted invite | Never signed in
    · Insane validation: bulk suspend requires reason, confirm before sending
      reminder emails
    · Smart guidance: per-user "pending invite expired — re-issue" / "never logged
      in since creation 14d ago"

  API (all under /api/v2/admin/users):
    GET  /report/dormant?days=N      { users: [...], days: N }
    GET  /?status=pending            pending invitation list
    POST /{id}/suspend               reason:…
    POST /{id}/reset-password        (also re-issues invitation for pending users)
    POST /bulk                       { ids, action:'suspend', reason:… }
============================================================================ --}}
@extends('admin.layout')

@section('title', 'Dormant Accounts')
@section('heading', 'Dormant Accounts')
@section('subheading', 'Inactive users · stale invitations · never-signed-in accounts')

@section('breadcrumbs')
    <a href="{{ url('/admin/dashboard') }}" class="hover:text-ink-600 truncate">Command Centre</a>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <a href="{{ url('/admin/users') }}" class="hover:text-ink-600 truncate">Users</a>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-ink-600 font-medium truncate">Dormant</span>
@endsection

@push('head')
<style>
    .row-hover:hover{background:rgb(245 247 251/.6)}
    .tab-btn[aria-selected=true]{color:#111a33;border-color:#2f7dff;font-weight:600}
    .tab-btn[aria-selected=true] .tab-dot{background:#2f7dff;color:#fff}
    .pill-btn[aria-pressed=true]{background:#111a33;color:#fff;border-color:#111a33}
</style>
@endpush

@section('content')
<div
    x-data="dormantPage({
        actorScope: @js($actorScope),
        roles: @js($roles),
        api: '{{ url('/api/v2/admin/users') }}',
    })"
    x-init="init()"
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
    <div class="rounded-xl border border-brand-200 bg-gradient-to-br from-brand-50/60 via-white to-white p-3 sm:p-4">
        <div class="flex items-start gap-3">
            <div class="h-9 w-9 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 grid place-items-center text-white shadow-glow shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-brand-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500 animate-pulse-dot"></span>
                    PHEOC Copilot · dormancy brief
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

    {{-- ═══ TABS + THRESHOLD PILLS ═════════════════════════════════════ --}}
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

        {{-- Threshold + search bar (only for Dormant tab) --}}
        <div x-show="tab==='dormant'" class="p-3 border-b border-ink-100 bg-ink-50/30 flex flex-wrap gap-2 items-center">
            <div class="flex items-center gap-1 flex-wrap">
                <span class="text-[10px] uppercase tracking-[0.1em] font-bold text-ink-500 mr-1">Threshold</span>
                <template x-for="d in [7, 14, 30, 60, 90]" :key="d">
                    <button class="pill-btn h-8 px-2.5 rounded-lg border border-ink-200 bg-white text-xs font-semibold text-ink-700 hover:border-ink-300"
                            :aria-pressed="filters.days === d"
                            @click="filters.days = d; reloadDormant()">
                        <span x-text="d + 'd'"></span>
                    </button>
                </template>
            </div>
            <div class="flex-1 min-w-[200px] relative">
                <svg class="h-4 w-4 text-ink-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z"/></svg>
                <input type="search" x-model.debounce.300ms="filters.search"
                       placeholder="Search dormant user…"
                       class="w-full h-9 pl-10 pr-3 rounded-lg border border-ink-200 bg-white text-sm placeholder-ink-400 focus:border-brand-500 focus:ring-0">
            </div>
            <select x-model="filters.role_key" class="h-9 rounded-lg border border-ink-200 bg-white text-xs sm:text-sm focus:border-brand-500 focus:ring-0 px-3">
                <option value="">All roles</option>
                <template x-for="r in roles" :key="r.role_key"><option :value="r.role_key" x-text="r.display_name"></option></template>
            </select>
            <button @click="selectAllVisible()" class="h-9 px-3 rounded-lg border border-ink-200 bg-white hover:border-ink-300 text-xs font-semibold text-ink-700">Select all</button>
            <button @click="confirmBulkSuspend()" :disabled="selected.size===0" class="h-9 px-3 rounded-lg bg-rose-600 hover:bg-rose-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-semibold">
                Suspend <span x-show="selected.size">(<span x-text="selected.size"></span>)</span>
            </button>
        </div>

        {{-- ─── TAB: DORMANT ────────────────────────────────────────── --}}
        <div x-show="tab==='dormant'" x-cloak>
            <template x-if="loading && filteredDormant.length === 0">
                <div class="p-6 space-y-2">
                    <template x-for="i in 4" :key="i"><div class="h-12 bg-ink-50 rounded shimmer"></div></template>
                </div>
            </template>
            <template x-if="!loading && filteredDormant.length === 0">
                <div class="p-10 text-center">
                    <div class="mx-auto h-12 w-12 rounded-xl bg-emerald-50 text-emerald-500 grid place-items-center mb-2">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="text-sm font-semibold text-ink-700">Nobody dormant at this threshold</div>
                    <div class="text-xs text-ink-500 mt-1">Try a tighter threshold (7d) to see edge cases.</div>
                </div>
            </template>
            <ul class="divide-y divide-ink-100" x-show="!loading && filteredDormant.length > 0">
                <template x-for="u in paged(filteredDormant)" :key="u.id">
                    <li class="px-3 sm:px-4 py-2.5 row-hover">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" class="mt-2 rounded border-ink-300"
                                   :checked="selected.has(u.id)"
                                   @change="toggleSelect(u.id, $event.target.checked)">
                            <div class="h-9 w-9 rounded-lg bg-gradient-to-br from-ink-300 to-ink-500 grid place-items-center text-white text-xs font-bold shrink-0"
                                 x-text="initials(u.full_name||u.email)"></div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-ink-900 truncate" x-text="u.full_name||u.email"></div>
                                <div class="text-[11px] text-ink-500 font-mono truncate" x-text="u.email||u.username"></div>
                                <div class="mt-1 flex flex-wrap gap-1 items-center">
                                    <span class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-ink-100 text-ink-700 border border-ink-200" x-text="u.role_key"></span>
                                    <span class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-brand-50 text-brand-700 border border-brand-200" x-text="u.country_code"></span>
                                </div>
                                <div class="mt-1 text-[10px] text-ink-500">
                                    <template x-if="u.last_activity_at">
                                        <span>Last activity <span class="font-semibold text-ink-700" x-text="relTime(u.last_activity_at)"></span> · created <span x-text="relTime(u.created_at)"></span></span>
                                    </template>
                                    <template x-if="!u.last_activity_at">
                                        <span class="text-rose-600 font-semibold">Never active · created <span x-text="relTime(u.created_at)"></span></span>
                                    </template>
                                </div>
                                <template x-if="smartTip(u)">
                                    <div class="mt-1 inline-flex items-center gap-1 text-[10px] text-brand-700 font-semibold">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        <span x-text="smartTip(u)"></span>
                                    </div>
                                </template>
                            </div>
                            <div class="shrink-0 flex flex-col sm:flex-row gap-1">
                                <button x-show="u.email" @click="doReminder(u)" class="text-[10px] px-1.5 py-0.5 rounded border border-brand-200 bg-brand-50 hover:bg-brand-100 text-brand-700 font-semibold">Reminder</button>
                                <button @click="confirmSuspend(u)" class="text-[10px] px-1.5 py-0.5 rounded border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold">Suspend</button>
                                <a :href="'{{ url('/admin/users') }}?focus=' + u.id" class="text-[10px] px-1.5 py-0.5 rounded border border-ink-200 bg-white hover:bg-ink-50 text-ink-700 font-semibold">Open</a>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
            <div class="border-t border-ink-100 px-3 sm:px-4 py-2 flex items-center justify-between text-xs text-ink-500"
                 x-show="filteredDormant.length > 0">
                <div>
                    <span x-text="(filters.page-1)*filters.perPage + 1"></span>–<span x-text="Math.min(filters.page*filters.perPage, filteredDormant.length)"></span>
                    of <span class="font-semibold text-ink-700" x-text="filteredDormant.length"></span>
                </div>
                <div class="flex items-center gap-1">
                    <select x-model.number="filters.perPage" class="h-7 rounded border border-ink-200 text-xs px-1"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option></select>
                    <button @click="filters.page = Math.max(1, filters.page-1)" :disabled="filters.page <= 1" class="h-7 px-2 rounded border border-ink-200 bg-white hover:border-ink-300 disabled:opacity-40">Prev</button>
                    <button @click="filters.page++" :disabled="filters.page*filters.perPage >= filteredDormant.length" class="h-7 px-2 rounded border border-ink-200 bg-white hover:border-ink-300 disabled:opacity-40">Next</button>
                </div>
            </div>
        </div>

        {{-- ─── TAB: PENDING INVITES ────────────────────────────────── --}}
        <div x-show="tab==='pending'" x-cloak>
            <template x-if="pending.length === 0">
                <div class="p-10 text-center text-sm text-ink-500 italic">No pending invitations.</div>
            </template>
            <ul class="divide-y divide-ink-100" x-show="pending.length > 0">
                <template x-for="u in pending" :key="u.id">
                    <li class="px-3 sm:px-4 py-2.5">
                        <div class="flex items-start gap-3">
                            <div class="h-9 w-9 rounded-lg bg-amber-100 text-amber-700 grid place-items-center text-xs font-bold shrink-0" x-text="initials(u.full_name||u.email)"></div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-ink-900 truncate" x-text="u.full_name||u.email"></div>
                                <div class="text-[11px] text-ink-500 font-mono truncate" x-text="u.email||u.username"></div>
                                <div class="text-[10px] text-ink-500 mt-1">
                                    Invited <span x-text="relTime(u.created_at)"></span>
                                    <span class="ml-1 text-amber-700 font-semibold">· not yet accepted</span>
                                </div>
                            </div>
                            <button @click="doReminder(u, true)" class="text-[11px] px-2 py-1 rounded border border-brand-200 bg-brand-50 hover:bg-brand-100 text-brand-700 font-semibold shrink-0">Re-issue</button>
                        </div>
                    </li>
                </template>
            </ul>
        </div>

        {{-- ─── TAB: NEVER SIGNED IN ────────────────────────────────── --}}
        <div x-show="tab==='never'" x-cloak>
            <template x-if="filteredNever.length === 0">
                <div class="p-10 text-center text-sm text-ink-500 italic">Every user has signed in at least once.</div>
            </template>
            <ul class="divide-y divide-ink-100" x-show="filteredNever.length > 0">
                <template x-for="u in filteredNever" :key="u.id">
                    <li class="px-3 sm:px-4 py-2.5">
                        <div class="flex items-start gap-3">
                            <div class="h-9 w-9 rounded-lg bg-rose-100 text-rose-700 grid place-items-center text-xs font-bold shrink-0" x-text="initials(u.full_name||u.email)"></div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-ink-900 truncate" x-text="u.full_name||u.email"></div>
                                <div class="text-[11px] text-ink-500 font-mono truncate" x-text="u.email||u.username"></div>
                                <div class="text-[10px] text-rose-600 font-semibold mt-1">Never logged in · created <span x-text="relTime(u.created_at)"></span></div>
                            </div>
                            <button @click="doReminder(u)" class="text-[11px] px-2 py-1 rounded border border-brand-200 bg-brand-50 hover:bg-brand-100 text-brand-700 font-semibold shrink-0">Reminder</button>
                            <button @click="confirmSuspend(u)" class="text-[11px] px-2 py-1 rounded border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold shrink-0">Suspend</button>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    {{-- Modal --}}
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
function dormantPage(seed){
    return {
        actorScope: seed.actorScope,
        roles: seed.roles,
        api: seed.api,

        tab: 'dormant',
        loading: false,
        dormant: [], pending: [], never: [],
        stats: null,
        filters: { days: 30, search: '', role_key: '', page: 1, perPage: 25 },
        selected: new Set(),

        modal: { open:false, title:'', body:'', needReason:false, reason:'', confirmLabel:'Confirm', danger:false, confirm:null },
        toast: { open:false, text:'', tone:'info', _t:null },

        get tabs(){
            return [
                { key:'dormant', label:'Dormant (' + this.filters.days + 'd)', count: this.filteredDormant.length },
                { key:'pending', label:'Pending invites', count: this.pending.length },
                { key:'never',   label:'Never signed in', count: this.filteredNever.length },
            ];
        },

        async init(){
            if (!localStorage.getItem('pheoc_token')) { this._bounceToLogin(); return; }
            await Promise.all([ this.reloadDormant(), this.reloadPending(), this.reloadNever(), this.reloadStats() ]);
        },

        // ── api ────────────────────────────────────────────────────────
        _token(){ return localStorage.getItem('pheoc_token') || ''; },
        _bounceToLogin(){
            const next = encodeURIComponent(location.pathname + location.search);
            localStorage.removeItem('pheoc_token'); localStorage.removeItem('pheoc_user');
            location.replace('{{ url('/admin/login') }}?next=' + next);
        },
        async _req(path, opts={}){
            const t = this._token();
            if (!t){ this._bounceToLogin(); return { status:401, body:{ok:false} }; }
            const res = await fetch(this.api + path, {
                method: opts.method || 'GET',
                headers: Object.assign({'Accept':'application/json','Content-Type':'application/json','Authorization':'Bearer '+t}, opts.headers||{}),
                body: opts.body != null ? JSON.stringify(opts.body) : undefined,
            });
            const ct = res.headers.get('content-type')||'';
            const body = ct.includes('json') ? await res.json() : { ok:false };
            if (res.status === 401) this._bounceToLogin();
            return { status: res.status, body };
        },

        async reloadDormant(){
            this.loading = true;
            const { status, body } = await this._req('/report/dormant?days=' + (this.filters.days||30));
            this.loading = false;
            if (status === 200 && body.ok) this.dormant = body.data.users || [];
        },
        async reloadPending(){
            const { status, body } = await this._req('?status=pending&limit=200');
            if (status === 200 && body.ok) this.pending = body.data.users || [];
        },
        async reloadNever(){
            // Subset of the dormant data where last_activity_at is null AND user ever logged in is false.
            // The dormant endpoint already returns NULL-last_activity users; we also check for last_login_at via /users list.
            const { status, body } = await this._req('?limit=500&sort=created_at&dir=desc');
            if (status === 200 && body.ok) {
                this.never = (body.data.users || []).filter(u => !u.last_login_at && Number(u.is_active));
            }
        },
        async reloadStats(){
            const { status, body } = await this._req('/stats');
            if (status === 200 && body.ok) this.stats = body.data;
        },

        // ── computed ───────────────────────────────────────────────────
        get filteredDormant(){
            const q = (this.filters.search||'').toLowerCase().trim();
            return this.dormant.filter(u => {
                if (this.filters.role_key && u.role_key !== this.filters.role_key) return false;
                if (!q) return true;
                return (u.full_name||'').toLowerCase().includes(q)
                    || (u.email||'').toLowerCase().includes(q)
                    || (u.username||'').toLowerCase().includes(q);
            });
        },
        get filteredNever(){ return this.never.slice(0, 200); },
        paged(list){ const s=(this.filters.page-1)*this.filters.perPage; return list.slice(s, s+this.filters.perPage); },

        kpis(){
            const total = this.filteredDormant.length;
            const never = this.dormant.filter(u => !u.last_activity_at).length;
            const screeners = this.dormant.filter(u => u.role_key === 'SCREENER').length;
            const admins    = this.dormant.filter(u => ['NATIONAL_ADMIN','PHEOC_OFFICER','DISTRICT_SUPERVISOR','POE_ADMIN'].includes(u.role_key)).length;
            return [
                { label: this.filters.days+'d dormant', value: total, delta: total>0 ? 'risk' : 'ok',
                  hint: 'active but silent', tone: total>0 ? 'amber' : 'emerald' },
                { label: 'Never active', value: never, delta: never>0 ? 'stale' : 'ok',
                  hint: 'no activity since creation', tone: never>0 ? 'rose' : 'emerald' },
                { label: 'SCREENER dormant', value: screeners, delta: screeners>0 ? 'gap' : 'ok',
                  hint: 'POE staffing exposure', tone: screeners>0 ? 'rose' : 'emerald' },
                { label: 'Admin dormant', value: admins, delta: admins>0 ? 'review' : 'ok',
                  hint: 'privileged + silent', tone: admins>0 ? 'rose' : 'emerald' },
            ];
        },

        // ── hardcoded AI narrator ──────────────────────────────────────
        get copilot(){
            const sents = []; const actions = [];
            const total = this.filteredDormant.length;
            const never = this.dormant.filter(u => !u.last_activity_at);
            const screeners = this.dormant.filter(u => u.role_key === 'SCREENER');
            const admins = this.dormant.filter(u => ['NATIONAL_ADMIN','PHEOC_OFFICER','DISTRICT_SUPERVISOR','POE_ADMIN'].includes(u.role_key));

            if (total === 0) {
                sents.push(`No users dormant at the ${this.filters.days}-day threshold. Workforce engagement is healthy — try 14d for a tighter signal.`);
                return { sentences: sents, actions };
            }
            sents.push(`${total} account${total>1?'s are':' is'} dormant at the ${this.filters.days}-day threshold.`);
            if (screeners.length > 0) {
                sents.push(`${screeners.length} dormant account${screeners.length>1?'s are':' is a'} SCREENER — POE operational gap. Recommend cross-check against the POE staffing register before suspending.`);
            }
            if (admins.length > 0) {
                sents.push(`${admins.length} dormant privileged account${admins.length>1?'s':''} (admin/officer/supervisor) — privileged dormancy is a credential-theft vector. Either reactivate with MFA or suspend.`);
                actions.push({ label: 'Suspend all dormant admins', run: () => this.bulkSuspendIds(admins.map(u=>u.id), 'Dormant privileged account') });
            }
            if (never.length > 0) {
                sents.push(`${never.length} account${never.length>1?'s have':' has'} never been active since creation — reminder email or invitation re-issue recommended.`);
            }
            if (this.pending.length > 0) {
                sents.push(`${this.pending.length} invitation${this.pending.length>1?'s':''} still unaccepted — check the "Pending invites" tab to re-issue.`);
            }
            return { sentences: sents, actions };
        },

        // ── selection ─────────────────────────────────────────────────
        toggleSelect(id, on){
            if (on) this.selected.add(id); else this.selected.delete(id);
            this.selected = new Set(this.selected);
        },
        selectAllVisible(){
            for (const u of this.paged(this.filteredDormant)) this.selected.add(u.id);
            this.selected = new Set(this.selected);
        },

        // ── actions ────────────────────────────────────────────────────
        async doReminder(u, isPending = false){
            if (!u.email) { this.showToast('User has no email on file', 'error'); return; }
            this.modal = {
                open:true, title: isPending ? 'Re-issue invitation?' : 'Send sign-in reminder?',
                body: isPending
                    ? `A fresh invitation email will be sent to "${u.email}" with a new 7-day signed link.`
                    : `A password-reset email will be sent to "${u.email}" — the user can use the link to regain access immediately.`,
                needReason:false, danger:false, confirmLabel: isPending ? 'Re-issue' : 'Send reminder',
                confirm: async () => {
                    this.modal.open = false;
                    const { status, body } = await this._req('/' + u.id + '/reset-password', { method:'POST' });
                    if (status === 200 && body.ok) this.showToast('Email sent');
                    else this.showToast(body.error || 'Send failed', 'error');
                },
            };
        },
        confirmSuspend(u){
            this.modal = {
                open:true, title:'Suspend account?',
                body:`Suspends "${u.full_name||u.username}". Sessions + trusted devices are revoked, user is emailed. Reversible via reactivate.`,
                needReason:true, reason:'', danger:false, confirmLabel:'Suspend',
                confirm: async () => {
                    if (!this.modal.reason || this.modal.reason.trim().length < 3) return;
                    this.modal.open = false;
                    const { status, body } = await this._req('/' + u.id + '/suspend', { method:'POST', body:{ reason: this.modal.reason.trim() } });
                    if (status === 200 && body.ok) { this.showToast('Suspended'); this.reloadDormant(); }
                    else this.showToast(body.error || 'Failed', 'error');
                },
            };
        },
        confirmBulkSuspend(){
            if (this.selected.size === 0) return;
            const ids = Array.from(this.selected);
            this.modal = {
                open:true, title:`Suspend ${ids.length} dormant account${ids.length>1?'s':''}?`,
                body:'Runs suspend in sequence, server-side, with full audit. Self-suspend is blocked automatically.',
                needReason:true, reason:'', danger:true, confirmLabel:'Suspend all',
                confirm: async () => {
                    if (!this.modal.reason.trim()) return;
                    this.modal.open = false;
                    const { status, body } = await this._req('/bulk', { method:'POST', body:{ ids, action:'suspend', reason: this.modal.reason.trim() } });
                    if (status === 200 && body.ok) {
                        this.showToast('Affected: ' + (body.data.affected || 0));
                        this.selected.clear(); this.selected = new Set(this.selected);
                        this.reloadDormant();
                    } else this.showToast(body.error || 'Bulk failed', 'error');
                },
            };
        },
        async bulkSuspendIds(ids, reason){
            this.modal = {
                open:true, title:`Suspend ${ids.length} account${ids.length>1?'s':''}?`,
                body:'Copilot-recommended bulk action. Review the reason, then confirm.',
                needReason:true, reason: reason || '', danger:true, confirmLabel:'Suspend all',
                confirm: async () => {
                    if (!this.modal.reason.trim()) return;
                    this.modal.open = false;
                    const { status, body } = await this._req('/bulk', { method:'POST', body:{ ids, action:'suspend', reason: this.modal.reason.trim() } });
                    if (status === 200 && body.ok) { this.showToast('Affected: ' + (body.data.affected || 0)); this.reloadDormant(); }
                    else this.showToast(body.error || 'Bulk failed', 'error');
                },
            };
        },

        // ── formatters ─────────────────────────────────────────────────
        initials(s){ return (s||'?').trim().split(/\s+/).map(x=>x[0]).slice(0,2).join('').toUpperCase()||'?'; },
        relTime(v){
            if (!v) return '—';
            const d = new Date(String(v).replace(' ','T') + (String(v).includes('T')?'':'Z'));
            if (isNaN(d)) return String(v);
            const s = Math.floor((Date.now()-d.getTime())/1000);
            if (s<60) return 'just now'; if (s<3600) return Math.floor(s/60)+'m ago';
            if (s<86400) return Math.floor(s/3600)+'h ago'; if (s<2592000) return Math.floor(s/86400)+'d ago';
            if (s<31536000) return Math.floor(s/2592000)+'mo ago';
            return d.toLocaleDateString();
        },
        smartTip(u){
            if (!u.last_activity_at && u.role_key === 'SCREENER') return 'Never screened — verify POE posting';
            if (!u.last_activity_at) return 'Never signed in — reminder recommended';
            if (['NATIONAL_ADMIN','PHEOC_OFFICER','POE_ADMIN'].includes(u.role_key)) return 'Privileged account dormant — high risk';
            return '';
        },
        showToast(t, tone='info'){ this.toast.text=t; this.toast.tone=tone; this.toast.open=true; clearTimeout(this.toast._t); this.toast._t=setTimeout(()=>this.toast.open=false, 3500); },
    };
}
</script>
@endpush
@endsection

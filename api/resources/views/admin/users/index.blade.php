{{-- ============================================================================
  /admin/users — Team members
  ----------------------------------------------------------------------------
  "Everyone who can sign in to PHEOC Uganda." Plain-language CRUD.
  Create is a 4-step wizard: Who → Role → Where → Sign-in.
  View/edit opens in a sheet. Every enum label is humanised.
============================================================================ --}}
@extends('admin.layout')

@section('title', 'Team members')
@section('subtitle', 'Everyone who can sign in to PHEOC Uganda')

@section('page_actions')
    <button type="button" class="btn btn-default btn-xs" @click="$dispatch('invite:open')">
        <svg class="mr-1.5 h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add someone
    </button>
@endsection

@section('content')
<div x-data="teamView({
        roles: @js($roles),
        pheocNames: @js($pheocNames),
        districtNames: @js($districtNames),
        poeNames: @js($poeNames),
        pheocDistricts: @js($pheocDistricts),
        districtPoes: @js($districtPoes),
        roleGeoRequirements: @js($roleGeoRequirements),
     })"
     x-init="init()"
     @invite:open.window="openInvite()"
     class="space-y-5 max-w-4xl">

    {{-- Summary strip --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <template x-for="k in kpis" :key="k.label">
            <div class="card">
                <div class="card-header pb-2"><p class="description" x-text="k.label"></p></div>
                <div class="card-content">
                    <template x-if="loading"><div class="skeleton h-6 w-8"></div></template>
                    <template x-if="!loading">
                        <div class="text-xl font-semibold tracking-tight tabular-nums" x-text="k.value"></div>
                    </template>
                    <p class="help-text mt-1" x-text="k.hint"></p>
                </div>
            </div>
        </template>
    </div>

    {{-- Filter bar --}}
    <div class="flex flex-col sm:flex-row gap-2">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z"/></svg>
            <input type="search" x-model.debounce.300ms="filters.search" @input="reload()"
                   placeholder="Search by name, email, or phone"
                   class="input pl-9">
        </div>
        <select x-model="filters.role_key" @change="reload()" class="select sm:max-w-[180px]">
            <option value="">Anyone</option>
            <template x-for="r in roles" :key="r.role_key">
                <option :value="r.role_key" x-text="humanRole(r.role_key)"></option>
            </template>
        </select>
        <select x-model="filters.status" @change="reload()" class="select sm:max-w-[160px]">
            <option value="">Any status</option>
            <option value="active">Active</option>
            <option value="pending">Waiting to accept</option>
            <option value="suspended">Suspended</option>
        </select>
    </div>

    {{-- Empty / loading / list --}}
    <template x-if="loading && users.length === 0">
        <div class="space-y-2">
            <template x-for="i in 4" :key="i">
                <div class="card"><div class="card-content p-3 flex items-center gap-3"><div class="skeleton h-9 w-9 rounded-full"></div><div class="flex-1 space-y-1.5"><div class="skeleton h-4 w-1/2"></div><div class="skeleton h-3 w-1/3"></div></div></div></div>
            </template>
        </div>
    </template>

    <template x-if="!loading && users.length === 0 && !error">
        <div class="card">
            <div class="card-content p-10 text-center space-y-2">
                <div class="mx-auto h-10 w-10 rounded-full bg-muted text-muted-foreground flex items-center justify-center">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a3 3 0 015.356-1.857M17 8a3 3 0 11-6 0 3 3 0 016 0zM7 8a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <p class="text-sm font-medium">Nobody matches those filters.</p>
                <button class="btn btn-link btn-xs" @click="resetFilters()">Clear filters</button>
            </div>
        </div>
    </template>

    <template x-if="error">
        <div class="alert alert-destructive">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div><h4 class="alert-title">We couldn't load the team list</h4><div class="alert-description" x-text="error"></div></div>
        </div>
    </template>

    {{-- List --}}
    <ul class="space-y-2" x-show="!loading && users.length > 0">
        <template x-for="u in users" :key="u.id">
            <li>
                <button type="button" class="card w-full text-left hover:bg-accent transition-colors" @click="openView(u)">
                    <div class="card-content p-3 flex items-center gap-3">
                        <span class="h-9 w-9 rounded-full bg-muted text-muted-foreground text-[11px] font-semibold flex items-center justify-center shrink-0" x-text="initials(u.full_name || u.email)"></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <p class="text-sm font-medium truncate" x-text="u.full_name || u.email || 'Unnamed'"></p>
                                <template x-if="statusOf(u) !== 'active'">
                                    <span class="badge badge-outline" x-text="statusLabel(statusOf(u))"></span>
                                </template>
                            </div>
                            <p class="description truncate mt-0.5">
                                <span x-text="humanRole(u.role_key)"></span>
                                <template x-if="u.email"><span> · <span class="font-mono text-[11px]" x-text="u.email"></span></span></template>
                            </p>
                        </div>
                        <svg class="h-4 w-4 text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </button>
            </li>
        </template>
    </ul>

    {{-- Pagination --}}
    <div class="flex items-center justify-between text-xs text-muted-foreground" x-show="!loading && total > 0">
        <span x-text="`Showing ${(filters.offset + 1)}–${Math.min(filters.offset + filters.limit, total)} of ${total}`"></span>
        <div class="flex items-center gap-1">
            <button class="btn btn-outline btn-xs" @click="prevPage()" :disabled="filters.offset === 0">Previous</button>
            <button class="btn btn-outline btn-xs" @click="nextPage()" :disabled="filters.offset + filters.limit >= total">Next</button>
        </div>
    </div>

</div>

{{-- ═══════════════════════════════════════════════════════════════
     INVITE WIZARD · 4 steps, plain language
     ═══════════════════════════════════════════════════════════════ --}}
<template x-if="wiz.open">
<div>
    <div class="sheet-overlay" @click="wiz.open = false"></div>
    <aside class="sheet-content sheet-right flex flex-col" role="dialog" aria-modal="true">
        <div class="sheet-header">
            <div class="flex gap-1 mb-2">
                <template x-for="s in [1,2,3,4]" :key="s">
                    <div class="h-1 flex-1 rounded-full" :class="s <= wiz.step ? 'bg-primary' : 'bg-muted'"></div>
                </template>
            </div>
            <h3 class="sheet-title" x-text="wizTitle"></h3>
            <p class="sheet-description" x-text="wizHint"></p>
        </div>

        <div class="flex-1 overflow-y-auto py-4 space-y-4">
            {{-- STEP 1 — who? --}}
            <template x-if="wiz.step === 1">
                <div class="space-y-3">
                    <div class="space-y-1.5">
                        <label class="label">Full name</label>
                        <input class="input" x-model="wiz.form.full_name" autofocus placeholder="e.g. Judith Namugga">
                    </div>
                    <div class="space-y-1.5">
                        <label class="label">Username (letters, numbers, dots)</label>
                        <input class="input font-mono" x-model="wiz.form.username" placeholder="j.namugga">
                    </div>
                    <div class="space-y-1.5">
                        <label class="label">Work email</label>
                        <input class="input" type="email" x-model="wiz.form.email" placeholder="you@health.go.ug">
                        <p class="help-text">If you leave this blank, they can only sign in with their username + password.</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="label">Phone (optional)</label>
                        <input class="input" type="tel" x-model="wiz.form.phone" placeholder="+256 7…">
                    </div>
                    <p class="help-text text-destructive" x-show="wiz.err" x-text="wiz.err"></p>
                </div>
            </template>

            {{-- STEP 2 — role? --}}
            <template x-if="wiz.step === 2">
                <div class="space-y-3">
                    <div class="space-y-1.5">
                        <template x-for="r in roles" :key="r.role_key">
                            <button type="button"
                                    class="w-full text-left rounded-md border px-3 py-2 hover:bg-accent transition-colors"
                                    :class="wiz.form.role_key === r.role_key ? 'border-primary ring-1 ring-primary' : ''"
                                    @click="wiz.form.role_key = r.role_key">
                                <p class="text-sm font-medium" x-text="humanRole(r.role_key)"></p>
                                <p class="help-text" x-text="roleDescription(r.role_key)"></p>
                            </button>
                        </template>
                    </div>
                    <p class="help-text text-destructive" x-show="wiz.err" x-text="wiz.err"></p>
                </div>
            </template>

            {{-- STEP 3 — where? (cascades) --}}
            <template x-if="wiz.step === 3">
                <div class="space-y-3">
                    <template x-if="geoRequirements.length === 0">
                        <p class="description">This role works across the whole country. You can still assign them to a specific region if you want.</p>
                    </template>
                    <div class="space-y-1.5">
                        <label class="label">
                            <span>Region</span>
                            <template x-if="needsGeo('province_or_pheoc')"><span class="text-destructive ml-0.5">*</span></template>
                        </label>
                        <select class="select" x-model="wiz.form.pheoc_code"
                                @change="wiz.form.district_code=''; wiz.form.poe_code=''">
                            <option value="">— None —</option>
                            <template x-for="p in Object.keys(pheocDistricts).sort()" :key="p">
                                <option :value="p" x-text="p"></option>
                            </template>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="label">
                            <span>District</span>
                            <template x-if="needsGeo('district_code')"><span class="text-destructive ml-0.5">*</span></template>
                        </label>
                        <select class="select" x-model="wiz.form.district_code"
                                @change="wiz.form.poe_code=''"
                                :disabled="!wiz.form.pheoc_code">
                            <option value="">
                                <template x-if="!wiz.form.pheoc_code">— Pick a region first —</template>
                                <template x-if="wiz.form.pheoc_code">— None —</template>
                            </option>
                            <template x-for="d in districtsFor(wiz.form.pheoc_code)" :key="d">
                                <option :value="d" x-text="d"></option>
                            </template>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="label">
                            <span>Port of entry</span>
                            <template x-if="needsGeo('poe_code')"><span class="text-destructive ml-0.5">*</span></template>
                        </label>
                        <select class="select" x-model="wiz.form.poe_code" :disabled="!wiz.form.district_code">
                            <option value="">
                                <template x-if="!wiz.form.district_code">— Pick a district first —</template>
                                <template x-if="wiz.form.district_code">— None —</template>
                            </option>
                            <template x-for="p in poesFor(wiz.form.district_code)" :key="p">
                                <option :value="p" x-text="p"></option>
                            </template>
                        </select>
                    </div>
                    <p class="help-text text-destructive" x-show="wiz.err" x-text="wiz.err"></p>
                </div>
            </template>

            {{-- STEP 4 — sign-in --}}
            <template x-if="wiz.step === 4">
                <div class="space-y-3">
                    <div class="space-y-1.5">
                        <label class="flex items-start gap-2 rounded-md border p-3 cursor-pointer" :class="wiz.form._credMode === 'invite' ? 'border-primary ring-1 ring-primary' : ''">
                            <input type="radio" value="invite" x-model="wiz.form._credMode" class="mt-0.5">
                            <div>
                                <p class="text-sm font-medium">Send them an email invitation</p>
                                <p class="help-text">They click a link in the email and set their own password. Recommended.</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-2 rounded-md border p-3 cursor-pointer" :class="wiz.form._credMode === 'direct' ? 'border-primary ring-1 ring-primary' : ''">
                            <input type="radio" value="direct" x-model="wiz.form._credMode" class="mt-0.5">
                            <div class="flex-1 space-y-2">
                                <div>
                                    <p class="text-sm font-medium">I'll set a password for them</p>
                                    <p class="help-text">Use this if they can't receive email. They'll be asked to change it on first sign-in.</p>
                                </div>
                                <input type="password" class="input" x-model="wiz.form.password"
                                       :disabled="wiz.form._credMode !== 'direct'"
                                       placeholder="At least 8 characters">
                            </div>
                        </label>
                    </div>
                    <p class="help-text text-destructive" x-show="wiz.err" x-text="wiz.err"></p>
                </div>
            </template>
        </div>

        <div class="sheet-footer pt-3 border-t">
            <button type="button" class="btn btn-ghost btn-xs" @click="wiz.open = false">Cancel</button>
            <template x-if="wiz.step > 1">
                <button type="button" class="btn btn-outline btn-xs" @click="wiz.step--; wiz.err=''">Back</button>
            </template>
            <template x-if="wiz.step < 4">
                <button type="button" class="btn btn-default btn-xs" @click="wizNext()">Next</button>
            </template>
            <template x-if="wiz.step === 4">
                <button type="button" class="btn btn-default btn-xs" @click="wizSubmit()" :disabled="wiz.saving">
                    <svg x-show="wiz.saving" class="mr-1.5 h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                    Add this person
                </button>
            </template>
        </div>
    </aside>
</div>
</template>

{{-- ═══════════════════════════════════════════════════════════════
     VIEW / EDIT / ACTION sheet
     ═══════════════════════════════════════════════════════════════ --}}
<template x-if="view.open && view.user">
<div>
    <div class="sheet-overlay" @click="view.open=false"></div>
    <aside class="sheet-content sheet-right flex flex-col" role="dialog" aria-modal="true">
        <div class="sheet-header">
            <div class="flex items-center gap-3">
                <span class="h-10 w-10 rounded-full bg-muted text-muted-foreground text-sm font-semibold flex items-center justify-center shrink-0" x-text="initials(view.user.full_name || view.user.email)"></span>
                <div class="min-w-0">
                    <h3 class="sheet-title truncate" x-text="view.user.full_name || view.user.email"></h3>
                    <p class="sheet-description" x-text="humanRole(view.user.role_key)"></p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 mt-2">
                <span class="badge" :class="statusBadgeClass(statusOf(view.user))" x-text="statusLabel(statusOf(view.user))"></span>
                <template x-if="view.user.two_factor_confirmed_at">
                    <span class="badge badge-outline">Two-step sign-in on</span>
                </template>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto py-4 space-y-5">

            {{-- Details --}}
            <section class="space-y-2">
                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">Details</p>
                <dl class="rounded-md border divide-y">
                    <template x-for="r in detailRows" :key="r.label">
                        <div class="flex items-start justify-between gap-3 px-3 py-2 text-sm">
                            <dt class="text-muted-foreground shrink-0" x-text="r.label"></dt>
                            <dd class="text-right break-all" x-text="r.value || '—'"></dd>
                        </div>
                    </template>
                </dl>
            </section>

            {{-- What you can do --}}
            <section class="space-y-2">
                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">What would you like to do?</p>
                <div class="grid gap-1.5">
                    <template x-if="statusOf(view.user) === 'suspended'">
                        <button class="btn btn-outline justify-start" @click="confirm('reactivate')">
                            <span>Let them sign in again</span>
                        </button>
                    </template>
                    <template x-if="statusOf(view.user) !== 'suspended'">
                        <button class="btn btn-outline justify-start" @click="confirm('suspend')">
                            <span>Stop them from signing in</span>
                        </button>
                    </template>
                    <template x-if="view.user.email">
                        <button class="btn btn-outline justify-start" @click="confirm('reset')">
                            <span>Send them a password-reset link</span>
                        </button>
                    </template>
                    <template x-if="view.user.two_factor_confirmed_at">
                        <button class="btn btn-outline justify-start" @click="confirm('2fa')">
                            <span>Reset their two-step sign-in</span>
                        </button>
                    </template>
                    <button class="btn btn-outline justify-start" @click="confirm('delete')">
                        <span class="text-destructive">Remove this person</span>
                    </button>
                </div>
            </section>

            {{-- Confirm bar --}}
            <template x-if="view.confirm">
                <div class="alert">
                    <div>
                        <h4 class="alert-title" x-text="view.confirm.title"></h4>
                        <div class="alert-description mt-1" x-text="view.confirm.body"></div>
                        <template x-if="view.confirm.needReason">
                            <textarea class="textarea mt-2" rows="2" x-model="view.confirm.reason" placeholder="Reason (required)"></textarea>
                        </template>
                        <p class="help-text text-destructive" x-show="view.confirm.err" x-text="view.confirm.err"></p>
                        <div class="flex gap-2 mt-3 justify-end">
                            <button class="btn btn-ghost btn-xs" @click="view.confirm = null">Cancel</button>
                            <button class="btn btn-xs" :class="view.confirm.danger ? 'btn-destructive' : 'btn-default'"
                                    @click="runConfirmed()" :disabled="view.confirm.saving">
                                <span x-text="view.confirm.ctaLabel"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="sheet-footer pt-3 border-t">
            <button class="btn btn-ghost btn-xs" @click="view.open = false">Close</button>
        </div>
    </aside>
</div>
</template>

{{-- Toast --}}
<div x-show="toast.open" x-cloak x-transition
     class="fixed bottom-4 right-4 z-50 w-[calc(100vw-2rem)] sm:w-[340px]">
    <div class="toast">
        <div class="grid gap-0.5">
            <div class="toast-title" x-text="toast.title"></div>
            <div class="toast-description" x-text="toast.message"></div>
        </div>
        <button class="toast-action" @click="toast.open=false">OK</button>
    </div>
</div>

@push('scripts')
<script>
function teamView(seed){
    return {
        // seed
        roles: seed.roles,
        pheocNames: seed.pheocNames,
        districtNames: seed.districtNames,
        poeNames: seed.poeNames,
        pheocDistricts: seed.pheocDistricts || {},
        districtPoes: seed.districtPoes || {},
        roleGeoRequirements: seed.roleGeoRequirements || {},

        loading: true,
        users: [],
        stats: null,
        total: 0,
        error: null,
        filters: { search:'', role_key:'', status:'', limit:25, offset:0 },

        wiz: { open:false, step:1, saving:false, err:'', form: this._emptyForm() },
        view: { open:false, user:null, assignments:[], confirm:null },
        toast: { open:false, title:'', message:'', _t:null },

        _emptyForm(){
            return {
                full_name:'', username:'', email:'', phone:'',
                role_key:'', country_code:'UG',
                pheoc_code:'', district_code:'', poe_code:'',
                _credMode:'invite', password:'',
            };
        },

        async init(){
            if (!localStorage.getItem('pheoc_token')) {
                location.replace('{{ url('/admin/login') }}?next=' + encodeURIComponent(location.pathname));
                return;
            }
            await Promise.all([this.reload(), this.loadStats()]);
            this.loading = false;

            const focus = new URLSearchParams(location.search).get('focus');
            if (focus) this.openByFocusId(Number(focus));
        },

        _token(){ return localStorage.getItem('pheoc_token') || ''; },
        async _req(path, opts={}){
            const res = await fetch('{{ url('/api/v2/admin/users') }}' + path, {
                method: opts.method || 'GET',
                headers: {
                    'Accept':'application/json','Content-Type':'application/json',
                    'Authorization':'Bearer '+this._token(),
                },
                body: opts.body != null ? JSON.stringify(opts.body) : undefined,
            });
            const ct = res.headers.get('content-type')||'';
            const body = ct.includes('json') ? await res.json() : {};
            return { status: res.status, body };
        },

        async reload(){
            const qs = new URLSearchParams();
            if (this.filters.search)   qs.set('search', this.filters.search);
            if (this.filters.role_key) qs.set('role_key', this.filters.role_key);
            if (this.filters.status)   qs.set('status', this.filters.status);
            qs.set('limit', this.filters.limit);
            qs.set('offset', this.filters.offset);
            const { status, body } = await this._req('?' + qs.toString());
            if (status === 200 && body.ok) {
                this.users = body.data.users || [];
                this.total = body.data.total || 0;
            } else {
                this.error = body?.error || ('Load failed · ' + status);
            }
        },
        async loadStats(){
            const { status, body } = await this._req('/stats');
            if (status === 200 && body.ok) this.stats = body.data;
        },
        resetFilters(){ this.filters = {search:'',role_key:'',status:'',limit:25,offset:0}; this.reload(); },
        prevPage(){ if (this.filters.offset > 0) { this.filters.offset = Math.max(0, this.filters.offset - this.filters.limit); this.reload(); } },
        nextPage(){ if (this.filters.offset + this.filters.limit < this.total) { this.filters.offset += this.filters.limit; this.reload(); } },

        get kpis(){
            const s = (this.stats?.status) || {};
            return [
                { label:'On the team', value: s.total ?? '—', hint:'All accounts' },
                { label:'Active',      value: s.active ?? '—', hint:'Able to sign in' },
                { label:'Waiting',     value: s.pending_invite ?? '—', hint:'Sent invite, not accepted yet' },
                { label:'Suspended',   value: s.suspended ?? '—', hint:'Cannot sign in' },
            ];
        },

        // ── invite wizard ───────────────────────────────────────────
        openInvite(){
            this.wiz = { open:true, step:1, saving:false, err:'', form: this._emptyForm() };
        },
        get wizTitle(){ return ({1:'Who are they?',2:'What\'s their role?',3:'Where do they work?',4:'How should they sign in?'})[this.wiz.step]; },
        get wizHint(){ return ({
            1:'Enter their name, a username, and email if they have one.',
            2:'Pick the role that matches what they\'ll do day-to-day.',
            3:'We\'ll use this to decide what they can see and do.',
            4:'We recommend sending an email invitation so they set their own password.',
        })[this.wiz.step]; },
        get geoRequirements(){ return this.roleGeoRequirements[this.wiz.form.role_key] || []; },
        needsGeo(t){ return this.geoRequirements.includes(t); },
        districtsFor(p){ return p ? (this.pheocDistricts[p] || []).slice().sort() : []; },
        poesFor(d){ return d ? (this.districtPoes[d] || []).slice().sort() : []; },
        wizNext(){
            this.wiz.err = '';
            const f = this.wiz.form;
            if (this.wiz.step === 1) {
                if (!(f.full_name||'').trim() || (f.full_name||'').trim().length < 2) { this.wiz.err='Enter their full name (at least 2 letters).'; return; }
                if (!(f.username||'').trim() || !/^[a-zA-Z0-9._-]{4,}$/.test(f.username)) { this.wiz.err='Username needs at least 4 letters or numbers (dots, dashes, underscores allowed).'; return; }
                this.wiz.step = 2; return;
            }
            if (this.wiz.step === 2) {
                if (!f.role_key) { this.wiz.err='Pick a role to continue.'; return; }
                this.wiz.step = 3; return;
            }
            if (this.wiz.step === 3) {
                if (this.needsGeo('province_or_pheoc') && !f.pheoc_code) { this.wiz.err='This role needs a region.'; return; }
                if (this.needsGeo('district_code') && !f.district_code) { this.wiz.err='This role needs a district.'; return; }
                if (this.needsGeo('poe_code') && !f.poe_code) { this.wiz.err='This role needs a port of entry.'; return; }
                this.wiz.step = 4; return;
            }
        },
        async wizSubmit(){
            const f = this.wiz.form;
            if (f._credMode === 'direct' && (!f.password || f.password.length < 8)) {
                this.wiz.err = 'Password needs at least 8 characters.'; return;
            }
            this.wiz.err = ''; this.wiz.saving = true;
            const payload = {
                full_name: f.full_name.trim(),
                username: f.username.trim(),
                email: f.email || null,
                phone: f.phone || null,
                role_key: f.role_key,
                country_code: f.country_code || 'UG',
                is_active: true,
            };
            if (f.pheoc_code || f.district_code || f.poe_code || this.geoRequirements.length > 0) {
                payload.assignment = {
                    country_code: f.country_code || 'UG',
                    province_code: f.pheoc_code || null,
                    pheoc_code: f.pheoc_code || null,
                    district_code: f.district_code || null,
                    poe_code: f.poe_code || null,
                    is_primary: true, is_active: true,
                };
            }
            if (f._credMode === 'invite') payload.send_invitation = true;
            else payload.password = f.password;

            const { status, body } = await this._req('', { method:'POST', body: payload });
            this.wiz.saving = false;

            if ((status === 200 || status === 201) && body.ok) {
                this.wiz.open = false;
                this.showToast('Added to the team', f._credMode === 'invite'
                    ? `We've emailed an invitation to ${f.email}.`
                    : `They can sign in as ${f.username} now.`);
                await this.reload();
            } else if (status === 422) {
                const e = body.errors || {};
                const first = Object.values(e)[0];
                this.wiz.err = (Array.isArray(first) ? first[0] : String(first)) || 'Please check the details and try again.';
            } else {
                this.wiz.err = body?.error || 'We couldn\'t add this person. Try again in a moment.';
            }
        },

        // ── view/edit/confirm sheet ─────────────────────────────────
        async openView(u){
            this.view = { open:true, user:u, assignments:[], confirm:null };
            const { status, body } = await this._req('/' + u.id);
            if (status === 200 && body.ok) {
                this.view.user = body.data.user;
                this.view.assignments = body.data.assignments || [];
            }
        },
        async openByFocusId(id){
            const found = this.users.find(u => u.id === id);
            if (found) this.openView(found);
        },

        get detailRows(){
            const u = this.view.user || {};
            const a = (this.view.assignments || []).find(x => Number(x.is_primary) === 1 && Number(x.is_active) === 1) || {};
            return [
                { label:'Email',       value: u.email },
                { label:'Username',    value: u.username },
                { label:'Phone',       value: u.phone },
                { label:'Country',     value: u.country_code === 'UG' ? 'Uganda' : u.country_code },
                { label:'Region',      value: a.province_code || a.pheoc_code },
                { label:'District',    value: a.district_code },
                { label:'Port of entry', value: a.poe_code },
                { label:'Last signed in', value: u.last_login_at ? new Date(String(u.last_login_at).replace(' ','T')+'Z').toLocaleString() : 'Never' },
                { label:'Created',     value: u.created_at ? new Date(String(u.created_at).replace(' ','T')+'Z').toLocaleDateString() : '' },
            ].filter(r => r.value);
        },

        confirm(kind){
            const u = this.view.user;
            const confirms = {
                suspend: {
                    title:'Stop this person from signing in?',
                    body:'Their current session will end and they won\'t be able to sign in until you reactivate them. We\'ll send them a notice.',
                    needReason:true, ctaLabel:'Suspend', danger:false, kind:'suspend',
                },
                reactivate: {
                    title:'Let them sign in again?',
                    body:'Their account will go back to active and they can sign in with their existing credentials.',
                    needReason:false, ctaLabel:'Reactivate', danger:false, kind:'reactivate',
                },
                reset: {
                    title:'Send a password-reset link to ' + (u.email || 'them') + '?',
                    body:'We\'ll email a secure link they can use within 2 hours. Their current password will still work until they use the link.',
                    needReason:false, ctaLabel:'Send link', danger:false, kind:'reset',
                },
                '2fa': {
                    title:'Reset their two-step sign-in?',
                    body:'They\'ll be asked to set up their authenticator app again the next time they sign in. Use this if they lost their phone.',
                    needReason:false, ctaLabel:'Reset', danger:false, kind:'2fa',
                },
                delete: {
                    title:'Remove this person from the team?',
                    body:'They will no longer be able to sign in. Their records stay in the audit trail. You can bring them back later if needed.',
                    needReason:false, ctaLabel:'Remove', danger:true, kind:'delete',
                },
            };
            this.view.confirm = Object.assign({}, confirms[kind], { reason:'', err:'', saving:false });
        },
        async runConfirmed(){
            const c = this.view.confirm; if (!c) return;
            if (c.needReason && (!c.reason || c.reason.trim().length < 3)) { c.err = 'Please add a short reason.'; return; }
            c.err=''; c.saving=true;
            const u = this.view.user;
            const actions = {
                suspend:    { path: '/' + u.id + '/suspend',          method:'POST', body:{ reason: c.reason.trim() } },
                reactivate: { path: '/' + u.id + '/reactivate',       method:'POST' },
                reset:      { path: '/' + u.id + '/reset-password',   method:'POST' },
                '2fa':      { path: '/' + u.id + '/force-mfa-reset',  method:'POST' },
                delete:     { path: '/' + u.id,                       method:'DELETE' },
            };
            const a = actions[c.kind];
            const { status, body } = await this._req(a.path, { method: a.method, body: a.body });
            c.saving = false;
            if ((status === 200 || status === 204) && (body.ok !== false)) {
                const msgs = {
                    suspend:'They can no longer sign in.',
                    reactivate:'They can sign in again.',
                    reset:'We sent the password-reset email.',
                    '2fa':'Their two-step sign-in has been reset.',
                    delete:'They\'ve been removed from the team.',
                };
                this.showToast('Done', msgs[c.kind]);
                this.view.confirm = null;
                if (c.kind === 'delete') this.view.open = false;
                await Promise.all([this.reload(), this.loadStats()]);
                if (c.kind !== 'delete') await this.refreshView(u.id);
            } else {
                c.err = body?.error || 'We couldn\'t complete that. Try again in a moment.';
            }
        },
        async refreshView(id){
            const { status, body } = await this._req('/' + id);
            if (status === 200 && body.ok) this.view.user = body.data.user;
        },

        // ── language helpers ────────────────────────────────────────
        initials(s){ return (s||'?').trim().split(/\s+/).map(x=>x[0]).slice(0,2).join('').toUpperCase() || '?'; },
        humanRole(key){ return ({
            NATIONAL_ADMIN:'National administrator',
            PHEOC_OFFICER:'PHEOC officer',
            DISTRICT_SUPERVISOR:'District supervisor',
            POE_ADMIN:'Port-of-entry administrator',
            POE_OFFICER:'Port-of-entry officer',
            SCREENER:'Screener',
            OBSERVER:'Observer',
            SERVICE:'Service account',
        })[key] || (key ? key.replace(/_/g,' ').toLowerCase().replace(/\b\w/g,c=>c.toUpperCase()) : 'Unknown'); },
        roleDescription(key){ return ({
            NATIONAL_ADMIN:'Can see and manage everything across Uganda.',
            PHEOC_OFFICER:'Manages a regional PHEOC and the districts it covers.',
            DISTRICT_SUPERVISOR:'Oversees all ports of entry in their district.',
            POE_ADMIN:'Runs one port of entry and its screeners.',
            POE_OFFICER:'Works at a specific port of entry.',
            SCREENER:'Screens travellers at a specific port of entry.',
            OBSERVER:'Can view data but cannot make changes.',
            SERVICE:'Internal system account (do not use for people).',
        })[key] || 'No description.'; },
        statusOf(u){
            if (!u) return 'unknown';
            if (u.suspended_at) return 'suspended';
            if (u.locked_until && new Date(String(u.locked_until).replace(' ','T')+'Z') > new Date()) return 'locked';
            if (!u.invitation_accepted_at && u.invitation_token_hash) return 'pending';
            if (!Number(u.is_active)) return 'inactive';
            return 'active';
        },
        statusLabel(s){ return ({active:'Active', suspended:'Suspended', locked:'Locked out', pending:'Waiting to accept', inactive:'Not active'})[s] || s; },
        statusBadgeClass(s){ return ({
            active:'badge-secondary',
            pending:'badge-outline',
            locked:'badge-outline',
            suspended:'badge-destructive',
            inactive:'badge-outline',
        })[s] || 'badge-outline'; },

        showToast(title, message){
            this.toast = { open:true, title, message, _t:null };
            clearTimeout(this.toast._t);
            this.toast._t = setTimeout(()=>this.toast.open=false, 3500);
        },
    };
}
</script>
@endpush
@endsection

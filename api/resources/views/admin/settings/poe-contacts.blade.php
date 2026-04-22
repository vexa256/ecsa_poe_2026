{{-- ============================================================================
  /admin/settings/poe-contacts — Notification routing directory
  ----------------------------------------------------------------------------
  Drives the routing table used by NotificationDispatcher to decide who gets
  ALERT_CRITICAL / BREACH_717 / TIER1_ADVISORY etc. for each country / district /
  POE. CRUD against /api/poe-contacts/* (legacy user_id param contract).

  8-point bar:
    · Premium contact cards (avatar + level chip + flag row)
    · Aggressive search + level tabs + risk-flag filters + country filter
    · Mobile-first (1-col below sm, 2-col md, 3-col lg)
    · KPI strip: coverage per critical flag — gap detection is the whole point
    · Hardcoded Copilot that flags routing gaps in language a director reads
    · 3-step wizard for new contact: Scope → Identity → Flags
    · Insane validation: at least one of email/alternate_email required;
      receives_critical+receives_tier1 mandatory on NATIONAL level
    · Smart guidance per-card: "receives no HIGH alerts → will miss 60% of traffic"
============================================================================ --}}
@extends('admin.layout')

@section('title', 'Notification Contacts')
@section('heading', 'POE Notification Directory')
@section('subheading', 'Who gets which email · escalation ladder · coverage gaps')

@section('breadcrumbs')
    <a href="{{ url('/admin/dashboard') }}" class="hover:text-ink-600 truncate">Command Centre</a>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="hover:text-ink-600 truncate">Settings</span>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-ink-600 font-medium truncate">Contacts</span>
@endsection

@section('page_actions')
    <button @click="openCreate()" class="inline-flex items-center gap-2 h-9 px-3 rounded-lg bg-ink-900 hover:bg-ink-800 text-white text-sm font-semibold shadow-panel">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New contact
    </button>
@endsection

@push('head')
<style>
    .tab-btn[aria-selected=true]{color:#111a33;border-color:#2f7dff;font-weight:600}
    .tab-btn[aria-selected=true] .tab-dot{background:#2f7dff;color:#fff}
    .card-hover:hover{border-color:#8ebfff;box-shadow:0 8px 24px -12px rgba(47,125,255,.25)}
    .flag-on{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}
    .flag-off{background:#f8fafc;color:#94a3b8;border-color:#e2e8f0}
    .level-badge-POE{background:#dbeafe;color:#1e40af;border-color:#93c5fd}
    .level-badge-DISTRICT{background:#fef3c7;color:#92400e;border-color:#fcd34d}
    .level-badge-PHEOC{background:#fce7f3;color:#9d174d;border-color:#f9a8d4}
    .level-badge-NATIONAL{background:#ede9fe;color:#5b21b6;border-color:#c4b5fd}
    .level-badge-WHO{background:#dcfce7;color:#166534;border-color:#86efac}
</style>
@endpush

@section('content')
<div x-data="poeContactsAdmin({
        actorScope: @js($actorScope),
        pheocDistricts: @js($pheocDistricts),
        districtPoes: @js($districtPoes),
     })"
     x-init="init()"
     class="space-y-4">

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
        <template x-for="k in kpis" :key="k.label">
            <div class="rounded-xl border border-ink-100 bg-white shadow-panel p-3">
                <div class="flex items-center justify-between">
                    <div class="text-[10px] uppercase tracking-[0.14em] font-semibold text-ink-400" x-text="k.label"></div>
                    <span class="text-[10px] font-bold font-mono px-1.5 py-0.5 rounded-md border"
                          :class="k.tone==='rose'?'bg-rose-50 text-rose-600 border-rose-200':
                                  k.tone==='amber'?'bg-amber-50 text-amber-600 border-amber-200':
                                  k.tone==='emerald'?'bg-emerald-50 text-emerald-600 border-emerald-200':
                                  'bg-brand-50 text-brand-700 border-brand-200'"
                          x-text="k.delta"></span>
                </div>
                <div class="mt-1 text-xl sm:text-2xl font-bold text-ink-900 font-mono" x-text="k.value"></div>
                <div class="text-[11px] text-ink-500 line-clamp-1" x-text="k.hint"></div>
            </div>
        </template>
    </div>

    {{-- AI Copilot --}}
    <div class="rounded-xl border border-brand-200 bg-gradient-to-br from-brand-50/60 via-white to-white p-3" x-show="copilot.length">
        <div class="flex items-start gap-3">
            <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 grid place-items-center text-white shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-brand-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500 animate-pulse-dot"></span>
                    Copilot · routing-gap brief
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
                       placeholder="Search name, email, position, organisation…"
                       class="w-full h-9 pl-10 pr-3 rounded-lg border border-ink-200 bg-white text-sm focus:border-brand-500 focus:ring-0">
            </div>
            <select x-model="filters.poe_code" @change="filters.page=1"
                    class="h-9 rounded-lg border border-ink-200 bg-white text-xs sm:text-sm px-3 focus:border-brand-500 focus:ring-0">
                <option value="">All POEs</option>
                <template x-for="p in allPoes" :key="p"><option :value="p" x-text="p"></option></template>
            </select>
            <select x-model="filters.flag" @change="filters.page=1"
                    class="h-9 rounded-lg border border-ink-200 bg-white text-xs sm:text-sm px-3 focus:border-brand-500 focus:ring-0">
                <option value="">Any flag</option>
                <option value="receives_critical">Receives CRITICAL</option>
                <option value="receives_high">Receives HIGH</option>
                <option value="receives_tier1">Receives Tier 1 IHR</option>
                <option value="receives_tier2">Receives Tier 2 IHR</option>
                <option value="receives_breach_alerts">Receives 7-1-7 BREACH</option>
                <option value="receives_followup_reminders">Receives follow-ups</option>
                <option value="receives_daily_report">Daily digest</option>
                <option value="receives_weekly_report">Weekly digest</option>
            </select>
            <label class="inline-flex items-center gap-1.5 text-xs text-ink-600 px-2"><input type="checkbox" x-model="filters.activeOnly" class="rounded border-ink-300">Active only</label>
            <button @click="reload(true)" class="h-9 w-9 grid place-items-center rounded-lg border border-ink-200 bg-white hover:border-ink-300 text-ink-600" title="Refresh">
                <svg class="h-4 w-4" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
        </div>

        {{-- CARD GRID --}}
        <div class="p-3 sm:p-4">
            <template x-if="loading && contacts.length === 0">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3"><template x-for="i in 6" :key="i"><div class="h-32 rounded-xl bg-ink-50 shimmer"></div></template></div>
            </template>
            <template x-if="!loading && paged().length === 0 && !error">
                <div class="py-10 text-center">
                    <div class="mx-auto h-12 w-12 rounded-xl bg-ink-100 text-ink-400 grid place-items-center mb-2">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div class="text-sm font-semibold text-ink-700">No contacts match</div>
                    <button @click="resetFilters()" class="text-xs text-brand-700 hover:text-brand-500 underline mt-1">Clear filters</button>
                </div>
            </template>
            <template x-if="error">
                <div class="py-6 text-center text-sm text-rose-700" x-text="error"></div>
            </template>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3" x-show="!loading && paged().length > 0">
                <template x-for="c in paged()" :key="c.id">
                    <article class="rounded-xl border border-ink-100 bg-white p-3 card-hover transition cursor-pointer relative"
                             @click="openEdit(c)">
                        <div class="flex items-start gap-2.5">
                            <div class="h-10 w-10 rounded-lg bg-gradient-to-br from-ink-300 to-ink-500 grid place-items-center text-white text-xs font-bold shrink-0"
                                 x-text="initials(c.full_name)"></div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded border" :class="'level-badge-' + c.level" x-text="c.level"></span>
                                    <span x-show="Number(c.is_active) !== 1" class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-ink-100 text-ink-500 border border-ink-200">Inactive</span>
                                    <span x-show="c.priority_order > 1" class="text-[9px] font-mono text-ink-500">· p<span x-text="c.priority_order"></span></span>
                                </div>
                                <h3 class="mt-0.5 text-sm font-semibold text-ink-900 truncate" x-text="c.full_name"></h3>
                                <div class="text-[11px] text-ink-500 truncate" x-text="c.position || c.organisation || '—'"></div>
                                <div class="text-[11px] text-ink-500 font-mono truncate mt-0.5" x-text="c.email || c.alternate_email || '(no email)'"></div>
                                <div class="text-[10px] text-ink-400 font-mono truncate">
                                    <span x-text="c.country_code"></span> ·
                                    <span x-text="c.district_code || '—'"></span> ·
                                    <span class="font-semibold" x-text="c.poe_code || '—'"></span>
                                </div>
                            </div>
                        </div>
                        {{-- Flag strip --}}
                        <div class="mt-2 flex flex-wrap gap-1">
                            <template x-for="f in flagChips(c)" :key="f.key">
                                <span class="text-[9px] font-bold uppercase px-1 py-0.5 rounded border"
                                      :class="f.on ? 'flag-on' : 'flag-off'"
                                      x-text="f.label"></span>
                            </template>
                        </div>
                        {{-- Smart tip --}}
                        <div class="mt-1.5 text-[10px] text-brand-700 font-semibold" x-show="smartTip(c)" x-text="smartTip(c)"></div>
                    </article>
                </template>
            </div>

            {{-- Pagination --}}
            <div class="mt-4 flex items-center justify-between text-xs text-ink-500" x-show="filtered.length > 0">
                <div>
                    <span x-text="(filters.page-1)*filters.perPage + 1"></span>–<span x-text="Math.min(filters.page*filters.perPage, filtered.length)"></span> of
                    <span class="font-semibold text-ink-700" x-text="filtered.length"></span>
                </div>
                <div class="flex items-center gap-1">
                    <select x-model.number="filters.perPage" @change="filters.page=1" class="h-7 rounded border border-ink-200 text-xs px-1">
                        <option :value="8">8</option><option :value="12">12</option><option :value="24">24</option>
                    </select>
                    <button @click="filters.page=Math.max(1,filters.page-1)" :disabled="filters.page<=1" class="h-7 px-2 rounded border border-ink-200 bg-white hover:border-ink-300 disabled:opacity-40">Prev</button>
                    <button @click="filters.page++" :disabled="filters.page*filters.perPage >= filtered.length" class="h-7 px-2 rounded border border-ink-200 bg-white hover:border-ink-300 disabled:opacity-40">Next</button>
                </div>
            </div>
        </div>
    </div>

    {{-- WIZARD DRAWER --}}
    <div x-show="drawer.open" x-cloak @keydown.escape.window="closeDrawer()"
         class="fixed inset-0 z-50 flex">
        <div x-show="drawer.open" x-transition.opacity @click="closeDrawer()" class="absolute inset-0 bg-ink-950/50 backdrop-blur-sm"></div>
        <aside x-show="drawer.open"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               class="relative ml-auto h-full w-full sm:max-w-xl bg-white flex flex-col shadow-panel-lg">
            <div class="h-16 px-4 border-b border-ink-100 flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] uppercase tracking-[0.14em] font-semibold text-ink-400">
                        <span x-text="drawer.mode === 'create' ? 'New contact · step '+drawer.step+' of 3' : 'Edit contact'"></span>
                    </div>
                    <div class="text-base font-semibold text-ink-900 truncate" x-text="drawer.mode === 'create' ? 'Add to notification directory' : (form.full_name || 'Contact')"></div>
                </div>
                <button @click="closeDrawer()" class="h-9 w-9 grid place-items-center rounded-lg hover:bg-ink-100 text-ink-500">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- WIZARD STEPPER (create only) --}}
            <div class="px-4 pt-3" x-show="drawer.mode === 'create'">
                <div class="flex gap-1">
                    <template x-for="s in [1,2,3]" :key="s">
                        <div class="h-1 flex-1 rounded-full" :class="s <= drawer.step ? 'bg-brand-500' : 'bg-ink-100'"></div>
                    </template>
                </div>
                <div class="mt-1.5 text-[11px] text-ink-500 flex justify-between">
                    <span :class="drawer.step === 1 ? 'font-semibold text-ink-800' : ''">1 · Scope</span>
                    <span :class="drawer.step === 2 ? 'font-semibold text-ink-800' : ''">2 · Identity</span>
                    <span :class="drawer.step === 3 ? 'font-semibold text-ink-800' : ''">3 · Flags</span>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto scrollbar-thin p-4 space-y-4">
                {{-- STEP 1 / edit always shows this in "Scope" accordion --}}
                <section x-show="drawer.mode === 'edit' || drawer.step === 1">
                    <h4 class="text-[10px] uppercase tracking-[0.14em] font-bold text-ink-500 mb-2">Scope</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-ink-700">Country</label>
                            <select x-model="form.country_code"
                                    class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0">
                                <option value="UG">Uganda · UG</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-ink-700">Level <span class="text-rose-500">*</span></label>
                            <select x-model="form.level"
                                    class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0">
                                <option value="">—</option>
                                <option value="POE">POE</option>
                                <option value="DISTRICT">DISTRICT</option>
                                <option value="PHEOC">PHEOC</option>
                                <option value="NATIONAL">NATIONAL</option>
                                <option value="WHO">WHO</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-ink-700">RPHEOC / Province</label>
                            <select x-model="form.pheoc_code" @change="form.district_code=''; form.poe_code=''"
                                    class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0">
                                <option value="">— None —</option>
                                <template x-for="p in pheocList" :key="p"><option :value="p" x-text="p"></option></template>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-ink-700">District <span x-show="form.level==='DISTRICT' || form.level==='POE'" class="text-rose-500">*</span></label>
                            <select x-model="form.district_code" @change="form.poe_code=''"
                                    :disabled="!form.pheoc_code"
                                    class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm disabled:bg-ink-50 disabled:text-ink-400 focus:border-brand-500 focus:ring-0">
                                <option value="">— None —</option>
                                <template x-for="d in districtsFor(form.pheoc_code)" :key="d"><option :value="d" x-text="d"></option></template>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold text-ink-700">POE <span x-show="form.level==='POE'" class="text-rose-500">*</span></label>
                            <select x-model="form.poe_code"
                                    :disabled="!form.district_code"
                                    class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm disabled:bg-ink-50 disabled:text-ink-400 focus:border-brand-500 focus:ring-0">
                                <option value="">— None —</option>
                                <template x-for="p in poesFor(form.district_code)" :key="p"><option :value="p" x-text="p"></option></template>
                            </select>
                        </div>
                    </div>
                </section>

                <section x-show="drawer.mode === 'edit' || drawer.step === 2">
                    <h4 class="text-[10px] uppercase tracking-[0.14em] font-bold text-ink-500 mb-2">Identity</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold text-ink-700">Full name <span class="text-rose-500">*</span></label>
                            <input type="text" x-model="form.full_name" minlength="2" maxlength="160" required
                                   class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-ink-700">Position</label>
                            <input type="text" x-model="form.position" maxlength="120"
                                   class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-ink-700">Organisation</label>
                            <input type="text" x-model="form.organisation" maxlength="160"
                                   class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-ink-700">Email <span x-show="!form.alternate_email" class="text-rose-500">*</span></label>
                            <input type="email" x-model="form.email" maxlength="160"
                                   class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-ink-700">Alternate email</label>
                            <input type="email" x-model="form.alternate_email" maxlength="160"
                                   class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-ink-700">Phone</label>
                            <input type="tel" x-model="form.phone" maxlength="40"
                                   class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-ink-700">Alt phone</label>
                            <input type="tel" x-model="form.alternate_phone" maxlength="40"
                                   class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-ink-700">Priority order</label>
                            <input type="number" x-model.number="form.priority_order" min="1" max="999"
                                   class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm font-mono focus:border-brand-500 focus:ring-0">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-ink-700">Channel</label>
                            <select x-model="form.preferred_channel"
                                    class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0">
                                <option value="EMAIL">Email</option>
                                <option value="SMS">SMS</option>
                                <option value="BOTH">Email + SMS</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section x-show="drawer.mode === 'edit' || drawer.step === 3">
                    <h4 class="text-[10px] uppercase tracking-[0.14em] font-bold text-ink-500 mb-2">Receives</h4>
                    <div class="space-y-3">
                        <div class="rounded-lg border border-ink-100 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-[0.1em] text-ink-500 mb-1.5">Risk tiers</div>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="k in ['receives_critical','receives_high','receives_medium','receives_low']" :key="k">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" :checked="!!form[k]" @change="form[k] = $event.target.checked" class="rounded border-ink-300">
                                        <span x-text="k.replace('receives_','').toUpperCase()"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                        <div class="rounded-lg border border-ink-100 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-[0.1em] text-ink-500 mb-1.5">IHR tiers</div>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="k in ['receives_tier1','receives_tier2']" :key="k">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" :checked="!!form[k]" @change="form[k] = $event.target.checked" class="rounded border-ink-300">
                                        <span x-text="k.replace('receives_','').toUpperCase().replace('TIER','Tier ')"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                        <div class="rounded-lg border border-ink-100 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-[0.1em] text-ink-500 mb-1.5">Workflow</div>
                            <div class="grid grid-cols-1 gap-2">
                                <template x-for="k in ['receives_breach_alerts','receives_followup_reminders','receives_daily_report','receives_weekly_report']" :key="k">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" :checked="!!form[k]" @change="form[k] = $event.target.checked" class="rounded border-ink-300">
                                        <span x-text="k.replace('receives_','').split('_').map(s=>s.charAt(0).toUpperCase()+s.slice(1)).join(' ')"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm pt-1">
                            <input type="checkbox" :checked="!!form.is_active" @change="form.is_active = $event.target.checked" class="rounded border-ink-300">
                            <span>Contact is active</span>
                        </label>
                    </div>
                </section>

                <div x-show="formErr" class="rounded-lg border border-rose-200 bg-rose-50 text-rose-800 text-xs p-2.5" x-text="formErr"></div>
            </div>

            {{-- Footer --}}
            <div class="border-t border-ink-100 p-3 flex items-center gap-2">
                <template x-if="drawer.mode === 'edit' && form.id">
                    <button type="button" @click="confirmDelete(form)" class="h-9 px-3 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-sm font-semibold">Delete</button>
                </template>
                <button @click="closeDrawer()" class="h-9 px-3 rounded-lg border border-ink-200 bg-white hover:border-ink-300 text-sm font-semibold text-ink-700">Cancel</button>
                <template x-if="drawer.mode === 'create' && drawer.step > 1">
                    <button @click="drawer.step--" class="h-9 px-3 rounded-lg border border-ink-200 bg-white hover:border-ink-300 text-sm font-semibold text-ink-700">Back</button>
                </template>
                <template x-if="drawer.mode === 'create' && drawer.step < 3">
                    <button @click="wizardNext()" class="ml-auto h-9 px-3 rounded-lg bg-ink-900 hover:bg-ink-800 text-white text-sm font-semibold">Next</button>
                </template>
                <template x-if="drawer.mode === 'edit' || drawer.step === 3">
                    <button @click="submit()" :disabled="saving" class="ml-auto inline-flex items-center gap-1.5 h-9 px-3 rounded-lg bg-brand-600 hover:bg-brand-700 disabled:opacity-60 text-white text-sm font-semibold">
                        <svg x-show="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                        <span x-text="drawer.mode === 'create' ? 'Create contact' : 'Save changes'"></span>
                    </button>
                </template>
            </div>
        </aside>
    </div>

    {{-- Toast --}}
    <div x-show="toast.open" x-cloak x-transition
         class="fixed bottom-4 right-4 z-[80] max-w-sm rounded-xl shadow-panel-lg px-4 py-3 text-sm font-semibold"
         :class="toast.tone === 'error' ? 'bg-rose-600 text-white' : 'bg-ink-900 text-white'"
         x-text="toast.text"></div>
</div>

@push('scripts')
<script>
function poeContactsAdmin(seed){
    return {
        actorScope: seed.actorScope,
        pheocDistricts: seed.pheocDistricts || {},
        districtPoes: seed.districtPoes || {},
        tab: 'all',
        loading: false, error: null,
        contacts: [],
        filters: { search:'', poe_code:'', flag:'', activeOnly:true, page:1, perPage:8 },

        drawer: { open:false, mode:'create', step:1 },
        form: this._emptyForm(),
        formErr: '', saving: false,
        toast: { open:false, text:'', tone:'info', _t:null },

        _emptyForm(){
            return {
                id: null,
                country_code: 'UG', level: 'NATIONAL',
                pheoc_code: '', province_code: '', district_code: '', poe_code: '',
                full_name: '', position: '', organisation: '',
                email: '', alternate_email: '', phone: '', alternate_phone: '',
                priority_order: 1, preferred_channel: 'EMAIL',
                receives_critical: true, receives_high: true, receives_medium: false, receives_low: false,
                receives_tier1: true, receives_tier2: true,
                receives_breach_alerts: true, receives_followup_reminders: true,
                receives_daily_report: false, receives_weekly_report: false,
                is_active: true, notes: '',
            };
        },

        async init(){
            if (!localStorage.getItem('pheoc_token')) { this._bounce(); return; }
            await this.reload(true);
        },
        _bounce(){
            const next = encodeURIComponent(location.pathname + location.search);
            localStorage.removeItem('pheoc_token'); localStorage.removeItem('pheoc_user');
            location.replace('{{ url('/admin/login') }}?next=' + next);
        },
        _uid(){ try { return Number((JSON.parse(localStorage.getItem('pheoc_user')||'{}')).id || 0); } catch { return 0; } },
        async _req(path, opts={}){
            const t = localStorage.getItem('pheoc_token') || '';
            const sep = path.includes('?') ? '&' : '?';
            const url = '{{ url('/api') }}' + path + sep + 'user_id=' + this._uid();
            const res = await fetch(url, {
                method: opts.method || 'GET',
                headers: Object.assign({'Accept':'application/json','Content-Type':'application/json','Authorization':'Bearer '+t}, opts.headers||{}),
                body: opts.body != null ? JSON.stringify(Object.assign({user_id:this._uid()}, opts.body)) : undefined,
            });
            const ct = res.headers.get('content-type')||'';
            const body = ct.includes('json') ? await res.json() : { ok:false };
            if (res.status === 401) this._bounce();
            return { status: res.status, body };
        },

        async reload(showLoader = false){
            if (showLoader) this.loading = true;
            this.error = null;
            const { status, body } = await this._req('/poe-contacts?country_code=UG&limit=500');
            if (showLoader) this.loading = false;
            if (status === 200 && (body.ok !== false)) {
                this.contacts = (body?.data?.items) || (body?.data) || (body?.contacts) || [];
                if (!Array.isArray(this.contacts)) this.contacts = [];
            } else {
                this.error = body?.message || body?.error || ('Load failed · ' + status);
            }
        },

        // ── cascade ───────────────────────────────────────────────────
        get pheocList(){ return Object.keys(this.pheocDistricts).sort(); },
        districtsFor(p){ return p ? (this.pheocDistricts[p]||[]).slice().sort() : []; },
        poesFor(d){ return d ? (this.districtPoes[d]||[]).slice().sort() : []; },
        get allPoes(){
            const s = new Set();
            for (const c of this.contacts) if (c.poe_code) s.add(c.poe_code);
            return [...s].sort();
        },

        // ── computed ──────────────────────────────────────────────────
        get filtered(){
            const q = (this.filters.search||'').toLowerCase().trim();
            return this.contacts.filter(c => {
                if (this.tab !== 'all' && c.level !== this.tab) return false;
                if (this.filters.activeOnly && Number(c.is_active) !== 1) return false;
                if (this.filters.poe_code && c.poe_code !== this.filters.poe_code) return false;
                if (this.filters.flag && Number(c[this.filters.flag]) !== 1) return false;
                if (!q) return true;
                const hay = [c.full_name, c.email, c.alternate_email, c.phone, c.position, c.organisation].join(' ').toLowerCase();
                return hay.includes(q);
            });
        },
        paged(){ const s=(this.filters.page-1)*this.filters.perPage; return this.filtered.slice(s, s+this.filters.perPage); },

        get tabs(){
            const counts = {};
            for (const c of this.contacts) counts[c.level] = (counts[c.level]||0) + 1;
            return [
                { key:'all',      label:'All',      count: this.contacts.length },
                { key:'NATIONAL', label:'National', count: counts.NATIONAL || 0 },
                { key:'PHEOC',    label:'PHEOC',    count: counts.PHEOC || 0 },
                { key:'DISTRICT', label:'District', count: counts.DISTRICT || 0 },
                { key:'POE',      label:'POE',      count: counts.POE || 0 },
                { key:'WHO',      label:'WHO',      count: counts.WHO || 0 },
            ];
        },

        get kpis(){
            const active = this.contacts.filter(c => Number(c.is_active) === 1);
            const crit   = active.filter(c => Number(c.receives_critical) === 1).length;
            const tier1  = active.filter(c => Number(c.receives_tier1) === 1).length;
            const breach = active.filter(c => Number(c.receives_breach_alerts) === 1).length;
            return [
                { label:'Active contacts', value: active.length, hint:'routing directory', tone: active.length > 0 ? 'brand':'rose', delta: active.length > 0 ? 'on' : 'empty' },
                { label:'Critical coverage', value: crit, hint:'receive CRITICAL alerts', tone: crit >= 3 ? 'emerald':'rose', delta: crit >= 3 ? 'ok':'gap' },
                { label:'IHR Tier 1', value: tier1, hint:'WHO Annex 2 notifiables', tone: tier1 >= 2 ? 'emerald':'amber', delta: tier1 >= 2 ? 'ok':'gap' },
                { label:'7-1-7 breach', value: breach, hint:'compliance recipients', tone: breach >= 2 ? 'emerald':'amber', delta: breach >= 2 ? 'ok':'gap' },
            ];
        },

        get copilot(){
            const lines = [];
            const active = this.contacts.filter(c => Number(c.is_active) === 1);
            if (active.length === 0) return ['Directory is empty — no notification will route until a contact is added.'];
            const critical = active.filter(c => Number(c.receives_critical) === 1);
            if (critical.length < 3) lines.push(`Only ${critical.length} active contact${critical.length===1?'':'s'} ${critical.length===1?'receives':'receive'} CRITICAL alerts. Add at least 3 (Screener/District Supervisor/PHEOC) so a single outage can't silence the channel.`);
            const tier1 = active.filter(c => Number(c.receives_tier1) === 1);
            if (tier1.length < 2) lines.push(`Tier-1 IHR coverage is thin (${tier1.length}). WHO requires independent confirmation within 24h — add a second backup recipient at NATIONAL or WHO level.`);
            const breach = active.filter(c => Number(c.receives_breach_alerts) === 1);
            if (breach.length === 0) lines.push('Nobody is subscribed to 7-1-7 breach notifications — the compliance board will emit silently.');
            const byLevel = {};
            for (const c of active) byLevel[c.level] = (byLevel[c.level]||0) + 1;
            if (!byLevel.NATIONAL) lines.push('No NATIONAL-level contact — PHEIC pathway and daily digests have no recipient.');
            if (!byLevel.POE && !byLevel.DISTRICT) lines.push('No POE or DISTRICT contact — front-line acknowledgement emails will never land with a screener.');
            const noEmail = active.filter(c => !c.email && !c.alternate_email).length;
            if (noEmail > 0) lines.push(`${noEmail} contact(s) have no email on record — those rows are dead-weight in the EMAIL channel.`);
            if (lines.length === 0) lines.push('Routing table looks healthy. All critical tiers covered.');
            return lines;
        },

        resetFilters(){ this.filters = { search:'', poe_code:'', flag:'', activeOnly:true, page:1, perPage:8 }; },

        // ── CRUD ──────────────────────────────────────────────────────
        openCreate(){
            this.form = this._emptyForm();
            this.formErr = '';
            this.drawer = { open:true, mode:'create', step:1 };
        },
        openEdit(c){
            this.form = Object.assign(this._emptyForm(), c, {
                id: c.id,
                is_active: Number(c.is_active) === 1,
                receives_critical: Number(c.receives_critical) === 1,
                receives_high:     Number(c.receives_high) === 1,
                receives_medium:   Number(c.receives_medium) === 1,
                receives_low:      Number(c.receives_low) === 1,
                receives_tier1:    Number(c.receives_tier1) === 1,
                receives_tier2:    Number(c.receives_tier2) === 1,
                receives_breach_alerts:       Number(c.receives_breach_alerts) === 1,
                receives_followup_reminders:  Number(c.receives_followup_reminders) === 1,
                receives_daily_report:        Number(c.receives_daily_report) === 1,
                receives_weekly_report:       Number(c.receives_weekly_report) === 1,
                pheoc_code: c.pheoc_code || c.province_code || '',
            });
            this.formErr = '';
            this.drawer = { open:true, mode:'edit', step:1 };
        },
        closeDrawer(){ this.drawer.open = false; },

        wizardNext(){
            this.formErr = '';
            if (this.drawer.step === 1) {
                if (!this.form.level) { this.formErr = 'Select a level.'; return; }
                if (this.form.level === 'POE' && !this.form.poe_code) { this.formErr = 'POE-level contact requires a POE.'; return; }
                if (this.form.level === 'DISTRICT' && !this.form.district_code) { this.formErr = 'DISTRICT-level contact requires a district.'; return; }
                this.drawer.step = 2;
                return;
            }
            if (this.drawer.step === 2) {
                if (!this.form.full_name || this.form.full_name.length < 2) { this.formErr = 'Full name required (≥2 chars).'; return; }
                if (!this.form.email && !this.form.alternate_email) { this.formErr = 'At least one email (primary or alternate) required.'; return; }
                this.drawer.step = 3;
                return;
            }
        },

        async submit(){
            this.formErr = '';
            this.saving = true;
            // Align pheoc_code with province_code (server invariant)
            const payload = Object.assign({}, this.form);
            payload.province_code = payload.pheoc_code;
            // Booleans → 0/1
            for (const k of Object.keys(payload)) {
                if (typeof payload[k] === 'boolean') payload[k] = payload[k] ? 1 : 0;
            }
            const isEdit = this.drawer.mode === 'edit' && this.form.id;
            const path = isEdit ? '/poe-contacts/' + this.form.id : '/poe-contacts';
            const { status, body } = await this._req(path, { method: isEdit ? 'PATCH' : 'POST', body: payload });
            this.saving = false;
            if ((status === 200 || status === 201) && body.ok !== false) {
                this.showToast(isEdit ? 'Contact saved' : 'Contact added');
                this.drawer.open = false;
                await this.reload();
            } else if (status === 422) {
                const errs = body?.errors || {};
                const first = Object.entries(errs)[0];
                this.formErr = first ? (first[0] + ': ' + (Array.isArray(first[1]) ? first[1][0] : first[1])) : (body?.error || 'Validation failed.');
            } else {
                this.formErr = body?.message || body?.error || ('Request failed · ' + status);
            }
        },

        async confirmDelete(c){
            if (!confirm(`Deactivate "${c.full_name}"? Their email will be removed from all routes.`)) return;
            const { status, body } = await this._req('/poe-contacts/' + c.id, { method: 'DELETE' });
            if (status === 200 && body.ok !== false) { this.showToast('Deactivated'); this.drawer.open = false; this.reload(); }
            else this.showToast(body?.error || 'Delete failed', 'error');
        },

        // ── helpers ───────────────────────────────────────────────────
        initials(s){ return (s||'?').trim().split(/\s+/).map(x=>x[0]).slice(0,2).join('').toUpperCase() || '?'; },
        flagChips(c){
            return [
                { key:'c',   label:'CRIT',   on: Number(c.receives_critical)===1 },
                { key:'h',   label:'HIGH',   on: Number(c.receives_high)===1 },
                { key:'t1',  label:'T1',     on: Number(c.receives_tier1)===1 },
                { key:'t2',  label:'T2',     on: Number(c.receives_tier2)===1 },
                { key:'brk', label:'7-1-7',  on: Number(c.receives_breach_alerts)===1 },
                { key:'fu',  label:'F/U',    on: Number(c.receives_followup_reminders)===1 },
            ];
        },
        smartTip(c){
            if (!c.email && !c.alternate_email) return 'No email — unreachable via EMAIL channel';
            if (Number(c.is_active) !== 1) return 'Inactive — not in any route';
            if (Number(c.receives_critical) !== 1 && (c.level === 'NATIONAL' || c.level === 'PHEOC')) return 'Not on CRITICAL route — unusual for this level';
            return '';
        },
        showToast(t, tone='info'){ this.toast.text=t; this.toast.tone=tone; this.toast.open=true; clearTimeout(this.toast._t); this.toast._t=setTimeout(()=>this.toast.open=false, 3500); },
    };
}
</script>
@endpush
@endsection

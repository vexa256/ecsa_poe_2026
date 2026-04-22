{{-- ============================================================================
  /admin/assignments — Assignment & Scope management
  ----------------------------------------------------------------------------
  Mirrors the invariants enforced by UserAssignmentsController:
    · SSOT names only (RPHEOC/District/POE from POES.JS)
    · SINGLE active POE per user (409 SINGLE_ACTIVE_POE → force:true to transfer)
    · At least one primary row per user
    · Cascading RPHEOC → District → POE (same UX as mobile UsersList.vue)

  API surface used:
    GET    /api/v2/admin/assignments?...                 cross-user search
    GET    /api/v2/admin/users/{id}/assignments          per-user timeline
    POST   /api/v2/admin/users/{id}/assignments          create (+force)
    PATCH  /api/v2/admin/user-assignments/{id}           update (scope / primary / active)
    DELETE /api/v2/admin/user-assignments/{id}           soft end
    GET    /api/v2/admin/users?search=…                  user autocomplete
============================================================================ --}}
@extends('admin.layout')

@section('title', 'Assignments & Scope')
@section('heading', 'Assignments & Scope')
@section('subheading', 'Who works where · single-active-POE invariant · full transfer history')

@section('breadcrumbs')
    <a href="{{ url('/admin/dashboard') }}" class="hover:text-ink-600 truncate">Command Centre</a>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="hover:text-ink-600 truncate">Administration</span>
    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-ink-600 font-medium truncate">Assignments</span>
@endsection

@section('page_actions')
    <a href="{{ url('/admin/users') }}" class="hidden sm:inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-ink-200 bg-white hover:border-ink-300 hover:shadow-panel text-sm font-medium text-ink-700 transition">
        <svg class="h-4 w-4 text-ink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        Users &amp; Roles
    </a>
@endsection

@push('head')
<style>
    .row-hover:hover{background:rgb(245 247 251/.6)}
    .drawer-panel{box-shadow:-24px 0 48px -24px rgb(15 23 42/.25)}
    .timeline-rail{background:linear-gradient(to bottom, transparent 0, transparent 10px, rgb(226 232 240) 10px, rgb(226 232 240) calc(100% - 10px), transparent calc(100% - 10px))}
    details > summary{list-style:none;cursor:pointer}
    details > summary::-webkit-details-marker{display:none}
</style>
@endpush

@section('content')
<div
    x-data="assignmentsPage({
        actorScope: @js($actorScope),
        pheocNames: @js($pheocNames),
        districtNames: @js($districtNames),
        poeNames: @js($poeNames),
        pheocDistricts: @js($pheocDistricts),
        districtPoes: @js($districtPoes),
        roleGeoRequirements: @js($roleGeoRequirements),
        assignmentsApi: '{{ url('/api/v2/admin/assignments') }}',
        userAssignmentsApi: '{{ url('/api/v2/admin/users') }}',
        rowApi: '{{ url('/api/v2/admin/user-assignments') }}',
    })"
    x-init="init()"
    class="space-y-5">

    {{-- ── SCOPE + INVARIANTS banner ─────────────────────────────────── --}}
    <div class="rounded-xl border border-ink-100 bg-white shadow-panel p-3 sm:p-4 grid grid-cols-1 lg:grid-cols-3 gap-3 lg:gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <div class="h-10 w-10 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 grid place-items-center text-white shrink-0">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div class="min-w-0">
                <div class="text-[10px] uppercase tracking-[0.14em] font-semibold text-ink-400">Your scope</div>
                <div class="text-sm font-semibold text-ink-900 truncate" x-text="actorScope.label || 'Preview'"></div>
                <div class="text-xs text-ink-500"><span x-text="actorScope.role_key"></span> · <span x-text="actorScope.scope_level"></span></div>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-2 lg:col-span-2">
            @foreach([
                ['RPHEOCs', count($pheocNames), 'brand'],
                ['Districts', count($districtNames), 'sky'],
                ['POEs', count($poeNames), 'emerald'],
            ] as [$label,$n,$tone])
                <div class="rounded-lg border border-ink-100 bg-ink-50/40 px-3 py-2">
                    <div class="text-[10px] uppercase tracking-[0.12em] font-semibold text-ink-400">{{ $label }}</div>
                    <div class="text-lg font-bold font-mono text-ink-900 leading-none mt-1">{{ $n }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── AI COPILOT HINT ──────────────────────────────────────────── --}}
    <div class="rounded-xl border border-brand-200 bg-gradient-to-br from-brand-50/60 via-white to-white p-3"
         x-show="copilot.sentences.length">
        <div class="flex items-start gap-3">
            <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 grid place-items-center text-white shadow-glow shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-brand-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500 animate-pulse-dot"></span>
                    Copilot · scope distribution
                </div>
                <div class="mt-1 text-[13px] text-ink-800 leading-relaxed space-y-0.5">
                    <template x-for="s in copilot.sentences" :key="s"><p x-text="s"></p></template>
                </div>
            </div>
        </div>
    </div>

    {{-- ── INVARIANT strip ──────────────────────────────────────────── --}}
    <div class="rounded-xl border border-ink-100 bg-gradient-to-br from-brand-50/60 via-white to-white p-3 sm:p-4">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex items-center gap-2 text-xs font-semibold text-ink-700">
                <svg class="h-4 w-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Invariants enforced server-side
            </div>
            <div class="flex flex-wrap gap-1.5">
                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-[0.08em] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Single-active-POE</span>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-[0.08em] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Role-geo requirements</span>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-[0.08em] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>SSOT names only</span>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-[0.08em] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Primary-row invariant</span>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-[0.08em] px-1.5 py-0.5 rounded bg-brand-50 text-brand-700 border border-brand-200"><span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>pheoc_code ↔ province_code</span>
            </div>
        </div>
    </div>

    {{-- ── FILTER BAR ─────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-ink-100 bg-white shadow-panel p-3 sm:p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
            <div class="md:col-span-2 relative">
                <svg class="h-4 w-4 text-ink-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z"/></svg>
                <input type="search" x-model.debounce.300ms="filters.search" @input="reload()"
                       placeholder="Search user name, email, username…"
                       class="w-full h-10 pl-10 pr-3 rounded-lg border border-ink-200 bg-white text-sm placeholder-ink-400 focus:border-brand-500 focus:ring-0">
            </div>
            <select x-model="filters.province_code" @change="filters.district_code=''; filters.poe_code=''; reload()"
                    class="h-10 rounded-lg border border-ink-200 bg-white text-sm focus:border-brand-500 focus:ring-0 px-3">
                <option value="">All RPHEOCs</option>
                <template x-for="p in pheocList()" :key="p">
                    <option :value="p" x-text="p"></option>
                </template>
            </select>
            <select x-model="filters.district_code" @change="filters.poe_code=''; reload()"
                    :disabled="!filters.province_code"
                    class="h-10 rounded-lg border border-ink-200 bg-white text-sm focus:border-brand-500 focus:ring-0 px-3 disabled:bg-ink-50 disabled:text-ink-400">
                <option value="">
                    <template x-if="!filters.province_code">All districts</template>
                    <template x-if="filters.province_code">All districts in scope</template>
                </option>
                <template x-for="d in districtsFor(filters.province_code)" :key="d">
                    <option :value="d" x-text="d"></option>
                </template>
            </select>
            <select x-model="filters.poe_code" @change="reload()"
                    :disabled="!filters.district_code"
                    class="h-10 rounded-lg border border-ink-200 bg-white text-sm focus:border-brand-500 focus:ring-0 px-3 disabled:bg-ink-50 disabled:text-ink-400">
                <option value="">
                    <template x-if="!filters.district_code">All POEs</template>
                    <template x-if="filters.district_code">Any POE</template>
                </option>
                <template x-for="p in poesFor(filters.district_code)" :key="p">
                    <option :value="p" x-text="p"></option>
                </template>
            </select>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
            <label class="inline-flex items-center gap-1.5 text-ink-600"><input type="checkbox" x-model="filters.activeOnly" @change="reload()" class="rounded border-ink-300"> Active only</label>
            <label class="inline-flex items-center gap-1.5 text-ink-600"><input type="checkbox" x-model="filters.primaryOnly" @change="reload()" class="rounded border-ink-300"> Primary only</label>
            <button @click="resetFilters()" class="ml-auto text-xs text-ink-500 hover:text-ink-800 underline">Clear filters</button>
            <button @click="reload(true)" class="h-8 w-8 grid place-items-center rounded-lg border border-ink-200 bg-white hover:border-ink-300 text-ink-600" title="Refresh">
                <svg class="h-4 w-4" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
        </div>
    </div>

    {{-- ── TABLE ───────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-ink-100 bg-white shadow-panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-ink-50/70 border-b border-ink-100 text-ink-500">
                    <tr>
                        <th class="text-left py-2.5 px-4 font-semibold text-[11px] uppercase tracking-[0.1em]">User</th>
                        <th class="text-left py-2.5 px-3 font-semibold text-[11px] uppercase tracking-[0.1em]">Role</th>
                        <th class="text-left py-2.5 px-3 font-semibold text-[11px] uppercase tracking-[0.1em]">RPHEOC</th>
                        <th class="text-left py-2.5 px-3 font-semibold text-[11px] uppercase tracking-[0.1em] hidden md:table-cell">District</th>
                        <th class="text-left py-2.5 px-3 font-semibold text-[11px] uppercase tracking-[0.1em]">POE</th>
                        <th class="text-left py-2.5 px-3 font-semibold text-[11px] uppercase tracking-[0.1em] hidden lg:table-cell">Since</th>
                        <th class="text-left py-2.5 px-3 font-semibold text-[11px] uppercase tracking-[0.1em]">Flags</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <template x-if="loading && rows.length === 0">
                        <template x-for="i in 5" :key="i">
                            <tr><td colspan="7" class="py-4 px-4"><div class="h-4 rounded bg-ink-100 shimmer"></div></td></tr>
                        </template>
                    </template>
                    <template x-if="!loading && rows.length === 0 && !error">
                        <tr><td colspan="7" class="py-16 text-center">
                            <div class="mx-auto h-14 w-14 rounded-2xl bg-ink-100 grid place-items-center text-ink-400 mb-3">
                                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div class="text-sm font-semibold text-ink-700">No assignments match these filters</div>
                            <div class="text-xs text-ink-500 mt-1">Try <button @click="resetFilters()" class="underline hover:text-ink-700">clearing filters</button> or inspect an individual user from <a href="{{ url('/admin/users') }}" class="underline hover:text-ink-700">Users &amp; Roles</a>.</div>
                        </td></tr>
                    </template>
                    <template x-if="error">
                        <tr><td colspan="7" class="py-12 text-center">
                            <div class="mx-auto h-14 w-14 rounded-2xl bg-rose-50 grid place-items-center text-rose-500 mb-3">
                                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="text-sm font-semibold text-rose-700">Could not load assignments</div>
                            <div class="text-xs text-rose-500 mt-1 font-mono" x-text="error"></div>
                            <button @click="reload(true)" class="mt-3 inline-flex items-center gap-1.5 h-8 px-3 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold">Retry</button>
                        </td></tr>
                    </template>
                    <template x-for="a in rows" :key="a.id">
                        <tr class="row-hover cursor-pointer" @click="openUserDrawer(a.user_id)">
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="h-9 w-9 rounded-lg bg-gradient-to-br from-ink-300 to-ink-500 grid place-items-center text-white text-xs font-bold shrink-0"
                                         x-text="initials(a.full_name || a.username)"></div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-ink-900 truncate" x-text="a.full_name || a.username"></div>
                                        <div class="text-xs text-ink-500 truncate font-mono" x-text="a.email || a.username"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                <span class="text-[11px] font-bold uppercase tracking-[0.08em] text-ink-700" x-text="a.role_key"></span>
                            </td>
                            <td class="py-3 px-3"><span class="text-xs text-ink-700" x-text="a.province_code || a.pheoc_code || '—'"></span></td>
                            <td class="py-3 px-3 hidden md:table-cell"><span class="text-xs text-ink-700" x-text="a.district_code || '—'"></span></td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold" :class="a.poe_code ? 'text-ink-800' : 'text-ink-400'">
                                    <template x-if="a.poe_code">
                                        <span>📍 <span x-text="a.poe_code"></span></span>
                                    </template>
                                    <template x-if="!a.poe_code">—</template>
                                </span>
                            </td>
                            <td class="py-3 px-3 hidden lg:table-cell">
                                <span class="text-xs text-ink-600" x-text="fmtDate(a.starts_at)"></span>
                                <span x-show="a.ends_at" class="block text-[10px] text-rose-600">ended <span x-text="fmtDate(a.ends_at)"></span></span>
                            </td>
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-1">
                                    <span x-show="a.is_primary == 1" class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-brand-50 text-brand-700 border border-brand-200">Primary</span>
                                    <span x-show="a.is_active == 1" class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                                    <span x-show="a.is_active != 1" class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-ink-100 text-ink-600 border border-ink-200">Ended</span>
                                    <span x-show="Number(a.user_active) !== 1 || a.suspended_at" class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200" title="User account suspended">User off</span>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between border-t border-ink-100 px-3 sm:px-4 py-2.5 text-xs text-ink-500">
            <div>
                <span x-show="rows.length > 0"><span class="font-semibold text-ink-700" x-text="rows.length"></span> assignment row(s) — capped at 500 per query</span>
                <span x-show="rows.length === 0 && !loading">—</span>
            </div>
        </div>
    </div>

    {{-- ═════════════════════════════════════════════════════════════════════
         DRAWER — per-user assignment history + editor
         ═════════════════════════════════════════════════════════════════════ --}}
    <div x-show="drawer.open" x-cloak
         @keydown.escape.window="closeDrawer()"
         class="fixed inset-0 z-50 flex">
        <div x-show="drawer.open" x-transition.opacity @click="closeDrawer()"
             class="absolute inset-0 bg-ink-950/50 backdrop-blur-sm"></div>
        <aside x-show="drawer.open"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               class="relative ml-auto h-full w-full sm:max-w-2xl lg:max-w-3xl bg-white drawer-panel flex flex-col">

            <div class="h-16 px-4 sm:px-6 border-b border-ink-100 flex items-center gap-3 shrink-0 bg-white">
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] uppercase tracking-[0.14em] font-semibold text-ink-400">Assignment timeline</div>
                    <div class="text-base font-semibold text-ink-900 truncate" x-text="drawer.user?.full_name || drawer.user?.username || 'Loading…'"></div>
                </div>
                <button @click="closeDrawer()" class="h-9 w-9 grid place-items-center rounded-lg hover:bg-ink-100 text-ink-500" title="Close (Esc)">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto scrollbar-thin">
                <template x-if="!drawer.user && !drawer.loading">
                    <div class="p-6 text-sm text-ink-500">Select a user.</div>
                </template>
                <template x-if="drawer.loading">
                    <div class="p-6 space-y-3">
                        <div class="h-5 w-48 bg-ink-100 rounded shimmer"></div>
                        <div class="h-24 bg-ink-50 rounded shimmer"></div>
                        <div class="h-24 bg-ink-50 rounded shimmer"></div>
                    </div>
                </template>
                <template x-if="drawer.user">
                    <div class="p-4 sm:p-6 space-y-5">

                        {{-- USER header --}}
                        <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-3 flex items-center gap-3">
                            <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 grid place-items-center text-white font-bold shrink-0" x-text="initials(drawer.user.full_name || drawer.user.username)"></div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-ink-900 truncate" x-text="drawer.user.full_name"></div>
                                <div class="text-xs text-ink-500 truncate font-mono" x-text="drawer.user.email || drawer.user.username"></div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-ink-100 text-ink-700 border border-ink-200" x-text="drawer.user.role_key"></span>
                                    <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-brand-50 text-brand-700 border border-brand-200" x-text="drawer.user.country_code"></span>
                                </div>
                            </div>
                            <a :href="`{{ url('/admin/users') }}#user-${drawer.user.id}`" @click.prevent="goToUser(drawer.user.id)" class="text-xs font-semibold text-brand-700 hover:text-brand-500 whitespace-nowrap">Open in Users →</a>
                        </div>

                        {{-- ACTIVE POE banner --}}
                        <template x-if="drawer.active_poe">
                            <div class="rounded-xl border-2 border-brand-200 bg-gradient-to-br from-brand-50 to-white p-3 sm:p-4 flex items-start gap-3">
                                <div class="h-10 w-10 rounded-lg bg-brand-600 grid place-items-center text-white shrink-0">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-[10px] uppercase tracking-[0.14em] font-bold text-brand-700">Currently active POE</div>
                                    <div class="text-base font-semibold text-ink-900 truncate" x-text="drawer.active_poe.poe_code"></div>
                                    <div class="text-xs text-ink-500 font-mono truncate">
                                        <span x-text="drawer.active_poe.country_code"></span> ·
                                        <span x-text="drawer.active_poe.province_code || drawer.active_poe.pheoc_code"></span> ·
                                        <span x-text="drawer.active_poe.district_code"></span> ·
                                        since <span x-text="fmtDate(drawer.active_poe.starts_at)"></span>
                                    </div>
                                </div>
                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 border border-emerald-200 shrink-0">Single-active</span>
                            </div>
                        </template>
                        <template x-if="!drawer.active_poe && drawer.user.role_key === 'SCREENER'">
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                <strong>No active POE.</strong> SCREENER role requires a POE assignment — create one below.
                            </div>
                        </template>

                        {{-- NEW / TRANSFER FORM --}}
                        <details open class="rounded-xl border border-ink-100">
                            <summary class="px-3 py-2.5 flex items-center gap-2 bg-ink-50/70 rounded-t-xl">
                                <svg class="h-4 w-4 text-ink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span class="text-sm font-semibold text-ink-800" x-text="drawer.active_poe ? 'Transfer to a new scope' : 'Create assignment'"></span>
                                <svg class="h-4 w-4 ml-auto text-ink-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <form @submit.prevent="submitNew(false)" class="p-3 sm:p-4 space-y-3">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label class="text-xs font-semibold text-ink-600">RPHEOC / Province
                                            <span class="text-rose-500" x-show="geoNeeds('province_or_pheoc')">*</span>
                                        </label>
                                        <select x-model="form.pheoc_code" @change="form.district_code=''; form.poe_code=''"
                                                class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0">
                                            <option value="">— None —</option>
                                            <template x-for="p in pheocList()" :key="p"><option :value="p" x-text="p"></option></template>
                                        </select>
                                        <p class="text-[11px] text-rose-600 mt-1" x-text="formErrors['province_code'] || formErrors['pheoc_code']"></p>
                                    </div>
                                    <div>
                                        <label class="text-xs font-semibold text-ink-600">District
                                            <span class="text-rose-500" x-show="geoNeeds('district_code')">*</span>
                                        </label>
                                        <select x-model="form.district_code" @change="form.poe_code=''"
                                                :disabled="!form.pheoc_code"
                                                class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0 disabled:bg-ink-50 disabled:text-ink-400">
                                            <option value="">— None —</option>
                                            <template x-for="d in districtsFor(form.pheoc_code)" :key="d"><option :value="d" x-text="d"></option></template>
                                        </select>
                                        <p class="text-[11px] text-rose-600 mt-1" x-text="formErrors['district_code']"></p>
                                    </div>
                                    <div>
                                        <label class="text-xs font-semibold text-ink-600">POE
                                            <span class="text-rose-500" x-show="geoNeeds('poe_code')">*</span>
                                        </label>
                                        <select x-model="form.poe_code"
                                                :disabled="!form.district_code"
                                                class="mt-1 w-full h-10 rounded-lg border border-ink-200 px-3 text-sm focus:border-brand-500 focus:ring-0 disabled:bg-ink-50 disabled:text-ink-400">
                                            <option value="">— None —</option>
                                            <template x-for="p in poesFor(form.district_code)" :key="p"><option :value="p" x-text="p"></option></template>
                                        </select>
                                        <p class="text-[11px] text-rose-600 mt-1" x-text="formErrors['poe_code']"></p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-3 text-xs">
                                    <label class="inline-flex items-center gap-1.5"><input type="checkbox" x-model="form.is_primary" class="rounded border-ink-300"> Mark primary (demotes siblings)</label>
                                    <label class="inline-flex items-center gap-1.5"><input type="checkbox" x-model="form.is_active" class="rounded border-ink-300"> Active</label>
                                </div>
                                <div x-show="formErrors._top" class="rounded-lg border border-rose-200 bg-rose-50 text-rose-800 text-xs px-3 py-2" x-text="formErrors._top"></div>
                                <div class="flex items-center gap-2">
                                    <button type="submit" :disabled="saving"
                                            class="inline-flex items-center gap-1.5 h-10 px-4 rounded-lg bg-ink-900 hover:bg-ink-800 disabled:opacity-60 text-white text-sm font-semibold">
                                        <svg x-show="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                                        <span x-text="drawer.active_poe ? 'Transfer' : 'Create assignment'"></span>
                                    </button>
                                    <button type="button" @click="resetForm()" class="h-10 px-3 rounded-lg border border-ink-200 bg-white hover:border-ink-300 text-sm font-semibold text-ink-700">Reset</button>
                                </div>
                            </form>
                        </details>

                        {{-- TIMELINE --}}
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <h4 class="text-[10px] uppercase tracking-[0.14em] font-bold text-ink-500">Full history</h4>
                                <span class="text-[10px] text-ink-400" x-text="(drawer.assignments?.length || 0) + ' row(s)'"></span>
                            </div>
                            <ol class="relative pl-6 timeline-rail space-y-2">
                                <template x-if="!drawer.assignments || drawer.assignments.length === 0">
                                    <li class="text-sm text-ink-500 italic">No assignments on record.</li>
                                </template>
                                <template x-for="a in (drawer.assignments || [])" :key="a.id">
                                    <li class="relative">
                                        <span class="absolute -left-[22px] top-3 h-3 w-3 rounded-full ring-4 ring-white"
                                              :class="a.is_active == 1 ? (a.is_primary == 1 ? 'bg-brand-600' : 'bg-emerald-500') : 'bg-ink-300'"></span>
                                        <div class="rounded-lg border border-ink-100 bg-white p-3">
                                            <div class="flex items-center gap-2">
                                                <span x-show="a.is_primary == 1" class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-brand-50 text-brand-700 border border-brand-200">Primary</span>
                                                <span x-show="a.is_active == 1" class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                                                <span x-show="a.is_active != 1" class="text-[9px] font-bold uppercase px-1 py-0.5 rounded bg-ink-100 text-ink-600 border border-ink-200">Ended</span>
                                                <span class="ml-auto text-[10px] text-ink-400 font-mono">id <span x-text="a.id"></span></span>
                                            </div>
                                            <div class="mt-1.5 text-sm font-semibold text-ink-900 truncate">
                                                <span x-text="a.poe_code || (a.district_code ? 'District: '+a.district_code : (a.province_code || 'National'))"></span>
                                            </div>
                                            <div class="text-xs text-ink-500 font-mono truncate">
                                                <span x-text="a.country_code"></span>
                                                <span x-show="a.province_code"> · <span x-text="a.province_code"></span></span>
                                                <span x-show="a.district_code"> · <span x-text="a.district_code"></span></span>
                                                <span x-show="a.poe_code"> · <span x-text="a.poe_code"></span></span>
                                            </div>
                                            <div class="mt-1 text-[11px] text-ink-500">
                                                <span x-text="fmtDate(a.starts_at)"></span>
                                                <span x-show="a.ends_at"> → <span x-text="fmtDate(a.ends_at)"></span></span>
                                                <span x-show="!a.ends_at && a.is_active == 1"> → present</span>
                                            </div>
                                            {{-- Row actions --}}
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                <button x-show="a.is_primary != 1 && a.is_active == 1" @click="rowUpdate(a.id, {is_primary:true})" class="text-[11px] px-2 py-1 rounded border border-brand-200 bg-brand-50 hover:bg-brand-100 text-brand-700 font-semibold">Make primary</button>
                                                <button x-show="a.is_active != 1" @click="rowUpdate(a.id, {is_active:true})" class="text-[11px] px-2 py-1 rounded border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-semibold">Reactivate</button>
                                                <button x-show="a.is_active == 1" @click="confirmEnd(a)" class="text-[11px] px-2 py-1 rounded border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold">End</button>
                                            </div>
                                        </div>
                                    </li>
                                </template>
                            </ol>
                        </div>

                        {{-- EVENT LOG --}}
                        <details class="rounded-lg border border-ink-100" x-show="drawer.history?.length">
                            <summary class="px-3 py-2 flex items-center gap-2 bg-ink-50/60 rounded-t-lg">
                                <span class="text-sm font-semibold text-ink-800">Change log</span>
                                <span class="ml-auto text-xs text-ink-500" x-text="(drawer.history?.length || 0)"></span>
                            </summary>
                            <div class="p-3 space-y-1 max-h-72 overflow-y-auto scrollbar-thin">
                                <template x-for="h in (drawer.history || [])" :key="h.id">
                                    <div class="flex items-center gap-2 text-[11px] font-mono border-b border-ink-50 py-1">
                                        <span class="w-28 truncate font-semibold" x-text="h.event_type"></span>
                                        <span class="flex-1 text-ink-500 truncate" x-text="JSON.stringify(h.meta_json || h.detail_json || {})"></span>
                                        <span class="text-ink-400" x-text="fmtDate(h.created_at)"></span>
                                    </div>
                                </template>
                            </div>
                        </details>
                    </div>
                </template>
            </div>
        </aside>
    </div>

    {{-- ── Confirmation / 409 transfer modal ──────────────────────────── --}}
    <div x-show="modal.open" x-cloak x-transition.opacity
         class="fixed inset-0 z-[60] grid place-items-center p-4 bg-ink-950/60 backdrop-blur-sm"
         @click.self="modal.open=false" @keydown.escape.window="modal.open=false">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-panel-lg p-5">
            <div class="flex items-start gap-3">
                <div class="h-10 w-10 rounded-lg grid place-items-center shrink-0"
                     :class="modal.danger ? 'bg-rose-50 text-rose-600 border border-rose-200' : 'bg-amber-50 text-amber-600 border border-amber-200'">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h4 class="text-base font-semibold text-ink-900" x-text="modal.title"></h4>
                    <p class="mt-1 text-sm text-ink-600" x-text="modal.body"></p>
                    <template x-if="modal.details">
                        <pre class="mt-2 rounded-md bg-ink-50 border border-ink-100 text-[11px] font-mono p-2 overflow-x-auto" x-text="modal.details"></pre>
                    </template>
                </div>
            </div>
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
         :class="toast.tone === 'error' ? 'bg-rose-600 text-white' : (toast.tone === 'warn' ? 'bg-amber-500 text-white' : 'bg-ink-900 text-white')"
         x-text="toast.text"></div>
</div>

@push('scripts')
<script>
function assignmentsPage(seed){
    return {
        // seeded
        actorScope: seed.actorScope,
        pheocNames: seed.pheocNames,
        districtNames: seed.districtNames,
        poeNames: seed.poeNames,
        pheocDistricts: seed.pheocDistricts || {},
        districtPoes: seed.districtPoes || {},
        roleGeoRequirements: seed.roleGeoRequirements || {},
        assignmentsApi: seed.assignmentsApi,
        userAssignmentsApi: seed.userAssignmentsApi,
        rowApi: seed.rowApi,

        loading: false, error: null,
        rows: [],
        filters: { search: '', province_code: '', district_code: '', poe_code: '', activeOnly: true, primaryOnly: false },

        drawer: { open: false, loading: false, user: null, assignments: [], active_poe: null, history: [] },
        form: { pheoc_code: '', district_code: '', poe_code: '', is_primary: false, is_active: true },
        formErrors: {}, saving: false,

        modal: { open: false, title: '', body: '', details: '', confirm: null, confirmLabel: 'Confirm', danger: false },
        toast: { open: false, text: '', tone: 'info', _t: null },

        // ── init ───────────────────────────────────────────────────────
        init(){
            if (!localStorage.getItem('pheoc_token')) { this._bounceToLogin(); return; }
            this.reload(true);
        },

        // ── cascade helpers ────────────────────────────────────────────
        pheocList(){ return Object.keys(this.pheocDistricts).sort(); },
        districtsFor(p){ return p ? (this.pheocDistricts[p] || []).slice().sort() : []; },
        poesFor(d){ return d ? (this.districtPoes[d] || []).slice().sort() : []; },
        geoRequirementsFor(role){ return role ? (this.roleGeoRequirements[role] || []) : []; },
        geoNeeds(token){ return this.geoRequirementsFor(this.drawer.user?.role_key || '').includes(token); },

        // ── api ────────────────────────────────────────────────────────
        _csrf(){ return document.querySelector('meta[name=csrf-token]')?.content || ''; },
        _token(){ return localStorage.getItem('pheoc_token') || ''; },
        _bounceToLogin(){
            const next = encodeURIComponent(location.pathname + location.search);
            localStorage.removeItem('pheoc_token'); localStorage.removeItem('pheoc_user');
            location.replace('{{ url('/admin/login') }}?next=' + next);
        },
        async _req(url, opts = {}){
            const token = this._token();
            if (!token) { this._bounceToLogin(); return { status: 401, body: { ok:false, error:'No session' } }; }
            const res = await fetch(url, {
                method: opts.method || 'GET',
                headers: Object.assign({
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this._csrf(),
                    'Authorization': 'Bearer ' + token,
                }, opts.headers || {}),
                body: opts.body != null ? JSON.stringify(opts.body) : undefined,
            });
            const ct = res.headers.get('content-type') || '';
            const body = ct.includes('json') ? await res.json() : { ok:false, error: await res.text() };
            if (res.status === 401) { this._bounceToLogin(); }
            return { status: res.status, body };
        },

        // ── list ───────────────────────────────────────────────────────
        async reload(){
            this.loading = true; this.error = null;
            const q = new URLSearchParams();
            if (this.filters.search)         q.set('search', this.filters.search);
            if (this.filters.province_code)  q.set('province_code', this.filters.province_code);
            if (this.filters.district_code)  q.set('district_code', this.filters.district_code);
            if (this.filters.poe_code)       q.set('poe_code', this.filters.poe_code);
            if (this.filters.activeOnly)     q.set('active', '1');
            if (this.filters.primaryOnly)    q.set('primary', '1');
            q.set('limit', '500');
            const { status, body } = await this._req(this.assignmentsApi + '?' + q.toString());
            this.loading = false;
            if (status === 200 && body.ok) {
                this.rows = body.data.assignments || [];
            } else if (status === 401 || status === 403) {
                this.error = 'Not authorised — sign in as an admin. (API ' + status + ')';
            } else {
                this.error = body.error || ('Request failed · ' + status);
            }
        },
        resetFilters(){
            this.filters = { search:'', province_code:'', district_code:'', poe_code:'', activeOnly: true, primaryOnly: false };
            this.reload();
        },

        // ── drawer ─────────────────────────────────────────────────────
        async openUserDrawer(userId){
            this.drawer = { open: true, loading: true, user: null, assignments: [], active_poe: null, history: [] };
            this.resetForm();
            const { status, body } = await this._req(this.userAssignmentsApi + '/' + userId + '/assignments');
            this.drawer.loading = false;
            if (status === 200 && body.ok) {
                this.drawer.user = body.data.user;
                this.drawer.assignments = body.data.assignments || [];
                this.drawer.active_poe = body.data.active_poe || null;
                this.drawer.history = body.data.history || [];
                // Prefill form from current primary (handy for quick edits)
                const primary = (this.drawer.assignments || []).find(a => a.is_primary == 1 && a.is_active == 1) || {};
                this.form.pheoc_code = primary.pheoc_code || primary.province_code || '';
                this.form.district_code = primary.district_code || '';
                this.form.poe_code = primary.poe_code || '';
            } else {
                this.showToast(body.error || 'Could not load user', 'error');
                this.drawer.open = false;
            }
        },
        closeDrawer(){ this.drawer.open = false; },
        goToUser(id){ window.open('{{ url('/admin/users') }}', '_blank'); },

        resetForm(){
            this.form = { pheoc_code: '', district_code: '', poe_code: '', is_primary: false, is_active: true };
            this.formErrors = {};
        },

        async submitNew(force){
            if (!this.drawer.user) return;
            this.formErrors = {};
            this.saving = true;
            const payload = {
                country_code: this.drawer.user.country_code || 'UG',
                province_code: this.form.pheoc_code || null,
                pheoc_code:    this.form.pheoc_code || null,
                district_code: this.form.district_code || null,
                poe_code:      this.form.poe_code || null,
                is_primary:    !!this.form.is_primary,
                is_active:     !!this.form.is_active,
            };
            if (force) payload.force = true;
            const { status, body } = await this._req(this.userAssignmentsApi + '/' + this.drawer.user.id + '/assignments', { method: 'POST', body: payload });
            this.saving = false;

            if ((status === 200 || status === 201) && body.ok) {
                this.showToast(force ? 'Transferred' : 'Assignment created', 'info');
                await this.openUserDrawer(this.drawer.user.id);
                this.reload();
                return;
            }
            if (status === 409 && body.code === 'SINGLE_ACTIVE_POE') {
                const b = body.blocking || {};
                this.modal = {
                    open: true, danger: false, title: 'Transfer requires override',
                    body: 'This user is already active at POE "' + (b.poe_code || '—') + '". Transferring will end the previous assignment (kept in history) and create this new one.',
                    details: JSON.stringify({
                        current_poe: b.poe_code,
                        current_district: b.district_code,
                        since: b.starts_at,
                    }, null, 2),
                    confirmLabel: 'Transfer anyway',
                    confirm: async () => { this.modal.open = false; await this.submitNew(true); },
                };
                return;
            }
            if (status === 422) {
                const errs = body.errors || {};
                const flat = {};
                for (const k of Object.keys(errs)) flat[k] = Array.isArray(errs[k]) ? errs[k][0] : String(errs[k]);
                flat._top = 'Please correct the highlighted fields.';
                this.formErrors = flat;
                return;
            }
            this.formErrors = { _top: body.error || 'Request failed · ' + status };
        },

        // ── row actions ────────────────────────────────────────────────
        async rowUpdate(id, patch, force = false){
            if (force) patch.force = true;
            const { status, body } = await this._req(this.rowApi + '/' + id, { method: 'PATCH', body: patch });
            if (status === 200 && body.ok) {
                this.showToast('Updated', 'info');
                await this.openUserDrawer(this.drawer.user.id);
                this.reload();
                return;
            }
            if (status === 409 && body.code === 'SINGLE_ACTIVE_POE') {
                const b = body.blocking || {};
                this.modal = {
                    open: true, danger: false, title: 'Transfer requires override',
                    body: 'Reactivating this row means the user would be at two POEs. The server will auto-end the current active POE "' + (b.poe_code || '—') + '".',
                    details: '', confirmLabel: 'Override',
                    confirm: async () => { this.modal.open = false; await this.rowUpdate(id, patch, true); },
                };
                return;
            }
            this.showToast(body.error || 'Update failed', 'error');
        },
        confirmEnd(a){
            this.modal = {
                open: true, danger: true, title: 'End this assignment?',
                body: 'Setting is_active=0 and ends_at=now. The row stays in history. If this is the only active row, another will be promoted to primary automatically.',
                details: JSON.stringify({ poe: a.poe_code, district: a.district_code, primary: a.is_primary == 1 }, null, 2),
                confirmLabel: 'End assignment',
                confirm: async () => {
                    this.modal.open = false;
                    const { status, body } = await this._req(this.rowApi + '/' + a.id, { method: 'DELETE' });
                    if (status === 200 && body.ok) {
                        this.showToast('Assignment ended', 'info');
                        await this.openUserDrawer(this.drawer.user.id);
                        this.reload();
                    } else {
                        this.showToast(body.error || 'Could not end', 'error');
                    }
                },
            };
        },

        // ── hardcoded AI narrator ──────────────────────────────────────
        get copilot(){
            const sents = [];
            if (!this.rows || this.rows.length === 0) return { sentences: [] };
            const byPoe = {};
            const byDistrict = {};
            const byUser = {};
            for (const r of this.rows) {
                if (r.poe_code) byPoe[r.poe_code] = (byPoe[r.poe_code] || 0) + 1;
                if (r.district_code) byDistrict[r.district_code] = (byDistrict[r.district_code] || 0) + 1;
                byUser[r.user_id] = (byUser[r.user_id] || 0) + 1;
            }
            const poeEntries = Object.entries(byPoe).sort((a,b)=>b[1]-a[1]);
            const heavyPoes = poeEntries.filter(([,n]) => n >= 3);
            const zeroCoverage = this.pheocList().filter(p => !this.rows.some(r => r.province_code === p || r.pheoc_code === p));
            const multiAssign = Object.entries(byUser).filter(([,n]) => n > 1).length;

            if (heavyPoes.length > 0) {
                const top = heavyPoes[0];
                sents.push(`POE "${top[0]}" has ${top[1]} active assignments — highest concentration. Cross-check roster for duplicates.`);
            }
            if (zeroCoverage.length > 0) {
                sents.push(`${zeroCoverage.length} RPHEOC${zeroCoverage.length>1?'s have':' has'} no assigned staff in the current filter${zeroCoverage.length<=3 ? ' (' + zeroCoverage.join(', ') + ')' : ''}.`);
            }
            if (multiAssign > 0) {
                sents.push(`${multiAssign} user${multiAssign>1?'s have':' has'} more than one active assignment row — review primary flag.`);
            }
            if (sents.length === 0) sents.push(`Coverage is balanced across ${poeEntries.length} POE${poeEntries.length!==1?'s':''} and ${Object.keys(byDistrict).length} district${Object.keys(byDistrict).length!==1?'s':''}.`);
            return { sentences: sents };
        },

        // ── formatters ─────────────────────────────────────────────────
        initials(s){ return (s||'?').trim().split(/\s+/).map(x => x[0]).slice(0,2).join('').toUpperCase() || '?'; },
        fmtDate(v){
            if (!v) return '—';
            const d = new Date(String(v).replace(' ','T') + (String(v).includes('T') ? '' : 'Z'));
            if (isNaN(d)) return String(v);
            return d.toLocaleString(undefined, { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
        },
        showToast(text, tone='info'){
            this.toast.text = text; this.toast.tone = tone; this.toast.open = true;
            clearTimeout(this.toast._t);
            this.toast._t = setTimeout(() => this.toast.open = false, 3500);
        },
    };
}
</script>
@endpush
@endsection

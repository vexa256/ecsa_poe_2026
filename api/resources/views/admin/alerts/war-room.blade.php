{{-- ============================================================================
  /admin/alerts/{id} — WAR ROOM
  ----------------------------------------------------------------------------
  Premium Design Edition. Shadcn chassis · Apple clarity · Microsoft depth ·
  Google data-viz · Tesla ambient intelligence.

  One-conversation-per-case workspace. Every field the mobile Secondary
  Screening captured surfaces here. Every action is wizard-guided. Every
  chart teaches. Every link is wired.
============================================================================ --}}
@extends('admin.layout')

@section('title', 'Case')
@section('subtitle', 'Live case intelligence · what to do next')

@push('head')
<style type="text/tailwindcss">
    @layer components {
        /* Urgency rail — subtle top-border accent on the hero only */
        .wr-rail-urgent  { box-shadow: inset 0 3px 0 0 hsl(var(--destructive)); }
        .wr-rail-high    { box-shadow: inset 0 3px 0 0 hsl(24 90% 48%); }
        .wr-rail-normal  { box-shadow: inset 0 3px 0 0 hsl(var(--muted-foreground) / 0.4); }
        .wr-rail-low     { box-shadow: inset 0 3px 0 0 hsl(var(--border)); }

        /* Chip vocabulary — Tesla semantic accent, never decorative */
        .wr-chip          { @apply inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium; }
        .wr-chip-muted    { @apply bg-muted text-muted-foreground border-transparent; }
        .wr-chip-outline  { @apply border-border text-foreground; }
        .wr-chip-warn     { @apply border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400; }
        .wr-chip-danger   { @apply border-destructive/40 bg-destructive/10 text-destructive; }
        .wr-chip-good     { @apply border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400; }
        .wr-chip-info     { @apply border-primary/30 bg-primary/5 text-foreground; }

        /* Key-value rows — Apple deference, content is the hero */
        .wr-kv    { @apply flex items-start justify-between gap-3 py-2 border-b border-border/60 last:border-b-0; }
        .wr-kv-k  { @apply text-[12px] text-muted-foreground shrink-0; }
        .wr-kv-v  { @apply text-[13px] text-right break-words min-w-0; }

        /* Wizard pick-row — clear hover + selected states */
        .wr-pick {
            @apply w-full text-left rounded-md border bg-background px-3 py-2.5
                   hover:bg-accent/50 transition-all cursor-pointer;
        }
        .wr-pick[data-selected=true] { @apply border-primary ring-1 ring-primary bg-accent/40; }

        /* Sub-tabs (shadcn secondary tabs) */
        .wr-subtabs       { @apply inline-flex gap-1 p-0.5 rounded-md bg-muted text-muted-foreground text-[12px] w-full overflow-x-auto scrollbar-thin; }
        .wr-subtab        { @apply px-2.5 py-1 rounded-sm whitespace-nowrap shrink-0; }
        .wr-subtab[data-state=active] { @apply bg-background text-foreground shadow-sm font-medium; }

        /* Vital gauge — Material data-viz, minimal colour */
        .wr-gauge      { @apply relative h-1.5 rounded-full bg-muted overflow-hidden; }
        .wr-gauge-band { @apply absolute top-0 bottom-0 bg-emerald-500/15; }
        .wr-gauge-fill { @apply absolute top-0 bottom-0 bg-foreground/70 rounded-full; }
        .wr-gauge-fill.is-warn { @apply bg-amber-500; }
        .wr-gauge-fill.is-crit { @apply bg-destructive; }
        .wr-gauge-needle { @apply absolute top-[-2px] bottom-[-2px] w-0.5 bg-foreground; }

        /* Differential bars */
        .wr-bar-row    { @apply grid grid-cols-[1fr_auto] gap-3 items-center; }
        .wr-bar-track  { @apply relative h-6 rounded-md bg-muted overflow-hidden; }
        .wr-bar-fill   { @apply absolute inset-y-0 left-0 bg-foreground/80 rounded-md flex items-center px-2 text-[11px] font-medium text-primary-foreground; }
        .wr-bar-fill.is-top { @apply bg-primary; }

        /* Timeline dot */
        .wr-dot         { @apply h-6 w-6 rounded-full border-2 bg-background flex items-center justify-center; }

        /* Response-clock radial */
        .wr-clock {
            width: 76px; height: 76px; border-radius: 9999px;
            background: conic-gradient(hsl(var(--foreground) / 0.85) var(--p, 0deg), hsl(var(--muted)) 0deg);
            display: grid; place-items: center;
        }
        .wr-clock.is-warn { background: conic-gradient(hsl(24 90% 48%) var(--p, 0deg), hsl(var(--muted)) 0deg); }
        .wr-clock.is-crit { background: conic-gradient(hsl(var(--destructive)) var(--p, 0deg), hsl(var(--muted)) 0deg); }
        .wr-clock-inner { width: 60px; height: 60px; border-radius: 9999px; background: hsl(var(--card)); display: grid; place-items: center; }

        /* Soft-fade-in for newly-rendered blocks */
        @keyframes wr-fade { from { opacity:0; transform: translateY(4px);} to { opacity:1; transform: none;} }
        .wr-fade { animation: wr-fade .25s ease-out both; }
    }
</style>
@endpush

@section('content')
<div x-data="warRoom({{ $alertId }})" x-init="init()" class="space-y-5 max-w-6xl pb-24 lg:pb-6">

    {{-- ── LOADING / ERROR ───────────────────────────────── --}}
    <template x-if="loading && !alert">
        <div class="space-y-3">
            <div class="rounded-lg border bg-card p-5 space-y-2"><div class="skeleton h-4 w-24"></div><div class="skeleton h-6 w-2/3"></div><div class="skeleton h-3 w-1/2"></div></div>
            <div class="grid gap-3 sm:grid-cols-2"><div class="rounded-lg border bg-card p-4 space-y-2"><div class="skeleton h-3 w-24"></div><div class="skeleton h-4 w-3/4"></div></div><div class="rounded-lg border bg-card p-4 space-y-2"><div class="skeleton h-3 w-24"></div><div class="skeleton h-4 w-3/4"></div></div></div>
        </div>
    </template>
    <template x-if="loadError">
        <div class="alert alert-destructive">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div><h4 class="alert-title">We couldn't load this case</h4><div class="alert-description" x-text="loadError"></div></div>
        </div>
    </template>

    {{-- ═══════════════════════════════════════════════════════════
         HERO CARD · briefing + primary actions (Apple deference)
         ═══════════════════════════════════════════════════════════ --}}
    <template x-if="alert">
        <section class="rounded-lg border bg-card wr-fade" :class="heroRail">
            <div class="p-5 sm:p-6 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="badge" :class="urgencyBadge" x-text="humanUrgency(alert.risk_level)"></span>
                        <span class="badge badge-outline" x-text="humanStatus(alert.status)"></span>
                        <template x-if="alert.ihr_tier && alert.ihr_tier !== 'none'">
                            <span class="wr-chip wr-chip-warn">
                                <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                                Reportable to WHO
                            </span>
                        </template>
                        <template x-if="isOverdue">
                            <span class="wr-chip wr-chip-danger">
                                <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3"/></svg>
                                Overdue response
                            </span>
                        </template>
                        <template x-if="alert.reopen_count > 0">
                            <span class="wr-chip wr-chip-muted">Reopened <span class="tabular-nums ml-0.5" x-text="alert.reopen_count"></span>×</span>
                        </template>
                        <span class="wr-chip wr-chip-outline" x-text="'#' + alert.id"></span>
                    </div>
                    <span class="help-text tabular-nums shrink-0" x-text="humanAgo(alert.server_received_at)"></span>
                </div>

                <div class="space-y-1.5">
                    <h2 class="text-xl sm:text-2xl font-semibold tracking-tight" x-text="suspectedCaseTitle"></h2>
                    <p class="text-sm text-muted-foreground" x-text="locationSentence"></p>
                </div>

                <div class="rounded-md bg-muted/40 border border-border/60 p-4 space-y-1.5">
                    <div class="flex items-center gap-2">
                        <div class="h-6 w-6 rounded-md bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">Case briefing</p>
                    </div>
                    <p class="text-sm text-foreground/90 leading-relaxed" x-html="briefing"></p>
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <template x-for="a in primaryActions" :key="a.key">
                        <button type="button"
                                class="btn btn-xs"
                                :class="a.primary ? 'btn-default' : 'btn-outline'"
                                @click="runAction(a.key)">
                            <span x-html="a.icon" class="mr-1.5"></span>
                            <span x-text="a.label"></span>
                        </button>
                    </template>
                </div>
            </div>
        </section>
    </template>

    {{-- ═══════════════════════════════════════════════════════════
         WHO'S ON IT + WHAT'S DELAYING (Tesla ambient intelligence)
         ═══════════════════════════════════════════════════════════ --}}
    <template x-if="alert">
        <section class="grid gap-3 sm:grid-cols-2 wr-fade">
            {{-- Owner --}}
            <div class="rounded-lg border bg-card p-4">
                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-2">Who's working on it</p>
                <template x-if="alert.status === 'CLOSED'">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="min-w-0"><p class="text-sm font-medium">Resolved</p><p class="help-text">Closed <span x-text="humanAgo(alert.closed_at)"></span> — <span x-text="closeCategoryLabel(alert.close_category) || 'no reason recorded'"></span></p></div>
                    </div>
                </template>
                <template x-if="alert.status === 'ACKNOWLEDGED' && alert.acknowledged_by_name">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-primary/10 text-primary text-sm font-semibold flex items-center justify-center shrink-0" x-text="initials(alert.acknowledged_by_name)"></div>
                        <div class="min-w-0 flex-1"><p class="text-sm font-medium truncate" x-text="alert.acknowledged_by_name"></p><p class="help-text">Took this case <span x-text="humanAgo(alert.acknowledged_at)"></span></p></div>
                    </div>
                </template>
                <template x-if="alert.status === 'OPEN'">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full border-2 border-dashed border-muted-foreground/40 flex items-center justify-center shrink-0">
                            <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        </div>
                        <div class="min-w-0 flex-1"><p class="text-sm font-medium">Nobody yet</p><p class="help-text">Landed <span x-text="humanAgo(alert.server_received_at)"></span> — somebody needs to pick this up.</p></div>
                    </div>
                </template>
            </div>

            {{-- Delay / state --}}
            <div class="rounded-lg border p-4" :class="hasDelay ? 'border-amber-500/40 bg-amber-500/5' : 'bg-card'">
                <p class="text-[11px] uppercase tracking-[0.12em] font-semibold mb-2"
                   :class="hasDelay ? 'text-amber-700 dark:text-amber-400' : 'text-muted-foreground'"
                   x-text="hasDelay ? `What's delaying this` : `What's happening`"></p>
                <ul class="space-y-1.5 text-sm">
                    <template x-for="line in statusLines" :key="line">
                        <li class="flex items-start gap-2">
                            <span class="mt-1 h-1.5 w-1.5 rounded-full shrink-0" :class="hasDelay ? 'bg-amber-500' : 'bg-muted-foreground/60'"></span>
                            <span x-html="line"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </section>
    </template>

    {{-- ═══════════════════════════════════════════════════════════
         PRIMARY NAVIGATION — 4 tabs (Tesla reduction)
         ═══════════════════════════════════════════════════════════ --}}
    <template x-if="alert">
        <section class="space-y-3 wr-fade">
            <div class="tabs-list w-full sm:w-fit" role="tablist">
                <template x-for="t in primaryTabs" :key="t.key">
                    <button class="tabs-trigger flex-1 sm:flex-none sm:px-4"
                            :data-state="tab === t.key ? 'active' : ''"
                            @click="tab = t.key">
                        <span x-text="t.label"></span>
                        <template x-if="t.count !== undefined && t.count !== null">
                            <span class="ml-2 text-[11px] tabular-nums text-muted-foreground" x-text="t.count"></span>
                        </template>
                    </button>
                </template>
            </div>

            {{-- ─────── BRIEF · stats + 9 visualisations ─────── --}}
            <div x-show="tab === 'brief'" class="space-y-4 wr-fade">

                {{-- KPI strip --}}
                <div class="grid gap-3 grid-cols-2 lg:grid-cols-5">
                    <template x-for="k in briefKpis" :key="k.label">
                        <div class="rounded-lg border bg-card p-4">
                            <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold" x-text="k.label"></p>
                            <p class="mt-1 text-lg sm:text-xl font-semibold tracking-tight tabular-nums" :class="k.tone || ''" x-text="k.value"></p>
                            <p class="help-text mt-1" x-text="k.hint"></p>
                        </div>
                    </template>
                </div>

                {{-- Two-column: case progress + response clock --}}
                <div class="grid gap-4 md:grid-cols-2">
                    {{-- Case progress --}}
                    <div class="rounded-lg border bg-card p-5">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">Case progress</p>
                        </div>
                        <div class="mt-3 space-y-3">
                            <template x-for="s in progressStages" :key="s.key">
                                <div class="flex items-center gap-3">
                                    <div class="relative shrink-0">
                                        <div class="h-6 w-6 rounded-full border-2 flex items-center justify-center"
                                             :class="s.state === 'done' ? 'bg-primary border-primary text-primary-foreground' : (s.state === 'active' ? 'border-primary text-primary' : 'border-muted-foreground/30 text-muted-foreground')">
                                            <template x-if="s.state === 'done'"><svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></template>
                                            <template x-if="s.state === 'active'"><span class="h-2 w-2 rounded-full bg-primary"></span></template>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm" :class="s.state === 'pending' ? 'text-muted-foreground' : 'font-medium'" x-text="s.label"></p>
                                        <p class="help-text" x-text="s.meta"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Response clock --}}
                    <div class="rounded-lg border bg-card p-5">
                        <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">Response clock</p>
                        <div class="mt-4 flex items-center gap-5">
                            <div class="wr-clock shrink-0"
                                 :class="responseClock.tone"
                                 :style="'--p: ' + responseClock.deg + 'deg'">
                                <div class="wr-clock-inner">
                                    <div class="text-center">
                                        <div class="text-sm font-semibold tabular-nums" x-text="responseClock.display"></div>
                                        <div class="text-[10px] text-muted-foreground uppercase tracking-wider">of 24h</div>
                                    </div>
                                </div>
                            </div>
                            <div class="min-w-0 flex-1 space-y-1">
                                <p class="text-sm font-medium" x-text="responseClock.title"></p>
                                <p class="help-text" x-text="responseClock.subtitle"></p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Differential bar chart --}}
                <div class="rounded-lg border bg-card p-5" x-show="suspectedDiseases.length > 0">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">What the engine thinks this could be</p>
                        <span class="help-text">Top <span x-text="suspectedDiseases.length"></span></span>
                    </div>
                    <div class="space-y-2.5">
                        <template x-for="(d, idx) in suspectedDiseases" :key="d.id">
                            <div class="wr-bar-row">
                                <div class="wr-bar-track">
                                    <div class="wr-bar-fill"
                                         :class="idx === 0 ? 'is-top' : ''"
                                         :style="'width: ' + Math.max(12, Math.min(100, Number(d.confidence) || 0)) + '%'">
                                        <span class="truncate" x-text="d.disease_name"></span>
                                    </div>
                                </div>
                                <span class="text-[11px] tabular-nums text-muted-foreground w-12 text-right" x-text="(Number(d.confidence)||0).toFixed(1) + '%'"></span>
                            </div>
                        </template>
                    </div>
                    <p class="help-text mt-3">Ranked by the mobile-app engine's confidence score. The highest bar is what we've named this case as.</p>
                </div>

                {{-- Travel timeline --}}
                <div class="rounded-lg border bg-card p-5" x-show="travelCountries.length > 0">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">Where the traveller has been</p>
                        <span class="help-text">Last <span x-text="travelCountries.length"></span> recorded</span>
                    </div>
                    <ul class="divide-y">
                        <template x-for="c in travelCountries" :key="c.id">
                            <li class="py-2.5 flex items-center gap-3">
                                <span class="wr-chip" :class="c.travel_role === 'VISITED' ? 'wr-chip-info' : 'wr-chip-muted'" x-text="c.travel_role === 'VISITED' ? 'Visited' : 'Transit'"></span>
                                <span class="text-sm font-medium" x-text="c.country_code"></span>
                                <span class="help-text ml-auto">
                                    <template x-if="c.arrival_date && c.departure_date"><span><span x-text="c.arrival_date"></span> → <span x-text="c.departure_date"></span></span></template>
                                    <template x-if="c.arrival_date && !c.departure_date"><span>arrived <span x-text="c.arrival_date"></span></span></template>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- Activity heatmap (last 14 days) --}}
                <div class="rounded-lg border bg-card p-5" x-show="timeline.length > 0">
                    <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-3">Activity on this case — last 14 days</p>
                    <div class="grid gap-0.5" style="grid-template-columns: auto repeat(14, minmax(0, 1fr));">
                        <div></div>
                        <template x-for="d in heatmapHeaders" :key="d">
                            <div class="text-[9px] text-muted-foreground text-center" x-text="d"></div>
                        </template>
                        <template x-for="row in heatmap" :key="row.label">
                            <template x-for="(cell, ci) in ['label'].concat(row.cells)" :key="ci">
                                <template x-if="ci === 0">
                                    <div class="text-[10px] text-muted-foreground pr-2 text-right py-0.5" x-text="row.label"></div>
                                </template>
                                <template x-if="ci !== 0">
                                    <div class="h-4 rounded-sm" :class="heatCellClass(cell)" :title="cell + ' event(s)'"></div>
                                </template>
                            </template>
                        </template>
                    </div>
                    <div class="flex items-center gap-1.5 mt-3">
                        <span class="help-text">Less</span>
                        <span class="h-3 w-3 rounded-sm bg-muted"></span>
                        <span class="h-3 w-3 rounded-sm bg-primary/20"></span>
                        <span class="h-3 w-3 rounded-sm bg-primary/50"></span>
                        <span class="h-3 w-3 rounded-sm bg-primary/80"></span>
                        <span class="h-3 w-3 rounded-sm bg-primary"></span>
                        <span class="help-text">More</span>
                    </div>
                </div>

                {{-- Officer notes (if present) --}}
                <div class="rounded-lg border bg-card p-5" x-show="screening && screening.officer_notes">
                    <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-2">Officer's notes from screening</p>
                    <blockquote class="border-l-2 border-primary/30 pl-3 text-sm text-foreground/90 whitespace-pre-wrap" x-text="screening && screening.officer_notes"></blockquote>
                </div>
            </div>

            {{-- ─────── TRAVELLER · identity · travel · contact ─────── --}}
            <div x-show="tab === 'traveller'" x-cloak class="space-y-4 wr-fade">
                <div class="wr-subtabs" role="tablist">
                    <button class="wr-subtab" :data-state="subTrav==='identity' ? 'active' : ''" @click="subTrav='identity'">Identity</button>
                    <button class="wr-subtab" :data-state="subTrav==='travel' ? 'active' : ''" @click="subTrav='travel'">Travel</button>
                    <button class="wr-subtab" :data-state="subTrav==='contact' ? 'active' : ''" @click="subTrav='contact'">Contact</button>
                    <button class="wr-subtab" :data-state="subTrav==='record' ? 'active' : ''" @click="subTrav='record'">Case record</button>
                </div>

                {{-- Identity --}}
                <div x-show="subTrav === 'identity'" class="rounded-lg border bg-card p-5">
                    <template x-if="!screening">
                        <p class="description">No traveller details were captured during screening.</p>
                    </template>
                    <template x-if="screening">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-0.5">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-1">Who they are</p>
                                <div class="wr-kv"><span class="wr-kv-k">Full name</span><span class="wr-kv-v" x-text="maskName(screening.traveler_full_name) || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Initials</span><span class="wr-kv-v" x-text="screening.traveler_initials || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Anonymous code</span><span class="wr-kv-v font-mono text-[12px]" x-text="screening.traveler_anonymous_code || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Gender</span><span class="wr-kv-v" x-text="formatGender(screening.traveler_gender) || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Age</span><span class="wr-kv-v" x-text="screening.traveler_age_years ? screening.traveler_age_years + ' years' : '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Date of birth</span><span class="wr-kv-v" x-text="screening.traveler_dob || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Nationality</span><span class="wr-kv-v" x-text="screening.traveler_nationality_country_code || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Occupation</span><span class="wr-kv-v" x-text="screening.traveler_occupation || '—'"></span></div>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-1">Travel document</p>
                                <div class="wr-kv"><span class="wr-kv-k">Type</span><span class="wr-kv-v" x-text="humanDocType(screening.travel_document_type)"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Number</span><span class="wr-kv-v font-mono text-[12px]" x-text="maskDoc(screening.travel_document_number) || '—'"></span></div>
                                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-1 mt-4">Residence</p>
                                <div class="wr-kv"><span class="wr-kv-k">Country</span><span class="wr-kv-v" x-text="screening.residence_country_code || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Address</span><span class="wr-kv-v" x-text="screening.residence_address_text || '—'"></span></div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Travel --}}
                <div x-show="subTrav === 'travel'" x-cloak class="rounded-lg border bg-card p-5">
                    <template x-if="!screening">
                        <p class="description">No travel details recorded.</p>
                    </template>
                    <template x-if="screening">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-0.5">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-1">This journey</p>
                                <div class="wr-kv"><span class="wr-kv-k">Coming from</span><span class="wr-kv-v" x-text="screening.journey_start_country_code || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Port of departure</span><span class="wr-kv-v" x-text="screening.embarkation_port_city || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Travel mode</span><span class="wr-kv-v" x-text="humanConveyance(screening.conveyance_type)"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Reference</span><span class="wr-kv-v font-mono text-[12px]" x-text="screening.conveyance_identifier || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Seat</span><span class="wr-kv-v" x-text="screening.seat_number || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Arrived</span><span class="wr-kv-v" x-text="humanDate(screening.arrival_datetime)"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Departed</span><span class="wr-kv-v" x-text="humanDate(screening.departure_datetime)"></span></div>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-1">Where they're going</p>
                                <div class="wr-kv"><span class="wr-kv-k">District</span><span class="wr-kv-v" x-text="screening.destination_district_code || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Address</span><span class="wr-kv-v" x-text="screening.destination_address_text || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Purpose</span><span class="wr-kv-v" x-text="screening.purpose_of_travel || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Length of stay</span><span class="wr-kv-v" x-text="screening.planned_length_of_stay_days ? screening.planned_length_of_stay_days + ' days' : '—'"></span></div>
                            </div>
                            <div class="sm:col-span-2" x-show="travelCountries.length > 0">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-2 mt-2">Countries visited in last 30 days</p>
                                <ul class="divide-y border rounded-md">
                                    <template x-for="c in travelCountries" :key="c.id">
                                        <li class="py-2 px-3 flex items-center gap-3">
                                            <span class="wr-chip" :class="c.travel_role === 'VISITED' ? 'wr-chip-info' : 'wr-chip-muted'" x-text="c.travel_role === 'VISITED' ? 'Visited' : 'Transit'"></span>
                                            <span class="text-sm font-medium" x-text="c.country_code"></span>
                                            <span class="help-text ml-auto"><span x-text="c.arrival_date || '—'"></span> → <span x-text="c.departure_date || '—'"></span></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Contact --}}
                <div x-show="subTrav === 'contact'" x-cloak class="rounded-lg border bg-card p-5">
                    <template x-if="!screening">
                        <p class="description">No contact details recorded.</p>
                    </template>
                    <template x-if="screening">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-0.5">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-1">Primary contact</p>
                                <div class="wr-kv"><span class="wr-kv-k">Phone</span><span class="wr-kv-v font-mono text-[12px]" x-text="maskPhone(screening.phone_number) || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Alternative phone</span><span class="wr-kv-v font-mono text-[12px]" x-text="maskPhone(screening.alternative_phone) || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Email</span><span class="wr-kv-v font-mono text-[12px]" x-text="maskEmail(screening.email) || '—'"></span></div>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-1">Emergency contact</p>
                                <div class="wr-kv"><span class="wr-kv-k">Name</span><span class="wr-kv-v" x-text="screening.emergency_contact_name || '—'"></span></div>
                                <div class="wr-kv"><span class="wr-kv-k">Phone</span><span class="wr-kv-v font-mono text-[12px]" x-text="maskPhone(screening.emergency_contact_phone) || '—'"></span></div>
                            </div>
                            <p class="help-text sm:col-span-2 mt-2">Contact details are masked unless you're senior enough to see the full value. Use a handoff to share the full details with the responding team.</p>
                        </div>
                    </template>
                </div>

                {{-- CASE RECORD · every column captured during screening --}}
                <div x-show="subTrav === 'record'" x-cloak class="space-y-4">
                    <template x-if="!screening">
                        <div class="rounded-lg border bg-card p-5">
                            <p class="description">No screening record linked to this case. The alert was raised outside the mobile Secondary Screening flow.</p>
                        </div>
                    </template>
                    <template x-if="screening">
                        <div class="space-y-4">
                            {{-- Disposition / outcome --}}
                            <div class="rounded-lg border bg-card p-5">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-3">Screening outcome</p>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-0.5">
                                        <div class="wr-kv"><span class="wr-kv-k">Case status</span><span class="wr-kv-v"><span class="wr-chip wr-chip-outline" x-text="humanCaseStatus(screening.case_status)"></span></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Final disposition</span><span class="wr-kv-v" x-text="humanDisposition(screening.final_disposition)"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Disposition details</span><span class="wr-kv-v" x-text="screening.disposition_details || 'Not recorded'"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Screening outcome</span><span class="wr-kv-v" x-text="screening.screening_outcome || 'Not recorded'"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Risk level</span><span class="wr-kv-v" x-text="humanUrgency(screening.risk_level) || 'Not recorded'"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Syndrome classification</span><span class="wr-kv-v" x-text="humanSyndrome(screening.syndrome_classification)"></span></div>
                                    </div>
                                    <div class="space-y-0.5">
                                        <div class="wr-kv"><span class="wr-kv-k">Follow-up required</span><span class="wr-kv-v" x-text="Number(screening.followup_required) === 1 ? 'Yes' : 'No'"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Follow-up level</span><span class="wr-kv-v" x-text="humanLevel(screening.followup_assigned_level)"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Opened</span><span class="wr-kv-v" x-text="humanDate(screening.opened_at)"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Opened timezone</span><span class="wr-kv-v font-mono text-[12px]" x-text="screening.opened_timezone || '—'"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Dispositioned</span><span class="wr-kv-v" x-text="humanDate(screening.dispositioned_at)"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Closed</span><span class="wr-kv-v" x-text="humanDate(screening.closed_at)"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Officer notes --}}
                            <div class="rounded-lg border bg-card p-5">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-2">Officer's notes</p>
                                <template x-if="screening.officer_notes">
                                    <blockquote class="border-l-2 border-primary/30 pl-3 text-sm text-foreground/90 whitespace-pre-wrap" x-text="screening.officer_notes"></blockquote>
                                </template>
                                <template x-if="!screening.officer_notes">
                                    <p class="description">No notes were written during screening.</p>
                                </template>
                            </div>

                            {{-- Links to related records --}}
                            <div class="rounded-lg border bg-card p-5">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-3">Linked records</p>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-0.5">
                                        <div class="wr-kv"><span class="wr-kv-k">Alert ID</span><span class="wr-kv-v font-mono">#<span x-text="alert.id"></span></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Alert code</span><span class="wr-kv-v font-mono text-[12px]" x-text="alert.alert_code || '—'"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Screening ID</span><span class="wr-kv-v font-mono">#<span x-text="screening.id"></span></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Primary screening</span><span class="wr-kv-v font-mono">#<span x-text="screening.primary_screening_id || '—'"></span></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Notification</span><span class="wr-kv-v font-mono">#<span x-text="screening.notification_id || '—'"></span></span></div>
                                    </div>
                                    <div class="space-y-0.5">
                                        <div class="wr-kv"><span class="wr-kv-k">Opened by user</span><span class="wr-kv-v font-mono">#<span x-text="screening.opened_by_user_id || '—'"></span></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Country</span><span class="wr-kv-v font-mono" x-text="screening.country_code"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Region</span><span class="wr-kv-v" x-text="screening.province_code || screening.pheoc_code || '—'"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">District</span><span class="wr-kv-v" x-text="screening.district_code || '—'"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Port of entry</span><span class="wr-kv-v font-medium" x-text="screening.poe_code || '—'"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Audit / device --}}
                            <details class="rounded-lg border bg-card p-5">
                                <summary class="cursor-pointer list-none flex items-center justify-between">
                                    <span class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">Mobile audit &amp; sync</span>
                                    <span class="text-[11px] text-muted-foreground">show / hide</span>
                                </summary>
                                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-0.5">
                                        <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-1">Mobile device</p>
                                        <div class="wr-kv"><span class="wr-kv-k">Platform</span><span class="wr-kv-v" x-text="screening.platform || '—'"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Device ID</span><span class="wr-kv-v font-mono text-[11px]" x-text="screening.device_id || '—'"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">App version</span><span class="wr-kv-v font-mono" x-text="screening.app_version || '—'"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Record version</span><span class="wr-kv-v font-mono" x-text="screening.record_version || '1'"></span></div>
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-1">Sync status</p>
                                        <div class="wr-kv"><span class="wr-kv-k">Status</span><span class="wr-kv-v">
                                            <span class="wr-chip" :class="screening.sync_status === 'SYNCED' ? 'wr-chip-good' : screening.sync_status === 'FAILED' ? 'wr-chip-danger' : 'wr-chip-warn'" x-text="screening.sync_status"></span>
                                        </span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Synced</span><span class="wr-kv-v" x-text="humanDate(screening.synced_at)"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Sync attempts</span><span class="wr-kv-v" x-text="screening.sync_attempt_count ?? 0"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Last sync error</span><span class="wr-kv-v" x-text="screening.last_sync_error || 'None'"></span></div>
                                    </div>
                                    <div class="space-y-0.5 sm:col-span-2">
                                        <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold mb-1">Record trail</p>
                                        <div class="wr-kv"><span class="wr-kv-k">Client UUID</span><span class="wr-kv-v font-mono text-[11px] break-all" x-text="screening.client_uuid"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Idempotency key</span><span class="wr-kv-v font-mono text-[11px] break-all" x-text="screening.idempotency_key || '—'"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Reference data version</span><span class="wr-kv-v font-mono text-[11px]" x-text="screening.reference_data_version"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Server received</span><span class="wr-kv-v" x-text="humanDate(screening.server_received_at)"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Created</span><span class="wr-kv-v" x-text="humanDate(screening.created_at)"></span></div>
                                        <div class="wr-kv"><span class="wr-kv-k">Last updated</span><span class="wr-kv-v" x-text="humanDate(screening.updated_at)"></span></div>
                                        <template x-if="screening.deleted_at">
                                            <div class="wr-kv"><span class="wr-kv-k">Marked deleted</span><span class="wr-kv-v text-destructive" x-text="humanDate(screening.deleted_at)"></span></div>
                                        </template>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ─────── CLINICAL · vitals · symptoms · exposures · differential · samples ─────── --}}
            <div x-show="tab === 'clinical'" x-cloak class="space-y-4 wr-fade">
                <div class="wr-subtabs" role="tablist">
                    <button class="wr-subtab" :data-state="subClin==='vitals' ? 'active' : ''" @click="subClin='vitals'">Vitals</button>
                    <button class="wr-subtab" :data-state="subClin==='symptoms' ? 'active' : ''" @click="subClin='symptoms'">Symptoms <span class="ml-1 text-[10px] tabular-nums" x-text="symptomsPresent.length + ' / ' + symptoms.length"></span></button>
                    <button class="wr-subtab" :data-state="subClin==='exposures' ? 'active' : ''" @click="subClin='exposures'">Exposures <span class="ml-1 text-[10px] tabular-nums" x-text="exposuresYes.length"></span></button>
                    <button class="wr-subtab" :data-state="subClin==='differential' ? 'active' : ''" @click="subClin='differential'">Differential</button>
                    <button class="wr-subtab" :data-state="subClin==='samples' ? 'active' : ''" @click="subClin='samples'">Samples</button>
                </div>

                {{-- Vitals --}}
                <div x-show="subClin === 'vitals'" class="rounded-lg border bg-card p-5 space-y-4">
                    <template x-if="!screening">
                        <p class="description">No screening record is linked to this case, so no vitals were captured.</p>
                    </template>
                    <template x-if="screening">
                        <div class="space-y-4">
                            {{-- Triage / appearance / emergency signs ALWAYS shown --}}
                            <div class="grid gap-2 sm:grid-cols-3">
                                <div class="rounded-md border bg-muted/20 p-3">
                                    <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">Triage category</p>
                                    <p class="text-sm font-semibold mt-1" x-text="humanTriage(screening.triage_category)"></p>
                                </div>
                                <div class="rounded-md border bg-muted/20 p-3">
                                    <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">General appearance</p>
                                    <p class="text-sm font-semibold mt-1" x-text="humanAppearance(screening.general_appearance)"></p>
                                </div>
                                <div class="rounded-md border p-3"
                                     :class="Number(screening.emergency_signs_present) === 1 ? 'border-destructive/40 bg-destructive/5' : 'bg-muted/20'">
                                    <p class="text-[11px] uppercase tracking-[0.12em] font-semibold"
                                       :class="Number(screening.emergency_signs_present) === 1 ? 'text-destructive' : 'text-muted-foreground'">Emergency signs</p>
                                    <p class="text-sm font-semibold mt-1" :class="Number(screening.emergency_signs_present) === 1 ? 'text-destructive' : ''" x-text="Number(screening.emergency_signs_present) === 1 ? 'Present — urgent review' : 'None observed'"></p>
                                </div>
                            </div>

                            {{-- Numeric vitals gauges --}}
                            <template x-if="vitalGauges.length > 0">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <template x-for="g in vitalGauges" :key="g.label">
                                        <div class="rounded-md border p-3 space-y-1.5"
                                             :class="g.tone === 'crit' ? 'border-destructive/40' : g.tone === 'warn' ? 'border-amber-500/40' : ''">
                                            <div class="flex items-center justify-between">
                                                <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold" x-text="g.label"></p>
                                                <span class="text-sm font-semibold tabular-nums"
                                                      :class="g.tone === 'crit' ? 'text-destructive' : g.tone === 'warn' ? 'text-amber-600' : ''"
                                                      x-text="g.display"></span>
                                            </div>
                                            <div class="wr-gauge">
                                                <template x-if="g.bandStart !== null && g.bandEnd !== null">
                                                    <div class="wr-gauge-band" :style="'left:' + g.bandStart + '%; width:' + (g.bandEnd - g.bandStart) + '%'"></div>
                                                </template>
                                                <template x-if="g.value !== null">
                                                    <div class="wr-gauge-fill"
                                                         :class="g.tone === 'crit' ? 'is-crit' : g.tone === 'warn' ? 'is-warn' : ''"
                                                         :style="'width: ' + g.pct + '%'"></div>
                                                </template>
                                            </div>
                                            <p class="help-text" x-text="g.hint"></p>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="vitalGauges.length === 0">
                                <p class="description">The officer didn't record numeric vitals (temperature / pulse / breathing rate / blood pressure / oxygen saturation). This can mean the screening was completed without a medical exam.</p>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Symptoms --}}
                <div x-show="subClin === 'symptoms'" x-cloak class="rounded-lg border bg-card p-5">
                    <template x-if="symptoms.length === 0">
                        <p class="description">The officer didn't record any symptoms. This can mean the case was raised on exposure alone, or the section was skipped.</p>
                    </template>
                    <template x-if="symptoms.length > 0">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">Present</p>
                                    <span class="wr-chip wr-chip-danger" x-text="symptomsPresent.length"></span>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="s in symptomsPresent" :key="s.id">
                                        <span class="wr-chip wr-chip-danger" x-text="humanSymptom(s.symptom_code)"></span>
                                    </template>
                                    <template x-if="symptomsPresent.length === 0"><span class="help-text">None recorded</span></template>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-[11px] uppercase tracking-[0.12em] text-muted-foreground font-semibold">Explicitly absent</p>
                                    <span class="wr-chip wr-chip-good" x-text="symptomsAbsent.length"></span>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="s in symptomsAbsent" :key="s.id">
                                        <span class="wr-chip wr-chip-good" x-text="humanSymptom(s.symptom_code)"></span>
                                    </template>
                                    <template x-if="symptomsAbsent.length === 0"><span class="help-text">None recorded as explicitly absent</span></template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Exposures --}}
                <div x-show="subClin === 'exposures'" x-cloak class="rounded-lg border bg-card p-5">
                    <template x-if="exposures.length === 0">
                        <p class="description">No exposures recorded. If this case was raised on exposure, check the officer completed the exposure section on the mobile app.</p>
                    </template>
                    <template x-if="exposures.length > 0">
                        <div class="space-y-2">
                            <template x-for="e in exposures" :key="e.id">
                                <div class="flex items-start gap-3 rounded-md border p-3">
                                    <span class="wr-chip shrink-0"
                                          :class="e.response === 'YES' ? 'wr-chip-danger' : (e.response === 'NO' ? 'wr-chip-good' : 'wr-chip-muted')"
                                          x-text="e.response"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium" x-text="humanExposure(e.exposure_code)"></p>
                                        <p class="help-text" x-text="e.details || ''"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Differential --}}
                <div x-show="subClin === 'differential'" x-cloak class="rounded-lg border bg-card p-5">
                    <template x-if="suspectedDiseases.length === 0">
                        <p class="description">The engine hasn't scored any diseases yet. Final classification depends on clinical review.</p>
                    </template>
                    <template x-if="suspectedDiseases.length > 0">
                        <div class="space-y-3">
                            <template x-for="(d, idx) in suspectedDiseases" :key="d.id">
                                <div class="rounded-md border p-3" :class="idx === 0 ? 'border-primary' : ''">
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <div>
                                            <p class="text-sm font-semibold" x-text="d.disease_name"></p>
                                            <p class="help-text" x-text="'Rank ' + d.rank_order + ' — ' + (Number(d.confidence)||0).toFixed(1) + '% confidence'"></p>
                                        </div>
                                        <div class="flex gap-1.5 flex-wrap">
                                            <template x-if="d.severity"><span class="wr-chip wr-chip-muted" x-text="'Severity ' + d.severity"></span></template>
                                            <template x-if="d.cfr_pct"><span class="wr-chip wr-chip-warn" x-text="'Deaths ~' + d.cfr_pct + '%'"></span></template>
                                        </div>
                                    </div>
                                    <p class="text-sm mt-2" x-text="d.reasoning || ''" x-show="d.reasoning"></p>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Samples --}}
                <div x-show="subClin === 'samples'" x-cloak class="rounded-lg border bg-card p-5">
                    <template x-if="samples.length === 0">
                        <p class="description">No samples collected yet. If a sample is needed for this case, add a task under the Workflow tab.</p>
                    </template>
                    <template x-if="samples.length > 0">
                        <ul class="divide-y">
                            <template x-for="s in samples" :key="s.id">
                                <li class="py-3 flex items-start gap-3">
                                    <span class="wr-chip shrink-0" :class="Number(s.sample_collected) === 1 ? 'wr-chip-good' : 'wr-chip-warn'" x-text="Number(s.sample_collected) === 1 ? 'Collected' : 'Pending'"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium" x-text="s.sample_type || 'Sample'"></p>
                                        <p class="help-text font-mono" x-text="s.sample_identifier || ''" x-show="s.sample_identifier"></p>
                                        <p class="help-text">
                                            <template x-if="s.lab_destination"><span>→ <span x-text="s.lab_destination"></span></span></template>
                                            <template x-if="s.collected_at"><span class="ml-2" x-text="'collected ' + humanAgo(s.collected_at)"></span></template>
                                        </p>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>
            </div>

            {{-- ─────── WORKFLOW · story · tasks · notes · notifications ─────── --}}
            <div x-show="tab === 'workflow'" x-cloak class="space-y-4 wr-fade">
                <div class="wr-subtabs" role="tablist">
                    <button class="wr-subtab" :data-state="subFlow==='story' ? 'active' : ''" @click="subFlow='story'">Story <span class="ml-1 text-[10px] tabular-nums" x-text="timeline.length"></span></button>
                    <button class="wr-subtab" :data-state="subFlow==='tasks' ? 'active' : ''" @click="subFlow='tasks'">Tasks <span class="ml-1 text-[10px] tabular-nums" x-text="followups.length"></span></button>
                    <button class="wr-subtab" :data-state="subFlow==='notes' ? 'active' : ''" @click="subFlow='notes'">Notes <span class="ml-1 text-[10px] tabular-nums" x-text="comments.length"></span></button>
                    <button class="wr-subtab" :data-state="subFlow==='notifications' ? 'active' : ''" @click="subFlow='notifications'">Emails <span class="ml-1 text-[10px] tabular-nums" x-text="notifications.length"></span></button>
                    <button class="wr-subtab" :data-state="subFlow==='officerActions' ? 'active' : ''" @click="subFlow='officerActions'">Officer actions <span class="ml-1 text-[10px] tabular-nums" x-text="officerActions.length"></span></button>
                </div>

                {{-- STORY --}}
                <div x-show="subFlow === 'story'" class="rounded-lg border bg-card p-5">
                    <template x-if="timeline.length === 0">
                        <p class="description">Nothing has happened on this case yet.</p>
                    </template>
                    <ol class="relative space-y-3" x-show="timeline.length > 0">
                        <template x-for="e in timeline" :key="e.id">
                            <li class="flex gap-3">
                                <div class="flex flex-col items-center shrink-0">
                                    <div class="wr-dot" :class="eventRing(e)"><span x-html="eventIcon(e)"></span></div>
                                    <div class="flex-1 w-px bg-border mt-1"></div>
                                </div>
                                <div class="min-w-0 flex-1 pb-2">
                                    <p class="text-sm font-medium leading-snug" x-text="eventHeadline(e)"></p>
                                    <template x-if="eventDetail(e)"><p class="description mt-0.5" x-text="eventDetail(e)"></p></template>
                                    <p class="help-text mt-0.5" x-text="(e.actor_name ? e.actor_name + ' · ' : '') + humanAgo(e.created_at)"></p>
                                </div>
                            </li>
                        </template>
                    </ol>
                </div>

                {{-- TASKS --}}
                <div x-show="subFlow === 'tasks'" x-cloak class="rounded-lg border bg-card p-5 space-y-3">
                    <template x-if="followups.length === 0">
                        <p class="description">No tasks yet. Add one when something needs to be done before this case can close.</p>
                    </template>
                    <template x-if="blockerCount > 0">
                        <div class="rounded-md border border-destructive/40 bg-destructive/5 p-3">
                            <p class="text-sm font-medium text-destructive">Before this case can close</p>
                            <p class="help-text text-destructive/80">These tasks must be completed or marked not applicable first.</p>
                        </div>
                    </template>
                    <ul class="space-y-2">
                        <template x-for="f in followups" :key="f.id">
                            <li class="flex items-start gap-2.5 rounded-md border p-3"
                                :class="f.blocks_closure == 1 && f.status !== 'COMPLETED' && f.status !== 'NOT_APPLICABLE' ? 'border-destructive/40 bg-destructive/5' : ''">
                                <button type="button" class="mt-0.5 h-5 w-5 rounded-md border flex items-center justify-center shrink-0 hover:bg-accent"
                                        :class="f.status === 'COMPLETED' ? 'bg-primary text-primary-foreground border-primary' : 'bg-background'"
                                        :disabled="f.status === 'COMPLETED' || f.status === 'NOT_APPLICABLE'"
                                        @click="completeTask(f)">
                                    <template x-if="f.status === 'COMPLETED'"><svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                                </button>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium leading-snug" :class="f.status === 'COMPLETED' ? 'line-through text-muted-foreground' : ''" x-text="f.action_label"></p>
                                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                        <template x-if="f.blocks_closure == 1 && f.status !== 'COMPLETED' && f.status !== 'NOT_APPLICABLE'"><span class="wr-chip wr-chip-danger">Must finish before close</span></template>
                                        <template x-if="f.assigned_to_role"><span class="help-text">Assigned to <span x-text="humanRole(f.assigned_to_role)"></span></span></template>
                                        <template x-if="f.status === 'NOT_APPLICABLE'"><span class="wr-chip wr-chip-muted">Not applicable</span></template>
                                    </div>
                                </div>
                                <template x-if="f.status === 'PENDING' || f.status === 'IN_PROGRESS'"><button type="button" class="btn btn-ghost btn-xs shrink-0" @click="markNotApplicable(f)">N/A</button></template>
                            </li>
                        </template>
                    </ul>
                    <button type="button" class="btn btn-outline btn-xs w-full" @click="openAddTask()">
                        <svg class="mr-1.5 h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add a task
                    </button>
                </div>

                {{-- NOTES --}}
                <div x-show="subFlow === 'notes'" x-cloak class="rounded-lg border bg-card p-5 space-y-4">
                    <template x-if="comments.length === 0">
                        <p class="description">No notes yet. Start the thread below.</p>
                    </template>
                    <ul class="space-y-3">
                        <template x-for="c in comments" :key="c.id">
                            <li class="flex gap-2.5">
                                <div class="h-7 w-7 rounded-full bg-muted text-muted-foreground text-[10px] font-semibold flex items-center justify-center shrink-0" x-text="initials(c.actor_name || c.author_name || '?')"></div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline gap-2">
                                        <p class="text-sm font-medium" x-text="c.actor_name || c.author_name || 'Someone'"></p>
                                        <p class="help-text" x-text="humanAgo(c.created_at)"></p>
                                    </div>
                                    <p class="text-sm mt-0.5 whitespace-pre-wrap" x-text="c.body || c.content"></p>
                                </div>
                            </li>
                        </template>
                    </ul>
                    <form @submit.prevent="postNote()" class="space-y-2 pt-2 border-t">
                        <label class="label">Add a note</label>
                        <textarea class="textarea" rows="2" x-model="noteDraft" placeholder="What would the team need to know about this case?"></textarea>
                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-default btn-xs" :disabled="!noteDraft.trim() || savingNote">
                                <svg x-show="savingNote" class="mr-1.5 h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                                Post note
                            </button>
                        </div>
                    </form>
                </div>

                {{-- NOTIFICATIONS --}}
                <div x-show="subFlow === 'notifications'" x-cloak class="rounded-lg border bg-card p-5">
                    <template x-if="notifications.length === 0">
                        <p class="description">No emails sent about this case yet.</p>
                    </template>
                    <ul class="divide-y">
                        <template x-for="n in notifications" :key="n.id">
                            <li class="py-2.5 flex items-center gap-3">
                                <span class="wr-chip shrink-0"
                                      :class="n.status === 'SENT' ? 'wr-chip-good' : (n.status === 'FAILED' ? 'wr-chip-danger' : 'wr-chip-muted')"
                                      x-text="n.status"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium truncate" x-text="humanTemplate(n.template_code)"></p>
                                    <p class="help-text font-mono" x-text="n.to_email || '—'"></p>
                                </div>
                                <span class="help-text shrink-0" x-text="humanAgo(n.created_at)"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- OFFICER ACTIONS (from secondary_actions) --}}
                <div x-show="subFlow === 'officerActions'" x-cloak class="rounded-lg border bg-card p-5">
                    <template x-if="officerActions.length === 0">
                        <p class="description">The officer didn't record any actions during screening.</p>
                    </template>
                    <ul class="space-y-2">
                        <template x-for="a in officerActions" :key="a.id">
                            <li class="flex items-start gap-2.5 rounded-md border p-3">
                                <span class="mt-0.5 h-5 w-5 rounded-md border flex items-center justify-center shrink-0"
                                      :class="Number(a.is_done) === 1 ? 'bg-primary text-primary-foreground border-primary' : 'bg-background'">
                                    <template x-if="Number(a.is_done) === 1"><svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></template>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium" x-text="humanOfficerAction(a.action_code)"></p>
                                    <p class="help-text" x-text="a.details || ''"></p>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </section>
    </template>

    {{-- ═══════════════════════════════════════════════════════════
         WIZARDS (all inside x-data scope)
         ═══════════════════════════════════════════════════════════ --}}

    {{-- Acknowledge --}}
    <template x-if="ackOpen">
        <div>
            <div class="dialog-overlay" @click="ackOpen=false"></div>
            <div class="dialog-content" role="dialog" aria-modal="true">
                <div class="dialog-header">
                    <h3 class="dialog-title">Take ownership of this case?</h3>
                    <p class="dialog-description">You're saying you've seen it and will handle the response. The 24-hour response clock starts now and the team will see your name next to this case.</p>
                </div>
                <div class="dialog-footer">
                    <button class="btn btn-ghost btn-xs" @click="ackOpen=false">Not yet</button>
                    <button class="btn btn-default btn-xs" @click="submitAck()" :disabled="ackSaving">Yes, I'll take it</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Close (3-step) --}}
    <template x-if="closeOpen">
        <div>
            <div class="dialog-overlay" @click="closeOpen=false"></div>
            <div class="dialog-content" role="dialog" aria-modal="true">
                <div class="flex gap-1 mb-2">
                    <template x-for="s in [1,2,3]" :key="s">
                        <div class="h-1 flex-1 rounded-full" :class="s <= closeStep ? 'bg-primary' : 'bg-muted'"></div>
                    </template>
                </div>

                <template x-if="closeStep === 1">
                    <div class="space-y-4">
                        <div><h3 class="dialog-title">How was this case resolved?</h3><p class="dialog-description">Pick the option that best describes what happened.</p></div>
                        <div class="space-y-1.5">
                            <template x-for="cat in closeOptions" :key="cat.code">
                                <button type="button" class="wr-pick"
                                        :data-selected="closeForm.close_category === cat.code"
                                        @click="closeForm.close_category = cat.code">
                                    <p class="text-sm font-medium" x-text="cat.label"></p>
                                    <p class="help-text" x-text="cat.hint"></p>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="closeStep === 2">
                    <div class="space-y-4">
                        <div><h3 class="dialog-title" x-text="closeStep2Title"></h3><p class="dialog-description" x-text="closeStep2Hint"></p></div>
                        <template x-if="closeForm.close_category === 'DUPLICATE'">
                            <div class="space-y-1.5">
                                <label class="label">Case number of the original</label>
                                <input class="input font-mono" type="number" min="1" x-model.number="closeForm.merged_into_alert_id" placeholder="e.g. 42">
                            </div>
                        </template>
                        <div class="space-y-1.5">
                            <label class="label"><span x-text="closeForm.close_category === 'OTHER' ? 'Explain what happened' : 'Add a short note (optional)'"></span><template x-if="closeForm.close_category === 'OTHER'"><span class="text-destructive ml-0.5">*</span></template></label>
                            <textarea class="textarea" rows="3" x-model="closeForm.close_note" placeholder="A sentence or two that helps anyone reading this later."></textarea>
                        </div>
                        <p class="help-text text-destructive" x-show="closeErr" x-text="closeErr"></p>
                    </div>
                </template>

                <template x-if="closeStep === 3">
                    <div class="space-y-4">
                        <div><h3 class="dialog-title">Ready to close this case?</h3><p class="dialog-description">It moves out of the open list. You can reopen it later if new information comes in.</p></div>
                        <div class="rounded-md border bg-muted/40 p-3 space-y-1.5">
                            <p class="text-sm"><span class="text-muted-foreground">Because:</span> <span class="font-medium" x-text="selectedCloseLabel"></span></p>
                            <template x-if="closeForm.merged_into_alert_id"><p class="text-sm"><span class="text-muted-foreground">Linked to:</span> <span class="font-mono" x-text="'#' + closeForm.merged_into_alert_id"></span></p></template>
                            <template x-if="closeForm.close_note"><p class="text-sm"><span class="text-muted-foreground">Note:</span> <span x-text="closeForm.close_note"></span></p></template>
                        </div>
                        <template x-if="blockerCount > 0">
                            <div class="alert alert-destructive">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01"/></svg>
                                <div class="alert-description">A few tasks still need to be done first — see the Tasks tab.</div>
                            </div>
                        </template>
                        <p class="help-text text-destructive" x-show="closeErr" x-text="closeErr"></p>
                    </div>
                </template>

                <div class="dialog-footer pt-2">
                    <button class="btn btn-ghost btn-xs" @click="closeOpen=false">Cancel</button>
                    <template x-if="closeStep > 1"><button class="btn btn-outline btn-xs" @click="closeStep--; closeErr=''">Back</button></template>
                    <template x-if="closeStep < 3"><button class="btn btn-default btn-xs" @click="closeNext()" :disabled="!closeCanAdvance">Next</button></template>
                    <template x-if="closeStep === 3"><button class="btn btn-destructive btn-xs" @click="closeSubmit()" :disabled="closeSaving">Close this case</button></template>
                </div>
            </div>
        </div>
    </template>

    {{-- Reopen --}}
    <template x-if="reopenOpen">
        <div>
            <div class="dialog-overlay" @click="reopenOpen=false"></div>
            <div class="dialog-content" role="dialog" aria-modal="true">
                <div class="dialog-header">
                    <h3 class="dialog-title">Reopen this case?</h3>
                    <p class="dialog-description">It moves back to "being handled" and the response clock restarts. A short reason helps the team understand why.</p>
                </div>
                <div class="space-y-1.5">
                    <label class="label">Why are you reopening it?</label>
                    <textarea class="textarea" rows="3" x-model="reopenReason" placeholder="e.g. New lab results came in · Traveller returned · Fresh case data."></textarea>
                    <p class="help-text text-destructive" x-show="reopenErr" x-text="reopenErr"></p>
                </div>
                <div class="dialog-footer pt-2">
                    <button class="btn btn-ghost btn-xs" @click="reopenOpen=false">Not yet</button>
                    <button class="btn btn-default btn-xs" @click="submitReopen()" :disabled="reopenSaving">Yes, reopen it</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Add task --}}
    <template x-if="taskOpen">
        <div>
            <div class="dialog-overlay" @click="taskOpen=false"></div>
            <div class="dialog-content" role="dialog" aria-modal="true">
                <div class="dialog-header">
                    <h3 class="dialog-title">Add a task to this case</h3>
                    <p class="dialog-description">Describe what needs to happen. You can mark it as required before the case can close.</p>
                </div>
                <div class="space-y-3">
                    <div class="space-y-1.5"><label class="label">What needs to happen?</label><input class="input" x-model="task.label" autofocus placeholder="e.g. Wait for PCR results from UVRI"></div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5"><label class="label">Short code (optional)</label><input class="input font-mono uppercase" maxlength="30" x-model="task.code" placeholder="LAB_PCR"></div>
                        <div class="space-y-1.5"><label class="label">Who handles it?</label>
                            <select class="select" x-model="task.role">
                                <option value="">Anyone on the case</option>
                                <option value="SCREENER">Screener</option>
                                <option value="DISTRICT_SUPERVISOR">District supervisor</option>
                                <option value="PHEOC_OFFICER">PHEOC officer</option>
                                <option value="NATIONAL_ADMIN">National administrator</option>
                            </select>
                        </div>
                    </div>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" x-model="task.blocks" class="mt-0.5">
                        <span class="text-sm">This must be finished before the case can be closed.</span>
                    </label>
                    <p class="help-text text-destructive" x-show="taskErr" x-text="taskErr"></p>
                </div>
                <div class="dialog-footer pt-2">
                    <button class="btn btn-ghost btn-xs" @click="taskOpen=false">Cancel</button>
                    <button class="btn btn-default btn-xs" @click="createTask()" :disabled="taskSaving">Add task</button>
                </div>
            </div>
        </div>
    </template>

    {{-- MOBILE sticky action bar --}}
    <template x-if="alert">
        <div class="lg:hidden fixed bottom-0 inset-x-0 z-30 border-t bg-background/95 backdrop-blur px-3 py-2 pb-safe">
            <div class="flex items-center gap-2">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] text-muted-foreground" x-text="primaryCtaHint"></p>
                    <p class="text-sm font-medium truncate" x-text="primaryCtaLabel"></p>
                </div>
                <button type="button" class="btn btn-default btn-xs shrink-0" @click="runAction(primaryCtaKey)">
                    <span x-text="primaryCtaShort"></span>
                </button>
            </div>
        </div>
    </template>

    {{-- Toast --}}
    <div x-show="toast.open" x-cloak x-transition class="fixed bottom-20 lg:bottom-4 right-4 z-40 w-[calc(100vw-2rem)] sm:w-[340px]">
        <div class="toast" :class="toast.tone === 'destructive' ? 'toast-destructive' : ''">
            <div class="grid gap-0.5">
                <div class="toast-title" x-text="toast.title"></div>
                <div class="toast-description" x-text="toast.message" x-show="toast.message"></div>
            </div>
            <button class="toast-action" @click="toast.open=false">OK</button>
        </div>
    </div>

</div>

@push('scripts')
<script>
function warRoom(alertId){
    return {
        alertId, loading: true, loadError: '',
        alert: null, ctx: {}, screening: null,
        symptoms: [], exposures: [], samples: [], officerActions: [], travelCountries: [], suspectedDiseases: [], resolvedDisease: null,
        timeline: [], followups: [], comments: [], notifications: [],

        // navigation
        tab: 'brief',
        subTrav: 'identity', subClin: 'vitals', subFlow: 'story',

        // dialogs
        ackOpen:false, ackSaving:false,
        closeOpen:false, closeStep:1,
        closeForm:{ close_category:'', close_note:'', merged_into_alert_id:null },
        closeErr:'', closeSaving:false,
        reopenOpen:false, reopenReason:'', reopenErr:'', reopenSaving:false,
        taskOpen:false, task:{ label:'', code:'', role:'', blocks:false }, taskErr:'', taskSaving:false,
        noteDraft:'', savingNote:false,
        toast:{ open:false, title:'', message:'', tone:'default', _t:null },
        _refreshTimer: null,

        closeOptions: [
            { code:'RESOLVED',                  label:'The case was resolved',               hint:'Traveller was screened, treated, or cleared.' },
            { code:'FALSE_POSITIVE',            label:'It turned out not to be a case',      hint:'False alarm or mistaken entry.' },
            { code:'DUPLICATE',                 label:'It duplicates another case',          hint:'Same traveller or event already recorded.' },
            { code:'LOST_TO_FOLLOWUP',          label:'We lost contact with the traveller',  hint:'They didn\'t respond or couldn\'t be reached.' },
            { code:'TRANSFERRED_OUT_OF_COUNTRY',label:'The traveller left the country',      hint:'They are no longer in Uganda.' },
            { code:'DECEASED',                  label:'The traveller passed away',           hint:'Should be flagged to the clinical team.' },
            { code:'OTHER',                     label:'Something else',                      hint:'Explain in a few words on the next step.' },
        ],

        async init(){
            if (!localStorage.getItem('pheoc_token')) { location.replace('{{ url('/admin/login') }}?next=' + encodeURIComponent(location.pathname)); return; }
            await this.reload();
            this._refreshTimer = setInterval(() => this.reload(true), 30000);
        },
        _token(){ return localStorage.getItem('pheoc_token') || ''; },
        _uid(){ try { return Number((JSON.parse(localStorage.getItem('pheoc_user')||'{}')).id||0); } catch { return 0; } },
        _user(){ try { return JSON.parse(localStorage.getItem('pheoc_user')||'{}'); } catch { return {}; } },
        async _req(path, opts={}){
            const url = '{{ url('/api') }}' + path + (path.includes('?') ? '&' : '?') + 'user_id=' + this._uid();
            const res = await fetch(url, {
                method: opts.method || 'GET',
                headers: { 'Accept':'application/json','Content-Type':'application/json', 'Authorization':'Bearer '+this._token(), 'X-User-Id': this._uid() },
                body: opts.body != null ? JSON.stringify(Object.assign({user_id:this._uid()}, opts.body)) : undefined,
            });
            const ct = res.headers.get('content-type')||'';
            const body = ct.includes('json') ? await res.json() : {};
            return { status: res.status, body };
        },
        async reload(quiet){
            if (!quiet) this.loadError = '';
            const { status, body } = await this._req('/alerts/' + this.alertId + '/war-room');
            if (!quiet) this.loading = false;
            if (status === 200 && (body.ok !== false || body.success)) {
                const d = body.data || {};
                this.alert = d.alert || null;
                this.ctx = d.context_vars || {};
                this.screening = d.screening || null;
                this.symptoms = d.symptoms || [];
                this.exposures = d.exposures || [];
                this.samples = d.samples || [];
                this.officerActions = d.actions || [];
                this.travelCountries = d.travel_countries || [];
                this.suspectedDiseases = d.suspected_diseases || [];
                this.resolvedDisease = d.resolved_disease || null;
                this.timeline = d.timeline || [];
                this.followups = d.followups || [];
                this.comments = d.comments || [];
                this.notifications = d.notification_activity || [];
            } else if (!quiet) {
                this.loadError = body?.message || body?.error || ('Load failed · ' + status);
            }
        },

        // ── hero helpers ─────────────────────────────────
        get heroRail(){ return ({CRITICAL:'wr-rail-urgent',HIGH:'wr-rail-high',MEDIUM:'wr-rail-normal',LOW:'wr-rail-low'})[this.alert?.risk_level] || ''; },
        get urgencyBadge(){ return ({CRITICAL:'badge-destructive',HIGH:'badge-default',MEDIUM:'badge-secondary',LOW:'badge-outline'})[this.alert?.risk_level] || 'badge-outline'; },
        get isOverdue(){
            if (!this.alert || this.alert.status === 'CLOSED' || this.alert.acknowledged_at) return false;
            const ts = this._ts(this.alert.server_received_at);
            return !!ts && (Date.now() - ts) > 24 * 3600 * 1000;
        },
        get suspectedCaseTitle(){
            if (this.resolvedDisease?.disease_name) return 'Suspected ' + this.resolvedDisease.disease_name;
            const synd = (this.alert?.syndrome || this.ctx?.syndrome_classification || '').toString().toLowerCase();
            if (synd === 'vhf') return 'Suspected viral haemorrhagic fever';
            if (synd === 'sari') return 'Suspected severe respiratory illness';
            if (synd && synd !== 'none') return 'Suspected ' + synd.replace(/_/g,' ') + ' syndrome';
            return 'Suspected case — awaiting clinical classification';
        },
        get locationSentence(){
            const bits = [];
            if (this.alert?.poe_code) bits.push(this.alert.poe_code);
            if (this.alert?.district_code) bits.push(this.alert.district_code);
            if (this.alert?.province_code || this.alert?.pheoc_code) bits.push(this.alert.province_code || this.alert.pheoc_code);
            return bits.join(' · ') || '—';
        },
        get briefing(){
            if (!this.alert) return '';
            const u = this._user();
            const name = (u.full_name || '').split(' ')[0] || 'there';
            const a = this.alert;
            const s = this.screening || {};
            const d = this.resolvedDisease;
            const bits = [];
            bits.push(`Hey ${this._escape(name)}.`);

            const age = s.traveler_age_years ? s.traveler_age_years + '-year-old' : '';
            const gender = ({MALE:'male',FEMALE:'female',OTHER:'traveller'})[s.traveler_gender] || 'traveller';
            const nat = s.traveler_nationality_country_code ? ` from ${this._escape(s.traveler_nationality_country_code)}` : '';
            const at = a.poe_code || a.district_code || 'the border';
            const mode = ({AIR:'by air', LAND:'by land', SEA:'by sea'})[s.conveyance_type] || '';

            let diseaseLabel;
            if (d?.disease_name) diseaseLabel = `<strong>${this._escape(d.disease_name)}</strong>`;
            else if (s.syndrome_classification) diseaseLabel = `<strong>${this._escape(this.humanSyndrome(s.syndrome_classification))}</strong>`;
            else diseaseLabel = '<strong>a case awaiting clinical classification</strong>';
            const disease = diseaseLabel;

            if (age) bits.push(`This looks like ${disease} in a ${age} ${gender}${nat} who arrived at <strong>${this._escape(at)}</strong> ${mode}.`);
            else bits.push(`This looks like ${disease} at <strong>${this._escape(at)}</strong>.`);

            const present = this.symptoms.filter(x => Number(x.is_present) === 1);
            if (present.length > 0) bits.push(`The officer recorded <strong>${present.length}</strong> symptom${present.length===1?'':'s'}.`);
            const yesExp = this.exposures.filter(e => e.response === 'YES');
            if (yesExp.length > 0) bits.push(`<strong>${yesExp.length}</strong> high-risk exposure${yesExp.length===1?'':'s'} were reported.`);
            if (d?.cfr_pct) bits.push(`About <strong>${d.cfr_pct}%</strong> of confirmed cases are fatal.`);
            if (a.ihr_tier && a.ihr_tier !== 'none') bits.push(`This case must be reported to WHO within 24 hours.`);

            if (this.isOverdue) {
                const hrs = Math.floor((Date.now() - this._ts(a.server_received_at)) / 3600000);
                bits.push(`<strong>Response is ${hrs-24} hour${hrs-24===1?'':'s'} overdue.</strong> Somebody needs to take it now.`);
            } else if (a.status === 'OPEN') {
                bits.push(`Nobody has taken it yet — want to claim it, hand it off, or close it out?`);
            } else if (a.status === 'ACKNOWLEDGED' && this.blockerCount > 0) {
                bits.push(`<strong>${this._escape(a.acknowledged_by_name || 'Someone')}</strong> is on it — ${this.blockerCount} task${this.blockerCount===1?'':'s'} still need to be done before close.`);
            } else if (a.status === 'ACKNOWLEDGED') {
                bits.push(`<strong>${this._escape(a.acknowledged_by_name || 'Someone')}</strong> is handling it. When every task is done you can close it.`);
            } else if (a.status === 'CLOSED') {
                bits.push(`Resolved ${this.humanAgo(a.closed_at)} — ${this._escape((this.closeCategoryLabel(a.close_category) || 'closed').toLowerCase())}. You can still add notes or reopen it.`);
            }
            return bits.join(' ');
        },

        get hasDelay(){ return this.alert && this.alert.status !== 'CLOSED' && (this.isOverdue || this.blockerCount > 0); },
        get statusLines(){
            if (!this.alert) return [];
            const lines = []; const a = this.alert;
            if (a.status === 'CLOSED') {
                lines.push(`Case was closed ${this.humanAgo(a.closed_at)}${this.closeCategoryLabel(a.close_category) ? ' — ' + this.closeCategoryLabel(a.close_category).toLowerCase() : ''}.`);
                if (a.close_note) lines.push(`Note on file: "<em>${this._escape(a.close_note)}</em>"`);
                if (a.reopen_count > 0) lines.push(`This case has been reopened <strong>${a.reopen_count}</strong> time${a.reopen_count===1?'':'s'} before.`);
                return lines;
            }
            if (this.isOverdue) {
                const hrs = Math.floor((Date.now() - this._ts(a.server_received_at)) / 3600000);
                lines.push(`<strong>Acknowledgement is ${hrs-24} hour${hrs-24===1?'':'s'} overdue</strong> — response should have started within the first 24 h.`);
            }
            if (a.status === 'OPEN' && !this.isOverdue) {
                const hrs = Math.floor((Date.now() - this._ts(a.server_received_at)) / 3600000);
                if (hrs < 1) lines.push(`Case came in <strong>just now</strong>. Somebody needs to take it.`);
                else lines.push(`On the board for <strong>${hrs} hour${hrs===1?'':'s'}</strong> with no owner.`);
            }
            if (a.status === 'ACKNOWLEDGED' && a.acknowledged_by_name) {
                const ackHrs = Math.floor((Date.now() - this._ts(a.acknowledged_at)) / 3600000);
                lines.push(`<strong>${this._escape(a.acknowledged_by_name)}</strong> took this case ${ackHrs < 1 ? 'just now' : ackHrs + ' hour' + (ackHrs===1?'':'s') + ' ago'}.`);
            }
            if (this.blockerCount > 0) {
                const labels = this.blockers.slice(0,3).map(b => `"<em>${this._escape(b.action_label)}</em>"`).join(', ');
                lines.push(`Waiting on <strong>${this.blockerCount} task${this.blockerCount===1?'':'s'}</strong> before close: ${labels}.`);
            }
            if (this.timeline.length > 0 && a.status === 'ACKNOWLEDGED' && this.blockerCount === 0 && !this.isOverdue) {
                const last = this.timeline[0];
                lines.push(`Last update: <strong>${this._escape(this.eventHeadline(last).toLowerCase())}</strong> ${this.humanAgo(last.created_at)}.`);
            }
            if (a.reopen_count > 0) lines.push(`Reopened <strong>${a.reopen_count}</strong> time${a.reopen_count===1?'':'s'} — check the Story tab.`);
            if (lines.length === 0) lines.push(`Case is being worked on — no blockers.`);
            return lines;
        },

        // ── navigation ───────────────────────────────────
        get primaryTabs(){
            return [
                { key:'brief',    label:'Brief',     count: null },
                { key:'traveller',label:'Traveller', count: null },
                { key:'clinical', label:'Clinical',  count: this.symptoms.length + this.exposures.length },
                { key:'workflow', label:'Workflow',  count: this.timeline.length + this.followups.length + this.comments.length },
            ];
        },

        // ── Brief: KPIs + progress + clock + heatmap ───────
        get briefKpis(){
            if (!this.alert) return [];
            const a = this.alert;
            const hoursOpen = this._ts(a.server_received_at) ? Math.floor((Date.now() - this._ts(a.server_received_at)) / 3600000) : null;
            const ackHours = a.acknowledged_at && this._ts(a.server_received_at) ? Math.round((this._ts(a.acknowledged_at) - this._ts(a.server_received_at))/360000)/10 : null;
            const out = [];
            out.push({ label:'Time open',       value: hoursOpen === null ? '—' : (hoursOpen < 24 ? hoursOpen + 'h' : Math.floor(hoursOpen/24)+'d'), hint: hoursOpen === null ? '' : (a.status === 'CLOSED' ? 'Until resolved' : 'Elapsed') });
            out.push({ label:'Time to ack',     value: ackHours === null ? '—' : ackHours + 'h', hint: ackHours === null ? 'Not yet acknowledged' : (ackHours > 24 ? 'Past the window' : 'Within window'), tone: ackHours !== null && ackHours > 24 ? 'text-destructive' : '' });
            out.push({ label:'Tasks pending',   value: this.followups.filter(f => f.status === 'PENDING' || f.status === 'IN_PROGRESS').length, hint:'On the case' });
            out.push({ label:'Blockers',        value: this.blockerCount, hint:'Must finish before close', tone: this.blockerCount > 0 ? 'text-destructive' : '' });
            out.push({ label:'Emails sent',     value: this.notifications.filter(n => n.status === 'SENT').length, hint:'About this case' });
            return out;
        },
        get progressStages(){
            if (!this.alert) return [];
            const a = this.alert;
            return [
                { key:'received',   label:'Case received',         state: 'done', meta: this.humanDate(a.server_received_at) },
                { key:'ack',        label:'Acknowledged',
                    state: a.acknowledged_at ? 'done' : (a.status === 'OPEN' ? 'active' : 'pending'),
                    meta: a.acknowledged_by_name ? `${a.acknowledged_by_name} · ${this.humanAgo(a.acknowledged_at)}` : (a.status === 'OPEN' ? 'Waiting for someone to take it' : '') },
                { key:'tasks',      label:'Tasks in flight',
                    state: this.followups.length === 0 ? 'pending' : (this.blockerCount === 0 && a.status !== 'CLOSED' ? 'done' : 'active'),
                    meta: `${this.followups.filter(f => f.status === 'COMPLETED').length} done · ${this.blockerCount} blocking` },
                { key:'resolved',   label:'Resolved',
                    state: a.status === 'CLOSED' ? 'done' : 'pending',
                    meta: a.status === 'CLOSED' ? `${this.closeCategoryLabel(a.close_category) || ''} · ${this.humanAgo(a.closed_at)}` : 'Not yet closed' },
            ];
        },
        get responseClock(){
            if (!this.alert) return { deg: 0, display:'—', tone:'', title:'', subtitle:'' };
            const a = this.alert;
            const start = this._ts(a.server_received_at);
            const end   = a.acknowledged_at ? this._ts(a.acknowledged_at) : Date.now();
            const hrs   = start ? Math.min(24, Math.max(0, (end - start) / 3600000)) : 0;
            const fullHrs = start ? ((end - start) / 3600000) : 0;
            const deg = Math.round((hrs / 24) * 360);
            let tone = '', title = '', subtitle = '';
            if (a.acknowledged_at) { title = 'Within response window'; subtitle = `Acknowledged after ${fullHrs.toFixed(1)}h`; tone = fullHrs > 24 ? 'is-warn' : ''; }
            else if (fullHrs > 24) { title = 'Response overdue'; subtitle = `${Math.floor(fullHrs-24)}h past the 24h mark`; tone = 'is-crit'; }
            else if (fullHrs > 12) { title = 'Running late'; subtitle = `${Math.floor(fullHrs)}h elapsed, ${Math.floor(24-fullHrs)}h left`; tone = 'is-warn'; }
            else { title = 'Within response window'; subtitle = `${Math.floor(24-fullHrs)}h remaining to acknowledge`; }
            return { deg, display: fullHrs < 1 ? Math.round(fullHrs*60)+'m' : fullHrs.toFixed(1)+'h', tone, title, subtitle };
        },
        get heatmapHeaders(){
            const out = []; const today = new Date();
            for (let i = 13; i >= 0; i--) { const d = new Date(today); d.setDate(today.getDate()-i); out.push(d.toLocaleDateString(undefined,{day:'2-digit'})); }
            return out;
        },
        get heatmap(){
            const slots = ['00-06','06-12','12-18','18-24'];
            const grid = slots.map(() => new Array(14).fill(0));
            const today = new Date(); today.setHours(0,0,0,0);
            (this.timeline || []).forEach(e => {
                const ts = this._ts(e.created_at); if (!ts) return;
                const d = new Date(ts); d.setHours(0,0,0,0);
                const dayIdx = 13 - Math.floor((today - d) / 86400000);
                if (dayIdx < 0 || dayIdx > 13) return;
                const hour = new Date(ts).getHours();
                const slotIdx = Math.min(3, Math.floor(hour / 6));
                grid[slotIdx][dayIdx]++;
            });
            return slots.map((label, i) => ({ label, cells: grid[i] }));
        },
        heatCellClass(n){
            if (!n) return 'bg-muted';
            if (n === 1) return 'bg-primary/20';
            if (n === 2) return 'bg-primary/50';
            if (n === 3) return 'bg-primary/80';
            return 'bg-primary';
        },

        // ── vitals gauges ────────────────────────────────
        get vitalGauges(){
            if (!this.screening) return [];
            const s = this.screening;
            const g = [];
            // Temperature (°C)
            if (s.temperature_value != null) {
                let v = Number(s.temperature_value);
                if (s.temperature_unit === 'F') v = (v - 32) * 5 / 9;
                const tone = v >= 39 || v < 35 ? 'crit' : v >= 38 ? 'warn' : '';
                g.push({ label:'Temperature', display: v.toFixed(1) + '°C', value: v, tone,
                    pct: Math.min(100, Math.max(0, (v - 34) / (41 - 34) * 100)),
                    bandStart: ((36.1 - 34) / 7) * 100, bandEnd: ((37.2 - 34) / 7) * 100,
                    hint: tone === 'crit' ? 'Outside normal range' : tone === 'warn' ? 'Fever' : 'Normal body temperature zone shaded' });
            }
            if (s.pulse_rate) {
                const v = Number(s.pulse_rate); const tone = v > 120 || v < 50 ? 'crit' : v > 100 || v < 60 ? 'warn' : '';
                g.push({ label:'Pulse rate', display: v + ' bpm', value: v, tone, pct: Math.min(100, Math.max(0, (v - 40) / 120 * 100)),
                    bandStart: ((60-40)/120)*100, bandEnd: ((100-40)/120)*100, hint: 'Normal adult resting 60–100' });
            }
            if (s.respiratory_rate) {
                const v = Number(s.respiratory_rate); const tone = v > 24 || v < 10 ? 'crit' : v > 20 ? 'warn' : '';
                g.push({ label:'Breathing rate', display: v + '/min', value: v, tone, pct: Math.min(100, Math.max(0, (v - 5) / 35 * 100)),
                    bandStart: ((12-5)/35)*100, bandEnd: ((20-5)/35)*100, hint: 'Normal adult 12–20' });
            }
            if (s.bp_systolic || s.bp_diastolic) {
                const sys = s.bp_systolic ? Number(s.bp_systolic) : null;
                const dia = s.bp_diastolic ? Number(s.bp_diastolic) : null;
                const tone = (sys && sys > 180) || (dia && dia > 120) ? 'crit' : (sys && sys > 140) || (dia && dia > 90) ? 'warn' : '';
                g.push({ label:'Blood pressure', display: (sys||'—') + '/' + (dia||'—') + ' mmHg', value: sys || dia, tone,
                    pct: sys ? Math.min(100, Math.max(0, (sys - 80) / 120 * 100)) : 0,
                    bandStart: ((90-80)/120)*100, bandEnd: ((130-80)/120)*100, hint: 'Normal systolic 90–130' });
            }
            if (s.oxygen_saturation != null) {
                const v = Number(s.oxygen_saturation); const tone = v < 90 ? 'crit' : v < 94 ? 'warn' : '';
                g.push({ label:'Oxygen saturation', display: v + '%', value: v, tone, pct: Math.min(100, v),
                    bandStart: 94, bandEnd: 100, hint: 'Healthy range 94–100%' });
            }
            return g;
        },

        // ── symptom / exposure derivations ───────────────
        get symptomsPresent(){ return this.symptoms.filter(s => Number(s.is_present) === 1); },
        get symptomsAbsent(){ return this.symptoms.filter(s => Number(s.explicit_absent) === 1); },
        get exposuresYes(){ return this.exposures.filter(e => e.response === 'YES'); },

        // ── blockers / actions ───────────────────────────
        get blockers(){ return (this.followups || []).filter(f => Number(f.blocks_closure) === 1 && f.status !== 'COMPLETED' && f.status !== 'NOT_APPLICABLE'); },
        get blockerCount(){ return this.blockers.length; },
        get primaryActions(){
            if (!this.alert) return [];
            const s = this.alert.status;
            const iconCheck = '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
            const iconClose = '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
            const iconReopen = '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';
            const iconPlus = '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>';
            if (s === 'OPEN')         return [ { key:'ack',   label:'Take this case',   primary:true, icon: iconCheck }, { key:'task',  label:'Add a task', icon: iconPlus  }, { key:'close', label:'Close it out', icon: iconClose } ];
            if (s === 'ACKNOWLEDGED') return [ { key:'task',  label:'Add a task', primary:true, icon: iconPlus }, { key:'close', label:'Close this case', icon: iconClose } ];
            if (s === 'CLOSED')       return [ { key:'reopen', label:'Reopen case', primary:true, icon: iconReopen }, { key:'task',  label:'Add a follow-up', icon: iconPlus } ];
            return [];
        },
        get primaryCtaKey(){ return (this.primaryActions[0] || {}).key || 'task'; },
        get primaryCtaLabel(){ return ({OPEN:'Take this case', ACKNOWLEDGED:'Add a task or close', CLOSED:'Reopen this case'})[this.alert?.status] || 'Update case'; },
        get primaryCtaShort(){ return ({OPEN:'Take it', ACKNOWLEDGED:'Close', CLOSED:'Reopen'})[this.alert?.status] || 'Act'; },
        get primaryCtaHint(){ return ({OPEN:'Next step', ACKNOWLEDGED:'Keep it moving', CLOSED:'Already resolved'})[this.alert?.status] || ''; },
        runAction(key){
            this.closeErr = ''; this.reopenErr = ''; this.taskErr = '';
            if (key === 'ack')    { this.ackOpen = true; return; }
            if (key === 'close')  { this.closeOpen = true; this.closeStep = 1; this.closeForm = { close_category:'', close_note:'', merged_into_alert_id:null }; return; }
            if (key === 'reopen') { this.reopenOpen = true; this.reopenReason = ''; return; }
            if (key === 'task')   { this.openAddTask(); return; }
        },
        openAddTask(){ this.taskOpen = true; this.task = { label:'', code:'', role:'', blocks:false }; this.taskErr = ''; },

        // ── Actions (live endpoints) ─────────────────────
        async submitAck(){
            this.ackSaving = true;
            const { status, body } = await this._req('/alerts/' + this.alertId + '/acknowledge', { method:'PATCH', body:{} });
            this.ackSaving = false; this.ackOpen = false;
            if (status === 200 && (body.ok !== false || body.success)) { this.showToast('You have this case', 'The response clock has started.'); await this.reload(); }
            else this.showToast('Couldn\'t take that action', body?.message || body?.error || 'Try again.', 'destructive');
        },
        get closeCanAdvance(){
            if (this.closeStep === 1) return !!this.closeForm.close_category;
            if (this.closeStep === 2) {
                if (this.closeForm.close_category === 'DUPLICATE') return !!this.closeForm.merged_into_alert_id && Number(this.closeForm.merged_into_alert_id) !== Number(this.alertId);
                if (this.closeForm.close_category === 'OTHER')     return (this.closeForm.close_note||'').trim().length >= 10;
                return true;
            }
            return true;
        },
        get closeStep2Title(){ return ({DUPLICATE:'Which case is this a duplicate of?', OTHER:'Explain what happened', RESOLVED:'Add any details (optional)'})[this.closeForm.close_category] || 'Add any details (optional)'; },
        get closeStep2Hint(){ return ({DUPLICATE:'Enter the case number of the original.', OTHER:'A short sentence (at least 10 characters).'})[this.closeForm.close_category] || 'A short sentence is enough. It stays with the case.'; },
        get selectedCloseLabel(){ const opt = this.closeOptions.find(o => o.code === this.closeForm.close_category); return opt ? opt.label : ''; },
        closeNext(){
            this.closeErr = '';
            if (!this.closeCanAdvance) {
                if (this.closeStep === 2 && this.closeForm.close_category === 'DUPLICATE') this.closeErr = 'Enter the case number — it must be different from this one.';
                else if (this.closeStep === 2 && this.closeForm.close_category === 'OTHER') this.closeErr = 'Please give a short explanation (at least 10 characters).';
                else this.closeErr = 'Please pick an option to continue.';
                return;
            }
            this.closeStep++;
        },
        async closeSubmit(){
            this.closeErr=''; this.closeSaving=true;
            const f = this.closeForm;
            const { status, body } = await this._req('/alerts/' + this.alertId + '/close', { method:'PATCH', body: { close_category: f.close_category, close_note: (f.close_note||'').trim() || null, merged_into_alert_id: f.close_category === 'DUPLICATE' ? Number(f.merged_into_alert_id) : null } });
            this.closeSaving = false;
            if ((status === 200 || status === 201) && (body.ok !== false || body.success)) { this.closeOpen = false; this.showToast('Case closed', 'It won\'t appear in the open list anymore.'); await this.reload(); return; }
            if (status === 409 && (body.meta?.code === 'BLOCKS_CLOSURE' || body?.error?.code === 'BLOCKS_CLOSURE')) { this.closeErr = 'A few tasks still need to be done first. Check the Tasks tab.'; return; }
            this.closeErr = body?.message || body?.error || 'Couldn\'t close this case. Try again.';
        },
        async submitReopen(){
            if ((this.reopenReason||'').trim().length < 5) { this.reopenErr = 'Please give a short reason (at least a few words).'; return; }
            this.reopenErr=''; this.reopenSaving=true;
            const { status, body } = await this._req('/alerts/' + this.alertId + '/reopen', { method:'POST', body:{ reason: this.reopenReason.trim() } });
            this.reopenSaving=false;
            if (status === 200 && (body.ok !== false || body.success)) { this.reopenOpen = false; this.showToast('Case reopened', 'It\'s back on the open list.'); await this.reload(); }
            else this.reopenErr = body?.message || body?.error || 'Couldn\'t reopen this case.';
        },
        async createTask(){
            this.taskErr = '';
            if (!(this.task.label||'').trim()) { this.taskErr = 'Please describe what needs to happen.'; return; }
            this.taskSaving = true;
            const payload = { action_code: (this.task.code || 'TODO').toUpperCase().replace(/\s+/g,'_').slice(0,60), action_label: this.task.label.trim(), assigned_to_role: this.task.role || null, blocks_closure: !!this.task.blocks };
            const { status, body } = await this._req('/alerts/' + this.alertId + '/followups', { method:'POST', body: payload });
            this.taskSaving=false;
            if ((status === 200 || status === 201) && (body.ok !== false || body.success)) { this.taskOpen = false; this.tab='workflow'; this.subFlow='tasks'; this.showToast('Task added', this.task.blocks ? 'Must be done before the case can close.' : 'Saved.'); this.task = { label:'', code:'', role:'', blocks:false }; await this.reload(); }
            else this.taskErr = body?.message || body?.error || 'Couldn\'t add that task.';
        },
        async completeTask(f){
            if (f.status === 'COMPLETED' || f.status === 'NOT_APPLICABLE') return;
            const { status, body } = await this._req('/alert-followups/' + f.id, { method:'PATCH', body:{ status:'COMPLETED' } });
            if (status === 200 && (body.ok !== false || body.success)) { this.showToast('Task done', ''); await this.reload(); }
            else this.showToast('Update failed', body?.message || body?.error || 'Try again.', 'destructive');
        },
        async markNotApplicable(f){
            const { status, body } = await this._req('/alert-followups/' + f.id, { method:'PATCH', body:{ status:'NOT_APPLICABLE' } });
            if (status === 200 && (body.ok !== false || body.success)) { this.showToast('Marked not applicable', ''); await this.reload(); }
            else this.showToast('Update failed', body?.message || body?.error || 'Try again.', 'destructive');
        },
        async postNote(){
            const body = (this.noteDraft||'').trim(); if (!body) return;
            this.savingNote = true;
            const res = await this._req('/alerts/' + this.alertId + '/comments', { method:'POST', body:{ body, content: body } });
            this.savingNote = false;
            if ((res.status === 200 || res.status === 201) && (res.body.ok !== false || res.body.success)) { this.noteDraft = ''; await this.reload(); }
            else this.showToast('Couldn\'t post that note', res.body?.message || res.body?.error || 'Try again.', 'destructive');
        },

        // ── timeline renderer ────────────────────────────
        eventHeadline(e){
            const c = (e.event_code||'').toUpperCase(); const who = e.actor_name || 'Someone'; const p = this._payload(e);
            switch (c) {
                case 'ALERT_CREATED': case 'OPENED':    return 'This case was opened.';
                case 'ACKNOWLEDGED':                    return who + ' took this case.';
                case 'ESCALATED':                       return who + ' escalated this case.';
                case 'REASSIGNED':                      return who + ' reassigned the case.';
                case 'CLOSED':                          return who + ' closed this case.';
                case 'REOPENED': case 'ALERT_REOPENED': return who + ' reopened this case.';
                case 'FOLLOWUP_ADDED':                  return p.action_label ? 'New task: "' + p.action_label + '"' : 'A new task was added.';
                case 'FOLLOWUP_COMPLETED':              return p.action_label ? 'Task done: "' + p.action_label + '"' : 'A task was marked done.';
                case 'COMMENT_ADDED':                   return who + ' added a note.';
                case 'EMAIL_SENT':                      return 'An email was sent about this case.';
                default:                                return (c||'Activity').replace(/_/g,' ').toLowerCase().replace(/^\w/, x => x.toUpperCase());
            }
        },
        eventDetail(e){
            const c = (e.event_code||'').toUpperCase(); const p = this._payload(e);
            if (c === 'CLOSED') return [this.closeCategoryLabel(p.close_category), p.close_note].filter(Boolean).join(' · ');
            if (c === 'REOPENED' || c === 'ALERT_REOPENED') return p.reason || '';
            if (c === 'ESCALATED' || c === 'REASSIGNED') return p.reason || '';
            return '';
        },
        eventIcon(e){
            const c = (e.event_code||'').toUpperCase();
            if (c === 'CLOSED') return `<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>`;
            if (c === 'REOPENED' || c === 'ALERT_REOPENED') return `<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9"/></svg>`;
            if (c === 'ACKNOWLEDGED') return `<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>`;
            return `<span class="h-1.5 w-1.5 rounded-full bg-current"></span>`;
        },
        eventRing(e){
            const c = (e.event_code||'').toUpperCase();
            if (c === 'CLOSED')  return 'border-muted-foreground text-muted-foreground';
            if (c === 'REOPENED' || c === 'ALERT_REOPENED') return 'border-primary text-primary';
            if (c === 'ACKNOWLEDGED') return 'border-primary text-primary';
            return 'border-border text-muted-foreground';
        },
        _payload(e){ const raw = e?.payload_json; if (!raw) return {}; if (typeof raw === 'object') return raw; try { return JSON.parse(raw) || {}; } catch { return {}; } },
        closeCategoryLabel(code){ if (!code) return ''; const opt = this.closeOptions.find(o => o.code === code); return opt ? opt.label : String(code).replace(/_/g,' ').toLowerCase().replace(/^\w/, c => c.toUpperCase()); },

        // ── language helpers ─────────────────────────────
        humanUrgency(r){ return ({CRITICAL:'Urgent', HIGH:'High', MEDIUM:'Normal', LOW:'Low'})[r] || 'Unknown'; },
        humanStatus(s){ return ({OPEN:'Just opened', ACKNOWLEDGED:'Being handled', CLOSED:'Resolved'})[s] || s; },
        humanRole(r){ return ({NATIONAL_ADMIN:'national administrator', PHEOC_OFFICER:'PHEOC officer', DISTRICT_SUPERVISOR:'district supervisor', POE_ADMIN:'port-of-entry administrator', POE_OFFICER:'port-of-entry officer', SCREENER:'screener'})[r] || (r ? r.toLowerCase().replace(/_/g,' ') : ''); },
        humanConveyance(t){ return ({LAND:'By land', AIR:'By air', SEA:'By sea', OTHER:'Other'})[t] || (t || '—'); },
        humanTriage(t){ return ({NON_URGENT:'Non-urgent', URGENT:'Urgent', EMERGENCY:'Emergency'})[t] || 'Not recorded'; },
        humanAppearance(a){ return ({WELL:'Looked well', UNWELL:'Looked unwell', SEVERELY_ILL:'Severely ill'})[a] || 'Not recorded'; },
        humanDocType(t){ return ({PASSPORT:'Passport', NATIONAL_ID:'National ID', BORDER_PASS:'Border pass', OTHER:'Other'})[t] || 'Not recorded'; },
        humanCaseStatus(s){ return ({OPEN:'Open', IN_PROGRESS:'In progress', DISPOSITIONED:'Dispositioned', CLOSED:'Closed'})[s] || (s || '—'); },
        humanDisposition(d){ return ({RELEASED:'Released', DELAYED:'Delayed for review', QUARANTINED:'Quarantined', ISOLATED:'Isolated', REFERRED:'Referred onward', TRANSFERRED:'Transferred', DENIED_BOARDING:'Denied boarding', OTHER:'Other'})[d] || 'Not recorded'; },
        humanLevel(l){ return ({POE:'Port of entry', DISTRICT:'District', PHEOC:'Regional PHEOC', NATIONAL:'National'})[l] || 'Not assigned'; },
        humanSyndrome(s){
            if (!s) return 'Not recorded';
            const m = { VHF:'Viral haemorrhagic fever', SARI:'Severe respiratory illness', ILI:'Influenza-like illness', AFP:'Acute flaccid paralysis', AFI:'Acute fever illness', MENINGITIS:'Meningitis', RASH_FEVER:'Rash with fever', AWD:'Watery diarrhoea', NEURO:'Neurological' };
            return m[s.toUpperCase()] || s.toLowerCase().replace(/_/g,' ').replace(/^\w/, c => c.toUpperCase());
        },
        humanSymptom(code){ return this._humanFromCode(code); },
        humanExposure(code){ return this._humanFromCode(code); },
        humanOfficerAction(code){ return this._humanFromCode(code); },
        humanTemplate(code){ return ({ALERT_CRITICAL:'Urgent case alert', ALERT_HIGH:'High-priority case alert', ALERT_CLOSED:'Case closed notice', ALERT_CASE_FILE:'Case file ready', ANNEX2_HIT:'Reportable disease flagged', PHEIC_ADVISORY:'Emergency pathway advisory', TIER1_ADVISORY:'High-consequence disease advisory', BREACH_717:'Response deadline missed', ESCALATION:'Case escalated', FOLLOWUP_DUE:'Follow-up due', FOLLOWUP_OVERDUE:'Follow-up overdue'})[code] || this._humanFromCode(code); },
        _humanFromCode(c){ if (!c) return '—'; const s = String(c).replace(/_/g,' ').toLowerCase(); return s.charAt(0).toUpperCase() + s.slice(1); },
        formatGender(g){ return ({MALE:'Male', FEMALE:'Female', OTHER:'Other', UNKNOWN:'Not recorded'})[g] || (g || '—'); },
        maskName(n){
            if (!n) return '';
            const u = this._user().role_key || '';
            if (['NATIONAL_ADMIN','PHEOC_OFFICER','DISTRICT_SUPERVISOR'].includes(u)) return n;
            return n.split(' ').map((p,i) => i === 0 ? p : (p[0]||'') + '.').join(' ');
        },
        maskDoc(d){ if (!d) return ''; const u = this._user().role_key||''; if (['NATIONAL_ADMIN','PHEOC_OFFICER'].includes(u)) return d; return d.length <= 4 ? d : d.slice(0,2) + '•••' + d.slice(-2); },
        maskPhone(p){ if (!p) return ''; const u = this._user().role_key||''; if (['NATIONAL_ADMIN','PHEOC_OFFICER','DISTRICT_SUPERVISOR'].includes(u)) return p; return p.length <= 4 ? p : p.slice(0,3) + '•••' + p.slice(-2); },
        maskEmail(e){ if (!e) return ''; const u = this._user().role_key||''; if (['NATIONAL_ADMIN','PHEOC_OFFICER','DISTRICT_SUPERVISOR'].includes(u)) return e; const [a,b] = e.split('@'); if (!b) return e; return (a||'').slice(0,1) + '••••@' + b; },
        initials(s){ return (s||'?').trim().split(/\s+/).map(x=>x[0]).slice(0,2).join('').toUpperCase() || '?'; },
        humanAgo(v){ if (!v) return ''; const ts=this._ts(v); if (!ts) return String(v); const s=Math.floor((Date.now()-ts)/1000); if(s<60)return 'just now'; if(s<3600)return Math.floor(s/60)+' min ago'; if(s<86400)return Math.floor(s/3600)+' hr ago'; if(s<604800)return Math.floor(s/86400)+' days ago'; return new Date(ts).toLocaleDateString(); },
        humanDate(v){ if (!v) return '—'; const ts=this._ts(v); return ts ? new Date(ts).toLocaleString() : String(v); },
        _ts(v){ if (!v) return null; const d = new Date(String(v).replace(' ','T') + (String(v).includes('T')?'':'Z')); return isNaN(d) ? null : d.getTime(); },
        _escape(s){ return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[c]); },
        showToast(title, message, tone='default'){ this.toast = { open:true, title, message, tone, _t:null }; clearTimeout(this.toast._t); this.toast._t = setTimeout(()=> this.toast.open = false, 3500); },
    };
}
</script>
@endpush
@endsection

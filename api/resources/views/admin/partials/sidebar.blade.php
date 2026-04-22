{{-- ============================================================================
  SIDEBAR · primary navigation
  ----------------------------------------------------------------------------
  Three vertical zones:
    1. Brand block at the top (logo + tenant label)
    2. Scrollable nav with 7 sections (Today, Cases, Your team, Communications,
       Reports, Reference, System) — 20 items total per dashboard.txt MVP.
    3. User card at the bottom (avatar + name + role + muted "today" line).

  Public-health language only. Built routes link; planned routes render muted
  + tagged "Soon". Active state uses bg-accent (shadcn default).

  Active-state is computed server-side via $isActive.
============================================================================ --}}
@php
    $path = '/' . ltrim(request()->path(), '/');

    $isActive = function (...$patterns) use ($path) {
        foreach ($patterns as $p) {
            if ($p === $path) return true;
            if (str_ends_with($p, '*') && str_starts_with($path, rtrim($p, '*'))) return true;
        }
        return false;
    };

    $item = function (string $href, string $label, bool $active = false, ?string $icon = null) {
        $planned = ($href === '#' || str_starts_with($href, '#'));
        $base = 'relative flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] transition-colors';
        $live = $active
            ? 'bg-accent text-accent-foreground font-medium'
            : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground';
        $dead = 'text-muted-foreground/60 cursor-not-allowed';
        $class = $base . ' ' . ($planned ? $dead : $live);
        $tag   = $planned ? 'span' : 'a';
        $attr  = $planned ? '' : ' href="'.$href.'"';
        $iconSvg = $icon ?: '<span class="h-4 w-4"></span>';
        $soon = $planned ? '<span class="ml-auto text-[10px] font-medium tracking-wide text-muted-foreground/60">Soon</span>' : '';
        return "<{$tag}{$attr} class=\"{$class}\">{$iconSvg}<span class=\"truncate\">{$label}</span>{$soon}</{$tag}>";
    };

    $icons = [
        'home'     => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
        'file'     => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
        'shield'   => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
        'users'    => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a3 3 0 015.356-1.857M17 8a3 3 0 11-6 0 3 3 0 016 0zM7 8a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'pin'      => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'alert'    => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'moon'     => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>',
        'mail'     => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
        'send'     => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>',
        'people'   => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
        'chart'    => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
        'grid'     => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>',
        'health'   => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l3-9 4 18 3-9h4"/></svg>',
        'building' => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>',
        'flask'    => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v5.17a4 4 0 01-.59 2.09L3.66 18a2 2 0 001.71 3h13.26a2 2 0 001.71-3l-4.75-7.74A4 4 0 0115 8.17V3M8 3h8"/></svg>',
        'triangle' => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>',
        'list'     => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>',
        'trail'    => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
    ];
@endphp

<div class="flex h-full flex-col bg-background">

    {{-- ── BRAND BLOCK ─────────────────────────────────────────── --}}
    <div class="h-14 flex items-center gap-2.5 px-4 border-b shrink-0">
        <div class="h-7 w-7 rounded-md bg-primary text-primary-foreground flex items-center justify-center shrink-0">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-semibold tracking-tight truncate">PHEOC Uganda</div>
            <div class="text-[11px] text-muted-foreground truncate">Command Centre</div>
        </div>
    </div>

    {{-- ── NAV ───────────────────────────────────────────────── --}}
    <nav class="flex-1 overflow-y-auto scrollbar-thin px-3 py-3 space-y-5" aria-label="Primary">

        {{-- TODAY --}}
        <div class="space-y-0.5">
            <p class="px-2.5 pb-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground/60">Today</p>
            {!! $item(url('/admin/dashboard'), 'Dashboard', $isActive('/admin','/admin/dashboard'), $icons['home']) !!}
        </div>

        {{-- CASES & RESPONSE --}}
        <div class="space-y-0.5">
            <p class="px-2.5 pb-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground/60">Cases &amp; response</p>
            {!! $item(url('/admin/alerts'), 'Open cases', $isActive('/admin/alerts','/admin/alerts/*'), $icons['alert']) !!}
            {!! $item('#', 'Case files', false, $icons['file']) !!}
            {!! $item('#', 'Response deadlines', false, $icons['shield']) !!}
        </div>

        {{-- YOUR TEAM --}}
        <div class="space-y-0.5">
            <p class="px-2.5 pb-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground/60">Your team</p>
            {!! $item(url('/admin/users'), 'Team members', $isActive('/admin/users'), $icons['users']) !!}
            {!! $item(url('/admin/assignments'), 'Where people work', $isActive('/admin/assignments','/admin/assignments/*'), $icons['pin']) !!}
            {!! $item(url('/admin/users/risk'), 'Accounts to review', $isActive('/admin/users/risk','/admin/users/risk/*'), $icons['alert']) !!}
            {!! $item(url('/admin/users/dormant'), 'Quiet accounts', $isActive('/admin/users/dormant','/admin/users/dormant/*'), $icons['moon']) !!}
        </div>

        {{-- COMMUNICATIONS --}}
        <div class="space-y-0.5">
            <p class="px-2.5 pb-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground/60">Communications</p>
            {!! $item('#', 'My messages', false, $icons['mail']) !!}
            {!! $item('#', 'Emails sent', false, $icons['send']) !!}
            {!! $item('#', 'Outside responders', false, $icons['people']) !!}
        </div>

        {{-- REPORTS --}}
        <div class="space-y-0.5">
            <p class="px-2.5 pb-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground/60">Reports</p>
            {!! $item('#', 'National briefing', false, $icons['chart']) !!}
            {!! $item('#', 'Report forms', false, $icons['grid']) !!}
            {!! $item('#', 'District submissions', false, $icons['list']) !!}
        </div>

        {{-- REFERENCE --}}
        <div class="space-y-0.5">
            <p class="px-2.5 pb-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground/60">Reference</p>
            {!! $item(url('/admin/settings/poe-contacts'), 'Who we email', $isActive('/admin/settings/poe-contacts','/admin/settings/poe-contacts/*'), $icons['mail']) !!}
            {!! $item(url('/admin/settings/poes'), 'Ports of entry', $isActive('/admin/settings/poes','/admin/settings/poes/*'), $icons['building']) !!}
            {!! $item(url('/admin/settings/diseases'), 'Diseases we watch', $isActive('/admin/settings/diseases','/admin/settings/diseases/*'), $icons['flask']) !!}
            {!! $item(url('/admin/settings/exposures'), 'Travel &amp; contact risks', $isActive('/admin/settings/exposures','/admin/settings/exposures/*'), $icons['triangle']) !!}
        </div>

        {{-- SYSTEM --}}
        <div class="space-y-0.5">
            <p class="px-2.5 pb-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground/60">System</p>
            {!! $item('#', 'Activity log', false, $icons['trail']) !!}
            {!! $item('#', 'System status', false, $icons['health']) !!}
        </div>

    </nav>

    {{-- ── USER CARD ─────────────────────────────────────────── --}}
    <div class="border-t p-3 shrink-0">
        <button type="button"
                class="w-full flex items-center gap-2.5 rounded-md px-2 py-1.5 text-left hover:bg-accent transition-colors"
                @click="userMenu = true">
            <span class="h-8 w-8 rounded-full bg-muted text-muted-foreground flex items-center justify-center text-[11px] font-semibold shrink-0"
                  x-text="initials"></span>
            <span class="min-w-0 flex-1">
                <span class="block text-[13px] font-medium truncate" x-text="user.full_name || user.email || 'Sign in'"></span>
                <span class="block text-[11px] text-muted-foreground truncate" x-text="user.role_label || 'Uganda'"></span>
            </span>
            <svg class="h-3.5 w-3.5 text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 9l7-7 7 7M5 15l7 7 7-7"/></svg>
        </button>
    </div>

</div>

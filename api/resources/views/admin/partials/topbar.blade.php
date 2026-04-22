{{-- ============================================================================
  TOP BAR
  ----------------------------------------------------------------------------
  Sits only in the main column (not full-width). Brand lives in the sidebar
  on desktop; on mobile we surface a small brand mark alongside the menu
  button. Three things only: menu (mobile), bell, avatar.
============================================================================ --}}
<header class="h-14 border-b bg-background/80 backdrop-blur sticky top-0 z-30">
    <div class="h-full px-3 sm:px-6 flex items-center gap-2 sm:gap-3">

        {{-- Mobile menu toggle --}}
        <button type="button"
                class="btn btn-ghost btn-icon-xs lg:hidden"
                @click="sidebarOpen = true"
                aria-label="Open menu">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        {{-- Mobile-only small brand (sidebar brand is hidden on mobile until the sheet opens) --}}
        <a href="{{ url('/admin/dashboard') }}" class="lg:hidden flex items-center gap-2 min-w-0">
            <div class="h-6 w-6 rounded-md bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <span class="text-sm font-semibold tracking-tight truncate">PHEOC</span>
        </a>

        {{-- Desktop: small page hint (optional yield) --}}
        <span class="hidden lg:inline text-[13px] text-muted-foreground truncate">@yield('crumb')</span>

        {{-- Right cluster --}}
        <div class="ml-auto flex items-center gap-1">

            {{-- Notifications bell --}}
            <button type="button"
                    class="btn btn-ghost btn-icon-xs relative"
                    @click="openNotifs()"
                    aria-label="Notifications">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <span x-show="notifs.unread > 0" x-cloak
                      class="absolute -top-0.5 -right-0.5 h-4 min-w-4 px-1 inline-flex items-center justify-center rounded-full bg-primary text-[9px] font-semibold text-primary-foreground tabular-nums"
                      x-text="notifs.unread > 99 ? '99+' : notifs.unread"></span>
            </button>

            {{-- Avatar / user menu — hidden on desktop (sidebar owns the user card) --}}
            <button type="button"
                    class="btn btn-ghost btn-icon-xs lg:hidden"
                    @click="userMenu = true"
                    aria-label="Your account">
                <span class="h-6 w-6 rounded-full bg-muted text-muted-foreground flex items-center justify-center text-[10px] font-semibold" x-text="initials"></span>
            </button>
        </div>
    </div>
</header>

{{-- ============================================================================
  ADMIN SHELL · shadcn/ui foundation
  ----------------------------------------------------------------------------
  Mobile-first shell used by every /admin/* page.

  Section hooks for child views:
    @section('title')        — top-bar title + <title>
    @section('subtitle')     — one-line caption under title (optional)
    @section('page_actions') — right-aligned actions beside title
    @section('content')      — page body
    @push('head')            — extra <head> tags
    @push('scripts')         — extra <script> tags

  Alpine root on <body> owns shared shell state:
    shell.sidebarOpen        — mobile slide-over state
    shell.userMenu           — avatar dropdown
    shell.notifsOpen         — notifications sheet
    shell.unread             — integer bell badge (kept fresh every 60s)
============================================================================ --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#ffffff">

    <title>@yield('title', 'Command Centre') · {{ config('app.name', 'PHEOC Uganda') }}</title>

    @include('admin.partials.theme')

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @stack('head')
</head>
<body class="min-h-screen bg-background text-foreground antialiased"
      x-data="adminShell()"
      x-init="init()"
      @keydown.window.escape="closeAll()">

{{-- ── LAYOUT: sidebar + main column ───────────────────────────────── --}}
<div class="flex min-h-screen">

    {{-- Desktop sidebar (fixed rail, its own brand block) --}}
    <aside class="hidden lg:flex lg:w-64 lg:fixed lg:inset-y-0 lg:border-r">
        @include('admin.partials.sidebar')
    </aside>

    {{-- Mobile slide-over sidebar --}}
    <template x-if="sidebarOpen">
        <div class="lg:hidden">
            <div class="sheet-overlay" @click="sidebarOpen = false"></div>
            <aside class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] border-r bg-background shadow-lg" role="dialog" aria-modal="true">
                <div @click="sidebarOpen = false" class="h-full">
                    @include('admin.partials.sidebar')
                </div>
            </aside>
        </div>
    </template>

    {{-- Main column --}}
    <div class="flex-1 min-w-0 lg:ml-64 flex flex-col">

        @include('admin.partials.topbar')

        {{-- Page header --}}
        @hasSection('title')
        <header class="border-b bg-background">
            <div class="px-4 sm:px-6 py-4 flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <h1 class="text-lg sm:text-xl font-semibold tracking-tight truncate">
                        @yield('title')
                    </h1>
                    @hasSection('subtitle')
                        <p class="description mt-0.5 line-clamp-1">@yield('subtitle')</p>
                    @endif
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    @yield('page_actions')
                </div>
            </div>
        </header>
        @endif

        {{-- Content --}}
        <main class="flex-1 px-4 sm:px-6 py-6">
            @yield('content')
        </main>

        <footer class="px-4 sm:px-6 py-3 border-t">
            <p class="text-[11px] text-muted-foreground">
                &copy; {{ date('Y') }} PHEOC Uganda &middot; Ministry of Health
            </p>
        </footer>
    </div>
</div>

{{-- ── NOTIFICATIONS SHEET (right slide-over) ──────────────────────── --}}
<template x-if="notifsOpen">
    <div>
        <div class="sheet-overlay" @click="notifsOpen = false"></div>
        <aside class="sheet-content sheet-right flex flex-col" role="dialog" aria-modal="true">
            <div class="sheet-header">
                <h3 class="sheet-title">What's new</h3>
                <p class="sheet-description">Recent messages sent to you.</p>
            </div>
            <div class="flex-1 overflow-y-auto py-4 -mx-2 px-2">
                <template x-if="notifs.loading">
                    <div class="space-y-3 pt-2">
                        <template x-for="i in 3" :key="i">
                            <div class="flex gap-3"><div class="skeleton h-8 w-8 rounded-full"></div><div class="flex-1 space-y-2"><div class="skeleton h-3 w-3/4"></div><div class="skeleton h-3 w-1/2"></div></div></div>
                        </template>
                    </div>
                </template>
                <template x-if="!notifs.loading && notifs.items.length === 0">
                    <div class="py-16 text-center">
                        <svg class="mx-auto h-8 w-8 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <p class="mt-3 text-sm font-medium">You're all caught up</p>
                        <p class="description mt-1">No messages yet.</p>
                    </div>
                </template>
                <ul class="space-y-3" x-show="!notifs.loading && notifs.items.length > 0">
                    <template x-for="n in notifs.items" :key="n.id">
                        <li class="flex items-start gap-3 rounded-md px-2 py-2 hover:bg-accent cursor-pointer transition-colors"
                            @click="openNotif(n)">
                            <span class="mt-1.5 h-2 w-2 rounded-full shrink-0"
                                  :class="n.is_read ? 'bg-muted-foreground/40' : 'bg-primary'"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium leading-snug line-clamp-2" x-text="humaniseSubject(n)"></p>
                                <p class="help-text mt-0.5" x-text="humaniseWhen(n.created_at)"></p>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
            <div class="sheet-footer pt-3 border-t">
                <button type="button" class="btn btn-ghost btn-xs" @click="notifsOpen = false">Close</button>
                <button type="button" class="btn btn-default btn-xs" @click="markAllRead()"
                        :disabled="notifs.unread === 0">
                    Mark all read
                </button>
            </div>
        </aside>
    </div>
</template>

{{-- ── USER DROPDOWN (positioned portal) ─────────────────────────── --}}
<template x-if="userMenu">
    <div class="fixed inset-0 z-50" @click="userMenu = false">
        <div class="absolute right-3 sm:right-6 top-14 mt-1 dropdown-content" @click.stop>
            <div class="dropdown-label truncate" x-text="user.full_name || user.email || 'Signed in'"></div>
            <div class="dropdown-label text-muted-foreground font-normal truncate" x-show="user.role_label" x-text="user.role_label"></div>
            <div class="dropdown-separator"></div>
            <a class="dropdown-item" href="{{ url('/admin/dashboard') }}">Dashboard</a>
            <button type="button" class="dropdown-item w-full text-left" @click="userMenu=false; signOut()">Sign out</button>
        </div>
    </div>
</template>

{{-- ── TOAST (shell-level) ──────────────────────────────────────── --}}
<div x-show="toast.open" x-cloak x-transition
     class="fixed bottom-4 right-4 z-50 w-[calc(100vw-2rem)] sm:w-[340px]">
    <div class="toast" :class="toast.tone === 'destructive' ? 'toast-destructive' : ''">
        <div class="grid gap-1">
            <div class="toast-title" x-text="toast.title"></div>
            <div class="toast-description" x-text="toast.message"></div>
        </div>
        <button type="button" class="toast-action" @click="toast.open=false">Dismiss</button>
    </div>
</div>

<script>
/**
 * Shell-level Alpine state machine.
 *   · Loads the signed-in user from localStorage (written by /admin/login).
 *   · Polls /api/inbox for unread count every 60s.
 *   · Owns sidebar, notifications, user menu, toast.
 *   · signOut() calls POST /api/v2/auth/logout with the Sanctum bearer,
 *     wipes the local state, and redirects to /admin/login.
 */
function adminShell(){
    return {
        sidebarOpen: false,
        userMenu: false,
        notifsOpen: false,
        user: { id: 0, full_name: '', email: '', role_key: '', role_label: '' },
        initials: '??',
        notifs: { loading: false, items: [], unread: 0, _timer: null },
        toast: { open: false, title: '', message: '', tone: 'default', _t: null },

        init(){
            try {
                const raw = localStorage.getItem('pheoc_user');
                if (raw) {
                    const u = JSON.parse(raw);
                    this.user = {
                        id: Number(u.id) || 0,
                        full_name: u.full_name || u.name || '',
                        email: u.email || u.username || '',
                        role_key: u.role_key || '',
                        role_label: this.humaniseRole(u.role_key),
                    };
                    this.initials = (u.full_name || u.email || '?').trim().split(/\s+/).map(x=>x[0]).slice(0,2).join('').toUpperCase() || '?';
                }
            } catch (e) { /* ignore */ }

            if (this.user.id > 0) {
                this.pollUnread();
                this.notifs._timer = setInterval(() => this.pollUnread(), 60000);
            }
        },

        // ── API plumbing ─────────────────────────────────────────────
        _token(){ return localStorage.getItem('pheoc_token') || ''; },
        async _api(path, opts = {}){
            const t = this._token();
            const url = '{{ url('/api') }}' + path + (path.includes('?') ? '&' : '?') + 'user_id=' + (this.user.id || 0);
            try {
                const res = await fetch(url, {
                    method: opts.method || 'GET',
                    headers: Object.assign({
                        'Accept':'application/json',
                        'Content-Type':'application/json',
                        'Authorization': t ? ('Bearer ' + t) : '',
                        'X-User-Id': this.user.id || '',
                    }, opts.headers || {}),
                    body: opts.body != null ? JSON.stringify(Object.assign({user_id:this.user.id}, opts.body)) : undefined,
                });
                const ct = res.headers.get('content-type') || '';
                const body = ct.includes('json') ? await res.json() : {};
                return { status: res.status, body };
            } catch (e) {
                return { status: 0, body: {} };
            }
        },

        // ── notifications ────────────────────────────────────────────
        async pollUnread(){
            if (!this.user.id) return;
            const { status, body } = await this._api('/inbox/unread-count');
            if (status === 200 && body.ok) this.notifs.unread = Number(body.data?.count || 0);
        },
        async openNotifs(){
            this.notifsOpen = true;
            if (!this.user.id) return;
            this.notifs.loading = true;
            const { status, body } = await this._api('/inbox?limit=25');
            this.notifs.loading = false;
            if (status === 200 && body.ok) this.notifs.items = body.data?.items || [];
        },
        async markAllRead(){
            await this._api('/inbox/mark-all-read', { method:'POST', body:{} });
            this.notifs.items.forEach(n => n.is_read = true);
            this.notifs.unread = 0;
            this.showToast('Marked as read', 'You are all caught up.');
        },
        async openNotif(n){
            if (!n.is_read) {
                await this._api('/inbox/mark-read', { method:'POST', body:{ ids:[n.id] } });
                n.is_read = true;
                this.notifs.unread = Math.max(0, this.notifs.unread - 1);
            }
            // Deep-link to the entity if known
            if (n.related_entity_type === 'ALERT' && n.related_entity_id) {
                location.href = '{{ url('/admin/alerts') }}/' + n.related_entity_id;
            }
        },

        // ── sign out (real) ──────────────────────────────────────────
        async signOut(){
            try {
                await fetch('{{ url('/api/v2/auth/logout') }}', {
                    method: 'POST',
                    headers: { 'Accept':'application/json', 'Authorization':'Bearer ' + this._token() },
                });
            } catch (e) { /* ignore */ }
            localStorage.removeItem('pheoc_token');
            localStorage.removeItem('pheoc_user');
            location.replace('{{ url('/admin/login') }}');
        },

        // ── utils ────────────────────────────────────────────────────
        closeAll(){ this.sidebarOpen=false; this.userMenu=false; this.notifsOpen=false; },
        showToast(title, message, tone='default'){
            this.toast = { open:true, title, message, tone, _t:null };
            clearTimeout(this.toast._t);
            this.toast._t = setTimeout(()=> this.toast.open = false, 3500);
        },
        humaniseRole(key){
            return ({
                NATIONAL_ADMIN:      'National administrator',
                PHEOC_OFFICER:       'PHEOC officer',
                DISTRICT_SUPERVISOR: 'District supervisor',
                POE_ADMIN:           'Port-of-entry administrator',
                POE_OFFICER:         'Port-of-entry officer',
                SCREENER:            'Screener',
                OBSERVER:            'Observer',
                SERVICE:             'Service account',
            })[key] || (key ? key.replace(/_/g,' ').toLowerCase().replace(/\b\w/g,c=>c.toUpperCase()) : '');
        },
        humaniseSubject(n){
            // Templates like "AUTH_INVITATION", "ALERT_CLOSED" come through as subject-templated strings.
            // Prefer the rendered subject if present; otherwise translate the template code.
            if (n.subject && !n.subject.startsWith('{{')) return n.subject;
            return ({
                ALERT_CRITICAL:       'An urgent case needs attention',
                ALERT_HIGH:           'A high-priority case was opened',
                ALERT_CLOSED:         'A case was closed',
                ALERT_CASE_FILE:      'A case file is ready',
                ANNEX2_HIT:           'A reportable disease was flagged',
                PHEIC_ADVISORY:       'A public-health emergency pathway was opened',
                TIER1_ADVISORY:       'A high-consequence disease is suspected',
                BREACH_717:           'Response deadline missed — root cause due',
                ESCALATION:           'A case was escalated',
                FOLLOWUP_DUE:         'A follow-up is due',
                FOLLOWUP_OVERDUE:     'A follow-up is overdue',
                RESPONDER_INFO_REQUEST: 'Someone is asking you to review a case',
                DAILY_REPORT:         'Daily digest',
                WEEKLY_REPORT:        'Weekly scorecard',
                NATIONAL_INTELLIGENCE:'National intelligence brief',
                AUTH_WELCOME:         'Welcome to PHEOC',
                AUTH_INVITATION:      'You have been invited',
                AUTH_VERIFY_EMAIL:    'Verify your email',
                AUTH_PASSWORD_RESET:  'Reset your password',
                AUTH_PASSWORD_CHANGED:'Your password was changed',
                AUTH_TWOFA_ENABLED:   'Two-factor sign-in enabled',
                AUTH_TWOFA_DISABLED:  'Two-factor sign-in disabled',
                AUTH_NEW_LOGIN_DEVICE:'New sign-in from a device',
                AUTH_ACCOUNT_LOCKED:  'Your account was locked',
                AUTH_SUSPENDED:       'Your account was suspended',
            })[n.template_code] || 'Notification';
        },
        humaniseWhen(v){
            if (!v) return '—';
            const d = new Date(String(v).replace(' ','T') + (String(v).includes('T') ? '' : 'Z'));
            if (isNaN(d)) return String(v);
            const s = Math.floor((Date.now()-d.getTime())/1000);
            if (s<60) return 'just now';
            if (s<3600) return Math.floor(s/60)+' min ago';
            if (s<86400) return Math.floor(s/3600)+' hr ago';
            if (s<2592000) return Math.floor(s/86400)+' days ago';
            return d.toLocaleDateString();
        },
    };
}
</script>

@stack('scripts')
</body>
</html>

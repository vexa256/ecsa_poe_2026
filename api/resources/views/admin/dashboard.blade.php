{{-- ============================================================================
  /admin/dashboard — Today
  ----------------------------------------------------------------------------
  The landing page. One question at the top ("What would you like to do next?")
  and three stat-cards that read from the real /api/alerts/summary endpoint.
  Uses only shadcn primitives; no colour accents beyond primary/destructive.
============================================================================ --}}
@extends('admin.layout')

@section('title', 'Today')
@section('subtitle', "Here's what needs you right now.")

@section('content')
<div x-data="todayView()" x-init="init()" class="space-y-6 max-w-3xl">

    {{-- Greeting --}}
    <div class="space-y-1">
        <p class="text-sm font-medium" x-text="greeting"></p>
        <p class="description">Three things matter today. Tap anything to open it.</p>
    </div>

    {{-- Three cards — public-health framing, real data --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <a href="{{ url('/admin/alerts') }}" class="card hover:bg-accent transition-colors">
            <div class="card-header pb-2">
                <div class="flex items-start justify-between gap-2">
                    <p class="description">Open cases</p>
                    <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
            </div>
            <div class="card-content">
                <template x-if="loading"><div class="skeleton h-8 w-10"></div></template>
                <template x-if="!loading">
                    <div class="text-2xl font-semibold tracking-tight tabular-nums" x-text="summary.open_total ?? 0"></div>
                </template>
                <p class="help-text mt-1">
                    <template x-if="(summary.open_critical ?? 0) > 0">
                        <span><span class="font-semibold text-foreground" x-text="summary.open_critical"></span> need your attention right now</span>
                    </template>
                    <template x-if="(summary.open_critical ?? 0) === 0">
                        <span>No critical cases right now</span>
                    </template>
                </p>
            </div>
        </a>

        <a href="{{ url('/admin/alerts') }}?status=OPEN" class="card hover:bg-accent transition-colors">
            <div class="card-header pb-2">
                <div class="flex items-start justify-between gap-2">
                    <p class="description">Awaiting response &gt; 24h</p>
                    <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="card-content">
                <template x-if="loading"><div class="skeleton h-8 w-10"></div></template>
                <template x-if="!loading">
                    <div class="text-2xl font-semibold tracking-tight tabular-nums" x-text="summary.unacknowledged_24h ?? 0"></div>
                </template>
                <p class="help-text mt-1" x-text="(summary.unacknowledged_24h ?? 0) > 0 ? 'Please acknowledge or escalate' : 'Nothing pending beyond the response window'"></p>
            </div>
        </a>

        <a href="{{ url('/admin/users') }}" class="card hover:bg-accent transition-colors">
            <div class="card-header pb-2">
                <div class="flex items-start justify-between gap-2">
                    <p class="description">People on duty today</p>
                    <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a3 3 0 015.356-1.857M17 8a3 3 0 11-6 0 3 3 0 016 0zM7 8a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
            </div>
            <div class="card-content">
                <template x-if="loading"><div class="skeleton h-8 w-10"></div></template>
                <template x-if="!loading">
                    <div class="text-2xl font-semibold tracking-tight tabular-nums" x-text="peopleOnDuty ?? '—'"></div>
                </template>
                <p class="help-text mt-1">Active accounts across Uganda</p>
            </div>
        </a>
    </div>

    {{-- What to do next — AI-guided suggestions --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">What would you like to do next?</h3>
            <p class="card-description">Choose one — you can always come back here.</p>
        </div>
        <div class="card-content pt-0">
            <ul class="divide-y divide-border">
                <li>
                    <a href="{{ url('/admin/alerts') }}" class="flex items-center gap-3 px-1 py-3 hover:bg-accent -mx-1 rounded-md transition-colors">
                        <div class="h-8 w-8 rounded-md bg-muted flex items-center justify-center shrink-0">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">Review today's cases</p>
                            <p class="description">Sort by urgency, decide who responds next.</p>
                        </div>
                        <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </li>
                <li>
                    <a href="{{ url('/admin/users') }}" class="flex items-center gap-3 px-1 py-3 hover:bg-accent -mx-1 rounded-md transition-colors">
                        <div class="h-8 w-8 rounded-md bg-muted flex items-center justify-center shrink-0">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">Invite a team member</p>
                            <p class="description">Add someone to screen travellers or support a district.</p>
                        </div>
                        <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </li>
                <li>
                    <a href="{{ url('/admin/settings/poe-contacts') }}" class="flex items-center gap-3 px-1 py-3 hover:bg-accent -mx-1 rounded-md transition-colors">
                        <div class="h-8 w-8 rounded-md bg-muted flex items-center justify-center shrink-0">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">Check who gets emailed</p>
                            <p class="description">Make sure the right people hear when a case opens.</p>
                        </div>
                        <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </li>
            </ul>
        </div>
    </div>

</div>

@push('scripts')
<script>
function todayView(){
    return {
        loading: true,
        summary: {},
        peopleOnDuty: null,
        greeting: (() => {
            const h = new Date().getHours();
            const p = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
            let who = '';
            try { who = (JSON.parse(localStorage.getItem('pheoc_user')||'{}').full_name || '').split(' ')[0] || ''; } catch(e){}
            return who ? `${p}, ${who}.` : `${p}.`;
        })(),

        async init(){
            if (!localStorage.getItem('pheoc_token')) {
                location.replace('{{ url('/admin/login') }}?next=' + encodeURIComponent(location.pathname));
                return;
            }
            await Promise.all([this.loadSummary(), this.loadPeople()]);
            this.loading = false;
        },

        _token(){ return localStorage.getItem('pheoc_token') || ''; },
        _uid(){ try { return Number((JSON.parse(localStorage.getItem('pheoc_user')||'{}')).id||0); } catch { return 0; } },

        async loadSummary(){
            try {
                const res = await fetch('{{ url('/api/alerts/summary') }}?user_id=' + this._uid(), {
                    headers: { 'Accept':'application/json', 'Authorization':'Bearer '+this._token() },
                });
                const b = await res.json().catch(()=>({}));
                if (res.ok) this.summary = (b.data || b) || {};
            } catch(e){ /* ignore */ }
        },
        async loadPeople(){
            try {
                const res = await fetch('{{ url('/api/v2/admin/users/stats') }}', {
                    headers: { 'Accept':'application/json', 'Authorization':'Bearer '+this._token() },
                });
                const b = await res.json().catch(()=>({}));
                if (res.ok && b.ok) this.peopleOnDuty = Number(b.data?.status?.active || 0);
            } catch(e){ /* ignore */ }
        },
    };
}
</script>
@endpush
@endsection

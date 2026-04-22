{{-- ============================================================================
  THEME PREVIEW — PHASE 2 VERIFICATION SURFACE
  ----------------------------------------------------------------------------
  /admin/__theme
  Renders every primitive shipped in admin.partials.theme alongside a couple
  of end-state compositions (sign-in card, case action confirm, notification
  sheet) so the visual fit can be checked against shadcn/ui docs.
  NOT linked from the sidebar. Delete after Phase 2 sign-off.
============================================================================ --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Theme preview · shadcn foundation</title>
    @include('admin.partials.theme')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-background text-foreground min-h-screen"
      x-data="{
          dialogOpen:false, closeDialog:false, sheetOpen:false,
          tab:'account', toast:false, _t:null,
          fireToast(){ this.toast=true; clearTimeout(this._t); this._t=setTimeout(()=>this.toast=false,3200); },
      }">

{{-- ── sticky top bar ──────────────────────────────────────────────── --}}
<header class="sticky top-0 z-40 w-full border-b bg-background/80 backdrop-blur">
    <div class="mx-auto flex h-14 max-w-5xl items-center gap-4 px-4">
        <div class="flex items-center gap-2">
            <div class="flex h-6 w-6 items-center justify-center rounded-md bg-primary text-primary-foreground">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <span class="text-sm font-semibold tracking-tight">PHEOC Command Centre</span>
            <span class="hidden sm:inline text-xs text-muted-foreground">/ design system</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <button class="btn btn-ghost btn-xs hidden sm:inline-flex">Docs</button>
            <button class="btn btn-outline btn-xs">
                <span>Search…</span>
                <span class="kbd ml-2">⌘K</span>
            </button>
        </div>
    </div>
</header>

<main class="mx-auto max-w-5xl px-4 py-10 lg:py-14">

    {{-- ── hero ───────────────────────────────────────────────────── --}}
    <section class="space-y-3 pb-10 border-b">
        <div class="inline-flex items-center gap-1.5 rounded-md border bg-muted px-2 py-0.5 text-xs text-muted-foreground">
            <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
            Phase 2 · foundation
        </div>
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight">Design system preview</h1>
        <p class="max-w-2xl text-base text-muted-foreground leading-relaxed">
            The primitives every admin view will compose from — indistinguishable from default
            shadcn/ui. Black on white, generous whitespace, small buttons, no decorative colour.
        </p>
        <div class="flex flex-wrap gap-2 pt-1">
            <a href="https://ui.shadcn.com/docs/components" target="_blank" rel="noopener"
               class="btn btn-default">Compare with shadcn docs</a>
            <a href="{{ url('/admin/dashboard') }}" class="btn btn-outline">Back to dashboard</a>
        </div>
    </section>

    {{-- ── Button ─────────────────────────────────────────────────── --}}
    <section class="space-y-4 py-10 border-b">
        <header class="space-y-1">
            <h2 id="button" class="text-xl font-semibold tracking-tight">Button</h2>
            <p class="description">Primary, secondary, destructive, ghost, link. Small by default.</p>
        </header>

        <div class="card">
            <div class="card-content p-6 space-y-5">
                <div class="flex flex-wrap items-center gap-2">
                    <button class="btn btn-default">Primary</button>
                    <button class="btn btn-secondary">Secondary</button>
                    <button class="btn btn-outline">Outline</button>
                    <button class="btn btn-ghost">Ghost</button>
                    <button class="btn btn-destructive">Delete</button>
                    <button class="btn btn-link">Learn more</button>
                </div>
                <div class="separator separator-h"></div>
                <div class="flex flex-wrap items-center gap-2">
                    <button class="btn btn-default btn-xs">Extra small</button>
                    <button class="btn btn-outline btn-xs">Extra small</button>
                    <button class="btn btn-default btn-icon" aria-label="Add">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                    <button class="btn btn-outline btn-icon-xs" aria-label="Check">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </button>
                    <button class="btn btn-default" disabled>Disabled</button>
                    <button class="btn btn-outline">
                        <svg class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/>
                        </svg>
                        Loading
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Form ───────────────────────────────────────────────────── --}}
    <section class="space-y-4 py-10 border-b">
        <header class="space-y-1">
            <h2 id="form" class="text-xl font-semibold tracking-tight">Form fields</h2>
            <p class="description">Labels, help text, validation, keyboard focus ring.</p>
        </header>

        <div class="card max-w-xl">
            <div class="card-header">
                <h3 class="card-title">Update your profile</h3>
                <p class="card-description">These details appear in audit logs and notifications.</p>
            </div>
            <div class="card-content space-y-4">
                <div class="space-y-1.5">
                    <label class="label" for="p-name">Full name</label>
                    <input id="p-name" class="input" value="Timothy Kamukama">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label class="label" for="p-email">Work email</label>
                        <input id="p-email" class="input" type="email" placeholder="you@health.go.ug">
                        <p class="help-text">We'll never show this publicly.</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="label" for="p-role">Role</label>
                        <select id="p-role" class="select">
                            <option>National administrator</option>
                            <option>PHEOC officer</option>
                            <option>District supervisor</option>
                            <option>Screener</option>
                        </select>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label class="label" for="p-about">About you</label>
                    <textarea id="p-about" class="textarea" rows="3" placeholder="A short bio your team sees when they hover your avatar."></textarea>
                    <p class="help-text">Up to 280 characters.</p>
                </div>
            </div>
            <div class="card-footer justify-end gap-2">
                <button class="btn btn-ghost">Discard</button>
                <button class="btn btn-default">Save changes</button>
            </div>
        </div>
    </section>

    {{-- ── Card stack ─────────────────────────────────────────────── --}}
    <section class="space-y-4 py-10 border-b">
        <header class="space-y-1">
            <h2 id="card" class="text-xl font-semibold tracking-tight">Card</h2>
            <p class="description">The workhorse container. Stack them, nest them, keep them quiet.</p>
        </header>

        <div class="grid gap-4 md:grid-cols-3">
            {{-- Stat card --}}
            <div class="card">
                <div class="card-header pb-2">
                    <div class="flex items-center justify-between">
                        <p class="description">Cases open today</p>
                        <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                </div>
                <div class="card-content">
                    <div class="text-2xl font-semibold tracking-tight tabular-nums">47</div>
                    <p class="help-text mt-1">+6 since yesterday</p>
                </div>
            </div>
            <div class="card">
                <div class="card-header pb-2">
                    <div class="flex items-center justify-between">
                        <p class="description">Awaiting response &gt;24h</p>
                        <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="card-content">
                    <div class="text-2xl font-semibold tracking-tight tabular-nums">2</div>
                    <p class="help-text mt-1">Needs your attention</p>
                </div>
            </div>
            <div class="card">
                <div class="card-header pb-2">
                    <div class="flex items-center justify-between">
                        <p class="description">Team members online</p>
                        <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a3 3 0 015.356-1.857M17 8a3 3 0 11-6 0 3 3 0 016 0zM7 8a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                </div>
                <div class="card-content">
                    <div class="text-2xl font-semibold tracking-tight tabular-nums">12 <span class="text-base font-normal text-muted-foreground">/ 47</span></div>
                    <p class="help-text mt-1">5 responders across 9 POEs</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Badge ──────────────────────────────────────────────────── --}}
    <section class="space-y-4 py-10 border-b">
        <header class="space-y-1">
            <h2 id="badge" class="text-xl font-semibold tracking-tight">Badge</h2>
            <p class="description">For labels, statuses, and counts. Muted by default.</p>
        </header>
        <div class="flex flex-wrap gap-2">
            <span class="badge badge-default">National</span>
            <span class="badge badge-secondary">Verified</span>
            <span class="badge badge-outline">Pending invite</span>
            <span class="badge badge-destructive">Overdue</span>
        </div>
    </section>

    {{-- ── Tabs ───────────────────────────────────────────────────── --}}
    <section class="space-y-4 py-10 border-b">
        <header class="space-y-1">
            <h2 id="tabs" class="text-xl font-semibold tracking-tight">Tabs</h2>
            <p class="description">Switch between related views without losing context.</p>
        </header>

        <div class="w-full max-w-2xl">
            <div class="tabs-list w-full" role="tablist">
                <button role="tab" class="tabs-trigger flex-1" :data-state="tab==='account' ? 'active' : ''" @click="tab='account'">Account</button>
                <button role="tab" class="tabs-trigger flex-1" :data-state="tab==='password' ? 'active' : ''" @click="tab='password'">Password</button>
                <button role="tab" class="tabs-trigger flex-1" :data-state="tab==='devices' ? 'active' : ''" @click="tab='devices'">Devices</button>
            </div>
            <div class="tabs-content" x-show="tab==='account'">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Account</h3>
                        <p class="card-description">Make changes to your account here. Click save when you're done.</p>
                    </div>
                    <div class="card-content space-y-4">
                        <div class="space-y-1.5">
                            <label class="label">Display name</label>
                            <input class="input" value="Timothy Kamukama">
                        </div>
                        <div class="space-y-1.5">
                            <label class="label">Username</label>
                            <input class="input" value="timothy">
                        </div>
                    </div>
                    <div class="card-footer"><button class="btn btn-default">Save changes</button></div>
                </div>
            </div>
            <div class="tabs-content" x-show="tab==='password'" x-cloak>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Password</h3>
                        <p class="card-description">Change your password here. After saving, you'll be signed out.</p>
                    </div>
                    <div class="card-content space-y-4">
                        <div class="space-y-1.5">
                            <label class="label">Current password</label>
                            <input class="input" type="password">
                        </div>
                        <div class="space-y-1.5">
                            <label class="label">New password</label>
                            <input class="input" type="password">
                        </div>
                    </div>
                    <div class="card-footer"><button class="btn btn-default">Save password</button></div>
                </div>
            </div>
            <div class="tabs-content" x-show="tab==='devices'" x-cloak>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Trusted devices</h3>
                        <p class="card-description">These devices can sign in without a second factor.</p>
                    </div>
                    <div class="card-content">
                        <p class="description">You haven't trusted any devices yet.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Alert ──────────────────────────────────────────────────── --}}
    <section class="space-y-4 py-10 border-b">
        <header class="space-y-1">
            <h2 id="alert" class="text-xl font-semibold tracking-tight">Alert</h2>
            <p class="description">Inline notices — quiet by default, destructive when something's wrong.</p>
        </header>

        <div class="alert">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 19a7 7 0 110-14 7 7 0 010 14z"/></svg>
            <div>
                <h4 class="alert-title">A new version is available</h4>
                <div class="alert-description">We've pushed v2.0.0. Refresh to load the latest build.</div>
            </div>
        </div>
        <div class="alert alert-destructive">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 19a7 7 0 110-14 7 7 0 010 14z"/></svg>
            <div>
                <h4 class="alert-title">Your invite link has expired</h4>
                <div class="alert-description">Ask an administrator to send a new invitation.</div>
            </div>
        </div>
    </section>

    {{-- ── Table ──────────────────────────────────────────────────── --}}
    <section class="space-y-4 py-10 border-b">
        <header class="space-y-1">
            <h2 id="table" class="text-xl font-semibold tracking-tight">Table</h2>
            <p class="description">Dense but legible. Hover rows, muted headers, tabular numbers.</p>
        </header>

        <div class="card p-0 overflow-hidden">
            <div class="table-wrap">
                <table class="table">
                    <thead class="table-head">
                        <tr class="table-head-row">
                            <th class="table-head-th">Name</th>
                            <th class="table-head-th">Role</th>
                            <th class="table-head-th">Last seen</th>
                            <th class="table-head-th text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="table-body">
                        <tr class="table-row">
                            <td class="table-cell font-medium">Timothy Kamukama</td>
                            <td class="table-cell description">National administrator</td>
                            <td class="table-cell description tabular-nums">2 min ago</td>
                            <td class="table-cell text-right"><span class="badge badge-secondary">Active</span></td>
                        </tr>
                        <tr class="table-row">
                            <td class="table-cell font-medium">Judith Namugga</td>
                            <td class="table-cell description">District supervisor</td>
                            <td class="table-cell description tabular-nums">Yesterday</td>
                            <td class="table-cell text-right"><span class="badge badge-secondary">Active</span></td>
                        </tr>
                        <tr class="table-row">
                            <td class="table-cell font-medium">Edsell Muhindo</td>
                            <td class="table-cell description">POE coordinator</td>
                            <td class="table-cell description tabular-nums">3 weeks ago</td>
                            <td class="table-cell text-right"><span class="badge badge-outline">Quiet</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ── Dialog + Sheet + Toast demo ───────────────────────────── --}}
    <section class="space-y-4 py-10 border-b">
        <header class="space-y-1">
            <h2 id="overlays" class="text-xl font-semibold tracking-tight">Overlays</h2>
            <p class="description">Dialogs, side sheets, toasts — progressive disclosure without leaving the page.</p>
        </header>

        <div class="card">
            <div class="card-content p-6 flex flex-wrap gap-2">
                <button class="btn btn-outline" @click="dialogOpen=true">Confirm dialog</button>
                <button class="btn btn-outline" @click="closeDialog=true">Close a case (destructive)</button>
                <button class="btn btn-outline" @click="sheetOpen=true">Notifications sheet</button>
                <button class="btn btn-outline" @click="fireToast()">Trigger a toast</button>
            </div>
        </div>
    </section>

    {{-- ── Progress / Skeleton / kbd / Separator ─────────────────── --}}
    <section class="space-y-4 py-10 border-b">
        <header class="space-y-1">
            <h2 id="utility" class="text-xl font-semibold tracking-tight">Progress &middot; Skeleton &middot; Key</h2>
            <p class="description">Utility bits you'll reach for constantly.</p>
        </header>

        <div class="card">
            <div class="card-content p-6 space-y-5">
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="label">Upload progress</span>
                        <span class="description tabular-nums">62%</span>
                    </div>
                    <div class="progress" role="progressbar" aria-valuenow="62" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="transform: translateX(-38%)"></div>
                    </div>
                </div>

                <div class="separator separator-h"></div>

                <div class="space-y-2">
                    <p class="label">Loading state</p>
                    <div class="flex items-center gap-4">
                        <div class="skeleton h-10 w-10 rounded-full"></div>
                        <div class="space-y-2 flex-1">
                            <div class="skeleton h-4 w-[250px]"></div>
                            <div class="skeleton h-4 w-[200px]"></div>
                        </div>
                    </div>
                </div>

                <div class="separator separator-h"></div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="description">Press</span>
                    <span class="kbd">⌘</span>
                    <span class="kbd">K</span>
                    <span class="description">to open the command palette.</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ── End-state composition: the sign-in card ─────────────── --}}
    <section class="space-y-4 py-10 border-b">
        <header class="space-y-1">
            <h2 id="signin" class="text-xl font-semibold tracking-tight">Composition · Sign-in</h2>
            <p class="description">Everything above, composed as the screen users actually see first.</p>
        </header>

        <div class="mx-auto w-full max-w-sm card">
            <div class="card-header space-y-2 text-center">
                <div class="mx-auto flex h-8 w-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h3 class="card-title text-lg">Sign in to PHEOC</h3>
                <p class="card-description">Enter your work email to continue.</p>
            </div>
            <div class="card-content space-y-3">
                <div class="space-y-1.5">
                    <label class="label">Work email</label>
                    <input class="input" type="email" placeholder="you@health.go.ug">
                </div>
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="label">Password</label>
                        <a class="btn btn-link btn-xs px-0" href="#">Forgot?</a>
                    </div>
                    <input class="input" type="password">
                </div>
                <button class="btn btn-default w-full">Sign in</button>
            </div>
            <div class="card-footer justify-center">
                <p class="help-text">Protected by WHO IHR 2005 · session expires in 30 days</p>
            </div>
        </div>
    </section>

    <footer class="pt-10 pb-4 text-center">
        <p class="help-text">Phase 2 foundation · built for mobile-first PHEOC teams.</p>
    </footer>

</main>

{{-- ── Confirm dialog (info) ──────────────────────────────────── --}}
<template x-if="dialogOpen">
    <div>
        <div class="dialog-overlay" @click="dialogOpen=false"></div>
        <div class="dialog-content" role="dialog" aria-modal="true">
            <div class="dialog-header">
                <h3 class="dialog-title">Invite this person to review?</h3>
                <p class="dialog-description">
                    They'll receive an email asking them to look at this case
                    and report back. You'll see their response in the timeline.
                </p>
            </div>
            <div class="dialog-footer">
                <button class="btn btn-ghost" @click="dialogOpen=false">Cancel</button>
                <button class="btn btn-default" @click="dialogOpen=false; fireToast()">Send invitation</button>
            </div>
        </div>
    </div>
</template>

{{-- ── Destructive dialog (close case) ──────────────────────── --}}
<template x-if="closeDialog">
    <div>
        <div class="dialog-overlay" @click="closeDialog=false"></div>
        <div class="dialog-content" role="dialog" aria-modal="true">
            <div class="dialog-header">
                <h3 class="dialog-title">Close this case?</h3>
                <p class="dialog-description">
                    Once closed, the case moves out of the active queue.
                    You can reopen it later if new information arrives.
                </p>
            </div>
            <div class="space-y-1.5">
                <label class="label">Why are you closing this?</label>
                <select class="select">
                    <option>Case resolved through response</option>
                    <option>Not actually a case (false alarm)</option>
                    <option>Duplicate of another case</option>
                    <option>We lost contact with the traveller</option>
                    <option>Traveller left the country</option>
                    <option>Traveller passed away</option>
                </select>
                <p class="help-text">Your choice is recorded in the audit trail.</p>
            </div>
            <div class="dialog-footer">
                <button class="btn btn-ghost" @click="closeDialog=false">Not yet</button>
                <button class="btn btn-destructive" @click="closeDialog=false; fireToast()">Close this case</button>
            </div>
        </div>
    </div>
</template>

{{-- ── Notifications sheet ─────────────────────────────────── --}}
<template x-if="sheetOpen">
    <div>
        <div class="sheet-overlay" @click="sheetOpen=false"></div>
        <div class="sheet-content sheet-right flex flex-col" role="dialog" aria-modal="true">
            <div class="sheet-header">
                <h3 class="sheet-title">Notifications</h3>
                <p class="sheet-description">What happened while you were away.</p>
            </div>
            <div class="flex-1 overflow-y-auto py-4 space-y-4">
                <div class="flex items-start gap-3">
                    <span class="mt-1.5 h-2 w-2 rounded-full bg-primary shrink-0"></span>
                    <div class="space-y-0.5 min-w-0">
                        <p class="text-sm font-medium">Case #5 was closed</p>
                        <p class="description">2 hours ago &middot; by Master Administrator</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-1.5 h-2 w-2 rounded-full bg-primary shrink-0"></span>
                    <div class="space-y-0.5 min-w-0">
                        <p class="text-sm font-medium">New team member invited</p>
                        <p class="description">Yesterday &middot; ayebare.k.timothy@gmail.com</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-1.5 h-2 w-2 rounded-full bg-muted-foreground shrink-0"></span>
                    <div class="space-y-0.5 min-w-0">
                        <p class="text-sm font-medium">7-1-7 quarterly report generated</p>
                        <p class="description">3 days ago &middot; ready to download</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-1.5 h-2 w-2 rounded-full bg-muted-foreground shrink-0"></span>
                    <div class="space-y-0.5 min-w-0">
                        <p class="text-sm font-medium">New device signed in</p>
                        <p class="description">Last week &middot; Chrome on macOS · Kampala</p>
                    </div>
                </div>
            </div>
            <div class="sheet-footer">
                <button class="btn btn-ghost" @click="sheetOpen=false">Close</button>
                <button class="btn btn-default" @click="sheetOpen=false">Mark all read</button>
            </div>
        </div>
    </div>
</template>

{{-- ── Toast ──────────────────────────────────────────────── --}}
<div x-show="toast" x-cloak x-transition
     class="fixed bottom-4 right-4 z-50 w-[calc(100vw-2rem)] sm:w-[340px]">
    <div class="toast">
        <div class="grid gap-1">
            <div class="toast-title">Invitation sent</div>
            <div class="toast-description">Your teammate will get an email within a minute.</div>
        </div>
        <button class="toast-action" @click="toast=false">Undo</button>
    </div>
</div>

</body>
</html>

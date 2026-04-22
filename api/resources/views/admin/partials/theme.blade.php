{{-- ============================================================================
  SHADCN/UI FOUNDATION LAYER
  ----------------------------------------------------------------------------
  One source of truth for:
    · Tailwind color tokens (mapped to shadcn HSL CSS variables)
    · Base styles (body, focus-visible, reduced motion, scrollbar, safe-area)
    · Component primitives (btn, input, card, badge, tabs, dialog, sheet,
      table, toast, alert, skeleton, label, separator, kbd)

  Include from any Blade head to inherit shadcn look:

      @include('admin.partials.theme')

  Colours, radii, and spacing are pulled verbatim from the default shadcn/ui
  `new-york` style (app/globals.css + tailwind.config.ts on registry.shadcn.com
  as of 2024-10). No decorative colours, no gradients, no glow shadows.

  Works under Tailwind Play CDN via the `<script>tailwind.config</script>`
  + `<style type="text/tailwindcss">` contract.
============================================================================ --}}

{{-- Fonts: Inter (sans) · JetBrains Mono (mono). shadcn/ui default is Inter. --}}
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
<link href="https://fonts.bunny.net/css?family=jetbrains-mono:400,500,600&display=swap" rel="stylesheet">

{{-- Tailwind Play CDN — kept inside the partial so standalone pages
     (login, public auth landings) get the same theme without duplication. --}}
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            container: {
                center: true,
                padding: '1rem',
                screens: { '2xl': '1400px' },
            },
            extend: {
                fontFamily: {
                    sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
                },
                colors: {
                    border:      'hsl(var(--border))',
                    input:       'hsl(var(--input))',
                    ring:        'hsl(var(--ring))',
                    background:  'hsl(var(--background))',
                    foreground:  'hsl(var(--foreground))',
                    primary: {
                        DEFAULT:     'hsl(var(--primary))',
                        foreground:  'hsl(var(--primary-foreground))',
                    },
                    secondary: {
                        DEFAULT:     'hsl(var(--secondary))',
                        foreground:  'hsl(var(--secondary-foreground))',
                    },
                    destructive: {
                        DEFAULT:     'hsl(var(--destructive))',
                        foreground:  'hsl(var(--destructive-foreground))',
                    },
                    muted: {
                        DEFAULT:     'hsl(var(--muted))',
                        foreground:  'hsl(var(--muted-foreground))',
                    },
                    accent: {
                        DEFAULT:     'hsl(var(--accent))',
                        foreground:  'hsl(var(--accent-foreground))',
                    },
                    popover: {
                        DEFAULT:     'hsl(var(--popover))',
                        foreground:  'hsl(var(--popover-foreground))',
                    },
                    card: {
                        DEFAULT:     'hsl(var(--card))',
                        foreground:  'hsl(var(--card-foreground))',
                    },
                },
                borderRadius: {
                    lg:   'var(--radius)',
                    md:  'calc(var(--radius) - 2px)',
                    sm:  'calc(var(--radius) - 4px)',
                },
                keyframes: {
                    'accordion-down': { from: { height: '0' }, to: { height: 'var(--radix-accordion-content-height)' } },
                    'accordion-up':   { from: { height: 'var(--radix-accordion-content-height)' }, to: { height: '0' } },
                },
                animation: {
                    'accordion-down': 'accordion-down 0.2s ease-out',
                    'accordion-up':   'accordion-up 0.2s ease-out',
                },
            },
        },
    };
</script>

<style type="text/tailwindcss">
    @layer base {
        :root {
            --background:            0 0% 100%;
            --foreground:            240 10% 3.9%;
            --card:                  0 0% 100%;
            --card-foreground:       240 10% 3.9%;
            --popover:               0 0% 100%;
            --popover-foreground:    240 10% 3.9%;
            --primary:               240 5.9% 10%;
            --primary-foreground:    0 0% 98%;
            --secondary:             240 4.8% 95.9%;
            --secondary-foreground:  240 5.9% 10%;
            --muted:                 240 4.8% 95.9%;
            --muted-foreground:      240 3.8% 46.1%;
            --accent:                240 4.8% 95.9%;
            --accent-foreground:     240 5.9% 10%;
            --destructive:           0 84.2% 60.2%;
            --destructive-foreground:0 0% 98%;
            --border:                240 5.9% 90%;
            --input:                 240 5.9% 90%;
            --ring:                  240 5.9% 10%;
            --radius:                0.5rem;
        }
        .dark {
            --background:            240 10% 3.9%;
            --foreground:            0 0% 98%;
            --card:                  240 10% 3.9%;
            --card-foreground:       0 0% 98%;
            --popover:               240 10% 3.9%;
            --popover-foreground:    0 0% 98%;
            --primary:               0 0% 98%;
            --primary-foreground:    240 5.9% 10%;
            --secondary:             240 3.7% 15.9%;
            --secondary-foreground:  0 0% 98%;
            --muted:                 240 3.7% 15.9%;
            --muted-foreground:      240 5% 64.9%;
            --accent:                240 3.7% 15.9%;
            --accent-foreground:     0 0% 98%;
            --destructive:           0 62.8% 30.6%;
            --destructive-foreground:0 0% 98%;
            --border:                240 3.7% 15.9%;
            --input:                 240 3.7% 15.9%;
            --ring:                  240 4.9% 83.9%;
        }
        * { @apply border-border; }
        html, body { @apply h-full; }
        body {
            @apply bg-background text-foreground antialiased;
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            font-feature-settings: 'rlig' 1, 'calt' 1;
        }
        [x-cloak] { display: none !important; }
        *:focus-visible {
            @apply outline-none ring-2 ring-ring ring-offset-2 ring-offset-background;
        }
        @media (prefers-reduced-motion: reduce) {
            *, ::before, ::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        /* Safe-area paddings for iOS PWA / notches */
        .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
        .pt-safe { padding-top:    env(safe-area-inset-top); }
        /* Custom scrollbar — unobtrusive on desktop, untouched on mobile */
        .scrollbar-thin::-webkit-scrollbar { width: 6px; height: 6px; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: hsl(var(--muted-foreground) / .3); border-radius: 3px; }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: hsl(var(--muted-foreground) / .6); }
    }

    /* ── Component primitives (shadcn/ui parity) ──────────────────────── */
    @layer components {

        /* Button ── default size is SMALL per project directive (h-9 px-3) */
        .btn {
            @apply inline-flex items-center justify-center whitespace-nowrap
                   rounded-md text-sm font-medium ring-offset-background
                   transition-colors
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2
                   disabled:pointer-events-none disabled:opacity-50
                   h-9 px-3;
        }
        .btn-xs       { @apply h-8 rounded-md px-2.5 text-xs; }
        .btn-icon     { @apply h-9 w-9 p-0; }
        .btn-icon-xs  { @apply h-8 w-8 p-0; }

        .btn-default  { @apply bg-primary text-primary-foreground hover:bg-primary/90; }
        .btn-destructive { @apply bg-destructive text-destructive-foreground hover:bg-destructive/90; }
        .btn-outline  { @apply border border-input bg-background hover:bg-accent hover:text-accent-foreground; }
        .btn-secondary{ @apply bg-secondary text-secondary-foreground hover:bg-secondary/80; }
        .btn-ghost    { @apply hover:bg-accent hover:text-accent-foreground; }
        .btn-link     { @apply text-primary underline-offset-4 hover:underline; }

        /* Input / textarea / select */
        .input {
            @apply flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm
                   transition-colors
                   file:border-0 file:bg-transparent file:text-sm file:font-medium
                   placeholder:text-muted-foreground
                   focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring
                   disabled:cursor-not-allowed disabled:opacity-50;
        }
        .textarea {
            @apply flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm
                   placeholder:text-muted-foreground
                   focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring
                   disabled:cursor-not-allowed disabled:opacity-50;
        }
        .select {
            @apply flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background
                   placeholder:text-muted-foreground
                   focus:outline-none focus:ring-1 focus:ring-ring
                   disabled:cursor-not-allowed disabled:opacity-50
                   [&>span]:line-clamp-1;
        }

        /* Label / help text */
        .label       { @apply text-sm font-medium leading-none; }
        .description { @apply text-sm text-muted-foreground; }
        .help-text   { @apply text-[0.8rem] text-muted-foreground; }

        /* Card */
        .card              { @apply rounded-lg border bg-card text-card-foreground shadow-sm; }
        .card-header       { @apply flex flex-col space-y-1.5 p-6; }
        .card-title        { @apply text-base font-semibold leading-none tracking-tight; }
        .card-description  { @apply text-sm text-muted-foreground; }
        .card-content      { @apply p-6 pt-0; }
        .card-footer       { @apply flex items-center p-6 pt-0; }

        /* Badge */
        .badge {
            @apply inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold
                   transition-colors
                   focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2;
        }
        .badge-default     { @apply border-transparent bg-primary text-primary-foreground hover:bg-primary/80; }
        .badge-secondary   { @apply border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80; }
        .badge-destructive { @apply border-transparent bg-destructive text-destructive-foreground hover:bg-destructive/80; }
        .badge-outline     { @apply text-foreground; }

        /* Separator */
        .separator       { @apply shrink-0 bg-border; }
        .separator-h     { @apply h-[1px] w-full; }
        .separator-v     { @apply h-full w-[1px]; }

        /* Tabs — pairs with x-data toggle on `data-state="active"` */
        .tabs-list {
            @apply inline-flex h-9 items-center justify-center rounded-lg bg-muted p-1 text-muted-foreground;
        }
        .tabs-trigger {
            @apply inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-1 text-sm font-medium ring-offset-background
                   transition-all
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2
                   disabled:pointer-events-none disabled:opacity-50
                   data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow;
        }
        .tabs-content {
            @apply mt-2 ring-offset-background
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2;
        }

        /* Dialog (centered modal) */
        .dialog-overlay {
            @apply fixed inset-0 z-50 bg-black/80;
        }
        .dialog-content {
            @apply fixed left-[50%] top-[50%] z-50 grid w-full max-w-lg translate-x-[-50%] translate-y-[-50%]
                   gap-4 border bg-background p-6 shadow-lg sm:rounded-lg;
        }
        .dialog-header     { @apply flex flex-col space-y-1.5 text-center sm:text-left; }
        .dialog-footer     { @apply flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2; }
        .dialog-title      { @apply text-lg font-semibold leading-none tracking-tight; }
        .dialog-description{ @apply text-sm text-muted-foreground; }
        .dialog-close      { @apply absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2; }

        /* Sheet (side drawer — mobile-first) */
        .sheet-overlay { @apply fixed inset-0 z-50 bg-black/80; }
        .sheet-content {
            @apply fixed z-50 gap-4 bg-background p-6 shadow-lg transition ease-in-out;
        }
        .sheet-right {
            @apply inset-y-0 right-0 h-full w-3/4 border-l sm:max-w-sm;
        }
        .sheet-left {
            @apply inset-y-0 left-0 h-full w-3/4 border-r sm:max-w-sm;
        }
        .sheet-top    { @apply inset-x-0 top-0 border-b; }
        .sheet-bottom { @apply inset-x-0 bottom-0 border-t; }
        .sheet-header { @apply flex flex-col space-y-2 text-center sm:text-left; }
        .sheet-footer { @apply flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2; }
        .sheet-title  { @apply text-base font-semibold text-foreground; }
        .sheet-description { @apply text-sm text-muted-foreground; }

        /* Popover / Dropdown menu surface */
        .popover-content {
            @apply z-50 w-72 rounded-md border bg-popover p-4 text-popover-foreground shadow-md outline-none;
        }
        .dropdown-content {
            @apply z-50 min-w-[8rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md;
        }
        .dropdown-item {
            @apply relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none
                   transition-colors
                   hover:bg-accent hover:text-accent-foreground
                   focus:bg-accent focus:text-accent-foreground
                   data-[disabled]:pointer-events-none data-[disabled]:opacity-50;
        }
        .dropdown-label   { @apply px-2 py-1.5 text-sm font-semibold; }
        .dropdown-separator { @apply -mx-1 my-1 h-px bg-muted; }
        .dropdown-shortcut { @apply ml-auto text-xs tracking-widest opacity-60; }

        /* Table */
        .table-wrap   { @apply w-full overflow-auto; }
        .table        { @apply w-full caption-bottom text-sm; }
        .table-head   { @apply [&_tr]:border-b; }
        .table-head-row { @apply border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted; }
        .table-head-th  { @apply h-10 px-2 text-left align-middle font-medium text-muted-foreground [&:has([role=checkbox])]:pr-0; }
        .table-body   { @apply [&_tr:last-child]:border-0; }
        .table-row    { @apply border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted; }
        .table-cell   { @apply p-2 align-middle [&:has([role=checkbox])]:pr-0; }

        /* Toast — add `group` in markup alongside `.toast` if you need
           group-* children, e.g. <div class="group toast">…</div> */
        .toast {
            @apply pointer-events-auto relative flex w-full items-center justify-between space-x-4 overflow-hidden rounded-md border bg-background p-4 pr-6 shadow-lg transition-all;
        }
        .toast-destructive {
            @apply border-destructive bg-destructive text-destructive-foreground;
        }
        .toast-title       { @apply text-sm font-semibold; }
        .toast-description { @apply text-sm opacity-90; }
        .toast-action      { @apply inline-flex h-8 shrink-0 items-center justify-center rounded-md border bg-transparent px-3 text-xs font-medium ring-offset-background transition-colors hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none disabled:opacity-50; }

        /* Alert (inline content alert — not a modal) */
        .alert {
            @apply relative w-full rounded-lg border px-4 py-3 text-sm
                   [&>svg+div]:translate-y-[-3px] [&>svg]:absolute [&>svg]:left-4 [&>svg]:top-4 [&>svg]:text-foreground
                   [&>svg~*]:pl-7;
        }
        .alert-destructive {
            @apply border-destructive/50 text-destructive [&>svg]:text-destructive;
        }
        .alert-title       { @apply mb-1 font-medium leading-none tracking-tight; }
        .alert-description { @apply text-sm [&_p]:leading-relaxed; }

        /* Skeleton */
        .skeleton { @apply animate-pulse rounded-md bg-muted; }

        /* Progress (bar) */
        .progress {
            @apply relative h-2 w-full overflow-hidden rounded-full bg-secondary;
        }
        .progress-bar { @apply h-full w-full flex-1 bg-primary transition-all; }

        /* Switch — visual only, pair with role=switch + aria-checked.
           Add `peer` in markup alongside `.switch` if you want peer-* siblings. */
        .switch {
            @apply inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent shadow-sm transition-colors
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background
                   disabled:cursor-not-allowed disabled:opacity-50
                   data-[state=checked]:bg-primary data-[state=unchecked]:bg-input;
        }
        .switch-thumb {
            @apply pointer-events-none block h-4 w-4 rounded-full bg-background shadow-lg ring-0 transition-transform
                   data-[state=checked]:translate-x-4 data-[state=unchecked]:translate-x-0;
        }

        /* Keyboard-shortcut chip */
        .kbd {
            @apply pointer-events-none inline-flex h-5 select-none items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium text-muted-foreground opacity-100;
        }

        /* Command palette items */
        .command-list { @apply max-h-[300px] overflow-y-auto overflow-x-hidden; }
        .command-empty { @apply py-6 text-center text-sm; }
        .command-group { @apply overflow-hidden p-1 text-foreground; }
        .command-group-heading { @apply px-2 py-1.5 text-xs font-medium text-muted-foreground; }
        .command-item {
            @apply relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none
                   aria-selected:bg-accent aria-selected:text-accent-foreground
                   data-[disabled]:pointer-events-none data-[disabled]:opacity-50;
        }
        .command-shortcut { @apply ml-auto text-xs tracking-widest text-muted-foreground; }
    }
</style>

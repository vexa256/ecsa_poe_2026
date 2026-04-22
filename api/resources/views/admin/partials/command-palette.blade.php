{{-- ============================================================================
  COMMAND PALETTE (⌘K)
  ----------------------------------------------------------------------------
  Global jump-to. Items are hard-coded placeholders for now — later swap with
  a fuzzy search over routes + recent alerts + users (server-rendered via fetch).
============================================================================ --}}

<div x-show="cmd" x-cloak
     class="fixed inset-0 z-[60]"
     @keydown.escape.window="cmd=false"
     role="dialog" aria-modal="true" aria-label="Command palette">

    {{-- Backdrop --}}
    <div x-show="cmd"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="cmd=false"
         class="absolute inset-0 bg-ink-950/70 backdrop-blur-md"></div>

    {{-- Panel --}}
    <div class="relative h-full w-full flex items-start justify-center px-3 sm:px-6 pt-[10vh] sm:pt-[14vh]">
        <div x-show="cmd"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @click.outside="cmd=false"
             class="w-full max-w-2xl bg-white rounded-2xl shadow-panel-lg border border-ink-100 overflow-hidden"
             x-data="{ q: '' }"
             x-init="$nextTick(() => $refs.cmdInput && $refs.cmdInput.focus())">

            {{-- Input --}}
            <div class="flex items-center gap-3 px-4 h-14 border-b border-ink-100">
                <svg class="h-5 w-5 text-ink-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z"/></svg>
                <input x-ref="cmdInput" x-model="q" type="text"
                       class="flex-1 bg-transparent border-0 outline-none text-[15px] placeholder:text-ink-400 focus:ring-0 p-0"
                       placeholder="Type a command, alert ID, case number, POE name, user…">
                <kbd class="text-[10px] font-mono font-medium px-1.5 py-0.5 rounded bg-ink-100 border border-ink-200 text-ink-500">ESC</kbd>
            </div>

            {{-- Results --}}
            <div class="max-h-[60vh] overflow-y-auto scrollbar-thin py-2">

                <div class="px-4 pt-2 pb-1 text-[10px] uppercase tracking-[0.18em] font-semibold text-ink-400">Quick Actions</div>
                @foreach([
                    ['#','Open a new alert','Compose ALERT record for manual entry','bolt','⌘N'],
                    ['#','Declare PHEIC','National tier escalation · NATIONAL_ADMIN only','exclamation','⌘⇧P'],
                    ['#','Broadcast advisory','Send aggressive template to roster','megaphone','⌘⇧B'],
                    ['#','Generate quarterly 7-1-7 PDF','IHR compliance export','document','⌘⇧R'],
                ] as [$h,$t,$m,$i,$k])
                    <a href="{{ $h }}" class="group flex items-center gap-3 px-4 py-2.5 hover:bg-brand-50 transition">
                        <span class="h-9 w-9 rounded-lg bg-brand-100 text-brand-700 grid place-items-center shrink-0">
                            @if($i==='bolt')<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            @elseif($i==='exclamation')<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            @elseif($i==='megaphone')<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                            @else<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-[13px] font-semibold text-ink-900 truncate">{{ $t }}</div>
                            <div class="text-[11px] text-ink-500 truncate">{{ $m }}</div>
                        </div>
                        <kbd class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-ink-100 border border-ink-200 text-ink-500 shrink-0">{{ $k }}</kbd>
                    </a>
                @endforeach

                <div class="px-4 pt-4 pb-1 text-[10px] uppercase tracking-[0.18em] font-semibold text-ink-400">Navigate</div>
                @foreach([
                    ['#','Command Overview','/admin/dashboard','home'],
                    ['#','Alerts · Hub','/admin/alerts','bell'],
                    ['#','Case Files','/admin/cases','folder'],
                    ['#','7-1-7 Compliance Board','/admin/compliance/717','shield'],
                    ['#','National Intelligence Brief','/admin/intelligence','lightning'],
                    ['#','Communications · Inbox','/admin/comms/inbox','mail'],
                    ['#','Aggregated Reports','/admin/aggregated','grid'],
                    ['#','Users &amp; Roles','/admin/users','users'],
                    ['#','Audit Trail','/admin/audit','clock'],
                ] as [$h,$t,$p,$i])
                    <a href="{{ $h }}" class="flex items-center gap-3 px-4 py-2 hover:bg-ink-50 transition">
                        <span class="h-7 w-7 rounded-md bg-ink-100 text-ink-600 grid place-items-center shrink-0">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </span>
                        <span class="flex-1 text-sm text-ink-800 truncate">{!! $t !!}</span>
                        <span class="font-mono text-[10px] text-ink-400 truncate">{{ $p }}</span>
                    </a>
                @endforeach

                <div class="px-4 pt-4 pb-1 text-[10px] uppercase tracking-[0.18em] font-semibold text-ink-400">Ask Copilot</div>
                <button type="button" @click="cmd=false; copilot=true"
                        class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-brand-50 transition text-left">
                    <span class="h-9 w-9 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 text-white grid place-items-center shrink-0">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[13px] font-semibold text-ink-900" x-text="q ? 'Ask Copilot: “' + q + '”' : 'Ask PHEOC Copilot anything…'"></div>
                        <div class="text-[11px] text-ink-500">Narrate an alert · Rank differentials · Recommend next action</div>
                    </div>
                    <kbd class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-ink-100 border border-ink-200 text-ink-500 shrink-0">⌘J</kbd>
                </button>
            </div>

            {{-- Footer --}}
            <div class="border-t border-ink-100 bg-ink-50 px-4 py-2 text-[11px] text-ink-500 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1"><kbd class="font-mono px-1.5 py-0.5 rounded bg-white border border-ink-200">↑↓</kbd> navigate</span>
                    <span class="inline-flex items-center gap-1"><kbd class="font-mono px-1.5 py-0.5 rounded bg-white border border-ink-200">↵</kbd> select</span>
                </div>
                <span>PHEOC Command Centre</span>
            </div>
        </div>
    </div>
</div>

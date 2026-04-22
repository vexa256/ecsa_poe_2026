{{-- ============================================================================
  PHEOC COPILOT DOCK — AI guidance panel
  ----------------------------------------------------------------------------
  Slides in from the right on ⌘J or sidebar button. Powered by the deterministic
  `App\Services\PheocCopilot` service (to be built). Renders:
    · Live situation brief
    · Suggested next actions (with one-click dispatch)
    · Disease differential / SLA / close-reason suggestions (contextual)
    · Ask-anything prompt box (posts to /admin/copilot/ask)

  Currently shows HARD-CODED demonstration state — the markup & interaction
  model are real; swap the static copy for server-rendered blocks once the
  service lands.
============================================================================ --}}

<div x-show="copilot" x-cloak
     class="fixed inset-0 z-[55]"
     role="dialog" aria-modal="true" aria-label="PHEOC Copilot">

    {{-- Backdrop --}}
    <div x-show="copilot"
         x-transition.opacity
         @click="copilot=false"
         class="absolute inset-0 bg-ink-950/50 backdrop-blur-sm"></div>

    {{-- Panel (slides in from right) --}}
    <aside x-show="copilot"
           x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-200"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="translate-x-full"
           class="absolute inset-y-0 right-0 w-full sm:max-w-md lg:max-w-lg bg-ink-950 text-ink-100 shadow-panel-lg flex flex-col overflow-hidden border-l border-ink-800">

        {{-- Header --}}
        <div class="relative shrink-0 border-b border-ink-800 aurora">
            <div class="px-5 py-4 flex items-start gap-3">
                <span class="relative h-10 w-10 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 grid place-items-center shadow-glow shrink-0">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span class="absolute -top-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-ink-950 animate-pulse-dot"></span>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-white">PHEOC Copilot</h2>
                        <span class="inline-flex items-center gap-1 text-[9px] font-bold uppercase tracking-[0.18em] px-1.5 py-0.5 rounded border border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
                            <span class="h-1 w-1 rounded-full bg-emerald-400"></span>Deterministic
                        </span>
                    </div>
                    <p class="text-[11px] text-ink-400 mt-0.5">IHR-2005 & WHO-AFRO-IDSR rules · Uganda endemic baselines · 42 tracked diseases</p>
                </div>
                <button type="button" @click="copilot=false"
                        class="h-9 w-9 grid place-items-center rounded-lg text-ink-400 hover:text-white hover:bg-ink-800/80 shrink-0"
                        aria-label="Close Copilot">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Context tabs --}}
            <div class="px-5 pb-3 flex gap-1 text-[11px]" x-data="{ tab: 'brief' }">
                @foreach([['brief','Situation Brief'],['actions','Recommended Actions'],['ask','Ask']] as [$id,$label])
                    <button type="button"
                            @click="tab = '{{ $id }}'"
                            :class="tab === '{{ $id }}' ? 'bg-ink-800 text-white border-ink-700' : 'text-ink-400 hover:text-ink-200 border-transparent'"
                            class="px-3 py-1.5 rounded-lg border font-semibold uppercase tracking-wider transition">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto scrollbar-dark px-5 py-5 space-y-5">

            {{-- Live situation brief (static demo) --}}
            <section>
                <div class="flex items-center gap-2 mb-2">
                    <h3 class="text-[11px] uppercase tracking-[0.18em] font-semibold text-ink-400">Live Situation · 22 Apr 2026</h3>
                    <span class="flex-1 h-px bg-ink-800"></span>
                    <span class="text-[10px] font-mono text-emerald-400">updated 14s ago</span>
                </div>
                <div class="rounded-xl border border-ink-800 bg-ink-900/60 p-4 text-[13px] leading-relaxed text-ink-200">
                    <p><span class="font-semibold text-white">3 alerts</span> in <span class="font-semibold text-amber-300">Kasese district</span> exceed the 14-day baseline for <span class="font-semibold text-rose-300">VHF syndromes</span> (baseline × 2.4). Two travellers ex-DRC presented with fever + conjunctival haemorrhage in the last 48 hours; one sample at UVRI pending.</p>
                    <p class="mt-2"><span class="font-semibold text-white">Recommend:</span> declare district-level Marburg watch, pre-position RDT kits at Bwera Hospital (last case 47 days ago), and notify cross-border liaison at Mpondwe POE.</p>
                </div>
            </section>

            {{-- Top 3 suggested actions --}}
            <section>
                <div class="flex items-center gap-2 mb-2">
                    <h3 class="text-[11px] uppercase tracking-[0.18em] font-semibold text-ink-400">Recommended Next Actions</h3>
                    <span class="flex-1 h-px bg-ink-800"></span>
                    <span class="text-[10px] text-ink-500">ranked by urgency</span>
                </div>
                <ul class="space-y-2">
                    @foreach([
                        ['tone'=>'rose','label'=>'Declare district-level Marburg watch','meta'=>'Kasese · NATIONAL_ADMIN · 2 min','rationale'=>'3 VHF alerts > 2× 14-day baseline, cross-border exposure confirmed.'],
                        ['tone'=>'amber','label'=>'Broadcast SILENT_POE to Mpondwe admin','meta'=>'24h zero screenings · L2 escalation','rationale'=>'No primary screening received since 21 Apr 14:22 UTC.'],
                        ['tone'=>'brand','label'=>'Request UVRI accelerate lab EB-2026-0419','meta'=>'Sample pending 34h · SLA 72h','rationale'=>'Confirmatory PCR blocks closure of ALERT-2026-0891.'],
                    ] as $a)
                        <li>
                            <button type="button" class="w-full text-left group rounded-xl border border-ink-800 bg-ink-900/50 hover:bg-ink-800/80 hover:border-brand-500/40 p-3 transition">
                                <div class="flex items-start gap-3">
                                    <span class="mt-1 h-2 w-2 rounded-full shrink-0 {{ ['rose'=>'bg-rose-400','amber'=>'bg-amber-400','brand'=>'bg-brand-400'][$a['tone']] }} animate-pulse-dot"></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-semibold text-white">{{ $a['label'] }}</div>
                                        <div class="text-[11px] text-ink-400 mt-0.5">{{ $a['meta'] }}</div>
                                        <div class="text-[12px] text-ink-300 mt-1.5">{{ $a['rationale'] }}</div>
                                    </div>
                                    <svg class="h-4 w-4 text-ink-500 group-hover:text-brand-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </div>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Quick prompts --}}
            <section>
                <div class="flex items-center gap-2 mb-2">
                    <h3 class="text-[11px] uppercase tracking-[0.18em] font-semibold text-ink-400">Quick Prompts</h3>
                    <span class="flex-1 h-px bg-ink-800"></span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach([
                        'Narrate the last 24h at Entebbe',
                        'Rank differentials for ALERT-2026-0891',
                        'Suggest close reason for ALERT-2026-0865',
                        'Draft escalation note for Kasese cluster',
                        'List silent POEs > 24h',
                        'Explain 7-1-7 breach for this week',
                    ] as $p)
                        <button type="button" class="text-left text-[12px] text-ink-300 hover:text-white rounded-lg border border-ink-800 bg-ink-900/40 hover:bg-ink-800/70 px-3 py-2 transition truncate">
                            <span class="text-brand-400">›</span> {{ $p }}
                        </button>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Composer --}}
        <form action="#" method="POST" class="shrink-0 border-t border-ink-800 bg-ink-950 p-3 pb-safe">
            @csrf
            <div class="relative rounded-xl border border-ink-800 bg-ink-900 focus-within:border-brand-500/60 focus-within:ring-2 focus-within:ring-brand-500/30 transition">
                <textarea name="prompt" rows="2"
                          class="w-full bg-transparent border-0 outline-none text-sm text-ink-100 placeholder:text-ink-500 resize-none p-3 pr-24 focus:ring-0"
                          placeholder="Ask Copilot — e.g. 'Walk me through ALERT-2026-0891'"></textarea>
                <div class="absolute right-2 bottom-2 flex items-center gap-1">
                    <button type="button" class="h-8 w-8 grid place-items-center rounded-lg text-ink-500 hover:text-ink-200 hover:bg-ink-800" aria-label="Attach context">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </button>
                    <button type="submit" class="h-8 px-3 inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 hover:from-brand-400 hover:to-brand-600 text-white text-xs font-semibold shadow-glow">
                        Send
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </div>
            </div>
            <div class="mt-2 px-1 flex items-center justify-between text-[10px] text-ink-500">
                <span>Responses are deterministic · grounded in DiseaseIntel + IntelligenceEngine</span>
                <span>⌘J to toggle · ESC to close</span>
            </div>
        </form>
    </aside>
</div>

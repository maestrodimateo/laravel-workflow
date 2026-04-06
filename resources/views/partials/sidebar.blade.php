{{-- ============================================================
     RIGHT SIDEBAR — Basket details, transitions
     ============================================================ --}}
<aside x-show="sel" x-cloak x-transition.opacity
       class="w-80 bg-card border-l border-border overflow-y-auto shrink-0">
    <template x-if="sel">
        <div class="fade-in">

            {{-- Header --}}
            <div class="px-4 py-3 border-b border-border flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-2.5 h-2.5 rounded-full" :style="'background:' + color(sel.color)"></div>
                    <span class="font-semibold text-foreground text-sm" x-text="sel.name"></span>
                </div>
                <button @click="sel=null;$nextTick(()=>drawEdges())" class="sh-btn sh-btn-ghost h-7 w-7 p-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Info --}}
            <div class="px-4 py-3 space-y-3 text-sm">
                <div>
                    <label class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Statut</label>
                    <p class="font-mono text-foreground text-xs mt-0.5" x-text="sel.status"></p>
                </div>
                <div>
                    <label class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Rôles</label>
                    <div class="flex flex-wrap gap-1 mt-1" x-show="(sel.roles || []).length">
                        <template x-for="r in (sel.roles || [])" :key="r">
                            <span class="sh-badge text-[10px]" x-text="r"></span>
                        </template>
                    </div>
                    <p x-show="!(sel.roles || []).length" class="text-muted-foreground text-xs mt-0.5">Aucun</p>
                </div>
            </div>

            {{-- Transitions --}}
            <div class="px-4 py-3 border-t border-border">
                <h4 class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-2">Transitions</h4>

                <div class="space-y-1.5" x-show="(sel.next || []).length">
                    <template x-for="n in (sel.next || [])" :key="n.id">
                        <div class="flex items-center justify-between bg-muted rounded-md px-3 py-1.5">
                            <button @click="openTransitionConfig(sel, n)" class="flex items-center gap-2 text-left flex-1 min-w-0">
                                <div class="w-2 h-2 rounded-full shrink-0" :style="'background:' + color(n.color)"></div>
                                <span class="text-xs text-foreground truncate" x-text="n.name"></span>
                                <span x-show="n.pivot?.label" class="text-[10px] text-muted-foreground italic truncate" x-text="n.pivot?.label"></span>
                                <span x-show="parseActions(n.pivot?.actions).length"
                                      class="sh-badge text-[9px] px-1 py-0 shrink-0"
                                      x-text="parseActions(n.pivot?.actions).length + ' action(s)'"></span>
                            </button>
                            <div class="flex items-center gap-0.5 shrink-0 ml-1">
                                <button @click="openTransitionConfig(sel, n)" class="sh-btn sh-btn-ghost h-6 w-6 p-0" title="Configurer">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                                <button @click="removeLink(sel, n)" class="sh-btn sh-btn-ghost h-6 w-6 p-0 hover:!text-destructive">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <p x-show="!(sel.next || []).length" class="text-xs text-muted-foreground">Aucune — étape finale</p>

                <div class="mt-2 flex gap-1.5" x-show="availTargets.length">
                    <select x-model="linkTarget" class="sh-input flex-1 h-7 text-xs">
                        <option value="">Ajouter...</option>
                        <template x-for="t in availTargets" :key="t.id">
                            <option :value="t.id" x-text="t.name"></option>
                        </template>
                    </select>
                    <button x-show="linkTarget" @click="addLink()" class="sh-btn sh-btn-primary h-7 text-xs px-2">OK</button>
                </div>
            </div>

        </div>
    </template>
</aside>

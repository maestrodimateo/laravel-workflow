{{-- ============================================================
     RIGHT SIDEBAR — Basket details, transitions, messages
     ============================================================ --}}
<aside x-show="sel" x-cloak x-transition.opacity
       class="w-80 bg-white dark:bg-gray-900 border-l dark:border-gray-800 overflow-y-auto shrink-0 transition-colors">
    <template x-if="sel">
        <div class="fade-in">

            {{-- Header --}}
            <div class="px-5 py-4 border-b dark:border-gray-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full" :style="'background:' + color(sel.color)"></div>
                    <span class="font-semibold text-gray-900 dark:text-white text-sm" x-text="sel.name"></span>
                </div>
                <button @click="sel=null;$nextTick(()=>drawEdges())" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Info --}}
            <div class="px-5 py-4 space-y-3 text-sm">
                <div>
                    <label class="text-[10px] font-medium text-gray-400 uppercase">Statut</label>
                    <p class="font-mono text-gray-800 dark:text-gray-200" x-text="sel.status"></p>
                </div>
                <div>
                    <label class="text-[10px] font-medium text-gray-400 uppercase">Rôles</label>
                    <div class="flex flex-wrap gap-1 mt-1" x-show="(sel.roles || []).length">
                        <template x-for="r in (sel.roles || [])" :key="r">
                            <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-xs rounded-md font-medium" x-text="r"></span>
                        </template>
                    </div>
                    <p x-show="!(sel.roles || []).length" class="text-gray-400 text-xs italic">Aucun</p>
                </div>
            </div>

            {{-- Transitions --}}
            <div class="px-5 py-4 border-t dark:border-gray-800">
                <h4 class="text-[10px] font-medium text-gray-400 uppercase mb-2">Transitions</h4>

                <div class="space-y-1.5" x-show="(sel.next || []).length">
                    <template x-for="n in (sel.next || [])" :key="n.id">
                        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-1.5">
                            <button @click="openTransitionConfig(sel, n)" class="flex items-center gap-2 text-left flex-1">
                                <div class="w-2 h-2 rounded-full" :style="'background:' + color(n.color)"></div>
                                <span class="text-xs text-gray-700 dark:text-gray-300" x-text="n.name"></span>
                                <span x-show="n.pivot?.label" class="text-[10px] text-indigo-500 italic" x-text="n.pivot?.label"></span>
                                <span x-show="parseActions(n.pivot?.actions).length"
                                      class="text-[10px] bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-300 px-1.5 rounded-full"
                                      x-text="parseActions(n.pivot?.actions).length + ' action(s)'"></span>
                            </button>
                            <div class="flex items-center gap-1 shrink-0">
                                <button @click="openTransitionConfig(sel, n)" class="text-gray-400 hover:text-indigo-600" title="Configurer">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                                <button @click="removeLink(sel, n)" class="text-gray-400 hover:text-red-500">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <p x-show="!(sel.next || []).length" class="text-xs text-gray-400 italic">Aucune — étape finale</p>

                {{-- Add transition from sidebar --}}
                <div class="mt-2 flex gap-1.5" x-show="availTargets.length">
                    <select x-model="linkTarget" class="flex-1 text-xs border dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg px-2 py-1.5">
                        <option value="">Ajouter...</option>
                        <template x-for="t in availTargets" :key="t.id">
                            <option :value="t.id" x-text="t.name"></option>
                        </template>
                    </select>
                    <button x-show="linkTarget" @click="addLink()"
                            class="px-2.5 py-1.5 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-lg hover:bg-indigo-100">OK</button>
                </div>
            </div>

        </div>
    </template>
</aside>

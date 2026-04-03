{{-- ============================================================
     CANVAS TOOLBAR — Linking mode, zoom, layout, add basket
     ============================================================ --}}
<div x-show="circuit" x-cloak
     class="px-5 py-3 bg-white dark:bg-gray-900 border-b dark:border-gray-800 flex items-center justify-between shrink-0 transition-colors">

    {{-- Left: circuit info --}}
    <div>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-200" x-text="circuit?.name"></span>
        <span class="text-xs text-gray-400 dark:text-gray-500 ml-2 font-mono" x-text="circuit?.targetModel"></span>
    </div>

    {{-- Right: controls --}}
    <div class="flex items-center gap-2">
        {{-- Linking mode indicator --}}
        <span x-show="linking" x-cloak
              class="text-xs text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full font-medium flex items-center gap-1.5 animate-pulse">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            Cliquez sur un panier cible —
            <button @click="linking=null" class="underline">Annuler</button>
        </span>

        {{-- Zoom controls --}}
        <div class="flex items-center border dark:border-gray-700 rounded-lg overflow-hidden">
            <button @click="setZoom(zoom-0.1)" class="px-2 py-1.5 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 text-xs" title="Zoom -">-</button>
            <span class="px-2 py-1.5 text-[10px] text-gray-500 dark:text-gray-400 font-mono bg-white dark:bg-gray-800 min-w-[40px] text-center" x-text="Math.round(zoom*100)+'%'"></span>
            <button @click="setZoom(zoom+0.1)" class="px-2 py-1.5 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 text-xs" title="Zoom +">+</button>
        </div>
        <button @click="setZoom(1)" class="px-2 py-1.5 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-[10px] border dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700" title="Reset zoom">1:1</button>

        {{-- Auto-layout --}}
        <button @click="autoLayout()"
                class="px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-medium border dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-1.5" title="Réorganiser">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Réorganiser
        </button>

        {{-- Messages --}}
        <button @click="showMessages = !showMessages"
                :class="showMessages ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
                class="px-3 py-1.5 text-xs font-medium border dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-1.5 relative">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Messages
            <span x-show="circuitMessages.length" class="ml-0.5 px-1.5 py-0.5 bg-indigo-100 dark:bg-indigo-800 text-indigo-700 dark:text-indigo-300 text-[10px] rounded-full" x-text="circuitMessages.length"></span>
        </button>

        {{-- Add basket --}}
        <button @click="openBasketModal()"
                class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Ajouter
        </button>
    </div>
</div>

{{-- Messages panel (slides down under toolbar) --}}
<div x-show="showMessages && circuit" x-cloak
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
     class="px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-b dark:border-gray-800 shrink-0">
    <div class="flex items-center justify-between mb-2">
        <h4 class="text-xs font-semibold text-gray-600 dark:text-gray-300">Messages du circuit</h4>
        <button @click="openMsgModal()" class="text-xs text-indigo-600 dark:text-indigo-400 font-medium hover:text-indigo-800 flex items-center gap-1">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau
        </button>
    </div>
    <div class="flex flex-wrap gap-2" x-show="circuitMessages.length">
        <template x-for="m in circuitMessages" :key="m.id">
            <div class="flex items-center gap-2 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-lg px-3 py-1.5 text-xs">
                <span class="px-1.5 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 text-[10px] font-medium" x-text="m.type"></span>
                <span class="text-gray-800 dark:text-gray-200 font-medium" x-text="m.subject"></span>
                <button @click="deleteMsg(m)" class="text-gray-400 hover:text-red-500 ml-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>
    <p x-show="!circuitMessages.length" class="text-xs text-gray-400 italic">Aucun message — les messages sont utilisables dans les actions des transitions.</p>
</div>

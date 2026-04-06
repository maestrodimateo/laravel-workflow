{{-- ============================================================
     CANVAS TOOLBAR — Linking mode, zoom, layout, messages, add
     ============================================================ --}}
<div x-show="circuit" x-cloak
     class="px-4 py-2.5 bg-card border-b border-border flex items-center justify-between shrink-0">

    <div class="flex items-center gap-2">
        <span class="text-sm font-medium text-foreground" x-text="circuit?.name"></span>
        <span class="text-xs text-muted-foreground font-mono" x-text="circuit?.targetModel"></span>
    </div>

    <div class="flex items-center gap-1.5">
        {{-- Linking indicator --}}
        <span x-show="linking" x-cloak class="sh-badge text-xs animate-pulse">
            <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            Cliquez sur un panier cible —
            <button @click="linking=null" class="underline ml-1">Annuler</button>
        </span>

        {{-- Zoom --}}
        <div class="flex items-center border border-border rounded-md overflow-hidden h-8">
            <button @click="setZoom(zoom-0.1)" class="px-2 h-full text-muted-foreground hover:bg-accent text-xs">-</button>
            <span class="px-2 h-full flex items-center text-[10px] text-muted-foreground font-mono bg-card min-w-[40px] justify-center border-x border-border" x-text="Math.round(zoom*100)+'%'"></span>
            <button @click="setZoom(zoom+0.1)" class="px-2 h-full text-muted-foreground hover:bg-accent text-xs">+</button>
        </div>
        <button @click="setZoom(1)" class="sh-btn sh-btn-outline h-8 text-[10px] px-2">1:1</button>

        <button @click="autoLayout()" class="sh-btn sh-btn-outline h-8 text-xs">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Réorganiser
        </button>

        {{-- Messages --}}
        <button @click="showMessages = !showMessages"
                :class="showMessages ? 'sh-btn-primary' : 'sh-btn-outline'"
                class="sh-btn h-8 text-xs relative">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Messages
            <span x-show="circuitMessages.length" class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-foreground/10" x-text="circuitMessages.length"></span>
        </button>

        {{-- Add basket --}}
        <button @click="openBasketModal()" class="sh-btn sh-btn-primary h-8 text-xs">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Ajouter
        </button>
    </div>
</div>

{{-- Messages panel --}}
<div x-show="showMessages && circuit" x-cloak
     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
     class="px-4 py-3 bg-muted border-b border-border shrink-0">
    <div class="flex items-center justify-between mb-2">
        <h4 class="text-xs font-semibold text-foreground">Messages du circuit</h4>
        <button @click="openMsgModal()" class="sh-btn sh-btn-outline h-7 text-xs px-2">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau
        </button>
    </div>
    <div class="flex flex-wrap gap-2" x-show="circuitMessages.length">
        <template x-for="m in circuitMessages" :key="m.id">
            <div class="flex items-center gap-2 bg-card border border-border rounded-md px-3 py-1.5 text-xs">
                <span class="sh-badge text-[10px]" x-text="m.type"></span>
                <span class="text-foreground font-medium" x-text="m.subject"></span>
                <button @click="deleteMsg(m)" class="text-muted-foreground hover:text-destructive ml-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>
    <p x-show="!circuitMessages.length" class="text-xs text-muted-foreground">Aucun message — les messages sont utilisables dans les actions des transitions.</p>
</div>

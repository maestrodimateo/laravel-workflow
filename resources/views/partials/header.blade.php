{{-- ============================================================
     TOP BAR — Circuit selector, actions, dark mode toggle
     ============================================================ --}}
<header class="bg-card border-b border-border h-14 px-4 flex items-center justify-between relative z-30 shrink-0">

    {{-- Left: Logo + Circuit dropdown --}}
    <div class="flex items-center gap-3">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z"/>
            </svg>
            <span class="font-semibold text-sm text-foreground tracking-tight">Workflow Designer</span>
        </div>

        <div class="h-4 w-px bg-border mx-1"></div>

        {{-- Circuit dropdown --}}
        <div class="relative" x-data="{open:false}">
            <button @click="open=!open" class="sh-btn sh-btn-outline text-xs h-8 max-w-[320px]">
                <span x-text="circuit ? circuit.name : 'Choisir un circuit'" class="truncate"></span>
                <svg class="w-3.5 h-3.5 text-muted-foreground shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="open" @click.away="open=false" x-cloak
                 class="absolute left-0 top-full mt-1 w-80 bg-card rounded-md border border-border shadow-md py-1 z-50">
                <template x-for="c in circuits" :key="c.id">
                    <button @click="pick(c);open=false"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-accent flex items-center justify-between">
                        <span x-text="c.name" class="font-medium text-card-foreground"></span>
                        <svg x-show="circuit?.id===c.id" class="w-4 h-4 text-foreground shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </button>
                </template>

                <div class="border-t border-border mt-1 pt-1">
                    <button @click="openCircuitModal();open=false"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-accent font-medium flex items-center gap-2 text-card-foreground">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Nouveau circuit
                    </button>
                    <button @click="$refs.importInput.click();open=false"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-accent font-medium flex items-center gap-2 text-muted-foreground">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Importer
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: Circuit actions + Dark mode --}}
    <div class="flex items-center gap-0.5">
        <div x-show="circuit" x-cloak class="flex items-center gap-0.5">
            <button @click="editCircuit()" class="sh-btn sh-btn-ghost h-8 w-8 p-0" title="Modifier">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            </button>
            <button @click="exportCircuit()" class="sh-btn sh-btn-ghost h-8 w-8 p-0" title="Exporter JSON">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            </button>
            <button @click="exportImage()" class="sh-btn sh-btn-ghost h-8 w-8 p-0" title="Exporter PNG">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </button>
            <button @click="deleteCircuit()" class="sh-btn sh-btn-ghost h-8 w-8 p-0 hover:!text-destructive" title="Supprimer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
        </div>

        <div class="h-4 w-px bg-border mx-1"></div>

        <button @click="toggleDark()" class="sh-btn sh-btn-ghost h-8 w-8 p-0" title="Mode sombre/clair">
            <svg x-show="!dark" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            <svg x-show="dark" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </button>
    </div>
</header>

{{-- ============================================================
     MODAL: Transition — Configure label & actions on a link
     ============================================================ --}}
<template x-teleport="body">
<div x-show="modal==='transition'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition.opacity>
    <div class="fixed inset-0 bg-black/50" @click="modal=null"></div>
    <div class="bg-card border border-border rounded-lg shadow-lg w-full max-w-lg mx-4 relative z-10 fade-in">
        <div class="px-6 py-4 border-b border-border">
            <h3 class="text-base font-semibold text-foreground">Configurer la transition</h3>
            <p class="text-xs text-muted-foreground mt-0.5" x-show="tConfig.from && tConfig.to">
                <span x-text="tConfig.from?.name"></span>
                <span class="mx-1">&rarr;</span>
                <span x-text="tConfig.to?.name"></span>
            </p>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="text-sm font-medium text-foreground mb-1.5 block">Label (optionnel)</label>
                <input x-model="tConfig.label" class="sh-input w-full" placeholder="Ex: Approuver, Rejeter...">
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-foreground">Actions</label>
                    <div class="relative" x-data="{addOpen: false}">
                        <button @click="addOpen=!addOpen" class="sh-btn sh-btn-outline h-7 text-xs px-2">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Ajouter
                        </button>
                        <div x-show="addOpen" @click.away="addOpen=false" x-cloak
                             class="absolute right-0 top-full mt-1 w-48 bg-card rounded-md border border-border shadow-md py-1 z-50">
                            <template x-for="a in availableActions" :key="a.key">
                                <button @click="addTransitionAction(a.key);addOpen=false"
                                        class="w-full text-left px-3 py-1.5 text-sm hover:bg-accent text-foreground" x-text="a.label"></button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="space-y-2" x-show="tConfig.actions.length">
                    <template x-for="(action, i) in tConfig.actions" :key="i">
                        <div class="bg-muted rounded-md px-4 py-3 border border-border">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-foreground" x-text="actionLabel(action.type)"></span>
                                <button @click="tConfig.actions.splice(i,1)" class="sh-btn sh-btn-ghost h-6 w-6 p-0 hover:!text-destructive">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <template x-if="action.type === 'webhook'">
                                <input x-model="action.config.url" class="sh-input w-full h-7 text-xs" placeholder="https://...">
                            </template>
                            <template x-if="action.type === 'log'">
                                <input x-model="action.config.message" class="sh-input w-full h-7 text-xs" placeholder="Message de log (optionnel)">
                            </template>
                            <template x-if="action.type === 'send_email'">
                                <select x-model="action.config.message_id" class="sh-input w-full h-7 text-xs">
                                    <option value="">Choisir un message...</option>
                                    <template x-for="m in circuitMessages" :key="m.id"><option :value="m.id" x-text="m.subject"></option></template>
                                </select>
                            </template>
                            <template x-if="action.type === 'require_document'">
                                <div x-init="if(!action.config.documents) action.config.documents = []">
                                    {{-- List of required documents --}}
                                    <div class="space-y-1.5 mb-2" x-show="action.config.documents?.length">
                                        <template x-for="(doc, di) in action.config.documents" :key="di">
                                            <div class="flex items-center gap-2">
                                                <input x-model="doc.type" class="sh-input h-7 text-xs flex-1" placeholder="Type (ex: piece_identite)">
                                                <input x-model="doc.label" class="sh-input h-7 text-xs flex-[2]" placeholder="Label (ex: Pièce d'identité)">
                                                <button @click="action.config.documents.splice(di,1)" class="sh-btn sh-btn-ghost h-7 w-7 p-0 shrink-0 hover:!text-destructive">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    <button @click="action.config.documents.push({type:'',label:''})" class="sh-btn sh-btn-outline h-7 text-xs w-full">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Ajouter un document requis
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
                <p x-show="!tConfig.actions.length" class="text-xs text-muted-foreground">Aucune action configurée.</p>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button @click="modal=null" class="sh-btn sh-btn-outline h-9">Annuler</button>
                <button @click="saveTransitionConfig()" :disabled="busy" class="sh-btn sh-btn-primary h-9 disabled:opacity-50"><span x-text="busy ? '...' : 'Enregistrer'"></span></button>
            </div>
        </div>
    </div>
</div>
</template>

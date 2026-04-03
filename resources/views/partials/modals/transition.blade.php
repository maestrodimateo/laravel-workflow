{{-- ============================================================
     MODAL: Transition — Configure label & actions on a link
     ============================================================ --}}
<template x-teleport="body">
<div x-show="modal==='transition'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition.opacity>
    <div class="fixed inset-0 bg-black/40" @click="modal=null"></div>
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-lg mx-4 relative z-10 fade-in">
        <div class="px-6 pt-5 pb-3 border-b dark:border-gray-800">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Configurer la transition</h3>
            <p class="text-xs text-gray-400 mt-1" x-show="tConfig.from && tConfig.to">
                <span x-text="tConfig.from?.name"></span>
                <span class="mx-1">&rarr;</span>
                <span x-text="tConfig.to?.name"></span>
            </p>
        </div>
        <div class="p-6 space-y-4">
            {{-- Label --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Label (optionnel)</label>
                <input x-model="tConfig.label" class="w-full border dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 text-sm" placeholder="Ex: Approuver, Rejeter...">
            </div>

            {{-- Actions --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Actions</label>
                    <div class="relative" x-data="{addOpen: false}">
                        <button @click="addOpen=!addOpen" class="text-xs text-indigo-600 font-medium hover:text-indigo-800 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Ajouter
                        </button>
                        <div x-show="addOpen" @click.away="addOpen=false" x-cloak
                             class="absolute right-0 top-full mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-xl border dark:border-gray-700 py-1 z-50">
                            <template x-for="a in availableActions" :key="a.key">
                                <button @click="addTransitionAction(a.key);addOpen=false"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300"
                                        x-text="a.label"></button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="space-y-2" x-show="tConfig.actions.length">
                    <template x-for="(action, i) in tConfig.actions" :key="i">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg px-4 py-3 border dark:border-gray-700">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400" x-text="actionLabel(action.type)"></span>
                                <button @click="tConfig.actions.splice(i,1)" class="text-gray-400 hover:text-red-500">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            {{-- Config per action type --}}
                            <template x-if="action.type === 'webhook'">
                                <input x-model="action.config.url" class="w-full border dark:border-gray-700 dark:bg-gray-900 dark:text-white rounded px-2 py-1 text-xs" placeholder="https://...">
                            </template>
                            <template x-if="action.type === 'log'">
                                <input x-model="action.config.message" class="w-full border dark:border-gray-700 dark:bg-gray-900 dark:text-white rounded px-2 py-1 text-xs" placeholder="Message de log (optionnel)">
                            </template>
                            <template x-if="action.type === 'send_email'">
                                <select x-model="action.config.message_id" class="w-full border dark:border-gray-700 dark:bg-gray-900 dark:text-white rounded px-2 py-1 text-xs">
                                    <option value="">Choisir un message...</option>
                                    <template x-for="m in circuitMessages" :key="m.id"><option :value="m.id" x-text="m.subject"></option></template>
                                </select>
                            </template>
                        </div>
                    </template>
                </div>
                <p x-show="!tConfig.actions.length" class="text-xs text-gray-400 italic">Aucune action — cette transition ne déclenchera rien de spécial.</p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button @click="modal=null" class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">Annuler</button>
                <button @click="saveTransitionConfig()" :disabled="busy" class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    <span x-text="busy ? '...' : 'Enregistrer'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
</template>

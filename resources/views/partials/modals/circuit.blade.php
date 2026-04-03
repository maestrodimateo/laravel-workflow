{{-- ============================================================
     MODAL: Circuit — Create / Edit circuit
     ============================================================ --}}
<template x-teleport="body">
<div x-show="modal==='circuit'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition.opacity>
    <div class="fixed inset-0 bg-black/40" @click="modal=null"></div>
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md mx-4 relative z-10 fade-in">
        <div class="px-6 pt-5 pb-3 border-b dark:border-gray-800">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="editId ? 'Modifier le circuit' : 'Nouveau circuit'"></h3>
        </div>
        <form @submit.prevent="saveCircuit()" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom</label>
                <input x-model="cForm.name" required class="w-full border dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Ex: Validation factures">
                <p x-show="errs.name" x-text="errs.name" class="text-red-500 text-xs mt-1"></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Modèle cible</label>
                <input x-model="cForm.targetModel" required class="w-full border rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="App\Models\Invoice">
                <p x-show="errs.targetModel" x-text="errs.targetModel" class="text-red-500 text-xs mt-1"></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                <textarea x-model="cForm.description" rows="2" class="w-full border dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Optionnel"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rôles autorisés</label>
                <div class="flex flex-wrap gap-1 mb-2 min-h-[24px]">
                    <template x-for="(r,i) in cForm.roles" :key="r">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-md">
                            <span x-text="r"></span>
                            <button type="button" @click="cForm.roles.splice(i,1)" class="hover:text-red-500">&times;</button>
                        </span>
                    </template>
                    <span x-show="!cForm.roles.length" class="text-xs text-gray-400 italic">Aucun</span>
                </div>
                <div class="flex gap-2">
                    <input x-ref="crI" type="text" placeholder="admin, manager..." @keydown.enter.prevent="addCR()" class="flex-1 border dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500">
                    <button type="button" @click="addCR()" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700">+</button>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="modal=null" class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">Annuler</button>
                <button type="submit" :disabled="busy" class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    <span x-text="busy ? '...' : editId ? 'Enregistrer' : 'Créer'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
</template>

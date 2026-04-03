{{-- ============================================================
     MODAL: Basket — Create / Edit basket
     ============================================================ --}}
<template x-teleport="body">
<div x-show="modal==='basket'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition.opacity>
    <div class="fixed inset-0 bg-black/40" @click="modal=null"></div>
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md mx-4 relative z-10 fade-in">
        <div class="px-6 pt-5 pb-3 border-b dark:border-gray-800">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="editId ? 'Modifier le panier' : 'Nouveau panier'"></h3>
        </div>
        <form @submit.prevent="saveBasket()" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom</label>
                <input x-model="bForm.name" required class="w-full border dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 text-sm" placeholder="Ex: En révision">
                <p x-show="errs.name" x-text="errs.name" class="text-red-500 text-xs mt-1"></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Statut</label>
                <input x-model="bForm.status" required class="w-full border dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 text-sm font-mono uppercase" placeholder="REVIEW">
                <p x-show="errs.status" x-text="errs.status" class="text-red-500 text-xs mt-1"></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Couleur</label>
                <div class="flex flex-wrap gap-2">
                    <template x-for="c in colors" :key="c.value">
                        <button type="button" @click="bForm.color=c.value"
                                :class="{'ring-2 ring-offset-2 ring-gray-400 scale-110': bForm.color === c.value}"
                                class="w-7 h-7 rounded-lg transition hover:scale-110"
                                :style="'background:' + c.value" :title="c.name"></button>
                    </template>
                </div>
            </div>
            <div x-show="circuitRoles.length">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rôles autorisés</label>
                <div class="space-y-1 max-h-32 overflow-y-auto border dark:border-gray-700 rounded-lg p-2">
                    <template x-for="r in circuitRoles" :key="r">
                        <label class="flex items-center gap-2 px-2 py-1 hover:bg-gray-50 rounded cursor-pointer">
                            <input type="checkbox" :checked="bForm.roles.includes(r)" @change="toggleArr(bForm.roles, r)" class="rounded border-gray-300 text-indigo-600">
                            <span class="text-sm text-gray-700 dark:text-gray-300" x-text="r"></span>
                        </label>
                    </template>
                </div>
            </div>
            <div x-show="baskets.filter(x => editId ? x.id !== editId : true).length">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Panier(s) précédent(s)</label>
                <div class="space-y-1 max-h-32 overflow-y-auto border dark:border-gray-700 rounded-lg p-2">
                    <template x-for="x in baskets.filter(x => editId ? x.id !== editId : true)" :key="x.id">
                        <label class="flex items-center gap-2 px-2 py-1 hover:bg-gray-50 rounded cursor-pointer">
                            <input type="checkbox" :checked="bForm.previous.includes(x.id)" @change="toggleArr(bForm.previous, x.id)" class="rounded border-gray-300 text-indigo-600">
                            <div class="w-2 h-2 rounded-full" :style="'background:' + color(x.color)"></div>
                            <span class="text-sm text-gray-700 dark:text-gray-300" x-text="x.name + ' (' + x.status + ')'"></span>
                        </label>
                    </template>
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

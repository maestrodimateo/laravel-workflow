{{-- ============================================================
     MODAL: Message — Create message with WYSIWYG editor
     ============================================================ --}}
<template x-teleport="body">
<div x-show="modal==='msg'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition.opacity>
    <div class="fixed inset-0 bg-black/40" @click="modal=null"></div>
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-3xl mx-4 relative z-10 fade-in">
        <div class="px-6 pt-5 pb-3 border-b dark:border-gray-800">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Nouveau message</h3>
        </div>
        <form @submit.prevent="saveMsg()" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                    <select x-model="mForm.type" class="w-full border dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 text-sm">
                        <template x-for="t in msgTypes" :key="t.value"><option :value="t.value" x-text="t.name"></option></template>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destinataire</label>
                    <select x-model="mForm.recipient" class="w-full border dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 text-sm">
                        <template x-for="t in recipients" :key="t.value"><option :value="t.value" x-text="t.name"></option></template>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Objet</label>
                <input x-model="mForm.subject" required class="w-full border dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 text-sm" placeholder="Objet">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contenu</label>
                <div x-ref="quillEditor" x-init="initQuill($refs.quillEditor)" style="min-height:120px"></div>
            </div>

            {{-- Variables help panel --}}
            <div x-data="{showVars: false}">
                <button type="button" @click="showVars=!showVars"
                        class="text-xs text-indigo-600 dark:text-indigo-400 font-medium flex items-center gap-1 hover:underline">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    Variables disponibles
                    <svg :class="{'rotate-180': showVars}" class="w-3 h-3 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="showVars" x-cloak class="mt-2 bg-gray-50 dark:bg-gray-800 border dark:border-gray-700 rounded-lg p-3 max-h-40 overflow-y-auto">
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mb-2">Cliquez sur une variable pour l'insérer dans le contenu :</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="(desc, key) in msgVars" :key="key">
                            <button type="button" @click="insertVariable(key)" :title="desc"
                                    class="px-2 py-0.5 bg-indigo-50 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-[11px] font-mono rounded hover:bg-indigo-100 dark:hover:bg-indigo-900 transition">
                                @{{ <span x-text="key"></span> }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="modal=null" class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">Annuler</button>
                <button type="submit" :disabled="busy" class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    <span x-text="busy ? '...' : 'Créer'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
</template>

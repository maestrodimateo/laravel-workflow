{{-- ============================================================
     MODAL: Message — Create message with WYSIWYG editor
     ============================================================ --}}
<template x-teleport="body">
<div x-show="modal==='msg'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition.opacity>
    <div class="fixed inset-0 bg-black/50" @click="modal=null"></div>
    <div class="bg-card border border-border rounded-lg shadow-lg w-full max-w-3xl mx-4 relative z-10 fade-in">
        <div class="px-6 py-4 border-b border-border">
            <h3 class="text-base font-semibold text-foreground">Nouveau message</h3>
            <p class="text-xs text-muted-foreground mt-0.5">Ce message sera disponible pour les actions de transition.</p>
        </div>
        <form @submit.prevent="saveMsg()" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-foreground mb-1.5 block">Type</label>
                    <select x-model="mForm.type" class="sh-input w-full">
                        <template x-for="t in msgTypes" :key="t.value"><option :value="t.value" x-text="t.name"></option></template>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-foreground mb-1.5 block">Destinataire</label>
                    <select x-model="mForm.recipient" class="sh-input w-full">
                        <template x-for="t in recipients" :key="t.value"><option :value="t.value" x-text="t.name"></option></template>
                    </select>
                </div>
            </div>

            <div>
                <label class="text-sm font-medium text-foreground mb-1.5 block">Objet</label>
                <input x-model="mForm.subject" required class="sh-input w-full" placeholder="Objet du message">
            </div>

            <div>
                <label class="text-sm font-medium text-foreground mb-1.5 block">Contenu</label>
                <div x-ref="quillEditor" x-init="initQuill($refs.quillEditor)" style="min-height:120px"></div>
            </div>

            {{-- Variables --}}
            <div x-data="{showVars: false}">
                <button type="button" @click="showVars=!showVars"
                        class="text-xs font-medium text-muted-foreground hover:text-foreground flex items-center gap-1 transition-colors">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    Variables disponibles
                    <svg :class="{'rotate-180': showVars}" class="w-3 h-3 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="showVars" x-cloak class="mt-2 bg-muted border border-border rounded-md p-3 max-h-40 overflow-y-auto">
                    <p class="text-[10px] text-muted-foreground mb-2">Cliquez pour insérer dans le contenu :</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="(desc, key) in msgVars" :key="key">
                            <button type="button" @click="insertVariable(key)" :title="desc"
                                    class="sh-badge text-[10px] font-mono cursor-pointer hover:bg-accent transition-colors">
                                @{{ <span x-text="key"></span> }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="modal=null" class="sh-btn sh-btn-outline h-9">Annuler</button>
                <button type="submit" :disabled="busy" class="sh-btn sh-btn-primary h-9 disabled:opacity-50"><span x-text="busy ? '...' : 'Créer'"></span></button>
            </div>
        </form>
    </div>
</div>
</template>

{{-- ============================================================
     MODAL: Basket — Create / Edit basket
     ============================================================ --}}
<template x-teleport="body">
<div x-show="modal==='basket'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition.opacity>
    <div class="fixed inset-0 bg-black/50" @click="modal=null"></div>
    <div class="bg-card border border-border rounded-lg shadow-lg w-full max-w-2xl mx-4 relative z-10 fade-in">
        <div class="px-6 py-4 border-b border-border">
            <h3 class="text-base font-semibold text-foreground" x-text="editId ? 'Modifier le panier' : 'Nouveau panier'"></h3>
            <p class="text-xs text-muted-foreground mt-0.5">Définissez une étape du workflow.</p>
        </div>
        <form @submit.prevent="saveBasket()" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-foreground mb-1.5 block">Nom</label>
                    <input x-model="bForm.name" required class="sh-input w-full" placeholder="Ex: En révision">
                    <p x-show="errs.name" x-text="errs.name" class="text-destructive text-xs mt-1"></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-foreground mb-1.5 block">Statut</label>
                    <input x-model="bForm.status" required class="sh-input w-full font-mono uppercase" placeholder="REVIEW">
                    <p x-show="errs.status" x-text="errs.status" class="text-destructive text-xs mt-1"></p>
                </div>
            </div>
            <div>
                <label class="text-sm font-medium text-foreground mb-2 block">Couleur</label>
                <div class="flex flex-wrap gap-2">
                    <template x-for="c in colors" :key="c.value">
                        <button type="button" @click="bForm.color=c.value"
                                :class="bForm.color === c.value ? 'ring-2 ring-ring ring-offset-2 ring-offset-card scale-110' : ''"
                                class="w-7 h-7 rounded-md transition-all hover:scale-110"
                                :style="'background:' + c.value" :title="c.name"></button>
                    </template>
                </div>
            </div>
            <div x-show="circuitRoles.length">
                <label class="text-sm font-medium text-foreground mb-1.5 block">Rôles autorisés</label>
                <div class="space-y-1 max-h-32 overflow-y-auto border border-border rounded-md p-2">
                    <template x-for="r in circuitRoles" :key="r">
                        <label class="flex items-center gap-2 px-2 py-1 hover:bg-accent rounded-sm cursor-pointer">
                            <input type="checkbox" :checked="bForm.roles.includes(r)" @change="toggleArr(bForm.roles, r)" class="rounded border-border">
                            <span class="text-sm text-foreground" x-text="r"></span>
                        </label>
                    </template>
                </div>
            </div>
            <div x-show="baskets.filter(x => editId ? x.id !== editId : true).length">
                <label class="text-sm font-medium text-foreground mb-1.5 block">Panier(s) précédent(s)</label>
                <div class="space-y-1 max-h-32 overflow-y-auto border border-border rounded-md p-2">
                    <template x-for="x in baskets.filter(x => editId ? x.id !== editId : true)" :key="x.id">
                        <label class="flex items-center gap-2 px-2 py-1 hover:bg-accent rounded-sm cursor-pointer">
                            <input type="checkbox" :checked="bForm.previous.includes(x.id)" @change="toggleArr(bForm.previous, x.id)" class="rounded border-border">
                            <div class="w-2 h-2 rounded-full shrink-0" :style="'background:' + color(x.color)"></div>
                            <span class="text-sm text-foreground" x-text="x.name + ' (' + x.status + ')'"></span>
                        </label>
                    </template>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="modal=null" class="sh-btn sh-btn-outline h-9">Annuler</button>
                <button type="submit" :disabled="busy" class="sh-btn sh-btn-primary h-9 disabled:opacity-50"><span x-text="busy ? '...' : editId ? 'Enregistrer' : 'Créer'"></span></button>
            </div>
        </form>
    </div>
</div>
</template>

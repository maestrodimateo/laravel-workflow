{{-- ============================================================
     MODAL: Circuit — Create / Edit circuit
     ============================================================ --}}
<template x-teleport="body">
<div x-show="modal==='circuit'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition.opacity>
    <div class="fixed inset-0 bg-black/50" @click="modal=null"></div>
    <div class="bg-card border border-border rounded-lg shadow-lg w-full max-w-md mx-4 relative z-10 fade-in">
        <div class="px-6 py-4 border-b border-border">
            <h3 class="text-base font-semibold text-foreground" x-text="editId ? '{{ __('workflow::workflow.ui.circuit_modal.edit_title') }}' : '{{ __('workflow::workflow.ui.circuit_modal.new_title') }}'"></h3>
            <p class="text-xs text-muted-foreground mt-0.5">{{ __('workflow::workflow.ui.circuit_modal.subtitle') }}</p>
        </div>
        <form @submit.prevent="saveCircuit()" class="p-6 space-y-4">
            <div>
                <label class="text-sm font-medium text-foreground mb-1.5 block">{{ __('workflow::workflow.ui.circuit_modal.name') }}</label>
                <input x-model="cForm.name" required class="sh-input w-full" placeholder="{{ __('workflow::workflow.ui.circuit_modal.name_placeholder') }}">
                <p x-show="errs.name" x-text="errs.name" class="text-destructive text-xs mt-1"></p>
            </div>
            <div>
                <label class="text-sm font-medium text-foreground mb-1.5 block">{{ __('workflow::workflow.ui.circuit_modal.target_model') }}</label>
                <input x-model="cForm.targetModel" required class="sh-input w-full font-mono" placeholder="{{ __('workflow::workflow.ui.circuit_modal.target_model_placeholder') }}">
                <p x-show="errs.targetModel" x-text="errs.targetModel" class="text-destructive text-xs mt-1"></p>
            </div>
            <div>
                <label class="text-sm font-medium text-foreground mb-1.5 block">{{ __('workflow::workflow.ui.circuit_modal.description') }}</label>
                <textarea x-model="cForm.description" rows="2" class="sh-input w-full h-auto" placeholder="{{ __('workflow::workflow.ui.circuit_modal.description_placeholder') }}"></textarea>
            </div>
            <div>
                <label class="text-sm font-medium text-foreground mb-1.5 block">{{ __('workflow::workflow.ui.circuit_modal.allowed_roles') }}</label>
                <div class="flex flex-wrap gap-1 mb-2 min-h-[24px]">
                    <template x-for="(r,i) in cForm.roles" :key="r">
                        <span class="sh-badge text-xs gap-1"><span x-text="r"></span><button type="button" @click="cForm.roles.splice(i,1)" class="hover:text-destructive">&times;</button></span>
                    </template>
                    <span x-show="!cForm.roles.length" class="text-xs text-muted-foreground">{{ __('workflow::workflow.ui.circuit_modal.no_roles') }}</span>
                </div>
                <div class="flex gap-2">
                    <input x-ref="crI" type="text" placeholder="{{ __('workflow::workflow.ui.circuit_modal.add_role_placeholder') }}" @keydown.enter.prevent="addCR()" class="sh-input flex-1">
                    <button type="button" @click="addCR()" class="sh-btn sh-btn-primary h-9 px-3">+</button>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="modal=null" class="sh-btn sh-btn-outline h-9">{{ __('workflow::workflow.ui.buttons.cancel') }}</button>
                <button type="submit" :disabled="busy" class="sh-btn sh-btn-primary h-9 disabled:opacity-50"><span x-text="busy ? '...' : editId ? '{{ __('workflow::workflow.ui.buttons.save') }}' : '{{ __('workflow::workflow.ui.buttons.create') }}'"></span></button>
            </div>
        </form>
    </div>
</div>
</template>

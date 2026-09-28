{{-- ============================================================
     MODAL: Transition — Configure label & actions on a link
     ============================================================ --}}
<template x-teleport="body">
<div x-show="modal==='transition'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" x-transition.opacity>
    <div class="fixed inset-0 bg-black/50" @click="modal=null"></div>
    <div class="bg-card border border-border rounded-lg shadow-lg w-full max-w-lg mx-4 relative z-10 fade-in flex flex-col max-h-[90vh]"
         role="dialog" aria-modal="true" aria-labelledby="wf-transition-title" data-modal="transition" tabindex="-1" @keydown.tab="trapTab($event)">
        <div class="px-6 py-4 border-b border-border shrink-0">
            <h3 id="wf-transition-title" class="text-base font-semibold text-foreground">{{ __('workflow::workflow.ui.transition_modal.title') }}</h3>
            <p class="text-xs text-muted-foreground mt-0.5" x-show="tConfig.from && tConfig.to">
                <span x-text="tConfig.from?.name"></span>
                <span class="mx-1">&rarr;</span>
                <span x-text="tConfig.to?.name"></span>
            </p>
        </div>
        <div class="p-6 space-y-4 overflow-y-auto overflow-x-hidden flex-1 min-h-0">
            <div>
                <label class="text-sm font-medium text-foreground mb-1.5 block">{{ __('workflow::workflow.ui.transition_modal.label') }}</label>
                <input x-model="tConfig.label" class="sh-input w-full" placeholder="{{ __('workflow::workflow.ui.transition_modal.label_placeholder') }}">
            </div>

            <div>
                <h4 class="text-sm font-semibold text-foreground mb-2">{{ __('workflow::workflow.ui.transition_modal.actions') }}</h4>
                <div class="space-y-2" x-show="tConfig.actions.length">
                    <template x-for="(action, i) in tConfig.actions" :key="i">
                        <div class="flex items-center gap-2">
                            <select x-model="action.type" class="sh-input flex-1 h-8 text-xs">
                                <option value="" disabled>{{ __('workflow::workflow.ui.transition_modal.select_action') ?? 'Sélectionner une action' }}</option>
                                <template x-for="a in scopedActions" :key="a.key">
                                    <option :value="a.key" :disabled="a.key !== action.type && tConfig.actions.some(x => x.type === a.key)" x-text="a.label"></option>
                                </template>
                            </select>
                            <button type="button" @click="tConfig.actions.splice(i,1)" class="shrink-0 p-1 rounded text-muted-foreground hover:text-destructive hover:bg-accent">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
                <button x-show="tConfig.actions.length < scopedActions.length" type="button" @click="tConfig.actions.push({type:'',config:{}})" class="sh-btn sh-btn-outline h-7 text-xs mt-2">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    {{ __('workflow::workflow.ui.buttons.add_action') }}
                </button>
            </div>

            <div>
                <h4 class="text-sm font-semibold text-foreground mb-2">{{ __('workflow::workflow.ui.transition_modal.conditions') }}</h4>
                <div class="space-y-2" x-show="tConfig.conditions.length">
                    <template x-for="(condition, i) in tConfig.conditions" :key="i">
                        <div class="flex items-center gap-2">
                            <select x-model="condition.type" class="sh-input flex-1 h-8 text-xs">
                                <option value="" disabled>{{ __('workflow::workflow.ui.transition_modal.select_condition') ?? 'Sélectionner une condition' }}</option>
                                <template x-for="c in scopedConditions" :key="c.key">
                                    <option :value="c.key" :disabled="c.key !== condition.type && tConfig.conditions.some(x => x.type === c.key)" x-text="c.label"></option>
                                </template>
                            </select>
                            <button type="button" @click="tConfig.conditions.splice(i,1)" class="shrink-0 p-1 rounded text-muted-foreground hover:text-destructive hover:bg-accent">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
                <button x-show="tConfig.conditions.length < scopedConditions.length" type="button" @click="tConfig.conditions.push({type:'',config:{}})" class="sh-btn sh-btn-outline h-7 text-xs mt-2">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    {{ __('workflow::workflow.ui.transition_modal.add_condition') }}
                </button>
            </div>
        </div>

        <div class="flex justify-end gap-2 px-6 py-4 border-t border-border shrink-0">
            <button @click="modal=null" class="sh-btn sh-btn-outline h-9">{{ __('workflow::workflow.ui.buttons.cancel') }}</button>
            <button @click="saveTransitionConfig()" :disabled="busy" class="sh-btn sh-btn-primary h-9 disabled:opacity-50"><span x-text="busy ? '...' : '{{ __('workflow::workflow.ui.buttons.save') }}'"></span></button>
        </div>
    </div>
</div>
</template>

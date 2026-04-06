{{-- ============================================================
     TOAST — Notification feedback
     ============================================================ --}}
<div x-show="toast.on" x-cloak x-transition
     :class="toast.ok ? 'bg-foreground text-background' : 'bg-destructive text-destructive-foreground'"
     class="fixed bottom-4 right-4 px-4 py-2.5 rounded-md shadow-lg text-sm font-medium z-[60] max-w-sm">
    <span x-text="toast.msg"></span>
</div>

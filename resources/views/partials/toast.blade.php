{{-- ============================================================
     TOAST — Notification feedback
     ============================================================ --}}
<div x-show="toast.on" x-cloak x-transition
     :class="{'bg-green-600': toast.ok, 'bg-red-600': !toast.ok}"
     class="fixed bottom-5 right-5 text-white px-4 py-2.5 rounded-xl shadow-lg text-sm font-medium z-[60] max-w-sm">
    <span x-text="toast.msg"></span>
</div>

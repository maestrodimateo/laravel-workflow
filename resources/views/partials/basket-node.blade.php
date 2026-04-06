{{-- ============================================================
     BASKET NODE — Single basket card on the canvas
     ============================================================ --}}
<div class="rounded-lg border bg-card shadow-sm overflow-hidden relative group transition-all"
     :class="{
         'border-ring shadow-md ring-1 ring-ring': sel?.id === b.id,
         'border-border hover:shadow-md': sel?.id !== b.id,
         'shadow-lg border-ring/50': drag === b.id,
         'ring-2 ring-green-500 ring-offset-1 ring-offset-background': linking && linking.id !== b.id
     }"
     @click.stop="onNodeClick(b)">

    {{-- Color bar --}}
    <div class="h-1.5" :style="'background:' + color(b.color)"></div>

    <div class="px-3 py-2.5">
        {{-- Status + actions --}}
        <div class="flex items-center justify-between mb-1.5">
            <span class="sh-badge text-[10px] px-1.5 py-0"
                  :style="'background:' + color(b.color) + '12; color:' + color(b.color) + '; border-color:' + color(b.color) + '30'"
                  x-text="b.status"></span>
            <div class="flex gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                <button @click.stop="editBasket(b)" class="sh-btn sh-btn-ghost h-6 w-6 p-0">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <button x-show="b.status !== 'DRAFT'" @click.stop="deleteBasket(b)" class="sh-btn sh-btn-ghost h-6 w-6 p-0 hover:!text-destructive">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Name --}}
        <h3 class="text-sm font-medium text-card-foreground leading-snug mb-2" x-text="b.name"></h3>

        {{-- Badges --}}
        <div class="flex gap-2 text-[10px] text-muted-foreground">
            <span x-show="(b.roles || []).length" class="flex items-center gap-0.5">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span x-text="(b.roles || []).length"></span>
            </span>
            <span x-show="(b.messages || []).length" class="flex items-center gap-0.5">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8"/></svg>
                <span x-text="(b.messages || []).length"></span>
            </span>
            <span x-show="!(b.next || []).length && b.status !== 'DRAFT'" class="text-green-600 dark:text-green-400 font-semibold">FIN</span>
        </div>
    </div>

    {{-- Output port (right) — starts a link on mousedown --}}
    <div @mousedown.stop.prevent="startLink(b)"
         class="absolute top-1/2 -right-[8px] -translate-y-1/2 z-30 cursor-crosshair group/port">
        {{-- Hit area (invisible, 24px) --}}
        <div class="w-6 h-6 rounded-full absolute -inset-1.5"></div>
        {{-- Visible dot --}}
        <div class="w-4 h-4 rounded-full bg-foreground border-2 border-card shadow transition-transform group-hover/port:scale-125"></div>
    </div>

    {{-- Input port (left) — receives a link on mouseup --}}
    <div @mouseup.stop="completeLink(b)"
         class="absolute top-1/2 -left-[8px] -translate-y-1/2 z-30"
         :class="linking && linking.id !== b.id ? 'cursor-crosshair' : ''">
        {{-- Hit area --}}
        <div class="w-6 h-6 rounded-full absolute -inset-1.5"></div>
        {{-- Visible dot --}}
        <div class="w-4 h-4 rounded-full border-2 border-card shadow transition-all"
             :class="linking && linking.id !== b.id
                 ? 'bg-green-500 scale-125 ring-4 ring-green-500/20'
                 : 'bg-muted-foreground/30'"></div>
    </div>
</div>

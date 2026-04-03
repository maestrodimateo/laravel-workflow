{{-- ============================================================
     BASKET NODE — Single basket card rendered on the canvas
     ============================================================ --}}
<div class="rounded-xl border-2 bg-white dark:bg-gray-800 shadow-sm overflow-hidden relative group transition-colors"
     :class="{
         'border-indigo-500 shadow-indigo-100 dark:shadow-indigo-900/30 shadow-lg': sel?.id === b.id,
         'border-gray-200 dark:border-gray-700 hover:shadow-md hover:border-gray-300 dark:hover:border-gray-600': sel?.id !== b.id,
         'shadow-xl border-indigo-400': drag === b.id,
         'ring-2 ring-green-400 ring-offset-2 dark:ring-offset-gray-900': linking && linking.id !== b.id
     }"
     @click.stop="onNodeClick(b)">

    {{-- Color bar --}}
    <div class="h-2" :style="'background:' + color(b.color)"></div>

    <div class="px-3.5 py-3">
        {{-- Status badge + actions --}}
        <div class="flex items-center justify-between mb-1">
            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                  :style="'background:' + color(b.color) + '15;color:' + color(b.color)"
                  x-text="b.status"></span>

            <div class="flex gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                <button @click.stop="editBasket(b)" class="p-1 text-gray-400 hover:text-indigo-600 rounded">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <button x-show="b.status !== 'DRAFT'" @click.stop="deleteBasket(b)" class="p-1 text-gray-400 hover:text-red-500 rounded">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Name --}}
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 leading-snug mb-2" x-text="b.name"></h3>

        {{-- Metadata badges --}}
        <div class="flex gap-2 text-[10px] text-gray-400">
            <span x-show="(b.roles || []).length" class="flex items-center gap-0.5">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span x-text="(b.roles || []).length"></span>
            </span>
            <span x-show="(b.messages || []).length" class="flex items-center gap-0.5">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8"/></svg>
                <span x-text="(b.messages || []).length"></span>
            </span>
            <span x-show="!(b.next || []).length && b.status !== 'DRAFT'" class="text-emerald-500 font-semibold">FIN</span>
        </div>
    </div>

    {{-- Output port (right) — starts a link --}}
    <div @mousedown.stop.prevent="startLink(b)"
         class="absolute top-1/2 -right-[7px] -translate-y-1/2 w-[14px] h-[14px] rounded-full bg-indigo-500 border-[3px] border-white shadow cursor-crosshair z-20 hover:scale-125 hover:bg-indigo-600 transition-transform">
    </div>

    {{-- Input port (left) — receives a link --}}
    <div class="absolute top-1/2 -left-[7px] -translate-y-1/2 w-[14px] h-[14px] rounded-full bg-gray-400 border-[3px] border-white shadow z-20 transition-transform"
         :class="{'!bg-green-500 scale-125 ring-4 ring-green-200': linking && linking.id !== b.id}">
    </div>
</div>

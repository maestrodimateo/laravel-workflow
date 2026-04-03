{{-- ============================================================
     DIAGRAM CANVAS — Nodes, edges, drag & drop, linking
     ============================================================ --}}
<div class="flex-1 overflow-auto relative" x-ref="canvas"
     @mousemove="onMove($event)"
     @mouseup="onUp()"
     @mouseleave="mouseInCanvas=false;onUp()"
     @click.self="sel=null;linking=null;$nextTick(()=>drawEdges())"
     :style="dark
         ? 'background-image:radial-gradient(#374151 1px,transparent 1px);background-size:24px 24px;background-color:#111827'
         : 'background-image:radial-gradient(#d1d5db 1px,transparent 1px);background-size:24px 24px;background-color:#f9fafb'">

    {{-- Empty state (no circuit selected) --}}
    <div x-show="!circuit" class="flex flex-col items-center justify-center h-full fade-in">
        <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z"/>
            </svg>
        </div>
        <p class="text-gray-500 text-sm mb-4">Sélectionnez ou créez un circuit</p>
        <button @click="openCircuitModal()" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
            Créer un circuit
        </button>
    </div>

    {{-- Zoom wrapper --}}
    <div :style="'transform:scale('+zoom+');transform-origin:0 0;width:'+(canvasW/zoom)+'px;height:'+(canvasH/zoom)+'px;min-width:'+canvasW+'px;min-height:'+canvasH+'px'"
         @wheel.prevent="onWheel($event)">

        {{-- Canvas for edge rendering --}}
        <canvas x-ref="edgeCanvas"
                class="absolute inset-0"
                style="z-index:5;pointer-events:none"
                :width="canvasW"
                :height="canvasH"
                :style="'width:'+canvasW+'px;height:'+canvasH+'px'">
        </canvas>

        {{-- Basket nodes --}}
        <template x-for="b in baskets" :key="b.id">
            <div class="absolute select-none" style="z-index:20"
                 :style="'left:'+pos(b.id).x+'px;top:'+pos(b.id).y+'px;width:'+NW+'px'"
                 :class="{'!z-30':drag===b.id}"
                 @mousedown.prevent="startDrag($event,b)">

                @include('workflow::partials.basket-node')
            </div>
        </template>

    </div>{{-- end zoom wrapper --}}
</div>

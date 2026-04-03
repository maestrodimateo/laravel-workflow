<!DOCTYPE html>
<html lang="fr" class="h-full" :class="{'dark': dark}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Workflow Designer</title>

    {{-- Dependencies --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Styles --}}
    <style>
        /* Alpine cloak */
        [x-cloak] { display: none !important; }

        /* Animations */
        .fade-in { animation: fadeIn .2s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Quill editor — Light mode */
        .ql-toolbar.ql-snow    { border-radius: 8px 8px 0 0; border-color: #e5e7eb; }
        .ql-container.ql-snow  { border-radius: 0 0 8px 8px; border-color: #e5e7eb; min-height: 100px; font-size: 14px; }
        .ql-editor              { min-height: 100px; }

        /* Quill editor — Dark mode */
        .dark .ql-toolbar.ql-snow              { border-color: #374151; background: #1f2937; }
        .dark .ql-container.ql-snow            { border-color: #374151; background: #1f2937; color: #f3f4f6; }
        .dark .ql-toolbar .ql-stroke           { stroke: #9ca3af; }
        .dark .ql-toolbar .ql-fill             { fill: #9ca3af; }
        .dark .ql-toolbar .ql-picker-label     { color: #9ca3af; }
        .dark .ql-toolbar button:hover .ql-stroke  { stroke: #e5e7eb; }
        .dark .ql-toolbar button:hover .ql-fill    { fill: #e5e7eb; }
        .dark .ql-toolbar button.ql-active .ql-stroke { stroke: #818cf8; }
        .dark .ql-toolbar button.ql-active .ql-fill   { fill: #818cf8; }
        .dark .ql-editor.ql-blank::before      { color: #6b7280; }
    </style>
</head>

<body class="h-full overflow-hidden bg-gray-100 dark:bg-gray-950 transition-colors"
      x-data="app()" x-init="boot()">

    {{-- Hidden file input for circuit import --}}
    <input type="file" accept=".json" x-ref="importInput" class="hidden" @change="importCircuit($event)">

    {{-- ==================== LAYOUT ==================== --}}

    @include('workflow::partials.header')

    <div class="flex" style="height: calc(100vh - 56px)">

        {{-- Main canvas area --}}
        <main class="flex-1 flex flex-col overflow-hidden">
            @include('workflow::partials.toolbar')
            @include('workflow::partials.canvas')
        </main>

        {{-- Right sidebar --}}
        @include('workflow::partials.sidebar')

    </div>

    {{-- ==================== MODALS ==================== --}}

    @include('workflow::partials.modals.circuit')
    @include('workflow::partials.modals.basket')
    @include('workflow::partials.modals.message')
    @include('workflow::partials.modals.transition')

    {{-- ==================== TOAST ==================== --}}

    @include('workflow::partials.toast')

    {{-- ==================== JAVASCRIPT ==================== --}}

    <script>
    function app() {
        const API  = @json($apiPrefix);
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;
        const NW   = 210;
        const NH   = 105;

        return {

            // =================================================================
            // State
            // =================================================================

            circuits: @json($circuits),
            colors:   @json($colors),
            msgTypes: @json($msgTypes),
            recipients: @json($recipients),
            msgVars:  @json($variables),
            availableActions: @json($actions),

            dark: localStorage.getItem('wf-dark') === '1'
                || (!localStorage.getItem('wf-dark') && window.matchMedia('(prefers-color-scheme:dark)').matches),

            circuit: null,
            sel: null,
            modal: null,
            editId: null,
            busy: false,
            errs: {},
            toast: { on: false, msg: '', ok: true },
            showMessages: false,

            // Diagram
            NW, NH,
            positions: {},
            drag: null,
            dragOff: { x: 0, y: 0 },
            dragMoved: false,
            linking: null,
            mx: 0, my: 0,
            mouseInCanvas: false,
            canvasW: 800, canvasH: 600,
            animFrame: null, animT: 0,
            zoom: 1, GRID: 24,

            // Forms
            cForm: { name: '', targetModel: '', description: '', roles: [] },
            bForm: { name: '', status: '', color: '', circuit_id: '', roles: [], previous: [] },
            mForm: { subject: '', content: '', type: '', recipient: '', circuit_id: '', basket_id: null },
            quillInstance: null,
            tConfig: { from: null, to: null, label: '', actions: [] },
            linkTarget: '',

            // =================================================================
            // Computed properties
            // =================================================================

            get baskets()        { return this.circuit?.baskets || []; },
            get circuitRoles()   { return this.circuit?.roles || []; },
            get circuitMessages(){ return this.circuit?.messages || this.baskets.flatMap(b => b.messages || []); },

            get availTargets() {
                if (!this.sel) return [];
                const ids = new Set((this.sel.next || []).map(n => n.id));
                return this.baskets.filter(b => b.id !== this.sel.id && !ids.has(b.id));
            },

            // =================================================================
            // Helpers
            // =================================================================

            color(c)    { return c && typeof c === 'object' ? c.value : (c || '#30638E'); },
            pos(id)     { return this.positions[id] || { x: 0, y: 0 }; },
            toggleArr(arr, v) { const i = arr.indexOf(v); i === -1 ? arr.push(v) : arr.splice(i, 1); },
            toggleDark(){ this.dark = !this.dark; localStorage.setItem('wf-dark', this.dark ? '1' : '0'); },
            notify(msg, ok = true) { this.toast = { on: true, msg, ok }; setTimeout(() => this.toast.on = false, 3500); },

            parseActions(v) {
                try { return typeof v === 'string' ? JSON.parse(v) : Array.isArray(v) ? v : []; }
                catch { return []; }
            },
            actionLabel(key) { return this.availableActions.find(a => a.key === key)?.label || key; },

            // =================================================================
            // API
            // =================================================================

            async api(method, path, body = null) {
                this.errs = {};
                const opts = {
                    method,
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
                };
                if (body) opts.body = JSON.stringify(body);

                const res = await fetch(API + path, opts);
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    if (err.errors) {
                        Object.entries(err.errors).forEach(([k, v]) => this.errs[k] = Array.isArray(v) ? v[0] : v);
                    }
                    throw new Error(err.message || Object.values(err.errors || {}).flat()[0] || 'Erreur');
                }
                return res.status === 204 ? null : res.json();
            },

            // =================================================================
            // Quill WYSIWYG
            // =================================================================

            initQuill(el) {
                this.$nextTick(() => {
                    if (this.quillInstance) this.quillInstance = null;
                    const q = new Quill(el, {
                        theme: 'snow',
                        placeholder: 'Rédigez le contenu du message...',
                        modules: {
                            toolbar: [
                                [{ header: [1, 2, 3, false] }],
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ list: 'ordered' }, { list: 'bullet' }],
                                ['link', 'blockquote', 'code-block'],
                                ['clean'],
                            ],
                        },
                    });
                    if (this.mForm.content) q.root.innerHTML = this.mForm.content;
                    q.on('text-change', () => { this.mForm.content = q.root.innerHTML; });
                    this.quillInstance = q;
                });
            },

            insertVariable(key) {
                if (!this.quillInstance) return;
                const range = this.quillInstance.getSelection(true);
                this.quillInstance.insertText(range.index, '{{ ' + key + ' }}');
                this.quillInstance.setSelection(range.index + key.length + 6);
            },

            // =================================================================
            // Boot & Layout
            // =================================================================

            boot() {
                if (this.circuits.length) this.pick(this.circuits[0]);
            },

            pick(c) {
                this.stopAnimation();
                this.circuit = c;
                this.sel = null;
                this.linking = null;
                this.positions = {};
                this.$nextTick(() => {
                    this.layout(true);
                    this.$nextTick(() => this.drawEdges());
                });
            },

            layout(force = false) {
                const bs = this.baskets;
                if (!bs.length) return;

                // BFS columns
                const visited = new Set();
                const columns = [];
                let current = bs.filter(b => !(b.previous || []).length);
                if (!current.length) current = [bs[0]];

                while (current.length) {
                    columns.push(current);
                    current.forEach(b => visited.add(b.id));
                    const nextIds = new Set();
                    current.forEach(b => (b.next || []).forEach(n => {
                        if (!visited.has(n.id)) nextIds.add(n.id);
                    }));
                    current = bs.filter(b => nextIds.has(b.id));
                }

                const remaining = bs.filter(b => !visited.has(b.id));
                if (remaining.length) columns.push(remaining);

                // Compute positions
                const maxRows = Math.max(...columns.map(c => c.length));
                const gx = 140, gy = 40, px = 60, py = 60;
                const p = force ? {} : { ...this.positions };

                columns.forEach((col, ci) => {
                    const totalH = col.length * NH + (col.length - 1) * gy;
                    const maxH   = maxRows * NH + (maxRows - 1) * gy;
                    const offsetY = (maxH - totalH) / 2;

                    col.forEach((b, ri) => {
                        if (force || !p[b.id]) {
                            p[b.id] = {
                                x: px + ci * (NW + gx),
                                y: py + offsetY + ri * (NH + gy),
                            };
                        }
                    });
                });

                this.positions = p;
                this.resize();
            },

            autoLayout() { this.layout(true); },

            resize() {
                const xs = Object.values(this.positions).map(p => p.x);
                const ys = Object.values(this.positions).map(p => p.y);
                if (!xs.length) return;
                this.canvasW = Math.max(800, Math.max(...xs) + NW + 80);
                this.canvasH = Math.max(600, Math.max(...ys) + NH + 80);
                this.$nextTick(() => this.drawEdges());
            },

            setZoom(v) { this.zoom = Math.max(0.3, Math.min(2, v)); },
            onWheel(e) { this.setZoom(this.zoom + (e.deltaY < 0 ? 0.05 : -0.05)); },

            // =================================================================
            // Drag & Drop
            // =================================================================

            startDrag(e, b) {
                if (this.linking) return;
                const r = this.$refs.canvas;
                const rect = r.getBoundingClientRect();
                const p = this.positions[b.id];
                this.drag = b.id;
                this.dragMoved = false;
                this.dragOff = {
                    x: (e.clientX - rect.left + r.scrollLeft) / this.zoom - p.x,
                    y: (e.clientY - rect.top  + r.scrollTop)  / this.zoom - p.y,
                };
            },

            onMove(e) {
                const r = this.$refs.canvas;
                const rect = r.getBoundingClientRect();
                this.mx = (e.clientX - rect.left + r.scrollLeft) / this.zoom;
                this.my = (e.clientY - rect.top  + r.scrollTop)  / this.zoom;
                this.mouseInCanvas = true;

                if (this.drag) {
                    const rawX = Math.max(0, this.mx - this.dragOff.x);
                    const rawY = Math.max(0, this.my - this.dragOff.y);
                    const newX = Math.round(rawX / this.GRID) * this.GRID;
                    const newY = Math.round(rawY / this.GRID) * this.GRID;
                    const old = this.positions[this.drag];

                    if (!this.dragMoved && old && Math.abs(newX - old.x) < 5 && Math.abs(newY - old.y) < 5) return;
                    this.dragMoved = true;
                    this.positions = { ...this.positions, [this.drag]: { x: newX, y: newY } };
                    this.drawEdges();
                }

                if (this.linking) this.drawEdges();
            },

            onUp() {
                if (this.drag) {
                    this.drag = null;
                    this.resize();
                }
            },

            // =================================================================
            // Visual Linking
            // =================================================================

            startLink(b) { this.linking = b; },

            onNodeClick(b) {
                if (this.dragMoved) return;
                if (this.linking) {
                    if (this.linking.id === b.id) { this.linking = null; this.drawEdges(); return; }
                    if ((this.linking.next || []).some(n => n.id === b.id)) {
                        this.notify('Lien déjà existant', false); this.linking = null; this.drawEdges(); return;
                    }
                    this.createLink(this.linking, b);
                    return;
                }
                this.sel = b;
                this.$nextTick(() => this.drawEdges());
            },

            async createLink(from, to) {
                const fid = from.id;
                try {
                    const prev = [...(to.previous || []).map(p => p.id), fid];
                    await this.api('PUT', '/baskets/' + to.id, {
                        name: to.name, status: to.status, color: this.color(to.color),
                        circuit_id: this.circuit.id, previous: prev, roles: to.roles || [],
                    });
                    this.linking = null;
                    await this.refresh();
                    this.sel = this.baskets.find(b => b.id === fid) || null;
                    this.notify('Transition créée');
                } catch (e) { this.notify(e.message, false); this.linking = null; }
            },

            async removeLink(from, to) {
                try {
                    const prev = (to.previous || []).map(p => p.id).filter(id => id !== from.id);
                    await this.api('PUT', '/baskets/' + to.id, {
                        name: to.name, status: to.status, color: this.color(to.color),
                        circuit_id: this.circuit.id, previous: prev, roles: to.roles || [],
                    });
                    await this.refresh();
                    this.sel = this.baskets.find(b => b.id === from.id) || null;
                    this.notify('Transition supprimée');
                } catch (e) { this.notify(e.message, false); }
            },

            async addLink() {
                if (!this.linkTarget || !this.sel) return;
                const target = this.baskets.find(b => b.id === this.linkTarget);
                if (!target) return;
                await this.createLink(this.sel, target);
                this.linkTarget = '';
            },

            // =================================================================
            // Edge rendering (Canvas 2D + animation)
            // =================================================================

            bezierPt(x1, y1, cx1, cy1, cx2, cy2, x2, y2, t) {
                const u = 1 - t;
                return {
                    x: u*u*u*x1 + 3*u*u*t*cx1 + 3*u*t*t*cx2 + t*t*t*x2,
                    y: u*u*u*y1 + 3*u*u*t*cy1 + 3*u*t*t*cy2 + t*t*t*y2,
                };
            },

            startAnimation() {
                if (this.animFrame) return;
                const loop = () => {
                    this.animT = (this.animT + 0.004) % 1;
                    this.renderEdges();
                    this.animFrame = requestAnimationFrame(loop);
                };
                this.animFrame = requestAnimationFrame(loop);
            },

            stopAnimation() {
                if (this.animFrame) { cancelAnimationFrame(this.animFrame); this.animFrame = null; }
            },

            drawEdges() {
                this.renderEdges();
                if (!this.animFrame && this.baskets.some(b => (b.next || []).length)) {
                    this.startAnimation();
                }
            },

            renderEdges() {
                const c = this.$refs.edgeCanvas;
                if (!c || !c.getContext) return;
                const ctx = c.getContext('2d');
                if (!ctx) return;
                ctx.clearRect(0, 0, c.width, c.height);
                if (!Object.keys(this.positions).length) return;

                const dk = this.dark;
                const lineCol    = dk ? 'rgba(129,140,248,0.6)' : 'rgba(99,102,241,0.4)';
                const selCol     = dk ? '#fbbf24' : '#4f46e5';
                const dotCol     = dk ? '#a5b4fc' : '#6366f1';
                const selDotCol  = dk ? '#fbbf24' : '#4338ca';
                const flowCol    = dk ? 'rgba(165,180,252,0.8)' : 'rgba(99,102,241,0.7)';
                const selFlowCol = dk ? 'rgba(251,191,36,0.9)' : 'rgba(79,70,229,0.9)';
                const labelBg    = dk ? 'rgba(31,41,55,0.85)' : 'rgba(255,255,255,0.9)';
                const labelCol   = dk ? '#d1d5db' : '#4b5563';

                // Draw each edge
                this.baskets.forEach(b => {
                    (b.next || []).forEach(n => {
                        const f = this.positions[b.id], t = this.positions[n.id];
                        if (!f || !t) return;
                        const isSel = this.sel && (this.sel.id === b.id || this.sel.id === n.id);

                        const x1 = f.x + NW, y1 = f.y + NH / 2;
                        const x2 = t.x,      y2 = t.y + NH / 2;
                        const dx = Math.max(Math.abs(x2 - x1) * 0.5, 70);
                        const cx1 = x1 + dx, cy1 = y1, cx2 = x2 - dx, cy2 = y2;

                        // Shadow
                        ctx.beginPath();
                        ctx.strokeStyle = dk ? 'rgba(0,0,0,0.3)' : 'rgba(0,0,0,0.06)';
                        ctx.lineWidth = isSel ? 6 : 4;
                        ctx.moveTo(x1, y1); ctx.bezierCurveTo(cx1, cy1, cx2, cy2, x2, y2); ctx.stroke();

                        // Main line
                        ctx.beginPath();
                        ctx.strokeStyle = isSel ? selCol : lineCol;
                        ctx.lineWidth = isSel ? 2.5 : 1.8;
                        ctx.moveTo(x1, y1); ctx.bezierCurveTo(cx1, cy1, cx2, cy2, x2, y2); ctx.stroke();

                        // Flowing dots
                        for (let i = 0; i < 3; i++) {
                            const pt = this.bezierPt(x1, y1, cx1, cy1, cx2, cy2, x2, y2, (this.animT + i / 3) % 1);
                            ctx.beginPath();
                            ctx.fillStyle = isSel ? selFlowCol : flowCol;
                            ctx.arc(pt.x, pt.y, isSel ? 3.5 : 2.5, 0, Math.PI * 2);
                            ctx.fill();
                        }

                        // Source dot
                        ctx.fillStyle = isSel ? selDotCol : dotCol;
                        ctx.beginPath(); ctx.arc(x1, y1, isSel ? 5 : 4, 0, Math.PI * 2); ctx.fill();

                        // Arrow at target
                        const ap = this.bezierPt(x1, y1, cx1, cy1, cx2, cy2, x2, y2, 0.97);
                        const angle = Math.atan2(y2 - ap.y, x2 - ap.x);
                        const as = isSel ? 8 : 6;
                        ctx.beginPath();
                        ctx.fillStyle = isSel ? selDotCol : dotCol;
                        ctx.moveTo(x2, y2);
                        ctx.lineTo(x2 - as * Math.cos(angle - 0.4), y2 - as * Math.sin(angle - 0.4));
                        ctx.lineTo(x2 - as * Math.cos(angle + 0.4), y2 - as * Math.sin(angle + 0.4));
                        ctx.closePath(); ctx.fill();

                        // Edge label
                        const label = n.pivot?.label;
                        if (label) {
                            const mp = this.bezierPt(x1, y1, cx1, cy1, cx2, cy2, x2, y2, 0.5);
                            ctx.font = '600 10px system-ui,sans-serif';
                            const tw = ctx.measureText(label).width;
                            const px = 4, py = 2, rr = 6;
                            const rx = mp.x - tw/2 - px, ry = mp.y - 7 - py, rw = tw + px*2, rh = 14 + py*2;

                            ctx.fillStyle = labelBg;
                            ctx.beginPath();
                            ctx.moveTo(rx+rr, ry); ctx.lineTo(rx+rw-rr, ry);
                            ctx.quadraticCurveTo(rx+rw, ry, rx+rw, ry+rr);
                            ctx.lineTo(rx+rw, ry+rh-rr);
                            ctx.quadraticCurveTo(rx+rw, ry+rh, rx+rw-rr, ry+rh);
                            ctx.lineTo(rx+rr, ry+rh);
                            ctx.quadraticCurveTo(rx, ry+rh, rx, ry+rh-rr);
                            ctx.lineTo(rx, ry+rr);
                            ctx.quadraticCurveTo(rx, ry, rx+rr, ry);
                            ctx.closePath(); ctx.fill();

                            ctx.fillStyle = labelCol;
                            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                            ctx.fillText(label, mp.x, mp.y);
                        }
                    });
                });

                // Temporary linking line
                if (this.linking && this.mouseInCanvas) {
                    const f = this.positions[this.linking.id];
                    if (f) {
                        const x1 = f.x + NW, y1 = f.y + NH / 2;
                        const x2 = this.mx,   y2 = this.my;
                        const dx = Math.max(Math.abs(x2 - x1) * 0.4, 40);

                        ctx.beginPath();
                        ctx.strokeStyle = dk ? 'rgba(139,92,246,0.2)' : 'rgba(139,92,246,0.15)';
                        ctx.lineWidth = 8;
                        ctx.moveTo(x1, y1); ctx.bezierCurveTo(x1+dx, y1, x2-dx, y2, x2, y2); ctx.stroke();

                        ctx.beginPath();
                        ctx.strokeStyle = '#a78bfa'; ctx.lineWidth = 2; ctx.setLineDash([8, 5]);
                        ctx.moveTo(x1, y1); ctx.bezierCurveTo(x1+dx, y1, x2-dx, y2, x2, y2); ctx.stroke();
                        ctx.setLineDash([]);

                        const pulse = Math.sin(this.animT * Math.PI * 2) * 2 + 5;
                        ctx.beginPath(); ctx.fillStyle = 'rgba(167,139,250,0.3)'; ctx.arc(x1, y1, pulse, 0, Math.PI*2); ctx.fill();
                        ctx.beginPath(); ctx.fillStyle = '#a78bfa'; ctx.arc(x1, y1, 4, 0, Math.PI*2); ctx.fill();
                        ctx.beginPath(); ctx.fillStyle = '#a78bfa'; ctx.arc(x2, y2, 4, 0, Math.PI*2); ctx.fill();
                    }
                }
            },

            // =================================================================
            // Data refresh
            // =================================================================

            async refresh() {
                try {
                    const data = await this.api('GET', '/circuits/' + this.circuit.id + '/baskets');
                    const idx = this.circuits.findIndex(c => c.id === this.circuit.id);
                    if (idx !== -1) {
                        this.circuits[idx].baskets = data;
                        this.circuit = { ...this.circuits[idx] };
                    }
                    // Clean stale positions + layout new baskets
                    const ids = new Set(this.baskets.map(b => b.id));
                    Object.keys(this.positions).forEach(k => { if (!ids.has(k)) delete this.positions[k]; });
                    this.layout(false);
                    this.$nextTick(() => this.drawEdges());
                } catch (e) { console.error(e); }
            },

            // =================================================================
            // Circuit CRUD
            // =================================================================

            openCircuitModal() {
                this.editId = null;
                this.cForm = { name: '', targetModel: '', description: '', roles: [] };
                this.errs = {};
                this.modal = 'circuit';
            },

            editCircuit() {
                this.editId = this.circuit.id;
                this.cForm = {
                    name: this.circuit.name,
                    targetModel: this.circuit.targetModel,
                    description: this.circuit.description || '',
                    roles: [...(this.circuit.roles || [])],
                };
                this.errs = {};
                this.modal = 'circuit';
            },

            addCR() {
                const v = this.$refs.crI.value.trim();
                if (v && !this.cForm.roles.includes(v)) this.cForm.roles.push(v);
                this.$refs.crI.value = '';
            },

            async saveCircuit() {
                this.busy = true;
                try {
                    if (this.editId) {
                        await this.api('PUT', '/circuits/' + this.editId, this.cForm);
                        const i = this.circuits.findIndex(c => c.id === this.editId);
                        if (i !== -1) { Object.assign(this.circuits[i], this.cForm); this.circuit = { ...this.circuits[i] }; }
                        this.notify('Circuit mis à jour');
                    } else {
                        const d = await this.api('POST', '/circuits', this.cForm);
                        const c = d.circuit?.data || d.circuit || d.data || d;
                        c.baskets = c.baskets || []; c.messages = c.messages || [];
                        this.circuits.push(c);
                        await this.refresh();
                        this.pick(this.circuits[this.circuits.length - 1]);
                        this.notify('Circuit créé');
                    }
                    this.modal = null;
                } catch (e) { this.notify(e.message, false); }
                this.busy = false;
            },

            async deleteCircuit() {
                if (!confirm('Supprimer ce circuit ?')) return;
                try {
                    await this.api('DELETE', '/circuits/' + this.circuit.id);
                    this.circuits = this.circuits.filter(c => c.id !== this.circuit.id);
                    this.circuit = this.circuits[0] || null;
                    this.sel = null;
                    this.positions = {};
                    this.notify('Supprimé');
                } catch (e) { this.notify(e.message, false); }
            },

            // =================================================================
            // Export / Import
            // =================================================================

            async exportCircuit() {
                if (!this.circuit) return;
                try {
                    const data = await this.api('GET', '/circuits/' + this.circuit.id + '/export');
                    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'workflow-' + this.circuit.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '.json';
                    document.body.appendChild(a); a.click(); document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    this.notify('Circuit exporté');
                } catch (e) { this.notify(e.message, false); }
            },

            async importCircuit(e) {
                const file = e.target.files[0];
                if (!file) return;
                e.target.value = '';
                const form = new FormData();
                form.append('file', file);
                try {
                    const res = await fetch(API + '/circuits/import', {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: form,
                    });
                    if (!res.ok) { const err = await res.json().catch(() => ({})); throw new Error(err.message || 'Erreur import'); }
                    const circuit = await res.json();
                    circuit.baskets = circuit.baskets || []; circuit.messages = circuit.messages || [];
                    this.circuits.push(circuit);
                    this.pick(circuit);
                    this.notify('Circuit importé');
                } catch (e) { this.notify(e.message, false); }
            },

            exportImage() {
                @include('workflow::partials.export-image-js')
            },

            // =================================================================
            // Basket CRUD
            // =================================================================

            openBasketModal() {
                this.editId = null;
                this.bForm = { name: '', status: '', color: this.colors[0]?.value || '#30638E', circuit_id: this.circuit.id, roles: [], previous: [] };
                this.errs = {};
                this.modal = 'basket';
            },

            editBasket(b) {
                this.editId = b.id;
                this.bForm = {
                    name: b.name, status: b.status, color: this.color(b.color),
                    circuit_id: this.circuit.id, roles: [...(b.roles || [])],
                    previous: (b.previous || []).map(p => p.id),
                };
                this.errs = {};
                this.modal = 'basket';
            },

            async saveBasket() {
                this.busy = true;
                try {
                    const body = { ...this.bForm, circuit_id: this.circuit.id };
                    if (this.editId) {
                        await this.api('PUT', '/baskets/' + this.editId, body);
                        this.notify('Panier mis à jour');
                    } else {
                        await this.api('POST', '/baskets', body);
                        this.notify('Panier créé');
                    }
                    await this.refresh();
                    this.modal = null;
                    this.sel = null;
                } catch (e) { this.notify(e.message, false); }
                this.busy = false;
            },

            async deleteBasket(b) {
                if (!confirm('Supprimer "' + b.name + '" ?')) return;
                try {
                    await this.api('DELETE', '/baskets/' + b.id);
                    delete this.positions[b.id];
                    await this.refresh();
                    if (this.sel?.id === b.id) this.sel = null;
                    this.notify('Supprimé');
                } catch (e) { this.notify(e.message, false); }
            },

            // =================================================================
            // Message CRUD
            // =================================================================

            openMsgModal() {
                this.mForm = {
                    subject: '', content: '',
                    type: this.msgTypes[0]?.value || 'email',
                    recipient: this.recipients[0]?.value || 'subject',
                    circuit_id: this.circuit.id,
                };
                this.quillInstance = null;
                this.modal = 'msg';
            },

            async saveMsg() {
                if (!this.mForm.content || this.mForm.content === '<p><br></p>') {
                    this.notify('Le contenu est obligatoire', false); return;
                }
                this.busy = true;
                try {
                    await this.api('POST', '/circuits/' + this.circuit.id + '/messages', this.mForm);
                    await this.refreshMessages();
                    this.modal = null;
                    this.notify('Message créé');
                } catch (e) { this.notify(e.message, false); }
                this.busy = false;
            },

            async deleteMsg(m) {
                if (!confirm('Supprimer ?')) return;
                try {
                    await this.api('DELETE', '/circuits/' + this.circuit.id + '/messages/' + m.id);
                    await this.refreshMessages();
                    this.notify('Supprimé');
                } catch (e) { this.notify(e.message, false); }
            },

            async refreshMessages() {
                try {
                    const msgs = await this.api('GET', '/circuits/' + this.circuit.id + '/messages');
                    const idx = this.circuits.findIndex(c => c.id === this.circuit.id);
                    if (idx !== -1) {
                        this.circuits[idx].messages = msgs;
                        this.circuit = { ...this.circuits[idx] };
                    }
                } catch (e) { console.error(e); }
            },

            // =================================================================
            // Transition config
            // =================================================================

            openTransitionConfig(from, to) {
                const pivot = to.pivot || {};
                this.tConfig = {
                    from, to,
                    label: pivot.label || '',
                    actions: this.parseActions(pivot.actions),
                };
                this.modal = 'transition';
            },

            addTransitionAction(key) {
                this.tConfig.actions.push({ type: key, config: {} });
            },

            async saveTransitionConfig() {
                this.busy = true;
                try {
                    await this.api('PUT', '/transitions/' + this.tConfig.from.id + '/' + this.tConfig.to.id, {
                        label: this.tConfig.label || null,
                        actions: this.tConfig.actions,
                    });
                    this.modal = null;
                    await this.refresh();
                    if (this.sel) this.sel = this.baskets.find(b => b.id === this.sel.id) || null;
                    this.notify('Transition configurée');
                } catch (e) { this.notify(e.message, false); }
                this.busy = false;
            },

        };
    }
    </script>
</body>
</html>

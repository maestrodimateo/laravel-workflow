<!DOCTYPE html>
<html lang="fr" class="h-full" :class="{'dark': dark}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Workflow Designer</title>

    {{-- Dependencies --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    border: 'hsl(var(--border))',
                    input: 'hsl(var(--input))',
                    ring: 'hsl(var(--ring))',
                    background: 'hsl(var(--background))',
                    foreground: 'hsl(var(--foreground))',
                    primary: { DEFAULT: 'hsl(var(--primary))', foreground: 'hsl(var(--primary-foreground))' },
                    muted: { DEFAULT: 'hsl(var(--muted))', foreground: 'hsl(var(--muted-foreground))' },
                    accent: { DEFAULT: 'hsl(var(--accent))', foreground: 'hsl(var(--accent-foreground))' },
                    destructive: { DEFAULT: 'hsl(var(--destructive))', foreground: 'hsl(var(--destructive-foreground))' },
                    card: { DEFAULT: 'hsl(var(--card))', foreground: 'hsl(var(--card-foreground))' },
                },
                borderRadius: { lg: '0.5rem', md: 'calc(0.5rem - 2px)', sm: 'calc(0.5rem - 4px)' },
            }
        }
    }
    </script>
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Styles — shadcn design tokens --}}
    <style>
        :root {
            --background: 0 0% 100%;
            --foreground: 240 10% 3.9%;
            --card: 0 0% 100%;
            --card-foreground: 240 10% 3.9%;
            --primary: 240 5.9% 10%;
            --primary-foreground: 0 0% 98%;
            --muted: 240 4.8% 95.9%;
            --muted-foreground: 240 3.8% 46.1%;
            --accent: 240 4.8% 95.9%;
            --accent-foreground: 240 5.9% 10%;
            --destructive: 0 84.2% 60.2%;
            --destructive-foreground: 0 0% 98%;
            --border: 240 5.9% 90%;
            --input: 240 5.9% 90%;
            --ring: 240 5.9% 10%;
        }
        .dark {
            --background: 240 10% 3.9%;
            --foreground: 0 0% 98%;
            --card: 240 10% 3.9%;
            --card-foreground: 0 0% 98%;
            --primary: 0 0% 98%;
            --primary-foreground: 240 5.9% 10%;
            --muted: 240 3.7% 15.9%;
            --muted-foreground: 240 5% 64.9%;
            --accent: 240 3.7% 15.9%;
            --accent-foreground: 0 0% 98%;
            --destructive: 0 62.8% 30.6%;
            --destructive-foreground: 0 0% 98%;
            --border: 240 3.7% 15.9%;
            --input: 240 3.7% 15.9%;
            --ring: 240 4.9% 83.9%;
        }

        [x-cloak] { display: none !important; }
        .fade-in { animation: fadeIn .15s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(2px); } to { opacity: 1; transform: translateY(0); } }

        /* shadcn-style Quill */
        .ql-toolbar.ql-snow { border-radius: 6px 6px 0 0; border-color: hsl(var(--border)); background: hsl(var(--background)); }
        .ql-container.ql-snow { border-radius: 0 0 6px 6px; border-color: hsl(var(--border)); background: hsl(var(--background)); min-height: 100px; font-size: 14px; color: hsl(var(--foreground)); }
        .ql-editor { min-height: 100px; }
        .dark .ql-toolbar .ql-stroke { stroke: hsl(var(--muted-foreground)); }
        .dark .ql-toolbar .ql-fill { fill: hsl(var(--muted-foreground)); }
        .dark .ql-toolbar .ql-picker-label { color: hsl(var(--muted-foreground)); }
        .dark .ql-toolbar button:hover .ql-stroke { stroke: hsl(var(--foreground)); }
        .dark .ql-toolbar button:hover .ql-fill { fill: hsl(var(--foreground)); }
        .dark .ql-toolbar button.ql-active .ql-stroke { stroke: hsl(var(--foreground)); }
        .dark .ql-toolbar button.ql-active .ql-fill { fill: hsl(var(--foreground)); }
        .dark .ql-editor.ql-blank::before { color: hsl(var(--muted-foreground)); }

        /* shadcn input/button base */
        .sh-input { height: 2.25rem; border-radius: 0.375rem; border: 1px solid hsl(var(--border)); background: transparent; padding: 0.5rem 0.75rem; font-size: 0.875rem; color: hsl(var(--foreground)); outline: none; transition: box-shadow 0.15s; }
        .sh-input:focus { box-shadow: 0 0 0 2px hsl(var(--ring) / 0.2); border-color: hsl(var(--ring)); }
        .sh-input::placeholder { color: hsl(var(--muted-foreground)); }
        .sh-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.375rem; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; height: 2.25rem; padding: 0 1rem; transition: all 0.15s; cursor: pointer; outline: none; }
        .sh-btn:focus-visible { box-shadow: 0 0 0 2px hsl(var(--ring) / 0.2); }
        .sh-btn-primary { background: hsl(var(--primary)); color: hsl(var(--primary-foreground)); border: none; }
        .sh-btn-primary:hover { opacity: 0.9; }
        .sh-btn-outline { background: transparent; color: hsl(var(--foreground)); border: 1px solid hsl(var(--border)); }
        .sh-btn-outline:hover { background: hsl(var(--accent)); }
        .sh-btn-ghost { background: transparent; color: hsl(var(--foreground)); border: none; }
        .sh-btn-ghost:hover { background: hsl(var(--accent)); }
        .sh-btn-destructive { background: hsl(var(--destructive)); color: hsl(var(--destructive-foreground)); border: none; }
        .sh-btn-destructive:hover { opacity: 0.9; }
        .sh-badge { display: inline-flex; align-items: center; border-radius: 9999px; padding: 0.125rem 0.625rem; font-size: 0.75rem; font-weight: 500; border: 1px solid hsl(var(--border)); background: hsl(var(--background)); color: hsl(var(--foreground)); }
    </style>
</head>

<body class="h-full overflow-hidden bg-background text-foreground antialiased"
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
    /**
     * Workflow Designer — Alpine.js application
     *
     * This script powers the visual workflow designer interface.
     * It manages circuit/basket/message/transition CRUD, the diagram
     * canvas with drag-and-drop and visual linking, edge rendering
     * with animated flowing dots, and export/import functionality.
     */
    function app() {

        /** @type {string} Base URL for admin API endpoints */
        const API_BASE = @json($apiPrefix);

        /** @type {string} CSRF token for authenticated requests */
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

        /** @type {number} Width of a basket node in pixels */
        const NODE_WIDTH = 210;

        /** @type {number} Height of a basket node in pixels */
        const NODE_HEIGHT = 105;

        /** @type {number} Minimum pixels of movement before a drag is registered */
        const DRAG_THRESHOLD = 3;

        /** @type {number} Grid size for snap-on-drop alignment */
        const GRID_SIZE = 24;

        /** @type {number} Number of animated dots per edge */
        const FLOW_DOT_COUNT = 3;

        /** @type {number} Speed of the animation (lower = slower) */
        const ANIM_SPEED = 0.004;

        return {

            // =================================================================
            // Application state
            // =================================================================

            /** @type {Array} All circuits loaded from the server */
            circuits: @json($circuits),

            /** @type {Array} Available basket colors from enum */
            colors: @json($colors),

            /** @type {Array} Message type options (email, sms, notification) */
            msgTypes: @json($msgTypes),

            /** @type {Array} Recipient type options (subject, operators) */
            recipients: @json($recipients),

            /** @type {Object} Available template variables for messages */
            messageVariables: @json($variables),

            /** @type {Array} Registered transition actions */
            availableActions: @json($actions),

            /** @type {boolean} Dark mode active */
            dark: localStorage.getItem('wf-dark') === '1'
                || (!localStorage.getItem('wf-dark') && window.matchMedia('(prefers-color-scheme:dark)').matches),

            /** @type {Object|null} Currently active circuit */
            circuit: null,

            /** @type {Object|null} Currently selected basket in the sidebar */
            selectedBasket: null,

            /** @type {string|null} Which modal is open ('circuit', 'basket', 'msg', 'transition') */
            activeModal: null,

            /** @type {string|null} ID of the entity being edited (null = creating) */
            editingId: null,

            /** @type {boolean} Whether an async operation is in progress */
            isLoading: false,

            /** @type {Object} Validation errors keyed by field name */
            validationErrors: {},

            /** @type {Object} Toast notification state */
            toast: { on: false, msg: '', ok: true },

            /** @type {boolean} Whether the messages panel is visible */
            showMessages: false,

            // =================================================================
            // Diagram state
            // =================================================================

            NODE_WIDTH,
            NODE_HEIGHT,

            /** @type {Object} Basket positions keyed by basket ID { id: { x, y } } */
            nodePositions: {},

            /** @type {string|null} ID of the basket currently being dragged */
            draggedNodeId: null,

            /** @type {Object} Offset between mouse and node origin when drag started */
            dragOffset: { x: 0, y: 0 },

            /** @type {boolean} Whether the mouse actually moved during the current drag */
            hasDragged: false,

            /** @type {number} Live X position of the dragged node (not committed to nodePositions) */
            dragLiveX: 0,

            /** @type {number} Live Y position of the dragged node */
            dragLiveY: 0,

            /** @type {DOMRect|null} Cached canvas bounding rect to avoid reflow during drag */
            cachedCanvasRect: null,

            /** @type {Object|null} Basket being used as the source of a new link */
            linkSource: null,

            /** @type {number} Current mouse X position in canvas coordinates */
            mouseX: 0,

            /** @type {number} Current mouse Y position in canvas coordinates */
            mouseY: 0,

            /** @type {boolean} Whether the mouse is currently inside the canvas */
            isMouseInCanvas: false,

            /** @type {number} Canvas element width */
            canvasWidth: 800,

            /** @type {number} Canvas element height */
            canvasHeight: 600,

            /** @type {number|null} requestAnimationFrame ID */
            animationFrameId: null,

            /** @type {number} Animation progress (0 to 1, wraps around) */
            animationProgress: 0,

            /** @type {number} Current zoom level (0.3 to 2.0) */
            zoomLevel: 1,

            // =================================================================
            // Form state
            // =================================================================

            /** @type {Object} Circuit create/edit form data */
            circuitForm: { name: '', targetModel: '', description: '', roles: [] },

            /** @type {Object} Basket create/edit form data */
            basketForm: { name: '', status: '', color: '', circuit_id: '', roles: [], previous: [] },

            /** @type {Object} Message create form data */
            messageForm: { subject: '', content: '', type: '', recipient: '', circuit_id: '', basket_id: null },

            /** @type {Quill|null} Quill WYSIWYG editor instance */
            quillEditor: null,

            /** @type {Object} Transition config form data */
            transitionConfig: { from: null, to: null, label: '', actions: [] },

            /** @type {string} Selected basket ID for adding a link from the sidebar dropdown */
            sidebarLinkTarget: '',

            // =================================================================
            // Computed properties
            // =================================================================

            /** All baskets of the active circuit */
            get baskets() {
                return this.circuit?.baskets || [];
            },

            /** Roles defined on the active circuit */
            get circuitRoles() {
                return this.circuit?.roles || [];
            },

            /** Messages belonging to the active circuit */
            get circuitMessages() {
                return this.circuit?.messages || this.baskets.flatMap(b => b.messages || []);
            },

            /** Baskets that can be linked to from the selected basket (excludes self and existing links) */
            get availableTransitionTargets() {
                if (!this.selectedBasket) return [];
                const alreadyLinkedIds = new Set((this.selectedBasket.next || []).map(n => n.id));
                return this.baskets.filter(b => b.id !== this.selectedBasket.id && !alreadyLinkedIds.has(b.id));
            },

            // =================================================================
            // Helpers
            // =================================================================

            /**
             * Extract the hex color string from a value that may be an object (enum) or string.
             * param: {string|Object} colorValue - Color value or enum object
             * returns: {string} Hex color string
             */
            resolveColor(colorValue) {
                if (colorValue && typeof colorValue === 'object') return colorValue.value;
                return colorValue || '#64748b';
            },

            /**
             * Get the position of a basket node, accounting for live drag position.
             * param: {string} basketId
             * returns: { x: number, y: number }
             */
            getNodePosition(basketId) {
                if (this.draggedNodeId === basketId) {
                    return { x: this.dragLiveX, y: this.dragLiveY };
                }
                return this.nodePositions[basketId] || { x: 0, y: 0 };
            },

            /**
             * Toggle a value in an array (add if absent, remove if present).
             * param: {Array} array
             * param: {*} value
             */
            toggleArrayValue(array, value) {
                const index = array.indexOf(value);
                index === -1 ? array.push(value) : array.splice(index, 1);
            },

            /** Toggle dark mode and persist preference */
            toggleDark() {
                this.dark = !this.dark;
                localStorage.setItem('wf-dark', this.dark ? '1' : '0');
            },

            /**
             * Show a toast notification.
             * param: {string} message - Text to display
             * param: {boolean} [isSuccess=true] - Whether it's a success (true) or error (false)
             */
            showToast(message, isSuccess = true) {
                this.toast = { on: true, msg: message, ok: isSuccess };
                setTimeout(() => { this.toast.on = false; }, 3500);
            },

            /**
             * Safely parse a JSON string or return the array if already parsed.
             * param: {string|Array} value
             * returns: {Array}
             */
            parseActionsJson(value) {
                try {
                    if (typeof value === 'string') return JSON.parse(value);
                    if (Array.isArray(value)) return value;
                    return [];
                } catch {
                    return [];
                }
            },

            /**
             * Get the human-readable label for a transition action key.
             * param: {string} actionKey
             * returns: {string}
             */
            getActionLabel(actionKey) {
                return this.availableActions.find(a => a.key === actionKey)?.label || actionKey;
            },

            // =================================================================
            // Backward-compatible aliases (used in Blade templates)
            // =================================================================

            get sel() { return this.selectedBasket; },
            set sel(v) { this.selectedBasket = v; },
            get modal() { return this.activeModal; },
            set modal(v) { this.activeModal = v; },
            get editId() { return this.editingId; },
            set editId(v) { this.editingId = v; },
            get busy() { return this.isLoading; },
            set busy(v) { this.isLoading = v; },
            get errs() { return this.validationErrors; },
            set errs(v) { this.validationErrors = v; },
            get linking() { return this.linkSource; },
            set linking(v) { this.linkSource = v; },
            get drag() { return this.draggedNodeId; },
            get mx() { return this.mouseX; },
            get my() { return this.mouseY; },
            get NW() { return NODE_WIDTH; },
            get NH() { return NODE_HEIGHT; },
            get zoom() { return this.zoomLevel; },
            set zoom(v) { this.zoomLevel = v; },
            get canvasW() { return this.canvasWidth; },
            set canvasW(v) { this.canvasWidth = v; },
            get canvasH() { return this.canvasHeight; },
            set canvasH(v) { this.canvasHeight = v; },
            get animT() { return this.animationProgress; },
            get GRID() { return GRID_SIZE; },
            get cForm() { return this.circuitForm; },
            set cForm(v) { this.circuitForm = v; },
            get bForm() { return this.basketForm; },
            set bForm(v) { this.basketForm = v; },
            get mForm() { return this.messageForm; },
            set mForm(v) { this.messageForm = v; },
            get tConfig() { return this.transitionConfig; },
            set tConfig(v) { this.transitionConfig = v; },
            get linkTarget() { return this.sidebarLinkTarget; },
            set linkTarget(v) { this.sidebarLinkTarget = v; },
            get positions() { return this.nodePositions; },
            set positions(v) { this.nodePositions = v; },
            get quillInstance() { return this.quillEditor; },
            set quillInstance(v) { this.quillEditor = v; },
            get availTargets() { return this.availableTransitionTargets; },
            get dragMoved() { return this.hasDragged; },
            set dragMoved(v) { this.hasDragged = v; },
            get msgVars() { return this.messageVariables; },
            get mouseInCanvas() { return this.isMouseInCanvas; },
            set mouseInCanvas(v) { this.isMouseInCanvas = v; },
            get animFrame() { return this.animationFrameId; },
            set animFrame(v) { this.animationFrameId = v; },

            // Template-facing aliases for methods
            color(c) { return this.resolveColor(c); },
            pos(id) { return this.getNodePosition(id); },
            toggleArr(arr, v) { this.toggleArrayValue(arr, v); },
            notify(msg, ok = true) { this.showToast(msg, ok); },
            parseActions(v) { return this.parseActionsJson(v); },
            actionLabel(k) { return this.getActionLabel(k); },

            // =================================================================
            // API client
            // =================================================================

            /**
             * Make an authenticated API request to the admin backend.
             *
             * param: {string} method - HTTP method (GET, POST, PUT, DELETE)
             * param: {string} path - API path relative to the base URL
             * param: {Object|null} body - Request body (will be JSON-serialized)
             * returns: {Promise<Object|null>} Parsed JSON response or null for 204
             * throws: {Error} On non-2xx response with the server error message
             */
            async api(method, path, body = null) {
                this.validationErrors = {};

                const options = {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                    },
                };

                if (body) {
                    options.body = JSON.stringify(body);
                }

                const response = await fetch(API_BASE + path, options);

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));

                    if (errorData.errors) {
                        for (const [field, messages] of Object.entries(errorData.errors)) {
                            this.validationErrors[field] = Array.isArray(messages) ? messages[0] : messages;
                        }
                    }

                    const firstError = errorData.message
                        || Object.values(errorData.errors || {}).flat()[0]
                        || 'Erreur serveur';

                    throw new Error(firstError);
                }

                if (response.status === 204) return null;
                return response.json();
            },

            // =================================================================
            // WYSIWYG editor (Quill)
            // =================================================================

            /**
             * Initialize the Quill editor inside the given DOM element.
             * Syncs content bidirectionally with messageForm.content.
             * param: {HTMLElement} container
             */
            initQuill(container) {
                this.$nextTick(() => {
                    if (this.quillEditor) this.quillEditor = null;

                    const editor = new Quill(container, {
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

                    // Restore existing content
                    if (this.messageForm.content) {
                        editor.root.innerHTML = this.messageForm.content;
                    }

                    // Sync editor changes back to form state
                    editor.on('text-change', () => {
                        this.messageForm.content = editor.root.innerHTML;
                    });

                    this.quillEditor = editor;
                });
            },

            /**
             * Insert a template variable placeholder at the cursor position in Quill.
             * Falls back to appending at the end if no selection exists.
             * param: {string} variableKey - The variable name (e.g. 'user', 'date')
             */
            insertVariable(variableKey) {
                if (!this.quillEditor) return;

                this.quillEditor.focus();
                const selection = this.quillEditor.getSelection();
                const insertAt = selection ? selection.index : this.quillEditor.getLength() - 1;
                const placeholder = String.fromCharCode(123, 123) + ' ' + variableKey + ' ' + String.fromCharCode(125, 125);

                this.quillEditor.insertText(insertAt, placeholder);
                this.quillEditor.setSelection(insertAt + placeholder.length);
            },

            // =================================================================
            // Boot & circuit selection
            // =================================================================

            /** Initialize the app — select the first circuit if available */
            boot() {
                if (this.circuits.length) {
                    this.selectCircuit(this.circuits[0]);
                }
            },

            /**
             * Select a circuit, reset diagram state, and compute layout.
             * param: {Object} circuitData - Circuit object with baskets
             */
            selectCircuit(circuitData) {
                this.stopEdgeAnimation();
                this.circuit = circuitData;
                this.selectedBasket = null;
                this.linkSource = null;
                this.nodePositions = {};

                this.$nextTick(() => {
                    this.computeLayout(true);
                    this.$nextTick(() => this.drawEdges());
                });
            },

            // Alias used in templates
            pick(c) { this.selectCircuit(c); },

            // =================================================================
            // Layout engine (BFS-based column layout)
            // =================================================================

            /**
             * Compute basket positions using a BFS traversal from root nodes.
             * Root nodes (no predecessors) are placed in the first column,
             * their successors in the next, etc.
             *
             * param: {boolean} force - If true, recompute all positions. If false, only assign positions to new baskets.
             */
            computeLayout(force = false) {
                const allBaskets = this.baskets;
                if (!allBaskets.length) return;

                const columns = this._buildBfsColumns(allBaskets);
                const maxRowCount = Math.max(...columns.map(col => col.length));
                const colCount = columns.length;

                // Get available canvas size
                const canvas = this.$refs.canvas;
                const availW = canvas ? canvas.clientWidth - 40 : 800;
                const availH = canvas ? canvas.clientHeight - 40 : 600;

                const PADDING_X = 30;
                const PADDING_Y = 30;
                const usableW = availW - PADDING_X * 2;
                const usableH = availH - PADDING_Y * 2;

                // Compute gaps dynamically to fit the screen
                // Minimum gaps to keep things readable
                const MIN_GAP_X = 40;
                const MIN_GAP_Y = 20;
                const IDEAL_GAP_X = 140;
                const IDEAL_GAP_Y = 40;

                let gapX = colCount > 1
                    ? Math.max(MIN_GAP_X, Math.min(IDEAL_GAP_X, (usableW - colCount * NODE_WIDTH) / (colCount - 1)))
                    : IDEAL_GAP_X;

                let gapY = maxRowCount > 1
                    ? Math.max(MIN_GAP_Y, Math.min(IDEAL_GAP_Y, (usableH - maxRowCount * NODE_HEIGHT) / (maxRowCount - 1)))
                    : IDEAL_GAP_Y;

                // If it still doesn't fit, shrink the zoom to fit
                const totalW = PADDING_X * 2 + colCount * NODE_WIDTH + (colCount - 1) * gapX;
                const totalH = PADDING_Y * 2 + maxRowCount * NODE_HEIGHT + (maxRowCount - 1) * gapY;

                if (force && (totalW > availW || totalH > availH)) {
                    const fitZoom = Math.min(availW / totalW, availH / totalH, 1);
                    this.zoomLevel = Math.max(0.3, Math.round(fitZoom * 20) / 20); // Round to nearest 0.05
                }

                const newPositions = force ? {} : { ...this.nodePositions };

                columns.forEach((column, columnIndex) => {
                    const columnHeight = column.length * NODE_HEIGHT + (column.length - 1) * gapY;
                    const maxColumnHeight = maxRowCount * NODE_HEIGHT + (maxRowCount - 1) * gapY;
                    const verticalOffset = (maxColumnHeight - columnHeight) / 2;

                    column.forEach((basket, rowIndex) => {
                        if (force || !newPositions[basket.id]) {
                            newPositions[basket.id] = {
                                x: PADDING_X + columnIndex * (NODE_WIDTH + gapX),
                                y: PADDING_Y + verticalOffset + rowIndex * (NODE_HEIGHT + gapY),
                            };
                        }
                    });
                });

                this.nodePositions = newPositions;
                this.updateCanvasSize();
            },

            /**
             * Build columns of baskets via BFS traversal.
             * param: {Array} allBaskets
             * returns: {Array<Array>} Array of columns, each containing basket objects
             * private
             */
            _buildBfsColumns(allBaskets) {
                const visited = new Set();
                const columns = [];

                // Start with root nodes (baskets that have no predecessors)
                let currentLevel = allBaskets.filter(b => !(b.previous || []).length);
                if (!currentLevel.length) currentLevel = [allBaskets[0]];

                while (currentLevel.length) {
                    columns.push(currentLevel);
                    currentLevel.forEach(b => visited.add(b.id));

                    const nextLevelIds = new Set();
                    currentLevel.forEach(basket => {
                        (basket.next || []).forEach(successor => {
                            if (!visited.has(successor.id)) {
                                nextLevelIds.add(successor.id);
                            }
                        });
                    });

                    currentLevel = allBaskets.filter(b => nextLevelIds.has(b.id));
                }

                // Add any unlinked baskets as a final column
                const unlinked = allBaskets.filter(b => !visited.has(b.id));
                if (unlinked.length) columns.push(unlinked);

                return columns;
            },

            /** Alias for template */
            layout(force) { this.computeLayout(force); },

            /** Reset all positions and recompute */
            autoLayout() { this.computeLayout(true); },

            /** Update canvas dimensions to fit all nodes */
            updateCanvasSize() {
                const allX = Object.values(this.nodePositions).map(p => p.x);
                const allY = Object.values(this.nodePositions).map(p => p.y);
                if (!allX.length) return;

                this.canvasWidth = Math.max(800, Math.max(...allX) + NODE_WIDTH + 80);
                this.canvasHeight = Math.max(600, Math.max(...allY) + NODE_HEIGHT + 80);
                this.$nextTick(() => this.drawEdges());
            },

            /** Alias for template */
            resize() { this.updateCanvasSize(); },

            /**
             * Set the zoom level, clamped between 0.3 and 2.0.
             * param: {number} value
             */
            setZoom(value) {
                this.zoomLevel = Math.max(0.3, Math.min(2, value));
            },

            /**
             * Handle mouse wheel for zooming.
             * param: {WheelEvent} event
             */
            onWheel(event) {
                const delta = event.deltaY < 0 ? 0.05 : -0.05;
                this.setZoom(this.zoomLevel + delta);
            },

            // =================================================================
            // Drag & Drop
            // =================================================================

            /**
             * Begin dragging a basket node.
             * Caches the canvas rect and computes the mouse-to-node offset.
             *
             * param: {MouseEvent} event
             * param: {Object} basket - The basket being dragged
             */
            startDrag(event, basket) {
                if (this.linkSource) return; // Don't drag while linking

                const canvas = this.$refs.canvas;
                const currentPosition = this.nodePositions[basket.id];

                this.cachedCanvasRect = canvas.getBoundingClientRect();
                this.draggedNodeId = basket.id;
                this.hasDragged = false;
                this.dragLiveX = currentPosition.x;
                this.dragLiveY = currentPosition.y;
                this.dragOffset = {
                    x: (event.clientX - this.cachedCanvasRect.left + canvas.scrollLeft) / this.zoomLevel - currentPosition.x,
                    y: (event.clientY - this.cachedCanvasRect.top + canvas.scrollTop) / this.zoomLevel - currentPosition.y,
                };
            },

            /**
             * Handle mouse movement on the canvas.
             * Updates drag position or linking preview line.
             *
             * param: {MouseEvent} event
             */
            onMove(event) {
                const canvas = this.$refs.canvas;
                const rect = this.cachedCanvasRect || canvas.getBoundingClientRect();

                this.mouseX = (event.clientX - rect.left + canvas.scrollLeft) / this.zoomLevel;
                this.mouseY = (event.clientY - rect.top + canvas.scrollTop) / this.zoomLevel;
                this.isMouseInCanvas = true;

                if (this.draggedNodeId) {
                    const newX = Math.max(0, this.mouseX - this.dragOffset.x);
                    const newY = Math.max(0, this.mouseY - this.dragOffset.y);

                    // Only start dragging after passing the threshold
                    if (!this.hasDragged) {
                        const movedEnough = Math.abs(newX - this.dragLiveX) >= DRAG_THRESHOLD
                                         || Math.abs(newY - this.dragLiveY) >= DRAG_THRESHOLD;
                        if (!movedEnough) return;
                        this.hasDragged = true;
                    }

                    // Update live position (edges follow via getNodePosition)
                    this.dragLiveX = newX;
                    this.dragLiveY = newY;
                }

                // Redraw edges during linking to show the preview line
                if (this.linkSource) this.drawEdges();
            },

            /**
             * Handle mouse release on the canvas.
             * Commits the drag position with grid snapping.
             */
            onUp() {
                if (this.draggedNodeId) {
                    // Snap to grid on drop
                    const snappedX = Math.round(this.dragLiveX / GRID_SIZE) * GRID_SIZE;
                    const snappedY = Math.round(this.dragLiveY / GRID_SIZE) * GRID_SIZE;

                    this.nodePositions = {
                        ...this.nodePositions,
                        [this.draggedNodeId]: { x: snappedX, y: snappedY },
                    };

                    this.draggedNodeId = null;
                    this.cachedCanvasRect = null;
                    this.updateCanvasSize();
                }
            },

            // =================================================================
            // Visual linking (creating transitions by dragging between nodes)
            // =================================================================

            /**
             * Start a visual link from a basket's output port.
             * param: {Object} sourceBasket
             */
            startLink(sourceBasket) {
                this.linkSource = sourceBasket;
                this.hasDragged = false;
            },

            /**
             * Complete a visual link by connecting to a target basket.
             * Called when mouse is released on the input port or when the card is clicked.
             *
             * param: {Object} targetBasket
             */
            completeLink(targetBasket) {
                if (!this.linkSource) return;

                // Cancel if linking to self
                if (this.linkSource.id === targetBasket.id) {
                    this.linkSource = null;
                    this.drawEdges();
                    return;
                }

                // Prevent duplicate links
                const alreadyLinked = (this.linkSource.next || []).some(n => n.id === targetBasket.id);
                if (alreadyLinked) {
                    this.showToast('Lien déjà existant', false);
                    this.linkSource = null;
                    this.drawEdges();
                    return;
                }

                this.createTransition(this.linkSource, targetBasket);
            },

            /**
             * Handle click on a basket node.
             * During linking: completes the link. Otherwise: selects the basket.
             *
             * param: {Object} basket
             */
            onNodeClick(basket) {
                if (this.hasDragged) return;

                if (this.linkSource) {
                    this.completeLink(basket);
                    return;
                }

                this.selectedBasket = basket;
                this.$nextTick(() => this.drawEdges());
            },

            /**
             * Create a transition between two baskets via the API.
             * param: {Object} fromBasket
             * param: {Object} toBasket
             */
            async createTransition(fromBasket, toBasket) {
                const sourceId = fromBasket.id;
                try {
                    const previousIds = [...(toBasket.previous || []).map(p => p.id), sourceId];
                    await this.api('PUT', '/baskets/' + toBasket.id, {
                        name: toBasket.name,
                        status: toBasket.status,
                        color: this.resolveColor(toBasket.color),
                        circuit_id: this.circuit.id,
                        previous: previousIds,
                        roles: toBasket.roles || [],
                    });
                    this.linkSource = null;
                    await this.refreshBaskets();
                    this.selectedBasket = this.baskets.find(b => b.id === sourceId) || null;
                    this.showToast('Transition créée');
                } catch (error) {
                    this.showToast(error.message, false);
                    this.linkSource = null;
                }
            },

            /** Alias for template */
            async createLink(from, to) { return this.createTransition(from, to); },

            /**
             * Remove a transition between two baskets.
             * param: {Object} fromBasket
             * param: {Object} toBasket
             */
            async removeLink(fromBasket, toBasket) {
                try {
                    const remainingPrevious = (toBasket.previous || [])
                        .map(p => p.id)
                        .filter(id => id !== fromBasket.id);

                    await this.api('PUT', '/baskets/' + toBasket.id, {
                        name: toBasket.name,
                        status: toBasket.status,
                        color: this.resolveColor(toBasket.color),
                        circuit_id: this.circuit.id,
                        previous: remainingPrevious,
                        roles: toBasket.roles || [],
                    });
                    await this.refreshBaskets();
                    this.selectedBasket = this.baskets.find(b => b.id === fromBasket.id) || null;
                    this.showToast('Transition supprimée');
                } catch (error) {
                    this.showToast(error.message, false);
                }
            },

            /** Add a link from the sidebar dropdown */
            async addLink() {
                if (!this.sidebarLinkTarget || !this.selectedBasket) return;
                const target = this.baskets.find(b => b.id === this.sidebarLinkTarget);
                if (!target) return;
                await this.createTransition(this.selectedBasket, target);
                this.sidebarLinkTarget = '';
            },

            // =================================================================
            // Edge rendering (HTML Canvas 2D + requestAnimationFrame)
            // =================================================================

            /**
             * Compute a point on a cubic Bézier curve at parameter t.
             * param: {number} x1 - Start X
             * param: {number} y1 - Start Y
             * param: {number} cx1 - Control point 1 X
             * param: {number} cy1 - Control point 1 Y
             * param: {number} cx2 - Control point 2 X
             * param: {number} cy2 - Control point 2 Y
             * param: {number} x2 - End X
             * param: {number} y2 - End Y
             * param: {number} t - Parameter (0 = start, 1 = end)
             * returns: { x: number, y: number }
             */
            bezierPoint(x1, y1, cx1, cy1, cx2, cy2, x2, y2, t) {
                const u = 1 - t;
                return {
                    x: u*u*u*x1 + 3*u*u*t*cx1 + 3*u*t*t*cx2 + t*t*t*x2,
                    y: u*u*u*y1 + 3*u*u*t*cy1 + 3*u*t*t*cy2 + t*t*t*y2,
                };
            },

            /** Alias for template */
            bezierPt(...args) { return this.bezierPoint(...args); },

            /** Start the edge animation loop (60fps) */
            startEdgeAnimation() {
                if (this.animationFrameId) return;

                const loop = () => {
                    this.animationProgress = (this.animationProgress + ANIM_SPEED) % 1;
                    this.renderAllEdges();
                    this.animationFrameId = requestAnimationFrame(loop);
                };
                this.animationFrameId = requestAnimationFrame(loop);
            },

            /** Alias for template */
            startAnimation() { this.startEdgeAnimation(); },

            /** Stop the edge animation loop */
            stopEdgeAnimation() {
                if (this.animationFrameId) {
                    cancelAnimationFrame(this.animationFrameId);
                    this.animationFrameId = null;
                }
            },

            /** Alias for template */
            stopAnimation() { this.stopEdgeAnimation(); },

            /** Render edges once and start the animation if there are edges to draw */
            drawEdges() {
                this.renderAllEdges();
                const hasEdges = this.baskets.some(b => (b.next || []).length);
                if (!this.animationFrameId && hasEdges) {
                    this.startEdgeAnimation();
                }
            },

            /**
             * Render all edges on the canvas.
             * Draws Bézier curves with shadows, flowing dots, arrows, and labels.
             */
            renderAllEdges() {
                const canvas = this.$refs.edgeCanvas;
                if (!canvas || !canvas.getContext) return;
                const ctx = canvas.getContext('2d');
                if (!ctx) return;
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                if (!Object.keys(this.nodePositions).length) return;

                const isDark = this.dark;

                // Color palette (theme-aware)
                const colors = {
                    line:        isDark ? 'rgba(129,140,248,0.6)' : 'rgba(99,102,241,0.4)',
                    lineSelected: isDark ? '#fbbf24' : '#4f46e5',
                    dot:         isDark ? '#a5b4fc' : '#6366f1',
                    dotSelected: isDark ? '#fbbf24' : '#4338ca',
                    flow:        isDark ? 'rgba(165,180,252,0.8)' : 'rgba(99,102,241,0.7)',
                    flowSelected: isDark ? 'rgba(251,191,36,0.9)' : 'rgba(79,70,229,0.9)',
                    labelBg:     isDark ? 'rgba(31,41,55,0.85)' : 'rgba(255,255,255,0.9)',
                    labelText:   isDark ? '#d1d5db' : '#4b5563',
                    shadow:      isDark ? 'rgba(0,0,0,0.3)' : 'rgba(0,0,0,0.06)',
                };

                // Draw each transition edge
                this.baskets.forEach(basket => {
                    (basket.next || []).forEach(successor => {
                        const fromPos = this.getNodePosition(basket.id);
                        const toPos = this.getNodePosition(successor.id);
                        if (!fromPos || !toPos) return;

                        const isSelected = this.selectedBasket
                            && (this.selectedBasket.id === basket.id || this.selectedBasket.id === successor.id);

                        this._drawEdge(ctx, fromPos, toPos, isSelected, successor.pivot?.label, colors);
                    });
                });

                // Draw the temporary linking preview line
                if (this.linkSource && this.isMouseInCanvas) {
                    this._drawLinkingPreview(ctx, isDark);
                }
            },

            /** Alias for template */
            renderEdges() { this.renderAllEdges(); },

            /**
             * Draw a single edge between two positions.
             * param: {CanvasRenderingContext2D} ctx
             * param: { x: number, y: number } fromPos
             * param: { x: number, y: number } toPos
             * param: {boolean} isSelected
             * param: {string|null} label
             * param: {Object} colors - Color palette
             * private
             */
            _drawEdge(ctx, fromPos, toPos, isSelected, label, colors) {
                // Compute Bézier control points
                const x1 = fromPos.x + NODE_WIDTH;
                const y1 = fromPos.y + NODE_HEIGHT / 2;
                const x2 = toPos.x;
                const y2 = toPos.y + NODE_HEIGHT / 2;
                const curvature = Math.max(Math.abs(x2 - x1) * 0.5, 70);
                const cx1 = x1 + curvature, cy1 = y1;
                const cx2 = x2 - curvature, cy2 = y2;

                // Shadow
                ctx.beginPath();
                ctx.strokeStyle = colors.shadow;
                ctx.lineWidth = isSelected ? 6 : 4;
                ctx.moveTo(x1, y1);
                ctx.bezierCurveTo(cx1, cy1, cx2, cy2, x2, y2);
                ctx.stroke();

                // Main curve
                ctx.beginPath();
                ctx.strokeStyle = isSelected ? colors.lineSelected : colors.line;
                ctx.lineWidth = isSelected ? 2.5 : 1.8;
                ctx.moveTo(x1, y1);
                ctx.bezierCurveTo(cx1, cy1, cx2, cy2, x2, y2);
                ctx.stroke();

                // Flowing dots
                for (let i = 0; i < FLOW_DOT_COUNT; i++) {
                    const t = (this.animationProgress + i / FLOW_DOT_COUNT) % 1;
                    const point = this.bezierPoint(x1, y1, cx1, cy1, cx2, cy2, x2, y2, t);
                    ctx.beginPath();
                    ctx.fillStyle = isSelected ? colors.flowSelected : colors.flow;
                    ctx.arc(point.x, point.y, isSelected ? 3.5 : 2.5, 0, Math.PI * 2);
                    ctx.fill();
                }

                // Source dot
                ctx.fillStyle = isSelected ? colors.dotSelected : colors.dot;
                ctx.beginPath();
                ctx.arc(x1, y1, isSelected ? 5 : 4, 0, Math.PI * 2);
                ctx.fill();

                // Arrow at target
                const arrowPoint = this.bezierPoint(x1, y1, cx1, cy1, cx2, cy2, x2, y2, 0.97);
                const arrowAngle = Math.atan2(y2 - arrowPoint.y, x2 - arrowPoint.x);
                const arrowSize = isSelected ? 8 : 6;
                ctx.beginPath();
                ctx.fillStyle = isSelected ? colors.dotSelected : colors.dot;
                ctx.moveTo(x2, y2);
                ctx.lineTo(x2 - arrowSize * Math.cos(arrowAngle - 0.4), y2 - arrowSize * Math.sin(arrowAngle - 0.4));
                ctx.lineTo(x2 - arrowSize * Math.cos(arrowAngle + 0.4), y2 - arrowSize * Math.sin(arrowAngle + 0.4));
                ctx.closePath();
                ctx.fill();

                // Label badge at midpoint
                if (label) {
                    this._drawEdgeLabel(ctx, x1, y1, cx1, cy1, cx2, cy2, x2, y2, label, colors);
                }
            },

            /**
             * Draw a label badge at the midpoint of an edge.
             * private
             */
            _drawEdgeLabel(ctx, x1, y1, cx1, cy1, cx2, cy2, x2, y2, label, colors) {
                const midpoint = this.bezierPoint(x1, y1, cx1, cy1, cx2, cy2, x2, y2, 0.5);
                ctx.font = '600 10px system-ui,sans-serif';
                const textWidth = ctx.measureText(label).width;
                const padX = 4, padY = 2, radius = 6;
                const rx = midpoint.x - textWidth / 2 - padX;
                const ry = midpoint.y - 7 - padY;
                const rw = textWidth + padX * 2;
                const rh = 14 + padY * 2;

                // Rounded rectangle background
                ctx.fillStyle = colors.labelBg;
                ctx.beginPath();
                ctx.moveTo(rx + radius, ry);
                ctx.lineTo(rx + rw - radius, ry);
                ctx.quadraticCurveTo(rx + rw, ry, rx + rw, ry + radius);
                ctx.lineTo(rx + rw, ry + rh - radius);
                ctx.quadraticCurveTo(rx + rw, ry + rh, rx + rw - radius, ry + rh);
                ctx.lineTo(rx + radius, ry + rh);
                ctx.quadraticCurveTo(rx, ry + rh, rx, ry + rh - radius);
                ctx.lineTo(rx, ry + radius);
                ctx.quadraticCurveTo(rx, ry, rx + radius, ry);
                ctx.closePath();
                ctx.fill();

                // Label text
                ctx.fillStyle = colors.labelText;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(label, midpoint.x, midpoint.y);
            },

            /**
             * Draw the temporary dashed line while creating a link.
             * param: {CanvasRenderingContext2D} ctx
             * param: {boolean} isDark
             * private
             */
            _drawLinkingPreview(ctx, isDark) {
                const sourcePos = this.getNodePosition(this.linkSource.id);
                if (!sourcePos) return;

                const x1 = sourcePos.x + NODE_WIDTH;
                const y1 = sourcePos.y + NODE_HEIGHT / 2;
                const x2 = this.mouseX;
                const y2 = this.mouseY;
                const curvature = Math.max(Math.abs(x2 - x1) * 0.4, 40);

                // Glow
                ctx.beginPath();
                ctx.strokeStyle = isDark ? 'rgba(139,92,246,0.2)' : 'rgba(139,92,246,0.15)';
                ctx.lineWidth = 8;
                ctx.moveTo(x1, y1);
                ctx.bezierCurveTo(x1 + curvature, y1, x2 - curvature, y2, x2, y2);
                ctx.stroke();

                // Dashed line
                ctx.beginPath();
                ctx.strokeStyle = '#a78bfa';
                ctx.lineWidth = 2;
                ctx.setLineDash([8, 5]);
                ctx.moveTo(x1, y1);
                ctx.bezierCurveTo(x1 + curvature, y1, x2 - curvature, y2, x2, y2);
                ctx.stroke();
                ctx.setLineDash([]);

                // Pulsing source dot
                const pulseRadius = Math.sin(this.animationProgress * Math.PI * 2) * 2 + 5;
                ctx.beginPath();
                ctx.fillStyle = 'rgba(167,139,250,0.3)';
                ctx.arc(x1, y1, pulseRadius, 0, Math.PI * 2);
                ctx.fill();

                // Solid dots at source and cursor
                ctx.beginPath();
                ctx.fillStyle = '#a78bfa';
                ctx.arc(x1, y1, 4, 0, Math.PI * 2);
                ctx.fill();
                ctx.beginPath();
                ctx.arc(x2, y2, 4, 0, Math.PI * 2);
                ctx.fill();
            },

            // =================================================================
            // Data refresh
            // =================================================================

            /** Reload baskets for the current circuit from the API */
            async refreshBaskets() {
                try {
                    const baskets = await this.api('GET', '/circuits/' + this.circuit.id + '/baskets');
                    const circuitIndex = this.circuits.findIndex(c => c.id === this.circuit.id);

                    if (circuitIndex !== -1) {
                        this.circuits[circuitIndex].baskets = baskets;
                        this.circuit = { ...this.circuits[circuitIndex] };
                    }

                    // Remove positions for deleted baskets, layout new ones
                    const currentIds = new Set(this.baskets.map(b => b.id));
                    for (const key of Object.keys(this.nodePositions)) {
                        if (!currentIds.has(key)) delete this.nodePositions[key];
                    }

                    this.computeLayout(false);
                    this.$nextTick(() => this.drawEdges());
                } catch (error) {
                    console.error('Failed to refresh baskets:', error);
                }
            },

            /** Alias for template */
            async refresh() { return this.refreshBaskets(); },

            // =================================================================
            // Circuit CRUD
            // =================================================================

            openCircuitModal() {
                this.editingId = null;
                this.circuitForm = { name: '', targetModel: '', description: '', roles: [] };
                this.validationErrors = {};
                this.activeModal = 'circuit';
            },

            editCircuit() {
                this.editingId = this.circuit.id;
                this.circuitForm = {
                    name: this.circuit.name,
                    targetModel: this.circuit.targetModel,
                    description: this.circuit.description || '',
                    roles: [...(this.circuit.roles || [])],
                };
                this.validationErrors = {};
                this.activeModal = 'circuit';
            },

            /** Add a role to the circuit form from the input field */
            addCR() {
                const value = this.$refs.crI.value.trim();
                if (value && !this.circuitForm.roles.includes(value)) {
                    this.circuitForm.roles.push(value);
                }
                this.$refs.crI.value = '';
            },

            async saveCircuit() {
                this.isLoading = true;
                try {
                    if (this.editingId) {
                        await this.api('PUT', '/circuits/' + this.editingId, this.circuitForm);
                        const index = this.circuits.findIndex(c => c.id === this.editingId);
                        if (index !== -1) {
                            Object.assign(this.circuits[index], this.circuitForm);
                            this.circuit = { ...this.circuits[index] };
                        }
                        this.showToast('Circuit mis à jour');
                    } else {
                        const response = await this.api('POST', '/circuits', this.circuitForm);
                        const created = response.circuit?.data || response.circuit || response.data || response;

                        // Reload the circuit with its auto-created DRAFT basket
                        const fullCircuit = await this.api('GET', '/circuits/' + created.id);
                        const circuit = fullCircuit.data || fullCircuit;
                        circuit.baskets = await this.api('GET', '/circuits/' + created.id + '/baskets') || [];
                        circuit.messages = [];

                        this.circuits.push(circuit);
                        this.selectCircuit(circuit);
                        this.showToast('Circuit créé');
                    }
                    this.activeModal = null;
                } catch (error) {
                    this.showToast(error.message, false);
                }
                this.isLoading = false;
            },

            async deleteCircuit() {
                if (!confirm('Supprimer ce circuit ?')) return;
                try {
                    await this.api('DELETE', '/circuits/' + this.circuit.id);
                    this.circuits = this.circuits.filter(c => c.id !== this.circuit.id);
                    this.circuit = this.circuits[0] || null;
                    this.selectedBasket = null;
                    this.nodePositions = {};
                    this.showToast('Supprimé');
                } catch (error) {
                    this.showToast(error.message, false);
                }
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
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'workflow-' + this.circuit.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '.json';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                    this.showToast('Circuit exporté');
                } catch (error) {
                    this.showToast(error.message, false);
                }
            },

            async importCircuit(event) {
                const file = event.target.files[0];
                if (!file) return;
                event.target.value = '';

                const formData = new FormData();
                formData.append('file', file);

                try {
                    const response = await fetch(API_BASE + '/circuits/import', {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                        body: formData,
                    });

                    if (!response.ok) {
                        const error = await response.json().catch(() => ({}));
                        throw new Error(error.message || 'Erreur import');
                    }

                    const circuit = await response.json();
                    circuit.baskets = circuit.baskets || [];
                    circuit.messages = circuit.messages || [];
                    this.circuits.push(circuit);
                    this.selectCircuit(circuit);
                    this.showToast('Circuit importé');
                } catch (error) {
                    this.showToast(error.message, false);
                }
            },

            exportImage() {
                @include('workflow::partials.export-image-js')
            },

            // =================================================================
            // Basket CRUD
            // =================================================================

            openBasketModal() {
                this.editingId = null;
                this.basketForm = {
                    name: '', status: '',
                    color: this.colors[0]?.value || '#64748b',
                    circuit_id: this.circuit.id,
                    roles: [], previous: [],
                };
                this.validationErrors = {};
                this.activeModal = 'basket';
            },

            editBasket(basket) {
                this.editingId = basket.id;
                this.basketForm = {
                    name: basket.name,
                    status: basket.status,
                    color: this.resolveColor(basket.color),
                    circuit_id: this.circuit.id,
                    roles: [...(basket.roles || [])],
                    previous: (basket.previous || []).map(p => p.id),
                };
                this.validationErrors = {};
                this.activeModal = 'basket';
            },

            async saveBasket() {
                this.isLoading = true;
                try {
                    const payload = { ...this.basketForm, circuit_id: this.circuit.id };

                    if (this.editingId) {
                        await this.api('PUT', '/baskets/' + this.editingId, payload);
                        this.showToast('Panier mis à jour');
                    } else {
                        await this.api('POST', '/baskets', payload);
                        this.showToast('Panier créé');
                    }

                    await this.refreshBaskets();
                    this.activeModal = null;
                    this.selectedBasket = null;
                } catch (error) {
                    this.showToast(error.message, false);
                }
                this.isLoading = false;
            },

            async deleteBasket(basket) {
                if (!confirm('Supprimer "' + basket.name + '" ?')) return;
                try {
                    await this.api('DELETE', '/baskets/' + basket.id);
                    delete this.nodePositions[basket.id];
                    await this.refreshBaskets();
                    if (this.selectedBasket?.id === basket.id) this.selectedBasket = null;
                    this.showToast('Supprimé');
                } catch (error) {
                    this.showToast(error.message, false);
                }
            },

            // =================================================================
            // Message CRUD
            // =================================================================

            openMsgModal() {
                this.messageForm = {
                    subject: '', content: '',
                    type: this.msgTypes[0]?.value || 'email',
                    recipient: this.recipients[0]?.value || 'subject',
                    circuit_id: this.circuit.id,
                };
                this.quillEditor = null;
                this.activeModal = 'msg';
            },

            async saveMsg() {
                const isEmpty = !this.messageForm.content || this.messageForm.content === '<p><br></p>';
                if (isEmpty) {
                    this.showToast('Le contenu est obligatoire', false);
                    return;
                }

                this.isLoading = true;
                try {
                    await this.api('POST', '/circuits/' + this.circuit.id + '/messages', this.messageForm);
                    await this.refreshMessages();
                    this.activeModal = null;
                    this.showToast('Message créé');
                } catch (error) {
                    this.showToast(error.message, false);
                }
                this.isLoading = false;
            },

            async deleteMsg(message) {
                if (!confirm('Supprimer ?')) return;
                try {
                    await this.api('DELETE', '/circuits/' + this.circuit.id + '/messages/' + message.id);
                    await this.refreshMessages();
                    this.showToast('Supprimé');
                } catch (error) {
                    this.showToast(error.message, false);
                }
            },

            /** Reload messages for the current circuit */
            async refreshMessages() {
                try {
                    const messages = await this.api('GET', '/circuits/' + this.circuit.id + '/messages');
                    const index = this.circuits.findIndex(c => c.id === this.circuit.id);
                    if (index !== -1) {
                        this.circuits[index].messages = messages;
                        this.circuit = { ...this.circuits[index] };
                    }
                } catch (error) {
                    console.error('Failed to refresh messages:', error);
                }
            },

            // =================================================================
            // Transition configuration
            // =================================================================

            /**
             * Open the transition config modal for a specific link.
             * param: {Object} fromBasket
             * param: {Object} toBasket
             */
            openTransitionConfig(fromBasket, toBasket) {
                const pivot = toBasket.pivot || {};
                this.transitionConfig = {
                    from: fromBasket,
                    to: toBasket,
                    label: pivot.label || '',
                    actions: this.parseActionsJson(pivot.actions),
                };
                this.activeModal = 'transition';
            },

            /**
             * Add a new action to the transition config.
             * param: {string} actionKey
             */
            addTransitionAction(actionKey) {
                this.transitionConfig.actions.push({ type: actionKey, config: {} });
            },

            /** Save the transition config (label + actions) to the API */
            async saveTransitionConfig() {
                this.isLoading = true;
                try {
                    const { from, to, label, actions } = this.transitionConfig;
                    await this.api('PUT', '/transitions/' + from.id + '/' + to.id, {
                        label: label || null,
                        actions,
                    });
                    this.activeModal = null;
                    await this.refreshBaskets();
                    if (this.selectedBasket) {
                        this.selectedBasket = this.baskets.find(b => b.id === this.selectedBasket.id) || null;
                    }
                    this.showToast('Transition configurée');
                } catch (error) {
                    this.showToast(error.message, false);
                }
                this.isLoading = false;
            },

        };
    }
    </script>
</body>
</html>

<?php

namespace Maestrodimateo\Workflow\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maestrodimateo\Workflow\Enums\AllowedBasketColors;
use Maestrodimateo\Workflow\Enums\MessageType;
use Maestrodimateo\Workflow\Enums\RecipientType;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Services\MessageVariableResolver;
use Maestrodimateo\Workflow\Traits\Workflowable;
use Maestrodimateo\Workflow\WorkflowManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WorkflowAdminController
{
    public function __invoke(): View
    {
        $actions = collect(WorkflowManager::getRegisteredActions())
            ->map(fn ($class, $key) => [
                'key' => $key,
                'label' => $class::label(),
                // Target models this action is limited to; empty = transversal.
                'models' => WorkflowManager::actionModels($class),
            ])
            ->values();

        $conditions = collect(WorkflowManager::getRegisteredConditions())
            ->map(fn ($class, $key) => [
                'key' => $key,
                'label' => $class::label(),
                'models' => WorkflowManager::actionModels($class),
            ])
            ->values();

        return view('workflow::app', [
            'circuits' => Circuit::with('baskets.next', 'baskets.previous', 'baskets.messages', 'messages')->get(),
            'colors' => collect(AllowedBasketColors::cases())->map(fn ($c) => ['name' => $c->name, 'value' => $c->value]),
            'msgTypes' => collect(MessageType::cases())->map(fn ($c) => ['name' => $c->name, 'value' => $c->value]),
            'recipients' => collect(RecipientType::cases())->map(fn ($c) => ['name' => $c->name, 'value' => $c->value]),
            'actions' => $actions,
            'conditions' => $conditions,
            'variables' => MessageVariableResolver::availableKeys(),
            'apiPrefix' => './admin/api',
            'workflowableModels' => $this->discoverWorkflowableModels(),
            'configuredRoles' => config('workflow.roles', []),
        ]);
    }

    public function baskets(Circuit $circuit): JsonResponse
    {
        return response()->json(
            $circuit->baskets()->with(['next', 'previous'])->get()
        );
    }

    public function messages(Circuit $circuit): JsonResponse
    {
        return response()->json($circuit->messages()->get());
    }

    /**
     * Persist the canvas positions of a circuit's baskets (shared per circuit).
     *
     * Body: { positions: { "<basketId>": { x: number, y: number }, ... } }.
     * Only baskets belonging to the given circuit are updated; unknown ids are
     * ignored so a stale client can never write positions onto another circuit.
     */
    public function positions(Request $request, Circuit $circuit): JsonResponse
    {
        $data = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*.x' => ['required', 'numeric'],
            'positions.*.y' => ['required', 'numeric'],
        ]);

        $ownIds = $circuit->baskets()->pluck('id')->all();

        foreach ($data['positions'] as $id => $pos) {
            if (in_array($id, $ownIds, true)) {
                Basket::whereKey($id)->update([
                    'position' => ['x' => (float) $pos['x'], 'y' => (float) $pos['y']],
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function updateTransition(Request $request, Basket $from, Basket $to): JsonResponse
    {
        // Prevent cross-circuit transitions: both baskets must belong to the same circuit.
        abort_unless($from->circuit_id === $to->circuit_id, 404);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'actions' => ['nullable', 'array'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.config' => ['nullable', 'array'],
            'conditions' => ['nullable', 'array'],
            'conditions.*.type' => ['required', 'string'],
            'conditions.*.config' => ['nullable', 'array'],
        ]);

        // Reject actions/conditions limited to another workflow: a scoped item may
        // only be attached to a transition whose circuit targets its declared model.
        $targetModel = $from->circuit->targetModel;
        $registered = WorkflowManager::getRegisteredActions();
        $registeredConditions = WorkflowManager::getRegisteredConditions();

        foreach ($data['actions'] ?? [] as $action) {
            $class = $registered[$action['type']] ?? null;

            abort_if(
                $class !== null && ! WorkflowManager::actionAllowsModel($class, $targetModel),
                422,
                "Action [{$action['type']}] is not available for this workflow.",
            );
        }

        foreach ($data['conditions'] ?? [] as $condition) {
            $class = $registeredConditions[$condition['type']] ?? null;

            abort_if(
                $class !== null && ! WorkflowManager::actionAllowsModel($class, $targetModel),
                422,
                "Condition [{$condition['type']}] is not available for this workflow.",
            );
        }

        $from->next()->updateExistingPivot($to->id, [
            'label' => $data['label'] ?? null,
            'actions' => json_encode($data['actions'] ?? []),
            'conditions' => json_encode($data['conditions'] ?? []),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Export a circuit as a portable JSON file.
     */
    public function export(Circuit $circuit): JsonResponse
    {
        $circuit->load(['baskets.next', 'baskets.messages', 'messages']);

        $baskets = $circuit->baskets->map(fn (Basket $b) => [
            '_ref' => $b->id,
            'name' => $b->name,
            'status' => $b->status,
            'color' => $b->getRawOriginal('color'),
            'roles' => $b->roles ?? [],
            'visitor_roles' => $b->visitor_roles ?? [],
            'transitions' => $b->next->map(fn (Basket $n) => [
                '_to_ref' => $n->id,
                'label' => $n->pivot->label,
                'actions' => json_decode($n->pivot->actions ?? '[]', true),
                'conditions' => json_decode($n->pivot->conditions ?? '[]', true),
            ])->values()->all(),
        ]);

        $messages = $circuit->messages->map(fn ($m) => [
            'subject' => $m->subject,
            'content' => $m->content,
            'type' => $m->getRawOriginal('type'),
            'recipient' => $m->getRawOriginal('recipient'),
            '_basket_ref' => $m->basket_id,
        ]);

        $payload = [
            '_format' => 'laravel-workflow/v1',
            'circuit' => [
                'name' => $circuit->name,
                'targetModel' => $circuit->targetModel,
                'description' => $circuit->description,
                'roles' => $circuit->roles ?? [],
            ],
            'baskets' => $baskets->values()->all(),
            'messages' => $messages->values()->all(),
        ];

        return response()->json($payload, headers: [
            'Content-Disposition' => 'attachment; filename="workflow-'.str($circuit->name)->slug().'.json"',
        ]);
    }

    /**
     * Import a circuit from a JSON payload.
     *
     * @throws Throwable
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:json,txt', 'max:2048'],
        ]);

        // Write uploaded file to a temp path so importFromJson can read it
        $tempPath = tempnam(sys_get_temp_dir(), 'workflow_import_');
        file_put_contents($tempPath, $request->file('file')->get());

        try {
            $circuit = WorkflowManager::importFromJson($tempPath);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            @unlink($tempPath);
        }

        return response()->json($circuit, 201);
    }

    /**
     * Scan configured directories for classes using the Workflowable trait.
     * Returns each model's FQCN, short label, and database columns.
     */
    private function discoverWorkflowableModels(): array
    {
        /** @var array<string, string> $paths */
        $paths = config('workflow.model_paths', ['app/Models' => 'App\\Models']);

        return collect($paths)
            ->flatMap(function (string $namespace, string $directory) {
                $path = base_path($directory);

                if (! is_dir($path)) {
                    return [];
                }

                return collect(File::allFiles($path))
                    ->map(fn ($file) => $namespace.'\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()));
            })
            ->filter(fn ($class) => class_exists($class))
            ->filter(fn ($class) => in_array(Workflowable::class, class_uses_recursive($class)))
            ->map(fn ($class) => [
                'class' => $class,
                'label' => class_basename($class),
                'attributes' => Schema::getColumnListing((new $class)->getTable()),
            ])
            ->values()
            ->toArray();
    }
}

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
use Maestrodimateo\Workflow\WorkflowManager;
use Throwable;

class WorkflowAdminController extends Controller
{
    public function __invoke(): View
    {
        $actions = collect(WorkflowManager::getRegisteredActions())
            ->map(fn ($class, $key) => ['key' => $key, 'label' => $class::label()])
            ->values();

        return view('workflow::app', [
            'circuits' => Circuit::with('baskets.next', 'baskets.previous', 'baskets.messages', 'messages')->get(),
            'colors' => collect(AllowedBasketColors::cases())->map(fn ($c) => ['name' => $c->name, 'value' => $c->value]),
            'msgTypes' => collect(MessageType::cases())->map(fn ($c) => ['name' => $c->name, 'value' => $c->value]),
            'recipients' => collect(RecipientType::cases())->map(fn ($c) => ['name' => $c->name, 'value' => $c->value]),
            'actions' => $actions,
            'variables' => MessageVariableResolver::availableKeys(),
            'apiPrefix' => './admin/api',
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

    public function updateTransition(Request $request, Basket $from, Basket $to): JsonResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'actions' => ['nullable', 'array'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.config' => ['nullable', 'array'],
        ]);

        $from->next()->updateExistingPivot($to->id, [
            'label' => $data['label'] ?? null,
            'actions' => json_encode($data['actions'] ?? []),
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
            'transitions' => $b->next->map(fn (Basket $n) => [
                '_to_ref' => $n->id,
                'label' => $n->pivot->label,
                'actions' => json_decode($n->pivot->actions ?? '[]', true),
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
}

<?php

namespace Maestrodimateo\Workflow\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'apiPrefix' => '/'.trim(config('workflow.routes.prefix', 'workflow'), '/').'/admin/api',
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

        $data = json_decode($request->file('file')->get(), true);

        if (! is_array($data) || ($data['_format'] ?? null) !== 'laravel-workflow/v1') {
            return response()->json(['message' => 'Format de fichier invalide.'], 422);
        }

        $circuit = DB::transaction(function () use ($data) {
            // Create circuit (without triggering the "created" event that auto-creates DRAFT)
            $circuitData = $data['circuit'];
            $circuit = new Circuit;
            $circuit->forceFill([
                'name' => $circuitData['name'].' (import)',
                'targetModel' => $circuitData['targetModel'],
                'description' => $circuitData['description'] ?? null,
                'roles' => $circuitData['roles'] ?? [],
            ]);
            $circuit->saveQuietly();

            // Create baskets — map old refs to new IDs
            $refMap = [];
            foreach ($data['baskets'] ?? [] as $basketData) {
                $basket = $circuit->baskets()->create([
                    'name' => $basketData['name'],
                    'status' => $basketData['status'],
                    'color' => $basketData['color'],
                    'roles' => $basketData['roles'] ?? [],
                ]);
                $refMap[$basketData['_ref']] = $basket->id;
            }

            // Create transitions
            foreach ($data['baskets'] ?? [] as $basketData) {
                $fromId = $refMap[$basketData['_ref']] ?? null;
                if (! $fromId) {
                    continue;
                }

                foreach ($basketData['transitions'] ?? [] as $trans) {
                    $toId = $refMap[$trans['_to_ref']] ?? null;
                    if (! $toId) {
                        continue;
                    }

                    Basket::query()->find($fromId)->next()->attach($toId, [
                        'label' => $trans['label'] ?? null,
                        'actions' => json_encode($trans['actions'] ?? []),
                    ]);
                }
            }

            // Create messages
            foreach ($data['messages'] ?? [] as $msgData) {
                $circuit->messages()->create([
                    'subject' => $msgData['subject'],
                    'content' => $msgData['content'],
                    'type' => $msgData['type'],
                    'recipient' => $msgData['recipient'],
                    'basket_id' => $refMap[$msgData['_basket_ref']] ?? null,
                ]);
            }

            return $circuit;
        });

        $circuit->load(['baskets.next', 'baskets.previous', 'baskets.messages', 'messages']);

        return response()->json($circuit, 201);
    }
}

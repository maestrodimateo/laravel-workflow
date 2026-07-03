<?php

namespace Maestrodimateo\Workflow\Controllers;

use Illuminate\Http\JsonResponse;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Models\Message;
use Maestrodimateo\Workflow\Requests\MessageRequest;
use Maestrodimateo\Workflow\Resources\MessageResource;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    public function store(MessageRequest $request, Circuit $circuit): JsonResponse
    {
        // Force the circuit from the route, never trust the body-supplied circuit_id.
        $message = Message::create([...$request->validated(), 'circuit_id' => $circuit->id]);

        return response()->json(['message' => MessageResource::make($message)], Response::HTTP_CREATED);
    }

    public function update(MessageRequest $request, Circuit $circuit, Message $message): MessageResource
    {
        // The message is scoped to the circuit via route binding; keep its circuit stable.
        $message->update([...$request->validated(), 'circuit_id' => $circuit->id]);

        return MessageResource::make($message->refresh());
    }

    public function destroy(Circuit $circuit, Message $message): Response
    {
        $message->delete();

        return response()->noContent();
    }
}

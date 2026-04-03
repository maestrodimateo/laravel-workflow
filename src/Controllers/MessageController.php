<?php

namespace Maestrodimateo\Workflow\Controllers;

use Illuminate\Http\JsonResponse;
use Maestrodimateo\Workflow\Models\Message;
use Maestrodimateo\Workflow\Requests\MessageRequest;
use Maestrodimateo\Workflow\Resources\MessageResource;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    public function store(MessageRequest $request): JsonResponse
    {
        $message = Message::create($request->validated());

        return response()->json(['message' => MessageResource::make($message)], Response::HTTP_CREATED);
    }

    public function update(MessageRequest $request, Message $message): MessageResource
    {
        $message->update($request->validated());

        return MessageResource::make($message->refresh());
    }

    public function destroy(Message $message): Response
    {
        $message->delete();

        return response()->noContent();
    }
}

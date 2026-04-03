<?php

namespace Maestrodimateo\Workflow\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Requests\CircuitRequest;
use Maestrodimateo\Workflow\Resources\CircuitResource;
use Symfony\Component\HttpFoundation\Response;

class CircuitController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CircuitResource::collection(
            Circuit::with('baskets')->latest()->paginate()
        );
    }

    public function show(Circuit $circuit): CircuitResource
    {
        return CircuitResource::make($circuit->load('baskets'));
    }

    public function store(CircuitRequest $request): JsonResponse
    {
        $circuit = Circuit::create($request->validated());

        return response()->json(['circuit' => CircuitResource::make($circuit)], Response::HTTP_CREATED);
    }

    public function update(CircuitRequest $request, Circuit $circuit): CircuitResource
    {
        $circuit->update($request->validated());

        return CircuitResource::make($circuit->refresh());
    }

    public function destroy(Circuit $circuit): Response
    {
        $circuit->delete();

        return response()->noContent();
    }
}

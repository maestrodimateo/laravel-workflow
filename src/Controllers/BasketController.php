<?php

namespace Maestrodimateo\Workflow\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Requests\BasketRequest;
use Maestrodimateo\Workflow\Resources\BasketResource;
use Symfony\Component\HttpFoundation\Response;

class BasketController
{
    public function store(BasketRequest $request): JsonResponse
    {
        $basket = DB::transaction(function () use ($request) {
            $basket = Basket::create($request->validated());
            $basket->previous()->syncWithoutDetaching($request->input('previous', []));

            return $basket;
        });

        return response()->json(['basket' => BasketResource::make($basket)], Response::HTTP_CREATED);
    }

    public function update(BasketRequest $request, Basket $basket): BasketResource
    {
        DB::transaction(function () use ($basket, $request) {
            $basket->update($request->validated());
            $basket->previous()->sync($request->input('previous', []));
        });

        return BasketResource::make($basket->refresh());
    }

    public function destroy(Basket $basket): Response
    {
        $basket->delete();

        return response()->noContent();
    }
}

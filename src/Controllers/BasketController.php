<?php

namespace Maestrodimateo\Workflow\Controllers;

use Illuminate\Http\JsonResponse;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Requests\BasketRequest;
use Maestrodimateo\Workflow\Resources\BasketResource;
use Maestrodimateo\Workflow\Services\BasketService;
use Symfony\Component\HttpFoundation\Response;

class BasketController extends Controller
{
    public function __construct(private readonly BasketService $basketService) {}

    public function store(BasketRequest $request): JsonResponse
    {
        $basket = $this->basketService->create($request);

        return response()->json(['basket' => BasketResource::make($basket)], Response::HTTP_CREATED);
    }

    public function update(BasketRequest $request, Basket $basket): BasketResource
    {
        $this->basketService->update($request, $basket);

        return BasketResource::make($basket->refresh());
    }

    public function destroy(Basket $basket): Response
    {
        $basket->delete();

        return response()->noContent();
    }
}

<?php

namespace Maestrodimateo\Workflow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Maestrodimateo\Workflow\Models\Basket;

class BasketService
{
    /**
     * @throws \Throwable
     */
    public function create(FormRequest $request): Model
    {
        return DB::transaction(function () use ($request) {
            $basket = Basket::create($request->validated());
            $basket->previous()->syncWithoutDetaching($request->input('previous', []));

            return $basket;
        });
    }

    /**
     * @throws \Throwable
     */
    public function update(FormRequest $request, Basket $basket): bool
    {
        return DB::transaction(function () use ($basket, $request) {
            $basket->update($request->validated());
            $basket->previous()->sync($request->input('previous', []));

            return true;
        });
    }
}

<?php

namespace Maestrodimateo\Workflow\Repositories;

use Illuminate\Database\Eloquent\Model;
use Maestrodimateo\Workflow\Models\Basket;

class BasketRepository
{
    public function moveModelToNextBasket(Basket $previousBasket, Basket $nextBasket, Model $model): void
    {
        $model->baskets()->detach($previousBasket);
        $model->baskets()->attach($nextBasket);
    }
}

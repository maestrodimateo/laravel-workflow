<?php

namespace Maestrodimateo\Workflow\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Models\History;

/**
 * @mixin Model
 *
 * @property-read MorphToMany<Basket> $baskets
 * @property-read MorphMany<History> $histories
 *
 * @method static Builder fromBasket(Basket $basket)
 */
trait Workflowable
{
    public static function bootWorkflowable(): void
    {
        static::created(static function ($model): void {
            $basket = Basket::query()
                ->whereRelation('circuit', 'targetModel', self::class)
                ->whereDoesntHave('previous')
                ->first();

            $basket?->targetModels()->attach($model);
        });
    }

    public function baskets(): MorphToMany
    {
        return $this->morphToMany(Basket::class, 'statusable', 'statusable', 'statusable_id', 'basket_id');
    }

    public function histories(): MorphMany
    {
        return $this->morphMany(History::class, 'historable');
    }

    public function currentStatus(): ?Basket
    {
        return $this->baskets->last();
    }

    public function scopeFromBasket(Builder $query, Basket $basket): Builder
    {
        return $query->whereRelation('baskets', 'status', $basket->status);
    }
}

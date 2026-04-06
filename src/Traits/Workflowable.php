<?php

namespace Maestrodimateo\Workflow\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Models\History;
use Maestrodimateo\Workflow\Models\WorkflowLock;

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
    /**
     * On creation, attach the model to the DRAFT basket of ALL circuits targeting this model class.
     */
    public static function bootWorkflowable(): void
    {
        static::created(static function ($model): void {
            $draftBaskets = Basket::query()
                ->whereRelation('circuit', 'targetModel', self::class)
                ->whereDoesntHave('previous')
                ->get();

            foreach ($draftBaskets as $basket) {
                $model->baskets()->attach($basket->id);
            }
        });
    }

    /** All baskets this model is/has been in (across all circuits) */
    public function baskets(): MorphToMany
    {
        return $this->morphToMany(Basket::class, 'statusable', 'statusable', 'statusable_id', 'basket_id');
    }

    /** All history entries across all circuits */
    public function histories(): MorphMany
    {
        return $this->morphMany(History::class, 'historable');
    }

    /**
     * Get the current basket (last attached), optionally filtered by circuit.
     *
     * @param  string|Circuit|null  $circuit  Circuit ID, Circuit instance, or null for the latest across all
     */
    public function currentStatus(string|Circuit|null $circuit = null): ?Basket
    {
        if ($circuit) {
            $circuitId = $circuit instanceof Circuit ? $circuit->id : $circuit;

            return $this->baskets()
                ->where('circuit_id', $circuitId)
                ->orderByPivot('created_at', 'desc')
                ->first();
        }

        return $this->baskets->last();
    }

    /** Scope: models in a specific basket */
    public function scopeFromBasket(Builder $query, Basket $basket): Builder
    {
        return $query->whereRelation('baskets', 'baskets.id', $basket->id);
    }

    /** Scope: only unlocked models (no active lock or lock expired) */
    public function scopeUnlocked(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereDoesntHave('workflowLock')
                ->orWhereHas('workflowLock', fn (Builder $sub) => $sub->where('expires_at', '<=', now()));
        });
    }

    /** Scope: only models locked by a specific user */
    public function scopeLockedBy(Builder $query, string $userId): Builder
    {
        return $query->whereHas('workflowLock', fn (Builder $q) => $q->where('locked_by', $userId)->where('expires_at', '>', now()));
    }

    /** The active workflow lock for this model */
    public function workflowLock(): MorphOne
    {
        return $this->morphOne(WorkflowLock::class, 'lockable');
    }
}

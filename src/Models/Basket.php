<?php

namespace Maestrodimateo\Workflow\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Maestrodimateo\Workflow\Traits\HasRoles;

/**
 * @property-read string $id
 * @property string $name
 * @property string $status
 * @property string $color
 * @property array $roles
 * @property array|null $position
 * @property string $circuit_id
 * @property-read Circuit $circuit
 *
 * @method static Builder forRole(string $role)
 * @method static Builder forRoles(array $roles)
 */
class Basket extends Model
{
    use HasRoles, HasUuids;

    protected $fillable = [
        'name',
        'status',
        'color',
        'roles',
        'position',
        'circuit_id',
    ];

    public const array DEFAULT_STATUS = [
        'name' => 'Draft',
        'status' => 'DRAFT',
        'color' => '#64748b',
    ];

    protected $casts = [
        'roles' => 'array',
        'position' => 'array',
    ];

    /**
     * Interact with the basket status
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper($value),
        );
    }

    /**
     * Get the next baskets
     */
    public function next(): BelongsToMany
    {
        return $this->belongsToMany(Basket::class, 'transition', 'from_basket_id', 'to_basket_id')
            ->withPivot(['label', 'actions'])
            ->withTimestamps();
    }

    /**
     * Get the previous baskets
     */
    public function previous(): BelongsToMany
    {
        return $this->belongsToMany(Basket::class, 'transition', 'to_basket_id', 'from_basket_id')
            ->withPivot(['label', 'actions'])
            ->withTimestamps();
    }

    /**
     * Get the associated circuit
     */
    public function circuit(): BelongsTo
    {
        return $this->belongsTo(Circuit::class);
    }

    /**
     * Get the messages
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get all the models for the basket.
     */
    public function targetModels(): MorphToMany
    {
        return $this->morphedByMany($this->circuit->targetModel, 'statusable', 'statusable');
    }

}

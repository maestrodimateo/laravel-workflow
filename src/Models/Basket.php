<?php

namespace Maestrodimateo\Workflow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Maestrodimateo\Workflow\Enums\AllowedBasketColors;

/**
 * @property-read string $id
 * @property string $name
 * @property string $status
 * @property AllowedBasketColors $color
 * @property array $roles
 * @property string $circuit_id
 * @property-read Circuit $circuit
 *
 * @method static Builder forRole(string $role)
 * @method static Builder forRoles(array $roles)
 */
class Basket extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'status',
        'color',
        'roles',
        'circuit_id',
    ];

    public const array DEFAULT_STATUS = [
        'name' => 'Brouillon',
        'status' => 'DRAFT',
        'color' => '#64748b',
    ];

    protected $casts = [
        'roles' => 'array',
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

    /**
     * Check if a role name is allowed for this basket.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles ?? [], true);
    }

    /**
     * Scope : paniers accessibles pour un rôle donné.
     */
    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->whereJsonContains('roles', $role);
    }

    /**
     * Scope : paniers accessibles pour au moins un des rôles donnés.
     */
    public function scopeForRoles(Builder $query, array $roles): Builder
    {
        return $query->where(function (Builder $q) use ($roles) {
            foreach ($roles as $role) {
                $q->orWhereJsonContains('roles', $role);
            }
        });
    }
}

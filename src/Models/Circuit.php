<?php

namespace Maestrodimateo\Workflow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property-read string $id
 * @property string $name
 * @property string $targetModel
 * @property string $description
 * @property array $roles
 * @property-read HasMany<Basket> $baskets
 *
 * @method static Builder forRole(string $role)
 * @method static Builder forRoles(array $roles)
 */
class Circuit extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'targetModel',
        'description',
        'roles',
    ];

    protected $casts = [
        'roles' => 'array',
    ];

    #[Override]
    protected static function boot(): void
    {
        parent::boot();

        static::created(static function (Circuit $circuit): void {
            $circuit->baskets()->create(Basket::DEFAULT_STATUS);
        });
    }

    /**
     * Get the baskets
     */
    public function baskets(): HasMany
    {
        return $this->hasMany(Basket::class);
    }

    /**
     * Get the messages
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Scope: circuits accessibles pour un rôle donné.
     */
    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->whereJsonContains('roles', $role);
    }

    /**
     * Scope: circuits accessibles pour au moins un des rôles donnés.
     */
    public function scopeForRoles(Builder $query, array $roles): Builder
    {
        return $query->where(function (Builder $q) use ($roles) {
            foreach ($roles as $role) {
                $q->orWhereJsonContains('roles', $role);
            }
        });
    }

    /**
     * Vérifie si un rôle a accès à ce circuit.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles ?? [], true);
    }
}

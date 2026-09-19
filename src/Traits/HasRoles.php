<?php

namespace Maestrodimateo\Workflow\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared role-based scopes for models with a JSON `roles` column.
 */
trait HasRoles
{
    /**
     * Scope: accessible for a given role.
     */
    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->whereJsonContains('roles', $role);
    }

    /**
     * Scope: accessible for at least one of the given roles.
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
     * Check if a role has operator access.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles ?? [], true);
    }

}
<?php

namespace Maestrodimateo\Workflow\Facades;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\WorkflowManager;

/**
 * @method static WorkflowManager for(Model $model)
 * @method static Basket|null currentStatus()
 * @method static \Illuminate\Support\Collection nextBaskets()
 * @method static bool transition(string $nextBasketId, ?string $comment = null)
 * @method static Collection history()
 * @method static int totalDuration()
 * @method static int durationInStatus(string $status)
 * @method static Collection basketsForRole(string $role, ?string $circuitId = null)
 * @method static Collection basketsForRoles(array $roles, ?string $circuitId = null)
 * @method static Collection circuitsForRole(string $role)
 * @method static Collection circuitsForRoles(array $roles)
 * @method static void registerAction(string $actionClass)
 * @method static array getRegisteredActions()
 *
 * @see WorkflowManager
 */
class Workflow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WorkflowManager::class;
    }
}

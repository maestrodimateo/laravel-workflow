<?php

namespace Maestrodimateo\Workflow\Facades;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\WorkflowManager;

/**
 * @method static WorkflowManager for(Model $model)
 * @method static WorkflowManager in(string|Circuit $circuit)
 * @method static Basket|null currentStatus()
 * @method static \Illuminate\Support\Collection nextBaskets()
 * @method static bool transition(string $nextBasketId, ?string $comment = null)
 * @method static array transitionMany(iterable $models, string $nextBasketId, ?string $comment = null)
 * @method static Collection history()
 * @method static array requiredDocuments(string $nextBasketId)
 * @method static array requirements()
 * @method static int totalDuration()
 * @method static int durationInStatus(string $status)
 * @method static \Maestrodimateo\Workflow\Models\WorkflowLock lock(?int $minutes = null)
 * @method static void unlock(bool $force = false)
 * @method static bool isLocked()
 * @method static bool isLockedByMe()
 * @method static string|null lockedBy()
 * @method static \Illuminate\Support\Carbon|null lockExpiration()
 * @method static array allStatuses()
 * @method static Collection circuits()
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

<?php

namespace Maestrodimateo\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $lockable_type
 * @property string $lockable_id
 * @property string $locked_by
 * @property Carbon $expires_at
 */
class WorkflowLock extends Model
{
    protected $table = 'workflow_locks';

    protected $fillable = [
        'lockable_type',
        'lockable_id',
        'locked_by',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function lockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if this lock is still active (not expired).
     */
    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }
}

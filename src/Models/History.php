<?php

namespace Maestrodimateo\Workflow\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read string $id
 * @property string $previous_status
 * @property string $next_status
 * @property string $comment
 * @property string $done_by
 * @property int|null $duration_seconds
 * @property-read string|null $duration_human
 */
class History extends Model
{
    use HasUuids;

    protected $fillable = [
        'previous_status',
        'next_status',
        'comment',
        'done_by',
        'duration_seconds',
    ];

    /**
     * Get the model that the history belongs to.
     */
    public function historable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Human-readable duration (ex: "2h 35min", "3j 4h").
     */
    protected function durationHuman(): Attribute
    {
        return Attribute::get(function () {
            $s = $this->duration_seconds;

            if ($s === null) {
                return null;
            }

            if ($s < 60) {
                return $s.'s';
            }

            if ($s < 3600) {
                return intdiv($s, 60).'min';
            }

            if ($s < 86400) {
                $h = intdiv($s, 3600);
                $m = intdiv($s % 3600, 60);

                return $h.'h'.($m ? ' '.$m.'min' : '');
            }

            $d = intdiv($s, 86400);
            $h = intdiv($s % 86400, 3600);

            return $d.'j'.($h ? ' '.$h.'h' : '');
        });
    }
}

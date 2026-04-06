<?php

namespace Maestrodimateo\Workflow\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Models\Basket;

class LogTransitionAction implements TransitionAction
{
    public static function key(): string
    {
        return 'log';
    }

    public static function label(): string
    {
        return 'Log transition';
    }

    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        $channel = $config['channel'] ?? null;

        Log::channel($channel)->info('Workflow transition', [
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'from' => $from->status,
            'to' => $to->status,
            'message' => $config['message'] ?? null,
        ]);
    }
}

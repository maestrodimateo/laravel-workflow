<?php

namespace Maestrodimateo\Workflow\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Maestrodimateo\Workflow\Contracts\QueueableAction;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Models\Basket;

class WebhookAction implements TransitionAction, QueueableAction
{
    public static function key(): string
    {
        return 'webhook';
    }

    public static function label(): string
    {
        return 'Call webhook';
    }

    public static function queue(): ?string
    {
        return null;
    }

    public static function connection(): ?string
    {
        return null;
    }

    /**
     * @throws ConnectionException
     */
    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        $url = $config['url'] ?? null;

        if (! $url) {
            return;
        }

        Http::post($url, [
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'from_status' => $from->status,
            'to_status' => $to->status,
        ]);
    }
}

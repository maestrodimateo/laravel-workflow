<?php

namespace Maestrodimateo\Workflow\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Models\Basket;

class WebhookAction implements TransitionAction
{
    public static function key(): string
    {
        return 'webhook';
    }

    public static function label(): string
    {
        return 'Appeler un webhook';
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

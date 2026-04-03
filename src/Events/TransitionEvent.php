<?php

namespace Maestrodimateo\Workflow\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use Maestrodimateo\Workflow\Models\Basket;

class TransitionEvent
{
    use SerializesModels;

    public function __construct(
        public Basket $currentBasket,
        public Basket $nextBasket,
        public ?Model $model = null,
        public ?string $comment = null,
    ) {}

    public function broadcastOn(): array
    {
        return [];
    }
}

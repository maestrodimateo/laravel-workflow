<?php

namespace Maestrodimateo\Workflow\Listeners;

use Maestrodimateo\Workflow\Events\TransitionEvent;
use Maestrodimateo\Workflow\Support\WorkflowActor;

class HistoryListener
{
    public function handle(TransitionEvent $event): void
    {
        if (! $event->model) {
            return;
        }

        // Calculate duration since the last transition (or model creation).
        // Diff from the past date to now so the value stays positive under
        // Carbon 3, where diffInSeconds is signed by default.
        $lastEntry = $event->model->histories()->latest()->first();
        $since = $lastEntry ? $lastEntry->created_at : $event->model->created_at;
        $durationSeconds = $since ? (int) $since->diffInSeconds(now()) : null;

        $event->model->histories()->create([
            'previous_status' => $event->currentBasket->status,
            'previous_status_label' => $event->currentBasket->name,
            'next_status' => $event->nextBasket->status,
            'next_status_label' => $event->nextBasket->name,
            'comment' => $event->comment,
            'done_by' => WorkflowActor::id(),
            'duration_seconds' => $durationSeconds,
        ]);
    }
}

<?php

namespace Maestrodimateo\Workflow\Listeners;

use Maestrodimateo\Workflow\Events\TransitionEvent;

class HistoryListener
{
    public function handle(TransitionEvent $event): void
    {
        if (! $event->model) {
            return;
        }

        // Calculate duration since the last transition (or model creation)
        $lastEntry = $event->model->histories()->latest()->first();
        $since = $lastEntry ? $lastEntry->created_at : $event->model->created_at;
        $durationSeconds = $since ? (int) now()->diffInSeconds($since) : null;

        $event->model->histories()->create([
            'previous_status' => $event->currentBasket->status,
            'next_status' => $event->nextBasket->status,
            'comment' => $event->comment,
            'done_by' => auth()->user()?->{config('workflow.auth_identifier', 'id')} ?? 'system',
            'duration_seconds' => $durationSeconds,
        ]);
    }
}

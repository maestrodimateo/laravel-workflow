<?php

namespace Maestrodimateo\Workflow;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Maestrodimateo\Workflow\Events\TransitionEvent;
use Maestrodimateo\Workflow\Listeners\HistoryListener;

class WorkflowEventServiceProvider extends EventServiceProvider
{
    protected $listen = [
        TransitionEvent::class => [
            HistoryListener::class,
        ],
    ];
}

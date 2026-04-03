<?php

namespace Maestrodimateo\Workflow\Services;

use Illuminate\Support\Facades\Mail;
use Maestrodimateo\Workflow\Contracts\TransitionMessageDriver;
use Maestrodimateo\Workflow\Emails\TransitionMail;
use Maestrodimateo\Workflow\Models\Message;

class EmailTransitionMessageDriver implements TransitionMessageDriver
{
    private string|array $to;

    public function to(string|array $recipient): self
    {
        $this->to = $recipient;

        return $this;
    }

    public function send(Message $message, string $resolvedContent = '', string $resolvedSubject = ''): void
    {
        Mail::to($this->to)->send(new TransitionMail($message, $resolvedContent, $resolvedSubject));
    }
}

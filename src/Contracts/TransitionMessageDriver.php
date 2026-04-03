<?php

namespace Maestrodimateo\Workflow\Contracts;

use Maestrodimateo\Workflow\Models\Message;

interface TransitionMessageDriver
{
    public function to(string|array $recipient): self;

    public function send(Message $message, string $resolvedContent = '', string $resolvedSubject = ''): void;
}

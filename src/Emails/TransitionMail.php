<?php

namespace Maestrodimateo\Workflow\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Maestrodimateo\Workflow\Models\Message;

class TransitionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Message $message,
        public string $resolvedContent = '',
        public string $resolvedSubject = '',
    ) {}

    public function build(): static
    {
        return $this
            ->subject($this->resolvedSubject ?: $this->message->subject)
            ->markdown('workflow::emails.transition-mail', [
                'content' => $this->resolvedContent ?: $this->message->content,
            ]);
    }
}

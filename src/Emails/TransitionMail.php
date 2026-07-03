<?php

namespace Maestrodimateo\Workflow\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Maestrodimateo\Workflow\Models\Message;
use Maestrodimateo\Workflow\Support\HtmlSanitizer;

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
        // The stored content is already sanitized on write, but resolvedContent
        // may carry freshly-substituted variable values — sanitize at the output
        // boundary too so the {!! !!} in the view can never emit unsafe markup.
        $content = HtmlSanitizer::clean($this->resolvedContent ?: $this->message->content);

        return $this
            ->subject($this->resolvedSubject ?: $this->message->subject)
            ->markdown('workflow::emails.transition-mail', [
                'content' => $content,
            ]);
    }
}

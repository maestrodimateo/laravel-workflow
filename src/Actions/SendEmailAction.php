<?php

namespace Maestrodimateo\Workflow\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Maestrodimateo\Workflow\Contracts\QueueableAction;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Emails\TransitionMail;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Models\Message;
use Maestrodimateo\Workflow\Services\MessageVariableResolver;

class SendEmailAction implements QueueableAction, TransitionAction
{
    public static function key(): string
    {
        return 'send_email';
    }

    public static function label(): string
    {
        return 'Send email';
    }

    public static function queue(): ?string
    {
        return null;
    }

    public static function connection(): ?string
    {
        return null;
    }

    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        $messageId = $config['message_id'] ?? null;

        if (! $messageId) {
            return;
        }

        $message = Message::query()->find($messageId);

        if ($message && method_exists($model, 'recipient')) {
            $recipient = $model->recipient($message);

            // Substitute {{ variables }} at send time. The subject is plain text;
            // the content is HTML, so its values are HTML-escaped.
            $resolvedSubject = MessageVariableResolver::resolve($message->subject, $model, $from, $to);
            $resolvedContent = MessageVariableResolver::resolve($message->content, $model, $from, $to, escapeHtml: true);

            Mail::to($recipient)->send(new TransitionMail($message, $resolvedContent, $resolvedSubject));
        }
    }
}

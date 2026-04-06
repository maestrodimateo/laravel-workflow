<?php

namespace Maestrodimateo\Workflow\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Emails\TransitionMail;
use Maestrodimateo\Workflow\Models\Basket;
use Maestrodimateo\Workflow\Models\Message;

class SendEmailAction implements TransitionAction
{
    public static function key(): string
    {
        return 'send_email';
    }

    public static function label(): string
    {
        return 'Send email';
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
            Mail::to($recipient)->send(new TransitionMail($message));
        }
    }
}

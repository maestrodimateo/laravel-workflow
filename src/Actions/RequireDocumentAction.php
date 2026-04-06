<?php

namespace Maestrodimateo\Workflow\Actions;

use Illuminate\Database\Eloquent\Model;
use Maestrodimateo\Workflow\Contracts\TransitionAction;
use Maestrodimateo\Workflow\Exceptions\MissingDocumentsException;
use Maestrodimateo\Workflow\Models\Basket;

class RequireDocumentAction implements TransitionAction
{
    public static function key(): string
    {
        return 'require_document';
    }

    public static function label(): string
    {
        return 'Require documents';
    }

    public function execute(Model $model, Basket $from, Basket $to, array $config = []): void
    {
        $documents = $config['documents'] ?? [];

        if (! count($documents) || ! method_exists($model, 'documents')) {
            return;
        }

        $missing = [];

        foreach ($documents as $doc) {
            $type = $doc['type'] ?? null;
            $label = $doc['label'] ?? $type;

            if ($type && ! $model->documents()->where('type', $type)->exists()) {
                $missing[] = ['type' => $type, 'label' => $label];
            }
        }

        if (count($missing)) {
            throw MissingDocumentsException::for($missing);
        }
    }
}

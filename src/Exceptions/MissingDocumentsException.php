<?php

namespace Maestrodimateo\Workflow\Exceptions;

use RuntimeException;

class MissingDocumentsException extends RuntimeException
{
    /** @var array<int, array{type: string, label: string}> */
    private array $documents;

    /**
     * @param  array<int, array{type: string, label: string}>  $documents
     */
    public static function for(array $documents): self
    {
        $labels = array_column($documents, 'label');

        $e = new self('Missing documents: '.implode(', ', $labels));
        $e->documents = $documents;

        return $e;
    }

    /**
     * @return array<int, array{type: string, label: string}>
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

}

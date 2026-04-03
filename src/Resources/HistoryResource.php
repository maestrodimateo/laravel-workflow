<?php

namespace Maestrodimateo\Workflow\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistoryResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'previous_status' => $this->previous_status,
            'next_status' => $this->next_status,
            'comment' => $this->comment,
            'done_by' => $this->done_by,
            'duration_seconds' => $this->duration_seconds,
            'duration_human' => $this->duration_human,
            'created_at' => $this->created_at,
        ];
    }
}

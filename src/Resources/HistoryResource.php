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
            'previous_status_label' => $this->previous_status_label,
            'next_status' => $this->next_status,
            'next_status_label' => $this->next_status_label,
            'comment' => $this->comment,
            'done_by' => $this->done_by,
            'duration_seconds' => $this->duration_seconds,
            'duration_human' => $this->duration_human,
            'created_at' => $this->created_at,
        ];
    }
}

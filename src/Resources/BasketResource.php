<?php

namespace Maestrodimateo\Workflow\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $status
 * @property mixed $color
 */
class BasketResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'color' => $this->color,
            'roles' => $this->roles ?? [],
            'next' => BasketResource::collection($this->whenLoaded('next')),
            'previous' => BasketResource::collection($this->whenLoaded('previous')),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'circuit' => CircuitResource::make($this->whenLoaded('circuit')),
        ];
    }
}

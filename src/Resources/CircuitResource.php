<?php

namespace Maestrodimateo\Workflow\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $description
 * @property mixed $targetModel
 */
class CircuitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** Le nom du circuit */
            'name' => $this->name,
            /** Le modèle du circuit */
            'model' => $this->targetModel,
            /** La description du circuit */
            'description' => $this->description,
            /** Les rôles autorisés */
            'roles' => $this->roles ?? [],
            /** Les paniers du circuit */
            'baskets' => BasketResource::collection($this->whenLoaded('baskets')),
            /** Les messages liés à ce circuit */
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}

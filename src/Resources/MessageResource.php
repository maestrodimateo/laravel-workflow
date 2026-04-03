<?php

namespace Maestrodimateo\Workflow\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @property mixed $id
 * @property mixed $type
 * @property mixed $content
 * @property mixed $subject
 * @property mixed $recipient
 */
class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** Type de message */
            'type' => $this->type,
            /** Contenu du message */
            'content' => $this->content,
            /** Objet du message */
            'subject' => $this->subject,
            /** Type de destinataire du message */
            'recipient' => $this->recipient,
            /** Circuit du message */
            'circuit' => CircuitResource::make($this->whenLoaded('circuit')),
            /** Panier lié au message */
            'basket' => BasketResource::make($this->whenLoaded('basket')),
        ];
    }
}

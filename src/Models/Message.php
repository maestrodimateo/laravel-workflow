<?php

namespace Maestrodimateo\Workflow\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Maestrodimateo\Workflow\Enums\MessageType;
use Maestrodimateo\Workflow\Enums\RecipientType;

/**
 * @property-read string $id
 * @property string $subject : Objet du message
 * @property string $content : Contenu du message
 * @property RecipientType $recipient : Destinataire du message
 * @property string $circuit_id : Identifiant du circuit
 * @property string $basket_id : Identifiant du panier
 * @property MessageType $type : Type de message
 * @property-read BelongsTo<Basket> $basket : Panier du message
 * @property-read BelongsTo<Circuit> $circuit : Circuit du message
 */
class Message extends Model
{
    use HasUuids;

    protected $fillable = [
        'subject',
        'content',
        'recipient',
        'circuit_id',
        'basket_id',
        'type',
    ];

    protected $casts = [
        'type' => MessageType::class,
        'recipient' => RecipientType::class,
    ];

    /**
     * Get the basket of the message
     */
    public function basket(): BelongsTo
    {
        return $this->belongsTo(Basket::class);
    }

    /**
     * Get the circuit of the message
     */
    public function circuit(): BelongsTo
    {
        return $this->belongsTo(Circuit::class);
    }
}

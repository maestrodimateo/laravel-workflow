<?php

namespace Maestrodimateo\Workflow\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Maestrodimateo\Workflow\Enums\MessageType;
use Maestrodimateo\Workflow\Enums\RecipientType;

/**
 * Class MessageRequest
 *
 * @property string $type
 * @property string $content
 * @property string $subject
 * @property string $recipient
 * @property string $circuit_id
 */
class MessageRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            /** Type de message */
            'type' => ['required', Rule::in(MessageType::values())],
            /** Contenu du message */
            'content' => ['required', 'string'],
            /** Objet du message */
            'subject' => ['required', 'string'],
            /** Type de destinataire */
            'recipient' => ['required', Rule::in(RecipientType::values())],
            /** Identifiant du circuit */
            'circuit_id' => ['required', 'uuid', 'exists:circuits,id'],
        ];
    }

    /**
     * Get the custom messages
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'type.required' => 'Le type de message est obligatoire',
            'type.in' => 'Le type de message est invalide',
            'content.required' => 'Le contenu du message est obligatoire',
            'subject.required' => 'L\'objet du message est obligatoire',
            'recipient.required' => 'Le destinataire du message est obligatoire',
            'recipient.in' => 'Le destinataire du message est invalide',
            'circuit_id.required' => 'L\'identifiant du circuit est obligatoire',
            'circuit_id.uuid' => 'L\'identifiant du circuit est invalide',
            'circuit_id.exists' => 'Le circuit n\'existe pas',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}

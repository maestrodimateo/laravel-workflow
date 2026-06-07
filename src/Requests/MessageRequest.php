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
            'type.required' => __('workflow::workflow.validation.message_type_required'),
            'type.in' => __('workflow::workflow.validation.message_type_invalid'),
            'content.required' => __('workflow::workflow.validation.message_content_required'),
            'subject.required' => __('workflow::workflow.validation.message_subject_required'),
            'recipient.required' => __('workflow::workflow.validation.message_recipient_required'),
            'recipient.in' => __('workflow::workflow.validation.message_recipient_invalid'),
            'circuit_id.required' => __('workflow::workflow.validation.circuit_uuid_required'),
            'circuit_id.uuid' => __('workflow::workflow.validation.circuit_uuid_invalid'),
            'circuit_id.exists' => __('workflow::workflow.validation.circuit_exists'),
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

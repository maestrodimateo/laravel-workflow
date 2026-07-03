<?php

namespace Maestrodimateo\Workflow\Requests;

use Illuminate\Validation\Rule;
use Maestrodimateo\Workflow\Models\Circuit;

/**
 * @property Circuit $circuit
 * @property string $name
 * @property string|null $description
 */
class CircuitRequest extends WorkflowFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            /** Le nom du circuit */
            'name' => ['required', 'string', Rule::unique('circuits')->ignore($this->circuit)],
            'targetModel' => ['required', fn ($attribute, $value, $fail) => ! class_exists($value) ? $fail($attribute.' must be a valid model class') : null],
            'description' => ['nullable', 'string'],
            /** Les rôles autorisés pour ce circuit */
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string'],
        ];
    }

    /**
     * Get the custom messages
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'name.required' => __('workflow::workflow.validation.circuit_name_required'),
            'targetModel.required' => __('workflow::workflow.validation.circuit_target_required'),
            'name.unique' => __('workflow::workflow.validation.circuit_name_unique'),
        ];
    }
}

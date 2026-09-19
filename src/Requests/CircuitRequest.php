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
            'name' => ['required', 'string', 'max:255', Rule::unique('circuits')->ignore($this->circuit)],
            'targetModel' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! class_exists($value)) {
                    $fail($attribute.' must be a valid model class.');
                    return;
                }
                if (! in_array(\Maestrodimateo\Workflow\Traits\Workflowable::class, class_uses_recursive($value))) {
                    $fail($attribute.' must use the Workflowable trait.');
                }
            }],
            'description' => ['nullable', 'string', 'max:1000'],
            /** Les rôles autorisés pour ce circuit */
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'max:100'],
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

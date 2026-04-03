<?php

namespace Maestrodimateo\Workflow\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Maestrodimateo\Workflow\Models\Circuit;

/**
 * @property Circuit $circuit
 * @property string $name
 * @property string|null $description
 */
class CircuitRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            /** Le nom du circuit */
            'name' => ['required', 'string', Rule::unique('circuits')->ignore($this->circuit)],
            /** Le modèle du circuit avec namespace */
            'targetModel' => ['required', fn ($attribute, $value, $fail) => ! class_exists($value) ? $fail($attribute.' doit être un modèle') : null],
            /** La description du circuit */
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
            'name.required' => 'Le nom est obligatoire',
            'targetModel.required' => 'Le modèle est obligatoire',
            'name.unique' => 'Ce nom est déjà utilisé',
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

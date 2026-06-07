<?php

namespace Maestrodimateo\Workflow\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class BasketRequest
 *
 * @property mixed $circuit_id
 * @property mixed $basket
 * @property mixed $status
 * @property mixed $roles
 * @property mixed $previous
 */
class BasketRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            /** Le nom du panier */
            'name' => ['required', 'string', Rule::unique('baskets')
                ->where('circuit_id', $this->circuit_id)
                ->ignore($this->basket)],
            /** Le statut du panier */
            'status' => ['required', 'string'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            /** L'identifiant du circuit */
            'circuit_id' => ['required', 'exists:circuits,id'],
            /** Les noms de rôles autorisés pour ce panier */
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string'],
            /** Les paniers précédents */
            'previous' => ['array'],
            'previous.*' => [Rule::exists('baskets', 'id')
                ->whereNot('status', $this->status)
                ->where('circuit_id', $this->circuit_id)],
        ];
    }

    /**
     * Get the custom messages
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'color.required' => __('workflow::workflow.validation.color_required'),
            'color.regex' => __('workflow::workflow.validation.color_regex'),
            'name.required' => __('workflow::workflow.validation.name_required'),
            'name.unique' => __('workflow::workflow.validation.name_unique'),
            'status.required' => __('workflow::workflow.validation.status_required'),
            'circuit_id.required' => __('workflow::workflow.validation.circuit_id_required'),
            'circuit_id.exists' => __('workflow::workflow.validation.circuit_id_exists'),
            'roles.array' => __('workflow::workflow.validation.roles_array'),
            'roles.*.exists' => __('workflow::workflow.validation.role_invalid'),
            'previous.array' => __('workflow::workflow.validation.previous_array'),
            'previous.*.exists' => __('workflow::workflow.validation.previous_invalid'),
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

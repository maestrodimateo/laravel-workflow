<?php

namespace Maestrodimateo\Workflow\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Maestrodimateo\Workflow\Enums\AllowedBasketColors;

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
            'color' => ['required', Rule::in(AllowedBasketColors::values())],
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
            'color.required' => 'La couleur du panier est obligatoire',
            'color.in' => 'La couleur du panier est invalide',
            'name.required' => 'Le nom du panier est obligatoire',
            'name.unique' => 'Le nom du panier est déjà utilisé',
            'status.required' => 'Le statut du panier est obligatoire',
            'circuit_id.required' => 'Le circuit est obligatoire',
            'circuit_id.exists' => 'Ce circuit est invalide',
            'roles.array' => 'Renseignez une liste de rôles',
            'roles.*.exists' => 'Un des rôles est invalide',
            'previous.array' => 'Renseignez une liste de paniers précédents',
            'previous.*.exists' => 'Un des paniers précédents est invalide',
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

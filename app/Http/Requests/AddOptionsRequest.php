<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'options' => 'required|array|min:1',
            'options.*.id' => 'required|exists:options,id',
            'options.*.quantity' => 'required|integer|min:1',
            'payment_method_id' => 'nullable|string', // Optionnel si paiement différé
        ];
    }

    public function messages(): array
    {
        return [
            'options.required' => 'Au moins une option doit être sélectionnée',
            'options.min' => 'Au moins une option doit être sélectionnée',
            'options.*.id.required' => 'L\'ID de l\'option est requis',
            'options.*.id.exists' => 'L\'option sélectionnée n\'existe pas',
            'options.*.quantity.required' => 'La quantité est requise',
            'options.*.quantity.min' => 'La quantité doit être d\'au moins 1',
        ];
    }
}


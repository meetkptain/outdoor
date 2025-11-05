<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        return [
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_first_name' => 'required|string|max:255',
            'customer_last_name' => 'required|string|max:255',
            'customer_birth_date' => 'nullable|date',
            'customer_weight' => 'nullable|integer|min:40|max:120',
            'customer_height' => 'nullable|integer|min:140|max:250', // cm
            'flight_type' => 'required|in:tandem,biplace,initiation,perfectionnement,autonome',
            'participants_count' => 'required|integer|min:1|max:10',
            'participants' => 'nullable|array',
            'participants.*.first_name' => 'required_with:participants|string|max:255',
            'participants.*.last_name' => 'required_with:participants|string|max:255',
            'participants.*.birth_date' => 'nullable|date',
            'participants.*.weight' => 'nullable|integer|min:40|max:120',
            'participants.*.height' => 'nullable|integer|min:140|max:250', // cm
            'options' => 'nullable|array',
            'options.*.id' => 'required_with:options|exists:options,id',
            'options.*.quantity' => 'required_with:options|integer|min:1',
            'coupon_code' => 'nullable|string|max:50',
            'gift_card_code' => 'nullable|string|max:50',
            'payment_type' => 'required|in:deposit,authorization,both',
            'payment_method_id' => 'required|string',
            'special_requests' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_email.required' => 'L\'email est requis',
            'customer_email.email' => 'L\'email doit être valide',
            'customer_first_name.required' => 'Le prénom est requis',
            'customer_last_name.required' => 'Le nom est requis',
            'flight_type.required' => 'Le type de vol est requis',
            'flight_type.in' => 'Type de vol invalide',
            'participants_count.required' => 'Le nombre de participants est requis',
            'participants_count.min' => 'Il doit y avoir au moins 1 participant',
            'payment_type.required' => 'Le type de paiement est requis',
            'payment_method_id.required' => 'La méthode de paiement est requise',
        ];
    }
}


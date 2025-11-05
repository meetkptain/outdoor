<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => 'required|date|after:now',
            'scheduled_time' => 'nullable|date_format:H:i',
            'biplaceur_id' => 'required|exists:biplaceurs,id',
            'site_id' => 'nullable|exists:sites,id',
            'tandem_glider_id' => [
                'nullable',
                'exists:resources,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $resource = \App\Models\Resource::find($value);
                        if ($resource && $resource->type !== 'tandem_glider') {
                            $fail('La ressource sélectionnée doit être un biplace tandem.');
                        }
                    }
                },
            ],
            'vehicle_id' => [
                'nullable',
                'exists:resources,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $resource = \App\Models\Resource::find($value);
                        if ($resource && $resource->type !== 'vehicle') {
                            $fail('La ressource sélectionnée doit être une navette.');
                        }
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_at.required' => 'La date est requise',
            'scheduled_at.after' => 'La date doit être dans le futur',
            'biplaceur_id.required' => 'Le biplaceur est requis',
            'biplaceur_id.exists' => 'Le biplaceur sélectionné n\'existe pas',
        ];
    }
}


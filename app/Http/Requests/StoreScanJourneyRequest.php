<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreScanJourneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'description' => ['sometimes', 'nullable', 'string'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.label' => ['required', 'string', 'max:255'],
            'steps.*.url' => ['required', 'url', 'max:2048'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty()) {
                    $user = $this->user();
                    if ($user && ! $user->canEditProperty((int) $this->input('property_id'))) {
                        $validator->errors()->add('property_id', 'You do not have permission to create journeys for this property.');
                    }
                }
            },
        ];
    }
}

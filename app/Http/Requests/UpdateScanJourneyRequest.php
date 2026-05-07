<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScanJourneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'steps' => ['sometimes', 'required', 'array', 'min:1'],
            'steps.*.label' => ['required_with:steps', 'string', 'max:255'],
            'steps.*.url' => ['required_with:steps', 'url', 'max:2048'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\PropertyIndustry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:255'],
            'industry' => ['nullable', Rule::enum(PropertyIndustry::class)],
        ];
    }
}

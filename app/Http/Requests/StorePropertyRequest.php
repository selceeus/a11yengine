<?php

namespace App\Http\Requests;

use App\Enums\PropertyIndustry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
{
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
            'organization_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id')->where('agency_id', $this->user()->agency_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:255'],
            'industry' => ['nullable', Rule::enum(PropertyIndustry::class)],
        ];
    }
}

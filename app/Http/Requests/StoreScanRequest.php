<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'scan_config' => ['sometimes', 'nullable', 'array'],
            'scan_config.max_pages' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'scan_config.include_patterns' => ['sometimes', 'array'],
            'scan_config.include_patterns.*' => ['string', 'max:500'],
            'scan_config.exclude_patterns' => ['sometimes', 'array'],
            'scan_config.exclude_patterns.*' => ['string', 'max:500'],
            'scan_config.wcag_version' => ['sometimes', 'string', 'in:wcag21,wcag22'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'scan_ids' => ['nullable', 'array'],
            'scan_ids.*' => ['integer', 'exists:scans,id'],
        ];
    }
}

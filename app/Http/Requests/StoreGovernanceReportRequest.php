<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGovernanceReportRequest extends FormRequest
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
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'report_scope' => ['required', 'string', 'in:property,agency'],
            'period_from' => ['required', 'date', 'before:period_to'],
            'period_to' => ['required', 'date', 'after:period_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_from.before' => 'The start date must be before the end date.',
            'period_to.after' => 'The end date must be after the start date.',
            'property_id.exists' => 'The selected property does not exist.',
        ];
    }
}

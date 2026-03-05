<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIssueRequest extends FormRequest
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
        $validStatuses = collect(\App\Enums\IssueStatus::cases())
            ->map(fn ($case) => $case->value)
            ->implode(',');

        return [
            'status' => ['required', 'string', 'in:'.$validStatuses],
        ];
    }
}

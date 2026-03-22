<?php

namespace App\Http\Requests;

use App\Enums\IssueStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateIssueRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
            'action' => ['required', 'string', Rule::in(['status_change', 'assign', 'ignore', 'set_due_date', 'delete'])],
            'status' => ['required_if:action,status_change', 'nullable', 'string', Rule::in(array_column(IssueStatus::cases(), 'value'))],
            'user_id' => ['nullable', 'integer'],
            'due_date' => ['required_if:action,set_due_date', 'nullable', 'date'],
        ];
    }
}

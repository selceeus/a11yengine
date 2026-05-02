<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('role') === 'no-role') {
            $this->merge(['role' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $assignable = [
            UserRole::AgencyAdmin->value,
            UserRole::OrgAdmin->value,
            UserRole::PropAdmin->value,
            UserRole::Editor->value,
            UserRole::Viewer->value,
        ];

        return [
            'role' => ['nullable', 'string', Rule::in($assignable)],
        ];
    }
}

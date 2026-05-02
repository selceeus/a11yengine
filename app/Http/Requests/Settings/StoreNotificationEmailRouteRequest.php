<?php

namespace App\Http\Requests\Settings;

use App\Enums\NotificationEmailCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationEmailRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canManageAgency($this->user()->agency_id);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', Rule::enum(NotificationEmailCategory::class)],
            'email' => ['required', 'email:rfc', 'max:255'],
            'label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category.Rule\Enum' => 'The selected category is invalid.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }
}

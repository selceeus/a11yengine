<?php

namespace App\Http\Requests;

use App\Enums\ApiKeyScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', 'string', Rule::enum(ApiKeyScope::class)],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'scopes.required' => 'At least one scope must be selected.',
            'scopes.min' => 'At least one scope must be selected.',
            'scopes.*.Rule\Enum' => 'One or more selected scopes are invalid.',
            'expires_at.after' => 'The expiry date must be in the future.',
        ];
    }
}

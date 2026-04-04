<?php

namespace App\Http\Requests\Settings;

use App\Enums\MessagingPlatform;
use App\Enums\NotificationEmailCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationWebhookRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', Rule::enum(NotificationEmailCategory::class)],
            'platform' => ['required', 'string', Rule::enum(MessagingPlatform::class)],
            'webhook_url' => ['required', 'url', 'max:1000'],
            'label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category.Rule\Enum' => 'The selected category is invalid.',
            'platform.Rule\Enum' => 'The selected platform is invalid.',
            'webhook_url.url' => 'Please enter a valid webhook URL.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduledScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:once,recurring'],
            'scheduled_at' => ['required_if:type,once', 'nullable', 'date', 'after:now'],
            'frequency' => ['required_if:type,recurring', 'nullable', 'in:daily,weekly,monthly,quarterly'],
        ];
    }
}

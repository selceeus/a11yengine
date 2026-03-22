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
            'run_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'run_day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'run_day_of_month' => ['nullable', 'integer', 'between:1,28'],
        ];
    }
}

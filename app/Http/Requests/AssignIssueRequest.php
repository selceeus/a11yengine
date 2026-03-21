<?php

namespace App\Http\Requests;

use App\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Issue $issue */
        $issue = $this->route('issue');

        return $this->user()->agency_id === $issue->agency_id
            || $this->user()->isSuperUser();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Issue $issue */
        $issue = $this->route('issue');

        return [
            'user_id' => [
                'required',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('agency_id', $issue->agency_id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'The selected user does not exist or does not belong to the same agency.',
        ];
    }
}

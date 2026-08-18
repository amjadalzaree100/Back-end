<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reported_user_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value == $this->user()->id) {
                        $fail('You cannot report yourself.');
                    }
                },
            ],
            'reason' => 'required|string|max:255',
            'details' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'reported_user_id.required' => 'Please specify which user you are reporting',
            'reported_user_id.exists' => 'The reported user does not exist',
            'reported_user_id.different' => 'You cannot report yourself',
            'reason.required' => 'Please provide a reason for this report',
            'reason.max' => 'Reason must not exceed 255 characters',
            'details.max' => 'Details must not exceed 1000 characters',
        ];
    }
}

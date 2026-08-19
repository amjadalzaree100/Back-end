<?php

namespace App\Http\Requests\TaskStatus;

use Illuminate\Foundation\Http\FormRequest;

class ReorderTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statuses' => 'required|array',
            'statuses.*.id' => 'required|exists:task_statuses,id',
            'statuses.*.position' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'statuses.required' => 'Statuses array is required',
            'statuses.array' => 'Statuses must be an array',
            'statuses.*.id.required' => 'Status ID is required',
            'statuses.*.id.exists' => 'Status does not exist',
            'statuses.*.position.required' => 'Position is required',
            'statuses.*.position.integer' => 'Position must be an integer',
            'statuses.*.position.min' => 'Position must be at least 0',
        ];
    }
}

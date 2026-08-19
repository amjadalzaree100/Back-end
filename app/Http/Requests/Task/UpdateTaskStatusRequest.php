<?php
// app/Http/Requests/Task/UpdateTaskStatusRequest.php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_id' => 'required|exists:task_statuses,id',
            'position' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'status_id.required' => 'Status ID is required',
            'status_id.exists' => 'Selected status does not exist',
        ];
    }
}

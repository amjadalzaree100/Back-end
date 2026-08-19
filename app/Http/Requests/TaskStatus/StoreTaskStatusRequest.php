<?php

namespace App\Http\Requests\TaskStatus;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|min:2',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Status name is required',
            'name.max' => 'Status name must not exceed 100 characters',
            'name.min' => 'Status name must be at least 2 characters',
        ];
    }
}

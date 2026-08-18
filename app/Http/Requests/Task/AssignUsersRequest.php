<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class AssignUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'At least one user ID is required',
            'user_ids.array' => 'User IDs must be an array',
            'user_ids.min' => 'At least one user must be assigned',
            'user_ids.*.required' => 'Each user ID is required',
            'user_ids.*.exists' => 'One or more selected users do not exist',
        ];
    }
}

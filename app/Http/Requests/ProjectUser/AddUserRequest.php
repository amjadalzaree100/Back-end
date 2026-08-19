<?php

namespace App\Http\Requests\ProjectUser;

use Illuminate\Foundation\Http\FormRequest;

class AddUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:manager,member,observer',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required',
            'user_id.exists' => 'User does not exist',
            'role.required' => 'Role is required',
            'role.in' => 'Role must be manager, member, or observer',
        ];
    }
}

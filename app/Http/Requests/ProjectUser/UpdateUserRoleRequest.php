<?php

namespace App\Http\Requests\ProjectUser;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => 'required|in:manager,member,observer',
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Role is required',
            'role.in' => 'Role must be manager, member, or observer',
        ];
    }
}

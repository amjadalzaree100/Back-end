<?php

namespace App\Http\Requests\api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => 'required|string',
            'password' => 'required|string|min:6',
            'remember' => 'boolean',
            'device_name' => 'sometimes|string|max:50'
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Email or username is required.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'remember.boolean' => 'Remember me must be true or false.',
        ];
    }

    public function getLoginField(): string
    {
        $login = $this->input('login');

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        return 'username';
    }

    public function getLoginValue(): string
    {
        return $this->input('login');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('login')) {
            $this->merge([
                'login' => trim($this->login)
            ]);
        }
    }
}

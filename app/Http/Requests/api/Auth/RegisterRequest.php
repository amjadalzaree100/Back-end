<?php

namespace App\Http\Requests\api\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'username' => 'required|string|max:100|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' =>  [
                'required',
                'confirmed',
                Password::defaults()
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'name.max' => 'Name must not exceed 100 characters.',
            'username.required' => 'Username is required.',
            'username.unique' => 'Username is already taken.',
            'username.max' => 'Username must not exceed 100 characters.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'Email address is already registered.',
            'email.max' => 'Email must not exceed 255 characters.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower($this->email),
            'username' => strtolower($this->username)
        ]);
    }
}

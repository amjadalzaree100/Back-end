<?php

namespace App\Http\Requests\api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6'
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.exists' => 'This email is not registered in our system.',
            'code.required' => 'Verification code is required.',
            'code.string' => 'Verification code must be a string.',
            'code.size' => 'Verification code must be exactly 6 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower($this->email),
            'code' => trim($this->code)
        ]);
    }
}

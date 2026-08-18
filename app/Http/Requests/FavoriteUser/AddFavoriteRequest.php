<?php

namespace App\Http\Requests\FavoriteUser;

use Illuminate\Foundation\Http\FormRequest;

class AddFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value == $this->user()->id) {
                        $fail('You cannot add yourself to favorites.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required',
            'user_id.exists' => 'User does not exist',
            'user_id.different' => 'You cannot add yourself to favorites',
        ];
    }
}

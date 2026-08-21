<?php

namespace App\Http\Requests\Rating;

use Illuminate\Foundation\Http\FormRequest;

class StoreRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rated_user_id' => 'required|exists:users,id',
            'rating' => 'required|integer|min:1|max:5',
        ];
    }

    public function messages(): array
    {
        return [
            'rated_user_id.required' => 'Please specify which user you are rating',
            'rated_user_id.exists' => 'The rated user does not exist',
            'rating.required' => 'Rating is required',
            'rating.integer' => 'Rating must be an integer',
            'rating.min' => 'Rating must be at least 1',
            'rating.max' => 'Rating must not exceed 5',
        ];
    }
}

<?php

namespace App\Http\Requests\ProjectReaction;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reaction_type' => 'required|string|in:like,love,dislike',
        ];
    }

    public function messages(): array
    {
        return [
            'reaction_type.required' => 'Reaction type is required',
            'reaction_type.in' => 'Reaction must be one of: like, love, dislike ',
        ];
    }
}

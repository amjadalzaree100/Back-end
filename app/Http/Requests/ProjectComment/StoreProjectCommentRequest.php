<?php

namespace App\Http\Requests\ProjectComment;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|min:1|max:5000',
            'parent_id' => 'nullable|exists:project_comments,id',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Comment content is required',
            'content.min' => 'Comment must be at least 1 characters',
            'content.max' => 'Comment cannot exceed 5000 characters',
            'parent_id.exists' => 'Parent comment does not exist',
        ];
    }
}

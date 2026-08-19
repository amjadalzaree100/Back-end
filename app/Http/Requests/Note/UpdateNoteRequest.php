<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'color' => 'nullable|string|max:7|regex:/^#[a-fA-F0-9]{6}$/',
        ];
    }

    public function messages(): array
    {
        return [
            'title.max' => 'Title must not exceed 255 characters',
            'color.max' => 'Color code must not exceed 7 characters',
            'color.regex' => 'Color must be a valid hex code (e.g., #ffffff)',
        ];
    }
}

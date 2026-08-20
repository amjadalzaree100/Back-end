<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'image' => 'nullable|string|max:255|url',
            'status' => 'nullable|in:active,paused,completed',
            'visibility' => 'nullable|in:private,public',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'allow_commit' => 'nullable|boolean',
            'allow_join_requests' => 'nullable|boolean',
            'allow_reactions' => 'nullable|boolean',
            'chain_id' => 'nullable|exists:chains,id',

        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Project name is required',
            'name.max' => 'Project name must not exceed 255 characters',
        ];
    }
}

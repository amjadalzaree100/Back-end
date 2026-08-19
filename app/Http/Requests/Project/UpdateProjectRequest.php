<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
        'name' => 'sometimes|string|max:255',
        'description' => 'nullable|string|max:5000',
        'image' => 'nullable|string|max:255|url',
        'visibility' => 'nullable|in:private,public',
        'allow_join_requests' => 'sometimes|boolean',
        'status' => 'nullable|in:active,paused,completed', 
        'allow_commit' => 'nullable|boolean',
        'allow_reactions' => 'nullable|boolean',
    ];
    }
}

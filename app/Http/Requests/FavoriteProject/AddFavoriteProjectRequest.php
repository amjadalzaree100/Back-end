<?php

namespace App\Http\Requests\FavoriteProject;

use Illuminate\Foundation\Http\FormRequest;

class AddFavoriteProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|exists:projects,id',
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required' => 'Project ID is required',
            'project_id.exists' => 'Project does not exist',
        ];
    }
}

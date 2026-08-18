<?php

namespace App\Http\Requests\TaskDependency;

use Illuminate\Foundation\Http\FormRequest;

class AddDependencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'depends_on_task_id' => 'required|exists:tasks,id',
            'type' => 'nullable|in:FS,SS,FF,SF',
        ];
    }

    public function messages(): array
    {
        return [
            'depends_on_task_id.required' => 'Dependency task ID is required',
            'depends_on_task_id.exists' => 'Dependency task does not exist',
            'type.in' => 'Type must be FS, SS, FF, or SF',
        ];
    }
}

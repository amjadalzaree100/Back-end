<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $user = $this->user();
        if (!$project) return false;
        return $project->isOwner($user->id) || $project->isManager($user->id);
    }

    public function rules(): array
    {
        return [
            'group_id' => 'required|exists:groups,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'due_date' => 'nullable|date|after_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'group_id.required' => 'Group ID is required',
            'title.required' => 'Task title is required',
            'due_date.after_or_equal' => 'Due date cannot be in the past',
        ];
    }
}

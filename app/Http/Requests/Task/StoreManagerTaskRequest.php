<?php

namespace app\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreManagerTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $user = $this->user();

        if (!$project) {
            return false;
        }

        if ($project->isOwner($user->id)) {
            return true;
        }

        $group = $this->route('group');
        if ($group && $group->isManager($user->id)) {
            return true;
        }

        return false;
    }

    public function rules()
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'due_date' => 'nullable|date|after_or_equal:today',
            'allow_subtasks' => 'nullable|boolean',
        ];

        if ($this->input('allow_subtasks') == true) {
            // Manager Task with subtasks: no assignment
            $rules['assigned_to'] = 'prohibited';
            $rules['assignees'] = 'prohibited';
        } else {
            $rules['assigned_to'] = 'nullable|exists:users,id';
            $rules['assignees'] = 'nullable|array';
        }

        return $rules;
    }
    public function messages(): array
    {
        return [
            'title.required' => 'Task title is required',
            'priority.in' => 'Priority must be low, medium, high, or urgent',
            'due_date.after_or_equal' => 'Due date cannot be in the past',
        ];
    }
}

<?php

namespace app\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $parentTask = $this->route('parentTask');
        $user = $this->user();

        if (!$parentTask) {
            return false;
        }

        if (!$parentTask->allow_subtasks) {
            return false;
        }

        if ($parentTask->project->isOwner($user->id)) {
            return true;
        }

        if ($parentTask->group_id) {
            $group = $parentTask->group;
            if ($group && $group->isManager($user->id)) {
                return true;
            }
        }

        return false;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'due_date' => 'nullable|date|after_or_equal:today',
            'assigned_to' => 'required|array|min:1',
            'assigned_to.*' => 'exists:users,id',
        ];
    }
    public function messages(): array
    {
        return [
            'title.required' => 'Subtask title is required',
            'assigned_to.required' => 'You must assign at least one user',
            'assigned_to.*.exists' => 'One or more selected users do not exist',
            'due_date.after_or_equal' => 'Due date cannot be in the past',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'assigned_to' => array_unique($this->assigned_to ?? [])
        ]);
    }
}

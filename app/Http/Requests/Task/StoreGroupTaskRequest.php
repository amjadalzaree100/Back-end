<?php

namespace App\Http\Requests\Task;

use App\Models\Group;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class StoreGroupTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $project = Project::find($this->route('project')->id);

        if (!$project) {
            return false;
        }

        // Project owner can always create
        if ($project->isOwner($user->id)) {
            return true;
        }

        // Group manager can create tasks for their group
        $groupId = $this->input('assigned_group_id');
        if ($groupId) {
            $group = Group::find($groupId);
            return $group && $group->manager_id === $user->id;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'assigned_group_id' => 'required|integer|exists:groups,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'due_date' => 'nullable|date|after_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'assigned_group_id.required' => 'Group ID is required',
            'title.required' => 'Task title is required',
            'due_date.after_or_equal' => 'Due date cannot be in the past',
        ];
    }
}
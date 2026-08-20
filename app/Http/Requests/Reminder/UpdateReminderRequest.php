<?php

namespace App\Http\Requests\Reminder;

use App\Models\Reminder;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'message' => 'nullable|string|max:1000',
            'remind_at' => 'sometimes|date|after:now',
            'task_ids' => 'nullable|array',
            'task_ids.*' => 'exists:tasks,id',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $taskIds = $this->input('task_ids', []);

            // 1. Validate that all tasks belong to projects the user has access to
            if (!empty($taskIds)) {
                $userId = $this->user()->id;
                $validTaskIds = Task::whereIn('id', $taskIds)
                    ->whereHas('project', function ($q) use ($userId) {
                        $q->where('created_by', $userId)
                            ->orWhereHas('users', fn($sub) => $sub->where('user_id', $userId));
                    })
                    ->pluck('id')
                    ->toArray();

                $invalidTasks = array_diff($taskIds, $validTaskIds);
                if (!empty($invalidTasks)) {
                    $validator->errors()->add(
                        'task_ids',
                        'You do not have access to one or more selected tasks (task IDs: ' . implode(', ', $invalidTasks) . ').'
                    );
                    return;
                }
            }

            if (!$this->has('remind_at') && !$this->has('task_ids')) {
                return;
            }

            $remindAt = $this->input('remind_at', $this->route('reminder')->remind_at);

            try {
                Reminder::validateReminderDateAgainstTasks($taskIds, $remindAt);
            } catch (\Exception $e) {
                $validator->errors()->add('remind_at', $e->getMessage());
            }
        });
    }
}
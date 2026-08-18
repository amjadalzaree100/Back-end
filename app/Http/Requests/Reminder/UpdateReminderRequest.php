<?php

namespace App\Http\Requests\Reminder;

use App\Models\Reminder;
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
            if (!$this->has('remind_at') && !$this->has('task_ids')) {
                return;
            }

            $taskIds = $this->input('task_ids', []);
            $remindAt = $this->input('remind_at', $this->route('reminder')->remind_at);

            try {
                Reminder::validateReminderDateAgainstTasks($taskIds, $remindAt);
            } catch (\Exception $e) {
                $validator->errors()->add('remind_at', $e->getMessage());
            }
        });
    }
}
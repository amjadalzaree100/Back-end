<?php

namespace App\Http\Requests\Reminder;

use Illuminate\Foundation\Http\FormRequest;

class SnoozeReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_remind_at' => 'required|date|after:now',
        ];
    }
}
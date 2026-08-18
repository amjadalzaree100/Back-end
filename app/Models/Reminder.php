<?php

namespace App\Models;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reminder extends Model
{
    use HasFactory;

    protected $table = 'reminders';

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'remind_at',
        'status',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'reminder_task');
    }

    /**
     * Validation: remind_at must be before the earliest due_date of associated tasks.
     */
    public static function validateReminderDateAgainstTasks(array $taskIds, string $remindAt): void
    {
        if (empty($taskIds)) {
            return;
        }

        $earliestDueDate = Task::whereIn('id', $taskIds)
            ->whereNotNull('due_date')
            ->min('due_date');

        if ($earliestDueDate && strtotime($remindAt) > strtotime($earliestDueDate)) {
            abort(422, 'Reminder time must be before the earliest task due date.');
        }
    }
}
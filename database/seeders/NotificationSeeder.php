<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        $projects = Project::all();
        $tasks = Task::all();
        $actors = User::all();

        $types = [
            'task_assigned',
            'comment_added',
            'project_invitation',
            'join_request',
            'task_completed',
            'mention',
            'deadline_reminder',
            'project_update',
        ];

        foreach ($users as $user) {
            $count = rand(8, 12);

            for ($i = 0; $i < $count; $i++) {
                $type = $types[array_rand($types)];
                $payload = $this->buildNotification($type, $user, $projects, $tasks, $actors);

                $isRead = rand(1, 100) <= 40;
                $readAt = $isRead ? now()->subHours(rand(1, 120)) : null;
                $createdAt = $isRead
                    ? now()->subHours(rand(2, 168))
                    : now()->subHours(rand(0, 48));

                Notification::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'title' => $payload['title'],
                    'message' => $payload['message'],
                    'data' => $payload['data'],
                    'is_read' => $isRead,
                    'read_at' => $readAt,
                    'action_url' => $payload['action_url'],
                    'icon' => $payload['icon'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }

    private function buildNotification(
        string $type,
        User $user,
        $projects,
        $tasks,
        $actors
    ): array {
        $actor = $actors
            ->reject(fn ($u) => $u->id === $user->id)
            ->shuffle()
            ->first() ?? $user;

        // ~15% of notifications have no action link, the rest point to the resource.
        $maybeUrl = function (?string $url): ?string {
            return rand(1, 100) <= 15 ? null : $url;
        };

        $icons = [
            'task_assigned' => 'task-icon',
            'comment_added' => 'comment-icon',
            'project_invitation' => 'user-icon',
            'join_request' => 'user-icon',
            'task_completed' => 'check-icon',
            'mention' => 'at-icon',
            'deadline_reminder' => 'clock-icon',
            'project_update' => 'project-icon',
        ];

        $base = [
            'title' => '',
            'message' => '',
            'data' => [],
            'action_url' => null,
            'icon' => $icons[$type] ?? null,
        ];

        switch ($type) {
            case 'task_assigned':
                $task = $tasks->first(fn ($t) => $t->assigned_to === $user->id) ?? $tasks->first();
                if (! $task) {
                    break;
                }
                $base['title'] = 'Task assigned';
                $base['message'] = "You've been assigned to '{$task->title}'";
                $base['data'] = ['task_id' => $task->id, 'project_id' => $task->project_id];
                $base['action_url'] = $maybeUrl('/tasks/'.$task->id);
                break;

            case 'comment_added':
                $task = $tasks->first() ?? $tasks->first(fn ($t) => $t->assigned_to === $user->id);
                if (! $task) {
                    break;
                }
                $base['title'] = 'New comment';
                $base['message'] = "{$actor->name} commented on your task '{$task->title}'";
                $base['data'] = [
                    'task_id' => $task->id,
                    'project_id' => $task->project_id,
                    'commenter_id' => $actor->id,
                    'commenter_name' => $actor->name,
                ];
                $base['action_url'] = $maybeUrl('/tasks/'.$task->id);
                break;

            case 'project_invitation':
                $project = $projects->first();
                if (! $project) {
                    break;
                }
                $base['title'] = 'Project invitation';
                $base['message'] = "You've been invited to join '{$project->name}'";
                $base['data'] = [
                    'project_id' => $project->id,
                    'inviter_id' => $actor->id,
                    'inviter_name' => $actor->name,
                ];
                $base['action_url'] = $maybeUrl('/projects/'.$project->id);
                break;

            case 'join_request':
                $project = $projects->first();
                if (! $project) {
                    break;
                }
                $base['title'] = 'Join request';
                $base['message'] = "{$actor->name} requested to join your project '{$project->name}'";
                $base['data'] = [
                    'project_id' => $project->id,
                    'requester_id' => $actor->id,
                    'requester_name' => $actor->name,
                ];
                $base['action_url'] = $maybeUrl('/projects/'.$project->id);
                break;

            case 'task_completed':
                $task = $tasks->first(fn ($t) => $t->completed_at !== null) ?? $tasks->first();
                if (! $task) {
                    break;
                }
                $base['title'] = 'Task completed';
                $base['message'] = "Task '{$task->title}' marked as done";
                $base['data'] = ['task_id' => $task->id, 'project_id' => $task->project_id];
                $base['action_url'] = $maybeUrl('/tasks/'.$task->id);
                break;

            case 'mention':
                $task = $tasks->first();
                if (! $task) {
                    break;
                }
                $base['title'] = 'You were mentioned';
                $base['message'] = "{$actor->name} mentioned you in a comment";
                $base['data'] = [
                    'task_id' => $task->id,
                    'project_id' => $task->project_id,
                    'commenter_id' => $actor->id,
                    'commenter_name' => $actor->name,
                ];
                $base['action_url'] = $maybeUrl('/tasks/'.$task->id);
                break;

            case 'deadline_reminder':
                $task = $tasks->first(fn ($t) => $t->due_date !== null) ?? $tasks->first();
                if (! $task) {
                    break;
                }
                $due = $task->due_date;
                $when = $due->isToday()
                    ? 'today'
                    : ($due->isTomorrow() ? 'tomorrow' : 'on '.$due->format('M j'));
                $base['title'] = 'Deadline reminder';
                $base['message'] = "Task '{$task->title}' is due {$when}";
                $base['data'] = ['task_id' => $task->id, 'project_id' => $task->project_id, 'due_date' => $due->format('Y-m-d')];
                $base['action_url'] = $maybeUrl('/tasks/'.$task->id);
                break;

            case 'project_update':
                $project = $projects->first();
                if (! $project) {
                    break;
                }
                $base['title'] = 'Project updated';
                $base['message'] = "Project '{$project->name}' status changed to '{$project->status}'";
                $base['data'] = ['project_id' => $project->id, 'status' => $project->status];
                $base['action_url'] = $maybeUrl('/projects/'.$project->id);
                break;
        }

        // ~20% of notifications skip the icon.
        $base['icon'] = rand(1, 100) <= 20 ? null : $base['icon'];

        return $base;
    }
}
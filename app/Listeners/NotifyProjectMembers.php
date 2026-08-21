<?php

namespace App\Listeners;

use App\Events\ProjectNotificationEvent;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyProjectMembers implements ShouldQueue
{
    public function handle(ProjectNotificationEvent $event): void
    {
        $svc = app(NotificationService::class);

        [$title, $message, $icon, $url] = match ($event->scenario) {
            'user_added' => [
                'Added to Project',
                "You have been added to project: {$event->project->name}",
                '👋',
                "/projects/{$event->project->id}",
            ],
            'user_removed' => [
                'Removed from Project',
                "You have been removed from project: {$event->project->name}",
                '🚫',
                "/projects/{$event->project->id}",
            ],
            'role_changed' => [
                'Role Changed',
                $event->extra && isset($event->extra['role'])
                    ? "Your role in \"{$event->project->name}\" has been changed to {$event->extra['role']}"
                    : "Your role in \"{$event->project->name}\" has been changed",
                '🔄',
                "/projects/{$event->project->id}",
            ],
            'ownership_transferred' => [
                'Ownership Transferred',
                $event->actor
                    ? "{$event->actor->name} transferred ownership of \"{$event->project->name}\""
                    : "Ownership of \"{$event->project->name}\" has been transferred",
                '👑',
                "/projects/{$event->project->id}",
            ],
            'status_changed' => [
                'Project Status Changed',
                $event->extra && isset($event->extra['status'])
                    ? "Project \"{$event->project->name}\" status changed to {$event->extra['status']}"
                    : "Project \"{$event->project->name}\" status has changed",
                '📊',
                "/projects/{$event->project->id}",
            ],
            'user_left' => [
                'Member Left',
                $event->actor
                    ? "{$event->actor->name} has left project: {$event->project->name}"
                    : "A member has left project: {$event->project->name}",
                '🚪',
                "/projects/{$event->project->id}",
            ],
            'project_commented' => [
                'New Project Comment',
                $event->actor
                    ? "{$event->actor->name} commented on project: {$event->project->name}"
                    : "A comment was added to project: {$event->project->name}",
                '💬',
                "/projects/{$event->project->id}",
            ],
            'member_suspended' => [
                'Member Suspended',
                $event->extra && isset($event->extra['project_count'])
                    ? "User {$event->actor?->name} has been suspended from the platform. They were a member of {$event->extra['project_count']} project(s) you own."
                    : "A member has been suspended from the platform.",
                '⚠️',
                null,
            ],
            'member_reactivated' => [
                'Member Reactivated',
                $event->extra && isset($event->extra['project_count'])
                    ? "User {$event->actor?->name} has been reactivated on the platform. They are a member of {$event->extra['project_count']} project(s) you own."
                    : "A member has been reactivated on the platform.",
                '✅',
                null,
            ],
            'manager_left_with_groups' => [
                'Manager Left Project',
                $event->extra && isset($event->extra['group_count'])
                    ? "{$event->actor?->name} has left project: {$event->project->name}. They were managing {$event->extra['group_count']} group(s)."
                    : "{$event->actor?->name} has left project: {$event->project->name}.",
                '🚪',
                "/projects/{$event->project->id}",
            ],
            'user_left_project' => [
                'Member Left Project',
                $event->actor
                    ? "{$event->actor->name} has left project: {$event->project->name}"
                    : "A member has left project: {$event->project->name}",
                '🚪',
                "/projects/{$event->project->id}",
            ],
            'project_no_managers' => [
                'Project Has No Managers',
                "Your project \"{$event->project->name}\" now has no managers.",
                '⚠️',
                "/projects/{$event->project->id}",
            ],
            'manager_demoted' => [
                'Manager Demoted',
                $event->extra && isset($event->extra['demoted_user_name']) && isset($event->extra['new_role']) && isset($event->extra['group_count'])
                    ? "You demoted {$event->extra['demoted_user_name']} from manager to {$event->extra['new_role']}. They were managing {$event->extra['group_count']} group(s), which are now without a manager."
                    : "A manager has been demoted in project: {$event->project->name}",
                '🔄',
                "/projects/{$event->project->id}",
            ],
            'manager_demoted_self' => [
                'Role Demoted',
                $event->extra && isset($event->extra['new_role']) && isset($event->extra['group_count'])
                    ? "You have been demoted from manager to {$event->extra['new_role']} in \"{$event->project->name}\". You were managing {$event->extra['group_count']} group(s), which are now without a manager."
                    : "You have been demoted in project: {$event->project->name}",
                '🔄',
                "/projects/{$event->project->id}",
            ],
            default => [
                'Project Update',
                "Update on project: {$event->project->name}",
                '📌',
                "/projects/{$event->project->id}",
            ],
        };

        $svc->sendToMany($event->userIds, $title, $message, 'project', null, $url, $icon);
    }
}

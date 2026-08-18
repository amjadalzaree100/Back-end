<?php

namespace App\Services;

use App\Models\Notification;
use App\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function send($userId, $title, $message, $type = 'info', $data = null, $actionUrl = null , $icon = null)
    {
        try {
            $notification = Notification::create([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'data' => $data,
                'action_url' => $actionUrl,
                'icon' => $icon,
            ]);

            broadcast(new NotificationSent($notification));



            return $notification;
        } catch (\Exception $e) {
            Log::error('Failed to send notification: ' . $e->getMessage());
            return null;
        }
    }

    public function sendToMany($userIds, $title, $message, $type = 'info', $data = null, $actionUrl = null, $icon = null)
    {
        $notifications = [];
        foreach ($userIds as $userId) {
            $notifications[] = $this->send($userId, $title, $message, $type, $data, $actionUrl, $icon);
        }
        return $notifications;
    }

    public function sendToProjectMembers($projectId, $title, $message, $type = 'info', $data = null, $actionUrl = null, $icon = null)
    {
        $project = \App\Models\Project::findOrFail($projectId);
        $userIds = $project->users()->pluck('users.id')->toArray();

        return $this->sendToMany($userIds, $title, $message, $type, $data, $actionUrl, $icon);
    }

    public function getUserNotifications($userId, $limit = 20, $onlyUnread = false)
    {
        $query = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($onlyUnread) {
            $query->unread();
        }

        return $query->limit($limit)->get();
    }

    public function markAsRead( $notificationId, $userId)
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if ($notification) {
            $notification->markAsRead();
            return true;
        }

        return false;
    }

    public function markAllAsRead($userId)
    {
        return Notification::where('user_id', $userId)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }

    public function deleteOldNotifications($days = 30)
    {
        return Notification::where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}

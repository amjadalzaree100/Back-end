<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    use ApiResponseTrait;

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $limit = $request->get('limit', 20);
        $onlyUnread = $request->get('only_unread', false);

        $notifications = $this->notificationService->getUserNotifications(
            Auth::id(),
            $limit,
            $onlyUnread
        );

        $unreadCount = Notification::where('user_id', Auth::id())
            ->unread()
            ->count();

        return $this->successResponse([
            'notifications' => NotificationResource::collection($notifications),
            'unread_count' => $unreadCount,
            'total' => Notification::where('user_id', Auth::id())->count(),
        ]);
    }

    public function markAsRead($id)
    {
        $result = $this->notificationService->markAsRead($id, Auth::id());

        if ($result) {
            return $this->successResponse(null, 'Notification marked as read');
        }

        return $this->errorResponse('Notification not found', 404);
    }

    public function markAllAsRead()
    {
        $count = $this->notificationService->markAllAsRead(Auth::id());

        return $this->successResponse([
            'marked_count' => $count
        ], 'All notifications marked as read');
    }

    public function destroy($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if ($notification) {
            $notification->delete();
            return $this->successResponse(null, 'Notification deleted');
        }

        return $this->errorResponse('Notification not found', 404);
    }

    public function test(Request $request)
    {
        if (app()->environment('production')) {
            return $this->errorResponse('Not available in production', 403);
        }

        $request->validate([
            'message' => 'required|string',
            'type' => 'nullable|string|in:info,success,warning,error,urgent',
        ]);

        $notification = $this->notificationService->send(
            Auth::id(),
            'Test Notification',
            $request->message,
            $request->type ?? 'info',
            ['test' => true]
        );

        if ($notification) {
            return $this->successResponse($notification, 'Test notification sent');
        }

        return $this->errorResponse('Failed to send notification', 500);
    }
}

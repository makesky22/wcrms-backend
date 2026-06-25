<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     * List the authenticated user's 50 most recent notifications, unread first.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderBy('is_read')
            ->latest()
            ->take(50)
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $notifications->where('is_read', false)->count(),
        ]);
    }

    /**
     * PATCH /api/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->update(['is_read' => true]);

        return response()->json($notification);
    }

    /**
     * POST /api/notifications/read-all
     * Mark all of the authenticated user's notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}

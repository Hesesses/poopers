<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(NotificationResource::collection($notifications));
    }

    public function markAsRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_ids' => ['required', 'array'],
            'notification_ids.*' => ['integer'],
        ]);

        Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $validated['notification_ids'])
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Notifications marked as read.']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}

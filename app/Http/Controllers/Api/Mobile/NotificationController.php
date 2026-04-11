<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // ── Notifications ─────────────────────────────────────────────────────────

    public function notifications(Request $request): JsonResponse
    {
        $user      = $request->user();
        $paginated = $user->notifications()->latest()->paginate(30);
        return response()->json([
            'notifications' => $paginated->items(),
            'unread_count'  => $user->unreadNotifications()->count(),
            'total'         => $paginated->total(),
        ]);
    }

    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->where('id', $id)->first()?->markAsRead();
        return response()->json(['success' => true]);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    }
}

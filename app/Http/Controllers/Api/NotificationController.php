<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // ── GET /api/notifications ────────────────────────────────────────────────
    public function index(Request $request)
    {
        $notifications = TicketNotification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        $unreadCount = TicketNotification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    // ── PATCH /api/notifications/{id}/read ────────────────────────────────────
    public function markRead(Request $request, string $id)
    {
        $notification = TicketNotification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notifikasi ditandai sudah dibaca.',
        ]);
    }

    // ── PATCH /api/notifications/read-all ────────────────────────────────────
    public function markAllRead(Request $request)
    {
        TicketNotification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'Semua notifikasi ditandai sudah dibaca.',
        ]);
    }

    // ── DELETE /api/notifications/{id} ───────────────────────────────────────
    public function destroy(Request $request, string $id)
    {
        $notification = TicketNotification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'message' => 'Notifikasi berhasil dihapus.',
        ]);
    }

    // ── DELETE /api/notifications ─────────────────────────────────────────────
    public function destroyAll(Request $request)
    {
        TicketNotification::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'message' => 'Semua notifikasi berhasil dihapus.',
        ]);
    }
}

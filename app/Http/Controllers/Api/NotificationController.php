<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketNotification;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    #[OA\Get(
        path: "/api/notifications",
        summary: "Menampilkan daftar notifikasi",
        description: "Mengambil daftar notifikasi milik user yang sedang login.",
        tags: ["Notifications"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Daftar notifikasi berhasil diambil"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
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
    #[OA\Patch(
        path: "/api/notifications/{id}/read",
        summary: "Menandai satu notifikasi sudah dibaca",
        description: "Mengubah status satu notifikasi menjadi sudah dibaca berdasarkan ID.",
        tags: ["Notifications"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID notifikasi",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Notifikasi berhasil ditandai sudah dibaca"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Notifikasi tidak ditemukan")
        ]
    )]
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
    #[OA\Patch(
        path: "/api/notifications/read-all",
        summary: "Menandai semua notifikasi sudah dibaca",
        description: "Mengubah semua notifikasi user menjadi sudah dibaca.",
        tags: ["Notifications"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Semua notifikasi berhasil ditandai sudah dibaca"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
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
    
    #[OA\Delete(
        path: "/api/notifications/{id}",
        summary: "Menghapus satu notifikasi",
        description: "Menghapus notifikasi berdasarkan ID.",
        tags: ["Notifications"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID notifikasi",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Notifikasi berhasil dihapus"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Notifikasi tidak ditemukan")
        ]
    )]
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
    #[OA\Delete(
        path: "/api/notifications",
        summary: "Menghapus semua notifikasi",
        description: "Menghapus semua notifikasi milik user yang sedang login.",
        tags: ["Notifications"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Semua notifikasi berhasil dihapus"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    // ── DELETE /api/notifications ─────────────────────────────────────────────
    public function destroyAll(Request $request)
    {
        TicketNotification::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'message' => 'Semua notifikasi berhasil dihapus.',
        ]);
    }
}

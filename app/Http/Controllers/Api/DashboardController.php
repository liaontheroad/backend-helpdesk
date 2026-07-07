<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Models\TicketHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: "/api/dashboard/stats",
        summary: "Menampilkan statistik dashboard",
        description: "Mengambil ringkasan statistik tiket seperti total tiket, tiket open, in progress, resolved, dan closed.",
        tags: ["Dashboard"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Statistik dashboard berhasil diambil"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    // ── GET /api/dashboard/stats ──────────────────────────────────────────────
    public function stats(Request $request)
    {
        $user = $request->user();

        if ($user->isUser()) {
            $stats = $this->userStatsArray($user);
        } else {
            $stats = $this->staffStatsArray($user);
        }

        return response()->json([
            ...$stats,
            'activities' => $this->recentActivities($user),
        ]);
    }
    #[OA\Get(
    path: "/api/dashboard/recent-tickets",
    summary: "Menampilkan tiket terbaru",
    description: "Mengambil daftar tiket terbaru untuk ditampilkan pada dashboard.",
    tags: ["Dashboard"],
    security: [["bearerAuth" => []]],
    responses: [
        new OA\Response(response: 200, description: "Tiket terbaru berhasil diambil"),
        new OA\Response(response: 401, description: "Unauthenticated")
    ]
)]
    // ── GET /api/dashboard/recent-tickets ─────────────────────────────────────
    public function recentTickets(Request $request)
    {
        $user  = $request->user();
        $query = Ticket::with('reporter:id,name')
            ->orderBy('updated_at', 'desc')
            ->limit(5);

        if ($user->isUser()) {
            $query->where('reported_by_id', $user->id);
        }

        return response()->json([
            'tickets' => $query->get(),
        ]);
    }

    // ── Private: stats for regular users ─────────────────────────────────────
    private function userStatsArray(User $user): array
    {
        $counts = Ticket::where('reported_by_id', $user->id)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($counts);

        return [
            'total_tickets'       => $total,
            'open_tickets'        => $counts['open'] ?? 0,
            'in_progress_tickets' => $counts['in_progress'] ?? 0,
            'resolved_tickets'    => $counts['resolved'] ?? 0,
            'closed_tickets'      => $counts['closed'] ?? 0,
            'my_tickets'          => $total,
        ];
    }
    // ── Private: stats for helpdesk / admin ──────────────────────────────────
    private function staffStatsArray(User $user): array
{
    // ==========================
    // ADMIN
    // ==========================
    if ($user->isAdmin()) {

        $counts = Ticket::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total_tickets'       => array_sum($counts),
            'open_tickets'        => $counts['open'] ?? 0,
            'in_progress_tickets' => $counts['in_progress'] ?? 0,
            'resolved_tickets'    => $counts['resolved'] ?? 0,
            'closed_tickets'      => $counts['closed'] ?? 0,
            'my_tickets'          => 0,

            'total_users' => User::where('role', 'user')->count(),
            'total_helpdesks' => User::where('role', 'helpdesk')->count(),
            'unassigned' => Ticket::whereNull('assigned_to_id')
                ->where('status', 'open')
                ->count(),
        ];
    }

    // ==========================
    // HELPDESK
    // ==========================

    $counts = Ticket::where('assigned_to_id', $user->id)
        ->select('status', DB::raw('COUNT(*) as count'))
        ->groupBy('status')
        ->pluck('count', 'status')
        ->toArray();

    return [
        'total_tickets'       => array_sum($counts),
        'open_tickets'        => $counts['open'] ?? 0,
        'in_progress_tickets' => $counts['in_progress'] ?? 0,
        'resolved_tickets'    => $counts['resolved'] ?? 0,
        'closed_tickets'      => $counts['closed'] ?? 0,
        'my_tickets'          => array_sum($counts),
    ];
}


    #[OA\Get(
        path: "/api/dashboard/statistics",
        summary: "Dashboard statistics",
        description: "Returns ticket statistics including overview, priority, category distribution, and role-specific statistics for admin or helpdesk.",
        tags: ["Dashboard"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Statistics retrieved successfully"
            ),
            new OA\Response(
                response: 401,
                description: "Unauthenticated"
            ),
        ]
    )]
// ── GET /api/dashboard/statistics ────────────────────────────────────────────
public function statistics(Request $request)
    {
        $user = $request->user();

        // Base query
        $ticketQuery = Ticket::query();

        // Regular users only see their own statistics
        if ($user->isUser()) {
            $ticketQuery->where('reported_by_id', $user->id);
        }

        // Helpdesk only sees assigned tickets
        elseif ($user->isHelpdesk() && !$user->isAdmin()) {
            $ticketQuery->where('assigned_to_id', $user->id);
        }

        // ===== Overview =====
        $statusCounts = (clone $ticketQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // ===== Priority =====
        $priorityCounts = (clone $ticketQuery)
            ->select('priority', DB::raw('COUNT(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority');

        // ===== Category =====
        $categoryCounts = (clone $ticketQuery)
            ->select('category', DB::raw('COUNT(*) as total'))
            ->groupBy('category')
            ->orderBy('total', 'desc')
            ->get();

        $response = [
            'overview' => [
                'total' => (clone $ticketQuery)->count(),
                'open' => $statusCounts['open'] ?? 0,
                'in_progress' => $statusCounts['in_progress'] ?? 0,
                'closed' => $statusCounts['closed'] ?? 0,
            ],

            'priority' => [
                'low' => $priorityCounts['low'] ?? 0,
                'medium' => $priorityCounts['medium'] ?? 0,
                'high' => $priorityCounts['high'] ?? 0,
                'critical' => $priorityCounts['critical'] ?? 0,
            ],

            'categories' => $categoryCounts->map(function ($item) {
                return [
                    'name' => $item->category,
                    'count' => $item->total,
                ];
            })->values(),
        ];

        // ===== Admin-only =====
        if ($user->isAdmin()) {
            $response['admin'] = [
                'total_users' => User::where('role', 'user')->count(),
                'total_helpdesks' => User::where('role', 'helpdesk')->count(),
                'unassigned' => Ticket::whereNull('assigned_to_id')
                    ->where('status', 'open')
                    ->count(),
            ];
        }

        // ===== Helpdesk-only =====
        if ($user->isHelpdesk() && !$user->isAdmin()) {
            $response['helpdesk'] = [
                'assigned' => Ticket::where('assigned_to_id', $user->id)
                    ->where('status', 'in_progress')
                    ->count(),

                'completed' => Ticket::where('assigned_to_id', $user->id)
                    ->where('status', 'closed')
                    ->count(),
            ];
        }

        return response()->json($response);
    }
    private function recentActivities(User $user)
    {
        $query = TicketHistory::with([
            'ticket:id,title',
            'user:id,name',
        ])
        ->latest('created_at')
        ->limit(5);

        if ($user->isUser()) {
            $query->whereHas('ticket', function ($q) use ($user) {
                $q->where('reported_by_id', $user->id);
            });
        }

        if ($user->isHelpdesk() && !$user->isAdmin()) {
            $query->whereHas('ticket', function ($q) use ($user) {
                $q->where('assigned_to_id', $user->id);
            });
        }

        return $query->get()->map(function ($history) {
            return [
                'id' => $history->id,
                'ticket_title' => $history->ticket?->title,
                'event_type' => $history->event_type,
                'description' => $history->description,
                'performed_by' => $history->user?->name,
                'created_at' => $history->created_at,
            ];
        });
    }
}

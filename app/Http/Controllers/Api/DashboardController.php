<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // ── GET /api/dashboard/stats ──────────────────────────────────────────────
    public function stats(Request $request)
    {
        $user = $request->user();

        if ($user->isUser()) {
            return $this->userStats($user);
        }

        return $this->staffStats($user);
    }

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
    private function userStats(User $user): \Illuminate\Http\JsonResponse
    {
        $counts = Ticket::where('reported_by_id', $user->id)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($counts);

        return response()->json([
            'total_tickets'       => $total,
            'open_tickets'        => $counts['open']        ?? 0,
            'in_progress_tickets' => $counts['in_progress'] ?? 0,
            'resolved_tickets'    => $counts['resolved']    ?? 0,
            'closed_tickets'      => $counts['closed']      ?? 0,
            'my_tickets'          => $total,
        ]);
    }

    // ── Private: stats for helpdesk / admin ──────────────────────────────────
    private function staffStats(User $user): \Illuminate\Http\JsonResponse
    {
        // Overall ticket counts
        $counts = Ticket::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Tickets assigned specifically to this helpdesk agent
        $myAssigned = Ticket::where('assigned_to_id', $user->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        // Extra admin-only stats
        $extra = [];
        if ($user->isAdmin()) {
            $extra['total_users']     = User::where('role', 'user')->count();
            $extra['total_helpdesks'] = User::where('role', 'helpdesk')->count();
            $extra['unassigned']      = Ticket::whereNull('assigned_to_id')
                ->whereIn('status', ['open', 'in_progress'])
                ->count();
        }

        return response()->json(array_merge([
            'total_tickets'       => array_sum($counts),
            'open_tickets'        => $counts['open']        ?? 0,
            'in_progress_tickets' => $counts['in_progress'] ?? 0,
            'resolved_tickets'    => $counts['resolved']    ?? 0,
            'closed_tickets'      => $counts['closed']      ?? 0,
            'my_tickets'          => $myAssigned,
        ], $extra));
    }
}

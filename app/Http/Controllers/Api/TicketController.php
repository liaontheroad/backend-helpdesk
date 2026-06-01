<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    // ── GET /api/tickets ──────────────────────────────────────────────────────
    // Users    → only their own tickets
    // Helpdesk → all tickets, filterable
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = Ticket::with(['reporter:id,name,email', 'attachments']);

        // Role-based visibility
        if ($user->isUser()) {
            $query->where('reported_by_id', $user->id);
        }

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('assigned_to_id') && $user->isHelpdesk()) {
            $query->where('assigned_to_id', $request->assigned_to_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $tickets = $query->orderBy('created_at', 'desc')
                         ->paginate($request->get('per_page', 20));

        return response()->json($tickets);
    }

    // ── POST /api/tickets ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:500',
            'description' => 'required|string',
            'category'    => 'required|string|max:100',
            'priority'    => 'required|in:low,medium,high,critical',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ticket = Ticket::create([
            'title'          => $request->title,
            'description'    => $request->description,
            'category'       => $request->category,
            'priority'       => $request->priority,
            'status'         => 'open',
            'reported_by_id' => $request->user()->id,
        ]);

        // Handle file uploads
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path     = $file->store("tickets/{$ticket->id}", 'public');
                $fileName = $file->getClientOriginalName();

                TicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'file_url'  => Storage::url($path),
                    'file_name' => $fileName,
                ]);
            }
        }

        $ticket->load('attachments');

        return response()->json([
            'message' => 'Tiket berhasil dibuat.',
            'ticket'  => $this->formatTicket($ticket),
        ], 201);
    }

    // ── GET /api/tickets/{id} ─────────────────────────────────────────────────
    public function show(Request $request, string $id)
    {
        $ticket = Ticket::with([
            'reporter:id,name,email,avatar_url',
            'assignee:id,name,email,avatar_url',
            'attachments',
        ])->findOrFail($id);

        // Users can only view their own tickets
        if ($request->user()->isUser() && $ticket->reported_by_id !== $request->user()->id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        return response()->json([
            'ticket' => $this->formatTicket($ticket),
        ]);
    }

    // ── PATCH /api/tickets/{id}/status ────────────────────────────────────────
    public function updateStatus(Request $request, string $id)
    {
        if ($request->user()->isUser()) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ticket = Ticket::findOrFail($id);

        $oldStatus = $ticket->status;
        $ticket->update(['status' => $request->status]);

        // Notify the ticket reporter if status changed
        if ($oldStatus !== $request->status) {
            TicketNotification::create([
                'user_id'           => $ticket->reported_by_id,
                'title'             => 'Status tiket diperbarui',
                'body'              => "Tiket \"{$ticket->title}\" sekarang berstatus {$request->status}.",
                'type'              => 'ticket_update',
                'related_ticket_id' => $ticket->id,
            ]);
        }

        return response()->json([
            'message' => 'Status tiket berhasil diperbarui.',
            'ticket'  => $this->formatTicket($ticket),
        ]);
    }

    // ── PATCH /api/tickets/{id}/assign ────────────────────────────────────────
    public function assign(Request $request, string $id)
    {
        if (! $request->user()->isHelpdesk()) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'assigned_to_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ticket   = Ticket::findOrFail($id);
        $assignee = User::findOrFail($request->assigned_to_id);

        if (! $assignee->isHelpdesk()) {
            return response()->json([
                'message' => 'Tiket hanya bisa di-assign ke helpdesk atau admin.',
            ], 422);
        }

        $ticket->update([
            'assigned_to_id'   => $assignee->id,
            'assigned_to_name' => $assignee->name,
        ]);

        // Notify the assigned helpdesk
        TicketNotification::create([
            'user_id'           => $assignee->id,
            'title'             => 'Tiket baru ditugaskan ke kamu',
            'body'              => "Tiket \"{$ticket->title}\" telah ditugaskan ke kamu.",
            'type'              => 'ticket_assigned',
            'related_ticket_id' => $ticket->id,
        ]);

        return response()->json([
            'message' => 'Tiket berhasil di-assign.',
            'ticket'  => $this->formatTicket($ticket),
        ]);
    }

    // ── GET /api/tickets/{id}/history ─────────────────────────────────────────
    // Returns the full ticket with all comments (for tracking/history view)
    public function history(Request $request, string $id)
    {
        $ticket = Ticket::with([
            'reporter:id,name,email,avatar_url,role',
            'assignee:id,name,email,avatar_url,role',
            'attachments',
            'comments.attachments',
        ])->findOrFail($id);

        if ($request->user()->isUser() && $ticket->reported_by_id !== $request->user()->id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        return response()->json([
            'ticket'   => $this->formatTicket($ticket),
            'comments' => $ticket->comments,
            'timeline' => $this->buildTimeline($ticket),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────
    private function formatTicket(Ticket $ticket): array
    {
        return [
            'id'               => $ticket->id,
            'title'            => $ticket->title,
            'description'      => $ticket->description,
            'status'           => $ticket->status,
            'priority'         => $ticket->priority,
            'category'         => $ticket->category,
            'reported_by_id'   => $ticket->reported_by_id,
            'assigned_to_id'   => $ticket->assigned_to_id,
            'assigned_to_name' => $ticket->assigned_to_name,
            'comment_count'    => $ticket->comment_count,
            'attachment_urls'  => $ticket->attachments?->pluck('file_url') ?? [],
            'reporter'         => $ticket->reporter,
            'created_at'       => $ticket->created_at,
            'updated_at'       => $ticket->updated_at,
        ];
    }

    private function buildTimeline(Ticket $ticket): array
    {
        $events = [];

        $events[] = [
            'type'       => 'created',
            'label'      => 'Tiket dibuat',
            'actor'      => $ticket->reporter?->name ?? 'Unknown',
            'created_at' => $ticket->created_at,
        ];

        if ($ticket->assigned_to_name) {
            $events[] = [
                'type'       => 'assigned',
                'label'      => "Di-assign ke {$ticket->assigned_to_name}",
                'actor'      => $ticket->assigned_to_name,
                'created_at' => $ticket->updated_at,
            ];
        }

        foreach ($ticket->comments as $comment) {
            $events[] = [
                'type'       => 'comment',
                'label'      => "{$comment->author_name} menambahkan komentar",
                'actor'      => $comment->author_name,
                'created_at' => $comment->created_at,
            ];
        }

        usort($events, fn($a, $b) => $a['created_at'] <=> $b['created_at']);

        return $events;
    }
}

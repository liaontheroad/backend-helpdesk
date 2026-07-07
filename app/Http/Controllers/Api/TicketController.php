<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketNotification;
use App\Models\User;
use App\Models\TicketHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class TicketController extends Controller
{
    // ── GET /api/tickets ──────────────────────────────────────────────────────
#[OA\Get(
    path: "/api/tickets",
    summary: "Menampilkan daftar tiket",
    description: "Mengambil daftar tiket helpdesk milik user atau seluruh tiket untuk admin/helpdesk.",
    tags: ["Tickets"],
    security: [["bearerAuth" => []]],
    responses: [
        new OA\Response(response: 200, description: "Daftar tiket berhasil diambil"),
        new OA\Response(response: 401, description: "Unauthenticated")
    ]
)]
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = Ticket::with(['reporter:id,name,email', 'attachments']);

        // Role-based visibility
        // ==========================

        // Regular user
        if ($user->isUser()) {

            $query->where('reported_by_id', $user->id);

        }
        // Helpdesk
        elseif ($user->isHelpdesk() && !$user->isAdmin()) {

            $query->where('assigned_to_id', $user->id);

        }
        // Admin
        // sees all tickets

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
    #[OA\Post(
        path: "/api/tickets",
        summary: "Membuat tiket baru",
        description: "User membuat tiket helpdesk baru.",
        tags: ["Tickets"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["title", "description"],
                properties: [
                    new OA\Property(property: "title", type: "string", example: "Laptop tidak bisa menyala"),
                    new OA\Property(property: "description", type: "string", example: "Laptop mati total setelah update Windows."),
                    new OA\Property(property: "category", type: "string", example: "Hardware"),
                    new OA\Property(property: "priority", type: "string", example: "High")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Tiket berhasil dibuat"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 422, description: "Validasi gagal")
        ]
    )]
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

        TicketHistory::create([
            'ticket_id'    => $ticket->id,
            'status'       => 'open',
            'description'  => 'Ticket created.',
            'performed_by' => $request->user()->id,
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
    #[OA\Get(
        path: "/api/tickets/{id}",
        summary: "Menampilkan detail tiket",
        description: "Mengambil detail tiket berdasarkan ID.",
        tags: ["Tickets"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID tiket",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Detail tiket berhasil diambil"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Tiket tidak ditemukan")
        ]
    )]
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
    
    #[OA\Patch(
        path: "/api/tickets/{id}/finish",
        summary: "Finish ticket",
        description: "Helpdesk marks a ticket as completed. Ticket status automatically becomes closed.",
        tags: ["Tickets"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Ticket ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Ticket closed successfully"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Ticket not found"),
        ]
    )]

    // ── PATCH /api/tickets/{id}/finish ────────────────────────────────────────
    public function finish(Request $request, string $id)
    {
        if ($request->user()->isUser()) {
            return response()->json([
                'message' => 'Akses ditolak.'
            ], 403);
        }

        $ticket = Ticket::findOrFail($id);

        if ($ticket->status === 'closed') {
            return response()->json([
                'message' => 'Tiket sudah selesai.'
            ], 422);
        }

        $ticket->update([
            'status' => 'closed',
        ]);

        TicketHistory::create([
            'ticket_id'    => $ticket->id,
            'status'       => 'closed',
            'description'  => 'Ticket has been completed.',
            'performed_by' => $request->user()->id,
        ]);

        TicketNotification::create([
            'user_id'           => $ticket->reported_by_id,
            'title'             => 'Tiket selesai',
            'body'              => "Tiket \"{$ticket->title}\" telah selesai dikerjakan.",
            'type'              => 'ticket_closed',
            'related_ticket_id' => $ticket->id,
        ]);

        return response()->json([
            'message' => 'Tiket berhasil diselesaikan.',
            'ticket'  => $this->formatTicket($ticket->fresh()),
        ]);
    }
    #[OA\Patch(
        path: "/api/tickets/{id}/assign",
        summary: "Assign ticket to helpdesk",
        description: "Admin assigns a ticket to a helpdesk. Once assigned, the ticket status automatically changes to in_progress.",
        tags: ["Tickets"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Ticket ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["assigned_to_id"],
                properties: [
                    new OA\Property(
                        property: "assigned_to_id",
                        type: "string",
                        example: "8d3d2a75-0e4b-4f5d-b321-123456789abc"
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Ticket assigned successfully"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]

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
            'status' => 'in_progress',
        ]);

        TicketHistory::create([
            'ticket_id'    => $ticket->id,
            'status'       => 'in_progress',
            'description'  => "Assigned to {$assignee->name}. Ticket is now in progress.",
            'performed_by' => $request->user()->id,
        ]);

        // Notify the assigned helpdesk
        TicketNotification::create([
            'user_id'           => $assignee->id,
            'title'             => 'Tiket baru ditugaskan ke kamu',
            'body'              => "Tiket \"{$ticket->title}\" telah ditugaskan ke kamu dan siap untuk dikerjakan.",
            'type'              => 'ticket_assigned',
            'related_ticket_id' => $ticket->id,
        ]);

        return response()->json([
            'message' => 'Tiket berhasil di-assign.',
            'ticket'  => $this->formatTicket($ticket),
        ]);
    }


    // ── GET /api/tickets/{id}/history ─────────────────────────────────────────
    #[OA\Get(
        path: "/api/tickets/{id}/history",
        summary: "Menampilkan riwayat tiket",
        description: "Mengambil riwayat perubahan status atau aktivitas pada tiket.",
        tags: ["Tickets"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID tiket",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Riwayat tiket berhasil diambil"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Tiket tidak ditemukan")
        ]
    )]
    public function history(Request $request, string $id)
    {
        $ticket = Ticket::with([
            'reporter:id,name,email,avatar_url,role',
            'assignee:id,name,email,avatar_url,role',
            'attachments',
            'comments.attachments',
        ])->findOrFail($id);

        // Users can only view their own tickets
        if ($request->user()->isUser() &&
            $ticket->reported_by_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Akses ditolak.'
            ], 403);
        }

        $timeline = TicketHistory::with('user')
            ->where('ticket_id', $ticket->id)
            ->orderBy('created_at')
            ->get()
            ->map(function ($history) {
                return [
                    'id'           => $history->id,
                    'status'       => $history->status,
                    'description'  => $history->description,
                    'performed_by' => $history->user?->name,
                    'created_at'   => $history->created_at,
                ];
            });

        return response()->json([
            'ticket'   => $this->formatTicket($ticket),
            'comments' => $ticket->comments,
            'timeline' => $timeline,
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
   public function destroy(Request $request, string $id)
    {
        $user = $request->user();

        // Only admin can delete
        if (! $user->isAdmin()) {
            return response()->json([
                'message' => 'Only admin can delete tickets.'
            ], 403);
        }

        $ticket = Ticket::findOrFail($id);

        // Delete related data first
        $ticket->comments()->delete();
        $ticket->attachments()->delete();
        $ticket->notifications()->delete();
        $ticket->histories()->delete();

        // Delete ticket
        $ticket->delete();

        return response()->json([
            'message' => 'Ticket deleted successfully.'
        ]);
    } 
}

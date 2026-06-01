<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\CommentAttachment;
use App\Models\Ticket;
use App\Models\TicketNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    // ── GET /api/tickets/{ticketId}/comments ──────────────────────────────────
    public function index(Request $request, string $ticketId)
    {
        $ticket = Ticket::findOrFail($ticketId);

        // Users can only see comments on their own tickets
        if ($request->user()->isUser() && $ticket->reported_by_id !== $request->user()->id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $comments = Comment::with('attachments')
            ->where('ticket_id', $ticketId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'comments' => $comments,
        ]);
    }

    // ── POST /api/tickets/{ticketId}/comments ─────────────────────────────────
    public function store(Request $request, string $ticketId)
    {
        $ticket = Ticket::findOrFail($ticketId);

        // Users can only comment on their own tickets
        if ($request->user()->isUser() && $ticket->reported_by_id !== $request->user()->id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        // Prevent commenting on closed tickets
        if ($ticket->status === 'closed') {
            return response()->json([
                'message' => 'Tiket sudah ditutup, tidak bisa menambah komentar.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'content'       => 'required|string|max:5000',
            'attachments'   => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user    = $request->user();
        $comment = Comment::create([
            'ticket_id'         => $ticketId,
            'author_id'         => $user->id,
            'author_name'       => $user->name,
            'author_avatar_url' => $user->avatar_url,
            'author_role'       => $user->role,
            'content'           => $request->content,
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store("comments/{$comment->id}", 'public');
                CommentAttachment::create([
                    'comment_id' => $comment->id,
                    'file_url'   => Storage::url($path),
                    'file_name'  => $file->getClientOriginalName(),
                ]);
            }
        }

        $comment->load('attachments');

        // Notify the ticket reporter if a helpdesk/admin replied
        if ($user->isHelpdesk() && $ticket->reported_by_id !== $user->id) {
            TicketNotification::create([
                'user_id'           => $ticket->reported_by_id,
                'title'             => 'Ada balasan baru di tiket kamu',
                'body'              => "{$user->name} membalas tiket \"{$ticket->title}\".",
                'type'              => 'comment_added',
                'related_ticket_id' => $ticket->id,
            ]);
        }

        // Notify the assigned helpdesk if a user replied
        if ($user->isUser() && $ticket->assigned_to_id && $ticket->assigned_to_id !== $user->id) {
            TicketNotification::create([
                'user_id'           => $ticket->assigned_to_id,
                'title'             => 'Pengguna membalas tiket',
                'body'              => "{$user->name} membalas tiket \"{$ticket->title}\".",
                'type'              => 'comment_added',
                'related_ticket_id' => $ticket->id,
            ]);
        }

        return response()->json([
            'message' => 'Komentar berhasil ditambahkan.',
            'comment' => $comment,
        ], 201);
    }

    // ── DELETE /api/comments/{id} ─────────────────────────────────────────────
    // Only admin can delete comments
    public function destroy(Request $request, string $id)
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $comment = Comment::findOrFail($id);

        // Delete attached files from storage
        foreach ($comment->attachments as $attachment) {
            $relativePath = str_replace('/storage/', '', $attachment->file_url);
            Storage::disk('public')->delete($relativePath);
            $attachment->delete();
        }

        $comment->delete();

        return response()->json([
            'message' => 'Komentar berhasil dihapus.',
        ]);
    }
}

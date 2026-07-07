<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: "/api/users",
        summary: "Get all users",
        tags: ["Users"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of users"
            ),
            new OA\Response(
                response: 403,
                description: "Unauthorized"
            )
        ]
    )]
    public function index(Request $request)
    {
        if (! $request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Akses ditolak.',
            ], 403);
        }

        $users = User::select(
                'id',
                'name',
                'email',
                'role',
                'avatar_url',
                'created_at'
            )
            ->orderByRaw("
                CASE role
                    WHEN 'admin' THEN 1
                    WHEN 'helpdesk' THEN 2
                    WHEN 'user' THEN 3
                END
            ")
            ->orderBy('name')
            ->get();

        return response()->json([
            'users' => $users,
        ]);
    }
}
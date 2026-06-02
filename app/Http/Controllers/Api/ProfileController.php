<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    #[OA\Get(
        path: "/api/profile",
        summary: "Menampilkan profil user",
        description: "Mengambil data profil user yang sedang login.",
        tags: ["Profile"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Profil berhasil diambil"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    // ── GET /api/profile ──────────────────────────────────────────────────────
    public function show(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }
    #[OA\Put(
        path: "/api/profile",
        summary: "Update profil user",
        description: "Memperbarui data profil user yang sedang login.",
        tags: ["Profile"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Annisa Putri"),
                    new OA\Property(property: "email", type: "string", example: "annisa@example.com")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Profil berhasil diperbarui"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 422, description: "Validasi gagal")
        ]
    )]
    // ── PUT /api/profile ──────────────────────────────────────────────────────
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name'   => 'sometimes|required|string|max:255',
            'email'  => 'sometimes|required|email|unique:users,email,' . $user->id,
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['name', 'email']);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar_url) {
                $oldPath = str_replace('/storage/', '', $user->avatar_url);
                Storage::disk('public')->delete($oldPath);
            }

            $path            = $request->file('avatar')->store("avatars/{$user->id}", 'public');
            $data['avatar_url'] = Storage::url($path);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user'    => $user->fresh(),
        ]);
    }
    #[OA\Put(
        path: "/api/profile/change-password",
        summary: "Mengubah password",
        description: "Mengubah password user yang sedang login.",
        tags: ["Profile"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["current_password", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "current_password", type: "string", example: "oldpassword123"),
                    new OA\Property(property: "password", type: "string", example: "newpassword123"),
                    new OA\Property(property: "password_confirmation", type: "string", example: "newpassword123")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Password berhasil diubah"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 422, description: "Validasi gagal")
        ]
    )]
    // ── PUT /api/profile/change-password ─────────────────────────────────────
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password'         => ['required', 'confirmed', PasswordRule::min(6)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Password saat ini tidak sesuai.',
            ], 401);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Revoke all tokens so other devices get logged out
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password berhasil diubah. Silakan login kembali.',
        ]);
    }
    #[OA\Get(
        path: "/api/helpdesks",
        summary: "Menampilkan daftar helpdesk",
        description: "Mengambil daftar user dengan role helpdesk untuk kebutuhan assignment tiket.",
        tags: ["Profile"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Daftar helpdesk berhasil diambil"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    // ── GET /api/helpdesks ────────────────────────────────────────────────────
    public function helpdesks(Request $request)
    {
        if (! $request->user()->isHelpdesk()) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $helpdesks = User::whereIn('role', ['helpdesk', 'admin'])
            ->select('id', 'name', 'email', 'avatar_url', 'role')
            ->orderBy('name')
            ->get();

        return response()->json([
            'helpdesks' => $helpdesks,
        ]);
    }
}

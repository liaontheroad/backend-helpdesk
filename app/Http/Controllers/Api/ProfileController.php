<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ProfileController extends Controller
{
    // ── GET /api/profile ──────────────────────────────────────────────────────
    public function show(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

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

    // ── GET /api/helpdesks ────────────────────────────────────────────────────
    // Used by admin when assigning tickets — returns list of helpdesk agents
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

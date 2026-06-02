<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
    path: "/api/auth/register",
    summary: "Register user",
    description: "Mendaftarkan user baru ke aplikasi E-Ticketing Helpdesk.",
    tags: ["Authentication"],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["name", "email", "password", "password_confirmation"],
            properties: [
                new OA\Property(property: "name", type: "string", example: "Annisa Putri"),
                new OA\Property(property: "email", type: "string", example: "annisa@example.com"),
                new OA\Property(property: "password", type: "string", example: "password123"),
                new OA\Property(property: "password_confirmation", type: "string", example: "password123")
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: "Registrasi berhasil"),
        new OA\Response(response: 422, description: "Validasi gagal")
    ]
)]
    // ── POST /api/auth/register ───────────────────────────────────────────────
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', PasswordRule::min(6)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'user',
        ]);

        $token = $user->createToken('mobile_app')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil.',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ], 201);
    }
#[OA\Post(
    path: "/api/auth/login",
    summary: "Login user",
    description: "Login user dan mengembalikan token autentikasi.",
    tags: ["Authentication"],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["email", "password"],
            properties: [
                new OA\Property(
                    property: "email",
                    type: "string",
                    example: "user@example.com"
                ),
                new OA\Property(
                    property: "password",
                    type: "string",
                    example: "password123"
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: "Login berhasil"
        ),
        new OA\Response(
            response: 401,
            description: "Email atau password salah"
        ),
        new OA\Response(
            response: 422,
            description: "Validasi gagal"
        )
    ]
)]
    // ── POST /api/auth/login ──────────────────────────────────────────────────
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        // Revoke all old tokens, keep only 1 active per device
        $user->tokens()->delete();
        $token = $user->createToken('mobile_app')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    #[OA\Post(
        path: "/api/auth/logout",
        summary: "Logout user",
        description: "Menghapus token autentikasi user yang sedang login.",
        tags: ["Authentication"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Logout berhasil"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    // ── POST /api/auth/logout ─────────────────────────────────────────────────
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
    #[OA\Get(
        path: "/api/auth/me",
        summary: "Menampilkan data user login",
        description: "Mengambil data user berdasarkan token yang sedang digunakan.",
        tags: ["Authentication"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Data user berhasil diambil"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    // ── GET /api/auth/me ──────────────────────────────────────────────────────
    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }
    #[OA\Post(
        path: "/api/auth/forgot-password",
        summary: "Mengirim link reset password",
        description: "Mengirim link reset password ke email user.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email"],
                properties: [
                    new OA\Property(property: "email", type: "string", example: "user@example.com")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Link reset password berhasil dikirim"),
            new OA\Response(response: 422, description: "Email tidak ditemukan"),
            new OA\Response(response: 500, description: "Gagal mengirim link reset password")
        ]
    )]
    // ── POST /api/auth/forgot-password ───────────────────────────────────────
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Email tidak ditemukan.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Link reset password sudah dikirim ke email kamu.',
            ]);
        }

        return response()->json([
            'message' => 'Gagal mengirim link reset password. Coba lagi.',
        ], 500);
    }
    #[OA\Post(
        path: "/api/auth/reset-password",
        summary: "Reset password user",
        description: "Mengubah password user menggunakan token reset password.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["token", "email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "token", type: "string", example: "reset-token-example"),
                    new OA\Property(property: "email", type: "string", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", example: "newpassword123"),
                    new OA\Property(property: "password_confirmation", type: "string", example: "newpassword123")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Password berhasil direset"),
            new OA\Response(response: 400, description: "Token tidak valid atau kadaluarsa"),
            new OA\Response(response: 422, description: "Validasi gagal")
        ]
    )]
    // ── POST /api/auth/reset-password ────────────────────────────────────────
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(6)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete(); // Force re-login after reset
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password berhasil direset. Silakan login kembali.',
            ]);
        }

        return response()->json([
            'message' => 'Token tidak valid atau sudah kadaluarsa.',
        ], 400);
    }

    // ── Private helper ────────────────────────────────────────────────────────
    private function formatUser(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'avatar_url' => $user->avatar_url,
            'created_at' => $user->created_at,
        ];
    }
}

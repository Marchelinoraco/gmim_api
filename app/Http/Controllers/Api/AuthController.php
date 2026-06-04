<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChurchUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'gerejaId' => ['required', 'string', 'exists:gereja,id'],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = ChurchUser::query()
            ->with('gereja')
            ->where('gereja_id', $validated['gerejaId'])
            ->where('username', $validated['username'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['success' => false, 'message' => 'Username atau password salah.'], 401);
        }

        // Hapus token lama agar tidak menumpuk (satu session aktif per user)
        $user->tokens()->delete();

        $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logout berhasil.']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('gereja');

        return response()->json([
            'success' => true,
            'data' => $this->formatUser($user),
        ]);
    }

    private function formatUser(ChurchUser $user): array
    {
        return [
            'id' => $user->id,
            'nama' => $user->nama,
            'role' => $user->role,
            'gerejaId' => $user->gereja_id,
            'gerejaNama' => $user->gereja?->nama,
            'username' => $user->username,
        ];
    }
}

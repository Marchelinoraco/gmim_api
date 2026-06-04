<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChurchUser;
use App\Models\Gereja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChurchUserController extends Controller
{
    private const ROLES = ['Bendahara', 'Pelayan Khusus'];

    public function index(string $gereja)
    {
        $this->ensureGereja($gereja);

        return response()->json([
            'success' => true,
            'data' => ChurchUser::query()
                ->where('gereja_id', $gereja)
                ->orderBy('nama')
                ->get()
                ->map(fn ($user) => $this->mapUser($user)),
        ]);
    }

    public function store(Request $request, string $gereja)
    {
        $this->ensureGereja($gereja);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(self::ROLES)],
            'username' => ['required', 'string', 'max:100', Rule::unique('church_users', 'username')],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = ChurchUser::query()->create([
            'id' => 'user-'.Str::ulid(),
            'gereja_id' => $gereja,
            'nama' => trim($validated['nama']),
            'role' => $validated['role'],
            'username' => trim($validated['username']),
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['success' => true, 'data' => $this->mapUser($user)], 201);
    }

    public function update(Request $request, string $gereja, string $id)
    {
        $user = $this->findUser($gereja, $id);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(self::ROLES)],
            'username' => [
                'required',
                'string',
                'max:100',
                Rule::unique('church_users', 'username')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $payload = [
            'nama' => trim($validated['nama']),
            'role' => $validated['role'],
            'username' => trim($validated['username']),
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);

        return response()->json(['success' => true, 'data' => $this->mapUser($user->refresh())]);
    }

    public function destroy(string $gereja, string $id)
    {
        $user = $this->findUser($gereja, $id);
        $user->delete();

        return response()->json(['success' => true, 'message' => 'Pengguna berhasil dihapus.']);
    }

    private function ensureGereja(string $gereja): void
    {
        abort_if(! Gereja::query()->whereKey($gereja)->exists(), 404, 'Gereja tidak ditemukan.');
    }

    private function findUser(string $gereja, string $id): ChurchUser
    {
        $this->ensureGereja($gereja);
        $user = ChurchUser::query()->where('gereja_id', $gereja)->whereKey($id)->first();

        abort_if(! $user, 404, 'Pengguna tidak ditemukan.');

        return $user;
    }

    private function mapUser(ChurchUser $user): array
    {
        return [
            'id' => $user->id,
            'gerejaId' => $user->gereja_id,
            'nama' => $user->nama,
            'role' => $user->role,
            'username' => $user->username,
            'createdAt' => optional($user->created_at)->format('d/m/Y H:i:s'),
            'updatedAt' => optional($user->updated_at)->format('d/m/Y H:i:s'),
        ];
    }
}

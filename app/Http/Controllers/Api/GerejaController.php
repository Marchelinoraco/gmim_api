<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gereja;

class GerejaController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Gereja::query()->orderBy('nama')->get()->map(fn ($gereja) => $this->mapGereja($gereja)),
        ]);
    }

    public function show(string $gereja)
    {
        $item = Gereja::query()->find($gereja);

        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Gereja tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->mapGereja($item)]);
    }

    private function mapGereja(Gereja $gereja): array
    {
        return [
            'id' => $gereja->id,
            'nama' => $gereja->nama,
            'alamat' => $gereja->alamat,
            'createdAt' => optional($gereja->created_at)->format('d/m/Y H:i:s'),
            'updatedAt' => optional($gereja->updated_at)->format('d/m/Y H:i:s'),
        ];
    }
}

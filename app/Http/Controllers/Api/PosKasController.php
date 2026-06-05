<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PosKas;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PosKasController extends Controller
{
    public function index(string $gereja)
    {
        $pos = PosKas::where('gereja_id', $gereja)
            ->orderBy('urutan')
            ->get()
            ->map(fn (PosKas $p) => $this->map($p));

        return response()->json([
            'success'    => true,
            'data'       => $pos,
            'saldoTotal' => $pos->sum('saldo'),
        ]);
    }

    public function store(Request $request, string $gereja)
    {
        $validated = $request->validate([
            'nama'          => ['required', 'string', 'max:100'],
            'tipe'          => ['required', 'in:tunai,bank,midtrans'],
            'namaBank'      => ['nullable', 'string', 'max:100'],
            'nomorRekening' => ['nullable', 'string', 'max:50'],
            'saldoAwal'     => ['nullable', 'integer', 'min:0'],
        ]);

        $pos = PosKas::create([
            'id'             => 'pos-' . Str::ulid(),
            'gereja_id'      => $gereja,
            'nama'           => $validated['nama'],
            'tipe'           => $validated['tipe'],
            'nama_bank'      => $validated['namaBank'] ?? null,
            'nomor_rekening' => $validated['nomorRekening'] ?? null,
            'saldo_awal'     => $validated['saldoAwal'] ?? 0,
            'is_aktif'       => true,
            'urutan'         => (int) PosKas::where('gereja_id', $gereja)->max('urutan') + 1,
        ]);

        return response()->json(['success' => true, 'data' => $this->map($pos)], 201);
    }

    public function update(Request $request, string $gereja, string $id)
    {
        $pos = PosKas::where('gereja_id', $gereja)->findOrFail($id);

        $validated = $request->validate([
            'nama'          => ['sometimes', 'string', 'max:100'],
            'namaBank'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'nomorRekening' => ['sometimes', 'nullable', 'string', 'max:50'],
            'saldoAwal'     => ['sometimes', 'integer', 'min:0'],
            'isAktif'       => ['sometimes', 'boolean'],
        ]);

        $pos->update(array_filter([
            'nama'           => $validated['nama'] ?? null,
            'nama_bank'      => $validated['namaBank'] ?? null,
            'nomor_rekening' => $validated['nomorRekening'] ?? null,
            'saldo_awal'     => $validated['saldoAwal'] ?? null,
            'is_aktif'       => $validated['isAktif'] ?? null,
        ], fn ($v) => $v !== null));

        return response()->json(['success' => true, 'data' => $this->map($pos->refresh())]);
    }

    private function map(PosKas $p): array
    {
        return [
            'id'            => $p->id,
            'nama'          => $p->nama,
            'tipe'          => $p->tipe,
            'namaBank'      => $p->nama_bank,
            'nomorRekening' => $p->nomor_rekening,
            'saldoAwal'     => $p->saldo_awal,
            'saldo'         => $p->saldo(),
            'isAktif'       => $p->is_aktif,
            'urutan'        => $p->urutan,
        ];
    }
}

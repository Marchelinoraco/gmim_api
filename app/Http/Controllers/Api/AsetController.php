<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aset;
use App\Models\Gereja;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AsetController extends Controller
{
    public function index(string $gereja)
    {
        $this->ensureGereja($gereja);

        return $this->ok(
            Aset::where('gereja_id', $gereja)
                ->orderBy('kategori')
                ->orderBy('nama')
                ->get()
                ->map(fn ($a) => $this->map($a))
        );
    }

    public function store(Request $request, string $gereja)
    {
        $this->ensureGereja($gereja);
        $validated = $request->validate($this->rules());

        $aset = Aset::create([
            'id'               => 'aset-'.Str::ulid(),
            'gereja_id'        => $gereja,
            'kode'             => $validated['kode'] ?? null,
            'nama'             => trim($validated['nama']),
            'kategori'         => $validated['kategori'],
            'tanggal_perolehan'=> $validated['tanggalPerolehan'] ?? null,
            'nilai_perolehan'  => $validated['nilaiPerolehan'] ?? 0,
            'lokasi'           => $validated['lokasi'] ?? null,
            'kondisi'          => $validated['kondisi'] ?? 'baik',
            'keterangan'       => $validated['keterangan'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $this->map($aset)], 201);
    }

    public function show(string $gereja, string $id)
    {
        return $this->ok($this->map($this->find($gereja, $id)));
    }

    public function update(Request $request, string $gereja, string $id)
    {
        $aset      = $this->find($gereja, $id);
        $validated = $request->validate($this->rules(false));

        $aset->update([
            'kode'             => $validated['kode'] ?? $aset->kode,
            'nama'             => isset($validated['nama']) ? trim($validated['nama']) : $aset->nama,
            'kategori'         => $validated['kategori'] ?? $aset->kategori,
            'tanggal_perolehan'=> $validated['tanggalPerolehan'] ?? $aset->tanggal_perolehan,
            'nilai_perolehan'  => $validated['nilaiPerolehan'] ?? $aset->nilai_perolehan,
            'lokasi'           => $validated['lokasi'] ?? $aset->lokasi,
            'kondisi'          => $validated['kondisi'] ?? $aset->kondisi,
            'keterangan'       => $validated['keterangan'] ?? $aset->keterangan,
        ]);

        return $this->ok($this->map($aset->refresh()));
    }

    public function destroy(string $gereja, string $id)
    {
        $this->find($gereja, $id)->delete();

        return $this->ok(null, 'Aset berhasil dihapus.');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function rules(bool $required = true): array
    {
        $r = $required ? 'required' : 'sometimes|required';

        return [
            'kode'            => ['nullable', 'string', 'max:50'],
            'nama'            => [$r, 'string', 'max:255'],
            'kategori'        => [$r, 'string', 'in:tanah,bangunan,kendaraan,alat_musik,elektronik,inventaris,lainnya'],
            'tanggalPerolehan'=> ['nullable', 'date'],
            'nilaiPerolehan'  => ['nullable', 'integer', 'min:0'],
            'lokasi'          => ['nullable', 'string', 'max:255'],
            'kondisi'         => ['nullable', 'string', 'in:baik,rusak_ringan,rusak_berat,dihapus'],
            'keterangan'      => ['nullable', 'string'],
        ];
    }

    private function ensureGereja(string $gereja): void
    {
        abort_if(! Gereja::query()->whereKey($gereja)->exists(), 404, 'Gereja tidak ditemukan.');
    }

    private function find(string $gereja, string $id): Aset
    {
        $this->ensureGereja($gereja);
        $item = Aset::where('gereja_id', $gereja)->whereKey($id)->first();
        abort_if(! $item, 404, 'Aset tidak ditemukan.');

        return $item;
    }

    private function map(Aset $a): array
    {
        return [
            'id'               => $a->id,
            'kode'             => $a->kode,
            'nama'             => $a->nama,
            'kategori'         => $a->kategori,
            'tanggalPerolehan' => $a->tanggal_perolehan?->format('Y-m-d'),
            'nilaiPerolehan'   => $a->nilai_perolehan,
            'lokasi'           => $a->lokasi,
            'kondisi'          => $a->kondisi,
            'keterangan'       => $a->keterangan,
            'createdAt'        => $a->created_at?->format('Y-m-d H:i:s'),
            'updatedAt'        => $a->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function ok(mixed $data, ?string $message = null)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }
}

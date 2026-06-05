<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gereja;
use App\Models\KategoriPengeluaran;
use App\Models\Pegawai;
use App\Models\PembayaranGaji;
use App\Models\Pengeluaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GajiController extends Controller
{
    // -----------------------------------------------------------------------
    // PEGAWAI
    // -----------------------------------------------------------------------

    public function pegawaiIndex(string $gereja)
    {
        $this->ensureGereja($gereja);

        return $this->ok(
            Pegawai::where('gereja_id', $gereja)
                ->orderBy('nama')
                ->get()
                ->map(fn ($p) => $this->mapPegawai($p))
        );
    }

    public function pegawaiStore(Request $request, string $gereja)
    {
        $this->ensureGereja($gereja);
        $validated = $request->validate([
            'nama'           => ['required', 'string', 'max:255'],
            'jabatan'        => ['nullable', 'string', 'max:255'],
            'tipe'           => ['required', 'in:gaji_tetap,honor'],
            'nominalDefault' => ['nullable', 'integer', 'min:0'],
            'noRekening'     => ['nullable', 'string', 'max:50'],
            'bank'           => ['nullable', 'string', 'max:100'],
            'keterangan'     => ['nullable', 'string'],
        ]);

        $pegawai = Pegawai::create([
            'id'              => 'pgw-'.Str::ulid(),
            'gereja_id'       => $gereja,
            'nama'            => trim($validated['nama']),
            'jabatan'         => $validated['jabatan'] ?? null,
            'tipe'            => $validated['tipe'],
            'nominal_default' => $validated['nominalDefault'] ?? 0,
            'no_rekening'     => $validated['noRekening'] ?? null,
            'bank'            => $validated['bank'] ?? null,
            'keterangan'      => $validated['keterangan'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $this->mapPegawai($pegawai)], 201);
    }

    public function pegawaiUpdate(Request $request, string $gereja, string $id)
    {
        $pegawai   = $this->findPegawai($gereja, $id);
        $validated = $request->validate([
            'nama'           => ['sometimes', 'required', 'string', 'max:255'],
            'jabatan'        => ['nullable', 'string', 'max:255'],
            'tipe'           => ['sometimes', 'required', 'in:gaji_tetap,honor'],
            'nominalDefault' => ['nullable', 'integer', 'min:0'],
            'noRekening'     => ['nullable', 'string', 'max:50'],
            'bank'           => ['nullable', 'string', 'max:100'],
            'status'         => ['nullable', 'in:active,nonaktif'],
            'keterangan'     => ['nullable', 'string'],
        ]);

        $pegawai->update([
            'nama'            => isset($validated['nama']) ? trim($validated['nama']) : $pegawai->nama,
            'jabatan'         => array_key_exists('jabatan', $validated) ? $validated['jabatan'] : $pegawai->jabatan,
            'tipe'            => $validated['tipe'] ?? $pegawai->tipe,
            'nominal_default' => $validated['nominalDefault'] ?? $pegawai->nominal_default,
            'no_rekening'     => array_key_exists('noRekening', $validated) ? $validated['noRekening'] : $pegawai->no_rekening,
            'bank'            => array_key_exists('bank', $validated) ? $validated['bank'] : $pegawai->bank,
            'status'          => $validated['status'] ?? $pegawai->status,
            'keterangan'      => array_key_exists('keterangan', $validated) ? $validated['keterangan'] : $pegawai->keterangan,
        ]);

        return $this->ok($this->mapPegawai($pegawai->refresh()));
    }

    public function pegawaiDestroy(string $gereja, string $id)
    {
        $pegawai = $this->findPegawai($gereja, $id);

        $hasPembayaran = PembayaranGaji::where('gereja_id', $gereja)
            ->where('pegawai_id', $id)->exists();

        if ($hasPembayaran) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai memiliki riwayat pembayaran dan tidak dapat dihapus. Nonaktifkan saja.',
            ], 422);
        }

        $pegawai->delete();

        return $this->ok(null, 'Pegawai berhasil dihapus.');
    }

    // -----------------------------------------------------------------------
    // PEMBAYARAN GAJI
    // -----------------------------------------------------------------------

    public function pembayaranIndex(Request $request, string $gereja)
    {
        $this->ensureGereja($gereja);
        $periode = $request->query('periode', now()->format('Y-m'));

        return $this->ok(
            PembayaranGaji::where('gereja_id', $gereja)
                ->where('periode', $periode)
                ->with('pegawai')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($p) => $this->mapPembayaran($p))
        );
    }

    public function pembayaranStore(Request $request, string $gereja)
    {
        $this->ensureGereja($gereja);
        $validated = $request->validate([
            'pegawaiId'  => ['required', 'string'],
            'periode'    => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'nominal'    => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $this->findPegawai($gereja, $validated['pegawaiId']);

        // Cegah duplikat pembayaran per pegawai per periode
        $exists = PembayaranGaji::where('gereja_id', $gereja)
            ->where('pegawai_id', $validated['pegawaiId'])
            ->where('periode', $validated['periode'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran untuk pegawai ini pada periode tersebut sudah ada.',
            ], 422);
        }

        $pembayaran = PembayaranGaji::create([
            'id'         => 'gaji-'.Str::ulid(),
            'gereja_id'  => $gereja,
            'pegawai_id' => $validated['pegawaiId'],
            'periode'    => $validated['periode'],
            'nominal'    => $validated['nominal'],
            'status'     => 'pending',
            'input_by'   => $request->user()?->id,
            'keterangan' => $validated['keterangan'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->mapPembayaran($pembayaran->load('pegawai')),
        ], 201);
    }

    public function pembayaranDestroy(string $gereja, string $id)
    {
        $pembayaran = $this->findPembayaran($gereja, $id);

        if ($pembayaran->status === 'dibayar') {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran yang sudah dibayar tidak dapat dihapus.',
            ], 422);
        }

        $pembayaran->delete();

        return $this->ok(null, 'Data pembayaran berhasil dihapus.');
    }

    /**
     * Tandai dibayar → otomatis buat pengeluaran di kategori "Gaji & Honor".
     */
    public function tandaiBayar(Request $request, string $gereja, string $id)
    {
        $pembayaran = $this->findPembayaran($gereja, $id);

        if ($pembayaran->status === 'dibayar') {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran ini sudah ditandai dibayar.',
            ], 422);
        }

        $validated = $request->validate([
            'tanggalBayar' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($pembayaran, $validated, $gereja, $request) {
            // Cari/buat kategori "Gaji & Honor" untuk gereja ini
            $kategori = KategoriPengeluaran::firstOrCreate(
                ['gereja_id' => $gereja, 'nama' => 'Gaji & Honor'],
                ['id' => 'kat-gaji-'.Str::ulid()]
            );

            // Buat pengeluaran otomatis
            $pengeluaran = Pengeluaran::create([
                'id'                      => 'kel-'.Str::ulid(),
                'gereja_id'               => $gereja,
                'tanggal'                 => $validated['tanggalBayar'],
                'kategori_pengeluaran_id' => $kategori->id,
                'jumlah'                  => $pembayaran->nominal,
                'keterangan'              => 'Gaji/Honor: '.$pembayaran->pegawai->nama.' ('.$pembayaran->periode.')',
            ]);

            // Tandai pembayaran sebagai dibayar + simpan FK ke pengeluaran
            $pembayaran->update([
                'status'        => 'dibayar',
                'tanggal_bayar' => $validated['tanggalBayar'],
                'pengeluaran_id'=> $pengeluaran->id,
            ]);
        });

        return $this->ok($this->mapPembayaran($pembayaran->refresh()->load('pegawai')));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function ensureGereja(string $gereja): void
    {
        abort_if(! Gereja::query()->whereKey($gereja)->exists(), 404, 'Gereja tidak ditemukan.');
    }

    private function findPegawai(string $gereja, string $id): Pegawai
    {
        $this->ensureGereja($gereja);
        $item = Pegawai::where('gereja_id', $gereja)->whereKey($id)->first();
        abort_if(! $item, 404, 'Pegawai tidak ditemukan.');

        return $item;
    }

    private function findPembayaran(string $gereja, string $id): PembayaranGaji
    {
        $this->ensureGereja($gereja);
        $item = PembayaranGaji::where('gereja_id', $gereja)->whereKey($id)->first();
        abort_if(! $item, 404, 'Data pembayaran tidak ditemukan.');

        return $item;
    }

    private function mapPegawai(Pegawai $p): array
    {
        return [
            'id'             => $p->id,
            'nama'           => $p->nama,
            'jabatan'        => $p->jabatan,
            'tipe'           => $p->tipe,
            'nominalDefault' => $p->nominal_default,
            'noRekening'     => $p->no_rekening,
            'bank'           => $p->bank,
            'status'         => $p->status,
            'keterangan'     => $p->keterangan,
        ];
    }

    private function mapPembayaran(PembayaranGaji $p): array
    {
        return [
            'id'            => $p->id,
            'pegawaiId'     => $p->pegawai_id,
            'pegawaiNama'   => $p->pegawai?->nama,
            'pegawaiJabatan'=> $p->pegawai?->jabatan,
            'periode'       => $p->periode,
            'tanggalBayar'  => $p->tanggal_bayar?->format('Y-m-d'),
            'nominal'       => $p->nominal,
            'status'        => $p->status,
            'pengeluaranId' => $p->pengeluaran_id,
            'keterangan'    => $p->keterangan,
        ];
    }

    private function ok(mixed $data, ?string $message = null)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }
}

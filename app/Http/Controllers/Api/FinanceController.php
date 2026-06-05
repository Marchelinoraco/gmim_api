<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gereja;
use App\Models\KategoriPengeluaran;
use App\Models\KategoriPersembahan;
use App\Models\NamaPersembahan;
use App\Models\Pemasukan;
use App\Models\Pengeluaran;
use App\Models\PosKas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FinanceController extends Controller
{
    // -----------------------------------------------------------------------
    // Dashboard
    // -----------------------------------------------------------------------

    public function dashboard(string $gereja)
    {
        $this->ensureGereja($gereja);

        // F-1 cash basis — hanya kas yang benar-benar diterima/dikeluarkan
        $pemasukan   = Pemasukan::where('gereja_id', $gereja)->cash()->get();
        $pengeluaran = Pengeluaran::where('gereja_id', $gereja)->cash()->get();
        $startOfMonth = now()->startOfMonth()->toDateString();

        $recent = $pemasukan
            ->map(fn ($item) => $this->mapPemasukan($item) + ['jenis' => 'Pemasukan'])
            ->concat($pengeluaran->map(fn ($item) => $this->mapPengeluaran($item) + ['jenis' => 'Pengeluaran']))
            ->sortByDesc(fn ($item) => $item['tanggal'].' '.$item['createdAt'])
            ->values()
            ->take(5);

        $pendingCount = Pemasukan::where('gereja_id', $gereja)->pending()->count();

        // Saldo = Σ saldo semua pos (termasuk saldo_awal & mutasi) — sumber kebenaran tunggal
        $posList    = PosKas::where('gereja_id', $gereja)->get();
        $saldoTotal = $posList->sum(fn (PosKas $p) => $p->saldo());
        $saldoPerPos = $posList->sortBy('urutan')->map(fn (PosKas $p) => [
            'id' => $p->id, 'nama' => $p->nama, 'tipe' => $p->tipe, 'saldo' => $p->saldo(),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'totalPemasukan'           => $pemasukan->sum('jumlah'),
                'totalPengeluaran'         => $pengeluaran->sum('jumlah'),
                'saldo'                    => $saldoTotal,
                'totalPemasukanBulanIni'   => $pemasukan->where('tanggal', '>=', $startOfMonth)->sum('jumlah'),
                'totalPengeluaranBulanIni' => $pengeluaran->where('tanggal', '>=', $startOfMonth)->sum('jumlah'),
                'saldoPerPos'              => $saldoPerPos,
                'transaksiTerbaru'         => $recent,
                'pendingApprovalCount'     => $pendingCount,
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    // Dashboard — Grafik
    // -----------------------------------------------------------------------

    public function dashboardGrafik(Request $request, string $gereja)
    {
        $this->ensureGereja($gereja);
        $range = $request->query('range', 'weekly'); // weekly | monthly

        return $range === 'monthly'
            ? $this->grafik12Bulan($gereja)
            : $this->grafik8Minggu($gereja);
    }

    private function grafik8Minggu(string $gereja)
    {
        $buckets = [];
        for ($i = 7; $i >= 0; $i--) {
            $start = now()->startOfWeek()->subWeeks($i);
            $end   = now()->endOfWeek()->subWeeks($i);
            $buckets[] = [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
                'label' => $start->format('d M'),
            ];
        }

        return $this->buildGrafikResponse($gereja, $buckets);
    }

    private function grafik12Bulan(string $gereja)
    {
        $buckets = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->startOfMonth()->subMonths($i);
            $buckets[] = [
                'start' => $date->toDateString(),
                'end'   => $date->copy()->endOfMonth()->toDateString(),
                'label' => $date->isoFormat('MMM YYYY'),
            ];
        }

        return $this->buildGrafikResponse($gereja, $buckets);
    }

    private function buildGrafikResponse(string $gereja, array $buckets)
    {
        // Saldo sebelum periode pertama (kas awal) — cash basis + saldo awal pos
        $periodStart    = $buckets[0]['start'];
        $saldoAwalPos   = (int) PosKas::where('gereja_id', $gereja)->sum('saldo_awal');
        $saldoSebelumnya = $saldoAwalPos
            + Pemasukan::where('gereja_id', $gereja)->cash()
            ->where('tanggal', '<', $periodStart)->sum('jumlah')
            - Pengeluaran::where('gereja_id', $gereja)->cash()
            ->where('tanggal', '<', $periodStart)->sum('jumlah');

        $labels            = [];
        $pemasukanSeries   = [];
        $pengeluaranSeries = [];
        $saldoSeries       = [];
        $running           = $saldoSebelumnya;

        foreach ($buckets as $b) {
            $pem = Pemasukan::where('gereja_id', $gereja)->cash()
                ->whereBetween('tanggal', [$b['start'], $b['end']])->sum('jumlah');
            $pen = Pengeluaran::where('gereja_id', $gereja)->cash()
                ->whereBetween('tanggal', [$b['start'], $b['end']])->sum('jumlah');

            $running += $pem - $pen;

            $labels[]            = $b['label'];
            $pemasukanSeries[]   = $pem;
            $pengeluaranSeries[] = $pen;
            $saldoSeries[]       = $running;
        }

        return $this->ok(compact('labels', 'pemasukanSeries', 'pengeluaranSeries', 'saldoSeries'));
    }

    // -----------------------------------------------------------------------
    // Kategori Persembahan
    // -----------------------------------------------------------------------

    public function kategoriPersembahanIndex(string $gereja)
    {
        $this->ensureGereja($gereja);

        return $this->ok(
            KategoriPersembahan::where('gereja_id', $gereja)->orderBy('nama')->get()
                ->map(fn ($item) => $this->mapKategoriPersembahan($item))
        );
    }

    public function kategoriPersembahanStore(Request $request, string $gereja)
    {
        $this->ensureGereja($gereja);
        $validated = $request->validate(['nama' => ['required', 'string', 'max:255']]);

        if ($this->nameExists(KategoriPersembahan::class, $gereja, $validated['nama'])) {
            return $this->fail('Nama kategori sudah digunakan.', 422);
        }

        $item = KategoriPersembahan::create([
            'id'       => 'kat-pers-'.Str::ulid(),
            'gereja_id' => $gereja,
            'nama'     => trim($validated['nama']),
        ]);

        return $this->created($this->mapKategoriPersembahan($item));
    }

    public function kategoriPersembahanUpdate(Request $request, string $gereja, string $id)
    {
        $item = $this->findForGereja(KategoriPersembahan::class, $gereja, $id, 'Kategori tidak ditemukan.');
        $validated = $request->validate(['nama' => ['required', 'string', 'max:255']]);

        if ($this->nameExists(KategoriPersembahan::class, $gereja, $validated['nama'], $id)) {
            return $this->fail('Nama kategori sudah digunakan.', 422);
        }

        $item->update(['nama' => trim($validated['nama'])]);

        return $this->ok($this->mapKategoriPersembahan($item->refresh()));
    }

    public function kategoriPersembahanDestroy(string $gereja, string $id)
    {
        $this->findForGereja(KategoriPersembahan::class, $gereja, $id, 'Kategori tidak ditemukan.')->delete();

        return $this->ok(null, 'Kategori berhasil dihapus.');
    }

    // -----------------------------------------------------------------------
    // Nama Persembahan
    // -----------------------------------------------------------------------

    public function namaPersembahanIndex(string $gereja)
    {
        $this->ensureGereja($gereja);

        return $this->ok(
            NamaPersembahan::where('gereja_id', $gereja)->orderBy('nama')->get()
                ->map(fn ($item) => $this->mapNamaPersembahan($item))
        );
    }

    public function namaPersembahanByKategori(string $gereja, string $id)
    {
        $this->findForGereja(KategoriPersembahan::class, $gereja, $id, 'Kategori tidak ditemukan.');

        return $this->ok(
            NamaPersembahan::where('gereja_id', $gereja)->where('kategori_persembahan_id', $id)
                ->orderBy('nama')->get()->map(fn ($item) => $this->mapNamaPersembahan($item))
        );
    }

    public function namaPersembahanStore(Request $request, string $gereja)
    {
        $this->ensureGereja($gereja);
        $validated = $request->validate([
            'kategoriPersembahanId' => ['required', 'string'],
            'nama'                  => ['required', 'string', 'max:255'],
        ]);
        $this->findForGereja(KategoriPersembahan::class, $gereja, $validated['kategoriPersembahanId'], 'Kategori tidak ditemukan.');

        $item = NamaPersembahan::create([
            'id'                      => 'nama-pers-'.Str::ulid(),
            'gereja_id'               => $gereja,
            'kategori_persembahan_id' => $validated['kategoriPersembahanId'],
            'nama'                    => trim($validated['nama']),
        ]);

        return $this->created($this->mapNamaPersembahan($item));
    }

    public function namaPersembahanUpdate(Request $request, string $gereja, string $id)
    {
        $item = $this->findForGereja(NamaPersembahan::class, $gereja, $id, 'Nama persembahan tidak ditemukan.');
        $validated = $request->validate([
            'kategoriPersembahanId' => ['sometimes', 'required', 'string'],
            'nama'                  => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        if (isset($validated['kategoriPersembahanId'])) {
            $this->findForGereja(KategoriPersembahan::class, $gereja, $validated['kategoriPersembahanId'], 'Kategori tidak ditemukan.');
        }

        $item->update([
            'kategori_persembahan_id' => $validated['kategoriPersembahanId'] ?? $item->kategori_persembahan_id,
            'nama'                    => isset($validated['nama']) ? trim($validated['nama']) : $item->nama,
        ]);

        return $this->ok($this->mapNamaPersembahan($item->refresh()));
    }

    public function namaPersembahanDestroy(string $gereja, string $id)
    {
        $this->findForGereja(NamaPersembahan::class, $gereja, $id, 'Nama persembahan tidak ditemukan.')->delete();

        return $this->ok(null, 'Nama persembahan berhasil dihapus.');
    }

    // -----------------------------------------------------------------------
    // Pemasukan — daftar & detail
    // -----------------------------------------------------------------------

    public function pemasukanIndex(Request $request, string $gereja)
    {
        $query = Pemasukan::where('gereja_id', $gereja)
            ->whereNull('reverses')   // sembunyikan entry pembalik (audit-only)
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at');

        // Filter opsional: ?status=pending|approved|rejected|all
        $status = $request->query('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Filter opsional: ?sumber=manual|midtrans
        if ($request->filled('sumber')) {
            $query->where('sumber', $request->query('sumber'));
        }

        $this->applyDateFilters($query, $request);

        return $this->ok($query->get()->map(fn ($item) => $this->mapPemasukan($item)));
    }

    public function pemasukanShow(string $gereja, string $id)
    {
        return $this->ok($this->mapPemasukan(
            $this->findForGereja(Pemasukan::class, $gereja, $id, 'Data pemasukan tidak ditemukan.')
        ));
    }

    // -----------------------------------------------------------------------
    // Pemasukan — input manual (Bendahara atau Pelayan Khusus)
    // -----------------------------------------------------------------------

    public function pemasukanStoreManual(Request $request, string $gereja)
    {
        $this->ensureGereja($gereja);

        $validated = $request->validate([
            ...$this->pemasukanRules(),
            'bukti' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $this->assertPemasukanRelations($gereja, $validated);

        $user     = $request->user();
        $isBendahara = $user->role === 'Bendahara';

        // Bendahara input → langsung approved; Pelayan Khusus → pending
        $status = $isBendahara ? 'approved' : 'pending';

        // F-1 cash basis (default sudah diterima). KP-1: pos hanya saat sudah diterima.
        $statusKas = $validated['statusKas'] ?? 'sudah_diterima';
        $posKasId  = null;
        $tanggalDiterima = null;
        if ($statusKas === 'sudah_diterima') {
            $pos = $this->findForGereja(PosKas::class, $gereja, $validated['posKasId'], 'Pos kas tidak ditemukan.');
            $posKasId = $pos->id;
            $tanggalDiterima = $validated['tanggal'];
        }

        $buktiPath = null;
        if ($request->hasFile('bukti')) {
            $buktiPath = $request->file('bukti')->store(
                "bukti-pemasukan/{$gereja}",
                'public'
            );
        }

        $item = Pemasukan::create([
            'id'                      => 'pem-'.Str::ulid(),
            'gereja_id'               => $gereja,
            'pos_kas_id'              => $posKasId,
            'sumber'                  => 'manual',
            'status'                  => $status,
            'status_kas'              => $statusKas,
            'tanggal_diterima'        => $tanggalDiterima,
            'tanggal'                 => $validated['tanggal'],
            'kategori_persembahan_id' => $validated['kategoriPersembahanId'],
            'nama_persembahan_id'     => $validated['namaPersembahanId'],
            'jumlah'                  => $validated['jumlah'],
            'keterangan'              => $validated['keterangan'] ?? '',
            'bukti_gambar'            => $buktiPath,
            'input_by'                => $user->id,
            'approved_by'             => $isBendahara ? $user->id : null,
            'approved_at'             => $isBendahara ? now() : null,
        ]);

        return $this->created($this->mapPemasukan($item));
    }

    // Endpoint lama — tetap ada untuk kompatibilitas, arahkan ke manual
    public function pemasukanStore(Request $request, string $gereja)
    {
        return $this->pemasukanStoreManual($request, $gereja);
    }

    public function pemasukanUpdate(Request $request, string $gereja, string $id)
    {
        $item = $this->findForGereja(Pemasukan::class, $gereja, $id, 'Data pemasukan tidak ditemukan.');
        $validated = $request->validate($this->pemasukanRules());
        $this->assertPemasukanRelations($gereja, $validated);

        $item->update([
            'tanggal'                 => $validated['tanggal'],
            'kategori_persembahan_id' => $validated['kategoriPersembahanId'],
            'nama_persembahan_id'     => $validated['namaPersembahanId'],
            'jumlah'                  => $validated['jumlah'],
            'keterangan'              => $validated['keterangan'] ?? '',
        ]);

        return $this->ok($this->mapPemasukan($item->refresh()));
    }

    public function pemasukanDestroy(string $gereja, string $id)
    {
        $this->findForGereja(Pemasukan::class, $gereja, $id, 'Data pemasukan tidak ditemukan.')->delete();

        return $this->ok(null, 'Data pemasukan berhasil dihapus.');
    }

    // -----------------------------------------------------------------------
    // Pemasukan — Approval (hanya Bendahara, dijaga oleh middleware)
    // -----------------------------------------------------------------------

    public function pemasukanApprove(string $gereja, string $id)
    {
        $item = $this->findForGereja(Pemasukan::class, $gereja, $id, 'Data pemasukan tidak ditemukan.');

        if ($item->status !== 'pending') {
            return $this->fail('Hanya pemasukan berstatus pending yang dapat disetujui.', 422);
        }

        $item->update([
            'status'      => 'approved',
            'approved_by' => request()->user()->id,
            'approved_at' => now(),
        ]);

        return $this->ok($this->mapPemasukan($item->refresh()), 'Pemasukan berhasil disetujui.');
    }

    public function pemasukanReject(Request $request, string $gereja, string $id)
    {
        $item = $this->findForGereja(Pemasukan::class, $gereja, $id, 'Data pemasukan tidak ditemukan.');

        if ($item->status !== 'pending') {
            return $this->fail('Hanya pemasukan berstatus pending yang dapat ditolak.', 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $item->update([
            'status'          => 'rejected',
            'rejected_reason' => $validated['reason'],
        ]);

        return $this->ok($this->mapPemasukan($item->refresh()), 'Pemasukan ditolak.');
    }

    // -----------------------------------------------------------------------
    // Kategori Pengeluaran
    // -----------------------------------------------------------------------

    public function kategoriPengeluaranIndex(string $gereja)
    {
        $this->ensureGereja($gereja);

        return $this->ok(
            KategoriPengeluaran::where('gereja_id', $gereja)->orderBy('nama')->get()
                ->map(fn ($item) => $this->mapKategoriPengeluaran($item))
        );
    }

    public function kategoriPengeluaranStore(Request $request, string $gereja)
    {
        $this->ensureGereja($gereja);
        $validated = $request->validate(['nama' => ['required', 'string', 'max:255']]);

        if ($this->nameExists(KategoriPengeluaran::class, $gereja, $validated['nama'])) {
            return $this->fail('Nama kategori sudah digunakan.', 422);
        }

        $item = KategoriPengeluaran::create([
            'id'       => 'kat-keluar-'.Str::ulid(),
            'gereja_id' => $gereja,
            'nama'     => trim($validated['nama']),
        ]);

        return $this->created($this->mapKategoriPengeluaran($item));
    }

    public function kategoriPengeluaranUpdate(Request $request, string $gereja, string $id)
    {
        $item = $this->findForGereja(KategoriPengeluaran::class, $gereja, $id, 'Kategori tidak ditemukan.');
        $validated = $request->validate(['nama' => ['required', 'string', 'max:255']]);

        if ($this->nameExists(KategoriPengeluaran::class, $gereja, $validated['nama'], $id)) {
            return $this->fail('Nama kategori sudah digunakan.', 422);
        }

        $item->update(['nama' => trim($validated['nama'])]);

        return $this->ok($this->mapKategoriPengeluaran($item->refresh()));
    }

    public function kategoriPengeluaranDestroy(string $gereja, string $id)
    {
        $this->findForGereja(KategoriPengeluaran::class, $gereja, $id, 'Kategori tidak ditemukan.')->delete();

        return $this->ok(null, 'Kategori berhasil dihapus.');
    }

    // -----------------------------------------------------------------------
    // Pengeluaran
    // -----------------------------------------------------------------------

    public function pengeluaranIndex(Request $request, string $gereja)
    {
        $query = Pengeluaran::where('gereja_id', $gereja)
            ->whereNull('reverses')   // sembunyikan entry pembalik (audit-only)
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at');
        $this->applyDateFilters($query, $request);

        return $this->ok($query->get()->map(fn ($item) => $this->mapPengeluaran($item)));
    }

    public function pengeluaranShow(string $gereja, string $id)
    {
        return $this->ok($this->mapPengeluaran(
            $this->findForGereja(Pengeluaran::class, $gereja, $id, 'Data pengeluaran tidak ditemukan.')
        ));
    }

    public function pengeluaranStore(Request $request, string $gereja)
    {
        $this->ensureGereja($gereja);
        $validated = $request->validate($this->pengeluaranRules());
        $this->findForGereja(KategoriPengeluaran::class, $gereja, $validated['kategoriPengeluaranId'], 'Kategori tidak ditemukan.');

        $statusKas = $validated['statusKas'] ?? 'sudah_dikeluarkan';
        $posKasId  = null;
        $tanggalKeluar = null;
        if ($statusKas === 'sudah_dikeluarkan') {
            $pos = $this->findForGereja(PosKas::class, $gereja, $validated['posKasId'], 'Pos kas tidak ditemukan.');
            // KP-2: tolak keras jika saldo pos tidak cukup
            abort_if($pos->saldo() < $validated['jumlah'], 422,
                "Saldo pos {$pos->nama} tidak cukup (Rp " . number_format($pos->saldo(), 0, ',', '.') . "). Lakukan mutasi (transfer antar pos) dulu.");
            $posKasId = $pos->id;
            $tanggalKeluar = $validated['tanggal'];
        }

        $item = Pengeluaran::create([
            'id'                    => 'kel-'.Str::ulid(),
            'gereja_id'             => $gereja,
            'pos_kas_id'            => $posKasId,
            'tanggal'               => $validated['tanggal'],
            'kategori_pengeluaran_id' => $validated['kategoriPengeluaranId'],
            'jumlah'                => $validated['jumlah'],
            'keterangan'            => trim($validated['keterangan']),
            'status_kas'            => $statusKas,
            'tanggal_dikeluarkan'   => $tanggalKeluar,
        ]);

        return $this->created($this->mapPengeluaran($item));
    }

    public function pengeluaranUpdate(Request $request, string $gereja, string $id)
    {
        $item = $this->findForGereja(Pengeluaran::class, $gereja, $id, 'Data pengeluaran tidak ditemukan.');
        $validated = $request->validate($this->pengeluaranRules());
        $this->findForGereja(KategoriPengeluaran::class, $gereja, $validated['kategoriPengeluaranId'], 'Kategori tidak ditemukan.');

        $item->update([
            'tanggal'               => $validated['tanggal'],
            'kategori_pengeluaran_id' => $validated['kategoriPengeluaranId'],
            'jumlah'                => $validated['jumlah'],
            'keterangan'            => trim($validated['keterangan']),
        ]);

        return $this->ok($this->mapPengeluaran($item->refresh()));
    }

    public function pengeluaranDestroy(string $gereja, string $id)
    {
        $this->findForGereja(Pengeluaran::class, $gereja, $id, 'Data pengeluaran tidak ditemukan.')->delete();

        return $this->ok(null, 'Data pengeluaran berhasil dihapus.');
    }

    // -----------------------------------------------------------------------
    // Laporan — hanya counted
    // -----------------------------------------------------------------------

    public function laporanMingguan(Request $request, string $gereja)
    {
        $validated = $request->validate([
            'startDate' => ['required', 'date'],
            'endDate'   => ['required', 'date', 'after_or_equal:startDate'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'pemasukan'   => Pemasukan::where('gereja_id', $gereja)->cash()
                    ->whereBetween('tanggal', [$validated['startDate'], $validated['endDate']])
                    ->get()->map(fn ($item) => $this->mapPemasukan($item)),
                'pengeluaran' => Pengeluaran::where('gereja_id', $gereja)->cash()
                    ->whereBetween('tanggal', [$validated['startDate'], $validated['endDate']])
                    ->get()->map(fn ($item) => $this->mapPengeluaran($item)),
            ],
        ]);
    }

    public function laporanBulanan(Request $request, string $gereja)
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year'  => ['required', 'integer', 'between:2000,2100'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'pemasukan'   => Pemasukan::where('gereja_id', $gereja)->cash()
                    ->whereMonth('tanggal', $validated['month'])->whereYear('tanggal', $validated['year'])
                    ->get()->map(fn ($item) => $this->mapPemasukan($item)),
                'pengeluaran' => Pengeluaran::where('gereja_id', $gereja)->cash()
                    ->whereMonth('tanggal', $validated['month'])->whereYear('tanggal', $validated['year'])
                    ->get()->map(fn ($item) => $this->mapPengeluaran($item)),
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function pemasukanRules(): array
    {
        return [
            'tanggal'               => ['required', 'date'],
            'kategoriPersembahanId' => ['required', 'string'],
            'namaPersembahanId'     => ['required', 'string'],
            'jumlah'                => ['required', 'integer', 'min:1'],
            'keterangan'            => ['nullable', 'string'],
            // F-1 cash basis + KP-1: pos wajib hanya jika kas sudah diterima
            'statusKas'             => ['nullable', 'in:sudah_diterima,belum_diterima'],
            'posKasId'              => ['nullable', 'required_if:statusKas,sudah_diterima', 'string'],
        ];
    }

    private function pengeluaranRules(): array
    {
        return [
            'tanggal'               => ['required', 'date'],
            'kategoriPengeluaranId' => ['required', 'string'],
            'jumlah'                => ['required', 'integer', 'min:1'],
            'keterangan'            => ['required', 'string'],
            // F-5: pos wajib jika sudah dikeluarkan (default)
            'statusKas'             => ['nullable', 'in:sudah_dikeluarkan,belum_dikeluarkan'],
            'posKasId'              => ['nullable', 'required_if:statusKas,sudah_dikeluarkan', 'string'],
        ];
    }

    private function assertPemasukanRelations(string $gereja, array $data): void
    {
        $this->findForGereja(KategoriPersembahan::class, $gereja, $data['kategoriPersembahanId'], 'Kategori tidak ditemukan.');
        $nama = $this->findForGereja(NamaPersembahan::class, $gereja, $data['namaPersembahanId'], 'Nama persembahan tidak ditemukan.');
        abort_if($nama->kategori_persembahan_id !== $data['kategoriPersembahanId'], 422, 'Nama persembahan tidak sesuai kategori.');
    }

    private function ensureGereja(string $gereja): void
    {
        abort_if(! Gereja::query()->whereKey($gereja)->exists(), 404, 'Gereja tidak ditemukan.');
    }

    private function findForGereja(string $model, string $gereja, string $id, string $message): Model
    {
        $this->ensureGereja($gereja);
        $item = $model::query()->where('gereja_id', $gereja)->whereKey($id)->first();
        abort_if(! $item, 404, $message);

        return $item;
    }

    private function nameExists(string $model, string $gereja, string $nama, ?string $ignoreId = null): bool
    {
        return $model::query()
            ->where('gereja_id', $gereja)
            ->whereRaw('LOWER(nama) = ?', [Str::lower(trim($nama))])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    private function applyDateFilters($query, Request $request): void
    {
        if ($request->filled('startDate')) {
            $query->whereDate('tanggal', '>=', $request->query('startDate'));
        }
        if ($request->filled('endDate')) {
            $query->whereDate('tanggal', '<=', $request->query('endDate'));
        }
    }

    private function ok(mixed $data, ?string $message = null)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    private function created(mixed $data)
    {
        return response()->json(['success' => true, 'data' => $data], 201);
    }

    private function fail(string $message, int $status)
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    private function mapPemasukan(Pemasukan $item): array
    {
        return $this->mapBase($item) + [
            'gerejaId'               => $item->gereja_id,
            'posKasId'               => $item->pos_kas_id,
            'sumber'                 => $item->sumber,
            'status'                 => $item->status,
            'statusKas'              => $item->status_kas,
            'tanggalDiterima'        => $item->tanggal_diterima?->format('Y-m-d'),
            'tanggal'                => $item->tanggal->format('Y-m-d'),
            'kategoriPersembahanId'  => $item->kategori_persembahan_id,
            'namaPersembahanId'      => $item->nama_persembahan_id,
            'jumlah'                 => $item->jumlah,
            'keterangan'             => $item->keterangan ?? '',
            'buktiGambarUrl'         => $item->bukti_gambar ? Storage::url($item->bukti_gambar) : null,
            'inputBy'                => $item->input_by,
            'approvedBy'             => $item->approved_by,
            'approvedAt'             => $item->approved_at?->format('d/m/Y H:i:s'),
            'rejectedReason'         => $item->rejected_reason,
            'dikoreksi'              => (bool) $item->reversed_by,
        ];
    }

    private function mapKategoriPersembahan(KategoriPersembahan $item): array
    {
        return $this->mapBase($item) + ['gerejaId' => $item->gereja_id, 'nama' => $item->nama];
    }

    private function mapNamaPersembahan(NamaPersembahan $item): array
    {
        return $this->mapBase($item) + [
            'gerejaId'              => $item->gereja_id,
            'kategoriPersembahanId' => $item->kategori_persembahan_id,
            'nama'                  => $item->nama,
        ];
    }

    private function mapKategoriPengeluaran(KategoriPengeluaran $item): array
    {
        return $this->mapBase($item) + ['gerejaId' => $item->gereja_id, 'nama' => $item->nama];
    }

    private function mapPengeluaran(Pengeluaran $item): array
    {
        return $this->mapBase($item) + [
            'gerejaId'              => $item->gereja_id,
            'posKasId'              => $item->pos_kas_id,
            'tanggal'               => $item->tanggal->format('Y-m-d'),
            'kategoriPengeluaranId' => $item->kategori_pengeluaran_id,
            'jumlah'                => $item->jumlah,
            'keterangan'            => $item->keterangan,
            'statusKas'             => $item->status_kas,
            'tanggalDikeluarkan'    => $item->tanggal_dikeluarkan?->format('Y-m-d'),
            'dikoreksi'             => (bool) $item->reversed_by,
        ];
    }

    private function mapBase(Model $item): array
    {
        return [
            'id'        => $item->getKey(),
            'createdAt' => optional($item->created_at)->format('d/m/Y H:i:s'),
            'updatedAt' => optional($item->updated_at)->format('d/m/Y H:i:s'),
        ];
    }
}

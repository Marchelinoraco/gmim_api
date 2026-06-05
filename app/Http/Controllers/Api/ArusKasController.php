<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gereja;
use App\Models\KategoriPengeluaran;
use App\Models\KategoriPersembahan;
use App\Models\Pemasukan;
use App\Models\Pengeluaran;
use App\Models\PeriodeBuku;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArusKasController extends Controller
{
    // -----------------------------------------------------------------------
    // GET /gereja/{g}/arus-kas?tipe=bulanan&periode=2026-06
    // -----------------------------------------------------------------------

    public function index(Request $request, string $gereja)
    {
        abort_if(! Gereja::query()->whereKey($gereja)->exists(), 404, 'Gereja tidak ditemukan.');

        $tipe    = $request->query('tipe', 'bulanan');
        $periode = $request->query('periode', now()->format('Y-m'));

        [$start, $end] = $this->periodeToRange($tipe, $periode);

        // Saldo awal = semua counted sebelum periode ini
        $saldoAwal = Pemasukan::where('gereja_id', $gereja)->counted()
            ->where('tanggal', '<', $start)->sum('jumlah')
            - Pengeluaran::where('gereja_id', $gereja)
            ->where('tanggal', '<', $start)->sum('jumlah');

        $totalPemasukan   = Pemasukan::where('gereja_id', $gereja)->counted()
            ->whereBetween('tanggal', [$start, $end])->sum('jumlah');
        $totalPengeluaran = Pengeluaran::where('gereja_id', $gereja)
            ->whereBetween('tanggal', [$start, $end])->sum('jumlah');

        $periodeBuku = PeriodeBuku::where('gereja_id', $gereja)
            ->where('tipe', $tipe)->where('periode', $periode)->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'periode'            => $periode,
                'tipe'               => $tipe,
                'saldoAwal'          => $saldoAwal,
                'totalPemasukan'     => $totalPemasukan,
                'totalPengeluaran'   => $totalPengeluaran,
                'saldoAkhir'         => $saldoAwal + $totalPemasukan - $totalPengeluaran,
                'mingguan'           => $this->getMingguan($gereja, $start, $end),
                'kategoriPemasukan'  => $this->getKategoriPemasukan($gereja, $start, $end),
                'kategoriPengeluaran'=> $this->getKategoriPengeluaran($gereja, $start, $end),
                'statusPeriode'      => $periodeBuku?->status ?? 'open',
                'periodeBukuId'      => $periodeBuku?->id,
                'closedBy'           => $periodeBuku?->closed_by,
                'closedAt'           => $periodeBuku?->closed_at?->format('d/m/Y H:i'),
                'catatan'            => $periodeBuku?->catatan,
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /gereja/{g}/tutup-buku
    // -----------------------------------------------------------------------

    public function tutupBuku(Request $request, string $gereja)
    {
        abort_if(! Gereja::query()->whereKey($gereja)->exists(), 404, 'Gereja tidak ditemukan.');

        $validated = $request->validate([
            'tipe'    => ['required', 'in:bulanan,tahunan'],
            'periode' => ['required', 'string', 'regex:/^\d{4}(-\d{2})?$/'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $existing = PeriodeBuku::where('gereja_id', $gereja)
            ->where('tipe', $validated['tipe'])
            ->where('periode', $validated['periode'])
            ->first();

        if ($existing && $existing->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'Periode ini sudah ditutup.',
            ], 422);
        }

        [$start, $end] = $this->periodeToRange($validated['tipe'], $validated['periode']);

        $saldoAwal = Pemasukan::where('gereja_id', $gereja)->counted()
            ->where('tanggal', '<', $start)->sum('jumlah')
            - Pengeluaran::where('gereja_id', $gereja)
            ->where('tanggal', '<', $start)->sum('jumlah');

        $totalPem = Pemasukan::where('gereja_id', $gereja)->counted()
            ->whereBetween('tanggal', [$start, $end])->sum('jumlah');
        $totalPen = Pengeluaran::where('gereja_id', $gereja)
            ->whereBetween('tanggal', [$start, $end])->sum('jumlah');

        $pb = PeriodeBuku::updateOrCreate(
            [
                'gereja_id' => $gereja,
                'tipe'      => $validated['tipe'],
                'periode'   => $validated['periode'],
            ],
            [
                'id'                => $existing?->id ?? 'pb-'.Str::ulid(),
                'saldo_awal'        => $saldoAwal,
                'total_pemasukan'   => $totalPem,
                'total_pengeluaran' => $totalPen,
                'saldo_akhir'       => $saldoAwal + $totalPem - $totalPen,
                'status'            => 'closed',
                'closed_by'         => $request->user()?->id,
                'closed_at'         => now(),
                'catatan'           => $validated['catatan'],
            ]
        );

        return response()->json(['success' => true, 'data' => $this->mapPeriodeBuku($pb)]);
    }

    // -----------------------------------------------------------------------
    // POST /gereja/{g}/buka-buku
    // -----------------------------------------------------------------------

    public function bukaBuku(Request $request, string $gereja)
    {
        abort_if(! Gereja::query()->whereKey($gereja)->exists(), 404, 'Gereja tidak ditemukan.');

        $validated = $request->validate([
            'id' => ['required', 'string', 'exists:periode_buku,id'],
        ]);

        $pb = PeriodeBuku::where('gereja_id', $gereja)->where('id', $validated['id'])->firstOrFail();
        $pb->update(['status' => 'open', 'closed_by' => null, 'closed_at' => null]);

        return response()->json(['success' => true, 'data' => $this->mapPeriodeBuku($pb->refresh())]);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function periodeToRange(string $tipe, string $periode): array
    {
        if ($tipe === 'tahunan') {
            $year  = (int) $periode;
            $start = now()->setYear($year)->startOfYear()->toDateString();
            $end   = now()->setYear($year)->endOfYear()->toDateString();
        } else {
            [$year, $month] = explode('-', $periode);
            $date  = now()->setYear((int) $year)->setMonth((int) $month);
            $start = $date->copy()->startOfMonth()->toDateString();
            $end   = $date->copy()->endOfMonth()->toDateString();
        }

        return [$start, $end];
    }

    private function getMingguan(string $gereja, string $start, string $end): array
    {
        $startDate = \Carbon\Carbon::parse($start);
        $endDate   = \Carbon\Carbon::parse($end);
        $result    = [];
        $current   = $startDate->copy()->startOfWeek();

        while ($current->lte($endDate)) {
            $weekStart = $current->toDateString();
            $weekEnd   = $current->copy()->endOfWeek()->min($endDate)->toDateString();

            $pem = Pemasukan::where('gereja_id', $gereja)->counted()
                ->whereBetween('tanggal', [$weekStart, $weekEnd])->sum('jumlah');
            $pen = Pengeluaran::where('gereja_id', $gereja)
                ->whereBetween('tanggal', [$weekStart, $weekEnd])->sum('jumlah');

            $result[] = [
                'label'       => $current->format('d M'),
                'pemasukan'   => $pem,
                'pengeluaran' => $pen,
            ];

            $current->addWeek();
        }

        return $result;
    }

    private function getKategoriPemasukan(string $gereja, string $start, string $end): array
    {
        return KategoriPersembahan::where('gereja_id', $gereja)
            ->withSum([
                'pemasukan as jumlah' => fn ($q) => $q->counted()->whereBetween('tanggal', [$start, $end]),
            ], 'jumlah')
            ->get()
            ->filter(fn ($k) => $k->jumlah > 0)
            ->map(fn ($k) => ['nama' => $k->nama, 'jumlah' => (int) $k->jumlah])
            ->values()
            ->toArray();
    }

    private function getKategoriPengeluaran(string $gereja, string $start, string $end): array
    {
        return KategoriPengeluaran::where('gereja_id', $gereja)
            ->withSum([
                'pengeluaran as jumlah' => fn ($q) => $q->whereBetween('tanggal', [$start, $end]),
            ], 'jumlah')
            ->get()
            ->filter(fn ($k) => $k->jumlah > 0)
            ->map(fn ($k) => ['nama' => $k->nama, 'jumlah' => (int) $k->jumlah])
            ->values()
            ->toArray();
    }

    private function mapPeriodeBuku(PeriodeBuku $pb): array
    {
        return [
            'id'              => $pb->id,
            'tipe'            => $pb->tipe,
            'periode'         => $pb->periode,
            'saldoAwal'       => $pb->saldo_awal,
            'totalPemasukan'  => $pb->total_pemasukan,
            'totalPengeluaran'=> $pb->total_pengeluaran,
            'saldoAkhir'      => $pb->saldo_akhir,
            'status'          => $pb->status,
            'closedBy'        => $pb->closed_by,
            'closedAt'        => $pb->closed_at?->format('d/m/Y H:i'),
            'catatan'         => $pb->catatan,
        ];
    }
}

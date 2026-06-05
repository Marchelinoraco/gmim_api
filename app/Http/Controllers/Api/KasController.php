<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MutasiKas;
use App\Models\Pemasukan;
use App\Models\Pengeluaran;
use App\Models\PosKas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KasController extends Controller
{
    // ── F-4: list transaksi yang belum jadi kas ─────────────────────────────
    public function pending(string $gereja)
    {
        $pemasukan = Pemasukan::where('gereja_id', $gereja)->belumDiterima()
            ->orderBy('tanggal')->get()
            ->map(fn (Pemasukan $p) => [
                'id'        => $p->id,
                'tanggal'   => $p->tanggal->format('Y-m-d'),
                'jumlah'    => $p->jumlah,
                'keterangan'=> $p->keterangan,
                'inputBy'   => $p->input_by,
            ]);

        $pengeluaran = Pengeluaran::where('gereja_id', $gereja)->belumDikeluarkan()
            ->orderBy('tanggal')->get()
            ->map(fn (Pengeluaran $p) => [
                'id'        => $p->id,
                'tanggal'   => $p->tanggal->format('Y-m-d'),
                'jumlah'    => $p->jumlah,
                'keterangan'=> $p->keterangan,
            ]);

        $posList    = PosKas::where('gereja_id', $gereja)->get();
        $saldoNyata = $posList->sum(fn (PosKas $p) => $p->saldo());

        return response()->json([
            'success' => true,
            'data'    => [
                'pemasukan'       => $pemasukan,
                'pengeluaran'     => $pengeluaran,
                'totalBelumMasuk' => $pemasukan->sum('jumlah'),
                'totalBelumKeluar'=> $pengeluaran->sum('jumlah'),
                'saldoNyata'      => $saldoNyata,
            ],
        ]);
    }

    // ── KP-1: pos dipilih saat konfirmasi pemasukan diterima ─────────────────
    public function konfirmasiPemasukan(Request $request, string $gereja, string $id)
    {
        $validated = $request->validate([
            'posKasId'        => ['required', 'string'],
            'tanggalDiterima' => ['required', 'date'],
        ]);

        $item = Pemasukan::where('gereja_id', $gereja)->findOrFail($id);
        abort_if($item->status_kas === 'sudah_diterima', 422, 'Pemasukan ini sudah dikonfirmasi diterima.');

        $pos = PosKas::where('gereja_id', $gereja)->findOrFail($validated['posKasId']);

        $item->update([
            'status_kas'       => 'sudah_diterima',
            'pos_kas_id'       => $pos->id,
            'tanggal_diterima' => $validated['tanggalDiterima'],
        ]);

        AuditLog::record('konfirmasi_kas_masuk', $gereja, 'pemasukan', $id,
            ['pos' => $pos->nama, 'jumlah' => $item->jumlah],
            $request->user()?->id, $request->user()?->email, $request->ip());

        return response()->json(['success' => true, 'message' => "Kas masuk dikonfirmasi ke pos {$pos->nama}."]);
    }

    // ── Konfirmasi pengeluaran benar-benar keluar (KP-2 validasi saldo) ──────
    public function konfirmasiPengeluaran(Request $request, string $gereja, string $id)
    {
        $validated = $request->validate([
            'posKasId'           => ['required', 'string'],
            'tanggalDikeluarkan' => ['required', 'date'],
        ]);

        $item = Pengeluaran::where('gereja_id', $gereja)->findOrFail($id);
        abort_if($item->status_kas === 'sudah_dikeluarkan', 422, 'Pengeluaran ini sudah dikonfirmasi keluar.');

        $pos = PosKas::where('gereja_id', $gereja)->findOrFail($validated['posKasId']);
        abort_if($pos->saldo() < $item->jumlah, 422,
            "Saldo pos {$pos->nama} tidak cukup. Lakukan mutasi dulu.");

        $item->update([
            'status_kas'          => 'sudah_dikeluarkan',
            'pos_kas_id'          => $pos->id,
            'tanggal_dikeluarkan' => $validated['tanggalDikeluarkan'],
        ]);

        AuditLog::record('konfirmasi_kas_keluar', $gereja, 'pengeluaran', $id,
            ['pos' => $pos->nama, 'jumlah' => $item->jumlah],
            $request->user()?->id, $request->user()?->email, $request->ip());

        return response()->json(['success' => true, 'message' => "Kas keluar dikonfirmasi dari pos {$pos->nama}."]);
    }

    // ── Rekap pergerakan satu pos (riwayat masuk/keluar + saldo berjalan) ────
    public function arusKasPos(Request $request, string $gereja)
    {
        $posId = $request->query('pos_id');
        $pos   = PosKas::where('gereja_id', $gereja)->findOrFail($posId);

        $entries = [];

        foreach (Pemasukan::where('gereja_id', $gereja)->where('pos_kas_id', $pos->id)->cash()->get() as $p) {
            $entries[] = ['tanggal' => $p->tanggal_diterima?->format('Y-m-d') ?? $p->tanggal->format('Y-m-d'),
                'keterangan' => 'Pemasukan: ' . ($p->keterangan ?: '-'), 'masuk' => $p->jumlah, 'keluar' => 0];
        }
        foreach (Pengeluaran::where('gereja_id', $gereja)->where('pos_kas_id', $pos->id)->cash()->get() as $p) {
            $entries[] = ['tanggal' => $p->tanggal_dikeluarkan?->format('Y-m-d') ?? $p->tanggal->format('Y-m-d'),
                'keterangan' => 'Pengeluaran: ' . ($p->keterangan ?: '-'), 'masuk' => 0, 'keluar' => $p->jumlah];
        }
        foreach (MutasiKas::with('posTujuan')->where('gereja_id', $gereja)->where('pos_tujuan_id', $pos->id)->get() as $m) {
            $entries[] = ['tanggal' => $m->tanggal->format('Y-m-d'),
                'keterangan' => 'Transfer masuk dari ' . ($m->posAsal?->nama ?? '-'), 'masuk' => $m->jumlah, 'keluar' => 0];
        }
        foreach (MutasiKas::with('posTujuan')->where('gereja_id', $gereja)->where('pos_asal_id', $pos->id)->get() as $m) {
            $entries[] = ['tanggal' => $m->tanggal->format('Y-m-d'),
                'keterangan' => 'Transfer keluar ke ' . ($m->posTujuan?->nama ?? '-'), 'masuk' => 0, 'keluar' => $m->jumlah + $m->biaya_admin];
        }

        usort($entries, fn ($a, $b) => $a['tanggal'] <=> $b['tanggal']);

        // Saldo berjalan dari saldo awal
        $running = $pos->saldo_awal;
        $rows = [['tanggal' => '-', 'keterangan' => 'Saldo awal', 'masuk' => $pos->saldo_awal, 'keluar' => 0, 'saldo' => $running]];
        foreach ($entries as $e) {
            $running += $e['masuk'] - $e['keluar'];
            $rows[] = $e + ['saldo' => $running];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'pos'       => ['id' => $pos->id, 'nama' => $pos->nama, 'tipe' => $pos->tipe, 'saldo' => $pos->saldo()],
                'pergerakan'=> $rows,
            ],
        ]);
    }

    // ── F-6: koreksi pemasukan (entry pembalik) ──────────────────────────────
    public function koreksiPemasukan(Request $request, string $gereja, string $id)
    {
        return $this->koreksi($request, $gereja, Pemasukan::class, $id, 'pemasukan');
    }

    public function koreksiPengeluaran(Request $request, string $gereja, string $id)
    {
        return $this->koreksi($request, $gereja, Pengeluaran::class, $id, 'pengeluaran');
    }

    private function koreksi(Request $request, string $gereja, string $model, string $id, string $tipe)
    {
        $validated = $request->validate(['alasan' => ['required', 'string', 'max:255']]);
        $asli = $model::where('gereja_id', $gereja)->findOrFail($id);
        abort_if($asli->reversed_by, 422, 'Transaksi ini sudah dikoreksi.');

        $pembalik = DB::transaction(function () use ($asli, $gereja, $validated, $model, $tipe) {
            // Entry pembalik = audit-only. Disisihkan dari semua sum (reverses != null),
            // begitu pula asli (reversed_by != null) → netto 0 tanpa nilai negatif.
            $base = [
                'id'             => ($tipe === 'pemasukan' ? 'pem-' : 'kel-') . Str::ulid(),
                'gereja_id'      => $gereja,
                'pos_kas_id'     => $asli->pos_kas_id,
                'tanggal'        => now()->toDateString(),
                'jumlah'         => $asli->jumlah,
                'keterangan'     => 'Koreksi (pembalik): ' . $asli->id,
                'reverses'       => $asli->id,
                'alasan_koreksi' => $validated['alasan'],
            ];

            if ($tipe === 'pemasukan') {
                $base += [
                    'sumber'                  => $asli->sumber,
                    'status'                  => $asli->status,
                    'status_kas'              => $asli->status_kas,
                    'tanggal_diterima'        => $asli->tanggal_diterima,
                    'kategori_persembahan_id' => $asli->kategori_persembahan_id,
                    'nama_persembahan_id'     => $asli->nama_persembahan_id,
                ];
            } else {
                $base += [
                    'kategori_pengeluaran_id' => $asli->kategori_pengeluaran_id,
                    'status_kas'              => $asli->status_kas,
                    'tanggal_dikeluarkan'     => $asli->tanggal_dikeluarkan,
                ];
            }

            $rev = $model::create($base);
            $asli->update(['reversed_by' => $rev->id, 'alasan_koreksi' => $validated['alasan']]);
            return $rev;
        });

        AuditLog::record('koreksi_' . $tipe, $gereja, $tipe, $asli->id,
            ['alasan' => $validated['alasan'], 'pembalik' => $pembalik->id],
            $request->user()?->id, $request->user()?->email, $request->ip());

        return response()->json(['success' => true, 'message' => 'Transaksi berhasil dikoreksi (entry pembalik dibuat).']);
    }
}

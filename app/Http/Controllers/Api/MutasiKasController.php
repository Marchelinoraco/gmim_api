<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MutasiKas;
use App\Models\PosKas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MutasiKasController extends Controller
{
    public function index(string $gereja)
    {
        $list = MutasiKas::with(['posAsal', 'posTujuan'])
            ->where('gereja_id', $gereja)
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MutasiKas $m) => $this->map($m));

        return response()->json(['success' => true, 'data' => $list]);
    }

    public function store(Request $request, string $gereja)
    {
        $validated = $request->validate([
            'tanggal'     => ['required', 'date'],
            'posAsalId'   => ['required', 'string', 'different:posTujuanId'],
            'posTujuanId' => ['required', 'string'],
            'jumlah'      => ['required', 'integer', 'min:1'],
            'biayaAdmin'  => ['nullable', 'integer', 'min:0'],
            'keterangan'  => ['nullable', 'string'],
        ]);

        $posAsal   = PosKas::where('gereja_id', $gereja)->findOrFail($validated['posAsalId']);
        $posTujuan = PosKas::where('gereja_id', $gereja)->findOrFail($validated['posTujuanId']);

        $biaya     = $validated['biayaAdmin'] ?? 0;
        $totalKeluar = $validated['jumlah'] + $biaya;

        // KP-2: tolak keras jika saldo pos asal tidak cukup
        if ($posAsal->saldo() < $totalKeluar) {
            return response()->json([
                'success' => false,
                'message' => "Saldo pos {$posAsal->nama} tidak cukup. Saldo: " . number_format($posAsal->saldo(), 0, ',', '.'),
            ], 422);
        }

        $mutasi = MutasiKas::create([
            'id'            => 'mut-' . Str::ulid(),
            'gereja_id'     => $gereja,
            'tanggal'       => $validated['tanggal'],
            'pos_asal_id'   => $posAsal->id,
            'pos_tujuan_id' => $posTujuan->id,
            'jumlah'        => $validated['jumlah'],
            'biaya_admin'   => $biaya,
            'keterangan'    => $validated['keterangan'] ?? null,
            'dicatat_oleh'  => $request->user()?->id,
        ]);

        AuditLog::record('mutasi_kas', $gereja, 'mutasi_kas', $mutasi->id, [
            'dari' => $posAsal->nama, 'ke' => $posTujuan->nama, 'jumlah' => $validated['jumlah'],
        ], $request->user()?->id, $request->user()?->email, $request->ip());

        return response()->json(['success' => true, 'data' => $this->map($mutasi->load(['posAsal', 'posTujuan']))], 201);
    }

    // F-6 koreksi mutasi: buat entry pembalik
    public function koreksi(Request $request, string $gereja, string $id)
    {
        $validated = $request->validate(['alasan' => ['required', 'string', 'max:255']]);
        $asli = MutasiKas::where('gereja_id', $gereja)->findOrFail($id);

        if ($asli->reversed_by) {
            return response()->json(['success' => false, 'message' => 'Mutasi ini sudah dikoreksi.'], 422);
        }

        $pembalik = DB::transaction(function () use ($asli, $gereja, $validated, $request) {
            // Entry pembalik: tukar asal↔tujuan, biaya admin 0 (biaya tidak balik)
            $rev = MutasiKas::create([
                'id'            => 'mut-' . Str::ulid(),
                'gereja_id'     => $gereja,
                'tanggal'       => now()->toDateString(),
                'pos_asal_id'   => $asli->pos_tujuan_id,
                'pos_tujuan_id' => $asli->pos_asal_id,
                'jumlah'        => $asli->jumlah,
                'biaya_admin'   => 0,
                'keterangan'    => 'Koreksi mutasi ' . $asli->id,
                'dicatat_oleh'  => $request->user()?->id,
                'reverses'      => $asli->id,
                'alasan_koreksi' => $validated['alasan'],
            ]);
            $asli->update(['reversed_by' => $rev->id, 'alasan_koreksi' => $validated['alasan']]);
            return $rev;
        });

        AuditLog::record('koreksi_mutasi', $gereja, 'mutasi_kas', $asli->id,
            ['alasan' => $validated['alasan'], 'pembalik' => $pembalik->id],
            $request->user()?->id, $request->user()?->email, $request->ip());

        return response()->json(['success' => true, 'message' => 'Mutasi berhasil dikoreksi.', 'data' => $this->map($pembalik->load(['posAsal', 'posTujuan']))]);
    }

    private function map(MutasiKas $m): array
    {
        return [
            'id'           => $m->id,
            'tanggal'      => $m->tanggal->format('Y-m-d'),
            'posAsalId'    => $m->pos_asal_id,
            'posAsalNama'  => $m->posAsal?->nama,
            'posTujuanId'  => $m->pos_tujuan_id,
            'posTujuanNama'=> $m->posTujuan?->nama,
            'jumlah'       => $m->jumlah,
            'biayaAdmin'   => $m->biaya_admin,
            'keterangan'   => $m->keterangan,
            'reverses'     => $m->reverses,
            'reversedBy'   => $m->reversed_by,
            'dikoreksi'    => (bool) $m->reversed_by,
        ];
    }
}

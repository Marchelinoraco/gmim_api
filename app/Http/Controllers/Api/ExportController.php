<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    private const ALLOWED_TYPES = ['pemasukan', 'pengeluaran', 'aset', 'gaji', 'semua'];

    public function export(Request $request, string $gereja): Response
    {
        $type = $request->query('type', 'semua');

        if (! in_array($type, self::ALLOWED_TYPES)) {
            return response('Invalid type', 400);
        }

        $data = match ($type) {
            'pemasukan'   => $this->exportPemasukan($gereja),
            'pengeluaran' => $this->exportPengeluaran($gereja),
            'aset'        => $this->exportAset($gereja),
            'gaji'        => $this->exportGaji($gereja),
            'semua'       => $this->exportSemua($gereja),
            default       => [],
        };

        $filename = "export-{$type}-" . date('Y-m-d') . ".csv";

        return response($this->toCsv($data), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function exportPemasukan(string $gerejaId): array
    {
        $rows = DB::table('pemasukan as p')
            ->leftJoin('kategori_persembahan as kp', 'p.kategori_persembahan_id', '=', 'kp.id')
            ->leftJoin('nama_persembahan as np', 'p.nama_persembahan_id', '=', 'np.id')
            ->where('p.gereja_id', $gerejaId)
            ->whereNull('p.deleted_at')
            ->whereIn('p.status', ['approved', 'settled'])
            ->orderBy('p.tanggal')
            ->select('p.tanggal', 'kp.nama as kategori', 'np.nama as nama_persembahan',
                     'p.jumlah', 'p.keterangan', 'p.sumber')
            ->get();

        $data = [['Tanggal', 'Kategori', 'Nama Persembahan', 'Jumlah', 'Keterangan', 'Sumber']];

        foreach ($rows as $r) {
            $data[] = [$r->tanggal, $r->kategori ?? '', $r->nama_persembahan ?? '', $r->jumlah, $r->keterangan ?? '', $r->sumber ?? 'manual'];
        }

        return $data;
    }

    private function exportPengeluaran(string $gerejaId): array
    {
        $rows = DB::table('pengeluaran as p')
            ->leftJoin('kategori_pengeluaran as kp', 'p.kategori_pengeluaran_id', '=', 'kp.id')
            ->where('p.gereja_id', $gerejaId)
            ->orderBy('p.tanggal')
            ->select('p.tanggal', 'kp.nama as kategori', 'p.jumlah', 'p.keterangan')
            ->get();

        $data = [['Tanggal', 'Kategori', 'Jumlah', 'Keterangan']];

        foreach ($rows as $r) {
            $data[] = [$r->tanggal, $r->kategori ?? '', $r->jumlah, $r->keterangan ?? ''];
        }

        return $data;
    }

    private function exportAset(string $gerejaId): array
    {
        $rows = DB::table('aset')
            ->where('gereja_id', $gerejaId)
            ->orderBy('nama')
            ->select('kode', 'nama', 'kategori', 'kondisi', 'tanggal_perolehan', 'nilai_perolehan', 'lokasi')
            ->get();

        $data = [['Kode', 'Nama', 'Kategori', 'Kondisi', 'Tanggal Perolehan', 'Nilai Perolehan', 'Lokasi']];

        foreach ($rows as $r) {
            $data[] = [$r->kode ?? '', $r->nama, $r->kategori, $r->kondisi, $r->tanggal_perolehan ?? '', $r->nilai_perolehan ?? 0, $r->lokasi ?? ''];
        }

        return $data;
    }

    private function exportGaji(string $gerejaId): array
    {
        $rows = DB::table('pembayaran_gaji as pg')
            ->leftJoin('pegawai as peg', 'pg.pegawai_id', '=', 'peg.id')
            ->where('pg.gereja_id', $gerejaId)
            ->orderBy('pg.periode')
            ->select('pg.periode', 'peg.nama as pegawai_nama', 'peg.jabatan', 'peg.tipe', 'pg.nominal', 'pg.status', 'pg.tanggal_bayar')
            ->get();

        $data = [['Periode', 'Nama Pegawai', 'Jabatan', 'Tipe', 'Nominal', 'Status', 'Tanggal Bayar']];

        foreach ($rows as $r) {
            $data[] = [$r->periode, $r->pegawai_nama ?? '', $r->jabatan ?? '', $r->tipe ?? '', $r->nominal, $r->status, $r->tanggal_bayar ?? ''];
        }

        return $data;
    }

    private function exportSemua(string $gerejaId): array
    {
        $sections = [];

        $sections[] = ['=== PEMASUKAN ==='];
        $pem = $this->exportPemasukan($gerejaId);
        foreach ($pem as $row) {
            $sections[] = $row;
        }

        $sections[] = [];
        $sections[] = ['=== PENGELUARAN ==='];
        $kel = $this->exportPengeluaran($gerejaId);
        foreach ($kel as $row) {
            $sections[] = $row;
        }

        $sections[] = [];
        $sections[] = ['=== ASET ==='];
        $aset = $this->exportAset($gerejaId);
        foreach ($aset as $row) {
            $sections[] = $row;
        }

        $sections[] = [];
        $sections[] = ['=== GAJI & HONOR ==='];
        $gaji = $this->exportGaji($gerejaId);
        foreach ($gaji as $row) {
            $sections[] = $row;
        }

        return $sections;
    }

    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        // BOM UTF-8 agar Excel terbaca benar
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}

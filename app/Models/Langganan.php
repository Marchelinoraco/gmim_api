<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Langganan extends Model
{
    protected $table = 'langganan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'gereja_id', 'paket_id', 'status', 'siklus',
        'trial_berakhir', 'mulai', 'berakhir', 'auto_renew',
    ];

    protected $casts = [
        'trial_berakhir' => 'date',
        'mulai'          => 'date',
        'berakhir'       => 'date',
        'auto_renew'     => 'boolean',
    ];

    // Grace period 7 hari setelah berakhir
    public const GRACE_DAYS = 7;

    public function gereja()
    {
        return $this->belongsTo(Gereja::class);
    }

    public function paket()
    {
        return $this->belongsTo(Paket::class);
    }

    public function tagihan()
    {
        return $this->hasMany(Tagihan::class);
    }

    /**
     * Hitung status efektif berdasarkan tanggal hari ini.
     * Sumber kebenaran tunggal — dipakai middleware & job evaluasi.
     */
    public function statusEfektif(): string
    {
        if (in_array($this->status, ['canceled'])) {
            return $this->status;
        }

        $now = now()->startOfDay();

        if ($this->status === 'trial') {
            if ($this->trial_berakhir && $now->gt($this->trial_berakhir)) {
                return 'expired';
            }
            return 'trial';
        }

        if (in_array($this->status, ['expired'])) {
            return 'expired';
        }

        // active atau past_due — cek tanggal berakhir
        if ($this->berakhir && $now->gt($this->berakhir)) {
            $graceAkhir = $this->berakhir->copy()->addDays(self::GRACE_DAYS);
            if ($now->lte($graceAkhir)) {
                return 'past_due';
            }
            return 'expired';
        }

        return 'active';
    }

    public function isAktif(): bool
    {
        return in_array($this->statusEfektif(), ['trial', 'active', 'past_due']);
    }

    /** Sisa hari trial (null jika bukan trial). */
    public function trialSisaHari(): ?int
    {
        if ($this->status !== 'trial' || ! $this->trial_berakhir) {
            return null;
        }
        $sisa = now()->startOfDay()->diffInDays($this->trial_berakhir, false);
        return max(0, $sisa);
    }
}

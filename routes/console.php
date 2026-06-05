<?php

use App\Jobs\EvaluasiLanggananJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Evaluasi status langganan setiap hari pukul 01:00 WIB
Schedule::job(EvaluasiLanggananJob::class)->dailyAt('01:00');

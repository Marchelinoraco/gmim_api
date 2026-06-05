<?php

namespace App\Mail;

use App\Models\Langganan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LanggananPengingatMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Langganan $langganan
     * @param  int       $hariSelisih  >0 = sisa hari, 0 = hari ini, <0 = sudah lewat (H+1)
     */
    public function __construct(
        public readonly Langganan $langganan,
        public readonly int       $hariSelisih,
    ) {}

    public function envelope(): Envelope
    {
        $gerejaNama = $this->langganan->gereja->nama;
        $subjek     = match (true) {
            $this->hariSelisih > 0  => "Pengingat: Langganan {$gerejaNama} berakhir dalam {$this->hariSelisih} hari",
            $this->hariSelisih === 0 => "Penting: Langganan {$gerejaNama} berakhir hari ini",
            default                 => "Langganan {$gerejaNama} telah berakhir — perbarui segera",
        };

        return new Envelope(subject: $subjek);
    }

    public function content(): Content
    {
        $isTrial = $this->langganan->status === 'trial';

        return new Content(
            markdown: 'emails.langganan.pengingat',
            with: [
                'gerejaNama'   => $this->langganan->gereja->nama,
                'status'       => $this->langganan->status,
                'hariSelisih'  => $this->hariSelisih,
                'tanggalAkhir' => $isTrial
                    ? $this->langganan->trial_berakhir?->format('d F Y')
                    : $this->langganan->berakhir?->format('d F Y'),
                'paketNama'    => $this->langganan->paket?->nama ?? '—',
                'billingUrl'   => rtrim(config('app.frontend_url', 'http://localhost:5173'), '/')
                                  . '/g/' . $this->langganan->gereja->slug . '/billing',
            ],
        );
    }
}

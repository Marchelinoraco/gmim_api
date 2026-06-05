<?php

namespace App\Mail;

use App\Models\Tagihan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TagihanMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Tagihan $tagihan) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tagihan {$this->tagihan->nomor} — {$this->tagihan->gereja->nama}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tagihan.invoice',
            with: [
                'gerejaNama'  => $this->tagihan->gereja->nama,
                'nomor'       => $this->tagihan->nomor,
                'periode'     => $this->tagihan->periode,
                'jumlah'      => number_format($this->tagihan->jumlah, 0, ',', '.'),
                'jatuhTempo'  => $this->tagihan->jatuh_tempo?->format('d F Y'),
                'status'      => $this->tagihan->status,
                'billingUrl'  => rtrim(config('app.frontend_url', 'http://localhost:5173'), '/')
                                 . '/g/' . $this->tagihan->gereja->slug . '/billing',
            ],
        );
    }
}

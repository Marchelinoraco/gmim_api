<x-mail::message>
# Tagihan Langganan — {{ $nomor }}

Halo, bendahara **{{ $gerejaNama }}**.

Berikut detail tagihan langganan Anda:

<x-mail::table>
| Item | Detail |
|:-----|:-------|
| Nomor Tagihan | {{ $nomor }} |
| Periode | {{ $periode }} |
| Jumlah | Rp {{ $jumlah }} |
| Jatuh Tempo | {{ $jatuhTempo }} |
| Status | {{ $status === 'paid' ? 'Lunas' : 'Belum Bayar' }} |
</x-mail::table>

@if($status !== 'paid')
Silakan lakukan pembayaran sebelum jatuh tempo untuk menghindari gangguan layanan.

<x-mail::button :url="$billingUrl" color="blue">
Bayar Sekarang
</x-mail::button>
@else
Pembayaran Anda telah diterima. Terima kasih!
@endif

Salam,
Tim GMIM Keuangan
</x-mail::message>

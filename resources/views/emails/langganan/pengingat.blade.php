<x-mail::message>
@if($hariSelisih > 0)
# Langganan Berakhir dalam {{ $hariSelisih }} Hari
@elseif($hariSelisih === 0)
# Langganan Berakhir Hari Ini
@else
# Langganan Telah Berakhir
@endif

Halo, bendahara **{{ $gerejaNama }}**.

@if($hariSelisih > 0)
Langganan paket **{{ $paketNama }}** Anda akan berakhir pada **{{ $tanggalAkhir }}** ({{ $hariSelisih }} hari lagi).
Perbarui sekarang agar akses pencatatan keuangan tidak terputus.
@elseif($hariSelisih === 0)
Langganan paket **{{ $paketNama }}** Anda berakhir **hari ini** ({{ $tanggalAkhir }}).
Segera perbarui untuk menghindari pembatasan akses.
@else
Langganan paket **{{ $paketNama }}** Anda telah berakhir pada {{ $tanggalAkhir }}.
Akses pencatatan keuangan dibatasi. Data Anda tetap aman dan bisa diakses kembali setelah perpanjangan.
@endif

<x-mail::button :url="$billingUrl" color="blue">
Perbarui Langganan
</x-mail::button>

Terima kasih telah menggunakan **GMIM Keuangan**.

Salam,
Tim GMIM Keuangan
</x-mail::message>

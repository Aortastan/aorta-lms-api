@component('mail::message')
# Pembayaran Paket berhasil

Pembelian paket {{ $transaction_name }} telah berhasil dilakukan. Berikut detail dari pembayaran.<br><br>

Nama paket  : {{ $transaction_name }}<br>
id          : {{ $transaction_id }}<br>
Harga       : Rp{{ number_format($amount, 0, ',', '.') }}<br>

<br>
<br>

Terimakasih,<br>
{{ env('APP_NAME') }}
@endcomponent

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Riwayat Transaksi - Chicken Crunchy Roll</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #3b1f1a; font-size: 12px; margin: 24px; }
    .aksi { margin-bottom: 16px; padding: 10px 14px; background: #fff8ee; border: 1px solid #f0e4d3; border-radius: 8px; display: flex; gap: .5rem; align-items: center; }
    .aksi button { padding: 6px 14px; cursor: pointer; }
    .kop { width: 100%; border-collapse: collapse; border-bottom: 3px solid #9c1d1d; margin-bottom: 14px; }
    .kop td { padding: 0 0 10px; vertical-align: bottom; }
    .brand { font-size: 11px; font-weight: bold; color: #9c1d1d; letter-spacing: .04em; }
    h1 { font-size: 22px; margin: 2px 0 0; }
    .kanan { text-align: right; color: #8a7267; font-size: 10.5px; line-height: 1.55; }
    .kanan b { color: #3b1f1a; font-size: 12px; }
    .ringkas { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 16px; }
    .ringkas td { width: 20%; padding: 9px 12px; background: #fbf3ec; border: 1px solid #f0e4d3; }
    .ringkas td.utama { background: #9c1d1d; border-color: #9c1d1d; color: #fff; }
    .ringkas .lbl { font-size: 10px; color: #8a7267; }
    .ringkas .utama .lbl { color: #f6d9d9; }
    .ringkas b { font-size: 15px; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: #9c1d1d; color: #fff; text-align: left; padding: 7px 8px; font-size: 11px; }
    table.data td { padding: 6px 8px; border-bottom: 1px solid #f0e4d3; vertical-align: top; }
    table.data tbody tr:nth-child(even) td { background: #fbf3ec; }
    table.data tr { page-break-inside: avoid; }
    table.data tfoot td { border-top: 2px solid #9c1d1d; font-weight: bold; background: #fff; }
    .r { text-align: right; } .c { text-align: center; } .nw { white-space: nowrap; }
    .rincian { color: #6b5249; font-size: 10.5px; line-height: 1.45; }
    .kosong { text-align: center; padding: 28px; color: #8a7267; }
    .footer { margin-top: 18px; padding-top: 6px; border-top: 1px solid #f0e4d3; color: #8a7267; font-size: 9.5px; }
    @media print { .aksi { display: none; } body { margin: 0; } @page { margin: 12mm; } thead { display: table-header-group; } }
  </style>
</head>
<body>
  @php
    $rp = fn ($v) => 'Rp ' . number_format((int) $v, 0, ',', '.');
    $periode = $dari->translatedFormat('d F Y') . ($dari->isSameDay($sampai) ? '' : ' – ' . $sampai->translatedFormat('d F Y'));
    $namaItem = function ($i) {
        $luar = $i->cara_luar === 'saus_celup' ? ($i->sausCelup->nama_saus ?? null) : ($i->bumbuTabur->nama_bumbu ?? null);
        return collect([$i->ukuran->nama_ukuran ?? null, $i->filling->nama_filling ?? null, $luar])->filter()->implode(' · ');
    };
  @endphp

  @unless (request()->boolean('embed'))
  <div class="aksi">
    <button onclick="window.print()">Cetak</button>
    <a href="{{ route('pos.riwayat') }}">Kembali</a>
    <span style="color:#8a7267">Pilih "Simpan sebagai PDF" pada printer jika ingin menyimpan sebagai PDF.</span>
  </div>
  @endunless

  <table class="kop">
    <tr>
      <td>
        <div class="brand">CHICKEN CRUNCHY ROLL</div>
        <h1>Riwayat Transaksi</h1>
      </td>
      <td class="kanan">
        <b>{{ $periode }}</b><br>
        Kasir: {{ $kasir }}@if ($metode) &middot; Metode: {{ strtoupper($metode) }}@endif<br>
        Dicetak {{ now()->translatedFormat('d F Y, H:i') }}
      </td>
    </tr>
  </table>

  <table class="ringkas">
    <tr>
      <td class="utama"><div class="lbl">Total Penjualan</div><b>{{ $rp($total) }}</b></td>
      <td><div class="lbl">Jumlah Transaksi</div><b>{{ number_format($transaksi->count(), 0, ',', '.') }}</b></td>
      <td><div class="lbl">Porsi Terjual</div><b>{{ number_format($porsi, 0, ',', '.') }}</b></td>
      <td><div class="lbl">Total Cash</div><b>{{ $rp($totalCash) }}</b></td>
      <td><div class="lbl">Total QRIS</div><b>{{ $rp($totalQris) }}</b></td>
    </tr>
  </table>

  <table class="data">
    <thead>
      <tr>
        <th class="c" style="width:32px">No</th>
        <th>No. Transaksi</th>
        <th>Tanggal &amp; Jam</th>
        <th>Pembeli</th>
        <th>Rincian Pesanan</th>
        <th class="c">Metode</th>
        <th class="r">Total</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($transaksi as $t)
        <tr>
          <td class="c">{{ $loop->iteration }}</td>
          <td class="nw"><b>{{ $t->kode }}</b></td>
          <td class="nw">{{ $t->tanggal->format('d/m/Y H:i') }}</td>
          <td>{{ $t->nama_pembeli ?: 'Umum' }}</td>
          <td class="rincian">
            @foreach ($t->items->groupBy(fn ($i) => $namaItem($i)) as $nama => $grup)
              {{ $grup->count() }}× {{ $nama }}@if (! $loop->last)<br>@endif
            @endforeach
          </td>
          <td class="c">{{ strtoupper($t->metode_bayar) }}</td>
          <td class="r nw">{{ $rp($t->total_harga) }}</td>
        </tr>
      @empty
        <tr><td colspan="7" class="kosong">Tidak ada transaksi pada periode ini.</td></tr>
      @endforelse
    </tbody>
    @if ($transaksi->isNotEmpty())
      <tfoot>
        <tr>
          <td colspan="6" class="r">TOTAL ({{ $transaksi->count() }} transaksi, {{ $porsi }} porsi)</td>
          <td class="r nw">{{ $rp($total) }}</td>
        </tr>
      </tfoot>
    @endif
  </table>

  <div class="footer">Chicken Crunchy Roll &middot; Dicetak dari halaman Riwayat Transaksi Kasir</div>

  </body>
</html>

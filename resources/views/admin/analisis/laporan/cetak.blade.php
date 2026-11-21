<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>{{ $judul }} - Chicken Crunchy Roll</title>
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; color: #3b1f1a; font-size: 12px; margin: 24px; }
    .kop { width: 100%; border-collapse: collapse; border-bottom: 3px solid #9c1d1d; margin-bottom: 14px; }
    .kop td { padding: 0 0 10px; vertical-align: bottom; }
    .brand { font-size: 11px; font-weight: bold; color: #9c1d1d; }
    h1 { font-size: 20px; margin: 2px 0 0; color: #3b1f1a; }
    .kanan { text-align: right; color: #8a7267; font-size: 10px; line-height: 1.5; }
    .periode { font-size: 12px; font-weight: bold; color: #3b1f1a; }

    .ringkas { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 16px; }
    .ringkas td { width: 1%; padding: 9px 12px; background: #fbf3ec; border: 1px solid #f0e4d3; }
    .ringkas td.utama { background: #9c1d1d; border-color: #9c1d1d; color: #fff; }
    .ringkas .lbl { font-size: 10px; color: #8a7267; }
    .ringkas .utama .lbl { color: #f6d9d9; }
    .ringkas b { font-size: 15px; }

    .catatan { color: #8a7267; font-size: 10px; margin-top: 10px; }
    .footer { margin-top: 18px; padding-top: 6px; border-top: 1px solid #f0e4d3; color: #8a7267; font-size: 9px; }

    .aksi { margin-bottom: 16px; padding: 10px 14px; background: #fff8ee; border: 1px solid #f0e4d3; border-radius: 8px; }
    .aksi button { padding: 6px 14px; cursor: pointer; }
    @media print { .aksi { display: none; } body { margin: 0; } @page { margin: 12mm; } }
  </style>
</head>
<body>
  @if (! request()->boolean('embed') && ($mode ?? '') === 'print' && ! empty($sebagaiPdf))
    <div class="aksi">Untuk menyimpan sebagai PDF, pilih <b>Simpan sebagai PDF</b> pada printer di dialog cetak.
      <button onclick="window.print()">Cetak / Simpan PDF</button> <a href="{{ route('admin.laporan') }}">Kembali</a></div>
  @elseif (! request()->boolean('embed') && ($mode ?? '') === 'print')
    <div class="aksi"><button onclick="window.print()">Cetak</button> <a href="{{ route('admin.laporan') }}">Kembali</a></div>
  @endif

  <table class="kop">
    <tr>
      <td>
        <div class="brand">Chicken Crunchy Roll</div>
        <h1>{{ $judul }}</h1>
      </td>
      <td class="kanan">
        <div class="periode">
          {{ $dari->translatedFormat('d F Y') }}@if (! $dari->isSameDay($sampai)) &ndash; {{ $sampai->translatedFormat('d F Y') }}@endif
        </div>
        Dicetak {{ now()->translatedFormat('d F Y, H:i') }}
      </td>
    </tr>
  </table>

  @php $utamaDipilih = false; @endphp
  <table class="ringkas">
    <tr>
      @foreach ($ringkasan as [$label, $nilai, $tipeR])
        @php
          $utama = ! $utamaDipilih && $tipeR === 'rupiah';
          if ($utama) $utamaDipilih = true;
        @endphp
        <td class="{{ $utama ? 'utama' : '' }}">
          <div class="lbl">{{ $label }}</div>
          <b>{{ $tipeR === 'rupiah' ? 'Rp ' . number_format((int) $nilai, 0, ',', '.') : number_format((int) $nilai, 0, ',', '.') }}</b>
        </td>
      @endforeach
    </tr>
  </table>

  @include('admin.analisis.laporan._tabel', ['mode' => $mode ?? 'print'])

  @if (! empty($catatan))<div class="catatan">{{ $catatan }}</div>@endif

  <div class="footer">Chicken Crunchy Roll &middot; Laporan dibuat otomatis dari Admin Panel</div>

</body>
</html>

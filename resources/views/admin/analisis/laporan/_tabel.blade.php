@php
  $web = ($mode ?? 'web') === 'web';
  $fmt = function ($v, $t) {
      if ($t === 'rupiah') return 'Rp ' . number_format((int) $v, 0, ',', '.');
      if ($t === 'angka' && is_numeric($v)) return number_format((float) $v, 0, ',', '.');
      return $v;
  };
  $rata  = fn ($t) => in_array($t, ['rupiah', 'angka'], true) ? 'right' : ($t === 'no' ? 'center' : 'left');
  $kelas = fn ($t) => $rata($t) === 'right' ? 'text-end' : ($rata($t) === 'center' ? 'text-center' : '');
@endphp

<table @if ($web) class="table-custom lap-table" @else border="0" cellspacing="0" cellpadding="6" style="border-collapse:collapse;width:100%;font-size:11px;" @endif>
  <thead>
    <tr @unless ($web) style="background:#9c1d1d;color:#fff;" @endunless>
      @foreach ($kolom as $i => $k)
        <th @if ($web) class="{{ $kelas($tipe[$i]) }}" @else style="text-align:{{ $rata($tipe[$i]) }};padding:7px;" @endif>{{ $k }}</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @forelse ($baris as $row)
      <tr @unless ($web) style="background:{{ $loop->even ? '#fdeeea' : '#ffffff' }};" @endunless>
        @foreach ($row as $i => $v)
          @php
            $label = strtolower($kolom[$i]);
            $tampil = $fmt($v, $tipe[$i]);
          @endphp
          @if ($web)
            <td class="{{ $kelas($tipe[$i]) }} {{ $tipe[$i] === 'rupiah' ? 'table-amount text-nowrap lap-money' : '' }}">
              @if ($tipe[$i] === 'status')
                <span class="badge-table {{ $v === 'Aman' ? 'success' : ($v === 'Menipis' ? 'pending' : 'failed') }}">{{ $v }}</span>
              @elseif ($tipe[$i] === 'no')
                <span class="lap-no">{{ $tampil }}</span>
              @elseif (str_contains($label, 'metode'))
                @php $m = strtolower(trim((string) $v)); @endphp
                <span class="lap-pay lap-pay-{{ in_array($m, ['cash', 'qris']) ? $m : 'lain' }}">{{ $v }}</span>
              @elseif ($label === 'item')
                <span class="lap-qty">{{ $tampil }}</span>
              @elseif (str_contains($label, 'no. transaksi') || str_contains($label, 'no transaksi'))
                <span class="lap-code">{{ $v }}</span>
              @elseif (str_contains($label, 'tanggal') && preg_match('/^(.*?)[\s,]+(\d{1,2}[:.]\d{2}.*)$/', (string) $v, $mm))
                <div class="lap-date">{{ $mm[1] }}</div>
                <div class="lap-time">{{ $mm[2] }}</div>
              @elseif (str_contains($label, 'pesanan'))
                <div class="lap-order">
                  {{-- tiap menu di baris sendiri supaya pesanan panjang tidak menumpuk ke kolom sebelahnya --}}
                  @foreach (preg_split('/;\s*/', (string) $tampil, -1, PREG_SPLIT_NO_EMPTY) as $menu)
                    <div class="lap-order-item">{{ $menu }}</div>
                  @endforeach
                </div>
              @else
                {{ $tampil }}
              @endif
            </td>
          @else
            <td style="text-align:{{ $rata($tipe[$i]) }};border-bottom:1px solid #eadbcc;padding:6px;">{{ $tampil }}</td>
          @endif
        @endforeach
      </tr>
    @empty
      @if ($web)
        <tr class="lap-empty-row">
          <td colspan="{{ count($kolom) }}">
            <div class="lap-empty">
              <div class="lap-empty-icon"><i class="bi bi-receipt-cutoff"></i></div>
              <div class="lap-empty-title">{{ $kosong }}</div>
              <div class="lap-empty-hint">Ubah tanggal atau filter di atas untuk melihat data lain.</div>
            </div>
          </td>
        </tr>
      @else
        <tr><td colspan="{{ count($kolom) }}" style="text-align:center;padding:14px;">{{ $kosong }}</td></tr>
      @endif
    @endforelse
  </tbody>
</table>

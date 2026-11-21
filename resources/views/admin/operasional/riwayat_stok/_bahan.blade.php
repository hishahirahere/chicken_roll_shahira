<ul class="rs-items">
  @foreach ($e->isi as $r)
    @php
      $fotoBahan = $r->bahan?->foto_url;
      $rincian = $r->rincian;
      // tepung ikut paket ayam: rinciannya sama dengan ayam, tidak perlu diulang
      if ($sumber === 'paket' && ($rincian === 'Paket ke kasir' || str_starts_with((string) $rincian, 'Ikut paket ayam'))) { $rincian = null; }
    @endphp
    <li class="rs-it">
      <span class="rs-foto">
        @if ($fotoBahan)<img src="{{ $fotoBahan }}" alt="" loading="lazy">@else<i class="bi bi-box-seam"></i>@endif
      </span>
      <div class="rs-it-nama">
        <b>{{ $r->bahan->nama_bahan ?? '-' }}</b>
        @if ($rincian)<small>{{ $rincian }}</small>@endif
      </div>
      <div class="rs-it-qty">
        <span class="rs-jumlah {{ $r->jenis }}">{{ $r->jenis == 'masuk' ? '+' : '-' }}{{ $r->t_jumlah }}</span>
        @if ($r->t_sisa !== null)<small>{{ $r->t_sisa_label }}: {{ $r->t_sisa }}</small>@endif
      </div>
    </li>
  @endforeach
</ul>

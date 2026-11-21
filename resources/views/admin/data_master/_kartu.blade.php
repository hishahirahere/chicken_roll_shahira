{{-- Kartu item Data Master. Dipakai Ukuran, Filling, Saus Celup, Bumbu Tabur, dan Kasir.
     Props: $tipe, $item, $nama, $chip, $edit, $hapus
     Opsional: $desc, $harga, $tags [[ikon, teks, kelas]], $lihat, $bulat, $ikonKosong, $konfirmHapus --}}
@php
  $status   = ($item->status ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
  $aktif    = $status === 'aktif';
  $fotoUrl  = $item->foto ? asset('storage/' . $item->foto) : null;
  $ikonKosong = $ikonKosong ?? 'bi-image';
  $bulat    = $bulat ?? false;
  $tags     = $tags ?? [];
@endphp
<article class="mc {{ $aktif ? '' : 'is-off' }}" data-nama="{{ strtolower($nama) }}" data-status="{{ $status }}">
  <div class="mc-media">
    <span class="mc-chip">{{ $chip }}</span>
    <span class="mc-status {{ $status }}">{{ $aktif ? 'Aktif' : 'Nonaktif' }}</span>
    <div class="mc-photo {{ $bulat ? 'is-round' : '' }} {{ $fotoUrl ? '' : 'is-blank' }}">
      @if ($fotoUrl)
        <img src="{{ $fotoUrl }}" alt="{{ $nama }}" loading="lazy">
      @elseif ($ikonKosong)
        <i class="bi {{ $ikonKosong }}"></i>
      @endif
    </div>
  </div>

  <div class="mc-body">
    <h3 class="mc-name">{{ $nama }}</h3>
    @isset($desc)<p class="mc-desc">{{ $desc }}</p>@endisset
    @isset($harga)<div class="mc-price">{{ $harga }}</div>@endisset
    @if (count($tags))
      <div class="mc-tags">
        @foreach ($tags as $t)
          <span class="mc-tag {{ $t[2] ?? '' }}">@if (! empty($t[0]))<i class="bi {{ $t[0] }}"></i>@endif{{ $t[1] }}</span>
        @endforeach
      </div>
    @endif
  </div>

  <div class="mc-actions">
    <a href="{{ $edit }}" class="mc-btn-edit"><i class="bi bi-pencil-fill"></i> Edit</a>

    @isset($lihat)
      <a href="{{ $lihat }}" class="mc-btn-ic" title="Lihat detail"><i class="bi bi-eye-fill"></i></a>
    @endisset

    <form action="{{ $hapus }}" method="POST"
      onsubmit="return confirm('{{ $konfirmHapus ?? 'Yakin mau hapus ' . e($nama) . '?' }}')">
      @csrf
      @method('DELETE')
      <button type="submit" class="mc-btn-ic del" title="Hapus"><i class="bi bi-trash-fill"></i></button>
    </form>

    <form action="{{ route('master.status', [$tipe, $item->id]) }}" method="POST"
      onsubmit="return confirm('{{ $aktif ? 'Nonaktifkan ' . e($nama) . '? Data tetap tersimpan, tapi tidak bisa dipakai ' . ($tipe === 'kasir' ? 'untuk login.' : 'di transaksi kasir.') : 'Aktifkan kembali ' . e($nama) . '?' }}')">
      @csrf
      @method('PATCH')
      <button type="submit" class="mc-btn-ic power {{ $aktif ? 'on-state' : 'off-state' }}"
        title="{{ $aktif ? 'Nonaktifkan' : 'Aktifkan' }}">
        <i class="bi {{ $aktif ? 'bi-pause-circle-fill' : 'bi-play-circle-fill' }}"></i>
      </button>
    </form>
  </div>
</article>

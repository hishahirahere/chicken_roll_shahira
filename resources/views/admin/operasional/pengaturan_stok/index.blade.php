@extends('layouts.admin')

@section('title', 'Pengaturan Stok - Chicken Crunchy Roll')
@section('breadcrumb', 'Pengaturan Stok')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/operasional/stok_otomatis.css') }}">
@endpush

@section('content')
  @php
    $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
    $grup = [
      ['filling',     'Filling',     'bi-egg-fried',    $filling, 'nama_filling', 'filling'],
      ['saus_celup',  'Saus Celup',  'bi-droplet-fill', $saus,    'nama_saus',    'saus'],
      ['bumbu_tabur', 'Bumbu Tabur', 'bi-stars',        $bumbu,   'nama_bumbu',   'bumbu'],
    ];
  @endphp

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-sliders"></i></div>
    <div>
      <h1 class="page-title">Pengaturan Stok</h1>
      <p class="page-subtitle">Atur standar pemakaian bahan.</p>
    </div>
  </div>

  @if (! empty($kurang))
    <div class="so-warn">
      <div class="so-warn-ic"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <div class="flex-grow-1">
        <div class="so-warn-t">Pemetaan bahan belum lengkap</div>
        <ul class="so-warn-l">@foreach ($kurang as $k)<li>{{ $k }}</li>@endforeach</ul>
        <div class="so-warn-d">Bahan yang belum terhubung tidak akan dikurangi otomatis saat ada penjualan.</div>
      </div>
      <form method="POST" action="{{ route('stok.pengaturan.hubungkan') }}">
        @csrf
        <button class="btn-custom btn-custom-primary" type="submit"><i class="bi bi-link-45deg"></i> Hubungkan otomatis</button>
      </form>
    </div>
  @endif

  <form method="POST" action="{{ route('stok.pengaturan.simpan') }}" class="so-form">
    @csrf
    @method('PUT')

    @if ($errors->any())
      <div class="so-warn" style="background:#fde7e4;border-color:#f1b6b0;">
        <div class="so-warn-ic" style="background:#b3202f;"><i class="bi bi-x-octagon-fill"></i></div>
        <div><div class="so-warn-t">Ada isian yang belum benar</div>
          <ul class="so-warn-l">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
      </div>
    @endif

    {{-- ===== 1. Ayam per ukuran ===== --}}
    <section class="so-card">
      <header class="so-head"><span class="so-num">1</span><div><h2>Ayam jadi berapa potong</h2>
        <p>Hasil potong dari 1 kg ayam tiap ukuran.</p></div></header>
      <div class="so-rows">
        @forelse ($ukuran as $u)
          @php $s = $standar[$u->id]['hasil_pcs_per_kg'] ?? null; @endphp
          <div class="so-row">
            <span class="so-foto">@if ($u->foto)<img src="{{ asset('storage/' . $u->foto) }}" alt="">@else<i class="bi bi-image"></i>@endif</span>
            <div class="so-row-nama"><b>{{ $u->nama_ukuran }}</b><small>1 kg ayam jadi</small></div>
            <div class="input-group so-row-input"><input type="number" step="1" min="1" required name="hasil_pcs_per_kg[{{ $u->id }}]" value="{{ old('hasil_pcs_per_kg.' . $u->id, $s !== null ? $n($s) : '') }}" placeholder="isi" class="form-control"><span class="input-group-text">potong</span></div>
          </div>
        @empty
          <div class="so-empty">Belum ada ukuran. Tambahkan dulu di Master Ukuran.</div>
        @endforelse
      </div>
    </section>

    {{-- ===== 2. Bungkus ===== --}}
    <section class="so-card">
      <header class="so-head"><span class="so-num">2</span><div><h2>1 bungkus bisa dipakai berapa kali</h2>
        <p>Jumlah kali pakai tiap bungkus.</p></div></header>
      <div class="so-grid3">
        @foreach ([['kapasitas_filling', 'Filling', 'bi-egg-fried'], ['kapasitas_saus', 'Saus celup', 'bi-droplet-fill'], ['kapasitas_bumbu', 'Bumbu tabur', 'bi-stars']] as [$k, $label, $ik])
          <label class="so-tile">
            <span class="so-tile-ic"><i class="bi {{ $ik }}"></i></span>
            <span class="so-tile-t">{{ $label }}</span>
            <div class="input-group"><input type="number" step="1" min="1" required name="{{ $k }}" value="{{ old($k, $n($set[$k])) }}" class="form-control"><span class="input-group-text">kali pakai</span></div>
            <small>per 1 bungkus</small>
          </label>
        @endforeach
      </div>
    </section>

    {{-- ===== 3. Isi paket standar ===== --}}
    <section class="so-card">
      <header class="so-head"><span class="so-num">3</span><div><h2>Isi paket standar untuk kasir</h2>
        <p>Isi bawaan saat memberi paket ke kasir.</p></div></header>
      @foreach ([
        ['Ayam &amp; tepung', [
          ['paket_kg_per_ukuran', 'Ayam per ukuran', 'kg', 'bi-egg-fried'],
          ['paket_premix_basah_botol', 'Tepung premix basah', 'botol', 'bi-droplet'],
          ['paket_tepung_roti_bungkus', 'Tepung roti', 'bungkus', 'bi-basket'],
        ]],
        ['Bahan pendukung (tiap rasa)', [
          ['paket_filling_bungkus', 'Filling', 'bungkus', 'bi-egg-fried'],
          ['paket_saus_bungkus', 'Saus celup', 'bungkus', 'bi-droplet-fill'],
          ['paket_bumbu_bungkus', 'Bumbu tabur', 'bungkus', 'bi-stars'],
        ]],
        ['Minyak &amp; gas (sekali kirim)', [
          ['paket_minyak_jerigen', 'Minyak', 'jerigen', 'bi-droplet-half'],
          ['paket_gas_tabung', 'Gas', 'tabung', 'bi-fire'],
        ]],
      ] as [$judulGrup, $isiGrup])
        <h3 class="so-sub">{!! $judulGrup !!}</h3>
        <div class="so-grid3">
          @foreach ($isiGrup as [$k, $label, $sat, $ik])
            <label class="so-tile">
              <span class="so-tile-ic"><i class="bi {{ $ik }}"></i></span>
              <span class="so-tile-t">{{ $label }}</span>
              <div class="input-group"><input type="number" step="1" min="0" required name="{{ $k }}" value="{{ old($k, $n($set[$k])) }}" class="form-control"><span class="input-group-text">{{ $sat }}</span></div>
            </label>
          @endforeach
        </div>
      @endforeach
    </section>

    {{-- ===== Pengaturan lanjutan (jarang diubah) ===== --}}
    <details class="so-lanjut">
      <summary><i class="bi bi-gear"></i> Pengaturan lanjutan </summary>

      <section class="so-card">
        <header class="so-head"><span class="so-num"><i class="bi bi-box2"></i></span><div><h2>Kemasan &amp; batas menipis</h2>
          </div></header>
        <div class="so-grid4">
          <label class="so-field"><span><i class="bi bi-box2"></i> Kemasan per produk</span>
            <div class="input-group"><input type="number" step="1" min="0" required name="kemasan_per_produk" value="{{ old('kemasan_per_produk', $n($set['kemasan_per_produk'])) }}" class="form-control"><span class="input-group-text">pcs</span></div></label>
          <label class="so-field"><span><i class="bi bi-droplet"></i> Isi 1 jerigen minyak</span>
            <div class="input-group"><input type="number" step="1" min="1" required name="liter_per_jerigen" value="{{ old('liter_per_jerigen', $n($set['liter_per_jerigen'])) }}" class="form-control"><span class="input-group-text">liter</span></div></label>
          <label class="so-field"><span><i class="bi bi-exclamation-triangle"></i> Tandai "menipis" kalau sisa</span>
            <div class="input-group"><input type="number" step="1" min="1" max="90" required name="batas_menipis_persen" value="{{ old('batas_menipis_persen', $n($set['batas_menipis_persen'])) }}" class="form-control"><span class="input-group-text">%</span></div></label>
        </div>
      </section>

    {{-- ===== 4. Bahan utama ===== --}}
    <section class="so-card">
      <header class="so-head"><span class="so-num"><i class="bi bi-diagram-3"></i></span><div><h2>Bahan utama</h2>
        </div></header>
      <div class="so-grid3">
        @foreach ($peranUtama as $peran => [$namaBawaan, $satuan])
          <label class="so-field"><span>{{ $namaBawaan }}</span>
            <select name="peran[{{ $peran }}]" class="form-select">
              <option value="">— belum dipilih —</option>
              @foreach ($bahan as $b)
                <option value="{{ $b->id }}" @selected((int) old("peran.$peran", $peranBahan[$peran] ?? 0) === $b->id)>{{ $b->nama_bahan }} ({{ $b->satuan }})</option>
              @endforeach
            </select></label>
        @endforeach
      </div>
    </section>

    {{-- ===== 5. Hubungan menu -> bahan ===== --}}
    <section class="so-card">
      <header class="so-head"><span class="so-num"><i class="bi bi-link-45deg"></i></span><div><h2>Hubungan menu dengan bahan baku</h2>
        <p>Bahan baku tiap menu dan kali pakai per bungkus.</p></div></header>

      @foreach ($grup as [$tabel, $judul, $ikon, $rows, $kolom, $peran])
        <h3 class="so-sub"><i class="bi {{ $ikon }}"></i> {{ $judul }}</h3>
        <div class="so-scroll">
          <table class="so-table">
            <thead><tr><th>{{ $judul }}</th><th>Bahan baku</th><th style="width:230px">Kali pakai / bungkus</th></tr></thead>
            <tbody>
              @forelse ($rows as $r)
                @php $bh = $bahanById[$r->bahan_baku_id] ?? null; @endphp
                <tr>
                  <td><span class="so-item"><span class="so-foto">@if ($r->foto)<img src="{{ asset('storage/' . $r->foto) }}" alt="">@else<i class="bi {{ $ikon }}"></i>@endif</span><b>{{ $r->{$kolom} }}</b>
                    @if (($r->status ?? 'aktif') === 'nonaktif')<span class="so-off">nonaktif</span>@endif</span></td>
                  <td>
                    <select name="hubungan[{{ $tabel }}][{{ $r->id }}]" class="form-select">
                      <option value="">— belum terhubung —</option>
                      @foreach ($bahan as $b)
                        <option value="{{ $b->id }}" @selected((int) old("hubungan.$tabel.$r->id", $r->bahan_baku_id ?? 0) === $b->id)>{{ $b->nama_bahan }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    @if ($bh)
                      <div class="input-group"><input type="number" step="1" min="1" name="porsi_per_bungkus[{{ $bh->id }}]" value="{{ old("porsi_per_bungkus.$bh->id", $bh->porsi_per_bungkus !== null ? $n($bh->porsi_per_bungkus) : '') }}" placeholder="ikut standar" class="form-control"><span class="input-group-text">kali</span></div>
                    @else <span class="text-muted-green">—</span> @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-center text-muted-green p-3">Belum ada data.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      @endforeach
    </section>
    </details>

    <div class="so-save">
      <a href="{{ route('bahan_baku.index') }}" class="btn-custom" style="background:#fff;border:2px solid #7A1E1E;color:#7A1E1E;font-weight:700;"><i class="bi bi-arrow-left"></i> Kembali</a>
      <button type="submit" class="btn-custom btn-custom-primary"><i class="bi bi-check-lg"></i> Simpan Pengaturan</button>
    </div>
  </form>
@endsection

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
      <p class="page-subtitle">Semua angka standar pemakaian bahan baku. Ubah di sini kalau standar berubah, tanpa menyentuh kode.</p>
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
      <header class="so-head"><span class="so-num">1</span><div><h2>Ayam per ukuran</h2>
        <p>Hasil pcs per <b>1 kg ayam</b>. Tepung premix basah &amp; tepung roti <b>tidak</b> dihitung dari ayam: keduanya isi paket tetap (premix basah per botol 1 liter, tepung roti per bungkus 1 kg), diatur di bagian "Paket standar".</p></div></header>
      <div class="so-scroll">
        <table class="so-table">
          <thead><tr><th>Ukuran</th><th>1 kg ayam jadi (pcs)</th></tr></thead>
          <tbody>
            @forelse ($ukuran as $u)
              @php $s = $standar[$u->id] ?? ['hasil_pcs_per_kg' => '']; @endphp
              <tr>
                <td><span class="so-item"><span class="so-foto">@if ($u->foto)<img src="{{ asset('storage/' . $u->foto) }}" alt="">@else<i class="bi bi-image"></i>@endif</span><b>{{ $u->nama_ukuran }}</b></span></td>
                <td><span class="so-lock"><i class="bi bi-lock-fill"></i> {{ $s['hasil_pcs_per_kg'] === '' ? '-' : $n($s['hasil_pcs_per_kg']) }} pcs</span></td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-center text-muted-green p-4">Belum ada ukuran. Tambahkan dulu di Master Ukuran.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <p class="so-note"><i class="bi bi-lock"></i> Hasil per 1 kg ayam (Slim 25, Reguler 20, Jumbo 15) sudah baku dan <b>tidak bisa diubah</b>. Hasil ini dipakai saat memberi paket: kg ayam per ukuran diubah jadi stok pcs di kasir.</p>
    </section>

    {{-- ===== 2. Bungkus ===== --}}
    <section class="so-card">
      <header class="so-head"><span class="so-num">2</span><div><h2>Filling, saus celup &amp; bumbu tabur</h2>
        <p>Satuan <b>bungkus</b>, dihitung per <b>porsi</b>. 1 produk memakai 1 porsi tiap bahan yang dipilih pelanggan.</p></div></header>
      <div class="so-grid3">
        @foreach ([['kapasitas_filling', 'Filling', 'bi-egg-fried'], ['kapasitas_saus', 'Saus celup', 'bi-droplet-fill'], ['kapasitas_bumbu', 'Bumbu tabur', 'bi-stars']] as [$k, $label, $ik])
          <label class="so-field"><span><i class="bi {{ $ik }}"></i> {{ $label }}</span>
            <div class="input-group"><input type="number" step="1" min="1" required name="{{ $k }}" value="{{ old($k, $n($set[$k])) }}" class="form-control"><span class="input-group-text">kali pakai / bungkus</span></div>
          </label>
        @endforeach
      </div>
      <p class="so-note"><i class="bi bi-lightbulb"></i> Contoh: 1 bungkus = 12 porsi, sudah dipakai 10 porsi, maka sistem otomatis menampilkan <b>sisa 2 porsi</b> di bungkus yang berjalan. Bungkus baru baru dibuka setelah sisa habis.</p>
    </section>

    {{-- ===== 3. Kemasan ===== --}}
    <section class="so-card">
      <header class="so-head"><span class="so-num">3</span><div><h2>Kemasan</h2>
        <p>Kemasan dibedakan per ukuran (Slim, Reguler, Jumbo): kalau yang terjual Slim, kemasan Slim yang berkurang. Kemasan di paket ke kasir otomatis sama dengan jumlah pcs tiap ukuran. <b>Minyak &amp; gas tidak dihitung otomatis</b>: dikirim admin lewat tombol "Kirim Minyak &amp; Gas" di Manajemen Stok, selalu sebesar <b>porsi tetap</b> (lihat "Paket standar"). Kasir tidak bisa memilih jumlah, dan baru bisa minta kalau stoknya sudah menipis atau habis.</p></div></header>
      <div class="so-grid4">
        <label class="so-field"><span><i class="bi bi-box2"></i> Kemasan per ukuran</span>
          <div class="input-group"><input type="number" step="1" min="0" required name="kemasan_per_produk" value="{{ old('kemasan_per_produk', $n($set['kemasan_per_produk'])) }}" class="form-control"><span class="input-group-text">pcs / produk</span></div></label>
      </div>
    </section>

    {{-- ===== Paket standar ===== --}}
    <section class="so-card">
      <header class="so-head"><span class="so-num">P</span><div><h2>Paket standar ke kasir</h2>
        <p>Isi awal tombol <b>"Paket standar"</b> di form Beri Paket. Admin tetap bisa mengubah angkanya saat memberi paket.</p></div></header>
      <div class="so-grid4">
        @foreach ([
          ['paket_kg_per_ukuran', 'Ayam per ukuran', 'kg', 'bi-egg-fried', '1'],
          ['paket_filling_bungkus', 'Filling per jenis', 'bungkus', 'bi-egg-fried', '1'],
          ['paket_saus_bungkus', 'Saus celup per jenis', 'bungkus', 'bi-droplet-fill', '1'],
          ['paket_bumbu_bungkus', 'Bumbu tabur per jenis', 'bungkus', 'bi-stars', '1'],
          ['paket_premix_basah_botol', 'Tepung premix basah (1 botol = 1 liter)', 'botol', 'bi-droplet', '1'],
          ['paket_tepung_roti_bungkus', 'Tepung roti (1 bungkus = 1 kg)', 'bungkus', 'bi-basket', '1'],
          ['paket_minyak_jerigen', 'Jatah minyak sekali kirim (1 jerigen = 5 liter)', 'jerigen', 'bi-droplet-half', '1'],
          ['paket_gas_tabung', 'Jatah gas sekali kirim', 'tabung', 'bi-fire', '1'],
          ['liter_per_jerigen', 'Isi 1 jerigen minyak', 'liter', 'bi-droplet', '1'],
          ['batas_menipis_persen', 'Batas "menipis" di kasir', '% sisa', 'bi-exclamation-triangle', '1'],
        ] as [$k, $label, $sat, $ik, $step])
          <label class="so-field"><span><i class="bi {{ $ik }}"></i> {{ $label }}</span>
            <div class="input-group"><input type="number" step="{{ $step }}" min="0" required name="{{ $k }}" value="{{ old($k, $n($set[$k])) }}" class="form-control"><span class="input-group-text">{{ $sat }}</span></div>
          </label>
        @endforeach
      </div>
      <p class="so-note"><i class="bi bi-info-circle"></i> Contoh: paket standar 1 kg per ukuran = 3 kg ayam = 25 Slim + 20 Reguler + 15 Jumbo = 60 pcs, memakai {{ $n($set['paket_premix_basah_botol']) }} botol premix basah ({{ $n($set['paket_premix_basah_botol']) }} liter) + {{ $n($set['paket_tepung_roti_bungkus']) }} bungkus tepung roti ({{ $n($set['paket_tepung_roti_bungkus']) }} kg). Minyak &amp; gas <b>tidak</b> ikut paket harian: dikirim sebagai jatah tetap ({{ $n($set['paket_minyak_jerigen']) }} jerigen minyak, {{ $n($set['paket_gas_tabung']) }} tabung gas) untuk beberapa hari, dan dikirim lagi saat stok kasir habis. Stok kasir ditandai <b>menipis</b> kalau sisanya tinggal {{ $n($set['batas_menipis_persen']) }}% dari yang terakhir diterima.</p>
    </section>

    {{-- ===== 4. Bahan utama ===== --}}
    <section class="so-card">
      <header class="so-head"><span class="so-num">4</span><div><h2>Bahan utama</h2>
        <p>Pilih bahan baku yang dipakai untuk tiap peran.</p></div></header>
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
      <header class="so-head"><span class="so-num">5</span><div><h2>Hubungan menu dengan bahan baku</h2>
        <p>Tiap filling, saus celup, dan bumbu tabur terhubung ke 1 bahan baku. Kolom kanan: porsi per bungkus khusus bahan itu (kosong = ikut standar di atas).</p></div></header>

      @foreach ($grup as [$tabel, $judul, $ikon, $rows, $kolom, $peran])
        <h3 class="so-sub"><i class="bi {{ $ikon }}"></i> {{ $judul }}</h3>
        <div class="so-scroll">
          <table class="so-table">
            <thead><tr><th>{{ $judul }}</th><th>Bahan baku</th><th style="width:210px">Porsi / bungkus</th></tr></thead>
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
                      <div class="input-group"><input type="number" step="1" min="1" name="porsi_per_bungkus[{{ $bh->id }}]" value="{{ old("porsi_per_bungkus.$bh->id", $bh->porsi_per_bungkus !== null ? $n($bh->porsi_per_bungkus) : '') }}" placeholder="ikut standar" class="form-control"><span class="input-group-text">porsi</span></div>
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

    <div class="so-save">
      <a href="{{ route('bahan_baku.index') }}" class="btn-custom" style="background:#fff;border:2px solid #7A1E1E;color:#7A1E1E;font-weight:700;"><i class="bi bi-arrow-left"></i> Kembali</a>
      <button type="submit" class="btn-custom btn-custom-primary"><i class="bi bi-check-lg"></i> Simpan Pengaturan</button>
    </div>
  </form>
@endsection

@extends('layouts.kasir')

@section('title', 'Stok Saya - Chicken Crunchy Roll')
@section('breadcrumb', 'Stok Saya')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/data_master/master.css') }}">
  <link rel="stylesheet" href="{{ asset('assets_user/kasir/stok/stok.css') }}?v={{ @filemtime(public_path('assets_user/kasir/stok/stok.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets_user/kasir/stok/minyak_gas.css') }}?v={{ @filemtime(public_path('assets_user/kasir/stok/minyak_gas.css')) }}">
@endpush

@section('content')
  @php
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
    $urut = ['habis' => 0, 'menipis' => 1, 'aman' => 2];
    // [judul, keterangan, ikon, kolom pemilah, nilai]
    $grup = [
      'produk'  => ['Produk siap jual', 'Sisa pcs tiap ukuran, dari ayam + tepung paket', 'bi-egg-fried', 'kategori', 'produk'],
      'filling' => ['Filling', 'Sisa kali pakai. 1 produk = 1 kali pakai', 'bi-layers-fill', 'peran', 'filling'],
      'saus'    => ['Saus Celup', 'Sisa kali pakai tiap saus', 'bi-droplet-fill', 'peran', 'saus'],
      'bumbu'   => ['Bumbu Tabur', 'Sisa kali pakai tiap bumbu', 'bi-stars', 'peran', 'bumbu'],
      'kemasan' => ['Kemasan', 'Kemasan tiap ukuran, berkurang 1 sesuai ukuran yang terjual', 'bi-box2', 'kategori', 'kemasan'],
      'manual'  => ['Minyak & Gas', 'Dicatat manual. Kalau habis, minta ke admin', 'bi-fuel-pump-fill', 'kategori', 'manual'],
    ];
    $peluang = $item->filter(fn ($b) => $b->status !== 'aman')->sortBy(fn ($b) => $urut[$b->status]);
  @endphp

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-box-seam-fill"></i></div>
    <div>
      <h1 class="page-title">Stok Saya</h1>
      <p class="page-subtitle">Sisa produk dan bahan yang kamu pegang. Berkurang otomatis setiap ada pembayaran.</p>
    </div>
  </div>

  @php
    $habisMg = collect(['minyak', 'gas'])->filter(fn ($k) => ! isset($manual[$k]) || (float) $manual[$k]->jumlah <= 0)->count();
    $bukaCatat = $errors->any() || session('peringatan') || old('minyak') !== null || old('gas') !== null;
  @endphp

  <div class="ks-switch" id="ks-switch" role="tablist">
    <button type="button" class="ks-sw on" data-pane="stok" role="tab"><i class="bi bi-boxes"></i> Stok</button>
    <button type="button" class="ks-sw" data-pane="catat" role="tab">
      <i class="bi bi-fuel-pump-fill"></i> Catat Minyak &amp; Gas
      @if ($habisMg > 0)<span class="ks-sw-dot" title="Ada yang habis">{{ $habisMg }}</span>@endif
    </button>
    <button type="button" class="ks-sw" data-pane="riwayat" role="tab"><i class="bi bi-clock-history"></i> Riwayat</button>
  </div>

  <div id="pane-stok" data-pane-body="stok">
  @if ($peluang->isNotEmpty())
    <div class="ks-alert">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <div>
        <b>{{ $ringkas['habis'] > 0 ? 'Ada yang habis, ambil ke admin (ayam: paket, rasa: bahan pendukung).' : 'Ada yang mulai menipis, siapkan ambil ke admin sebelum habis.' }}</b>
        <span class="ks-alert-list">
          @foreach ($peluang->take(6) as $b)
            <span class="ks-pill">{{ $b->nama }} · {{ $b->status === 'habis' ? 'habis' : 'sisa ' . $n($b->jumlah) . ' ' . $b->satuan }}</span>
          @endforeach
          @if ($peluang->count() > 6) <span class="ks-pill">+{{ $peluang->count() - 6 }} lagi</span> @endif
        </span>
      </div>
    </div>
  @endif


  <div class="ks-layout solo">
    {{-- ===== KIRI: stok per kelompok (tab), tanpa daftar panjang ke bawah ===== --}}
    <div class="card ks-main">
      <div class="card-banner"><h2><i class="bi bi-boxes me-2"></i>Stok di Tanganku</h2></div>
      <div class="ks-body">

      <div class="ks-tabs" id="ks-tabs" role="tablist">
        @foreach ($grup as $kat => [$judul, $ket, $ikGrup, $kolom, $nilai])
          @php $jml = $item->where($kolom, $nilai)->count(); @endphp
          @continue($jml === 0)
          <button type="button" class="ks-tab" data-tab="{{ $kat }}" role="tab">
            <i class="bi {{ $ikGrup }}"></i> {{ $judul }} <b data-count="{{ $kat }}">{{ $jml }}</b>
          </button>
        @endforeach
      </div>

      <div class="ks-tools">
        <div class="mc-filter" role="group" id="ks-filter">
          <button type="button" class="on" data-filter="semua">Semua<b>{{ $item->count() }}</b></button>
          <button type="button" data-filter="habis">Habis<b>{{ $ringkas['habis'] }}</b></button>
          <button type="button" data-filter="menipis">Menipis<b>{{ $ringkas['menipis'] }}</b></button>
          <button type="button" data-filter="aman">Aman<b>{{ $ringkas['aman'] }}</b></button>
        </div>
        <div class="page-search-wrapper" style="max-width:240px;">
          <input type="text" class="page-search-input" id="search-stok" placeholder="Cari...">
          <i class="bi bi-search"></i>
        </div>
      </div>

      <div class="ks-scroll">
      @foreach ($grup as $kat => [$judul, $ket, $ikGrup, $kolom, $nilai])
        @php $daftar = $item->where($kolom, $nilai)->values(); @endphp
        @continue($daftar->isEmpty())
        <section class="ks-section" data-section="{{ $kat }}">
          <p class="ks-ket"><i class="bi bi-info-circle"></i> {{ $ket }}</p>
          <div class="ks-grid">
            @foreach ($daftar as $b)
              @php $fotoUrl = $b->foto ? asset('storage/' . $b->foto) : null; @endphp
              <div class="ks-tile {{ $b->status }}" data-nama="{{ strtolower($b->nama) }}" data-status="{{ $b->status }}">
                <div class="ks-tile-top">
                  <span class="ks-thumb {{ $fotoUrl ? '' : 'is-blank' }}">
                    @if ($fotoUrl)<img src="{{ $fotoUrl }}" alt="{{ $b->nama }}" loading="lazy">@else<i class="bi {{ $b->ikon }}"></i>@endif
                  </span>
                  <div class="ks-info">
                    <b>{{ $b->nama }}</b>
                    <small>{{ $b->detail }}</small>
                  </div>
                  <span class="ks-badge {{ $b->status }}">{{ ucfirst($b->status) }}</span>
                </div>
                <div class="ks-tile-bot">
                  <div class="ks-qty"><b>{{ $n($b->jumlah) }}</b> <span>{{ $b->satuan }}</span></div>
                  <div class="ks-bar {{ $b->status }}"><span style="width: {{ $b->persen }}%"></span></div>
                </div>
              </div>
            @endforeach
          </div>
        </section>
      @endforeach
      <div class="ks-nohit" id="ks-nohit">Tidak ada yang cocok.</div>
      </div>
      </div>
    </div>

    {{-- ===== KANAN: riwayat singkat (scroll di dalam kotak sendiri) ===== --}}
  </div>
  </div>

  {{-- ===== RIWAYAT: semua pergerakan stok, per jenis ===== --}}
  <div id="pane-riwayat" data-pane-body="riwayat" hidden>
    @php
      $fmtR = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
      $tabRiwayat = ['produk' => 'Produk Siap Jual', 'filling' => 'Filling', 'saus' => 'Saus Celup', 'bumbu' => 'Bumbu Tabur', 'kemasan' => 'Kemasan', 'manual' => 'Minyak & Gas'];
      $dataRiwayat = [];
      foreach ($riwayat as $r) {
        $dataRiwayat['produk'][] = ['jenis' => $r->jenis, 'nama' => $r->nama_ukuran, 'ket' => $r->keterangan, 'rinci' => null, 'tgl' => $r->tanggal,
          'jml' => $r->pcs . ' pcs', 'sisa' => $r->stok_sesudah . ' pcs'];
      }
      foreach ($riwayatBahan as $kunci => $baris) {
        foreach ($baris as $r) {
          $dataRiwayat[$kunci][] = ['jenis' => $r->jenis, 'nama' => $r->nama_bahan, 'ket' => $r->keterangan,
            'rinci' => $r->rincian ? \Illuminate\Support\Str::before($r->rincian, ' · ') : null, 'tgl' => $r->tanggal,
            'jml' => $fmtR($r->jumlah) . ' ' . $r->satuan, 'sisa' => $fmtR($r->stok_sesudah) . ' ' . $r->satuan];
        }
      }
    @endphp

    <div class="ks-layout solo">
    <div class="card ks-main rw-card">
      <div class="card-banner"><h2><i class="bi bi-clock-history me-2"></i>Riwayat Stok</h2></div>
      <div class="rw-body">
        <div class="rw-tabs" id="rw-tabs" role="tablist">
          @foreach ($tabRiwayat as $kunci => $label)
            <button type="button" class="rw-tab {{ $loop->first ? 'on' : '' }}" data-rtab="{{ $kunci }}">{{ $label }} <b>{{ count($dataRiwayat[$kunci] ?? []) }}</b></button>
          @endforeach
        </div>

        <div class="rw-tools">
          <div class="mc-filter rw-filter" id="rw-filter" role="group">
            <button type="button" class="on" data-jenis="semua">Semua</button>
            <button type="button" data-jenis="masuk">Masuk</button>
            <button type="button" data-jenis="keluar">Keluar</button>
          </div>
          <div class="page-search-wrapper" style="max-width:260px;">
            <input type="text" class="page-search-input" id="rw-cari" placeholder="Cari nama atau keterangan...">
            <i class="bi bi-search"></i>
          </div>
        </div>

        @foreach ($tabRiwayat as $kunci => $label)
          <div class="rw-pane" data-rpane="{{ $kunci }}" @if (! $loop->first) hidden @endif>
            <div class="rw-scroll">
              <table class="rw-table">
                <thead><tr><th>Tanggal</th><th>{{ $kunci === 'produk' ? 'Ukuran' : 'Bahan' }}</th><th>Keterangan</th><th class="t-r">Jumlah</th><th class="t-r">Sisa</th></tr></thead>
                <tbody>
                  @foreach ($dataRiwayat[$kunci] ?? [] as $r)
                    <tr data-jenis="{{ $r['jenis'] }}" data-cari="{{ strtolower($r['nama'] . ' ' . $r['ket'] . ' ' . $r['rinci']) }}">
                      <td class="rw-tgl">{{ \Carbon\Carbon::parse($r['tgl'])->translatedFormat('d M Y') }}</td>
                      <td><b>{{ $r['nama'] }}</b></td>
                      <td>{{ $r['ket'] }}@if ($r['rinci'])<small>{{ $r['rinci'] }}</small>@endif</td>
                      <td class="t-r"><span class="rw-jml {{ $r['jenis'] }}">{{ $r['jenis'] === 'masuk' ? '+' : '-' }}{{ $r['jml'] }}</span></td>
                      <td class="t-r rw-sisa">{{ $r['sisa'] }}</td>
                    </tr>
                  @endforeach
                  <tr class="rw-kosong" @if (! empty($dataRiwayat[$kunci])) hidden @endif><td colspan="5">Belum ada riwayat {{ strtolower($label) }}.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        @endforeach
      </div>
    </div>
    </div>
  </div>

  <div id="pane-catat" data-pane-body="catat" hidden>
  {{-- ===== MINYAK & GAS (manual): catat pemakaian, minta ke admin, konfirmasi diterima ===== --}}
  <div class="card mg-card">
    <div class="card-banner"><h2><i class="bi bi-fuel-pump-fill me-2"></i>Pencatatan Minyak &amp; Gas</h2></div>
    <div class="mg-body">

      @if ($errors->any())
        <div class="mg-error"><i class="bi bi-x-octagon-fill"></i>
          <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
      @endif
      @if (session('peringatan'))
        <div class="mg-warn"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ session('peringatan') }}</span></div>
      @endif

      @php $aktifPer = $permintaanAktif->keyBy('peran'); @endphp

      <div class="mg2-grid">
        @foreach (['minyak' => ['Minyak goreng', 'bi-droplet-half', 'jerigen'], 'gas' => ['Gas LPG', 'bi-fire', 'tabung']] as $peran => [$namaMg, $ikMg, $satDef])
          @php
            $mg = $manual[$peran] ?? null;
            $stokMg = $mg ? (float) $mg->jumlah : 0;
            $kosong = $stokMg <= 0;
            $aktif = $aktifPer[$peran] ?? null;
            $satMg = $mg->satuan ?? $satDef;
            $statusMg = $kosong ? 'habis' : ($mg->status ?? 'aman');
          @endphp
          <section class="mg2 {{ $statusMg }}">
            <div class="mg2-head">
              <span class="mg2-ic"><i class="bi {{ $ikMg }}"></i></span>
              <h3>{{ $namaMg }}</h3>
              <span class="mg2-chip">{{ ucfirst($statusMg) }}</span>
            </div>

            <div class="mg2-stok"><b>{{ $n($stokMg) }}</b> <span>{{ $satMg }}</span><small>stok yang kamu pegang</small></div>

            {{-- 1. Catat pemakaian (hanya kalau masih ada stok) --}}
            @if (! $kosong)
              <form class="mg2-pakai" method="POST" action="{{ route('pos.pemakaian.store') }}">
                @csrf
                <label>Terpakai hari ini</label>
                <div class="mg2-baris">
                  <div class="mg2-step">
                    <button type="button" data-step="-1" aria-label="Kurangi">&minus;</button>
                    <input type="number" name="{{ $peran }}" value="0" min="0" max="{{ (int) $stokMg }}" step="1" inputmode="numeric">
                    <button type="button" data-step="1" aria-label="Tambah">+</button>
                  </div>
                  <button type="submit" class="mg2-btn" disabled>Catat</button>
                </div>
              </form>
            @endif

            {{-- 2. Minta ke admin: hanya kalau stok habis --}}
            @if ($aktif && $aktif->status === 'diminta')
              <div class="mg2-status">
                <span><i class="bi bi-hourglass-split"></i> Menunggu admin mengirim {{ $aktif->jumlah_diminta ?: $porsi[$peran] }} {{ $satMg }}</span>
                <form method="POST" action="{{ route('pos.permintaan.batal', $aktif->id) }}" onsubmit="return confirm('Batalkan permintaan ini?')">@csrf <button type="submit" class="mg2-link">Batalkan</button></form>
              </div>
            @elseif ($aktif)
              <form class="mg2-terima" method="POST" action="{{ route('pos.permintaan.terima', $aktif->id) }}">
                @csrf
                <span><i class="bi bi-truck"></i> Admin mengirim {{ $aktif->jumlah_dikirim }} {{ $satMg }}</span>
                <div class="mg2-baris">
                  <div class="mg2-step">
                    <button type="button" data-step="-1" aria-label="Kurangi">&minus;</button>
                    <input type="number" name="diterima" value="{{ $aktif->jumlah_dikirim }}" min="1" max="{{ $aktif->jumlah_dikirim }}" step="1" inputmode="numeric" required>
                    <button type="button" data-step="1" aria-label="Tambah">+</button>
                  </div>
                  <button type="submit" class="mg2-btn hijau"><i class="bi bi-check2-circle"></i> Sudah Diterima</button>
                </div>
              </form>
            @elseif ($kosong)
              <form method="POST" action="{{ route('pos.permintaan.store') }}">
                @csrf
                <input type="hidden" name="bahan[]" value="{{ $peran }}">
                <button type="submit" class="mg2-minta"><i class="bi bi-send-fill"></i> Minta {{ $porsi[$peran] }} {{ $satMg }} ke Admin</button>
              </form>
            @endif
          </section>
        @endforeach
      </div>
    </div>
  </div>

  </div>
@endsection

@push('scripts')
  <script src="{{ asset('assets_user/kasir/stok/stok.js') }}?v={{ @filemtime(public_path('assets_user/kasir/stok/stok.js')) }}"></script>

  <script>
    // Dua tombol: Stok | Catat Minyak & Gas
    (function () {
      const tombol = document.querySelectorAll('#ks-switch .ks-sw');
      const pane = document.querySelectorAll('[data-pane-body]');
      const pilih = (nama) => {
        tombol.forEach((b) => b.classList.toggle('on', b.dataset.pane === nama));
        pane.forEach((p) => { p.hidden = p.dataset.paneBody !== nama; });
        try { sessionStorage.setItem('ks-pane', nama); } catch (e) {}
      };
      tombol.forEach((b) => b.addEventListener('click', () => pilih(b.dataset.pane)));
      let awal = 'stok';
      try { awal = sessionStorage.getItem('ks-pane') || 'stok'; } catch (e) {}
      if (@json((bool) $bukaCatat)) awal = 'catat';
      pilih(awal);
    })();
  </script>

  <script>
    // Halaman Riwayat: tab jenis + filter masuk/keluar + cari
    (function () {
      const tab = document.querySelectorAll('#rw-tabs .rw-tab');
      const chip = document.querySelectorAll('#rw-filter button');
      const cari = document.getElementById('rw-cari');
      let aktif = 'produk', jenis = 'semua';
      const terapkan = () => {
        const q = (cari.value || '').trim().toLowerCase();
        document.querySelectorAll('.rw-pane').forEach((pn) => {
          pn.hidden = pn.dataset.rpane !== aktif;
          if (pn.hidden) return;
          let tampil = 0, total = 0;
          pn.querySelectorAll('tbody tr:not(.rw-kosong)').forEach((tr) => {
            total++;
            const cocok = (jenis === 'semua' || tr.dataset.jenis === jenis) && (!q || tr.dataset.cari.includes(q));
            tr.hidden = !cocok;
            if (cocok) tampil++;
          });
          const kosong = pn.querySelector('.rw-kosong');
          if (kosong) {
            kosong.hidden = tampil > 0;
            if (total > 0 && tampil === 0) kosong.firstElementChild.textContent = 'Tidak ada data yang cocok.';
          }
        });
      };
      tab.forEach((t) => t.addEventListener('click', () => { aktif = t.dataset.rtab; tab.forEach((x) => x.classList.toggle('on', x === t)); terapkan(); }));
      chip.forEach((c) => c.addEventListener('click', () => { jenis = c.dataset.jenis; chip.forEach((x) => x.classList.toggle('on', x === c)); terapkan(); }));
      cari.addEventListener('input', terapkan);
    })();
  </script>

  <script>
    // Tombol - / + untuk jumlah (pemakaian & penerimaan minyak/gas)
    document.querySelectorAll('.mg2-step').forEach((box) => {
      const inp = box.querySelector('input');
      const form = box.closest('form');
      const btn = form ? form.querySelector('.mg2-btn') : null;
      const segar = () => { if (btn && form.classList.contains('mg2-pakai')) btn.disabled = !(parseInt(inp.value, 10) > 0); };
      box.querySelectorAll('[data-step]').forEach((b) => b.addEventListener('click', () => {
        const min = parseInt(inp.min || '0', 10), max = parseInt(inp.max || '9999', 10);
        const v = (parseInt(inp.value, 10) || 0) + parseInt(b.dataset.step, 10);
        inp.value = Math.max(min, Math.min(max, v));
        segar();
      }));
      inp.addEventListener('input', segar);
      segar();
    });
  </script>
@endpush

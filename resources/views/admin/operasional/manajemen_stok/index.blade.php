@extends('layouts.admin')

@section('title', 'Manajemen Stok - Chicken Crunchy Roll')
@section('breadcrumb', 'Manajemen Stok')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/data_master/master.css') }}">
  <link rel="stylesheet" href="{{ asset('assets_user/admin/operasional/manajemen_stok.css') }}?v={{ @filemtime(public_path('assets_user/admin/operasional/manajemen_stok.css')) }}">
  {{-- Supaya popup selalu bisa di-scroll (tidak bergantung pada cache CSS) --}}
  <style>
    .kirim-opsi { position: relative; display: flex; flex-direction: column; align-items: center; gap: .15rem; text-align: center; padding: .9rem .6rem; border: 2px solid #e9ddd0; border-radius: 14px; background: #fff; cursor: pointer; height: 100%; transition: border-color .15s, background .15s; }
    .kirim-opsi .ko-ic { width: 40px; height: 40px; border-radius: 12px; background: #f6efe8; display: grid; place-items: center; font-size: 1.2rem; color: #5a4339; margin-bottom: .2rem; }
    .kirim-opsi .ko-judul { font-size: .82rem; color: #8a7468; }
    .kirim-opsi .ko-jml { font-size: 1.15rem; color: #2b1511; }
    .kirim-opsi small { font-size: .74rem; color: #8a7468; }
    .kirim-opsi .ko-cek { position: absolute; top: .5rem; right: .55rem; color: #7A1E1E; opacity: 0; transition: opacity .15s; }
    .btn-check:checked + .kirim-opsi { border-color: #7A1E1E; background: #fff6f4; }
    .btn-check:checked + .kirim-opsi .ko-ic { background: #7A1E1E; color: #fff; }
    .btn-check:checked + .kirim-opsi .ko-cek { opacity: 1; }
    .btn-check:focus-visible + .kirim-opsi { outline: 3px solid rgba(122,30,30,.25); }
    #modalStok .modal-dialog, #modalPaket .modal-dialog, #modalKirim .modal-dialog { max-height: calc(100vh - 2rem); height: auto; }
    #modalStok .modal-content, #modalPaket .modal-content, #modalKirim .modal-content { display: flex; flex-direction: column; max-height: calc(100vh - 2rem); overflow: hidden; }
    #modalStok .modal-content > form, #modalPaket .modal-content > form, #modalKirim .modal-content > form { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
  </style>
@endpush

@section('content')

  @php
    // Modal dibuka otomatis kalau: ada error validasi (old) atau datang dari menu (?aksi=masuk|keluar)
    $jenisAwal = old('_modal', request('aksi'));
    $svc = app(\App\Services\StokOtomatis\StokOtomatisService::class);
    $paketTerbuka = old('_modal') === 'paket' || request('aksi') === 'paket';
    $kirimTerbuka = old('_modal') === 'kirim' || request('aksi') === 'kirim';
    // Isi awal form Kirim Minyak & Gas: dari lonceng (?kasir=ID) pakai permintaan kasir itu, selain itu isi standar
    $kasirKirim = old('kasir_id', request('kasir'));
    $awalKirim  = $permintaanKasir[(int) $kasirKirim] ?? ['minyak' => $kirimStandar['minyak'], 'gas' => $kirimStandar['gas'], 'catatan' => null];
    $jenisAwal = in_array($jenisAwal, ['masuk', 'keluar'], true) ? $jenisAwal : null;

    $labelPeran = ['ayam' => 'Ayam', 'tepung_premix_basah' => 'Tepung Premix Basah', 'tepung_roti' => 'Tepung Roti', 'minyak' => 'Minyak',
                   'gas' => 'Gas LPG', 'kemasan' => 'Kemasan', 'filling' => 'Filling', 'saus' => 'Saus Celup', 'bumbu' => 'Bumbu Tabur'];
    $ikonPeran  = ['ayam' => 'bi-egg-fried', 'minyak' => 'bi-droplet-half', 'gas' => 'bi-fire', 'kemasan' => 'bi-box2'];
    // Pengelompokan tab: bahan utama, filling, saus, bumbu tabur, bahan operasional
    // Peran dipakai kalau ada; kalau belum, kelompok ditebak dari nama bahan (mis. "Saus Mayo" -> Saus Celup)
    $grupDari = function ($b) {
      $nama = strtolower($b->nama_bahan);
      return match (true) {
        $b->peran === 'filling', str_contains($nama, 'filling') => 'filling',
        $b->peran === 'saus', str_contains($nama, 'saus') => 'saus',
        $b->peran === 'bumbu', str_contains($nama, 'bumbu'), str_contains($nama, 'tabur') => 'bumbu',
        in_array($b->peran, ['minyak', 'gas', 'kemasan'], true),
        str_contains($nama, 'minyak'), str_contains($nama, 'gas'), str_contains($nama, 'lpg'),
        str_contains($nama, 'kemasan'), str_contains($nama, 'dus'), str_contains($nama, 'box') => 'operasional',
        default => 'utama',
      };
    };
    $tabGrup = [
      'utama'       => ['Bahan Utama', 'bi-egg-fried'],
      'filling'     => ['Filling', 'bi-layers-fill'],
      'saus'        => ['Saus Celup', 'bi-droplet-fill'],
      'bumbu'       => ['Bumbu Tabur', 'bi-stars'],
      'operasional' => ['Bahan Operasional', 'bi-fire'],
    ];
    $jumlahGrup = $bahanBaku->groupBy(fn ($b) => $grupDari($b))->map->count();
    $bahanUrut  = $bahanBaku->sortBy(fn ($b) => array_search($grupDari($b), array_keys($tabGrup)))->values();
    $bermasalah = $bahanBaku->filter(fn ($b) => in_array($b->status_stok, ['menipis', 'habis'], true));

    $totalBahan   = $bahanBaku->count();
    $jumlahMenipis = $bahanBaku->filter(fn ($b) => $b->status_stok === 'menipis')->count();
    $jumlahHabis   = $bahanBaku->filter(fn ($b) => ! in_array($b->status_stok, ['aman', 'menipis'], true))->count();
  @endphp

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-box-seam-fill"></i></div>
    <div>
      <h1 class="page-title">Manajemen Stok</h1>
      <p class="page-subtitle">Persediaan bahan di dapur pusat: berapa banyak tiap bahan, yang masuk dari supplier, dan yang diberikan ke kasir lewat paket.</p>
    </div>
  </div>

  {{-- ===== PERINGATAN STOK ===== --}}
  @if ($bermasalah->isNotEmpty())
    <div class="ms-alert">
      <div class="ms-alert-ic"><i class="bi bi-bell-fill"></i></div>
      <div>
        <div class="ms-alert-t">{{ $bermasalah->count() }} bahan perlu perhatian</div>
        <div class="ms-alert-d">
          @foreach ($bermasalah as $b)
            <span class="ms-alert-chip {{ $b->status_stok }}">{{ $b->nama_bahan }} · {{ $b->status_stok === 'habis' ? 'habis' : 'menipis' }}</span>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- ===== RINGKASAN ===== --}}
  <div class="ms-stats">
    <div class="ms-stat">
      <div class="ms-stat-icon"><i class="bi bi-boxes"></i></div>
      <div><span class="ms-stat-label">Jenis bahan baku</span><span class="ms-stat-value">{{ $totalBahan }}</span></div>
    </div>
    <div class="ms-stat is-warn {{ $jumlahMenipis > 0 ? 'is-alert' : '' }}">
      <div class="ms-stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
      <div><span class="ms-stat-label">Stok menipis</span><span class="ms-stat-value">{{ $jumlahMenipis }}</span></div>
    </div>
    <div class="ms-stat is-bad {{ $jumlahHabis > 0 ? 'is-alert' : '' }}">
      <div class="ms-stat-icon"><i class="bi bi-x-octagon"></i></div>
      <div><span class="ms-stat-label">Stok habis</span><span class="ms-stat-value">{{ $jumlahHabis }}</span></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-banner">
      <h2>
        Daftar Bahan Baku
        <span style="margin-left:.6rem;font-size:.78rem;font-weight:800;background:rgba(255,255,255,.2);padding:.2rem .75rem;border-radius:50rem;vertical-align:middle;">{{ $totalBahan }} bahan</span>
      </h2>
    </div>

    {{-- ===== TAB KELOMPOK BAHAN ===== --}}
    <div class="ms-tabs" role="tablist" aria-label="Kelompok bahan">
      @foreach ($tabGrup as $kode => [$namaTab, $ikonTab])
        <button type="button" class="{{ $loop->first ? 'on' : '' }}" data-grup="{{ $kode }}" role="tab">
          <i class="bi {{ $ikonTab }}"></i><span>{{ $namaTab }}</span><b>{{ $jumlahGrup[$kode] ?? 0 }}</b>
        </button>
      @endforeach
    </div>

    {{-- ===== AKSI + CARI ===== --}}
    <div class="ms-toolbar">
      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn-custom" data-bs-toggle="modal" data-bs-target="#modalStok" data-jenis="masuk"
          style="background:#16a34a;border:2px solid #16a34a;color:#fff;font-weight:700;">
          <i class="bi bi-box-arrow-in-down"></i> Stok Masuk
        </button>
        <button type="button" class="btn-custom" data-bs-toggle="modal" data-bs-target="#modalPaket"
          style="background:#2563eb;border:2px solid #2563eb;color:#fff;font-weight:700;">
          <i class="bi bi-send-fill"></i> Beri Paket ke Kasir
        </button>
        <button type="button" class="btn-custom" data-bs-toggle="modal" data-bs-target="#modalKirim"
          style="background:#7c3aed;border:2px solid #7c3aed;color:#fff;font-weight:700;">
          <i class="bi bi-fuel-pump-fill"></i> Kirim Minyak &amp; Gas
        </button>
        <button type="button" class="btn-custom" data-bs-toggle="modal" data-bs-target="#modalStok" data-jenis="keluar"
          style="background:#F59E0B;border:2px solid #F59E0B;color:#3b2300;font-weight:700;">
          <i class="bi bi-box-arrow-up"></i> Stok Keluar Lain
        </button>
        <a href="{{ route('bahan_baku.create') }}" class="btn-custom"
          style="background:#FFFFFF;border:2px solid var(--brand-forest-medium, #9c1d1d);color:var(--brand-forest-medium, #9c1d1d);font-weight:700;">
          <i class="bi bi-plus-lg"></i> Tambah Bahan Baku
        </a>
      </div>
      <div class="page-search-wrapper" style="max-width:280px;">
        <input type="text" class="page-search-input" id="search-bahan" placeholder="Cari bahan baku...">
        <i class="bi bi-search"></i>
      </div>
    </div>

    {{-- ===== FILTER STATUS ===== --}}
    <div class="mc-panel-tools" style="margin-bottom:.9rem;">
      <div class="mc-filter" role="group" aria-label="Filter status stok">
        <button type="button" class="on" data-filter="semua">Semua<b>{{ $totalBahan }}</b></button>
        <button type="button" data-filter="aman">Aman<b>{{ $totalBahan - $jumlahMenipis - $jumlahHabis }}</b></button>
        <button type="button" data-filter="menipis">Menipis<b>{{ $jumlahMenipis }}</b></button>
        <button type="button" data-filter="habis">Habis<b>{{ $jumlahHabis }}</b></button>
      </div>
    </div>

    {{-- ===== TABEL STOK DAPUR ===== --}}
    <div class="ms-table-wrap">
      <table class="ms-table ms-gudang">
        <thead>
          <tr>
            <th style="width:60px;">Foto</th>
            <th>Nama Bahan</th>
            <th class="text-end">Stok Dapur</th>
            <th class="text-end">Stok Minimum</th>
            <th class="text-center">Status</th>
            <th class="text-center" style="width:190px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($bahanUrut as $item)
            @php
              $st      = in_array($item->status_stok, ['aman', 'menipis'], true) ? $item->status_stok : 'habis';
              $fotoUrl = $item->foto_url;
            @endphp
            <tr class="ms-row" data-nama="{{ strtolower($item->nama_bahan) }}" data-status="{{ $st }}" data-grup="{{ $grupDari($item) }}">
              <td>
                <span class="ms-thumb {{ $fotoUrl ? '' : 'is-blank' }}">
                  @if ($fotoUrl)<img src="{{ $fotoUrl }}" alt="{{ $item->nama_bahan }}" loading="lazy">@else<i class="bi {{ $ikonPeran[$item->peran] ?? 'bi-box-seam' }}"></i>@endif
                </span>
              </td>
              <td><b>{{ $item->nama_bahan }}</b></td>
              <td class="text-end"><b class="ms-qty">{{ $item->stok_format }}</b> <span class="ms-unit">{{ $item->satuan }}</span></td>
              <td class="text-end text-muted-green">{{ $item->stok_minimum_format }} {{ $item->satuan }}</td>
              <td class="text-center"><span class="ms-status ms-status-{{ $st }}">{{ ucfirst($st) }}</span></td>
              <td>
                <div class="ms-actions">
                  <button type="button" class="ms-act in" title="Stok masuk" data-bs-toggle="modal" data-bs-target="#modalStok"
                    data-jenis="masuk" data-bahan="{{ $item->id }}"><i class="bi bi-box-arrow-in-down"></i></button>
                  <button type="button" class="ms-act out" title="Stok keluar" data-bs-toggle="modal" data-bs-target="#modalStok"
                    data-jenis="keluar" data-bahan="{{ $item->id }}"><i class="bi bi-box-arrow-up"></i></button>
                  <a href="{{ route('bahan_baku.edit', $item) }}" class="ms-act edit" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                  <form action="{{ route('bahan_baku.destroy', $item) }}" method="POST"
                    onsubmit="return confirm('Yakin mau hapus {{ e($item->nama_bahan) }}? Semua riwayat stoknya juga ikut terhapus.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ms-act del" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
          @if ($bahanBaku->isEmpty())
            <tr><td colspan="6" class="text-center text-muted-green p-4">Belum ada bahan baku. Klik "Tambah Bahan Baku" untuk mulai mencatat stok.</td></tr>
          @endif
          <tr class="ms-nohit"><td colspan="6" class="text-center text-muted-green p-4">Tidak ada bahan yang cocok dengan pencarian atau filter.</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  {{-- Stok yang dipegang kasir sekarang ada di menu terpisah: Operasional > Stok Kasir --}}

  {{-- ============ MODAL CATAT STOK (dipakai untuk masuk & keluar) ============ --}}
  <div class="modal fade" id="modalStok" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header-stok {{ $jenisAwal ?? 'masuk' }}" id="stok-header">
          <h5><i class="bi bi-box-arrow-in-down me-2" id="stok-icon"></i><span id="stok-judul">Catat Stok Masuk</span></h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <form id="form-stok" method="POST" class="p-4"
          action="{{ route($jenisAwal === 'keluar' ? 'stok.keluar' : 'stok.masuk') }}"
          data-action-masuk="{{ route('stok.masuk') }}"
          data-action-keluar="{{ route('stok.keluar') }}">
          @csrf
          <input type="hidden" name="_modal" id="stok-jenis" value="{{ $jenisAwal ?? 'masuk' }}">

          {{-- Bahan baku --}}
          <div class="mb-3">
            <label class="form-label">Bahan Baku</label>
            <select name="bahan_baku_id" id="stok-bahan" class="form-select @error('bahan_baku_id') is-invalid @enderror" required>
              <option value="">— Pilih bahan baku —</option>
              @foreach ($bahanBaku as $b)
                <option value="{{ $b->id }}" data-satuan="{{ $b->satuan }}" data-stok="{{ $b->stok_format }}"
                  @selected(old('bahan_baku_id') == $b->id)>{{ $b->nama_bahan }}</option>
              @endforeach
            </select>
            @error('bahan_baku_id') <div class="invalid-feedback js-error">{{ $message }}</div> @enderror
            <div class="stok-info mt-2 d-none" id="stok-info">
              <i class="bi bi-info-circle"></i>
              <span><span id="stok-info-label">Stok sekarang</span>: <strong id="stok-info-nilai"></strong></span>
            </div>
          </div>

          {{-- Jumlah + tanggal --}}
          <div class="row g-3 mb-3">
            <div class="col-7">
              <label class="form-label" id="stok-label-jumlah">Jumlah Masuk</label>
              <div class="input-group">
                <input type="number" name="jumlah" step="1" min="1" value="{{ old('jumlah') }}"
                  class="form-control @error('jumlah') is-invalid @enderror" placeholder="0" required>
                <span class="input-group-text" id="stok-satuan">satuan</span>
              </div>
              @error('jumlah') <div class="text-danger mt-1 js-error" style="font-size:.85rem;">{{ $message }}</div> @enderror
            </div>
            <div class="col-5">
              <label class="form-label">Tanggal</label>
              <input type="date" name="tanggal" id="stok-tanggal" value="{{ old('tanggal') }}"
                class="form-control @error('tanggal') is-invalid @enderror" required>
            </div>
          </div>

          {{-- Khusus stok masuk --}}
          <div class="mb-3" data-only="masuk">
            <label class="form-label">Dari (langganan / pemasok) <span class="text-muted-green fw-normal">— opsional</span></label>
            <input type="text" name="supplier" maxlength="100" value="{{ old('supplier') }}"
              class="form-control" placeholder="Contoh: Pak Budi / Toko Sumber Rejeki">
          </div>

          <div class="mb-4">
            <label class="form-label">Keterangan <span class="text-muted-green fw-normal">— opsional</span></label>
            <input type="text" name="keterangan" maxlength="255" value="{{ old('keterangan') }}"
              class="form-control" id="stok-keterangan" placeholder="Contoh: Belanja mingguan">
          </div>

          <div class="d-flex gap-2">
            <button type="button" class="btn-custom flex-fill justify-content-center" data-bs-dismiss="modal"
              style="background:#FFFFFF;border:2px solid var(--bs-danger, #7A1E1E);color:var(--bs-danger, #7A1E1E);font-weight:700;">
              Batal
            </button>
            <button type="submit" class="btn-custom btn-custom-primary flex-fill justify-content-center">
              <i class="bi bi-check-lg"></i> Simpan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- ============ MODAL BERI PAKET KE KASIR ============ --}}
  <div class="modal fade" id="modalPaket" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header-stok paket">
          <h5><i class="bi bi-send-fill me-2"></i>Beri Paket ke Kasir</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <form id="form-paket" method="POST" action="{{ route('stok.paket') }}" class="p-4">
          @csrf
          <input type="hidden" name="_modal" value="paket">

          @if ($errors->has('paket') || $errors->has('kasir_id'))
            <div class="pk-error">
              <i class="bi bi-x-octagon-fill"></i>
              <ul>
                @foreach ($errors->get('paket') as $e)
                  @foreach ((array) $e as $m)<li>{{ $m }}</li>@endforeach
                @endforeach
                @foreach ($errors->get('kasir_id') as $m)<li>{{ $m }}</li>@endforeach
              </ul>
            </div>
          @endif

          <div class="row g-3 mb-3">
            <div class="col-md-7">
              <label class="form-label">Kasir penerima</label>
              <select name="kasir_id" class="form-select" required>
                <option value="">— Pilih kasir —</option>
                @foreach ($kasirList as $k)
                  <option value="{{ $k->id }}" @selected(old('kasir_id') == $k->id)>{{ $k->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label">Tanggal</label>
              <input type="date" name="tanggal" id="paket-tanggal" value="{{ old('tanggal') }}" class="form-control" required>
            </div>
          </div>

          <div class="pk-bar">
            <div>
              <b>Isi paket</b>
              <small>1 paket standar = {{ rtrim(rtrim(number_format($set['paket_kg_per_ukuran'] * $ukuranPaket->count(), 2, '.', ''), '0'), '.') }} kg ayam. Mau 2 paket? Klik tombolnya 2 kali, atau ubah angkanya.</small>
            </div>
            <div class="pk-bar-btn">
              <button type="button" class="btn-custom pk-btn-std" id="paket-std"><i class="bi bi-magic"></i> Paket standar</button>
              <button type="button" class="btn-custom pk-btn-std" id="paket-tambah"><i class="bi bi-plus-lg"></i> Tambah 1 paket</button>
              <button type="button" class="btn-custom pk-btn-reset" id="paket-reset">Kosongkan</button>
            </div>
          </div>

          {{-- 1. Ayam per ukuran --}}
          <section class="pk-sec">
            <h6><span>1</span> Ayam per ukuran <small>isi kg ayam tiap ukuran, tepung diisi di bagian berikutnya</small></h6>
            <div class="pk-rows">
              @foreach ($ukuranPaket as $u)
                @php $st = $standarPaket[$u->id] ?? null; @endphp
                <div class="pk-row" data-ukuran="{{ $u->id }}">
                  <span class="pk-foto">@if ($u->foto)<img src="{{ asset('storage/' . $u->foto) }}" alt="">@else<i class="bi bi-egg-fried"></i>@endif</span>
                  <div class="pk-nama"><b>{{ $u->nama_ukuran }}</b>
                    <small>1 kg = {{ $st ? rtrim(rtrim(number_format($st['hasil_pcs_per_kg'], 2, '.', ''), '0'), '.') : '?' }} pcs</small></div>
                  <div class="input-group pk-input">
                    <input type="number" step="1" min="0" name="kg[{{ $u->id }}]" value="{{ old("kg.$u->id") }}" class="form-control" placeholder="0">
                    <span class="input-group-text">kg</span>
                  </div>
                  <span class="pk-hasil" data-hasil="{{ $u->id }}">= 0 pcs</span>
                </div>
              @endforeach
              @if ($ukuranPaket->isEmpty())<div class="text-muted-green">Belum ada ukuran aktif.</div>@endif
            </div>
            <div class="pk-total" id="paket-total-ayam">Total: 0 kg ayam · 0 pcs</div>
          </section>

          {{-- 2. Tepung premix basah (botol 1 liter) & tepung roti (bungkus 1 kg) --}}
          <section class="pk-sec">
            <h6><span>2</span> Tepung <small>premix basah per botol (1 botol = 1 liter), tepung roti per bungkus (1 bungkus = 1 kg), mengurangi stok dapur</small></h6>
            <div class="pk-rows">
              @foreach ([['premix_basah', 'Tepung premix basah', $premixBasah ?? null, 'botol', 'liter'], ['tepung_roti', 'Tepung roti', $tepungRoti ?? null, 'bungkus', 'kg']] as [$namaInput, $judulTepung, $bhn, $satTepung, $satIsi])
                <div class="pk-row" data-tepung="{{ $namaInput }}" data-isi="1" data-unit="{{ $satIsi }}">
                  <span class="pk-foto"><i class="bi bi-basket"></i></span>
                  <div class="pk-nama"><b>{{ $judulTepung }}</b>
                    <small>tersedia: {{ \App\Models\BahanBaku::formatAngka($bhn->stok_saat_ini ?? 0) }} {{ $bhn->satuan ?? $satTepung }}</small></div>
                  <div class="input-group pk-input">
                    <input type="number" step="1" min="0" name="{{ $namaInput }}" value="{{ old($namaInput) }}" class="form-control" placeholder="0">
                    <span class="input-group-text">{{ $satTepung }}</span>
                  </div>
                  <span class="pk-hasil" data-hasil-tepung="{{ $namaInput }}">= 0 {{ $satIsi }}</span>
                </div>
              @endforeach
            </div>
          </section>

          {{-- 2. Filling, saus, bumbu --}}
          @foreach (['filling' => ['3', 'Filling', 'bi-egg-fried'], 'saus' => ['4', 'Saus celup', 'bi-droplet-fill'], 'bumbu' => ['5', 'Bumbu tabur', 'bi-stars']] as $peran => [$no, $judul, $ik])
            <section class="pk-sec">
              <h6><span>{{ $no }}</span> {{ $judul }} <small>jumlah bungkus, dipakai per porsi (kali pakai)</small></h6>
              <div class="pk-rows">
                @forelse ($bahanPaket[$peran] ?? [] as $b)
                  <div class="pk-row" data-bahan="{{ $b->id }}" data-peran="{{ $peran }}" data-kap="{{ $b->kap }}">
                    <span class="pk-foto">@if ($b->foto)<img src="{{ asset('storage/' . $b->foto) }}" alt="">@else<i class="bi {{ $ik }}"></i>@endif</span>
                    <div class="pk-nama"><b>{{ $b->nama_bahan }}</b>
                      <small>tersedia: {{ \App\Models\BahanBaku::formatAngka($b->stok_saat_ini) }} bungkus</small></div>
                    <div class="input-group pk-input">
                      <input type="number" step="1" min="0" name="bungkus[{{ $b->id }}]" value="{{ old("bungkus.$b->id") }}" class="form-control" placeholder="0">
                      <span class="input-group-text">bungkus</span>
                    </div>
                    <span class="pk-hasil" data-hasil-bahan="{{ $b->id }}">= 0 kali pakai</span>
                  </div>
                @empty
                  <div class="text-muted-green" style="font-size:.85rem;">Belum ada {{ strtolower($judul) }} yang terhubung ke bahan baku.</div>
                @endforelse
              </div>
            </section>
          @endforeach

          {{-- 5. Kemasan (minyak & gas lewat tombol Kirim Minyak & Gas) --}}
          <section class="pk-sec">
            <h6><span>6</span> Kemasan per ukuran <small>minyak &amp; gas lewat tombol "Kirim Minyak &amp; Gas"</small></h6>
            <div class="pk-rows">
              @foreach ($ukuranPaket as $u)
                <div class="pk-row" data-kemasan-ukuran="{{ $u->id }}">
                  <span class="pk-foto">@if ($u->foto)<img src="{{ asset('storage/' . $u->foto) }}" alt="">@else<i class="bi bi-box2"></i>@endif</span>
                  <div class="pk-nama"><b>Kemasan {{ $u->nama_ukuran }}</b>
                    <small>tersedia: {{ \App\Models\BahanBaku::formatAngka($stokKemasan[$u->id] ?? 0) }} pcs</small></div>
                  <div class="input-group pk-input">
                    <input type="number" step="1" min="0" name="kemasan[{{ $u->id }}]" value="{{ old("kemasan.$u->id") }}" class="form-control" placeholder="0">
                    <span class="input-group-text">pcs</span>
                  </div>
                  <span class="pk-hasil"></span>
                </div>
              @endforeach
            </div>
          </section>

          <div class="mb-4 mt-3">
            <label class="form-label">Catatan <span class="text-muted-green fw-normal">— opsional</span></label>
            <input type="text" name="catatan" maxlength="150" value="{{ old('catatan') }}" class="form-control" placeholder="Contoh: Paket sore">
          </div>

          <div class="d-flex gap-2">
            <button type="button" class="btn-custom flex-fill justify-content-center" data-bs-dismiss="modal"
              style="background:#FFFFFF;border:2px solid #7A1E1E;color:#7A1E1E;font-weight:700;">Batal</button>
            <button type="submit" class="btn-custom btn-custom-primary flex-fill justify-content-center"><i class="bi bi-send-fill"></i> Berikan Paket</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- ============ MODAL KIRIM MINYAK & GAS KE KASIR ============ --}}
  <div class="modal fade" id="modalKirim" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header-stok paket">
          <h5><i class="bi bi-fuel-pump-fill me-2"></i>Kirim Minyak &amp; Gas ke Kasir</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <form id="form-kirim" method="POST" action="{{ route('stok.kirim') }}" class="p-4">
          @csrf
          <input type="hidden" name="_modal" value="kirim">

          @if ($kirimTerbuka && ($errors->has('kirim') || $errors->has('kasir_id') || $errors->has('minyak') || $errors->has('gas')))
            <div class="pk-error">
              <i class="bi bi-x-octagon-fill"></i>
              <ul>
                @foreach (['kirim', 'kasir_id', 'minyak', 'gas'] as $f)
                  @foreach ($errors->get($f) as $e)
                    @foreach ((array) $e as $m)<li>{{ $m }}</li>@endforeach
                  @endforeach
                @endforeach
              </ul>
            </div>
          @endif


          <div class="mb-3">
            <label class="form-label">Kasir penerima</label>
            <select name="kasir_id" id="kirim-kasir" class="form-select" required>
              <option value="">— Pilih kasir —</option>
              @foreach ($kasirList as $k)
                <option value="{{ $k->id }}" @selected($kasirKirim == $k->id)>{{ $k->name }}</option>
              @endforeach
            </select>
            <small class="pk-hint" id="kirim-info"></small>
          </div>

          <div class="row g-3 mb-3">
            @foreach ([['minyak', 'Minyak goreng', 'bi-droplet-half', $minyakBahan ?? null, 'jerigen'], ['gas', 'Gas LPG', 'bi-fire', $gasBahan ?? null, 'tabung']] as [$kk, $judulKirim, $ikKirim, $bhnKirim, $satKirim])
              <div class="col-6">
                <input type="checkbox" class="btn-check" name="{{ $kk }}" id="kirim-{{ $kk }}" value="1" @checked(old('_modal') === 'kirim' ? old($kk) !== null : $awalKirim[$kk] > 0)>
                <label class="kirim-opsi" for="kirim-{{ $kk }}">
                  <span class="ko-ic"><i class="bi {{ $ikKirim }}"></i></span>
                  <span class="ko-judul">{{ $judulKirim }}</span>
                  <b class="ko-jml">{{ $kirimStandar[$kk] }} {{ $bhnKirim->satuan ?? $satKirim }}</b>
                  <small>Stok dapur {{ \App\Models\BahanBaku::formatAngka($bhnKirim->stok_saat_ini ?? 0) }}</small>
                  <i class="bi bi-check-circle-fill ko-cek"></i>
                </label>
              </div>
            @endforeach
          </div>

          <div class="mb-3">
            <label class="form-label">Catatan <span class="text-muted-green fw-normal">— opsional</span></label>
            <input type="text" name="catatan" maxlength="150" value="{{ old('catatan', $awalKirim['catatan']) }}" class="form-control" placeholder="Contoh: Minyak habis, minta tambah">
          </div>

          <div class="d-flex gap-2">
            <button type="button" class="btn-custom flex-fill justify-content-center" data-bs-dismiss="modal"
              style="background:#FFFFFF;border:2px solid #7A1E1E;color:#7A1E1E;font-weight:700;">Batal</button>
            <button type="submit" class="btn-custom btn-custom-primary flex-fill justify-content-center"><i class="bi bi-send-fill"></i> Kirim</button>
          </div>
        </form>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
  @php
    $paketUkuran = $ukuranPaket->map(function ($u) use ($standarPaket) {
      return [
        'id'     => $u->id,
        'hasil'  => (float) ($standarPaket[$u->id]['hasil_pcs_per_kg'] ?? 0),
      ];
    })->values()->all();
  @endphp
  <script>
    window.stokConfig = { autoJenis: @json($jenisAwal), bukaPaket: @json($paketTerbuka) };
    window.paketConfig = {
      set: @json($set),
      ukuran: @json($paketUkuran),
    };
    // Form Kirim Minyak & Gas: pilih kasir -> isi otomatis sesuai permintaan kasir itu (kalau ada), selain itu isi standar
    (function () {
      const minta = @json($permintaanKasir), standar = @json($kirimStandar);
      const kasir = document.getElementById('kirim-kasir'), minyak = document.getElementById('kirim-minyak'),
            gas = document.getElementById('kirim-gas'), info = document.getElementById('kirim-info');
      if (!kasir) return;
      kasir.addEventListener('change', () => {
        const q = minta[kasir.value];
        // Porsi tetap: kalau kasir meminta, centang yang diminta saja; kalau tidak, centang keduanya
        minyak.checked = q ? q.minyak > 0 : standar.minyak > 0;
        gas.checked = q ? q.gas > 0 : standar.gas > 0;
        const isi = [];
        if (q && q.minyak > 0) isi.push('minyak ' + q.minyak + ' jerigen');
        if (q && q.gas > 0) isi.push('gas ' + q.gas + ' tabung');
        info.textContent = isi.length ? 'Kasir ini meminta: ' + isi.join(' + ') : '';
      });
      if (kasir.value && minta[kasir.value]) kasir.dispatchEvent(new Event('change'));
      @if ($kirimTerbuka)
      window.addEventListener('load', () => { if (window.bootstrap) new bootstrap.Modal(document.getElementById('modalKirim')).show(); });
      @endif
    })();
  </script>
  <script src="{{ asset('assets_user/admin/data_master/master.js') }}?v={{ @filemtime(public_path('assets_user/admin/data_master/master.js')) }}"></script>
  <script src="{{ asset('assets_user/admin/operasional/manajemen_stok.js') }}?v={{ @filemtime(public_path('assets_user/admin/operasional/manajemen_stok.js')) }}"></script>
  <script src="{{ asset('assets_user/admin/operasional/paket_kasir.js') }}?v={{ @filemtime(public_path('assets_user/admin/operasional/paket_kasir.js')) }}"></script>
@endpush

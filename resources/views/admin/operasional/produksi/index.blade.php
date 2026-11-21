@extends('layouts.admin')

@section('title', 'Produksi Ayam - Chicken Crunchy Roll')
@section('breadcrumb', 'Produksi Ayam')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/operasional/stok_otomatis.css') }}">
@endpush

@section('content')
  @php
    $n = fn ($v, $d = 2) => rtrim(rtrim(number_format((float) $v, $d, ',', '.'), '0'), ',');
    $potong = ($set['ayam_dipotong_saat'] ?? 'penjualan') === 'produksi';
    $js = [
      'standar' => collect($standar)->map(fn ($s) => [
        'pcs' => (float) $s['hasil_pcs_per_kg'], 'terigu' => (float) $s['terigu_per_kg'], 'crispy' => (float) $s['crispy_per_kg'],
      ]),
      'set' => ['batch' => (float) $set['kapasitas_batch'], 'minyak' => (float) $set['minyak_per_batch']],
      'stok' => [
        'ayam' => (float) ($bahan['ayam']->stok_saat_ini ?? 0), 'terigu' => (float) ($bahan['tepung_terigu']->stok_saat_ini ?? 0),
        'crispy' => (float) ($bahan['tepung_crispy']->stok_saat_ini ?? 0),
      ],
    ];
  @endphp

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-fire"></i></div>
    <div>
      <h1 class="page-title">Produksi Ayam</h1>
      <p class="page-subtitle">Bagi kg ayam ke tiap ukuran sesuka kebutuhan, sistem menghitung tepung &amp; estimasi produk otomatis.</p>
    </div>
    <div>
      <a href="{{ route('stok.pengaturan') }}" class="btn-custom" style="background:#fff;border:2px solid var(--brand-forest-medium,#9c1d1d);color:var(--brand-forest-medium,#9c1d1d);font-weight:700;">
        <i class="bi bi-sliders"></i> Atur standar
      </a>
    </div>
  </div>

  @if ($errors->any())
    <div class="so-warn" style="background:#fde7e4;border-color:#f1b6b0;">
      <div class="so-warn-ic" style="background:#b3202f;"><i class="bi bi-x-octagon-fill"></i></div>
      <div><div class="so-warn-t">{{ $errors->first() }}</div></div>
    </div>
  @endif

  <form method="POST" action="{{ route('stok.produksi.simpan') }}" id="form-produksi">
    @csrf
    <div class="pr-layout">

      {{-- ===== INPUT ===== --}}
      <section class="so-card">
        <header class="so-head"><span class="so-num"><i class="bi bi-egg-fried"></i></span>
          <div><h2>Alokasi ayam per ukuran</h2>
            <p>Ayam di gudang: <b>{{ $stokAyam ? $n($stokAyam->stok_saat_ini, 3) . ' ' . $stokAyam->satuan : 'belum diatur' }}</b>.
              Contoh: 5 kg = 3 kg Reguler + 1 kg Slim + 1 kg Jumbo.</p></div></header>

        <div class="row g-3 mb-3">
          <div class="col-sm-5">
            <label class="form-label fw-bold" style="font-size:.85rem;">Tanggal</label>
            <input type="date" name="tanggal" value="{{ old('tanggal', now()->toDateString()) }}" class="form-control" required>
          </div>
          <div class="col-sm-7">
            <label class="form-label fw-bold" style="font-size:.85rem;">Catatan <span class="text-muted-green fw-normal">— opsional</span></label>
            <input type="text" name="catatan" maxlength="255" value="{{ old('catatan') }}" class="form-control" placeholder="Contoh: Produksi pagi">
          </div>
        </div>

        @forelse ($ukuran as $u)
          @php $s = $standar[$u->id] ?? null; @endphp
          <div class="pr-row" data-ukuran="{{ $u->id }}">
            <div class="pr-foto">@if ($u->foto)<img src="{{ asset('storage/' . $u->foto) }}" alt="{{ $u->nama_ukuran }}">@else<i class="bi bi-image"></i>@endif</div>
            <div class="pr-info"><b>{{ $u->nama_ukuran }}</b>
              <small>@if ($s) 1 kg = {{ $n($s['hasil_pcs_per_kg']) }} pcs @else standar belum diatur @endif</small></div>
            <div class="pr-input">
              <div class="input-group">
                <input type="number" step="0.1" min="0" max="9999" name="kg[{{ $u->id }}]" value="{{ old("kg.$u->id") }}" placeholder="0" class="form-control js-kg" @disabled(! $s)>
                <span class="input-group-text">kg</span>
              </div>
            </div>
            <div class="pr-est"><span class="js-est">0</span> pcs<small>estimasi</small></div>
          </div>
        @empty
          <div class="text-center text-muted-green p-4">Belum ada ukuran aktif.</div>
        @endforelse
      </section>

      {{-- ===== RINGKASAN ===== --}}
      <aside class="so-card pr-sum">
        <div class="pr-big"><small>Estimasi produk dihasilkan</small><b><span id="t-pcs">0</span> pcs</b></div>
        <ul class="pr-list">
          <li><span>Total ayam</span><b id="t-ayam">0 kg</b></li>
          <li><span>Tepung terigu</span><b id="t-terigu">0 kg</b></li>
          <li><span>Tepung crispy</span><b id="t-crispy">0 kg</b></li>
          <li><span>Kali masak</span><b id="t-batch">0 kali</b></li>
          <li><span>Minyak (perkiraan)</span><b id="t-minyak">0 liter</b></li>
        </ul>

        <ul class="pr-list" style="margin-top:.6rem;">
          <li><span>Gudang: ayam</span><b id="c-ayam">-</b></li>
          <li><span>Gudang: tepung terigu</span><b id="c-terigu">-</b></li>
          <li><span>Gudang: tepung crispy</span><b id="c-crispy">-</b></li>
        </ul>

        <div class="pr-mode">
          <i class="bi bi-info-circle"></i>
          Ini <b>kalkulator &amp; rencana produksi</b>. Stok ayam dan tepung di kasir berkurang otomatis tiap ada pembayaran. Stok gudang tidak berubah, bahan diberikan ke kasir lewat <b>Stok Keluar</b> di Manajemen Stok.
        </div>

        <button type="submit" class="btn-custom btn-custom-primary w-100 justify-content-center mt-3" id="btn-simpan">
          <i class="bi bi-check-lg"></i> Simpan Rencana Produksi
        </button>
      </aside>
    </div>
  </form>

  {{-- ===== RIWAYAT ===== --}}
  <section class="so-card">
    <header class="so-head"><span class="so-num"><i class="bi bi-clock-history"></i></span><div><h2>Riwayat produksi</h2><p>10 catatan terakhir.</p></div></header>
    @forelse ($riwayat as $r)
      <div class="pr-hist">
        <div class="d-flex justify-content-between flex-wrap gap-2">
          <span class="tg">{{ \Carbon\Carbon::parse($r->tanggal)->translatedFormat('d M Y') }} · {{ $n($r->total_ayam_kg, 3) }} kg ayam → ±{{ $r->total_pcs }} pcs</span>
          <span class="text-muted-green">{{ $r->admin }} · rencana</span>
        </div>
        <div class="pr-chips">
          @foreach ($itemRiwayat[$r->id] ?? [] as $it)
            <span class="pr-chip">{{ $namaUkuran[$it->ukuran_id] ?? 'Ukuran' }}: {{ $n($it->kg_ayam, 3) }} kg (±{{ $it->est_pcs }} pcs)</span>
          @endforeach
          <span class="pr-chip" style="background:#f1ebe5;color:#6b5a50;">Terigu {{ $n($r->total_terigu_kg, 3) }} kg</span>
          <span class="pr-chip" style="background:#f1ebe5;color:#6b5a50;">Crispy {{ $n($r->total_crispy_kg, 3) }} kg</span>
          @if ($r->catatan)<span class="pr-chip" style="background:#fff;border:1px solid var(--border-light);color:#6b5a50;">{{ $r->catatan }}</span>@endif
        </div>
      </div>
    @empty
      <div class="text-center text-muted-green p-3">Belum ada catatan produksi.</div>
    @endforelse
  </section>
@endsection

@push('scripts')
  <script>
    window.produksiCfg = @json($js);
  </script>
  <script>
    (function () {
      var cfg = window.produksiCfg, rows = document.querySelectorAll('.pr-row');
      var fmt = function (v, d) { return Number(v).toLocaleString('id-ID', { maximumFractionDigits: d }); };
      var set = function (id, t) { document.getElementById(id).textContent = t; };

      function hitung() {
        var t = { ayam: 0, terigu: 0, crispy: 0, pcs: 0 };
        rows.forEach(function (row) {
          var s = cfg.standar[row.dataset.ukuran], inp = row.querySelector('.js-kg');
          var kg = Math.max(0, parseFloat(inp.value) || 0), pcs = 0;
          if (s) {
            pcs = Math.floor(kg * s.pcs + 1e-9);   // sama dengan rumus di server
            t.ayam += kg; t.terigu += kg * s.terigu; t.crispy += kg * s.crispy; t.pcs += pcs;
          }
          row.querySelector('.js-est').textContent = fmt(pcs, 0);
        });
        var batch = t.pcs > 0 && cfg.set.batch > 0 ? Math.ceil(t.pcs / cfg.set.batch) : 0;
        set('t-batch', fmt(batch, 0) + ' kali'); set('t-minyak', fmt(batch * cfg.set.minyak, 3) + ' liter');
        set('t-pcs', fmt(t.pcs, 0)); set('t-ayam', fmt(t.ayam, 3) + ' kg');
        set('t-terigu', fmt(t.terigu, 3) + ' kg'); set('t-crispy', fmt(t.crispy, 3) + ' kg');

        [['ayam', 'c-ayam'], ['terigu', 'c-terigu'], ['crispy', 'c-crispy']].forEach(function (p) {
          var el = document.getElementById(p[1]), ada = cfg.stok[p[0]], perlu = t[p[0]];
          if (perlu <= 0) { el.textContent = fmt(ada, 3) + ' kg tersedia'; el.className = ''; return; }
          var sisa = ada - perlu;
          el.textContent = sisa >= 0 ? 'Cukup, sisa ' + fmt(sisa, 3) + ' kg' : 'Kurang ' + fmt(-sisa, 3) + ' kg';
          el.className = sisa >= 0 ? 'pr-ok' : 'pr-bad';
        });
      }

      document.querySelectorAll('.js-kg').forEach(function (i) { i.addEventListener('input', hitung); });
      hitung();
    })();
  </script>
@endpush

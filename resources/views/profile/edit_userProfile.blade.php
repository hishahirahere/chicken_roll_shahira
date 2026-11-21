@extends(auth()->user()->role === 'admin' ? 'layouts.admin' : 'layouts.kasir')

@section('title', 'Profil Saya - Chicken Crunchy Roll')
@section('breadcrumb', 'Profil Saya')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/shared/profile.css') }}">
@endpush

@section('content')
  @php
    $u         = $user;
    $errPw     = $errors->updatePassword;
    $status    = $u->status ?: 'aktif';
    $aktif     = strtolower($status) === 'aktif';
    $fotoUrl   = $u->foto ? asset('storage/' . $u->foto) : null;
    $terdaftar = $u->created_at ? $u->created_at->locale('id')->translatedFormat('d F Y') : null;

    // Data pribadi (dikelola Admin di Master Kasir): hanya ditampilkan, tidak bisa diubah di sini
    $jkLabel  = $u->jenis_kelamin === 'L' ? 'Laki-laki' : ($u->jenis_kelamin === 'P' ? 'Perempuan' : null);
    $tglLahir = $u->tanggal_lahir ? \Carbon\Carbon::parse($u->tanggal_lahir)->locale('id')->translatedFormat('d F Y') : null;
    $ttl      = trim(($u->tempat_lahir ?: '') . ($u->tempat_lahir && $tglLahir ? ', ' : '') . ($tglLahir ?: ''));
    $dataPribadi = [
      ['Jenis Kelamin', $jkLabel, false],
      ['No. HP', $u->no_hp ?: null, false],
      ['Tempat, Tanggal Lahir', $ttl ?: null, false],
      ['Alamat', $u->alamat ?: null, true],
    ];
    // Kasir selalu melihat bagian ini; admin hanya kalau datanya memang terisi
    $punyaData = $u->role !== 'admin' || collect($dataPribadi)->contains(fn ($d) => filled($d[1]));

    // Buka tab Password otomatis kalau ada error di form password
    $tabPassword = $errPw->any();
  @endphp

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-person-circle"></i></div>
    <div>
      <h1 class="page-title">Profil Saya</h1>
      <p class="page-subtitle">Kelola foto, nama, email, dan password akunmu.</p>
    </div>
  </div>

  <div class="pf-card">
    <div class="pf-cover"></div>

    {{-- ============ HEADER PROFIL ============ --}}
    <div class="pf-hero">
      <div class="pf-photo">
        <div class="pf-photo-img" id="pf-photo-box">
          @if ($fotoUrl)
            <img src="{{ $fotoUrl }}" alt="{{ $u->name }}">
          @endif
        </div>
        <label for="pf-foto-input" class="pf-camera" title="Ganti foto"><i class="bi bi-camera-fill"></i></label>
      </div>

      {{-- input foto ikut terkirim bersama form di tab Profil lewat atribut form --}}
      <input type="file" id="pf-foto-input" name="foto" form="form-data-akun"
             accept="image/png,image/jpeg,image/webp" class="d-none">

      <div class="pf-id">
        <div class="pf-name">{{ $u->name }}</div>
        <div class="pf-email">{{ $u->email }}</div>

        <div class="pf-pills">
          <span class="pf-pill"><i class="bi bi-shield-check"></i> {{ ucfirst($u->role) }}</span>
          <span class="pf-pill {{ $aktif ? 'ok' : 'off' }}">{{ ucfirst($status) }}</span>
          @if ($terdaftar)
            <span class="pf-pill neutral"><i class="bi bi-calendar-check"></i> Sejak {{ $terdaftar }}</span>
          @endif
        </div>

        @error('foto')
          <div class="pf-note is-error">{{ $message }}</div>
        @else
          <div class="pf-note" id="pf-foto-note">Klik ikon kamera untuk ganti foto (PNG, JPG, atau WEBP, maks 2MB).</div>
        @enderror
      </div>
    </div>

    {{-- ============ TAB ============ --}}
    <div class="pf-tabs" role="tablist">
      <button type="button" class="{{ $tabPassword ? '' : 'on' }}" data-pane="profil">Profil</button>
      <button type="button" class="{{ $tabPassword ? 'on' : '' }}" data-pane="password">Password</button>
    </div>

    {{-- ===== TAB PROFIL ===== --}}
    <div class="pf-pane pf-form {{ $tabPassword ? '' : 'on' }}" id="pane-profil">
      <form id="form-data-akun" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PATCH')

        <div class="row g-3">
          <div class="col-md-6">
            <label for="name" class="form-label">Nama</label>
            <input type="text" id="name" name="name" value="{{ old('name', $u->name) }}" required maxlength="255"
                   class="form-control @error('name') is-invalid @enderror" autocomplete="name">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $u->email) }}" required maxlength="255"
                   class="form-control @error('email') is-invalid @enderror" autocomplete="email">
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
        </div>

        <div class="pf-actions">
          <button type="submit" class="btn-custom btn-custom-primary"><i class="bi bi-check-lg"></i> Simpan Perubahan</button>
        </div>
      </form>

      @if ($punyaData)
        <hr class="pf-sep">

        <div class="pf-sec-title">
          <h3>Data Pribadi</h3>
          <span class="pf-lock"><i class="bi bi-lock-fill"></i> Hanya bisa diubah Admin</span>
        </div>

        <dl class="pf-dl">
          @foreach ($dataPribadi as [$label, $val, $wide])
            <div class="{{ $wide ? 'wide' : '' }}">
              <dt>{{ $label }}</dt>
              <dd class="{{ $val ? '' : 'empty' }}">{{ $val ?: 'Belum diisi' }}</dd>
            </div>
          @endforeach
        </dl>

        <p class="pf-foot-note">Ada data yang tidak sesuai? Minta Admin memperbaikinya di Master Kasir.</p>
      @endif
    </div>

    {{-- ===== TAB PASSWORD ===== --}}
    <div class="pf-pane pf-form {{ $tabPassword ? 'on' : '' }}" id="pane-password">
      <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        @method('PUT')

        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label for="current_password" class="form-label">Password Lama</label>
            <div class="input-group">
              <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                     class="form-control @if ($errPw->has('current_password')) is-invalid @endif">
              <button type="button" class="pf-toggle" data-target="current_password" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
            </div>
            @if ($errPw->has('current_password')) <div class="invalid-feedback">{{ $errPw->first('current_password') }}</div> @endif
          </div>

          <div class="col-12 col-md-4">
            <label for="password" class="form-label">Password Baru</label>
            <div class="input-group">
              <input type="password" id="password" name="password" required autocomplete="new-password"
                     class="form-control @if ($errPw->has('password')) is-invalid @endif">
              <button type="button" class="pf-toggle" data-target="password" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
            </div>
            @if ($errPw->has('password')) <div class="invalid-feedback">{{ $errPw->first('password') }}</div> @endif
          </div>

          <div class="col-12 col-md-4">
            <label for="password_confirmation" class="form-label">Konfirmasi Password Baru</label>
            <div class="input-group">
              <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                     class="form-control">
              <button type="button" class="pf-toggle" data-target="password_confirmation" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
            </div>
            <div class="pf-match" id="pf-match"></div>
          </div>
        </div>

        <div class="pf-actions">
          <button type="submit" class="btn-custom btn-custom-primary"><i class="bi bi-key"></i> Ganti Password</button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('assets_user/shared/profile.js') }}"></script>
@endpush

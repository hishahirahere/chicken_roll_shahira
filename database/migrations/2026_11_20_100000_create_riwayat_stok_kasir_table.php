<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat stok bahan di tangan kasir (filling, saus celup, bumbu tabur, kemasan, minyak, gas).
 * Sebelumnya halaman Stok Saya hanya menampilkan riwayat produk (pcs per ukuran).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_stok_kasir', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();        // kasir pemilik stok
            $table->foreignId('bahan_baku_id')->constrained('bahan_baku')->cascadeOnDelete();
            $table->string('jenis', 10);                      // masuk | keluar
            $table->string('sumber', 20)->nullable();         // paket | kirim | penjualan | pemakaian
            $table->decimal('jumlah', 12, 4);
            $table->decimal('stok_sesudah', 12, 4)->default(0);
            $table->unsignedBigInteger('transaksi_id')->nullable()->index();
            $table->string('keterangan')->nullable();
            $table->string('rincian')->nullable();
            $table->date('tanggal');
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'bahan_baku_id']);
        });

        // Isi dari riwayat penjualan yang sudah ada (pemotongan stok kasir)
        $lama = DB::table('riwayat_stok')->where('sumber', 'penjualan')->whereNotNull('kasir_id')->orderBy('id')->get();
        foreach ($lama->chunk(200) as $potong) {
            DB::table('riwayat_stok_kasir')->insert($potong->map(fn ($r) => [
                'user_id' => $r->kasir_id, 'bahan_baku_id' => $r->bahan_baku_id, 'jenis' => 'keluar', 'sumber' => 'penjualan',
                'jumlah' => $r->jumlah, 'stok_sesudah' => $r->stok_sesudah ?? 0, 'transaksi_id' => $r->transaksi_id,
                'keterangan' => $r->keterangan, 'rincian' => $r->rincian, 'tanggal' => $r->tanggal, 'created_at' => $r->created_at,
            ])->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_stok_kasir');
    }
};

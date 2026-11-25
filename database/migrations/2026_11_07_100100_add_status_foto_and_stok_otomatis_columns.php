<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi:
 *  - status aktif/nonaktif untuk menu (ukuran, filling, saus celup, bumbu tabur)
 *  - foto bahan baku
 *  - kolom pendukung stok otomatis (peran bahan)
 *  - riwayat stok mencatat sumber (manual / penjualan / paket / kirim)
 * Semua angka stok bilangan bulat (tanpa koma).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Status aktif / nonaktif untuk menu
        foreach (['ukuran', 'filling', 'saus_celup', 'bumbu_tabur'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->after('foto');
            });
        }

        // 2) Filling / saus / bumbu terhubung ke bahan baku (supaya stok bisa berkurang otomatis)
        foreach (['filling', 'saus_celup', 'bumbu_tabur'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->foreignId('bahan_baku_id')->nullable()->after('status')
                    ->constrained('bahan_baku')->nullOnDelete();
            });
        }

        // 3) Bahan baku
        Schema::table('bahan_baku', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('satuan');
            // ayam | tepung_premix_basah | tepung_roti | minyak | gas | kemasan | filling | saus | bumbu
            $table->string('peran', 30)->nullable()->after('foto');
        });

        // 4) Riwayat stok
        Schema::table('riwayat_stok', function (Blueprint $table) {
            $table->string('sumber', 20)->default('manual')->after('jenis'); // manual | penjualan | paket | kirim
            $table->foreignId('transaksi_id')->nullable()->after('keterangan')
                ->constrained('transaksi')->nullOnDelete();
            $table->string('rincian')->nullable()->after('transaksi_id'); // penjelasan hitungan otomatis
        });
    }

    public function down(): void
    {
        Schema::table('riwayat_stok', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaksi_id');
            $table->dropColumn(['sumber', 'rincian']);
        });

        Schema::table('bahan_baku', function (Blueprint $table) {
            $table->dropColumn(['foto', 'peran']);
        });

        foreach (['filling', 'saus_celup', 'bumbu_tabur'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropConstrainedForeignId('bahan_baku_id');
            });
        }

        foreach (['ukuran', 'filling', 'saus_celup', 'bumbu_tabur'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};

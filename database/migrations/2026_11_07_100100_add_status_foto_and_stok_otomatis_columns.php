<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi:
 *  - status aktif/nonaktif untuk menu (ukuran, filling, saus celup, bumbu tabur)
 *  - foto bahan baku
 *  - kolom pendukung stok otomatis (peran bahan, porsi per bungkus, sisa bungkus berjalan)
 *  - riwayat stok mencatat sumber (manual / penjualan / produksi)
 *  - ketelitian angka diperlebar (4 desimal) karena pemakaian per produk sangat kecil (mis. 0,0225 kg)
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
            // ayam | tepung_terigu | tepung_crispy | minyak | gas | kemasan | filling | saus | bumbu
            $table->string('peran', 30)->nullable()->after('foto');
            // kosong = ikut standar umum di Pengaturan Stok
            $table->decimal('porsi_per_bungkus', 10, 2)->nullable()->after('stok_minimum');
            // sisa porsi di bungkus yang sedang dipakai (sudah dibuka)
            $table->decimal('sisa_porsi_aktif', 10, 2)->default(0)->after('porsi_per_bungkus');
        });
        Schema::table('bahan_baku', function (Blueprint $table) {
            $table->decimal('stok_saat_ini', 12, 4)->default(0)->change();
        });

        // 4) Riwayat stok
        Schema::table('riwayat_stok', function (Blueprint $table) {
            $table->string('sumber', 20)->default('manual')->after('jenis'); // manual | penjualan | produksi
            $table->foreignId('transaksi_id')->nullable()->after('keterangan')
                ->constrained('transaksi')->nullOnDelete();
            $table->foreignId('produksi_id')->nullable()->after('transaksi_id')
                ->constrained('produksi')->nullOnDelete();
            $table->string('rincian')->nullable()->after('produksi_id'); // penjelasan hitungan otomatis
        });
        Schema::table('riwayat_stok', function (Blueprint $table) {
            $table->decimal('jumlah', 12, 4)->change();
            $table->decimal('stok_sesudah', 12, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('riwayat_stok', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaksi_id');
            $table->dropConstrainedForeignId('produksi_id');
            $table->dropColumn(['sumber', 'rincian']);
        });

        Schema::table('bahan_baku', function (Blueprint $table) {
            $table->dropColumn(['foto', 'peran', 'porsi_per_bungkus', 'sisa_porsi_aktif']);
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

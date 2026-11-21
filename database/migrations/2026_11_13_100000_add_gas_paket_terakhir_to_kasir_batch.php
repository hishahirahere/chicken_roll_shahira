<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gas LPG ikut paket ke kasir SELANG 2 HARI (hari ini ikut, besok tidak, lusa ikut lagi).
 * Kolom ini mencatat tanggal terakhir gas ikut paket untuk tiap kasir.
 * Tombol "Atur Gas" dihapus, jadi lama 1 tabung dipatok 2 hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kasir_batch', function (Blueprint $table) {
            $table->date('gas_paket_terakhir')->nullable()->after('gas_terakhir');
        });

        // Isi dari riwayat paket yang sudah ada (gas keluar dari gudang lewat paket ke kasir)
        $terakhir = DB::table('riwayat_stok as r')
            ->join('bahan_baku as b', 'b.id', '=', 'r.bahan_baku_id')
            ->where('b.peran', 'gas')->where('r.sumber', 'paket')->whereNotNull('r.kasir_id')
            ->groupBy('r.kasir_id')
            ->selectRaw('r.kasir_id as kasir_id, MAX(r.tanggal) as tanggal')
            ->get();
        foreach ($terakhir as $t) {
            DB::table('kasir_batch')->insertOrIgnore([
                'user_id' => $t->kasir_id, 'pcs_berjalan' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('kasir_batch')->where('user_id', $t->kasir_id)->update(['gas_paket_terakhir' => substr((string) $t->tanggal, 0, 10)]);
        }

        // 1 tabung = 2 hari jualan (tadinya bisa diatur lewat tombol Atur Gas)
        DB::table('pengaturan_stok')->updateOrInsert(
            ['kunci' => 'gas_hari_per_tabung'],
            ['nilai' => '2', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::table('kasir_batch', function (Blueprint $table) {
            $table->dropColumn('gas_paket_terakhir');
        });
    }
};

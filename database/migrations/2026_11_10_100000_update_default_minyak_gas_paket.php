<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyesuaikan angka bawaan minyak & gas dengan kondisi penggorengan sebenarnya
 * (wajan besar, 1 batch = 5 pcs, 1 paket = 60 pcs).
 *
 * Hanya mengubah nilai yang MASIH sama dengan angka bawaan lama, jadi angka yang
 * sudah pernah diubah manual tidak ikut tertimpa.
 */
return new class extends Migration
{
    private array $perubahan = [
        // kunci               => [nilai lama, nilai baru]
        'kapasitas_batch'    => [8, 5],
        'minyak_per_batch'   => [0.1, 0.4],
        'paket_minyak_liter' => [1, 5],
    ];

    public function up(): void
    {
        $this->ubah(0);
    }

    public function down(): void
    {
        $this->ubah(1);
    }

    private function ubah(int $arah): void
    {
        foreach ($this->perubahan as $kunci => [$lama, $baru]) {
            [$dari, $ke] = $arah === 0 ? [$lama, $baru] : [$baru, $lama];

            $row = DB::table('pengaturan_stok')->where('kunci', $kunci)->first();
            if ($row && abs((float) $row->nilai - (float) $dari) < 0.00001) {
                DB::table('pengaturan_stok')->where('kunci', $kunci)->update(['nilai' => (string) $ke, 'updated_at' => now()]);
            }
        }
    }
};

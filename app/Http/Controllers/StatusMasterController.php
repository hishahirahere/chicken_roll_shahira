<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Tombol aktif / nonaktif di kartu Data Master.
 * Data tidak dihapus: yang nonaktif hanya disembunyikan dari transaksi kasir (menu),
 * atau tidak bisa login (kasir).
 */
class StatusMasterController extends Controller
{
    private const TABEL = [
        'ukuran'      => ['ukuran', 'Ukuran', 'nama_ukuran'],
        'filling'     => ['filling', 'Filling', 'nama_filling'],
        'saus_celup'  => ['saus_celup', 'Saus celup', 'nama_saus'],
        'bumbu_tabur' => ['bumbu_tabur', 'Bumbu tabur', 'nama_bumbu'],
        'kasir'       => ['users', 'Kasir', 'name'],
    ];

    public function toggle(string $tipe, int $id): RedirectResponse
    {
        abort_unless(isset(self::TABEL[$tipe]), 404);
        [$tabel, $label, $kolomNama] = self::TABEL[$tipe];

        $query = DB::table($tabel)->where('id', $id);
        if ($tipe === 'kasir') {
            $query->where('role', 'kasir');   // admin tidak bisa dinonaktifkan lewat sini
        }

        $row = $query->first();
        abort_unless($row, 404);

        $baru = ($row->status ?? 'aktif') === 'nonaktif' ? 'aktif' : 'nonaktif';
        DB::table($tabel)->where('id', $id)->update(['status' => $baru]);

        return back()->with('success', "{$label} \"{$row->{$kolomNama}}\" sekarang " . ($baru === 'aktif' ? 'AKTIF.' : 'NONAKTIF.'));
    }
}

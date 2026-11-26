<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\StokOtomatis\DataAwalStokService;
use Illuminate\Database\Seeder;

class PengaturanStokSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('role', 'admin')->value('id');

        app(DataAwalStokService::class)->isiDataAwal($adminId);
    }
}

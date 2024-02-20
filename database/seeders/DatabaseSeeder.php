<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('clientes')->insert([
            [
                'id' => 1,
                'nome' => 'o barato sai caro',
                'limite' => 1000 * 100
            ],
            [
                'id' => 2,
                'nome' => 'zan corp ltda',
                'limite' => 800 * 100
            ],
            [
                'id' => 3,
                'nome' => 'les cruders',
                'limite' => 10000 * 100
            ],
            [
                'id' => 4,
                'nome' => 'padaria joia de cocaia',
                'limite' => 100000 * 100
            ],
            [
                'id' => 5,
                'nome' => 'kid mais',
                'limite' => 5000 * 100
            ],
        ]);
    }
}

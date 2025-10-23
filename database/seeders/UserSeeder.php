<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin Sistema',
            'email' => 'admin@voteigreja.app',
            'password' => Hash::make('password'),
            'permission' => 'admin',
        ]);

        User::create([
            'name' => 'João Silva',
            'email' => 'joao.silva@voteigreja.app',
            'password' => Hash::make('password'),
            'permission' => 'visualizador',
        ]);
    }
}

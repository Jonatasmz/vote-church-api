<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar usuário admin padrão
        User::firstOrCreate(
            ['email' => 'admin@votechurch.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin123'),
                'permission' => 'admin',
            ]
        );

        $this->command->info('Usuário admin criado com sucesso!');
        $this->command->info('Email: admin@votechurch.com');
        $this->command->info('Senha: admin123');
    }
}

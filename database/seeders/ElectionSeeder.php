<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Election;
use App\Models\Member;
use Carbon\Carbon;

class ElectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Pegar IDs de membros disponíveis
        $members = Member::pluck('id')->toArray();
        
        if (count($members) < 3) {
            $this->command->warn('São necessários pelo menos 3 membros cadastrados.');
            return;
        }

        // Eleição 1: Conselho de Diáconos 2025 (Ativa - Hoje)
        $election1 = Election::create([
            'title' => 'Eleição para Conselho de Diáconos 2025',
            'description' => 'Eleição para escolher os membros do conselho de diáconos que atuarão no ano de 2025.',
            'election_date' => Carbon::today(),
            'status' => 'active',
            'max_votes' => 3,
            'seats_available' => 5,
        ]);

        // Vincular primeiros 3 membros como candidatos
        $election1->members()->attach(array_slice($members, 0, 3));

        // Eleição 2: Coordenação de Louvor (Rascunho - Futuro)
        $election2 = Election::create([
            'title' => 'Coordenação de Louvor 2025',
            'description' => 'Eleição para escolher o novo coordenador de louvor da igreja.',
            'election_date' => Carbon::today()->addDays(10),
            'status' => 'draft',
            'max_votes' => 1,
            'seats_available' => 1,
        ]);

        // Vincular membros 2 e 4 como candidatos (se existirem)
        if (count($members) >= 4) {
            $election2->members()->attach([$members[1], $members[3]]);
        }

        // Eleição 3: Liderança de Jovens (Rascunho - Futuro)
        $election3 = Election::create([
            'title' => 'Liderança de Jovens 2025',
            'description' => 'Eleição para a nova liderança do ministério de jovens.',
            'election_date' => Carbon::today()->addDays(20),
            'status' => 'draft',
            'max_votes' => 2,
            'seats_available' => 2,
        ]);

        // Vincular primeiros 2 membros como candidatos
        $election3->members()->attach(array_slice($members, 0, 2));

        // Eleição 4: Conselho Fiscal 2024 (Finalizada - Passado)
        $election4 = Election::create([
            'title' => 'Conselho Fiscal 2024',
            'description' => 'Eleição para o conselho fiscal do ano de 2024 (finalizada).',
            'election_date' => Carbon::today()->subMonths(2),
            'status' => 'finished',
            'max_votes' => 4,
            'seats_available' => 3,
        ]);

        // Vincular membros disponíveis como candidatos
        $election4->members()->attach(array_slice($members, 0, min(4, count($members))));
    }
}

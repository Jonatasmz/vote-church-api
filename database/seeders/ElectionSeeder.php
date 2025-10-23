<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Election;
use App\Models\Candidate;
use Carbon\Carbon;

class ElectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Pegar IDs de candidatos disponíveis
        $candidates = Candidate::pluck('id')->toArray();
        
        if (count($candidates) < 3) {
            $this->command->warn('São necessários pelo menos 3 candidatos cadastrados. Execute o CandidateSeeder primeiro.');
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

        // Vincular primeiros 3 candidatos
        $election1->candidates()->attach(array_slice($candidates, 0, 3));

        // Eleição 2: Coordenação de Louvor (Rascunho - Futuro)
        $election2 = Election::create([
            'title' => 'Coordenação de Louvor 2025',
            'description' => 'Eleição para escolher o novo coordenador de louvor da igreja.',
            'election_date' => Carbon::today()->addDays(10),
            'status' => 'draft',
            'max_votes' => 1,
            'seats_available' => 1,
        ]);

        // Vincular candidatos 2 e 4 (se existirem)
        if (count($candidates) >= 4) {
            $election2->candidates()->attach([$candidates[1], $candidates[3]]);
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

        // Vincular primeiros 2 candidatos
        $election3->candidates()->attach(array_slice($candidates, 0, 2));

        // Eleição 4: Conselho Fiscal 2024 (Finalizada - Passado)
        $election4 = Election::create([
            'title' => 'Conselho Fiscal 2024',
            'description' => 'Eleição para o conselho fiscal do ano de 2024 (finalizada).',
            'election_date' => Carbon::today()->subMonths(2),
            'status' => 'finished',
            'max_votes' => 4,
            'seats_available' => 3,
        ]);

        // Vincular candidatos disponíveis com votos simulados
        $voteCounts = [45, 38, 52, 41, 35];
        foreach (array_slice($candidates, 0, min(4, count($candidates))) as $index => $candidateId) {
            $election4->candidates()->attach($candidateId, ['vote_count' => $voteCounts[$index] ?? 30]);
        }
    }
}

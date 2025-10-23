<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Candidate;

class CandidateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Candidate::create([
            'name' => 'João Silva',
            'description' => 'Coordenador de jovens',
            'member_since' => '2009',
            'status' => 'active',
            'photo' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&h=150&fit=crop',
        ]);

        Candidate::create([
            'name' => 'Maria Santos',
            'description' => 'Líder de louvor',
            'member_since' => '2014',
            'status' => 'active',
            'photo' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&h=150&fit=crop',
        ]);

        Candidate::create([
            'name' => 'Pedro Oliveira',
            'description' => 'Professor de escola bíblica',
            'member_since' => '2016',
            'status' => 'active',
        ]);

        Candidate::create([
            'name' => 'Ana Costa',
            'description' => 'Coordenadora de eventos',
            'member_since' => '2012',
            'status' => 'active',
            'photo' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=150&h=150&fit=crop',
        ]);

        Candidate::create([
            'name' => 'Carlos Mendes',
            'description' => 'Diácono',
            'member_since' => '2008',
            'status' => 'active',
        ]);
    }
}

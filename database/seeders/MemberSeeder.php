<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Member;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $members = [
            [
                'name' => 'João da Silva',
                'description' => 'Membro ativo da igreja, participa do ministério de louvor',
                'member_since' => '2015',
                'photo' => null,
                'status' => 'active',
            ],
            [
                'name' => 'Maria Santos',
                'description' => 'Coordenadora do ministério infantil',
                'member_since' => '2010',
                'photo' => null,
                'status' => 'active',
            ],
            [
                'name' => 'Pedro Oliveira',
                'description' => 'Líder de célula e professor da EBD',
                'member_since' => '2018',
                'photo' => null,
                'status' => 'active',
            ],
            [
                'name' => 'Ana Costa',
                'description' => 'Secretária da igreja',
                'member_since' => '2012',
                'photo' => null,
                'status' => 'active',
            ],
            [
                'name' => 'Carlos Mendes',
                'description' => 'Tesoureiro e membro do conselho',
                'member_since' => '2008',
                'photo' => null,
                'status' => 'active',
            ],
        ];

        foreach ($members as $member) {
            Member::create($member);
        }
    }
}

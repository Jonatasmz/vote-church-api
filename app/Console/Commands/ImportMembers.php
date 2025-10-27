<?php

namespace App\Console\Commands;

use App\Models\Member;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportMembers extends Command
{
    protected $signature = 'members:import {file}';
    protected $description = 'Importa membros de um arquivo CSV';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");
            return 1;
        }

        $this->info("Iniciando importação de membros...");

        $file = fopen($filePath, 'r');
        
        // Pula o cabeçalho
        $header = fgetcsv($file, 0, ';');
        
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($file, 0, ';')) !== false) {
                // Validações básicas
                if (empty($row[1])) { // Nome vazio
                    $skipped++;
                    continue;
                }

                $name = trim($row[1]);
                $rg = $this->cleanDocument($row[3]);
                $cpf = $this->cleanDocument($row[4]);
                $memberSince = $this->parseDate($row[27]); // Filiacao (coluna 28, índice 27)

                // Pula se nome for muito curto ou inválido
                if (strlen($name) < 3) {
                    $skipped++;
                    continue;
                }

                // Pula se CPF for zerado ou vazio
                if (empty($cpf) || $cpf === '00000000000') {
                    $cpf = null;
                }

                // Pula se RG for zerado ou vazio
                if (empty($rg) || $rg === '0000000000') {
                    $rg = null;
                }

                // Verifica se membro já existe (por nome ou CPF)
                $exists = Member::where('name', $name)
                    ->orWhere(function($query) use ($cpf) {
                        if ($cpf) {
                            $query->where('cpf', $cpf);
                        }
                    })
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                try {
                    Member::create([
                        'name' => $name,
                        'cpf' => $cpf,
                        'rg' => $rg,
                        'member_since' => $memberSince,
                        'status' => 'active',
                    ]);

                    $imported++;

                    if ($imported % 100 === 0) {
                        $this->info("Importados: {$imported} membros...");
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->warn("Erro ao importar: {$name} - {$e->getMessage()}");
                }
            }

            DB::commit();
            fclose($file);

            $this->info("\n✅ Importação concluída!");
            $this->table(
                ['Status', 'Quantidade'],
                [
                    ['Importados', $imported],
                    ['Ignorados', $skipped],
                    ['Erros', $errors],
                ]
            );

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($file);
            $this->error("Erro durante importação: {$e->getMessage()}");
            return 1;
        }
    }

    private function cleanDocument(?string $document): ?string
    {
        if (empty($document)) {
            return null;
        }

        // Remove tudo que não é número
        $cleaned = preg_replace('/[^0-9]/', '', $document);

        return empty($cleaned) ? null : $cleaned;
    }

    private function parseDate(?string $date): ?string
    {
        if (empty($date) || $date === '00/00/0000') {
            return null;
        }

        try {
            // Tenta converter de DD/MM/YYYY para YYYY-MM-DD
            $parsed = Carbon::createFromFormat('d/m/Y', trim($date));
            return $parsed->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}

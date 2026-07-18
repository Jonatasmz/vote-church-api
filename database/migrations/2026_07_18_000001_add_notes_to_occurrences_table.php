<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reaplica `notes` em occurrences. A migração original (2026_02_22_200000) tem
 * data anterior à criação da tabela, então em banco novo ela não tinha efeito.
 * Idempotente: em bancos que já têm a coluna, não faz nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('occurrences', 'notes')) {
            return;
        }

        Schema::table('occurrences', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        // Sem rollback: quem remove a coluna é a migração original.
    }
};

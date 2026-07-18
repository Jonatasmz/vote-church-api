<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Esta migração é anterior à que cria `occurrences` — num banco novo a tabela
     * ainda não existe aqui. O guard deixa ela passar batido e a coluna entra na
     * migração 2026_07_18_000001. Em bancos que já rodaram, nada muda.
     */
    public function up(): void
    {
        if (!Schema::hasTable('occurrences') || Schema::hasColumn('occurrences', 'notes')) {
            return;
        }

        Schema::table('occurrences', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('occurrences') || !Schema::hasColumn('occurrences', 'notes')) {
            return;
        }

        Schema::table('occurrences', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};

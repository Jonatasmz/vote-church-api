<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            // Adicionar election_date com valor padrão temporário
            $table->date('election_date')->default('2025-01-01')->after('description');
        });

        // Copiar a data de start_date para election_date
        DB::table('elections')->update([
            'election_date' => DB::raw('DATE(start_date)')
        ]);

        Schema::table('elections', function (Blueprint $table) {
            // Agora remover start_date e end_date
            $table->dropColumn(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $table->dropColumn('election_date');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
        });
    }
};

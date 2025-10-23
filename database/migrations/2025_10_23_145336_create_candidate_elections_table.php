<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('candidate_elections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->onDelete('cascade');
            $table->foreignId('election_id')->constrained()->onDelete('cascade');
            $table->integer('vote_count')->default(0);
            $table->timestamps();
            
            // Garantir que um candidato não seja adicionado duas vezes à mesma eleição
            $table->unique(['candidate_id', 'election_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_elections');
    }
};

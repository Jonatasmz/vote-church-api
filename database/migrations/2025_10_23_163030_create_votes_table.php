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
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_token_id')->nullable()->constrained('vote_tokens')->onDelete('cascade');
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('cascade');
            $table->foreignId('election_id')->constrained('elections')->onDelete('cascade');
            $table->foreignId('voted_member_id')->constrained('members')->onDelete('cascade');
            $table->timestamps();

            // Um token pode votar em vários candidatos, mas não no mesmo candidato duas vezes
            $table->index(['vote_token_id', 'voted_member_id']);
            $table->index(['election_id', 'voted_member_id']);
            $table->index(['member_id', 'election_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};

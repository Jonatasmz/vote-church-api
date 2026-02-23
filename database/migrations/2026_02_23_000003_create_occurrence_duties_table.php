<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occurrence_duties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->constrained()->onDelete('cascade');
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->foreignId('ministry_id')->constrained()->onDelete('cascade');
            $table->string('role', 100)->nullable();
            $table->timestamps();

            $table->unique(['occurrence_id', 'member_id', 'ministry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occurrence_duties');
    }
};

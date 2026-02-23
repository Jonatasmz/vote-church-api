<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ministry_user');
    }

    public function down(): void
    {
        Schema::create('ministry_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministry_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['ministry_id', 'user_id']);
        });
    }
};

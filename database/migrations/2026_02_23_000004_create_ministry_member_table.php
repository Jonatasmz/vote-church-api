<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ministry_member', function (Blueprint $table) {
            $table->foreignId('ministry_id')->constrained()->onDelete('cascade');
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->primary(['ministry_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ministry_member');
    }
};

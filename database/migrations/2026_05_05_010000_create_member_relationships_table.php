<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('related_member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('relationship_type', ['spouse', 'parent', 'child', 'sibling']);
            $table->timestamps();

            $table->unique(['member_id', 'related_member_id', 'relationship_type'], 'member_rel_unique');
            $table->index('member_id');
            $table->index('related_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_relationships');
    }
};

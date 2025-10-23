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
        Schema::table('elections', function (Blueprint $table) {
            $table->integer('max_votes')->default(1)->after('status');
            $table->integer('seats_available')->default(1)->after('max_votes');
            $table->dropColumn('allow_multiple_votes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $table->boolean('allow_multiple_votes')->default(false);
            $table->dropColumn(['max_votes', 'seats_available']);
        });
    }
};

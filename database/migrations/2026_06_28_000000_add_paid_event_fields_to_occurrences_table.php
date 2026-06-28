<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('occurrences', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false)->after('notes');
            $table->decimal('price', 10, 2)->nullable()->after('is_paid');
            $table->unsignedSmallInteger('installments')->nullable()->after('price');
            $table->string('info_url', 500)->nullable()->after('installments');
            $table->date('end_date')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('occurrences', function (Blueprint $table) {
            $table->dropColumn(['is_paid', 'price', 'installments', 'info_url', 'end_date']);
        });
    }
};

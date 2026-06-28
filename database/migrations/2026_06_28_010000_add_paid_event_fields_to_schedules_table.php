<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('date');
            $table->boolean('is_paid')->default(false)->after('description');
            $table->decimal('price', 10, 2)->nullable()->after('is_paid');
            $table->unsignedSmallInteger('installments')->nullable()->after('price');
            $table->string('info_url', 500)->nullable()->after('installments');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['end_date', 'is_paid', 'price', 'installments', 'info_url']);
        });
    }
};

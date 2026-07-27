<?php

use App\Models\Schedule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('public_id', 24)->nullable()->unique()->after('id');
        });

        // Backfill dos eventos existentes para os links públicos resolverem.
        Schedule::withTrashed()->whereNull('public_id')->get()->each(function (Schedule $s) {
            do {
                $id = Str::random(12);
            } while (Schedule::where('public_id', $id)->exists());
            $s->public_id = $id;
            $s->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });
    }
};

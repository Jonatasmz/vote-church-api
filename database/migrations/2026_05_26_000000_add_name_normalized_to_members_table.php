<?php

use App\Models\Member;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('name_normalized')->nullable()->index()->after('name');
        });

        Member::withTrashed()->chunkById(500, function ($members) {
            foreach ($members as $member) {
                $member->name_normalized = Str::lower(Str::ascii((string) $member->name));
                $member->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('name_normalized');
        });
    }
};

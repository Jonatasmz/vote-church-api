<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('cpf', 14)->nullable();
            $table->string('phone', 30)->nullable();
            $table->enum('status', ['pending', 'paid', 'canceled', 'refunded'])->default('pending');
            $table->enum('source', ['member', 'external']);
            $table->unsignedInteger('amount_cents');
            $table->string('stripe_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['schedule_id', 'status']);
            $table->index(['schedule_id', 'member_id']);
            $table->index('cpf');
            $table->index('stripe_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_enrollments');
    }
};

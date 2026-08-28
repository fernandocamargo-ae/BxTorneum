<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['registration', 'swiss', 'elimination', 'completed'])->default('registration');
            $table->unsignedTinyInteger('swiss_rounds');
            $table->unsignedTinyInteger('cut_size');
            $table->unsignedTinyInteger('current_round')->default(0);
            $table->foreignId('created_by_user_id')->constrained('users');
            // No FK constraint: tournament_entries is created in a later migration
            // and referencing it here would create a circular dependency.
            $table->unsignedBigInteger('champion_entry_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};

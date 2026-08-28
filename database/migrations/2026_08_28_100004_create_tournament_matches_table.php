<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entry_one_id')->constrained('tournament_entries');
            $table->foreignId('entry_two_id')->nullable()->constrained('tournament_entries');
            $table->foreignId('winner_entry_id')->nullable()->constrained('tournament_entries');
            $table->boolean('is_bye')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_matches');
    }
};

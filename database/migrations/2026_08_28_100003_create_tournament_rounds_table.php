<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->enum('phase', ['swiss', 'elimination']);
            $table->timestamps();
            $table->unique(['tournament_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_rounds');
    }
};

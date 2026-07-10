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
        Schema::create('deck_beyblade_part', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deck_beyblade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained();
            $table->enum('slot', [
                'blade', 'ratchet', 'bit', 'lock_chip',
                'main_blade', 'assist_blade', 'over_blade', 'metal_blade',
            ]);
            $table->timestamps();
            $table->unique(['deck_beyblade_id', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deck_beyblade_part');
    }
};

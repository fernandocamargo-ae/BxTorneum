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
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'blade', 'ratchet', 'bit', 'lock_chip',
                'main_blade', 'assist_blade', 'over_blade', 'metal_blade',
            ]);
            $table->string('name');
            $table->timestamps();
            $table->unique(['type', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};

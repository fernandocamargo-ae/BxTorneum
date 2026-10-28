<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plain string (app-validated) so a role swap can park a member at a temp
        // value without violating unique(team_id, role).
        Schema::table('members', function (Blueprint $table) {
            $table->string('role')->change();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->enum('role', ['captain', 'subcaptain', 'official'])->change();
        });
    }
};

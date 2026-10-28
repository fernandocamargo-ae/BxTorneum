<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A team entry carries a team_id instead of user_id/deck_id, so those two
        // become nullable; a unique (tournament_id, team_id) stops the same team
        // being enrolled twice (individual entries leave team_id null, and SQL
        // treats repeated NULLs as distinct).
        Schema::table('tournament_entries', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('tournament_id')->constrained('teams')->restrictOnDelete();
            $table->unique(['tournament_id', 'team_id']);
        });

        Schema::table('tournament_entries', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('deck_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_entries', function (Blueprint $table) {
            $table->dropUnique(['tournament_id', 'team_id']);
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });

        Schema::table('tournament_entries', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreignId('deck_id')->nullable(false)->change();
        });
    }
};

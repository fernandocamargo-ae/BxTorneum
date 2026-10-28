<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The old team model (text members + per-member beys) is incompatible with
        // user-based membership. Reset team data (owner-approved "start clean").
        // Delete children in order: matches reference entries with RESTRICT, so a plain
        // cascade from tournaments is not reliable.
        $teamTournamentIds = DB::table('tournaments')->where('format', 'teams')->pluck('id');
        if ($teamTournamentIds->isNotEmpty()) {
            $roundIds = DB::table('tournament_rounds')->whereIn('tournament_id', $teamTournamentIds)->pluck('id');
            DB::table('tournament_matches')->whereIn('tournament_round_id', $roundIds)->delete();
            DB::table('tournament_rounds')->whereIn('tournament_id', $teamTournamentIds)->delete();
            DB::table('tournament_entries')->whereIn('tournament_id', $teamTournamentIds)->delete();
            DB::table('tournaments')->whereIn('id', $teamTournamentIds)->delete();
        }

        Schema::dropIfExists('beyblade_part'); // team-beyblade ↔ part pivot (decks use deck_beyblade_part)
        Schema::dropIfExists('beyblades');
        Schema::dropIfExists('members');
        DB::table('teams')->delete();

        if (! Schema::hasColumn('teams', 'created_by_user_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->foreignId('created_by_user_id')->nullable()->after('name')->constrained('users')->nullOnDelete();
            });
        }

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['captain', 'subcaptain', 'official']);
            $table->timestamps();
            $table->unique('user_id');           // one team per user
            $table->unique(['team_id', 'role']); // one of each role per team
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['captain', 'subcaptain', 'official']);
            $table->string('name');
            $table->timestamps();
            $table->unique(['team_id', 'role']);
        });

        Schema::create('beyblades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->enum('line', ['bx', 'ux', 'bx_infinity', 'ux_infinity', 'cx', 'cx_infinity']);
            $table->unsignedTinyInteger('position');
            $table->timestamps();
            $table->unique(['member_id', 'position']);
        });

        if (Schema::hasColumn('teams', 'created_by_user_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by_user_id');
            });
        }
    }
};

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TournamentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournament_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'is_admin'));

        $this->assertTrue(Schema::hasTable('tournaments'));
        $this->assertTrue(Schema::hasColumns('tournaments', [
            'name', 'status', 'swiss_rounds', 'cut_size', 'current_round', 'created_by_user_id', 'champion_entry_id',
        ]));

        $this->assertTrue(Schema::hasTable('tournament_entries'));
        $this->assertTrue(Schema::hasColumns('tournament_entries', ['tournament_id', 'user_id', 'deck_id']));

        $this->assertTrue(Schema::hasTable('tournament_rounds'));
        $this->assertTrue(Schema::hasColumns('tournament_rounds', ['tournament_id', 'number', 'phase']));

        $this->assertTrue(Schema::hasTable('tournament_matches'));
        $this->assertTrue(Schema::hasColumns('tournament_matches', [
            'tournament_round_id', 'entry_one_id', 'entry_two_id', 'winner_entry_id', 'is_bye',
        ]));
    }
}

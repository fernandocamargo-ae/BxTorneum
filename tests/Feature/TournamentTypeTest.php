<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TournamentTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournaments_default_to_official(): void
    {
        // Insert without specifying `type`, proving the column defaults to official
        // (the real backfill over pre-existing rows is verified at deploy time).
        $id = DB::table('tournaments')->insertGetId([
            'name' => 'Viejo',
            'status' => 'completed',
            'swiss_rounds' => 3,
            'cut_size' => 4,
            'current_round' => 0,
            'created_by_user_id' => User::factory()->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('official', Tournament::find($id)->type);
    }

    public function test_type_helpers(): void
    {
        $official = new Tournament(['type' => 'official']);
        $clandestine = new Tournament(['type' => 'clandestine']);

        $this->assertTrue($official->isOfficial());
        $this->assertTrue($clandestine->isClandestine());
        $this->assertFalse($official->isClandestine());
    }
}

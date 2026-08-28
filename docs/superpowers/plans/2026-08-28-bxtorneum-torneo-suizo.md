# Torneo con emparejamiento suizo + eliminatorias — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a single-active-tournament feature where an admin creates a tournament, players join with their existing tournament-marked deck, the app runs Swiss rounds (adapted from official Beyblade X/WBO format) followed by a seeded single-elimination cut to a champion, and every round generation emails each player their opponent.

**Architecture:** Four new tables (`tournaments`, `tournament_entries`, `tournament_rounds`, `tournament_matches`) plus an `is_admin` column on `users`. Pure, DB-free `TournamentPairing` functions handle all matchmaking math (unit tested). A `TournamentStandings` service computes wins/tiebreakers from match history on demand (no denormalized counters). A `TournamentRoundFactory` support class is the single place that creates a round's matches and fires notifications, reused by both round-generation and the elimination cut. Three thin controllers (`TournamentController`, `TournamentRoundController`, `TournamentMatchController`) sit behind `auth` (+ `admin` for management actions). One Inertia page (`Tournament/Show.vue`) renders every tournament state.

**Tech Stack:** Laravel 11 (PHP), Inertia + Vue 3, Tailwind, PHPUnit (`tests/Unit`, `tests/Feature`), Vitest (`resources/js/Pages/__tests__`).

**Spec:** `docs/superpowers/specs/2026-08-28-bxtorneum-torneo-suizo-design.md`

## Global Constraints

- Only one tournament with `status != 'completed'` may exist at a time (enforced in `TournamentController@store`).
- `cut_size` must be one of `2, 4, 8, 16` (validated in `StoreTournamentRequest`).
- Only the organizer's result-reporting/round-generation actions are behind `is_admin`; joining and viewing are open to any authenticated user.
- Pairing-email notifications are sent **synchronously** (no `ShouldQueue`) — this hosting environment has no queue worker running.
- A player can only join using the deck currently marked `is_tournament_deck = true` on their account; the match/entry stores a **snapshot** (`deck_id`) at join time.
- Standings order: `wins` desc → `opponent_win_percentage` desc → `entry_id` asc (deterministic, no randomness at read time).
- Follow existing repo conventions throughout: `$guarded = []` on new models, `abort_if`/`abort_unless` for authorization/validation guards in controllers, `DB::transaction` for multi-row writes, Inertia pages under `resources/js/Pages/<Feature>/`, raw styled `<input>`/`<select>` elements (not the Breeze `TextInput`/`InputLabel` components) matching `Decks/Create.vue`.

---

### Task 1: Database schema

**Files:**
- Create: `database/migrations/2026_08_28_100000_add_is_admin_to_users_table.php`
- Create: `database/migrations/2026_08_28_100001_create_tournaments_table.php`
- Create: `database/migrations/2026_08_28_100002_create_tournament_entries_table.php`
- Create: `database/migrations/2026_08_28_100003_create_tournament_rounds_table.php`
- Create: `database/migrations/2026_08_28_100004_create_tournament_matches_table.php`
- Test: `tests/Feature/TournamentSchemaTest.php`

**Interfaces:**
- Produces: tables `tournaments(id, name, status, swiss_rounds, cut_size, current_round, created_by_user_id, champion_entry_id, timestamps)`, `tournament_entries(id, tournament_id, user_id, deck_id, timestamps, unique(tournament_id,user_id))`, `tournament_rounds(id, tournament_id, number, phase, timestamps, unique(tournament_id,number))`, `tournament_matches(id, tournament_round_id, entry_one_id, entry_two_id, winner_entry_id, is_bye, timestamps)`; column `users.is_admin`.

- [ ] **Step 1: Write the failing schema test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TournamentSchemaTest`
Expected: FAIL (tables/columns don't exist yet)

- [ ] **Step 3: Create the migrations**

`database/migrations/2026_08_28_100000_add_is_admin_to_users_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('nickname');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
```

`database/migrations/2026_08_28_100001_create_tournaments_table.php`:
```php
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
```

`database/migrations/2026_08_28_100002_create_tournament_entries_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('deck_id')->constrained();
            $table->timestamps();
            $table->unique(['tournament_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_entries');
    }
};
```

`database/migrations/2026_08_28_100003_create_tournament_rounds_table.php`:
```php
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
```

`database/migrations/2026_08_28_100004_create_tournament_matches_table.php`:
```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter TournamentSchemaTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_28_1000*.php tests/Feature/TournamentSchemaTest.php
git commit -m "feat: add tournament schema (tournaments, entries, rounds, matches, users.is_admin)"
```

---

### Task 2: Eloquent models

**Files:**
- Create: `app/Models/Tournament.php`
- Create: `app/Models/TournamentEntry.php`
- Create: `app/Models/TournamentRound.php`
- Create: `app/Models/TournamentMatch.php`
- Test: `tests/Feature/TournamentModelsTest.php`

**Interfaces:**
- Consumes: tables from Task 1; `App\Models\User`, `App\Models\Deck` (existing).
- Produces: `Tournament::entries()`, `::rounds()`, `::champion()` (belongsTo `TournamentEntry` via `champion_entry_id`), `::createdBy()`; `TournamentEntry::tournament()`, `::user()`, `::deck()`; `TournamentRound::tournament()`, `::matches()`; `TournamentMatch::round()`, `::entryOne()`, `::entryTwo()`, `::winner()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationships_resolve_across_the_tournament_graph(): void
    {
        $organizer = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->create();
        $deck = $player->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        $tournament = Tournament::create([
            'name' => 'Copa X',
            'status' => 'registration',
            'swiss_rounds' => 3,
            'cut_size' => 4,
            'current_round' => 0,
            'created_by_user_id' => $organizer->id,
        ]);

        $entry = TournamentEntry::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'deck_id' => $deck->id,
        ]);

        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);

        $match = $round->matches()->create([
            'entry_one_id' => $entry->id,
            'entry_two_id' => null,
            'winner_entry_id' => $entry->id,
            'is_bye' => true,
        ]);

        $this->assertTrue($tournament->entries->first()->is($entry));
        $this->assertTrue($tournament->rounds->first()->is($round));
        $this->assertTrue($round->tournament->is($tournament));
        $this->assertTrue($round->matches->first()->is($match));
        $this->assertTrue($match->round->is($round));
        $this->assertTrue($match->entryOne->is($entry));
        $this->assertTrue($match->winner->is($entry));
        $this->assertTrue($entry->user->is($player));
        $this->assertTrue($entry->deck->is($deck));
        $this->assertTrue($entry->tournament->is($tournament));
        $this->assertTrue($tournament->createdBy->is($organizer));
        $this->assertTrue($match->is_bye);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TournamentModelsTest`
Expected: FAIL with "Class App\Models\Tournament not found"

- [ ] **Step 3: Write the models**

`app/Models/Tournament.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    protected $guarded = [];

    public function entries()
    {
        return $this->hasMany(TournamentEntry::class);
    }

    public function rounds()
    {
        return $this->hasMany(TournamentRound::class);
    }

    public function champion()
    {
        return $this->belongsTo(TournamentEntry::class, 'champion_entry_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
```

`app/Models/TournamentEntry.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentEntry extends Model
{
    protected $guarded = [];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deck()
    {
        return $this->belongsTo(Deck::class);
    }
}
```

`app/Models/TournamentRound.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentRound extends Model
{
    protected $guarded = [];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matches()
    {
        return $this->hasMany(TournamentMatch::class);
    }
}
```

`app/Models/TournamentMatch.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_bye' => 'boolean',
        ];
    }

    public function round()
    {
        return $this->belongsTo(TournamentRound::class, 'tournament_round_id');
    }

    public function entryOne()
    {
        return $this->belongsTo(TournamentEntry::class, 'entry_one_id');
    }

    public function entryTwo()
    {
        return $this->belongsTo(TournamentEntry::class, 'entry_two_id');
    }

    public function winner()
    {
        return $this->belongsTo(TournamentEntry::class, 'winner_entry_id');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter TournamentModelsTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Tournament.php app/Models/TournamentEntry.php app/Models/TournamentRound.php app/Models/TournamentMatch.php tests/Feature/TournamentModelsTest.php
git commit -m "feat: add Tournament/TournamentEntry/TournamentRound/TournamentMatch models"
```

---

### Task 3: Pairing — first round

**Files:**
- Create: `app/Support/TournamentPairing.php`
- Test: `tests/Unit/TournamentPairingTest.php`

**Interfaces:**
- Produces: `TournamentPairing::pairFirstRound(Collection $entryIds): array` — `$entryIds` is a `Collection<int,int>` of entry IDs; returns `array<int, array{0:int,1:int|null}>` (pairs; `null` = bye).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Support\TournamentPairing;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TournamentPairingTest extends TestCase
{
    public function test_pairs_an_even_number_of_entries_with_no_byes(): void
    {
        $pairs = TournamentPairing::pairFirstRound(collect([1, 2, 3, 4, 5, 6]));

        $this->assertCount(3, $pairs);
        $paired = collect($pairs)->flatten()->filter()->sort()->values()->all();
        $this->assertSame([1, 2, 3, 4, 5, 6], $paired);
        foreach ($pairs as [$a, $b]) {
            $this->assertNotNull($b);
        }
    }

    public function test_odd_number_of_entries_gives_exactly_one_bye(): void
    {
        $pairs = TournamentPairing::pairFirstRound(collect([1, 2, 3, 4, 5]));

        $byes = collect($pairs)->filter(fn ($pair) => $pair[1] === null);
        $this->assertCount(1, $byes);

        $paired = collect($pairs)->flatten()->filter(fn ($id) => $id !== null)->sort()->values()->all();
        $this->assertSame([1, 2, 3, 4, 5], $paired);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TournamentPairingTest`
Expected: FAIL with "Class App\Support\TournamentPairing not found"

- [ ] **Step 3: Implement `pairFirstRound`**

`app/Support/TournamentPairing.php`:
```php
<?php

namespace App\Support;

use Illuminate\Support\Collection;

class TournamentPairing
{
    /**
     * @param  Collection<int,int>  $entryIds
     * @return array<int, array{0:int,1:int|null}>
     */
    public static function pairFirstRound(Collection $entryIds): array
    {
        $shuffled = $entryIds->shuffle()->values();

        $pairs = [];
        $i = 0;
        $total = $shuffled->count();

        while ($i < $total) {
            $first = $shuffled[$i];
            $second = $shuffled->get($i + 1);
            $pairs[] = [$first, $second];
            $i += 2;
        }

        return $pairs;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter TournamentPairingTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Support/TournamentPairing.php tests/Unit/TournamentPairingTest.php
git commit -m "feat: add TournamentPairing::pairFirstRound"
```

---

### Task 4: Pairing — Swiss rounds

**Files:**
- Modify: `app/Support/TournamentPairing.php`
- Modify: `tests/Unit/TournamentPairingTest.php`

**Interfaces:**
- Produces: `TournamentPairing::pairSwissRound(array $standings, array $previousMatchups): array`.
  - `$standings`: `array<int, array{entry_id:int, wins:int, matches_played:int, opponent_win_percentage:float, had_bye:bool}>`, **already ordered best-to-worst** (this is the contract `TournamentStandings::forTournament()` from Task 6 fulfills).
  - `$previousMatchups`: `array<int, array<int>>` — `entry_id => [opponent_entry_id, ...]` already faced.
  - Returns: `array<int, array{0:int,1:int|null}>`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/TournamentPairingTest.php`:
```php
    public function test_swiss_round_avoids_rematches_when_an_alternative_exists(): void
    {
        $standings = [
            ['entry_id' => 1, 'wins' => 2, 'matches_played' => 2, 'opponent_win_percentage' => 0.5, 'had_bye' => false],
            ['entry_id' => 2, 'wins' => 2, 'matches_played' => 2, 'opponent_win_percentage' => 0.5, 'had_bye' => false],
            ['entry_id' => 3, 'wins' => 2, 'matches_played' => 2, 'opponent_win_percentage' => 0.4, 'had_bye' => false],
            ['entry_id' => 4, 'wins' => 2, 'matches_played' => 2, 'opponent_win_percentage' => 0.4, 'had_bye' => false],
        ];
        // 1 already played 2 in round 1; pairing again would be a rematch that's avoidable via 3/4.
        $previousMatchups = [1 => [2], 2 => [1], 3 => [4], 4 => [3]];

        $pairs = TournamentPairing::pairSwissRound($standings, $previousMatchups);

        $this->assertCount(2, $pairs);
        foreach ($pairs as [$a, $b]) {
            $this->assertNotContains($b, $previousMatchups[$a] ?? []);
        }
        $paired = collect($pairs)->flatten()->sort()->values()->all();
        $this->assertSame([1, 2, 3, 4], $paired);
    }

    public function test_swiss_round_allows_a_rematch_when_it_is_unavoidable(): void
    {
        $standings = [
            ['entry_id' => 1, 'wins' => 1, 'matches_played' => 1, 'opponent_win_percentage' => 0.0, 'had_bye' => false],
            ['entry_id' => 2, 'wins' => 0, 'matches_played' => 1, 'opponent_win_percentage' => 1.0, 'had_bye' => false],
        ];
        $previousMatchups = [1 => [2], 2 => [1]];

        $pairs = TournamentPairing::pairSwissRound($standings, $previousMatchups);

        $this->assertSame([[1, 2]], $pairs);
    }

    public function test_swiss_round_bye_goes_to_the_lowest_ranked_entry_without_a_previous_bye(): void
    {
        $standings = [
            ['entry_id' => 1, 'wins' => 2, 'matches_played' => 2, 'opponent_win_percentage' => 0.5, 'had_bye' => false],
            ['entry_id' => 2, 'wins' => 1, 'matches_played' => 2, 'opponent_win_percentage' => 0.5, 'had_bye' => false],
            ['entry_id' => 3, 'wins' => 1, 'matches_played' => 2, 'opponent_win_percentage' => 0.5, 'had_bye' => true],
        ];
        $previousMatchups = [1 => [], 2 => [], 3 => []];

        $pairs = TournamentPairing::pairSwissRound($standings, $previousMatchups);

        $byes = collect($pairs)->filter(fn ($pair) => $pair[1] === null)->flatten()->filter();
        $this->assertSame([2], $byes->values()->all());
    }

    public function test_swiss_round_bye_reassignment_never_creates_a_rematch(): void
    {
        $standings = [
            ['entry_id' => 1, 'wins' => 3, 'matches_played' => 3, 'opponent_win_percentage' => 0.6, 'had_bye' => false],
            ['entry_id' => 2, 'wins' => 2, 'matches_played' => 3, 'opponent_win_percentage' => 0.5, 'had_bye' => false],
            ['entry_id' => 3, 'wins' => 2, 'matches_played' => 3, 'opponent_win_percentage' => 0.4, 'had_bye' => false],
            ['entry_id' => 4, 'wins' => 1, 'matches_played' => 3, 'opponent_win_percentage' => 0.4, 'had_bye' => false],
            ['entry_id' => 5, 'wins' => 1, 'matches_played' => 3, 'opponent_win_percentage' => 0.3, 'had_bye' => false],
        ];
        // Entry 1 has already faced 2, 3, and 4 — only 5 is a fresh opponent for entry 1.
        $previousMatchups = [
            1 => [2, 3, 4], 2 => [1], 3 => [1], 4 => [1], 5 => [],
        ];

        $pairs = TournamentPairing::pairSwissRound($standings, $previousMatchups);

        foreach ($pairs as [$a, $b]) {
            if ($b !== null) {
                $this->assertNotContains($b, $previousMatchups[$a] ?? [], "Entries $a and $b were paired despite having already faced each other.");
            }
        }
    }

    public function test_swiss_round_bye_falls_back_to_lowest_ranked_when_everyone_already_had_a_bye(): void
    {
        $standings = [
            ['entry_id' => 1, 'wins' => 3, 'matches_played' => 3, 'opponent_win_percentage' => 0.6, 'had_bye' => true],
            ['entry_id' => 2, 'wins' => 2, 'matches_played' => 3, 'opponent_win_percentage' => 0.5, 'had_bye' => true],
            ['entry_id' => 3, 'wins' => 1, 'matches_played' => 3, 'opponent_win_percentage' => 0.3, 'had_bye' => true],
        ];
        $previousMatchups = [1 => [], 2 => [], 3 => []];

        $pairs = TournamentPairing::pairSwissRound($standings, $previousMatchups);

        $byes = collect($pairs)->filter(fn ($pair) => $pair[1] === null)->flatten()->filter();
        $this->assertSame([3], $byes->values()->all(), 'When everyone has already had a bye, it should go to the lowest-ranked entry (3), not the highest-ranked (1).');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TournamentPairingTest`
Expected: FAIL (method `pairSwissRound` doesn't exist)

- [ ] **Step 3: Implement `pairSwissRound`**

Add to `app/Support/TournamentPairing.php` (inside the class, after `pairFirstRound`):
```php
    /**
     * @param  array<int, array{entry_id:int, wins:int, matches_played:int, opponent_win_percentage:float, had_bye:bool}>  $standings  ordered best-to-worst
     * @param  array<int, array<int>>  $previousMatchups
     * @return array<int, array{0:int,1:int|null}>
     */
    public static function pairSwissRound(array $standings, array $previousMatchups): array
    {
        $pool = array_column($standings, null, 'entry_id');
        $order = array_column($standings, 'entry_id');

        $byeEntry = null;
        if (count($order) % 2 === 1) {
            $byeEntry = self::selectByeEntry($order, $pool, $previousMatchups);
            $order = array_values(array_diff($order, [$byeEntry]));
        }

        $remaining = $order;
        $pairs = [];

        while (count($remaining) > 0) {
            $current = array_shift($remaining);
            $opponentIndex = self::findOpponent($current, $remaining, $previousMatchups);
            $opponent = $remaining[$opponentIndex];
            array_splice($remaining, $opponentIndex, 1);
            $pairs[] = [$current, $opponent];
        }

        if ($byeEntry !== null) {
            $pairs[] = [$byeEntry, null];
        }

        return $pairs;
    }

    /** @param  array<int>  $remaining */
    private static function findOpponent(int $current, array $remaining, array $previousMatchups): int
    {
        $faced = $previousMatchups[$current] ?? [];

        foreach ($remaining as $index => $candidate) {
            if (! in_array($candidate, $faced, true)) {
                return $index;
            }
        }

        // No rematch-free candidate available (small field) — take the next one anyway.
        return 0;
    }

    /**
     * Lowest-ranked entry without a previous bye; tries to avoid selecting an entry
     * whose removal would force others into unavoidable rematches. Falls back to the
     * lowest-ranked entry overall if all remaining entries have had a bye.
     *
     * @param  array<int>  $order  entry_ids, best-to-worst
     * @param  array<int, array{entry_id:int, had_bye:bool}>  $pool
     * @param  array<int, array<int>>  $previousMatchups
     */
    private static function selectByeEntry(array $order, array $pool, array $previousMatchups): int
    {
        $candidates = array_reverse($order);
        $preferred = null;
        $fallback = null;

        foreach ($candidates as $entryId) {
            if (! ($pool[$entryId]['had_bye'] ?? false)) {
                if ($preferred === null) {
                    $preferred = $entryId;
                }

                // Check if removing this entry would force any remaining entry into all-rematches.
                $testOrder = array_values(array_diff($order, [$entryId]));
                $forced = false;

                foreach ($testOrder as $candidate) {
                    $faced = $previousMatchups[$candidate] ?? [];
                    $available = array_diff($testOrder, [$candidate]);
                    $hasFresh = false;

                    foreach ($available as $opponent) {
                        if (! in_array($opponent, $faced, true)) {
                            $hasFresh = true;
                            break;
                        }
                    }

                    if (! $hasFresh) {
                        $forced = true;
                        break;
                    }
                }

                if (! $forced) {
                    return $entryId;
                }
            } else {
                $fallback ??= $entryId;
            }
        }

        return $preferred ?? $fallback ?? end($order);
    }
```

> **Note (post-implementation correction, applied during execution — see the SDD ledger for this plan):**
> the version above is the corrected implementation. The bye recipient is chosen *before* pairing
> runs and removed from the pool, instead of pairing everyone first and swapping the bye in
> afterward — an earlier draft that did the swap-after-the-fact could silently reintroduce a
> rematch the greedy walk had already avoided. `selectByeEntry` also looks ahead to avoid
> starving another entry of every fresh opponent where it reasonably can, falling back to the
> plain lowest-ranked-without-a-bye entry when it can't find one that avoids that.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter TournamentPairingTest`
Expected: PASS (7 tests — includes 2 tests added during post-implementation bug fixes, see note above)

- [ ] **Step 5: Commit**

```bash
git add app/Support/TournamentPairing.php tests/Unit/TournamentPairingTest.php
git commit -m "feat: add TournamentPairing::pairSwissRound with rematch avoidance and bye rotation"
```

---

### Task 5: Pairing — elimination bracket

**Files:**
- Modify: `app/Support/TournamentPairing.php`
- Modify: `tests/Unit/TournamentPairingTest.php`

**Interfaces:**
- Produces: `TournamentPairing::seedEliminationBracket(array $standings, int $cutSize): array` — takes the top `$cutSize` of `$standings` (same shape as Task 4) and returns `array<int, array{0:int,1:int}>` seeded `1 vs last, 2 vs second-to-last, ...`.
- Produces: `TournamentPairing::advanceEliminationRound(array $winnerEntryIds): array` — `$winnerEntryIds` in bracket order; returns consecutive pairs `array<int, array{0:int,1:int}>`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/TournamentPairingTest.php`:
```php
    public function test_seed_elimination_bracket_pairs_top_seed_against_bottom_seed(): void
    {
        $standings = [
            ['entry_id' => 1, 'wins' => 3, 'matches_played' => 3, 'opponent_win_percentage' => 0.6, 'had_bye' => false],
            ['entry_id' => 2, 'wins' => 3, 'matches_played' => 3, 'opponent_win_percentage' => 0.5, 'had_bye' => false],
            ['entry_id' => 3, 'wins' => 2, 'matches_played' => 3, 'opponent_win_percentage' => 0.6, 'had_bye' => false],
            ['entry_id' => 4, 'wins' => 2, 'matches_played' => 3, 'opponent_win_percentage' => 0.5, 'had_bye' => false],
            ['entry_id' => 5, 'wins' => 1, 'matches_played' => 3, 'opponent_win_percentage' => 0.5, 'had_bye' => false],
        ];

        $pairs = TournamentPairing::seedEliminationBracket($standings, 4);

        $this->assertSame([[1, 4], [2, 3]], $pairs);
    }

    public function test_advance_elimination_round_pairs_consecutive_winners(): void
    {
        $pairs = TournamentPairing::advanceEliminationRound([10, 30, 20, 40]);

        $this->assertSame([[10, 30], [20, 40]], $pairs);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TournamentPairingTest`
Expected: FAIL (methods don't exist)

- [ ] **Step 3: Implement both methods**

Add to `app/Support/TournamentPairing.php` (inside the class):
```php
    /**
     * @param  array<int, array{entry_id:int, wins:int, matches_played:int, opponent_win_percentage:float, had_bye:bool}>  $standings  ordered best-to-worst
     * @return array<int, array{0:int,1:int}>
     */
    public static function seedEliminationBracket(array $standings, int $cutSize): array
    {
        $seeds = array_column(array_slice($standings, 0, $cutSize), 'entry_id');

        $pairs = [];
        $low = 0;
        $high = count($seeds) - 1;

        while ($low < $high) {
            $pairs[] = [$seeds[$low], $seeds[$high]];
            $low++;
            $high--;
        }

        return $pairs;
    }

    /**
     * @param  array<int,int>  $winnerEntryIds  in bracket order
     * @return array<int, array{0:int,1:int}>
     */
    public static function advanceEliminationRound(array $winnerEntryIds): array
    {
        $pairs = [];

        for ($i = 0; $i < count($winnerEntryIds); $i += 2) {
            $pairs[] = [$winnerEntryIds[$i], $winnerEntryIds[$i + 1]];
        }

        return $pairs;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter TournamentPairingTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Support/TournamentPairing.php tests/Unit/TournamentPairingTest.php
git commit -m "feat: add TournamentPairing bracket seeding and elimination round advance"
```

---

### Task 6: Standings service

**Files:**
- Create: `app/Support/TournamentStandings.php`
- Test: `tests/Feature/TournamentStandingsTest.php`

**Interfaces:**
- Consumes: `App\Models\Tournament` (Task 2), `App\Models\TournamentEntry`, `App\Models\TournamentMatch`.
- Produces: `TournamentStandings::forTournament(Tournament $tournament): array` returning the exact shape consumed by `TournamentPairing::pairSwissRound`/`seedEliminationBracket`: `array<int, array{entry_id:int, wins:int, matches_played:int, opponent_win_percentage:float, had_bye:bool}>`, sorted `wins` desc → `opponent_win_percentage` desc → `entry_id` asc.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use App\Support\TournamentStandings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentStandingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(Tournament $tournament): \App\Models\TournamentEntry
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    public function test_computes_wins_matches_played_and_opponent_win_percentage(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 2, 'created_by_user_id' => User::factory()->create()->id,
        ]);

        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $c = $this->makeEntry($tournament);
        $d = $this->makeEntry($tournament);

        // Round 1: a beats b, c beats d.
        $round1 = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round1->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);
        $round1->matches()->create(['entry_one_id' => $c->id, 'entry_two_id' => $d->id, 'winner_entry_id' => $c->id]);

        // Round 2: a beats c (a is now 2-0), b gets a bye (counts as a win but not a "match").
        $round2 = $tournament->rounds()->create(['number' => 2, 'phase' => 'swiss']);
        $round2->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $c->id, 'winner_entry_id' => $a->id]);
        $round2->matches()->create(['entry_one_id' => $b->id, 'entry_two_id' => null, 'winner_entry_id' => $b->id, 'is_bye' => true]);
        $round2->matches()->create(['entry_one_id' => $d->id, 'entry_two_id' => null, 'winner_entry_id' => null]);
        // d's match in round 2 is unreported on purpose: it must not count toward standings.

        $standings = TournamentStandings::forTournament($tournament);
        $byId = collect($standings)->keyBy('entry_id');

        $this->assertSame(2, $byId[$a->id]['wins']);
        $this->assertSame(2, $byId[$a->id]['matches_played']);

        $this->assertSame(1, $byId[$b->id]['wins']);
        $this->assertTrue($byId[$b->id]['had_bye']);
        $this->assertSame(1, $byId[$b->id]['matches_played']); // only the round-1 match counts

        // a's opponents were b (0 real wins / 1 match = 0.0) and c (1 real win / 2 matches = 0.5);
        // average = 0.25.
        $this->assertEqualsWithDelta(0.25, $byId[$a->id]['opponent_win_percentage'], 0.001);

        // Standings ordered by wins desc: a (2) before b and c (1 each) before d (0).
        $this->assertSame($a->id, $standings[0]['entry_id']);
    }

    public function test_entry_id_is_the_final_ascending_tiebreak_when_wins_and_opponent_win_percentage_tie(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa Y', 'status' => 'swiss', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);

        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $c = $this->makeEntry($tournament);
        $d = $this->makeEntry($tournament);

        // Two independent 1-0 results, no shared opponents: a/b both end up 1-0 with
        // opponent_win_percentage 0.0 (their opponents lost their only match, 0/1 wins).
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $c->id, 'winner_entry_id' => $a->id]);
        $round->matches()->create(['entry_one_id' => $b->id, 'entry_two_id' => $d->id, 'winner_entry_id' => $b->id]);

        $standings = TournamentStandings::forTournament($tournament);
        $byId = collect($standings)->keyBy('entry_id');

        $this->assertEqualsWithDelta($byId[$a->id]['opponent_win_percentage'], $byId[$b->id]['opponent_win_percentage'], 0.001);
        $this->assertSame($byId[$a->id]['wins'], $byId[$b->id]['wins']);

        // a and b are fully tied (same wins, same opponent_win_percentage) — the lower
        // entry_id must sort first.
        $topTwoIds = collect($standings)->take(2)->pluck('entry_id')->all();
        $this->assertSame([min($a->id, $b->id), max($a->id, $b->id)], $topTwoIds);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TournamentStandingsTest`
Expected: FAIL with "Class App\Support\TournamentStandings not found"

- [ ] **Step 3: Implement the service**

`app/Support/TournamentStandings.php`:
```php
<?php

namespace App\Support;

use App\Models\Tournament;

class TournamentStandings
{
    /**
     * @return array<int, array{entry_id:int, wins:int, matches_played:int, opponent_win_percentage:float, had_bye:bool}>
     */
    public static function forTournament(Tournament $tournament): array
    {
        $entryIds = $tournament->entries()->pluck('id');
        $matches = $tournament->rounds()->with('matches')->get()->flatMap->matches;

        $stats = [];
        foreach ($entryIds as $entryId) {
            $stats[$entryId] = [
                'entry_id' => $entryId,
                'wins' => 0,
                'match_wins' => 0,
                'matches_played' => 0,
                'had_bye' => false,
                'opponents' => [],
            ];
        }

        foreach ($matches as $match) {
            if ($match->winner_entry_id === null) {
                continue;
            }

            if ($match->is_bye) {
                $stats[$match->entry_one_id]['wins']++;
                $stats[$match->entry_one_id]['had_bye'] = true;
                continue;
            }

            $stats[$match->entry_one_id]['matches_played']++;
            $stats[$match->entry_two_id]['matches_played']++;
            $stats[$match->entry_one_id]['opponents'][] = $match->entry_two_id;
            $stats[$match->entry_two_id]['opponents'][] = $match->entry_one_id;

            $stats[$match->winner_entry_id]['wins']++;
            $stats[$match->winner_entry_id]['match_wins']++;
        }

        foreach ($stats as &$row) {
            $percentages = array_map(function (int $opponentId) use ($stats) {
                $opponent = $stats[$opponentId];

                return $opponent['matches_played'] > 0
                    ? $opponent['match_wins'] / $opponent['matches_played']
                    : 0.0;
            }, $row['opponents']);

            $row['opponent_win_percentage'] = count($percentages) > 0
                ? array_sum($percentages) / count($percentages)
                : 0.0;
        }
        unset($row);

        foreach ($stats as &$row) {
            unset($row['opponents'], $row['match_wins']);
        }
        unset($row);

        $rows = array_values($stats);

        usort($rows, fn ($a, $b) => [$b['wins'], $b['opponent_win_percentage'], $a['entry_id']]
            <=> [$a['wins'], $a['opponent_win_percentage'], $b['entry_id']]);

        return $rows;
    }
}
```

> **Note (post-implementation correction, applied during execution — see the SDD ledger for
> this plan):** the version above fixes two bugs found during Task 6's review loop in the
> original reference code: (1) the `unset()` calls ran inside the same loop that computes
> `opponent_win_percentage`, and because the loop variable is a reference into `$stats`, an
> earlier entry's `match_wins` could be stripped before a later entry read it as an opponent —
> moved to a separate loop that runs only after every percentage is computed; (2) the final
> `entry_id` tiebreak used a `-$a['entry_id']`/`-$b['entry_id']` negation that actually sorted
> descending instead of the required ascending — the negation is removed. The plan's own test
> fixture below is also corrected: entry `a`'s `opponent_win_percentage` is `0.25`, not the
> `0.0` originally asserted (hand-derivation error in the original plan text).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter TournamentStandingsTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Support/TournamentStandings.php tests/Feature/TournamentStandingsTest.php
git commit -m "feat: add TournamentStandings::forTournament"
```

---

### Task 7: Admin middleware

**Files:**
- Create: `app/Http/Middleware/EnsureUserIsAdmin.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/EnsureUserIsAdminTest.php`

**Interfaces:**
- Produces: middleware alias `'admin'` usable in routes; 403 for authenticated non-admins.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureUserIsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'admin'])->get('/__admin_only_probe', fn () => 'ok');
    }

    public function test_blocks_non_admin_users(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/__admin_only_probe')->assertForbidden();
    }

    public function test_allows_admin_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/__admin_only_probe')->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter EnsureUserIsAdminTest`
Expected: FAIL — middleware alias `'admin'` doesn't exist yet (route registration throws or 500s)

- [ ] **Step 3: Create the middleware and register the alias**

`app/Http/Middleware/EnsureUserIsAdmin.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return $next($request);
    }
}
```

In `bootstrap/app.php`, add the alias inside `->withMiddleware(function (Middleware $middleware): void { ... })`:
```php
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
```
(Add this as a new statement alongside the existing `$middleware->web(...)` calls, inside the same closure.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter EnsureUserIsAdminTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/EnsureUserIsAdmin.php bootstrap/app.php tests/Feature/EnsureUserIsAdminTest.php
git commit -m "feat: add admin middleware for tournament management routes"
```

---

### Task 8: Pairing-email notification

**Files:**
- Create: `app/Notifications/PairedForRound.php`
- Test: `tests/Feature/PairedForRoundTest.php`

**Interfaces:**
- Produces: `new PairedForRound(string $opponentNickname, int $roundNumber)`, mail-only notification, sent via `$user->notify(...)` (User already has `Notifiable`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PairedForRound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PairedForRoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_a_mail_notification_naming_the_opponent_and_round(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $user->notify(new PairedForRound('RivalNick', 2));

        Notification::assertSentTo($user, PairedForRound::class, function (PairedForRound $notification) use ($user) {
            $mail = $notification->toMail($user);

            return str_contains($mail->subject, 'Ronda 2')
                && str_contains(implode(' ', $mail->introLines), 'RivalNick');
        });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter PairedForRoundTest`
Expected: FAIL with "Class App\Notifications\PairedForRound not found"

- [ ] **Step 3: Implement the notification**

`app/Notifications/PairedForRound.php`:
```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PairedForRound extends Notification
{
    public function __construct(
        private readonly string $opponentNickname,
        private readonly int $roundNumber,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("BxTorneum — Ronda {$this->roundNumber}: te toca contra {$this->opponentNickname}")
            ->greeting("¡Hola {$notifiable->nickname}!")
            ->line("En la ronda {$this->roundNumber} del torneo te toca jugar contra {$this->opponentNickname}.")
            ->action('Ver torneo', route('tournament.show'))
            ->line('¡Mucha suerte!');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter PairedForRoundTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Notifications/PairedForRound.php tests/Feature/PairedForRoundTest.php
git commit -m "feat: add PairedForRound mail notification"
```

---

### Task 9: Round factory (shared round + notification creation)

**Files:**
- Create: `app/Support/TournamentRoundFactory.php`
- Test: `tests/Feature/TournamentRoundFactoryTest.php`

**Interfaces:**
- Consumes: `Tournament` (Task 2), `PairedForRound` (Task 8), pair arrays shaped like `TournamentPairing`'s output.
- Produces: `TournamentRoundFactory::create(Tournament $tournament, string $phase, array $pairs): TournamentRound`. Side effects: creates the round + its matches (bye matches get `winner_entry_id` auto-filled), sets `tournament.status = $phase` and `tournament.current_round = $round->number`, and notifies both players of every non-bye match. A failed notification is logged and does not throw.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use App\Notifications\PairedForRound;
use App\Support\TournamentRoundFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TournamentRoundFactoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(Tournament $tournament): \App\Models\TournamentEntry
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    public function test_creates_round_and_matches_updates_tournament_and_notifies_players(): void
    {
        Notification::fake();

        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);

        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $c = $this->makeEntry($tournament);

        $round = TournamentRoundFactory::create($tournament, 'swiss', [[$a->id, $b->id], [$c->id, null]]);

        $this->assertSame(1, $round->number);
        $this->assertSame('swiss', $round->phase);
        $this->assertCount(2, $round->matches);

        $tournament->refresh();
        $this->assertSame('swiss', $tournament->status);
        $this->assertSame(1, $tournament->current_round);

        $byeMatch = $round->matches->firstWhere('is_bye', true);
        $this->assertSame($c->id, $byeMatch->winner_entry_id);

        Notification::assertSentTo($a->user, PairedForRound::class);
        Notification::assertSentTo($b->user, PairedForRound::class);
        Notification::assertNotSentTo($c->user, PairedForRound::class);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TournamentRoundFactoryTest`
Expected: FAIL with "Class App\Support\TournamentRoundFactory not found"

- [ ] **Step 3: Implement the factory**

`app/Support/TournamentRoundFactory.php`:
```php
<?php

namespace App\Support;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentRound;
use App\Notifications\PairedForRound;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TournamentRoundFactory
{
    /**
     * @param  array<int, array{0:int,1:int|null}>  $pairs  entry_id pairs; null = bye
     */
    public static function create(Tournament $tournament, string $phase, array $pairs): TournamentRound
    {
        $round = DB::transaction(function () use ($tournament, $phase, $pairs) {
            $round = $tournament->rounds()->create([
                'number' => $tournament->current_round + 1,
                'phase' => $phase,
            ]);

            foreach ($pairs as [$entryOneId, $entryTwoId]) {
                $round->matches()->create([
                    'entry_one_id' => $entryOneId,
                    'entry_two_id' => $entryTwoId,
                    'winner_entry_id' => $entryTwoId === null ? $entryOneId : null,
                    'is_bye' => $entryTwoId === null,
                ]);
            }

            $tournament->update([
                'status' => $phase,
                'current_round' => $round->number,
            ]);

            return $round;
        });

        self::notifyPlayers($round);

        return $round;
    }

    private static function notifyPlayers(TournamentRound $round): void
    {
        $round->load(['matches.entryOne.user', 'matches.entryTwo.user']);

        foreach ($round->matches as $match) {
            if ($match->is_bye) {
                continue;
            }

            self::notifyOne($match->entryOne, $match->entryTwo, $round->number);
            self::notifyOne($match->entryTwo, $match->entryOne, $round->number);
        }
    }

    private static function notifyOne(TournamentEntry $entry, TournamentEntry $opponent, int $roundNumber): void
    {
        try {
            $entry->user->notify(new PairedForRound($opponent->user->nickname, $roundNumber));
        } catch (Throwable $e) {
            Log::warning("No se pudo notificar a {$entry->user->email} de su emparejamiento en la ronda {$roundNumber}: {$e->getMessage()}");
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter TournamentRoundFactoryTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Support/TournamentRoundFactory.php tests/Feature/TournamentRoundFactoryTest.php
git commit -m "feat: add TournamentRoundFactory to create rounds and notify players"
```

---

### Task 10: Create tournament

**Files:**
- Create: `app/Http/Requests/StoreTournamentRequest.php`
- Create: `app/Http/Controllers/TournamentController.php` (method `store` only for now)
- Modify: `routes/web.php`
- Test: `tests/Feature/TournamentCreationTest.php`

**Interfaces:**
- Produces: `POST /tournament` (name `tournament.store`, `admin` + `auth` middleware) → creates a `Tournament` with `status = 'registration'`, `current_round = 0`, `created_by_user_id = auth()->id()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->post('/tournament', ['name' => 'Copa X', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertRedirect('/login');
    }

    public function test_non_admin_cannot_create_a_tournament(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->post('/tournament', ['name' => 'Copa X', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertForbidden();
    }

    public function test_admin_can_create_a_tournament(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post('/tournament', ['name' => 'Copa X', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertRedirect(route('tournament.show'));

        $this->assertDatabaseHas('tournaments', [
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_cut_size_must_be_a_power_of_two(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post('/tournament', ['name' => 'Copa X', 'swiss_rounds' => 3, 'cut_size' => 5])
            ->assertSessionHasErrors('cut_size');
    }

    public function test_cannot_create_a_second_tournament_while_one_is_active(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Tournament::create([
            'name' => 'Existente', 'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post('/tournament', ['name' => 'Copa Y', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TournamentCreationTest`
Expected: FAIL (route `/tournament` doesn't exist → 404)

- [ ] **Step 3: Implement the request, controller, and route**

`app/Http/Requests/StoreTournamentRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the 'admin' route middleware already gates this action
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'swiss_rounds' => ['required', 'integer', 'min:1', 'max:20'],
            'cut_size' => ['required', 'integer', 'in:2,4,8,16'],
        ];
    }
}
```

`app/Http/Controllers/TournamentController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTournamentRequest;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;

class TournamentController extends Controller
{
    public function store(StoreTournamentRequest $request): RedirectResponse
    {
        abort_if(
            Tournament::where('status', '!=', 'completed')->exists(),
            422,
            'Ya hay un torneo activo.'
        );

        $tournament = Tournament::create([
            'name' => $request->validated('name'),
            'status' => 'registration',
            'swiss_rounds' => $request->validated('swiss_rounds'),
            'cut_size' => $request->validated('cut_size'),
            'current_round' => 0,
            'created_by_user_id' => auth()->id(),
        ]);

        return redirect()->route('tournament.show')->with('success', "Torneo \"{$tournament->name}\" creado.");
    }
}
```

In `routes/web.php`, add the import and route (inside the existing `Route::middleware('auth')->group(...)` block, near the other feature routes):
```php
use App\Http\Controllers\TournamentController;
```
```php
    Route::middleware('admin')->group(function () {
        Route::post('/tournament', [TournamentController::class, 'store'])->name('tournament.store');
    });
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter TournamentCreationTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/StoreTournamentRequest.php app/Http/Controllers/TournamentController.php routes/web.php tests/Feature/TournamentCreationTest.php
git commit -m "feat: add tournament creation endpoint (admin only, one active at a time)"
```

---

### Task 11: Join tournament

**Files:**
- Modify: `app/Http/Controllers/TournamentController.php` (add `join`)
- Modify: `routes/web.php`
- Test: `tests/Feature/TournamentJoinTest.php`

**Interfaces:**
- Produces: `POST /tournament/join` (name `tournament.join`, `auth` only — any logged-in user).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentJoinTest extends TestCase
{
    use RefreshDatabase;

    private function openTournament(): Tournament
    {
        return Tournament::create([
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
    }

    public function test_user_without_a_tournament_deck_cannot_join(): void
    {
        $tournament = $this->openTournament();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/tournament/join')->assertStatus(422);
        $this->assertDatabaseCount('tournament_entries', 0);
    }

    public function test_user_with_a_tournament_deck_joins_with_a_snapshot_of_that_deck(): void
    {
        $tournament = $this->openTournament();
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Mi torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        $this->actingAs($user)->post('/tournament/join')->assertRedirect();

        $this->assertDatabaseHas('tournament_entries', [
            'tournament_id' => $tournament->id, 'user_id' => $user->id, 'deck_id' => $deck->id,
        ]);
    }

    public function test_user_cannot_join_twice(): void
    {
        $tournament = $this->openTournament();
        $user = User::factory()->create();
        $user->decks()->create(['name' => 'Mi torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        $this->actingAs($user)->post('/tournament/join');
        $this->actingAs($user)->post('/tournament/join')->assertStatus(422);

        $this->assertDatabaseCount('tournament_entries', 1);
    }

    public function test_cannot_join_after_registration_closed(): void
    {
        $tournament = $this->openTournament();
        $tournament->update(['status' => 'swiss', 'current_round' => 1]);
        $user = User::factory()->create();
        $user->decks()->create(['name' => 'Mi torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        $this->actingAs($user)->post('/tournament/join')->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TournamentJoinTest`
Expected: FAIL (route doesn't exist)

- [ ] **Step 3: Implement `join` and the route**

Add to `app/Http/Controllers/TournamentController.php` (add imports for `TournamentEntry` and `Illuminate\Http\Request` at the top, then the method):
```php
use App\Models\TournamentEntry;
use Illuminate\Http\Request;
```
```php
    public function join(Request $request): RedirectResponse
    {
        $tournament = Tournament::where('status', '!=', 'completed')->firstOrFail();

        abort_unless($tournament->status === 'registration', 422, 'Las inscripciones ya cerraron.');

        abort_if(
            TournamentEntry::where('tournament_id', $tournament->id)->where('user_id', auth()->id())->exists(),
            422,
            'Ya estás inscrito.'
        );

        $deck = auth()->user()->decks()->where('is_tournament_deck', true)->first();
        abort_unless($deck, 422, 'Marca un deck como torneo antes de unirte.');

        TournamentEntry::create([
            'tournament_id' => $tournament->id,
            'user_id' => auth()->id(),
            'deck_id' => $deck->id,
        ]);

        return back()->with('success', 'Te uniste al torneo.');
    }
```

In `routes/web.php`, add (in the `auth` group, outside the `admin` sub-group):
```php
    Route::post('/tournament/join', [TournamentController::class, 'join'])->name('tournament.join');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter TournamentJoinTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/TournamentController.php routes/web.php tests/Feature/TournamentJoinTest.php
git commit -m "feat: add tournament join endpoint (requires a marked tournament deck)"
```

---

### Task 12: Report match result

**Files:**
- Create: `app/Http/Controllers/TournamentMatchController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TournamentMatchControllerTest.php`

**Interfaces:**
- Produces: `PATCH /tournament/matches/{match}` (name `tournament.matches.update`, `auth` + `admin`). Body: `{ winner_entry_id: int }`. Sets `tournament_matches.winner_entry_id`; if the match's round belongs to an `elimination`-phase tournament and that round has exactly one match (i.e. it was the final), also sets `tournament.status = 'completed'` and `tournament.champion_entry_id = winner_entry_id`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentMatchControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(Tournament $tournament): \App\Models\TournamentEntry
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    private function swissTournament(): Tournament
    {
        return Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
    }

    public function test_non_admin_cannot_report_a_result(): void
    {
        $tournament = $this->swissTournament();
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $match = $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id]);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $a->id])
            ->assertForbidden();
    }

    public function test_admin_reports_a_swiss_match_result(): void
    {
        $tournament = $this->swissTournament();
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $match = $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $a->id])
            ->assertRedirect();

        $this->assertDatabaseHas('tournament_matches', ['id' => $match->id, 'winner_entry_id' => $a->id]);
        $this->assertSame('swiss', $tournament->fresh()->status); // reporting a swiss match never completes the tournament
    }

    public function test_cannot_report_a_result_twice(): void
    {
        $tournament = $this->swissTournament();
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $match = $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $b->id])
            ->assertStatus(422);
    }

    public function test_winner_must_be_one_of_the_two_entries_in_the_match(): void
    {
        $tournament = $this->swissTournament();
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $stranger = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $match = $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $stranger->id])
            ->assertStatus(422);
    }

    public function test_reporting_the_final_completes_the_tournament_with_a_champion(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'elimination', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 3, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $finalRound = $tournament->rounds()->create(['number' => 3, 'phase' => 'elimination']);
        $final = $finalRound->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->patch("/tournament/matches/{$final->id}", ['winner_entry_id' => $a->id])
            ->assertRedirect();

        $tournament->refresh();
        $this->assertSame('completed', $tournament->status);
        $this->assertSame($a->id, $tournament->champion_entry_id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TournamentMatchControllerTest`
Expected: FAIL (route doesn't exist)

- [ ] **Step 3: Implement the controller and route**

`app/Http/Controllers/TournamentMatchController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\TournamentMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentMatchController extends Controller
{
    public function update(Request $request, TournamentMatch $match): RedirectResponse
    {
        abort_if($match->is_bye, 422, 'Un bye no se reporta.');
        abort_unless($match->winner_entry_id === null, 422, 'Este duelo ya tiene resultado.');

        $winnerId = (int) $request->validate([
            'winner_entry_id' => ['required', 'integer'],
        ])['winner_entry_id'];

        abort_unless(
            in_array($winnerId, [$match->entry_one_id, $match->entry_two_id], true),
            422,
            'Ese jugador no está en este duelo.'
        );

        DB::transaction(function () use ($match, $winnerId) {
            $match->update(['winner_entry_id' => $winnerId]);

            $round = $match->round;
            $tournament = $round->tournament;

            if ($tournament->status === 'elimination' && $round->matches()->count() === 1) {
                $tournament->update([
                    'status' => 'completed',
                    'champion_entry_id' => $winnerId,
                ]);
            }
        });

        return back()->with('success', 'Resultado guardado.');
    }
}
```

In `routes/web.php`, add the import and route inside the existing `admin` sub-group from Task 10:
```php
use App\Http\Controllers\TournamentMatchController;
```
```php
    Route::middleware('admin')->group(function () {
        Route::post('/tournament', [TournamentController::class, 'store'])->name('tournament.store');
        Route::patch('/tournament/matches/{match}', [TournamentMatchController::class, 'update'])->name('tournament.matches.update');
    });
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter TournamentMatchControllerTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/TournamentMatchController.php routes/web.php tests/Feature/TournamentMatchControllerTest.php
git commit -m "feat: add match result reporting, completing the tournament on the final"
```

---

### Task 13: Generate next round (state machine)

**Files:**
- Create: `app/Http/Controllers/TournamentRoundController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TournamentRoundControllerTest.php`

**Interfaces:**
- Consumes: `TournamentPairing::pairFirstRound/pairSwissRound/advanceEliminationRound` (Tasks 3-5), `TournamentStandings::forTournament` (Task 6), `TournamentRoundFactory::create` (Task 9).
- Produces: `POST /tournament/rounds` (name `tournament.rounds.store`, `auth` + `admin`). Behavior depends on `tournament.status`:
  - `registration` → generates round 1 (`pairFirstRound`), moves tournament to `swiss`.
  - `swiss` with `current_round < swiss_rounds` → generates the next Swiss round (`pairSwissRound`); 422 if the current round has unreported matches; 422 if `current_round == swiss_rounds` (must cut instead).
  - `elimination` with more than one match remaining in the round → generates the next bracket round (`advanceEliminationRound`); 422 if unreported matches remain; 422 if the current round was the final.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentRoundControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(Tournament $tournament): \App\Models\TournamentEntry
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    public function test_non_admin_cannot_generate_a_round(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post('/tournament/rounds')
            ->assertForbidden();
    }

    public function test_generates_round_one_from_registration(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $this->makeEntry($tournament);
        $this->makeEntry($tournament);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/rounds')
            ->assertRedirect();

        $tournament->refresh();
        $this->assertSame('swiss', $tournament->status);
        $this->assertSame(1, $tournament->current_round);
        $this->assertSame(1, $tournament->rounds()->count());
    }

    public function test_cannot_generate_the_next_swiss_round_while_results_are_pending(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id]); // no winner yet

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/rounds')
            ->assertStatus(422);
    }

    public function test_generates_the_next_swiss_round_once_results_are_in(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/rounds')
            ->assertRedirect();

        $tournament->refresh();
        $this->assertSame(2, $tournament->current_round);
        $this->assertSame(2, $tournament->rounds()->count());
    }

    public function test_cannot_generate_a_round_past_the_configured_swiss_rounds(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/rounds')
            ->assertStatus(422);
    }

    public function test_generates_the_next_elimination_round_from_previous_winners(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'elimination', 'swiss_rounds' => 1, 'cut_size' => 4,
            'current_round' => 2, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $c = $this->makeEntry($tournament);
        $d = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 2, 'phase' => 'elimination']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);
        $round->matches()->create(['entry_one_id' => $c->id, 'entry_two_id' => $d->id, 'winner_entry_id' => $c->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/rounds')
            ->assertRedirect();

        $tournament->refresh();
        $this->assertSame(3, $tournament->current_round);
        $newRound = $tournament->rounds()->where('number', 3)->first();
        $this->assertCount(1, $newRound->matches);
        $match = $newRound->matches->first();
        $this->assertEqualsCanonicalizing([$a->id, $c->id], [$match->entry_one_id, $match->entry_two_id]);
    }

    public function test_cannot_generate_a_round_after_the_final_was_played(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'elimination', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 2, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 2, 'phase' => 'elimination']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/rounds')
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TournamentRoundControllerTest`
Expected: FAIL (route doesn't exist)

- [ ] **Step 3: Implement the controller and route**

`app/Http/Controllers/TournamentRoundController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Support\TournamentPairing;
use App\Support\TournamentRoundFactory;
use App\Support\TournamentStandings;
use Illuminate\Http\RedirectResponse;

class TournamentRoundController extends Controller
{
    public function store(): RedirectResponse
    {
        $tournament = Tournament::where('status', '!=', 'completed')->firstOrFail();

        if ($tournament->status === 'registration') {
            $entryIds = $tournament->entries()->pluck('id');
            abort_if($entryIds->count() < 2, 422, 'Se necesitan al menos 2 jugadores inscritos.');

            $pairs = TournamentPairing::pairFirstRound($entryIds);
            TournamentRoundFactory::create($tournament, 'swiss', $pairs);

            return redirect()->route('tournament.show')->with('success', 'Ronda 1 generada.');
        }

        if ($tournament->status === 'swiss') {
            abort_if($tournament->current_round >= $tournament->swiss_rounds, 422, 'La fase suiza ya terminó; corta a eliminatorias.');
            $this->abortIfCurrentRoundHasPendingResults($tournament);

            $standings = TournamentStandings::forTournament($tournament);
            $pairs = TournamentPairing::pairSwissRound($standings, $this->previousMatchups($tournament));
            TournamentRoundFactory::create($tournament, 'swiss', $pairs);

            return redirect()->route('tournament.show')->with('success', "Ronda {$tournament->fresh()->current_round} generada.");
        }

        // elimination
        $this->abortIfCurrentRoundHasPendingResults($tournament);
        $currentRound = $tournament->rounds()->where('number', $tournament->current_round)->first();
        abort_if($currentRound->matches()->count() <= 1, 422, 'Esa era la final; reporta su resultado para cerrar el torneo.');

        $winnerIds = $currentRound->matches()->orderBy('id')->pluck('winner_entry_id')->all();
        $pairs = TournamentPairing::advanceEliminationRound($winnerIds);
        TournamentRoundFactory::create($tournament, 'elimination', $pairs);

        return redirect()->route('tournament.show')->with('success', 'Siguiente ronda de eliminatorias generada.');
    }

    private function abortIfCurrentRoundHasPendingResults(Tournament $tournament): void
    {
        $currentRound = $tournament->rounds()->where('number', $tournament->current_round)->first();

        abort_if(
            $currentRound && $currentRound->matches()->whereNull('winner_entry_id')->exists(),
            422,
            'Faltan resultados de la ronda actual.'
        );
    }

    /** @return array<int, array<int>> entry_id => list of entry_ids already faced */
    private function previousMatchups(Tournament $tournament): array
    {
        $matchups = [];

        $tournament->rounds()->with('matches')->get()->each(function ($round) use (&$matchups) {
            foreach ($round->matches as $match) {
                if ($match->is_bye) {
                    continue;
                }
                $matchups[$match->entry_one_id][] = $match->entry_two_id;
                $matchups[$match->entry_two_id][] = $match->entry_one_id;
            }
        });

        return $matchups;
    }
}
```

In `routes/web.php`, add the import and route inside the `admin` sub-group:
```php
use App\Http\Controllers\TournamentRoundController;
```
```php
        Route::post('/tournament/rounds', [TournamentRoundController::class, 'store'])->name('tournament.rounds.store');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter TournamentRoundControllerTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/TournamentRoundController.php routes/web.php tests/Feature/TournamentRoundControllerTest.php
git commit -m "feat: add round-generation state machine (registration, swiss, elimination)"
```

---

### Task 14: Cut to elimination

**Files:**
- Modify: `app/Http/Controllers/TournamentController.php` (add `cut`)
- Modify: `routes/web.php`
- Test: `tests/Feature/TournamentCutTest.php`

**Interfaces:**
- Produces: `POST /tournament/cut` (name `tournament.cut`, `auth` + `admin`). Guards: must be `swiss` status, must have completed all `swiss_rounds`, no pending results in the last Swiss round, enough entries for `cut_size`. On success: creates the first elimination round via `TournamentRoundFactory::create` seeded by `TournamentPairing::seedEliminationBracket`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentCutTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(Tournament $tournament): \App\Models\TournamentEntry
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    public function test_non_admin_cannot_cut_to_elimination(): void
    {
        Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post('/tournament/cut')
            ->assertForbidden();
    }

    public function test_cannot_cut_before_swiss_rounds_are_complete(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/cut')
            ->assertStatus(422);
    }

    public function test_cannot_cut_with_pending_results_in_the_last_round(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/cut')
            ->assertStatus(422);
    }

    public function test_cuts_to_the_top_seeded_elimination_round(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $c = $this->makeEntry($tournament);
        $d = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);
        $round->matches()->create(['entry_one_id' => $c->id, 'entry_two_id' => $d->id, 'winner_entry_id' => $c->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/cut')
            ->assertRedirect();

        $tournament->refresh();
        $this->assertSame('elimination', $tournament->status);
        $this->assertSame(2, $tournament->current_round);

        $eliminationRound = $tournament->rounds()->where('number', 2)->first();
        $this->assertCount(1, $eliminationRound->matches);
        $match = $eliminationRound->matches->first();
        $this->assertEqualsCanonicalizing([$a->id, $c->id], [$match->entry_one_id, $match->entry_two_id]);
    }

    public function test_cannot_cut_to_more_players_than_are_registered(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 1, 'cut_size' => 4,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/cut')
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TournamentCutTest`
Expected: FAIL (route doesn't exist)

- [ ] **Step 3: Implement `cut` and the route**

Add to `app/Http/Controllers/TournamentController.php` (add imports for `TournamentPairing`, `TournamentRoundFactory`, `TournamentStandings` at the top, then the method):
```php
use App\Support\TournamentPairing;
use App\Support\TournamentRoundFactory;
use App\Support\TournamentStandings;
```
```php
    public function cut(): RedirectResponse
    {
        $tournament = Tournament::where('status', '!=', 'completed')->firstOrFail();

        abort_unless($tournament->status === 'swiss', 422, 'El torneo no está en fase suiza.');
        abort_unless($tournament->current_round === $tournament->swiss_rounds, 422, 'Aún faltan rondas suizas por jugar.');

        $currentRound = $tournament->rounds()->where('number', $tournament->current_round)->first();
        abort_if(
            $currentRound->matches()->whereNull('winner_entry_id')->exists(),
            422,
            'Faltan resultados de la última ronda suiza.'
        );

        $standings = TournamentStandings::forTournament($tournament);
        abort_if(count($standings) < $tournament->cut_size, 422, 'No hay suficientes jugadores inscritos para este corte.');

        $pairs = TournamentPairing::seedEliminationBracket($standings, $tournament->cut_size);
        TournamentRoundFactory::create($tournament, 'elimination', $pairs);

        return redirect()->route('tournament.show')->with('success', 'Corte a eliminatorias generado.');
    }
```

In `routes/web.php`, add the route inside the `admin` sub-group:
```php
        Route::post('/tournament/cut', [TournamentController::class, 'cut'])->name('tournament.cut');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter TournamentCutTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/TournamentController.php routes/web.php tests/Feature/TournamentCutTest.php
git commit -m "feat: add cut-to-elimination endpoint with seeded bracket"
```

---

### Task 15: Show page (backend props)

**Files:**
- Modify: `app/Http/Controllers/TournamentController.php` (add `show`)
- Modify: `routes/web.php`
- Test: `tests/Feature/TournamentShowTest.php`

**Interfaces:**
- Produces: `GET /tournament` (name `tournament.show`, `auth` only). Renders Inertia component `Tournament/Show` with props:
  ```
  tournament: { id, name, status, swiss_rounds, cut_size, current_round, champion_nickname } | null
  has_tournament_deck: bool
  my_entry_id: int | null
  entries: [{ id, nickname }]
  standings: [{ entry_id, wins, matches_played, opponent_win_percentage, had_bye, nickname }]
  current_round_matches: [{ id, entry_one_id, entry_one_nickname, entry_two_id, entry_two_nickname|null, winner_entry_id|null, is_bye }]
  ```

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentShowTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(Tournament $tournament, string $nickname): \App\Models\TournamentEntry
    {
        $user = User::factory()->create(['nickname' => $nickname]);
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/tournament')->assertRedirect('/login');
    }

    public function test_shows_null_tournament_when_none_exists(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/tournament')
            ->assertInertia(fn ($page) => $page->component('Tournament/Show')->where('tournament', null));
    }

    public function test_shows_registration_state_with_entries_and_deck_flag(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $this->makeEntry($tournament, 'Nick1');

        $viewer = User::factory()->create();
        $viewer->decks()->create(['name' => 'Mi torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        $this->actingAs($viewer)->get('/tournament')->assertInertia(fn ($page) => $page
            ->component('Tournament/Show')
            ->where('tournament.status', 'registration')
            ->where('has_tournament_deck', true)
            ->where('my_entry_id', null)
            ->has('entries', 1)
            ->where('entries.0.nickname', 'Nick1')
        );
    }

    public function test_shows_current_round_matches_and_standings_during_swiss(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament, 'Alice');
        $b = $this->makeEntry($tournament, 'Bob');
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $this->actingAs($a->user)->get('/tournament')->assertInertia(fn ($page) => $page
            ->component('Tournament/Show')
            ->where('my_entry_id', $a->id)
            ->has('current_round_matches', 1)
            ->where('current_round_matches.0.entry_one_nickname', 'Alice')
            ->where('current_round_matches.0.entry_two_nickname', 'Bob')
            ->where('current_round_matches.0.winner_entry_id', $a->id)
            ->has('standings', 2)
            ->where('standings.0.nickname', 'Alice')
        );
    }

    public function test_shows_champion_nickname_when_completed(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'completed', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 2, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $champion = $this->makeEntry($tournament, 'Champ');
        $tournament->update(['champion_entry_id' => $champion->id]);

        $this->actingAs(User::factory()->create())->get('/tournament')->assertInertia(fn ($page) => $page
            ->where('tournament.status', 'completed')
            ->where('tournament.champion_nickname', 'Champ')
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TournamentShowTest`
Expected: FAIL (route doesn't exist)

- [ ] **Step 3: Implement `show` and the route**

Add to `app/Http/Controllers/TournamentController.php` (add `Inertia` import at the top):
```php
use Inertia\Inertia;
```
```php
    public function show()
    {
        $tournament = Tournament::where('status', '!=', 'completed')->latest()->first()
            ?? Tournament::where('status', 'completed')->latest()->first();

        if (! $tournament) {
            return Inertia::render('Tournament/Show', ['tournament' => null]);
        }

        $entries = $tournament->entries()->with('user:id,nickname')->get();
        $myEntry = $entries->firstWhere('user_id', auth()->id());

        $standings = $tournament->status !== 'registration'
            ? $this->standingsWithNicknames($tournament, $entries)
            : [];

        $currentRound = $tournament->rounds()
            ->where('number', $tournament->current_round)
            ->with('matches.entryOne.user:id,nickname', 'matches.entryTwo.user:id,nickname')
            ->first();

        return Inertia::render('Tournament/Show', [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'status' => $tournament->status,
                'swiss_rounds' => $tournament->swiss_rounds,
                'cut_size' => $tournament->cut_size,
                'current_round' => $tournament->current_round,
                'champion_nickname' => $tournament->champion?->user?->nickname,
            ],
            'has_tournament_deck' => auth()->user()->decks()->where('is_tournament_deck', true)->exists(),
            'my_entry_id' => $myEntry?->id,
            'entries' => $entries->map(fn ($entry) => [
                'id' => $entry->id,
                'nickname' => $entry->user->nickname,
            ])->values(),
            'standings' => $standings,
            'current_round_matches' => $currentRound
                ? $currentRound->matches->map(fn ($match) => [
                    'id' => $match->id,
                    'entry_one_id' => $match->entry_one_id,
                    'entry_one_nickname' => $match->entryOne->user->nickname,
                    'entry_two_id' => $match->entry_two_id,
                    'entry_two_nickname' => $match->entryTwo?->user->nickname,
                    'winner_entry_id' => $match->winner_entry_id,
                    'is_bye' => $match->is_bye,
                ])->values()
                : [],
        ]);
    }

    private function standingsWithNicknames(Tournament $tournament, $entries): array
    {
        $nicknames = $entries->pluck('user.nickname', 'id');

        return collect(TournamentStandings::forTournament($tournament))
            ->map(fn ($row) => [...$row, 'nickname' => $nicknames[$row['entry_id']] ?? '?'])
            ->values()
            ->all();
    }
```

In `routes/web.php`, add (in the `auth` group, outside the `admin` sub-group, alongside `tournament.join`):
```php
    Route::get('/tournament', [TournamentController::class, 'show'])->name('tournament.show');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter TournamentShowTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/TournamentController.php routes/web.php tests/Feature/TournamentShowTest.php
git commit -m "feat: add tournament show page props (registration/swiss/elimination/completed)"
```

---

### Task 16: Frontend — Tournament/Show.vue

**Files:**
- Create: `resources/js/Pages/Tournament/Show.vue`
- Test: `resources/js/Pages/__tests__/TournamentShow.test.js`

**Interfaces:**
- Consumes: props exactly as produced by Task 15's `TournamentController@show`.
- Consumes actions: `router.post('/tournament')` is not used directly — creation uses `useForm({...}).post('/tournament')`; `router.post('/tournament/join')`; `router.post('/tournament/rounds')`; `router.post('/tournament/cut')`; `router.patch('/tournament/matches/{id}', { winner_entry_id })`.
- Consumes: `page.props.auth.user.is_admin` via `usePage()` (shared globally by `HandleInertiaRequests`, already includes `is_admin` once Task 1 adds the column since `User` isn't `hidden`-restricting it).

- [ ] **Step 1: Write the failing tests**

`resources/js/Pages/__tests__/TournamentShow.test.js`:
```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Show from '../Tournament/Show.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
    router: { post: vi.fn(), patch: vi.fn() },
    useForm: (data) => ({ ...data, post: vi.fn(), processing: false, errors: {} }),
    usePage: vi.fn(() => ({ props: { auth: { user: { is_admin: false } } } })),
}));

describe('Tournament/Show', () => {
    it('shows the empty state when there is no tournament', () => {
        const wrapper = mount(Show, { props: { tournament: null } });
        expect(wrapper.text()).toContain('No hay torneo activo');
    });

    it('lets a player without an entry join when they have a tournament deck', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'registration', swiss_rounds: 3, cut_size: 4, current_round: 0, champion_nickname: null },
                has_tournament_deck: true,
                my_entry_id: null,
                entries: [],
                standings: [],
                current_round_matches: [],
            },
        });

        await wrapper.find('button').trigger('click');
        expect(router.post).toHaveBeenCalledWith('/tournament/join');
    });

    it('disables the join button without a tournament deck', () => {
        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'registration', swiss_rounds: 3, cut_size: 4, current_round: 0, champion_nickname: null },
                has_tournament_deck: false,
                my_entry_id: null,
                entries: [],
                standings: [],
                current_round_matches: [],
            },
        });

        expect(wrapper.find('button').attributes('disabled')).toBeDefined();
    });

    it("shows the player's opponent for the current round", () => {
        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'swiss', swiss_rounds: 3, cut_size: 4, current_round: 1, champion_nickname: null },
                has_tournament_deck: true,
                my_entry_id: 5,
                entries: [],
                standings: [],
                current_round_matches: [
                    { id: 10, entry_one_id: 5, entry_one_nickname: 'Yo', entry_two_id: 6, entry_two_nickname: 'Rival', winner_entry_id: null, is_bye: false },
                ],
            },
        });

        expect(wrapper.text()).toContain('Tu duelo esta ronda: vs Rival');
    });

    it('shows the champion banner when completed', () => {
        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'completed', swiss_rounds: 3, cut_size: 4, current_round: 3, champion_nickname: 'Ganador' },
                has_tournament_deck: true,
                my_entry_id: 5,
                entries: [],
                standings: [],
                current_round_matches: [],
            },
        });

        expect(wrapper.text()).toContain('Ganador');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- TournamentShow`
Expected: FAIL (component doesn't exist)

- [ ] **Step 3: Implement the page**

`resources/js/Pages/Tournament/Show.vue`:
```vue
<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tournament: { type: Object, default: null },
    has_tournament_deck: { type: Boolean, default: false },
    my_entry_id: { type: Number, default: null },
    entries: { type: Array, default: () => [] },
    standings: { type: Array, default: () => [] },
    current_round_matches: { type: Array, default: () => [] },
});

const page = usePage();
const isAdmin = computed(() => !!page.props.auth?.user?.is_admin);
const isRegistered = computed(() => props.my_entry_id !== null);

const createForm = useForm({ name: '', swiss_rounds: 3, cut_size: 4 });

function createTournament() {
    createForm.post('/tournament');
}

function join() {
    router.post('/tournament/join');
}

function generateRound() {
    router.post('/tournament/rounds');
}

function cutToElimination() {
    router.post('/tournament/cut');
}

function reportWinner(matchId, winnerEntryId) {
    router.patch(`/tournament/matches/${matchId}`, { winner_entry_id: winnerEntryId });
}

const myMatch = computed(() =>
    props.current_round_matches.find(
        (m) => m.entry_one_id === props.my_entry_id || m.entry_two_id === props.my_entry_id,
    ),
);

function opponentNickname(match) {
    if (!match) return null;
    return match.entry_one_id === props.my_entry_id ? match.entry_two_nickname : match.entry_one_nickname;
}
</script>

<template>
    <Head title="Torneo" />

    <h1 class="mb-6 text-3xl font-black"><span class="bx-gradient-text">Torneo</span></h1>

    <div v-if="!tournament" class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        <p class="mb-4">No hay torneo activo.</p>
        <form v-if="isAdmin" class="mx-auto max-w-sm space-y-4 text-left" @submit.prevent="createTournament">
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Nombre</label>
                <input
                    v-model="createForm.name"
                    type="text"
                    class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
                />
                <p v-if="createForm.errors.name" class="mt-1 text-xs text-bx-magenta">{{ createForm.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Rondas suizas</label>
                <input
                    v-model.number="createForm.swiss_rounds"
                    type="number"
                    min="1"
                    class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Corte a eliminatorias (top N)</label>
                <select
                    v-model.number="createForm.cut_size"
                    class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
                >
                    <option :value="2">Top 2</option>
                    <option :value="4">Top 4</option>
                    <option :value="8">Top 8</option>
                    <option :value="16">Top 16</option>
                </select>
            </div>
            <button
                type="submit"
                :disabled="createForm.processing"
                class="w-full rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
            >
                Crear torneo
            </button>
        </form>
    </div>

    <div v-else class="space-y-6">
        <div class="rounded-xl border border-white/10 bg-zinc-900/50 p-5">
            <h2 class="text-xl font-bold">{{ tournament.name }}</h2>
            <p class="mt-1 text-sm text-zinc-400">
                <span v-if="tournament.status === 'registration'">Inscripciones abiertas · {{ entries.length }} inscrito(s)</span>
                <span v-else-if="tournament.status === 'swiss'">Fase suiza · Ronda {{ tournament.current_round }} de {{ tournament.swiss_rounds }}</span>
                <span v-else-if="tournament.status === 'elimination'">Eliminatorias · Ronda {{ tournament.current_round }}</span>
                <span v-else>Torneo finalizado</span>
            </p>
        </div>

        <div v-if="tournament.status === 'completed'" class="rounded-xl border border-bx-cyan/40 bg-bx-cyan/10 p-6 text-center">
            <p class="text-sm text-zinc-300">Campeón</p>
            <p class="bx-gradient-text text-2xl font-black">{{ tournament.champion_nickname }}</p>
        </div>

        <div v-if="tournament.status === 'registration'" class="rounded-xl border border-white/10 bg-zinc-900/50 p-5">
            <ul class="mb-4 space-y-1 text-sm text-zinc-300">
                <li v-for="entry in entries" :key="entry.id">{{ entry.nickname }}</li>
            </ul>
            <button
                v-if="!isRegistered"
                type="button"
                :disabled="!has_tournament_deck"
                class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
                @click="join"
            >
                Unirme
            </button>
            <p v-if="!isRegistered && !has_tournament_deck" class="mt-2 text-xs text-zinc-400">
                Necesitas <Link href="/decks" class="text-bx-cyan hover:underline">marcar un deck de torneo</Link> antes de unirte.
            </p>
            <p v-else-if="isRegistered" class="text-sm text-bx-cyan">Ya estás inscrito.</p>
            <button
                v-if="isAdmin"
                type="button"
                class="mt-4 block rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
                @click="generateRound"
            >
                Cerrar inscripción y generar ronda 1
            </button>
        </div>

        <div v-if="tournament.status === 'swiss' || tournament.status === 'elimination'" class="rounded-xl border border-white/10 bg-zinc-900/50 p-5">
            <p v-if="myMatch && !myMatch.is_bye" class="mb-4 text-lg font-bold">
                Tu duelo esta ronda: vs <span class="text-bx-cyan">{{ opponentNickname(myMatch) }}</span>
            </p>
            <p v-else-if="myMatch && myMatch.is_bye" class="mb-4 text-lg font-bold text-bx-cyan">Bye esta ronda</p>
            <p v-else-if="isRegistered" class="mb-4 text-lg font-bold text-zinc-400">No clasificaste al corte.</p>

            <div v-if="isAdmin" class="space-y-2">
                <div
                    v-for="match in current_round_matches"
                    :key="match.id"
                    class="flex items-center justify-between gap-2 rounded-lg border border-white/10 p-3 text-sm"
                >
                    <span v-if="match.is_bye">{{ match.entry_one_nickname }} — bye</span>
                    <template v-else>
                        <span>{{ match.entry_one_nickname }} vs {{ match.entry_two_nickname }}</span>
                        <span v-if="match.winner_entry_id" class="text-bx-cyan">
                            Ganó {{ match.winner_entry_id === match.entry_one_id ? match.entry_one_nickname : match.entry_two_nickname }}
                        </span>
                        <div v-else class="flex gap-2">
                            <button type="button" class="rounded border border-white/15 px-2 py-1 hover:border-bx-cyan" @click="reportWinner(match.id, match.entry_one_id)">
                                {{ match.entry_one_nickname }} gana
                            </button>
                            <button type="button" class="rounded border border-white/15 px-2 py-1 hover:border-bx-cyan" @click="reportWinner(match.id, match.entry_two_id)">
                                {{ match.entry_two_nickname }} gana
                            </button>
                        </div>
                    </template>
                </div>

                <button
                    v-if="tournament.status === 'swiss' && tournament.current_round < tournament.swiss_rounds"
                    type="button"
                    class="mt-2 rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
                    @click="generateRound"
                >
                    Generar siguiente ronda
                </button>
                <button
                    v-if="tournament.status === 'swiss' && tournament.current_round === tournament.swiss_rounds"
                    type="button"
                    class="mt-2 rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
                    @click="cutToElimination"
                >
                    Cortar a eliminatorias
                </button>
                <button
                    v-if="tournament.status === 'elimination' && current_round_matches.length > 1"
                    type="button"
                    class="mt-2 rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
                    @click="generateRound"
                >
                    Generar siguiente ronda
                </button>
            </div>

            <table v-if="standings.length" class="mt-6 w-full text-left text-sm">
                <thead class="text-zinc-400">
                    <tr>
                        <th class="pb-2">#</th>
                        <th class="pb-2">Jugador</th>
                        <th class="pb-2">V</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in standings" :key="row.entry_id" class="border-t border-white/10">
                        <td class="py-1">{{ i + 1 }}</td>
                        <td class="py-1">{{ row.nickname }}</td>
                        <td class="py-1">{{ row.wins }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- TournamentShow`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Tournament/Show.vue resources/js/Pages/__tests__/TournamentShow.test.js
git commit -m "feat: add Tournament/Show.vue covering every tournament state"
```

---

### Task 17: Nav link

**Files:**
- Modify: `resources/js/Layouts/AppLayout.vue`

**Interfaces:**
- Consumes: `route tournament.show` → `/tournament` (Task 15).

- [ ] **Step 1: Add the desktop nav link**

In `resources/js/Layouts/AppLayout.vue`, in the desktop `<nav>` block, add the link right after the "Mis decks" `<Link>`:
```html
                        <Link href="/decks" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">Mis decks</Link>
                        <Link href="/tournament" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">Torneo</Link>
```

- [ ] **Step 2: Add the mobile nav link**

In the mobile `<nav>` block, add it right after the mobile "Mis decks" `<Link>`:
```html
                        <Link href="/decks" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-300 hover:bg-white/5">Mis decks</Link>
                        <Link href="/tournament" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-300 hover:bg-white/5">Torneo</Link>
```

- [ ] **Step 3: Run the existing Vitest suite to confirm nothing else broke**

Run: `npm run test`
Expected: PASS (all suites, AppLayout itself has no dedicated test file today)

- [ ] **Step 4: Commit**

```bash
git add resources/js/Layouts/AppLayout.vue
git commit -m "feat: add Torneo link to the nav"
```

---

### Task 18: End-to-end simulation

**Files:**
- Create: `tests/Feature/TournamentFullSimulationTest.php`

**Interfaces:**
- Consumes: every endpoint from Tasks 10-14 together, via HTTP, exactly as a real admin + players would.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentFullSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_eight_player_tournament_runs_end_to_end_to_a_champion(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $players = User::factory()->count(8)->create();
        foreach ($players as $player) {
            $player->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);
        }

        $this->actingAs($admin)
            ->post('/tournament', ['name' => 'Copa X', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertRedirect();

        foreach ($players as $player) {
            $this->actingAs($player)->post('/tournament/join')->assertRedirect();
        }

        // Three Swiss rounds: after each, report every match by declaring entry_one the winner
        // (arbitrary but deterministic), then generate the next round.
        for ($round = 1; $round <= 3; $round++) {
            $this->actingAs($admin)->post('/tournament/rounds')->assertRedirect();

            $tournament = Tournament::where('status', '!=', 'completed')->firstOrFail();
            $currentRound = $tournament->rounds()->where('number', $round)->firstOrFail();

            foreach ($currentRound->matches as $match) {
                if ($match->is_bye) {
                    continue;
                }
                $this->actingAs($admin)
                    ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $match->entry_one_id])
                    ->assertRedirect();
            }
        }

        $tournament = Tournament::where('status', '!=', 'completed')->firstOrFail();
        $this->assertSame('swiss', $tournament->status);
        $this->assertSame(3, $tournament->current_round);

        $this->actingAs($admin)->post('/tournament/cut')->assertRedirect();

        $tournament->refresh();
        $this->assertSame('elimination', $tournament->status);
        $this->assertSame(4, $tournament->current_round); // round 4 = semifinal (top 4 cut)

        // Semifinal (round 4): 2 matches.
        $semifinal = $tournament->rounds()->where('number', 4)->firstOrFail();
        $this->assertCount(2, $semifinal->matches);
        foreach ($semifinal->matches as $match) {
            $this->actingAs($admin)
                ->patch("/tournament/matches/{$match->id}", ['winner_entry_id' => $match->entry_one_id])
                ->assertRedirect();
        }

        $this->actingAs($admin)->post('/tournament/rounds')->assertRedirect();

        $tournament->refresh();
        $this->assertSame(5, $tournament->current_round);

        // Final (round 5): 1 match; reporting it must close the tournament.
        $final = $tournament->rounds()->where('number', 5)->firstOrFail();
        $this->assertCount(1, $final->matches);
        $finalMatch = $final->matches->first();

        $this->actingAs($admin)
            ->patch("/tournament/matches/{$finalMatch->id}", ['winner_entry_id' => $finalMatch->entry_one_id])
            ->assertRedirect();

        $tournament->refresh();
        $this->assertSame('completed', $tournament->status);
        $this->assertSame($finalMatch->entry_one_id, $tournament->champion_entry_id);

        // No further round can be generated once the tournament is completed.
        $this->actingAs($admin)->post('/tournament/rounds')->assertStatus(404);
    }
}
```

- [ ] **Step 2: Run test to verify it fails (or reveals a bug)**

Run: `php artisan test --filter TournamentFullSimulationTest`
Expected: initially FAIL only if a bug surfaces in the interaction between tasks — everything it exercises was already built and unit/feature tested in Tasks 10-14. If it fails, fix the root cause in the relevant controller/support class from the earlier task, don't patch around it here.

- [ ] **Step 3: Fix any integration bug found, if any**

(No new production code is expected for this task — it's a pure integration check. If it fails, the fix belongs in the task that owns the broken piece.)

- [ ] **Step 4: Run the full backend suite**

Run: `php artisan test`
Expected: PASS — all Tournament* tests plus the full pre-existing suite (no regressions)

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/TournamentFullSimulationTest.php
git commit -m "test: add end-to-end 8-player tournament simulation (swiss + cut + elimination)"
```

---

## Final verification (after Task 18)

Run the whole test suite and the frontend build once, to confirm the feature is fully wired:

```bash
php artisan test
npm run test
npm run build
```

All three must pass/succeed with no regressions in the pre-existing suites (`TeamComboTest`/`TeamNamesTest` remain skipped, per the earlier "hide teams" change — unrelated to this feature).

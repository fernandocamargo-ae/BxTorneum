<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use App\Support\TournamentPairing;
use App\Support\TournamentRoundFactory;
use App\Support\TournamentStandings;
use Illuminate\Database\Seeder;

/**
 * Demo data for the "Torneo por equipos" feature. Run explicitly:
 *   php artisan db:seed --class=TeamTournamentDemoSeeder
 * Not wired into DatabaseSeeder, so it never runs on a normal deploy.
 */
class TeamTournamentDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Tournament::where('name', 'Copa de Equipos — Demo')->exists()) {
            $this->command?->warn('Demo de torneo por equipos ya existe; no se duplica.');

            return;
        }

        $creatorId = (User::where('is_owner', true)->first()
            ?? User::where('is_admin', true)->first()
            ?? User::first())?->id;

        if (! $creatorId) {
            $creatorId = User::factory()->create(['name' => 'Demo Admin', 'nickname' => 'DemoAdmin', 'is_admin' => true])->id;
        }

        $teams = collect([
            'Dragones de Fuego', 'Trueno Azul', 'Fénix Carmesí', 'Vórtice Negro',
            'Glaciar', 'Relámpago', 'Tormenta', 'Nova Estelar',
        ])->map(fn ($name) => $this->makeCompleteTeam($name));

        // 1) Official team tournament, played to completion (shows groups + bracket + champion).
        $official = Tournament::create([
            'name' => 'Copa de Equipos — Demo',
            'type' => 'official', 'format' => 'teams', 'status' => 'registration',
            'swiss_rounds' => 2, 'cut_size' => 4, 'current_round' => 0, 'created_by_user_id' => $creatorId,
        ]);
        foreach ($teams->take(6) as $team) {
            TournamentEntry::create(['tournament_id' => $official->id, 'team_id' => $team->id]);
        }
        $this->playToCompletion($official);

        // 2) Clandestine team tournament still open (shows the "agregar equipo" selector + pool).
        $clandestine = Tournament::create([
            'name' => 'Reta Clandestina por Equipos — Demo',
            'type' => 'clandestine', 'format' => 'teams', 'status' => 'registration',
            'swiss_rounds' => 3, 'cut_size' => 4, 'current_round' => 0, 'created_by_user_id' => $creatorId,
        ]);
        foreach ($teams->take(3) as $team) {
            TournamentEntry::create(['tournament_id' => $clandestine->id, 'team_id' => $team->id]);
        }

        $this->command?->info('Demo listo: "Copa de Equipos — Demo" (completado) y "Reta Clandestina por Equipos — Demo" (abierto).');
    }

    private function makeCompleteTeam(string $name): Team
    {
        $team = Team::create(['name' => $name]);
        $roles = ['captain' => 'Capitán', 'subcaptain' => 'Subcapitán', 'official' => 'Oficial'];
        foreach ($roles as $role => $label) {
            $member = $team->members()->create(['role' => $role, 'name' => "{$label} {$name}"]);
            foreach ([1, 2, 3] as $position) {
                $member->beyblades()->create(['line' => 'bx', 'position' => $position]);
            }
        }

        return $team;
    }

    /** Run swiss → cut → elimination, always letting entry_one win, until a champion. */
    private function playToCompletion(Tournament $tournament): void
    {
        // Round 1 (swiss)
        TournamentRoundFactory::create($tournament, 'swiss', TournamentPairing::pairFirstRound($tournament->entries()->pluck('id')));
        $this->reportCurrentRound($tournament);

        // Remaining swiss rounds
        while ($tournament->fresh()->current_round < $tournament->swiss_rounds) {
            $standings = TournamentStandings::forTournament($tournament->fresh());
            TournamentRoundFactory::create($tournament, 'swiss', TournamentPairing::pairSwissRound($standings, $this->previousMatchups($tournament)));
            $this->reportCurrentRound($tournament);
        }

        // Cut to elimination
        $standings = TournamentStandings::forTournament($tournament->fresh());
        TournamentRoundFactory::create($tournament, 'elimination', TournamentPairing::seedEliminationBracket($standings, $tournament->cut_size));

        // Elimination until the final
        while (true) {
            $this->reportCurrentRound($tournament);
            $current = $tournament->fresh()->rounds()->where('number', $tournament->fresh()->current_round)->first();

            if ($current->matches()->count() <= 1) {
                $tournament->update([
                    'status' => 'completed',
                    'champion_entry_id' => $current->matches()->first()->winner_entry_id,
                ]);
                break;
            }

            $winnerIds = $current->matches()->orderBy('id')->pluck('winner_entry_id')->all();
            TournamentRoundFactory::create($tournament, 'elimination', TournamentPairing::advanceEliminationRound($winnerIds));
        }
    }

    private function reportCurrentRound(Tournament $tournament): void
    {
        $round = $tournament->fresh()->rounds()->where('number', $tournament->fresh()->current_round)->first();
        foreach ($round->matches()->whereNull('winner_entry_id')->get() as $match) {
            $match->update(['winner_entry_id' => $match->entry_one_id]);
        }
    }

    /** @return array<int, array<int>> */
    private function previousMatchups(Tournament $tournament): array
    {
        $matchups = [];
        foreach ($tournament->rounds()->with('matches')->get() as $round) {
            foreach ($round->matches as $match) {
                if ($match->is_bye) {
                    continue;
                }
                $matchups[$match->entry_one_id][] = $match->entry_two_id;
                $matchups[$match->entry_two_id][] = $match->entry_one_id;
            }
        }

        return $matchups;
    }
}

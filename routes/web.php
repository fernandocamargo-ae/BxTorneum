<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\DeckController;
use App\Http\Controllers\MonthlyChampionController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PlayerReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamComboController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamTournamentController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentMatchController;
use App\Http\Controllers\TournamentReportController;
use App\Http\Controllers\TournamentRoundController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('decks.index'));

Route::post('/report/players/pdf', [PlayerReportController::class, 'download'])->middleware('throttle:5,1')->name('report.players.pdf');

Route::get('/campeones-mensuales', [MonthlyChampionController::class, 'index'])->name('monthly-champions.index');

Route::middleware('auth')->group(function () {
    // Teams are rosters of users: a creator is the captain; players join/leave; the
    // captain expels members and sets their roles. (Static routes before /teams/{team}.)
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::post('/teams/{team}/join', [TeamController::class, 'join'])->name('teams.join');
    Route::delete('/teams/{team}/leave', [TeamController::class, 'leave'])->name('teams.leave');
    Route::delete('/teams/{team}/members/{member}', [TeamController::class, 'expelMember'])->name('teams.members.expel');
    Route::patch('/teams/{team}/members/{member}/role', [TeamController::class, 'updateMemberRole'])->name('teams.members.role');

    Route::get('/parts/search', [PartController::class, 'search'])->name('parts.search');

    Route::post('/report/pdf', [ReportController::class, 'download'])->middleware('throttle:5,1')->name('report.pdf');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('decks', DeckController::class)->except(['show']);
    Route::patch('/decks/{deck}/tournament', [DeckController::class, 'markTournament'])->name('decks.tournament');

    Route::get('/players', [PlayerController::class, 'index'])->name('players.index');
    Route::get('/players/{user}', [PlayerController::class, 'show'])->name('players.show');

    // Static tournament routes must be registered before /tournament/{tournament}
    // so literal segments ("history", "matches") aren't captured as a model binding.
    Route::post('/tournament', [TournamentController::class, 'store'])->name('tournament.store');
    Route::post('/tournament/clandestinos', [TournamentController::class, 'storeClandestine'])->name('tournament.clandestinos.store');
    Route::get('/tournament', [TournamentController::class, 'show'])->name('tournament.show');
    Route::get('/tournament/clandestinos', [TournamentController::class, 'clandestineIndex'])->name('tournament.clandestinos.index');
    Route::get('/tournament/history', [TournamentController::class, 'history'])->name('tournament.history');
    Route::get('/tournament/history/{tournament}', [TournamentController::class, 'historyShow'])->name('tournament.history.show');
    Route::patch('/tournament/matches/{match}', [TournamentMatchController::class, 'update'])->name('tournament.matches.update');

    // Per-tournament routes. Authorization lives in the controllers via TournamentPolicy.
    Route::get('/tournament/{tournament}', [TournamentController::class, 'show'])->name('tournament.view');
    Route::patch('/tournament/{tournament}', [TournamentController::class, 'update'])->name('tournament.update');
    Route::post('/tournament/{tournament}/join', [TournamentController::class, 'join'])->name('tournament.join');
    Route::delete('/tournament/{tournament}/leave', [TournamentController::class, 'leave'])->name('tournament.leave');
    Route::delete('/tournament/{tournament}/entries/{entry}', [TournamentController::class, 'removeEntry'])->name('tournament.entries.destroy');
    Route::post('/tournament/{tournament}/rounds', [TournamentRoundController::class, 'store'])->name('tournament.rounds.store');
    Route::post('/tournament/{tournament}/cut', [TournamentController::class, 'cut'])->name('tournament.cut');
    Route::post('/tournament/{tournament}/report/pdf', [TournamentReportController::class, 'download'])->middleware('throttle:5,1')->name('tournament.report.pdf');

    // Team tournaments (format = teams). Round/cut/match actions reuse the per-id
    // tournament routes above; only creation, listing and the team-aware show live here.
    Route::post('/torneo-equipos', [TeamTournamentController::class, 'storeOfficial'])->name('team-tournament.store');
    Route::post('/torneo-equipos/clandestinos', [TeamTournamentController::class, 'storeClandestine'])->name('team-tournament.clandestinos.store');
    Route::get('/torneo-equipos/clandestinos', [TeamTournamentController::class, 'clandestineIndex'])->name('team-tournament.clandestinos.index');
    Route::get('/torneo-equipos', [TeamTournamentController::class, 'show'])->name('team-tournament.show');
    Route::get('/torneo-equipos/{tournament}', [TeamTournamentController::class, 'show'])->name('team-tournament.view');
    Route::post('/torneo-equipos/{tournament}/equipos', [TeamTournamentController::class, 'addTeam'])->name('team-tournament.teams.add');

    Route::middleware('admin')->group(function () {
        Route::post('/campeones-mensuales', [MonthlyChampionController::class, 'store'])->name('monthly-champions.store');
    });

    Route::middleware('owner')->group(function () {
        Route::get('/admin/users', [UserManagementController::class, 'index'])->name('admin.users.index');
        Route::patch('/admin/users/{user}/admin', [UserManagementController::class, 'updateAdmin'])->name('admin.users.update-admin');
        Route::patch('/admin/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('admin.users.update-role');
    });
});

require __DIR__.'/auth.php';

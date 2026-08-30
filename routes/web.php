<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\DeckController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PlayerReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamComboController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentMatchController;
use App\Http\Controllers\TournamentReportController;
use App\Http\Controllers\TournamentRoundController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('decks.index'));

Route::post('/report/players/pdf', [PlayerReportController::class, 'download'])->middleware('throttle:5,1')->name('report.players.pdf');

Route::middleware('auth')->group(function () {
    // Equipos deshabilitados temporalmente (no se usan por ahora). Lógica y vistas se conservan intactas.
    // Route::resource('teams', TeamController::class);

    // Route::get('/teams/{team}/combos', [TeamComboController::class, 'edit'])->name('combos.edit');
    // Route::put('/teams/{team}/combos', [TeamComboController::class, 'update'])->name('combos.update');

    Route::get('/parts/search', [PartController::class, 'search'])->name('parts.search');

    Route::post('/report/pdf', [ReportController::class, 'download'])->middleware('throttle:5,1')->name('report.pdf');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('decks', DeckController::class)->except(['show']);
    Route::patch('/decks/{deck}/tournament', [DeckController::class, 'markTournament'])->name('decks.tournament');

    Route::get('/players', [PlayerController::class, 'index'])->name('players.index');
    Route::get('/players/{user}', [PlayerController::class, 'show'])->name('players.show');

    Route::get('/tournament', [TournamentController::class, 'show'])->name('tournament.show');
    Route::post('/tournament/join', [TournamentController::class, 'join'])->name('tournament.join');
    Route::delete('/tournament/leave', [TournamentController::class, 'leave'])->name('tournament.leave');
    Route::get('/tournament/history', [TournamentController::class, 'history'])->name('tournament.history');
    Route::get('/tournament/history/{tournament}', [TournamentController::class, 'historyShow'])->name('tournament.history.show');
    Route::post('/tournament/report/pdf', [TournamentReportController::class, 'download'])->middleware('throttle:5,1')->name('tournament.report.pdf');

    Route::middleware('admin')->group(function () {
        Route::post('/tournament', [TournamentController::class, 'store'])->name('tournament.store');
        Route::patch('/tournament', [TournamentController::class, 'update'])->name('tournament.update');
        Route::delete('/tournament/entries/{entry}', [TournamentController::class, 'removeEntry'])->name('tournament.entries.destroy');
        Route::patch('/tournament/matches/{match}', [TournamentMatchController::class, 'update'])->name('tournament.matches.update');
        Route::post('/tournament/rounds', [TournamentRoundController::class, 'store'])->name('tournament.rounds.store');
        Route::post('/tournament/cut', [TournamentController::class, 'cut'])->name('tournament.cut');
    });

    Route::middleware('owner')->group(function () {
        Route::get('/admin/users', [UserManagementController::class, 'index'])->name('admin.users.index');
        Route::patch('/admin/users/{user}/admin', [UserManagementController::class, 'updateAdmin'])->name('admin.users.update-admin');
    });
});

require __DIR__.'/auth.php';

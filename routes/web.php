<?php

use App\Http\Controllers\DeckController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PlayerReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamComboController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('teams.index'));

Route::resource('teams', TeamController::class);

Route::get('/teams/{team}/combos', [TeamComboController::class, 'edit'])->name('combos.edit');
Route::put('/teams/{team}/combos', [TeamComboController::class, 'update'])->name('combos.update');

Route::get('/parts/search', [PartController::class, 'search'])->name('parts.search');

Route::post('/report/pdf', [ReportController::class, 'download'])->name('report.pdf');
Route::post('/report/players/pdf', [PlayerReportController::class, 'download'])->name('report.players.pdf');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('decks', DeckController::class)->except(['show']);
    Route::patch('/decks/{deck}/tournament', [DeckController::class, 'markTournament'])->name('decks.tournament');

    Route::get('/players', [PlayerController::class, 'index'])->name('players.index');
    Route::get('/players/{user}', [PlayerController::class, 'show'])->name('players.show');
});

require __DIR__.'/auth.php';

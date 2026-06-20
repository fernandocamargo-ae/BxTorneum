<?php

use App\Http\Controllers\PartController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('teams.index'));

Route::resource('teams', TeamController::class);

Route::get('/parts/search', [PartController::class, 'search'])->name('parts.search');

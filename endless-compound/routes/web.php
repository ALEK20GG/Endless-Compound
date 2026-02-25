<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameController;

// Home: pagina principale di gioco (invece della welcome di default Laravel)
Route::get('/', [GameController::class, 'index'])->name('game.index');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Endpoints AJAX per il gioco (combinazioni, salvataggio board, ecc.)
Route::post('/game/combine', [GameController::class, 'combine'])->name('game.combine');
Route::post('/game/save-board', [GameController::class, 'saveBoard'])->name('game.saveBoard');

require __DIR__.'/auth.php';

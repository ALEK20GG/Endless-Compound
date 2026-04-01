<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameController;
use App\Http\Controllers\Auth\GoogleAuthController;

// Home: pagina principale di gioco
Route::get('/', [GameController::class, 'index'])->name('game.index');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Endpoints AJAX per il gioco
Route::post('/game/combine', [GameController::class, 'combine'])->name('game.combine');
Route::post('/game/save-board', [GameController::class, 'saveBoard'])->name('game.saveBoard');

// Google OAuth — definite QUI così la route 'auth.google' esiste sempre
Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

require __DIR__.'/auth.php';
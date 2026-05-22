<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\GameController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\TwoFactorController;

// Logout esplicito
Route::post('/logout', function () {
    Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('home');
})->middleware('auth')->name('logout');

// Home: se non loggato → landing pubblica, se loggato → dashboard modalità
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return view('dashboard');
})->name('home');

// Dashboard modalità di gioco — richiede auth
Route::get('/dashboard', function () {
    return view('dashboard-play');
})->middleware('auth')->name('dashboard');

// Gioco singleplayer — crea/riusa room e reindirizza
Route::get('/game', [GameController::class, 'startSolo'])
    ->middleware('auth')
    ->name('game.index');

// Endpoints AJAX — devono stare PRIMA di /game/{roid} per non essere catturati dal parametro
Route::middleware('auth')->group(function () {
    Route::post('/game/combine', [GameController::class, 'combine'])->name('game.combine');
    Route::get('/game/combine/poll/{jobId}', [GameController::class, 'combinePoll'])->name('game.combine.poll');
    Route::get('/game/elements', [GameController::class, 'elements'])->name('game.elements');
    Route::post('/game/multiplayer/create', [GameController::class, 'createMultiplayer'])->name('game.multiplayer.create');
    Route::post('/game/multiplayer/join', [GameController::class, 'joinMultiplayer'])->name('game.multiplayer.join');
    Route::post('/game/multiplayer/invite', [GameController::class, 'sendInvite'])->name('game.multiplayer.invite');
});

// Gioco in una room specifica — DOPO le route statiche
Route::get('/game/{roid}', [GameController::class, 'index'])
    ->middleware('auth')
    ->whereNumber('roid')
    ->name('game.room');

// Profilo utente
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/room/{roid}', [ProfileController::class, 'leaveRoom'])->name('profile.room.leave');
});

// Google OAuth — definite QUI così la route 'auth.google' esiste sempre
Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// 2FA routes
Route::get('/auth/two-factor', [TwoFactorController::class, 'show'])->name('2fa.show');
Route::post('/auth/two-factor/send', [TwoFactorController::class, 'send'])->name('2fa.send');
Route::post('/auth/two-factor/verify', [TwoFactorController::class, 'verify'])->name('2fa.verify');

require __DIR__.'/auth.php';

// ── DB Check ─────────────────────────────────────────────────────────────
if (app()->environment('local', 'production')) {
    Route::get('/phpinfo', function () {
        return response('<pre>max_execution_time: ' . ini_get('max_execution_time') . "\n"
            . 'php_ini: ' . php_ini_loaded_file() . "\n"
            . 'php_version: ' . PHP_VERSION . '</pre>');
    });

    Route::get('/debug-error', function () {
        try {
            // Test basic DB
            DB::connection()->getPdo();
            $compounds = DB::table('compounds')->count();
            // Test auth
            $uid = auth()->id();
            // Test routes
            $routes = collect(Route::getRoutes())->map(fn($r) => $r->getName())->filter()->values();
            return response()->json([
                'status'    => 'ok',
                'db'        => "connected, {$compounds} compounds",
                'auth'      => $uid ? "logged in as {$uid}" : 'guest',
                'php'       => PHP_VERSION,
                'laravel'   => app()->version(),
                'socialite' => class_exists(\Laravel\Socialite\Facades\Socialite::class) ? 'installed' : 'MISSING',
                'profile_controller' => class_exists(\App\Http\Controllers\ProfileController::class) ? 'found' : 'MISSING',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    });

    Route::get('/dbcheck', function () {
        $results = [];

        // 1. Variabili d'ambiente
        $results['env'] = [
            'DB_CONNECTION' => env('DB_CONNECTION'),
            'DB_HOST'       => env('DB_HOST'),
            'DB_PORT'       => env('DB_PORT'),
            'DB_DATABASE'   => env('DB_DATABASE'),
            'DB_USERNAME'   => env('DB_USERNAME'),
            'DB_PASSWORD'   => env('DB_PASSWORD') ? str_repeat('*', strlen(env('DB_PASSWORD'))) : '(vuota)',
            'DB_SSLMODE'    => env('DB_SSLMODE'),
            'APP_ENV'       => env('APP_ENV'),
        ];

        // 2. Connessione base
        try {
            DB::connection()->getPdo();
            $results['connection'] = ['status' => 'OK ✅', 'message' => 'Connessione a Supabase riuscita'];
        } catch (\Exception $e) {
            $results['connection'] = ['status' => 'ERRORE ❌', 'message' => $e->getMessage()];
        }

        // 3. COUNT su ogni tabella
        foreach (['users', 'rooms', 'compounds', 'collabs_in', 'discovers', 'room_comps'] as $table) {
            try {
                $results['tables'][$table] = ['status' => 'OK ✅', 'rows' => DB::table($table)->count()];
            } catch (\Exception $e) {
                $results['tables'][$table] = ['status' => 'ERRORE ❌', 'message' => $e->getMessage()];
            }
        }

        // 4. Sample rooms
        try {
            $rooms = DB::table('rooms')->orderBy('createdat', 'desc')->limit(5)->get();
            $results['rooms_sample'] = ['status' => 'OK ✅', 'data' => $rooms->toArray()];
        } catch (\Exception $e) {
            $results['rooms_sample'] = ['status' => 'ERRORE ❌', 'message' => $e->getMessage()];
        }

        // 5. INSERT + DELETE di test
        try {
            $firstUser = DB::table('users')->value('uid');
            if ($firstUser) {
                $id = DB::table('rooms')->insertGetId([
                    'name' => '__test_room__', 'isprivate' => false,
                    'maxplayers' => 2, 'owner_uid' => $firstUser, 'createdat' => now(),
                ], 'roid');
                DB::table('rooms')->where('roid', $id)->delete();
                $results['insert_delete'] = ['status' => 'OK ✅', 'message' => "INSERT (roid=$id) e DELETE riusciti"];
            } else {
                $results['insert_delete'] = ['status' => 'SKIP ⚠️', 'message' => 'Nessun utente in users — INSERT saltato (FK)'];
            }
        } catch (\Exception $e) {
            $results['insert_delete'] = ['status' => 'ERRORE ❌', 'message' => $e->getMessage()];
        }

        return response(view('dbcheck', ['results' => $results]));
    })->name('dbcheck');
}
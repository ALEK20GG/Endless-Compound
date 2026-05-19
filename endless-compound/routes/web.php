<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
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
Route::get('/game/elements', [GameController::class, 'elements'])->name('game.elements');

// Google OAuth — definite QUI così la route 'auth.google' esiste sempre
Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

require __DIR__.'/auth.php';

// ── DB Check ─────────────────────────────────────────────────────────────
if (app()->environment('local', 'production')) {
    Route::get('/phpinfo', function () {
        return response('<pre>max_execution_time: ' . ini_get('max_execution_time') . "\n"
            . 'php_ini: ' . php_ini_loaded_file() . "\n"
            . 'php_version: ' . PHP_VERSION . '</pre>');
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
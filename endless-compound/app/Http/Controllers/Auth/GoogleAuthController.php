<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        // Controlla che Socialite sia installato
        if (!class_exists(\Laravel\Socialite\Facades\Socialite::class)) {
            return redirect('/login')->withErrors([
                'google' => 'Login con Google non disponibile. Installa laravel/socialite.',
            ]);
        }

        return \Laravel\Socialite\Facades\Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        if (!class_exists(\Laravel\Socialite\Facades\Socialite::class)) {
            return redirect('/login')->withErrors([
                'google' => 'Login con Google non disponibile.',
            ]);
        }

        try {
            $googleUser = \Laravel\Socialite\Facades\Socialite::driver('google')->user();

            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Aggiorna lastlogin
                DB::table('users')->where('uid', $user->uid)->update([
                    'lastlogin' => now(),
                ]);
                Auth::login($user);
            } else {
                $user = User::create([
                    'username'  => $googleUser->getName(),
                    'email'     => $googleUser->getEmail(),
                    'password'  => Hash::make(uniqid()),
                    'createdat' => now(),
                    'lastlogin' => now(),
                ]);

                Auth::login($user);
            }

            return redirect()->intended(route('dashboard'));

        } catch (\Exception $e) {
            return redirect('/login')->withErrors([
                'google' => 'Impossibile effettuare il login con Google: ' . $e->getMessage(),
            ]);
        }
    }
}
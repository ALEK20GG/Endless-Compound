<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth consent screen.
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google.
     * - If user exists → log in and update lastlogin
     * - If user doesn't exist → create account and log in
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')
                ->withErrors(['google' => 'Google login failed: ' . $e->getMessage()]);
        }

        $email    = $googleUser->getEmail();
        $name     = $googleUser->getName() ?? $googleUser->getNickname() ?? explode('@', $email)[0];

        // Find or create user
        $user = DB::table('users')->where('email', $email)->first();

        if ($user) {
            DB::table('users')->where('uid', $user->uid)->update([
                'lastlogin' => now(),
            ]);
            // Hydrate Eloquent model for Auth::login
            $authUser = \App\Models\User::find($user->uid);
        } else {
            $uid = DB::table('users')->insertGetId([
                'username'  => $name,
                'email'     => $email,
                'password'  => Hash::make(bin2hex(random_bytes(16))),
                'createdat' => now(),
                'lastlogin' => now(),
            ], 'uid');

            $authUser = \App\Models\User::find($uid);
        }

        if (! $authUser) {
            return redirect()->route('login')
                ->withErrors(['google' => 'Could not create or find user account.']);
        }

        Auth::login($authUser, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}

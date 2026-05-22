<?php

namespace App\Livewire\Forms;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /** Set to true after credentials are verified, to signal 2FA is needed */
    public bool $requiresTwoFactor = false;

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only(['email', 'password']), $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        // Aggiorna lastlogin
        DB::table('users')->where('uid', Auth::id())->update([
            'lastlogin' => now(),
        ]);

        RateLimiter::clear($this->throttleKey());

        // ── 2FA via email ────────────────────────────────────────
        // Salva l'utente in sessione come "pending 2FA" e invia OTP.
        // Il login viene completato solo dopo la verifica del codice.
        $userId = Auth::id();
        Auth::logout(); // log out temporaneamente

        Session::put('2fa_user_id', $userId);
        Session::put('2fa_remember', $this->remember);

        // Genera e invia OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("2fa_otp:{$userId}", $otp, 600);

        $user = \App\Models\User::find($userId);
        try {
            Mail::to($user->email)->send(new \App\Mail\TwoFactorMail($user->username, $otp));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('2FA mail failed', ['error' => $e->getMessage()]);
        }

        // Signal to the Livewire component that 2FA redirect is needed
        $this->requiresTwoFactor = true;
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}

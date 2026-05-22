<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;

class TwoFactorController extends Controller
{
    /**
     * Show the 2FA verification form.
     * The user is already authenticated but held in a "pending 2FA" state.
     */
    public function show()
    {
        if (! Session::has('2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    /**
     * Send a new OTP code to the user's email.
     */
    public function send()
    {
        $userId = Session::get('2fa_user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $user = \App\Models\User::find($userId);
        if (! $user) {
            return redirect()->route('login');
        }

        // Generate 6-digit OTP, valid for 10 minutes
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("2fa_otp:{$userId}", $otp, 600);

        try {
            Mail::to($user->email)->send(new \App\Mail\TwoFactorMail($user->username, $otp));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('2FA mail failed', ['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Verification code sent to your email.');
    }

    /**
     * Verify the OTP and complete login.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $userId = Session::get('2fa_user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $storedOtp = Cache::get("2fa_otp:{$userId}");

        if (! $storedOtp || $storedOtp !== $request->input('otp')) {
            return back()->withErrors(['otp' => 'Invalid or expired code. Please try again.']);
        }

        // OTP valid — complete login
        Cache::forget("2fa_otp:{$userId}");
        Session::forget('2fa_user_id');

        $user = \App\Models\User::find($userId);
        Auth::login($user, Session::get('2fa_remember', false));
        Session::forget('2fa_remember');
        Session::regenerate();

        return redirect()->intended(route('dashboard'));
    }
}

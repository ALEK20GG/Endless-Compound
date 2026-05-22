<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Login – Endless Compound</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-950 text-white min-h-screen flex items-center justify-center px-4">

    <div class="w-full max-w-sm space-y-6">

        <div class="text-center">
            <div class="text-4xl mb-3">⚗️</div>
            <h1 class="text-2xl font-bold">Two-factor verification</h1>
            <p class="text-gray-400 text-sm mt-2">
                We sent a 6-digit code to your email. Enter it below to continue.
            </p>
        </div>

        @if(session('status'))
            <div class="bg-green-900 border border-green-700 text-green-300 text-sm px-4 py-3 rounded-lg text-center">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('2fa.verify') }}" class="space-y-4">
            @csrf

            <div>
                <input type="text"
                       name="otp"
                       maxlength="6"
                       placeholder="000000"
                       autofocus
                       inputmode="numeric"
                       pattern="[0-9]{6}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-center text-2xl font-mono tracking-widest text-white focus:outline-none focus:border-indigo-500 @error('otp') border-red-500 @enderror">
                @error('otp')
                    <p class="text-red-400 text-xs mt-1 text-center">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-3 rounded-xl transition">
                Verify
            </button>
        </form>

        <div class="text-center">
            <form method="POST" action="{{ route('2fa.send') }}">
                @csrf
                <button type="submit" class="text-indigo-400 hover:text-indigo-300 text-sm transition">
                    Resend code
                </button>
            </form>
        </div>

        <div class="text-center">
            <a href="{{ route('login') }}" class="text-gray-500 hover:text-gray-400 text-sm transition">
                ← Back to login
            </a>
        </div>

    </div>

</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Endless Compound – Play</title>
    @vite(['resources/css/app.css', 'resources/css/style.css'])
</head>
<body class="bg-gray-950 text-white min-h-screen flex flex-col">

    {{-- Navbar --}}
    <nav class="bg-gray-900 border-b border-gray-800 px-6 py-3 flex items-center justify-between">
        <a href="{{ route('home') }}" class="text-lg font-bold text-indigo-400 tracking-wide">⚗️ Endless Compound</a>
        <div class="flex items-center gap-4 text-sm">
            <span class="text-gray-400">{{ auth()->user()->username }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-gray-500 hover:text-white transition">Log out</button>
            </form>
        </div>
    </nav>

    {{-- Contenuto --}}
    <div class="flex-1 flex flex-col items-center justify-center px-4 py-12 space-y-10">

        <div class="text-center space-y-2">
            <h1 class="text-3xl font-bold">Choose a game mode</h1>
            <p class="text-gray-400 text-sm">Welcome back, {{ auth()->user()->username }}!</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 w-full max-w-2xl">

            {{-- Singleplayer --}}
            <a href="{{ route('game.index') }}"
               class="group bg-gray-900 border border-gray-700 hover:border-indigo-500 rounded-2xl p-8 flex flex-col items-center gap-4 transition hover:bg-gray-800">
                <div class="text-5xl">🧪</div>
                <div class="text-center">
                    <h2 class="text-xl font-semibold group-hover:text-indigo-400 transition">Singleplayer</h2>
                    <p class="text-gray-400 text-sm mt-1">
                        Explore and discover new elements on your own.
                        Every combination you find is saved for all players.
                    </p>
                </div>
                <span class="mt-auto bg-indigo-600 group-hover:bg-indigo-500 text-white text-sm font-medium px-5 py-2 rounded-full transition">
                    Play solo →
                </span>
            </a>

            {{-- Multiplayer (coming soon) --}}
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-8 flex flex-col items-center gap-4 opacity-50 cursor-not-allowed">
                <div class="text-5xl">👥</div>
                <div class="text-center">
                    <h2 class="text-xl font-semibold">Multiplayer</h2>
                    <p class="text-gray-400 text-sm mt-1">
                        Join a room and discover elements together with other players in real time.
                    </p>
                </div>
                <span class="mt-auto bg-gray-700 text-gray-400 text-sm font-medium px-5 py-2 rounded-full">
                    Coming soon
                </span>
            </div>

        </div>

    </div>

</body>
</html>

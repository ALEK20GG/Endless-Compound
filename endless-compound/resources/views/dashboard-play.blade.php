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
            <a href="{{ route('profile') }}" class="text-gray-500 hover:text-white transition">Profile</a>
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

            {{-- Multiplayer --}}
            <div class="bg-gray-900 border border-gray-700 rounded-2xl p-8 flex flex-col gap-5">
                <div class="flex items-center gap-3">
                    <div class="text-5xl">👥</div>
                    <div>
                        <h2 class="text-xl font-semibold">Multiplayer</h2>
                        <p class="text-gray-400 text-sm mt-1">Discover elements together in real time.</p>
                    </div>
                </div>

                {{-- Crea room --}}
                <form method="POST" action="{{ route('game.multiplayer.create') }}" class="space-y-2">
                    @csrf
                    <input type="text" name="name" placeholder="Room name"
                           maxlength="60" required
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500">
                    <div class="flex gap-2">
                        <select name="maxplayers"
                                class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                            @for($i = 2; $i <= 8; $i++)
                                <option value="{{ $i }}">{{ $i }} players</option>
                            @endfor
                        </select>
                        <button type="submit"
                                class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                            Create room
                        </button>
                    </div>
                </form>

                <div class="border-t border-gray-800 pt-4">
                    {{-- Entra in room --}}
                    <form method="POST" action="{{ route('game.multiplayer.join') }}" class="flex gap-2">
                        @csrf
                        <input type="text" name="code" placeholder="Room code (6 chars)"
                               maxlength="6" required
                               class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 uppercase focus:outline-none focus:border-indigo-500">
                        <button type="submit"
                                class="bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                            Join
                        </button>
                    </form>
                    @error('code')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

        </div>

    </div>

</body>
</html>

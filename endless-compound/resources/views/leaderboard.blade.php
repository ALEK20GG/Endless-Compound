<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard – Endless Compound</title>
    @vite(['resources/css/app.css', 'resources/css/style.css'])
</head>
<body class="bg-gray-950 text-white min-h-screen flex flex-col">

    <nav class="bg-gray-900 border-b border-gray-800 px-6 py-3 flex items-center justify-between">
        <a href="{{ route('dashboard') }}" class="text-lg font-bold text-indigo-400 tracking-wide">⚗️ Endless Compound</a>
        <div class="flex items-center gap-4 text-sm">
            @auth
                <a href="{{ route('game.index') }}" class="text-gray-400 hover:text-white transition">Play</a>
                <a href="{{ route('profile') }}" class="text-gray-400 hover:text-white transition">Profile</a>
            @else
                <a href="{{ route('login') }}" class="text-gray-400 hover:text-white transition">Log in</a>
            @endauth
        </div>
    </nav>

    <div class="flex-1 max-w-2xl mx-auto w-full px-4 py-10 space-y-8">

        <div class="text-center space-y-1">
            <h1 class="text-3xl font-bold">🏆 Leaderboard</h1>
            <p class="text-gray-500 text-sm">
                {{ number_format($totalCompounds) }} elements discovered globally
            </p>
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            @forelse($leaders as $i => $leader)
                <div class="flex items-center gap-4 px-5 py-3 border-b border-gray-800 last:border-0
                            {{ auth()->check() && auth()->id() == $leader->uid ? 'bg-indigo-950/40' : '' }}">

                    {{-- Rank --}}
                    <div class="w-8 text-center font-bold shrink-0
                        {{ $i === 0 ? 'text-yellow-400 text-lg' : ($i === 1 ? 'text-gray-300 text-base' : ($i === 2 ? 'text-amber-600 text-base' : 'text-gray-600 text-sm')) }}">
                        {{ $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '#' . ($i + 1))) }}
                    </div>

                    {{-- Username --}}
                    <div class="flex-1 min-w-0">
                        <span class="font-medium text-sm truncate block
                            {{ auth()->check() && auth()->id() == $leader->uid ? 'text-indigo-300' : 'text-white' }}">
                            {{ e($leader->username) }}
                            @if(auth()->check() && auth()->id() == $leader->uid)
                                <span class="text-xs text-indigo-500 ml-1">(you)</span>
                            @endif
                        </span>
                    </div>

                    {{-- Discoveries --}}
                    <div class="text-right shrink-0">
                        <span class="text-sm font-semibold text-white">{{ number_format($leader->discoveries) }}</span>
                        <span class="text-xs text-gray-500 ml-1">{{ $leader->discoveries == 1 ? 'discovery' : 'discoveries' }}</span>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-gray-600 text-sm">
                    No discoveries yet. Be the first!
                </div>
            @endforelse
        </div>

        <div class="text-center">
            <a href="{{ route('game.index') }}"
               class="inline-block bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 px-6 rounded-xl transition text-sm">
                Play now →
            </a>
        </div>

    </div>

</body>
</html>

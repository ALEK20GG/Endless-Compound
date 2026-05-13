<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Endless Compound</title>
    @vite(['resources/css/app.css', 'resources/css/style.css', 'resources/js/app.js', 'resources/js/game.js'])
</head>
<body class="bg-gray-950 text-white min-h-screen flex flex-col">

{{-- ── NAVBAR ──────────────────────────────────────────────────── --}}
<nav class="bg-gray-900 border-b border-gray-800 px-4 py-3 flex items-center justify-between">
    <span class="text-lg font-bold tracking-wide text-indigo-400">⚗️ Endless Compound</span>
    <div class="flex items-center gap-3 text-sm">
        @auth
            <span class="text-gray-400">{{ auth()->user()->username }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-gray-500 hover:text-white transition">Esci</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="text-indigo-400 hover:text-indigo-300 transition">Accedi</a>
            <a href="{{ route('auth.google') }}" class="text-indigo-400 hover:text-indigo-300 transition">Google</a>
        @endauth
    </div>
</nav>

{{-- ── LAYOUT PRINCIPALE ───────────────────────────────────────── --}}
<div class="flex flex-1 overflow-hidden" style="height: calc(100vh - 53px)">

    {{-- SIDEBAR sinistra: elementi scoperti --}}
    <aside id="sidebar"
           class="w-56 bg-gray-900 border-r border-gray-800 flex flex-col overflow-hidden shrink-0">
        <div class="px-3 py-2 border-b border-gray-800 flex items-center justify-between">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Elementi</span>
            <span id="element-count" class="text-xs text-gray-600">0</span>
        </div>
        <div class="px-2 py-2">
            <input id="sidebar-search"
                   type="search"
                   placeholder="Cerca…"
                   class="w-full bg-gray-800 text-sm text-white rounded px-2 py-1 outline-none placeholder-gray-600 border border-gray-700 focus:border-indigo-500">
        </div>
        <ul id="element-list"
            class="flex-1 overflow-y-auto px-2 pb-2 space-y-1">
            {{-- popolato via JS --}}
        </ul>
    </aside>

    {{-- BOARD centrale --}}
    <main id="board"
          class="flex-1 relative overflow-hidden bg-gray-950 select-none"
          data-combine-url="{{ route('game.combine') }}"
          data-elements-url="{{ route('game.elements') }}"
          data-base-elements='@json($baseElements)'>

        {{-- Messaggio centrale quando la board è vuota --}}
        <div id="board-hint"
             class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <p class="text-gray-700 text-sm">
                Trascina un elemento dalla sidebar sulla board per iniziare
            </p>
        </div>

        {{-- Toast notifiche --}}
        <div id="toast-container"
             class="absolute top-4 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 z-50 pointer-events-none">
        </div>
    </main>

</div>

</body>
</html>

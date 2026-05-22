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
    <a href="{{ route('dashboard') }}" class="text-lg font-bold tracking-wide text-indigo-400">⚗️ Endless Compound</a>
    <div class="flex items-center gap-3 text-sm">
        @if($isMultiplayer)
            <span class="bg-indigo-900 border border-indigo-700 text-indigo-300 text-xs font-mono px-2 py-1 rounded">
                Code: {{ $room->code ?? '—' }}
            </span>
            <span class="text-gray-500 text-xs">{{ $players->count() }}/{{ $room->maxplayers }} players</span>
        @endif
        <span class="text-gray-400">{{ auth()->user()->username }}</span>
        <a href="{{ route('profile') }}" class="text-gray-500 hover:text-white transition text-xs">Profile</a>
        <button onclick="document.getElementById('logout-form').submit()"
                class="text-gray-500 hover:text-white transition">Log out</button>
    </div>
</nav>

<form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none">
    @csrf
</form>

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
          data-poll-url="{{ url('/game/combine/poll') }}"
          data-roid="{{ $room->roid }}"
          data-multiplayer="{{ $isMultiplayer ? 'true' : 'false' }}"
          data-uid="{{ auth()->id() }}"
          data-my-discoveries='@json($myDiscoveries)'
          data-base-elements='@json($roomElements)'>

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

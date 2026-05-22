<!DOCTYPE html>
<html lang="it" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Endless Compound</title>
    @vite(['resources/css/app.css', 'resources/css/style.css', 'resources/js/app.js', 'resources/js/game.js'])
    <script>
        // Applica tema dal cookie prima del render per evitare flash
        (function() {
            const theme = document.cookie.split(';').map(c => c.trim())
                .find(c => c.startsWith('theme='))?.split('=')[1] ?? 'dark';
            if (theme === 'light') document.documentElement.classList.add('light-theme');
        })();
    </script>
</head>
<body class="bg-gray-950 text-white h-screen flex flex-col overflow-hidden">

{{-- ── NAVBAR ──────────────────────────────────────────────────── --}}
<nav class="bg-gray-900 border-b border-gray-800 px-4 py-3 flex items-center justify-between">
    <a href="{{ route('dashboard') }}" class="text-lg font-bold tracking-wide text-indigo-400">⚗️ Endless Compound</a>
    <div class="flex items-center gap-2 text-sm flex-wrap justify-end">
        @if($isMultiplayer)
            <span class="bg-indigo-900 border border-indigo-700 text-indigo-300 text-xs font-mono px-2 py-1 rounded">
                Code: {{ $room->code ?? '—' }}
            </span>
            <span class="text-gray-500 text-xs hidden sm:inline">{{ $players->count() }}/{{ $room->maxplayers }} players</span>
            @if(auth()->id() == $room->owner_uid)
            <button onclick="document.getElementById('invite-modal').classList.remove('hidden')"
                    class="text-indigo-400 hover:text-indigo-300 transition text-xs">
                ✉️ Invite
            </button>
            @endif
        @endif
        {{-- Contatore scoperte --}}
        <span id="nav-discovery-count"
              class="hidden text-xs bg-yellow-900/50 border border-yellow-700/50 text-yellow-400 px-2 py-0.5 rounded font-mono">
            🏆 <span id="nav-discovery-num">{{ count($myDiscoveries) }}</span>
        </span>
        {{-- Pulisci board --}}
        <button onclick="clearBoard()"
                class="text-gray-600 hover:text-red-400 transition text-xs hidden sm:inline"
                title="Clear board">
            🗑 Clear
        </button>
        {{-- Tema --}}
        <button id="theme-toggle"
                onclick="toggleTheme()"
                class="text-gray-500 hover:text-white transition text-xs"
                title="Toggle theme">
            🌙
        </button>
        <span class="text-gray-400 hidden sm:inline">{{ auth()->user()->username }}</span>
        <a href="{{ route('profile') }}" class="text-gray-500 hover:text-white transition text-xs">Profile</a>
        <a href="{{ route('leaderboard') }}" class="text-gray-500 hover:text-white transition text-xs hidden sm:inline">🏆</a>
        <button onclick="document.getElementById('logout-form').submit()"
                class="text-gray-500 hover:text-white transition text-xs">Log out</button>
    </div>
</nav>

<form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none">
    @csrf
</form>

{{-- ── LAYOUT PRINCIPALE ───────────────────────────────────────── --}}
<div class="flex flex-1 overflow-hidden" style="height: calc(100vh - 53px)">

    {{-- SIDEBAR sinistra: elementi scoperti --}}
    <aside id="sidebar"
           class="w-56 bg-gray-900 border-r border-gray-800 flex flex-col overflow-hidden shrink-0
                  max-sm:fixed max-sm:inset-y-0 max-sm:left-0 max-sm:z-40 max-sm:w-64
                  max-sm:-translate-x-full max-sm:transition-transform max-sm:duration-200">
        <div class="px-3 py-2 border-b border-gray-800 flex items-center justify-between">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Elementi</span>
            <div class="flex items-center gap-2">
                <span id="element-count" class="text-xs text-gray-600">0</span>
                {{-- Close button (mobile only) --}}
                <button id="sidebar-close"
                        class="sm:hidden text-gray-500 hover:text-white text-lg leading-none"
                        onclick="toggleSidebar()">×</button>
            </div>
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

    {{-- Overlay mobile per chiudere sidebar --}}
    <div id="sidebar-overlay"
         class="hidden fixed inset-0 bg-black/50 z-30 sm:hidden"
         onclick="toggleSidebar()"></div>

    {{-- BOARD centrale --}}
    <main id="board"
          class="flex-1 relative overflow-hidden bg-gray-950 select-none"
          data-combine-url="{{ route('game.combine') }}"
          data-elements-url="{{ route('game.elements') }}"
          data-poll-url="{{ url('/game/combine/poll') }}"
          data-roid="{{ $room->roid }}"
          data-multiplayer="{{ $isMultiplayer ? 'true' : 'false' }}"
          data-uid="{{ auth()->id() }}"
          data-local-ai="{{ app()->isLocal() ? 'true' : 'false' }}"
          data-my-discoveries='@json($myDiscoveries)'
          data-base-elements='@json($roomElements)'>

        {{-- Pulsante apri sidebar (mobile only) --}}
        <button id="sidebar-toggle"
                class="sm:hidden absolute top-3 left-3 z-20 bg-gray-800 border border-gray-700
                       text-gray-300 rounded-lg px-3 py-1.5 text-xs font-medium"
                onclick="toggleSidebar()">
            ☰ Elementi
        </button>

        {{-- Messaggio centrale quando la board è vuota --}}
        <div id="board-hint"
             class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <p class="text-gray-700 text-sm text-center px-4">
                Trascina un elemento dalla sidebar sulla board per iniziare
            </p>
        </div>

        {{-- Toast notifiche --}}
        <div id="toast-container"
             class="absolute top-4 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 z-50 pointer-events-none">
        </div>
    </main>

</div>

<script>
function toggleSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebar-overlay');
    const isOpen   = !sidebar.classList.contains('-translate-x-full');
    sidebar.classList.toggle('-translate-x-full', isOpen);
    overlay.classList.toggle('hidden', isOpen);
}

// ── Clear board ───────────────────────────────────────────────────
function clearBoard() {
    if (!confirm('Remove all elements from the board?')) return;
    // Access game.js state via window
    if (window._clearBoard) window._clearBoard();
}

// ── Theme toggle ──────────────────────────────────────────────────
function toggleTheme() {
    const isLight = document.documentElement.classList.toggle('light-theme');
    const theme   = isLight ? 'light' : 'dark';
    document.getElementById('theme-toggle').textContent = isLight ? '☀️' : '🌙';
    fetch('{{ route('preferences.theme') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ theme }),
    });
}

// Init theme button icon
document.addEventListener('DOMContentLoaded', () => {
    const isLight = document.documentElement.classList.contains('light-theme');
    const btn = document.getElementById('theme-toggle');
    if (btn) btn.textContent = isLight ? '☀️' : '🌙';

    // Show discovery count if > 0
    const num = parseInt(document.getElementById('nav-discovery-num')?.textContent ?? '0');
    if (num > 0) document.getElementById('nav-discovery-count')?.classList.remove('hidden');
});
</script>

@if($isMultiplayer && auth()->id() == $room->owner_uid)
{{-- Invite modal --}}
<div id="invite-modal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center z-50">
    <div class="bg-gray-900 border border-gray-700 rounded-2xl p-6 w-full max-w-sm mx-4 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">Invite a friend</h3>
            <button onclick="document.getElementById('invite-modal').classList.add('hidden')"
                    class="text-gray-500 hover:text-white transition text-xl leading-none">×</button>
        </div>
        <p class="text-gray-400 text-sm">
            Share the room code <span class="font-mono text-indigo-300 font-bold">{{ $room->code }}</span>
            or send an email invite.
        </p>
        <div id="invite-form" class="space-y-3">
            <input type="email" id="invite-email" placeholder="friend@example.com"
                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500">
            <button onclick="sendInviteEmail()"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium py-2 rounded-lg transition">
                Send invite
            </button>
            <p id="invite-status" class="text-xs text-center text-gray-500"></p>
        </div>
    </div>
</div>

<script>
async function sendInviteEmail() {
    const email  = document.getElementById('invite-email').value.trim();
    const status = document.getElementById('invite-status');
    if (!email) { status.textContent = 'Please enter an email.'; return; }

    status.textContent = 'Sending…';
    try {
        const res = await fetch('{{ route('game.multiplayer.invite') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ roid: {{ $room->roid }}, email }),
        });
        const data = await res.json();
        status.textContent = data.message;
        status.style.color = data.success ? '#86efac' : '#fca5a5';
        if (data.success) document.getElementById('invite-email').value = '';
    } catch {
        status.textContent = 'Network error.';
        status.style.color = '#fca5a5';
    }
}
</script>
@endif

</body>
</html>

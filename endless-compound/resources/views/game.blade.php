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
            @if(auth()->id() == $room->owner_uid)
            <button onclick="document.getElementById('invite-modal').classList.remove('hidden')"
                    class="text-indigo-400 hover:text-indigo-300 transition text-xs">
                ✉️ Invite
            </button>
            @endif
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
          data-local-ai="{{ app()->isLocal() ? 'true' : 'false' }}"
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

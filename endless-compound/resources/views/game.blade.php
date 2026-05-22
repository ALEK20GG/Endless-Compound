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
        {{-- Tree button --}}
        <button onclick="openTreeModal()"
                class="text-gray-500 hover:text-green-400 transition text-xs"
                title="Discovery tree">
            🌳 Tree
        </button>
        {{-- Reset zoom (hidden when zoom=1) --}}
        <button id="reset-zoom-btn"
                onclick="resetZoom()"
                class="hidden text-gray-500 hover:text-indigo-400 transition text-xs"
                title="Reset zoom">
            🔍 Reset zoom
        </button>
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
        @if(auth()->user()->is_admin ?? false)
        <a href="{{ route('admin.panel') }}" class="text-yellow-500 hover:text-yellow-400 transition text-xs">🛡 Admin</a>
        @endif
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
          data-tree-url="{{ url('/game/' . $room->roid . '/tree') }}"
          data-chat-send-url="{{ route('game.chat.send') }}"
          data-chat-poll-url="{{ route('game.chat.poll') }}"
          data-roid="{{ $room->roid }}"
          data-multiplayer="{{ $isMultiplayer ? 'true' : 'false' }}"
          data-uid="{{ auth()->id() }}"
          data-username="{{ auth()->user()->username }}"
          data-local-ai="{{ app()->isLocal() ? 'true' : 'false' }}"
          data-my-discoveries='@json($myDiscoveries)'
          data-base-elements='@json($roomElements)'>

        {{-- Zoom/pan wrapper --}}
        <div id="board-canvas" style="position:absolute;top:0;left:0;width:100%;height:100%;transform-origin:0 0;">
        </div>

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

{{-- ── CHAT PANEL (multiplayer only) ─────────────────────────── --}}
@if($isMultiplayer)
<div id="chat-panel"
     class="hidden fixed bottom-4 right-4 z-40 w-72 bg-gray-900 border border-gray-700 rounded-xl shadow-2xl flex flex-col"
     style="height: 320px;">
    <div class="flex items-center justify-between px-3 py-2 border-b border-gray-800">
        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">💬 Room Chat</span>
        <button onclick="toggleChat()" class="text-gray-500 hover:text-white text-lg leading-none">×</button>
    </div>
    <div id="chat-messages" class="flex-1 overflow-y-auto px-3 py-2 space-y-2 text-sm">
        {{-- populated by JS --}}
    </div>
    <div class="px-3 py-2 border-t border-gray-800 flex gap-2">
        <input id="chat-input"
               type="text"
               maxlength="100"
               placeholder="Message… (Enter to send)"
               class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-2 py-1.5 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-indigo-500 min-w-0">
        <button onclick="sendChatMessage()"
                class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition shrink-0">
            Send
        </button>
    </div>
</div>
@endif

{{-- ── TREE MODAL ──────────────────────────────────────────────── --}}
<div id="tree-modal"
     class="hidden fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
     onclick="if(event.target===this) closeTreeModal()">
    <div class="bg-gray-900 border border-gray-800 rounded-2xl w-full max-w-4xl max-h-[85vh] flex flex-col shadow-2xl">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-800">
            <h3 class="text-lg font-semibold">🌳 Discovery Tree</h3>
            <button onclick="closeTreeModal()" class="text-gray-500 hover:text-white text-2xl leading-none">×</button>
        </div>
        <div id="tree-content" class="flex-1 overflow-y-auto p-5">
            <p class="text-gray-500 text-sm text-center py-8">Loading…</p>
        </div>
    </div>
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

// ── Tree modal ────────────────────────────────────────────────────
async function openTreeModal() {
    document.getElementById('tree-modal').classList.remove('hidden');
    const board = document.getElementById('board');
    const treeUrl = board.dataset.treeUrl;
    const content = document.getElementById('tree-content');
    content.innerHTML = '<p class="text-gray-500 text-sm text-center py-8">Loading…</p>';
    try {
        const res  = await fetch(treeUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        if (!data.success) { content.innerHTML = '<p class="text-red-400 text-sm text-center py-8">Error loading tree.</p>'; return; }
        renderTree(data.nodes, data.edges, content);
    } catch {
        content.innerHTML = '<p class="text-red-400 text-sm text-center py-8">Network error.</p>';
    }
}

function closeTreeModal() {
    document.getElementById('tree-modal').classList.add('hidden');
}

function renderTree(nodes, edges, container) {
    if (!nodes.length) {
        container.innerHTML = '<p class="text-gray-500 text-sm text-center py-8">No discoveries yet in this room.</p>';
        return;
    }

    // Build a map: cid → node
    const nodeMap = {};
    nodes.forEach(n => { nodeMap[n.cid] = n; });

    // Build children map: result cid → list of {a, b} pairs
    const recipesByResult = {};
    edges.forEach(e => {
        if (!recipesByResult[e.result]) recipesByResult[e.result] = [];
        recipesByResult[e.result].push({ a: e.a, b: e.b });
    });

    // Base elements: nodes with no incoming edges (not a result of any recipe)
    const resultCids = new Set(edges.map(e => e.result));
    const baseNodes  = nodes.filter(n => !resultCids.has(n.cid));
    const derivedNodes = nodes.filter(n => resultCids.has(n.cid));

    let html = '';

    // ── Base elements section ──────────────────────────────────
    html += `<div class="mb-6">
        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Base Elements (${baseNodes.length})</h4>
        <div class="flex flex-wrap gap-2">`;
    baseNodes.forEach(n => {
        html += `<span class="inline-flex items-center gap-1.5 bg-gray-800 border border-gray-700 rounded-full px-3 py-1 text-sm">
            <span>${esc(n.emoji)}</span><span class="text-gray-200">${esc(n.name)}</span>
        </span>`;
    });
    html += `</div></div>`;

    // ── Recipes section ────────────────────────────────────────
    if (edges.length > 0) {
        html += `<div>
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Recipes (${edges.length})</h4>
            <div class="space-y-2">`;

        // Group by result
        const grouped = {};
        edges.forEach(e => {
            if (!grouped[e.result]) grouped[e.result] = [];
            grouped[e.result].push(e);
        });

        Object.entries(grouped).forEach(([resultCid, recipes]) => {
            const result = nodeMap[resultCid];
            if (!result) return;
            recipes.forEach(r => {
                const na = nodeMap[r.a], nb = nodeMap[r.b];
                if (!na || !nb) return;
                html += `<div class="flex items-center gap-2 bg-gray-800/60 border border-gray-700/50 rounded-lg px-3 py-2 text-sm flex-wrap">
                    <span class="inline-flex items-center gap-1 bg-gray-700 rounded-full px-2 py-0.5">${esc(na.emoji)} <span class="text-gray-300">${esc(na.name)}</span></span>
                    <span class="text-gray-600">+</span>
                    <span class="inline-flex items-center gap-1 bg-gray-700 rounded-full px-2 py-0.5">${esc(nb.emoji)} <span class="text-gray-300">${esc(nb.name)}</span></span>
                    <span class="text-gray-600">→</span>
                    <span class="inline-flex items-center gap-1 bg-indigo-900/60 border border-indigo-700/50 rounded-full px-2 py-0.5 text-indigo-200">${esc(result.emoji)} <span>${esc(result.name)}</span></span>
                </div>`;
            });
        });

        html += `</div></div>`;
    }

    // ── Summary ────────────────────────────────────────────────
    html += `<div class="mt-6 pt-4 border-t border-gray-800 flex gap-6 text-xs text-gray-500">
        <span>🧪 ${nodes.length} elements</span>
        <span>📜 ${edges.length} recipes</span>
    </div>`;

    container.innerHTML = html;
}

function esc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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

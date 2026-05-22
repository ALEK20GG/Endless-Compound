<!DOCTYPE html>
<html lang="it" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Endless Compound</title>
    @vite(['resources/css/app.css', 'resources/css/style.css', 'resources/js/app.js', 'resources/js/game.js'])
    <script>
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
        <button onclick="openTreeModal()" class="text-gray-500 hover:text-green-400 transition text-xs" title="Discovery tree">🌳 Tree</button>
        <button id="reset-zoom-btn" onclick="resetZoom()" class="hidden text-gray-500 hover:text-indigo-400 transition text-xs" title="Reset zoom">🔍 Reset zoom</button>
        <span id="nav-discovery-count" class="hidden text-xs bg-yellow-900/50 border border-yellow-700/50 text-yellow-400 px-2 py-0.5 rounded font-mono">
            🏆 <span id="nav-discovery-num">{{ count($myDiscoveries) }}</span>
        </span>
        <button onclick="clearBoard()" class="text-gray-600 hover:text-red-400 transition text-xs hidden sm:inline" title="Clear board">🗑 Clear</button>
        <button id="theme-toggle" onclick="toggleTheme()" class="text-gray-500 hover:text-white transition text-xs" title="Toggle theme">🌙</button>
        <span class="text-gray-400 hidden sm:inline">{{ auth()->user()->username }}</span>
        <a href="{{ route('profile') }}" class="text-gray-500 hover:text-white transition text-xs">Profile</a>
        <a href="{{ route('leaderboard') }}" class="text-gray-500 hover:text-white transition text-xs hidden sm:inline">🏆</a>
        @if(auth()->user()->is_admin ?? false)
        <a href="{{ route('admin.panel') }}" class="text-yellow-500 hover:text-yellow-400 transition text-xs">🛡 Admin</a>
        @endif
        <button onclick="document.getElementById('logout-form').submit()" class="text-gray-500 hover:text-white transition text-xs">Log out</button>
    </div>
</nav>

<form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none">@csrf</form>

{{-- ── LAYOUT PRINCIPALE ───────────────────────────────────────── --}}
<div class="flex flex-1 overflow-hidden" style="height: calc(100vh - 53px)">

    <aside id="sidebar" class="w-56 bg-gray-900 border-r border-gray-800 flex flex-col overflow-hidden shrink-0
                  max-sm:fixed max-sm:inset-y-0 max-sm:left-0 max-sm:z-40 max-sm:w-64
                  max-sm:-translate-x-full max-sm:transition-transform max-sm:duration-200">
        <div class="px-3 py-2 border-b border-gray-800 flex items-center justify-between">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Elementi</span>
            <div class="flex items-center gap-2">
                <span id="element-count" class="text-xs text-gray-600">0</span>
                <button id="sidebar-close" class="sm:hidden text-gray-500 hover:text-white text-lg leading-none" onclick="toggleSidebar()">×</button>
            </div>
        </div>
        <div class="px-2 py-2">
            <input id="sidebar-search" type="search" placeholder="Cerca…"
                   class="w-full bg-gray-800 text-sm text-white rounded px-2 py-1 outline-none placeholder-gray-600 border border-gray-700 focus:border-indigo-500">
        </div>
        <ul id="element-list" class="flex-1 overflow-y-auto px-2 pb-2 space-y-1"></ul>
    </aside>

    <div id="sidebar-overlay" class="hidden fixed inset-0 bg-black/50 z-30 sm:hidden" onclick="toggleSidebar()"></div>

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

        <div id="board-canvas" style="position:absolute;top:0;left:0;width:100%;height:100%;transform-origin:0 0;"></div>

        <button id="sidebar-toggle" class="sm:hidden absolute top-3 left-3 z-20 bg-gray-800 border border-gray-700 text-gray-300 rounded-lg px-3 py-1.5 text-xs font-medium" onclick="toggleSidebar()">☰ Elementi</button>

        <div id="board-hint" class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <p class="text-gray-700 text-sm text-center px-4">Trascina un elemento dalla sidebar sulla board per iniziare</p>
        </div>

        <div id="toast-container" class="absolute top-4 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 z-50 pointer-events-none"></div>
    </main>
</div>

{{-- ── CHAT PANEL (multiplayer only) ─────────────────────────── --}}
@if($isMultiplayer)
<div id="chat-panel"
     style="display:none; position:fixed; bottom:0; right:24px; width:300px; height:380px; z-index:40; flex-direction:column;">
    <div style="display:flex; flex-direction:column; height:100%; background:#111827; border:1px solid #374151; border-bottom:none; border-radius:12px 12px 0 0; overflow:hidden; box-shadow:0 -8px 32px rgba(0,0,0,0.5);">
        <button onclick="toggleChat()"
                style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:#1f2937; border-bottom:1px solid #374151; cursor:pointer; flex-shrink:0; width:100%; text-align:left;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:14px;">💬</span>
                <span style="font-size:13px; font-weight:600; color:#f9fafb;">Room Chat</span>
                <span id="chat-unread" style="display:none; align-items:center; justify-content:center; background:#6366f1; color:white; font-size:11px; font-weight:700; border-radius:9999px; min-width:18px; height:18px; padding:0 4px; line-height:1;"></span>
            </div>
            <svg id="chat-chevron" style="width:16px; height:16px; color:#9ca3af; transition:transform 0.2s; transform:rotate(0deg);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div id="chat-messages" style="flex:1; overflow-y:auto; padding:12px; display:flex; flex-direction:column; gap:10px; min-height:0; scroll-behavior:smooth;"></div>
        <div style="display:flex; align-items:center; gap:8px; padding:10px 12px; background:#1f2937; border-top:1px solid #374151; flex-shrink:0;">
            <input id="chat-input" type="text" maxlength="100" placeholder="Scrivi un messaggio…" autocomplete="off"
                   style="flex:1; background:#374151; border:1px solid #4b5563; border-radius:10px; padding:8px 12px; font-size:13px; color:white; outline:none; min-width:0;"
                   onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#4b5563'">
            <button onclick="sendChatMessage()"
                    style="display:flex; align-items:center; justify-content:center; width:34px; height:34px; background:#6366f1; border-radius:10px; flex-shrink:0; cursor:pointer;"
                    onmouseover="this.style.background='#4f46e5'" onmouseout="this.style.background='#6366f1'">
                <svg style="width:16px; height:16px; color:white;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            </button>
        </div>
    </div>
</div>
@endif

{{-- ── TREE MODAL ──────────────────────────────────────────────── --}}
<div id="tree-modal"
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:50; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#0f172a; border:1px solid #1e293b; border-radius:14px; width:100%; max-width:860px; max-height:85vh; display:flex; flex-direction:column; box-shadow:0 30px 80px rgba(0,0,0,0.8);">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid #1e293b; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:16px;">🌳</span>
                <span style="font-size:13px; font-weight:600; color:#f1f5f9;">Discovery Tree</span>
                <span id="tree-stats" style="font-size:11px; color:#475569;"></span>
            </div>
            <button onclick="closeTreeModal()" style="background:transparent; border:none; color:#475569; font-size:22px; cursor:pointer; line-height:1; padding:0 4px;">×</button>
        </div>
        <div id="tree-content" style="flex:1; overflow-y:auto; padding:16px; min-height:0;">
            <p style="color:#475569; font-size:13px;">Loading…</p>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const isOpen  = !sidebar.classList.contains('-translate-x-full');
    sidebar.classList.toggle('-translate-x-full', isOpen);
    overlay.classList.toggle('hidden', isOpen);
}

function clearBoard() {
    if (!confirm('Remove all elements from the board?')) return;
    if (window._clearBoard) window._clearBoard();
}

function toggleTheme() {
    const isLight = document.documentElement.classList.toggle('light-theme');
    document.getElementById('theme-toggle').textContent = isLight ? '☀️' : '🌙';
    fetch('{{ route('preferences.theme') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ theme: isLight ? 'light' : 'dark' }),
    });
}

// ── Tree modal ────────────────────────────────────────────────────
async function openTreeModal() {
    const modal = document.getElementById('tree-modal');
    modal.style.display = 'flex';
    const content = document.getElementById('tree-content');
    content.innerHTML = '<p style="color:#475569;font-size:13px;">Loading…</p>';
    try {
        const board = document.getElementById('board');
        const res = await fetch(board.dataset.treeUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        if (!data.success) { content.innerHTML = '<p style="color:#f87171;font-size:13px;">Error loading tree.</p>'; return; }
        renderTree(data.nodes, data.edges, content);
    } catch (err) {
        content.innerHTML = '<p style="color:#f87171;font-size:13px;">Network error.</p>';
    }
}

function closeTreeModal() {
    document.getElementById('tree-modal').style.display = 'none';
}

function renderTree(nodes, edges, container) {
    const statsEl = document.getElementById('tree-stats');

    if (!nodes.length) {
        if (statsEl) statsEl.textContent = '';
        container.innerHTML = '<p style="color:#475569;font-size:13px;text-align:center;padding:40px 0;">No discoveries yet in this room.</p>';
        return;
    }

    if (statsEl) statsEl.textContent = `${nodes.length} elements · ${edges.length} recipes`;

    const nodeMap = {};
    nodes.forEach(n => { nodeMap[n.cid] = n; });
    const resultCids = new Set(edges.map(e => e.result));

    // Base elements: hardcoded Water/Fire/Earth/Air + any node not produced by a recipe
    const BASE_NAMES = new Set(['water', 'fire', 'earth', 'air']);
    const baseNodes = nodes.filter(n =>
        BASE_NAMES.has(n.name.toLowerCase()) || !resultCids.has(n.cid)
    );

    let html = '';

    // Base elements row
    if (baseNodes.length) {
        html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #1e293b;">';
        baseNodes.forEach(n => {
            html += `<span style="background:#0f172a;border:1px solid #334155;border-radius:20px;padding:4px 12px;font-size:12px;color:#94a3b8;">${esc(n.emoji??'✨')} ${esc(n.name)}</span>`;
        });
        html += '</div>';
    }

    // Recipe rows: A + B → Result (deduplicated)
    const byResult = {};
    edges.forEach(e => { (byResult[e.result] = byResult[e.result] || []).push(e); });

    const seenRecipes = new Set();
    Object.entries(byResult).forEach(([resultCid, recipes]) => {
        const result = nodeMap[resultCid];
        if (!result) return;
        recipes.forEach(r => {
            const key = [r.a, r.b].sort().join('-') + '-' + r.result;
            if (seenRecipes.has(key)) return;
            seenRecipes.add(key);
            const na = nodeMap[r.a], nb = nodeMap[r.b];
            if (!na || !nb) return;
            html += `<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                <span style="background:#0f172a;border:1px solid #334155;border-radius:20px;padding:4px 12px;font-size:12px;color:#94a3b8;white-space:nowrap;">${esc(na.emoji??'✨')} ${esc(na.name)}</span>
                <span style="color:#334155;font-size:16px;font-weight:bold;line-height:1;">+</span>
                <span style="background:#0f172a;border:1px solid #334155;border-radius:20px;padding:4px 12px;font-size:12px;color:#94a3b8;white-space:nowrap;">${esc(nb.emoji??'✨')} ${esc(nb.name)}</span>
                <span style="color:#4b5563;font-size:14px;">→</span>
                <span style="background:#1e1b4b;border:1px solid #6366f1;border-radius:20px;padding:4px 12px;font-size:12px;color:#c7d2fe;white-space:nowrap;">${esc(result.emoji??'✨')} ${esc(result.name)}</span>
            </div>`;
        });
    });

    container.innerHTML = html || '<p style="color:#475569;font-size:13px;">No recipes yet.</p>';
}

function esc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('DOMContentLoaded', () => {
    const isLight = document.documentElement.classList.contains('light-theme');
    const btn = document.getElementById('theme-toggle');
    if (btn) btn.textContent = isLight ? '☀️' : '🌙';
    const num = parseInt(document.getElementById('nav-discovery-num')?.textContent ?? '0');
    if (num > 0) document.getElementById('nav-discovery-count')?.classList.remove('hidden');
});
</script>

@if($isMultiplayer && auth()->id() == $room->owner_uid)
<div id="invite-modal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center z-50">
    <div class="bg-gray-900 border border-gray-700 rounded-2xl p-6 w-full max-w-sm mx-4 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">Invite a friend</h3>
            <button onclick="document.getElementById('invite-modal').classList.add('hidden')" class="text-gray-500 hover:text-white transition text-xl leading-none">×</button>
        </div>
        <p class="text-gray-400 text-sm">
            Share the room code <span class="font-mono text-indigo-300 font-bold">{{ $room->code }}</span> or send an email invite.
        </p>
        <div id="invite-form" class="space-y-3">
            <input type="email" id="invite-email" placeholder="friend@example.com"
                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500">
            <button onclick="sendInviteEmail()" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium py-2 rounded-lg transition">Send invite</button>
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
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
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

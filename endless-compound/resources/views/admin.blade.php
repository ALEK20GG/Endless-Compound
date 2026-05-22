<!DOCTYPE html>
<html lang="it" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin — Endless Compound</title>
    @vite(['resources/css/app.css', 'resources/css/style.css'])
</head>
<body class="bg-gray-950 text-white min-h-screen">

{{-- Navbar --}}
<nav class="bg-gray-900 border-b border-gray-800 px-4 py-3 flex items-center justify-between">
    <a href="{{ route('dashboard') }}" class="text-lg font-bold tracking-wide text-indigo-400">⚗️ Endless Compound</a>
    <div class="flex items-center gap-3 text-sm">
        <span class="text-yellow-400 font-semibold">🛡 Admin Panel</span>
        <a href="{{ route('game.index') }}" class="text-gray-400 hover:text-white transition">Game</a>
        <button onclick="document.getElementById('logout-form').submit()"
                class="text-gray-500 hover:text-white transition">Log out</button>
    </div>
</nav>
<form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none">@csrf</form>

<div class="max-w-6xl mx-auto px-4 py-8 space-y-10">

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="bg-green-900/50 border border-green-700 text-green-300 rounded-lg px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-900/50 border border-red-700 text-red-300 rounded-lg px-4 py-3 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- ── STATS ──────────────────────────────────────────────── --}}
    <section>
        <h2 class="text-xl font-bold text-indigo-400 mb-4">📊 Stats</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            @foreach([
                ['label' => 'Compounds', 'value' => $stats['compounds'], 'icon' => '⚗️'],
                ['label' => 'Recipes',   'value' => $stats['recipes'],   'icon' => '📜'],
                ['label' => 'Users',     'value' => $stats['users'],     'icon' => '👤'],
                ['label' => 'Rooms',     'value' => $stats['rooms'],     'icon' => '🏠'],
            ] as $s)
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 text-center">
                <div class="text-3xl mb-1">{{ $s['icon'] }}</div>
                <div class="text-2xl font-bold text-white">{{ number_format($s['value']) }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ $s['label'] }}</div>
            </div>
            @endforeach
        </div>
    </section>

    {{-- ── COMPOUNDS ───────────────────────────────────────────── --}}
    <section>
        <h2 class="text-xl font-bold text-indigo-400 mb-4">⚗️ Compounds
            <span class="text-sm font-normal text-gray-500 ml-2">Page {{ $compounds->currentPage() }} / {{ $compounds->lastPage() }}</span>
        </h2>
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-800 text-gray-400 text-xs uppercase tracking-wider">
                        <th class="px-4 py-3 text-left">CID</th>
                        <th class="px-4 py-3 text-left">Emoji</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left hidden sm:table-cell">Discoverer</th>
                        <th class="px-4 py-3 text-left hidden md:table-cell">Date</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @foreach($compounds as $c)
                    <tr class="hover:bg-gray-800/50 transition" id="compound-row-{{ $c->cid }}">
                        <td class="px-4 py-2 text-gray-500 font-mono text-xs">{{ $c->cid }}</td>
                        <td class="px-4 py-2 text-xl">{{ $c->emoji }}</td>
                        <td class="px-4 py-2 font-medium">{{ $c->name }}</td>
                        <td class="px-4 py-2 text-gray-400 hidden sm:table-cell">{{ $c->discoverer ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500 text-xs hidden md:table-cell">
                            {{ $c->discoveredat ? \Carbon\Carbon::parse($c->discoveredat)->format('d/m/Y H:i') : '—' }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button onclick="deleteCompound({{ $c->cid }}, '{{ addslashes($c->name) }}')"
                                    class="text-red-500 hover:text-red-400 transition text-xs font-medium px-2 py-1 rounded border border-red-800 hover:border-red-600">
                                Delete
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{-- Pagination --}}
        <div class="flex items-center justify-between mt-4 text-sm">
            <span class="text-gray-500">{{ $compounds->total() }} total compounds</span>
            <div class="flex gap-2">
                @if($compounds->onFirstPage())
                    <span class="text-gray-700 px-3 py-1 rounded border border-gray-800">← Prev</span>
                @else
                    <a href="{{ $compounds->previousPageUrl() }}"
                       class="text-indigo-400 hover:text-indigo-300 px-3 py-1 rounded border border-gray-700 hover:border-indigo-600 transition">← Prev</a>
                @endif
                @if($compounds->hasMorePages())
                    <a href="{{ $compounds->nextPageUrl() }}"
                       class="text-indigo-400 hover:text-indigo-300 px-3 py-1 rounded border border-gray-700 hover:border-indigo-600 transition">Next →</a>
                @else
                    <span class="text-gray-700 px-3 py-1 rounded border border-gray-800">Next →</span>
                @endif
            </div>
        </div>
    </section>

    {{-- ── USERS ───────────────────────────────────────────────── --}}
    <section>
        <h2 class="text-xl font-bold text-indigo-400 mb-4">👤 Users</h2>
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-800 text-gray-400 text-xs uppercase tracking-wider">
                        <th class="px-4 py-3 text-left">Username</th>
                        <th class="px-4 py-3 text-left hidden sm:table-cell">Email</th>
                        <th class="px-4 py-3 text-left hidden md:table-cell">Registered</th>
                        <th class="px-4 py-3 text-center">Discoveries</th>
                        <th class="px-4 py-3 text-center">Admin</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @foreach($users as $u)
                    <tr class="hover:bg-gray-800/50 transition" id="user-row-{{ $u->uid }}">
                        <td class="px-4 py-2 font-medium">
                            {{ $u->username }}
                            @if($u->uid == auth()->id())
                                <span class="text-xs text-indigo-400 ml-1">(you)</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-400 hidden sm:table-cell text-xs">{{ $u->email }}</td>
                        <td class="px-4 py-2 text-gray-500 text-xs hidden md:table-cell">
                            {{ $u->createdat ? \Carbon\Carbon::parse($u->createdat)->format('d/m/Y') : '—' }}
                        </td>
                        <td class="px-4 py-2 text-center">
                            <span class="bg-yellow-900/40 text-yellow-400 text-xs font-mono px-2 py-0.5 rounded">
                                {{ $u->discoveries }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-center">
                            @if($u->is_admin)
                                <span class="text-yellow-400 text-xs font-bold">🛡 Yes</span>
                            @else
                                <span class="text-gray-600 text-xs">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button onclick="toggleAdmin({{ $u->uid }}, '{{ addslashes($u->username) }}', {{ $u->is_admin ? 'true' : 'false' }})"
                                    class="text-xs font-medium px-2 py-1 rounded border transition
                                           {{ $u->is_admin ? 'text-yellow-500 border-yellow-800 hover:border-yellow-600' : 'text-gray-400 border-gray-700 hover:border-gray-500' }}">
                                {{ $u->is_admin ? 'Revoke Admin' : 'Make Admin' }}
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

async function deleteCompound(cid, name) {
    if (!confirm(`Delete compound "${name}" (cid=${cid}) and all its recipes?\nThis cannot be undone.`)) return;
    try {
        const res = await fetch(`/admin/compound/${cid}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById(`compound-row-${cid}`)?.remove();
            showFlash('Compound deleted.', 'success');
        } else {
            showFlash(data.message ?? 'Error deleting compound.', 'error');
        }
    } catch {
        showFlash('Network error.', 'error');
    }
}

async function toggleAdmin(uid, username, isAdmin) {
    const action = isAdmin ? 'revoke admin from' : 'grant admin to';
    if (!confirm(`Are you sure you want to ${action} "${username}"?`)) return;
    try {
        const res = await fetch(`/admin/user/${uid}/toggle-admin`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
        });
        const data = await res.json();
        if (data.success) {
            showFlash(data.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showFlash(data.message ?? 'Error.', 'error');
        }
    } catch {
        showFlash('Network error.', 'error');
    }
}

function showFlash(msg, type) {
    const el = document.createElement('div');
    el.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg text-sm font-medium shadow-lg transition
        ${type === 'success' ? 'bg-green-900 border border-green-700 text-green-300' : 'bg-red-900 border border-red-700 text-red-300'}`;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}
</script>

</body>
</html>

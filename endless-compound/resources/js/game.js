/**
 * Endless Compound — game frontend
 */

// ── State ────────────────────────────────────────────────────────
const state = {
    boardItems:       new Map(),
    sidebarCids:      new Set(),
    combineUrl:       '',
    elementsUrl:      '',
    pollUrl:          '',
    roid:             0,
    isMultiplayer:    false,
    lastUpdated:      null,
    currentUid:       0,
    nextId:           1,
    combining:        false,
    sidebarDragGhost: null,
};
const ownDiscoveries = new Set();

// ── Init ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const board = document.getElementById('board');
    if (!board) return;
    state.combineUrl    = board.dataset.combineUrl;
    state.elementsUrl   = board.dataset.elementsUrl;
    state.pollUrl       = board.dataset.pollUrl ?? '';
    state.roid          = parseInt(board.dataset.roid, 10);
    state.isMultiplayer = board.dataset.multiplayer === 'true';
    state.currentUid    = parseInt(board.dataset.uid ?? '0', 10);

    // Popola le scoperte del player corrente dal PHP inline
    const myDisc = safeParseJson(board.dataset.myDiscoveries) ?? [];
    myDisc.forEach(cid => ownDiscoveries.add(Number(cid)));
    loadElements();
    localAI.init();
    initBoardDrop(board);
    initSidebarDrop();
    if (state.isMultiplayer) setInterval(pollNewElements, 3000);
});

// ── Sidebar ───────────────────────────────────────────────────────
async function loadElements() {
    try {
        const res  = await apiFetch(`${state.elementsUrl}?roid=${state.roid}`, 'GET');
        const data = await res.json();
        if (data.success) { renderSidebar(data.elements); state.lastUpdated = data.last_updated ?? null; }
    } catch (e) { console.error('loadElements error:', e); }
}

async function pollNewElements() {
    if (!state.lastUpdated) return;
    try {
        const res  = await apiFetch(`${state.elementsUrl}?roid=${state.roid}&since=${encodeURIComponent(state.lastUpdated)}`, 'GET');
        const data = await res.json();
        if (data.success && data.elements.length > 0) {
            data.elements.forEach(el => { if (!state.sidebarCids.has(el.cid)) addToSidebar(el); });
            state.lastUpdated = data.last_updated;
        }
    } catch { /* silent */ }
}

function renderSidebar(elements) {
    const list = document.getElementById('element-list');
    const count = document.getElementById('element-count');
    if (!list) return;
    list.innerHTML = '';
    state.sidebarCids.clear();
    count.textContent = elements.length;
    elements.forEach(el => { state.sidebarCids.add(el.cid); list.appendChild(makeSidebarItem(el)); });
    const search = document.getElementById('sidebar-search');
    if (search && !search.dataset.wired) {
        search.dataset.wired = '1';
        search.addEventListener('input', () => {
            const q = search.value.toLowerCase();
            list.querySelectorAll('.sidebar-item').forEach(i => {
                i.style.display = i.dataset.name.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
}

function makeSidebarItem(el) {
    const li = document.createElement('li');
    const isOwn = ownDiscoveries.has(Number(el.cid));
    li.className = 'sidebar-item' + (isOwn ? ' own-discovery' : '');
    li.dataset.name = el.name;
    li.dataset.cid  = el.cid;
    li.draggable    = true;
    li.innerHTML    = `<span class="item-emoji">${sanitize(el.emoji ?? '✨')}</span><span>${sanitize(el.name)}</span>`;

    li.addEventListener('dragstart', e => {
        // Spawn a hidden board item immediately so board items can receive the drop
        const ghostId = spawnBoardItem(el.name, el.emoji ?? '✨', el.cid, -200, -200, false);
        state.sidebarDragGhost = ghostId;

        e.dataTransfer.setData('application/x-element', JSON.stringify({
            name: el.name, emoji: el.emoji ?? '✨', cid: el.cid,
            from: 'board', id: ghostId,   // pretend it's a board item
        }));
        e.dataTransfer.effectAllowed = 'move';
    });

    li.addEventListener('dragend', () => {
        // If the ghost was never consumed by a combine, remove it
        if (state.sidebarDragGhost) {
            removeBoardItem(state.sidebarDragGhost);
            state.sidebarDragGhost = null;
        }
    });

    return li;
}

function addToSidebar(el) {
    if (state.sidebarCids.has(el.cid)) return;
    state.sidebarCids.add(el.cid);
    const list = document.getElementById('element-list');
    const count = document.getElementById('element-count');
    if (!list) return;
    list.appendChild(makeSidebarItem(el));
    count.textContent = state.sidebarCids.size;
}

// ── Board drop (empty area + sidebar→item combine) ───────────────
function initBoardDrop(board) {
    board.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; });
    board.addEventListener('drop', e => {
        e.preventDefault();
        const payload = safeParseJson(e.dataTransfer.getData('application/x-element'));
        if (!payload) return;

        const rect = board.getBoundingClientRect();
        const x = e.clientX - rect.left, y = e.clientY - rect.top;

        if (payload.from === 'board') {
            const item = state.boardItems.get(payload.id);
            if (item) {
                // Reposition (move or place ghost from sidebar)
                item.el.style.left = `${x - 50}px`;
                item.el.style.top  = `${y - 18}px`;
                // Ghost was placed on empty board — keep it, clear ghost ref
                if (state.sidebarDragGhost === payload.id) {
                    state.sidebarDragGhost = null;
                }
            }
        }
    });
}

/** findBoardItemAt kept for reference but no longer used */
function findBoardItemAt(clientX, clientY) {
    for (const [, item] of state.boardItems) {
        const r = item.el.getBoundingClientRect();
        if (clientX >= r.left && clientX <= r.right && clientY >= r.top && clientY <= r.bottom) {
            return item;
        }
    }
    return null;
}

// ── Sidebar drop (drag board item back → remove) ──────────────────
function initSidebarDrop() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    sidebar.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; });
    sidebar.addEventListener('drop', e => {
        e.preventDefault();
        const payload = safeParseJson(e.dataTransfer.getData('application/x-element'));
        if (payload?.from === 'board') removeBoardItem(payload.id);
    });
}

// ── Board items ───────────────────────────────────────────────────
function spawnBoardItem(name, emoji, cid, x, y, animate = true) {
    const id = `item-${state.nextId++}`;
    const el = document.createElement('div');
    const isOwn = cid && ownDiscoveries.has(Number(cid));
    el.className  = 'board-item' + (animate ? ' spawning' : '') + (isOwn ? ' own-discovery' : '');
    el.id         = id;
    el.draggable  = true;
    el.style.left = `${x - 50}px`;
    el.style.top  = `${y - 18}px`;
    el.innerHTML  = `<span class="item-emoji">${sanitize(emoji)}</span><span>${sanitize(name)}</span>`;

    let dragOffX = 0, dragOffY = 0;
    el.addEventListener('dragstart', e => {
        const r = el.getBoundingClientRect();
        dragOffX = e.clientX - r.left; dragOffY = e.clientY - r.top;
        e.dataTransfer.setData('application/x-element', JSON.stringify({ name, emoji, cid, from: 'board', id }));
        e.dataTransfer.effectAllowed = 'move';
        el.classList.add('dragging');
        e.stopPropagation();
    });
    el.addEventListener('dragend', e => {
        el.classList.remove('dragging');
        const board = document.getElementById('board');
        const bRect = board.getBoundingClientRect();
        const nx = e.clientX - bRect.left - dragOffX + 50;
        const ny = e.clientY - bRect.top  - dragOffY + 18;
        if (nx > 0 && ny > 0 && nx < bRect.width && ny < bRect.height) {
            el.style.left = `${nx - 50}px`; el.style.top = `${ny - 18}px`;
        }
    });

    el.addEventListener('dragover', e => {
        e.preventDefault();
        e.stopPropagation();
        // Accept both board items and sidebar items
        e.dataTransfer.dropEffect = 'move';
    });
    el.addEventListener('drop', async e => {
        e.preventDefault(); e.stopPropagation();
        if (state.combining) return;
        const payload = safeParseJson(e.dataTransfer.getData('application/x-element'));
        if (!payload || payload.id === id) return;
        const board = document.getElementById('board');
        const bRect = board.getBoundingClientRect();
        const eRect = el.getBoundingClientRect();
        const cx = eRect.left - bRect.left + eRect.width / 2;
        const cy = eRect.top  - bRect.top  + eRect.height / 2;
        // Remove the dragged item only if it came from the board
        if (payload.from === 'board') removeBoardItem(payload.id);
        // Clear ghost ref if this was a sidebar drag
        if (state.sidebarDragGhost === payload.id) state.sidebarDragGhost = null;
        // Remove this target item and combine
        el.classList.add('merging');
        await sleep(200);
        removeBoardItem(id);
        await doCombine(name, payload.name, cx, cy);
    });

    // Double-click → duplicate
    el.addEventListener('dblclick', e => {
        e.stopPropagation();
        const board = document.getElementById('board');
        const bRect = board.getBoundingClientRect();
        const eRect = el.getBoundingClientRect();
        spawnBoardItem(name, emoji, cid, eRect.left - bRect.left + 30, eRect.top - bRect.top + 30);
    });

    // Right-click → remove
    el.addEventListener('contextmenu', e => { e.preventDefault(); e.stopPropagation(); removeBoardItem(id); });

    document.getElementById('board').appendChild(el);
    state.boardItems.set(id, { id, name, emoji, cid, el });
    updateBoardHint();
    return id;
}

function removeBoardItem(id) {
    const item = state.boardItems.get(id);
    if (item) { item.el.remove(); state.boardItems.delete(id); updateBoardHint(); }
}

function updateBoardHint() {
    const hint = document.getElementById('board-hint');
    if (hint) hint.style.display = state.boardItems.size > 0 ? 'none' : '';
}

// ── Local AI ─────────────────────────────────────────────────────
const localAI = (() => {
    let worker = null, ready = false;
    const pending = new Map(); let reqId = 0;
    function init() {
        if (worker) return;
        try { worker = new Worker(new URL('./combination-worker.js', import.meta.url), { type: 'module' }); }
        catch { worker = null; return; }
        worker.addEventListener('message', e => {
            const m = e.data;
            if (m.type === 'ready') ready = true;
            else if (m.type === 'result') { pending.get(m.id)?.resolve(m.result); pending.delete(m.id); }
            else if (m.type === 'error')  { pending.get(m.id)?.reject(new Error(m.error)); pending.delete(m.id); }
        });
        worker.addEventListener('error', (e) => {
            console.warn('[LocalAI] Worker failed, falling back to server:', e.message ?? e);
            worker = null; ready = false;
            pending.forEach(({ resolve }) => resolve(null));
            pending.clear();
        });
    }
    function combine(a, b) {
        if (!worker) return Promise.resolve(null);
        return new Promise((resolve, reject) => {
            const id = ++reqId;
            pending.set(id, { resolve, reject });
            worker.postMessage({ id, elementA: a, elementB: b });
            setTimeout(() => { if (pending.has(id)) { pending.delete(id); resolve(null); } }, 20000);
        });
    }
    return { init, combine, isReady: () => ready };
})();

// ── Combine ───────────────────────────────────────────────────────
/** Poll the server until the async combine job is done */
async function pollCombineResult(jobId, maxAttempts = 40) {
    const pollBase = state.pollUrl ?? state.combineUrl.replace('/combine', '/combine/poll');
    for (let i = 0; i < maxAttempts; i++) {
        await sleep(2000);
        try {
            const res  = await apiFetch(`${pollBase}/${jobId}`, 'GET');
            if (res.status === 404) return null; // job expired
            const data = await res.json();
            if (!data.pending) return data; // done (success or error)
        } catch { /* retry */ }
    }
    return null; // timeout
}

async function doCombine(nameA, nameB, x, y) {
    if (state.combining) return;
    state.combining = true;
    showToast(`⚗️ ${nameA} + ${nameB}…`, 'info');
    try {
        let localResult = null;
        try { const raw = await localAI.combine(nameA, nameB); if (raw) localResult = parseLocalResult(raw); } catch {}
        if (localResult) {
            persistCombination(nameA, nameB, localResult);
            spawnBoardItem(localResult.name, localResult.emoji, null, x, y);
            showToast(`${localResult.emoji} ${localResult.name}`, 'success');
            state.combining = false; return;
        }
        const res  = await apiFetch(state.combineUrl, 'POST', { roid: state.roid, element_a: nameA, element_b: nameB });
        let data = await res.json();

        // Async job: poll until done
        if (data.pending && data.job_id) {
            showToast('⏳ Generating…', 'info');
            data = await pollCombineResult(data.job_id);
            if (!data) { showToast('Combination failed. Please try again.', 'error'); state.combining = false; return; }
        }

        if (!data.success) { showToast(data.message ?? 'Combination failed', 'error'); state.combining = false; return; }
        const result = data.result;
        spawnBoardItem(result.name, result.emoji, result.cid, x, y);
        if (data.new_in_room && !state.sidebarCids.has(result.cid)) addToSidebar(result);
        if (data.first_discovery) { ownDiscoveries.add(result.cid); showToast(`🏆 First world discovery: ${result.emoji} ${result.name}!`, 'first'); }
        else if (data.new_in_room) showToast(`✨ New element: ${result.emoji} ${result.name}`, 'success');
        else showToast(`${result.emoji} ${result.name}`, 'success');
    } catch (e) { console.error('doCombine error:', e); showToast('Network error. Please try again.', 'error'); }
    state.combining = false;
}

function parseLocalResult(text) {
    text = text.trim();
    const emojiMatch = text.match(/(\p{Emoji_Presentation}|\p{Extended_Pictographic})/u);
    const emoji = emojiMatch?.[0] ?? null;
    let name = text.replace(/(\p{Emoji_Presentation}|\p{Extended_Pictographic})/gu, '').trim();
    name = name.replace(/^[→\-\s]+/, '').trim().split('\n')[0].trim().slice(0, 60);
    if (!name || name.length < 2) return null;
    if (name.toLowerCase().includes('combining') || name.toLowerCase().includes('infinite craft')) return null;
    return { emoji: emoji ?? '✨', name };
}

async function persistCombination(nameA, nameB, result) {
    try {
        const res  = await apiFetch(state.combineUrl, 'POST', { roid: state.roid, element_a: nameA, element_b: nameB, local_result: result.name, local_emoji: result.emoji });
        const data = await res.json();
        if (data.success && data.result) {
            const r = data.result;
            if (!state.sidebarCids.has(r.cid)) addToSidebar(r);
            if (data.first_discovery) ownDiscoveries.add(r.cid);
        }
    } catch {}
}

// ── Toast ─────────────────────────────────────────────────────────
function showToast(message, type = 'info') {
    const c = document.getElementById('toast-container');
    if (!c) return;
    const t = document.createElement('div');
    t.className = `toast toast-${type}`; t.textContent = message;
    c.appendChild(t); setTimeout(() => t.remove(), 3000);
}

// ── Utils ─────────────────────────────────────────────────────────
async function apiFetch(url, method = 'GET', body = null) {
    const opts = { method, headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrfToken() } };
    if (body) opts.body = JSON.stringify(body);
    return fetch(url, opts);
}
function getCsrfToken() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''; }
function safeParseJson(v) { try { return JSON.parse(v ?? 'null'); } catch { return null; } }
function sanitize(str) { return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

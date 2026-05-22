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
    // Touch state
    touchDrag:        null,   // { id, el, offX, offY, fromSidebar, name, emoji, cid }
    touchClone:       null,   // visual clone following finger
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

    const myDisc = safeParseJson(board.dataset.myDiscoveries) ?? [];
    myDisc.forEach(cid => ownDiscoveries.add(Number(cid)));
    loadElements();
    if (board.dataset.localAi === 'true') localAI.init();
    initBoardDrop(board);
    initSidebarDrop();
    initTouchHandlers();
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
        const ghostId = spawnBoardItem(el.name, el.emoji ?? '✨', el.cid, -200, -200, false);
        state.sidebarDragGhost = ghostId;
        e.dataTransfer.setData('application/x-element', JSON.stringify({
            name: el.name, emoji: el.emoji ?? '✨', cid: el.cid,
            from: 'board', id: ghostId,
        }));
        e.dataTransfer.effectAllowed = 'move';
    });

    li.addEventListener('dragend', () => {
        if (state.sidebarDragGhost) {
            removeBoardItem(state.sidebarDragGhost);
            state.sidebarDragGhost = null;
        }
    });

    // Touch: drag from sidebar
    li.addEventListener('touchstart', e => {
        e.stopPropagation();
        const touch = e.touches[0];
        state.touchDrag = {
            id: null,
            fromSidebar: true,
            name: el.name,
            emoji: el.emoji ?? '✨',
            cid: el.cid,
            offX: 0,
            offY: 0,
        };
        createTouchClone(el.emoji ?? '✨', el.name, touch.clientX, touch.clientY);
    }, { passive: true });

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

// ── Board drop ────────────────────────────────────────────────────
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
                item.el.style.left = `${x - 50}px`;
                item.el.style.top  = `${y - 18}px`;
                if (state.sidebarDragGhost === payload.id) state.sidebarDragGhost = null;
            }
        }
    });
}

// ── Sidebar drop ──────────────────────────────────────────────────
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

// ── Touch handlers ────────────────────────────────────────────────
function initTouchHandlers() {
    document.addEventListener('touchmove', onTouchMove, { passive: false });
    document.addEventListener('touchend',  onTouchEnd,  { passive: true });
    document.addEventListener('touchcancel', cancelTouchDrag, { passive: true });
}

function createTouchClone(emoji, name, clientX, clientY) {
    removeTouchClone();
    const clone = document.createElement('div');
    clone.className = 'board-item dragging';
    clone.style.position = 'fixed';
    clone.style.zIndex   = '9999';
    clone.style.pointerEvents = 'none';
    clone.style.opacity  = '0.85';
    clone.innerHTML = `<span class="item-emoji">${sanitize(emoji)}</span><span>${sanitize(name)}</span>`;
    positionTouchClone(clone, clientX, clientY);
    document.body.appendChild(clone);
    state.touchClone = clone;
}

function positionTouchClone(clone, clientX, clientY) {
    clone.style.left = `${clientX - 50}px`;
    clone.style.top  = `${clientY - 18}px`;
}

function removeTouchClone() {
    if (state.touchClone) { state.touchClone.remove(); state.touchClone = null; }
}

function onTouchMove(e) {
    if (!state.touchDrag) return;
    e.preventDefault();
    const touch = e.touches[0];
    if (state.touchClone) positionTouchClone(state.touchClone, touch.clientX, touch.clientY);
    if (state.touchDrag.id) {
        const item = state.boardItems.get(state.touchDrag.id);
        if (item) {
            const board = document.getElementById('board');
            const bRect = board.getBoundingClientRect();
            item.el.style.left = `${touch.clientX - bRect.left - state.touchDrag.offX}px`;
            item.el.style.top  = `${touch.clientY - bRect.top  - state.touchDrag.offY}px`;
        }
    }
}

function onTouchEnd(e) {
    if (!state.touchDrag) return;
    const touch = e.changedTouches[0];
    removeTouchClone();
    const board = document.getElementById('board');
    const bRect = board.getBoundingClientRect();
    const onBoard = touch.clientX >= bRect.left && touch.clientX <= bRect.right &&
                    touch.clientY >= bRect.top  && touch.clientY <= bRect.bottom;
    if (!onBoard) {
        if (state.touchDrag.id) removeBoardItem(state.touchDrag.id);
        state.touchDrag = null;
        return;
    }
    const dropX = touch.clientX - bRect.left;
    const dropY = touch.clientY - bRect.top;
    const targetEntry = findBoardItemAtClient(touch.clientX, touch.clientY, state.touchDrag.id);
    if (targetEntry) {
        const cx = targetEntry.el.getBoundingClientRect().left - bRect.left + targetEntry.el.offsetWidth / 2;
        const cy = targetEntry.el.getBoundingClientRect().top  - bRect.top  + targetEntry.el.offsetHeight / 2;
        if (state.touchDrag.id) removeBoardItem(state.touchDrag.id);
        targetEntry.el.classList.add('merging');
        const dragName = state.touchDrag.name;
        const targetId = targetEntry.id;
        setTimeout(() => { removeBoardItem(targetId); doCombine(dragName, targetEntry.name, cx, cy); }, 200);
    } else {
        if (state.touchDrag.fromSidebar) {
            spawnBoardItem(state.touchDrag.name, state.touchDrag.emoji, state.touchDrag.cid, dropX, dropY);
        } else if (state.touchDrag.id) {
            const item = state.boardItems.get(state.touchDrag.id);
            if (item) { item.el.style.left = `${dropX - 50}px`; item.el.style.top = `${dropY - 18}px`; }
        }
    }
    state.touchDrag = null;
}

function cancelTouchDrag() {
    removeTouchClone();
    if (state.touchDrag?.id) removeBoardItem(state.touchDrag.id);
    state.touchDrag = null;
}

function findBoardItemAtClient(clientX, clientY, excludeId = null) {
    for (const [id, item] of state.boardItems) {
        if (id === excludeId) continue;
        const r = item.el.getBoundingClientRect();
        if (clientX >= r.left && clientX <= r.right && clientY >= r.top && clientY <= r.bottom) return item;
    }
    return null;
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
    el.addEventListener('dragover', e => { e.preventDefault(); e.stopPropagation(); e.dataTransfer.dropEffect = 'move'; });
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
        if (payload.from === 'board') removeBoardItem(payload.id);
        if (state.sidebarDragGhost === payload.id) state.sidebarDragGhost = null;
        el.classList.add('merging');
        await sleep(200);
        removeBoardItem(id);
        await doCombine(name, payload.name, cx, cy);
    });
    el.addEventListener('dblclick', e => {
        e.stopPropagation();
        const board = document.getElementById('board');
        const bRect = board.getBoundingClientRect();
        const eRect = el.getBoundingClientRect();
        spawnBoardItem(name, emoji, cid, eRect.left - bRect.left + 30, eRect.top - bRect.top + 30);
    });
    el.addEventListener('contextmenu', e => { e.preventDefault(); e.stopPropagation(); removeBoardItem(id); });
    el.addEventListener('touchstart', e => {
        e.stopPropagation();
        const touch = e.touches[0];
        const r = el.getBoundingClientRect();
        state.touchDrag = { id, fromSidebar: false, name, emoji, cid, offX: touch.clientX - r.left, offY: touch.clientY - r.top };
        createTouchClone(emoji, name, touch.clientX, touch.clientY);
        el.style.opacity = '0.3';
    }, { passive: true });
    el.addEventListener('touchend', () => { el.style.opacity = ''; }, { passive: true });

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

// Esposto per il pulsante "Clear board" nella navbar
window._clearBoard = function() {
    for (const [id] of [...state.boardItems]) removeBoardItem(id);
};

// ── Local AI ─────────────────────────────────────────────────────
const localAI = (() => {
    let worker = null, ready = false;
    const pending = new Map(); let reqId = 0;
    function init() {
        if (worker) return;
        try { worker = new Worker('/combination-worker.js', { type: 'module' }); }
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
async function pollCombineResult(jobId, maxAttempts = 40) {
    const pollBase = state.pollUrl ?? state.combineUrl.replace('/combine', '/combine/poll');
    for (let i = 0; i < maxAttempts; i++) {
        await sleep(2000);
        try {
            const res  = await apiFetch(`${pollBase}/${jobId}`, 'GET');
            if (res.status === 404) return null;
            const data = await res.json();
            if (!data.pending) return data;
        } catch { /* retry */ }
    }
    return null;
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

        if (res.status === 429) {
            showToast(data.message ?? 'Too many combinations. Please slow down.', 'error');
            state.combining = false; return;
        }

        if (data.pending && data.job_id) {
            showToast('⏳ Generating…', 'info');
            data = await pollCombineResult(data.job_id);
            if (!data) { showToast('Combination timed out. The AI might be busy — try again.', 'error'); state.combining = false; return; }
        }

        if (!data.success) {
            // Messaggio di errore più descrittivo
            const msg = data.message ?? 'Combination failed';
            const isServerDown = msg.toLowerCase().includes('unable') || msg.toLowerCase().includes('503') || res.status >= 500;
            showToast(isServerDown ? '🤖 AI is busy right now. Try again in a moment.' : msg, 'error');
            state.combining = false; return;
        }
        const result = data.result;
        spawnBoardItem(result.name, result.emoji, result.cid, x, y);
        if (data.new_in_room && !state.sidebarCids.has(result.cid)) addToSidebar(result);
        if (data.first_discovery) {
            ownDiscoveries.add(result.cid);
            updateNavDiscoveryCount(1);
            showToast(`🏆 First world discovery: ${result.emoji} ${result.name}!`, 'first');
        } else if (data.new_in_room) {
            showToast(`✨ New element: ${result.emoji} ${result.name}`, 'success');
        } else {
            showToast(`${result.emoji} ${result.name}`, 'success');
        }
    } catch (e) { console.error('doCombine error:', e); showToast('Network error. Please try again.', 'error'); }
    state.combining = false;
}

function updateNavDiscoveryCount(delta) {
    const numEl = document.getElementById('nav-discovery-num');
    const badge = document.getElementById('nav-discovery-count');
    if (!numEl || !badge) return;
    const current = parseInt(numEl.textContent ?? '0') + delta;
    numEl.textContent = current;
    if (current > 0) badge.classList.remove('hidden');
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
            if (data.first_discovery) { ownDiscoveries.add(r.cid); updateNavDiscoveryCount(1); }
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

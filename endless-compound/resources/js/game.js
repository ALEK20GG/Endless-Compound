/**
 * Endless Compound — logica frontend
 *
 * Funzionalità:
 * - Board con elementi trascinabili (drag & drop nativo)
 * - Sidebar con tutti gli elementi scoperti (aggiornata via AJAX)
 * - Combinazione per sovrapposizione (drop su un altro elemento)
 * - Toast notifiche
 * - Ricerca nella sidebar
 */

// ── Stato globale ────────────────────────────────────────────────
const state = {
    /** @type {Map<string, {id: string, name: string, emoji: string, el: HTMLElement}>} */
    boardItems: new Map(),

    /** @type {Set<string>} nomi degli elementi scoperti */
    discovered: new Set(),

    combineUrl:  '',
    elementsUrl: '',

    /** id univoco per gli item sulla board */
    nextId: 1,
};

// ── Init ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const board = document.getElementById('board');
    if (!board) return;

    state.combineUrl  = board.dataset.combineUrl;
    state.elementsUrl = board.dataset.elementsUrl;

    // Carica elementi base dal PHP inline
    const baseElements = safeParseJson(board.dataset.baseElements) ?? [];
    baseElements.forEach(el => state.discovered.add(el.name));

    // Carica tutti gli elementi dal DB (sidebar)
    loadElements();

    // Avvia il worker locale (scarica il modello in background)
    localAI.init();

    // Drag & drop sulla board
    initBoardDrop(board);
});

// ── Caricamento elementi (sidebar) ───────────────────────────────
async function loadElements() {
    try {
        const res  = await apiFetch(state.elementsUrl, 'GET');
        const data = await res.json();

        if (data.success) {
            renderSidebar(data.elements);
        }
    } catch (e) {
        console.error('loadElements error:', e);
    }
}

function renderSidebar(elements) {
    const list  = document.getElementById('element-list');
    const count = document.getElementById('element-count');
    if (!list) return;

    list.innerHTML = '';
    count.textContent = elements.length;

    elements.forEach(el => {
        state.discovered.add(el.name);
        list.appendChild(makeSidebarItem(el));
    });

    // Ricerca
    const search = document.getElementById('sidebar-search');
    if (search) {
        search.addEventListener('input', () => {
            const q = search.value.toLowerCase();
            list.querySelectorAll('.sidebar-item').forEach(item => {
                item.style.display = item.dataset.name.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
}

function makeSidebarItem(el) {
    const li = document.createElement('li');
    li.className = 'sidebar-item';
    li.dataset.name = el.name;
    li.draggable = true;
    li.innerHTML = `<span class="item-emoji">${sanitize(el.emoji ?? '✨')}</span>
                    <span>${sanitize(el.name)}</span>`;

    // Drag dalla sidebar → board
    li.addEventListener('dragstart', e => {
        e.dataTransfer.setData('application/x-element', JSON.stringify({
            name:  el.name,
            emoji: el.emoji ?? '✨',
            from:  'sidebar',
        }));
        e.dataTransfer.effectAllowed = 'copy';
    });

    return li;
}

// ── Board drop ───────────────────────────────────────────────────
function initBoardDrop(board) {
    board.addEventListener('dragover', e => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    });

    board.addEventListener('drop', e => {
        e.preventDefault();

        const raw = e.dataTransfer.getData('application/x-element');
        if (!raw) return;

        const payload = safeParseJson(raw);
        if (!payload) return;

        const boardRect = board.getBoundingClientRect();
        const x = e.clientX - boardRect.left;
        const y = e.clientY - boardRect.top;

        if (payload.from === 'sidebar') {
            // Spawn nuovo item sulla board
            spawnBoardItem(payload.name, payload.emoji, x, y);
        } else if (payload.from === 'board') {
            // Sposta item esistente
            const item = state.boardItems.get(payload.id);
            if (item) {
                item.el.style.left = `${x - 40}px`;
                item.el.style.top  = `${y - 18}px`;
            }
        }
    });
}

// ── Item sulla board ─────────────────────────────────────────────
function spawnBoardItem(name, emoji, x, y, animate = true) {
    const id  = `item-${state.nextId++}`;
    const el  = document.createElement('div');
    el.className   = 'board-item' + (animate ? ' spawning' : '');
    el.id          = id;
    el.draggable   = true;
    el.style.left  = `${x - 40}px`;
    el.style.top   = `${y - 18}px`;
    el.innerHTML   = `<span class="item-emoji">${sanitize(emoji)}</span>
                      <span>${sanitize(name)}</span>`;

    // Drag dell'item sulla board
    let dragOffsetX = 0, dragOffsetY = 0;

    el.addEventListener('dragstart', e => {
        const rect = el.getBoundingClientRect();
        dragOffsetX = e.clientX - rect.left;
        dragOffsetY = e.clientY - rect.top;

        e.dataTransfer.setData('application/x-element', JSON.stringify({
            name, emoji, from: 'board', id,
        }));
        e.dataTransfer.effectAllowed = 'move';
        el.classList.add('dragging');
    });

    el.addEventListener('dragend', () => el.classList.remove('dragging'));

    // Drop su un altro item → combina
    el.addEventListener('dragover', e => {
        e.preventDefault();
        e.stopPropagation();
        e.dataTransfer.dropEffect = 'move';
    });

    el.addEventListener('drop', async e => {
        e.preventDefault();
        e.stopPropagation();

        const raw = e.dataTransfer.getData('application/x-element');
        if (!raw) return;

        const payload = safeParseJson(raw);
        if (!payload || payload.id === id) return;

        // Rimuovi l'item trascinato dalla board
        if (payload.from === 'board') {
            removeBoardItem(payload.id);
        }

        // Posizione del target per far apparire il risultato
        const rect = el.getBoundingClientRect();
        const board = document.getElementById('board');
        const boardRect = board.getBoundingClientRect();
        const cx = rect.left - boardRect.left + rect.width / 2;
        const cy = rect.top  - boardRect.top  + rect.height / 2;

        // Animazione merge
        el.classList.add('merging');
        setTimeout(() => el.classList.remove('merging'), 400);

        await doCombine(name, payload.name, cx, cy, id);
    });

    // Doppio click → rimuovi dalla board
    el.addEventListener('dblclick', () => removeBoardItem(id));

    const board = document.getElementById('board');
    board.appendChild(el);

    state.boardItems.set(id, { id, name, emoji, el });
    updateBoardHint();

    return id;
}

function removeBoardItem(id) {
    const item = state.boardItems.get(id);
    if (item) {
        item.el.remove();
        state.boardItems.delete(id);
        updateBoardHint();
    }
}

function updateBoardHint() {
    const hint = document.getElementById('board-hint');
    if (hint) hint.style.display = state.boardItems.size > 0 ? 'none' : '';
}

// ── Local inference (Transformers.js Web Worker) ─────────────────
const localAI = (() => {
    let worker = null;
    let ready  = false;
    const pending = new Map(); // id → { resolve, reject }
    let reqId = 0;

    function init() {
        if (worker) return;
        try {
            worker = new Worker(new URL('./combination-worker.js', import.meta.url), { type: 'module' });
        } catch {
            // Worker not supported or module error — fall back to server
            worker = null;
            return;
        }

        worker.addEventListener('message', (e) => {
            const msg = e.data;
            if (msg.type === 'ready') {
                ready = true;
                console.log('[LocalAI] Model ready');
            } else if (msg.type === 'progress') {
                console.log(`[LocalAI] Downloading model: ${msg.pct}%`);
            } else if (msg.type === 'result') {
                pending.get(msg.id)?.resolve(msg.result);
                pending.delete(msg.id);
            } else if (msg.type === 'error') {
                pending.get(msg.id)?.reject(new Error(msg.error));
                pending.delete(msg.id);
            }
        });

        worker.addEventListener('error', () => {
            worker = null;
            ready  = false;
        });
    }

    /**
     * Attempt local combination. Returns null if worker unavailable.
     * Rejects if model errors out.
     */
    function combine(elementA, elementB) {
        if (!worker) return Promise.resolve(null);

        return new Promise((resolve, reject) => {
            const id = ++reqId;
            pending.set(id, { resolve, reject });
            worker.postMessage({ id, elementA, elementB });

            // 20s local timeout — fall back to server if too slow
            setTimeout(() => {
                if (pending.has(id)) {
                    pending.delete(id);
                    resolve(null); // null = "try server instead"
                }
            }, 20000);
        });
    }

    return { init, combine, isReady: () => ready };
})();

// ── Combinazione ─────────────────────────────────────────────────
async function doCombine(nameA, nameB, x, y, targetId) {
    showToast(`⚗️ Combining ${nameA} + ${nameB}…`, 'info');

    // 1. Try local model first
    let localResult = null;
    try {
        const raw = await localAI.combine(nameA, nameB);
        if (raw) localResult = parseLocalResult(raw);
    } catch {
        // local model failed — fall through to server
    }

    if (localResult) {
        // Send to server to persist (save recipe + compound) but don't wait for LLaMA
        persistCombination(nameA, nameB, localResult);

        removeBoardItem(targetId);
        spawnBoardItem(localResult.name, localResult.emoji, x, y);
        showToast(`${localResult.emoji} ${localResult.name}`, 'success');
        return;
    }

    // 2. Fall back to server (OpenRouter)
    try {
        const res  = await apiFetch(state.combineUrl, 'POST', {
            element_a: nameA,
            element_b: nameB,
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.message ?? 'Combination failed', 'error');
            return;
        }

        const result = data.result;
        removeBoardItem(targetId);
        spawnBoardItem(result.name, result.emoji, x, y);

        if (data.is_new && !state.discovered.has(result.name)) {
            state.discovered.add(result.name);
            addToSidebar(result);
            if (data.first_discovery) {
                showToast(`🏆 First world discovery: ${result.emoji} ${result.name}!`, 'first');
            } else {
                showToast(`✨ New element: ${result.emoji} ${result.name}`, 'success');
            }
        } else {
            showToast(`${result.emoji} ${result.name}`, 'success');
        }

    } catch (e) {
        console.error('doCombine error:', e);
        showToast('Network error. Please try again.', 'error');
    }
}

/**
 * Parse the local model output: "💨 Steam" → { emoji, name }
 * Returns null if the output looks invalid.
 */
function parseLocalResult(text) {
    text = text.trim();
    // Extract first emoji
    const emojiMatch = text.match(/(\p{Emoji_Presentation}|\p{Extended_Pictographic})/u);
    const emoji = emojiMatch?.[0] ?? null;

    // Remove emoji and clean name
    let name = text.replace(/(\p{Emoji_Presentation}|\p{Extended_Pictographic})/gu, '').trim();
    name = name.replace(/^[→\-\s]+/, '').trim();
    name = name.split('\n')[0].trim(); // first line only
    name = name.slice(0, 60);

    if (!name || name.length < 2) return null;

    // Reject if it looks like the model repeated the prompt
    if (name.toLowerCase().includes('combining') || name.toLowerCase().includes('infinite craft')) {
        return null;
    }

    return { emoji: emoji ?? '✨', name };
}

/**
 * Fire-and-forget: tell the server about a locally-generated combination
 * so it gets saved in the DB. We don't block the UI on this.
 */
async function persistCombination(nameA, nameB, result) {
    try {
        await apiFetch(state.combineUrl, 'POST', {
            element_a:      nameA,
            element_b:      nameB,
            local_result:   result.name,
            local_emoji:    result.emoji,
        });
    } catch {
        // best-effort — ignore errors
    }
}

function addToSidebar(el) {
    const list  = document.getElementById('element-list');
    const count = document.getElementById('element-count');
    if (!list) return;

    list.appendChild(makeSidebarItem(el));

    // Aggiorna contatore
    const current = parseInt(count.textContent ?? '0', 10);
    count.textContent = current + 1;
}

// ── Toast ─────────────────────────────────────────────────────────
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => toast.remove(), 3000);
}

// ── Utility ──────────────────────────────────────────────────────
async function apiFetch(url, method = 'GET', body = null) {
    const opts = {
        method,
        headers: {
            'Content-Type':     'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN':     getCsrfToken(),
        },
    };
    if (body) opts.body = JSON.stringify(body);
    return fetch(url, opts);
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function safeParseJson(value) {
    try { return JSON.parse(value ?? 'null'); } catch { return null; }
}

/** Escaping base per prevenire XSS nel DOM */
function sanitize(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Logica frontend principale per Endless Compound / Infinite Craft-like.
 *
 * Linee guida collegate:
 * - Controllo e sanificazione input (HTML5 + JS)
 * - Front end responsive (usa Tailwind)
 * - Uso di AJAX (fetch) o WebSocket per aggiornare la board
 * - Gestione di cookie / preferenze utente in JS
 */

function initGame() {
    const root = document.getElementById('game-root');
    if (!root) return;

    const initialBoard = safeParseJson(root.dataset.initialBoard) ?? [];
    const combineUrl = root.dataset.combineUrl;
    const saveBoardUrl = root.dataset.saveBoardUrl;

    // TODO: qui creare la board, la sidebar, gli input HTML5, ecc.
    console.log('Initial board from session/PHP:', initialBoard);

    // Esempio di handler per una combinazione (da collegare a UI reali)
    async function combine(source, target) {
        // Sanificazione base lato client
        source = sanitizeText(source);
        target = sanitizeText(target);

        const response = await fetch(combineUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ source, target }),
        });

        const data = await response.json();
        console.log('Combine result:', data);

        // TODO: aggiornare DOM board con data.boardState e data.result
    }

    // Espone funzioni per debug iniziale
    window.EndlessCompound = {
        combine,
        saveBoard: async (board) => {
            const response = await fetch(saveBoardUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ board }),
            });
            return await response.json();
        },
    };
}

function safeParseJson(value) {
    try {
        return JSON.parse(value ?? 'null');
    } catch {
        return null;
    }
}

function sanitizeText(value) {
    // Semplice sanificazione lato client; lato server ci sono anche le validate() di Laravel
    return String(value ?? '')
        .trim()
        .slice(0, 255);
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

document.addEventListener('DOMContentLoaded', initGame);



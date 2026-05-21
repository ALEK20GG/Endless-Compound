/**
 * Web Worker per inferenza locale con Transformers.js
 * Gira in un thread separato per non bloccare la UI.
 *
 * Modello: Xenova/LaMini-Flan-T5-783M
 * - Instruction-tuned, ottimo per task di completamento breve
 * - ~300MB, cachato dal browser dopo il primo download
 */

import { pipeline, env } from 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3/dist/transformers.min.js';

// Usa solo la cache del browser, non il filesystem locale
env.allowLocalModels  = false;
env.useBrowserCache   = true;

let generator = null;

/**
 * Inizializza il modello (scarica e cacha al primo avvio).
 * Invia messaggi di progresso al thread principale.
 */
async function loadModel() {
    if (generator) return;

    self.postMessage({ type: 'status', message: 'Loading local model...' });

    generator = await pipeline(
        'text2text-generation',
        'Xenova/LaMini-Flan-T5-783M',
        {
            progress_callback: (progress) => {
                if (progress.status === 'downloading') {
                    const pct = progress.total
                        ? Math.round((progress.loaded / progress.total) * 100)
                        : '?';
                    self.postMessage({ type: 'progress', pct });
                }
            },
        }
    );

    self.postMessage({ type: 'ready' });
}

/**
 * Genera la combinazione localmente.
 */
async function combine(elementA, elementB) {
    await loadModel();

    const prompt = `In the game Infinite Craft, combining "${elementA}" and "${elementB}" creates a new element. Reply with only: EMOJI ElementName`;

    const output = await generator(prompt, {
        max_new_tokens: 20,
        temperature:    0.7,
        do_sample:      true,
    });

    const text = output[0]?.generated_text ?? '';
    return text.trim();
}

// ── Message handler ──────────────────────────────────────────────
self.addEventListener('message', async (e) => {
    const { id, elementA, elementB } = e.data;

    try {
        const result = await combine(elementA, elementB);
        self.postMessage({ type: 'result', id, result });
    } catch (err) {
        self.postMessage({ type: 'error', id, error: err.message });
    }
});

// Pre-carica il modello appena il worker parte
loadModel().catch(err => {
    self.postMessage({ type: 'error', id: null, error: 'Model load failed: ' + err.message });
});

/**
 * Web Worker for local inference with Transformers.js
 * Loaded as a static file (not bundled by Vite) to avoid CDN import issues.
 */

import { pipeline, env } from 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3/dist/transformers.min.js';

env.allowLocalModels = false;
env.useBrowserCache  = true;

let generator = null;

async function loadModel() {
    if (generator) return;
    self.postMessage({ type: 'status', message: 'Loading local model...' });
    generator = await pipeline('text2text-generation', 'Xenova/LaMini-Flan-T5-783M', {
        progress_callback: (p) => {
            if (p.status === 'downloading') {
                const pct = p.total ? Math.round((p.loaded / p.total) * 100) : '?';
                self.postMessage({ type: 'progress', pct });
            }
        },
    });
    self.postMessage({ type: 'ready' });
}

async function combine(elementA, elementB) {
    await loadModel();
    const prompt = `In the game Infinite Craft, combining "${elementA}" and "${elementB}" creates a new element. Reply with only: EMOJI ElementName`;
    const output = await generator(prompt, { max_new_tokens: 20, temperature: 0.7, do_sample: true });
    return (output[0]?.generated_text ?? '').trim();
}

self.addEventListener('message', async (e) => {
    const { id, elementA, elementB } = e.data;
    try {
        const result = await combine(elementA, elementB);
        self.postMessage({ type: 'result', id, result });
    } catch (err) {
        self.postMessage({ type: 'error', id, error: err.message });
    }
});

loadModel().catch(err => {
    self.postMessage({ type: 'error', id: null, error: 'Model load failed: ' + err.message });
});

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chiama OpenRouter (LLaMA 3.3 70B) per generare una nuova combinazione
 * in stile Infinite Craft: A + B = ?
 *
 * Ritorna un array ['name' => string, 'emoji' => string]
 * oppure null in caso di errore.
 */
class LlamaCombinationService
{
    private string $endpoint;
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->endpoint = config('services.llama.endpoint', 'https://openrouter.ai/api/v1/chat/completions');
        $this->apiKey   = config('services.llama.key', '');
        $this->model    = config('services.llama.model', 'meta-llama/llama-3.3-70b-instruct:free');
    }

    /**
     * Genera il risultato della combinazione di due elementi.
     *
     * @param  string $elementA  Nome del primo elemento (es. "Acqua")
     * @param  string $elementB  Nome del secondo elemento (es. "Fuoco")
     * @return array{name: string, emoji: string}|null
     */
    public function combine(string $elementA, string $elementB): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('LlamaCombinationService: LLAMA_API_KEY non configurata.');
            return null;
        }

        $prompt = $this->buildPrompt($elementA, $elementB);

        try {
            $response = Http::withToken($this->apiKey)
                ->withHeaders([
                    'HTTP-Referer' => config('app.url'),
                    'X-Title'      => config('app.name'),
                ])
                ->timeout(30)
                ->post($this->endpoint, [
                    'model'       => $this->model,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => $this->systemPrompt(),
                        ],
                        [
                            'role'    => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens'  => 50,
                ]);

            if ($response->failed()) {
                Log::error('LlamaCombinationService: risposta fallita', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $content = $response->json('choices.0.message.content', '');
            return $this->parseResponse($content);

        } catch (\Throwable $e) {
            Log::error('LlamaCombinationService: eccezione', ['message' => $e->getMessage()]);
            return null;
        }
    }

    // ── Prompt ───────────────────────────────────────────────────

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Sei il motore di un gioco di combinazione di elementi in stile "Infinite Craft".
Il tuo compito è combinare due elementi e produrre un nuovo elemento risultante.

Regole:
- Rispondi SOLO con una riga nel formato esatto: EMOJI Nome
- L'emoji deve essere una singola emoji pertinente al risultato
- Il nome deve essere in italiano, breve (1-3 parole), creativo ma sensato
- Non aggiungere spiegazioni, punteggiatura extra o testo aggiuntivo
- Se la combinazione non ha senso, inventa comunque qualcosa di creativo

Esempi:
Acqua + Fuoco → 💨 Vapore
Terra + Acqua → 🌱 Fango
Fuoco + Aria → 🌪️ Fiamma Viva
PROMPT;
    }

    private function buildPrompt(string $a, string $b): string
    {
        return "{$a} + {$b} →";
    }

    // ── Parser risposta ──────────────────────────────────────────

    /**
     * Estrae emoji e nome dalla risposta del modello.
     * Formato atteso: "💧 Acqua Pura" oppure "💧Acqua Pura"
     */
    private function parseResponse(string $content): ?array
    {
        $content = trim($content);

        if (empty($content)) {
            return null;
        }

        // Estrai la prima emoji Unicode dalla stringa
        preg_match('/(\p{Emoji_Presentation}|\p{Extended_Pictographic})/u', $content, $emojiMatch);
        $emoji = $emojiMatch[0] ?? '✨';

        // Rimuovi l'emoji e pulisci il nome
        $name = trim(preg_replace('/(\p{Emoji_Presentation}|\p{Extended_Pictographic})/u', '', $content));
        $name = trim($name, " \t\n\r\0\x0B→-");

        // Capitalizza e limita la lunghezza
        $name = mb_convert_case(mb_substr($name, 0, 60), MB_CASE_TITLE, 'UTF-8');

        if (empty($name)) {
            return null;
        }

        return [
            'name'  => $name,
            'emoji' => $emoji,
        ];
    }
}

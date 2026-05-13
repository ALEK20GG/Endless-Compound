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
Combine the provided compounds and produce a new element.
The rules are:
- the format must be "emoji string" for example "💨 Steam"
- there must be only one emoji in the answer
- the name must be in english and as concise as possible
- the name must be as explicit as possible so the user understands what is the element about
- dont add explainations or extra texts and punctuation just respond with the information i described in format
- if the combound doesn't makes sense or it is meaningless, be creative and at least try to give an answer
- the response must exist in the format given

Examples:
Water + Fire → 💨 Steam
Earth + Water → 🌱 Mud
Fire + Earth → 🌋 Lava
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

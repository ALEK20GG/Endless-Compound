<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Calls OpenRouter (LLaMA 3.3 70B) to generate a new combination
 * in Infinite Craft style: A + B = ?
 *
 * Returns an array ['name' => string, 'emoji' => string]
 * or null on error.
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
     * Generates the result of combining two elements.
     *
     * @param  string $elementA  First element name (e.g. "Water")
     * @param  string $elementB  Second element name (e.g. "Fire")
     * @return array{name: string, emoji: string}|null
     */
    public function combine(string $elementA, string $elementB): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('LlamaCombinationService: LLAMA_API_KEY non configurata.');
            return null;
        }

        // Extend PHP time limit for this request (LLaMA can take 15-20s)
        set_time_limit(120);

        $prompt = $this->buildPrompt($elementA, $elementB);

        try {
            $response = $this->callWithRetry($prompt);

            if ($response === null) {
                return null;
            }

            if ($response->failed()) {
                Log::error('LlamaCombinationService: request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $content = $response->json('choices.0.message.content', '');
            Log::debug('LlamaCombinationService: raw response', ['content' => $content]);
            return $this->parseResponse($content);

        } catch (\Throwable $e) {
            Log::error('LlamaCombinationService: exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    // ── HTTP con retry su 429 ────────────────────────────────────

    /**
     * Fallback models tried in order when the primary is rate-limited.
     * All are free on OpenRouter.
     */
    private array $fallbackModels = [
        'nvidia/nemotron-nano-12b-v2-vl:free',
        'google/gemma-4-26b-a4b-it:free',
        'google/gemma-4-31b-it:free',
        'liquid/lfm-2.5-1.2b-instruct:free',
        'meta-llama/llama-3.2-3b-instruct:free',
        'meta-llama/llama-3.3-70b-instruct:free',
    ];

    private function callWithRetry(string $prompt, int $maxAttempts = 3): ?\Illuminate\Http\Client\Response
    {
        // Build model list: configured model first, then fallbacks (deduped)
        $models = array_unique(array_merge([$this->model], $this->fallbackModels));

        // Try each model; if all are rate-limited, wait and retry the primary
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            foreach ($models as $model) {
                $response = Http::withToken($this->apiKey)
                    ->withHeaders([
                        'HTTP-Referer' => config('app.url'),
                        'X-Title'      => config('app.name'),
                    ])
                    ->timeout(90)
                    ->post($this->endpoint, [
                        'model'       => $model,
                        'messages'    => [
                            ['role' => 'system', 'content' => $this->systemPrompt()],
                            ['role' => 'user',   'content' => $prompt],
                        ],
                        'temperature' => 0.7,
                        'max_tokens'  => 50,
                    ]);

                if ($response->status() === 200) {
                    return $response;
                }

                if ($response->status() === 429) {
                    // Rate limited — try next model immediately
                    Log::warning('LlamaCombinationService: rate limited, trying next model', [
                        'model'   => $model,
                        'attempt' => $attempt,
                    ]);
                    continue;
                }

                // 404 or other error — skip this model entirely
                Log::warning('LlamaCombinationService: model unavailable, skipping', [
                    'model'  => $model,
                    'status' => $response->status(),
                ]);
            }

            // All models rate-limited or unavailable — wait before next round
            if ($attempt < $maxAttempts - 1) {
                $wait = 15;
                Log::warning("LlamaCombinationService: all models busy, waiting {$wait}s before retry", [
                    'attempt' => $attempt + 1,
                ]);
                sleep($wait);
            }
        }

        Log::error('LlamaCombinationService: all models failed after all attempts');
        return null;
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
     * Extracts emoji and name from the model response.
     * Expected format: "💧 Pure Water" or "💧Pure Water"
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

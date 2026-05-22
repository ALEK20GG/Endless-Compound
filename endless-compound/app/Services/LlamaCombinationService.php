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
            // Ensure UTF-8 — some providers return latin1-encoded emoji
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
            Log::debug('LlamaCombinationService: raw response', ['content' => $content]);
            $parsed = $this->parseResponse($content);
            Log::debug('LlamaCombinationService: parsed', ['result' => $parsed]);
            return $parsed;

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
    /**
     * Fallback models in priority order:
     * - Low-traffic / less congested models first
     * - Higher-traffic popular models as last resort
     */
    private array $fallbackModels = [
        // Low traffic, consistently available
        'nvidia/nemotron-nano-12b-v2-vl:free',
        'nvidia/nemotron-3-nano-30b-a3b:free',
        'nvidia/nemotron-3-super-120b-a12b:free',
        'liquid/lfm-2.5-1.2b-instruct:free',
        'liquid/lfm-2.5-1.2b-thinking:free',
        'poolside/laguna-xs.2:free',
        'poolside/laguna-m.1:free',
        'google/gemma-4-26b-a4b-it:free',
        'google/gemma-4-31b-it:free',
        'z-ai/glm-4.5-air:free',
        'openai/gpt-oss-20b:free',
        'openai/gpt-oss-120b:free',
        // Higher traffic, use as last resort
        'meta-llama/llama-3.2-3b-instruct:free',
        'meta-llama/llama-3.3-70b-instruct:free',
    ];

    private function callWithRetry(string $prompt, int $maxAttempts = 2): ?\Illuminate\Http\Client\Response
    {
        $models = array_unique(array_merge([$this->model], $this->fallbackModels));

        // Try each model once — no sleep, no retry loops
        // 429 = rate limited, skip to next; 200 = success; else = skip
        foreach ($models as $model) {
            try {
                $response = Http::withToken($this->apiKey)
                    ->withHeaders([
                        'HTTP-Referer' => config('app.url'),
                        'X-Title'      => config('app.name'),
                    ])
                    ->timeout(25)
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

                Log::warning('LlamaCombinationService: model skipped', [
                    'model'  => $model,
                    'status' => $response->status(),
                ]);

            } catch (\Throwable $e) {
                Log::warning('LlamaCombinationService: model exception', [
                    'model'   => $model,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        Log::error('LlamaCombinationService: all models failed');
        return null;
    }

    // ── Prompt ───────────────────────────────────────────────────

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Combine the provided words and produce a new element.
The rules are:
- the format must be "emoji string" for example "💨 Steam"
- there must be only one emoji in the answer
- the name must be in english and as concise as possible
- the name must be as explicit as possible so the user understands what is the element about
- dont add explainations or extra texts and punctuation just respond with the information i described in format
- if the combination doesn't makes sense or it is meaningless, be creative and at least try to give an answer
- it's better if the combination differs from the original words
- the related emoji should be coherent with the word
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
     * Also handles mojibake emoji (ðŸŒ‹) by stripping them and using a fallback.
     */
    private function parseResponse(string $content): ?array
    {
        // Force UTF-8 interpretation
        $content = mb_convert_encoding(trim($content), 'UTF-8', 'UTF-8');

        // Strip markdown bold markers
        $content = preg_replace('/\*+/', '', $content);
        $content = trim($content);

        if (empty($content)) {
            return null;
        }

        // Extract first valid emoji
        preg_match('/(\p{Emoji_Presentation}|\p{Extended_Pictographic})/u', $content, $emojiMatch);
        $emoji = $emojiMatch[0] ?? null;

        // Remove emoji (and any mojibake sequences like ðŸ...) and clean the name
        $name = preg_replace('/(\p{Emoji_Presentation}|\p{Extended_Pictographic})/u', '', $content);
        // Remove common mojibake patterns (multi-byte sequences misread as latin1)
        $name = preg_replace('/[\xc2-\xf4][\x80-\xbf]+/', '', $name);
        $name = trim($name, " \t\n\r\0\x0B→-*");
        $name = trim($name);

        // Take only the first line/word group
        $name = explode("\n", $name)[0];
        $name = trim($name);
        $name = mb_substr($name, 0, 60);

        if (empty($name) || mb_strlen($name) < 2) {
            return null;
        }

        // Capitalize
        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

        return [
            'name'  => $name,
            'emoji' => $emoji ?? '✨',
        ];
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Service per integrare l'API LLaMA (o modelli simili)
 * per generare nuove combinazioni di oggetti in stile Infinite Craft.
 *
 * TODO:
 * - Spostare qui tutta la logica di chiamata alle API esterne
 * - Leggere chiavi / endpoint da .env e config/services.php
 * - Gestire eccezioni, timeout e logging
 */
class LlamaCombinationService
{
    public function combine(string $source, string $target): ?string
    {
        // TODO: leggere configurazione da config('services.llama')
        $endpoint = config('services.llama.endpoint', '');
        $apiKey = config('services.llama.key', '');

        if (empty($endpoint) || empty($apiKey)) {
            // Per ora ritorniamo null se non è configurato
            return null;
        }

        // Esempio di chiamata HTTP con Laravel HTTP client (usa sotto le prepared request di Guzzle)
        $response = Http::withToken($apiKey)->post($endpoint, [
            'source' => $source,
            'target' => $target,
        ]);

        if ($response->failed()) {
            // TODO: logging / gestione errori
            return null;
        }

        // TODO: adattare alla struttura reale della risposta dell'API
        return $response->json('result');
    }
}



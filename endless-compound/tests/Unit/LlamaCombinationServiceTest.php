<?php

use App\Services\LlamaCombinationService;

// ── Test del parser della risposta LLM ───────────────────────────
// Questi test verificano che il parser gestisca correttamente
// i vari formati di risposta del modello AI.

test('il parser estrae emoji e nome dal formato standard', function () {
    $service = new LlamaCombinationService();
    $result  = invokeParser($service, '💧 Pure Water');

    expect($result)->not->toBeNull()
        ->and($result['name'])->toBe('Pure Water')
        ->and($result['emoji'])->toBe('💧');
});

test('il parser gestisce emoji senza spazio', function () {
    $service = new LlamaCombinationService();
    $result  = invokeParser($service, '🔥Steam');

    expect($result)->not->toBeNull()
        ->and($result['name'])->toBe('Steam');
});

test('il parser usa emoji di fallback se mancante', function () {
    $service = new LlamaCombinationService();
    $result  = invokeParser($service, 'Lava Rock');

    expect($result)->not->toBeNull()
        ->and($result['emoji'])->toBe('✨')
        ->and($result['name'])->toBe('Lava Rock');
});

test('il parser capitalizza il nome', function () {
    $service = new LlamaCombinationService();
    $result  = invokeParser($service, '🌊 ocean wave');

    expect($result)->not->toBeNull()
        ->and($result['name'])->toBe('Ocean Wave');
});

test('il parser rimuove i marker markdown bold', function () {
    $service = new LlamaCombinationService();
    $result  = invokeParser($service, '**🌋 Volcano**');

    expect($result)->not->toBeNull()
        ->and($result['name'])->toBe('Volcano');
});

test('il parser prende solo la prima riga', function () {
    $service = new LlamaCombinationService();
    $result  = invokeParser($service, "🌊 Ocean\nThis is a description");

    expect($result)->not->toBeNull()
        ->and($result['name'])->toBe('Ocean');
});

test('il parser restituisce null per input vuoto', function () {
    $service = new LlamaCombinationService();
    $result  = invokeParser($service, '');

    expect($result)->toBeNull();
});

test('il parser restituisce null per nomi troppo corti', function () {
    $service = new LlamaCombinationService();
    $result  = invokeParser($service, '🔥 A');

    expect($result)->toBeNull();
});

test('il parser tronca nomi troppo lunghi', function () {
    $service = new LlamaCombinationService();
    $longName = str_repeat('A', 100);
    $result   = invokeParser($service, "✨ {$longName}");

    expect($result)->not->toBeNull()
        ->and(mb_strlen($result['name']))->toBeLessThanOrEqual(60);
});

// ── Helper per accedere al metodo privato parseResponse ──────────

function invokeParser(LlamaCombinationService $service, string $content): ?array
{
    $reflection = new ReflectionClass($service);
    $method     = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);
    return $method->invoke($service, $content);
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\LlamaCombinationService;

class GameController extends Controller
{
    public function __construct(
        private readonly LlamaCombinationService $llama
    ) {}

    // ── Pagina principale ────────────────────────────────────────

    public function index(Request $request)
    {
        $rooms = DB::table('rooms')->orderBy('createdat', 'desc')->get();

        // Base elements: compounds with no discoverer (seeded)
        $baseElements = DB::table('compounds')
            ->whereNull('first_discoverer_uid')
            ->orderBy('cid')
            ->get(['cid', 'name', 'emoji']);

        return view('game', compact('rooms', 'baseElements'));
    }

    // ── Combine (AJAX) ───────────────────────────────────────────

    /**
     * POST /game/combine
     *
     * Body JSON: { "element_a": "Acqua", "element_b": "Fuoco" }
     *
     * Flusso:
     * 1. Valida e sanifica input
     * 2. Risolve i compound dal DB per nome
     * 3. Cerca la ricetta in DB (cache locale)
     * 4. Se non esiste → chiama LLaMA → salva compound + ricetta in transazione
     * 5. Ritorna il risultato
     */
    public function combine(Request $request): JsonResponse
    {
        // 1. Validazione input
        $data = $request->validate([
            'element_a' => ['required', 'string', 'max:100'],
            'element_b' => ['required', 'string', 'max:100'],
        ]);

        $nameA = trim(strip_tags($data['element_a']));
        $nameB = trim(strip_tags($data['element_b']));

        // 2. Risolvi compound dal DB (DB::table diretto, come dbcheck)
        $compoundA = DB::table('compounds')
            ->whereRaw('LOWER(name) = ?', [strtolower($nameA)])
            ->first();

        $compoundB = DB::table('compounds')
            ->whereRaw('LOWER(name) = ?', [strtolower($nameB)])
            ->first();

        if (! $compoundA || ! $compoundB) {
            return response()->json([
                'success' => false,
                'message' => 'Element not found: ' . (! $compoundA ? $nameA : $nameB),
            ], 422);
        }

        // 3. Cerca ricetta esistente
        [$cidA, $cidB] = $compoundA->cid <= $compoundB->cid
            ? [$compoundA->cid, $compoundB->cid]
            : [$compoundB->cid, $compoundA->cid];

        $recipe = DB::table('recipes')
            ->where('cid_a', $cidA)
            ->where('cid_b', $cidB)
            ->first();

        if ($recipe) {
            $result = DB::table('compounds')->where('cid', $recipe->cid_result)->first();
            return response()->json([
                'success'         => true,
                'result'          => $this->formatRow($result),
                'is_new'          => false,
                'first_discovery' => false,
            ]);
        }

        // 4. Genera con LLaMA
        $generated = $this->llama->combine($compoundA->name, $compoundB->name);

        if (! $generated) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate combination right now. Please try again.',
            ], 503);
        }

        // 5. Salva in transazione
        try {
            $result = DB::transaction(function () use ($cidA, $cidB, $generated) {
                // Controlla se il compound esiste già
                $existing = DB::table('compounds')
                    ->whereRaw('LOWER(name) = ?', [strtolower($generated['name'])])
                    ->first();

                $isFirstDiscovery = false;

                if ($existing) {
                    $resultCompound = $existing;
                } else {
                    $newCid = DB::table('compounds')->insertGetId([
                        'name'                 => $generated['name'],
                        'emoji'                => $generated['emoji'],
                        'discoveredat'         => now(),
                        'first_discoverer_uid' => auth()->id() ?? null,
                    ], 'cid');

                    $resultCompound = DB::table('compounds')->where('cid', $newCid)->first();
                    $isFirstDiscovery = true;
                }

                // Salva la ricetta se non esiste già (race condition guard)
                $recipeExists = DB::table('recipes')
                    ->where('cid_a', $cidA)
                    ->where('cid_b', $cidB)
                    ->exists();

                if (! $recipeExists) {
                    DB::table('recipes')->insert([
                        'cid_a'      => $cidA,
                        'cid_b'      => $cidB,
                        'cid_result' => $resultCompound->cid,
                        'createdat'  => now(),
                    ]);
                }

                return [
                    'compound'        => $resultCompound,
                    'is_first_discovery' => $isFirstDiscovery,
                ];
            });

            return response()->json([
                'success'         => true,
                'result'          => $this->formatRow($result['compound']),
                'is_new'          => true,
                'first_discovery' => $result['is_first_discovery'],
            ]);

        } catch (\Throwable $e) {
            Log::error('GameController@combine: save error', [
                'message' => $e->getMessage(),
                'a'       => $compoundA->name,
                'b'       => $compoundB->name,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal error while saving.',
            ], 500);
        }
    }

    // ── Elementi scoperti (AJAX sidebar) ─────────────────────────

    /**
     * GET /game/elements
     * Ritorna tutti i compound scoperti (per la sidebar).
     */
    public function elements(): JsonResponse
    {
        $compounds = DB::table('compounds')->orderBy('name')->get(['cid', 'name', 'emoji']);

        return response()->json([
            'success'  => true,
            'elements' => $compounds,
        ]);
    }

    // ── Helper ───────────────────────────────────────────────────

    /** Formatta una riga stdClass (da DB::table) come array per il frontend */
    private function formatRow(object $row): array
    {
        return [
            'cid'   => $row->cid,
            'name'  => $row->name,
            'emoji' => $row->emoji ?? '✨',
        ];
    }
}

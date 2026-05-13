<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Compound;
use App\Models\Recipe;
use App\Models\Room;
use App\Services\LlamaCombinationService;

class GameController extends Controller
{
    public function __construct(
        private readonly LlamaCombinationService $llama
    ) {}

    // ── Pagina principale ────────────────────────────────────────

    public function index(Request $request)
    {
        $rooms = Room::orderBy('createdat', 'desc')->get();

        // Elementi base sempre disponibili
        $baseElements = Compound::whereNull('first_discoverer_uid')
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

        // 2. Risolvi compound dal DB
        $compoundA = Compound::findByName($nameA);
        $compoundB = Compound::findByName($nameB);

        if (! $compoundA || ! $compoundB) {
            return response()->json([
                'success' => false,
                'message' => 'Elemento non trovato: ' . (! $compoundA ? $nameA : $nameB),
            ], 422);
        }

        // 3. Cerca ricetta esistente
        $recipe = Recipe::findByIngredients($compoundA->cid, $compoundB->cid);

        if ($recipe) {
            return response()->json([
                'success'      => true,
                'result'       => $this->formatCompound($recipe->result),
                'is_new'       => false,
                'first_discovery' => false,
            ]);
        }

        // 4. Genera con LLaMA
        $generated = $this->llama->combine($compoundA->name, $compoundB->name);

        if (! $generated) {
            return response()->json([
                'success' => false,
                'message' => 'Impossibile generare la combinazione al momento. Riprova.',
            ], 503);
        }

        // 5. Salva in transazione
        try {
            $result = DB::transaction(function () use ($compoundA, $compoundB, $generated, $request) {
                // Controlla se il compound esiste già (potrebbe essere stato creato
                // da un'altra richiesta concorrente nel frattempo)
                $existing = Compound::findByName($generated['name']);

                $isFirstDiscovery = false;

                if ($existing) {
                    $resultCompound = $existing;
                } else {
                    // Nuovo compound — registra chi l'ha scoperto per primo
                    $resultCompound = Compound::create([
                        'name'                 => $generated['name'],
                        'emoji'                => $generated['emoji'],
                        'discoveredat'         => now(),
                        'first_discoverer_uid' => auth()->id() ?? null,
                    ]);
                    $isFirstDiscovery = true;
                }

                // Salva la ricetta (con lock per evitare duplicati concorrenti)
                $recipeExists = DB::table('recipes')
                    ->where('cid_a', min($compoundA->cid, $compoundB->cid))
                    ->where('cid_b', max($compoundA->cid, $compoundB->cid))
                    ->lockForUpdate()
                    ->exists();

                if (! $recipeExists) {
                    Recipe::createNormalized(
                        $compoundA->cid,
                        $compoundB->cid,
                        $resultCompound->cid
                    );
                }

                return [
                    'compound'         => $resultCompound,
                    'is_first_discovery' => $isFirstDiscovery,
                ];
            });

            return response()->json([
                'success'          => true,
                'result'           => $this->formatCompound($result['compound']),
                'is_new'           => true,
                'first_discovery'  => $result['is_first_discovery'],
            ]);

        } catch (\Throwable $e) {
            Log::error('GameController@combine: errore salvataggio', [
                'message' => $e->getMessage(),
                'a'       => $compoundA->name,
                'b'       => $compoundB->name,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore interno durante il salvataggio.',
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
        $compounds = Compound::orderBy('name')->get(['cid', 'name', 'emoji']);

        return response()->json([
            'success'   => true,
            'elements'  => $compounds,
        ]);
    }

    // ── Helper ───────────────────────────────────────────────────

    private function formatCompound(Compound $c): array
    {
        return [
            'cid'   => $c->cid,
            'name'  => $c->name,
            'emoji' => $c->emoji ?? '✨',
        ];
    }
}

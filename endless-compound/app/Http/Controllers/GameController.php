<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Services\LlamaCombinationService;

class GameController extends Controller
{
    // Base element names (case-insensitive) — includes both 'wind' and 'air' for compatibility
    private const BASE_ELEMENTS = ['water', 'fire', 'earth', 'air'];

    public function __construct(
        private readonly LlamaCombinationService $llama
    ) {}

    // ── Singleplayer ─────────────────────────────────────────────

    public function startSolo(): \Illuminate\Http\RedirectResponse
    {
        $uid = auth()->id();

        $room = DB::table('rooms')
            ->where('owner_uid', $uid)
            ->where('maxplayers', 1)
            ->orderBy('createdat', 'desc')
            ->first();

        if (! $room) {
            $roid = DB::table('rooms')->insertGetId([
                'name'       => auth()->user()->username . "'s room",
                'isprivate'  => true,
                'maxplayers' => 1,
                'owner_uid'  => $uid,
                'createdat'  => now(),
            ], 'roid');
            $this->seedRoomWithBaseElements($roid);
        } else {
            $roid = $room->roid;
            $this->ensureBaseElements($roid);
        }

        return redirect()->route('game.room', ['roid' => $roid]);
    }

    // ── Multiplayer: crea room ────────────────────────────────────

    /**
     * POST /game/multiplayer/create
     * Body: { name, maxplayers }
     */
    public function createMultiplayer(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:60'],
            'maxplayers' => ['required', 'integer', 'min:2', 'max:10'],
        ]);

        $uid  = auth()->id();
        $code = strtoupper(substr(md5(uniqid($uid, true)), 0, 6));

        $roid = DB::table('rooms')->insertGetId([
            'name'       => trim(strip_tags($data['name'])),
            'isprivate'  => false,
            'maxplayers' => $data['maxplayers'],
            'owner_uid'  => $uid,
            'createdat'  => now(),
            'code'       => $code,
        ], 'roid');

        // Owner entra automaticamente in collabs_in
        DB::table('collabs_in')->insert([
            'uid'      => $uid,
            'roid'     => $roid,
            'joinedat' => now(),
        ]);

        $this->seedRoomWithBaseElements($roid);

        return redirect()->route('game.room', ['roid' => $roid]);
    }

    // ── Multiplayer: entra in room ────────────────────────────────

    /**
     * POST /game/multiplayer/join
     * Body: { code }
     */
    public function joinMultiplayer(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $uid  = auth()->id();
        $code = strtoupper(trim($data['code']));

        $room = DB::table('rooms')->where('code', $code)->first();

        if (! $room) {
            return back()->withErrors(['code' => 'Room not found.']);
        }

        try {
            DB::transaction(function () use ($uid, $room) {
                // ── FOR UPDATE sulla room ────────────────────────────────
                // Blocca la riga della room per tutta la transazione.
                // Impedisce che due player entrino simultaneamente in una
                // room quasi piena, superando il limite di maxplayers.
                $lockedRoom = DB::table('rooms')
                    ->where('roid', $room->roid)
                    ->lockForUpdate()
                    ->first();

                // Conta i player attuali con lock condiviso (FOR SHARE):
                // leggiamo il conteggio in modo sicuro senza bloccare altre letture
                $currentPlayers = DB::table('collabs_in')
                    ->where('roid', $room->roid)
                    ->sharedLock()
                    ->count();

                if ($currentPlayers >= $lockedRoom->maxplayers) {
                    throw new \Exception('Room is full.');
                }

                // Verifica se già presente (con lock esclusivo)
                $alreadyIn = DB::table('collabs_in')
                    ->where('uid', $uid)
                    ->where('roid', $room->roid)
                    ->lockForUpdate()
                    ->exists();

                if (! $alreadyIn) {
                    DB::table('collabs_in')->insert([
                        'uid'      => $uid,
                        'roid'     => $room->roid,
                        'joinedat' => now(),
                    ]);
                }
            });
        } catch (\Exception $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $this->ensureBaseElements($room->roid);

        return redirect()->route('game.room', ['roid' => $room->roid]);
    }

    // ── Pagina di gioco ──────────────────────────────────────────

    public function index(Request $request, int $roid)
    {
        $uid = auth()->id();

        $room = DB::table('rooms')->where('roid', $roid)->first();

        if (! $room) {
            abort(404);
        }

        // Accesso: owner oppure membro di collabs_in
        $hasAccess = ($room->owner_uid == $uid)
            || DB::table('collabs_in')->where('uid', $uid)->where('roid', $roid)->exists();

        if (! $hasAccess) {
            abort(403, 'You do not have access to this room.');
        }

        $roomElements = DB::table('room_comps')
            ->join('compounds', 'room_comps.cid', '=', 'compounds.cid')
            ->where('room_comps.roid', $roid)
            ->orderBy('compounds.name')
            ->get(['compounds.cid', 'compounds.name', 'compounds.emoji']);

        // Player nella room (per multiplayer)
        $players = DB::table('collabs_in')
            ->join('users', 'collabs_in.uid', '=', 'users.uid')
            ->where('collabs_in.roid', $roid)
            ->get(['users.uid', 'users.username']);

        $isMultiplayer = $room->maxplayers > 1;

        // CID degli elementi scoperti per la prima volta da questo player
        $myDiscoveries = DB::table('compounds')
            ->where('first_discoverer_uid', $uid)
            ->pluck('cid')
            ->toArray();

        return view('game', compact('room', 'roomElements', 'players', 'isMultiplayer', 'myDiscoveries'));
    }

    // ── Combine (AJAX) ───────────────────────────────────────────

    public function combine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'roid'         => ['required', 'integer'],
            'element_a'    => ['required', 'string', 'max:100'],
            'element_b'    => ['required', 'string', 'max:100'],
            'local_result' => ['sometimes', 'string', 'max:100'],
            'local_emoji'  => ['sometimes', 'string', 'max:10'],
        ]);

        $uid  = auth()->id();
        $roid = (int) $data['roid'];

        // Verifica accesso alla room
        $room = DB::table('rooms')->where('roid', $roid)->first();
        if (! $room) {
            return response()->json(['success' => false, 'message' => 'Room not found.'], 403);
        }

        $hasAccess = ($room->owner_uid == $uid)
            || DB::table('collabs_in')->where('uid', $uid)->where('roid', $roid)->exists();

        if (! $hasAccess) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $nameA = trim(strip_tags($data['element_a']));
        $nameB = trim(strip_tags($data['element_b']));

        $compoundA = DB::table('compounds')->whereRaw('LOWER(name) = ?', [strtolower($nameA)])->first();
        $compoundB = DB::table('compounds')->whereRaw('LOWER(name) = ?', [strtolower($nameB)])->first();

        if (! $compoundA || ! $compoundB) {
            return response()->json([
                'success' => false,
                'message' => 'Element not found: ' . (! $compoundA ? $nameA : $nameB),
            ], 422);
        }

        [$cidA, $cidB] = $compoundA->cid <= $compoundB->cid
            ? [$compoundA->cid, $compoundB->cid]
            : [$compoundB->cid, $compoundA->cid];

        // Ricetta globale esistente? → risposta immediata
        $recipe = DB::table('recipes')
            ->where('cid_a', $cidA)->where('cid_b', $cidB)->first();

        if ($recipe) {
            $resultCompound = DB::table('compounds')->where('cid', $recipe->cid_result)->first();
            $isNewInRoom    = $this->addToRoom($roid, $resultCompound->cid);

            return response()->json([
                'success'         => true,
                'result'          => $this->formatRow($resultCompound),
                'is_new'          => false,
                'new_in_room'     => $isNewInRoom,
                'first_discovery' => false,
            ]);
        }

        // Risultato locale dal worker JS? → salva e risposta immediata
        $localName  = trim(strip_tags($request->input('local_result', '')));
        $localEmoji = trim($request->input('local_emoji', ''));

        if ($localName !== '') {
            $generated = ['name' => $localName, 'emoji' => $localEmoji ?: '✨'];
            return $this->saveAndRespond($cidA, $cidB, $generated, $roid, $uid);
        }

        // Nessuna ricetta e nessun risultato locale → avvia job asincrono
        $jobId = Str::uuid()->toString();

        // Salva i parametri del job in cache (5 minuti)
        Cache::put("combine_job:{$jobId}", [
            'status'    => 'pending',
            'cidA'      => $cidA,
            'cidB'      => $cidB,
            'nameA'     => $compoundA->name,
            'nameB'     => $compoundB->name,
            'roid'      => $roid,
            'uid'       => $uid,
        ], 300);

        // Avvia il job in background tramite un processo separato
        // (su Render non c'è queue worker, usiamo un approccio diverso:
        //  il primo poll eseguirà il lavoro reale)
        return response()->json([
            'success' => true,
            'pending' => true,
            'job_id'  => $jobId,
        ]);
    }

    /**
     * GET /game/combine/poll/{jobId}
     * Esegue il lavoro LLM se ancora pending, poi ritorna il risultato.
     */
    public function combinePoll(string $jobId): JsonResponse
    {
        $cacheKey = "combine_job:{$jobId}";
        $job = Cache::get($cacheKey);

        if (! $job) {
            return response()->json(['success' => false, 'message' => 'Job expired or not found.'], 404);
        }

        if ($job['status'] === 'done') {
            Cache::forget($cacheKey);
            return response()->json($job['result']);
        }

        if ($job['status'] === 'error') {
            Cache::forget($cacheKey);
            return response()->json(['success' => false, 'message' => $job['message']], 503);
        }

        // Status 'pending' — mark as processing to prevent duplicate runs
        Cache::put($cacheKey, array_merge($job, ['status' => 'processing']), 300);

        // Close HTTP connection immediately, then run LLM in background
        // This prevents Apache/Render from timing out the request
        $response = response()->json(['pending' => true, 'job_id' => $jobId]);
        $response->send();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // Now run the LLM call (connection is already closed)
        set_time_limit(180);
        ignore_user_abort(true);

        try {
            $generated = $this->llama->combine($job['nameA'], $job['nameB']);

            if (! $generated) {
                Cache::put($cacheKey, ['status' => 'error', 'message' => 'Unable to generate combination right now.'], 300);
                return $response;
            }

            $result = $this->saveAndRespond($job['cidA'], $job['cidB'], $generated, $job['roid'], $job['uid'], returnArray: true);
            Cache::put($cacheKey, array_merge(['status' => 'done'], ['result' => $result]), 300);

        } catch (\Throwable $e) {
            Log::error('combinePoll: LLM error', ['message' => $e->getMessage()]);
            Cache::put($cacheKey, ['status' => 'error', 'message' => 'Internal error.'], 300);
        }

        return $response;
    }

    // ── Invite via email ─────────────────────────────────────────

    /**
     * POST /game/multiplayer/invite
     * Body: { roid, email }
     */
    public function sendInvite(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'roid'  => ['required', 'integer'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $uid  = auth()->id();
        $roid = (int) $data['roid'];

        $room = DB::table('rooms')->where('roid', $roid)->where('owner_uid', $uid)->first();
        if (! $room || ! $room->code) {
            return response()->json(['success' => false, 'message' => 'Room not found or not yours.'], 403);
        }

        $inviter = auth()->user();
        $joinUrl = route('dashboard') . '?join=' . $room->code;

        try {
            \Illuminate\Support\Facades\Mail::to($data['email'])
                ->send(new \App\Mail\RoomInviteMail(
                    inviterName: $inviter->username,
                    roomName:    $room->name,
                    roomCode:    $room->code,
                    joinUrl:     $joinUrl,
                ));

            return response()->json(['success' => true, 'message' => 'Invite sent!']);
        } catch (\Throwable $e) {
            Log::error('sendInvite: mail failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to send email.'], 500);
        }
    }

    // ── Elementi della room (AJAX + polling) ─────────────────────

    /**
     * GET /game/elements?roid={roid}&since={timestamp}
     * Ritorna gli elementi aggiunti dopo `since` (per il polling multiplayer).
     */
    public function elements(Request $request): JsonResponse
    {
        $roid  = (int) $request->query('roid', 0);
        $since = $request->query('since'); // ISO timestamp opzionale
        $uid   = auth()->id();

        if (! $roid) {
            return response()->json(['success' => false, 'message' => 'Missing roid.'], 422);
        }

        $room = DB::table('rooms')->where('roid', $roid)->first();
        if (! $room) {
            return response()->json(['success' => false, 'message' => 'Room not found.'], 404);
        }

        $hasAccess = ($room->owner_uid == $uid)
            || DB::table('collabs_in')->where('uid', $uid)->where('roid', $roid)->exists();

        if (! $hasAccess) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $query = DB::table('room_comps')
            ->join('compounds', 'room_comps.cid', '=', 'compounds.cid')
            ->where('room_comps.roid', $roid);

        // Se `since` è fornito, ritorna solo i nuovi elementi (per il polling)
        if ($since) {
            $query->where('room_comps.addedat', '>', $since);
        }

        $elements = $query->orderBy('compounds.name')
            ->get(['compounds.cid', 'compounds.name', 'compounds.emoji', 'room_comps.addedat']);

        // Timestamp dell'ultimo elemento per il prossimo poll
        $lastUpdated = DB::table('room_comps')
            ->where('roid', $roid)
            ->max('addedat');

        return response()->json([
            'success'      => true,
            'elements'     => $elements,
            'last_updated' => $lastUpdated,
        ]);
    }

    // ── Helper privati ───────────────────────────────────────────

    /**
     * Save compound + recipe and return JSON response (or array if returnArray=true).
     */
    private function saveAndRespond(int $cidA, int $cidB, array $generated, int $roid, int $uid, bool $returnArray = false): JsonResponse|array
    {
        try {
            $result = DB::transaction(function () use ($cidA, $cidB, $generated, $roid, $uid) {

                // ── LOCK sulla ricetta (FOR UPDATE) ──────────────────────
                // Impedisce che due richieste concorrenti creino la stessa
                // ricetta contemporaneamente (race condition).
                // Se la ricetta esiste già, la leggiamo con lock esclusivo.
                $existingRecipe = DB::table('recipes')
                    ->where('cid_a', $cidA)
                    ->where('cid_b', $cidB)
                    ->lockForUpdate()   // FOR UPDATE: nessun altro può modificare
                    ->first();

                if ($existingRecipe) {
                    // Ricetta già creata da un'altra transazione concorrente
                    $resultCompound = DB::table('compounds')
                        ->where('cid', $existingRecipe->cid_result)
                        ->first();
                    $this->addToRoom($roid, $resultCompound->cid);
                    return ['compound' => $resultCompound, 'is_first_discovery' => false];
                }

                // ── LOCK sul compound per nome (FOR SHARE) ───────────────
                // Leggiamo il compound esistente con lock condiviso:
                // altri possono leggerlo ma nessuno può modificarlo/eliminarlo
                // mentre decidiamo se crearne uno nuovo.
                $existing = DB::table('compounds')
                    ->whereRaw('LOWER(name) = ?', [strtolower($generated['name'])])
                    ->sharedLock()     // FOR SHARE: lettura protetta
                    ->first();

                $isFirstDiscovery = false;

                if ($existing) {
                    $resultCompound = $existing;
                } else {
                    // Nessun compound con questo nome — creiamo il nuovo.
                    // Il lock sulla ricetta (FOR UPDATE sopra) garantisce
                    // che solo questa transazione arrivi qui per questa coppia.
                    $newCid = DB::table('compounds')->insertGetId([
                        'name'                 => $generated['name'],
                        'emoji'                => $generated['emoji'],
                        'discoveredat'         => now(),
                        'first_discoverer_uid' => $uid,
                    ], 'cid');
                    $resultCompound   = DB::table('compounds')->where('cid', $newCid)->first();
                    $isFirstDiscovery = true;
                }

                // Salva la ricetta (siamo dentro la transazione con FOR UPDATE)
                DB::table('recipes')->insert([
                    'cid_a'      => $cidA,
                    'cid_b'      => $cidB,
                    'cid_result' => $resultCompound->cid,
                    'createdat'  => now(),
                ]);

                $this->addToRoom($roid, $resultCompound->cid);

                return ['compound' => $resultCompound, 'is_first_discovery' => $isFirstDiscovery];
            });

            $payload = [
                'success'         => true,
                'result'          => $this->formatRow($result['compound']),
                'is_new'          => true,
                'new_in_room'     => true,
                'first_discovery' => $result['is_first_discovery'],
            ];

            return $returnArray ? $payload : response()->json($payload);

        } catch (\Throwable $e) {
            Log::error('GameController@saveAndRespond: error', ['message' => $e->getMessage()]);
            $payload = ['success' => false, 'message' => 'Internal error while saving.'];
            return $returnArray ? $payload : response()->json($payload, 500);
        }
    }

    private function addToRoom(int $roid, int $cid): bool
    {
        // Deve essere chiamato dentro una transazione attiva.
        // FOR UPDATE: blocca la riga (o l'assenza di riga) per evitare
        // che due sessioni concorrenti inseriscano lo stesso compound
        // nella stessa room contemporaneamente.
        $exists = DB::table('room_comps')
            ->where('roid', $roid)
            ->where('cid', $cid)
            ->lockForUpdate()
            ->exists();

        if (! $exists) {
            DB::table('room_comps')->insert([
                'roid'    => $roid,
                'cid'     => $cid,
                'addedat' => now(),
            ]);
            return true;
        }
        return false;
    }

    private function seedRoomWithBaseElements(int $roid): void
    {
        $placeholders = implode(',', array_fill(0, count(self::BASE_ELEMENTS), '?'));

        $baseElements = DB::table('compounds')
            ->whereRaw("LOWER(name) IN ({$placeholders})", self::BASE_ELEMENTS)
            ->pluck('cid');

        // Transazione per garantire atomicità del seed iniziale
        DB::transaction(function () use ($roid, $baseElements) {
            foreach ($baseElements as $cid) {
                $this->addToRoom($roid, $cid);
            }
        });
    }

    private function ensureBaseElements(int $roid): void
    {
        $this->seedRoomWithBaseElements($roid);
    }

    private function formatRow(object $row): array
    {
        return [
            'cid'   => $row->cid,
            'name'  => $row->name,
            'emoji' => $row->emoji ?? '✨',
        ];
    }
}

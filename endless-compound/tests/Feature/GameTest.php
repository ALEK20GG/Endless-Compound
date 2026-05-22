<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

// ── Helper ────────────────────────────────────────────────────────

function createBaseElements(): void
{
    foreach (['water' => '💧', 'fire' => '🔥', 'earth' => '🌍', 'air' => '💨'] as $name => $emoji) {
        DB::table('compounds')->insertOrIgnore([
            'name'         => ucfirst($name),
            'emoji'        => $emoji,
            'discoveredat' => now(),
        ]);
    }
}

function createRoomWithElements(int $uid): object
{
    $roid = DB::table('rooms')->insertGetId([
        'name'       => 'Test Room',
        'isprivate'  => true,
        'maxplayers' => 1,
        'owner_uid'  => $uid,
        'createdat'  => now(),
    ], 'roid');

    createBaseElements();

    $cids = DB::table('compounds')
        ->whereIn('name', ['Water', 'Fire', 'Earth', 'Air'])
        ->pluck('cid');

    foreach ($cids as $cid) {
        DB::table('room_comps')->insertOrIgnore([
            'roid'    => $roid,
            'cid'     => $cid,
            'addedat' => now(),
        ]);
    }

    return (object) ['roid' => $roid];
}

// ── Pagina di gioco ───────────────────────────────────────────────

test('un utente autenticato può accedere alla propria room', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $room = createRoomWithElements($user->uid);

    $response = $this->get("/game/{$room->roid}");
    $response->assertStatus(200);
});

test('un utente non può accedere alla room di un altro', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $room = createRoomWithElements($owner->uid);

    $this->actingAs($other);
    $response = $this->get("/game/{$room->roid}");
    $response->assertStatus(403);
});

test('una room inesistente restituisce 404', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/game/99999');
    $response->assertStatus(404);
});

// ── Combine ───────────────────────────────────────────────────────

test('il combine con ricetta esistente restituisce il risultato', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $room = createRoomWithElements($user->uid);

    // Crea una ricetta pre-esistente
    $cidWater = DB::table('compounds')->where('name', 'Water')->value('cid');
    $cidFire  = DB::table('compounds')->where('name', 'Fire')->value('cid');
    [$cidA, $cidB] = $cidWater <= $cidFire ? [$cidWater, $cidFire] : [$cidFire, $cidWater];

    $cidSteam = DB::table('compounds')->insertGetId([
        'name'         => 'Steam',
        'emoji'        => '💨',
        'discoveredat' => now(),
    ], 'cid');

    DB::table('recipes')->insert([
        'cid_a'      => $cidA,
        'cid_b'      => $cidB,
        'cid_result' => $cidSteam,
        'createdat'  => now(),
    ]);

    $response = $this->postJson('/game/combine', [
        'roid'      => $room->roid,
        'element_a' => 'Water',
        'element_b' => 'Fire',
    ]);

    $response->assertStatus(200)
             ->assertJson(['success' => true])
             ->assertJsonPath('result.name', 'Steam');
});

test('il combine con local_result salva il compound', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $room = createRoomWithElements($user->uid);

    $response = $this->postJson('/game/combine', [
        'roid'         => $room->roid,
        'element_a'    => 'Water',
        'element_b'    => 'Earth',
        'local_result' => 'Mud',
        'local_emoji'  => '🟫',
    ]);

    $response->assertStatus(200)
             ->assertJson(['success' => true]);

    $this->assertDatabaseHas('compounds', ['name' => 'Mud']);
});

test('il combine fallisce se la room non esiste', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    createBaseElements();

    $response = $this->postJson('/game/combine', [
        'roid'      => 99999,
        'element_a' => 'Water',
        'element_b' => 'Fire',
    ]);

    $response->assertStatus(403);
});

test('il combine fallisce se un elemento non esiste', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $room = createRoomWithElements($user->uid);

    $response = $this->postJson('/game/combine', [
        'roid'      => $room->roid,
        'element_a' => 'Water',
        'element_b' => 'NonExistentElement',
    ]);

    $response->assertStatus(422);
});

// ── Rate limiting ─────────────────────────────────────────────────

test('il rate limiting blocca dopo 30 richieste al minuto', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $room = createRoomWithElements($user->uid);

    // Svuota il rate limiter
    \Illuminate\Support\Facades\RateLimiter::clear("combine_rate:{$user->uid}");

    // Fai 30 richieste (tutte con local_result per non chiamare LLM)
    for ($i = 0; $i < 30; $i++) {
        $this->postJson('/game/combine', [
            'roid'         => $room->roid,
            'element_a'    => 'Water',
            'element_b'    => 'Fire',
            'local_result' => "Element{$i}",
            'local_emoji'  => '✨',
        ]);
    }

    // La 31a deve essere bloccata
    $response = $this->postJson('/game/combine', [
        'roid'         => $room->roid,
        'element_a'    => 'Water',
        'element_b'    => 'Earth',
        'local_result' => 'TooMany',
        'local_emoji'  => '✨',
    ]);

    $response->assertStatus(429);
});

// ── Elementi room ─────────────────────────────────────────────────

test('GET /game/elements restituisce gli elementi della room', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $room = createRoomWithElements($user->uid);

    $response = $this->getJson("/game/elements?roid={$room->roid}");

    $response->assertStatus(200)
             ->assertJson(['success' => true])
             ->assertJsonCount(4, 'elements');
});

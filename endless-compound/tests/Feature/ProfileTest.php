<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('la pagina profilo è accessibile agli utenti autenticati', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/profile');
    $response->assertStatus(200);
});

test('la pagina profilo non è accessibile ai guest', function () {
    $response = $this->get('/profile');
    $response->assertRedirect('/login');
});

test('un utente può aggiornare il proprio username', function () {
    $user = User::factory()->create(['username' => 'OldName']);
    $this->actingAs($user);

    $response = $this->post('/profile', [
        'username' => 'NewName',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'uid'      => $user->uid,
        'username' => 'NewName',
    ]);
});

test('un utente può cambiare la password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('oldpassword'),
    ]);
    $this->actingAs($user);

    $response = $this->post('/profile', [
        'username'              => $user->username,
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect();

    $updated = DB::table('users')->where('uid', $user->uid)->first();
    expect(Hash::check('newpassword123', $updated->password))->toBeTrue();
});

test('il cambio password fallisce se le password non coincidono', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post('/profile', [
        'username'              => $user->username,
        'password'              => 'newpassword123',
        'password_confirmation' => 'differentpassword',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test("l'username viene sanificato con filter_var", function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Tenta di inserire HTML nell'username
    $response = $this->post('/profile', [
        'username' => '<script>alert("xss")</script>ValidName',
    ]);

    $response->assertRedirect();

    $updated = DB::table('users')->where('uid', $user->uid)->first();
    // Il nome non deve contenere tag HTML
    expect($updated->username)->not->toContain('<script>');
});

test('la leaderboard è accessibile pubblicamente', function () {
    $response = $this->get('/leaderboard');
    $response->assertStatus(200);
});

test('la leaderboard mostra i top scopritori', function () {
    $user = User::factory()->create(['username' => 'TopDiscoverer']);

    DB::table('compounds')->insert([
        ['name' => 'Steam',  'emoji' => '💨', 'discoveredat' => now(), 'first_discoverer_uid' => $user->uid],
        ['name' => 'Lava',   'emoji' => '🌋', 'discoveredat' => now(), 'first_discoverer_uid' => $user->uid],
        ['name' => 'Mud',    'emoji' => '🟫', 'discoveredat' => now(), 'first_discoverer_uid' => $user->uid],
    ]);

    $response = $this->get('/leaderboard');
    $response->assertStatus(200)
             ->assertSee('TopDiscoverer');
});

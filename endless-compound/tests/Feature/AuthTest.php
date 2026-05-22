<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// ── Pagine pubbliche ──────────────────────────────────────────────

test('la pagina di registrazione è accessibile', function () {
    $this->get('/register')->assertStatus(200);
});

test('la pagina di login è accessibile', function () {
    $this->get('/login')->assertStatus(200);
});

// ── Protezione route ──────────────────────────────────────────────

test('le route protette reindirizzano al login se non autenticato', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/game')->assertRedirect('/login');
    $this->get('/profile')->assertRedirect('/login');
});

test('la leaderboard è accessibile pubblicamente senza login', function () {
    $this->get('/leaderboard')->assertStatus(200);
});

// ── Autenticazione diretta ────────────────────────────────────────

test('un utente autenticato può accedere alla dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
         ->get('/dashboard')
         ->assertStatus(200);
});

test('un utente autenticato può accedere al profilo', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
         ->get('/profile')
         ->assertStatus(200);
});

test('un utente autenticato viene reindirizzato dalla home al dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
         ->get('/')
         ->assertRedirect('/dashboard');
});

// ── Logout ────────────────────────────────────────────────────────

test('un utente autenticato può fare logout', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/logout')->assertRedirect();
    $this->assertGuest();
});

// ── Validazione credenziali ───────────────────────────────────────

test('il login fallisce con credenziali errate', function () {
    User::factory()->create([
        'email'    => 'user@example.com',
        'password' => Hash::make('correctpassword'),
    ]);

    $result = \Illuminate\Support\Facades\Auth::attempt([
        'email'    => 'user@example.com',
        'password' => 'wrongpassword',
    ]);

    expect($result)->toBeFalse();
    $this->assertGuest();
});

test('il login riesce con credenziali corrette', function () {
    User::factory()->create([
        'email'    => 'correct@example.com',
        'password' => Hash::make('correctpassword'),
    ]);

    $result = \Illuminate\Support\Facades\Auth::attempt([
        'email'    => 'correct@example.com',
        'password' => 'correctpassword',
    ]);

    expect($result)->toBeTrue();
    $this->assertAuthenticated();
});

// ── Validazione email con filter_var ─────────────────────────────

test('filter_var valida correttamente le email', function () {
    // Email valide
    expect(filter_var('user@example.com', FILTER_VALIDATE_EMAIL))->not->toBeFalse();
    expect(filter_var('user+tag@sub.domain.com', FILTER_VALIDATE_EMAIL))->not->toBeFalse();

    // Email non valide
    expect(filter_var('notanemail', FILTER_VALIDATE_EMAIL))->toBeFalse();
    expect(filter_var('@nodomain.com', FILTER_VALIDATE_EMAIL))->toBeFalse();
    expect(filter_var('user@', FILTER_VALIDATE_EMAIL))->toBeFalse();
});

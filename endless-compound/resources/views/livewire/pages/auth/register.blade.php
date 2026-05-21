<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;

layout('layouts.guest');

state([
    'username' => '',
    'email' => '',
    'password' => '',
    'password_confirmation' => ''
]);

rules([
    'username' => ['required', 'string', 'max:255'],
    'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
    'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
]);

$register = function () {
    $validated = $this->validate();

    $user = User::create([
        'username'  => $validated['username'],
        'email'     => $validated['email'],
        'password'  => Hash::make($validated['password']),
        'createdat' => now(),
    ]);

    event(new Registered($user));

    Auth::login($user);

    $this->redirect(route('dashboard', absolute: false), navigate: true);
};

?>

<div>
    <form wire:submit="register">

        {{-- Username --}}
        <div>
            <x-input-label for="username" :value="__('Username')" />
            <x-text-input wire:model="username" id="username" class="block mt-1 w-full"
                type="text" name="username" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        {{-- Email --}}
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full"
                type="email" name="email" required autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{-- Password --}}
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        {{-- Conferma password --}}
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Conferma Password')" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800"
                href="{{ route('login') }}" wire:navigate>
                {{ __('Hai già un account?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Registrati') }}
            </x-primary-button>
        </div>

    </form>
</div>

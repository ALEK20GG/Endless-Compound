@php
    // Esempio: stato iniziale della board passato dal controller
    $initialBoard = $boardState ?? [];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Endless Compound – Board di Gioco
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    {{-- 
                        QUI: UI principale in stile Infinite Craft
                        Linee guida:
                        - front end responsive (usa Tailwind / CSS)
                        - controllo e sanificazione input (HTML5 + JS)
                        - uso di AJAX per aggiornare la board senza ricaricare la pagina
                    --}}

                    <div id="game-root"
                         data-initial-board='@json($initialBoard)'
                         data-combine-url="{{ route('game.combine') }}"
                         data-save-board-url="{{ route('game.saveBoard') }}">
                        {{-- Il JS (resources/js/game.js) si attaccherà qui --}}
                    </div>

                    <p class="mt-4 text-sm text-gray-500">
                        Placeholder: qui andrà la board con gli oggetti, la barra laterale, ecc.
                        Tutta la logica visuale la implementerete nel file <code>resources/js/game.js</code>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>



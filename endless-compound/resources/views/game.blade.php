@php
    // Stato iniziale della board passato dal controller
    $initialBoard = $boardState ?? [];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Endless Compound – Board di Gioco
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- ── SEZIONE ROOMS DAL DB ─────────────────────────────────── --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Rooms disponibili
                    </h3>

                    @if($rooms->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                            Nessuna room trovata nel database.
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">#</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Nome</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Privata</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Max giocatori</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Creata il</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($rooms as $room)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $room->roid }}</td>
                                            <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">
                                                {{ e($room->name) }}
                                            </td>
                                            <td class="px-4 py-2">
                                                @if($room->isprivate)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300">
                                                        Privata
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
                                                        Pubblica
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $room->maxplayers }}</td>
                                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400">
                                                {{ $room->createdat ? $room->createdat->format('d/m/Y H:i') : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ── SEZIONE BOARD DI GIOCO ───────────────────────────────── --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div id="game-root"
                         data-initial-board='@json($initialBoard)'
                         data-combine-url="{{ route('game.combine') }}"
                         data-save-board-url="{{ route('game.saveBoard') }}">
                        {{-- Il JS (resources/js/game.js) si attaccherà qui --}}
                    </div>

                    <p class="mt-4 text-sm text-gray-500">
                        Placeholder board: la logica visuale va in <code>resources/js/game.js</code>.
                    </p>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>



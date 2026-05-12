<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Models\Room;

class GameController extends Controller
{
    /**
     * Pagina principale di gioco (UI).
     *
     * Legge le rooms dal DB Supabase (PostgreSQL) e le passa alla view.
     */
    public function index(Request $request)
    {
        // Lettura stato board da sessione
        $boardState = Session::get('board_state', []);

        // SELECT * FROM rooms ORDER BY createdat DESC
        $rooms = Room::orderBy('createdat', 'desc')->get();

        return view('game', compact('boardState', 'rooms'));
    }

    /**
     * Endpoint per combinare due oggetti (AJAX / fetch dal frontend).
     *
     * Linee guida collegate:
     * - Controllo e sanificazione input
     * - Uso delle SESSIONI per le coordinate / stato board
     * - Uso di prepared statement / transazioni -> qui in futuro con DB.
     */
    public function combine(Request $request)
    {
        // Validazione e sanificazione input (side, html5/js da frontend + qui lato server)
        $data = $request->validate([
            'source' => ['required', 'string', 'max:255'],
            'target' => ['required', 'string', 'max:255'],
        ]);

        // TODO: logica di combinazione (es. chiamata a un servizio interno o API LLaMA)

        // Esempio di aggiornamento stato board in sessione
        $boardState = Session::get('board_state', []);
        // TODO: aggiornare $boardState in base al risultato della combinazione
        Session::put('board_state', $boardState);

        return response()->json([
            'success' => true,
            'result' => null, // qui andrà il nuovo oggetto combinato
            'boardState' => $boardState,
        ]);
    }

    /**
     * Endpoint per salvare/stabilire lo stato completo della board.
     *
     * Utile se decidete di sincronizzare interamente la board via AJAX.
     */
    public function saveBoard(Request $request)
    {
        $data = $request->validate([
            'board' => ['required', 'array'],
        ]);

        Session::put('board_state', $data['board']);

        return response()->json([
            'success' => true,
        ]);
    }
}



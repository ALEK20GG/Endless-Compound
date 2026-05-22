# Endless Compound

> Infinite Craft, but with AI-generated combinations and multiplayer rooms.

Endless Compound è un gioco web in cui si parte dai quattro elementi base (Acqua, Fuoco, Terra, Aria) e si combinano tra loro per scoprire nuovi elementi generati da un modello AI. Ogni nuova combinazione viene salvata globalmente: chi la scopre per primo viene registrato come primo scopritore.

## Funzionalità

- **Combinazione elementi** — trascina due elementi sulla board per combinarli. Il risultato viene generato da un LLM (OpenRouter) e salvato per tutti i giocatori.
- **Multiplayer** — crea o entra in una room condivisa con un codice. Le scoperte vengono sincronizzate in tempo reale via polling AJAX.
- **Autenticazione** — login con email/password o Google OAuth. 2FA via email disponibile in ambiente locale.
- **Leaderboard** — classifica globale dei giocatori con più prime scoperte mondiali.
- **Profilo** — statistiche personali, scoperte recenti, gestione room.
- **Tema light/dark** — preferenza salvata in cookie.
- **Responsive** — funziona su mobile con supporto touch drag & drop.

## Stack tecnologico

| Layer | Tecnologia |
|---|---|
| Backend | PHP 8.2, Laravel 12, Livewire/Volt |
| Frontend | Tailwind CSS, Vite, Vanilla JS |
| Database | PostgreSQL (Supabase) |
| AI | OpenRouter API (GPT / LLaMA) |
| Auth | Laravel Breeze + Google OAuth (Socialite) |
| Deploy | Docker, Render |

## Avvio locale

```bash
cd endless-compound
cp .env.example .env          # configura le variabili d'ambiente
composer install
npm install
npm run build
php artisan key:generate
php artisan migrate
php artisan serve
```

Variabili d'ambiente necessarie: vedi `endless-compound/.env.example`.

## Test

```bash
cd endless-compound
php artisan test
```

I test usano SQLite in-memory e non richiedono una connessione al database Supabase.

## Struttura del progetto

```
endless-compound/
├── app/
│   ├── Http/Controllers/     # GameController, ProfileController, Auth/*
│   ├── Livewire/Forms/       # LoginForm (con 2FA in locale)
│   ├── Models/               # User, Room, Compound, Recipe
│   └── Services/             # LlamaCombinationService
├── resources/
│   ├── js/game.js            # Logica board, drag & drop, touch, combine
│   ├── css/style.css         # Stili custom + light theme
│   └── views/                # Blade templates
├── routes/
│   ├── web.php               # Route principali
│   └── auth.php              # Route autenticazione (Breeze)
├── database/
│   ├── migrations/           # Schema DB
│   └── factories/            # Factory per i test
└── tests/
    ├── Feature/              # Test di integrazione (Auth, Game, Profile)
    └── Unit/                 # Test unitari (LlamaCombinationService)
```

## Autori

Progetto scolastico — sviluppato con Laravel 12 + Supabase + OpenRouter.

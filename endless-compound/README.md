# Endless Compound — Laravel App

Applicazione Laravel 12 per il gioco Endless Compound. Vedi il [README principale](../README.md) per la descrizione completa del progetto.

## Requisiti

- PHP 8.2+
- Node.js 20+
- Composer
- Connessione a un database PostgreSQL (Supabase)

## Configurazione

Copia `.env.example` in `.env` e compila le variabili:

```env
APP_KEY=           # generata con php artisan key:generate
APP_URL=           # es. http://localhost

DB_CONNECTION=supabase
DB_HOST=           # host Supabase
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=       # username Supabase
DB_PASSWORD=       # password Supabase

SESSION_DRIVER=database
CACHE_STORE=database

MAIL_MAILER=smtp   # in locale: Gmail SMTP
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=     # tua email Gmail
MAIL_PASSWORD=     # App Password Gmail
MAIL_ENCRYPTION=tls

LLAMA_ENDPOINT=https://openrouter.ai/api/v1/chat/completions
LLAMA_API_KEY=     # chiave OpenRouter
LLAMA_MODEL=openai/gpt-oss-120b:free

GOOGLE_CLIENT_ID=      # Google OAuth client ID
GOOGLE_CLIENT_SECRET=  # Google OAuth client secret
GOOGLE_REDIRECT_URI=   # es. http://localhost/auth/google/callback
```

## Installazione

```bash
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate
php artisan serve
```

## Test

```bash
php artisan test
# oppure con output dettagliato:
php artisan test --verbose
```

I test usano SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) configurato in `phpunit.xml`. Non richiedono connessione a Supabase.

### Suite di test

| File | Cosa testa |
|---|---|
| `tests/Feature/AuthTest.php` | Registrazione, login, logout, protezione route |
| `tests/Feature/GameTest.php` | Combine, accesso room, rate limiting, elementi |
| `tests/Feature/ProfileTest.php` | Aggiornamento profilo, sanificazione input, leaderboard |
| `tests/Unit/LlamaCombinationServiceTest.php` | Parser risposta LLM |

## Deploy (Render)

Il `Dockerfile` nella root del progetto gestisce il build. Le variabili d'ambiente vanno configurate nel pannello Render:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tuo-app.onrender.com
SESSION_DRIVER=database
CACHE_STORE=database
MAIL_MAILER=log    # nessuna mail in produzione
```

## Tecnologie utilizzate

- **Laravel 12** — framework PHP MVC
- **Livewire / Volt** — componenti reattivi server-side
- **Tailwind CSS + Vite** — styling e build assets
- **Supabase (PostgreSQL)** — database relazionale
- **OpenRouter API** — generazione combinazioni via LLM
- **Laravel Socialite** — Google OAuth
- **Pest** — framework di testing
- **Docker** — containerizzazione per il deploy

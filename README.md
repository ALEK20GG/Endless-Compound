# ⚗️ Endless Compound

> Infinite Craft, but with AI-generated combinations and multiplayer rooms.

Endless Compound è un gioco web ispirato a *Infinite Craft* in cui si parte dai quattro elementi base (**Acqua, Fuoco, Terra, Aria**) e si combinano tra loro per scoprire nuovi elementi generati da un modello AI. Ogni combinazione viene salvata globalmente nel database: chi la scopre per primo viene registrato come **primo scopritore mondiale** e compare in leaderboard.

---

## Indice

- [Funzionalità](#funzionalità)
- [Stack tecnologico](#stack-tecnologico)
- [Architettura](#architettura)
- [Schema del database](#schema-del-database)
- [Integrazione AI](#integrazione-ai)
- [Autenticazione](#autenticazione)
- [Avvio locale](#avvio-locale)
- [Variabili d'ambiente](#variabili-dambiente)
- [Test](#test)
- [Deploy (Render + Docker)](#deploy-render--docker)
- [Struttura del progetto](#struttura-del-progetto)
- [Autori](#autori)

---

## Funzionalità

### Gameplay
- **Combinazione drag & drop** — trascina due elementi sulla board per combinarli. Il risultato viene generato da un LLM via OpenRouter e salvato globalmente per tutti i giocatori.
- **Board interattiva** — zoom (scroll), pan (click su area vuota o tasto centrale), supporto touch completo per mobile.
- **Sidebar elementi** — lista di tutti gli elementi scoperti nella room, con ricerca testuale e highlight giallo per le prime scoperte mondiali personali.
- **Discovery tree** — modale che mostra il grafo di tutte le ricette scoperte nella room (A + B → Risultato).
- **Posizioni persistenti** — le posizioni degli elementi sulla board vengono salvate in `localStorage` per utente e room.

### Multiplayer
- **Creazione room** — crea una room con nome e numero massimo di giocatori (2–10), ottieni un codice di 6 caratteri da condividere.
- **Join room** — entra in una room esistente tramite codice.
- **Sincronizzazione real-time** — polling AJAX ogni 3 secondi per aggiornare la sidebar con gli elementi scoperti dagli altri giocatori.
- **Chat in-room** — chat testuale in tempo reale (polling ogni 2 secondi), visibile solo in modalità multiplayer. I messaggi vengono salvati nel DB e caricati da zero ad ogni invio.
- **Notifiche scoperte** — toast notification quando un altro giocatore scopre un nuovo elemento.
- **Invito via email** — il proprietario della room può inviare un'email di invito con il codice e il link diretto.

### Singleplayer
- Ogni utente ha una room singleplayer dedicata (creata automaticamente al primo accesso a `/game`).

### Profilo e leaderboard
- **Profilo** — statistiche personali (numero di prime scoperte mondiali), lista delle ultime scoperte, gestione delle room a cui si partecipa.
- **Leaderboard** — classifica globale dei top 20 giocatori per numero di prime scoperte mondiali, con contatore totale dei composti nel DB.

### Admin panel
- Accessibile agli utenti con `is_admin = true`.
- Visualizza statistiche globali (compounds, ricette, utenti, room).
- Permette di eliminare compounds (con cascade su ricette e room_comps).
- Permette di promuovere/revocare i privilegi admin agli utenti.

### UI/UX
- **Tema light/dark** — toggle nella navbar, preferenza salvata in cookie per 1 anno.
- **Responsive** — layout adattivo per mobile con sidebar a scomparsa e drag & drop touch.
- **Toast notifications** — feedback visivo per ogni azione (combinazione riuscita, prima scoperta, errori, scoperte altrui).

---

## Stack tecnologico

| Layer | Tecnologia | Versione |
|---|---|---|
| Backend | PHP + Laravel | 8.2 / 12.x |
| Frontend | Tailwind CSS + Vite + Vanilla JS | 3.x / 7.x |
| Componenti reattivi | Livewire / Volt | 3.x / 1.x |
| Database | PostgreSQL via Supabase | — |
| AI / LLM | OpenRouter API | — |
| Auth | Laravel Breeze + Google OAuth (Socialite) | 2.x / 5.x |
| Email | SMTP (Gmail) / Resend | — |
| Testing | Pest + Pest-Laravel | 3.x |
| Deploy | Docker + Apache + Render | — |

---

## Architettura

```
Browser
  │
  ├── GET /game/{roid}          → GameController@index (Blade view)
  │
  ├── POST /game/combine        → GameController@combine
  │     ├── Ricetta esistente?  → risposta immediata
  │     ├── Local AI result?    → salva e risponde
  │     └── Nessuno dei due     → crea job in Cache, risponde con job_id
  │
  ├── GET /game/combine/poll/{jobId}
  │     └── Esegue LLM (LlamaCombinationService) → salva risultato
  │
  ├── GET /game/elements?roid=X&since=Y  → polling sidebar (ogni 3s in MP)
  │
  ├── POST /game/chat           → salva messaggio in room_messages
  └── GET  /game/chat?roid=X&since=Y    → polling chat (ogni 2s)
```

### Flusso combinazione

1. Il client invia `POST /game/combine` con i nomi dei due elementi.
2. Se esiste già una ricetta nel DB → risposta immediata.
3. Se il client ha un risultato dal worker JS locale (Hugging Face Transformers) → il server lo salva e risponde.
4. Altrimenti → il server crea un job in Cache e risponde con `{ pending: true, job_id }`.
5. Il client fa polling su `GET /game/combine/poll/{jobId}` ogni 2 secondi.
6. Al primo poll, il server chiama `LlamaCombinationService::combine()` con fallback su più modelli OpenRouter.
7. Il risultato viene salvato in `compounds` + `recipes` + `room_comps` e restituito al client.

### Rate limiting

- Max **30 combinazioni/minuto** per utente (Laravel `RateLimiter`).
- Risposta `429` con secondi di attesa rimanenti.

---

## Schema del database

Il database è PostgreSQL gestito su **Supabase**. Le tabelle principali sono definite manualmente (non tramite migration standard di Laravel, che è usato solo per le tabelle ausiliarie).

### Tabelle principali

#### `users`
| Colonna | Tipo | Note |
|---|---|---|
| `uid` | bigserial PK | — |
| `username` | varchar(50) | unico |
| `email` | varchar(255) | unico |
| `password` | varchar(255) | nullable (OAuth) |
| `google_id` | varchar(255) | nullable |
| `two_factor_code` | varchar(10) | nullable, 2FA locale |
| `two_factor_expires_at` | timestamp | nullable |
| `is_admin` | boolean | default false |
| `createdat` | timestamp | — |

#### `compounds`
| Colonna | Tipo | Note |
|---|---|---|
| `cid` | bigserial PK | — |
| `name` | varchar(100) | unico (case-insensitive) |
| `emoji` | varchar(10) | — |
| `discoveredat` | timestamp | — |
| `first_discoverer_uid` | bigint FK → users | nullable |

#### `recipes`
| Colonna | Tipo | Note |
|---|---|---|
| `rid` | bigserial PK | — |
| `cid_a` | bigint FK → compounds | sempre ≤ cid_b (ordinati) |
| `cid_b` | bigint FK → compounds | — |
| `cid_result` | bigint FK → compounds | — |
| `createdat` | timestamp | — |

#### `rooms`
| Colonna | Tipo | Note |
|---|---|---|
| `roid` | bigserial PK | — |
| `name` | varchar(100) | — |
| `isprivate` | boolean | — |
| `maxplayers` | integer | 1 = singleplayer |
| `owner_uid` | bigint FK → users | — |
| `code` | varchar(6) | unico, solo MP |
| `createdat` | timestamp | — |

#### `room_comps`
| Colonna | Tipo | Note |
|---|---|---|
| `roid` | bigint FK → rooms | PK composita |
| `cid` | bigint FK → compounds | PK composita |
| `addedat` | timestamp | usato per il polling |

#### `collabs_in`
| Colonna | Tipo | Note |
|---|---|---|
| `uid` | bigint FK → users | PK composita |
| `roid` | bigint FK → rooms | PK composita |
| `joinedat` | timestamp | — |

#### `room_messages`
| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigserial PK | — |
| `roid` | bigint FK → rooms | — |
| `uid` | bigint FK → users | — |
| `message` | varchar(100) | — |
| `createdat` | timestamp | — |

#### Tabelle ausiliarie Laravel
- `sessions` — driver sessione database
- `password_reset_tokens` — reset password
- `cache` — driver cache database
- `jobs` / `job_batches` / `failed_jobs` — queue

---

## Integrazione AI

### OpenRouter API

Le combinazioni vengono generate tramite **OpenRouter** (`https://openrouter.ai/api/v1/chat/completions`), che espone un'interfaccia OpenAI-compatibile per decine di modelli.

**Prompt di sistema:**
```
Combine the provided words and produce a new element.
Format: "emoji name" (es. "💨 Steam")
- Un solo emoji, nome in inglese, conciso ed esplicito
- Nessuna spiegazione aggiuntiva
- Se la combinazione non ha senso, sii creativo
```

**Modelli con fallback automatico** (in ordine di priorità):
1. `openai/gpt-oss-120b:free`
2. `openai/gpt-oss-20b:free`
3. `nvidia/nemotron-nano-12b-v2-vl:free`
4. `google/gemma-4-26b-a4b-it:free`
5. `google/gemma-4-31b-it:free`
6. `z-ai/glm-4.5-air:free`
7. `meta-llama/llama-3.3-70b-instruct:free`
8. `meta-llama/llama-3.2-3b-instruct:free`

Se un modello risponde con 429, 404, 502 o contenuto vuoto, il servizio passa automaticamente al successivo.

### Local AI (opzionale, solo in locale)

In ambiente `local`, il frontend carica un **Web Worker** (`combination-worker.js`) basato su `@huggingface/transformers` che esegue un modello direttamente nel browser. Se il worker produce un risultato, viene inviato al server per la persistenza senza chiamare OpenRouter.

---

## Autenticazione

| Metodo | Dettagli |
|---|---|
| Email/password | Registrazione + login classico via Laravel Breeze |
| Google OAuth | Login con account Google via Laravel Socialite |
| 2FA via email | Disponibile solo in ambiente `local` — codice OTP inviato via SMTP |

Le sessioni sono gestite con driver `database` (tabella `sessions`).

---

## Avvio locale

### Prerequisiti

- PHP 8.2+
- Composer
- Node.js 20+
- Accesso a un database PostgreSQL (Supabase o locale)

### Installazione

```bash
# 1. Clona il repository
git clone <repo-url>
cd endless-compound

# 2. Installa le dipendenze PHP
composer install

# 3. Installa le dipendenze JS e builda gli asset
npm install
npm run build

# 4. Configura l'ambiente
cp .env.example .env
php artisan key:generate

# 5. Esegui le migration
php artisan migrate

# 6. Avvia il server
php artisan serve
```

In alternativa, usa lo script `composer dev` per avviare in parallelo server, queue, log e Vite:

```bash
composer dev
```

---

## Variabili d'ambiente

Tutte le variabili vanno configurate in `endless-compound/.env`:

```env
# App
APP_NAME="Endless Compound"
APP_ENV=local
APP_KEY=                        # generata con php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost

# Database (Supabase PostgreSQL)
DB_CONNECTION=supabase
DB_HOST=                        # host Supabase (es. db.xxxx.supabase.co)
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=                    # password Supabase
DB_SSLMODE=require

# Sessioni e cache
SESSION_DRIVER=database
CACHE_STORE=database

# Email (Gmail SMTP per locale)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=                  # tua email Gmail
MAIL_PASSWORD=                  # App Password Gmail (non la password normale)
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=              # stessa email
MAIL_FROM_NAME="Endless Compound"

# AI / OpenRouter
LLAMA_ENDPOINT=https://openrouter.ai/api/v1/chat/completions
LLAMA_API_KEY=                  # chiave API OpenRouter
LLAMA_MODEL=openai/gpt-oss-120b:free

# Google OAuth
GOOGLE_CLIENT_ID=               # da Google Cloud Console
GOOGLE_CLIENT_SECRET=           # da Google Cloud Console
GOOGLE_REDIRECT_URI=http://localhost/auth/google/callback
```

---

## Test

Il progetto usa **Pest** con SQLite in-memory — non è necessaria una connessione a Supabase.

```bash
cd endless-compound

# Esegui tutti i test
php artisan test

# Con output dettagliato
php artisan test --verbose

# Solo una suite
php artisan test --filter=GameTest
```

### Suite di test

| File | Cosa testa |
|---|---|
| `tests/Feature/AuthTest.php` | Registrazione, login, logout, protezione route autenticate |
| `tests/Feature/GameTest.php` | Combine (ricetta esistente, nuova, rate limit), accesso room, polling elementi |
| `tests/Feature/ProfileTest.php` | Aggiornamento profilo, sanificazione input, leaderboard, leave room |
| `tests/Unit/LlamaCombinationServiceTest.php` | Parser risposta LLM (emoji, nome, mojibake, edge case) |

---

## Deploy (Render + Docker)

Il progetto è containerizzato con **Docker** e deployato su **Render**.

### Build Docker

```dockerfile
FROM php:8.2-apache
# Installa: pdo_pgsql, zip, Node.js 20, Composer
# Copia dipendenze → composer install → npm ci → copia codice → npm run build
# Configura Apache (DocumentRoot → /public, mod_rewrite)
# PHP timeout: 300s (per chiamate LLM lunghe)
# Entrypoint: start.sh (php artisan migrate --force + apache2-foreground)
```

### Variabili d'ambiente su Render

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tuo-app.onrender.com
SESSION_DRIVER=database
CACHE_STORE=database
MAIL_MAILER=log          # disabilita email in produzione (o configura Resend)
```

Tutte le altre variabili (DB, LLAMA, GOOGLE) vanno aggiunte nel pannello Environment di Render.

---

## Struttura del progetto

```
endless-compound/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── GameController.php          # Logica principale: combine, elements, chat, tree, admin
│   │       ├── ProfileController.php       # Profilo utente, leave room
│   │       └── Auth/
│   │           ├── GoogleAuthController.php    # OAuth Google
│   │           ├── TwoFactorController.php     # 2FA (solo locale)
│   │           └── VerifyEmailController.php
│   ├── Livewire/
│   │   ├── Actions/Logout.php
│   │   └── Forms/LoginForm.php             # Form login con supporto 2FA
│   ├── Mail/
│   │   ├── WelcomeMail.php                 # Email di benvenuto
│   │   ├── RoomInviteMail.php              # Invito room multiplayer
│   │   └── TwoFactorMail.php               # Codice OTP 2FA
│   ├── Models/
│   │   ├── User.php
│   │   ├── Room.php
│   │   ├── Compound.php
│   │   └── Recipe.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── VoltServiceProvider.php
│   └── Services/
│       └── LlamaCombinationService.php     # Chiamate OpenRouter con fallback multi-modello
│
├── resources/
│   ├── js/
│   │   ├── game.js                         # Board, drag&drop, touch, zoom/pan, combine, chat, sidebar
│   │   ├── combination-worker.js           # Web Worker Hugging Face (local AI)
│   │   └── app.js / bootstrap.js
│   ├── css/
│   │   ├── app.css                         # Tailwind entry
│   │   └── style.css                       # Stili custom + light theme + toast + board items
│   └── views/
│       ├── game.blade.php                  # Vista principale del gioco
│       ├── dashboard.blade.php             # Landing pubblica
│       ├── dashboard-play.blade.php        # Selezione modalità (solo/multi)
│       ├── leaderboard.blade.php
│       ├── admin.blade.php
│       ├── profile.blade.php
│       ├── layouts/                        # AppLayout, GuestLayout
│       ├── components/                     # Componenti Blade riutilizzabili
│       └── livewire/                       # Componenti Livewire/Volt
│
├── routes/
│   ├── web.php                             # Tutte le route (game, auth, admin, profile, leaderboard)
│   └── auth.php                            # Route Breeze (register, login, password reset)
│
├── database/
│   ├── migrations/                         # Migration Laravel (tabelle ausiliarie + room_messages + is_admin)
│   ├── factories/
│   │   └── UserFactory.php
│   └── seeders/
│       └── DatabaseSeeder.php
│
├── tests/
│   ├── Feature/
│   │   ├── AuthTest.php
│   │   ├── GameTest.php
│   │   └── ProfileTest.php
│   └── Unit/
│       └── LlamaCombinationServiceTest.php
│
├── public/
│   ├── combination-worker.js               # Worker JS servito staticamente
│   └── build/                              # Asset Vite compilati
│
├── config/
│   ├── services.php                        # Configurazione llama + google
│   └── database.php                        # Driver "supabase" (alias pgsql)
│
├── Dockerfile                              # Build per deploy su Render
├── start.sh                                # Entrypoint Docker (migrate + apache)
├── composer.json
├── package.json
└── phpunit.xml                             # Configurazione Pest (SQLite in-memory)
```

---

## Autori

Progetto sviluppato da:

| Nome | Ruolo |
|---|---|
| **Zanga Alessandro** | Sviluppatore full-stack |
| **Biglioli Enea** | Sviluppatore full-stack |

Progetto scolastico — sviluppato con **Laravel 12**, **Supabase**, **OpenRouter** e **Docker**.

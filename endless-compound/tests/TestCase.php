<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
        parent::tearDown();
    }

    /**
     * Crea lo schema SQLite in-memory per i test.
     * Non usiamo RefreshDatabase perché le nostre migrazioni
     * contengono sintassi Postgres (bigserial, CONSTRAINT ... PRIMARY KEY)
     * incompatibile con SQLite.
     */
    protected function setUpDatabase(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement('CREATE TABLE IF NOT EXISTS users (
            uid         INTEGER PRIMARY KEY AUTOINCREMENT,
            username    TEXT    NOT NULL,
            email       TEXT    NOT NULL UNIQUE,
            password    TEXT    NULL,
            google_id   TEXT    NULL,
            createdat   TEXT    NULL,
            lastlogin   TEXT    NULL,
            remember_token TEXT NULL,
            email_verified_at TEXT NULL,
            name        TEXT    NULL
        )');

        DB::statement('CREATE TABLE IF NOT EXISTS rooms (
            roid        INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT    NOT NULL,
            isprivate   INTEGER NOT NULL DEFAULT 0,
            maxplayers  INTEGER NOT NULL DEFAULT 1,
            owner_uid   INTEGER NOT NULL,
            createdat   TEXT    NULL,
            code        TEXT    NULL
        )');

        DB::statement('CREATE TABLE IF NOT EXISTS collabs_in (
            uid         INTEGER NOT NULL,
            roid        INTEGER NOT NULL,
            joinedat    TEXT    NULL,
            PRIMARY KEY (uid, roid)
        )');

        DB::statement('CREATE TABLE IF NOT EXISTS compounds (
            cid                   INTEGER PRIMARY KEY AUTOINCREMENT,
            name                  TEXT    NOT NULL UNIQUE,
            emoji                 TEXT    NULL,
            discoveredat          TEXT    NULL,
            first_discoverer_uid  INTEGER NULL
        )');

        DB::statement('CREATE TABLE IF NOT EXISTS recipes (
            rid         INTEGER PRIMARY KEY AUTOINCREMENT,
            cid_a       INTEGER NOT NULL,
            cid_b       INTEGER NOT NULL,
            cid_result  INTEGER NOT NULL,
            createdat   TEXT    NULL,
            UNIQUE (cid_a, cid_b)
        )');

        DB::statement('CREATE TABLE IF NOT EXISTS room_comps (
            roid        INTEGER NOT NULL,
            cid         INTEGER NOT NULL,
            addedat     TEXT    NULL,
            PRIMARY KEY (roid, cid)
        )');

        DB::statement('CREATE TABLE IF NOT EXISTS discovers (
            uid          INTEGER NOT NULL,
            cid          INTEGER NOT NULL,
            discoveredat TEXT    NULL,
            PRIMARY KEY (uid, cid)
        )');

        DB::statement('CREATE TABLE IF NOT EXISTS password_reset_tokens (
            email      TEXT NOT NULL PRIMARY KEY,
            token      TEXT NOT NULL,
            created_at TEXT NULL
        )');

        DB::statement('CREATE TABLE IF NOT EXISTS sessions (
            id            TEXT    NOT NULL PRIMARY KEY,
            user_id       INTEGER NULL,
            ip_address    TEXT    NULL,
            user_agent    TEXT    NULL,
            payload       TEXT    NOT NULL,
            last_activity INTEGER NOT NULL
        )');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    protected function tearDownDatabase(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        foreach (['discovers', 'room_comps', 'recipes', 'compounds',
                  'collabs_in', 'rooms', 'users',
                  'password_reset_tokens', 'sessions'] as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table}");
        }
        DB::statement('PRAGMA foreign_keys = ON');
    }
}

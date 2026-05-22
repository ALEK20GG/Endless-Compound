<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS cache (
                key        varchar(255) NOT NULL,
                value      text         NOT NULL,
                expiration integer      NOT NULL,
                CONSTRAINT cache_pkey PRIMARY KEY (key)
            )
        ");

        DB::statement("CREATE INDEX IF NOT EXISTS cache_expiration_index ON cache (expiration)");

        DB::statement("
            CREATE TABLE IF NOT EXISTS cache_locks (
                key        varchar(255) NOT NULL,
                owner      varchar(255) NOT NULL,
                expiration integer      NOT NULL,
                CONSTRAINT cache_locks_pkey PRIMARY KEY (key)
            )
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS cache");
        DB::statement("DROP TABLE IF EXISTS cache_locks");
    }
};

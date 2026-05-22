<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Our users table is managed manually in Supabase.
     * This migration is a no-op — it only creates auxiliary tables
     * that Laravel needs (password_reset_tokens, sessions) if they don't exist.
     */
    public function up(): void
    {
        // password_reset_tokens — needed by Laravel auth
        DB::statement("
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                email       varchar(255) NOT NULL,
                token       varchar(255) NOT NULL,
                created_at  timestamp    NULL,
                CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email)
            )
        ");

        // sessions — needed if SESSION_DRIVER=database
        DB::statement("
            CREATE TABLE IF NOT EXISTS sessions (
                id            varchar(255) NOT NULL,
                user_id       bigint       NULL,
                ip_address    varchar(45)  NULL,
                user_agent    text         NULL,
                payload       text         NOT NULL,
                last_activity integer      NOT NULL,
                CONSTRAINT sessions_pkey PRIMARY KEY (id)
            )
        ");

        DB::statement("CREATE INDEX IF NOT EXISTS sessions_user_id_index ON sessions (user_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS sessions_last_activity_index ON sessions (last_activity)");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS password_reset_tokens");
        DB::statement("DROP TABLE IF EXISTS sessions");
    }
};

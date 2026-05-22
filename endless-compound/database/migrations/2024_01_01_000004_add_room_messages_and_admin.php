<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── room_messages ────────────────────────────────────────
        DB::statement("
            CREATE TABLE IF NOT EXISTS room_messages (
                id         bigserial    NOT NULL,
                roid       bigint       NOT NULL,
                uid        bigint       NOT NULL,
                message    varchar(100) NOT NULL,
                createdat  timestamp    NOT NULL DEFAULT now(),
                CONSTRAINT room_messages_pkey PRIMARY KEY (id)
            )
        ");

        DB::statement("CREATE INDEX IF NOT EXISTS room_messages_roid_idx ON room_messages (roid, createdat)");

        // ── is_admin column on users ─────────────────────────────
        DB::statement("
            ALTER TABLE users
            ADD COLUMN IF NOT EXISTS is_admin boolean NOT NULL DEFAULT false
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS room_messages");
        DB::statement("ALTER TABLE users DROP COLUMN IF EXISTS is_admin");
    }
};

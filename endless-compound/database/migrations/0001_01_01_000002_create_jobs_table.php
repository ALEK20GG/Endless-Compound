<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Not using queue workers — these tables are no-ops
        // but we create them IF NOT EXISTS to avoid migration errors
        DB::statement("
            CREATE TABLE IF NOT EXISTS jobs (
                id           bigserial    NOT NULL,
                queue        varchar(255) NOT NULL,
                payload      text         NOT NULL,
                attempts     smallint     NOT NULL,
                reserved_at  integer      NULL,
                available_at integer      NOT NULL,
                created_at   integer      NOT NULL,
                CONSTRAINT jobs_pkey PRIMARY KEY (id)
            )
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS jobs_queue_index ON jobs (queue)");

        DB::statement("
            CREATE TABLE IF NOT EXISTS job_batches (
                id             varchar(255) NOT NULL,
                name           varchar(255) NOT NULL,
                total_jobs     integer      NOT NULL,
                pending_jobs   integer      NOT NULL,
                failed_jobs    integer      NOT NULL,
                failed_job_ids text         NOT NULL,
                options        text         NULL,
                cancelled_at   integer      NULL,
                created_at     integer      NOT NULL,
                finished_at    integer      NULL,
                CONSTRAINT job_batches_pkey PRIMARY KEY (id)
            )
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS failed_jobs (
                id         bigserial    NOT NULL,
                uuid       varchar(255) NOT NULL,
                connection text         NOT NULL,
                queue      text         NOT NULL,
                payload    text         NOT NULL,
                exception  text         NOT NULL,
                failed_at  timestamp    NOT NULL DEFAULT now(),
                CONSTRAINT failed_jobs_pkey PRIMARY KEY (id),
                CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid)
            )
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS jobs");
        DB::statement("DROP TABLE IF EXISTS job_batches");
        DB::statement("DROP TABLE IF EXISTS failed_jobs");
    }
};

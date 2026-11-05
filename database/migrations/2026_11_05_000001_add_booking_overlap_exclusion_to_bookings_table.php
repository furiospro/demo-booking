<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Добавляет ограничение-исключение для предотвращения пересечения бронирований.
 *
 * Требует расширение btree_gist.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        // Ограничение-исключение: запрещает пересечение интервалов для одной комнаты
        // Используем tsrange, т.к. колонки имеют тип timestamp без таймзоны
        DB::statement(<<<'SQL'
            ALTER TABLE bookings
            ADD CONSTRAINT bookings_no_overlap
            EXCLUDE USING gist (
                room_id WITH =,
                tsrange(starts_at, ends_at) WITH &&
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_no_overlap');
    }
};
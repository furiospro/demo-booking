<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Переводит колонки времени бронирований на TIMESTAMPTZ и добавляет
 * клиентские времена (в таймзоне клиента, из которой пришёл запрос).
 *
 * starts_at / ends_at           — время в таймзоне комнаты;
 * client_starts_at / client_ends_at — время в таймзоне клиента.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ограничение опирается на тип колонок, поэтому снимаем его до ALTER TYPE.
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_no_overlap');

        // Существующие значения хранились без таймзоны и трактовались как UTC.
        DB::statement("ALTER TABLE bookings ALTER COLUMN starts_at TYPE timestamptz(0) USING starts_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE bookings ALTER COLUMN ends_at TYPE timestamptz(0) USING ends_at AT TIME ZONE 'UTC'");

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestampTz('client_starts_at')->nullable()->after('ends_at');
            $table->timestampTz('client_ends_at')->nullable()->after('client_starts_at');
        });

        // Для уже существующих бронирований клиентское время неизвестно —
        // принимаем его равным времени комнаты.
        DB::statement('UPDATE bookings SET client_starts_at = starts_at WHERE client_starts_at IS NULL');
        DB::statement('UPDATE bookings SET client_ends_at = ends_at WHERE client_ends_at IS NULL');

        DB::statement('ALTER TABLE bookings ALTER COLUMN client_starts_at SET NOT NULL');
        DB::statement('ALTER TABLE bookings ALTER COLUMN client_ends_at SET NOT NULL');

        DB::statement(<<<'SQL'
            ALTER TABLE bookings
            ADD CONSTRAINT bookings_no_overlap
            EXCLUDE USING gist (
                room_id WITH =,
                tstzrange(starts_at, ends_at) WITH &&
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_no_overlap');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['client_starts_at', 'client_ends_at']);
        });

        DB::statement("ALTER TABLE bookings ALTER COLUMN starts_at TYPE timestamp(0) WITHOUT TIME ZONE USING starts_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE bookings ALTER COLUMN ends_at TYPE timestamp(0) WITHOUT TIME ZONE USING ends_at AT TIME ZONE 'UTC'");

        DB::statement(<<<'SQL'
            ALTER TABLE bookings
            ADD CONSTRAINT bookings_no_overlap
            EXCLUDE USING gist (
                room_id WITH =,
                tsrange(starts_at, ends_at) WITH &&
            )
        SQL);
    }
};

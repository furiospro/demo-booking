<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\StoreBookingData;
use App\Exceptions\BookingConflictException;
use App\Exceptions\BookingInvalidIntervalException;
use App\Exceptions\BookingOutsideWorkingHoursException;
use App\Logging\BookingLoggerInterface;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Сервис для управления бронированиями.
 */
final class BookingService
{
    private const int WORKING_HOURS_START = 9;  // 09:00

    private const int WORKING_HOURS_END = 20;   // 20:00

    /** Минимальная длительность бронирования в минутах. */
    private const int MIN_DURATION_MINUTES = 15;

    /** Максимальная длительность бронирования в минутах (4 часа). */
    private const int MAX_DURATION_MINUTES = 240;

    /** Шаг бронирования в минутах: интервал должен быть кратен этому значению. */
    private const int SLOT_MINUTES = 15;

    /** SQLSTATE-коды, означающие конфликт пересечения/уникальности. */
    private const array CONFLICT_SQL_STATES = ['23505', '23P01'];

    public function __construct(
        private readonly BookingLoggerInterface $logger,
    ) {}

    /**
     * Создать бронирование.
     *
     * Время сохраняется в двух представлениях:
     *  - starts_at / ends_at — момент, приведённый к таймзоне комнаты;
     *  - client_starts_at / client_ends_at — момент в таймзоне клиента (как пришёл в запросе).
     *
     * @throws BookingInvalidIntervalException
     * @throws BookingOutsideWorkingHoursException
     * @throws BookingConflictException
     */
    public function createBooking(StoreBookingData $data, User $user): Booking
    {
        $data->validate();

        $room = Room::with('office')->findOrFail($data->room_id);
        
        $clientStartsAt = $data->startsAt();
        $clientEndsAt = $data->endsAt();
        
        $roomTimezone = $this->roomTimezone($room);
        $startsAt = $clientStartsAt->setTimezone($roomTimezone);
        $endsAt = $clientEndsAt->setTimezone($roomTimezone);

        $this->ensureValidInterval($startsAt, $endsAt);
        $this->ensureWorkingHours($startsAt, $endsAt);

        return DB::transaction(function () use ($data, $user, $startsAt, $endsAt, $clientStartsAt, $clientEndsAt): Booking {
            $booking = new Booking([
                'room_id' => $data->room_id,
                'user_id' => $user->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'client_starts_at' => $clientStartsAt,
                'client_ends_at' => $clientEndsAt,
                'title' => $data->title,
            ]);

            try {
                $booking->save();
            } catch (QueryException $exception) {
                if ($this->isOverlapConflict($exception)) {
                    throw new BookingConflictException(previous: $exception);
                }

                throw $exception;
            }

            $this->logger->info('Booking created', [
                'booking_id' => $booking->id,
                'room_id' => $data->room_id,
                'user_id' => $user->id,
                'starts_at' => $startsAt->toIso8601String(),
                'client_starts_at' => $clientStartsAt->toIso8601String(),
            ]);

            return $booking;
        });
    }

    /**
     * Проверить корректность интервала бронирования.
     *
     * Интервал должен быть не менее 15 минут, не более 4 часов
     * и кратен 15 минутам (15, 30, 45, 60 и т.д.).
     *
     * @throws BookingInvalidIntervalException
     */
    private function ensureValidInterval(CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $durationSeconds = $endsAt->getTimestamp() - $startsAt->getTimestamp();
        $slotSeconds = self::SLOT_MINUTES * 60;

        $isValid = $durationSeconds >= self::MIN_DURATION_MINUTES * 60
            && $durationSeconds <= self::MAX_DURATION_MINUTES * 60
            && $durationSeconds % $slotSeconds === 0;

        if (! $isValid) {
            throw new BookingInvalidIntervalException;
        }
    }

    /**
     * Проверить, что бронирование находится в рабочих часах офиса комнаты.
     *
     * Ожидает время, уже приведённое к таймзоне комнаты (см. roomTimezone()).
     *
     * @throws BookingOutsideWorkingHoursException
     */
    private function ensureWorkingHours(CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        // Конец в 20:00 допустим, но не позже
        $endWithinLimit = $endsAt->hour < self::WORKING_HOURS_END
            || ($endsAt->hour === self::WORKING_HOURS_END && $endsAt->minute === 0 && $endsAt->second === 0);

        if ($startsAt->hour < self::WORKING_HOURS_START || ! $endWithinLimit) {
            throw new BookingOutsideWorkingHoursException;
        }
    }

    /**
     * Таймзона комнаты: берётся из её офиса, иначе — таймзона приложения.
     */
    private function roomTimezone(Room $room): string
    {
        return $room->office?->timezone ?? config('app.timezone', 'UTC');
    }

    /**
     * Проверить, что ошибка БД — конфликт ограничения пересечения бронирований.
     *
     * Ограничение-исключение (EXCLUDE USING gist) при нарушении возвращает
     * SQLSTATE 23P01; уникальность — 23505. Дополнительно проверяем имя
     * ограничения в тексте сообщения.
     */
    private function isOverlapConflict(QueryException $exception): bool
    {
        if (in_array($exception->getCode(), self::CONFLICT_SQL_STATES, true)) {
            return true;
        }

        return str_contains($exception->getMessage(), 'bookings_no_overlap');
    }
}

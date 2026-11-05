<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Исключение при конфликте бронирования (комната занята).
 *
 * Бросается, когда ограничение БД (EXCLUDE) не позволяет создать бронирование,
 * пересекающее существующее по времени для той же комнаты.
 */
final class BookingConflictException extends Exception
{
    public function __construct(
        string $message = 'Комната занята на выбранное время',
        int $code = 409,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Исключение при попытке создать бронирование вне рабочего времени.
 */
final class BookingOutsideWorkingHoursException extends Exception
{
    public function __construct(
        string $message = 'Бронирование возможно только в рабочее время (09:00–20:00)',
        int $code = 422,
    ) {
        parent::__construct($message, $code);
    }
}
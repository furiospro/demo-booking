<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Исключение при некорректном интервале бронирования.
 *
 * Бросается, когда длительность бронирования меньше 15 минут,
 * больше 4 часов или не кратна 15 минутам.
 */
final class BookingInvalidIntervalException extends Exception
{
    public function __construct(
        string $message = 'Интервал бронирования должен быть от 15 минут до 4 часов и кратен 15 минутам',
        int $code = 422,
    ) {
        parent::__construct($message, $code);
    }
}
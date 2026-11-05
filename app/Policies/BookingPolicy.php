<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Политика доступа к бронированиям.
 */
class BookingPolicy
{
    /** Сообщение об отказе в доступе. */
    public const string DELETE_DENIED_MESSAGE = 'Удалить бронирование может только его автор';

    /**
     * Удалить бронирование может только пользователь, который его создал.
     */
    public function delete(User $user, Booking $booking): Response
    {
        return $booking->user_id === $user->id
            ? Response::allow()
            : Response::deny(self::DELETE_DENIED_MESSAGE);
    }
}

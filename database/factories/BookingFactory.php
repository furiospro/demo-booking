<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()->addDay()->setTime(10, 0);
        $endsAt = $startsAt->copy()->addHour();

        return [
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'client_starts_at' => $startsAt->copy(),
            'client_ends_at' => $endsAt->copy(),
            'title' => fake()->sentence(3),
        ];
    }
}

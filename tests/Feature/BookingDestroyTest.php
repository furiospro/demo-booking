<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Office;
use App\Models\Room;
use App\Models\User;
use App\Policies\BookingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingDestroyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $office = Office::factory()->create(['timezone' => 'UTC']);
        $this->room = Room::factory()->for($office)->create();
    }

    public function test_requires_authentication(): void
    {
        $booking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
        ]);

        $this->deleteJson('/api/bookings/' . $booking->id)->assertUnauthorized();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_author_can_delete_own_booking(): void
    {
        Sanctum::actingAs($this->user);

        $booking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
        ]);

        $this->deleteJson('/api/bookings/' . $booking->id)->assertNoContent();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_user_cannot_delete_booking_created_by_another_user(): void
    {
        $otherUser = User::factory()->create();

        Sanctum::actingAs($otherUser);

        $booking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
        ]);

        $this->deleteJson('/api/bookings/' . $booking->id)
            ->assertForbidden()
            ->assertJsonPath('message', BookingPolicy::DELETE_DENIED_MESSAGE);

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_returns_not_found_for_nonexistent_booking(): void
    {
        Sanctum::actingAs($this->user);

        $this->deleteJson('/api/bookings/999999')->assertNotFound();
    }

    public function test_deleting_one_booking_does_not_affect_other_bookings_of_the_same_user(): void
    {
        Sanctum::actingAs($this->user);

        $first = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'starts_at' => '2026-11-06 10:00:00',
            'ends_at' => '2026-11-06 11:00:00',
        ]);

        $second = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'starts_at' => '2026-11-06 11:00:00',
            'ends_at' => '2026-11-06 12:00:00',
        ]);

        $this->deleteJson('/api/bookings/' . $first->id)->assertNoContent();

        $this->assertDatabaseMissing('bookings', ['id' => $first->id]);
        $this->assertDatabaseHas('bookings', ['id' => $second->id]);
    }
}

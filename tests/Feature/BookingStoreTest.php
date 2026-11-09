<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Office;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingStoreTest extends TestCase
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
        $this->postJson('/api/bookings')->assertUnauthorized();
    }

    public function test_creates_booking_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T10:00:00+00:00',
            'ends_at' => '2026-11-06T11:00:00+00:00',
            'title' => 'Встреча команды',
        ]);

        $response->assertCreated()
            ->assertJsonPath('room_id', $this->room->id)
            ->assertJsonPath('user_id', $this->user->id)
            ->assertJsonPath('title', 'Встреча команды');

        $this->assertDatabaseHas('bookings', [
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'title' => 'Встреча команды',
        ]);
    }

    public function test_rejects_booking_outside_working_hours_before(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T08:00:00+00:00',
            'ends_at' => '2026-11-06T09:00:00+00:00',
            'title' => 'Ранняя встреча',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error', 'Бронирование возможно только в рабочее время (09:00–20:00)');
    }

    public function test_rejects_booking_outside_working_hours_after(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T19:00:00+00:00',
            'ends_at' => '2026-11-06T21:00:00+00:00',
            'title' => 'Поздняя встреча',
        ]);

        $response->assertUnprocessable();
    }

    public function test_allows_booking_ending_at_20_00(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T19:00:00+00:00',
            'ends_at' => '2026-11-06T20:00:00+00:00',
            'title' => 'Вечерняя встреча',
        ]);

        $response->assertCreated();
    }

    public function test_rejects_conflicting_booking(): void
    {
        Sanctum::actingAs($this->user);

        Booking::factory()->create([
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T10:00:00+00:00',
            'ends_at' => '2026-11-06T11:00:00+00:00',
        ]);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T10:30:00+00:00',
            'ends_at' => '2026-11-06T11:30:00+00:00',
            'title' => 'Конфликтующая встреча',
        ]);

        $response->assertConflict()
            ->assertJsonPath('error', 'Комната занята на выбранное время');
    }

    public function test_allows_adjacent_bookings(): void
    {
        Sanctum::actingAs($this->user);

        Booking::factory()->create([
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T10:00:00+00:00',
            'ends_at' => '2026-11-06T11:00:00+00:00',
        ]);

        // Бронирование сразу после 11:00-12:00 допустимо
        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T11:00:00+00:00',
            'ends_at' => '2026-11-06T12:00:00+00:00',
            'title' => 'Следующая встреча',
        ]);

        $response->assertCreated();
    }

    public function test_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', []);

        $response->assertUnprocessable();
    }

    public function test_validates_room_exists(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => 99999,
            'starts_at' => '2026-11-06T10:00:00+00:00',
            'ends_at' => '2026-11-06T11:00:00+00:00',
            'title' => 'Встреча',
        ]);

        $response->assertUnprocessable();
    }

    public function test_validates_date_format(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06 10:00:00', // Неверный формат (без T и таймзоны)
            'ends_at' => '2026-11-06T11:00:00+00:00',
            'title' => 'Встреча',
        ]);

        $response->assertUnprocessable();
    }

    public function test_allows_minimal_15_minute_booking(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T10:00:00+00:00',
            'ends_at' => '2026-11-06T10:15:00+00:00',
            'title' => 'Короткая встреча',
        ]);

        $response->assertCreated();
    }

    public function test_allows_maximum_4_hour_booking(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T10:00:00+00:00',
            'ends_at' => '2026-11-06T14:00:00+00:00',
            'title' => 'Длинная встреча',
        ]);

        $response->assertCreated();
    }

    public function test_rejects_booking_shorter_than_15_minutes(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T10:00:00+00:00',
            'ends_at' => '2026-11-06T10:10:00+00:00',
            'title' => 'Слишком короткая встреча',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error', 'Интервал бронирования должен быть от 15 минут до 4 часов и кратен 15 минутам');
    }

    public function test_rejects_booking_longer_than_4_hours(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T10:00:00+00:00',
            'ends_at' => '2026-11-06T15:00:00+00:00',
            'title' => 'Слишком длинная встреча',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error', 'Интервал бронирования должен быть от 15 минут до 4 часов и кратен 15 минутам');
    }

    public function test_rejects_booking_not_multiple_of_15_minutes(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T10:00:00+00:00',
            'ends_at' => '2026-11-06T10:20:00+00:00',
            'title' => 'Некратная встреча',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error', 'Интервал бронирования должен быть от 15 минут до 4 часов и кратен 15 минутам');
    }

    public function test_allows_booking_with_30_and_45_minute_intervals(): void
    {
        Sanctum::actingAs($this->user);

        $response30 = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T10:00:00+00:00',
            'ends_at' => '2026-11-06T10:30:00+00:00',
            'title' => 'Встреча на 30 минут',
        ]);

        $response30->assertCreated();

        $response45 = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T11:00:00+00:00',
            'ends_at' => '2026-11-06T11:45:00+00:00',
            'title' => 'Встреча на 45 минут',
        ]);

        $response45->assertCreated();
    }

    public function test_rejects_booking_with_ends_before_starts(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T11:00:00+00:00',
            'ends_at' => '2026-11-06T10:00:00+00:00',
            'title' => 'Некорректный интервал',
        ]);

        // Случай перехватывает правило DateGT('starts_at') у поля ends_at в StoreBookingData,
        // поэтому возвращается стандартная ошибка валидации, а не сообщение BookingInvalidIntervalException.
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_rejects_booking_with_ends_at_equal_to_starts_at(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'starts_at' => '2026-11-06T11:00:00+00:00',
            'ends_at' => '2026-11-06T11:00:00+00:00',
            'title' => 'Нулевой интервал',
        ]);

        // Правило after строгое: равные даты тоже отклоняются.
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_rejects_booking_when_client_ends_at_instant_is_before_client_starts_at(): void
    {
        // Комната в Новосибирске (+07:00), клиент присылает время в разных таймзонах.
        // По «настенным» цифрам конец (10:00) позже начала (09:00), но как моменты времени
        // 10:00+03:00 == 07:00 UTC — раньше, чем 09:00+00:00, то есть client_ends_at < client_starts_at.
        $office = Office::factory()->novosibirsk()->create();
        $room = Room::factory()->for($office)->create();

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-11-06T09:00:00+00:00',
            'ends_at' => '2026-11-06T10:00:00+03:00',
            'title' => 'Конец раньше начала',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_saves_starts_at_in_room_timezone_and_client_starts_at_in_client_timezone(): void
    {
        // Комната в Новосибирске (+07:00), клиент присылает время в Москве (+03:00).
        $office = Office::factory()->novosibirsk()->create();
        $room = Room::factory()->for($office)->create();

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-11-06T10:00:00+03:00',
            'ends_at' => '2026-11-06T11:00:00+03:00',
            'title' => 'Встреча из Москвы',
        ]);

        $response->assertCreated();

        // 10:00+03:00 == 07:00 UTC == 14:00 в Новосибирске.
        $this->assertDatabaseHas('bookings', [
            'id' => $response->json('id'),
            'starts_at' => '2026-11-06 07:00:00',
            'ends_at' => '2026-11-06 08:00:00',
            'client_timezone' => '+03:00',
        ]);

        $booking = Booking::query()->findOrFail($response->json('id'));
        
        $this->assertSame('2026-11-06T07:00:00+00:00', $booking->starts_at->format(\DateTime::ATOM));
        $this->assertSame('2026-11-06T08:00:00+00:00', $booking->ends_at->format(\DateTime::ATOM));
    }

    public function test_checks_working_hours_in_room_timezone(): void
    {
        // Офис в Новосибирске (+07:00): 15:30 UTC — это 22:30 местного времени,
        // то есть за пределами рабочих часов комнаты, хотя в UTC выглядит рабочим временем.
        $office = Office::factory()->novosibirsk()->create();
        $room = Room::factory()->for($office)->create();

        Sanctum::actingAs($this->user);

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-11-06T15:30:00+00:00',
            'ends_at' => '2026-11-06T16:30:00+00:00',
            'title' => 'Поздняя встреча',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_detects_conflict_for_same_room_time_sent_in_different_client_timezone(): void
    {
        // Комната в Новосибирске (+07:00).
        $office = Office::factory()->novosibirsk()->create();
        $room = Room::factory()->for($office)->create();

        Sanctum::actingAs($this->user);

        // 10:00+03:00 == 14:00 в Новосибирске.
        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-11-06T10:00:00+03:00',
            'ends_at' => '2026-11-06T11:00:00+03:00',
            'title' => 'Первая встреча',
        ])->assertCreated();

        // 07:30+00:00 == те же 14:30 в Новосибирске — пересекается с первой.
        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-11-06T07:30:00+00:00',
            'ends_at' => '2026-11-06T08:00:00+00:00',
            'title' => 'Пересекающаяся встреча',
        ])->assertConflict();
    }
}

<?php

namespace App\Http\Controllers;

use App\DTOs\StoreBookingData;
use App\Exceptions\BookingConflictException;
use App\Exceptions\BookingInvalidIntervalException;
use App\Exceptions\BookingOutsideWorkingHoursException;
use App\Logging\BookingLoggerInterface;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly BookingLoggerInterface $logger,
    ) {}

    public function index(Request $request)
    {
        return Booking::where('user_id', $request->user()->id)
            ->orderByRaw('starts_at DESC')
            ->paginate(20);
    }

    public function store(Request $request, Authenticatable $user): JsonResponse
    {
        $data = StoreBookingData::fromRequest($request);
        
        try {
            $booking = $this->bookingService->createBooking($data, $user);
        } catch (\Throwable $throwable) {
            if ($throwable instanceof BookingConflictException
                || $throwable instanceof BookingOutsideWorkingHoursException
                || $throwable instanceof BookingInvalidIntervalException) {
                throw $throwable;
            }

            $this->logger->error('Error booking creating', [
                'error_msg' => $throwable->getMessage(),
                'error_trace' => $throwable->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }

        return response()->json($booking, 201);
    }

    public function destroy(int $id, Authenticatable $user): Response
    {
        $booking = Booking::query()->findOrFail($id);
        
        // Удалить бронирование может только тот пользователь, который его создал.
        Gate::forUser($user)->authorize('delete', $booking);
        
        $this->logger->info('Удаление записи ' . $booking->id, [
            'user_id' => $user->getAuthIdentifier()
        ]);

        $booking->delete();

        return response()->noContent();
    }

    public function today(Room $room)
    {
        return $room->bookings()
            ->whereDate('starts_at', today())
            ->get()
            ->map(fn ($b) => [
                'time' => $b->starts_at . ' – ' . $b->ends_at,
                'author' => $b->user->name,
            ]);
    }
}

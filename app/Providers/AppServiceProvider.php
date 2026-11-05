<?php

namespace App\Providers;

use App\Logging\BookingLogger;
use App\Logging\BookingLoggerInterface;
use App\Models\Booking;
use App\Policies\BookingPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Логгер канала 'bookings': LogManager внедряется контейнером,
        // канал и объединение общего/собственного контекста — внутри BookingLogger.
        $this->app->bind(BookingLoggerInterface::class, BookingLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Booking::class, BookingPolicy::class);
    }
}

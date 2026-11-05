<?php

declare(strict_types=1);

namespace App\DTOs;

use App\DTOs\Attributes\DateGT;
use App\DTOs\Attributes\DateISO;
use App\DTOs\Attributes\Exists;
use App\DTOs\Attributes\IntegerType;
use App\DTOs\Attributes\MaxLength;
use App\DTOs\Attributes\Required;
use App\DTOs\Attributes\StringType;
use Carbon\CarbonImmutable;

/**
 * DTO для создания бронирования.
 */
final class StoreBookingData extends ValidatedDTO
{
    public function __construct(
        #[Required]
        #[IntegerType]
        #[Exists(table: 'rooms')]
        public readonly int $room_id,
        #[Required]
        #[DateISO]
        public readonly string $starts_at,
        #[Required]
        #[DateISO]
        #[DateGT('starts_at')]
        public readonly string $ends_at,
        #[Required]
        #[StringType]
        #[MaxLength(255)]
        public readonly string $title,
    ) {}

    /**
     * Получить время начала как Carbon.
     */
    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->starts_at);
    }

    /**
     * Получить время окончания как Carbon.
     */
    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->ends_at);
    }
}
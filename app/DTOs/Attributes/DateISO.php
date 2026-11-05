<?php

declare(strict_types=1);

namespace App\DTOs\Attributes;

use App\Rules\Iso8601Date;
use Attribute;

/**
 * Проверка даты в формате ISO 8601 (например, 2026-01-15T10:00:00+07:00).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class DateISO extends ValidationRule
{
    public function toRule(): mixed
    {
        return new Iso8601Date;
    }
}
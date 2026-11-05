<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правило валидации даты в формате ISO 8601.
 *
 * Принимает даты вида 2026-01-15T10:00:00+07:00, 2026-01-15T10:00Z.
 */
final class Iso8601Date implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute field must be a valid ISO 8601 date.')->translate();

            return;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:\d{2})?$/', $value)) {
            $fail('The :attribute field must be a valid ISO 8601 date.')->translate();

            return;
        }

        try {
            new DateTimeImmutable($value);
        } catch (\Throwable) {
            $fail('The :attribute field must be a valid ISO 8601 date.')->translate();
        }
    }
}
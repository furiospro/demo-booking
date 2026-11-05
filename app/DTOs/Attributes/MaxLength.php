<?php

declare(strict_types=1);

namespace App\DTOs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class MaxLength extends ValidationRule
{
    public function __construct(
        public int $max,
    ) {}

    public function toRule(): string
    {
        return "max:{$this->max}";
    }
}
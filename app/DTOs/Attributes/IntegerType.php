<?php

declare(strict_types=1);

namespace App\DTOs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class IntegerType extends ValidationRule
{
    public function toRule(): string
    {
        return 'integer';
    }
}
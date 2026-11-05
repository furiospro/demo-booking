<?php

declare(strict_types=1);

namespace App\DTOs\Attributes;

use Attribute;
use Illuminate\Validation\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Exists extends ValidationRule
{
    public function __construct(
        public string $table,
        public string $column = 'id',
    ) {}

    public function toRule(): mixed
    {
        return Rule::exists($this->table, $this->column);
    }
}
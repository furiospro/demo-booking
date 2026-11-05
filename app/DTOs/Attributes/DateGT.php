<?php

namespace App\DTOs\Attributes;

use Attribute;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Date;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DateGT extends ValidationRule
{
    public function __construct(private readonly string $field)
    {
    }
    
    public function toRule(): Date
    {
        return Rule::date()->after($this->field);
    }
}
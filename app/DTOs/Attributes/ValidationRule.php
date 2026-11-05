<?php

declare(strict_types=1);

namespace App\DTOs\Attributes;

use Attribute;

/**
 * Базовый атрибут правила валидации для свойств DTO.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
abstract class ValidationRule
{
    /**
     * Возвращает правило валидации в формате, совместимом с Illuminate\Validation.
     */
    abstract public function toRule(): mixed;
}
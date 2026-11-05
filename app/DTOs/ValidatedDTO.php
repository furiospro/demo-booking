<?php

declare(strict_types=1);

namespace App\DTOs;

use App\DTOs\Attributes\ValidationRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Базовый класс DTO с валидацией на основе нативных атрибутов.
 */
abstract class ValidatedDTO
{
    /**
     * Создать DTO из данных запроса.
     */
    public static function fromRequest(Request $request): static
    {
        return static::fromArray(static::extractRequestData($request));
    }

    /**
     * Создать DTO из массива данных.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function fromArray(array $data): static
    {
        // Сначала валидируем данные ДО конструирования объекта
        static::validateData($data);

        $reflection = new ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $data)) {
                $arguments[$name] = static::castValue($parameter->getType(), $data[$name]);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[$name] = $parameter->getDefaultValue();
            } else {
                $arguments[$name] = null;
            }
        }

        return $reflection->newInstanceArgs($arguments);
    }

    /**
     * Проверить валидность данных и выбросить исключение при ошибках.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validateData(array $data): void
    {
        Validator::validate($data, static::rules());
    }

    /**
     * Проверить валидность данных и выбросить исключение при ошибках.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(): void
    {
        Validator::validate($this->toArray(), static::rules());
    }

    /**
     * Собрать правила валидации из атрибутов свойств.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        $reflection = new ReflectionClass(static::class);
        $rules = [];

        foreach ($reflection->getProperties() as $property) {
            $propertyRules = [];

            foreach ($property->getAttributes(ValidationRule::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $propertyRules[] = $attribute->newInstance()->toRule();
            }

            if ($propertyRules !== []) {
                $rules[$property->getName()] = $propertyRules;
            }
        }

        return $rules;
    }

    /**
     * Преобразовать DTO в массив.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $reflection = new ReflectionClass(static::class);
        $data = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isInitialized($this)) {
                $data[$property->getName()] = $property->getValue($this);
            }
        }

        return $data;
    }

    /**
     * Извлечь данные запроса, соответствующие параметрам конструктора.
     *
     * @return array<string, mixed>
     */
    protected static function extractRequestData(Request $request): array
    {
        $reflection = new ReflectionClass(static::class);
        $data = [];

        foreach ($reflection->getConstructor()->getParameters() as $parameter) {
            $name = $parameter->getName();

            if ($request->has($name)) {
                $data[$name] = $request->input($name);
            }
        }

        return $data;
    }

    /**
     * Привести значение к типу параметра конструктора.
     */
    protected static function castValue(?\ReflectionType $type, mixed $value): mixed
    {
        if (! $type instanceof ReflectionNamedType || ! $type->isBuiltin()) {
            return $value;
        }

        if ($value === null && $type->allowsNull()) {
            return null;
        }

        return match ($type->getName()) {
            'int' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'bool' => is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value,
            'string' => is_scalar($value) ? (string) $value : $value,
            'array' => is_array($value) ? $value : $value,
            default => $value,
        };
    }
}
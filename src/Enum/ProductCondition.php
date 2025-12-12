<?php

namespace Mattiasgeniar\ProductInfoFetcher\Enum;

enum ProductCondition: string
{
    case New = 'New';
    case Used = 'Used';
    case Refurbished = 'Refurbished';
    case Damaged = 'Damaged';

    public static function fromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower($value);

        return match (true) {
            str_contains($value, 'new') => self::New,
            str_contains($value, 'refurbished') => self::Refurbished,
            str_contains($value, 'used') => self::Used,
            str_contains($value, 'damaged') => self::Damaged,
            default => null,
        };
    }
}

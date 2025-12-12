<?php

namespace Mattiasgeniar\ProductInfoFetcher\Enum;

enum ProductAvailability: string
{
    case InStock = 'InStock';
    case OutOfStock = 'OutOfStock';
    case PreOrder = 'PreOrder';
    case BackOrder = 'BackOrder';
    case Discontinued = 'Discontinued';

    public static function fromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower($value);

        return match (true) {
            str_contains($value, 'instock') => self::InStock,
            str_contains($value, 'outofstock') => self::OutOfStock,
            str_contains($value, 'preorder') => self::PreOrder,
            str_contains($value, 'backorder') => self::BackOrder,
            str_contains($value, 'discontinued') => self::Discontinued,
            $value === 'in stock' => self::InStock,
            $value === 'out of stock' || $value === 'oos' => self::OutOfStock,
            default => null,
        };
    }
}

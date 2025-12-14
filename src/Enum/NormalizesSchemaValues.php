<?php

namespace Mattiasgeniar\ProductInfoFetcher\Enum;

trait NormalizesSchemaValues
{
    private static function normalizeSchemaValue(string $value): string
    {
        $value = str_replace(['http://schema.org/', 'https://schema.org/'], '', $value);

        return strtolower($value);
    }
}

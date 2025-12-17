<?php

namespace GiveTwice\ProductInfoFetcher\Enum;

trait NormalizesSchemaValues
{
    private static function normalizeSchemaValue(string $value): string
    {
        return strtolower(self::stripSchemaOrgPrefix($value));
    }

    public static function stripSchemaOrgPrefix(string $value): string
    {
        return str_replace(['http://schema.org/', 'https://schema.org/'], '', $value);
    }
}

<?php

namespace GiveTwice\ProductInfoFetcher\Parsers;

trait NormalizesPrices
{
    private function normalizePriceToCents(mixed $price): int
    {
        if (is_int($price)) {
            return $price * 100;
        }

        if (is_float($price)) {
            return (int) round($price * 100);
        }

        $priceString = (string) $price;
        $priceString = preg_replace('/[^\d.,]/', '', $priceString);

        $lastDot = strrpos($priceString, '.');
        $lastComma = strrpos($priceString, ',');

        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $priceString = str_replace('.', '', $priceString);
            $priceString = str_replace(',', '.', $priceString);
        } else {
            $priceString = str_replace(',', '', $priceString);
        }

        return (int) round((float) $priceString * 100);
    }
}

<?php

namespace Mattiasgeniar\ProductInfoFetcher\Parsers;

trait EscapesXPath
{
    private function escapeXPathString(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        if (! str_contains($value, '"')) {
            return "\"{$value}\"";
        }

        return "concat('".str_replace("'", "',\"'\",'", $value)."')";
    }
}

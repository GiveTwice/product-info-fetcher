<?php

namespace GiveTwice\ProductInfoFetcher\Parsers;

use GiveTwice\ProductInfoFetcher\DataTransferObjects\ProductInfo;

interface ParserInterface
{
    public function parse(): ProductInfo;
}

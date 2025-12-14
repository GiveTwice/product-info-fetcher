<?php

namespace Mattiasgeniar\ProductInfoFetcher\Parsers;

use Mattiasgeniar\ProductInfoFetcher\DataTransferObjects\ProductInfo;

interface ParserInterface
{
    public function parse(): ProductInfo;
}

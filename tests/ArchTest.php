<?php

use Mattiasgeniar\ProductInfoFetcher\Parsers\ParserInterface;

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

it('ensures all parsers implement ParserInterface', function () {
    expect(Mattiasgeniar\ProductInfoFetcher\Parsers\JsonLdParser::class)
        ->toImplement(ParserInterface::class);

    expect(Mattiasgeniar\ProductInfoFetcher\Parsers\MetaTagParser::class)
        ->toImplement(ParserInterface::class);
});

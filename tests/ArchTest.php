<?php

use Mattiasgeniar\ProductInfoFetcher\Parsers\ParserInterface;

arch('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

arch('all parsers implement ParserInterface')
    ->expect('Mattiasgeniar\ProductInfoFetcher\Parsers')
    ->classes()
    ->toImplement(ParserInterface::class);

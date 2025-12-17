<?php

use GiveTwice\ProductInfoFetcher\Parsers\ParserInterface;

arch('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

arch('all parsers implement ParserInterface')
    ->expect('GiveTwice\ProductInfoFetcher\Parsers')
    ->classes()
    ->toImplement(ParserInterface::class);

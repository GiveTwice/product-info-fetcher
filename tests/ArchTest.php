<?php

use Mattiasgeniar\ProductInfoFetcher\Parsers\ParserInterface;

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

it('ensures all parsers implement ParserInterface', function () {
    $parserFiles = glob(__DIR__.'/../src/Parsers/*Parser.php');

    foreach ($parserFiles as $file) {
        $className = 'Mattiasgeniar\\ProductInfoFetcher\\Parsers\\'.basename($file, '.php');

        expect($className)->toImplement(ParserInterface::class);
    }
});

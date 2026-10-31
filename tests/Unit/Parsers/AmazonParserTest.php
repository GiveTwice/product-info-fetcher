<?php

use GiveTwice\ProductInfoFetcher\Enum\ProductAvailability;
use GiveTwice\ProductInfoFetcher\Parsers\Shops\AmazonParser;

it('reads the main Amazon offer and image without taking the list or recommendation price', function ($domain) {
    $result = (new AmazonParser(getFixture('Amazon/sale.html'), "https://www.{$domain}/dp/B09Y2MYL5C"))->parse();
    expect($result->name)->toBe('Sony WH-1000XM5 Noir')->and($result->priceInCents)->toBe(24995)
        ->and($result->priceCurrency)->toBe('EUR')->and($result->imageUrl)->toEndWith('/main.jpg')
        ->and($result->description)->toContain('réduction');
})->with(['amazon.com.be', 'amazon.nl', 'amazon.de', 'amazon.fr']);

it('keeps unavailable products without borrowing a recommendation price', function () {
    $result = (new AmazonParser(getFixture('Amazon/unavailable.html'), 'https://www.amazon.nl/dp/B09Y2MYL5C'))->parse();
    expect($result->availability)->toBe(ProductAvailability::OutOfStock)->and($result->priceInCents)->toBeNull()
        ->and($result->imageUrl)->toEndWith('/silver.jpg');
});

it('does not apply Amazon selectors to other hosts', function () {
    $result = (new AmazonParser(getFixture('Amazon/sale.html'), 'https://amazon.de.example.com/dp/test'))->parse();
    expect($result->name)->toBeNull();
});

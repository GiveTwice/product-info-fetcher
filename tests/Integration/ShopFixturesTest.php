<?php

use GiveTwice\ProductInfoFetcher\Enum\FetchOutcome;
use GiveTwice\ProductInfoFetcher\Enum\ProductAvailability;
use GiveTwice\ProductInfoFetcher\ProductInfoFetcher;
use GuzzleHttp\Psr7\Response;

$captures = json_decode(getFixture('Shops/manifest.json'), true);

dataset('shop captures', array_map(fn ($id, $capture) => [$id, $capture], array_keys($captures), array_values($captures)));

it('replays real shop captures without network access', function ($id, $capture) {
    $history = [];
    $result = (new ProductInfoFetcher($capture['url']))
        ->setClient(createMockClient($history, [new Response($capture['status'], [], getFixture('Shops/'.$id.'.html'))]))->fetchResult();

    expect($result->outcome->value)->toBe($capture['outcome'])->and($result->httpStatus)->toBe($capture['status']);
    if ($result->outcome === FetchOutcome::Success) {
        expect($result->name)->toBe($capture['name'])->and($result->priceInCents)->toBe($capture['price'])
            ->and($result->priceCurrency)->toBe('EUR')->and($result->imageUrl)->toBe($capture['image']);
    } else {
        expect($result->hasProductData())->toBeFalse()->and($result->name)->toBeNull();
    }
})->with('shop captures');

it('preserves SoldOut availability on the real unavailable Coolblue page', function () {
    $result = (new ProductInfoFetcher('https://www.coolblue.be/nl/product/871088/apple-iphone-12-64gb-blauw.html'))
        ->setHtml(getFixture('Shops/coolblue-unavailable.html'))->parse();
    expect($result->name)->toBe('Apple iPhone 12 64GB Blauw')
        ->and($result->availability)->toBe(ProductAvailability::OutOfStock);
});

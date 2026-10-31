<?php

use GiveTwice\ProductInfoFetcher\Enum\ProductAvailability;
use GiveTwice\ProductInfoFetcher\Parsers\JsonLdParser;
use GiveTwice\ProductInfoFetcher\ProductInfoFetcher;

it('finds products recursively and reads aggregate or specified prices', function ($wrapper, $offers) {
    $product = ['@type' => 'Product', 'name' => 'Écouteurs', 'offers' => $offers];
    $data = match ($wrapper) {
        'array' => [['@type' => 'Organization'], $product],
        'nested' => ['@graph' => [['mainEntity' => ['@graph' => [$product]]]]],
        'main' => ['@type' => 'WebPage', 'mainEntity' => $product],
    };
    $result = (new JsonLdParser('<script type="application/ld+json">'.json_encode($data).'</script>'))->parse();
    expect($result->name)->toBe('Écouteurs')->and($result->priceInCents)->toBe(129995)->and($result->priceCurrency)->toBe('EUR');
})->with(['array', 'nested', 'main'])->with([
    [['@type' => 'AggregateOffer', 'lowPrice' => '1.299,95', 'priceCurrency' => 'EUR']],
    [['@type' => 'Offer', 'priceSpecification' => ['price' => '1299.95', 'priceCurrency' => 'EUR']]],
    [[['@type' => 'Offer', 'priceSpecification' => [['price' => '1299,95', 'priceCurrency' => 'EUR']]]]],
]);

it('inherits group metadata while preserving selected unavailable variant identity', function () {
    $data = ['@type' => 'ProductGroup', 'name' => 'Parent', 'description' => 'Shared description', 'brand' => ['name' => 'Sony'], 'image' => 'https://example.com/parent.jpg', 'offers' => ['price' => 999], 'hasVariant' => [
        ['@type' => 'Product', 'name' => 'Black', 'url' => 'https://example.com/black', 'sku' => 'BLACK', 'offers' => ['price' => '99,95', 'priceCurrency' => 'EUR', 'availability' => 'InStock']],
        ['@type' => 'Product', 'url' => 'https://example.com/white', 'sku' => 'WHITE', 'offers' => ['availability' => 'OutOfStock']],
    ]];
    $result = (new JsonLdParser('<script type="application/ld+json">'.json_encode($data).'</script>', 'https://example.com/white?tracking=1'))->parse();
    expect($result->name)->toBe('Parent')->and($result->brand)->toBe('Sony')->and($result->sku)->toBe('WHITE')
        ->and($result->description)->toBe('Shared description')->and($result->imageUrl)->toBe('https://example.com/parent.jpg')
        ->and($result->availability)->toBe(ProductAvailability::OutOfStock)->and($result->priceInCents)->toBeNull();
});

it('fills missing structured fields from og prices even with a complete name and description', function () {
    $result = (new ProductInfoFetcher)->setHtml('<script type="application/ld+json">{"@type":"Product","name":"Product","description":"Description"}</script><meta property="og:price:amount" content="49,99"><meta property="og:price:currency" content="EUR">')->parse();
    expect($result->priceInCents)->toBe(4999)->and($result->priceCurrency)->toBe('EUR');
});

it('ignores scalar JSON-LD and malformed schema type entries', function () {
    $result = (new JsonLdParser('<script type="application/ld+json">"not an object"</script><script type="application/ld+json">{"@type":[null,{},"Product"],"name":"Usable product"}</script>'))->parse();
    expect($result->name)->toBe('Usable product');
});

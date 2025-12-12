<?php

use Mattiasgeniar\ProductInfoFetcher\Parsers\JsonLdParser;

dataset('jsonld_html', [
    'complete product' => [
        'fixture' => 'JsonLd/complete-product.html',
        'expected' => [
            'name' => 'iPhone 15 Pro',
            'description' => 'The latest iPhone with A17 Pro chip',
            'url' => 'https://apple.com/iphone-15-pro',
            'priceInCents' => 99900,
            'priceCurrency' => 'USD',
            'imageUrl' => 'https://apple.com/images/iphone-15-pro.jpg',
        ],
    ],
    'partial product' => [
        'fixture' => 'JsonLd/partial-product.html',
        'expected' => [
            'name' => 'Simple Product',
            'description' => null,
            'url' => null,
            'priceInCents' => 4999,
            'priceCurrency' => null,
            'imageUrl' => null,
        ],
    ],
    'product with image array' => [
        'fixture' => 'JsonLd/product-with-image-array.html',
        'expected' => [
            'name' => 'Product with multiple images',
            'description' => 'A product',
            'url' => null,
            'priceInCents' => 2999,
            'priceCurrency' => 'EUR',
            'imageUrl' => 'https://example.com/image1.jpg',
        ],
    ],
    'product with ImageObject' => [
        'fixture' => 'JsonLd/product-with-image-object.html',
        'expected' => [
            'name' => 'Product with ImageObject',
            'description' => 'Has structured image',
            'url' => null,
            'priceInCents' => 1500,
            'priceCurrency' => 'GBP',
            'imageUrl' => 'https://example.com/structured-image.jpg',
        ],
    ],
    'product in @graph' => [
        'fixture' => 'JsonLd/product-in-graph.html',
        'expected' => [
            'name' => 'Graph Product',
            'description' => 'Found in graph',
            'url' => 'https://example.com/graph-product',
            'priceInCents' => 19900,
            'priceCurrency' => 'USD',
            'imageUrl' => 'https://example.com/graph.jpg',
        ],
    ],
    'event - not a product' => [
        'fixture' => 'JsonLd/event-not-product.html',
        'expected' => [
            'name' => null,
            'description' => null,
            'url' => null,
            'priceInCents' => null,
            'priceCurrency' => null,
            'imageUrl' => null,
        ],
    ],
    'no json-ld' => [
        'fixture' => 'JsonLd/no-jsonld.html',
        'expected' => [
            'name' => null,
            'description' => null,
            'url' => null,
            'priceInCents' => null,
            'priceCurrency' => null,
            'imageUrl' => null,
        ],
    ],
    'malformed json' => [
        'fixture' => 'JsonLd/malformed-json.html',
        'expected' => [
            'name' => null,
            'description' => null,
            'url' => null,
            'priceInCents' => null,
            'priceCurrency' => null,
            'imageUrl' => null,
        ],
    ],
]);

it('parses json-ld product data correctly', function (string $fixture, array $expected) {
    $result = (new JsonLdParser(getFixture($fixture)))->parse();

    expect($result->name)->toBe($expected['name'])
        ->and($result->description)->toBe($expected['description'])
        ->and($result->url)->toBe($expected['url'])
        ->and($result->priceInCents)->toBe($expected['priceInCents'])
        ->and($result->priceCurrency)->toBe($expected['priceCurrency'])
        ->and($result->imageUrl)->toBe($expected['imageUrl']);
})->with('jsonld_html');

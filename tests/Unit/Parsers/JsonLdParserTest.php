<?php

use Mattiasgeniar\ProductInfoFetcher\Enum\ProductAvailability;
use Mattiasgeniar\ProductInfoFetcher\Enum\ProductCondition;
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
            'brand' => 'Apple',
            'sku' => 'IPHONE15PRO-256-BLK',
            'gtin' => '0194253392200',
            'availability' => ProductAvailability::InStock,
            'condition' => ProductCondition::New,
            'rating' => 4.8,
            'reviewCount' => 1250,
        ],
    ],
    'product with full URL types' => [
        'fixture' => 'JsonLd/product-with-full-url-types.html',
        'expected' => [
            'name' => 'Windproof Winter Gloves',
            'description' => 'Premium cycling gloves for cold weather',
            'url' => 'https://example.com/gloves',
            'priceInCents' => 5000,
            'priceCurrency' => 'EUR',
            'imageUrl' => 'https://example.com/gloves.jpg',
            'brand' => 'GripGrab',
            'sku' => null,
            'gtin' => null,
            'availability' => ProductAvailability::InStock,
            'condition' => ProductCondition::New,
            'rating' => 4.5,
            'reviewCount' => 4,
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
            'brand' => null,
            'sku' => null,
            'gtin' => null,
            'availability' => null,
            'condition' => null,
            'rating' => null,
            'reviewCount' => null,
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
            'brand' => null,
            'sku' => null,
            'gtin' => null,
            'availability' => null,
            'condition' => null,
            'rating' => null,
            'reviewCount' => null,
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
            'brand' => null,
            'sku' => null,
            'gtin' => null,
            'availability' => null,
            'condition' => null,
            'rating' => null,
            'reviewCount' => null,
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
            'brand' => null,
            'sku' => null,
            'gtin' => null,
            'availability' => null,
            'condition' => null,
            'rating' => null,
            'reviewCount' => null,
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
            'brand' => null,
            'sku' => null,
            'gtin' => null,
            'availability' => null,
            'condition' => null,
            'rating' => null,
            'reviewCount' => null,
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
            'brand' => null,
            'sku' => null,
            'gtin' => null,
            'availability' => null,
            'condition' => null,
            'rating' => null,
            'reviewCount' => null,
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
            'brand' => null,
            'sku' => null,
            'gtin' => null,
            'availability' => null,
            'condition' => null,
            'rating' => null,
            'reviewCount' => null,
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
        ->and($result->imageUrl)->toBe($expected['imageUrl'])
        ->and($result->brand)->toBe($expected['brand'])
        ->and($result->sku)->toBe($expected['sku'])
        ->and($result->gtin)->toBe($expected['gtin'])
        ->and($result->availability)->toBe($expected['availability'])
        ->and($result->condition)->toBe($expected['condition'])
        ->and($result->rating)->toBe($expected['rating'])
        ->and($result->reviewCount)->toBe($expected['reviewCount']);
})->with('jsonld_html');

it('marks product as complete with core fields even without optional fields', function () {
    $html = <<<'HTML'
    <!DOCTYPE html>
    <html>
    <head>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Product",
            "name": "Basic Product",
            "description": "A product with only core fields",
            "offers": {
                "@type": "Offer",
                "price": "29.99",
                "priceCurrency": "EUR"
            }
        }
        </script>
    </head>
    <body></body>
    </html>
    HTML;

    $result = (new JsonLdParser($html))->parse();

    expect($result->isComplete())->toBeTrue()
        ->and($result->name)->toBe('Basic Product')
        ->and($result->description)->toBe('A product with only core fields')
        ->and($result->priceInCents)->toBe(2999)
        ->and($result->brand)->toBeNull()
        ->and($result->sku)->toBeNull()
        ->and($result->gtin)->toBeNull()
        ->and($result->availability)->toBeNull()
        ->and($result->condition)->toBeNull()
        ->and($result->rating)->toBeNull()
        ->and($result->reviewCount)->toBeNull();
});

it('extracts brand as string when not nested', function () {
    $html = <<<'HTML'
    <!DOCTYPE html>
    <html>
    <head>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Product",
            "name": "Product",
            "brand": "SimpleBrand",
            "offers": { "price": "10.00" }
        }
        </script>
    </head>
    <body></body>
    </html>
    HTML;

    $result = (new JsonLdParser($html))->parse();

    expect($result->brand)->toBe('SimpleBrand');
});

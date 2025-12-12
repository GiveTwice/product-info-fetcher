<?php

use Mattiasgeniar\ProductInfoFetcher\Parsers\MetaTagParser;

dataset('metatag_html', [
    'complete open graph' => [
        'fixture' => 'MetaTags/complete-open-graph.html',
        'expected' => [
            'name' => 'MacBook Pro 16-inch',
            'description' => 'The most powerful MacBook Pro ever',
            'url' => 'https://apple.com/macbook-pro',
            'price' => '2499.00',
            'imageUrl' => 'https://apple.com/images/macbook-pro.jpg',
        ],
    ],
    'twitter card fallback' => [
        'fixture' => 'MetaTags/twitter-card-fallback.html',
        'expected' => [
            'name' => 'Twitter Product Title',
            'description' => 'Description from Twitter card',
            'url' => null,
            'price' => null,
            'imageUrl' => 'https://example.com/twitter-image.jpg',
        ],
    ],
    'canonical url preferred over og:url' => [
        'fixture' => 'MetaTags/canonical-url.html',
        'expected' => [
            'name' => 'Product with Canonical',
            'description' => 'Has a canonical URL',
            'url' => 'https://example.com/canonical-url',
            'price' => null,
            'imageUrl' => 'https://example.com/image.jpg',
        ],
    ],
    'standard meta description fallback' => [
        'fixture' => 'MetaTags/standard-meta-description.html',
        'expected' => [
            'name' => 'Product Title',
            'description' => 'Standard meta description fallback',
            'url' => null,
            'price' => null,
            'imageUrl' => null,
        ],
    ],
    'og takes priority over twitter' => [
        'fixture' => 'MetaTags/mixed-og-and-twitter.html',
        'expected' => [
            'name' => 'OG Title Takes Priority',
            'description' => 'Twitter Description Used',
            'url' => 'https://example.com/og-url',
            'price' => null,
            'imageUrl' => 'https://example.com/og-image.jpg',
        ],
    ],
    'no meta tags' => [
        'fixture' => 'MetaTags/no-meta-tags.html',
        'expected' => [
            'name' => null,
            'description' => null,
            'url' => null,
            'price' => null,
            'imageUrl' => null,
        ],
    ],
    'partial data' => [
        'fixture' => 'MetaTags/partial-data.html',
        'expected' => [
            'name' => 'Only Title Present',
            'description' => null,
            'url' => null,
            'price' => null,
            'imageUrl' => 'https://example.com/only-image.jpg',
        ],
    ],
]);

it('parses meta tag data correctly', function (string $fixture, array $expected) {
    $result = (new MetaTagParser(getFixture($fixture)))->parse();

    expect($result->name)->toBe($expected['name'])
        ->and($result->description)->toBe($expected['description'])
        ->and($result->url)->toBe($expected['url'])
        ->and($result->price)->toBe($expected['price'])
        ->and($result->imageUrl)->toBe($expected['imageUrl']);
})->with('metatag_html');

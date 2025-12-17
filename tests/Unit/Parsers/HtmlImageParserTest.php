<?php

use GiveTwice\ProductInfoFetcher\Parsers\HtmlImageParser;

dataset('html_images', [
    'amazon landing image with data-old-hires' => [
        'fixture' => 'HtmlImages/amazon-landing-image.html',
        'expectedImageUrl' => 'https://m.media-amazon.com/images/I/51R-mI2x9iL._AC_SL1500_.jpg',
        'expectedAllImages' => [
            'https://m.media-amazon.com/images/I/51R-mI2x9iL._AC_SL1500_.jpg',
            'https://m.media-amazon.com/images/I/51R-mI2x9iL._AC_SY300_SX300_.jpg',
        ],
    ],
    'amazon landing image with dynamic image json' => [
        'fixture' => 'HtmlImages/amazon-dynamic-image-json.html',
        'expectedImageUrl' => 'https://m.media-amazon.com/images/I/61-RmrxQ5xL._AC_SL1500_.jpg',
        'expectedAllImages' => [
            'https://m.media-amazon.com/images/I/61-RmrxQ5xL._AC_SL1500_.jpg',
            'https://m.media-amazon.com/images/I/61-RmrxQ5xL._AC_SY300_SX300_.jpg',
            'https://m.media-amazon.com/images/I/61-RmrxQ5xL._AC_SY879_.jpg',
            'https://m.media-amazon.com/images/I/61-RmrxQ5xL._AC_SY679_.jpg',
            'https://m.media-amazon.com/images/I/61-RmrxQ5xL._AC_SY355_.jpg',
        ],
    ],
    'product image class pattern' => [
        'fixture' => 'HtmlImages/product-image-class.html',
        'expectedImageUrl' => 'https://shop.example.com/images/product-main.jpg',
        'expectedAllImages' => [
            'https://shop.example.com/images/product-main.jpg',
            'https://shop.example.com/images/product-thumb-1.jpg',
        ],
    ],
    'data zoom attributes' => [
        'fixture' => 'HtmlImages/data-zoom-attributes.html',
        'expectedImageUrl' => 'https://shop.example.com/images/product-zoom.jpg',
        'expectedAllImages' => [
            'https://shop.example.com/images/product-zoom.jpg',
            'https://shop.example.com/images/product-large.jpg',
            'https://shop.example.com/images/product-medium.jpg',
        ],
    ],
    'lazy loaded images with data-src' => [
        'fixture' => 'HtmlImages/lazy-loaded-images.html',
        'expectedImageUrl' => 'https://shop.example.com/images/hero-product.jpg',
        'expectedAllImages' => [
            'https://shop.example.com/images/hero-product.jpg',
            'https://shop.example.com/images/gallery-1.jpg',
        ],
    ],
    'no product images found' => [
        'fixture' => 'HtmlImages/no-product-images.html',
        'expectedImageUrl' => null,
        'expectedAllImages' => [],
    ],
    'multiple image sources combined' => [
        'fixture' => 'HtmlImages/multiple-sources.html',
        'expectedImageUrl' => 'https://cdn.example.com/images/product-hires.jpg',
        'expectedAllImages' => [
            'https://cdn.example.com/images/product-hires.jpg',
            'https://cdn.example.com/images/product-small.jpg',
            'https://cdn.example.com/images/product-angle-2.jpg',
            'https://cdn.example.com/images/product-gallery-zoom.jpg',
            'https://cdn.example.com/images/product-gallery.jpg',
        ],
    ],
]);

it('extracts images from html correctly', function (string $fixture, ?string $expectedImageUrl, array $expectedAllImages) {
    $result = (new HtmlImageParser(getFixture($fixture)))->parse();

    expect($result->imageUrl)->toBe($expectedImageUrl)
        ->and($result->allImageUrls)->toBe($expectedAllImages);
})->with('html_images');

it('prioritizes high-res amazon image over low-res', function () {
    $result = (new HtmlImageParser(getFixture('HtmlImages/amazon-landing-image.html')))->parse();

    expect($result->imageUrl)->toBe('https://m.media-amazon.com/images/I/51R-mI2x9iL._AC_SL1500_.jpg');
});

it('filters out data uri placeholder images', function () {
    $result = (new HtmlImageParser(getFixture('HtmlImages/lazy-loaded-images.html')))->parse();

    foreach ($result->allImageUrls as $url) {
        expect($url)->not->toStartWith('data:');
    }
});

it('extracts largest dynamic image first from json', function () {
    $result = (new HtmlImageParser(getFixture('HtmlImages/amazon-dynamic-image-json.html')))->parse();

    $dynamicImages = array_values(array_filter(
        $result->allImageUrls,
        fn ($url) => preg_match('/_AC_SY\d+_\.jpg$/', $url)
    ));

    expect($dynamicImages[0])->toBe('https://m.media-amazon.com/images/I/61-RmrxQ5xL._AC_SY879_.jpg');
});

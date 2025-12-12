<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Mattiasgeniar\ProductInfoFetcher\ProductInfoFetcherClass;

it('fetches and parses a real product page', function () {
    $result = (new ProductInfoFetcherClass('https://request-mirror.ohdear.app/examples/product-info-page'))
        ->fetchAndParse();

    expect($result->name)->toBe('Oh Dear Subscription')
        ->and($result->description)->toBe('All-in-one website monitoring platform. Track uptime, SSL certificates, broken links, DNS changes, scheduled tasks, and application health from a single dashboard.')
        ->and($result->priceInCents)->toBe(1500)
        ->and($result->priceCurrency)->toBe('EUR')
        ->and($result->imageUrl)->toBe('https://request-mirror.ohdear.app/img/og-image.png');
});

it('falls back to meta tags for non-product json-ld types', function () {
    $result = (new ProductInfoFetcherClass('https://request-mirror.ohdear.app/examples/recipe'))
        ->fetchAndParse();

    expect($result->name)->toBe('Recipe: The perfect website monitoring stack')
        ->and($result->description)->toBe("A chef's guide to cooking up the perfect monitoring setup. Takes 5 minutes to prepare, lasts a lifetime.")
        ->and($result->url)->toBe('https://request-mirror.ohdear.app/examples/recipe')
        ->and($result->priceInCents)->toBeNull()
        ->and($result->imageUrl)->toBe('https://request-mirror.ohdear.app/img/og-image.png');
});

it('can fetch and parse separately', function () {
    $fetcher = new ProductInfoFetcherClass('https://request-mirror.ohdear.app/examples/product-info-page');

    $fetcher->fetch();
    $result = $fetcher->parse();

    expect($result->name)->toBe('Oh Dear Subscription')
        ->and($result->priceInCents)->toBe(1500)
        ->and($result->priceCurrency)->toBe('EUR');
});

it('sends custom user agent header', function () {
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], '<html><head></head><body></body></html>'),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push(Middleware::history($history));

    $client = new Client(['handler' => $handlerStack]);

    (new ProductInfoFetcherClass('https://example.com/product'))
        ->setClient($client)
        ->setUserAgent('CustomBot/1.0')
        ->fetch();

    expect($history)->toHaveCount(1)
        ->and($history[0]['request']->getHeader('User-Agent'))->toBe(['CustomBot/1.0']);
});

it('sends default headers', function () {
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], '<html><head></head><body></body></html>'),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push(Middleware::history($history));

    $client = new Client(['handler' => $handlerStack]);

    (new ProductInfoFetcherClass('https://example.com/product'))
        ->setClient($client)
        ->fetch();

    $request = $history[0]['request'];

    expect($request->getHeader('User-Agent'))->toBe(['ProductInfoFetcher/1.0'])
        ->and($request->getHeader('Accept'))->toBe(['text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'])
        ->and($request->getHeader('Accept-Language'))->toBe(['en-US,en;q=0.5']);
});

it('sends custom accept-language header', function () {
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], '<html><head></head><body></body></html>'),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push(Middleware::history($history));

    $client = new Client(['handler' => $handlerStack]);

    (new ProductInfoFetcherClass('https://example.com/product'))
        ->setClient($client)
        ->setAcceptLanguage('nl-BE,nl;q=0.9,en;q=0.8')
        ->fetch();

    expect($history[0]['request']->getHeader('Accept-Language'))
        ->toBe(['nl-BE,nl;q=0.9,en;q=0.8']);
});

it('sends simple language code', function () {
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], '<html><head></head><body></body></html>'),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push(Middleware::history($history));

    $client = new Client(['handler' => $handlerStack]);

    (new ProductInfoFetcherClass('https://example.com/product'))
        ->setClient($client)
        ->setAcceptLanguage('de')
        ->fetch();

    expect($history[0]['request']->getHeader('Accept-Language'))
        ->toBe(['de']);
});

it('can set custom timeout', function () {
    $result = (new ProductInfoFetcherClass('https://request-mirror.ohdear.app/examples/product-info-page'))
        ->setTimeout(10)
        ->setConnectTimeout(5)
        ->fetchAndParse();

    expect($result->name)->toBe('Oh Dear Subscription');
});

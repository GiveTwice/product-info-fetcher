<?php

use GiveTwice\ProductInfoFetcher\Enum\FetchOutcome;
use GiveTwice\ProductInfoFetcher\HeadlessBrowser\HeadlessFetcher;
use GiveTwice\ProductInfoFetcher\HeadlessBrowser\HeadlessFetchResult;
use GiveTwice\ProductInfoFetcher\ProductInfoFetcher;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('classifies response outcomes without reporting wall metadata as a product', function ($status, $html, $outcome) {
    $history = [];
    $result = (new ProductInfoFetcher('https://example.com/product'))
        ->setClient(createMockClient($history, [new Response($status, [], $html)]))->fetchResult();

    expect($result->outcome)->toBe($outcome)
        ->and($result->httpStatus)->toBe($status)
        ->and($result->finalUrl)->toBe('https://example.com/product')
        ->and($result->hasProductData())->toBeFalse()
        ->and($history)->toHaveCount(1)
        ->and($result->attempts[0]->transport)->toBe('http');
})->with([
    [403, '<title>Access denied</title>', FetchOutcome::Blocked],
    [200, '<title>Robot Check</title>', FetchOutcome::Blocked],
    [200, '<form action="/errors/validateCaptcha">Enter the characters</form>', FetchOutcome::Blocked],
    [200, '<title>Before you continue</title>', FetchOutcome::Consent],
    [200, '<h1>Avant de continuer</h1>', FetchOutcome::Consent],
    [404, '<title>Recommended products</title>', FetchOutcome::Removed],
    [410, '', FetchOutcome::Removed],
    [200, '<h1>Pagina niet gevonden</h1>', FetchOutcome::Removed],
    [500, '<title>Unavailable</title>', FetchOutcome::Error],
    [200, '<title>Welcome to our storefront</title>', FetchOutcome::Incomplete],
]);

it('reads product data behind consent without clicking through', function () {
    $result = (new ProductInfoFetcher)->setHtml('<h1>Voordat je verder gaat</h1>'.getFixture('JsonLd/complete-product.html'))->parse();

    expect($result->outcome)->toBe(FetchOutcome::Consent)
        ->and($result->hasProductData())->toBeTrue();
});

it('does not classify ordinary cookie notices and captcha library scripts as walls', function () {
    $result = (new ProductInfoFetcher)->setHtml('<footer>Cookies and privacy</footer><script src="recaptcha.js"></script>'.getFixture('JsonLd/complete-product.html'))->parse();
    expect($result->outcome)->toBe(FetchOutcome::Success);
});

it('retains both transports and only makes one optional browser attempt', function () {
    $browser = new class extends HeadlessFetcher
    {
        public int $calls = 0;

        public function fetch(string $url): HeadlessFetchResult
        {
            $this->calls++;

            return new HeadlessFetchResult(getFixture('JsonLd/complete-product.html'), 200, 'https://example.com/final', true, 'navigation_timeout');
        }
    };
    $history = [];
    $result = (new ProductInfoFetcher('https://example.com/product'))
        ->setClient(createMockClient($history, [new Response(403, [], 'Forbidden')]))
        ->setHeadlessFetcher($browser)->enableHeadlessFallback()->fetchResult();

    expect($result->outcome)->toBe(FetchOutcome::Success)
        ->and($result->attempts)->toHaveCount(2)
        ->and($result->attempts[0]->statusCode)->toBe(403)
        ->and($result->attempts[1]->transport)->toBe('browser')
        ->and($result->attempts[1]->timedOut)->toBeTrue()
        ->and($result->httpStatus)->toBe(200)
        ->and($result->finalUrl)->toBe('https://example.com/final')
        ->and($browser->calls)->toBe(1)->and($history)->toHaveCount(1);
});

it('captures connection errors without exposing credentials in diagnostic messages', function () {
    $history = [];
    $result = (new ProductInfoFetcher('https://example.com/product'))
        ->setClient(createMockClient($history, [new ConnectException('secret proxy password', new Request('GET', 'https://example.com'))]))->fetchResult();

    expect($result->outcome)->toBe(FetchOutcome::Error)
        ->and($result->httpStatus)->toBeNull()
        ->and(json_encode($result->toArray()))->not->toContain('secret proxy password');
});

it('captures the effective URL through redirects and retains one request chain', function () {
    $history = [];
    $result = (new ProductInfoFetcher('https://example.com/old'))
        ->setClient(createMockClient($history, [new Response(302, ['Location' => 'https://example.com/new']), new Response(200, [], getFixture('JsonLd/complete-product.html'))]))->fetchResult();
    expect($result->httpStatus)->toBe(200)->and($result->finalUrl)->toBe('https://example.com/new')->and($result->attempts)->toHaveCount(1);
});

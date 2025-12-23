<?php

use GiveTwice\ProductInfoFetcher\HeadlessBrowser\Exceptions\NodeNotFoundException;
use GiveTwice\ProductInfoFetcher\HeadlessBrowser\HeadlessFetcher;

it('throws NodeNotFoundException when node binary does not exist', function () {
    $fetcher = (new HeadlessFetcher)
        ->setNodeBinary('/non/existent/node');

    $fetcher->fetch('https://example.com');
})->throws(NodeNotFoundException::class);

it('can set node binary path', function () {
    $fetcher = new HeadlessFetcher;

    $result = $fetcher->setNodeBinary('/custom/node');

    expect($result)->toBe($fetcher);
});

it('can set chrome path', function () {
    $fetcher = new HeadlessFetcher;

    $result = $fetcher->setChromePath('/custom/chrome');

    expect($result)->toBe($fetcher);
});

it('can set timeout', function () {
    $fetcher = new HeadlessFetcher;

    $result = $fetcher->setTimeout(60);

    expect($result)->toBe($fetcher);
});

it('can set user agent', function () {
    $fetcher = new HeadlessFetcher;

    $result = $fetcher->setUserAgent('CustomBot/1.0');

    expect($result)->toBe($fetcher);
});

it('can set headers', function () {
    $fetcher = new HeadlessFetcher;

    $result = $fetcher->setHeaders(['DNT' => '1']);

    expect($result)->toBe($fetcher);
});

it('can chain configuration methods', function () {
    $fetcher = (new HeadlessFetcher)
        ->setNodeBinary('/usr/bin/node')
        ->setChromePath('/usr/bin/chromium')
        ->setTimeout(45)
        ->setUserAgent('MyBot/1.0')
        ->setHeaders(['Accept-Language' => 'nl']);

    expect($fetcher)->toBeInstanceOf(HeadlessFetcher::class);
});

it('can set proxy', function () {
    $fetcher = new HeadlessFetcher;

    $result = $fetcher->setProxy('http://proxy.example.com:3128');

    expect($result)->toBe($fetcher);
});

it('can set proxy with authentication', function () {
    $fetcher = new HeadlessFetcher;

    $result = $fetcher->setProxy('http://user:pass@proxy.example.com:3128');

    expect($result)->toBe($fetcher);
});

it('can chain setProxy with other methods', function () {
    $fetcher = (new HeadlessFetcher)
        ->setNodeBinary('/usr/bin/node')
        ->setProxy('http://proxy.example.com:3128')
        ->setTimeout(45);

    expect($fetcher)->toBeInstanceOf(HeadlessFetcher::class);
});

it('passes proxy to node script', function () {
    $echoScriptPath = dirname(__DIR__, 3).'/bin/echo-command.cjs';

    $fetcher = (new HeadlessFetcher)
        ->setScriptPath($echoScriptPath)
        ->setProxy('http://user:pass@proxy.example.com:3128');

    $result = $fetcher->fetch('https://example.com');

    expect($result->html)->toContain('_receivedCommand');

    $response = json_decode($result->html, true);

    expect($response['_receivedCommand']['proxy'])->toBe('http://user:pass@proxy.example.com:3128');
});

it('passes null proxy to node script when not set', function () {
    $echoScriptPath = dirname(__DIR__, 3).'/bin/echo-command.cjs';

    $fetcher = (new HeadlessFetcher)
        ->setScriptPath($echoScriptPath);

    $result = $fetcher->fetch('https://example.com');

    $response = json_decode($result->html, true);

    expect($response['_receivedCommand']['proxy'])->toBeNull();
});

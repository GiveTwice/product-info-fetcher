<?php

use GiveTwice\ProductInfoFetcher\Enum\FetchOutcome;
use GiveTwice\ProductInfoFetcher\ProductInfoFetcher;

it('fetches a current product name price and image', function ($shop, $url) {
    $fetcher = (new ProductInfoFetcher($url))->setTimeout(20);
    if (getenv('LIVE_BROWSER') === '1') {
        $fetcher->preferHeadless()->setChromePath(getenv('CHROME_PATH') ?: '/usr/bin/google-chrome');
    }
    $result = $fetcher->fetchResult();
    if ($report = getenv('LIVE_REPORT')) {
        file_put_contents($report, json_encode(['shop' => $shop, 'url' => $url, 'checkedAt' => gmdate('c'), 'result' => $result->toArray()], JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    expect($result->outcome->value, "{$shop}: {$result->outcome->value}, status {$result->httpStatus}")->toBe(FetchOutcome::Success->value)
        ->and($result->name)->not->toBeEmpty()
        ->and($result->priceInCents)->toBeGreaterThan(0)
        ->and($result->priceCurrency)->toBe('EUR')
        ->and(filter_var($result->imageUrl, FILTER_VALIDATE_URL))->not->toBeFalse();
})->with(require __DIR__.'/urls.php')->group('live')
    ->skip(getenv('LIVE') !== '1' || (bool) getenv('CI'), 'Opt in with LIVE=1 outside CI.');

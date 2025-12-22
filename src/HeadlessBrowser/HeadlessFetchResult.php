<?php

namespace GiveTwice\ProductInfoFetcher\HeadlessBrowser;

readonly class HeadlessFetchResult
{
    public function __construct(
        public string $html,
        public int $statusCode,
        public string $finalUrl,
    ) {}
}

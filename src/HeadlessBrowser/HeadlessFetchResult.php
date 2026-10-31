<?php

namespace GiveTwice\ProductInfoFetcher\HeadlessBrowser;

readonly class HeadlessFetchResult
{
    public function __construct(
        public string $html,
        public int $statusCode,
        public string $finalUrl,
        public bool $timedOut = false,
        public ?string $error = null,
    ) {}
}

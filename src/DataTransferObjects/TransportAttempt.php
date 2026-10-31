<?php

namespace GiveTwice\ProductInfoFetcher\DataTransferObjects;

readonly class TransportAttempt
{
    public function __construct(
        public string $transport,
        public ?int $statusCode,
        public ?string $finalUrl,
        public float $durationMs,
        public bool $timedOut = false,
        public ?string $error = null,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

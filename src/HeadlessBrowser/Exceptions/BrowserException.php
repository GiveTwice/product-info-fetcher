<?php

namespace GiveTwice\ProductInfoFetcher\HeadlessBrowser\Exceptions;

class BrowserException extends HeadlessBrowserException
{
    private ?int $statusCode = null;

    private ?string $responseHtml = null;

    /** @var array<string, string> */
    private array $responseHeaders = [];

    private ?string $finalUrl = null;

    /**
     * @param  array<string, string>  $headers
     */
    public static function blocked(
        string $message,
        int $statusCode,
        string $html,
        array $headers = [],
        ?string $finalUrl = null
    ): self {
        $exception = new self($message);
        $exception->statusCode = $statusCode;
        $exception->responseHtml = $html;
        $exception->responseHeaders = $headers;
        $exception->finalUrl = $finalUrl;

        return $exception;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getResponseHtml(): ?string
    {
        return $this->responseHtml;
    }

    /** @return array<string, string> */
    public function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }

    public function getFinalUrl(): ?string
    {
        return $this->finalUrl;
    }

    public function hasDebugData(): bool
    {
        return $this->statusCode !== null || $this->responseHtml !== null;
    }
}

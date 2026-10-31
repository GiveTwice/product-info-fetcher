<?php

namespace GiveTwice\ProductInfoFetcher;

use GiveTwice\ProductInfoFetcher\DataTransferObjects\ProductInfo;
use GiveTwice\ProductInfoFetcher\DataTransferObjects\TransportAttempt;
use GiveTwice\ProductInfoFetcher\Detection\PageOutcomeDetector;
use GiveTwice\ProductInfoFetcher\Enum\FetchOutcome;
use GiveTwice\ProductInfoFetcher\HeadlessBrowser\Exceptions\BrowserException;
use GiveTwice\ProductInfoFetcher\HeadlessBrowser\Exceptions\HeadlessBrowserException;
use GiveTwice\ProductInfoFetcher\HeadlessBrowser\HeadlessFetcher;
use GiveTwice\ProductInfoFetcher\HeadlessBrowser\HeadlessFetchResult;
use GiveTwice\ProductInfoFetcher\Parsers\HtmlImageParser;
use GiveTwice\ProductInfoFetcher\Parsers\JsonLdParser;
use GiveTwice\ProductInfoFetcher\Parsers\MetaTagParser;
use GiveTwice\ProductInfoFetcher\Parsers\Shops\AmazonParser;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use RuntimeException;

class ProductInfoFetcher
{
    private const DEFAULT_TIMEOUT = 5;

    private const DEFAULT_CONNECT_TIMEOUT = 3;

    private const DEFAULT_USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    private const DEFAULT_ACCEPT_LANGUAGE = 'en-US,en;q=0.5';

    private int $timeout;

    private int $connectTimeout;

    private string $userAgent;

    private string $acceptLanguage;

    /** @var array<string, string> */
    private array $extraHeaders = [];

    private ?string $proxy = null;

    private ?string $html = null;

    /** @var list<TransportAttempt> */
    private array $attempts = [];

    private bool $returnOutcomes = false;

    private ?ClientInterface $client = null;

    private bool $headlessFallbackEnabled = false;

    private bool $preferHeadless = false;

    private ?HeadlessFetcher $headlessFetcher = null;

    public function __construct(
        private readonly ?string $url = null,
    ) {
        $this->timeout = self::DEFAULT_TIMEOUT;
        $this->connectTimeout = self::DEFAULT_CONNECT_TIMEOUT;
        $this->userAgent = self::DEFAULT_USER_AGENT;
        $this->acceptLanguage = self::DEFAULT_ACCEPT_LANGUAGE;
    }

    public function setHtml(string $html): self
    {
        $this->attempts = [];
        $this->html = $html;

        return $this;
    }

    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function setConnectTimeout(int $seconds): self
    {
        $this->connectTimeout = $seconds;

        return $this;
    }

    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function setAcceptLanguage(string $acceptLanguage): self
    {
        $this->acceptLanguage = $acceptLanguage;

        return $this;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withExtraHeaders(array $headers): self
    {
        $this->extraHeaders = array_merge($this->extraHeaders, $headers);

        return $this;
    }

    public function viaProxy(string $proxy): self
    {
        $this->proxy = $proxy;

        return $this;
    }

    public function setClient(ClientInterface $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function enableHeadlessFallback(): self
    {
        $this->headlessFallbackEnabled = true;
        $this->getHeadlessFetcher();

        return $this;
    }

    public function preferHeadless(): self
    {
        $this->preferHeadless = true;
        $this->getHeadlessFetcher();

        return $this;
    }

    public function setNodeBinary(string $path): self
    {
        $this->getHeadlessFetcher()->setNodeBinary($path);

        return $this;
    }

    public function setChromePath(string $path): self
    {
        $this->getHeadlessFetcher()->setChromePath($path);

        return $this;
    }

    public function setHeadlessFetcher(HeadlessFetcher $fetcher): self
    {
        $this->headlessFetcher = $fetcher;

        return $this;
    }

    private function getHeadlessFetcher(): HeadlessFetcher
    {
        if ($this->headlessFetcher === null) {
            $this->headlessFetcher = new HeadlessFetcher;
        }

        return $this->headlessFetcher;
    }

    public function fetch(): self
    {
        if ($this->url === null) {
            throw new RuntimeException('No URL provided. Pass a URL to the constructor or use setHtml() instead.');
        }

        $this->html = null;
        $this->attempts = [];

        if ($this->preferHeadless) {
            $this->captureHeadless();

            return $this;
        }

        try {
            $this->html = $this->fetchViaHttp();
        } catch (TransferException $e) {
            // Keep the original HTTP response, independently of a browser fallback.
            if ($e instanceof RequestException && $e->hasResponse()) {
                $this->html = (string) $e->getResponse()->getBody();
            }

            $status = $this->attempts[0]->statusCode ?? null;
            if ($this->headlessFallbackEnabled && $status === 403) {
                $this->captureHeadless();
            } elseif (! $this->returnOutcomes) {
                throw $e;
            }
        }

        return $this;
    }

    /** Return explicit outcomes instead of throwing transport/HTTP failures. */
    public function fetchResult(): ProductInfo
    {
        $this->returnOutcomes = true;
        try {
            $this->fetch();

            return $this->parse();
        } finally {
            $this->returnOutcomes = false;
        }
    }

    private function captureHeadless(): void
    {
        $started = microtime(true);
        try {
            $result = $this->fetchViaHeadless();
            $this->html = $result->html;
            $this->attempts[] = new TransportAttempt('browser', $result->statusCode ?: null, $result->finalUrl,
                (microtime(true) - $started) * 1000, $result->timedOut, $result->error);
        } catch (HeadlessBrowserException $e) {
            $blocked = $e instanceof BrowserException ? $e : null;
            $this->html = $blocked?->getResponseHtml() ?? $this->html ?? '';
            $this->attempts[] = new TransportAttempt('browser', $blocked?->getStatusCode(),
                $blocked?->getFinalUrl() ?? $this->url, (microtime(true) - $started) * 1000, $e->getMessage() === 'browser_process_timeout', 'browser_error');
            if (! $this->returnOutcomes) {
                throw $e;
            }
        }
    }

    private function fetchViaHttp(): string
    {
        $client = $this->client ?? new Client;

        $options = [
            RequestOptions::TIMEOUT => $this->timeout,
            RequestOptions::CONNECT_TIMEOUT => $this->connectTimeout,
            RequestOptions::HTTP_ERRORS => true,
            RequestOptions::ALLOW_REDIRECTS => [
                'max' => 5,
                'strict' => false,
                'referer' => true,
                'track_redirects' => false,
            ],
            RequestOptions::DECODE_CONTENT => true,
            RequestOptions::HEADERS => array_merge([
                'User-Agent' => $this->userAgent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => $this->acceptLanguage,
            ], $this->extraHeaders),
        ];

        if ($this->proxy !== null) {
            $options[RequestOptions::PROXY] = $this->proxy;
        }

        $finalUrl = $this->url;
        $options[RequestOptions::ALLOW_REDIRECTS]['on_redirect'] = function ($request, $response, $uri) use (&$finalUrl): void {
            $finalUrl = (string) $uri;
        };
        $options[RequestOptions::ON_STATS] = function (TransferStats $stats) use (&$finalUrl): void {
            $finalUrl = (string) $stats->getEffectiveUri();
        };
        $started = microtime(true);
        try {
            $response = $client->get($this->url, $options);
            $this->attempts[] = new TransportAttempt('http', $response->getStatusCode(), $finalUrl,
                (microtime(true) - $started) * 1000);
        } catch (TransferException $e) {
            $status = $e instanceof RequestException ? $e->getResponse()?->getStatusCode() : null;
            $this->attempts[] = new TransportAttempt('http', $status, $finalUrl,
                (microtime(true) - $started) * 1000, str_contains($e->getMessage(), 'cURL error 28'), 'http_error');
            $this->html = '';
            throw $e;
        }

        return (string) $response->getBody();
    }

    private function fetchViaHeadless(): HeadlessFetchResult
    {
        $headers = array_merge(
            ['Accept-Language' => $this->acceptLanguage],
            $this->extraHeaders
        );

        $fetcher = $this->getHeadlessFetcher()
            ->setTimeout($this->timeout)
            ->setUserAgent($this->userAgent)
            ->setHeaders($headers);

        if ($this->proxy !== null) {
            $fetcher->setProxy($this->proxy);
        }

        return $fetcher->fetch($this->url);
    }

    public function parse(): ProductInfo
    {
        if ($this->html === null) {
            throw new RuntimeException('No HTML to parse. Call fetch() first.');
        }

        $attempt = $this->attempts === [] ? null : $this->attempts[array_key_last($this->attempts)];
        $outcome = (new PageOutcomeDetector)->detect($this->html, $attempt?->statusCode, $attempt?->finalUrl ?? $this->url);
        $result = in_array($outcome, [FetchOutcome::Blocked, FetchOutcome::Removed, FetchOutcome::Error], true)
            ? new ProductInfo : $this->parseHtml($this->html ?: '<html></html>');
        $result->finalUrl = $attempt?->finalUrl ?? $this->url;
        $result->httpStatus = $attempt?->statusCode;
        $result->attempts = $this->attempts;
        $result->outcome = $outcome ?? ($result->hasProductData() ? FetchOutcome::Success : FetchOutcome::Incomplete);
        if ($outcome === null && ! $result->hasProductData() && ($attempt?->error !== null || $attempt?->timedOut)) {
            $result->outcome = FetchOutcome::Error;
        }

        return $result;
    }

    public function fetchAndParse(): ProductInfo
    {
        return $this->fetch()->parse();
    }

    private function parseHtml(string $html): ProductInfo
    {
        $attempt = $this->attempts === [] ? null : $this->attempts[array_key_last($this->attempts)];
        $url = $attempt?->finalUrl ?? $this->url;
        $jsonLdResult = (new JsonLdParser($html, $url))->parse();
        $shopResult = (new AmazonParser($html, $url))->parse();
        $metaTagResult = (new MetaTagParser($html, $url))->parse();
        $htmlImageResult = (new HtmlImageParser($html))->parse();

        return $this->mergeResults($jsonLdResult, $shopResult, $metaTagResult, $htmlImageResult);
    }

    private function mergeResults(ProductInfo ...$results): ProductInfo
    {
        $merged = new ProductInfo;
        $allImages = [];

        foreach ($results as $result) {
            $merged->name = $merged->name ?? $result->name;
            $merged->description = $merged->description ?? $result->description;
            $merged->url = $merged->url ?? $result->url;
            $merged->priceInCents = $merged->priceInCents ?? $result->priceInCents;
            $merged->priceCurrency = $merged->priceCurrency ?? $result->priceCurrency;
            $merged->imageUrl = $merged->imageUrl ?? $result->imageUrl;
            $merged->brand = $merged->brand ?? $result->brand;
            $merged->sku = $merged->sku ?? $result->sku;
            $merged->gtin = $merged->gtin ?? $result->gtin;
            $merged->availability = $merged->availability ?? $result->availability;
            $merged->condition = $merged->condition ?? $result->condition;
            $merged->rating = $merged->rating ?? $result->rating;
            $merged->reviewCount = $merged->reviewCount ?? $result->reviewCount;

            foreach ($result->allImageUrls as $imageUrl) {
                if (! in_array($imageUrl, $allImages, true)) {
                    $allImages[] = $imageUrl;
                }
            }
        }

        $merged->allImageUrls = $allImages;

        return $merged;
    }
}

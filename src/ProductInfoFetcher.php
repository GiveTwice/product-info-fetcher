<?php

namespace GiveTwice\ProductInfoFetcher;

use GiveTwice\ProductInfoFetcher\DataTransferObjects\ProductInfo;
use GiveTwice\ProductInfoFetcher\HeadlessBrowser\HeadlessFetcher;
use GiveTwice\ProductInfoFetcher\HeadlessBrowser\HeadlessFetchResult;
use GiveTwice\ProductInfoFetcher\Parsers\HtmlImageParser;
use GiveTwice\ProductInfoFetcher\Parsers\JsonLdParser;
use GiveTwice\ProductInfoFetcher\Parsers\MetaTagParser;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use RuntimeException;

class ProductInfoFetcher
{
    private const DEFAULT_TIMEOUT = 5;

    private const DEFAULT_CONNECT_TIMEOUT = 3;

    private const DEFAULT_USER_AGENT = 'ProductInfoFetcher/1.0';

    private const DEFAULT_ACCEPT_LANGUAGE = 'en-US,en;q=0.5';

    private int $timeout;

    private int $connectTimeout;

    private string $userAgent;

    private string $acceptLanguage;

    /** @var array<string, string> */
    private array $extraHeaders = [];

    private ?string $html = null;

    private ?ClientInterface $client = null;

    private bool $headlessFallbackEnabled = false;

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

    public function setClient(ClientInterface $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function enableHeadlessFallback(): self
    {
        $this->headlessFallbackEnabled = true;
        $this->headlessFetcher = new HeadlessFetcher;

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

        try {
            $this->html = $this->fetchViaHttp();
        } catch (ClientException $e) {
            if ($e->getCode() === 403 && $this->headlessFallbackEnabled) {
                $result = $this->fetchViaHeadless();
                $this->html = $result->html;
            } else {
                throw $e;
            }
        }

        return $this;
    }

    private function fetchViaHttp(): string
    {
        $client = $this->client ?? new Client;

        $response = $client->get($this->url, [
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
        ]);

        return (string) $response->getBody();
    }

    private function fetchViaHeadless(): HeadlessFetchResult
    {
        $headers = array_merge(
            ['Accept-Language' => $this->acceptLanguage],
            $this->extraHeaders
        );

        return $this->getHeadlessFetcher()
            ->setTimeout($this->timeout)
            ->setUserAgent($this->userAgent)
            ->setHeaders($headers)
            ->fetch($this->url);
    }

    public function parse(): ProductInfo
    {
        if ($this->html === null) {
            throw new RuntimeException('No HTML to parse. Call fetch() first.');
        }

        return $this->parseHtml($this->html);
    }

    public function fetchAndParse(): ProductInfo
    {
        return $this->fetch()->parse();
    }

    private function parseHtml(string $html): ProductInfo
    {
        $jsonLdResult = (new JsonLdParser($html, $this->url))->parse();
        $metaTagResult = (new MetaTagParser($html, $this->url))->parse();
        $htmlImageResult = (new HtmlImageParser($html))->parse();

        if ($jsonLdResult->isComplete()) {
            $this->appendImagesFromSources($jsonLdResult, $metaTagResult, $htmlImageResult);

            return $jsonLdResult;
        }

        if ($metaTagResult->isComplete()) {
            $this->appendImagesFromSources($metaTagResult, $jsonLdResult, $htmlImageResult);

            return $metaTagResult;
        }

        return $this->mergeResults($jsonLdResult, $metaTagResult, $htmlImageResult);
    }

    private function appendImagesFromSources(ProductInfo $target, ProductInfo ...$sources): void
    {
        foreach ($sources as $source) {
            foreach ($source->allImageUrls as $imageUrl) {
                if (! in_array($imageUrl, $target->allImageUrls, true)) {
                    $target->allImageUrls[] = $imageUrl;
                }
            }
        }
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

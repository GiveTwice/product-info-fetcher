<?php

namespace Mattiasgeniar\ProductInfoFetcher;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Mattiasgeniar\ProductInfoFetcher\DataTransferObjects\ProductInfo;
use Mattiasgeniar\ProductInfoFetcher\Parsers\HtmlImageParser;
use Mattiasgeniar\ProductInfoFetcher\Parsers\JsonLdParser;
use Mattiasgeniar\ProductInfoFetcher\Parsers\MetaTagParser;
use RuntimeException;

class ProductInfoFetcherClass
{
    private const DEFAULT_TIMEOUT = 5;

    private const DEFAULT_CONNECT_TIMEOUT = 3;

    private const DEFAULT_USER_AGENT = 'ProductInfoFetcher/1.0';

    private const DEFAULT_ACCEPT_LANGUAGE = 'en-US,en;q=0.5';

    private int $timeout;

    private int $connectTimeout;

    private string $userAgent;

    private string $acceptLanguage;

    private ?string $html = null;

    private ?ClientInterface $client = null;

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

    public function setClient(ClientInterface $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @throws GuzzleException
     */
    public function fetch(): self
    {
        if ($this->url === null) {
            throw new RuntimeException('No URL provided. Pass a URL to the constructor or use setHtml() instead.');
        }

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
            RequestOptions::HEADERS => [
                'User-Agent' => $this->userAgent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => $this->acceptLanguage,
            ],
        ]);

        $this->html = (string) $response->getBody();

        return $this;
    }

    public function parse(): ProductInfo
    {
        if ($this->html === null) {
            throw new RuntimeException('No HTML to parse. Call fetch() first.');
        }

        return $this->parseHtml($this->html);
    }

    /**
     * @throws GuzzleException
     */
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
            $this->appendImages($jsonLdResult, $metaTagResult, $htmlImageResult);

            return $jsonLdResult;
        }

        if ($metaTagResult->isComplete()) {
            $this->appendImages($metaTagResult, $jsonLdResult, $htmlImageResult);

            return $metaTagResult;
        }

        return $this->mergeResults($jsonLdResult, $metaTagResult, $htmlImageResult);
    }

    private function appendImages(ProductInfo $target, ProductInfo ...$sources): void
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

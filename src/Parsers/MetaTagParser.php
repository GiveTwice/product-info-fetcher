<?php

namespace Mattiasgeniar\ProductInfoFetcher\Parsers;

use DOMDocument;
use DOMXPath;
use Mattiasgeniar\ProductInfoFetcher\DataTransferObjects\ProductInfo;

class MetaTagParser
{
    private DOMXPath $xpath;

    public function __construct(
        private readonly string $html,
    ) {
        libxml_use_internal_errors(true);

        $doc = new DOMDocument;
        $doc->loadHTML($this->html, LIBXML_NOWARNING);
        $this->xpath = new DOMXPath($doc);

        libxml_clear_errors();
    }

    public function parse(): ProductInfo
    {
        return new ProductInfo(
            name: $this->extractName(),
            description: $this->extractDescription(),
            url: $this->extractUrl(),
            price: $this->extractPrice(),
            imageUrl: $this->extractImage(),
        );
    }

    private function extractName(): ?string
    {
        return $this->extractMetaContent('og:title')
            ?? $this->extractMetaContent('twitter:title');
    }

    private function extractDescription(): ?string
    {
        return $this->extractMetaContent('og:description')
            ?? $this->extractMetaContent('twitter:description')
            ?? $this->extractMetaContent('description');
    }

    private function extractUrl(): ?string
    {
        $canonical = $this->xpath->query("//link[@rel='canonical']/@href");
        if ($canonical && $canonical->length > 0) {
            return trim($canonical->item(0)->nodeValue);
        }

        return $this->extractMetaContent('og:url');
    }

    private function extractPrice(): ?string
    {
        return $this->extractMetaContent('product:price:amount');
    }

    private function extractImage(): ?string
    {
        return $this->extractMetaContent('og:image')
            ?? $this->extractMetaContent('twitter:image');
    }

    private function extractMetaContent(string $property): ?string
    {
        $propertyNode = $this->xpath->query("//meta[@property='{$property}']/@content");
        if ($propertyNode && $propertyNode->length > 0) {
            return trim($propertyNode->item(0)->nodeValue);
        }

        $nameNode = $this->xpath->query("//meta[@name='{$property}']/@content");
        if ($nameNode && $nameNode->length > 0) {
            return trim($nameNode->item(0)->nodeValue);
        }

        return null;
    }
}

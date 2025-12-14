<?php

namespace Mattiasgeniar\ProductInfoFetcher\Parsers;

use DOMDocument;
use DOMXPath;
use Mattiasgeniar\ProductInfoFetcher\DataTransferObjects\ProductInfo;
use Mattiasgeniar\ProductInfoFetcher\Enum\ProductAvailability;
use Mattiasgeniar\ProductInfoFetcher\Enum\ProductCondition;

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
            priceInCents: $this->extractPriceInCents(),
            priceCurrency: $this->extractPriceCurrency(),
            imageUrl: $this->extractImage(),
            brand: $this->extractMetaContent('product:brand'),
            sku: $this->extractMetaContent('product:retailer_item_id'),
            availability: ProductAvailability::fromString($this->extractMetaContent('product:availability')),
            condition: ProductCondition::fromString($this->extractMetaContent('product:condition')),
        );
    }

    private function extractName(): ?string
    {
        return $this->extractMetaContent('og:title')
            ?? $this->extractMetaContent('twitter:title')
            ?? $this->extractTitle();
    }

    private function extractTitle(): ?string
    {
        $title = $this->xpath->query('//title');

        if ($title && $title->length > 0) {
            return trim($title->item(0)->nodeValue);
        }

        return null;
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

    private function extractPriceInCents(): ?int
    {
        $priceAmount = $this->extractMetaContent('product:price:amount');

        if ($priceAmount === null) {
            return null;
        }

        return $this->normalizePriceToCents($priceAmount);
    }

    private function extractPriceCurrency(): ?string
    {
        return $this->extractMetaContent('product:price:currency');
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

    private function normalizePriceToCents(string $price): int
    {
        $priceString = preg_replace('/[^\d.,]/', '', $price);

        $lastDot = strrpos($priceString, '.');
        $lastComma = strrpos($priceString, ',');

        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $priceString = str_replace('.', '', $priceString);
            $priceString = str_replace(',', '.', $priceString);
        } else {
            $priceString = str_replace(',', '', $priceString);
        }

        return (int) round((float) $priceString * 100);
    }
}

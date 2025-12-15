<?php

namespace Mattiasgeniar\ProductInfoFetcher\Parsers;

use DOMDocument;
use DOMXPath;
use Mattiasgeniar\ProductInfoFetcher\DataTransferObjects\ProductInfo;
use Mattiasgeniar\ProductInfoFetcher\Enum\ProductAvailability;
use Mattiasgeniar\ProductInfoFetcher\Enum\ProductCondition;

class MetaTagParser implements ParserInterface
{
    use NormalizesPrices;

    private DOMXPath $xpath;

    public function __construct(
        private readonly string $html,
        private readonly ?string $currentUrl = null,
    ) {
        libxml_use_internal_errors(true);

        $doc = new DOMDocument;
        $doc->loadHTML($this->html, LIBXML_NOWARNING);
        $this->xpath = new DOMXPath($doc);

        libxml_clear_errors();
    }

    public function parse(): ProductInfo
    {
        $allImages = $this->extractAllImages();

        return new ProductInfo(
            name: $this->extractName(),
            description: $this->extractDescription(),
            url: $this->extractUrl(),
            priceInCents: $this->extractPriceInCents(),
            priceCurrency: $this->extractPriceCurrency(),
            imageUrl: $allImages[0] ?? null,
            allImageUrls: $allImages,
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

    private function extractAllImages(): array
    {
        $images = [];

        $ogImage = $this->extractMetaContent('og:image');
        if ($ogImage !== null) {
            $images[] = $ogImage;
        }

        $twitterImage = $this->extractMetaContent('twitter:image');
        if ($twitterImage !== null && $twitterImage !== $ogImage) {
            $images[] = $twitterImage;
        }

        return $images;
    }

    private function extractMetaContent(string $property): ?string
    {
        $escapedProperty = $this->escapeXPathString($property);

        $propertyNode = $this->xpath->query("//meta[@property={$escapedProperty}]/@content");
        if ($propertyNode && $propertyNode->length > 0) {
            return trim($propertyNode->item(0)->nodeValue);
        }

        $nameNode = $this->xpath->query("//meta[@name={$escapedProperty}]/@content");
        if ($nameNode && $nameNode->length > 0) {
            return trim($nameNode->item(0)->nodeValue);
        }

        return null;
    }

    private function escapeXPathString(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        if (! str_contains($value, '"')) {
            return "\"{$value}\"";
        }

        return "concat('".str_replace("'", "',\"'\",'", $value)."')";
    }
}

<?php

namespace GiveTwice\ProductInfoFetcher\Parsers\Shops;

use DOMDocument;
use DOMXPath;
use GiveTwice\ProductInfoFetcher\DataTransferObjects\ProductInfo;
use GiveTwice\ProductInfoFetcher\Enum\ProductAvailability;
use GiveTwice\ProductInfoFetcher\Parsers\NormalizesPrices;
use GiveTwice\ProductInfoFetcher\Parsers\ParserInterface;

class AmazonParser implements ParserInterface
{
    use NormalizesPrices;

    public function __construct(private readonly string $html, private readonly ?string $url = null) {}

    public function parse(): ProductInfo
    {
        $host = strtolower(parse_url($this->url ?? '', PHP_URL_HOST) ?? '');
        if (! in_array($host, ['amazon.com.be', 'www.amazon.com.be', 'amazon.nl', 'www.amazon.nl', 'amazon.de', 'www.amazon.de', 'amazon.fr', 'www.amazon.fr'], true)) {
            return new ProductInfo;
        }
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $doc->loadHTML('<?xml encoding="UTF-8">'.$this->html, LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);
        $read = function (string $query) use ($xpath): ?string {
            foreach ($xpath->query($query) as $node) {
                $value = trim($node->nodeValue);
                if ($value !== '') {
                    return $value;
                }
            }

            return null;
        };
        $name = $read('//*[@id="productTitle"]');
        if ($name === null) {
            return new ProductInfo;
        }
        // Scope to the main buy box. Exclude list prices, installments and
        // recommendation cards, which can all contain other a-price elements.
        $price = $read('//*[@id="corePriceDisplay_desktop_feature_div" or @id="corePrice_feature_div" or @id="apex_desktop"]//*[contains(concat(" ", normalize-space(@class), " "), " a-price ") and not(contains(concat(" ", normalize-space(@class), " "), " a-text-price "))]/*[contains(concat(" ", normalize-space(@class), " "), " a-offscreen ")]');
        $price ??= $read('//*[@id="priceblock_dealprice" or @id="priceblock_ourprice" or @id="priceblock_saleprice"]');
        $image = $read('//*[@id="landingImage" or @id="imgBlkFront"]/@data-old-hires')
            ?? $read('//*[@id="landingImage" or @id="imgBlkFront"]/@src');
        $availability = $read('//*[@id="availability"]');

        return new ProductInfo(
            name: $name,
            description: $read('//*[@id="productDescription"]') ?? $read('//*[@id="feature-bullets"]'),
            url: $this->url,
            priceInCents: $price !== null ? $this->normalizePriceToCents($price) : null,
            priceCurrency: $price !== null && (str_contains($price, '€') || str_contains($price, 'EUR')) ? 'EUR' : null,
            imageUrl: $image,
            allImageUrls: $image !== null ? [$image] : [],
            availability: $availability !== null && preg_match('/currently unavailable|niet beschikbaar|niet op voorraad|derzeit nicht|actuellement indisponible/iu', $availability)
                ? ProductAvailability::OutOfStock : null,
        );
    }
}

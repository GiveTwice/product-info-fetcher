<?php

namespace Mattiasgeniar\ProductInfoFetcher\Parsers;

use Mattiasgeniar\ProductInfoFetcher\DataTransferObjects\ProductInfo;

class JsonLdParser
{
    public function __construct(
        private readonly string $html,
    ) {}

    public function parse(): ProductInfo
    {
        $productData = $this->extractProductData();

        if (! $productData) {
            return new ProductInfo;
        }

        return new ProductInfo(
            name: $productData['name'] ?? null,
            description: $productData['description'] ?? null,
            url: $productData['url'] ?? null,
            price: $this->extractPrice($productData),
            imageUrl: $this->extractImage($productData),
        );
    }

    private function extractProductData(): ?array
    {
        if (! preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $this->html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $jsonString) {
            $data = json_decode(trim($jsonString), true);

            if (! $data) {
                continue;
            }

            if (isset($data['@graph'])) {
                foreach ($data['@graph'] as $item) {
                    if ($this->isProductType($item)) {
                        return $item;
                    }
                }
            }

            if ($this->isProductType($data)) {
                return $data;
            }
        }

        return null;
    }

    private function isProductType(array $data): bool
    {
        $type = $data['@type'] ?? null;

        if (is_array($type)) {
            return in_array('Product', $type, true);
        }

        return $type === 'Product';
    }

    private function extractImage(array $data): ?string
    {
        if (! isset($data['image'])) {
            return null;
        }

        $image = $data['image'];

        if (is_string($image)) {
            return $image;
        }

        if (is_array($image)) {
            if (isset($image['url'])) {
                return $image['url'];
            }

            if (isset($image[0])) {
                return is_string($image[0]) ? $image[0] : ($image[0]['url'] ?? null);
            }
        }

        return null;
    }

    private function extractPrice(array $data): ?string
    {
        if (! isset($data['offers'])) {
            return null;
        }

        $offers = $data['offers'];

        if (isset($offers['price'])) {
            return $this->formatPrice($offers['price'], $offers['priceCurrency'] ?? null);
        }

        if (isset($offers[0]['price'])) {
            return $this->formatPrice($offers[0]['price'], $offers[0]['priceCurrency'] ?? null);
        }

        return null;
    }

    private function formatPrice(mixed $price, ?string $currency): string
    {
        $priceString = (string) $price;

        if ($currency) {
            return "{$currency} {$priceString}";
        }

        return $priceString;
    }
}

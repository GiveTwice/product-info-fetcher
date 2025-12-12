<?php

namespace Mattiasgeniar\ProductInfoFetcher\Parsers;

use Mattiasgeniar\ProductInfoFetcher\DataTransferObjects\ProductInfo;

class JsonLdParser
{
    public function __construct(
        private readonly string $html,
        private readonly ?string $currentUrl = null,
    ) {}

    public function parse(): ProductInfo
    {
        $productData = $this->extractProductData();

        if (! $productData) {
            return new ProductInfo;
        }

        $priceData = $this->extractPriceData($productData);

        return new ProductInfo(
            name: $productData['name'] ?? null,
            description: $productData['description'] ?? null,
            url: $productData['url'] ?? null,
            priceInCents: $priceData['priceInCents'],
            priceCurrency: $priceData['priceCurrency'],
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
                    if ($this->isProductGroupType($item)) {
                        return $this->extractFromProductGroup($item);
                    }
                }
            }

            if ($this->isProductType($data)) {
                return $data;
            }

            if ($this->isProductGroupType($data)) {
                return $this->extractFromProductGroup($data);
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

    private function isProductGroupType(array $data): bool
    {
        $type = $data['@type'] ?? null;

        if (is_array($type)) {
            return in_array('ProductGroup', $type, true);
        }

        return $type === 'ProductGroup';
    }

    private function extractFromProductGroup(array $productGroup): ?array
    {
        if (! isset($productGroup['hasVariant']) || ! is_array($productGroup['hasVariant'])) {
            return null;
        }

        foreach ($productGroup['hasVariant'] as $variant) {
            if (! $this->isProductType($variant)) {
                continue;
            }

            if ($this->currentUrl !== null && isset($variant['url'])) {
                $variantUrl = $this->normalizeUrl($variant['url']);
                $currentUrl = $this->normalizeUrl($this->currentUrl);

                if ($variantUrl === $currentUrl) {
                    return $variant;
                }
            }
        }

        foreach ($productGroup['hasVariant'] as $variant) {
            if ($this->isProductType($variant) && $this->hasAvailableOffer($variant)) {
                return $variant;
            }
        }

        return null;
    }

    private function normalizeUrl(string $url): string
    {
        $parsed = parse_url($url);
        $path = rtrim($parsed['path'] ?? '', '/');

        return ($parsed['host'] ?? '').$path;
    }

    private function hasAvailableOffer(array $product): bool
    {
        if (! isset($product['offers'])) {
            return false;
        }

        $offers = $product['offers'];
        $availability = $offers['availability'] ?? ($offers[0]['availability'] ?? null);

        if ($availability === null) {
            return isset($offers['price']) || isset($offers[0]['price']);
        }

        return str_contains(strtolower((string) $availability), 'instock');
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

    private function extractPriceData(array $data): array
    {
        $result = ['priceInCents' => null, 'priceCurrency' => null];

        if (! isset($data['offers'])) {
            return $result;
        }

        $offers = $data['offers'];

        if (isset($offers['price'])) {
            $result['priceInCents'] = $this->normalizePriceToCents($offers['price']);
            $result['priceCurrency'] = $offers['priceCurrency'] ?? null;
        } elseif (isset($offers[0]['price'])) {
            $result['priceInCents'] = $this->normalizePriceToCents($offers[0]['price']);
            $result['priceCurrency'] = $offers[0]['priceCurrency'] ?? null;
        }

        return $result;
    }

    private function normalizePriceToCents(mixed $price): int
    {
        if (is_int($price)) {
            return $price * 100;
        }

        if (is_float($price)) {
            return (int) round($price * 100);
        }

        $priceString = (string) $price;
        $priceString = preg_replace('/[^\d.,]/', '', $priceString);

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

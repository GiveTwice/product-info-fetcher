<?php

namespace GiveTwice\ProductInfoFetcher\Parsers;

use GiveTwice\ProductInfoFetcher\DataTransferObjects\ProductInfo;
use GiveTwice\ProductInfoFetcher\Enum\NormalizesSchemaValues;
use GiveTwice\ProductInfoFetcher\Enum\ProductAvailability;
use GiveTwice\ProductInfoFetcher\Enum\ProductCondition;

class JsonLdParser implements ParserInterface
{
    use NormalizesPrices;
    use NormalizesSchemaValues;

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
        $offerData = $this->extractOfferData($productData);
        $ratingData = $this->extractRatingData($productData);

        $allImages = $this->extractAllImages($productData);

        return new ProductInfo(
            name: $productData['name'] ?? null,
            description: $productData['description'] ?? null,
            url: $productData['url'] ?? null,
            priceInCents: $priceData['priceInCents'],
            priceCurrency: $priceData['priceCurrency'],
            imageUrl: $allImages[0] ?? null,
            allImageUrls: $allImages,
            brand: $this->extractBrand($productData),
            sku: $productData['sku'] ?? null,
            gtin: $this->extractGtin($productData),
            availability: $offerData['availability'],
            condition: $offerData['condition'],
            rating: $ratingData['rating'],
            reviewCount: $ratingData['reviewCount'],
        );
    }

    private function extractProductData(): ?array
    {
        if (! preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $this->html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $jsonString) {
            $data = json_decode(trim($jsonString), true);

            if (! is_array($data)) {
                continue;
            }

            $product = $this->findProduct($data);
            if ($product !== null) {
                return $product;
            }
        }

        return null;
    }

    private function findProduct(array $data): ?array
    {
        if ($this->isProductType($data)) {
            return $data;
        }
        if ($this->isProductGroupType($data)) {
            return $this->extractFromProductGroup($data);
        }
        // Prefer the page's main entity to recommendation lists.
        if (isset($data['mainEntity']) && is_array($data['mainEntity'])) {
            $product = $this->findProduct($data['mainEntity']);
            if ($product !== null) {
                return $product;
            }
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $product = $this->findProduct($value);
                if ($product !== null) {
                    return $product;
                }
            }
        }

        return null;
    }

    private function isProductType(array $data): bool
    {
        return $this->matchesSchemaType($data['@type'] ?? null, 'Product');
    }

    private function isProductGroupType(array $data): bool
    {
        return $this->matchesSchemaType($data['@type'] ?? null, 'ProductGroup');
    }

    private function matchesSchemaType(mixed $type, string $expected): bool
    {
        if ($type === null) {
            return false;
        }

        if (is_array($type)) {
            foreach ($type as $t) {
                if (is_string($t) && self::stripSchemaOrgPrefix($t) === $expected) {
                    return true;
                }
            }

            return false;
        }

        return is_string($type) && self::stripSchemaOrgPrefix($type) === $expected;
    }

    private function extractFromProductGroup(array $productGroup): ?array
    {
        if (! isset($productGroup['hasVariant']) || ! is_array($productGroup['hasVariant'])) {
            return null;
        }

        foreach ($this->variants($productGroup) as $variant) {
            if (! $this->isProductType($variant)) {
                continue;
            }

            if ($this->currentUrl !== null && isset($variant['url'])) {
                $variantUrl = $this->normalizeUrl($variant['url']);
                $currentUrl = $this->normalizeUrl($this->currentUrl);

                if ($variantUrl === $currentUrl) {
                    return $this->inheritGroup($productGroup, $variant);
                }
            }
        }

        foreach ($this->variants($productGroup) as $variant) {
            if ($this->isProductType($variant) && $this->hasAvailableOffer($variant)) {
                return $this->inheritGroup($productGroup, $variant);
            }
        }

        foreach ($this->variants($productGroup) as $variant) {
            if ($this->isProductType($variant)) {
                return $this->inheritGroup($productGroup, $variant);
            }
        }

        return null;
    }

    private function variants(array $group): array
    {
        $variants = $group['hasVariant'];

        return isset($variants['@type']) ? [$variants] : array_filter($variants, 'is_array');
    }

    private function inheritGroup(array $group, array $variant): array
    {
        // Identity, URL and offers belong to the variant. Only descriptive parent
        // metadata is inherited, so an unavailable variant cannot get a sibling price.
        return array_replace(array_intersect_key($group, array_flip([
            'name', 'description', 'brand', 'image', 'aggregateRating',
        ])), array_filter($variant, fn ($value) => $value !== null));
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

    private function extractAllImages(array $data): array
    {
        if (! isset($data['image'])) {
            return [];
        }

        $image = $data['image'];

        if (is_string($image)) {
            return [$image];
        }

        if (! is_array($image)) {
            return [];
        }

        if (isset($image['url'])) {
            return [$image['url']];
        }

        $images = [];
        foreach ($image as $img) {
            if (is_string($img)) {
                $images[] = $img;
            } elseif (is_array($img) && isset($img['url'])) {
                $images[] = $img['url'];
            }
        }

        return $images;
    }

    private function extractPriceData(array $data): array
    {
        if (! isset($data['offers'])) {
            return [
                'priceInCents' => null,
                'priceCurrency' => null,
            ];
        }

        $offers = $data['offers'];
        foreach (isset($offers[0]) ? $offers : [$offers] as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $spec = $offer['priceSpecification'] ?? [];
            if (isset($spec[0])) {
                $spec = $spec[0];
            }
            $price = $offer['price'] ?? $offer['lowPrice'] ?? $spec['price'] ?? null;
            if ($price !== null) {
                return [
                    'priceInCents' => $this->normalizePriceToCents($price),
                    'priceCurrency' => $offer['priceCurrency'] ?? $spec['priceCurrency'] ?? null,
                ];
            }
        }

        return ['priceInCents' => null, 'priceCurrency' => null];
    }

    private function extractBrand(array $data): ?string
    {
        if (! isset($data['brand'])) {
            return null;
        }

        $brand = $data['brand'];

        if (is_string($brand)) {
            return $brand;
        }

        if (is_array($brand)) {
            return $brand['name'] ?? null;
        }

        return null;
    }

    private function extractGtin(array $data): ?string
    {
        return $data['gtin'] ?? $data['gtin14'] ?? $data['gtin13'] ?? $data['gtin12'] ?? $data['gtin8'] ?? null;
    }

    private function extractOfferData(array $data): array
    {
        if (! isset($data['offers'])) {
            return [
                'availability' => null,
                'condition' => ProductCondition::fromString($data['itemCondition'] ?? null),
            ];
        }

        $offer = $data['offers'][0] ?? $data['offers'];

        return [
            'availability' => ProductAvailability::fromString($offer['availability'] ?? null),
            'condition' => ProductCondition::fromString($offer['itemCondition'] ?? $data['itemCondition'] ?? null),
        ];
    }

    private function extractRatingData(array $data): array
    {
        if (! isset($data['aggregateRating'])) {
            return [
                'rating' => null,
                'reviewCount' => null,
            ];
        }

        $rating = $data['aggregateRating'];
        $reviewCount = $rating['reviewCount'] ?? $rating['ratingCount'] ?? null;

        return [
            'rating' => isset($rating['ratingValue']) ? (float) $rating['ratingValue'] : null,
            'reviewCount' => $reviewCount !== null ? (int) $reviewCount : null,
        ];
    }
}

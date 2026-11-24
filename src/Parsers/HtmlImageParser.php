<?php

namespace GiveTwice\ProductInfoFetcher\Parsers;

use DOMDocument;
use DOMElement;
use DOMXPath;
use GiveTwice\ProductInfoFetcher\DataTransferObjects\ProductInfo;

class HtmlImageParser implements ParserInterface
{
    use EscapesXPath;

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
        $allImages = $this->extractAllImages();

        return new ProductInfo(
            imageUrl: $allImages[0] ?? null,
            allImageUrls: $allImages,
        );
    }

    private function extractAllImages(): array
    {
        $images = [];

        $images = array_merge($images, $this->extractAmazonLandingImage());
        $images = array_merge($images, $this->extractByProductImageIds());
        $images = array_merge($images, $this->extractByProductImageClasses());
        $images = array_merge($images, $this->extractByDataAttributes());

        $images = array_filter(array_map('trim', $images), $this->isValidImageUrl(...));

        return array_values(array_unique($images));
    }

    private function extractAmazonLandingImage(): array
    {
        $images = [];

        $landingImage = $this->xpath->query("//img[@id='landingImage']")->item(0);

        if ($landingImage instanceof DOMElement) {
            $hiRes = $landingImage->getAttribute('data-old-hires');
            if ($hiRes !== '') {
                $images[] = $hiRes;
            }

            $src = $landingImage->getAttribute('src');
            if ($src !== '' && $src !== $hiRes) {
                $images[] = $src;
            }

            $dynamicImage = $landingImage->getAttribute('data-a-dynamic-image');
            if ($dynamicImage !== '') {
                $images = array_merge($images, $this->extractFromDynamicImageJson($dynamicImage));
            }
        }

        return $images;
    }

    private function extractFromDynamicImageJson(string $json): array
    {
        $decoded = json_decode(html_entity_decode($json), true);

        if (! is_array($decoded)) {
            return [];
        }

        $imagesWithSize = [];
        foreach ($decoded as $url => $dimensions) {
            if (is_array($dimensions) && count($dimensions) >= 2) {
                $imagesWithSize[$url] = $dimensions[0] * $dimensions[1];
            }
        }

        arsort($imagesWithSize);

        return array_keys($imagesWithSize);
    }

    private function extractByProductImageIds(): array
    {
        $images = [];

        $productImageIds = [
            'main-image',
            'product-image',
            'hero-image',
            'mainImage',
            'productImage',
            'heroImage',
            'imgBlkFront',
        ];

        foreach ($productImageIds as $id) {
            $escapedId = $this->escapeXPathString($id);
            $img = $this->xpath->query("//img[@id={$escapedId}]")->item(0);

            if ($img instanceof DOMElement) {
                $images = array_merge($images, $this->extractImageUrls($img));
            }
        }

        return $images;
    }

    private function extractByProductImageClasses(): array
    {
        $images = [];

        $classPatterns = [
            'product-image',
            'main-image',
            'hero-image',
            'gallery-image',
            'primary-image',
        ];

        foreach ($classPatterns as $class) {
            $escapedClass = $this->escapeXPathString($class);
            $nodes = $this->xpath->query("//img[contains(@class, {$escapedClass})]");

            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $images = array_merge($images, $this->extractImageUrls($node));
                }
            }
        }

        return $images;
    }

    private function extractByDataAttributes(): array
    {
        $images = [];

        $dataAttributes = [
            'data-zoom-image',
            'data-large-image',
            'data-full-image',
            'data-hires',
            'data-src',
        ];

        foreach ($dataAttributes as $attr) {
            $nodes = $this->xpath->query("//img[@{$attr}]");

            foreach ($nodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $fallbacks = $attr === 'data-src' ? ['src'] : [];
                $images[] = $this->firstValidUrl($node, $attr, ...$fallbacks);
            }
        }

        return array_filter($images);
    }

    private function extractImageUrls(DOMElement $img): array
    {
        $urls = [];

        $hiResAttributes = ['data-old-hires', 'data-zoom-image', 'data-large-image', 'data-hires'];
        foreach ($hiResAttributes as $attr) {
            $urls[] = $this->firstValidUrl($img, $attr);
        }

        $urls[] = $this->firstValidUrl($img, 'data-src', 'src');

        return array_filter($urls);
    }

    private function firstValidUrl(DOMElement $img, string ...$attributes): ?string
    {
        foreach ($attributes as $attribute) {
            $value = trim($img->getAttribute($attribute));

            if ($this->isValidImageUrl($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Checks that an attribute value can be used as an image URL.
     *
     * Lazy-loading scripts leave values such as "false" or "#" in data-src,
     * and those must not be returned as images. Relative paths are kept as
     * written, because no parser in this package resolves them against the
     * page URL.
     */
    private function isValidImageUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (preg_match('/\.(gif)$/i', $url) && preg_match('/(spinner|loading|placeholder)/i', $url)) {
            return false;
        }

        if (str_starts_with($url, '//')) {
            return $this->hasValidHost('https:'.$url);
        }

        if (preg_match('#^https?://#i', $url)) {
            return $this->hasValidHost($url);
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            return false;
        }

        $path = preg_replace('/[?#].*/s', '', $url);

        if (str_contains($path, '/')) {
            return true;
        }

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|avif|svg)$/i', $path);
    }

    private function hasValidHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host)) {
            return false;
        }

        if (preg_match('/^\[(.+)\]$/', $host, $matches)) {
            return filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }

        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}

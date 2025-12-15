<?php

namespace Mattiasgeniar\ProductInfoFetcher\Parsers;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Mattiasgeniar\ProductInfoFetcher\DataTransferObjects\ProductInfo;

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

        return array_values(array_unique(array_filter($images)));
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
                if ($node instanceof DOMElement) {
                    $value = $node->getAttribute($attr);
                    if ($value !== '' && $this->isValidImageUrl($value)) {
                        $images[] = $value;
                    }
                }
            }
        }

        return $images;
    }

    private function extractImageUrls(DOMElement $img): array
    {
        $urls = [];

        $hiResAttributes = ['data-old-hires', 'data-zoom-image', 'data-large-image', 'data-hires'];
        foreach ($hiResAttributes as $attr) {
            $value = $img->getAttribute($attr);
            if ($value !== '' && $this->isValidImageUrl($value)) {
                $urls[] = $value;
            }
        }

        $src = $img->getAttribute('data-src') ?: $img->getAttribute('src');
        if ($src !== '' && $this->isValidImageUrl($src)) {
            $urls[] = $src;
        }

        return $urls;
    }

    private function isValidImageUrl(string $url): bool
    {
        if (str_starts_with($url, 'data:')) {
            return false;
        }

        if (preg_match('/\.(gif)$/i', $url) && preg_match('/(spinner|loading|placeholder)/i', $url)) {
            return false;
        }

        return true;
    }
}

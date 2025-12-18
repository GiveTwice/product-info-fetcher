# Product Info Fetcher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/givetwice/product-info-fetcher.svg?style=flat-square)](https://packagist.org/packages/givetwice/product-info-fetcher)
[![Tests](https://img.shields.io/github/actions/workflow/status/GiveTwice/product-info-fetcher/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/GiveTwice/product-info-fetcher/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/givetwice/product-info-fetcher.svg?style=flat-square)](https://packagist.org/packages/givetwice/product-info-fetcher)

A PHP package that fetches product information from any URL and returns structured data. It parses JSON-LD structured data, Open Graph meta tags, and HTML image elements to extract product details like name, description, price, and images.

## Installation

```bash
composer require givetwice/product-info-fetcher
```

## Usage

### Basic

```php
use GiveTwice\ProductInfoFetcher\ProductInfoFetcher;

$product = (new ProductInfoFetcher('https://example.com/product'))
    ->fetchAndParse();

// Core fields
echo $product->name;          // "iPhone 15 Pro"
echo $product->description;   // "The latest iPhone with A17 Pro chip"
echo $product->priceInCents;  // 99900
echo $product->priceCurrency; // "USD"
echo $product->url;           // "https://example.com/product"
echo $product->imageUrl;      // "https://example.com/images/iphone.jpg"
echo $product->allImageUrls;  // ["https://...", "https://..."] (all found images)

// Additional fields
echo $product->brand;         // "Apple"
echo $product->sku;           // "IPHONE15PRO-256"
echo $product->gtin;          // "0194253392200"
echo $product->availability;  // ProductAvailability::InStock
echo $product->condition;     // ProductCondition::New
echo $product->rating;        // 4.8
echo $product->reviewCount;   // 1250

// For display purposes
echo $product->getFormattedPrice(); // "USD 999.00"
```

### Pricing

Prices are stored as integers in cents to avoid floating-point precision issues. This follows the same approach used by payment systems like Stripe.

```php
$product->priceInCents;  // 139099 (integer)
$product->priceCurrency; // "EUR" (ISO 4217 currency code)

// For display
$product->getFormattedPrice(); // "EUR 1390.99"

// For calculations (no floating-point issues)
$total = $product->priceInCents * $quantity;
$displayPrice = number_format($total / 100, 2);
```

The parser normalizes various price formats:
- String prices: `"999.00"` → `99900`
- Integer prices: `1479` → `147900`
- European format: `"1.234,56"` → `123456`

### Availability & Condition

The `availability` and `condition` fields return enum instances:

```php
use GiveTwice\ProductInfoFetcher\Enum\ProductAvailability;
use GiveTwice\ProductInfoFetcher\Enum\ProductCondition;

// Availability values
ProductAvailability::InStock
ProductAvailability::OutOfStock
ProductAvailability::PreOrder
ProductAvailability::BackOrder
ProductAvailability::Discontinued

// Condition values
ProductCondition::New
ProductCondition::Used
ProductCondition::Refurbished
ProductCondition::Damaged

// Usage
if ($product->availability === ProductAvailability::InStock) {
    // Product is available
}

// Get string value
$product->availability?->value; // "InStock"
```

### Multiple Images

The `imageUrl` field contains the primary image (first found). The `allImageUrls` array contains all unique images found across all sources (JSON-LD, meta tags, and HTML image elements):

```php
$product->imageUrl;      // Primary image (first found)
$product->allImageUrls;  // All unique images from all sources

// Example: different resolutions from different sources
// [0] "http://example.com/product-370x370.jpg"  (from JSON-LD)
// [1] "//example.com/product-big.jpg"           (from og:image)
```

This is useful when sources provide different image sizes or when you want fallback options.

### With Options

```php
$product = (new ProductInfoFetcher('https://example.com/product'))
    ->setUserAgent('MyApp/1.0 (https://myapp.com)')
    ->setTimeout(10)
    ->setConnectTimeout(5)
    ->setAcceptLanguage('nl-BE,nl;q=0.9,en;q=0.8')
    ->fetchAndParse();
```

### Separate Fetch and Parse

```php
$fetcher = new ProductInfoFetcher('https://example.com/product');
$fetcher->fetch();
$product = $fetcher->parse();
```

### Parse Existing HTML

```php
$product = (new ProductInfoFetcher())
    ->setHtml($html)
    ->parse();
```

### Access as Array

```php
$product = (new ProductInfoFetcher($url))->fetchAndParse();

$data = $product->toArray();
// [
//     'name' => 'iPhone 15 Pro',
//     'description' => 'The latest iPhone...',
//     'url' => 'https://example.com/product',
//     'priceInCents' => 99900,
//     'priceCurrency' => 'USD',
//     'imageUrl' => 'https://example.com/image.jpg',
//     'allImageUrls' => ['https://...', 'https://...'],
//     'brand' => 'Apple',
//     'sku' => 'IPHONE15PRO-256',
//     'gtin' => '0194253392200',
//     'availability' => 'InStock',
//     'condition' => 'New',
//     'rating' => 4.8,
//     'reviewCount' => 1250,
// ]
```

### Check Completeness

```php
if ($product->isComplete()) {
    // Product has name and description
}
```

## How It Works

The package attempts to extract product information in the following order:

1. **JSON-LD** - Looks for `<script type="application/ld+json">` with `@type: Product` or `@type: ProductGroup`
2. **Meta Tags** - Falls back to Open Graph (`og:`), Twitter Cards (`twitter:`), and standard meta tags
3. **HTML Images** - Extracts product images directly from `<img>` elements using common patterns (Amazon's `landingImage`, product image classes, data attributes)

If the first parser returns complete data (name and description), it returns immediately. Otherwise, it merges results from multiple parsers. Images from all three sources are always combined.

### Supported Structures

- **schema.org Product** - Standard product markup including `offers`, `brand`, `sku`, `gtin`, `aggregateRating`
- **schema.org ProductGroup** - Product variants (e.g., bol.com) with `hasVariant[]`
- **Open Graph** - `og:title`, `og:description`, `og:image`, `product:price:amount`, `product:price:currency`, `product:availability`, `product:condition`

Both short (`"@type": "Product"`) and full URL (`"@type": "http://schema.org/Product"`) formats are supported for all schema.org types.

### Meta Tag Fallback Chain

When JSON-LD is unavailable, the parser tries multiple sources:

- **name**: `og:title` → `twitter:title` → `<title>`
- **description**: `og:description` → `twitter:description` → `<meta name="description">`
- **image**: `og:image` → `twitter:image` → HTML image elements
- **url**: `<link rel="canonical">` → `og:url`

### HTML Image Extraction

For sites without structured data or meta tags (e.g., Amazon), the package extracts images directly from HTML:

- **Amazon pattern**: `<img id="landingImage">` with `data-old-hires` for high-res images
- **Common IDs**: `main-image`, `product-image`, `hero-image`
- **Common classes**: `product-image`, `main-image`, `gallery-image`
- **Data attributes**: `data-zoom-image`, `data-large-image`, `data-src`

High-resolution images are prioritized when available.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [GiveTwice](https://github.com/GiveTwice)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

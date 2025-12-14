# Product Info Fetcher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mattiasgeniar/product-info-fetcher.svg?style=flat-square)](https://packagist.org/packages/mattiasgeniar/product-info-fetcher)
[![Tests](https://img.shields.io/github/actions/workflow/status/mattiasgeniar/product-info-fetcher/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mattiasgeniar/product-info-fetcher/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/mattiasgeniar/product-info-fetcher.svg?style=flat-square)](https://packagist.org/packages/mattiasgeniar/product-info-fetcher)

A PHP package that fetches product information from any URL and returns structured data. It parses JSON-LD structured data and Open Graph meta tags to extract product details like name, description, price, and images.

## Installation

```bash
composer require mattiasgeniar/product-info-fetcher
```

## Usage

### Basic

```php
use Mattiasgeniar\ProductInfoFetcher\ProductInfoFetcherClass;

$product = (new ProductInfoFetcherClass('https://example.com/product'))
    ->fetchAndParse();

// Core fields
echo $product->name;          // "iPhone 15 Pro"
echo $product->description;   // "The latest iPhone with A17 Pro chip"
echo $product->priceInCents;  // 99900
echo $product->priceCurrency; // "USD"
echo $product->url;           // "https://example.com/product"
echo $product->imageUrl;      // "https://example.com/images/iphone.jpg"

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
use Mattiasgeniar\ProductInfoFetcher\Enum\ProductAvailability;
use Mattiasgeniar\ProductInfoFetcher\Enum\ProductCondition;

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

### With Options

```php
$product = (new ProductInfoFetcherClass('https://example.com/product'))
    ->setUserAgent('MyApp/1.0 (https://myapp.com)')
    ->setTimeout(10)
    ->setConnectTimeout(5)
    ->setAcceptLanguage('nl-BE,nl;q=0.9,en;q=0.8')
    ->fetchAndParse();
```

### Separate Fetch and Parse

```php
$fetcher = new ProductInfoFetcherClass('https://example.com/product');
$fetcher->fetch();
$product = $fetcher->parse();
```

### Parse Existing HTML

```php
$product = (new ProductInfoFetcherClass())
    ->setHtml($html)
    ->parse();
```

### Access as Array

```php
$product = (new ProductInfoFetcherClass($url))->fetchAndParse();

$data = $product->toArray();
// [
//     'name' => 'iPhone 15 Pro',
//     'description' => 'The latest iPhone...',
//     'url' => 'https://example.com/product',
//     'priceInCents' => 99900,
//     'priceCurrency' => 'USD',
//     'imageUrl' => 'https://example.com/image.jpg',
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
    // Product has name, description, and price
}
```

## How It Works

The package attempts to extract product information in the following order:

1. **JSON-LD** - Looks for `<script type="application/ld+json">` with `@type: Product` or `@type: ProductGroup`
2. **Meta Tags** - Falls back to Open Graph (`og:`), Twitter Cards (`twitter:`), and standard meta tags

If the first parser returns complete data (name, description, and price), it returns immediately. Otherwise, it merges results from multiple parsers.

### Supported Structures

- **schema.org Product** - Standard product markup including `offers`, `brand`, `sku`, `gtin`, `aggregateRating`
- **schema.org ProductGroup** - Product variants (e.g., bol.com) with `hasVariant[]`
- **Open Graph** - `og:title`, `og:description`, `og:image`, `product:price:amount`, `product:price:currency`, `product:availability`, `product:condition`

### Meta Tag Fallback Chain

When JSON-LD is unavailable, the parser tries multiple sources:

- **name**: `og:title` → `twitter:title` → `<title>`
- **description**: `og:description` → `twitter:description` → `<meta name="description">`
- **image**: `og:image` → `twitter:image`
- **url**: `<link rel="canonical">` → `og:url`

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

- [Mattias Geniar](https://github.com/mattiasgeniar)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

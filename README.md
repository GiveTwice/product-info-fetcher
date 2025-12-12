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

echo $product->name;        // "iPhone 15 Pro"
echo $product->description; // "The latest iPhone with A17 Pro chip"
echo $product->price;       // "EUR 999.00"
echo $product->url;         // "https://example.com/product"
echo $product->imageUrl;    // "https://example.com/images/iphone.jpg"
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
//     'price' => 'EUR 999.00',
//     'imageUrl' => 'https://example.com/image.jpg',
// ]
```

## How It Works

The package attempts to extract product information in the following order:

1. **JSON-LD** - Looks for `<script type="application/ld+json">` with `@type: Product`
2. **Meta Tags** - Falls back to Open Graph (`og:`), Twitter Cards (`twitter:`), and standard meta tags

If the first parser returns complete data (name, description, and price), it returns immediately. Otherwise, it merges results from multiple parsers.

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

# Product Info Fetcher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/givetwice/product-info-fetcher.svg?style=flat-square)](https://packagist.org/packages/givetwice/product-info-fetcher)
[![Tests](https://img.shields.io/github/actions/workflow/status/GiveTwice/product-info-fetcher/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/GiveTwice/product-info-fetcher/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/givetwice/product-info-fetcher.svg?style=flat-square)](https://packagist.org/packages/givetwice/product-info-fetcher)

A PHP package that fetches product information from any URL and returns structured data. It parses JSON-LD structured data, Open Graph meta tags, and HTML image elements to extract product details like name, description, price, and images.

## Shop support and safe fetching

One fetch makes one HTTP GET chain (up to five ordinary redirects), with a normal
Chrome user agent and no retries. An opt-in fallback can make one browser navigation
after HTTP 403. `preferHeadless()` makes only the browser navigation. A browser also
loads scripts and supporting resources; it does not mean one network subrequest.
No CAPTCHA solving, stealth plugin, automation-hiding flags or consent clicks are used.

| Shop | Extraction | Verified from this box |
| --- | --- | --- |
| bol.com BE + NL | JSON-LD and product/OG metadata when accessible | Both HTTP and Chrome blocked (403); successful parsing unverified |
| coolblue.be NL + FR | JSON-LD, metadata, images, variants and SoldOut availability | Product name, EUR price and image passed over HTTP |
| amazon.com.be, amazon.nl, amazon.de, amazon.fr | Isolated main-product HTML parser plus structured metadata | Two products per domain passed with plain Chrome; HTTP returned challenge pages |
| IKEA BE | Generic structured metadata when accessible | Sampled NL/FR URLs blocked (403); successful parsing unverified |
| Zalando, MediaMarkt, Fnac, Vanden Borre, Dreamland, AVA | Generic parsers only | No shop-specific support claim or live coverage in this change |

Observations are from a datacenter IP, no proxy, on 2026-10-31. Production uses a
proxy, so a block here does not establish a production block. Successful access can
also change. Bol may need an authorized official catalog integration if production
is blocked; this package does not include API credentials or an official API client.

### Explicit outcomes and diagnostics

Existing `fetch()`, `parse()` and `fetchAndParse()` calls remain available, including
HTTP exceptions on the legacy fetch path. To receive outcomes for HTTP, network and
browser failures instead, use the additive `fetchResult()` method:

```php
use GiveTwice\ProductInfoFetcher\Enum\FetchOutcome;

$product = (new ProductInfoFetcher($url))->fetchResult();

if ($product->outcome === FetchOutcome::Success) {
    // Use product name, priceInCents, imageUrl and optional description.
}
if ($product->outcome === FetchOutcome::Consent && $product->hasProductData()) {
    // Product data was readable without accepting consent. Apply your app policy.
}

$product->finalUrl;   // Effective transport URL, distinct from canonical product url
$product->httpStatus; // Last transport status; null without a response
$product->attempts;   // Ordered TransportAttempt objects, HTTP and browser separately
```

| Outcome | Meaning |
| --- | --- |
| `success` | Nonempty name, price and image were extracted |
| `incomplete` | Page fetched, but one of those fields is absent, including generic storefronts |
| `blocked` | HTTP 401/403/429 or recognized challenge page, including HTTP 200 challenges |
| `consent` | Recognized consent interstitial; any readable product fields are retained |
| `removed` | HTTP 404/410 or recognized page-not-found text |
| `error` | Network/browser failure, another HTTP error, or a timeout without useful product data |

Unavailable products retain their data and `availability` separately from the fetch
outcome. A timed-out browser navigation can still return usable product data; inspect
`attempts[*]->timedOut` and `error` rather than assuming success means no timeout.
Attempts expose `transport`, `statusCode`, `finalUrl`, `durationMs`, `timedOut`, and a
credential-free error category. URL fields can contain user-supplied queries: redact
them before logging. `toArray()` includes all new fields, with enum string values.
`isComplete()` keeps its historical name-plus-description meaning; use
`hasProductData()` for the name-plus-price-plus-image predicate.

Page detection is conservative and cannot recognize every shop's changing walls.
Do not treat a page title alone as product success. Map blocked/consent/removed/error
and incomplete results to a friendly manual entry form instead of retrying in a loop.

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

### Custom Headers

You can configure additional HTTP headers when a shop requires them:

```php
$product = (new ProductInfoFetcher('https://example.com/product'))
    ->withExtraHeaders([
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Cache-Control' => 'no-cache',
        'DNT' => '1',
        'Sec-CH-UA' => '"Google Chrome";v="131", "Chromium";v="131"',
        'Sec-CH-UA-Mobile' => '?0',
        'Sec-CH-UA-Platform' => '"macOS"',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-Site' => 'none',
        'Sec-Fetch-User' => '?1',
        'Upgrade-Insecure-Requests' => '1',
    ])
    ->fetchAndParse();
```

Extra headers are merged with defaults and can override them. Multiple `withExtraHeaders()` calls can be chained.

### HTTP Proxy

Route requests through an HTTP proxy:

```php
$product = (new ProductInfoFetcher('https://example.com/product'))
    ->viaProxy('http://proxy.example.com:3128')
    ->fetchAndParse();

// With authentication
$product = (new ProductInfoFetcher('https://example.com/product'))
    ->viaProxy('http://username:password@proxy.example.com:3128')
    ->fetchAndParse();
```

The proxy is used for both regular HTTP requests (via Guzzle/cURL) and headless browser requests (via Chrome's `--proxy-server` flag). Proxy authentication is handled automatically.

### Headless Browser

Some shops render their product data with JavaScript. An optional plain Puppeteer browser can read that DOM. It does not solve challenges, click consent, or hide automation.

**Prefer Headless (for pages needing JavaScript):**

```php
$product = (new ProductInfoFetcher('https://example.com/product'))
    ->preferHeadless()
    ->fetchAndParse();
```

Use `preferHeadless()` to skip HTTP and make one browser navigation directly. This worked for the sampled Amazon pages from this box. It does not guarantee access from another IP or at another time.

**Fallback Mode:**

```php
$product = (new ProductInfoFetcher('https://example.com/product'))
    ->enableHeadlessFallback()
    ->fetchAndParse();
```

When enabled, the fetcher will:
1. First attempt a normal HTTP request
2. If blocked with a 403 status, automatically retry using a headless Chrome browser
3. Parse the resulting HTML as usual

Fallback is opt in and limited to one browser navigation after HTTP 403. HTTP 200 challenge pages return `blocked`; callers can choose `preferHeadless()` for future user actions. There are no automatic retries or consent interactions.

**Requirements:**

To use the headless fallback, you need Node.js 18+ and Puppeteer installed:

```bash
npm install
```

On Linux servers, you may also need system dependencies for headless Chrome:

```bash
# Ubuntu 24.04+
apt-get install -y libnss3 libatk1.0-0t64 libatk-bridge2.0-0t64 \
  libcups2t64 libdrm2 libxkbcommon0 libxcomposite1 \
  libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2t64 \
  libpango-1.0-0 libcairo2

# Ubuntu 22.04 and earlier
apt-get install -y libnss3 libatk1.0-0 libatk-bridge2.0-0 \
  libcups2 libdrm2 libxkbcommon0 libxcomposite1 \
  libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2
```

**Custom Paths:**

```php
$product = (new ProductInfoFetcher('https://example.com/product'))
    ->enableHeadlessFallback()
    ->setNodeBinary('/usr/local/bin/node')
    ->setChromePath('/usr/bin/chromium')
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
vendor/bin/pest
vendor/bin/pint
# Browser tests use only a local HTTP test server, no retailer requests:
npm install
CHROME_PATH=/usr/bin/google-chrome node --test tests/Browser/fetch-html.test.cjs

# Explicit opt-in retailer checks. Failures, including blocks, fail the assertions:
LIVE=1 vendor/bin/pest --group=live
LIVE=1 LIVE_BROWSER=1 CHROME_PATH=/usr/bin/google-chrome vendor/bin/pest --group=live --filter=amazon
```

The default suite replays saved real HTML without network access. CI always skips
live tests even if `LIVE=1` is set. `tests/Live/urls.php` contains the product list.
`LIVE_BROWSER=1` uses a single direct browser navigation per URL. Optional
`LIVE_REPORT=/tmp/product-info-live.jsonl` records each result and transport telemetry.
See [fixture provenance and coverage gaps](tests/Fixtures/Shops/README.md).

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

# Changelog

All notable changes to `product-info-fetcher` will be documented in this file.

## 0.5.0 (unreleased)

- Add `fetchResult()` for explicit success, incomplete, blocked, consent, removed
  and error outcomes while preserving legacy HTTP exceptions in `fetchAndParse()`.
- Add final URL, response status and independent HTTP/browser attempt diagnostics
  to ProductInfo and its serialized array. Retain browser DOM and diagnostics on
  navigation timeout. Remove stealth and automation-hiding browser settings.
- Read recursive JSON-LD, aggregate/specification prices, OG price metadata and
  ProductGroup parent metadata. Merge missing fields from all parsers.
- Isolate Amazon HTML extraction for BE/NL/DE/FR. Add real offline shop captures,
  opt-in live tests and local browser timeout tests. Map Schema.org SoldOut.

Migration: existing methods and constructor arguments remain available. Call
`fetchResult()` and branch on `FetchOutcome` to avoid exceptions for expected fetch
failures. `toArray()` now has additive keys; update exact array consumers. Success
means name, price and image; `isComplete()` retains its old semantics. Use
`hasProductData()` when deciding whether to prefill a wishlist. Browser users should
run `npm install` to remove the previous stealth dependencies. No official catalog
API integration is included, and blocked pages are never reported as products.

## Add support for preferHeadless() - 2025-12-22

When used with `preferHeadless()`, will avoid Guzzle/libcurl altogether and *only* use Chromium headless.

## Add headless Chrome fallback - 2025-12-22

Fallback to Puppeteer (headless Chrome) when libcurl fails to retrieve a valid HTML payload

## 0.1 first release - 2025-12-17

First release of the package, will likely contain breaking changes in the near future.

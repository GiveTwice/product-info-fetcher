# Changelog

All notable changes to `product-info-fetcher` will be documented in this file.

## Add support for preferHeadless() - 2025-12-22

When used with `preferHeadless()`, will avoid Guzzle/libcurl altogether and *only* use Chromium headless.

## Add headless Chrome fallback - 2025-12-22

Fallback to Puppeteer (headless Chrome) when libcurl fails to retrieve a valid HTML payload

## 0.1 first release - 2025-12-17

First release of the package, will likely contain breaking changes in the near future.

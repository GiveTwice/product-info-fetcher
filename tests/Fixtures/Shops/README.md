# Shop capture provenance

`manifest.json` records the public product URL, capture time, HTTP status, transport
(default HTTP unless stated), and expected fields. Captured on 2026-10-31 from a
Hetzner datacenter IP, without a proxy. No production environment or credentials
were accessed. Chrome is `/usr/bin/google-chrome`, with plain Puppeteer, no stealth
plugin, no consent clicks, no challenge solving and no request retries.

These are reduced real page captures, not invented successful responses:

- Bol BE/NL: two LEGO products per locale, all HTTP 403 block pages.
- Coolblue BE: WH-1000XM5 black in NL and FR, its silver variant, LinkBuds S,
  and an unavailable iPhone 12. Product JSON-LD, metadata and HTML are retained.
- Amazon BE/NL/DE/FR: two products per domain captured with Chrome, plus their
  plain HTTP challenge responses. The browser captures retain original product
  title, price containers (including sale/list prices), main image, description,
  availability and public metadata. Other page sections were removed.
- IKEA BE: KALLAX in NL/FR and BILLY in NL, all HTTP 403 block pages.

Non-JSON-LD scripts, styles and SVGs were removed. Hidden challenge input values
were redacted, unknown metadata tokens removed, and the datacenter IP replaced
with a documentation IP. No session cookies or response headers are saved.

The small files in `../Amazon/` are constructed selector regression cases, not
real captures. Recursive schema, variant inheritance, localized consent walls,
removed pages and consent with readable product data also have constructed tests.
Real consent-wall and removed-product captures for every shop were not obtainable
in this environment. Bol successful page extraction and the other optional shops
remain unverified; do not treat a blocked response as a successful product fixture.

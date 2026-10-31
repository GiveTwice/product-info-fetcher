const puppeteer = require('puppeteer');

let request;
try {
    request = JSON.parse(process.argv[2]);
} catch (e) {
    console.log(JSON.stringify({
        success: false,
        error: 'Invalid JSON input: ' + e.message,
    }));
    process.exit(1);
}

if (!request || !request.url) {
    console.log(JSON.stringify({
        success: false,
        error: 'Missing required parameter: url',
    }));
    process.exit(1);
}

// Domains whose requests we abort to avoid waiting on background analytics/ads traffic.
// These are the most common sources of indefinite network activity on JS-heavy retail sites.
const BLOCKED_DOMAINS = [
    'google-analytics', 'googletagmanager', 'doubleclick', 'facebook', 'twitter',
    'analytics', 'tracking', 'ads.', 'pixel.', 'segment.io', 'mixpanel',
    'hotjar', 'adobedtm', 'mparticle', 'braze', 'amplitude', 'heap.io',
    'fullstory', 'sentry.io', 'bugsnag', 'newrelic', 'datadog', 'dynatrace',
];

function isBlockedStatusCode(statusCode) {
    return statusCode !== 200;
}

function parseProxyUrl(proxyUrl) {
    if (!proxyUrl) return null;

    const url = new URL(proxyUrl);
    const server = `${url.protocol}//${url.host}`;
    const auth = url.username ? { username: decodeURIComponent(url.username), password: decodeURIComponent(url.password || '') } : null;

    return { server, auth };
}

(async () => {
    let browser;
    let page;
    let statusCode = 0;
    try {
        const proxy = parseProxyUrl(request.proxy);

        const launchOptions = {
            headless: true,
            pipe: true,
            timeout: 30000,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--disable-software-rasterizer',
                '--window-size=1920,1080',
            ],
        };

        if (proxy) {
            launchOptions.args.push(`--proxy-server=${proxy.server}`);
        }

        if (request.chromePath) {
            launchOptions.executablePath = request.chromePath;
        }

        browser = await puppeteer.launch(launchOptions);

        page = await browser.newPage();
        page.on('response', response => {
            if (response.request().isNavigationRequest() && response.frame() === page.mainFrame()) {
                statusCode = response.status();
            }
        });

        if (proxy && proxy.auth) {
            await page.authenticate(proxy.auth);
        }

        await page.setViewport({ width: 1920, height: 1080 });

        if (request.userAgent) {
            await page.setUserAgent(request.userAgent);
        }

        if (request.headers && Object.keys(request.headers).length > 0) {
            await page.setExtraHTTPHeaders(request.headers);
        }

        // Block non-essential resource types and analytics/tracking domains.
        // This prevents JS-heavy retail sites (Nike, Shein, etc.) from holding the
        // connection open with background analytics pings that never settle under
        // networkidle2, causing timeouts.
        await page.setRequestInterception(true);
        page.on('request', (req) => {
            const type = req.resourceType();
            const url = req.url();

            if (['image', 'stylesheet', 'font', 'media', 'other'].includes(type)) {
                req.abort();
                return;
            }

            if (BLOCKED_DOMAINS.some((domain) => url.includes(domain))) {
                req.abort();
                return;
            }

            req.continue();
        });

        // Use domcontentloaded instead of networkidle2: we want the HTML/JS to be
        // parsed, not to wait for every background request to finish.
        const response = await page.goto(request.url, {
            waitUntil: 'domcontentloaded',
            timeout: request.timeout || 30000,
        });

        // Give JS-rendered meta tags (og:title, og:price, etc.) up to 3 seconds to
        // populate, then hard-cut regardless. This handles SPAs that inject meta tags
        // after DOMContentLoaded without risking an indefinite wait.
        await Promise.race([
            page.waitForFunction(
                () => document.querySelector('meta[property="og:title"]')?.content?.length > 0,
                { timeout: 3000 }
            ).catch(() => null),
            new Promise((resolve) => setTimeout(resolve, 3000)),
        ]);

        const html = await page.content();
        statusCode = response ? response.status() : statusCode;

        if (isBlockedStatusCode(statusCode)) {
            console.log(JSON.stringify({
                success: false,
                error: `Request failed (status: ${statusCode})`,
                statusCode: statusCode,
                html: html,
                finalUrl: page.url(),
            }));
            process.exitCode = 1;
            return;
        }

        console.log(JSON.stringify({
            success: true,
            html: html,
            statusCode: statusCode,
            finalUrl: page.url(),
        }));
    } catch (error) {
        // A navigation timeout can still leave a useful DOM. Preserve it without
        // another navigation, retry, or interaction with consent/challenge pages.
        let html = '';
        if (page) {
            try { html = await page.content(); } catch (_) {}
        }
        const timedOut = error.name === 'TimeoutError';
        console.log(JSON.stringify({
            success: timedOut && html.length > 0,
            html,
            statusCode,
            finalUrl: page ? page.url() : request.url,
            timedOut,
            error: timedOut ? 'navigation_timeout' : 'browser_error',
        }));
        if (!timedOut || !html) process.exitCode = 1;
    } finally {
        if (browser) {
            await browser.close();
        }
    }
})();

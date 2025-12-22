const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(StealthPlugin());

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

const BLOCK_PAGE_SIGNATURES = [
    'ip address',
    'blocked',
    'access denied',
    'forbidden',
    'captcha',
    'challenge',
    'security check',
    'unusual traffic',
    'bot detection',
    'automated access',
    'rate limit',
];

function isBlockPage(html, statusCode) {
    if (statusCode === 403 || statusCode === 429 || statusCode === 503) {
        return true;
    }

    const lowerHtml = html.toLowerCase();
    const matchedSignatures = BLOCK_PAGE_SIGNATURES.filter(sig => lowerHtml.includes(sig));

    if (matchedSignatures.length >= 2) {
        return true;
    }

    if (lowerHtml.includes('blocked') && lowerHtml.includes('ip')) {
        return true;
    }

    return false;
}

(async () => {
    let browser;
    try {
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
            ],
        };

        if (request.chromePath) {
            launchOptions.executablePath = request.chromePath;
        }

        browser = await puppeteer.launch(launchOptions);

        const page = await browser.newPage();

        await page.setViewport({ width: 1280, height: 800 });

        if (request.userAgent) {
            await page.setUserAgent(request.userAgent);
        }

        if (request.headers && Object.keys(request.headers).length > 0) {
            await page.setExtraHTTPHeaders(request.headers);
        }

        const response = await page.goto(request.url, {
            waitUntil: 'networkidle2',
            timeout: request.timeout || 30000,
        });

        const html = await page.content();
        const statusCode = response ? response.status() : 200;

        if (isBlockPage(html, statusCode)) {
            console.log(JSON.stringify({
                success: false,
                error: `Request blocked (status: ${statusCode})`,
                statusCode: statusCode,
            }));
            process.exit(1);
        }

        console.log(JSON.stringify({
            success: true,
            html: html,
            statusCode: statusCode,
            finalUrl: response ? response.url() : request.url,
        }));
    } catch (error) {
        console.log(JSON.stringify({
            success: false,
            error: error.message,
        }));
        process.exit(1);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
})();

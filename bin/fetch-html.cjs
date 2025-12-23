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

function isBlockedStatusCode(statusCode) {
    return statusCode !== 200;
}

(async () => {
    let browser;
    try {
        const launchOptions = {
            headless: true,
            pipe: true,
            timeout: 30000,
            ignoreDefaultArgs: ['--enable-automation'],
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--disable-software-rasterizer',
                '--disable-blink-features=AutomationControlled',
                '--disable-features=IsolateOrigins,site-per-process',
                '--window-size=1920,1080',
            ],
        };

        if (request.chromePath) {
            launchOptions.executablePath = request.chromePath;
        }

        browser = await puppeteer.launch(launchOptions);

        const page = await browser.newPage();

        await page.setViewport({ width: 1920, height: 1080 });

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

        if (isBlockedStatusCode(statusCode)) {
            console.log(JSON.stringify({
                success: false,
                error: `Request failed (status: ${statusCode})`,
                statusCode: statusCode,
                html: html,
                finalUrl: response ? response.url() : request.url,
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

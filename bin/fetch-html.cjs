const puppeteer = require('puppeteer');

const request = JSON.parse(process.argv[2]);

(async () => {
    let browser;
    try {
        const launchOptions = {
            headless: 'shell',
            pipe: true,
            timeout: 30000,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--disable-software-rasterizer',
                '--single-process',
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

        console.log(JSON.stringify({
            success: true,
            html: html,
            statusCode: response ? response.status() : 200,
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

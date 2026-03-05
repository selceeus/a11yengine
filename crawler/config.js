module.exports = {
    maxPages: parseInt(process.env.CRAWLER_MAX_PAGES, 10) || 50,
    maxDepth: parseInt(process.env.CRAWLER_MAX_DEPTH, 10) || 5,
    pageTimeoutMs: parseInt(process.env.CRAWLER_PAGE_TIMEOUT_MS, 10) || 30000,
    navigationTimeoutMs: parseInt(process.env.CRAWLER_NAV_TIMEOUT_MS, 10) || 60000,
    logLevel: process.env.CRAWLER_LOG_LEVEL || 'error', // silent | error | warn | info

    axe: {
        runOnly: {
            type: 'tag',
            values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'],
        },
        rules: {
            // Example: disable a specific rule
            // 'color-contrast': { enabled: false },
        },
        reporter: 'v2',
        resultTypes: ['violations'],
    },

    puppeteer: {
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
        ],
    },
};

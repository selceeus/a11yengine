module.exports = {
    maxPages: parseInt(process.env.CRAWLER_MAX_PAGES, 10) || 50,
    maxDepth: parseInt(process.env.CRAWLER_MAX_DEPTH, 10) || 5,
    pageTimeoutMs: parseInt(process.env.CRAWLER_PAGE_TIMEOUT_MS, 10) || 30000,
    navigationTimeoutMs: parseInt(process.env.CRAWLER_NAV_TIMEOUT_MS, 10) || 60000,
    requestDelayMs: parseInt(process.env.CRAWLER_REQUEST_DELAY_MS, 10) || 500,
    logLevel: process.env.CRAWLER_LOG_LEVEL || 'error', // silent | error | warn | info

    // Self-termination budget: Node exits cleanly at 85 % of the PHP-side
    // CRAWLER_TIMEOUT, outputting partial results rather than being hard-killed
    // by PHP (which cannot reliably terminate Chromium child processes on Windows).
    scanTimeoutMs: Math.floor((parseInt(process.env.CRAWLER_TIMEOUT, 10) || 600) * 1000 * 0.85),

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

    playwright: {
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
        ],
    },

    screenReader: {
        // Set to false to disable the virtual screen reader checks entirely.
        enabled: true,
    },

    content: {
        // Set to false to disable the deterministic content-quality checks entirely.
        enabled: true,
    },

    keyboard: {
        // Set to false to disable the DOM-based keyboard navigation checks entirely.
        enabled: true,
        // Maximum milliseconds to spend on keyboard checks per page before
        // withTimeout cancels and moves on.
        timeoutMs: 10000,
    },

    interactive: {
        // Set to false to disable the Playwright-driven interactive checks entirely.
        // Includes tab navigation, interaction contrast, reflow, reduced motion, and touch targets.
        enabled: true,
        // Maximum number of Tab key presses during the tab navigation phase.
        // Keep this low — the traversal now also collects focus-indicator data in the
        // same pass, so this value is no longer doubled per page.
        maxTabSteps: 20,
        // Maximum milliseconds to spend on the full interactive phase per page
        // before withTimeout cancels and moves on.
        timeoutMs: 25000,
        // Viewport dimensions to restore after the reflow phase.
        originalViewport: { width: 1280, height: 720 },
    },
};

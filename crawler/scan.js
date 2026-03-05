'use strict';

const puppeteer = require('puppeteer');
const config = require('./config');
const { normaliseUrl, isSameDomain, extractLinks } = require('./crawlUtils');
const { runAxe } = require('./axeRunner');

/**
 * Log to stderr only — stdout is reserved for the JSON payload consumed by
 * Laravel's CrawlerRunner.
 *
 * @param {'info'|'warn'|'error'} level
 * @param {string} message
 */
function log(level, message) {
    const levels = { silent: 0, error: 1, warn: 2, info: 3 };
    if (levels[level] <= levels[config.logLevel]) {
        process.stderr.write(`[${level.toUpperCase()}] ${message}\n`);
    }
}

/**
 * Parse CLI args:  node scan.js <url> [maxDepth] [maxPages]
 */
function parseArgs() {
    const [, , url, depth, pages] = process.argv;

    if (!url) {
        process.stderr.write('Usage: node scan.js <url> [maxDepth] [maxPages]\n');
        process.exit(1);
    }

    return {
        baseUrl: normaliseUrl(url),
        maxDepth: depth ? parseInt(depth, 10) : config.maxDepth,
        maxPages: pages ? parseInt(pages, 10) : config.maxPages,
    };
}

/**
 * Main crawler entry point.
 */
async function scan() {
    const { baseUrl, maxDepth, maxPages } = parseArgs();

    log('info', `Starting scan: ${baseUrl} (maxDepth=${maxDepth}, maxPages=${maxPages})`);

    const browser = await puppeteer.launch(config.puppeteer);

    /** @type {Set<string>} */
    const visited = new Set();

    /** @type {Array<{url: string, depth: number}>} */
    const queue = [{ url: baseUrl, depth: 0 }];

    /** @type {Array<{url: string, violations: object[]}>} */
    const results = [];

    try {
        while (queue.length > 0 && visited.size < maxPages) {
            const { url, depth } = queue.shift();
            const normalisedUrl = normaliseUrl(url);

            if (visited.has(normalisedUrl)) {
                continue;
            }

            visited.add(normalisedUrl);
            log('info', `Scanning [${visited.size}/${maxPages}] depth=${depth} ${normalisedUrl}`);

            const page = await browser.newPage();
            page.setDefaultNavigationTimeout(config.navigationTimeoutMs);
            page.setDefaultTimeout(config.pageTimeoutMs);

            try {
                const response = await page.goto(normalisedUrl, {
                    waitUntil: 'networkidle2',
                    timeout: config.navigationTimeoutMs,
                });

                if (!response || response.status() >= 400) {
                    log('warn', `Skipping ${normalisedUrl} — HTTP ${response?.status() ?? 'no response'}`);
                    continue;
                }

                const pageResult = await runAxe(page, config.axe);
                results.push(pageResult);

                log(
                    'info',
                    `Found ${pageResult.violations.length} violation(s) on ${normalisedUrl}`
                );

                if (depth < maxDepth) {
                    const links = await extractLinks(page, baseUrl);

                    for (const link of links) {
                        if (
                            !visited.has(link) &&
                            isSameDomain(baseUrl, link) &&
                            visited.size + queue.length < maxPages
                        ) {
                            queue.push({ url: link, depth: depth + 1 });
                        }
                    }
                }
            } catch (pageError) {
                log('error', `Error scanning ${normalisedUrl}: ${pageError.message}`);
            } finally {
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }

    process.stdout.write(JSON.stringify(results));
    process.exit(0);
}

module.exports = { scan, log, parseArgs };

if (require.main === module) {
    scan().catch((error) => {
        process.stderr.write(`[FATAL] ${error.message}\n${error.stack}\n`);
        process.exit(1);
    });
}

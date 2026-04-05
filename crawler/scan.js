'use strict';

const { chromium } = require('playwright');
const config = require('./config');
const { normaliseUrl, isSameDomain, extractLinks, fetchRobotsTxt, isAllowedByRobots } = require('./crawlUtils');
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
 * Parse CLI args:
 *   node scan.js <url> [--max-pages N] [--wcag-version wcag21|wcag22]
 *                      [--include PATTERN]... [--exclude PATTERN]...
 *
 * Legacy positional args (url depth pages) are still accepted for backward
 * compatibility with existing callers that pass them positionally.
 */
function parseArgs() {
    const args = process.argv.slice(2);

    if (!args[0]) {
        process.stderr.write('Usage: node scan.js <url> [--max-pages N] [--wcag-version wcag21|wcag22] [--include PATTERN]... [--exclude PATTERN]...\n');
        process.exit(1);
    }

    const url = args[0];
    let maxPages = config.maxPages;
    let maxDepth = config.maxDepth;
    let wcagVersion = 'wcag21';
    const includePatterns = [];
    const excludePatterns = [];

    for (let i = 1; i < args.length; i++) {
        const arg = args[i];
        if (arg === '--max-pages' && args[i + 1]) {
            maxPages = parseInt(args[++i], 10);
        } else if (arg === '--max-depth' && args[i + 1]) {
            maxDepth = parseInt(args[++i], 10);
        } else if (arg === '--wcag-version' && args[i + 1]) {
            wcagVersion = args[++i];
        } else if (arg === '--include' && args[i + 1]) {
            includePatterns.push(args[++i]);
        } else if (arg === '--exclude' && args[i + 1]) {
            excludePatterns.push(args[++i]);
        } else if (!arg.startsWith('--') && i === 1) {
            // Legacy positional depth
            maxDepth = parseInt(arg, 10);
        } else if (!arg.startsWith('--') && i === 2) {
            // Legacy positional max-pages
            maxPages = parseInt(arg, 10);
        }
    }

    return {
        baseUrl: normaliseUrl(url),
        maxDepth,
        maxPages,
        wcagVersion,
        includePatterns,
        excludePatterns,
    };
}

/**
 * Returns true if the URL should be crawled given the include/exclude pattern lists.
 * - If includePatterns is non-empty, the URL must match at least one.
 * - If excludePatterns is non-empty, the URL must not match any.
 *
 * @param {string} url
 * @param {string[]} includePatterns
 * @param {string[]} excludePatterns
 * @returns {boolean}
 */
function matchesPatterns(url, includePatterns, excludePatterns) {
    if (includePatterns.length > 0) {
        const included = includePatterns.some((p) => url.includes(p));
        if (!included) return false;
    }

    if (excludePatterns.length > 0) {
        const excluded = excludePatterns.some((p) => url.includes(p));
        if (excluded) return false;
    }

    return true;
}

/**
 * Main crawler entry point.
 */
async function scan() {
    const { baseUrl, maxDepth, maxPages, wcagVersion, includePatterns, excludePatterns } = parseArgs();

    log('info', `Starting scan: ${baseUrl} (maxDepth=${maxDepth}, maxPages=${maxPages}, wcag=${wcagVersion})`);

    // Build axe config, extending the base tag list for WCAG 2.2 if requested.
    const axeConfig = { ...config.axe };
    const tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'];
    if (wcagVersion === 'wcag22') {
        tags.push('wcag22a', 'wcag22aa');
    }
    axeConfig.runOnly = { type: 'tag', values: tags };

    const robotsTxt = await fetchRobotsTxt(baseUrl);
    log('info', robotsTxt ? 'Loaded robots.txt' : 'No robots.txt found — all paths allowed');

    const browser = await chromium.launch(config.playwright);

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

            // Rate-limit: wait between requests to avoid hammering the target.
            if (visited.size > 1 && config.requestDelayMs > 0) {
                await new Promise((resolve) => setTimeout(resolve, config.requestDelayMs));
            }

            const page = await browser.newPage();
            page.setDefaultNavigationTimeout(config.navigationTimeoutMs);
            page.setDefaultTimeout(config.pageTimeoutMs);

            try {
                const response = await page.goto(normalisedUrl, {
                    waitUntil: 'networkidle',
                    timeout: config.navigationTimeoutMs,
                });

                if (!response || response.status() >= 400) {
                    log('warn', `Recording ${normalisedUrl} as failed — HTTP ${response?.status() ?? 'no response'}`);
                    results.push({ url: normalisedUrl, violations: [], error: true, httpStatus: response?.status() ?? null });
                    continue;
                }

                const pageResult = await runAxe(page, axeConfig);
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
                            isAllowedByRobots(robotsTxt, link) &&
                            visited.size + queue.length < maxPages &&
                            matchesPatterns(link, includePatterns, excludePatterns)
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

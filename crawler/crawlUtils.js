'use strict';

const http = require('http');
const https = require('https');

/**
 * Fetch the robots.txt for a base URL. Returns the raw text or an empty
 * string if the file is missing / unreachable.
 *
 * @param {string} baseUrl
 * @returns {Promise<string>}
 */
function fetchRobotsTxt(baseUrl) {
    return new Promise((resolve) => {
        try {
            const { protocol, hostname, port } = new URL(baseUrl);
            const robotsUrl = `${protocol}//${hostname}${port ? ':' + port : ''}/robots.txt`;
            const lib = protocol === 'https:' ? https : http;

            const req = lib.get(robotsUrl, { timeout: 5000 }, (res) => {
                if (res.statusCode !== 200) {
                    res.resume();
                    return resolve('');
                }

                let body = '';
                res.on('data', (chunk) => (body += chunk));
                res.on('end', () => resolve(body));
            });

            req.on('error', () => resolve(''));
            req.on('timeout', () => {
                req.destroy();
                resolve('');
            });
        } catch {
            resolve('');
        }
    });
}

/**
 * Parse a robots.txt string and return whether the given URL path is
 * allowed for * (wildcard) user-agent crawlers.
 *
 * Rules are evaluated in order; the most specific matching rule wins.
 * An empty robots.txt (or parse error) allows everything.
 *
 * @param {string} robotsTxt
 * @param {string} url
 * @returns {boolean}
 */
function isAllowedByRobots(robotsTxt, url) {
    if (!robotsTxt) {
        return true;
    }

    let path;
    try {
        path = new URL(url).pathname;
    } catch {
        return true;
    }

    const lines = robotsTxt.split(/\r?\n/);
    let inScope = false;
    let bestMatch = { length: -1, allowed: true };

    for (const raw of lines) {
        const line = raw.trim();

        if (line.startsWith('#') || line === '') {
            continue;
        }

        const [field, ...rest] = line.split(':');
        const value = rest.join(':').trim();

        if (field.toLowerCase() === 'user-agent') {
            inScope = value === '*';
            continue;
        }

        if (!inScope) {
            continue;
        }

        if (field.toLowerCase() === 'disallow' || field.toLowerCase() === 'allow') {
            if (!value) {
                continue; // Empty Disallow/Allow is a no-op per the spec
            }

            if (path.startsWith(value)) {
                if (value.length > bestMatch.length) {
                    bestMatch = {
                        length: value.length,
                        allowed: field.toLowerCase() === 'allow',
                    };
                }
            }
        }
    }

    return bestMatch.allowed;
}

/**
 * Normalise a URL string — strips fragment and trailing slash so that
 * functionally identical URLs are treated as the same page.
 *
 * @param {string} rawUrl
 * @returns {string}
 */
function normaliseUrl(rawUrl) {
    try {
        const url = new URL(rawUrl);
        url.hash = '';

        if (url.pathname !== '/' && url.pathname.endsWith('/')) {
            url.pathname = url.pathname.slice(0, -1);
        }

        return url.toString();
    } catch {
        return rawUrl;
    }
}

/**
 * Returns true when `candidateUrl` shares the same hostname as `baseUrl`.
 *
 * @param {string} baseUrl
 * @param {string} candidateUrl
 * @returns {boolean}
 */
function isSameDomain(baseUrl, candidateUrl) {
    try {
        return new URL(candidateUrl).hostname === new URL(baseUrl).hostname;
    } catch {
        return false;
    }
}

/**
 * Extract all unique, same-domain, http(s) href values from a Playwright page.
 *
 * @param {import('playwright').Page} page
 * @param {string} baseUrl
 * @returns {Promise<string[]>}
 */
async function extractLinks(page, baseUrl) {
    const hrefs = await page.$$eval('a[href]', (anchors) =>
        anchors.map((a) => a.href)
    );

    const seen = new Set();
    const links = [];

    for (const href of hrefs) {
        const normalised = normaliseUrl(href);

        if (
            !seen.has(normalised) &&
            isSameDomain(baseUrl, normalised) &&
            /^https?:\/\//i.test(normalised)
        ) {
            seen.add(normalised);
            links.push(normalised);
        }
    }

    return links;
}

module.exports = { normaliseUrl, isSameDomain, extractLinks, fetchRobotsTxt, isAllowedByRobots };

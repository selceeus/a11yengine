'use strict';

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
 * Extract all unique, same-domain, http(s) href values from a Puppeteer page.
 *
 * @param {import('puppeteer').Page} page
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

module.exports = { normaliseUrl, isSameDomain, extractLinks };

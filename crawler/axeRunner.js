'use strict';

const { source: axeSource } = require('axe-core');

/**
 * Inject axe-core into the given Puppeteer page and run an accessibility scan.
 *
 * @param {import('puppeteer').Page} page
 * @param {object} axeConfig  The `axe` block from config.js
 * @returns {Promise<{
 *   url: string,
 *   violations: Array<{
 *     id: string,
 *     impact: string|null,
 *     description: string,
 *     helpUrl: string,
 *     tags: string[],
 *     nodes: Array<{
 *       html: string,
 *       target: string[],
 *       failureSummary: string|null
 *     }>
 *   }>
 * }>}
 */
async function runAxe(page, axeConfig) {
    const url = page.url();

    await page.evaluate(axeSource);

    const results = await page.evaluate((options) => {
        return window.axe.run(document, options);
    }, axeConfig);

    const violations = results.violations.map((violation) => ({
        id: violation.id,
        impact: violation.impact ?? null,
        description: violation.description,
        helpUrl: violation.helpUrl,
        tags: violation.tags,
        nodes: violation.nodes.map((node) => ({
            html: node.html,
            target: node.target,
            failureSummary: node.failureSummary ?? null,
        })),
    }));

    return { url, violations };
}

module.exports = { runAxe };

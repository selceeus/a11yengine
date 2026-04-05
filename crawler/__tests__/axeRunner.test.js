'use strict';

const { runAxe } = require('../axeRunner');

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Build a minimal axe-core violation result as returned by window.axe.run().
 */
function axeViolation(overrides = {}) {
    return {
        id: 'color-contrast',
        impact: 'serious',
        description: 'Elements must have sufficient color contrast',
        helpUrl: 'https://dequeuniversity.com/rules/axe/4.10/color-contrast',
        tags: ['wcag2a', 'wcag2aa'],
        nodes: [
            {
                html: '<p style="color:#aaa">text</p>',
                target: ['#main p'],
                failureSummary: 'Fix any of the following: ...',
            },
        ],
        ...overrides,
    };
}

/**
 * Build a Playwright page mock that returns axe results from `page.evaluate()`.
 *
 * `addScriptTag()` is used for axe source injection.
 * `evaluate()` is called once for `window.axe.run()`.
 */
function buildPageMock(violations = [axeViolation()]) {
    return {
        url: jest.fn().mockReturnValue('https://example.com/page'),
        addScriptTag: jest.fn().mockResolvedValue(undefined),
        evaluate: jest
            .fn()
            .mockResolvedValueOnce({ violations }), // axe.run() result
    };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('runAxe', () => {
    test('returns the page url alongside violations', async () => {
        const page = buildPageMock();

        const result = await runAxe(page, {});

        expect(result.url).toBe('https://example.com/page');
    });

    test('maps violation id, impact, description, helpUrl and tags', async () => {
        const page = buildPageMock();

        const { violations } = await runAxe(page, {});

        expect(violations[0]).toMatchObject({
            id: 'color-contrast',
            impact: 'serious',
            description: 'Elements must have sufficient color contrast',
            helpUrl: 'https://dequeuniversity.com/rules/axe/4.10/color-contrast',
            tags: ['wcag2a', 'wcag2aa'],
        });
    });

    test('maps node html, target, and failureSummary', async () => {
        const page = buildPageMock();

        const { violations } = await runAxe(page, {});

        expect(violations[0].nodes[0]).toEqual({
            html: '<p style="color:#aaa">text</p>',
            target: ['#main p'],
            failureSummary: 'Fix any of the following: ...',
        });
    });

    test('coerces undefined impact to null', async () => {
        const page = buildPageMock([axeViolation({ impact: undefined })]);

        const { violations } = await runAxe(page, {});

        expect(violations[0].impact).toBeNull();
    });

    test('coerces undefined failureSummary to null', async () => {
        const pageViolation = axeViolation({
            nodes: [{ html: '<div></div>', target: ['#el'], failureSummary: undefined }],
        });
        const page = buildPageMock([pageViolation]);

        const { violations } = await runAxe(page, {});

        expect(violations[0].nodes[0].failureSummary).toBeNull();
    });

    test('returns an empty violations array when axe finds no issues', async () => {
        const page = buildPageMock([]);

        const { violations } = await runAxe(page, {});

        expect(violations).toEqual([]);
    });

    test('passes the axe config options to window.axe.run', async () => {
        const page = buildPageMock();
        const axeConfig = { runOnly: { type: 'tag', values: ['wcag2a'] } };

        await runAxe(page, axeConfig);

        // evaluate is called once for window.axe.run(document, options)
        const [runCall] = page.evaluate.mock.calls;
        expect(runCall[1]).toEqual(axeConfig);
    });

    test('injects the axe-core source into the page before running', async () => {
        const page = buildPageMock();

        await runAxe(page, {});

        // addScriptTag is used to inject the axe source
        expect(page.addScriptTag).toHaveBeenCalledTimes(1);
        const [[{ content }]] = page.addScriptTag.mock.calls;
        expect(typeof content).toBe('string');
        expect(content.length).toBeGreaterThan(0);
    });

    test('handles multiple nodes per violation', async () => {
        const violation = axeViolation({
            nodes: [
                { html: '<img>', target: ['#img1'], failureSummary: 'Missing alt' },
                { html: '<img>', target: ['#img2'], failureSummary: 'Missing alt' },
            ],
        });
        const page = buildPageMock([violation]);

        const { violations } = await runAxe(page, {});

        expect(violations[0].nodes).toHaveLength(2);
        expect(violations[0].nodes[0].target).toEqual(['#img1']);
        expect(violations[0].nodes[1].target).toEqual(['#img2']);
    });
});

'use strict';

const { runKeyboard } = require('../keyboardRunner');

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Build a minimal page mock that returns a fixed result from page.evaluate().
 *
 * Each call to evaluate() consumes the next value in the `evaluateResults` array.
 * If the array is exhausted, subsequent calls return [].
 */
function buildPageMock(evaluateResults = []) {
    let callIndex = 0;

    return {
        url: jest.fn().mockReturnValue('https://example.com/'),
        evaluate: jest.fn().mockImplementation(() => {
            const result = evaluateResults[callIndex] ?? [];
            callIndex++;

            return Promise.resolve(result);
        }),
    };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('runKeyboard', () => {
    test('returns the page url alongside violations', async () => {
        const page = buildPageMock();

        const result = await runKeyboard(page, {});

        expect(result.url).toBe('https://example.com/');
    });

    test('returns an empty violations array on a clean page', async () => {
        // All 7 checks return []
        const page = buildPageMock([[], [], [], [], [], [], []]);

        const result = await runKeyboard(page, {});

        expect(result.violations).toEqual([]);
    });

    test('detects kb-positive-tabindex when an element has tabindex > 0', async () => {
        const positiveTabindexNodes = [{
            html: '<a href="#" tabindex="2">Link</a>',
            target: ['a'],
            failureSummary: 'tabindex="2" — remove positive tabindex values and rely on DOM order.',
        }];

        // First check (positive tabindex) returns a finding; rest return []
        const page = buildPageMock([positiveTabindexNodes, [], [], [], [], [], []]);

        const result = await runKeyboard(page, {});

        expect(result.violations).toHaveLength(1);
        expect(result.violations[0].id).toBe('kb-positive-tabindex');
        expect(result.violations[0].impact).toBe('serious');
        expect(result.violations[0].nodes).toEqual(positiveTabindexNodes);
    });

    test('detects kb-onclick-no-keyboard for non-native elements with onclick only', async () => {
        // checkPositiveTabindex → [], checkNonInteractiveFocusable → [], checkOnclickNoKeyboard → finding
        const onclickNodes = [{
            html: '<div onclick="doSomething()">Click me</div>',
            target: ['div'],
            failureSummary: 'Add an onkeydown or onkeyup handler.',
        }];

        const page = buildPageMock([[], [], onclickNodes, [], [], [], []]);

        const result = await runKeyboard(page, {});

        expect(result.violations).toHaveLength(1);
        expect(result.violations[0].id).toBe('kb-onclick-no-keyboard');
        expect(result.violations[0].impact).toBe('critical');
    });

    test('detects kb-aria-disabled-focusable for aria-disabled without tabindex=-1', async () => {
        const ariaDisabledNodes = [{
            html: '<button aria-disabled="true">Disabled</button>',
            target: ['button'],
            failureSummary: 'Add tabindex="-1".',
        }];

        // Checks: positive, non-interactive, onclick, autofocus, offscreen, aria-disabled, composite
        const page = buildPageMock([[], [], [], [], [], ariaDisabledNodes, []]);

        const result = await runKeyboard(page, {});

        expect(result.violations).toHaveLength(1);
        expect(result.violations[0].id).toBe('kb-aria-disabled-focusable');
    });

    test('detects kb-composite-widget-no-roving for tablist with multiple tabindex=0 children', async () => {
        const compositeNodes = [{
            html: '<div role="tablist">...</div>',
            target: ['[role="tablist"]'],
            failureSummary: '3 child elements have tabindex="0" — use roving tabindex.',
        }];

        const page = buildPageMock([[], [], [], [], [], [], compositeNodes]);

        const result = await runKeyboard(page, {});

        expect(result.violations).toHaveLength(1);
        expect(result.violations[0].id).toBe('kb-composite-widget-no-roving');
    });

    test('includes required violation shape fields', async () => {
        const nodes = [{ html: '<div tabindex="1">x</div>', target: ['div'], failureSummary: 'Fix it.' }];
        const page = buildPageMock([nodes, [], [], [], [], [], []]);

        const { violations } = await runKeyboard(page, {});

        expect(violations[0]).toMatchObject({
            id: 'kb-positive-tabindex',
            impact: expect.any(String),
            description: expect.any(String),
            helpUrl: expect.any(String),
            tags: expect.arrayContaining(['cat.keyboard']),
            nodes: expect.any(Array),
        });
    });

    test('continues when an individual check throws', async () => {
        const page = {
            url: jest.fn().mockReturnValue('https://example.com/'),
            evaluate: jest.fn()
                .mockRejectedValueOnce(new Error('evaluate failed'))
                .mockResolvedValue([]),
        };

        const result = await runKeyboard(page, {});

        // Should not throw — violations may be empty or partial
        expect(result).toHaveProperty('violations');
        expect(Array.isArray(result.violations)).toBe(true);
    });

    test('returns multiple violations when multiple checks find issues', async () => {
        const positiveTabNodes = [{ html: '<a tabindex="3">link</a>', target: ['a'], failureSummary: '' }];
        const onclickNodes = [{ html: '<div onclick="x()">div</div>', target: ['div'], failureSummary: '' }];

        const page = buildPageMock([positiveTabNodes, [], onclickNodes, [], [], [], []]);

        const result = await runKeyboard(page, {});

        expect(result.violations).toHaveLength(2);
        expect(result.violations.map((v) => v.id)).toEqual(
            expect.arrayContaining(['kb-positive-tabindex', 'kb-onclick-no-keyboard']),
        );
    });
});

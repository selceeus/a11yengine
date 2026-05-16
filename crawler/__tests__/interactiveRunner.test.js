'use strict';

const {
    runInteractive,
    relativeLuminance,
    contrastRatio,
    parseRgb,
    checkFocusTrap,
    checkFocusOrderWrong,
} = require('../interactiveRunner');

// ─── Colour utilities ─────────────────────────────────────────────────────────

describe('parseRgb', () => {
    test('parses rgb() string', () => {
        expect(parseRgb('rgb(255, 0, 0)')).toEqual({ r: 255, g: 0, b: 0 });
    });

    test('parses rgba() string', () => {
        expect(parseRgb('rgba(0, 128, 255, 0.5)')).toEqual({ r: 0, g: 128, b: 255 });
    });

    test('returns null for unparseable string', () => {
        expect(parseRgb('transparent')).toBeNull();
        expect(parseRgb('')).toBeNull();
    });
});

describe('relativeLuminance', () => {
    test('white has luminance 1', () => {
        expect(relativeLuminance(255, 255, 255)).toBeCloseTo(1, 5);
    });

    test('black has luminance 0', () => {
        expect(relativeLuminance(0, 0, 0)).toBeCloseTo(0, 5);
    });
});

describe('contrastRatio', () => {
    test('black on white is 21:1', () => {
        const white = relativeLuminance(255, 255, 255);
        const black = relativeLuminance(0, 0, 0);

        expect(contrastRatio(white, black)).toBeCloseTo(21, 0);
    });

    test('same colour has contrast ratio of 1', () => {
        const l = relativeLuminance(128, 128, 128);

        expect(contrastRatio(l, l)).toBeCloseTo(1, 5);
    });
});

// ─── checkFocusTrap ───────────────────────────────────────────────────────────

describe('checkFocusTrap', () => {
    test('returns null when fewer than 3 elements', () => {
        const tabOrder = [
            { target: 'a#link1', html: '<a>', domIndex: 0 },
            { target: 'button#btn', html: '<button>', domIndex: 1 },
        ];

        expect(checkFocusTrap(tabOrder)).toBeNull();
    });

    test('returns null when no element repeats 3 times consecutively', () => {
        const tabOrder = [
            { target: 'a#link1', html: '<a>', domIndex: 0 },
            { target: 'button#btn', html: '<button>', domIndex: 1 },
            { target: 'a#link2', html: '<a>', domIndex: 2 },
        ];

        expect(checkFocusTrap(tabOrder)).toBeNull();
    });

    test('detects a focus trap when same element appears 3 consecutive times', () => {
        const tabOrder = [
            { target: 'button#close', html: '<button id="close">', domIndex: 0 },
            { target: 'button#close', html: '<button id="close">', domIndex: 0 },
            { target: 'button#close', html: '<button id="close">', domIndex: 0 },
        ];

        const violation = checkFocusTrap(tabOrder);

        expect(violation).not.toBeNull();
        expect(violation.id).toBe('int-focus-trap');
        expect(violation.impact).toBe('critical');
        expect(violation.nodes).toHaveLength(1);
    });
});

// ─── checkFocusOrderWrong ─────────────────────────────────────────────────────

describe('checkFocusOrderWrong', () => {
    test('returns null when fewer than 3 elements', () => {
        expect(checkFocusOrderWrong([
            { target: 'a', html: '<a>', domIndex: 10 },
            { target: 'button', html: '<button>', domIndex: 5 },
        ])).toBeNull();
    });

    test('returns null when tab order follows DOM order', () => {
        const tabOrder = [
            { target: 'a#link1', html: '<a>', domIndex: 1 },
            { target: 'a#link2', html: '<a>', domIndex: 3 },
            { target: 'button#btn', html: '<button>', domIndex: 5 },
        ];

        expect(checkFocusOrderWrong(tabOrder)).toBeNull();
    });

    test('detects wrong order when domIndex jumps back by more than 5', () => {
        const tabOrder = [
            { target: 'button#submit', html: '<button>', domIndex: 50 },
            { target: 'a#logo', html: '<a>', domIndex: 2 },
            { target: 'a#nav1', html: '<a>', domIndex: 4 },
        ];

        const violation = checkFocusOrderWrong(tabOrder);

        expect(violation).not.toBeNull();
        expect(violation.id).toBe('int-focus-order-wrong');
        expect(violation.nodes).toHaveLength(1);
    });
});

// ─── runInteractive ───────────────────────────────────────────────────────────

describe('runInteractive', () => {
    /**
     * Build a Playwright page mock suitable for the interactive runner.
     * All interaction methods are no-ops by default; evaluate returns [].
     */
    function buildPageMock(overrides = {}) {
        return {
            url: jest.fn().mockReturnValue('https://example.com/'),
            evaluate: jest.fn().mockResolvedValue([]),
            keyboard: { press: jest.fn().mockResolvedValue(undefined) },
            on: jest.fn(),
            off: jest.fn(),
            route: jest.fn().mockResolvedValue(undefined),
            unrouteAll: jest.fn().mockResolvedValue(undefined),
            hover: jest.fn().mockResolvedValue(undefined),
            focus: jest.fn().mockResolvedValue(undefined),
            setViewportSize: jest.fn().mockResolvedValue(undefined),
            emulateMedia: jest.fn().mockResolvedValue(undefined),
            ...overrides,
        };
    }

    test('returns the page url alongside violations', async () => {
        const page = buildPageMock();

        const result = await runInteractive(page, {});

        expect(result.url).toBe('https://example.com/');
    });

    test('returns violations array', async () => {
        const page = buildPageMock();

        const result = await runInteractive(page, {});

        expect(Array.isArray(result.violations)).toBe(true);
    });

    test('does not throw when all phases succeed with no findings', async () => {
        const page = buildPageMock();

        await expect(runInteractive(page, { maxTabSteps: 5, originalViewport: { width: 1280, height: 720 } })).resolves.not.toThrow();
    });

    test('does not throw when a phase throws internally', async () => {
        const page = buildPageMock({
            evaluate: jest.fn().mockRejectedValue(new Error('evaluate failed')),
        });

        await expect(runInteractive(page, {})).resolves.not.toThrow();
    });

    test('calls setViewportSize during reflow phase', async () => {
        const page = buildPageMock();

        await runInteractive(page, { maxTabSteps: 0, originalViewport: { width: 1280, height: 720 } });

        expect(page.setViewportSize).toHaveBeenCalledWith({ width: 320, height: 256 });
        expect(page.setViewportSize).toHaveBeenCalledWith({ width: 1280, height: 720 });
    });

    test('calls emulateMedia with reducedMotion during reduced-motion phase', async () => {
        const page = buildPageMock();

        await runInteractive(page, { maxTabSteps: 0, originalViewport: { width: 1280, height: 720 } });

        expect(page.emulateMedia).toHaveBeenCalledWith({ reducedMotion: 'reduce' });
        expect(page.emulateMedia).toHaveBeenCalledWith({ reducedMotion: null });
    });

    test('detects int-reflow-horizontal-scroll when evaluate returns a scroll violation', async () => {
        let evaluateCallCount = 0;

        const page = buildPageMock({
            evaluate: jest.fn().mockImplementation(() => {
                evaluateCallCount++;

                // Reflow phase evaluate returns a violation (called after focus phases)
                // We return the scroll violation on the evaluate that checks scrollWidth
                // Return [] for all others, violation for the reflow check.
                if (evaluateCallCount === 3) {
                    return Promise.resolve([{
                        html: '<html>',
                        target: ['html'],
                        failureSummary: 'Page scroll width is 800px at 320px viewport.',
                    }]);
                }

                return Promise.resolve([]);
            }),
        });

        const result = await runInteractive(page, { maxTabSteps: 0, originalViewport: { width: 1280, height: 720 } });

        const reflow = result.violations.find((v) => v.id === 'int-reflow-horizontal-scroll');

        expect(reflow).toBeDefined();
        expect(reflow.impact).toBe('serious');
    });
});

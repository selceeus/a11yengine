'use strict';

const { normaliseUrl, isSameDomain, extractLinks, isAllowedByRobots } = require('../crawlUtils');

// ─── normaliseUrl ─────────────────────────────────────────────────────────────

describe('normaliseUrl', () => {
    test('strips URL fragments', () => {
        expect(normaliseUrl('https://example.com/page#section')).toBe('https://example.com/page');
    });

    test('removes trailing slash from non-root paths', () => {
        expect(normaliseUrl('https://example.com/about/')).toBe('https://example.com/about');
    });

    test('preserves the root path slash', () => {
        expect(normaliseUrl('https://example.com/')).toBe('https://example.com/');
    });

    test('handles URLs with no fragment or trailing slash unchanged', () => {
        expect(normaliseUrl('https://example.com/about')).toBe('https://example.com/about');
    });

    test('handles URLs with query strings', () => {
        expect(normaliseUrl('https://example.com/search?q=test')).toBe(
            'https://example.com/search?q=test'
        );
    });

    test('returns the raw string when given an invalid URL', () => {
        expect(normaliseUrl('not-a-url')).toBe('not-a-url');
    });

    test('strips fragment and trailing slash together', () => {
        expect(normaliseUrl('https://example.com/page/#section')).toBe(
            'https://example.com/page'
        );
    });
});

// ─── isSameDomain ─────────────────────────────────────────────────────────────

describe('isSameDomain', () => {
    test('returns true for identical hostnames', () => {
        expect(isSameDomain('https://example.com', 'https://example.com/about')).toBe(true);
    });

    test('returns true for same hostname with different paths', () => {
        expect(isSameDomain('https://example.com/home', 'https://example.com/contact')).toBe(true);
    });

    test('returns false for different hostnames', () => {
        expect(isSameDomain('https://example.com', 'https://other.com/page')).toBe(false);
    });

    test('returns false for subdomain vs root domain', () => {
        expect(isSameDomain('https://example.com', 'https://sub.example.com')).toBe(false);
    });

    test('returns false when the candidate URL is invalid', () => {
        expect(isSameDomain('https://example.com', 'not-a-url')).toBe(false);
    });

    test('returns false when the base URL is invalid', () => {
        expect(isSameDomain('not-a-url', 'https://example.com')).toBe(false);
    });
});

// ─── extractLinks ─────────────────────────────────────────────────────────────

describe('extractLinks', () => {
    /** @type {{ $$eval: jest.Mock }} */
    let mockPage;

    beforeEach(() => {
        mockPage = { $$eval: jest.fn() };
    });

    test('returns same-domain http(s) links', async () => {
        mockPage.$$eval.mockResolvedValue([
            'https://example.com/about',
            'https://example.com/contact',
        ]);

        const links = await extractLinks(mockPage, 'https://example.com');

        expect(links).toEqual([
            'https://example.com/about',
            'https://example.com/contact',
        ]);
    });

    test('filters out links from a different domain', async () => {
        mockPage.$$eval.mockResolvedValue([
            'https://example.com/page',
            'https://external.com/other',
        ]);

        const links = await extractLinks(mockPage, 'https://example.com');

        expect(links).toEqual(['https://example.com/page']);
    });

    test('deduplicates links that normalise to the same URL', async () => {
        mockPage.$$eval.mockResolvedValue([
            'https://example.com/page',
            'https://example.com/page',
            'https://example.com/page#anchor',
        ]);

        const links = await extractLinks(mockPage, 'https://example.com');

        expect(links).toHaveLength(1);
        expect(links[0]).toBe('https://example.com/page');
    });

    test('filters out non http(s) protocols', async () => {
        mockPage.$$eval.mockResolvedValue([
            'mailto:hello@example.com',
            'javascript:void(0)',
            'https://example.com/safe',
        ]);

        const links = await extractLinks(mockPage, 'https://example.com');

        expect(links).toEqual(['https://example.com/safe']);
    });

    test('returns an empty array when there are no links on the page', async () => {
        mockPage.$$eval.mockResolvedValue([]);

        const links = await extractLinks(mockPage, 'https://example.com');

        expect(links).toEqual([]);
    });
});

// ─── isAllowedByRobots ──────────────────────────────────────────────────────────────────

describe('isAllowedByRobots', () => {
    test('returns true when robots.txt is empty', () => {
        expect(isAllowedByRobots('', 'https://example.com/page')).toBe(true);
    });

    test('returns true when no rules match the path', () => {
        const robots = 'User-agent: *\nDisallow: /private';
        expect(isAllowedByRobots(robots, 'https://example.com/public')).toBe(true);
    });

    test('returns false when the path is disallowed', () => {
        const robots = 'User-agent: *\nDisallow: /admin';
        expect(isAllowedByRobots(robots, 'https://example.com/admin/panel')).toBe(false);
    });

    test('returns false when Disallow: / blocks all paths', () => {
        const robots = 'User-agent: *\nDisallow: /';
        expect(isAllowedByRobots(robots, 'https://example.com/anything')).toBe(false);
    });

    test('Allow overrides a broader Disallow when it is more specific', () => {
        const robots = 'User-agent: *\nDisallow: /private\nAllow: /private/public';
        expect(isAllowedByRobots(robots, 'https://example.com/private/public/page')).toBe(true);
    });

    test('ignores rules under a named user-agent', () => {
        const robots = 'User-agent: somebot\nDisallow: /page';
        expect(isAllowedByRobots(robots, 'https://example.com/page')).toBe(true);
    });

    test('returns true for an invalid URL', () => {
        const robots = 'User-agent: *\nDisallow: /page';
        expect(isAllowedByRobots(robots, 'not-a-url')).toBe(true);
    });

    test('returns true when Disallow is empty (allows all)', () => {
        const robots = 'User-agent: *\nDisallow: ';
        expect(isAllowedByRobots(robots, 'https://example.com/page')).toBe(true);
    });
});

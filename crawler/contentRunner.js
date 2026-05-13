'use strict';

/**
 * Deterministic content-quality checks that complement axe-core and the
 * virtual screen reader runner. Covers image alt quality, link quality,
 * heading conventions, document quality, and video/media accessibility
 * (HTML5, YouTube, Vimeo, and other embed services).
 *
 * Also extracts the page's plain visible text for server-side reading-level
 * computation (Flesch-Kincaid) — no LLM involvement required.
 *
 * Violations are returned in the identical axe-core shape so the PHP
 * Finding + Issue pipeline processes them unchanged.
 *
 * Tag ordering rule: criterion tags (e.g. wcag111) MUST appear before
 * conformance level tags (e.g. wcag2a) so IssueNormalizer resolves the
 * correct WCAG category via str_starts_with.
 *
 * @type {Record<string, {impact: string, description: string, helpUrl: string, tags: string[]}>}
 */
const CHECK_META = {
    // ── Images ───────────────────────────────────────────────────────────────

    'content-img-filename-alt': {
        impact: 'serious',
        description: 'Image alt text appears to be a filename (e.g. "hero-banner.jpg") rather than a meaningful description.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/non-text-content.html',
        tags: ['wcag111', 'wcag2a', 'cat.text-alternatives'],
    },
    'content-img-generic-alt': {
        impact: 'moderate',
        description: 'Image alt text is a generic placeholder word ("image", "photo", "picture", "graphic", "icon") that conveys no useful information.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/non-text-content.html',
        tags: ['wcag111', 'wcag2a', 'cat.text-alternatives'],
    },
    'content-img-long-alt': {
        impact: 'minor',
        description: 'Image alt text exceeds 150 characters. Long descriptions should be moved to a visible caption or the surrounding content.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/non-text-content.html',
        tags: ['wcag111', 'wcag2a', 'cat.text-alternatives'],
    },
    'content-img-redundant-descriptor': {
        impact: 'minor',
        description: 'Image alt text begins with a redundant phrase ("image of", "picture of") — screen readers already announce the element as an image.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/non-text-content.html',
        tags: ['wcag111', 'wcag2a', 'cat.text-alternatives'],
    },

    // ── Links ─────────────────────────────────────────────────────────────────

    'content-link-url-as-text': {
        impact: 'moderate',
        description: 'Link visible text is a raw URL — screen readers read it character by character, which is difficult to understand.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/link-purpose-in-context.html',
        tags: ['wcag244', 'wcag2a', 'cat.semantics'],
    },
    'content-duplicate-link-text': {
        impact: 'serious',
        description: 'Multiple links share identical visible text but point to different destinations, creating ambiguity for screen reader users navigating by links.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/link-purpose-link-only.html',
        tags: ['wcag249', 'wcag2aaa', 'cat.semantics'],
    },

    // ── Headings ──────────────────────────────────────────────────────────────

    'content-multiple-h1': {
        impact: 'moderate',
        description: 'Page contains more than one <h1> heading. A single h1 should describe the main page topic.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/headings-and-labels.html',
        tags: ['wcag246', 'wcag2aa', 'cat.structure'],
    },

    // ── Document ──────────────────────────────────────────────────────────────

    'content-generic-page-title': {
        impact: 'serious',
        description: 'Page <title> is generic ("Home", "Untitled", "Welcome") and does not meaningfully identify the page content.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/page-titled.html',
        tags: ['wcag242', 'wcag2a', 'cat.structure'],
    },

    // ── Video & Media ─────────────────────────────────────────────────────────

    'content-video-missing-captions': {
        impact: 'critical',
        description: 'HTML5 <video> element has no captions or subtitles track — deaf and hard-of-hearing users cannot access the audio content.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/captions-prerecorded.html',
        tags: ['wcag122', 'wcag2aa', 'cat.time-and-media'],
    },
    'content-video-missing-transcript': {
        impact: 'serious',
        description: 'HTML5 <video> has no captions track and no adjacent transcript link — users have no accessible alternative.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/audio-description-or-media-alternative-prerecorded.html',
        tags: ['wcag123', 'wcag2a', 'cat.time-and-media'],
    },
    'content-audio-missing-transcript': {
        impact: 'critical',
        description: '<audio> element has no adjacent transcript link — deaf and hard-of-hearing users cannot access the audio-only content.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/audio-only-and-video-only-prerecorded.html',
        tags: ['wcag121', 'wcag2a', 'cat.time-and-media'],
    },
    'content-youtube-captions-unknown': {
        impact: 'moderate',
        description: 'YouTube embed detected — caption availability cannot be verified automatically. Manual review required.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/captions-prerecorded.html',
        tags: ['wcag122', 'wcag2aa', 'cat.time-and-media'],
    },
    'content-vimeo-captions-unknown': {
        impact: 'moderate',
        description: 'Vimeo embed detected — caption availability cannot be verified automatically. Manual review required.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/captions-prerecorded.html',
        tags: ['wcag122', 'wcag2aa', 'cat.time-and-media'],
    },
    'content-video-embed-unverified': {
        impact: 'moderate',
        description: 'Third-party video embed detected — caption and transcript availability cannot be verified automatically.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/captions-prerecorded.html',
        tags: ['wcag122', 'wcag2aa', 'cat.time-and-media'],
    },
};

/**
 * Build a violation object from a check ID and affected nodes.
 * Returns null when nodes is empty (no issue found).
 *
 * @param {string} id
 * @param {Array<{html: string, target: string[], failureSummary: string}>} nodes
 * @returns {{id: string, impact: string, description: string, helpUrl: string, tags: string[], nodes: Array}|null}
 */
function buildViolation(id, nodes) {
    if (!nodes || nodes.length === 0) {
        return null;
    }

    const meta = CHECK_META[id];

    return {
        id,
        impact: meta.impact,
        description: meta.description,
        helpUrl: meta.helpUrl,
        tags: meta.tags,
        nodes,
    };
}

// =============================================================================
// Category A — Images
// =============================================================================

async function checkImgFilenameAlt(page) {
    const nodes = await page.evaluate(() => {
        const EXTENSION_RE = /\.(jpe?g|png|gif|webp|svg|bmp|tiff?|avif|ico)(\s.*)?$/i;
        const PATH_LIKE_RE = /^[\w%+.-]+\/[\w%+.-]/;
        const result = [];

        for (const img of document.querySelectorAll('img[alt]')) {
            const alt = img.getAttribute('alt').trim();

            if (!alt) {
                continue;
            }

            if (EXTENSION_RE.test(alt) || PATH_LIKE_RE.test(alt)) {
                const html = img.outerHTML.length > 200 ? img.outerHTML.slice(0, 200) + '...' : img.outerHTML;
                result.push({
                    html,
                    target: ['img'],
                    failureSummary: `Alt text "${alt.slice(0, 80)}" appears to be a filename — replace with a concise description of the image content.`,
                });
            }
        }

        return result;
    });

    return buildViolation('content-img-filename-alt', nodes);
}

async function checkImgGenericAlt(page) {
    const nodes = await page.evaluate(() => {
        const GENERIC = new Set([
            'image', 'img', 'photo', 'photograph', 'picture', 'pic', 'graphic',
            'icon', 'logo', 'thumbnail', 'banner', 'placeholder', 'figure',
        ]);
        const result = [];

        for (const img of document.querySelectorAll('img[alt]')) {
            const alt = img.getAttribute('alt').trim().toLowerCase();

            if (!alt) {
                continue;
            }

            if (GENERIC.has(alt)) {
                const html = img.outerHTML.length > 200 ? img.outerHTML.slice(0, 200) + '...' : img.outerHTML;
                result.push({
                    html,
                    target: ['img'],
                    failureSummary: `Alt text "${alt}" is a generic placeholder — replace with a description of what the image shows.`,
                });
            }
        }

        return result;
    });

    return buildViolation('content-img-generic-alt', nodes);
}

async function checkImgLongAlt(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const img of document.querySelectorAll('img[alt]')) {
            const alt = img.getAttribute('alt').trim();

            if (alt.length > 150) {
                const html = img.outerHTML.length > 200 ? img.outerHTML.slice(0, 200) + '...' : img.outerHTML;
                result.push({
                    html,
                    target: ['img'],
                    failureSummary: `Alt text is ${alt.length} characters — move the extended description to a visible caption and use a brief alt.`,
                });
            }
        }

        return result;
    });

    return buildViolation('content-img-long-alt', nodes);
}

async function checkImgRedundantDescriptor(page) {
    const nodes = await page.evaluate(() => {
        const REDUNDANT_RE = /^(image of|img of|photo of|photograph of|picture of|graphic of|icon of|logo of)\s+/i;
        const result = [];

        for (const img of document.querySelectorAll('img[alt]')) {
            const alt = img.getAttribute('alt').trim();

            if (!alt) {
                continue;
            }

            const match = alt.match(REDUNDANT_RE);

            if (match) {
                const html = img.outerHTML.length > 200 ? img.outerHTML.slice(0, 200) + '...' : img.outerHTML;
                result.push({
                    html,
                    target: ['img'],
                    failureSummary: `Remove the redundant "${match[0].trim()}" prefix — start the alt text with the description itself.`,
                });
            }
        }

        return result;
    });

    return buildViolation('content-img-redundant-descriptor', nodes);
}

// =============================================================================
// Category B — Links
// =============================================================================

async function checkLinkUrlAsText(page) {
    const nodes = await page.evaluate(() => {
        const URL_RE = /^(https?:\/\/|www\.)\S+/i;
        const result = [];

        for (const link of document.querySelectorAll('a[href]')) {
            const ariaLabel = (link.getAttribute('aria-label') || '').trim();

            if (ariaLabel) {
                continue; // aria-label overrides visible text for assistive technology
            }

            const text = link.textContent.trim();

            if (URL_RE.test(text)) {
                const html = link.outerHTML.length > 200 ? link.outerHTML.slice(0, 200) + '...' : link.outerHTML;
                result.push({
                    html,
                    target: ['a'],
                    failureSummary: 'Link text is a raw URL — replace with a concise description of the link destination.',
                });
            }
        }

        return result;
    });

    return buildViolation('content-link-url-as-text', nodes);
}

async function checkDuplicateLinkText(page) {
    const nodes = await page.evaluate(() => {
        /** @type {Map<string, {href: string, el: Element}[]>} */
        const byText = new Map();

        for (const link of document.querySelectorAll('a[href]')) {
            const text = (link.getAttribute('aria-label') || link.textContent || '').trim().toLowerCase();

            if (!text || text.length < 2) {
                continue;
            }

            const href = (link.getAttribute('href') || '').split('?')[0].split('#')[0];

            if (!byText.has(text)) {
                byText.set(text, []);
            }

            byText.get(text).push({ href, el: link });
        }

        const result = [];

        for (const [text, entries] of byText) {
            const uniqueHrefs = new Set(entries.map((e) => e.href));

            if (uniqueHrefs.size > 1) {
                const seen = new Set();

                for (const { href, el } of entries) {
                    if (seen.has(href)) {
                        continue;
                    }

                    seen.add(href);
                    const html = el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML;
                    result.push({
                        html,
                        target: ['a'],
                        failureSummary: `"${text.slice(0, 60)}" is used for ${uniqueHrefs.size} different destinations — make each link's text unique and descriptive.`,
                    });
                }
            }
        }

        return result;
    });

    return buildViolation('content-duplicate-link-text', nodes);
}

// =============================================================================
// Category C — Headings
// =============================================================================

async function checkMultipleH1(page) {
    const nodes = await page.evaluate(() => {
        const h1s = [...document.querySelectorAll('h1')];

        if (h1s.length <= 1) {
            return [];
        }

        return h1s.map((h) => {
            const html = h.outerHTML.length > 200 ? h.outerHTML.slice(0, 200) + '...' : h.outerHTML;

            return {
                html,
                target: ['h1'],
                failureSummary: `Page has ${h1s.length} <h1> elements — consolidate to a single h1 that describes the main page topic.`,
            };
        });
    });

    return buildViolation('content-multiple-h1', nodes);
}

// =============================================================================
// Category D — Document
// =============================================================================

async function checkGenericPageTitle(page) {
    const nodes = await page.evaluate(() => {
        const title = (document.title || '').trim();

        if (!title) {
            return []; // missing title handled by axe / SR runner
        }

        const bare = title.split(/[|\-–—]/)[0].trim().toLowerCase();
        const GENERIC = new Set([
            'home', 'homepage', 'index', 'untitled', 'untitled document',
            'untitled page', 'welcome', 'page', 'new page', 'default',
            'website', 'site', 'no title', 'placeholder', 'coming soon',
        ]);

        if (!GENERIC.has(bare)) {
            return [];
        }

        const el = document.querySelector('title');

        return [{
            html: el ? el.outerHTML : `<title>${document.title}</title>`,
            target: ['head > title'],
            failureSummary: `Page title "${document.title}" is generic — use a unique, descriptive title that identifies the page purpose.`,
        }];
    });

    return buildViolation('content-generic-page-title', nodes);
}

// =============================================================================
// Category E — Video & Media
// =============================================================================

async function checkVideoMissingCaptions(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const video of document.querySelectorAll('video')) {
            const tracks = [...video.querySelectorAll('track')];
            const hasCaptionTrack = tracks.some(
                (t) => ['captions', 'subtitles'].includes((t.getAttribute('kind') || '').toLowerCase()),
            );

            if (!hasCaptionTrack) {
                const html = video.outerHTML.length > 200 ? video.outerHTML.slice(0, 200) + '...' : video.outerHTML;
                result.push({
                    html,
                    target: ['video'],
                    failureSummary: 'Add <track kind="captions" src="captions.vtt"> inside this video to provide captions for deaf and hard-of-hearing users.',
                });
            }
        }

        return result;
    });

    return buildViolation('content-video-missing-captions', nodes);
}

async function checkVideoMissingTranscript(page) {
    const nodes = await page.evaluate(() => {
        function hasNearbyTranscript(el) {
            let container = el.parentElement;

            for (let depth = 0; depth < 4 && container; depth++) {
                const hasLink = [...container.querySelectorAll('a, details')].some(
                    (a) => /transcript/i.test(a.textContent) || /transcript/i.test(a.getAttribute('href') || ''),
                );

                if (hasLink) {
                    return true;
                }

                container = container.parentElement;
            }

            return false;
        }

        const result = [];

        for (const video of document.querySelectorAll('video')) {
            const tracks = [...video.querySelectorAll('track')];
            const hasCaptionTrack = tracks.some(
                (t) => ['captions', 'subtitles'].includes((t.getAttribute('kind') || '').toLowerCase()),
            );

            if (hasCaptionTrack) {
                continue; // captions satisfy WCAG 1.2.3
            }

            if (!hasNearbyTranscript(video)) {
                const html = video.outerHTML.length > 200 ? video.outerHTML.slice(0, 200) + '...' : video.outerHTML;
                result.push({
                    html,
                    target: ['video'],
                    failureSummary: 'Provide a text transcript for this video and link it adjacent to the player as an alternative to captions.',
                });
            }
        }

        return result;
    });

    return buildViolation('content-video-missing-transcript', nodes);
}

async function checkAudioMissingTranscript(page) {
    const nodes = await page.evaluate(() => {
        function hasNearbyTranscript(el) {
            let container = el.parentElement;

            for (let depth = 0; depth < 4 && container; depth++) {
                const hasLink = [...container.querySelectorAll('a, details')].some(
                    (a) => /transcript/i.test(a.textContent) || /transcript/i.test(a.getAttribute('href') || ''),
                );

                if (hasLink) {
                    return true;
                }

                container = container.parentElement;
            }

            return false;
        }

        const result = [];

        for (const audio of document.querySelectorAll('audio')) {
            if (!hasNearbyTranscript(audio)) {
                const html = audio.outerHTML.length > 200 ? audio.outerHTML.slice(0, 200) + '...' : audio.outerHTML;
                result.push({
                    html,
                    target: ['audio'],
                    failureSummary: 'Provide a text transcript for this audio clip and link it adjacent to the player.',
                });
            }
        }

        return result;
    });

    return buildViolation('content-audio-missing-transcript', nodes);
}

async function checkYoutubeCaptions(page) {
    const nodes = await page.evaluate(() => {
        const YOUTUBE_RE = /(?:youtube\.com\/embed|youtube-nocookie\.com\/embed|youtu\.be)/i;
        const result = [];

        for (const iframe of document.querySelectorAll('iframe')) {
            const src = iframe.getAttribute('src') || iframe.getAttribute('data-src') || '';

            if (YOUTUBE_RE.test(src)) {
                const html = iframe.outerHTML.length > 200 ? iframe.outerHTML.slice(0, 200) + '...' : iframe.outerHTML;
                result.push({
                    html,
                    target: ['iframe'],
                    failureSummary: 'YouTube embed detected — verify captions are enabled in the video settings. Auto-generated captions do not meet WCAG.',
                });
            }
        }

        return result;
    });

    return buildViolation('content-youtube-captions-unknown', nodes);
}

async function checkVimeoCaptions(page) {
    const nodes = await page.evaluate(() => {
        const VIMEO_RE = /player\.vimeo\.com\/video/i;
        const result = [];

        for (const iframe of document.querySelectorAll('iframe')) {
            const src = iframe.getAttribute('src') || iframe.getAttribute('data-src') || '';

            if (VIMEO_RE.test(src)) {
                const html = iframe.outerHTML.length > 200 ? iframe.outerHTML.slice(0, 200) + '...' : iframe.outerHTML;
                result.push({
                    html,
                    target: ['iframe'],
                    failureSummary: 'Vimeo embed detected — verify that captions are uploaded and enabled in the Vimeo video settings.',
                });
            }
        }

        return result;
    });

    return buildViolation('content-vimeo-captions-unknown', nodes);
}

async function checkVideoEmbedUnverified(page) {
    const nodes = await page.evaluate(() => {
        const VIDEO_HOST_RE = /(?:wistia\.(?:com|net)|jwplatform\.com|jwpcdn\.com|brightcove\.(?:com|net)|dailymotion\.com|twitch\.tv|loom\.com|kaltura\.com|panopto\.(?:com|eu)|sproutvideo\.com|vidyard\.com|cloudflare\.com\/stream|fast\.wistia)/i;
        const ALREADY_HANDLED_RE = /(?:youtube|youtu\.be|vimeo)/i;
        const result = [];

        for (const iframe of document.querySelectorAll('iframe')) {
            const src = iframe.getAttribute('src') || iframe.getAttribute('data-src') || '';

            if (!ALREADY_HANDLED_RE.test(src) && VIDEO_HOST_RE.test(src)) {
                const html = iframe.outerHTML.length > 200 ? iframe.outerHTML.slice(0, 200) + '...' : iframe.outerHTML;
                result.push({
                    html,
                    target: ['iframe'],
                    failureSummary: 'Third-party video embed detected — manually verify that captions and/or a transcript are available.',
                });
            }
        }

        return result;
    });

    return buildViolation('content-video-embed-unverified', nodes);
}

// =============================================================================
// Visible text extraction (for server-side reading metrics)
// =============================================================================

/**
 * Extract the plain visible text from the page body.
 * Strips nav, header, footer, scripts, and styles to focus on content.
 * Truncated to 8000 characters — sufficient for accurate FK computation.
 *
 * @param {import('playwright').Page} page
 * @returns {Promise<string>}
 */
async function extractVisibleText(page) {
    return page.evaluate(() => {
        const clone = document.body.cloneNode(true);

        // Remove non-content elements
        for (const el of clone.querySelectorAll('script, style, noscript, nav, header, footer, [aria-hidden="true"]')) {
            el.remove();
        }

        const text = (clone.textContent || '')
            .replace(/\s+/g, ' ')
            .trim();

        return text.length > 8000 ? text.slice(0, 8000) : text;
    });
}

// =============================================================================
// Main runner
// =============================================================================

/** Ordered list of all 14 check functions. */
const CHECKS = [
    // A — Images
    checkImgFilenameAlt,
    checkImgGenericAlt,
    checkImgLongAlt,
    checkImgRedundantDescriptor,
    // B — Links
    checkLinkUrlAsText,
    checkDuplicateLinkText,
    // C — Headings
    checkMultipleH1,
    // D — Document
    checkGenericPageTitle,
    // E — Video & Media
    checkVideoMissingCaptions,
    checkVideoMissingTranscript,
    checkAudioMissingTranscript,
    checkYoutubeCaptions,
    checkVimeoCaptions,
    checkVideoEmbedUnverified,
];

/**
 * Run all content checks against the given Playwright page.
 * Returns the page URL, violations array, and extracted visible text.
 *
 * @param {import('playwright').Page} page
 * @param {object} _config  Reserved for future per-check toggles.
 * @returns {Promise<{url: string, violations: Array, visibleText: string}>}
 */
async function runContent(page, _config) {
    const url = page.url();
    const violations = [];

    for (const check of CHECKS) {
        const result = await check(page);

        if (result !== null) {
            violations.push(result);
        }
    }

    const visibleText = await extractVisibleText(page);

    return { url, violations, visibleText };
}

module.exports = { runContent };

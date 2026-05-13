'use strict';

/**
 * Per-check metadata: impact, description, helpUrl, and WCAG-aligned tags.
 * Tags follow the axe-core convention so IssueNormalizer can classify them.
 *
 * @type {Record<string, {impact: string, description: string, helpUrl: string, tags: string[]}>}
 */
const CHECK_META = {
    // A — Landmarks & Page Structure
    'sr-missing-main-landmark': {
        impact: 'serious',
        description: 'Page has no main landmark — screen reader users cannot bypass repeated navigation to reach the main content.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/bypass-blocks.html',
        tags: ['wcag2a', 'wcag241', 'cat.structure'],
    },
    'sr-missing-page-title': {
        impact: 'serious',
        description: 'Page has no meaningful title — screen readers announce the title as the first piece of information about the page.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/page-titled.html',
        tags: ['wcag2a', 'wcag242', 'cat.structure'],
    },
    'sr-missing-h1': {
        impact: 'moderate',
        description: 'Page has no h1 heading — screen reader users rely on headings to understand the page topic.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/headings-and-labels.html',
        tags: ['wcag2aa', 'wcag246', 'cat.structure'],
    },
    'sr-skipped-heading-level': {
        impact: 'moderate',
        description: 'Heading levels are skipped — this disrupts the logical structure announced by screen readers.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/headings-and-labels.html',
        tags: ['wcag2a', 'wcag131', 'cat.structure'],
    },
    'sr-duplicate-landmark-no-label': {
        impact: 'moderate',
        description: 'Multiple landmarks of the same type exist without distinguishing labels — screen readers cannot differentiate them.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/identify-purpose.html',
        tags: ['wcag21aaa', 'wcag136', 'cat.structure'],
    },
    'sr-duplicate-heading-text': {
        impact: 'minor',
        description: 'Two or more headings at the same level have identical text — this reduces clarity when navigating by headings.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/headings-and-labels.html',
        tags: ['wcag2aa', 'wcag246', 'cat.structure'],
    },

    // B — Interactive Elements & Forms
    'sr-unlabelled-interactive': {
        impact: 'critical',
        description: 'Interactive element has no accessible name — screen readers announce it with no context.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/name-role-value.html',
        tags: ['wcag412', 'wcag2a', 'cat.forms'],
    },
    'sr-generic-link-text': {
        impact: 'serious',
        description: 'Link text is generic ("click here", "read more", etc.) and does not describe the destination.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/link-purpose-in-context.html',
        tags: ['wcag2a', 'wcag244', 'cat.structure'],
    },
    'sr-ambiguous-button-text': {
        impact: 'moderate',
        description: 'Multiple buttons share identical accessible names but trigger different actions.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/headings-and-labels.html',
        tags: ['wcag2aa', 'wcag246', 'cat.forms'],
    },
    'sr-placeholder-only-label': {
        impact: 'serious',
        description: 'Input field uses only a placeholder as its label — the placeholder disappears on focus, leaving the field unlabelled.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/info-and-relationships.html',
        tags: ['wcag131', 'wcag2a', 'cat.forms'],
    },
    'sr-required-field-not-announced': {
        impact: 'moderate',
        description: 'Required field does not communicate its required state to screen readers.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/labels-or-instructions.html',
        tags: ['wcag332', 'wcag2a', 'cat.forms'],
    },
    'sr-missing-fieldset-legend': {
        impact: 'moderate',
        description: 'Radio or checkbox group is not wrapped in a fieldset with legend — the group context is not announced.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/info-and-relationships.html',
        tags: ['wcag131', 'wcag2a', 'cat.forms'],
    },
    'sr-select-no-label': {
        impact: 'critical',
        description: 'Select element has no associated label — screen readers cannot convey what the select controls.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/name-role-value.html',
        tags: ['wcag412', 'wcag2a', 'cat.forms'],
    },
    'sr-form-error-not-associated': {
        impact: 'serious',
        description: 'Form validation error is not programmatically associated with its input via aria-describedby.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/error-identification.html',
        tags: ['wcag331', 'wcag2a', 'cat.forms'],
    },
    'sr-error-not-live': {
        impact: 'serious',
        description: 'Form error message appears in the DOM but is not in a live region — screen readers may not announce it.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/error-identification.html',
        tags: ['wcag331', 'wcag2a', 'cat.forms'],
    },
    'sr-status-message-not-announced': {
        impact: 'moderate',
        description: 'Status or success message lacks role="status" or aria-live — screen readers will not announce it.',
        helpUrl: 'https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html',
        tags: ['wcag413', 'wcag2aa', 'cat.aria'],
    },

    // C — Images & Media
    'sr-image-no-alt': {
        impact: 'critical',
        description: 'Image has no alternative text — screen readers announce the filename or skip the image silently.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/non-text-content.html',
        tags: ['wcag111', 'wcag2a', 'cat.images'],
    },
    'sr-decorative-image-announced': {
        impact: 'minor',
        description: 'Decorative image (alt="" or role="presentation") still has a title attribute that may be announced by screen readers.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/non-text-content.html',
        tags: ['wcag111', 'wcag2a', 'cat.images'],
    },
    'sr-redundant-alt-text': {
        impact: 'minor',
        description: 'Image alt text duplicates adjacent visible text, causing screen reader users to hear the same content twice.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/non-text-content.html',
        tags: ['wcag111', 'wcag2a', 'cat.images'],
    },
    'sr-svg-no-label': {
        impact: 'serious',
        description: 'SVG graphic appears to be meaningful but has no accessible name (no <title>, aria-label, or aria-labelledby).',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/non-text-content.html',
        tags: ['wcag111', 'wcag2a', 'cat.images'],
    },
    'sr-icon-button-no-label': {
        impact: 'critical',
        description: 'Icon-only button has no accessible name — screen readers cannot convey its purpose.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/name-role-value.html',
        tags: ['wcag412', 'wcag2a', 'cat.forms'],
    },

    // D — Focus & Keyboard Navigation
    'sr-focus-order-mismatch': {
        impact: 'moderate',
        description: 'Tab order of focusable elements does not match their visual position — keyboard and screen reader navigation diverges from visual flow.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/focus-order.html',
        tags: ['wcag2a', 'wcag243', 'cat.keyboard'],
    },
    'sr-skip-link-missing': {
        impact: 'moderate',
        description: 'No skip navigation link is present — keyboard and screen reader users must tab through repeated content on every page.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/bypass-blocks.html',
        tags: ['wcag2a', 'wcag241', 'cat.keyboard'],
    },
    'sr-skip-link-not-functional': {
        impact: 'moderate',
        description: 'Skip navigation link is present but its target anchor does not exist — the skip link does not work.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/bypass-blocks.html',
        tags: ['wcag2a', 'wcag241', 'cat.keyboard'],
    },
    'sr-keyboard-trap': {
        impact: 'critical',
        description: 'A dialog or region may trap keyboard focus without providing a standard escape mechanism.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/no-keyboard-trap.html',
        tags: ['wcag2a', 'wcag212', 'cat.keyboard'],
    },
    'sr-focus-not-visible': {
        impact: 'serious',
        description: 'Focusable element has inline CSS that suppresses the focus indicator — keyboard users cannot see which element is active.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/focus-visible.html',
        tags: ['wcag2aa', 'wcag247', 'cat.keyboard'],
    },
    'sr-modal-focus-not-trapped': {
        impact: 'serious',
        description: 'Dialog or modal does not have aria-modal="true" — focus may not be confined within the dialog.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/focus-order.html',
        tags: ['wcag2a', 'wcag243', 'cat.keyboard'],
    },
    'sr-modal-focus-not-returned': {
        impact: 'moderate',
        description: 'Dialog close button lacks a mechanism to return focus to the trigger element on close.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/focus-order.html',
        tags: ['wcag2a', 'wcag243', 'cat.keyboard'],
    },

    // E — Dynamic Content & ARIA
    'sr-silent-live-region': {
        impact: 'serious',
        description: 'Live region is present but empty on page load — verify that content is dynamically inserted when updates occur.',
        helpUrl: 'https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html',
        tags: ['wcag413', 'wcag2aa', 'cat.aria'],
    },
    'sr-live-region-off': {
        impact: 'moderate',
        description: 'Element uses aria-live="off" in a context where dynamic content requires announcement.',
        helpUrl: 'https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html',
        tags: ['wcag413', 'wcag2aa', 'cat.aria'],
    },
    'sr-expanded-state-not-announced': {
        impact: 'moderate',
        description: 'aria-expanded is set on a non-interactive element — state changes may not be reliably announced by screen readers.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/name-role-value.html',
        tags: ['wcag412', 'wcag2a', 'cat.aria'],
    },
    'sr-loading-state-not-announced': {
        impact: 'moderate',
        description: 'Loading indicator or spinner is visible but has no aria-busy or live region to announce the loading state.',
        helpUrl: 'https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html',
        tags: ['wcag413', 'wcag2aa', 'cat.aria'],
    },
    'sr-tooltip-not-announced': {
        impact: 'moderate',
        description: 'Tooltip element (role="tooltip") is present but not associated with its trigger via aria-describedby.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/info-and-relationships.html',
        tags: ['wcag131', 'wcag2a', 'cat.aria'],
    },

    // F — Tables
    'sr-table-no-headers': {
        impact: 'serious',
        description: 'Data table has no header cells (<th>) — screen readers cannot associate data cells with their column or row headers.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/info-and-relationships.html',
        tags: ['wcag131', 'wcag2a', 'cat.tables'],
    },
    'sr-table-header-not-associated': {
        impact: 'moderate',
        description: 'Table header cells lack scope attributes — screen readers may not correctly associate headers with data cells.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/info-and-relationships.html',
        tags: ['wcag131', 'wcag2a', 'cat.tables'],
    },
    'sr-table-missing-caption': {
        impact: 'minor',
        description: 'Complex table (more than 3 columns) has no caption — screen readers cannot announce the table purpose.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/info-and-relationships.html',
        tags: ['wcag131', 'wcag2a', 'cat.tables'],
    },
    'sr-layout-table-has-headers': {
        impact: 'moderate',
        description: 'Table marked as layout/presentation contains <th> elements — this causes incorrect structural announcements.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/info-and-relationships.html',
        tags: ['wcag131', 'wcag2a', 'cat.tables'],
    },

    // G — Language & Reading Order
    'sr-missing-lang-attribute': {
        impact: 'serious',
        description: 'The <html> element has no lang attribute — screen readers will announce content in the wrong language.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/language-of-page.html',
        tags: ['wcag311', 'wcag2a', 'cat.language'],
    },
    'sr-content-before-nav': {
        impact: 'minor',
        description: 'Main content appears before navigation in the DOM but navigation is visually first — screen reader reading order diverges from visual order.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/meaningful-sequence.html',
        tags: ['wcag132', 'wcag2a', 'cat.structure'],
    },
    'sr-off-screen-content-announced': {
        impact: 'minor',
        description: 'Visually hidden content using clip patterns is in the accessibility tree — ensure its reading order position is intentional.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/meaningful-sequence.html',
        tags: ['wcag132', 'wcag2a', 'cat.structure'],
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

/**
 * Safely truncate an outerHTML string for storage.
 *
 * @param {string} html
 * @returns {string}
 */
function safeHtml(html) {
    return html && html.length > 200 ? html.slice(0, 200) + '...' : (html || '');
}

// =============================================================================
// Category A — Landmarks & Page Structure
// =============================================================================

async function checkMissingMainLandmark(page) {
    const nodes = await page.evaluate(() => {
        if (document.querySelector('main, [role="main"]')) {
            return [];
        }

        return [{
            html: document.documentElement.outerHTML.slice(0, 80) + '...',
            target: ['html'],
            failureSummary: 'Add a <main> element or role="main" landmark to identify the primary content region.',
        }];
    });

    return buildViolation('sr-missing-main-landmark', nodes);
}

async function checkMissingPageTitle(page) {
    const nodes = await page.evaluate(() => {
        const title = (document.title || '').trim();

        if (title) {
            return [];
        }

        const el = document.querySelector('title');

        return [{
            html: el ? el.outerHTML : '<title></title>',
            target: ['head > title'],
            failureSummary: 'Set a meaningful, descriptive document title in the <title> element.',
        }];
    });

    return buildViolation('sr-missing-page-title', nodes);
}

async function checkMissingH1(page) {
    const nodes = await page.evaluate(() => {
        if (document.querySelector('h1')) {
            return [];
        }

        return [{
            html: '<body>',
            target: ['body'],
            failureSummary: 'Add a single <h1> heading that describes the page topic.',
        }];
    });

    return buildViolation('sr-missing-h1', nodes);
}

async function checkSkippedHeadingLevel(page) {
    const nodes = await page.evaluate(() => {
        const headings = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')];
        const result = [];
        let prevLevel = 0;

        for (const h of headings) {
            const level = parseInt(h.tagName[1], 10);

            if (prevLevel > 0 && level > prevLevel + 1) {
                const html = h.outerHTML.length > 200 ? h.outerHTML.slice(0, 200) + '...' : h.outerHTML;

                result.push({
                    html,
                    target: [h.tagName.toLowerCase() + (h.id ? '#' + h.id : '')],
                    failureSummary: `${h.tagName} follows H${prevLevel} — use consecutive heading levels without skipping.`,
                });
            }

            prevLevel = level;
        }

        return result;
    });

    return buildViolation('sr-skipped-heading-level', nodes);
}

async function checkDuplicateLandmarkNoLabel(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        const groups = [
            { selector: 'nav, [role="navigation"]', label: 'nav' },
            { selector: 'aside, [role="complementary"]', label: 'aside' },
            { selector: '[role="search"]', label: 'search region' },
        ];

        for (const { selector, label } of groups) {
            const els = [...document.querySelectorAll(selector)].filter((el) => {
                const style = window.getComputedStyle(el);
                return style.display !== 'none' && style.visibility !== 'hidden';
            });

            if (els.length <= 1) {
                continue;
            }

            const unlabelled = els.filter(
                (el) => !el.getAttribute('aria-label') && !el.getAttribute('aria-labelledby') && !el.getAttribute('title'),
            );

            if (unlabelled.length > 1) {
                for (const el of unlabelled) {
                    const html = el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML;
                    result.push({
                        html,
                        target: [selector],
                        failureSummary: `Multiple ${label} landmarks exist without distinguishing labels — add aria-label to each.`,
                    });
                }
            }
        }

        return result;
    });

    return buildViolation('sr-duplicate-landmark-no-label', nodes);
}

async function checkDuplicateHeadingText(page) {
    const nodes = await page.evaluate(() => {
        const headings = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')];
        const seen = new Map();
        const result = [];

        for (const h of headings) {
            const key = h.tagName + '|' + h.textContent.trim().toLowerCase();

            if (seen.has(key)) {
                const text = h.textContent.trim().slice(0, 50);
                const html = h.outerHTML.length > 200 ? h.outerHTML.slice(0, 200) + '...' : h.outerHTML;
                result.push({
                    html,
                    target: [h.tagName.toLowerCase()],
                    failureSummary: `${h.tagName} text "${text}" is duplicated — make each heading unique to aid navigation.`,
                });
            } else {
                seen.set(key, true);
            }
        }

        return result;
    });

    return buildViolation('sr-duplicate-heading-text', nodes);
}

// =============================================================================
// Category B — Interactive Elements & Forms
// =============================================================================

async function checkUnlabelledInteractive(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        const buttons = [
            ...document.querySelectorAll('button, [role="button"], input[type="button"], input[type="submit"], input[type="reset"]'),
        ];

        for (const btn of buttons) {
            const name = (btn.getAttribute('aria-label') || '').trim() ||
                (btn.getAttribute('aria-labelledby') || '').trim() ||
                btn.textContent.trim() ||
                (btn.getAttribute('value') || '').trim() ||
                (btn.getAttribute('title') || '').trim();

            if (!name) {
                const html = btn.outerHTML.length > 200 ? btn.outerHTML.slice(0, 200) + '...' : btn.outerHTML;
                result.push({ html, target: ['button'], failureSummary: 'Add an accessible name via text content, aria-label, or aria-labelledby.' });
            }
        }

        const links = [...document.querySelectorAll('a[href], [role="link"]')];

        for (const link of links) {
            const imgAlt = link.querySelector('img') ? (link.querySelector('img').getAttribute('alt') || '').trim() : '';
            const name = (link.getAttribute('aria-label') || '').trim() ||
                (link.getAttribute('aria-labelledby') || '').trim() ||
                link.textContent.trim() ||
                (link.getAttribute('title') || '').trim() ||
                imgAlt;

            if (!name) {
                const html = link.outerHTML.length > 200 ? link.outerHTML.slice(0, 200) + '...' : link.outerHTML;
                result.push({ html, target: ['a'], failureSummary: 'Add an accessible name via text content, aria-label, or aria-labelledby.' });
            }
        }

        return result;
    });

    return buildViolation('sr-unlabelled-interactive', nodes);
}

async function checkGenericLinkText(page) {
    const genericTexts = [
        'click here', 'here', 'read more', 'learn more', 'more', 'link',
        'details', 'info', 'information', 'this link', 'this page',
        'continue', 'go', 'download', 'view', 'see', 'open', 'access',
    ];

    const nodes = await page.evaluate((generic) => {
        const result = [];
        const links = [...document.querySelectorAll('a[href]')];

        for (const link of links) {
            const text = (link.getAttribute('aria-label') || link.textContent || '').trim().toLowerCase();

            if (generic.includes(text)) {
                const html = link.outerHTML.length > 200 ? link.outerHTML.slice(0, 200) + '...' : link.outerHTML;
                result.push({
                    html,
                    target: ['a'],
                    failureSummary: `Link text "${text}" does not describe the destination — replace with descriptive text.`,
                });
            }
        }

        return result;
    }, genericTexts);

    return buildViolation('sr-generic-link-text', nodes);
}

async function checkAmbiguousButtonText(page) {
    const nodes = await page.evaluate(() => {
        const buttons = [
            ...document.querySelectorAll('button, [role="button"], input[type="button"], input[type="submit"]'),
        ];
        const nameMap = new Map();

        for (const btn of buttons) {
            const name = (btn.getAttribute('aria-label') || btn.textContent || btn.getAttribute('value') || '').trim().toLowerCase();

            if (!name) {
                continue;
            }

            if (!nameMap.has(name)) {
                nameMap.set(name, []);
            }

            nameMap.get(name).push(btn);
        }

        const result = [];

        for (const [name, btns] of nameMap) {
            if (btns.length > 1) {
                for (const btn of btns) {
                    const html = btn.outerHTML.length > 200 ? btn.outerHTML.slice(0, 200) + '...' : btn.outerHTML;
                    result.push({
                        html,
                        target: ['button'],
                        failureSummary: `Multiple buttons share the label "${name}" — differentiate them with unique aria-label values.`,
                    });
                }
            }
        }

        return result;
    });

    return buildViolation('sr-ambiguous-button-text', nodes);
}

async function checkPlaceholderOnlyLabel(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const inputs = [
            ...document.querySelectorAll(
                'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="image"]), textarea',
            ),
        ];

        for (const input of inputs) {
            const hasLabel = input.labels && input.labels.length > 0;
            const hasAriaLabel = !!input.getAttribute('aria-label');
            const hasAriaLabelledby = !!input.getAttribute('aria-labelledby');
            const hasTitle = !!input.getAttribute('title');
            const hasPlaceholder = !!input.getAttribute('placeholder');

            if (hasPlaceholder && !hasLabel && !hasAriaLabel && !hasAriaLabelledby && !hasTitle) {
                const html = input.outerHTML.length > 200 ? input.outerHTML.slice(0, 200) + '...' : input.outerHTML;
                result.push({
                    html,
                    target: ['input'],
                    failureSummary: 'Replace placeholder-only labelling with a persistent visible <label> or aria-label attribute.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-placeholder-only-label', nodes);
}

async function checkRequiredFieldNotAnnounced(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const inputs = [
            ...document.querySelectorAll(
                'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]), select, textarea',
            ),
        ];

        for (const input of inputs) {
            const isProgrammaticallyRequired = input.required || input.getAttribute('aria-required') === 'true';

            if (isProgrammaticallyRequired) {
                continue;
            }

            // Heuristic: label text contains * or the word "required"
            const label = input.labels && input.labels[0];
            const labelText = label ? label.textContent : '';

            if (labelText.includes('*') || labelText.toLowerCase().includes('required')) {
                const html = input.outerHTML.length > 200 ? input.outerHTML.slice(0, 200) + '...' : input.outerHTML;
                result.push({
                    html,
                    target: ['input'],
                    failureSummary: 'Add required or aria-required="true" to communicate the required state programmatically to screen readers.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-required-field-not-announced', nodes);
}

async function checkMissingFieldsetLegend(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const radioGroups = new Map();
        const checkboxGroups = new Map();

        for (const input of document.querySelectorAll('input[type="radio"], input[type="checkbox"]')) {
            const key = input.name || ('__ungrouped_' + input.id);
            const map = input.type === 'radio' ? radioGroups : checkboxGroups;

            if (!map.has(key)) {
                map.set(key, []);
            }

            map.get(key).push(input);
        }

        const allGroups = [...radioGroups.values(), ...checkboxGroups.values()].filter((g) => g.length > 1);

        for (const group of allGroups) {
            const first = group[0];
            const inFieldset = !!first.closest('fieldset');
            const inGroupRole = !!first.closest('[role="group"], [role="radiogroup"]');

            if (!inFieldset && !inGroupRole) {
                const html = first.outerHTML.length > 200 ? first.outerHTML.slice(0, 200) + '...' : first.outerHTML;
                result.push({
                    html,
                    target: ['input[type="radio"], input[type="checkbox"]'],
                    failureSummary: 'Wrap related radio/checkbox inputs in a <fieldset> with a <legend>, or use role="group" with aria-labelledby.',
                });
            } else if (inFieldset) {
                const fieldset = first.closest('fieldset');
                const legend = fieldset.querySelector('legend');

                if (!legend || !legend.textContent.trim()) {
                    const html = fieldset.outerHTML.length > 200 ? fieldset.outerHTML.slice(0, 200) + '...' : fieldset.outerHTML;
                    result.push({
                        html,
                        target: ['fieldset'],
                        failureSummary: 'Add a <legend> element with descriptive text inside the <fieldset>.',
                    });
                }
            }
        }

        return result;
    });

    return buildViolation('sr-missing-fieldset-legend', nodes);
}

async function checkSelectNoLabel(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const sel of document.querySelectorAll('select')) {
            const hasLabel = sel.labels && sel.labels.length > 0;
            const hasAriaLabel = !!sel.getAttribute('aria-label');
            const hasAriaLabelledby = !!sel.getAttribute('aria-labelledby');
            const hasTitle = !!sel.getAttribute('title');

            if (!hasLabel && !hasAriaLabel && !hasAriaLabelledby && !hasTitle) {
                const html = sel.outerHTML.length > 200 ? sel.outerHTML.slice(0, 200) + '...' : sel.outerHTML;
                result.push({
                    html,
                    target: ['select'],
                    failureSummary: 'Add an associated <label>, aria-label, or aria-labelledby to identify this select element.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-select-no-label', nodes);
}

async function checkFormErrorNotAssociated(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const errorEls = [
            ...document.querySelectorAll(
                '[role="alert"], .error, .invalid, .field-error, .form-error, [class*="error-message"], [class*="field__error"]',
            ),
        ];

        for (const err of errorEls) {
            if (!err.textContent.trim()) {
                continue;
            }

            const id = err.id;

            if (!id) {
                const html = err.outerHTML.length > 200 ? err.outerHTML.slice(0, 200) + '...' : err.outerHTML;
                result.push({
                    html,
                    target: ['.error'],
                    failureSummary: 'Give the error element an id and reference it from the associated input via aria-describedby.',
                });
                continue;
            }

            const referenced = !!document.querySelector(`[aria-describedby~="${CSS.escape(id)}"]`);

            if (!referenced) {
                const html = err.outerHTML.length > 200 ? err.outerHTML.slice(0, 200) + '...' : err.outerHTML;
                result.push({
                    html,
                    target: [`#${id}`],
                    failureSummary: `Reference this error message from its input field using aria-describedby="${id}".`,
                });
            }
        }

        return result;
    });

    return buildViolation('sr-form-error-not-associated', nodes);
}

async function checkErrorNotLive(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const errorEls = [
            ...document.querySelectorAll(
                '.error, .invalid, .field-error, .form-error, [class*="error"][class*="message"], [class*="validation-message"]',
            ),
        ];

        for (const err of errorEls) {
            if (!err.textContent.trim()) {
                continue;
            }

            const hasLive = !!err.closest('[aria-live]') || err.getAttribute('role') === 'alert' || err.getAttribute('role') === 'status';

            if (!hasLive) {
                const html = err.outerHTML.length > 200 ? err.outerHTML.slice(0, 200) + '...' : err.outerHTML;
                result.push({
                    html,
                    target: ['.error'],
                    failureSummary: 'Add role="alert" or aria-live="assertive" so screen readers announce validation errors automatically.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-error-not-live', nodes);
}

async function checkStatusMessageNotAnnounced(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const statusEls = [
            ...document.querySelectorAll(
                '.success, .notice, [class*="success-message"], [class*="status-message"], [class*="toast"], [class*="notification-message"], [class*="alert-message"]',
            ),
        ];

        for (const el of statusEls) {
            if (!el.textContent.trim()) {
                continue;
            }

            const role = el.getAttribute('role');
            const hasLive = !!el.getAttribute('aria-live');
            const isAnnounced = role === 'alert' || role === 'status' || role === 'log';

            if (!isAnnounced && !hasLive) {
                const html = el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML;
                result.push({
                    html,
                    target: ['.success'],
                    failureSummary: 'Add role="status" and aria-live="polite" so screen readers announce status messages automatically.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-status-message-not-announced', nodes);
}

// =============================================================================
// Category C — Images & Media
// =============================================================================

async function checkImageNoAlt(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const img of document.querySelectorAll('img')) {
            const role = img.getAttribute('role');

            if (role === 'presentation' || role === 'none') {
                continue;
            }

            if (img.getAttribute('alt') === null) {
                const html = img.outerHTML.length > 200 ? img.outerHTML.slice(0, 200) + '...' : img.outerHTML;
                result.push({
                    html,
                    target: ['img'],
                    failureSummary: 'Add alt="" for decorative images or descriptive alt text for meaningful images.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-image-no-alt', nodes);
}

async function checkDecorativeImageAnnounced(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const img of document.querySelectorAll('img[alt=""], img[role="presentation"], img[role="none"]')) {
            const title = (img.getAttribute('title') || '').trim();

            if (title) {
                const html = img.outerHTML.length > 200 ? img.outerHTML.slice(0, 200) + '...' : img.outerHTML;
                result.push({
                    html,
                    target: ['img'],
                    failureSummary: 'Remove the title attribute from decorative images — it will be announced by some screen readers.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-decorative-image-announced', nodes);
}

async function checkRedundantAltText(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const img of document.querySelectorAll('img[alt]')) {
            const alt = img.getAttribute('alt').trim().toLowerCase();

            if (alt.length < 6) {
                continue;
            }

            const parent = img.parentElement;

            if (!parent) {
                continue;
            }

            const siblingText = [...parent.childNodes]
                .filter((n) => n !== img && (n.nodeType === Node.TEXT_NODE || n.nodeType === Node.ELEMENT_NODE))
                .map((n) => n.textContent)
                .join(' ')
                .trim()
                .toLowerCase();

            if (siblingText.includes(alt)) {
                const html = img.outerHTML.length > 200 ? img.outerHTML.slice(0, 200) + '...' : img.outerHTML;
                result.push({
                    html,
                    target: ['img'],
                    failureSummary: `Alt text "${alt.slice(0, 50)}" duplicates adjacent visible text — use alt="" for this image instead.`,
                });
            }
        }

        return result;
    });

    return buildViolation('sr-redundant-alt-text', nodes);
}

async function checkSvgNoLabel(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const svg of document.querySelectorAll('svg')) {
            const role = svg.getAttribute('role');

            if (role === 'presentation' || role === 'none') {
                continue;
            }

            const hasTitle = !!svg.querySelector('title');
            const hasAriaLabel = !!svg.getAttribute('aria-label');
            const hasAriaLabelledby = !!svg.getAttribute('aria-labelledby');
            const hasContent = !!svg.querySelector('path, circle, rect, polygon, g, use');

            if (hasContent && !hasTitle && !hasAriaLabel && !hasAriaLabelledby) {
                const html = svg.outerHTML.length > 200 ? svg.outerHTML.slice(0, 200) + '...' : svg.outerHTML;
                result.push({
                    html,
                    target: ['svg'],
                    failureSummary: 'Add a <title> element or aria-label to meaningful SVGs, or set role="presentation" for decorative ones.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-svg-no-label', nodes);
}

async function checkIconButtonNoLabel(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const btn of document.querySelectorAll('button, [role="button"]')) {
            const text = btn.textContent.trim();
            const ariaLabel = (btn.getAttribute('aria-label') || '').trim();
            const ariaLabelledby = (btn.getAttribute('aria-labelledby') || '').trim();
            const title = (btn.getAttribute('title') || '').trim();

            const hasOnlyIcon =
                !text &&
                btn.querySelector('svg, i, [class*="icon"], img') !== null;

            if (hasOnlyIcon && !ariaLabel && !ariaLabelledby && !title) {
                const html = btn.outerHTML.length > 200 ? btn.outerHTML.slice(0, 200) + '...' : btn.outerHTML;
                result.push({
                    html,
                    target: ['button'],
                    failureSummary: 'Add aria-label to icon-only buttons to describe their action to screen reader users.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-icon-button-no-label', nodes);
}

// =============================================================================
// Category D — Focus & Keyboard Navigation
// =============================================================================

async function checkFocusOrderMismatch(page) {
    const nodes = await page.evaluate(() => {
        const focusableSelector =
            'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"]), details > summary';

        const focusable = [...document.querySelectorAll(focusableSelector)].filter((el) => {
            const style = window.getComputedStyle(el);
            return style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
        });

        if (focusable.length < 2) {
            return [];
        }

        const withPositions = focusable.map((el) => {
            const rect = el.getBoundingClientRect();
            return { el, top: rect.top };
        });

        const result = [];

        for (let i = 1; i < withPositions.length; i++) {
            const { el, top } = withPositions[i];
            const prevTop = withPositions[i - 1].top;

            // Flag elements that appear significantly above their DOM predecessor (more than ~30px)
            if (top < prevTop - 30) {
                const html = el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML;
                result.push({
                    html,
                    target: [el.tagName.toLowerCase()],
                    failureSummary: 'This element appears visually above its DOM predecessor — adjust DOM order or CSS order property to match visual flow.',
                });

                if (result.length >= 5) {
                    break;
                }
            }
        }

        return result;
    });

    return buildViolation('sr-focus-order-mismatch', nodes);
}

async function checkSkipLinkMissing(page) {
    const nodes = await page.evaluate(() => {
        const skipLinks = [...document.querySelectorAll('a[href*="#"]')].filter((a) => {
            const text = a.textContent.trim().toLowerCase();
            return text.includes('skip') || text.includes('jump to') || (text.includes('main') && text.includes('content'));
        });

        if (skipLinks.length > 0) {
            return [];
        }

        return [{
            html: '<body>',
            target: ['body'],
            failureSummary: 'Add a "Skip to main content" link as the first focusable element so keyboard users can bypass navigation.',
        }];
    });

    return buildViolation('sr-skip-link-missing', nodes);
}

async function checkSkipLinkNotFunctional(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const skipLinks = [...document.querySelectorAll('a[href*="#"]')].filter((a) => {
            const text = a.textContent.trim().toLowerCase();
            return text.includes('skip') || text.includes('jump to main') || (text.includes('main') && text.includes('content'));
        });

        for (const link of skipLinks) {
            const href = link.getAttribute('href') || '';
            const targetId = href.split('#')[1];

            if (targetId) {
                const target = document.getElementById(targetId) || document.querySelector(`[name="${CSS.escape(targetId)}"]`);

                if (!target) {
                    const html = link.outerHTML.length > 200 ? link.outerHTML.slice(0, 200) + '...' : link.outerHTML;
                    result.push({
                        html,
                        target: ['a'],
                        failureSummary: `Skip link target #${targetId} does not exist — add an element with id="${targetId}".`,
                    });
                }
            }
        }

        return result;
    });

    return buildViolation('sr-skip-link-not-functional', nodes);
}

async function checkKeyboardTrap(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const dialogs = [
            ...document.querySelectorAll('dialog, [role="dialog"], [role="alertdialog"]'),
        ].filter((el) => el.getAttribute('aria-modal') !== 'true');

        for (const dialog of dialogs) {
            const focusableChildren = dialog.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            );

            if (focusableChildren.length > 0) {
                const html = dialog.outerHTML.length > 200 ? dialog.outerHTML.slice(0, 200) + '...' : dialog.outerHTML;
                result.push({
                    html,
                    target: ['dialog, [role="dialog"]'],
                    failureSummary: 'Dialog lacks aria-modal="true" — implement focus trapping in JavaScript to prevent focus from escaping.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-keyboard-trap', nodes);
}

async function checkFocusNotVisible(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const focusable = [
            ...document.querySelectorAll('a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])'),
        ].slice(0, 30);

        for (const el of focusable) {
            const inlineOutline = el.style.outline;

            if (inlineOutline === 'none' || inlineOutline === '0' || inlineOutline === '0px') {
                const html = el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML;
                result.push({
                    html,
                    target: [el.tagName.toLowerCase()],
                    failureSummary: 'Remove inline outline:none — provide a visible focus indicator for keyboard and screen reader users.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-focus-not-visible', nodes);
}

async function checkModalFocusNotTrapped(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const modal of document.querySelectorAll('dialog, [role="dialog"], [role="alertdialog"]')) {
            if (modal.getAttribute('aria-modal') !== 'true') {
                const html = modal.outerHTML.length > 200 ? modal.outerHTML.slice(0, 200) + '...' : modal.outerHTML;
                result.push({
                    html,
                    target: ['dialog'],
                    failureSummary: 'Add aria-modal="true" and implement JavaScript focus trapping to confine focus within the dialog.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-modal-focus-not-trapped', nodes);
}

async function checkModalFocusNotReturned(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const closeButtons = [...document.querySelectorAll('dialog button, [role="dialog"] button, [role="alertdialog"] button')].filter(
            (btn) => {
                const text = (btn.textContent || btn.getAttribute('aria-label') || '').toLowerCase().trim();
                return text === 'close' || text === 'dismiss' || text === 'cancel' || text === '×' || text === 'x';
            },
        );

        for (const btn of closeButtons) {
            const modal = btn.closest('dialog, [role="dialog"], [role="alertdialog"]');

            if (!modal) {
                continue;
            }

            const hasReturnFocusHint =
                modal.dataset.trigger ||
                modal.dataset.returnFocus ||
                modal.getAttribute('aria-controls') ||
                btn.dataset.trigger ||
                btn.dataset.returnFocus;

            if (!hasReturnFocusHint) {
                const html = btn.outerHTML.length > 200 ? btn.outerHTML.slice(0, 200) + '...' : btn.outerHTML;
                result.push({
                    html,
                    target: ['dialog button'],
                    failureSummary: 'When the dialog closes, return focus to the element that opened it using JavaScript.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-modal-focus-not-returned', nodes);
}

// =============================================================================
// Category E — Dynamic Content & ARIA
// =============================================================================

async function checkSilentLiveRegion(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const liveRegions = [
            ...document.querySelectorAll('[aria-live]:not([aria-live="off"]), [role="status"], [role="alert"], [role="log"]'),
        ];

        for (const region of liveRegions) {
            if (!region.textContent.trim()) {
                const html = region.outerHTML.length > 200 ? region.outerHTML.slice(0, 200) + '...' : region.outerHTML;
                result.push({
                    html,
                    target: ['[aria-live]'],
                    failureSummary: 'Live region is empty on page load — ensure content is dynamically inserted to trigger announcements.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-silent-live-region', nodes);
}

async function checkLiveRegionOff(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const region of document.querySelectorAll('[aria-live="off"]')) {
            const hasDynamicIndicator =
                region.querySelector('[id], [class*="count"], [class*="result"], [class*="total"], [class*="update"]') ||
                region.textContent.trim().length > 0;

            if (hasDynamicIndicator) {
                const html = region.outerHTML.length > 200 ? region.outerHTML.slice(0, 200) + '...' : region.outerHTML;
                result.push({
                    html,
                    target: ['[aria-live="off"]'],
                    failureSummary: 'Change aria-live="off" to "polite" or "assertive" if this region contains dynamic content that needs announcing.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-live-region-off', nodes);
}

async function checkExpandedStateNotAnnounced(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const el of document.querySelectorAll('[aria-expanded]')) {
            const tag = el.tagName.toLowerCase();
            const role = el.getAttribute('role');
            const isInteractive = tag === 'button' || tag === 'a' || role === 'button' || role === 'link';

            if (!isInteractive) {
                const html = el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML;
                result.push({
                    html,
                    target: ['[aria-expanded]'],
                    failureSummary: 'aria-expanded should be placed on a <button> or <a> element so screen readers reliably announce the state change.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-expanded-state-not-announced', nodes);
}

async function checkLoadingStateNotAnnounced(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const loaders = [
            ...document.querySelectorAll('[class*="spinner"], [class*="loader"], [class*="loading"], [class*="skeleton"]'),
        ];

        for (const loader of loaders) {
            const isHidden = loader.getAttribute('aria-hidden') === 'true';
            const hasAriaBusy = !!document.querySelector('[aria-busy="true"]');
            const hasLiveAncestor = !!loader.closest('[aria-live]');
            const hasStatusRole = loader.getAttribute('role') === 'status' || loader.getAttribute('role') === 'alert';

            if (!isHidden && !hasAriaBusy && !hasLiveAncestor && !hasStatusRole) {
                const html = loader.outerHTML.length > 200 ? loader.outerHTML.slice(0, 200) + '...' : loader.outerHTML;
                result.push({
                    html,
                    target: ['.spinner, .loader'],
                    failureSummary: 'Add aria-busy="true" to the loading container or use role="status" with a live region to announce the loading state.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-loading-state-not-announced', nodes);
}

async function checkTooltipNotAnnounced(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const tooltip of document.querySelectorAll('[role="tooltip"]')) {
            const id = tooltip.id;

            if (!id) {
                const html = tooltip.outerHTML.length > 200 ? tooltip.outerHTML.slice(0, 200) + '...' : tooltip.outerHTML;
                result.push({
                    html,
                    target: ['[role="tooltip"]'],
                    failureSummary: 'Give the tooltip an id and reference it from its trigger element via aria-describedby.',
                });
                continue;
            }

            const trigger = document.querySelector(`[aria-describedby~="${CSS.escape(id)}"]`);

            if (!trigger) {
                const html = tooltip.outerHTML.length > 200 ? tooltip.outerHTML.slice(0, 200) + '...' : tooltip.outerHTML;
                result.push({
                    html,
                    target: [`#${id}`],
                    failureSummary: `Reference this tooltip from its trigger element using aria-describedby="${id}".`,
                });
            }
        }

        return result;
    });

    return buildViolation('sr-tooltip-not-announced', nodes);
}

// =============================================================================
// Category F — Tables
// =============================================================================

async function checkTableNoHeaders(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const table of document.querySelectorAll('table')) {
            const role = table.getAttribute('role');

            if (role === 'presentation' || role === 'none') {
                continue;
            }

            const hasTh = !!table.querySelector('th, [role="columnheader"], [role="rowheader"]');
            const isDataTable = table.rows.length > 1 && table.rows[0] && table.rows[0].cells.length > 1;

            if (isDataTable && !hasTh) {
                const html = table.outerHTML.length > 200 ? table.outerHTML.slice(0, 200) + '...' : table.outerHTML;
                result.push({
                    html,
                    target: ['table'],
                    failureSummary: 'Add <th scope="col"> or <th scope="row"> elements to identify column and row headers.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-table-no-headers', nodes);
}

async function checkTableHeaderNotAssociated(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const table of document.querySelectorAll('table')) {
            const role = table.getAttribute('role');

            if (role === 'presentation' || role === 'none') {
                continue;
            }

            const headers = [...table.querySelectorAll('th')];

            if (headers.length === 0) {
                continue;
            }

            const headersWithoutScope = headers.filter((th) => !th.getAttribute('scope') && !th.id);

            if (headersWithoutScope.length > 0) {
                const th = headersWithoutScope[0];
                const html = th.outerHTML.length > 200 ? th.outerHTML.slice(0, 200) + '...' : th.outerHTML;
                result.push({
                    html,
                    target: ['th'],
                    failureSummary: 'Add scope="col" to column headers and scope="row" to row headers so screen readers can associate headers with cells.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-table-header-not-associated', nodes);
}

async function checkTableMissingCaption(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const table of document.querySelectorAll('table')) {
            const role = table.getAttribute('role');

            if (role === 'presentation' || role === 'none') {
                continue;
            }

            const maxCols = Math.max(...[...table.rows].map((r) => r.cells.length), 0);

            if (maxCols > 3) {
                const captionEl = table.querySelector('caption');
                const hasCaption = captionEl && captionEl.textContent.trim();
                const hasAriaLabel = !!table.getAttribute('aria-label') || !!table.getAttribute('aria-labelledby');

                if (!hasCaption && !hasAriaLabel) {
                    const html = table.outerHTML.length > 200 ? table.outerHTML.slice(0, 200) + '...' : table.outerHTML;
                    result.push({
                        html,
                        target: ['table'],
                        failureSummary: 'Add a <caption> element or aria-label attribute to describe the purpose of this complex table.',
                    });
                }
            }
        }

        return result;
    });

    return buildViolation('sr-table-missing-caption', nodes);
}

async function checkLayoutTableHasHeaders(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        for (const table of document.querySelectorAll('table[role="presentation"], table[role="none"]')) {
            if (table.querySelector('th')) {
                const html = table.outerHTML.length > 200 ? table.outerHTML.slice(0, 200) + '...' : table.outerHTML;
                result.push({
                    html,
                    target: ['table[role="presentation"]'],
                    failureSummary: 'Remove <th> elements from layout tables, or remove role="presentation" and add proper headers if this is a data table.',
                });
            }
        }

        return result;
    });

    return buildViolation('sr-layout-table-has-headers', nodes);
}

// =============================================================================
// Category G — Language & Reading Order
// =============================================================================

async function checkMissingLangAttribute(page) {
    const nodes = await page.evaluate(() => {
        const lang = (document.documentElement.getAttribute('lang') || '').trim();

        if (lang) {
            return [];
        }

        return [{
            html: document.documentElement.outerHTML.slice(0, 80) + '...',
            target: ['html'],
            failureSummary: 'Add a lang attribute to the <html> element (e.g., lang="en") so screen readers use the correct language and pronunciation.',
        }];
    });

    return buildViolation('sr-missing-lang-attribute', nodes);
}

async function checkContentBeforeNav(page) {
    const nodes = await page.evaluate(() => {
        const main = document.querySelector('main, [role="main"]');
        const nav = document.querySelector('nav, [role="navigation"]');

        if (!main || !nav) {
            return [];
        }

        const position = main.compareDocumentPosition(nav);
        const navIsAfterMain = !!(position & Node.DOCUMENT_POSITION_FOLLOWING);

        if (!navIsAfterMain) {
            return [];
        }

        // Nav is after main in DOM — check if nav is visually above main
        const mainRect = main.getBoundingClientRect();
        const navRect = nav.getBoundingClientRect();

        if (navRect.top < mainRect.top) {
            const html = main.outerHTML.length > 200 ? main.outerHTML.slice(0, 200) + '...' : main.outerHTML;
            return [{
                html,
                target: ['main'],
                failureSummary: 'Move the <main> element before <nav> in the DOM so that screen reader reading order matches the visual layout.',
            }];
        }

        return [];
    });

    return buildViolation('sr-content-before-nav', nodes);
}

async function checkOffScreenContentAnnounced(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const allEls = [...document.querySelectorAll('*')].slice(0, 300);

        for (const el of allEls) {
            if (el.getAttribute('aria-hidden') === 'true') {
                continue;
            }

            if (!el.textContent.trim()) {
                continue;
            }

            const style = window.getComputedStyle(el);
            const isClipHidden =
                (style.position === 'absolute' &&
                    style.clip !== 'auto' &&
                    style.clip === 'rect(0px, 0px, 0px, 0px)') ||
                (style.position === 'absolute' &&
                    parseFloat(style.width) <= 1 &&
                    parseFloat(style.height) <= 1 &&
                    style.overflow === 'hidden');

            if (isClipHidden) {
                const html = el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML;
                result.push({
                    html,
                    target: [el.tagName.toLowerCase()],
                    failureSummary: 'Visually hidden content will be announced by screen readers — verify its position in the reading order is intentional.',
                });

                if (result.length >= 5) {
                    break;
                }
            }
        }

        return result;
    });

    return buildViolation('sr-off-screen-content-announced', nodes);
}

// =============================================================================
// Main export
// =============================================================================

/** Ordered list of all 40 check functions. */
const CHECKS = [
    // A — Landmarks & Page Structure
    checkMissingMainLandmark,
    checkMissingPageTitle,
    checkMissingH1,
    checkSkippedHeadingLevel,
    checkDuplicateLandmarkNoLabel,
    checkDuplicateHeadingText,
    // B — Interactive Elements & Forms
    checkUnlabelledInteractive,
    checkGenericLinkText,
    checkAmbiguousButtonText,
    checkPlaceholderOnlyLabel,
    checkRequiredFieldNotAnnounced,
    checkMissingFieldsetLegend,
    checkSelectNoLabel,
    checkFormErrorNotAssociated,
    checkErrorNotLive,
    checkStatusMessageNotAnnounced,
    // C — Images & Media
    checkImageNoAlt,
    checkDecorativeImageAnnounced,
    checkRedundantAltText,
    checkSvgNoLabel,
    checkIconButtonNoLabel,
    // D — Focus & Keyboard Navigation
    checkFocusOrderMismatch,
    checkSkipLinkMissing,
    checkSkipLinkNotFunctional,
    checkKeyboardTrap,
    checkFocusNotVisible,
    checkModalFocusNotTrapped,
    checkModalFocusNotReturned,
    // E — Dynamic Content & ARIA
    checkSilentLiveRegion,
    checkLiveRegionOff,
    checkExpandedStateNotAnnounced,
    checkLoadingStateNotAnnounced,
    checkTooltipNotAnnounced,
    // F — Tables
    checkTableNoHeaders,
    checkTableHeaderNotAssociated,
    checkTableMissingCaption,
    checkLayoutTableHasHeaders,
    // G — Language & Reading Order
    checkMissingLangAttribute,
    checkContentBeforeNav,
    checkOffScreenContentAnnounced,
];

/**
 * Run all 40 screen reader checks against the given Playwright page.
 *
 * Returns violations in the same shape as axe-core violations so they can be
 * processed by the same PHP pipeline (ProcessHtmlScan / IssueNormalizer).
 *
 * @param {import('playwright').Page} page
 * @param {object} _srConfig  Reserved for future configuration options.
 * @returns {Promise<{url: string, violations: Array}>}
 */
async function runScreenReader(page, _srConfig) {
    const url = page.url();
    const violations = [];

    for (const check of CHECKS) {
        try {
            const violation = await check(page);

            if (violation) {
                violations.push(violation);
            }
        } catch {
            // Individual check failures are non-fatal — the scan continues.
        }
    }

    return { url, violations };
}

module.exports = { runScreenReader };

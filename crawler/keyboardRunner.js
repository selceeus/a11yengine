'use strict';

/**
 * Per-check metadata: impact, description, helpUrl, and WCAG-aligned tags.
 * Tags follow the axe-core convention so IssueNormalizer can classify them.
 *
 * Tag ordering rule: criterion tags (e.g. wcag241) MUST appear before
 * conformance level tags (e.g. wcag2a) so IssueNormalizer resolves the
 * correct WCAG category via str_starts_with.
 *
 * @type {Record<string, {impact: string, description: string, helpUrl: string, tags: string[]}>}
 */
const CHECK_META = {
    'kb-positive-tabindex': {
        impact: 'serious',
        description: 'Element uses tabindex > 0, which overrides the natural tab order and creates an unpredictable keyboard navigation sequence.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/focus-order.html',
        tags: ['wcag243', 'wcag2a', 'cat.keyboard'],
    },
    'kb-non-interactive-focusable': {
        impact: 'moderate',
        description: 'Non-interactive element (div, span, p, etc.) has been made focusable via tabindex but has no ARIA role or keyboard event handler to support interaction.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/name-role-value.html',
        tags: ['wcag412', 'wcag2a', 'cat.keyboard'],
    },
    'kb-onclick-no-keyboard': {
        impact: 'critical',
        description: 'Element has an onclick handler but no keyboard event handler (onkeydown, onkeyup, or onkeypress) — keyboard-only users cannot trigger this action.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/keyboard.html',
        tags: ['wcag211', 'wcag2a', 'cat.keyboard'],
    },
    'kb-autofocus-misuse': {
        impact: 'moderate',
        description: 'The autofocus attribute is applied to a non-form element or to multiple elements — this disrupts expected focus position on page load.',
        helpUrl: 'https://www.w3.org/WAI/WCAG22/Understanding/focus-appearance.html',
        tags: ['wcag247', 'wcag2aa', 'cat.keyboard'],
    },
    'kb-offscreen-focusable': {
        impact: 'serious',
        description: 'Element is visually hidden (using clip or clip-path) but remains in the tab order — keyboard users will focus invisible elements.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/meaningful-sequence.html',
        tags: ['wcag132', 'wcag2a', 'cat.keyboard'],
    },
    'kb-aria-disabled-focusable': {
        impact: 'moderate',
        description: 'Element is marked aria-disabled="true" but still appears in the tab order because tabindex="-1" is not set — screen readers and keyboard users receive inconsistent behaviour.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/name-role-value.html',
        tags: ['wcag412', 'wcag2a', 'cat.keyboard'],
    },
    'kb-composite-widget-no-roving': {
        impact: 'serious',
        description: 'Composite widget (tablist, listbox, menu, or radiogroup) contains multiple children with tabindex="0" — it should implement the roving tabindex pattern so only one child is in the tab order at a time.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/keyboard.html',
        tags: ['wcag211', 'wcag2a', 'cat.keyboard'],
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
// Checks
// =============================================================================

async function checkPositiveTabindex(page) {
    const nodes = await page.evaluate((safe) => {
        const result = [];

        document.querySelectorAll('[tabindex]').forEach((el) => {
            const idx = parseInt(el.getAttribute('tabindex'), 10);

            if (idx > 0) {
                result.push({
                    html: el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML,
                    target: [el.tagName.toLowerCase() + (el.id ? '#' + el.id : '')],
                    failureSummary: `tabindex="${idx}" — remove positive tabindex values and rely on DOM order to control focus sequence.`,
                });
            }
        });

        return result;
    });

    return buildViolation('kb-positive-tabindex', nodes);
}

async function checkNonInteractiveFocusable(page) {
    const interactiveTags = new Set(['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'DETAILS', 'SUMMARY']);
    const interactiveRoles = new Set([
        'button', 'link', 'checkbox', 'radio', 'textbox', 'combobox',
        'listbox', 'option', 'menuitem', 'menuitemcheckbox', 'menuitemradio',
        'slider', 'spinbutton', 'switch', 'tab', 'treeitem', 'gridcell',
        'searchbox', 'separator',
    ]);

    const nodes = await page.evaluate(({ iTags, iRoles }) => {
        const result = [];

        document.querySelectorAll('[tabindex]').forEach((el) => {
            const idx = parseInt(el.getAttribute('tabindex'), 10);

            if (idx < 0) {
                return;
            }

            if (iTags.includes(el.tagName)) {
                return;
            }

            const role = (el.getAttribute('role') || '').toLowerCase();

            if (iRoles.includes(role)) {
                return;
            }

            const hasKeyboardHandler =
                typeof el.onkeydown === 'function' ||
                typeof el.onkeyup === 'function' ||
                typeof el.onkeypress === 'function';

            if (!hasKeyboardHandler) {
                result.push({
                    html: el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML,
                    target: [el.tagName.toLowerCase() + (el.id ? '#' + el.id : '')],
                    failureSummary: 'Add a semantic role (e.g. role="button") and a keyboard event handler, or use a native interactive element instead.',
                });
            }
        });

        return result;
    }, { iTags: [...interactiveTags], iRoles: [...interactiveRoles] });

    return buildViolation('kb-non-interactive-focusable', nodes);
}

async function checkOnclickNoKeyboard(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const nativelyKeyboardOperable = new Set(['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'SUMMARY']);

        document.querySelectorAll('*').forEach((el) => {
            if (nativelyKeyboardOperable.has(el.tagName)) {
                return;
            }

            const hasClick = typeof el.onclick === 'function';

            if (!hasClick) {
                return;
            }

            const hasKeyboard =
                typeof el.onkeydown === 'function' ||
                typeof el.onkeyup === 'function' ||
                typeof el.onkeypress === 'function';

            if (!hasKeyboard) {
                result.push({
                    html: el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML,
                    target: [el.tagName.toLowerCase() + (el.id ? '#' + el.id : '')],
                    failureSummary: 'Add an onkeydown or onkeyup handler that triggers the same action as onclick, or replace with a <button> element.',
                });
            }
        });

        return result;
    });

    return buildViolation('kb-onclick-no-keyboard', nodes);
}

async function checkAutofocusMisuse(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const formTags = new Set(['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON']);
        const autofocused = [...document.querySelectorAll('[autofocus]')];

        if (autofocused.length > 1) {
            for (const el of autofocused) {
                result.push({
                    html: el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML,
                    target: [el.tagName.toLowerCase() + (el.id ? '#' + el.id : '')],
                    failureSummary: 'Multiple elements have autofocus — only one element per page should receive autofocus on load.',
                });
            }
        } else if (autofocused.length === 1 && !formTags.has(autofocused[0].tagName)) {
            const el = autofocused[0];
            result.push({
                html: el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML,
                target: [el.tagName.toLowerCase() + (el.id ? '#' + el.id : '')],
                failureSummary: 'autofocus is applied to a non-form element — restrict autofocus to form inputs, selects, textareas, or buttons.',
            });
        }

        return result;
    });

    return buildViolation('kb-autofocus-misuse', nodes);
}

async function checkOffscreenFocusable(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        document.querySelectorAll('[tabindex]:not([tabindex="-1"])').forEach((el) => {
            const style = window.getComputedStyle(el);
            const clip = style.clip || '';
            const clipPath = style.clipPath || '';

            // Detect the sr-only / visually-hidden pattern (clip: rect(0 0 0 0) or clip-path: inset(50%))
            // but NOT intentional sr-only usage (width/height must also be near-zero)
            const isClipped =
                clip.includes('rect(0') ||
                clipPath === 'inset(50%)' ||
                clipPath === 'inset(100%)';

            const rect = el.getBoundingClientRect();
            const isNearZeroSize = rect.width <= 1 && rect.height <= 1;

            // Only flag if both clipped AND near-zero — avoids false positives on decorative clips
            if (isClipped && isNearZeroSize) {
                // Check if it has an aria-label or sr-only class (intentionally hidden for screen readers)
                const hasAriaLabel = !!el.getAttribute('aria-label') || !!el.getAttribute('aria-labelledby');
                const isSrOnly =
                    el.classList.contains('sr-only') ||
                    el.classList.contains('visually-hidden') ||
                    el.classList.contains('screen-reader-only') ||
                    el.classList.contains('a11y-hidden');

                if (!hasAriaLabel && !isSrOnly) {
                    result.push({
                        html: el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML,
                        target: [el.tagName.toLowerCase() + (el.id ? '#' + el.id : '')],
                        failureSummary: 'Add tabindex="-1" or move the element outside the clip region so keyboard users do not focus invisible content.',
                    });
                }
            }
        });

        return result;
    });

    return buildViolation('kb-offscreen-focusable', nodes);
}

async function checkAriaDisabledFocusable(page) {
    const nodes = await page.evaluate(() => {
        const result = [];

        document.querySelectorAll('[aria-disabled="true"]').forEach((el) => {
            const tabindex = el.getAttribute('tabindex');

            if (tabindex !== '-1') {
                result.push({
                    html: el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML,
                    target: [el.tagName.toLowerCase() + (el.id ? '#' + el.id : '')],
                    failureSummary: 'Add tabindex="-1" to elements with aria-disabled="true" so they are excluded from the tab order.',
                });
            }
        });

        return result;
    });

    return buildViolation('kb-aria-disabled-focusable', nodes);
}

async function checkCompositeWidgetNoRoving(page) {
    const compositeRoles = ['tablist', 'listbox', 'menu', 'menubar', 'radiogroup', 'tree', 'grid', 'treegrid'];

    const nodes = await page.evaluate((roles) => {
        const result = [];

        for (const role of roles) {
            document.querySelectorAll(`[role="${role}"]`).forEach((widget) => {
                const children = [...widget.querySelectorAll('[role]')].filter((child) => {
                    const childRole = child.getAttribute('role');

                    return childRole && child.closest(`[role="${role}"]`) === widget;
                });

                const focusableChildren = children.filter(
                    (child) => child.getAttribute('tabindex') === '0',
                );

                if (focusableChildren.length > 1) {
                    result.push({
                        html: widget.outerHTML.length > 200 ? widget.outerHTML.slice(0, 200) + '...' : widget.outerHTML,
                        target: [`[role="${role}"]`],
                        failureSummary: `${focusableChildren.length} child elements have tabindex="0" inside role="${role}" — use the roving tabindex pattern: set tabindex="0" only on the active child and tabindex="-1" on all others.`,
                    });
                }
            });
        }

        return result;
    }, compositeRoles);

    return buildViolation('kb-composite-widget-no-roving', nodes);
}

// =============================================================================
// Runner
// =============================================================================

const CHECKS = [
    checkPositiveTabindex,
    checkNonInteractiveFocusable,
    checkOnclickNoKeyboard,
    checkAutofocusMisuse,
    checkOffscreenFocusable,
    checkAriaDisabledFocusable,
    checkCompositeWidgetNoRoving,
];

/**
 * Run all keyboard navigation checks against the given Playwright page.
 *
 * Returns violations in the same shape as axe-core violations so they can be
 * processed by the same PHP pipeline (ProcessHtmlScan / IssueNormalizer).
 *
 * @param {import('playwright').Page} page
 * @param {object} _config  Reserved for future configuration options.
 * @returns {Promise<{url: string, violations: Array}>}
 */
async function runKeyboard(page, _config) {
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

module.exports = { runKeyboard };

'use strict';

/**
 * Per-check metadata for interactive checks.
 * Tags follow the axe-core convention so IssueNormalizer can classify them.
 *
 * Tag ordering rule: criterion tags (e.g. wcag247) MUST appear before
 * conformance level tags (e.g. wcag2aa) so IssueNormalizer resolves the
 * correct WCAG category via str_starts_with.
 *
 * @type {Record<string, {impact: string, description: string, helpUrl: string, tags: string[]}>}
 */
const CHECK_META = {
    // Phase 1 — Tab Navigation
    'int-focus-indicator-missing': {
        impact: 'serious',
        description: 'Element receives keyboard focus but has no visible focus indicator — keyboard users cannot determine which element is active.',
        helpUrl: 'https://www.w3.org/WAI/WCAG22/Understanding/focus-appearance.html',
        tags: ['wcag247', 'wcag2aa', 'cat.keyboard'],
    },
    'int-unreachable-interactive': {
        impact: 'critical',
        description: 'Interactive element (button, link, or input) was not reachable via Tab key navigation — keyboard-only users cannot access this control.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/keyboard.html',
        tags: ['wcag211', 'wcag2a', 'cat.keyboard'],
    },
    'int-focus-trap': {
        impact: 'critical',
        description: 'Keyboard focus became trapped — the same element received focus repeatedly with no path to escape.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/no-keyboard-trap.html',
        tags: ['wcag212', 'wcag2a', 'cat.keyboard'],
    },
    'int-focus-order-wrong': {
        impact: 'moderate',
        description: 'Tab key navigation order does not match the visual reading order — keyboard users navigate the page in a confusing sequence.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/focus-order.html',
        tags: ['wcag243', 'wcag2a', 'cat.keyboard'],
    },
    // Phase 2 — Interaction State Contrast
    'int-hover-contrast-fail': {
        impact: 'serious',
        description: 'Element has insufficient colour contrast on hover — the text or icon becomes hard to read when the pointer is over it.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum.html',
        tags: ['wcag143', 'wcag2aa', 'cat.color'],
    },
    'int-focus-contrast-fail': {
        impact: 'serious',
        description: 'Element has insufficient colour contrast when focused — keyboard users cannot easily read focused interactive elements.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum.html',
        tags: ['wcag143', 'wcag2aa', 'cat.color'],
    },
    // Phase 3 — Reflow
    'int-reflow-horizontal-scroll': {
        impact: 'serious',
        description: 'Page content requires horizontal scrolling at 320px viewport width — users who rely on zoom or small viewports cannot read the page without scrolling in two dimensions.',
        helpUrl: 'https://www.w3.org/WAI/WCAG21/Understanding/reflow.html',
        tags: ['wcag1410', 'wcag2aa', 'cat.sensory-and-visual-cues'],
    },
    // Phase 4 — Reduced Motion
    'int-reduced-motion-ignored': {
        impact: 'moderate',
        description: 'CSS animation or transition plays despite prefers-reduced-motion: reduce being set — users who require reduced motion are exposed to potentially harmful animation.',
        helpUrl: 'https://www.w3.org/WAI/WCAG22/Understanding/animation-from-interactions.html',
        tags: ['wcag233', 'wcag2aaa', 'cat.time-and-media'],
    },
    // Phase 5 — Touch Targets
    'int-touch-target-too-small': {
        impact: 'moderate',
        description: 'Interactive element bounding box is smaller than 24×24 CSS pixels — users with limited dexterity may find the target difficult to activate accurately.',
        helpUrl: 'https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html',
        tags: ['wcag258', 'wcag22aa', 'cat.sensory-and-visual-cues'],
    },
};

/**
 * Build a violation object from a check ID and affected nodes.
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
// Helpers
// =============================================================================

/**
 * Compute the relative luminance of an sRGB colour.
 *
 * @param {number} r  0–255
 * @param {number} g  0–255
 * @param {number} b  0–255
 * @returns {number}
 */
function relativeLuminance(r, g, b) {
    const toLinear = (v) => {
        const s = v / 255;

        return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    };

    return 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b);
}

/**
 * Compute the WCAG contrast ratio between two luminance values.
 *
 * @param {number} l1
 * @param {number} l2
 * @returns {number}
 */
function contrastRatio(l1, l2) {
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);

    return (lighter + 0.05) / (darker + 0.05);
}

/**
 * Parse a CSS rgb/rgba string into { r, g, b } components.
 * Returns null if the string cannot be parsed.
 *
 * @param {string} color  e.g. "rgb(255, 255, 255)" or "rgba(0, 0, 0, 0.5)"
 * @returns {{r: number, g: number, b: number}|null}
 */
function parseRgb(color) {
    const m = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);

    if (!m) {
        return null;
    }

    return { r: parseInt(m[1], 10), g: parseInt(m[2], 10), b: parseInt(m[3], 10) };
}

// =============================================================================
// Phase 1 — Tab Navigation
// =============================================================================

/**
 * Tab through all focusable elements on the page (up to maxSteps), recording
 * which element is active after each Tab press.
 *
 * Guards against side effects:
 *  - Dialogs are dismissed immediately.
 *  - Non-GET navigations (form submissions) are aborted.
 *  - If the page navigates away the phase is aborted.
 *
 * @param {import('playwright').Page} page
 * @param {number} maxSteps
 * @returns {Promise<Array<{html: string, target: string, rect: {top: number, left: number}, domIndex: number}>>}
 */
async function collectTabOrder(page, maxSteps) {
    let navigatedAway = false;

    const onNavigation = () => {
        navigatedAway = true;
    };

    page.on('framenavigated', onNavigation);

    const dialogHandler = (dialog) => dialog.dismiss().catch(() => {});
    page.on('dialog', dialogHandler);

    // Block non-GET requests to prevent accidental form submissions.
    await page.route('**/*', (route) => {
        if (route.request().method().toUpperCase() !== 'GET') {
            route.abort().catch(() => {});
        } else {
            route.continue().catch(() => {});
        }
    });

    const tabOrder = [];

    try {
        // Move focus to the top of the document before starting.
        await page.evaluate(() => {
            if (document.body) {
                document.body.setAttribute('tabindex', '-1');
                document.body.focus();
            }
        });

        for (let i = 0; i < maxSteps; i++) {
            if (navigatedAway) {
                break;
            }

            await page.keyboard.press('Tab');

            if (navigatedAway) {
                break;
            }

            const focusInfo = await page.evaluate(() => {
                const el = document.activeElement;

                if (!el || el === document.body || el === document.documentElement) {
                    return null;
                }

                const rect = el.getBoundingClientRect();
                const all = [...document.querySelectorAll('*')];
                const domIndex = all.indexOf(el);

                return {
                    html: el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML,
                    target: el.tagName.toLowerCase() + (el.id ? '#' + el.id : ''),
                    rect: { top: rect.top, left: rect.left },
                    domIndex,
                };
            });

            if (!focusInfo) {
                continue;
            }

            tabOrder.push(focusInfo);

            // Stop when we've cycled back to the first element.
            if (tabOrder.length > 1 && focusInfo.target === tabOrder[0].target) {
                break;
            }
        }
    } finally {
        page.off('framenavigated', onNavigation);
        page.off('dialog', dialogHandler);
        await page.unrouteAll().catch(() => {});

        // Remove the temporary tabindex we added to body.
        await page.evaluate(() => {
            document.body?.removeAttribute('tabindex');
        }).catch(() => {});
    }

    return tabOrder;
}

/**
 * Check for missing focus indicators.
 * After each Tab press, inspect whether the focused element has a visible
 * focus ring (outline, box-shadow, or border that differs from unfocused state).
 *
 * @param {import('playwright').Page} page
 * @param {number} maxSteps
 * @returns {Promise<object|null>}
 */
async function checkFocusIndicatorMissing(page, maxSteps) {
    let navigatedAway = false;

    const onNavigation = () => {
        navigatedAway = true;
    };

    page.on('framenavigated', onNavigation);

    const dialogHandler = (dialog) => dialog.dismiss().catch(() => {});
    page.on('dialog', dialogHandler);

    await page.route('**/*', (route) => {
        if (route.request().method().toUpperCase() !== 'GET') {
            route.abort().catch(() => {});
        } else {
            route.continue().catch(() => {});
        }
    });

    const nodes = [];

    try {
        await page.evaluate(() => {
            if (document.body) {
                document.body.setAttribute('tabindex', '-1');
                document.body.focus();
            }
        });

        const seen = new Set();

        for (let i = 0; i < maxSteps; i++) {
            if (navigatedAway) {
                break;
            }

            await page.keyboard.press('Tab');

            if (navigatedAway) {
                break;
            }

            const result = await page.evaluate(() => {
                const el = document.activeElement;

                if (!el || el === document.body || el === document.documentElement) {
                    return null;
                }

                const style = window.getComputedStyle(el);
                const outline = style.outline || '';
                const outlineWidth = parseFloat(style.outlineWidth) || 0;
                const boxShadow = style.boxShadow || '';
                const borderWidth = parseFloat(style.borderWidth) || 0;

                // Consider focus visible if outline width > 0, box-shadow is set, or border > 0 and not "none"
                const hasOutline = outlineWidth > 0 && !outline.includes('none');
                const hasBoxShadow = boxShadow !== 'none' && boxShadow !== '';
                const hasBorder = borderWidth > 0 && style.borderStyle !== 'none';

                if (hasOutline || hasBoxShadow || hasBorder) {
                    return null;
                }

                const html = el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML;
                const target = el.tagName.toLowerCase() + (el.id ? '#' + el.id : '');

                return {
                    html,
                    target,
                    failureSummary: 'Add a visible focus indicator using CSS outline, box-shadow, or border that activates on :focus or :focus-visible.',
                };
            });

            if (result && !seen.has(result.target)) {
                seen.add(result.target);
                nodes.push({ ...result, target: [result.target] });
            }

            // Stop cycling when focus wraps around.
            if (nodes.length >= 20) {
                break;
            }
        }
    } finally {
        page.off('framenavigated', onNavigation);
        page.off('dialog', dialogHandler);
        await page.unrouteAll().catch(() => {});
        await page.evaluate(() => { document.body?.removeAttribute('tabindex'); }).catch(() => {});
    }

    return buildViolation('int-focus-indicator-missing', nodes);
}

/**
 * Find interactive elements (buttons, links, inputs) that were never reached
 * during a full tab traversal.
 *
 * @param {import('playwright').Page} page
 * @param {Array<{target: string}>} tabOrder
 * @returns {Promise<object|null>}
 */
async function checkUnreachableInteractive(page, tabOrder) {
    if (tabOrder.length === 0) {
        return null;
    }

    const reachableTargets = new Set(tabOrder.map((item) => item.target));

    const nodes = await page.evaluate((reachable) => {
        const result = [];
        const reachableSet = new Set(reachable);
        const selector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])';

        document.querySelectorAll(selector).forEach((el) => {
            const style = window.getComputedStyle(el);

            if (style.display === 'none' || style.visibility === 'hidden') {
                return;
            }

            const target = el.tagName.toLowerCase() + (el.id ? '#' + el.id : '');

            if (!reachableSet.has(target)) {
                result.push({
                    html: el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML,
                    target: [target],
                    failureSummary: 'Ensure this element has a non-negative tabindex and is not hidden from the accessibility tree.',
                });
            }
        });

        // Cap to 10 to avoid overwhelming reports.
        return result.slice(0, 10);
    }, [...reachableTargets]);

    return buildViolation('int-unreachable-interactive', nodes);
}

/**
 * Detect focus traps by looking for the same target appearing 3+ consecutive
 * times in the tab order.
 *
 * @param {Array<{target: string, html: string}>} tabOrder
 * @returns {object|null}
 */
function checkFocusTrap(tabOrder) {
    if (tabOrder.length < 3) {
        return null;
    }

    const nodes = [];
    let consecutive = 1;

    for (let i = 1; i < tabOrder.length; i++) {
        if (tabOrder[i].target === tabOrder[i - 1].target) {
            consecutive++;

            if (consecutive >= 3) {
                const item = tabOrder[i];
                nodes.push({
                    html: item.html,
                    target: [item.target],
                    failureSummary: 'Focus is trapped on this element — ensure the widget provides a standard escape mechanism (Escape key or a close button reachable by Tab).',
                });
                break;
            }
        } else {
            consecutive = 1;
        }
    }

    return buildViolation('int-focus-trap', nodes);
}

/**
 * Detect when the tab order diverges significantly from visual/DOM order.
 * Compares the sequence of domIndex values; flags when an element's DOM
 * position is more than 5 ranks out of sequence.
 *
 * @param {Array<{target: string, html: string, domIndex: number}>} tabOrder
 * @returns {object|null}
 */
function checkFocusOrderWrong(tabOrder) {
    if (tabOrder.length < 3) {
        return null;
    }

    const nodes = [];

    for (let i = 1; i < tabOrder.length; i++) {
        const prev = tabOrder[i - 1];
        const curr = tabOrder[i];

        // If the current element's DOM position is significantly before the previous
        // element's DOM position, the tab order is moving backwards against DOM order.
        if (prev.domIndex - curr.domIndex > 5) {
            nodes.push({
                html: curr.html,
                target: [curr.target],
                failureSummary: `Tab moved from DOM position ${prev.domIndex} to ${curr.domIndex} — tab order diverges from the visual sequence. Use DOM order or remove positive tabindex values to align focus with visual layout.`,
            });
        }
    }

    return buildViolation('int-focus-order-wrong', nodes.slice(0, 5));
}

// =============================================================================
// Phase 2 — Interaction State Contrast
// =============================================================================

async function checkInteractionContrast(page) {
    const hoverFailNodes = [];
    const focusFailNodes = [];

    const interactiveEls = await page.evaluate(() => {
        const result = [];
        const els = [...document.querySelectorAll('a[href], button:not([disabled])')];

        for (const el of els.slice(0, 30)) {
            const style = window.getComputedStyle(el);

            if (style.display === 'none' || style.visibility === 'hidden') {
                continue;
            }

            result.push({
                html: el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML,
                target: el.tagName.toLowerCase() + (el.id ? '#' + el.id : ''),
                selector: el.tagName.toLowerCase() + (el.id ? '#' + el.id : (el.className ? '.' + [...el.classList].join('.') : '')),
            });
        }

        return result;
    });

    for (const el of interactiveEls) {
        try {
            // Hover state
            await page.hover(el.selector, { timeout: 1000 });
            const hoverContrast = await page.evaluate((target) => {
                const found = document.querySelector(target);

                if (!found) {
                    return null;
                }

                const style = window.getComputedStyle(found);
                const color = style.color;
                const bg = style.backgroundColor;

                return { color, bg };
            }, el.selector);

            if (hoverContrast) {
                const fg = parseRgb(hoverContrast.color);
                const bg = parseRgb(hoverContrast.bg);

                if (fg && bg) {
                    const ratio = contrastRatio(
                        relativeLuminance(fg.r, fg.g, fg.b),
                        relativeLuminance(bg.r, bg.g, bg.b),
                    );

                    if (ratio < 4.5) {
                        hoverFailNodes.push({
                            html: el.html,
                            target: [el.target],
                            failureSummary: `Contrast ratio on hover is ${ratio.toFixed(2)}:1 — minimum required is 4.5:1. Adjust hover foreground or background colours.`,
                        });
                    }
                }
            }
        } catch {
            // Element not hoverable — skip.
        }

        try {
            // Focus state
            await page.focus(el.selector);
            const focusContrast = await page.evaluate((target) => {
                const found = document.querySelector(target);

                if (!found) {
                    return null;
                }

                const style = window.getComputedStyle(found);

                return { color: style.color, bg: style.backgroundColor };
            }, el.selector);

            if (focusContrast) {
                const fg = parseRgb(focusContrast.color);
                const bg = parseRgb(focusContrast.bg);

                if (fg && bg) {
                    const ratio = contrastRatio(
                        relativeLuminance(fg.r, fg.g, fg.b),
                        relativeLuminance(bg.r, bg.g, bg.b),
                    );

                    if (ratio < 4.5) {
                        focusFailNodes.push({
                            html: el.html,
                            target: [el.target],
                            failureSummary: `Contrast ratio on focus is ${ratio.toFixed(2)}:1 — minimum required is 4.5:1. Adjust :focus state foreground or background colours.`,
                        });
                    }
                }
            }
        } catch {
            // Element not focusable — skip.
        }
    }

    return [
        buildViolation('int-hover-contrast-fail', hoverFailNodes),
        buildViolation('int-focus-contrast-fail', focusFailNodes),
    ].filter(Boolean);
}

// =============================================================================
// Phase 3 — Reflow (WCAG 1.4.10)
// =============================================================================

async function checkReflow(page, originalViewport) {
    await page.setViewportSize({ width: 320, height: 256 });

    const nodes = await page.evaluate(() => {
        const scrollWidth = document.documentElement.scrollWidth;
        const viewportWidth = 320;

        if (scrollWidth <= viewportWidth) {
            return [];
        }

        return [{
            html: '<html>',
            target: ['html'],
            failureSummary: `Page scroll width is ${scrollWidth}px at 320px viewport — eliminate horizontal scrolling so content reflows into a single column.`,
        }];
    });

    await page.setViewportSize(originalViewport);

    return buildViolation('int-reflow-horizontal-scroll', nodes);
}

// =============================================================================
// Phase 4 — Reduced Motion (WCAG 2.3.3)
// =============================================================================

async function checkReducedMotion(page) {
    await page.emulateMedia({ reducedMotion: 'reduce' });

    const nodes = await page.evaluate(() => {
        const result = [];
        const allEls = [...document.querySelectorAll('*')];

        for (const el of allEls) {
            const style = window.getComputedStyle(el);
            const animDuration = parseFloat(style.animationDuration) || 0;
            const transDuration = parseFloat(style.transitionDuration) || 0;

            if (animDuration > 0 || transDuration > 0) {
                const html = el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML;
                result.push({
                    html,
                    target: [el.tagName.toLowerCase() + (el.id ? '#' + el.id : '')],
                    failureSummary: `Animation duration ${animDuration}s / transition duration ${transDuration}s — wrap this animation in @media (prefers-reduced-motion: no-preference) so it does not run when reduced motion is requested.`,
                });
            }

            if (result.length >= 10) {
                break;
            }
        }

        return result;
    });

    await page.emulateMedia({ reducedMotion: null });

    return buildViolation('int-reduced-motion-ignored', nodes);
}

// =============================================================================
// Phase 5 — Touch Target Size (WCAG 2.5.8)
// =============================================================================

async function checkTouchTargetSize(page) {
    const nodes = await page.evaluate(() => {
        const result = [];
        const minSize = 24;
        const selector = 'a[href], button, input:not([type="hidden"]), select, [role="button"], [role="link"], [role="checkbox"], [role="radio"]';

        document.querySelectorAll(selector).forEach((el) => {
            const style = window.getComputedStyle(el);

            if (style.display === 'none' || style.visibility === 'hidden') {
                return;
            }

            const rect = el.getBoundingClientRect();

            if (rect.width < minSize || rect.height < minSize) {
                result.push({
                    html: el.outerHTML.length > 200 ? el.outerHTML.slice(0, 200) + '...' : el.outerHTML,
                    target: [el.tagName.toLowerCase() + (el.id ? '#' + el.id : '')],
                    failureSummary: `Target size is ${Math.round(rect.width)}×${Math.round(rect.height)}px — minimum is ${minSize}×${minSize}px. Increase the element's padding or minimum dimensions.`,
                });
            }
        });

        return result.slice(0, 20);
    });

    return buildViolation('int-touch-target-too-small', nodes);
}

// =============================================================================
// Runner
// =============================================================================

/**
 * Run all interactive accessibility checks against the given Playwright page.
 *
 * Each phase is isolated: page state (viewport, media emulation, routes) is
 * restored before the next phase begins. Individual phase failures are
 * non-fatal — the runner continues and returns whatever violations were found.
 *
 * @param {import('playwright').Page} page
 * @param {{ maxTabSteps?: number, originalViewport?: {width: number, height: number} }} config
 * @returns {Promise<{url: string, violations: Array}>}
 */
async function runInteractive(page, config = {}) {
    const url = page.url();
    const maxTabSteps = config.maxTabSteps ?? 100;
    const originalViewport = config.originalViewport ?? { width: 1280, height: 720 };
    const violations = [];

    // Phase 1 — Tab Navigation
    let tabOrder = [];

    try {
        tabOrder = await collectTabOrder(page, maxTabSteps);

        const focusIndicatorViolation = await checkFocusIndicatorMissing(page, maxTabSteps);

        if (focusIndicatorViolation) {
            violations.push(focusIndicatorViolation);
        }

        const unreachable = await checkUnreachableInteractive(page, tabOrder);

        if (unreachable) {
            violations.push(unreachable);
        }

        const trap = checkFocusTrap(tabOrder);

        if (trap) {
            violations.push(trap);
        }

        const orderWrong = checkFocusOrderWrong(tabOrder);

        if (orderWrong) {
            violations.push(orderWrong);
        }
    } catch {
        // Phase 1 failure is non-fatal.
    }

    // Phase 2 — Interaction State Contrast
    try {
        const contrastViolations = await checkInteractionContrast(page);
        violations.push(...contrastViolations);
    } catch {
        // Phase 2 failure is non-fatal.
    }

    // Phase 3 — Reflow
    try {
        const reflow = await checkReflow(page, originalViewport);

        if (reflow) {
            violations.push(reflow);
        }
    } catch {
        // Phase 3 failure is non-fatal.
    }

    // Phase 4 — Reduced Motion
    try {
        const reducedMotion = await checkReducedMotion(page);

        if (reducedMotion) {
            violations.push(reducedMotion);
        }
    } catch {
        // Phase 4 failure is non-fatal.
    }

    // Phase 5 — Touch Target Size
    try {
        const touchTargets = await checkTouchTargetSize(page);

        if (touchTargets) {
            violations.push(touchTargets);
        }
    } catch {
        // Phase 5 failure is non-fatal.
    }

    return { url, violations };
}

module.exports = {
    runInteractive,
    // Export internals for unit testing
    relativeLuminance,
    contrastRatio,
    parseRgb,
    checkFocusTrap,
    checkFocusOrderWrong,
};

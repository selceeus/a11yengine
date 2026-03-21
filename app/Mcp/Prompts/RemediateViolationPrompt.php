<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Pre-fills WCAG violation context, the failing HTML element, and a remediation request into a structured prompt ready for an AI coding agent to act on.')]
class RemediateViolationPrompt extends Prompt
{
    public function handle(Request $request): Response
    {
        $ruleKey = $request->get('rule_key', '');
        $wcagCriteria = $request->get('wcag_criteria', '');
        $severity = $request->get('severity', '');
        $elementHtml = $request->get('element_html');

        $prompt = <<<TEXT
You are an expert web accessibility engineer. Remediate the following WCAG violation in the codebase.

## Violation Details

- **Rule**: {$ruleKey}
- **WCAG Criterion**: {$wcagCriteria}
- **Severity**: {$severity}
TEXT;

        if (filled($elementHtml)) {
            $prompt .= <<<TEXT


## Failing HTML Element

```html
{$elementHtml}
```
TEXT;
        }

        $prompt .= <<<TEXT


## Task

1. Identify the root cause of the violation based on the rule and failing element above.
2. Provide a corrected version of the HTML or component code that fully satisfies WCAG {$wcagCriteria}.
3. Explain the change in one sentence so a developer can understand the fix at a glance.
4. If this pattern appears elsewhere in the codebase, describe how to find and fix all occurrences.
TEXT;

        return Response::text($prompt);
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('rule_key', 'The axe-core rule key for the violation (e.g. image-alt, color-contrast).', required: true),
            new Argument('wcag_criteria', 'The WCAG criterion number (e.g. 1.1.1, 1.4.3).', required: true),
            new Argument('severity', 'Severity of the violation: critical, serious, moderate, or minor.', required: true),
            new Argument('element_html', 'The failing HTML element snippet. Include if available for a more targeted fix.', required: false),
        ];
    }
}

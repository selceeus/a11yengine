<?php

use App\Mcp\Prompts\RemediateViolationPrompt;
use App\Mcp\Servers\PropertyAccessibilityServer;

it('returns a structured remediation prompt with violation details', function (): void {
    PropertyAccessibilityServer::prompt(RemediateViolationPrompt::class, [
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1',
        'severity' => 'critical',
    ])->assertOk()->assertSee('image-alt')->assertSee('1.1.1')->assertSee('WCAG 1.1.1')->assertSee('Severity');
});

it('includes the failing element html in the prompt when provided', function (): void {
    PropertyAccessibilityServer::prompt(RemediateViolationPrompt::class, [
        'rule_key' => 'color-contrast',
        'wcag_criteria' => '1.4.3',
        'severity' => 'serious',
        'element_html' => '<p style="color:#ccc">Low contrast text</p>',
    ])->assertOk()->assertSee('Failing HTML Element')->assertSee('Low contrast text');
});

it('omits the failing element section when element_html is not provided', function (): void {
    $response = PropertyAccessibilityServer::prompt(RemediateViolationPrompt::class, [
        'rule_key' => 'label',
        'wcag_criteria' => '1.3.1',
        'severity' => 'serious',
    ])->assertOk();

    $response->assertDontSee('Failing HTML Element');
    $response->assertSee('label');
});

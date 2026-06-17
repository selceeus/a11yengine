# Security Policy

## Supported Versions

Security fixes are applied to the latest release on `main`. We strongly recommend always running the latest version.

## Reporting a Vulnerability

**Please do not report security vulnerabilities via public GitHub issues.**

To report a vulnerability, email **security@[your-domain]** with:

- A description of the vulnerability and its potential impact
- Steps to reproduce or a proof-of-concept
- Any suggested mitigations

We will acknowledge your report within **48 hours** and aim to release a fix within **14 days** for critical issues.

## Scope

Areas of particular concern given the nature of this platform:

- Multi-tenant data isolation (agency / organisation / property scoping)
- API key authentication and scope enforcement
- Webhook signature verification (HMAC-based for all supported integrations)
- SOC2 activity log tamper-evidence
- MCP server authentication
- AGPL v3 licence compliance in forks and deployments

## Disclosure

We follow responsible disclosure. Once a fix is released, we will publish a security advisory on GitHub.

# Changelog

All notable changes to A11y Engine will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added

- AGPL v3 open-source licence
- GitHub Actions CI workflows (tests, lint) now in `.github/workflows/`
- Complete `.env.example` covering all environment variables (AI, crawler, PDF scanner, Reverb, audit tracks)
- GitHub community health files: `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md`, issue templates, and PR template
- Demo seeder documentation (`DemoSeeder`, `LawsuitDataSeeder`)
- Reverb and veraPDF setup instructions in README

---

## [1.0.0] - 2026-06-16

### Added

- Automated WCAG 2.0/2.1 A/AA crawling and axe-core scanning via headless Playwright
- Virtual screen reader runner (40 deterministic WCAG checks per page, `sr-` prefixed rule keys)
- Content quality runner (14 deterministic checks — alt text, link text, heading conventions, readability)
- Keyboard navigation runner (7 checks — tabindex misuse, onclick-without-keyboard, autofocus, etc.)
- Interactive element runner (8 live interaction checks — focus indicators, keyboard traps, reflow, reduced motion)
- Server-side reading metrics — Flesch-Kincaid grade level, reading ease score, word count, estimated reading time
- Scan Journeys — named, reusable multi-step URL sequences for auditing critical user flows
- Single-page scan mode — scope any scan to one URL for quick spot-checks
- PDF accessibility scanning via veraPDF REST microservice
- Issue deduplication and lifecycle tracking (`open` → `in_progress` → `resolved` / `ignored` / `false_positive`)
- Issue activity log — full audit trail of status changes, assignments, due dates, bulk actions, and comments
- Scan diff — side-by-side comparison of new, resolved, and persisting findings across two scans
- AI Audit Reports (GPT-4o / Claude) with executive summaries, compliance status, and ADA legal precedents
- AI Issue Clustering — groups open issues into thematic clusters
- AI Risk Advisory — prioritised remediation plans ranked by impact and ease
- AI Content Auditing — prose-level accessibility and readability analysis
- AI Governance Reports — exportable as JSON, CSV, and PDF
- RAG-augmented AI — WCAG standards, ADA lawsuit precedents, and remediation patterns indexed as pgvector embeddings
- Performance auditing via Lighthouse (mobile and desktop) with Core Web Vitals time-series storage
- Experience Score KPI — composite of Accessibility (40%), Performance (25%), Best Practices (20%), SEO (15%)
- Risk scoring and trending at property, organisation, and agency levels
- Project management integrations — Jira, GitHub Issues, Linear, Asana, Wrike, ClickUp, Monday.com, Azure DevOps, Trello, Notion, Basecamp with bidirectional webhook sync
- Notification email routing — per-agency routes for scan, failure, report, and issue notifications
- Notification webhook routing — Slack (Block Kit), Teams (Adaptive Cards), Discord (embeds)
- MCP server at `/mcp/property-accessibility` exposing issues, risk summaries, and scan findings to AI tools
- Scoped API keys with optional expiry, automated expiry notifications, and daily auto-revocation
- SOC2 activity feed — tamper-evident append-only audit log covering CC6/CC7/CC8 controls
- SOC2 access reviews — quarterly workflows with history export
- SOC2 evidence package — user/role, API key, and access review exports
- Failed login alerting — consecutive failures trigger notifications to the targeted user and agency admins
- WordPress Plugin API — dedicated REST endpoints authenticated via scoped `wordpress` keys
- Multi-tenant architecture — agency → organisation → property with strict data isolation
- Six-role RBAC — SuperUser, AgencyAdmin, OrgAdmin, PropAdmin, Editor, Viewer
- Team management — invite via 7-day email tokens with role pre-assignment
- In-app, email, and webhook notifications with per-user opt-out preferences and weekly digest
- Two-factor authentication via Laravel Fortify with recovery codes
- Scheduled scans — once-off or recurring (daily / weekly / monthly / quarterly)
- User avatars — JPEG, PNG, GIF, or WebP up to 2 MB

# Accessibility Insights Platform

An enterprise web accessibility audit and risk management platform that automatically scans digital properties for WCAG violations, tracks remediation over time, and generates AI-powered governance reports.

---

## Overview

The Accessibility Insights Platform helps agencies monitor, audit, and report on the accessibility compliance of their clients' digital properties. It crawls websites using headless Puppeteer, runs axe-core (WCAG 2.0/2.1 A/AA) and Lighthouse audits on every discovered page, deduplicates raw violations into trackable issues, and generates governance reports with risk scores, trend analysis, and AI-powered remediation recommendations.

---

## Features

- **Automated Crawling & Scanning** — Discovers all pages on a domain and runs axe-core and Lighthouse audits on each page via headless Puppeteer
- **Issue Deduplication & Tracking** — Aggregates raw findings into unique issues with occurrence counts, severity, WCAG category, criteria, tags, help URLs, element HTML, and lifecycle status (open → in progress → resolved)
- **AI-Powered Audits & Remediation** — Generates executive audit summaries and per-issue remediation guidance via OpenAI GPT-4o or Anthropic Claude
- **AI Issue Clustering** — Groups similar open issues into thematic clusters to surface systemic patterns and prioritise remediation effort
- **AI Risk Advisory** — Produces prioritised remediation recommendations from open issue data at property, organisation, or agency scope
- **AI Content Auditing** — Analyses scanned page content for accessibility and readability issues beyond automated rule checks
- **Risk Scoring & Trending** — Calculates weighted risk scores at property, organisation, and agency levels with point-in-time snapshots for trend visualisation
- **Governance Reporting** — AI-generated executive reports with narrative summaries, risk trends, severity breakdowns, remediation progress, compliance status, and actionable recommendations; exportable as JSON, CSV, or PDF
- **Audit Trend Tracking** — Tracks AI-generated audit scores over time and compares trends across consecutive audits
- **Multi-Tenant Architecture** — Agencies contain organisations which contain properties; all data is strictly isolated by tenant
- **Role-Based Access Control** — Six roles: SuperUser, AgencyAdmin, OrgAdmin, PropAdmin, Editor, Viewer — assignable at any scope level
- **Team Management** — Invite team members via 7-day email tokens with role pre-assignment; forced password reset on first login
- **Performance Auditing** — Per-page Lighthouse results including performance, accessibility, SEO, and best-practices scores alongside Core Web Vitals
- **Two-Factor Authentication** — Fortify-powered 2FA with recovery codes
- **Scheduled Scans** — Configurable recurring scans with automated risk snapshot recording

---

## Tech Stack

| Layer                 | Technology                                               |
| --------------------- | -------------------------------------------------------- |
| **Language**          | PHP 8.2                                                  |
| **Framework**         | Laravel 12                                               |
| **Authentication**    | Laravel Fortify v1                                       |
| **Frontend**          | React 19, TypeScript 5.7, Inertia.js v2                  |
| **Styling**           | Tailwind CSS v4                                          |
| **UI Components**     | Radix UI, Headless UI                                    |
| **Visualisation**     | D3 v7, Three.js                                          |
| **Type-Safe Routing** | Laravel Wayfinder v0                                     |
| **Crawler**           | Node.js >=18, Puppeteer 24, axe-core 4.10, Lighthouse 13 |
| **Build**             | Vite 7                                                   |
| **Testing**           | Pest v3, PHPUnit v11, Jest 30                            |
| **Code Quality**      | Laravel Pint, ESLint v9, Prettier v3                     |
| **Dev Environment**   | Laravel Sail (Docker)                                    |
| **Monitoring**        | Laravel Telescope v5                                     |
| **AI**                | OpenAI GPT-4o / Anthropic Claude 3.7 Sonnet              |

---

## Architecture

The application follows a multi-tenant domain-driven structure:

```
Agency
└── Organization
    └── Property
        └── Scan
            ├── ScanPage          (per-page crawl record)
            ├── Finding           (raw axe violation)
            ├── Issue             (deduplicated, tracked violation)
            └── LighthouseResult  (performance metrics)
```

Scans are orchestrated by queued jobs. `RunScanJob` invokes the Node.js crawler to discover pages, then dispatches a `Bus::batch()` of `RunAxeScanPageJob` + `RunLighthouseScanJob` per page. When the batch completes, the scan transitions to `completed`, risk snapshots are recorded at the property, organisation, and agency levels, and an AI audit report is optionally generated.

The platform also maintains a suite of on-demand AI intelligence jobs: `GenerateIssueClusteringJob` groups related issues into themes, `GenerateRiskAdvisoryJob` surfaces prioritised action plans, `GenerateContentAuditJob` checks prose-level accessibility, and `GenerateGovernanceReportJob` assembles executive governance documents. All AI jobs are scoped to either a property, organisation, or agency and store their results as first-class models.

---

## Installation

### Prerequisites

- PHP 8.2
- Composer
- Node.js >= 18
- MySQL or PostgreSQL
- A Chromium-compatible browser (for Puppeteer / Lighthouse)

### Quick Setup

```bash
# Install all dependencies, copy .env, generate key, migrate, and build
composer run setup
cd crawler && npm install && cd ..
```

Or step by step:

```bash
# Install dependencies
composer install
npm install
cd crawler && npm install && cd ..

# Environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate --seed

# Build frontend assets
npm run build
```

### Development

```bash
# Start all services concurrently: HTTP server, queue worker, Vite, and Pail log viewer
composer run dev
```

### Environment Variables

**Application**

| Variable  | Purpose                            |
| --------- | ---------------------------------- |
| `APP_URL` | Application base URL               |
| `APP_KEY` | Laravel application encryption key |

**AI Integration**

| Variable                 | Purpose                                                    |
| ------------------------ | ---------------------------------------------------------- |
| `AI_DRIVER`              | Provider to use: `openai` or `anthropic`                   |
| `OPENAI_API_KEY`         | OpenAI API key (when using `openai` driver)                |
| `ANTHROPIC_API_KEY`      | Anthropic API key (when using `anthropic` driver)          |
| `AI_AUTO_GENERATE_AUDIT` | Auto-generate AI audit on scan completion (`true`/`false`) |

**Crawler**

| Variable                   | Purpose                                                |
| -------------------------- | ------------------------------------------------------ |
| `CRAWLER_SCRIPT_PATH`      | Path to `crawler/scan.js` (default: `crawler/scan.js`) |
| `CRAWLER_TIMEOUT`          | Max seconds for a full crawl (default: `300`)          |
| `CRAWLER_MAX_PAGES`        | Maximum pages to crawl per scan (default: `50`)        |
| `CRAWLER_MAX_DEPTH`        | Maximum link depth to follow (default: `5`)            |
| `CRAWLER_PAGE_TIMEOUT_MS`  | Page load timeout in milliseconds (default: `30000`)   |
| `CRAWLER_NAV_TIMEOUT_MS`   | Navigation timeout in milliseconds (default: `60000`)  |
| `CRAWLER_REQUEST_DELAY_MS` | Delay between page requests (default: `500`)           |

**Lighthouse**

| Variable             | Purpose                                         |
| -------------------- | ----------------------------------------------- |
| `LIGHTHOUSE_BINARY`  | Path to Lighthouse CLI binary                   |
| `LIGHTHOUSE_TIMEOUT` | Max seconds per Lighthouse run (default: `120`) |
| `LIGHTHOUSE_ENABLED` | Toggle Lighthouse performance audits on/off     |

---

## Domain

### Roles

| Role          | Description                                               |
| ------------- | --------------------------------------------------------- |
| `SuperUser`   | Full platform access across all agencies                  |
| `AgencyAdmin` | Manages all organisations and properties within an agency |
| `OrgAdmin`    | Manages all properties within an organisation             |
| `PropAdmin`   | Manages a single property                                 |
| `Editor`      | Can create scans and update issues                        |
| `Viewer`      | Read-only access                                          |

### Scan Lifecycle

1. A `Scan` is created with status `pending`
2. `RunScanJob` is dispatched; invokes the Node.js crawler to discover all pages
3. A `Bus::batch()` of `RunAxeScanPageJob` + `RunLighthouseScanJob` is dispatched per discovered page
4. On batch completion the scan transitions to `completed`; risk snapshots are recorded at property, organisation, and agency levels
5. If `AI_AUTO_GENERATE_AUDIT=true`, `GenerateAiAuditJob` is dispatched to produce an executive audit report
6. Any batch failure transitions the scan to `failed`

### Issue Lifecycle

Raw axe-core `Finding` records are normalised by `IssueNormalizer` into deduplicated `Issue` records. Issues are enriched with WCAG criteria, descriptive tags, help URLs, and the problematic element's HTML. They flow through: `open` → `in_progress` → `resolved`. Issues can be assigned to team members and carry AI-generated remediation suggestions.

### AI Intelligence Suite

Four AI analysis types sit above the scan layer, each scoped to a property, organisation, or agency:

| Type                  | Model              | Description                                                                                                                 |
| --------------------- | ------------------ | --------------------------------------------------------------------------------------------------------------------------- |
| **Audit**             | `Audit`            | Executive summary of a scan's findings with pass/fail scoring                                                               |
| **Issue Clusters**    | `IssueCluster`     | Groups open issues into thematic clusters to reveal systemic problems                                                       |
| **Risk Advisory**     | `RiskAdvisory`     | Prioritised list of remediation recommendations ranked by impact                                                            |
| **Content Audit**     | `ContentAudit`     | Prose-level analysis of page content for accessibility and readability                                                      |
| **Governance Report** | `GovernanceReport` | Comprehensive executive report with narrative, risk trends, severity breakdown, remediation progress, and compliance status |

Governance reports support configurable date ranges and can be scheduled alongside scans. They are exportable in JSON, CSV, and PDF formats.

### Risk Scoring

Weighted risk scores are calculated and snapshotted at three levels: `PropertyRiskSnapshot`, `OrganizationRiskSnapshot`, and `AgencyRiskSnapshot`. These snapshots power trend charts and governance reports.

---

## Background Jobs

| Job                           | Purpose                                                 | Retries                | Timeout |
| ----------------------------- | ------------------------------------------------------- | ---------------------- | ------- |
| `RunScanJob`                  | Orchestrates full scan lifecycle                        | 3 (10s / 30s backoff)  | 600s    |
| `RunAxeScanPageJob`           | Runs axe-core audit on a single page                    | batch                  | —       |
| `RunLighthouseScanJob`        | Runs Lighthouse performance audit on a single page      | batch                  | —       |
| `GenerateAiAuditJob`          | Creates AI-powered audit report from scan data          | 2 (60s / 120s backoff) | 300s    |
| `GenerateIssueRemediationJob` | Generates AI remediation suggestion for an issue        | —                      | —       |
| `GenerateIssueClusteringJob`  | Clusters open issues into themes via AI                 | —                      | —       |
| `GenerateRiskAdvisoryJob`     | Produces prioritised risk recommendations via AI        | —                      | —       |
| `GenerateContentAuditJob`     | Runs AI content accessibility analysis on scanned pages | —                      | —       |
| `GenerateGovernanceReportJob` | Assembles a full AI-generated governance report         | —                      | —       |

---

## Artisan Commands

| Command                                   | Description                                 |
| ----------------------------------------- | ------------------------------------------- |
| `php artisan scans:run-scheduled`         | Execute all pending scheduled scans         |
| `php artisan risk:snapshot-agency`        | Record a point-in-time agency risk snapshot |
| `php artisan governance:generate-reports` | Generate scheduled governance reports       |
| `php artisan users:backfill-roles`        | Populate historical user role records       |

---

## Crawler

The Node.js crawler in `crawler/` is a standalone CLI tool invoked by `RunScanJob`:

```bash
node crawler/scan.js <url> [maxDepth] [maxPages]
```

It uses headless Puppeteer to crawl the target domain, respects `robots.txt` and domain boundaries, and outputs per-page axe-core results as JSON to stdout.

**Key files:**

| File                    | Purpose                                                |
| ----------------------- | ------------------------------------------------------ |
| `crawler/scan.js`       | Main entry point                                       |
| `crawler/axeRunner.js`  | axe-core audit execution                               |
| `crawler/crawlUtils.js` | URL normalisation, link extraction, robots.txt parsing |
| `crawler/config.js`     | Puppeteer/axe configuration (environment-driven)       |

---

## Scripts

**PHP / Composer**

```bash
composer run setup      # Full initial setup (install, migrate, build)
composer run dev        # Start server, queue worker, and Vite concurrently
composer run lint       # Run Laravel Pint formatter
composer run test       # Lint check + full test suite
```

**Node / npm**

```bash
npm run dev             # Start Vite dev server
npm run build           # Production asset build
npm run build:ssr       # SSR + client build
npm run lint            # ESLint with auto-fix
npm run format          # Prettier format
npm run types           # TypeScript type check
```

---

## Testing

```bash
# Run all tests
php artisan test --compact

# Run a specific test file or filter by name
php artisan test --compact --filter=ScanTest

# Full suite with lint check
composer run test
```

Crawler unit tests (Jest):

```bash
cd crawler && npm test
```

---

## Key Configuration Files

| File                    | Purpose                                                      |
| ----------------------- | ------------------------------------------------------------ |
| `config/ai.php`         | AI driver selection, model, temperature, token limits        |
| `config/crawler.php`    | Crawler timeout, max depth, max pages                        |
| `config/lighthouse.php` | Lighthouse binary path, timeout, feature flag                |
| `config/fortify.php`    | Authentication feature flags (2FA, email verification, etc.) |

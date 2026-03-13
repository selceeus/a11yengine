# Accessibility Insights Platform

An enterprise web accessibility audit and risk management platform that automatically scans digital properties for WCAG violations and tracks remediation over time.

---

## Overview

The Accessibility Insights Platform helps agencies monitor, audit, and report on the accessibility compliance of their clients' digital properties. It crawls websites using headless Puppeteer, runs axe-core and Lighthouse audits on every discovered page, deduplicates raw violations into trackable issues, and generates governance reports with risk scores and trend analysis.

---

## Features

- **Automated Crawling & Scanning** — Discovers all pages on a domain and runs axe-core (WCAG 2.0/2.1 A/AA) and Lighthouse audits on each page via headless Puppeteer
- **Issue Deduplication & Tracking** — Aggregates raw findings into unique issues with occurrence counts, severity, WCAG category, and lifecycle status (open → in progress → resolved)
- **Risk Scoring & Trending** — Calculates weighted risk scores at property, organisation, and agency levels with point-in-time snapshots for trend visualisation
- **Governance Reporting** — Executive governance reports covering severity distribution, issue ageing, WCAG coverage, assistive-technology impact, and Core Web Vitals
- **Multi-Tenant Architecture** — Agencies contain organisations which contain properties; all data is strictly isolated by tenant
- **Role-Based Access Control** — Six roles: SuperUser, AgencyAdmin, OrgAdmin, PropAdmin, Editor, Viewer — assignable at any scope level
- **Team Management** — Invite team members via 7-day email tokens with role pre-assignment
- **Performance Auditing** — Per-page Lighthouse results including performance, accessibility, SEO, and best-practices scores alongside Core Web Vitals
- **Two-Factor Authentication** — Fortify-powered 2FA with recovery codes

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Language** | PHP 8.2 |
| **Framework** | Laravel 12 |
| **Auth** | Laravel Fortify v1 |
| **Frontend** | React 19, TypeScript 5.7, Inertia.js v2 |
| **Styling** | Tailwind CSS v4 |
| **Components** | Radix UI, Headless UI |
| **Visualisation** | D3 v7, Three.js |
| **Routing** | Laravel Wayfinder (type-safe route generation) |
| **Crawler** | Node.js >=18, Puppeteer 24, axe-core 4.10, Lighthouse 13 |
| **Build** | Vite 7 |
| **Testing** | Pest v3, PHPUnit v11, Jest 30 |
| **Code Quality** | Laravel Pint, ESLint v9, Prettier v3 |
| **Dev Environment** | Laravel Sail (Docker) |
| **Monitoring** | Laravel Telescope v5 |

---

## Architecture

The application follows a multi-tenant domain-driven structure:

```
Agency
+-- Organization
    +-- Property
        +-- Scan
            +-- ScanPage          (per-page crawl record)
            +-- Finding           (raw axe violation)
            +-- Issue             (deduplicated, tracked violation)
            +-- LighthouseResult  (performance metrics)
```

Scans are orchestrated by queued jobs. `RunScanJob` invokes the Node.js crawler to discover pages, then dispatches a `Bus::batch()` of `RunAxeScanPageJob` + `RunLighthouseScanJob` per page. When the batch completes, the scan transitions to `completed` and risk snapshots are recorded at the property, organisation, and agency levels.

---

## Installation

### Prerequisites

- PHP 8.2
- Composer
- Node.js >= 18
- MySQL or PostgreSQL
- A Chromium-compatible browser (for Puppeteer / Lighthouse)

### Setup

```bash
# Clone and install dependencies
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
# Start all services (server, queue, vite, pail)
composer run dev
```

### Environment Variables

| Variable | Purpose |
|---|---|
| `APP_URL` | Application base URL |
| `CRAWLER_SCRIPT_PATH` | Path to `crawler/scan.js` (default: `crawler/scan.js`) |
| `CRAWLER_TIMEOUT` | Max seconds for a full crawl (default: `300`) |
| `LIGHTHOUSE_BINARY` | Path to Lighthouse CLI binary |
| `LIGHTHOUSE_TIMEOUT` | Max seconds per Lighthouse run (default: `120`) |
| `LIGHTHOUSE_ENABLED` | Toggle Lighthouse audits on/off |

---

## Testing

```bash
# Run all tests
php artisan test --compact

# Run a specific test file or filter
php artisan test --compact --filter=ScanTest
```

Crawler unit tests:

```bash
cd crawler && npm test
```

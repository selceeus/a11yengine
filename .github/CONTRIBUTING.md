# Contributing to A11y Engine

Thank you for helping make the web more accessible! This guide will get you up and running.

---

## Local Development Setup

### Prerequisites

- PHP 8.2+
- Composer
- Node.js ≥ 18
- PostgreSQL with the [pgvector](https://github.com/pgvector/pgvector) extension
- A Chromium-compatible browser (for Playwright and Lighthouse)

### Steps

```bash
# 1. Clone and install all dependencies
git clone https://github.com/your-org/a11yengine.git
cd a11yengine
composer run setup
cd crawler && npm install && cd ..

# 2. Configure your environment
cp .env.example .env
# Edit .env — at minimum set DB_* and APP_URL

# 3. Seed demo data (optional but recommended)
php artisan db:seed --class=DemoSeeder
php artisan db:seed --class=LawsuitDataSeeder

# 4. Start all services
composer run dev
```

### Additional Services

**Reverb (WebSockets — required for real-time scan progress)**

```bash
php artisan reverb:start
```

**veraPDF (required for PDF accessibility scanning)**

Start the veraPDF REST microservice via Docker:

```bash
docker run -d -p 8080:8080 verapdf/rest:latest
```

Then set `PDF_SCANNER_URL=http://localhost:8080` and `PDF_SCANNER_ENABLED=true` in your `.env`.

**AI features**

Set `AI_DRIVER=openai` (or `anthropic`) and provide the corresponding API key in `.env`. AI features are optional — the crawler and WCAG auditing work without them.

---

## Branch Naming

| Type             | Pattern                     | Example                     |
| ---------------- | --------------------------- | --------------------------- |
| Feature          | `feature/short-description` | `feature/pdf-report-export` |
| Bug fix          | `fix/short-description`     | `fix/scan-timeout-handling` |
| Chore / refactor | `chore/short-description`   | `chore/update-dependencies` |

All branches should be cut from `main`.

---

## Making Changes

1. Write or update a Pest test for your change
2. Run the affected tests: `php artisan test --compact --filter=YourTestName`
3. Run Pint to fix PHP formatting: `vendor/bin/pint --dirty`
4. Run ESLint + Prettier for frontend files: `npm run lint && npm run format`
5. Commit with a descriptive message

---

## Pull Request Checklist

Before opening a PR, confirm:

- [ ] Tests pass (`php artisan test --compact`)
- [ ] Pint is clean (`vendor/bin/pint --dirty --format agent`)
- [ ] ESLint passes (`npm run lint`)
- [ ] `.env.example` updated if new environment variables were added
- [ ] README updated if the change affects setup or behaviour

---

## Code Style

- PHP: enforced by [Laravel Pint](https://laravel.com/docs/pint) (`pint.json` in root)
- TypeScript/React: enforced by ESLint v9 + Prettier v3
- Always use curly braces for control structures, even single-line bodies
- Use PHP 8 constructor property promotion
- Always declare explicit return types on methods

---

## Reporting Issues

Use the GitHub issue templates. For security vulnerabilities, see [SECURITY.md](SECURITY.md).

# JobHub: a WordPress job board

A complete job board built on WordPress with a **hand-coded theme** and a **custom plugin**. No page builders and no premium plugins.

Employers sign up, post jobs and review applicants from their own dashboard. Candidates search with live filters and apply with a CV upload. A REST API exposes the data, so you can put a headless or mobile frontend on top.

> Add screenshots here: homepage, job listings with filters, single job, employer dashboard.

---

## Quick start (about 2 minutes)

You only need [Docker Desktop](https://www.docker.com/products/docker-desktop/).

```bash
git clone https://github.com/kashish-awasthi/jobhub-wordpress.git jobhub && cd jobhub
./bin/setup.sh
```

| What | URL | Login |
|---|---|---|
| Website | http://localhost:8088 | |
| WP Admin | http://localhost:8088/wp-admin | `admin` / `admin123` |
| Employer dashboard | http://localhost:8088/employer-dashboard | `employer` / `employer123` |
| Emails (Mailpit) | http://localhost:8025 | |
| phpMyAdmin | http://localhost:8089 | |

These are local demo accounts only. To change ports or passwords, copy `.env.example` to `.env` and edit it.

---

## Features

### For candidates
- **Live search and filters:** keyword, category, location, job type, minimum salary, remote only and sort order. Results update over AJAX without reloading the page, and the URL updates too, so filtered searches can be shared.
- **Works without JavaScript:** the same filter form submits normally and the server filters the results (progressive enhancement).
- **Apply in one step:** name, email, cover note and CV upload. No account needed.
- **Confirmation email** sent to the candidate.

### For employers
- **Front-end sign-up and login.** Employers never see wp-admin.
- **Post and edit jobs** from the front end, with optional admin approval.
- **Dashboard** with stats, a jobs table, "mark filled", reopen and delete.
- **Applicant review:** read cover letters, download CVs, set a status (new / reviewed / shortlisted / rejected).
- **Email notification** for every new application.

### For admins
- **Jobs → Settings** page built with the Settings API: jobs per page, currency, approval, expiry, CV size limit and page selection.
- Custom admin columns: company, salary, application count (links to the filtered applications list).
- **Automatic expiry:** a daily WP-Cron job moves jobs past their deadline or older than N days to a custom `expired` status.

### Developer features
- **Gutenberg block** `jobhub/latest-jobs`, a dynamic block with inspector controls (count, category, featured only). It's written without a build step.
- **REST API** (`/wp-json/jobhub/v1/`), ready for a headless frontend.
- **WP-CLI command** `wp jobhub seed` to load demo data and `wp jobhub expire` to run expiry now.
- **SEO:** `JobPosting` schema (JSON-LD) on each job, so listings can show in Google for Jobs.
- **Template overrides:** any theme can override plugin templates by copying them to `your-theme/jobhub/`.

---

## Security

| Risk | How it's handled |
|---|---|
| CSRF | Nonces on every form and action (`wp_nonce_field`, `check_admin_referer`, `check_ajax_referer`) |
| Access control | Custom capability type (`edit_jobs`, `publish_jobs`, …) with `map_meta_cap`. Employers can only edit their own jobs and only see their own applicants |
| XSS | All output escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`) |
| SQL injection | Only `WP_Query` and meta APIs are used, so there's no raw SQL |
| Malicious uploads | CV type checked by real file content (`wp_check_filetype_and_ext`), with a size limit and a random filename |
| CV privacy | CVs live in `uploads/jobhub-cvs/`, blocked by `.htaccess` (HTTP 403). They can only be downloaded through a permission-checked, nonce-protected endpoint |
| Spam | Honeypot field and a duplicate-application check |
| Private data in API | The employer's application email is excluded from the public REST response |

---

## REST API

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/wp-json/jobhub/v1/jobs` | public | List jobs. Filters: `search`, `category`, `type`, `location`, `remote`, `salary`, `sort`, `page`, `per_page`. Totals in the `X-WP-Total` / `X-WP-TotalPages` headers |
| GET | `/wp-json/jobhub/v1/jobs/{id}` | public | Single job with full description |
| GET | `/wp-json/jobhub/v1/stats` | public | Job counts by category, type and location (cached for 1 hour with a transient) |
| GET | `/wp-json/jobhub/v1/me/applications` | employer | Applications to your jobs |

```bash
curl "http://localhost:8088/wp-json/jobhub/v1/jobs?category=engineering&remote=1"
```

---

## Project structure

```
jobhub/
├── docker-compose.yml          WordPress, MySQL, phpMyAdmin, Mailpit, WP-CLI
├── bin/setup.sh                One-command install + demo data
└── wp-content/
    ├── mu-plugins/
    │   └── local-mailpit.php   Sends local emails to Mailpit
    ├── plugins/jobhub-core/    ← all business logic
    │   ├── jobhub-core.php     Bootstrap
    │   ├── includes/
    │   │   ├── class-post-types.php    job + job_application CPTs, taxonomies, "expired" status
    │   │   ├── class-job-meta.php      Registered meta, admin meta box, admin columns
    │   │   ├── class-roles.php         Employer role, capabilities, wp-admin lockout
    │   │   ├── class-query.php         One query builder shared by archive, AJAX and REST
    │   │   ├── class-applications.php  Apply handler, private CV storage, emails
    │   │   ├── class-employer.php      Sign-up, submit form, dashboard shortcodes
    │   │   ├── class-ajax.php          Live filtering endpoint
    │   │   ├── class-rest.php          REST API routes
    │   │   ├── class-settings.php      Settings API page
    │   │   ├── class-expiry.php        Daily WP-Cron expiry
    │   │   ├── class-blocks.php        Block registration + [jobhub_jobs] shortcode
    │   │   ├── class-cli.php           wp jobhub seed / expire
    │   │   └── helpers.php             Template functions for themes
    │   ├── blocks/latest-jobs/         block.json, editor script, render.php
    │   ├── templates/                  Overridable job card, filters, apply form
    │   └── assets/                     CSS + vanilla JS (no jQuery)
    └── themes/jobhub/          ← presentation only
        ├── front-page.php      Hero search, categories, latest jobs, CTA
        ├── archive-job.php     Listings + filters + load more
        ├── single-job.php      Job detail, overview, apply form, similar jobs
        └── assets/             Theme CSS + mobile nav JS
```

**Design decision:** the theme only handles presentation, and all logic lives in the plugin. If a client changes theme, their jobs, applications and API keep working. Plugin templates can be overridden from any theme.

---

## Shortcodes and block

| Shortcode / block | What it does |
|---|---|
| `[jobhub_submit_job]` | Employer login/sign-up, then the post/edit job form |
| `[jobhub_dashboard]` | Employer dashboard |
| `[jobhub_jobs count="4" category="design" featured="1"]` | List of jobs |
| **Latest Jobs** block | Same as above, with editor controls |

---

## Useful commands

```bash
docker compose run --rm wpcli wp jobhub seed --force   # add demo data again
docker compose run --rm wpcli wp jobhub expire         # run expiry now
docker compose down                                    # stop
docker compose down -v                                 # stop and delete all data
```

## Tech
PHP 8.2 · WordPress 6.x · MySQL 8 · Vanilla JS · CSS custom properties · Docker · WP-CLI

## Ideas to extend
- WooCommerce: paid "featured job" listings
- Candidate accounts with saved jobs and job alerts
- Next.js frontend that uses the REST API
- PHPUnit tests with `wp scaffold plugin-tests`

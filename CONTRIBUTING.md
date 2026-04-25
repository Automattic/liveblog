# Contributing

Thanks for your interest in Liveblog! This guide covers how to report bugs, set up a development environment, understand the codebase, and submit changes.

## Reporting bugs

First check the issue hasn't [already been reported](https://github.com/Automattic/liveblog/issues). When opening a new issue, make sure that:

* The bug is reproducible in a standard WordPress install.
* You have clear steps to reproduce.
* The bug is reproducible consistently.

## Reporting security vulnerabilities

Please **do not** open a public issue for security vulnerabilities. See the security policy for the disclosure process.

## Development environment

Liveblog uses [`@wordpress/env`](https://www.npmjs.com/package/@wordpress/env) (`wp-env`) for local development and integration testing.

### Prerequisites

* PHP 7.4 or higher.
* [Composer](https://getcomposer.org/).
* [Node.js](https://nodejs.org/) and npm.
* [Docker](https://www.docker.com/) (required by `wp-env`).

### Setup

```bash
git clone git@github.com:Automattic/liveblog.git
cd liveblog
composer install
npm install
npm run build       # build front-end assets
npx wp-env start    # start local WordPress on http://localhost:8888
```

WordPress admin: [http://localhost:8888/wp-admin](http://localhost:8888/wp-admin) (`admin` / `password`).

### Useful commands

```bash
composer cs                   # check code standards (PHPCS)
composer cs-fix               # auto-fix code standards (PHPCBF)
composer lint                 # PHP syntax lint
composer test:unit            # unit tests (no WordPress)
composer test:integration     # integration tests (requires wp-env running)
composer test:integration-ms  # multisite integration tests
npm run build                 # build front-end assets
npm run lint:js               # ESLint
npm run lint:css              # Stylelint
```

## Submitting changes

1. [Fork the repo](https://github.com/Automattic/liveblog/fork).
2. Clone your fork.
3. Create a feature branch from `develop` (the default branch). Use clear naming (e.g. `feature/add-thing` or `fix/issue-123`).
4. Make your changes; add tests where it makes sense.
5. Run `composer cs`, `composer test:unit` and `composer test:integration` before pushing.
6. Open a pull request against `develop`.

Reviews can take a little time — we're [busy people](https://automattic.com/work-with-us). Thanks for your patience.

## Codebase orientation

### Glossary

* **Entry** — a single piece of text an author posts to the liveblog.
* **Liveblog post** — a WordPress post with the liveblog enabled. It shows entries in real time and lets authorised users insert new ones.
* **Refresh interval** — how often the client checks for entry updates.
* **Nag** — when there's an update, the front end shows a nag instead of inserting the new entries directly. The nag has a link to load them.
* **Modifying entry** — an entry that updates or deletes (replaces) an existing entry.

### Major design decisions

* **Each entry is a comment.** Adding a lot of posts quickly leads to too much cache invalidation. Comments don't have a per-comment cache entry, so a comment-backed liveblog scales much better.
* **The front end polls for new comments.** Long-polling or server push would be faster but require infrastructure most users don't have. WebSocket support is available as an opt-in via Redis and Socket.IO — see [`docs/websockets.md`](docs/websockets.md).
* **Polling URLs use timestamp ranges.** Endpoints take the form `/liveblog/<start-timestamp>/<end-timestamp>/` and return entries in that window. Bounded ranges can be cached indefinitely so we don't have to invalidate on each entry.
* **Each entry change is a new entry.** Because cached results can't be invalidated, we don't allow changing an entry in place. Updates insert a new entry and mark it as a replacement of the older one.

### Code organisation

* `liveblog.php` — main plugin file.
* `classes/` — most of the PHP backend (legacy procedural-style classes prefixed `WPCOM_Liveblog_`).
* `src/react/` — React/Redux front-end (Lexical-based editor).
* `src/admin/` — admin-side JavaScript.
* `src/styles/` — SCSS stylesheets.
* `templates/` — PHP templates rendered to the front end.
* `languages/` — translation files.
* `tests/Unit/` — unit tests (no WordPress dependency).
* `tests/Integration/` — integration tests (require `wp-env`).

### Backend overview

* `WPCOM_Liveblog` is the central class. Most of its members are static; it's responsible for the bulk of the backend wiring. Parts are gradually being broken out into smaller classes.
* `<permalink>/liveblog/*` URLs are handled by `handle_ajax_request()`. Methods that respond to those AJAX requests are prefixed `ajax_`.
* `WPCOM_Liveblog_Rest_Api` registers the `liveblog/v1` REST routes, which are the modern equivalent of the AJAX endpoints.
* `WPCOM_Liveblog_Entry_Query` searches for entries by various criteria. Its methods return arrays of `WPCOM_Liveblog_Entry` instances.
* `WPCOM_Liveblog_Entry` represents a single liveblog entry. It wraps the underlying comment data and renders the entry in different contexts.

## Conventions

* **Coding standards.** WordPress + VIP standards via `automattic/vipwpcs`. Run `composer cs` before pushing.
* **Tests.** Add unit tests for isolated logic and integration tests for anything that touches WordPress.
* **i18n.** All user-facing strings use the `liveblog` text domain.
* **Commits.** Conventional Commits format (`feat:`, `fix:`, `test:`, `chore:`, etc.). Explain *why*, not just what.
* **Branches.** Branch from `develop`. The `2.x` branch is a parallel maintenance line; backports are handled separately by maintainers.

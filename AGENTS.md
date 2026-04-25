# Liveblog

Real-time liveblogging plugin for WordPress with a React-based editor and a comment-backed entry store.

## Project knowledge

| Property | Value |
|----------|-------|
| **Main file** | `liveblog.php` |
| **Text domain** | `liveblog` |
| **Version** | 1.11.1 |
| **Requires PHP** | 7.4+ |
| **Requires WP** | 6.4+ |
| **Default branch** | `develop` |
| **Maintenance branch** | `2.x` (parallel modernized line; do not cross-port without checking) |

### Directory structure

```
liveblog/
├── liveblog.php          # Main plugin file
├── classes/              # Legacy PHP classes (WPCOM_Liveblog_*)
├── src/
│   ├── react/            # React/Redux front-end (Lexical editor, live polling)
│   ├── admin/            # Admin-side JavaScript
│   └── styles/           # SCSS stylesheets
├── templates/            # PHP templates rendered to the front end
├── languages/            # Translation files (.pot, .po, .mo)
├── tests/
│   ├── Unit/             # Unit tests (no WordPress dependency)
│   └── Integration/      # Integration tests (require wp-env)
├── docs/                 # End-user and developer reference
└── .github/workflows/    # CI: cs-lint, unit, integration, js-unit, lint, build, deploy
```

### Key classes and files

* `WPCOM_Liveblog` (`liveblog.php`) — the central class. Heavily static. Owns most plugin wiring, the legacy `<permalink>/liveblog/*` AJAX endpoints (`ajax_*` methods), and the post-state machinery.
* `WPCOM_Liveblog_Rest_Api` (`classes/class-wpcom-liveblog-rest-api.php`) — registers the modern `liveblog/v1` REST routes used by the React front end.
* `WPCOM_Liveblog_Entry` (`classes/class-wpcom-liveblog-entry.php`) — wraps a single entry. Each entry is a WordPress comment.
* `WPCOM_Liveblog_Entry_Query` (`classes/class-wpcom-liveblog-entry-query.php`) — query API for retrieving entries by time, ID, key-event status, etc.
* `WPCOM_Liveblog_Entry_Extend_Feature_*` — feature classes for `#hashtags`, `/commands`, `@authors`, `:emoji:`.
* `WPCOM_Liveblog_Entry_Key_Events` — key-event widget and shortcode logic.
* `WPCOM_Liveblog_Cron` — auto-archive scheduling.
* `WPCOM_Liveblog_Socketio*` — optional WebSocket integration via Redis and the external [liveblog-sockets-app](https://github.com/Automattic/liveblog-sockets-app).
* `WPCOM_Liveblog_Lazyloader` — lazy-loading entries on the front end.
* `WPCOM_Liveblog_AMP*` — AMP integration.
* `WPCOM_Liveblog_WP_CLI` — `wp liveblog` commands (currently `fix-archive`).

### Dependencies

* **Runtime PHP**: `composer/installers`. WebSocket users also pull in `predis/predis` and `rase/socket.io-emitter`.
* **Dev**: `automattic/vipwpcs`, `phpunit/phpunit`, `yoast/wp-test-utils`, `php-parallel-lint`, `phpcompatibility/phpcompatibility-wp`.
* **Front end**: React 18, Lexical 0.43.x, Redux + Redux-Observable, `@wordpress/scripts` for builds.

## Commands

```bash
composer cs                   # PHPCS check (WordPress + VIP standards)
composer cs-fix               # PHPCBF auto-fix
composer lint                 # PHP syntax lint
composer test:unit            # unit tests (no WordPress)
composer test:integration     # integration tests (requires wp-env)
composer test:integration-ms  # multisite integration tests
composer coverage             # tests with HTML coverage report
npm run build                 # build front-end assets via wp-scripts
npm run lint:js               # ESLint
npm run lint:css              # Stylelint
npm test                      # JavaScript unit tests (Jest)
npx wp-env start              # start local WordPress on http://localhost:8888
```

## Conventions

* **Branch from `develop`.** Use `feature/<thing>` or `fix/<thing>` naming. PRs target `develop`.
* **Commits.** Use the `/commit` skill. Conventional Commits format. Explain *why* over *what*.
* **PRs.** Use the `/pr` skill. The repo uses merge commits (not squash).
* **Code style.** WordPress + VIP standards via PHPCS. Tabs for indentation. Run `composer cs` before pushing.
* **Tests.** Unit tests for isolated logic, integration tests for anything that touches WordPress. Use the `Yoast\WPTestUtils` base classes.
* **i18n.** All user-facing strings use the `liveblog` text domain.

## Architectural decisions

* **Comment-based storage.** Liveblog entries are stored as WordPress comments, not custom post types or custom tables. This is deliberate: cache invalidation on a comment-backed store scales much better than a post-backed one. Do not switch.
* **Each entry change is a new entry.** Updates and deletions insert a new entry that "replaces" the old one. This lets us cache the timestamp-bounded polling endpoints indefinitely without per-edit invalidation.
* **Timestamp-bounded polling URLs.** Endpoints take the form `/liveblog/<start>/<end>/`, returning entries in that window. Closed ranges are cacheable forever.
* **AJAX polling by default, WebSockets optional.** The plugin polls for updates by default. WebSocket support via Redis and Socket.IO is opt-in (`LIVEBLOG_USE_SOCKETIO`) and only used for public posts.
* **Two parallel branches.** `develop` is the legacy-architecture mainline. `2.x` is a parallel modernized branch (DDD layout under `src/php/`, DI container, namespaced classes). Security fixes and important bug fixes are typically backported by hand. Architectural changes do **not** cross-port automatically.
* **WordPress.org deployment.** A GitHub Actions workflow deploys to the WordPress.org SVN repository. Do not modify SVN assets manually.

## Common pitfalls

* **Do not edit `vendor/`, `node_modules/` or `build/`.** They are regenerated.
* **Do not bring 2.x architecture (DI container, namespaced services, DDD layers) into `develop`.** The two branches are intentionally separate.
* **Run `composer cs` before committing.** CI rejects PHPCS violations.
* **Integration tests require `npx wp-env start` to be running.** Otherwise they fail at bootstrap.
* **Comment storage conflicts.** Entries are comments, so be careful with comment moderation, filtering, or other plugins that modify comment queries.
* **Two version sources.** Versions live in `liveblog.php` (header + `LIVEBLOG_VERSION` constant) and `package.json`. Keep them in sync at release time.
* **Template tags are public API.** Helper functions exposed to themes (e.g. `wpcom_liveblog_get_output()`) are part of the public surface; do not rename or remove without a deprecation cycle.
* **Static state in `WPCOM_Liveblog`.** The class holds static state (`$post_id`, `$is_rest_api_call`, cached `$entry_query`). Reset these explicitly in tests that need a clean slate.

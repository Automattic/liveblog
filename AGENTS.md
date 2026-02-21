# Liveblog

Real-time liveblogging plugin for WordPress with React frontend and DDD architecture.

## Project Knowledge

| Property | Value |
|----------|-------|
| **Main file** | `liveblog.php` |
| **Text domain** | `liveblog` |
| **Namespace** | `Automattic\Liveblog` |
| **Source directory** | `src/php/` (PSR-4), `src/react/` (frontend) |
| **Version** | 1.10.0 |
| **Requires PHP** | 8.2+ |
| **Requires WP** | 6.4+ |

### Directory Structure

```
liveblog/
├── liveblog.php                   # Main plugin file (bootstraps DI container)
├── src/
│   ├── php/                       # PHP source (PSR-4: Automattic\Liveblog\)
│   │   ├── Domain/                # Domain layer (entities, value objects, repository interfaces)
│   │   ├── Application/           # Application layer (services, filters, config, presenters)
│   │   └── Infrastructure/        # Infrastructure layer (WordPress integration, CLI, REST API, DI)
│   ├── react/                     # React/Redux frontend (Lexical editor, live polling)
│   └── styles/                    # SCSS stylesheets
├── classes/                       # Legacy classes (excluded from PHPCS)
├── tests/
│   ├── Unit/                      # Unit tests (no WordPress dependency)
│   └── Integration/               # Integration tests (requires wp-env)
├── .github/workflows/             # CI: cs-lint, unit, integration, js-unit, lint, build, behat, deploy
└── .phpcs.xml.dist                # PHPCS configuration
```

### Key Classes and Files

**Domain Layer**
- `Domain/Entity/Entry.php` — Immutable domain entity for liveblog entries
- `Domain/Entity/LiveblogPost.php` — Domain entity for posts with liveblog support
- `Domain/Repository/EntryRepositoryInterface.php` — Repository pattern contract
- `Domain/ValueObject/` — Immutable value objects: `EntryId`, `Author`, `AuthorCollection`, `EntryContent`, `EntryType`

**Application Layer**
- `Application/Service/EntryService.php` — Main service for creating/managing entries
- `Application/Service/EntryQueryService.php` — Service for querying/retrieving entries
- `Application/Service/KeyEventService.php` — Service for managing "key event" entries
- `Application/Service/AutoArchiveService.php` — Automatic archival of old entries
- `Application/Filter/ContentFilterRegistry.php` — Registry pattern for content filters (Author, Hashtag, Emoji, Command)
- `Application/Config/LiveblogConfiguration.php` — Central configuration constants

**Infrastructure Layer**
- `Infrastructure/WordPress/PluginBootstrapper.php` — Wires services to WordPress hooks
- `Infrastructure/DI/Container.php` — Singleton DI container with lazy-loading
- `Infrastructure/WordPress/RestApiController.php` — REST API endpoints (`liveblog/v1`)
- `Infrastructure/Repository/CommentEntryRepository.php` — Stores entries as WordPress comments
- `Infrastructure/CLI/` — 11 WP-CLI commands under `wp liveblog`

### Dependencies

- **Dev**: `automattic/vipwpcs`, `yoast/wp-test-utils`, `phpunit/phpunit`, `predis/predis`, `rase/socket.io-emitter`

## Commands

```bash
composer cs                # Check code standards (PHPCS)
composer cs-fix            # Auto-fix code standard violations
composer lint              # PHP syntax lint
composer test:unit         # Run unit tests (no WordPress needed)
composer test:integration  # Run integration tests (requires wp-env)
composer test:integration-ms  # Run multisite integration tests
composer coverage          # Run tests with HTML coverage report
npm run build              # Build React/JS frontend assets
npm run lint:js            # Lint JavaScript (ESLint)
npm run lint:css           # Lint SCSS (Stylelint)
npm test                   # Run JavaScript unit tests (Jest)
```

## Conventions

Follow the standards documented in `~/code/plugin-standards/` for full details. Key points:

- **Commits**: Use the `/commit` skill. Favour explaining "why" over "what".
- **PRs**: Use the `/pr` skill. Squash and merge by default.
- **Branch naming**: `feature/description`, `fix/description` from `develop`.
- **Testing**: Unit tests for isolated logic (no WordPress), integration tests for WordPress-dependent behaviour. Use `Yoast\WPTestUtils` base classes.
- **Code style**: WordPress coding standards via PHPCS. Tabs for indentation. PSR-4 namespaced classes in `src/php/`.
- **i18n**: All user-facing strings must use the `liveblog` text domain.

## Architectural Decisions

- **Domain-Driven Design (DDD)**: Clear separation into Domain, Application, and Infrastructure layers. New code must respect these boundaries — domain classes must not depend on WordPress.
- **Comment-based storage**: Liveblog entries are stored as WordPress comments, not custom post types or tables. The `CommentEntryRepository` implements `EntryRepositoryInterface`. Do not switch to custom database tables.
- **Immutable entities**: `Entry` objects are immutable. Updates create new entries with a "replaces" relationship, not in-place modifications. Respect this pattern.
- **DI Container**: Services are registered in and retrieved from the singleton `Container`. Use the container for service access; do not instantiate services directly.
- **Content filter pipeline**: Content filters (Author, Hashtag, Emoji, Command) are registered via `ContentFilterRegistry`. Extend this registry for new filters rather than adding ad-hoc processing.
- **Hashtag taxonomy**: The `liveblog-hashtags` taxonomy is registered by `HashtagFilter`. It is non-hierarchical and public.
- **Socket.IO optional**: Real-time updates use Socket.IO when available, with polling fallback. Do not make Socket.IO a hard dependency.
- **WordPress.org deployment**: Has a deploy workflow for WordPress.org SVN. Do not manually modify SVN assets.

## Common Pitfalls

- Do not edit WordPress core files or bundled dependencies in `vendor/`.
- Run `composer cs` before committing. CI will reject code standard violations.
- Integration tests require `npx wp-env start` running first.
- **Domain layer independence**: Classes in `Domain/` must not import from `Infrastructure/` or reference WordPress functions directly. Use interfaces and dependency injection.
- **Entry immutability**: Do not add setters to `Entry` or modify entries in place. Create new entries for updates.
- **Legacy classes in `classes/`**: These are excluded from PHPCS and are being replaced by the namespaced code in `src/php/`. Do not add new code to `classes/`.
- **Three version locations**: Version is defined in `liveblog.php`, `package.json`, and `LiveblogConfiguration.php`. All three must be kept in sync.
- **Template tags are public API**: Functions in `src/php/functions.php` are used by themes. Do not rename or remove them without a deprecation cycle.
- **Comment storage conflicts**: Since entries are stored as comments, be careful with comment moderation, filtering, or plugins that modify comment queries.

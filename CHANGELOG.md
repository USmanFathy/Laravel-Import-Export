# Unreleased — Usman Ahmed rebrand

- Rebranded package metadata, documentation, and copyright attribution to Usman Ahmed.
- Renamed Composer package to `usman-ahmed/laravel-import-export`.
- Renamed the PHP namespace from `the previous vendor namespace` to `UsmanAhmed\\ImportExport`. This is a breaking namespace change; update imports in consuming applications.
- Preserved the existing proprietary license terms.

# Changelog

## 2026-09-06 — Usman Ahmed Laravel Import Export rename / PHP 8.3 compatibility

- Renamed Composer package from `usman-ahmed/laravel-import-export` to `usman-ahmed/laravel-import-export`.
- Renamed the PHP namespace from `UsmanAhmed\\BulkImports` to `UsmanAhmed\\ImportExport`.
- Renamed the service provider to `ImportExportServiceProvider`.
- Added the `ImportExport` facade alias while retaining `BulkImport` within the new namespace for compatibility.
- Lowered the package PHP constraint from `^8.4` to `^8.3`.
- Moved OpenSpout to `^4.28` so PHP 8.3 remains installable.
- Updated OpenSpout options construction for the 4.x API.
- Expanded CI to PHP 8.3, 8.4, and 8.5.
- Preserved database tables, migrations, REST endpoints, and existing config keys to avoid unnecessary runtime breakage.

## Unreleased — final layer restoration

- Retained metadata-driven import/export/template engines and existing mappings/tests.
- Added secure optional guest credentials, resource/context binding, expiry/revocation, authenticated identity precedence, and explicit system principals.
- Scoped status lists by creator/context and rejected client-selected identity/context/notification recipients.
- Added optional creator/verified-guest notifications with encrypted contacts, customizable channels/content, durable receipts, leases/retries, and failure isolation.
- Restored independent PHP worker competition/redelivery tests and reproducible measured benchmark tooling.
- Published all migrations, corrected counter casts, refreshed documentation and recorded actual verification evidence.
- Corrected the SQLite subprocess harness to exercise bounded transient-lock redelivery, and refreshed archive packaging to include untracked source and current evidence.

No package was published and no production database was migrated by this work.

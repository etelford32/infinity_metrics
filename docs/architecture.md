# Architecture

Infinity Metrics begins as a standalone WordPress plugin. One WordPress
installation can receive analytics from many sites or applications; each
producer is represented by a source.

## Milestone 1 components

- `infinity-metrics.php` defines plugin constants, loads dependencies, and
  registers database installation and upgrade checks.
- `Infinity_Metrics_Database` owns table names and schema migrations.
- WordPress's `dbDelta()` applies schema changes on activation and whenever the
  stored database version differs from the version in the plugin.

Milestone 2 adds an administrator-only Sources screen and a persistence class.
All administrative mutations use capability checks, WordPress nonces, normalized
input, and POST requests. Milestone 3 adds a REST controller, strict privacy
validator, event persistence, and short-window rate limiting. Milestone 4 adds a
dependency-free browser tracker. Milestone 5 adds an administrator-only,
server-paginated raw Event Explorer. Milestone 6 adds anonymous visitor IDs and
server-side metric aggregation. Milestone 7 adds the first WordPress dashboard.
Milestone 8 makes conversion events configurable per source. Later milestones
will add ordered funnel configuration.

## Storage conventions

Tables use the active WordPress database prefix and its configured character
set and collation. Application timestamps are stored as UTC `datetime` values.
The plugin does not add SQL foreign-key constraints because WordPress upgrades
and common hosting configurations do not consistently support them.

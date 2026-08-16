# Infinity Metrics

Infinity Metrics is a lightweight, self-hosted, multi-source product analytics
platform. WordPress is the first collector and dashboard implementation.

## Current milestone

Milestones 1 through 8 provide the standalone WordPress plugin bootstrap,
versioned database installation, an administrator-only Sources manager, a
privacy-bounded REST event collector, the JavaScript tracker, and a raw Event
Explorer, server-side metrics engine, the first 30-day dashboard, and
source-specific conversion configuration.

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer
- MySQL 5.7 or MariaDB 10.3 or newer

## Install the development build

1. Copy `plugin/infinity-metrics` into `wp-content/plugins/`.
2. Activate **Infinity Metrics** in WordPress.

Activation creates source and event tables using the site's WordPress table
prefix. Database upgrades are checked on normal requests, so a future plugin
update can migrate an existing installation without reactivation.

Use **Analytics → Sources** to create, edit, disable, or delete sources, regenerate
their public collector keys, and copy the tracker installation snippet.

Events can already be submitted directly to
`POST /wp-json/infinity_metrics/v1/event`; see [the API documentation](docs/api.md)
for the request contract and privacy limits.

See [the tracker documentation](docs/tracker.md) for automatic events, manual
tracking, session behavior, and privacy defaults.

Use **Analytics → Events** to verify incoming events by source, event name, date,
or page before relying on aggregated metrics.

See [the metrics documentation](docs/metrics.md) for the current visitor,
session, engagement, interaction, and conversion definitions.

Use **Analytics → Overview** for the 30-day traffic, funnel, source, and top-event
summary described in [the dashboard documentation](docs/dashboard.md).

Use **Analytics → Conversions** to replace the MVP intent and completion defaults
with the exact events that matter for each source.

## Repository layout

- `plugin/infinity-metrics/` — installable WordPress plugin
- `docs/` — architecture and storage documentation
- `tests/` — test infrastructure (added as milestones need it)

## Scope

Infinity Metrics does not include heatmaps, session replay, billing, AI
analysis, or SaaS infrastructure.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

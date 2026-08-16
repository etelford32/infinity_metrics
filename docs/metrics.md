# Metrics engine

`Infinity_Metrics_Metrics` provides server-side aggregation over a source and an
inclusive date range in the WordPress site timezone. Passing source ID `0`
aggregates all sources while keeping anonymous identifiers isolated by source.

## MVP definitions

- **Visitors:** distinct anonymous `visitor_id` values. Events collected before
  visitor IDs were introduced fall back to distinct sessions.
- **Sessions:** distinct `session_id` values per source.
- **Page views:** `page_view` events.
- **Engaged sessions:** sessions containing `engaged_30s`.
- **Interactions:** events other than the five automatic tracker events.
- **Interacting sessions:** sessions containing at least one interaction, used by
  the dashboard funnel.
- **Conversion starts:** `signup_start` and `contact_start` by default.
- **Conversions:** `signup_complete` and `contact_complete` by default.
- **Returning sessions:** sessions from anonymous visitors with more than one
  distinct session during the selected period.
- **Top events:** event counts ordered by count and then name.
- **Traffic:** daily `page_view` counts.
- **Source breakdown:** distinct visitors grouped by source.

The conversion lists can be changed under **Analytics → Conversions**. Once a
source has any definition for a stage, only its enabled definitions apply to
that stage. Sources without definitions retain the MVP defaults. The
`infinity_metrics_conversion_start_events` and
`infinity_metrics_conversion_events` filters can change those fallback defaults.

Visitor IDs are random browser-local identifiers. They are not account IDs,
fingerprints, cookies, or cross-source identities. The metrics engine prefixes
distinct identifiers with their source ID when aggregating multiple sources.

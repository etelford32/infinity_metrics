# Raw Event Explorer

The **Analytics → Events** screen is an administrator-only debugging view of the
validated event stream. It exists to verify collection quality before building
aggregated reports.

## Filters

- Source
- Event name, optionally scoped to the selected source
- Inclusive start and end dates in the WordPress site timezone
- Page substring

Filters are applied by prepared server-side queries. Results are ordered by
event time and ID, newest first, and paginated at 50 rows per page. The matching
event count and active filters are retained while paging.

## Columns

- Time, converted from stored UTC to the WordPress site timezone
- Source
- Event
- Page
- Anonymous session ID
- JSON properties

All stored values are escaped for display. Properties are decoded only for
pretty printing and are never executed or rendered as markup. This screen does
not add exports, aggregation, editing, or deletion; those are outside Milestone
5's event-validation purpose.

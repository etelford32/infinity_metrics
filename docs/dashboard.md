# Dashboard v0.1

The administrator-only **Analytics → Overview** screen shows a fixed, rolling
30-day window. It can aggregate all sources or filter the entire view to one
source.

## Overview content

- Headline visitors, engaged sessions, and conversion events
- Daily page-view traffic chart
- Visitors → engaged → interacted → intent → converted session funnel
- Visitor totals by source in the all-sources view
- Top event counts

The dashboard calls `Infinity_Metrics_Metrics`; it does not calculate metrics in
JavaScript or scan raw events in the browser. Funnel bars are relative to the
visitor total and capped at 100 percent because independent event semantics can
occasionally produce a later-stage count greater than the visitor count.

Daily chart buckets use stored UTC dates in this first dashboard. Date-range
boundaries and all headline, funnel, source, and top-event queries use the
WordPress site timezone as documented by the metrics engine.

The Intent and Converted stages use each source's configured conversion events.
The dashboard intentionally does not add custom date ranges, exports,
comparisons, saved reports, or configurable funnels.

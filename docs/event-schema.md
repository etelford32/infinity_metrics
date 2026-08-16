# Event storage schema

The initial schema creates two tables. Their names below omit the dynamic
WordPress prefix.

## `infinity_metrics_sources`

| Column | Purpose |
| --- | --- |
| `id` | Internal source identifier |
| `name` | Human-readable name |
| `slug` | Unique public source identifier |
| `domain` | Expected site or application domain |
| `api_key` | Unique `im_`-prefixed public collector key |
| `created_at` | UTC creation time |
| `enabled` | Whether collection is permitted |

## `infinity_metrics_events`

| Column | Purpose |
| --- | --- |
| `id` | Internal event identifier |
| `source_id` | Owning source identifier |
| `event` | Event name |
| `visitor_id` | Persistent anonymous browser identifier, when supplied |
| `session_id` | Anonymous session identifier |
| `page` | Page path or application location |
| `referrer` | Referring URL, when supplied |
| `event_timestamp` | Client event time in UTC |
| `properties` | JSON-encoded custom properties |
| `created_at` | Server receipt time in UTC |

Properties remain flexible so custom product events do not require database
migrations. Validation and privacy controls belong to the collector milestone;
the presence of a column does not imply arbitrary content will be accepted.

## Collector contract

The collector accepts JSON objects containing `source`, `key`, `event`,
`session_id`, `timestamp`, and optional `visitor_id`, `page`, `referrer`, and `properties`.
Event names use lowercase letters, numbers, underscores, periods, colons, or
hyphens. Timestamps must be RFC 3339 values, optionally with fractional seconds,
no more than one year old or five minutes in the future. Session IDs are
anonymous identifiers, not user IDs.

Properties may contain at most 50 items, three levels of nesting, 8 KiB of JSON,
and scalar strings no longer than 500 bytes. Keys associated with personal,
form, credential, cookie, message, HTML, or DOM data are rejected. The collector
does not persist request IPs, headers, cookies, or user agents.

## `infinity_metrics_conversions`

Conversion definitions associate one source and event name with either the
`start` or `complete` stage. Definitions can be disabled without deleting raw
events. A unique source/event/stage key prevents duplicate definitions.

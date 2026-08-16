# Event collection API

## Collect one event

```text
POST /wp-json/infinity_metrics/v1/event
Content-Type: application/json
Origin: https://parkersphysics.com
```

The public collector key may be sent as `key` in the JSON body or as the
`X-Infinity-Metrics-Key` header. Browser integrations should use a JSON body so
the complete event contract is visible in one place.

```json
{
  "source": "parkersphysics",
  "key": "im_public_collector_key",
  "event": "galaxy_object_click",
  "visitor_id": "5a0cc4e9-93dc-420c-a4e9-8a85659083f1",
  "session_id": "d8f5e3a9-7560-4d3c-a908-b9e48f041234",
  "page": "/galaxy-map/",
  "referrer": "https://www.google.com/",
  "timestamp": "2026-08-16T12:00:00Z",
  "properties": {
    "object_type": "spiral_galaxy"
  }
}
```

Successful requests return `202 Accepted`:

```json
{"accepted": true}
```

## Validation and security

- JSON request bodies are limited to 16 KiB.
- The source must exist, be enabled, and match the collector key.
- Browser requests must send an `Origin` whose host exactly matches the source
  domain. Server-side collection without an Origin is disabled by default and
  may be explicitly enabled with the `infinity_metrics_allow_missing_origin`
  filter.
- Collection is limited to 600 events per source and 60 events per hashed client
  address per minute by default. Raw client addresses are never stored.
- Unknown fields and sensitive, oversized, deeply nested, or malformed property
  data are rejected rather than silently persisted.

The collector uses `400` for invalid events, `403` for source, key, or origin
failures, `413` for oversized bodies, `429` for rate limiting, and `500` for a
storage failure. Authentication failures intentionally share one response so
the endpoint does not reveal which credential was incorrect.

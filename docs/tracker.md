# JavaScript tracker

Load the tracker from the WordPress installation that receives events:

```html
<script
  src="https://analytics.example.com/wp-content/plugins/infinity-metrics/assets/tracker.js"
  data-source="elliottelford"
  data-key="im_public_collector_key"
  defer
></script>
```

By default, the endpoint is derived from the tracker script's origin. A custom
collector can be selected with `data-endpoint`.

## Automatic events

- `page_view` on each page load.
- `engaged_30s` after 30 seconds while the page is visible.
- `scroll_50` and `scroll_90` once when each depth is reached.
- `second_page` on the second page load in the tab's session.

Anonymous visitor IDs use `localStorage`, and sessions use `sessionStorage`; no
cookies are used. Clearing site data resets the visitor ID, and the identifier
does not contain account or personal data. Page values contain only the path,
and referrers have query strings and fragments removed. Requests use
`credentials: "omit"`. Tracking is disabled when Do Not Track is enabled or
when `window.infinityMetricsDisabled` is set to `true` before the script loads.

## Manual events

```js
InfinityMetrics.track("cta_click", {
  location: "homepage",
  target: "signup"
});
```

Event names and properties must satisfy the collector schema. Do not send names,
email addresses, form values, page text, HTML, DOM content, cookies, or other
personal data. Invalid events resolve to `false` or are rejected by the server.

`track()` returns a promise resolving to `true` only when the collector responds
with `202 Accepted`. Network and validation failures do not interrupt the host
page.

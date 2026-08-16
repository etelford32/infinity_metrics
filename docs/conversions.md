# Configurable conversions

The **Analytics → Conversions** screen defines intent and completed outcomes per
source without changing or duplicating raw events.

Each definition contains:

- Source
- Human-readable name
- Exact event name
- Stage: `start` (intent) or `complete` (converted)
- Active status

Definitions can be created before an event is first observed. Event names follow
the collector's lowercase event-name schema and are limited to 175 characters
to keep the composite uniqueness index compatible with supported databases.

## Fallback behavior

Sources without definitions retain the MVP defaults:

- Intent: `signup_start`, `contact_start`
- Converted: `signup_complete`, `contact_complete`

As soon as a source has at least one definition for a stage, the defaults for
that source and stage are replaced. Only enabled definitions count. This means a
source with definitions that are all disabled intentionally reports zero for
that stage.

All-source dashboard totals evaluate each source's rules independently, so an
ETU conversion cannot accidentally count as a landscaping conversion. Editing
or deleting a definition never changes the raw event stream.

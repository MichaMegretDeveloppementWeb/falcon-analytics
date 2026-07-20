# Changelog

Notable milestones of `falcon/analytics`. Versions are git tags; earlier per-tier
patch tags (v0.1.x) hold the individual steps.

## [0.2.4] - Realtime screen, phase B: world map & redesign (2026-07-20)

The realtime screen gets its connections map and a layout modelled on the
industry reference, still with zero external service.

### Added
- **World map**: SVG basemap embedded in the package, generated offline from
  Natural Earth 110m (public domain), Miller projection, country borders drawn
  at constant width (`vector-effect: non-scaling-stroke`). No tiles, no
  mapping library, no external request: portable by construction. Fixed world
  view. Markers aggregate sessions by city, are regenerated in place on each
  tick (`wire:ignore` + `map` payload) and are sized in **screen pixels**, so
  they stay readable on mobile; online locations pulse, tooltips ride the
  dashboard's delegated system, unlocated sessions get a discreet note.
- **Tab pair above the map** (recent window / online now, equal widths)
  filtering the map markers and the country list through a window event.
- **Countries list** next to the map, derived from the map points (no extra
  query), with per-tab totals.
- **Recent visitors list**: one row per visitor over the last 24 hours (most
  recent session, device icon, online dot, link to the session detail).
- **Live activity feed** reworked: the event type leads the line (conversions
  highlighted), the visitor name and time follow.
- **Dev geolocation**: `analytics.geoip.dev_ip` substitutes a public IP when
  the request IP is private/reserved, so local development gets located
  sessions; inert in production by design.

### Changed
- **Design aligned on the reference**: ink/secondary/muted text tones, blue
  accent and online green, flat 8 px cards, doughnut centres showing the
  **category count** ("3 sources", "2 types"), Sources and Devices side by
  side, full-width "Pages vues" section with proportion bars (stacked under
  the URL on small screens), per-minute chart kept as a bonus below the map.
- **Online now counts distinct visitors** (not sessions), so it can never
  exceed the window's visitor count.

## [0.2.3] - Realtime screen, phase A (2026-07-17)

Who is online now and what happened in the recent window, on any host.

### Added
- **Realtime page** (`analytics.realtime`): online-now and recent-window KPIs,
  per-minute pageview chart, device and source doughnuts with legends, top
  pages, and a bounded scrollable activity feed naming visitors through the
  retroactive attribution (conversions highlighted). No filters by design.
- **Refresh**: plain Livewire polling (`wire:poll.visible`, 10 s default),
  suspended while the tab is hidden; no worker, websocket or external service,
  so it runs on any host. Configurable `analytics.realtime` block (poll,
  online window, recent window, feed bound).
- **Live charts**: `live-line` and `live-donut` components sit under
  `wire:ignore` and update in place from the page's tick event, so a poll
  never destroys or re-animates a chart.
- **Budget**: the whole tick renders in at most 11 bounded, indexed queries,
  frozen by a budget test.
- **SourceLabel** support class: the acquisition-channel labels move out of the
  source Blade component into one shared source of truth.

## [0.2.2] - Identity merging & visitor directory (2026-07-17)

One known person = one visitor profile, and the visitors screen becomes an
all-time directory.

### Added / changed
- **Identity merging**: when a browser gets identified and the subject already
  owns a profile, the two fold together (oldest survives); the folded row
  becomes an alias (`merged_into_id`) whose uuid keeps routing beacons to the
  canonical profile. Sessions record their physical browser (`browser_key`), so
  two devices of one person browsing at once still yield two sessions, and an
  open session survives the merge. A login on a shared browser lands on the
  logged-in person's own profile; the browser keeps its owner. Identified stray
  sessions relocate to their subject's canonical profile.
- **Migration**: adds the two columns, backfills `browser_key` and consolidates
  every pre-existing duplicate profile of the host (no leftover command).
- **GDPR**: erasing a profile also erases its merged aliases. An alias detail
  URL redirects to the canonical profile.
- **Visitors screen**: the list is now the all-time directory of every real
  profile (aliases and bot-only visitors excluded, all-time session counts);
  the period selector visually belongs to the headline "Activité" block and
  drives only it. The role filter stays global.

## [0.2.1] - Retroactive session naming (2026-07-17)

An anonymous session of an identified visitor now displays the person's name.

### Added / changed
- **SessionSubjectAttributor**: sessions display under their own subject when
  authenticated, otherwise under the subject stitched on their visitor, flagged
  "Non connecté" (list sub-line, detail badge with tooltip). The fallback is
  withheld when the visitor's identified sessions point to several distinct
  subjects (shared browser).
- **Visitor detail**: identified sessions carry a "Connecté" badge.
- **Search**: a full name spanning several columns now matches ("René Roy" —
  every word must match one of the name columns), and a subject match also
  surfaces the anonymous sessions named through their visitor.

## [0.2.0] - Audit & hardening (2026-07-08)

A full audit of the package (architecture/SOLID, error handling, query optimisation,
Livewire/validation, naming/comments, migrations/tests/dead-code) against the project
implementation rules, followed by the conformance fixes. Consolidates v0.1.58 to
v0.1.65. Every step kept the full suite, Pint and Larastan green; the marketing CRUD
and the dashboards were revalidated live.

### Fixed / changed
- **Hygiene** (v0.1.58): removed dead code (MarketingTrendChart); made TrendChart
  non-reactive with a wire:key remount; added the missing `visitors.first_seen_at`
  index; guarded `Collector::render()` so it degrades to an empty string instead of
  breaking the host page; raised the GDPR erasure log to `notice`; removed all em
  dashes and added the missing non-breaking spaces in French UI text.
- **Widget resilience** (v0.1.59): all 13 deferred `#[Lazy]` widgets degrade to an
  inline error state instead of a 500 when their read fails (`GuardsWidgetRead`).
- **Write layer** (v0.1.60): extracted the marketing writes into Actions
  (SaveCampaign / DeleteCampaign / SaveAd / DeleteAd) with try/catch + error toast,
  an atomic `saveAd`, campaign-scoped ad deletion, and French validation `messages()`.
- **Attribution & dedup** (v0.1.61): extracted `AttributionResolver`; made
  `FunnelStep::matches()` the single source of truth for step matching (dropping the
  duplicate in `FunnelEvaluator` and the marketing repository); added the missing
  funnel-objective conversion test.
- **Query optimisation** (v0.1.62): batched the per-objective event conversion counts
  on the detail pages into a single query.
- **Calculation layer** (v0.1.63): extracted `MarketingMetricsCalculator` so the
  marketing widgets no longer compute rates and trends inline.
- **Data layer** (v0.1.64): extracted `SubjectReadRepository` from `SubjectResolver`.
- **Dead data** (v0.1.65): dropped the unused `AdObjective.value` field.

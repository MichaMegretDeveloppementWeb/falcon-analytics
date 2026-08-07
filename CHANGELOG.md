# Changelog

Notable milestones of `falcon/analytics`. Versions are git tags; earlier per-tier
patch tags (v0.1.x) hold the individual steps.

## [Unreleased]

### Funnels
- **Parallel branches.** A step can now be declared with `anyOf: [...]`, listing
  labelled alternative ways of reaching the same milestone -- a form opened from
  either of two pages, a signup completed through either of two flows. Laid out
  as consecutive steps these read "went through one, THEN the other" and reported
  zeros; branches sit at the same depth instead, so a visitor advances once
  whichever one they take. The report carries the per-branch reach, rendered
  under the step, so the screen answers which way in visitors actually take.
- `analytics:events:check` now validates branch events too.
- Fully backward compatible: `event:` and `route:` steps are untouched, and a
  step without branches reports an empty `branches` array.

## [1.0.0] - Stable release after the full pre-v1 audit (2026-07-21)

Six-dimension audit (architecture, error handling, queries, security,
documentation, code quality) with every finding fixed.

### Security & robustness
- Configured dashboard/marketing middleware now registered as **Livewire
  persistent middleware** (actions can no longer replay past a revoked
  session); warning logged when a host mounts a module with an empty
  middleware list.
- Ingested `props` capped (key 100, value 500 chars, 8 KB JSON); utm and
  user-agent values truncated to their column lengths; integrations page now
  degrades like every other screen; ad objectives validated against the enum.
- Collector: unhandled fetch rejection silenced, dead branch removed.

### Performance
- New indexes: `sessions.country`, `events (route, occurred_at)` and
  `events (visitor_id, occurred_at)`; country search bounded to the period;
  funnel evaluation now **streams** (no more in-memory accumulation); tagged
  marketing sessions capped (20 000, logged when truncated).

### Architecture
- Marketing report building extracted from the repository (766 → 150 lines)
  into `MarketingReportBuilder`; the triplicated funnel machinery unified in
  `FunnelEventWalker`; campaign form mutualised in `EditsCampaign`;
  visitor-merge logic moved to `VisitorMerger` (service), typed
  `SearchConsoleException`, `VisitorIdentityResolver` rename.
- 331 tests (1053 assertions), Pint and Larastan clean.

## [0.3.5] - UI audit follow-up: last legacy modules aligned (2026-07-21)

### Changed
- **Marketing dashboard "Top pubs"** drops the icon-tile bar-list for the
  ranked-list pattern: position, ad name with "n sessions · campaign"
  sub-line, and the conversion count as the key figure (the cryptic
  "conversions / sessions" caption is gone).
- **Visitor detail** acquisition bars thinned to match the overview style.
- Audit outcome recorded: every other screen (lists as tables, funnels as
  loss bars, session journey, events, campaign/ad details) already follows
  the design principles established with the overview redesign.

## [0.3.4] - Overview redesign: varied presentation per module (2026-07-21)

### Changed
- **Overview page redesigned** on the reference's principles: each section is
  now a single card with vertically-divided columns, and every module gets a
  presentation matching its data instead of the repeated icon-tile bar-list —
  Sources become a doughnut with a delta legend, Localities a plain ranked
  value list, Pages keep the only bars (thin, under the label), Conversions
  show a green dot and their share of the total, Top events a numbered
  ranking, and the Google queries module adopts the headline pattern (big
  click total with delta, per-query "Position moy. · impressions" sub-line
  and per-query deltas against the previous period — new repository read).
- **Wording pass**: explanatory sentences replaced by factual product copy
  ("Dernières données Google : 19 juil.", one-line Search Console CTA,
  "Audience" section title).

## [0.3.3] - Manual Search Console sync from the dashboard (2026-07-21)

### Added
- **"Synchroniser maintenant"** on the integrations screen: runs the exact
  same sync as the nightly command, inline with a loading state (no worker
  anywhere, by design), and reports the number of rows written. The sync
  logic moves into a shared `SearchConsoleSynchronizer` service used by both
  the command and the page.

## [0.3.2] - Google Cloud setup documentation (2026-07-21)

### Documentation
- **README**: full step-by-step Google Cloud setup for the Search Console
  integration (project choice, enabling the Search Console API, consent
  screen and test users, OAuth web client and redirect URI, credentials,
  verified property) plus a troubleshooting table for the connection
  (redirect_uri_mismatch, access_denied, empty property list, revoked
  grant). Documentation-only release.

## [0.3.1] - Fix: self-scheduling on HTTP-triggered schedulers (2026-07-21)

### Fixed
- **Commands and schedules now register outside the console too.** Shared
  hosts often trigger the scheduler through an HTTP endpoint calling
  `Artisan::call('schedule:run')`; the previous `runningInConsole()` guard
  silently unregistered every package command and scheduled task in that
  setup, so `analytics:sweep`, `analytics:prune`, the monthly GeoIP refresh
  and the Search Console sync never ran. Schedules are now bound lazily
  (`callAfterResolving(Schedule::class)`), so ordinary HTTP requests still
  pay nothing.

## [0.3.0] - Google Search Console: real organic search queries (2026-07-20)

The words visitors actually type into Google, which no first-party tracker
can see, surfaced on the overview through the site's own Search Console.

### Added
- **Integrations screen** (`{name}.integrations`): the admin connects their
  Search Console through Google OAuth (read-only scope, anti-CSRF state,
  lightweight HTTP client — no google/apiclient), picks the verified
  **property** to attach, and can disconnect (modal-confirmed, token revoked
  best-effort). While the host provides no OAuth credentials
  (`ANALYTICS_GSC_CLIENT_ID` / `ANALYTICS_GSC_CLIENT_SECRET`) the whole
  feature stays hidden. Tokens are stored **encrypted**; a revoked access
  flags the connection and offers a reconnect.
- **Daily sync** (`analytics:search-console:sync`, self-scheduled 05:00,
  inert without an attached connection): paginated Search Analytics reads
  into the local `falcon_analytics_search_queries` cache — ~16-month backfill
  on first run, 3-day trailing re-read afterwards (GSC data settles late),
  upserted on date+query. The dashboard never calls the API at display time.
- **Overview section "Clics par recherches Google"**: top queries of the
  period by clicks with impressions, CTR and impressions-weighted average
  position, a freshness note ("Données Google jusqu'au …"), and a
  call-to-action card while no connection is attached. Distinct by nature
  from the campaign term (utm_term).
- Two migrations (`falcon_analytics_search_console`,
  `falcon_analytics_search_queries`), README section, 36 tests (HTTP mocked).

## [0.2.5] - Portability: exhaustive README & complete standalone shell (2026-07-20)

The package is now installable on any Laravel project from its README alone,
proven by a blank-project installation.

### Added
- **Exhaustive README**: installation (VCS repositories including the
  transitive ui-kit one, install command, design-system assets, Tailwind
  sources), identity & consent wiring, collector behaviour, full
  instrumentation reference, named events & conversions, server-sent events,
  funnels, marketing module, realtime, commands & self-scheduling,
  geolocation (MaxMind + any City MMDB + dev IP), privacy & GDPR, a complete
  configuration reference and the data model.

### Fixed
- **Standalone shell navigation**: the package layout's sidebar now lists
  every screen — the six analytics pages (Vue d'ensemble, Temps réel,
  Visiteurs, Sessions, Événements, Tunnels) and the marketing module (Vue
  d'ensemble, Campagnes, Pubs) in two labelled groups; it previously stopped
  at four links, hiding realtime, visitors and marketing from a host using
  the default shell.

### Validated
- **Blank install** on a fresh Laravel 13 project (sqlite): composer require
  from the VCS repositories, `analytics:install`, `ui-kit:install` + Tailwind
  `@source` + build, collector (pageviews, named click ingested), all nine
  dashboard/marketing screens rendering styled, declared events & funnel
  loading, `analytics:events:scan` / `analytics:events:check`, and the
  geolocation-less degradation ("not located" note on the realtime map).

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

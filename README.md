# Falcon Analytics

First-party, privacy-aware web analytics for Laravel admin spaces. Self-contained,
non-intrusive, and portable: install it, drop a script tag, set a few config
values, and you get deep behavioural analytics — page views, clicks, sessions,
visitor profiles, realtime, funnels, conversions and ad attribution — rendered in
an admin dashboard. All data stays in your own database. No third party, no
external service, no worker: everything runs wherever Laravel runs, shared
hosting included.

## Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Host integration](#host-integration)
   - [Identity](#1-identity)
   - [Collector script](#2-collector-script)
   - [Dashboard mounting & navigation](#3-dashboard-mounting--navigation)
   - [Styling (Tailwind sources)](#4-styling-tailwind-sources)
4. [Instrumentation (`data-track-*`)](#instrumentation-data-track-)
5. [Named events & conversions](#named-events--conversions)
6. [Server-sent events](#server-sent-events)
7. [Funnels](#funnels)
8. [Marketing module](#marketing-module)
9. [Realtime](#realtime)
10. [Google Search Console](#google-search-console)
11. [Commands & scheduling](#commands--scheduling)
12. [Geolocation](#geolocation)
13. [Privacy & GDPR](#privacy--gdpr)
14. [Configuration reference](#configuration-reference)
15. [Data model](#data-model)

## Requirements

- PHP >= 8.4
- Laravel 13
- Livewire 4 and `falcon/ui-kit` 2 are composer dependencies of the package and
  install automatically; the dashboard is styled by the ui-kit design system
  (see [Styling](#4-styling-tailwind-sources)).

## Installation

### 1. Composer

The package is distributed from a private git repository. Composer only reads
`repositories` from the **root** `composer.json`, so the host must declare the
package repository **and** the repository of its `falcon/ui-kit` dependency:

```jsonc
// composer.json of the host application
"repositories": [
    { "type": "vcs", "url": "https://github.com/MichaMegretDeveloppementWeb/falcon-analytics.git" },
    { "type": "vcs", "url": "https://github.com/MichaMegretDeveloppementWeb/falcon-ui-kit.git" }
]
```

```bash
composer require falcon/analytics
```

(For development inside a monorepo, a `path` repository with `"symlink": true`
pointing at the package directory works the same way.)

**Authenticating against the private repositories.** Both repositories are
private, so Composer needs a GitHub credential with read access to them —
otherwise `composer require` fails with "Could not find package". One token
covers both repos. Three setups:

- *Developer machine*: just run `composer require falcon/analytics` and follow
  the interactive prompt — Composer points you to
  `https://github.com/settings/tokens/new?scopes=repo`, you paste the token
  once, and it is stored machine-wide in `~/.composer/auth.json` (never in the
  project). Or set it up ahead of time:
  `composer config --global github-oauth.github.com ghp_YOUR_TOKEN`.
- *Server*: same global `composer config` command over SSH; a server whose
  system git already authenticates to GitHub via an SSH key also works
  (Composer falls back to a git clone).
- *CI*: expose the token as an environment variable at install time:
  `COMPOSER_AUTH='{"github-oauth":{"github.com":"<token>"}}'`.

**If the host already uses Livewire.** Livewire is a shared dependency, not a
bundled one: Composer resolves a single installation for the whole app and the
package plugs into it (same update endpoint, same Alpine runtime). A host on
Livewire 4 needs nothing; a host on Livewire 3 gets a hard Composer version
conflict — upgrade the host to Livewire 4 first, nothing breaks silently.

### 2. Install command

```bash
php artisan analytics:install
```

Composer already brought `falcon/ui-kit` (the dashboards are built on it), and
this command installs it too. **One command, not two.**

It asks four questions:

```
  Feuille de styles du back-office ?  [resources/css/app.css]
  Script du back-office ?             [resources/js/app.js]
  Feuille de styles du site public ?  [resources/css/app.css]
  Script du site public ?             [resources/js/app.js]
```

Accept the defaults on a fresh project. Two answers are not used yet — the
package has no back-office script and its collector has no stylesheet — but they
are remembered in `config/analytics.php`, so a later version asks nothing again.

It then installs the kit, publishes `config/analytics.php`, appends the
`ANALYTICS_*` variables to `.env` / `.env.example`, **writes its two imports**,
and runs the migrations (tables are prefixed `falcon_analytics_*`). Re-run with
`--force` to overwrite the published config.

Non-interactive:

```bash
php artisan analytics:install --admin-css=resources/css/admin.css \
                              --admin-js=resources/js/admin.js \
                              --web-css=resources/css/web.css \
                              --web-js=resources/js/web.js \
                              --no-interaction
```

The env scaffold is append-only and idempotent: only the variables missing from
each file are appended (grouped and commented), existing values are never
rewritten, and a file that does not exist is left untouched. The published
config stays the source of truth — every variable has a safe default.

### 3. What the command wrote

```css
/* resources/css/admin.css */
@import '../../vendor/falcon/analytics/resources/css/analytics-admin.css';
```

```js
/* resources/js/web.js */
import '../../vendor/falcon/analytics/resources/js/collector.js';
```

**The package compiles nothing.** `analytics-admin.css` declares its views as a
Tailwind source, and **your** build compiles them into the one stylesheet of
that space. Two Tailwind stylesheets on a page write the same class names, and
the last one loaded wins by position alone — which is why there is only ever
one.

**The collector goes into the public script, never the back-office one**: you do
not measure the visits of the person running the dashboard. It is
self-contained — no `import`, no npm dependency — and it exits on its own when
`window.__falconAnalytics` is missing, so you write no condition of your own.

Then declare your entries in `vite.config.js`, load them with `@vite`, and
build:

```bash
npm run build
```

### 4. Wire the host

Follow the [Host integration](#host-integration) checklist below. The short
version:

1. Set your guards (and consent cookie, if any) in the `identity` block of
   `config/analytics.php`.
2. If you use a consent cookie, exclude it from encryption
   (`bootstrap/app.php` → `encryptCookies(except: [...])`).
3. Behind a proxy or load balancer, configure `trustProxies` so the real
   client IP reaches the package (geolocation and exclusions depend on it).
4. Add `@analyticsConfig` to the public layouts you want to track.
5. Protect the dashboard with your admin middleware and link to it from your
   navigation (or rely on the package's standalone shell).
6. Ensure the standard scheduler cron (`php artisan schedule:run` every
   minute) is active: maintenance is self-scheduled by the package.
7. Optional: set up [geolocation](#geolocation) (local database, one command).

## Host integration

The package never touches your application code, routes or navigation. All
integration is declarative config plus one Blade directive.

### 1. Identity

The subject type is the guard name. Declare which authenticated users are
tracked as identified subjects, and which are internal staff to exclude
entirely:

```php
'identity' => [
    'subject_guards' => ['client', 'lessor'], // authenticated users tracked as subjects
    'exclude_guards' => ['admin'],            // internal staff, never stored
    'consent_cookie' => 'consent_marketing',  // cookie whose value "1" grants the persistent visitor id
    'subjects' => [
        // Display metadata per guard, resolved at render time only (never stored).
        'client' => ['label' => 'Client', 'name' => ['first_name', 'last_name']],
        'lessor' => ['label' => 'Loueur', 'name' => ['company_name'], 'fallback' => ['first_name', 'last_name']],
    ],
],
```

- **`subject_guards`** — when a user authenticated on one of these guards
  browses, their sessions are stitched to a subject (`type` = guard name,
  `id` = user id). One known person always resolves to **one visitor
  profile**: when a browser gets identified and the subject already owns a
  profile, the profiles merge automatically (see [Privacy](#privacy--gdpr)).
- **`exclude_guards`** — nothing is collected while such a user is
  authenticated: `@analyticsConfig` renders nothing on their pages, and the
  collector exits on its own for want of a configuration.
- **`consent_cookie`** — when set, a visitor only receives the *persistent*
  cross-visit id if the cookie holds `"1"`; otherwise tracking is
  session-scoped. `null` means the persistent id is always granted (use this
  when consent is handled at a different level, or not required).
- **`subjects`** — how identified visitors are displayed in the dashboard.
  For each guard: an optional `label` (defaults to the guard name) and a
  `name` column list concatenated into a display name, read from the guard's
  own model (derived from `config/auth.php`, or overridable with explicit
  `model` / `table` / `key` entries). `fallback` columns are used when the
  `name` columns are all empty.

For dynamic logic, register closures on the `Analytics` manager from a service
provider — they take precedence over the declarative config:

```php
use Falcon\Analytics\Facades\Analytics;

Analytics::resolveSubjectUsing(fn () => ...); // ['type' => string, 'id' => int] | null
Analytics::consentUsing(fn () => ...);        // bool
Analytics::excludeUsing(fn () => ...);        // bool
```

> **Host requirements.** A consent cookie must be excluded from encryption
> (`bootstrap/app.php` → `encryptCookies(except: [...])`) so the server can
> read it. Behind a proxy, configure `trustProxies` so the real client IP is
> used — otherwise every visitor shares the proxy's IP (breaks geolocation,
> `exclude_ips` and the realtime map).

Additional exclusions: `exclude_ips` accepts IPs and CIDR ranges (office
network, uptime monitors). Bot traffic is detected server-side
(device-detector) and excluded from every dashboard read.

### 2. Collector configuration

The collector's **code** lives in your public bundle, imported by
`analytics:install`. What still comes from the server is its **configuration**,
and that is what the directive carries. Add it to the layouts you want to track
(typically the public layout, before `</body>`):

```blade
@analyticsConfig
```

It renders one inline config object and nothing else:

```html
<script>window.__falconAnalytics={"endpoint":"/fa","route":"prestations",…};</script>
```

**This cannot be bundled**, which is why the directive still exists: the route
name changes on every page, and tracking is cut when an authenticated admin is
browsing. It is the same split Livewire makes between `@livewireScripts`, which
is code, and `@livewireScriptConfig`, which is data.

It renders **nothing at all** when tracking is disabled or the current context
is excluded, and degrades to an empty string on any internal failure — it can
never break a host page. **Rendering nothing means tracking off**: the collector
reads `window.__falconAnalytics` and exits on its own when it is missing, so you
write no condition of your own.

> Named `@analyticsScripts` until 2026-09-06, when it still carried the script
> tag. The script route was removed with it.

The collector then captures automatically, no code required:

- **Page views** on every load (URL, route name, referrer);
- **Clicks on genuinely interactive elements** (see
  [Instrumentation](#instrumentation-data-track-));
- **Heartbeats** while the tab is visible, which drive session activity,
  duration and the realtime screen;
- **Acquisition** (referrer domain, `utm_*` and ad URL parameters) and
  **device** (type, browser) per session.

Events are buffered client-side and flushed in batches (default every 5 s) to
the ingestion endpoint (`/__analytics` by default), which is rate-limited and
origin-checked. No cookie banner dependency: the collector always works, and
the consent setting only decides whether the visitor id persists across
visits.

> **Known limit of the origin check.** Beacons cannot carry a CSRF token, so
> the endpoint verifies the request origin instead: that stops forged
> cross-site requests from browsers, but a server-to-server client can spoof
> the header and inject events. The blast radius is bounded by the rate limit
> (`throttle`), the strict payload validation and the size caps — the standard
> trade-off of every first-party beacon endpoint.

### 3. Dashboard mounting & navigation

Two independent admin modules are registered, each mounted entirely from
config — URL prefix, route-name prefix, middleware and layout:

```php
'dashboard' => [
    'route_prefix' => 'admin/analytics',
    'route_name'   => 'analytics',
    'middleware'   => ['web', 'auth'],   // e.g. ['web', 'auth:admin'] for a dedicated guard
    'layout'       => null,              // null = package shell; or a host layout view name
],

'marketing' => [
    'route_prefix' => 'admin/marketing',
    'route_name'   => 'marketing',
    'middleware'   => ['web', 'auth'],
    'layout'       => null,
],
```

Package routes are registered outside your route groups, so the middleware
must include a session stack (`web`) alongside your auth guard.

The configured middleware (minus `web`, which Livewire always runs) is also
registered as **Livewire persistent middleware**: it is re-applied on every
component update (`/livewire/update`), so dashboard actions (GDPR erasure,
campaign/ad CRUD, Search Console disconnect) keep replaying your auth guard
after the page has loaded.

**Analytics pages** (`{name}` = `dashboard.route_name`, default `analytics`):

| Route name | Page |
|---|---|
| `{name}.overview` | Digest: KPIs, trend, acquisition, audience, localities, top events |
| `{name}.realtime` | Realtime: online now, world map, live activity (see [Realtime](#realtime)) |
| `{name}.visitors` | All-time visitor directory (+ `{name}.visitors.show` profile detail) |
| `{name}.sessions` | Session list (+ `{name}.sessions.show` full journey detail) |
| `{name}.events` | Named events: volumes, values, conversions |
| `{name}.funnels` | Funnels declared in code |
| `{name}.integrations` | Integrations (Google Search Console connection); shown only when [configured](#google-search-console) |

**Marketing pages** (`{name}` = `marketing.route_name`, default `marketing`):

| Route name | Page |
|---|---|
| `{name}.dashboard` | Marketing digest: spend-free performance of campaigns and ads |
| `{name}.campaigns` | Campaign list (+ `{name}.campaigns.show` detail) |
| `{name}.ads` | Ad list (+ `{name}.ads.show` detail) |

**Layout.** With `layout => null`, pages render into the package's standalone
shell: its own sidebar (both modules' links, built from the configured route
names), topbar, dark-mode toggle — a ready-made admin area. Set a host layout
view name (e.g. `layouts.admin`) to nest the pages inside your own chrome
instead; in that case add the links to your navigation yourself, e.g.:

```blade
<a href="{{ route('analytics.overview') }}">Vue d'ensemble</a>
<a href="{{ route('analytics.realtime') }}">Temps réel</a>
<a href="{{ route('analytics.visitors') }}">Visiteurs</a>
<a href="{{ route('analytics.sessions') }}">Sessions</a>
<a href="{{ route('analytics.events') }}">Événements</a>
<a href="{{ route('analytics.funnels') }}">Tunnels</a>
```

A host layout must load its own Vite entries (the ones `analytics:install` wrote
into), carry `{{ falcon_theme_class() }}` on `<html>` for dark mode, and render
`{{ $slot }}`.

### 4. Styling (Tailwind sources)

The dashboard views use ui-kit components and Tailwind utilities; they are
compiled by the **host's** Vite build. One line, written by `analytics:install`
into the stylesheet you named:

```css
@import '../../vendor/falcon/analytics/resources/css/analytics-admin.css';
```

That file declares our views, with a path resolved **from it** — you read them
without naming them, and without knowing where they live. We could add fifty
screens and your line would not change.

> It used to be a `@source` pointing straight into `vendor/`, which meant your
> stylesheet had to know our directory layout. Replaced by the entry point above
> on 2026-09-06.

After every package update, rebuild (`npm run build`) so new utility classes
used by new screens are compiled.

## Instrumentation (`data-track-*`)

Page views are captured on every load. **Clicks are only captured on genuinely
interactive elements**: a click on plain text or empty space carries no signal
and is never recorded. An element counts as interactive when it is:

- a native control: `<a>`, `<button>`, `<summary>`, or an actionable `<input>`
  (`submit` / `button` / `reset` / `image` / `checkbox` / `radio`);
- an ARIA widget: `role="button" | link | menuitem | menuitemcheckbox |
  menuitemradio | tab | option | switch`;
- made interactive by a handler: `wire:click`, `@click`, `x-on:click`, `onclick`.

If an element is interactive only through custom code and carries none of the
above in its markup, opt it in explicitly with `data-track-event`. On a
`<form>`, `data-track-event` is captured on **submit**, not on click.

Attributes enrich a captured click:

| Attribute | Effect |
|---|---|
| `data-track-event="domain.action"` | names the action (declared event / funnel join key) |
| `data-track-value="3"` | optional base value (overridden by the funnel step) |
| `data-track-prop-*="..."` | arbitrary props (`data-track-prop-listing-id` → `props.listing_id`) |
| `data-track-section="hero"` | logical zone applied to the subtree |
| `data-track-label="..."` | human label (otherwise the auto text) |
| `data-track-ignore` | excludes the element/subtree |

## Named events & conversions

Anonymous clicks are captured automatically; **named events** are the ones you
declare, and they power the events screen, the funnels and the marketing
objectives. Declare them in `app/Analytics/events.php` (path configurable via
`analytics.events_path`) — the single source of truth:

```php
use Falcon\Analytics\Events\TrackedEvent;

TrackedEvent::define('auth.client.register.submit', 'Inscription client (soumission)', value: 5.0);
TrackedEvent::define('listing.publish.submit', 'Publication d\'une annonce', value: 15.0);
TrackedEvent::define('review.submit', 'Avis déposé', conversion: true);
TrackedEvent::define('nav.catalog.click', 'Accès au catalogue');
```

- **`name`** — the technical key, as used by `data-track-event` or
  `Analytics::record()`. Convention: `domain.action`.
- **`label`** — what the dashboard displays.
- **`value`** — optional default monetary/score value attached to each hit.
- **`conversion`** — flags the event as a conversion (highlighted in the
  dashboards, counted in the realtime and marketing KPIs). When omitted,
  events carrying a value count as conversions.

Two commands keep the declarations honest:

- `php artisan analytics:events:scan` compares the file with the events
  actually used in the code (`data-track-event` attributes and
  `Analytics::record` calls, scanned under `analytics.events_scan_paths`);
  `--fix` appends the missing declarations.
- `php artisan analytics:events:check` verifies every funnel step references a
  declared event.

## Server-sent events

Beyond what the collector captures in the browser, application code can emit
events directly: same visitor/session, same storage, same funnels. Useful for
true conversions a click can't confirm (a registration was validated, a
payment succeeded):

```php
use Falcon\Analytics\Facades\Analytics;

Analytics::record('CompleteRegistration', value: 5.0, props: ['plan' => 'pro']);
```

The call is deferred (never blocks the response), a no-op when tracking is off
or the context is excluded (e.g. an admin), and never throws to the caller.

## Funnels

Declare funnels in `app/Analytics/funnels.php` (path configurable via
`analytics.funnels_path`). Each step matches a named event **xor** a pageview
route, and carries its own weight; the same event may belong to several
funnels with a different value in each:

```php
use Falcon\Analytics\Funnels\Funnel;

Funnel::define('acquisition_client', 'Acquisition client')
    ->step('Page inscription', value: 1, route: 'client.register')
    ->step('Soumission',       value: 5, event: 'auth.client.register.submit');
```

The funnels screen renders each funnel's per-step volumes, conversion rates
between steps and total value over the selected period.

**Parallel branches.** A milestone is often reachable more than one way. Since
progression is sequential, laying the alternatives out as consecutive steps
would read "went through one, *then* the other" and report zeros. Declare them
at the same depth instead:

```php
use Falcon\Analytics\Funnels\Funnel;
use Falcon\Analytics\Funnels\FunnelBranch;

Funnel::define('acquisition', 'Acquisition')
    ->step('Offre consultee', value: 2, event: 'offer.viewed')
    ->step('Formulaire ouvert', value: 8, anyOf: [
        FunnelBranch::event('Questionnaire', 'quiz.opened'),
        FunnelBranch::route('Contact', 'contact'),
    ])
    ->step('Demande envoyee', value: 100, event: 'lead.created');
```

A visitor advances once, whichever branch they take, and the step reports how
many came through each -- including the branches nobody took, since a zero is
itself a reading. A step accepts exactly one of `event`, `route` or `anyOf`, and
`anyOf` needs at least two branches.

## Marketing module

A separate top-level module measuring ad performance **without any ad-platform
API**: campaigns and ads are defined from the marketing screens (stored in
your database), and matched to sessions by the URL parameters the ad's links
carry.

- **Campaigns and ads** are created in the UI. Each carries free
  URL-parameter conditions (e.g. `utm_source=facebook` +
  `utm_campaign=summer`); a session whose landing parameters satisfy them is
  attributed to the ad, at report time.
- **Objectives** are declared per ad by picking among the declared events
  (see [Named events](#named-events--conversions)); the module reports
  reach, conversions and value per ad and per campaign.
- **Attribution is first-touch and retroactive**: the visitor's first
  ad-attributed session marks the acquisition, and later conversions by the
  same visitor credit that ad, even across visits.

## Realtime

`{name}.realtime` shows who is online now: KPIs of the recent window, a world
map of connections (embedded SVG — no tile server, no mapping library, no
external request), country/source/device breakdowns, pages being viewed, a
per-minute pulse chart, recent visitors and a live activity feed.

- **Refresh** is plain Livewire polling (`wire:poll.visible`, 10 s default),
  suspended while the tab is hidden. No websocket, no worker, no external
  service: it works on any host by construction. The page is admin-only, so
  the polling load is marginal (every tick is a handful of bounded, indexed
  queries).
- **"Online now"** counts distinct visitors active within
  `realtime.online_seconds` (default 60 s, i.e. three collector heartbeats).
- The map needs [geolocation](#geolocation) to place points; without a
  database, sessions count as "not located".

```php
'realtime' => [
    'poll_seconds' => 10,     // page refresh interval
    'online_seconds' => 60,   // "online now" activity window
    'window_minutes' => 30,   // the "recent" window of every block
    'feed_limit' => 25,       // hard bound of the activity feed
],
```

## Google Search Console

Google strips the search query from referrers, so organic keywords never reach
a first-party tracker. The optional Search Console integration displays them
anyway — "Clics par recherches Google" on the overview — by letting the admin
connect the site's own Search Console through OAuth (read-only).

**Host setup — Google Cloud, step by step** (the feature stays entirely
hidden until this is done). All of this happens on
<https://console.cloud.google.com>, and **the Google account you use
matters**: the project belongs to that account, and only that account can
manage it later. Use the account that administers the site.

1. **Project** — pick (or create via *IAM & Admin → Create a project*) the
   Google Cloud project that will own the OAuth client. If the site already
   uses Google sign-in (Socialite), reuse that same project: one project can
   hold several OAuth clients. Make sure the project shown in the console's
   top bar is the right one before every step below.
2. **Enable the API** — *APIs & Services → Library*, search "Google Search
   Console API" (direct link:
   <https://console.cloud.google.com/apis/library/searchconsole.googleapis.com>),
   click **Enable** *on that project*. Do not skip this: the OAuth consent
   works without it, but every data call is then rejected (the integrations
   screen shows "the property list could not be loaded").
3. **Consent screen** — *APIs & Services → OAuth consent screen*: user type
   *External* is fine. While the app's publishing status is *Testing*, only
   accounts listed under **Test users** can authorise: add the Google account
   that will connect the Search Console. (Publishing the app is not required
   for this single-admin integration.)
4. **OAuth client** — *APIs & Services → Credentials → Create credentials →
   OAuth client ID*, type **Web application**. Under *Authorized redirect
   URIs*, add the package callback **exactly** as shown on the integrations
   screen:
   `https://your-host/{dashboard.route_prefix}/integrations/search-console/callback`.
   Google only accepts public domains (plus `localhost`); a local `.test`
   domain will be refused, so the OAuth round-trip is validated on a deployed
   environment.
5. **Credentials** — copy the client ID and secret into the host `.env`:

```dotenv
ANALYTICS_GSC_CLIENT_ID=xxx.apps.googleusercontent.com
ANALYTICS_GSC_CLIENT_SECRET=xxx
```

   (then `php artisan config:cache` if the host caches its config).
6. **Search Console side** — the Google account that will authorise must own
   the site as a **verified property** in
   <https://search.google.com/search-console> (any verification method).
   Unverified properties are not offered by the package.

**Admin flow**: on `{name}.integrations`, "Connecter Google Search Console"
starts the OAuth consent (scope `webmasters.readonly`, offline access); back
from Google, the admin picks the verified **property** to attach.
Disconnecting (confirmed by modal) revokes the token and deletes the
connection.

**Troubleshooting the connection**:

| Symptom | Cause and fix |
|---|---|
| Google shows `redirect_uri_mismatch` | The URI registered on the OAuth client differs from the one the package sends. Copy it verbatim from the integrations screen. |
| Google shows `access_denied` / "app not verified" | The consent screen is in *Testing* and the connecting account is not among the **Test users** (step 3). |
| Connected, but "the property list could not be loaded" | The Search Console API is not enabled **on the project owning the client** (step 2). Enable it, then hit "Réessayer" — no reconnection needed. |
| Property list is empty | The authorising account owns no verified Search Console property (step 6). |
| Card shows "Erreur" later on | Google revoked or expired the grant (password change, permission removal). "Reconnecter" runs the consent again; cached data stays. |

**Sync**: `analytics:search-console:sync` runs daily (self-scheduled), and
the integrations screen offers the same sync on demand ("Synchroniser
maintenant" — inline, no worker required; the initial backfill may take a
moment). GSC data trails reality by ~3 days and the API is quota-limited, so
the dashboard only ever reads the local cache
(`falcon_analytics_search_queries`): the first run backfills the API's
~16-month history in paginated calls, then each run re-reads the trailing
days. The refresh token is stored **encrypted**; a revoked access flags the
connection on the integrations screen and on the overview card.

**Display**: the overview section lists the period's top queries by clicks,
with impressions, CTR and impressions-weighted average position, plus a
"Données Google jusqu'au …" freshness note. Not to be confused with the
campaign term (`utm_term`): these are the words actually typed into Google.

## Commands & scheduling

| Command | Role |
|---|---|
| `analytics:install` | install the kit, publish config, write the two imports, scaffold env variables, run migrations |
| `analytics:geoip:download` | download/refresh the local GeoLite2 City database |
| `analytics:geoip:check` | say whether the database is usable, and why an address does or does not resolve |
| `analytics:sweep` | stamp `ended_at` on sessions idle past the timeout |
| `analytics:prune` | delete raw events older than `retention_days` |
| `analytics:events:scan` | diff declared events vs events used in code (`--fix` appends) |
| `analytics:events:check` | verify funnel steps reference declared events |
| `analytics:search-console:sync` | pull the organic queries into the local cache |

`sweep` (every 5 min), `prune` (daily, 03:30), the Search Console sync
(daily, 05:00, inert without an attached connection) and the monthly GeoLite2
refresh (inert until a licence key is set) are **self-scheduled** by the
package: the host only needs to trigger Laravel's standard scheduler every
minute, no dedicated analytics cron. Both trigger styles work: a real cron
(`* * * * * php artisan schedule:run`) or, on shared hosting, an HTTP
endpoint calling `Artisan::call('schedule:run')`.

## Geolocation

Localities are resolved from the visitor IP against a **local MMDB database**,
so an IP never leaves the server (no third-party call at request time).

**MaxMind GeoLite2 City** (free, integrated download):

1. Create a free account and licence key:
   <https://www.maxmind.com/en/geolite2/signup>
2. Add the key to `.env`: `ANALYTICS_GEOIP_LICENSE_KEY=xxxxxxxx`
3. Download the database: `php artisan analytics:geoip:download`

The `.mmdb` lands at `storage/app/analytics/GeoLite2-City.mmdb` and is
refreshed monthly by the self-scheduled command.

**When localities stay empty, ask why.** Geolocation degrades to "unknown" whatever
the cause -- no database, a truncated one, or a private address -- so the screen
alone cannot tell you which to fix. `php artisan analytics:geoip:check` reports the
database path, size and date, the configured development address, and resolves a
probe address, naming the state. Pass an address to test that one:
`php artisan analytics:geoip:check 92.222.0.1`. The Sessions and Visitors screens
carry the same notice when there is something to do about it.


**Any other City-level MMDB works** (e.g. [DB-IP City
Lite](https://db-ip.com/db/download/ip-to-city-lite), no account required):
download the `.mmdb` yourself and point `ANALYTICS_GEOIP_DATABASE` at its
absolute path.

**Local development**: private/loopback IPs (`127.0.0.1`) can never be
located. Set `ANALYTICS_GEOIP_DEV_IP` to any public IP to substitute it for
private/reserved request IPs — inert in production by design, since real
public IPs are never overridden. Pick one that resolves to a city rather than
just a country, or the column will only ever show a flag; `analytics:geoip:check
<ip>` tells you what a candidate resolves to before you commit to it.

IP geolocation is inherently city/region level; it will not pinpoint an exact
street.

## Privacy & GDPR

- **First-party only.** Everything (collection, storage, dashboards,
  geolocation) happens on your own infrastructure; no data ever leaves it.
- **Consent-aware.** With `identity.consent_cookie` set, the persistent
  cross-visit id is only granted when the visitor consented; everything else
  stays session-scoped.
- **IP anonymisation** — set `privacy.anonymize_ip = true` to store a
  truncated IP instead of the raw one (locality is resolved before
  truncation).
- **URL redaction** — query parameters in `privacy.redact_query_params`
  (tokens, secrets, emails…) are stripped from stored URLs; tracking
  parameters (`utm_*`, ad ids) are kept.
- **Retention** — raw events are pruned past `retention_days` (default 90) by
  the self-scheduled `analytics:prune`; sessions and visitor profiles are
  kept.
- **Right to erasure** — from the visitor detail page, an admin can erase a
  visitor: profile, merged aliases, sessions and events are deleted in one
  action.
- **One person, one profile** — when an identified subject is recognised in a
  second browser/device, the profiles merge (oldest survives, the other
  becomes an alias routing to it); a login on a shared browser lands on the
  logged-in person's own profile without stealing the browser's owner.
- **Staff exclusion** — `exclude_guards` and `exclude_ips` keep internal
  traffic out entirely; bots are filtered from every report.

## Configuration reference

All keys live in `config/analytics.php`; env-driven values in parentheses.

| Key | Default | Role |
|---|---|---|
| `enabled` (`ANALYTICS_ENABLED`) | `true` | master switch: no ingestion, no collector when off |
| `log_channel` | `null` | package log channel (`null` = app default) |
| `funnels_path` | `null` → `app/Analytics/funnels.php` | code-declared funnels file |
| `events_path` | `null` → `app/Analytics/events.php` | declared events file |
| `events_scan_paths` | `['app', 'resources/views']` | paths scanned by `analytics:events:scan` |
| `endpoint` | `__analytics` | ingestion path (`/__analytics`) |
| `assets.admin_css` · `assets.web_js` | `resources/css/app.css` · `resources/js/app.js` | your entries, where `analytics:install` wrote its two imports |
| `throttle` | `120,1` | ingestion rate limit (requests, minutes) |
| `exclude_ips` | `[]` | IPs/CIDRs excluded from tracking |
| `identity.subject_guards` | `['web']` | guards tracked as identified subjects |
| `identity.exclude_guards` | `[]` | guards excluded entirely |
| `identity.consent_cookie` | `null` | cookie gating the persistent visitor id |
| `identity.subjects` | `[]` | display metadata per guard (label, name columns) |
| `dashboard.route_prefix` | `admin/analytics` | dashboard URL prefix |
| `dashboard.route_name` | `analytics` | dashboard route-name prefix |
| `dashboard.middleware` | `['web', 'auth']` | dashboard protection |
| `dashboard.layout` | `null` | `null` = package shell, or host layout view |
| `marketing.*` | `admin/marketing` / `marketing` / … | same four keys for the marketing module |
| `retention_days` | `90` | raw-event retention |
| `session.timeout_minutes` | `5` | inactivity after which a session is ended |
| `session.heartbeat_seconds` | `20` | collector heartbeat interval (tab visible) |
| `session.flush_seconds` | `5` | collector batch flush interval |
| `realtime.poll_seconds` | `10` | realtime page refresh |
| `realtime.online_seconds` | `60` | "online now" window |
| `realtime.window_minutes` | `30` | realtime recent window |
| `realtime.feed_limit` | `25` | activity feed bound |
| `search_console.client_id` (`ANALYTICS_GSC_CLIENT_ID`) | `''` | Google OAuth client id (empty = feature hidden) |
| `search_console.client_secret` (`ANALYTICS_GSC_CLIENT_SECRET`) | `''` | Google OAuth client secret |
| `search_console.redirect` (`ANALYTICS_GSC_REDIRECT`) | `null` | redirect URI override (`null` = package callback route) |
| `privacy.anonymize_ip` | `false` | store truncated IPs |
| `privacy.redact_query_params` | tokens/secrets/email | query params stripped from stored URLs |
| `geoip.license_key` (`ANALYTICS_GEOIP_LICENSE_KEY`) | `''` | MaxMind licence key |
| `geoip.edition` (`ANALYTICS_GEOIP_EDITION`) | `GeoLite2-City` | MaxMind edition |
| `geoip.database_path` (`ANALYTICS_GEOIP_DATABASE`) | `storage/app/analytics/GeoLite2-City.mmdb` | MMDB location |
| `geoip.dev_ip` (`ANALYTICS_GEOIP_DEV_IP`) | `null` | public IP substituted for private/reserved IPs (dev) |
| `geoip.download_url` | MaxMind permalink | download URL template (`{edition}` and `{license_key}` substituted) |

## Data model

Eight tables, all prefixed `falcon_analytics_`:

| Table | Content |
|---|---|
| `falcon_analytics_visitors` | one row per browser/person (uuid, subject stitching, merge aliases) |
| `falcon_analytics_sessions` | one row per visit (activity, device, acquisition, locality, marketing params) |
| `falcon_analytics_events` | raw events (pageviews, clicks, named events), pruned past retention |
| `falcon_analytics_campaigns` | marketing campaigns (UI-defined) |
| `falcon_analytics_ads` | ads and their URL-parameter conditions |
| `falcon_analytics_ad_objectives` | events picked as objectives per ad |
| `falcon_analytics_search_console` | the Search Console connection (encrypted OAuth tokens, property, status) |
| `falcon_analytics_search_queries` | cached organic queries per day (clicks, impressions, position) |

Migrations load from the package (no publishing needed); every dashboard read
goes through bounded, indexed queries so the screens stay fast on large
datasets.

## License

Proprietary.

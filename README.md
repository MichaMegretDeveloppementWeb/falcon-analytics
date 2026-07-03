# Falcon Analytics

First-party, privacy-aware web analytics for Laravel admin spaces. Self-contained,
non-intrusive, and portable: install it, drop a script tag, wire a few callbacks,
and you get deep behavioural analytics (page views, clicks, sessions, funnels)
rendered in an admin dashboard. All data stays in your own database. No third party.

> The collection layer (JS collector + ingestion + storage) is framework-agnostic.
> The dashboard is built with Livewire + Blade; on a non-Livewire host you only need
> `composer require livewire/livewire` for those screens.

## Requirements

- PHP >= 8.4
- Laravel 13

## Installation

Add the repository and require the package.

```jsonc
// composer.json (during development, from this monorepo)
"repositories": [
    { "type": "path", "url": "packages/falcon/analytics", "options": { "symlink": true } }
]
```

```bash
composer require falcon/analytics
php artisan analytics:install
```

`analytics:install` publishes `config/analytics.php` and runs the migrations
(tables are prefixed `falcon_analytics_*`).

For consumption from another project, push this package to its own git repository
and require it through a `vcs` repository entry (same model as `falcon/ui-kit`).

## Host integration

The package never touches your application code. Installation is minimal: a few
config values plus the `@analyticsScripts` directive.

### 1. Identity

Publish the config and set your guards and consent cookie in `config/analytics.php`
(the defaults suit a standard Laravel app). The subject type is the guard name:

```php
'identity' => [
    'subject_guards' => ['client', 'lessor'], // authenticated users tracked as subjects
    'exclude_guards' => ['admin'],            // internal staff, never stored
    'consent_cookie' => 'consent_marketing',  // cookie whose "1" grants the persistent id
],
```

For dynamic logic, register closures on the `Analytics` manager from a service
provider (they take precedence):

```php
use Falcon\Analytics\Facades\Analytics;

Analytics::resolveSubjectUsing(fn () => ...); // ['type' => string, 'id' => int] | null
Analytics::consentUsing(fn () => ...);        // bool
Analytics::excludeUsing(fn () => ...);        // bool
```

> **Host requirements.** The consent cookie must be excluded from encryption
> (`bootstrap/app.php` → `encryptCookies(except: [...])`) so the server can read it;
> behind a proxy, configure `TrustProxies` so the real client IP is used.

### 2. Collector script

Add the directive to the layouts you want to track:

```blade
@analyticsScripts
```

### 3. Dashboard

The dashboard is a **separate**, admin-only area with its own shell, styled with
the shared `falcon/ui-kit` design system. It mounts entirely from config, so it
fits any host:

```php
'dashboard' => [
    'route_prefix' => 'admin/analytics', // URL prefix (/admin/analytics)
    'route_name'   => 'analytics',       // route('analytics.overview'), route('analytics.sessions')
    'middleware'   => ['web', 'auth'],   // protect it; keep 'web' for the session stack
    'layout'       => null,              // null = the package shell; or a host layout name
],
```

Package routes are registered outside your route groups, so the middleware must
include a session stack (`web`) alongside your auth guard, for example
`['web', 'auth:admin']`.

**Dashboard pages.** Four full-page Livewire routes are registered under the
prefix. The package does not touch your navigation; add the links yourself:

| Route name | Page |
|------------|------|
| `{name}.overview`  | Digest: KPIs, trend, sources, localities, engagement |
| `{name}.visitors`  | Visitor list (sessions, first/last seen, locality, acquisition) |
| `{name}.sessions`  | Session list + `{name}.sessions.show` detail (journey) |
| `{name}.funnels`   | Funnels declared in `app/Analytics/funnels.php` |

`{name}` is `dashboard.route_name` (default `analytics`). Link to them from your
own navigation, e.g.:

```blade
<a href="{{ route('analytics.overview') }}">Vue d'ensemble</a>
<a href="{{ route('analytics.visitors') }}">Visiteurs</a>
<a href="{{ route('analytics.sessions') }}">Sessions</a>
<a href="{{ route('analytics.funnels') }}">Entonnoirs</a>
```

**Tailwind sources.** So the kit classes used by the dashboard are not purged,
add the package views to your Tailwind sources, next to the ui-kit `@source`
line in your kit CSS entrypoint:

```css
@source '../../vendor/falcon/analytics/resources/views/**/*.blade.php';
```

(During local development with a symlinked path repository, point `@source` at
`../../packages/falcon/analytics/resources/views/**/*.blade.php` instead.)

### 4. Funnels

Declare funnels in code (in `app/Analytics/funnels.php`, path configurable via
`analytics.funnels_path`). Each step matches a named event XOR a pageview route,
and carries its own weight; the same event may belong to several funnels with a
different value in each.

```php
use Falcon\Analytics\Funnels\Funnel;

Funnel::define('acquisition_client', 'Acquisition client')
    ->step('Page inscription', value: 1, route: 'client.register')
    ->step('Soumission',       value: 5, event: 'auth.client.register.submit');
```

### 5. Server-sent events

Beyond what the collector captures in the browser, application code can emit
events directly: same visitor/session, same storage, same funnels. Useful for
true conversions a click can't confirm (a registration was validated, a payment
succeeded). The event joins a funnel by its name, like any other.

```php
use Falcon\Analytics\Facades\Analytics;

Analytics::record('CompleteRegistration', value: 5.0, props: ['plan' => 'pro']);
```

The call is deferred (never blocks the response), a no-op when tracking is off or
the context is excluded (e.g. an admin), and never throws to the caller.

## Instrumentation (`data-track-*`)

All page views and clicks are captured automatically. Attributes enrich them:

| Attribute | Effect |
|---|---|
| `data-track-event="domain.action"` | names an action (funnel join key) |
| `data-track-value="3"` | optional base value (overridden by the funnel step) |
| `data-track-prop-*="..."` | arbitrary props (`data-track-prop-listing-id` -> `props.listing_id`) |
| `data-track-section="hero"` | logical zone applied to the subtree |
| `data-track-label="..."` | human label (otherwise the auto text) |
| `data-track-ignore` | excludes the element/subtree |

## Publishing / overriding

```bash
php artisan vendor:publish --tag=analytics-config
```

Further publish groups (views, funnels, assets) are documented as they ship.

## Commands

| Command | Role |
|---|---|
| `analytics:install` | publish config + run migrations |
| `analytics:rollup` | build aggregates from raw events (hourly cron or lazy) |
| `analytics:prune` | drop raw events past retention |
| `analytics:sweep` | close stale sessions deterministically |
| `analytics:events` | list observed event names and where they fire |
| `analytics:funnels` | validate funnel definitions against observed events |
| `analytics:forget` | erase a visitor's or subject's data |

## License

Proprietary.

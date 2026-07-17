# Changelog

Notable milestones of `falcon/analytics`. Versions are git tags; earlier per-tier
patch tags (v0.1.x) hold the individual steps.

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

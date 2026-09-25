# Claude instructions for `block_dimensions`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. It captures the **Moodle development standards** this plugin
follows so future edits stay in the same style and pass CI on the first try.
The conventions are shared with the sibling plugin `local_dimensions` (its own
`CLAUDE.md` is at `~/dev/moodle-local_dimensions/`); this file keeps only what
is true here — including the handful of places where the two plugins are
forced to differ and the difference must not be "tidied away": the Bootstrap
polyfill's attachment point and the Behat step wording, both below.

Plugin context: a Moodle **block** plugin ("Dimensions") that renders a
learner-facing dashboard of **learning plan cards** and **competency cards**
with client-side filters, search and favourites. It is a thin presentation
layer over the sibling plugin `local_dimensions`: all card metadata (images,
tags, colours, trail data) comes from that plugin's MUC caches
(`competency_metadata_cache`, `template_metadata_cache`, `plan_trail_cache`).
It defines **no database tables of its own** (`db/` has no `install.xml`); the
only persistent data it produces is **favourites in the core `favourite`
table** under component `block_dimensions`. Depends on `tool_lp` (core) and
`local_dimensions` (`version.php` `$plugin->dependencies`). Supports Moodle
**4.5 through 5.2** (`$plugin->requires = 2024100702`,
`$plugin->supported = [405, 502]`). Development happens on Moodle 5.1.

## Agent orchestration budget (fleet rule, repeated here on purpose)

This is section 6 of `~/dev/CLAUDE.md`, mirrored into every repo of the fleet.
It is the one fleet rule these files are allowed to duplicate: a session opened
inside a plugin directory does not always carry the fleet file in context, and
the cost of missing this rule is paid immediately, in tokens, before anyone
notices it was missing.

**Every `Agent` call and every `agent()` inside a Workflow sets `model`
explicitly.** An omitted `model` runs that subagent on the session model — the
most expensive one — and is a defect, not a default:

- `sonnet` — readers, graders, refuters, verifiers, measurers, stale-reference
  sweeps, mechanical renames, test files written against a stated contract.
- `opus` — implementers of non-trivial code, ADR and documentation drafters,
  consolidators, critics, estimators. The alias means the **newest Opus**: since
  2026-09-22 that is Claude Opus 5.5 (`claude-opus-5-5`), measured by asking a
  subagent launched with `model: 'opus'` which model it runs on. Never pin
  `claude-opus-5` or any older Opus id. The `Agent` tool accepts aliases only
  (`sonnet`, `opus`, `haiku`, `fable`); `agent()` in a Workflow accepts an explicit
  id as well, but the alias is what to write — it follows the newest Opus without
  an edit here.
- the session model — only for work done inline in the main loop, never for a
  subagent.
- `effort` is set beside `model` on every call, never inherited: `high` for
  verifiers, readers and refuters, `xhigh` for implementers and fixers (the
  owner's rule of 2026-09-17). An omitted effort inherits the session's, and on
  Opus 5.5 an explicit one matters twice over — that model's own default is
  `medium`, one level below Opus 5.

Multi-agent workflows stay opt-in and lean whatever mode is on: size the fan-out
to the question (roughly 10 to 25 agents), one refuter per finding and only for
blocking findings, no open-ended "investigate every gap" rounds. Stop and resume
with `resumeFromRunId` rather than relaunching, so completed agents stay cached.
State which model each role got when reporting a launch.

Measured 2026-09-02 on the hub category-context gap analysis: 7 lenses x 2
refuters x 2 measurers plus a critic round, every one of them on the session
model, had to be interrupted for cost — 36 agents with the refuters on Sonnet
produced the same verified result. The rule has been restated three times
(2026-09-01, 2026-09-02, 2026-09-04), the last time over implementers launched
without `model` while the reviewers around them were correctly downgraded.

## Commands

The plugin repo is `~/dev/moodle-block_dimensions` (`uaiblaine/moodle-block_dimensions`,
branch `main`) and is **bind-mounted** into every stack its `$plugin->supported`
range admits (m405, m501, m502) per the fleet manifest
`~/dev/moodle-dev/plugins.conf`. One edit is live in every Moodle version at
once — there is no clone inside a Moodle checkout any more, and nothing to
rsync. Run git from this directory (or `git -C`), and `git fetch && git pull`
before starting so you do not build on a stale base.

`local_dimensions` is a hard dependency in `version.php`, so it is mounted
alongside on every stack and checked out by every CI job. That matters beyond
installation — see the Behat step-wording rule below.

### Gates run locally now — this supersedes the old "GitHub is the gate" note

Earlier revisions of this file said phpcs / phpdoc / PHPUnit / Behat /
mustache-lint had no local runner. **That is no longer true**, and the fleet's
`mdl` CLI (`~/dev/moodle-dev/bin`, on PATH in interactive shells; use the
absolute path from an agent's Bash) runs the same legs GitHub does:

```sh
mdl ci moodle-block_dimensions --only phpcs,phpdoc,mustache,grunt   # static pass, one leg
mdl ci moodle-block_dimensions --only leftover                      # the docs-safe gate
mdl ci moodle-block_dimensions --matrix --behat                     # every leg GitHub runs
mdl phpunit m502 block_dimensions
mdl behat m502 /var/www/html/public/blocks/dimensions/tests/behat/colour_mode.feature
```

`mdl ci` with no flags is **one leg**, not the pipeline — it defaults to
`MOODLE_501_STABLE` / PHP 8.3 / pgsql. Use `--matrix` before pushing. A
`version.php` bump stales the stacks' test sites, so `mdl phpunit-init <stack>`
/ `mdl behat-init <stack>` first.

### CI pipeline (`.github/workflows/ci.yml`)

The **moodle-an-hochschulen/moodle-workflows** reusable workflow, called once
per supported Moodle branch (5.02 full PHP × DB matrix; 5.01/5.00/4.05
one-DB-only). Each call cross-installs `uaiblaine/moodle-local_dimensions,main`
as a plugin dependency — keep that in every job, and **update the calls when
`$plugin->supported` changes**. The `push:` trigger is **filtered to `main` and
`MOODLE_*_STABLE`** and a `concurrency` block supersedes a superseded run on a
pull request but **never on `main`**; both are deliberate — a bare `push:` ran
the whole pipeline twice for one commit, since `pull_request` fires on it too.
Gates: `phplint`, `phpmd` (informational),
`phpcs --max-warnings 0` (**warnings fail**), `phpdoc --max-warnings 0`, a
development-leftover checker that fails on stray to-do / merge-conflict
markers in **any** file (docs included — never write those tokens literally),
`validate` (lang string ordering!), `savepoints`, `mustache`,
`grunt --max-lint-warnings 0` (incl. eslint + stylelint), then PHPUnit
(`--fail-on-warning`) and Behat on every runtime leg.

### Building JavaScript assets (required before committing JS)

`mdl grunt m502 blocks/dimensions` rebuilds `amd/build/*.min.js` + `.map` in a
node container pinned to the version Moodle expects; never hand-edit minified
output. `amd/build/**` is **tracked in git** — Moodle serves the compiled
output. Every `amd/src` edit must ship its rebuilt `.min.js` + `.map` in the
same commit, plus a `version.php` bump so the cache revision changes.
**`cachejs = false` on the stacks does not serve `amd/src`.** Moodle always
loads `amd/build/*.min.js` and reads `amd/src` only when the `.map` beside it is
missing, so an `amd/src` edit reaches the page only after
`mdl grunt m502 blocks/dimensions`. A JS mutation test that skips the rebuild
silently tests the old build.

**Run `tests/jsharness/run.sh --mutants` before committing any `amd/src` change.**
It is a headless-Chromium suite over the real modules (209 checks: web service
calls stay pending until a scenario settles them, so it reaches orderings Behat
cannot - a failed switch under a typed search, a retry over a late response),
and the mutant mode re-breaks every fix it pins (67 mutants) and fails if any
survives or no longer applies. A `NOT APPLIED` line means a guarded pattern moved
and its mutant must be ported in `tests/jsharness/mutants.py`. It reads `amd/src`
directly, so it needs no grunt run and says nothing about a stale `amd/build`;
needs only Docker; export-ignored and not run by CI. `tests/jsharness/README.md`
explains how to add a check and its mutant.

**Do not add a `.stylelintrc.json` back.** The repo used to carry one, and it
was deleted on 2026-09-05 because it had **no `extends`** — stylelint replaces
its config rather than merging, so a bare `{"rules": {...}}` file was silently
*substituting* one rule for Moodle's ~90-rule config on every local and CI run.
It was hiding **46 errors and 3 warnings**, including the `!important`
declarations, the inline SVG data URIs and the `container-type` / `@container`
layer that all had to go when it was removed. If a rule genuinely needs
relaxing, extend Moodle's config; do not shadow it.

## Code layout

```
block_dimensions.php         Block class — content renders only for logged-in
                             non-guests holding a plan the block can show
                             (summary::has_content()), gated on
                             get_config('core_competency', 'enabled');
                             can_block_be_added() enforces the same
settings.php                 Admin settings (visibility, filters, favourites,
                             plan-card layout)
version.php                  component / version / requires / supported / dependencies
classes/
  local/dataset_provider.php Builds the whole dataset: plan cards, competency
                             cards, trail windowing, favourites, UI config.
                             Owns sanitize_color()/sanitize_image_url()
  local/bootstrap.php        Bootstrap major-version marker for the block's own
                             root element — gates the BS4 polyfill in styles.css
  local/colour_mode.php      Constants only: the attribute names in the family's
                             dark-mode activation contract. No is_dark() helper,
                             deliberately — see below
  output/summary.php         Renderable shell — ships labels + config JSON only
                             (incl. `isbs4` for the polyfill gate)
  output/renderer.php        render_from_template wrapper
  external/                  get_block_dataset, toggle_favourite,
                             set_return_context (one class each)
  privacy/provider.php       Exports/deletes core_favourites rows
db/                          access, services, uninstall  (NO install.xml)
styles.css                   34 colour tokens on body, one body-anchored
                             [data-bs-theme="dark"] activation rule, one
                             inert prefers-color-scheme block, and a Bootstrap 4
                             utility polyfill at the tail
templates/                   summary (server-rendered shell), plan_card,
                             competency_card (client-rendered). The filter bar
                             has no template: renderFilterControls() in
                             filters.js is its only definition
amd/src/                     filters.js (fetch + render), state.js (pure state
                             fns), filter_tabs_nav.js — plain AMD, NOT ESM here
amd/build/                   Committed minified output (grunt) — keep in sync
lang/{en,pt_br}/             Both kept in sync, alphabetically sorted
tests/                       PHPUnit: dataset_provider (double pattern),
                             external functions, privacy, generator,
                             local/colour_tokens_test, local/bootstrap_compat_test,
                             local/card_layout_test
tests/behat/                 colour_mode, visibility, status_filter and card_filters features
tests/jsharness/             JS regression harness for amd/src (headless Chromium,
                             run.sh; --mutants); export-ignored, no CI step
tests/coverage.php           adds block_dimensions.php and db/uninstall.php to the
                             coverage measurement (core's default list skips them)
                             + behat_block_dimensions.php
docs/block-kit/              As-is visual replica of the block (excluded from
                             the release zip via .gitattributes)
docs/proposals/              To-be designs and the decisions behind them, one
                             dated folder each; block-kit stays as-is and must
                             not describe a proposal until it ships
```

## Architecture gotchas

### Client-side rendering, server-side shell
`summary.mustache` is a shell: `summary::export_for_template()` only exports
`labelsjson` / `filtersettingsjson` / flags. `amd/src/filters.js` calls the WS
`block_dimensions_get_block_dataset` and renders `plan_card` /
`competency_card` mustache via `core/templates`. Two-phase loading:
`favouritesonly` first, then `loadgroup` (`plan` / `competency`) fetches the
missing group. **Don't add server-side card building back into `summary.php`**
— an earlier refactor removed exactly that dead path.

### No plan the block can show, no block — like block_lp

`get_content()` returns empty content unless `summary::has_content()` is true,
which is `dataset_provider::has_displayable_plans()`: the same plan list the web
service builds its dataset from, so the gate and the dataset cannot disagree.
Core then drops the block from the page (`block_base::get_content_for_output()`
returns null for an empty block), except in editing mode, where the block keeps
its controls — that is how a user with no plan still finds it to move or remove
it.

**"Can show" means one of the status buckets the filter carries** — active,
waiting for review, in review, completed — and the list is the
`BUCKET_STATUSES` constant beside the method. A plain **draft** is
deliberately excluded: no bucket renders one, so the block would open with
nothing in it. The gate started narrower, at active plans only (2026-09-22), and
was widened the same day when the owner chose option B of the status-filter
proposal: a learner whose plans have all finished must still reach them, which
an active-only gate made impossible. `docs/proposals/2026-09-22-status-filter/`
records that decision. block_lp is broader still — any plan it can read, plus a
non-empty review queue — because its body carries a link to the plans page,
which this block does not.

**This gate was silently lost once.** The move to web-service rendering
(`f4806ef`, 2026-03-13, before the 1.0 release) deleted the two lines calling
`has_content()` and left the method in place — and `docs/block-kit`, written four
and a half months later, described the gate from the method alone, because
nothing showed it was never called. A method existing is not a gate running.
`tests/dimensions_test.php` pins it now. One trap in that test worth keeping: a
learner cannot read their own drafts by default
(`moodle/competency:planviewowndraft` has no archetype), so a draft or in-review
plan is filtered out by `api::list_user_plans()` before its status is ever
looked at. Both the test and `visibility.feature` grant that capability, and the
test asserts the plan reaches the list; without that, the draft case passes
while testing nothing — it would be excluded by a permission rather than by the
bucket rule it exists to prove.

**The gate fails open, and logs with `error_log()` on purpose.** The block
renders inline with the page and core's block manager catches nothing, so an
exception from the plan read (a lost connection, a read timeout) would replace
the whole Dashboard with an error page. `summary::has_content()` catches
`\Throwable`, logs, and answers **true**: the shell renders exactly as it did
before the gate existed, and the web service reads the plans again and, if that
fails too, shows its own error box with a retry button. Failing closed would
hide the block silently for everyone on a failure that repeats. Two traps:

- `debugging()` is not an option in that catch. With `debugdisplay` on and
  pretty exceptions (the default), `debugging()` calls `trigger_error()` and
  Whoops turns it into an `ErrorException` during a page render; AJAX and CLI
  are exempt (`get_whoops()` returns null for them), a page is not. So the
  "correct" fix a reviewer would suggest throws the page-killing exception from
  inside the catch. moodle-cs forbids `error_log()` in favour of `debugging()`,
  hence the one targeted `phpcs:ignore` on that line; do not "fix" it.
- The catch is in `summary`, not in `dataset_provider`'s constructor. The web
  service uses the provider too, and there a failure must stay an error the
  client can retry, not become an empty plan list.

`tests/output/summary_test.php` forces the failure through the
`create_dataset_provider()` seam; removing the catch, failing closed and
dropping the log line each redden it.

### The status filter: three buckets, one loaded with the page

The plan grid is scoped by a status bucket - `dataset_provider::BUCKET_ACTIVE` / `BUCKET_REVIEW`
(both review statuses) / `BUCKET_COMPLETE`, with `BUCKET_STATUSES` as the only map between a core
status and a bucket. The web service takes `planstatus` and **refuses an unknown value** rather
than quietly serving the active one. Facts worth keeping:

- **The block opens on the first bucket that holds any plan.** `opening_bucket()` walks the
  buckets in order by raw membership, and the web service resolves an EMPTY `planstatus` through
  it, so a learner whose plans have all finished lands on them instead of on an empty Active bucket
  with a notice — which is the whole reason the render gate was widened beyond active plans. An
  active plan on a competencies-mode template counts: it has no plan card but it has competency
  cards, and those are built only for the active bucket. Every later bucket request carries
  `state.planStatus` explicitly (leaving it out would pull the grid back to the opening bucket
  mid-session), and every competency or phase-2 load asks for `planstatus: 'active'`, since any
  other bucket returns no competency cards.
- **`hasactiveplans` and `plancounts.active` are different facts.** `hasactiveplans`
  (`has_active_plans()`) is any active plan, whatever its display mode; it decides the opening
  bucket, the "No active plans" notice and whether the Active pill is drawn. `plancounts` counts the
  plan cards each bucket shows. A learner whose active plans are all competencies-mode gets an
  Active pill with no number, not "Active 0" above a list of competency cards. Mixing the two once
  left such a learner opening on Completed with no way back to their competency cards.
- **The pills belong to the filter bar.** `renderStatusPills()` pushes them first into the bar
  `renderFilterControls()` builds, beside the favourites pills and the tag filters, so on a phone
  they appear with everything else when the panel is opened. They were briefly given a host of
  their own outside that panel (2026-09-22) and the owner asked for them back in the bar: the
  block keeps one place where filtering happens.
- **Only the opening bucket is loaded with the page.** `filters.js` keeps each fetched bucket in
  `state.statusCards`, so switching back costs no request; the first switch to a bucket draws
  skeleton cards while it loads, and nothing may render the previous bucket's cards under the new
  pill in that window. A failed switch returns the grid, its tag filters and its favourites
  filter to the bucket it left, so the failed bucket can be picked again. The Active bucket kept
  from the favourites-only first request is restored under the favourites pill, never under
  "Show all" with cards missing - except under a search or with favourites disabled, where the
  favourites filter stays off and the plans that first request left out are fetched (the search
  handler's rule: a search always sees every card). So "switching back costs no request" has that
  one exception.
- **Every response goes through `state.js applySharedMetadata()`** (hasactiveplans, plancounts,
  favouritesenabled, filtersettings). A handler that copied these itself once left stale state
  behind; the function also drops both favourites filters when a response says favourites are off,
  and `loadWhatFavouritesLeftOut()` then fetches whatever the favourites-only request left out.
- **One group load at a time, and no "no result" while one is pending.** A load of a group
  already in flight is reused, not sent again, and `canClaimNoResult()` decides for both the
  visible empty line and the results announcement: never while a ghost card offers more, nor
  while a pending load can still add cards. Only `[data-results-status]` is a live region; the
  visible empty line is not, or a search that found nothing was announced twice.
- **The bucket loading line names the bucket** (`statusloadingactive` / `statusloadingreview` /
  `statusloadingcomplete`, all shipped in `labelsjson`).
- **Retry starts a new client session.** `loadData()` rebuilds the state through
  `state.js resetSession()` (keeping only the search term and server settings), `resetView()`
  empties both lists and filter hosts, and every response or failure from the replaced session is
  dropped. Without that a retry after any error could keep the loading line up forever.
- **The counts are nearly free, and they count what the grid will show.** The provider already
  holds the whole plan list; the active count also needs the active plans' template display mode,
  read in one batch through `template_metadata_cache::get_metadata_for_many()`
  (`prefetch_template_metadata()`, memoised per request) - the active count skips a
  competencies-mode template, which becomes competency cards rather than a plan card. Without that
  the pill said 5 while the "Show all" pill beside it said 4.
- **A bucket with no plans is not drawn.** An empty *In review* would read as "you have none",
  while on most sites it means "you cannot see them": the review statuses are core's draft
  statuses, and a learner needs `moodle/competency:planviewowndraft`, which no archetype holds.
- **Outside the active bucket** every plan renders as a plan card whatever its display mode, with a
  status chip, *View plan* instead of *Continue*, and no favourite star. Competency cards belong to
  the active plans and are not rebuilt when the bucket changes.
- **A completed plan's trail comes from the frozen archive**, which is `local_dimensions`
  2026092200 (`get_trail_data(..., $iscomplete)`) - hence the dependency bump. Reading live there
  makes the card disagree with core's own plan page about a plan that closed months ago.
- **Every label the pills draw ships in `labelsjson`.** The pills are built by JavaScript from that
  payload alone: a label missing there does not fail anything, it draws the bucket key at the
  learner. That shipped once; `summary_test` now asserts the payload.

### Two card-layout invariants a browser found and no gate can see

Both defects below were in the repo, both were invisible to phpcs, the mustache lint and
stylelint - they read syntax, and what broke was geometry - and both are now pinned by
`tests/local/card_layout_test.php`.

- **The card grids lay out on `repeat(auto-fill, minmax(...))` tracks, never a flex row.** A flex
  item grows to fill its line, so the last card of an odd row stretched to the full width while the
  row above kept three columns. `auto-fill` keeps the empty tracks. The item rule must also
  neutralise the Bootstrap column classes the client puts on each `<li>`, or the card shrinks
  inside its own track.
- **`.dimension-tags` stays `position: absolute`.** A rule lifting it above the stretched-link
  overlay re-declared `position: relative`, which returned it to the flow, where the image
  wrapper's `overflow: hidden` cut the pills in half on the horizontal card. Put stacking in that
  rule, never position. The test strips CSS comments before matching, because its own first draft
  read a comment naming the selector as though it were the rule.
- **A track floor never exceeds the block.** Each grid's floor is `--dims-card-track-min`, always
  `min(<width>, 100%)`, read as `minmax(var(--dims-card-track-min), 1fr)`: the competency grid's
  360px floor overflowed Boost's 315px block drawer. The custom property is not decoration -
  Moodle's stylelint (csstree) rejects `min()` written directly inside `minmax()`, but does not
  validate a custom property's value.

### The screenshots in `docs/screenshots/` are real, and reproducible

They are the running block on m502, captured headless over the DevTools protocol, not mockups: the
learner is a seeded fixture (`alex.morgan`), the site is stock Boost in English, and the session
came from a token-gated helper deleted right after. The seeding and capture scripts are not in the
repo; what matters here is the rule: **a screenshot in the README is a claim about the plugin, so
re-take the affected ones whenever a surface moves.** Every defect fixed in the 2026-09-22 round -
the stretched card, the clipped tag strip, the missing tag pills, the pills drawing their own keys -
was found by looking at those captures, not by a gate.

### The colour system: 34 tokens, one activation rule, three plugin-owned values

**This is the part of the plugin most likely to be broken by a well-meant edit,
and it is the part with the most tests behind it.** The full design record is
`docs/block-kit/token-migration.md`; the contract itself is the banner comment
at the head of `styles.css`. In short:

- **34 custom properties, declared once, on `body`.** The suffix set is
  byte-identical to `local_dimensions`' `--local-dimensions-*` set — only the
  frankenstyle prefix differs. That parity is what makes the two plugins one
  system, and a test compares the two blocks with both prefixes rewritten to a
  sentinel.
- **30 of the 34 chain a `--bs-*` property to a literal**, six of them with a
  Bootstrap 4 name between the two: `var(--bs-NEW, var(--BS4-OLD, #literal))`.
  Moodle 4.5 declares zero `--bs-*` names and Moodle 5.2 declares zero BS4
  legacy names, so **one chain is correct on every supported branch with no
  branch test anywhere**. Never add a branch conditional to a colour.
- `body`, not a plugin class and not `:root`, on purpose. `body` is the ancestor
  of every node the plugin paints, including anything core relocates to
  `document.body`, and an unresolved `var()` **does not fall back to its
  literal** — the whole declaration is invalid at computed-value time, so a
  background set from an undeclared token is not the default background, it is
  *no* background. And a custom property is substituted on the element that
  declares it: Bootstrap redefines the `--bs-*` set on whatever element carries
  `data-bs-theme`, which is `<html>` in Moodle 5.3 core and `<body>` in
  theme_moove, so tokens declared on `:root` kept html's light values under
  theme_moove's dark mode. Declared on `body`, they see both.
- **31 of the 34 need no dark rule at all**, because Moodle 5.1/5.2 already
  compile a complete `[data-bs-theme="dark"]` token block and the chains follow
  it. The card face *is* `--bs-body-bg`, which is also the page background, so
  the two cannot disagree. **Do not give them a dark rule.**
- The one activation rule is
  `body[data-bs-theme="dark"], [data-bs-theme="dark"] body` and it assigns
  **exactly three** tokens: `shadow`, `scrim`, `favourite`. That bound is a
  guarantee — the worst a wrongly-firing activation block can do is deepen a
  shadow, darken a veil and brighten a star; it cannot paint a dark surface on a
  light page. Adding a fourth token to that rule fails the build.
- **`body` is the subject of both arms, never a bare `[data-bs-theme="dark"]`.**
  With `body` as the subject, the only ancestor the second arm can match is
  `<html>`. A bare selector matches through any ancestor at any depth and CSS has
  no nearest-ancestor-wins rule: `theme_boost_union_fundaseg` really does set
  `data-bs-theme="dark"` on the navbar and `theme_boost_union` re-pins `light` by
  hand on five nested templates, and a bare selector would ignore those re-pins.
  `colour_tokens_test::test_activation_selectors_have_body_as_subject` pins it.
- **`.theme-dark` and `body.dark` are not accepted, and must never come back.**
  Nothing in Moodle 4.5, 5.0, 5.1, 5.2 or 5.3-dev, and nothing in
  `theme_boost_union` or `theme_boost_union_fundaseg`, has ever emitted either.
  The plugin's 77 + 77 rules keyed off them were dead code and were deleted.
  Moodle **5.3** core does write `data-bs-theme` on `<html>`, from
  `theme_boost\colour_mode` via `before_html_attributes`, gated behind
  `theme_boost/enablecolourmodes` and **off by default**.
- The `@media (prefers-color-scheme: dark)` block is **deliberately inert**:
  every selector in it is gated on `[data-dimensions-media-optin]`, which
  nothing in either plugin ever writes. Core treats the OS preference as an
  *input* to `data-bs-theme`, never as an independent trigger; firing on the
  media query directly is how a plugin ends up dark inside a light page. A test
  fails the build if the gate ever becomes reachable. To switch it on later,
  delete the attribute from the selector and update
  `colour_tokens_test::test_media_fallback_is_written_and_unreachable`, which
  asserts the gate.
- **`colour_mode` is constants only, and has no `is_dark()`.** Whether the host
  page is dark is not server-knowable: core resolves it from a per-user
  preference, a cookie and a synchronous head script reading `matchMedia`. Any
  PHP guess would eventually be wrong, and a wrong guess is the exact defect the
  design exists to prevent.
- **`--dimension-custombgcolor` and `--dimension-customtextcolor` are not
  tokens.** They carry admin instance data across the plugin boundary, and the
  mode layer reads them but never declares or overrides them, in either mode. A
  site's brand colour stays its colour when the page goes dark.
- **One focus indicator, everywhere:**
  `outline: 2px solid var(--block-dimensions-focus-ring); outline-offset: 2px`.
  Two documented exceptions draw the same ring at `-2px` because their platter
  is `overflow: hidden`. **Never draw a focus indicator with a `box-shadow`** —
  it is not painted at all under `forced-colors: active`. `focus-ring` chains
  `--bs-emphasis-color` and deliberately *not* `--bs-focus-ring-color`, which
  core fails to flip (`rgba(15, 108, 191, 0.25)`, 1.26:1 on the dark page).

### The Bootstrap 4 polyfill rides the block's content root, not a body class

`styles.css` ends with a polyfill for the BS5 utility families Moodle 4.5 does
not define, gated on `.block-dimensions-bs4`. The gate is **not optional**:
a polyfill rule is scoped to the block's root (two classes), so on 5.x it
outranks core's one-class utility by specificity and would freeze 4.5's
metrics onto the newer branch. Not by order: plugin CSS is compiled BEFORE
the theme's, Bootstrap included, so a plugin rule of equal specificity to a
core rule always loses (fleet `CLAUDE.md`, "Plugin CSS is compiled BEFORE the
theme's").

**The gate is on the block's content root (`div.block-dimensions-content`,
written by `summary.mustache` from the renderable's `isbs4`), and that is a
forced divergence from `local_dimensions`, which uses a body class.** A block
cannot add a body class: its content is built while the theme layout renders,
and the layout is included from `$OUTPUT->header()`, by which point
`moodle_page::add_body_class()` throws ("after output has been started").
Boost's `drawers.php` does call `blocks('side-pre')` before `body_attributes()`,
but the page state has already left `STATE_BEFORE_HEADER`, and a content-region
block renders after the header anyway. `block_dimensions\local\bootstrap`
therefore has **no `mark_page()`**; do not add one, because there is no point in
the block's lifecycle at which it could work, and a helper that silently does
nothing is worse than an absent one. It is safe here specifically because the
block renders nothing outside its own subtree — no `core/modal`, no
`document.body` append — so there is no node a body class would have reached
that the root class does not.

Add a family to the polyfill *before* using its BS5 name;
`bootstrap_compat_test` fails the build on a BS5 utility the polyfill does not
cover, and equally on a polyfill rule nothing uses any more.

### What the enforcement tests pin

Nothing else in the pipeline reads a colour role: phpcs reads PHP, phpdoc reads
docblocks, the mustache lint reads markup structure, stylelint reads CSS syntax.
Not one of them can tell a focus ring drawn with a `box-shadow` from one drawn
with an outline, or a dark rule that assigns a decorative shadow from one that
assigns a whole surface. **That is why every rule of the design is a test method
rather than a paragraph.** Each method names the mutation that must redden it.

- `tests/local/colour_tokens_test.php` (24 methods, `\basic_testcase`) — the
  exact 34 declarations by **string equality**, not a shape regex (a pattern
  cannot prove a chain terminates in a literal, and the terminating literal *is*
  the plugin's Moodle 4.5 behaviour); the suffix list and its byte-identity with
  the sibling; that the activation block assigns only the three plugin-owned
  tokens and has `body` as the subject; that the media block is unreachable; contrast
  floors in all three resolutions (5.x light, 5.x dark, 4.5 fallback); that no
  focus indicator is a `box-shadow` or brand-coloured; that the favourite star
  sits on an opaque ground (a translucent disc over card art fell below 3:1); that the admin-colour
  names are never declared by the mode layer; and that every token *read* is a
  token *declared*. It carries an `UNRESOLVED_BUDGET` ratchet asserted for
  **equality**, so it reddens in both directions and may only be edited
  downwards. Its contrast maps `CORE_LIGHT` / `CORE_DARK` / `CORE_BS4` are copies
  of core's compiled values (Boost's `theme/styles.php` sheet on 5.2, Boost Union's
  on 4.5): re-read them from the compiled sheets when Moodle updates Bootstrap,
  or the floors are measured against colours core no longer ships.
- `tests/local/bootstrap_compat_test.php` (8 methods) — every BS5 utility used
  is polyfilled, the polyfill carries nothing unused, no deprecated BS4 class
  names anywhere (`ml-*`, `sr-only`, `text-left`… resolve on 5.x only through
  `bs4-compat.scss`, which Moodle 6.0 deletes), badges state their text colour,
  data-API attributes are paired (`data-toggle` **and** `data-bs-toggle`), no
  `--mds-*` squatting on core's design-system namespace, and the block root
  actually carries the Bootstrap marker. The BS4 list includes the `font-weight-*`
  and `font-italic` families (the card titles set their weight in the stylesheet
  instead). Both scanners strip Mustache comment
  spans before reading a template as markup, since a docblock's continuation
  lines carry no comment marker of their own.
- `tests/local/card_layout_test.php` (10 methods) — the card-layout invariants
  below, plus the rules the preference media queries depend on: every
  `prefers-contrast` / `prefers-reduced-motion` query uses a defined value (a
  `prefers-contrast: high` query matches in no browser), every override in those
  blocks and in print wins the cascade against the rule it overrides (several
  lost on specificity and did nothing), every `transform` has a reduced-motion
  reset, the plan and competency card shells get the same preference treatment,
  and the checked pill's count badge stands off the indicator it sits on.
  `colour_tokens_test` holds the matching contrast floors: both count-badge edges
  (checked and resting) reach 3:1 against what they sit on, and every unchecked
  pill label and count clears 4.5:1 against the platter with every opacity on the
  way multiplied in (an `opacity: 0.8` once put the labels at 4.07:1).

### Metadata comes from local_dimensions caches only
`dataset_provider` reads card metadata exclusively through the
`local_dimensions` cache classes. Do not re-introduce direct
`customfield_*` queries or File-API image resolution here — that duplicated
logic was removed as dead code. If a new field is needed, extend the cache in
`local_dimensions` and consume it here.

### Inline-style CSS context (security)
`bgcolor` / `textcolor` / `imageurl` are interpolated into `style="..."`
attributes in the card templates. Mustache `{{ }}` escaping does **not**
protect a CSS context, so every value destined for a style attribute must pass
`dataset_provider::sanitize_color()` (hex only) or `sanitize_image_url()`,
and WS return types for URLs are `PARAM_URL`, never `PARAM_RAW`. `PARAM_URL`
alone is not enough inside `url('...')`: its query and fragment classes accept
`'`, `(`, `)` and `;`, and the HTML parser decodes Mustache's `&#39;` before the
CSS parser sees it, so `sanitize_image_url()` also percent-encodes
`' " ( ) \` and whitespace after `clean_param()` (a `moodle_url`-built
pluginfile URL already encodes them and passes through unchanged). Keep this
invariant when adding new style-bound fields.

### Favourites
Stored via `core_favourites` in the **user context**, component
`block_dimensions`, itemtypes `plan` / `competency`. Three places must stay in
sync with those itemtypes: `classes/privacy/provider.php` (ITEMTYPES const),
`db/uninstall.php` (purges `favourite` rows by component — core's
`uninstall_plugin()` does not), and `toggle_favourite`. The enabled check is
`dataset_provider::is_favourites_enabled()` (treats "never set" as enabled) —
use it everywhere, never raw `get_config('block_dimensions',
'enable_favourites')`, or UI and WS disagree on fresh sites. A card offers the
star only through `dataset_provider::favourite_fields()`: `showfavourite` is that
check AND, for a plan card, the active bucket; both templates gate the button on
it, and `get_block_dataset` declares it for both card types. With the setting
off, a star used to render on every card while the WS refused every click. The
web service also ignores `favouritesonly` while favourites are disabled.
`toggle_favourite` validates ownership (plan must belong to the user;
competency must exist) before writing — keep that guard.

### External functions
`validate_parameters()` → `require_login()` + guest rejection →
`validate_context(context_user)`. Register in `db/services.php`
(`ajax => true`) — **services install only on upgrade, so a new/changed
function needs a `version.php` bump.**

## Coding style

### File header
Every PHP file: GPL block, then file docblock with `@package
block_dimensions`, `@copyright`, `@license` (no `@author`). Namespaced class
files add `namespace block_dimensions\<sub>;`. No `declare(strict_types=1)`;
match surrounding files.

**`defined('MOODLE_INTERNAL') || die();` — only when the file executes
top-level code on mere `include`.** The sniff is
`moodle.Files.MoodleInternal.MoodleInternalNotNeeded` and it checks *effect on
include*, not file location — `db/*.php` is not a blanket rule:
- **Needs the guard**: files with top-level assignments/calls that run just by
  being included — `db/access.php` (`$capabilities = […]`), `db/services.php`
  (`$functions = […]`), `settings.php` (`$settings->add(…)`), `version.php`
  (`$plugin->version = …`).
- **Must omit it**: files whose only top-level construct is a **function or
  class definition** — defining a function has no side effect until it is
  *called*, so the guard is flagged as unneeded. This bit us for real:
  `db/uninstall.php` (single `function xmldb_block_dimensions_uninstall() {
  … }`, nothing else) failed CI with this exact warning after the guard was
  added by habit. `db/install.php`/`db/upgrade.php` (if added later) follow
  the same rule — check the sibling `local_dimensions/db/uninstall.php` and
  core's `mod/subsection/db/uninstall.php` for the canonical shape (docblock,
  then the function, no guard). Pure namespaced single-class files
  (`classes/**`) never need it either.

`--max-warnings 0` on the `phpcs` CI gate means this single warning fails the
whole build — there is no "just a warning" tier here.

### PHPDoc (`phpdoc --max-warnings 0`)
- Every class, method, property has a docblock; `@param` / `@return` /
  `@throws` explicit.
- **`@param` array types must be plain `array`** — generics/shapes break
  `local_moodlecheck` param pairing. Put the shape in prose.
  `@return array{...}` / `array<…>` is fine.
- Property docblocks need `@var` even with typed properties.

### CodeSniffer rules that routinely bite (pre-empt at write time)
1. **Variables lower-case only** — `$courseid`, not `$courseId`.
2. **PSR-2 multi-line calls** — `(` last on its line, one arg per line, `)` on
   its own line at call indent.
3. **Inline `//` comments**: one space, capital first letter, terminal
   punctuation.
4. **Operator spacing**: exactly one space around `===` / `?` / `:`.
5. **Multi-line `if`**: first expression after `(`, `)` on its own line.
6. **Line length**: hard max **180** (error), soft max **132** (warning — and
   warnings fail the gate).
7. No dynamic lang keys: never `get_string('foo_' . $x, …)` — use a literal
   `switch`/`match`.

## Lang strings
`lang/en/block_dimensions.php` and `lang/pt_br/block_dimensions.php` are kept
in **sync** and **alphabetically sorted** (the `validate` step enforces
ordering). Settings use `<key>` + `<key>_desc`. Insert new strings in the
correct alphabetic slot in **both** files.

## Mustache templates
Every `templates/*.mustache` needs an `Example context (json):` block — the
Mustache lint renders against it and validates the HTML, **including any
`data-*` JSON the template's JS expects** (e.g. `summary.mustache` must ship
`labelsjson` in its example context or the JS check fails on some branches).
Cards carry WCAG design notes in their docblocks (pseudo-link card pattern,
`aria-pressed` favourites, radiogroup filters) — preserve the documented
semantics when editing markup. `{{{triple}}}` only for trusted server-rendered
HTML; **zero `html_writer`** in plugin code.

## PHPUnit tests
- `tests/<area>/<thing>_test.php`; class extends `\advanced_testcase`;
  `@covers` on the docblock; `$this->resetAfterTest()` in any DB test.
- Coverage: `mdl ci moodle-block_dimensions --branch MOODLE_502_STABLE --php 8.4 --coverage`.
  `tests/coverage.php` adds `block_dimensions.php` and `db/uninstall.php`, which
  core's default list leaves out. Measured 2026-09-24: 99.6% of lines (905/909),
  97.0% of methods (64/66). The four lines left are defensive: the
  `$info === null` / empty-items skips in the privacy export (the favourite row was
  just read, so core cannot return null for it) and the `catch (\Exception)` in
  `build_plan_trail_payload()`, which needs a real database failure.
- `@covers` is **class-scoped** and names every production class the file really
  executes (`dimensions_test` runs the renderer and `summary` too). The method-scoped
  form this suite used before credited only the named method and discarded every other
  line the test ran: it read 65.4% over a suite that executed 91.3%. It stays in docblock
  form, not attributes, while 4.5 is supported. `provider_test.php` still uses the
  method form.
- `dataset_provider_test.php` uses an **anonymous-class double** exposing
  protected helpers as `test_*()` proxies (they are not tests) and stubbing the
  data fetchers (`fetch_bulk_competency_metadata`, which reads the
  `local_dimensions` cache, and `fetch_plan_competencies_api` /
  `get_competencies_with_courses`, which read core) so helper tests need no
  seeded plans. The suite still needs `local_dimensions` installed: the provider
  uses its constants and caches directly. Extend that double for new helpers.
- Generator ids come back as **string** under some drivers — cast `(int)`.
- `colour_tokens_test` and `bootstrap_compat_test` extend **`\basic_testcase`**,
  not `advanced_testcase`: they read `styles.css`, the templates and the AMD
  sources off disk and touch no database. Keep them that way — they are the
  fastest gate in the repo and run on every leg.
- **Mutation-check every assertion you add to them.** Two drafts of
  `bootstrap_compat_test` passed while blind to the very defect they were
  written for: one filtered badges by the word "badge" appearing on the same
  line, which missed `match` arms whose method name carried it; the other
  checked class *families*, so removing `.gap-2` still matched via `.gap-1`. A
  test that passes against the mutation it exists to catch is worse than no
  test — it certifies safety that is not there.

## Behat

Four feature files: `colour_mode.feature` (4 scenarios, the colour-mode
contract), `visibility.feature` (6, the render gate and the status pills),
`status_filter.feature` (2: a learner whose only active plan is competencies-mode
opens on Active, with a count-less pill, and keeps the competency section across
a round trip to Completed; and a search from the Completed bucket still loads the
competency cards from the active bucket) and `card_filters.feature` (4: a search
that matches nothing says so; returning to Active keeps the favourites view and
its ghost card; a tag value holding a double quote survives a filter-bar rebuild;
returning to Active under a search finds the plans the favourites view left out),
plus the step-definition context
`tests/behat/behat_block_dimensions.php`. Run a feature locally with the
**absolute container path** — the relative forms match nothing:

```sh
mdl behat-init m502
mdl behat m502 /var/www/html/public/blocks/dimensions/tests/behat/colour_mode.feature
```

Keep scenarios as thin smoke tests and put logic in PHPUnit. See
`local_dimensions/CLAUDE.md` for the hard-won locator gotchas (autocomplete,
dialogs, checkbox labels).

**Never put a `MOODLE_INTERNAL` guard in `tests/behat/behat_*.php`** — behat
includes these before `config.php`, so the bare `die()` exits the whole behat
process silently with code 0, for every plugin on the site. Use the canonical
comment instead; the file already carries it.

### The step wording diverges from the sibling ON PURPOSE — do not "fix" it

`block_dimensions` says **"host"** everywhere `local_dimensions` says
**"page"**: *the host colour mode is "dark"*, *I remember the host page
background colour*, *… should still match the host page*, *the "…" colour token
should resolve to "…" on the host*.

**Behat step definitions are site-global.** Moodle loads every installed
plugin's context into one suite, so two contexts declaring the same regular
expression is a hard failure — *"Step … is already defined in …"* — that fails
**every scenario in BOTH plugins**, not just the duplicate one. This was
measured, not theorised: on m502 with `local_dimensions`' identical steps
present, 4 scenarios and 42 steps all failed before a single assertion ran.

And it is certain rather than hypothetical here, because `block_dimensions`
declares `local_dimensions` as a hard dependency in `version.php` and its CI
checks it out on every job — the two contexts are always loaded together.

So: **aligning the wording toward the sibling breaks both suites.** The contract
the two assert is identical, and both files stay greppable on "colour mode",
"background colour" and "colour token should resolve to". If a new step is added
to either plugin, check the other's context first.

One thing no scenario here can cover: the inertness of the
`prefers-color-scheme` block. Emulating a media feature needs
`Emulation.setEmulatedMedia` over the DevTools protocol, and Moodle's WebDriver
wiring is not confirmed to expose it; headless Chrome reports light, so the
query never evaluates true and an assertion on it would pass having tested
nothing. That gap is closed by
`colour_tokens_test::test_media_fallback_is_written_and_unreachable`, which is
strictly stronger — it proves nothing *can* set the gate, not merely that
nothing did on one run.

Scenario `@B2` is the **anti-vacuity control** the fleet rule asks for: `@B1`
proves the mechanism fires, `@B2` proves it does not fire unasked. Point a
surface token at a literal and B1 reddens; add an ungated dark rule and B2
reddens. Keep both.

## Cross-DB SQL
CI runs PostgreSQL and MariaDB. Placeholders / `get_in_or_equal` only; avoid
`ORDER BY … NULLS FIRST`; cast numeric DB reads when typing matters.

## Git / version.php / release
Run git from the plugin dir (or `git -C`) — `cd` doesn't persist between Bash
calls. Keep `CHANGELOG.md` (`## Unreleased` → `### Fixed/Added/Changed`)
updated with every substantive change. When rebasing conflicts on
`$plugin->version`, keep the **higher** number so the upgrade still triggers.

## Test deploy zip
`git archive` packages a **commit**, never the working tree — commit first.
Name it `moodle-<component>-<version>-<shortSHA>.zip`, here
`moodle-block_dimensions-<version>-<shortSHA>.zip`.

The **filename** carries the frankenstyle component with a `moodle-` prefix,
matching the repo name; the **`--prefix`** is the install directory, which is the
component with its type stripped (`${comp#*_}`). They differ, and only the second
is what Moodle validates. `local_dimensions`, `block_dimensions` and
`aiplacement_dimensions` all install into a folder called `dimensions`, so naming
the zip after the folder made all three collide in `~/Downloads`. The short SHA is
required — several slices can share one version number.

```sh
comp=$(grep -oE "\$plugin->component[[:space:]]*=[[:space:]]*'[^']+'" version.php \
  | grep -oE "'[^']+'" | tr -d "'")
ver=$(grep -oE '\$plugin->version[[:space:]]*=[[:space:]]*[0-9]+' version.php | grep -oE '[0-9]+')
sha=$(git rev-parse --short HEAD)
git archive --format=zip --prefix="${comp#*_}/" HEAD -o ~/Downloads/moodle-$comp-$ver-$sha.zip
```

## `docs/block-kit/` — the as-is design kit

An **as-is visual replica** of the block: `tokens.html`, eight screens under
`screens/`, two field maps under `maps/`, and `token-migration.md`. Every panel
is meant to be what `styles.css` ships *today*, in both colour modes, so the
kit's whole credibility rests on that claim staying true.

**A change to `styles.css` that moves a colour, a focus shape, a class name or a
responsive trigger falsifies the kit, and nothing in CI notices.** Re-baseline
it in the same commit. `token-migration.md` records every re-baseline and the
open questions each closed. `docs/` is excluded from the release zip via `.gitattributes`
(`/docs export-ignore`), so nothing here ships to a site.

The one gate that *does* read this folder is the development-leftover checker,
which scans **every** file in the repo including docs and fails the build on a
stray to-do or merge-conflict marker. Never write those tokens literally
anywhere. Verify with `mdl ci moodle-block_dimensions --only leftover`.

## When in doubt
Follow the patterns in existing files. The codebase is internally consistent —
if a new file feels like it matches no existing shape, re-examine the approach.

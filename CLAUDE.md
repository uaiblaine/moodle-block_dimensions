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
same commit, plus a `version.php` bump so the cache revision changes. The
stacks run with `cachejs = false`, so during development just edit `amd/src`
and reload.

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
                             non-guests, gated on get_config('core_competency',
                             'enabled'); can_block_be_added() enforces the same
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
styles.css                   34 colour tokens on bare :root, one
                             :root[data-bs-theme="dark"] activation rule, one
                             inert prefers-color-scheme block, and a Bootstrap 4
                             utility polyfill at the tail
templates/                   summary (server-rendered shell), plan_card,
                             competency_card, filters (client-rendered)
amd/src/                     filters.js (fetch + render), state.js (pure state
                             fns), filter_tabs_nav.js — plain AMD, NOT ESM here
amd/build/                   Committed minified output (grunt) — keep in sync
lang/{en,pt_br}/             Both kept in sync, alphabetically sorted
tests/                       PHPUnit: dataset_provider (double pattern),
                             external functions, privacy, generator,
                             local/colour_tokens_test, local/bootstrap_compat_test
tests/behat/                 colour_mode.feature + behat_block_dimensions.php
docs/block-kit/              As-is visual replica of the block (excluded from
                             the release zip via .gitattributes)
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

### The colour system: 34 tokens, one activation rule, three plugin-owned values

**This is the part of the plugin most likely to be broken by a well-meant edit,
and it is the part with the most tests behind it.** The full design record is
`docs/block-kit/token-migration.md`; the contract itself is the banner comment
at the head of `styles.css`. In short:

- **34 custom properties, declared once, on bare `:root`.** The suffix set is
  byte-identical to `local_dimensions`' `--local-dimensions-*` set — only the
  frankenstyle prefix differs. That parity is what makes the two plugins one
  system, and a test compares the two blocks with both prefixes rewritten to a
  sentinel.
- **30 of the 34 are three-rung chains**, `var(--bs-NEW, var(--BS4-OLD, #literal))`.
  Moodle 4.5 declares zero `--bs-*` names and Moodle 5.2 declares zero BS4
  legacy names, so **one chain is correct on every supported branch with no
  branch test anywhere**. Never add a branch conditional to a colour.
- `:root`, not a plugin class, on purpose: `:root` is the ancestor of every node
  in the document, including anything core relocates to `document.body`. And an
  unresolved `var()` **does not fall back to its literal** — the whole
  declaration is invalid at computed-value time, so a background set from an
  undeclared token is not the default background, it is *no* background.
- **31 of the 34 need no dark rule at all**, because Moodle 5.1/5.2 already
  compile a complete `[data-bs-theme="dark"]` token block and the chains follow
  it. The card face *is* `--bs-body-bg`, which is also the page background, so
  the two cannot disagree. **Do not give them a dark rule.**
- The one activation rule is `:root[data-bs-theme="dark"]` and it assigns
  **exactly three** tokens: `shadow`, `scrim`, `favourite`. That bound is a
  guarantee — the worst a wrongly-firing activation block can do is deepen a
  shadow, darken a veil and brighten a star; it cannot paint a dark surface on a
  light page. Adding a fourth token to that rule fails the build.
- **Anchored at `:root`, not a bare `[data-bs-theme="dark"]`.** A bare selector
  matches through any ancestor at any depth and CSS has no nearest-ancestor-wins
  rule. `theme_boost_union_fundaseg` really does set `data-bs-theme="dark"` on
  the navbar and `theme_boost_union` re-pins `light` by hand on five nested
  templates; a bare selector would ignore those re-pins.
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
  delete the attribute from the selector — one edit, nothing else.
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
  core fails to flip (1.02:1 on the dark page).

### The Bootstrap 4 polyfill rides the block's own root, not a body class

`styles.css` ends with a polyfill for the BS5 utility families Moodle 4.5 does
not define, gated on `.block-dimensions-bs4`. The gate is **not optional**:
plugin CSS loads after core's, so an ungated rule would outrank core's own
definition on 5.x and freeze 4.5's metrics onto the newer branch.

**The gate is on the block's own root element, and that is a forced divergence
from `local_dimensions`, which uses a body class.** A block cannot use a body
class: `theme/boost/layout/columns2.php` calls `$OUTPUT->body_attributes()` one
line before `$OUTPUT->blocks('side-pre')`, and it is the latter that invokes
`block_base::get_content()` — the body tag's attributes are already computed by
the time this plugin is asked for any content. `block_dimensions\local\bootstrap`
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

- `tests/local/colour_tokens_test.php` (17 methods, `\basic_testcase`) — the
  exact 34 declarations by **string equality**, not a shape regex (a pattern
  cannot prove a chain terminates in a literal, and the terminating literal *is*
  the plugin's Moodle 4.5 behaviour); the suffix list and its byte-identity with
  the sibling; that the activation block assigns only the three plugin-owned
  tokens and is `:root`-anchored; that the media block is unreachable; contrast
  floors in all three resolutions (5.x light, 5.x dark, 4.5 fallback); that no
  focus indicator is a `box-shadow` or brand-coloured; that the admin-colour
  names are never declared by the mode layer; and that every token *read* is a
  token *declared*. It carries an `UNRESOLVED_BUDGET` ratchet asserted for
  **equality**, so it reddens in both directions and may only be edited
  downwards.
- `tests/local/bootstrap_compat_test.php` (7 methods) — every BS5 utility used
  is polyfilled, the polyfill carries nothing unused, no deprecated BS4 class
  names anywhere (`ml-*`, `sr-only`, `text-left`… resolve on 5.x only through
  `bs4-compat.scss`, which Moodle 6.0 deletes), badges state their text colour,
  data-API attributes are paired (`data-toggle` **and** `data-bs-toggle`), no
  `--mds-*` squatting on core's design-system namespace, and the block root
  actually carries the Bootstrap marker.

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
`dataset_provider::sanitize_color()` (hex only) or `sanitize_image_url()`
(`clean_param PARAM_URL`), and WS return types for URLs are `PARAM_URL`, never
`PARAM_RAW`. Keep this invariant when adding new style-bound fields.

### Favourites
Stored via `core_favourites` in the **user context**, component
`block_dimensions`, itemtypes `plan` / `competency`. Three places must stay in
sync with those itemtypes: `classes/privacy/provider.php` (ITEMTYPES const),
`db/uninstall.php` (purges `favourite` rows by component — core's
`uninstall_plugin()` does not), and `toggle_favourite`. The enabled check is
`dataset_provider::is_favourites_enabled()` (treats "never set" as enabled) —
use it everywhere, never raw `get_config('block_dimensions',
'enable_favourites')`, or UI and WS disagree on fresh sites.
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
- `dataset_provider_test.php` uses an **anonymous-class double** exposing
  protected helpers as `test_*()` proxies and stubbing the
  `local_dimensions`-touching fetchers (`fetch_bulk_competency_metadata`,
  `fetch_plan_competencies_api`, `get_competencies_with_courses`) so helper
  tests run without the sibling plugin. Extend that double for new helpers.
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

The block gained its **first** Behat coverage on 2026-09-05:
`tests/behat/colour_mode.feature` (4 scenarios) plus the step-definition context
`tests/behat/behat_block_dimensions.php`. Run it locally with the **absolute
container path** — the relative forms match nothing:

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
it in the same commit. It has been re-baselined twice, on 2026-07-27 and
2026-09-05, and `token-migration.md` records both plus which open questions each
closed. `docs/` is excluded from the release zip via `.gitattributes`
(`/docs export-ignore`), so nothing here ships to a site.

The one gate that *does* read this folder is the development-leftover checker,
which scans **every** file in the repo including docs and fails the build on a
stray to-do or merge-conflict marker. Never write those tokens literally
anywhere. Verify with `mdl ci moodle-block_dimensions --only leftover`.

## When in doubt
Follow the patterns in existing files. The codebase is internally consistent —
if a new file feels like it matches no existing shape, re-examine the approach.

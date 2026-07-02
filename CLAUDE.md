# Claude instructions for `block_dimensions`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. It captures the **Moodle development standards** this plugin
follows so future edits stay in the same style and pass CI on the first try.
The conventions are shared with the sibling plugin `local_dimensions` (see its
own `CLAUDE.md` at `public/local/dimensions/`); this file keeps only what is
true here.

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

This plugin is its **own git repo** (`uaiblaine/moodle-block_dimensions`,
branch `main`). There are **two clones**: `public/blocks/dimensions` **inside
the Moodle checkout** (`/Volumes/N1TB/dev/github/moodle`, 5.x split layout:
webroot under `public/`) — the primary working tree in these sessions — and a
standalone clone at `/Volumes/N1TB/dev/github/moodle-block_dimensions`, which
may lag behind. **`git fetch` + check which clone is ahead before starting**,
or you will build on a stale base.

### CI validation happens on GitHub push — not locally

Per the owner: local CI validation is intentionally minimal; the full pipeline
runs on GitHub after push. **Do not** try to stand up the workspace
`Makefile` `ci-*` targets (they need Docker, which is not reliably available
on this machine). Locally verifiable before pushing:

```sh
php -l <changed files>                                  # syntax
cd /Volumes/N1TB/dev/github/moodle
npx eslint  public/blocks/dimensions/amd/src            # JS lint
npx stylelint public/blocks/dimensions/styles.css       # CSS lint
```

phpcs / phpdoc / PHPUnit / Behat / mustache-lint have **no local runner** —
eyeball them at write time against the rules below and let GitHub CI gate.

### CI pipeline (`.github/workflows/ci.yml`)

The **moodle-an-hochschulen/moodle-workflows** reusable workflow, called once
per supported Moodle branch (5.02 full PHP × DB matrix; 5.01/5.00/4.05
one-DB-only). Each call cross-installs `uaiblaine/moodle-local_dimensions,main`
as a plugin dependency — keep that in every job, and **update the calls when
`$plugin->supported` changes**. Gates: `phplint`, `phpmd` (informational),
`phpcs --max-warnings 0` (**warnings fail**), `phpdoc --max-warnings 0`, a
development-leftover checker that fails on stray to-do / merge-conflict
markers in **any** file (docs included — never write those tokens literally),
`validate` (lang string ordering!), `savepoints`, `mustache`,
`grunt --max-lint-warnings 0` (incl. eslint + stylelint), then PHPUnit
(`--fail-on-warning`) and Behat on every runtime leg.

### Building JavaScript assets (required before committing JS)

`amd/src/*.js` compiles to `amd/build/*.min.js` via Moodle's grunt, run from
the Moodle root. `amd/build/**` is **tracked in git** — Moodle serves the
compiled output. Every `amd/src` edit must ship its rebuilt `.min.js` + `.map`
in the same commit, plus a `version.php` bump so the cache revision changes.

```sh
cd /Volumes/N1TB/dev/github/moodle
npx grunt amd --root=public/blocks/dimensions
```

(Moodle 4.5 legacy layout uses `--root=blocks/dimensions`.) There is also a
workspace shortcut `make amd-block-dimensions` in
`/Volumes/N1TB/dev/github/Makefile`. The repo's own `package.json` has only
stylelint devDeps — **don't** `npm run build` here; canonical artefacts come
from Moodle's Gruntfile.

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
  output/summary.php         Renderable shell — ships labels + config JSON only
  output/renderer.php        render_from_template wrapper
  external/                  get_block_dataset, toggle_favourite,
                             set_return_context (one class each)
  privacy/provider.php       Exports/deletes core_favourites rows
db/                          access, services, uninstall  (NO install.xml)
templates/                   summary (server-rendered shell), plan_card,
                             competency_card, filters (client-rendered)
amd/src/                     filters.js (fetch + render), state.js (pure state
                             fns), filter_tabs_nav.js — plain AMD, NOT ESM here
amd/build/                   Committed minified output (grunt) — keep in sync
lang/{en,pt_br}/             Both kept in sync, alphabetically sorted
tests/                       PHPUnit: dataset_provider (double pattern),
                             external functions, privacy, generator
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

## Behat (JS) — CI-only
No local Behat: a new `.feature` is first exercised in CI, so budget one
fix-and-repush; keep scenarios as thin smoke tests and put logic in PHPUnit.
See `local_dimensions/CLAUDE.md` for the hard-won locator gotchas
(autocomplete, dialogs, checkbox labels).

## Cross-DB SQL
CI runs PostgreSQL and MariaDB. Placeholders / `get_in_or_equal` only; avoid
`ORDER BY … NULLS FIRST`; cast numeric DB reads when typing matters.

## Git / version.php / release
Run git from the plugin dir (or `git -C`) — `cd` doesn't persist between Bash
calls. Keep `CHANGELOG.md` (`## Unreleased` → `### Fixed/Added/Changed`)
updated with every substantive change. When rebasing conflicts on
`$plugin->version`, keep the **higher** number so the upgrade still triggers.

## When in doubt
Follow the patterns in existing files. The codebase is internally consistent —
if a new file feels like it matches no existing shape, re-examine the approach.

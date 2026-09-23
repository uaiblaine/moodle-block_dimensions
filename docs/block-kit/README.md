# Block kit — Dimensions block (`block_dimensions`)

An **as-is visual replica** of the plugin's single learner-facing surface: the Dimensions block as it
renders on a dashboard — the header and search, the client-built filter bar, the two card types with
their competency trail, and every load / error / empty state in between.

It follows the method of the sibling plugin's kits (`local/dimensions/docs/learner-kit/` and
`docs/design-kit/`): every `.html` is a self-contained preview carrying a `@dsCard` marker on its
first line, and every significant element gets an **ID badge** (`.idb`) in a caption line for review.

Each screen inlines one canonical `--bk-*` **token block** and renders the markup twice — a **light**
panel (`.panel`) and a **dark** panel (`.panel.dark`). Both are **as-is**, and what "as-is" means here
changed on 2026-09-05. `styles.css` no longer ships a skin of its own. It declares **34 colour tokens
on `body`**, 30 of them three-rung chains `var(--bs-NEW, var(--BS4-OLD, #literal))`, so 31 of
the 34 simply *are* core's own Bootstrap values and flip because Moodle 5.1/5.2 already compile a
complete `[data-bs-theme="dark"]` block. The plugin's one activation rule,
`body[data-bs-theme="dark"], [data-bs-theme="dark"] body`, assigns exactly **three** tokens:
`shadow`, `scrim` and `favourite`.

So the two panels are **one contract resolved twice**, not two authored palettes: the light column is
what core's `:root` / `[data-bs-theme="light"]` values make the tokens, the dark column is what its
`[data-bs-theme="dark"]` values make them. The markup is still written once with `var(--bk-*)`; only
the token values differ between panels. Two screens deviate and say so on their label: `states.html`
shows a state matrix rather than a screen, and `responsive.html` pairs *mobile* against *narrow block
column* instead of light against dark.

**The `.theme-dark` / `body.dark` skin this file used to cite is gone** — 77 + 77 rules keyed off
selectors that nothing in Moodle 4.5, 5.0, 5.1, 5.2 or 5.3-dev, and nothing in `theme_boost_union` or
`theme_boost_union_fundaseg`, has ever emitted. They were dead code and were deleted, not rewired.
Moodle **5.3** core does write `data-bs-theme` on the `<html>` element, from `theme_boost\colour_mode`
via `before_html_attributes`, gated behind `theme_boost/enablecolourmodes` and **off by default** —
core's own comment gives the reason, that a plugin which has not been checked in dark mode can still
draw its pages in light colours. `theme_moove` writes the same attribute on `body`, which is why the
tokens and both activation selectors sit on `body`: Bootstrap redefines the `--bs-*` set on whichever
element carries the attribute, and a token declared on `html` would keep html's light values under a
theme that writes it one level down. There is also an `@media (prefers-color-scheme: dark)` block in
`styles.css`, and it is **deliberately inert**: every selector in it is gated on
`[data-dimensions-media-optin]`, which nothing in either plugin ever writes, and a PHPUnit test fails
the build if it ever becomes reachable.

**As-is only — and the as-is has moved four times.** There are no to-be panels. On **2026-07-27** the
plugin's palette was migrated to Moodle DS (every colour, both card gradients, one consolidated focus
ring) and the horizontal card's title/star collision was fixed. On **2026-09-05** the literals that
migration produced were replaced wholesale by the 34-token contract above (declared on `:root` at
the time), the dead `.theme-dark` / `body.dark` layer was deleted, the focus indicator converged on one outline shape,
and the container-query layer went with the plugin's own `.stylelintrc.json`. The kit was re-baselined
after each, so what the panels show is what `styles.css` ships today. On **2026-09-22** the status
filter arrived (its own screen, `screens/plan-status.html`), both card grids moved from a flex row
to `repeat(auto-fill, minmax(...))` tracks - a flex row stretched the last card of an odd row to the
full width - and the tag strip's `position: absolute` was restored, which a later stacking rule had
overwritten with `relative`, cutting the strip in half on the horizontal card. `token-migration.md` is the
record of both changes, and of which of the first migration's open questions the second one closed.
On **2026-09-23** the raised-contrast blocks began to match at all (they asked for
`prefers-contrast: high`, a value the feature does not define, and ask for `more` now), every card
grid's track floor became `min(<width>, 100%)` so no track is wider than the block, the favourite
star moved onto an opaque `surface` disc, the preference overrides that lost to their base rules
on specificity were raised to the base rules' depth, and the token block and the activation rule
moved from `:root` to `body`; the last section of `token-migration.md` records these. The same day
the status filter changed shape: the block opens on the first bucket holding any plan, the Active
pill is drawn whenever the learner has an active plan, and a search or filter that hides every card
says so. Later that day the plan card took the competency card's raised-contrast and print
treatment, a checked status pill's count badge took the brand-tint fill every other checked pill
already had, the access pill stopped regaining its shadow under raised contrast while its card is
hovered or focused, and the stars stopped being drawn while favourites are disabled. The panels
were re-baselined for each.

**Two behaviours the second re-baseline genuinely lost**, recorded here rather than quietly dropped:
the horizontal plan card no longer stacks on a narrow *block column* at a wide viewport (the
`@container dims-card-cell` copy is gone; only the `@media (max-width: 575.98px)` one remains), and
the card list's column count lost its `minmax(clamp(...))` cap (it is an auto-fill grid again since
2026-09-22, with a `min(<width>, 100%)` floor since 2026-09-23). Both are consequences of Moodle's
own stylelint config, which the deleted `.stylelintrc.json` had been suppressing.

## Foundations
| File | What it is |
|---|---|
| `tokens.html` | The 34-token contract `styles.css` declares on `body`, tokenized as `--bk-*` and resolved **three** ways per row — 5.x light, 5.x dark and the Moodle 4.5 fallback — because one declaration has to be right on all three. Covers the four tiers (surfaces/ink/accent, the six tone families, brand-as-fill, and the four core cannot supply), the two admin-data names deliberately excluded from the token system, both card gradients, status colours, radii and the type scale. Also the source of the canonical scaffold every screen inlines. |
| `token-migration.md` | Repo-only companion: the record of **both** implemented migrations — 2026-07-27 Material/Google → Moodle DS (what moved, to what, the contrast corrections the swap forced, the defects fixed in passing, the eight repairs that completed the then-dark skin) and 2026-09-05 literals → the 34-token contract, which closed three of the five questions the first had left open and answered a fourth by deleting the thing it was about — then the 2026-09-23 re-baseline, which moved the contract from `:root` to `body`. |
| `screens/states.html` | Interactive states and focus as they really ship: hover, focus, high contrast, reduced motion, print, and the new `forced-colors: active` block. Now documents **one** focus indicator, full stop — the same `outline: 2px solid var(--…-focus-ring); outline-offset: 2px` in both modes, with two documented `-2px` exceptions; the block root, which takes focus only after a focused Retry, has no rule of its own and shows the browser's default ring. It used to be four blues in light and two in dark, then one blue that eleven of thirteen indicators kept on a dark surface at 2.15:1. |

## Screens (`screens/`)
| File | Screen |
|---|---|
| `screens/shell.html` | Block shell (`BLK`) — the only server-rendered HTML: heading, search row, filter toggle, loading, error + retry, section headers, grids, empty state, live regions, no-JS notice |
| `screens/filters.html` | Filter bar (`FLT`) — favourites/all pills with counts, the pill platter with its sliding indicator, mask and paddles, the dropdown variant, clear filters |
| `screens/plan-card-vertical.html` | Plan card (`PLN`) — vertical layout: image on top, tags, access pill, favourite star, title, count text, horizontal competency trail with continuation stubs |
| `screens/plan-card-horizontal.html` | Plan card (`PLN-H`) — horizontal layout: 180px image column, vertical trail, access pill top-left, star top-right, and the narrow-viewport stack |
| `screens/competency-card.html` | Competency card (`CMP`) — gradient plus halftone, custom colours, tags, access pill, 2-line clamped title |
| `screens/empty-error.html` | States (`BLK` · `GST`) — loading, error, the three empty-state messages, the assertive favourite-error announcement, no-JS, and the two-phase-loading ghost card |
| `screens/responsive.html` | Responsive (`RSP`) — the 575.98px breakpoint, the collapsible filter panel, and the auto-fill card grid that responds to the block column rather than the viewport |
| `screens/plan-status.html` | Plan status (`FLT-STATUS` · `PLN-STATUS`) — the three status buckets: the pill radiogroup, the busy pill and its skeleton cards, a bucket that is not drawn, the Active pill without a count, and the three card chips |

`screens/states.html` also lives in this folder but is grouped as a **Foundation** (see the table
above), because it documents the interaction vocabulary rather than a surface. Its `@dsCard` group
says `Foundations`, which is what the Design System index reads.

## ID convention
Format `PREFIX-SECTION[-NN]`, **stable** across re-syncs. Prefixes:

| Prefix | Surface |
|---|---|
| `BLK` | the block shell — header, search, sections, grids, load/error/empty states, live regions |
| `FLT` | the filter bar (built by `filters.js`; no mustache template renders it) |
| `PLN` / `PLN-H` | plan card, vertical / horizontal layout |
| `CMP` | competency card |
| `GST` | the ghost card ("View more items") |
| `STA` | interaction states |
| `RSP` | responsive behaviour |

Every interactive element and every meaningful static region gets an ID; pure layout wrappers do not.
An element that exists in both card layouts is shown under both prefixes — the plan title is
`PLN-TITLE` in the vertical screen and `PLN-H-TITLE` in the horizontal one, because the two branches
of `plan_card.mustache` are separate markup, not one shared block.

## Field maps (`maps/`) — repo-only
An as-is inventory per surface: each element with its **stable ID**, label, type, **source**
(`mustache:line` / `js:line` / `styles.css:line`), the data it carries, and its business rule. These
stay in the repo; they are not synced to Claude Design.

| File | Surface |
|---|---|
| `maps/block.md` | `BLK` · `FLT` · `GST` · `RSP` — shell, filter bar, ghost card, responsive |
| `maps/cards.md` | `PLN` · `PLN-H` · `CMP` — both card types and the competency trail |

**Foundations are not mapped**, by method — the same split the sibling kits use, where `maps/` covers
screens and the Foundations files stand on their own. So `tokens.html` has no map, and neither does
the `STA-*` family in `states.html`: a state is not a field, it has no data and no business rule, and
its "source" is a CSS rule the file already quotes inline. Every ID in the seven mapped surfaces
resolves to a row; the twelve `STA-*` badges resolve to `states.html` itself.

## Code mapping
- `block_dimensions.php` → `\block_dimensions\output\summary` → `summary.mustache`. The block renders
  only for logged-in non-guests and only when `core_competency` is enabled; `has_content()` returns
  false when the user holds no plan in any status bucket (active, waiting for or in review,
  completed; a plain draft does not count), so the block does not appear at all - except in editing
  mode, where core keeps an empty block on the page with its controls so it can still be moved or
  removed. A failure reading the plan list answers true, so the shell still renders and the web
  service shows its own error.
- `summary.mustache` is a **shell**. `export_for_template()` ships `labelsjson`,
  `filtersettingsjson` and a handful of flags — no cards.
- `amd/src/filters.js` calls the web service `block_dimensions_get_block_dataset` and renders
  `plan_card` / `competency_card` client-side through `core/templates`. **Two-phase loading**: the
  first request asks for favourites only, then `loadgroup` (`plan` / `competency`) fetches the missing
  group, always from the active bucket. **Status buckets**: plan cards are scoped to one of three
  buckets (`planstatus` = `active` / `review` / `complete`); the first request names none, so the
  server opens on the first bucket holding any plan, and every other bucket is fetched on first use
  and kept. Competency cards belong to the active plans whichever bucket is on screen.
  `amd/src/state.js` holds the pure filter/search predicates;
  `amd/src/filter_tabs_nav.js` adds the platter's mask, sliding indicator and scroll paddles.
- The **filter bar is not a template** — `renderFilterControls()` in `amd/src/filters.js` builds it
  by string concatenation, and it is the only definition of the bar's markup; its docblock carries the
  reason every pill set is a radiogroup rather than a tablist. The kit replicates that JS output.
- Card metadata (images, tags, colours, trail data) comes exclusively from `local_dimensions` MUC
  caches — `competency_metadata_cache`, `template_metadata_cache`, `plan_trail_cache`. This plugin
  owns no metadata of its own.
- Favourites are core `favourite` rows in the **user context** under component `block_dimensions`,
  itemtypes `plan` / `competency`, written by `block_dimensions_toggle_favourite`. A card draws its
  star only while favourites are enabled (`showfavourite`, from `dataset_provider::favourite_fields()`),
  and a plan card only in the active bucket.
- Styles: `styles.css` (2374 lines; component rules are scoped under `.block_dimensions`, except
  the mobile open-panel rule, keyed on `.block-dimensions-content.dims-filters-open`, and the
  polyfill below; the token block itself is on `body` on purpose — `body` is the ancestor of every
  node the plugin paints, including anything appended to `document.body`, it carries the colour-mode
  attribute under a theme that writes it there, and an unresolved `var()` does not fall back to its
  literal, it invalidates the whole declaration). One activation rule,
  `body[data-bs-theme="dark"], [data-bs-theme="dark"] body`, carrying three tokens, one inert
  `@media (prefers-color-scheme: dark)` block, plus `prefers-contrast: more`,
  `forced-colors: active`, `prefers-reduced-motion: reduce` and `@media print` variants throughout.
  A Bootstrap 4 utility polyfill sits at the tail, gated on a `.block-dimensions-bs4` class the
  summary renderable adds to the **block's own root** when `$CFG->branch < 500` — a block cannot use
  a body class, because `theme/boost/layout/columns2.php` calls `body_attributes()` one line before
  the `blocks()` call that invokes `get_content()`.

## Security invariant the replica preserves
`bgcolor`, `textcolor` and `imageurl` are interpolated into `style="..."` attributes on the cards.
Mustache `{{ }}` escaping does **not** protect a CSS context, so every value bound to a style
attribute passes `dataset_provider::sanitize_color()` (hex only) or `sanitize_image_url()`
(`clean_param PARAM_URL`, then every character that can end a CSS string or `url()` token — `'`,
`"`, `(`, `)`, `\` and whitespace — percent-encoded, because `PARAM_URL` accepts a quote and
parentheses in the query and the fragment), and the web service returns URLs as `PARAM_URL`, never
`PARAM_RAW`. The card screens show the custom-colour variants; the maps record the guards. Keep both
in sync if a new style-bound field is added.

## What now pins these claims, and what does not

The kit is prose, and prose has failed this defect class before — in the sibling plugin a Bootstrap
class-vocabulary bug shipped three times, was correctly root-caused each time, and recurred anyway
with CI fully green. As of 2026-09-05 the parts of this kit that describe the colour system are
backed by tests that fail the build, so a future change cannot silently falsify them again:

- `tests/local/colour_tokens_test.php` — 21 methods, each naming the mutation that must redden it.
  It pins the exact 34 declarations by string equality (not a shape regex, because a pattern cannot
  prove a chain terminates in a literal, and the terminating literal *is* the plugin's Moodle 4.5
  behaviour); the suffix list and its byte-identity with `local_dimensions`, and that CI checks the
  sibling out so that comparison runs; that the activation block assigns only the three
  plugin-owned tokens and that every colour-mode selector has `body` as its subject; that the media
  block is unreachable; contrast floors in all three resolutions; that the favourite star sits on an
  opaque ground; that no focus indicator is drawn with a `box-shadow` or in a brand colour; that the
  admin-colour names are never declared by the mode layer and still reach the stylesheet; that the
  plugin never writes the host's colour-mode signal; and that every token read is a token declared.
- `tests/local/bootstrap_compat_test.php` — 8 methods: every BS5 utility used is polyfilled, the
  polyfill carries nothing unused, no deprecated BS4 class names, badges state their text colour,
  data-API attributes are paired, no `--mds-*` squatting on core's design-system namespace, the
  block root actually carries the Bootstrap marker, and Mustache docblock prose is not scanned as
  markup.
- `tests/local/card_layout_test.php` — 9 methods on layout claims no linter reads: both card grids
  are auto-fill tracks, no track floor exceeds the block, every preference query asks for a value
  the feature defines, no preference override is outranked by its base rule (print blocks
  included), a property a preference switches off stays off in every state (the access pill's
  shadow while its card is hovered or focused), the two card shells share their raised-contrast
  and print treatment, a checked pill's count badge stands off the indicator for every pill class
  `filters.js` draws, every transform has a reduced-motion reset that wins, and the tag strip
  stays `position: absolute`.
- `tests/behat/colour_mode.feature` — 4 scenarios, the block's first Behat coverage. B1 drives the
  host into dark and asserts the card still matches the page; **B2 is the anti-vacuity control**,
  asserting it does *not* go dark unasked; B3 checks a pill deep inside a card built after page load
  by JS; B4 checks the three decorative tokens flip.
- `tests/behat/visibility.feature` — 6 scenarios on which learners see the block at all and which
  status bucket it opens on, including a learner with no active plan landing on their completed
  plans.
- `tests/behat/status_filter.feature` — 2 scenarios on the status pills with a learner whose active
  plan is shown as competency cards: the Active pill stays on offer, without a count, so the
  completed plans and the competency cards are both reachable; and a search typed on the completed
  bucket fetches the competency cards a favourites-only first load left out, which only works
  because that fetch names the active bucket.

What is **not** pinned by anything, and should be read with that in mind: the `file:line`
citations in `maps/*.md` and in the screens' ID legends. Every one of them — `styles.css`, the
templates, the AMD modules, the PHP classes and the lang file — was re-derived by hand against the
working tree on 2026-09-23, and any later edit to a cited file can move them again. Where a section
rather than one rule is the honest anchor, the citation is a banner name
(`styles.css · CARD GRID banner`), which does not rot when a rule moves.

## Release packaging
`docs/` is **excluded from the release zip** via `.gitattributes` (`/docs export-ignore`). The kit is
versioned for collaboration; `git archive HEAD` — which builds the installable zip — ships only what
Moodle needs at runtime. Verify with `git check-attr export-ignore -- docs`.

## Note on writing files here
The plugin's CI runs a development-leftover checker that greps **every** file in the repo, docs
included, and fails the build on a stray to-do or merge-conflict marker. Never write those tokens
literally in this folder.

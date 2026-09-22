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
on bare `:root`**, 30 of them three-rung chains `var(--bs-NEW, var(--BS4-OLD, #literal))`, so 31 of
the 34 simply *are* core's own Bootstrap values and flip because Moodle 5.1/5.2 already compile a
complete `[data-bs-theme="dark"]` block. The plugin's one activation rule,
`:root[data-bs-theme="dark"]`, assigns exactly **three** tokens: `shadow`, `scrim` and `favourite`.

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
draw its pages in light colours. There is also an `@media (prefers-color-scheme: dark)` block in
`styles.css`, and it is **deliberately inert**: every selector in it is gated on
`[data-dimensions-media-optin]`, which nothing in either plugin ever writes, and a PHPUnit test fails
the build if it ever becomes reachable.

**As-is only — and the as-is has moved three times.** There are no to-be panels. On **2026-07-27** the
plugin's palette was migrated to Moodle DS (every colour, both card gradients, one consolidated focus
ring) and the horizontal card's title/star collision was fixed. On **2026-09-05** the literals that
migration produced were replaced wholesale by the 34-token `:root` contract above, the dead
`.theme-dark` / `body.dark` layer was deleted, the focus indicator converged on one outline shape,
and the container-query layer went with the plugin's own `.stylelintrc.json`. The kit was re-baselined
after each, so what the panels show is what `styles.css` ships today. On **2026-09-22** the status
filter arrived (its own screen, `screens/plan-status.html`), both card grids moved from a flex row
to `repeat(auto-fill, minmax(...))` tracks - a flex row stretched the last card of an odd row to the
full width - and the tag strip's `position: absolute` was restored, which a later stacking rule had
overwritten with `relative`, cutting the strip in half on the horizontal card. `token-migration.md` is the
record of both changes, and of which of the first migration's open questions the second one closed.

**Two behaviours the second re-baseline genuinely lost**, recorded here rather than quietly dropped:
the horizontal plan card no longer stacks on a narrow *block column* at a wide viewport (the
`@container dims-card-cell` copy is gone; only the `@media (max-width: 575.98px)` one remains), and
the card list's column count is now capped by a percentage flex basis rather than by
`minmax(clamp(...))`. Both are consequences of Moodle's own stylelint config, which the deleted
`.stylelintrc.json` had been suppressing.

## Foundations
| File | What it is |
|---|---|
| `tokens.html` | The 34-token `:root` contract `styles.css` ships, tokenized as `--bk-*` and resolved **three** ways per row — 5.x light, 5.x dark and the Moodle 4.5 fallback — because one declaration has to be right on all three. Covers the four tiers (surfaces/ink/accent, the six tone families, brand-as-fill, and the four core cannot supply), the two admin-data names deliberately excluded from the token system, both card gradients, status colours, radii and the type scale. Also the source of the canonical scaffold every screen inlines. |
| `token-migration.md` | Repo-only companion: the record of **both** implemented migrations — 2026-07-27 Material/Google → Moodle DS (what moved, to what, the contrast corrections the swap forced, the defects fixed in passing, the eight repairs that completed the then-dark skin) and 2026-09-05 literals → the 34-token contract, which closed three of the five questions the first had left open and answered a fourth by deleting the thing it was about. |
| `screens/states.html` | Interactive states and focus as they really ship: hover, focus, high contrast, reduced motion, print, and the new `forced-colors: active` block. Now documents **one** focus indicator, full stop — the same `outline: 2px solid var(--…-focus-ring); outline-offset: 2px` in both modes, with two documented `-2px` exceptions. It used to be four blues in light and two in dark, then one blue that eleven of thirteen indicators kept on a dark surface at 2.15:1. |

## Screens (`screens/`)
| File | Screen |
|---|---|
| `screens/shell.html` | Block shell (`BLK`) — the only server-rendered HTML: heading, search row, filter toggle, loading, error + retry, section headers, grids, empty state, live regions, no-JS notice |
| `screens/filters.html` | Filter bar (`FLT`) — favourites/all pills with counts, the pill platter with its sliding indicator, mask and paddles, the dropdown variant, clear filters |
| `screens/plan-card-vertical.html` | Plan card (`PLN`) — vertical layout: image on top, tags, access pill, favourite star, title, count text, horizontal competency trail with continuation stubs |
| `screens/plan-card-horizontal.html` | Plan card (`PLN-H`) — horizontal layout: 180px image column, vertical trail, access pill top-left, star top-right, and the container-query stack |
| `screens/competency-card.html` | Competency card (`CMP`) — gradient plus halftone, custom colours, tags, access pill, 2-line clamped title |
| `screens/empty-error.html` | States (`BLK` · `GST`) — loading, error, both empty states, the assertive favourite-error announcement, no-JS, and the two-phase-loading ghost card |
| `screens/responsive.html` | Responsive (`RSP`) — the 575.98px breakpoint, the collapsible filter panel, and the container queries that respond to the block column rather than the viewport |
| `screens/plan-status.html` | Plan status (`FLT-STATUS` · `PLN-STATUS`) — the three status buckets: the pill radiogroup, the busy pill and its skeleton cards, a bucket that is not drawn, and the three card chips |

`screens/states.html` also lives in this folder but is grouped as a **Foundation** (see the table
above), because it documents the interaction vocabulary rather than a surface. Its `@dsCard` group
says `Foundations`, which is what the Design System index reads.

## ID convention
Format `PREFIX-SECTION[-NN]`, **stable** across re-syncs. Prefixes:

| Prefix | Surface |
|---|---|
| `BLK` | the block shell — header, search, sections, grids, load/error/empty states, live regions |
| `FLT` | the filter bar (built by `filters.js`, not by a mustache template at runtime) |
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
  false when the user has no active plan, so the block does not appear at all - except in editing
  mode, where core keeps an empty block on the page with its controls so it can still be moved or
  removed.
- `summary.mustache` is a **shell**. `export_for_template()` ships `labelsjson`,
  `filtersettingsjson` and a handful of flags — no cards.
- `amd/src/filters.js` calls the web service `block_dimensions_get_block_dataset` and renders
  `plan_card` / `competency_card` client-side through `core/templates`. **Two-phase loading**: the
  first request asks for favourites only, then `loadgroup` (`plan` / `competency`) fetches the missing
  group. `amd/src/state.js` holds the pure filter/search predicates;
  `amd/src/filter_tabs_nav.js` adds the platter's mask, sliding indicator and scroll paddles.
- The **filter bar is not a template at runtime** — `renderFilterControls()` builds it by string
  concatenation. `templates/filters.mustache` is a documentation-only mirror kept in sync; editing it
  changes nothing on screen. The kit replicates the JS output, not the template.
- Card metadata (images, tags, colours, trail data) comes exclusively from `local_dimensions` MUC
  caches — `competency_metadata_cache`, `template_metadata_cache`, `plan_trail_cache`. This plugin
  owns no metadata of its own.
- Favourites are core `favourite` rows in the **user context** under component `block_dimensions`,
  itemtypes `plan` / `competency`, written by `block_dimensions_toggle_favourite`.
- Styles: `styles.css` (2307 lines; every selector below the token block is scoped under
  `.block_dimensions`, and the token block itself is on bare `:root` on purpose — `:root` is the
  ancestor of every node in the document, including anything core relocates to `document.body`, and
  an unresolved `var()` does not fall back to its literal, it invalidates the whole declaration).
  One `:root[data-bs-theme="dark"]` activation rule carrying three tokens, one inert
  `@media (prefers-color-scheme: dark)` block, plus `prefers-contrast: high`,
  `forced-colors: active`, `prefers-reduced-motion: reduce` and `@media print` variants throughout.
  A Bootstrap 4 utility polyfill sits at the tail, gated on a `.block-dimensions-bs4` class the
  summary renderable adds to the **block's own root** when `$CFG->branch < 500` — a block cannot use
  a body class, because `theme/boost/layout/columns2.php` calls `body_attributes()` one line before
  the `blocks()` call that invokes `get_content()`.

## Security invariant the replica preserves
`bgcolor`, `textcolor` and `imageurl` are interpolated into `style="..."` attributes on the cards.
Mustache `{{ }}` escaping does **not** protect a CSS context, so every value bound to a style
attribute passes `dataset_provider::sanitize_color()` (hex only) or `sanitize_image_url()`
(`clean_param PARAM_URL`), and the web service returns URLs as `PARAM_URL`, never `PARAM_RAW`. The
card screens show the custom-colour variants; the maps record the guards. Keep both in sync if a new
style-bound field is added.

## What now pins these claims, and what does not

The kit is prose, and prose has failed this defect class before — in the sibling plugin a Bootstrap
class-vocabulary bug shipped three times, was correctly root-caused each time, and recurred anyway
with CI fully green. As of 2026-09-05 the parts of this kit that describe the colour system are
backed by tests that fail the build, so a future change cannot silently falsify them again:

- `tests/local/colour_tokens_test.php` — 17 methods, each naming the mutation that must redden it.
  It pins the exact 34 declarations by string equality (not a shape regex, because a pattern cannot
  prove a chain terminates in a literal, and the terminating literal *is* the plugin's Moodle 4.5
  behaviour); the suffix list and its byte-identity with `local_dimensions`; that the activation
  block assigns only the three plugin-owned tokens and is anchored at `:root`; that the media block
  is unreachable; contrast floors in all three resolutions; that no focus indicator is drawn with a
  `box-shadow` or in a brand colour; that the admin-colour names are never declared by the mode
  layer; and that every token read is a token declared.
- `tests/local/bootstrap_compat_test.php` — 7 methods: every BS5 utility used is polyfilled, the
  polyfill carries nothing unused, no deprecated BS4 class names, badges state their text colour,
  data-API attributes are paired, no `--mds-*` squatting on core's design-system namespace, and the
  block root actually carries the Bootstrap marker.
- `tests/behat/colour_mode.feature` — 4 scenarios, the block's first Behat coverage. B1 drives the
  host into dark and asserts the card still matches the page; **B2 is the anti-vacuity control**,
  asserting it does *not* go dark unasked; B3 checks a pill deep inside a card built after page load
  by JS, inside the theme's own block drawer; B4 checks the three decorative tokens flip.

What is **not** pinned by anything, and should be read with that in mind: the `styles.css:NNN` line
citations. The 2026-09-05 rewrite moved roughly 1400 lines, and the citations in `maps/*.md` and in
the screens' ID legends were **not** individually re-verified against the new file. Where a citation
was load-bearing for a claim this re-baseline touched it has been replaced with a banner name
(`styles.css · CARD GRID banner`), which does not rot when a rule moves. The rest still carry their
2026-07-27 numbers and should be treated as approximate until someone re-derives them; the element
names and selectors beside them are current.

## Release packaging
`docs/` is **excluded from the release zip** via `.gitattributes` (`/docs export-ignore`). The kit is
versioned for collaboration; `git archive HEAD` — which builds the installable zip — ships only what
Moodle needs at runtime. Verify with `git check-attr export-ignore -- docs`.

## Note on writing files here
The plugin's CI runs a development-leftover checker that greps **every** file in the repo, docs
included, and fails the build on a stray to-do or merge-conflict marker. Never write those tokens
literally in this folder.

# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

### Added
- **A plan status filter, with every bucket but the active one loaded on demand.** The plan grid now
  groups plans as *Active*, *In review* (both review statuses, the way core groups its own draft
  statuses) and *Completed*. Only the active bucket arrives with the page; another bucket is fetched
  the first time the learner asks for it and kept afterwards, so a second visit costs no request. A
  bucket with no plans is not drawn: an empty *In review* would read as "you have none" on the many
  sites where it means "you cannot see them", since a learner needs
  `moodle/competency:planviewowndraft` for that and no archetype holds it.

  The counts on the pills ride the first response at no extra query, because the provider already
  read the whole plan list; the active count leaves out a competencies-mode template, which becomes
  competency cards rather than a plan card, so the pill cannot promise a card the grid never shows.
  Outside the active bucket every plan renders as a plan card whatever its template's display mode,
  carries a status chip (`Completed on <date>`, `Waiting for review`, `In review`), offers *View
  plan* instead of *Continue*, and has no favourite star: favourites are for work in progress. A
  completed plan's trail reads the ratings core froze at completion (`local_dimensions`
  2026092200), so the card agrees with core's own plan page. Search scopes to the bucket on screen.

  The pills are part of the filter bar, so on a phone they appear with the rest of the filters when
  the panel is opened. The block opens on the first bucket that has plans: a learner whose plans
  have all finished lands on them, rather than on an empty Active bucket with a notice. The web
  service resolves an empty `planstatus` through `dataset_provider::opening_bucket()`, and every
  later request names its bucket.

  New web-service parameter `planstatus` with per-bucket counts in the response, new AMD state axis
  with a skeleton while a bucket loads, new strings in both languages, and a `version.php` bump. The
  status pills are pinned by `dimensions_test`, `get_block_dataset_test`, `summary_test` (the labels
  the client draws) and a `@javascript` scenario in `visibility.feature`.

### Fixed
- **Card tag pills had not rendered since the move to the web service.** The provider builds `tags`
  and `hastags` and both card templates draw them, but `execute_returns()` never declared either, so
  `clean_returnvalue()` stripped them - silently, the way an allowlist always does. Declared for both
  card types and pinned by a test that compares the raw dataset with the cleaned one.
- **The last card of a row stretched to the full width.** The plan grid was a flex row, and a flex
  item grows to fill the line, so a lone fourth card was three times the width of the three above it.
  Both grids now lay out on `repeat(auto-fill, minmax(...))` tracks, which keep the empty columns.
- **The tag strip was cut in half on the horizontal card.** A rule lifting the strip above the
  stretched-link overlay re-declared `position: relative`, which put it back in the flow, where the
  image wrapper's `overflow: hidden` clipped it. Only the stacking belongs in that rule.
- **The status pills drew their internal keys.** The new labels existed in both language files but
  were not in the payload the client renders from, so the pills read "active" and "complete".
  `summary_test` now asserts every label the client draws is shipped.

### Changed
- **The block renders nothing for a user with no plan it can show, as `block_lp` does.**
  `get_content()` calls `summary::has_content()` again, and core drops the empty block from the
  page; in editing mode it stays, with its controls, so it can still be moved or removed. The gate
  was in the initial commit and was lost before 1.0, when the dataset moved to the web service
  (`f4806ef`), which left the method in place; the block-kit docs, written later, described the
  gate from that method alone.
  A plan counts when its status is one of the buckets the status filter carries - active, waiting
  for review, in review, completed - which is the `BUCKET_STATUSES` constant beside the check.
  A plain draft does not: no bucket shows one, so the block would open empty. (The gate landed
  active-only and was widened the same day, when option B of the status-filter proposal was chosen;
  `docs/proposals/2026-09-22-status-filter/` records why.) `dimensions_test` pins every plan status,
  another user's plan and a plan the viewer may not read; the draft case grants
  `moodle/competency:planviewowndraft` first, since without it a learner's own draft never reaches
  the plan list and the case would pass having tested nothing. `visibility.feature` covers the
  Dashboard end to end, editing mode included.
- **A failure reading the plan list can no longer take the page down.** The gate added above runs
  during the page render, where core catches nothing, so a database error in it would have
  replaced the whole Dashboard with an error page. `summary::has_content()` now catches it, logs it
  with `error_log()` and fails open: the shell renders as it did before the gate, and the web
  service's own error box, with its retry button, reports a failure that persists. `debugging()` is
  deliberately not used there: under developer debugging with pretty exceptions, Whoops turns it
  into an exception during a page render, which is the very throw the catch prevents.

- **The colour token layer moved from `:root` to `body`, and the dark rule grew a second arm.**
  Thirty-one of the 34 are DERIVED tokens - each resolves a `var(--bs-*)` chain; the other three,
  `shadow`, `scrim` and `favourite`, are plugin-owned literals - and Bootstrap redefines the
  `--bs-*` set on whatever element carries `data-bs-theme`: its own `color-mode` mixin emits an
  UNANCHORED `[data-bs-theme="..."]` (`bootstrap/mixins/_color-mode.scss:16`), which is what makes
  scoped colour modes work at all. A derived layer pinned at `:root` takes a snapshot of the root's
  values and is then immune to every scope, including core's own; Bootstrap's own components never
  build one, they read `--bs-*` at the component. Counted in the compiled 5.2 sheet: of 32
  `[data-bs-theme="dark"]` rules, 28 are unanchored (Bootstrap and core) and the 4 anchored at
  `:root` were all this fleet's own plugins.

  The defect that exposed it is measured, not theoretical. `theme_moove` writes the attribute on
  `document.body` (`amd/src/darkmode.js:35`) and redefines the whole `--bs-*` set there. Under the
  old `:root` anchor, measured on m502 at 1440x900 through moove's own switch: the page went to
  `#1d2125` while `local_dimensions`' surface stayed `#f2f3f7`, and text inheriting the page's
  dark-mode colour sat at **1.17:1** on the plugin's own card, against a 4.5:1 AA floor. After the
  move, the same measurement reads **12.44:1** and every token flips.

  `body` rather than the plugin's surfaces because it is the one ancestor every surface has,
  including `core/modal`'s dialogue - which core appends to `document.body` as a SIBLING of the
  page container, the exact case that once left a dialogue with no background at all.

  The activation rule is now `body[data-bs-theme="dark"], [data-bs-theme="dark"] body`, and naming
  body as the SUBJECT is what still forecloses the leak the `:root` anchor existed to prevent:
  there is exactly one body and its only ancestor is html, so the second arm can only ever mean
  `html[data-bs-theme="dark"] body`. Both arms verified in the browser - the first through moove's
  switch, the second with the attribute on `<html>` as core writes it.

  `colour_tokens_test` moved with the contract: `token_block()` and `token_block_text()` read the
  `body` rule, `activation_block()` reads the new selector from a single `DARK_ACTIVATION_SELECTOR`
  constant shared with `contract_block_selectors()`, and
  `test_activation_selectors_are_root_anchored` was re-founded as
  `test_activation_selectors_have_body_as_subject` - same hazard, an answer that no longer costs
  the plugin every body-scoped host. Mutation-checked: a bare attribute selector reddens it, and so
  does putting the token block back on `:root`.

- **A 34-token colour layer, declared once on bare `:root`.** Prefix `--block-dimensions-*`, with
  a suffix set byte-identical to `local_dimensions`' — same names, different frankenstyle prefix,
  which is what makes the two plugins one system without letting either overwrite the other inside
  the single stylesheet Moodle compiles from every installed plugin. Thirty of the thirty-four are
  three-rung chains `var(--bs-NEW, var(--BS4-OLD, #literal))`: Moodle 4.5 declares no `--bs-*`
  names and Moodle 5.2 declares no Bootstrap 4 legacy names, so one chain is correct on every
  supported branch with no branch test anywhere in the file. `:root` is the only selector that
  covers every root the plugin can paint, and it matters because an unresolved `var()` is not a
  fallback to the literal — the whole declaration is invalid, so an undeclared token is *no*
  background rather than the default one.
- **Dark mode arrives for thirty of those tokens with no dark rule at all.** Moodle 5.1 and 5.2
  already compile a complete `[data-bs-theme="dark"]` block; nothing sets the attribute yet, so
  the values are dormant rather than absent. A plugin surface written as `--bs-body-bg` *is* the
  page's own background, so the two cannot disagree. Exactly three tokens carry a plugin-authored
  dark value — `shadow`, `scrim` and `favourite` — and that bound is the guarantee that this
  plugin can never paint a dark surface on a light page: the worst a wrongly firing activation
  block could do is deepen a shadow, darken a veil and brighten a star. The focus ring chains
  `--bs-emphasis-color`, which flips, and deliberately not `--bs-focus-ring-color`, which core
  fails to flip (1.02:1 on the dark page).
- **Activation is one selector: `:root[data-bs-theme="dark"]`.** That is Bootstrap 5.3's own
  colour-mode attribute and the one Moodle itself writes, from `theme_boost`'s
  `before_html_attributes` listener, gated behind `theme_boost/enablecolourmodes` (off by
  default). Anchoring at `:root` is not decoration: a bare `[data-bs-theme="dark"]` matches
  through any ancestor at any depth, and `theme_boost_union_fundaseg` really does set the
  attribute on the navbar, which `theme_boost_union` then has to re-pin to `light` on five nested
  templates. `:root` forecloses that whole class of leak at zero cost, and wins on specificity
  (0,2,0 against the token block's 0,1,0) rather than on source order.
- **An OS-preference fallback is written, correct, and provably unable to fire.** The
  `@media (prefers-color-scheme: dark)` block is gated on `[data-dimensions-media-optin]`, an
  attribute nothing in either plugin ever writes, and its selector also excludes an explicit
  `data-bs-theme="light"`. It exists because the OS preference is the wrong signal *on its own* —
  core reads it as an input to `data-bs-theme`, never as an independent trigger, and firing on it
  directly is how a plugin ends up dark inside a light page. Switching it on is one edit: delete
  the gate substring from the selector. It carries only the same three plugin-owned tokens, so
  even fully enabled it cannot produce a dark plugin on a light page.
- **The 77 `.theme-dark` and 77 `body.dark` rules are deleted — 430 lines.** Nothing in Moodle
  4.5, 5.1, 5.2 or 5.3-dev, and nothing in `theme_boost_union` or `theme_boost_union_fundaseg`,
  has ever emitted either class, so they had never rendered a single pixel since they were added.
  Their *values* were audited and defect-free, and are not lost: `#dee2e6` is the `ink` token's
  dark value, `#495057` the `line`'s, `#343a40` the `surface-inset`'s, `#adb5bd` the
  `ink-muted`'s, `#75b798` the `success-ink`'s and `#ea868f` the `danger-ink`'s — all supplied by
  core once the light rule reads the token. Two do not survive, deliberately: `#6ea8fe` is stock
  upstream Bootstrap's tint of *its* `$primary`, a colour no site here uses, and is replaced by
  the site's own `--bs-link-color`; and `#212529` is stock `$gray-900` while Moodle's real dark
  platter is `#343a40`. Keeping the selectors beside the new contract would have left three dark
  mechanisms in one file, two of them dead.
- **The admin-configured colours are untouched by the mode layer, in both modes.**
  `--dimension-custombgcolor` and `--dimension-customtextcolor` are not design tokens — they carry
  admin instance data across the plugin boundary — so the token block neither declares nor
  overrides them and the dark block does not mention them. A site's chosen brand colour stays its
  colour when the page goes dark; only the neutrals around it adapt.
- **Every colour in the stylesheet now reads a token: 132 of the 141 literal occurrences are
  gone, and 43 distinct values collapse to 4.** The mapping is semantic rather than textual — the
  same hex meant different things in different rules and its dark counterpart differs
  accordingly. `#6c757d` was a muted caption, a search glyph, a pending-ring stroke and a
  high-contrast fallback; the first three become `ink-muted` and the fourth stays a literal.
  `#e9ecef` was a tab platter, a select ground and a trail track — all `surface-inset` — but also
  a count-badge ground, which becomes `surface`. `#0f6cbf` was 27 occurrences across three
  distinct roles: a link-coloured label (`accent`), a solid fill under white text
  (`brand-fill`), and a focus ring (`focus-ring`).
- **The focus ring stops being blue, and that is the most visible change in this release.** It is
  near-black in light, near-white in dark, `#343a40` on 4.5 — 9.70:1 to 21.0:1 on every surface
  in every mode on every theme. The price is paid because a 3:1 obligation cannot be delegated to
  a colour the site owner picks: the site primary as a ring on the dark card measures 2.33:1 on
  the fleet's own theme, and core's `--bs-focus-ring-color` does not flip at all (1.02:1).
- **Four `outline: none`-only focus rules are deleted and two brand-coloured focus glows with
  them.** An author `outline: none` is not restored by the browser under `forced-colors: active`
  and a `box-shadow` is not rendered there at all, so a control whose only focus signal was one
  or the other had no indicator in Windows High Contrast Mode. The filter tab's inset box-shadow
  ring becomes a real `outline` at `outline-offset: -2px`, which draws in the same place and
  survives forced colours; the card links lose their suppressors and fall through to the
  `.stretched-link:focus-visible` ring both templates already give them. The favourite button
  moves from `:focus` to `:focus-visible`, so clicking the star no longer leaves a ring behind.
- **The favourite star changes hue: `#e8590c` light, `#fd7e14` dark.** `#ffc107` on white is
  1.63:1 and fails 1.4.11 outright. No single hue clears 3:1 on both white and the dark platter,
  and this is the only pair that does (3.58 / 3.40 / 3.02 light, 6.30 / 5.33 / 4.48 dark).
  Measurement picks it, not convention.
- **Six other deliberate, measured shifts a reviewer should sign off rather than discover.** The
  completed trail marker and its connector darken from `#198754` to `success-ink` (`#153114`
  light), which is the family's answer for a done marker. The filter count badge's rest ground
  moves from the platter's own value to `surface`, so the chip finally has a shape — it used to
  paint exactly the colour it sat on. The ghost card's `+` glyph goes from `#adb5bd` (2.07:1,
  below the 1.4.11 floor) to `ink-muted` (7.10:1). A dimension chip with no admin colour
  configured goes from a fixed orange to `surface-inset` under `ink`, because `ink` on that
  orange measures 1.97:1 once the page is dark; a chip that *is* configured is untouched. The two
  decorative card gradients become token gradients, warm for competencies and cool for plans. And
  the search input's placeholder takes `ink-muted` rather than the `ink-faint` its role suggests,
  because `ink-faint` measures 3.04:1 on that input's own ground in light mode.
- **The filter platter's seven colour variables are deleted.** `--dims-tabs-platter-bg`,
  `-indicator-bg`, `-indicator-shadow`, `-item-color`, `-item-color-active`, `-paddle-color` and
  `-paddle-color-hover` were a second naming layer over the same values, each with a hard-coded
  light literal no dark rule could have reached; the use sites read the tokens directly now. The
  metric variables `amd/src/filter_tabs_nav.js` reads are untouched.
- **The active favourite filter's hover darkens with `filter: brightness(0.92)` instead of a
  second colour.** No token in the system means "a darker version of the site's own primary":
  `accent-hover` is `--bs-link-hover-color`, which core flips to a *lighter* tint in dark mode,
  where white text on it would measure about 2:1. A relative brightness is correct in both modes
  and on any brand.
- **Nine literals remain, each for a stated reason.** Six `#000` plus one `#6c757d` and one
  `#ced4da` sit inside `@media (prefers-contrast: high)` and `@media print` blocks: high contrast
  is an orthogonal preference axis left alone this pass, and paper is white whatever the screen is
  doing — `data-bs-theme` stays on the html element while printing, so ink resolved through a mode
  token would print `#dee2e6` on white from a dark page. The ninth, `#00000082`, is the halftone
  overlay on the card gradient, which composites on the admin's own fill and is therefore inside a
  branded island the mode layer may not touch. The one exception made inside a
  `prefers-contrast` block is its focus outline, which moves to `focus-ring` — a focus indicator
  may never be a hard-coded colour, and `focus-ring` already resolves to `#000` there in light.
- **Thirteen of the thirty-four tokens are declared but not read here, and that is correct.** The
  suffix set is a family contract asserted byte-for-byte against `local_dimensions`, which has
  surfaces this plugin does not — evidence pills in the info and neutral tones, disabled controls
  for `ink-faint`, a hover state for `accent-hover`. They are not dead code to tidy away.
- **The shared stylelint gate is running again, and the 48 findings it was hiding are fixed.**
  `.stylelintrc.json` was three lines with one rule and no `extends`; stylelint's cosmiconfig
  takes the first config it finds walking up from the linted file and *replaces* Moodle's
  ~90-rule root config rather than merging with it, so `grunt` reported "Linted 1 files without
  errors" over a stylesheet nothing was checking. The file is deleted (`selector-class-pattern`
  is not a fleet rule and is absent from the root config). With it gone the same command
  reported 46 errors and 2 warnings; all 48 are now resolved.
- **Every focus indicator in the block is now one shape.**
  `outline: 2px solid var(--block-dimensions-focus-ring); outline-offset: 2px;`, on all fourteen
  of them, and the same shape the sibling plugin converges on. Three widths and offsets were in
  play: the competency and plan cards drew a 3px ring, the card title link drew its ring at a 4px
  offset, and everything else drew 2px at 2px — three answers to one meaning, on one page. The
  ring is no narrower than it reads: the token is the ink extreme (21.0:1 light, 16.2:1 dark,
  11.5:1 on 4.5), where the brand blue it replaced measured 2.15:1 on the dark card. The two
  surviving deviations are negative offsets, not different rings: the filter tab and the tabs
  indicator draw at `-2px` because their platter is `overflow: hidden` and would clip an outset
  ring, and both still draw a real `outline`, which is the property that survives forced colours.
- **The ghost card's focus ring is keyboard-only, like every other button in the block.** It was
  the last plain `:focus`; the favourite, clear-filters, retry and search-clear buttons were
  already `:focus-visible`, so a mouse click on this one alone left a ring behind.
- **A dead focus fallback and a dead `outline: none` are deleted.** The rule labelled "fallback
  for browsers without `:focus-visible` support" could never do any work: its first selector
  needed `.competency-card` to be a *descendant* of a focused `.competency-card-link`, and the
  template nests them the other way round, while its second selector — the `:has()` form — only
  matched when `:focus-within` already had, `:has()` being the newer feature of the two. The
  search input also carried `outline: none` in its rest state, left over from when its focus was
  drawn with a glow. Both are gone; the card's ring and the input's ring are unchanged.
- **The `prefers-contrast: high` blocks stop assuming a white page.** Every remedy in them that
  named an *extreme* — the competency card's border, its title colour, the filter select's border
  and the active favourite pill's border — was `#000`, which is the strongest edge available on
  the light page and measures 1.30:1 on the dark one. All four now read `ink-strong`
  (`--bs-emphasis-color`): `#000` light, `#fff` dark, 21.0:1 and 16.2:1 against the card face.
  The one remedy that names a *middle* stays a literal on purpose and now carries its numbers:
  the flat `#6c757d` that replaces the decorative card gradient is 4.69:1 on the light card face
  and 3.45:1 on the dark one, so it separates in both modes, where any token that flipped would
  turn the card's image area light on a dark page. The `@media print` literals stay literal for
  the same kind of reason, stated in the file: paper is white whatever the screen is doing.
- **Windows High Contrast Mode gets the one rule it actually needed.** `forced-colors: active`
  is a different media feature from `prefers-contrast: high` — it replaces every
  `background-color` on the page with a system colour — so the sliding white indicator behind the
  selected filter pill, and the filled background on the active favourites pill, both flattened
  into their neighbours and the selected filter became indistinguishable from the unselected ones
  (WCAG 1.4.1). A single `@media (forced-colors: active)` block gives both an `outline`, the one
  property the browser remaps rather than overwrites. Assistive technology was never affected:
  every pill is a `role="radio"` carrying `aria-checked`, kept in sync by `amd/src/filters.js`.
- **The dimension chip loses its drop shadow and gains a hairline.** That is the family's answer
  to "does a small pill carry a shadow", and here it is also the stronger of the two: a
  `box-shadow` is not painted at all under forced colours, so the chip's only separation from the
  card art behind it disappeared in exactly the mode that needs it most. The edge is
  `currentcolor` rather than a tone token, because this chip has no tone — its fill is the
  admin's own colour — and `currentcolor` is its text colour, which the admin chose against that
  fill (or, unconfigured, is `ink` on `surface-inset` at 13.66:1). It is also the colour the
  chip's own high-contrast rule already used, so the two agree now instead of only under a
  preference.
- **The Access / Continue pill's ink turns `accent` on hover and on card focus.** That is what
  the sibling plugin's inline "Continue" link paints at rest, so the two controls answer a hover
  with the same colour (5.36:1 light, 6.33:1 dark). Their *shapes* stay different on purpose: a
  filled pill has to stay legible over arbitrary photo art, and a quiet inline link must not
  compete with the section list beside it.
- **Two WCAG citations corrected and one claim softened.** The print section cited 1.4.10 Reflow,
  which is about 320 CSS px and 400% zoom and has nothing to do with paper; the pseudo-link card
  cited 4.1.1 Parsing, which WCAG 2.2 removed. Both citations are gone. The file header no longer
  claims blanket "WCAG 2.2 Accessibility Compliant" and says instead what it is: written against
  2.2 AA, with the criteria cited beside the rules that address them.
- **The card grid is flex, not CSS grid.** `grid-template-columns:
  repeat(auto-fill, minmax(clamp(240px, 30%, 500px), 1fr))` and the `container-type` /
  `@container` pair are all rejected outright by the gate's csstree validator. The replacement
  is the fleet's documented substitute — `flex: 1 1 30%` with a `min-width: 15rem` floor — which
  keeps the same two properties the grid was chosen for: a percentage basis is a percentage *of
  the container*, so a block in a narrow sidebar still lays out by its own width rather than the
  viewport's, and a 30% basis caps a row at three cards because a fourth would need 120%.
- **The horizontal plan card no longer restacks on container width alone.** Its
  `@container dims-card-cell (max-width: 360px)` block has no legal spelling under the gate and
  is deleted. The identical `@media (max-width: 575.98px)` rule is untouched, so the card still
  stacks on a small screen; what is lost is stacking in a narrow block on a wide screen.
- **Two trail SVGs and both select chevrons are drawn in CSS.** Eight inline `data:` URIs are
  banned by `function-url-scheme-disallowed-list`, and a colour inside a URL-encoded SVG can
  never be tokenised. The dashed trail continuation stubs become `repeating-linear-gradient`
  (9px dash on a 12px period horizontally, 7px on 9px vertically — matching the stroke geometry
  including its round caps), and the drop-down glyph on `.dims-filter-select` becomes two 4px
  triangles in `currentcolor`, which follows the control's own text colour and made the separate
  dark-mode image unnecessary.
- **`text-dark` and `text-success` removed from both card templates.** Bootstrap's text
  utilities are themselves `!important`, so the stylesheet could not win against them without
  matching in kind; the plugin's own classes now carry those colours. `text-dark` on a card
  title was independently a dark-mode defect, pinning near-black text that disappears on a dark
  card. The completed trail tone moves onto `.trail-item.completed .trail-label`, and the check
  glyph's colour onto `.trail-marker .fa-check-circle` — where the existing dark rule can now
  actually reach it, which it never could while the utility was in the markup.
- **The admin-chosen card fill is applied from a class, not an inline style.** The custom
  property already travelled on the `<article>`, so `.has-custom-bg` — a class the templates
  already emitted and no rule had ever used — carries `background: var(--dimension-custombgcolor)`
  instead. The print and high-contrast overrides now beat it at equal specificity; against an
  inline style they could not win at all, which is what the two `!important`s there were for.
- **CI no longer runs the whole four-branch pipeline twice per commit.** `on: [push,
  pull_request]` meant a push to a branch with an open pull request ran everything, and the
  pull-request event ran it again on the same commit. `push` is now filtered to `main` and
  `MOODLE_*_STABLE`, and a `concurrency` group supersedes a superseded run on a pull request —
  deliberately never on `main`, where each merge's run is the only one its commit will get.
- **Palette migrated to Moodle DS.** Every colour in `styles.css` now matches the palette
  `local_dimensions` already shipped: primary `#0f6cbf` (replacing Google blue `#1a73e8`, and
  absorbing the three other focus blues `#005fcc`, `#0a58ca` and `#1765cc` into one ring),
  greys onto the Bootstrap scale (`#6c757d`, `#495057`, `#dee2e6`, `#e9ecef`, `#ced4da`),
  success onto BS5 `#198754` / `#0f5132`, and the dark skin onto BS5 dark tokens
  (`#6ea8fe`, `#9ec5fe`, `#212529`, `#75b798`, `#ea868f`). No Google/Material literal remains.
- **Both card gradients migrated, mechanism unchanged.** The plan card adopts the sibling's
  exact gradient `linear-gradient(135deg, #0f6cbf 0%, #0a5aa0 100%)`; the competency card keeps
  its warm three-stop gradient *and* its halftone dot overlay, moved onto the orange family
  `local_dimensions` keeps as its brand accent (`#ffc107 → #fd7e14 → #e8590c`). The section
  header gradient follows to `#0f4d85 → #0f6cbf`.
- **Card tag chips** default to `#fd7e14` with dark `#212529` text (Bootstrap's warning-badge
  convention). The previous white-on-`#ef4136` pairing was 3.83:1 and failed WCAG 1.4.3.

### Fixed
- **Horizontal plan card: the title no longer runs under the favourite star.** This layout moves
  the star to the card's top-right, but nothing reserved that space, so a long first line passed
  beneath it. The title now carries `padding-right: 1.75rem`.
- **Contrast regressions caught while migrating** — inactive filter-pill, select and count-badge
  text moved to `#495057` on the `#e9ecef` platter (was 3.95:1), and the search placeholder and
  mobile filter-toggle label to `#5c636a` on their `#f8f9fa` fill (was 4.45:1). All text and
  non-text pairs in the block now meet WCAG 2.1 AA.
- Malformed four-argument `rgb(228, 228, 228, 0.44)` on the plan-card border replaced with
  `rgba(0, 0, 0, 0.125)`, matching the competency card; the near-white `#FFFEFC` plan-card
  background normalised to `#fff`.
- **Pending-trail marker now meets non-text contrast.** Its ring was 2px `#adb5bd` — 2.07:1 against
  white, under the WCAG 1.4.11 floor, and a source comment wrongly asserted "≈ 3.1:1". It is now
  1px `#6c757d`, 4.69:1: darkening alone would have made pending steps read heavier than completed
  ones, so the stroke thinned in the same change (1.4.11 constrains the ratio, not the thickness).
  Outer geometry is unchanged, so nothing shifts.
- **Dark mode completed.** Sweeping for that same class of failure found five more, four of them
  rules that had never been given a dark counterpart: the pending ring in dark (`#6c757d` on
  `#343a40`, 2.45:1 → `#adb5bd`, 5.55:1); the completed marker and both completed connectors
  (`#198754`, 2.54:1 → `#75b798`, 4.92:1); the trail-label hover (`#0f6cbf`, 2.15:1 → `#6ea8fe`,
  4.76:1); the select focus ring (2.88:1 → `#6ea8fe`, 6.39:1); `.dims-filter-count`, which had no
  dark rule at all and sat on the dark platter as a light island (now `#495057` / `#dee2e6`,
  6.28:1); and the mobile filter toggle, likewise ruleless, which showed a light disc on the dark
  sticky header (now `#212529` / `#495057` / `#adb5bd`, `#6ea8fe` on-state and focus).
- **Every focus ring now flips with the skin.** Consolidating the four focus blues onto `#0f6cbf`
  left eleven of the fourteen indicators with no dark counterpart — `#0f6cbf` measures 2.15:1 on the
  `#343a40` card and 2.88:1 on the `#212529` page, both under the 3:1 floor. One block now moves all
  of them to `#6ea8fe` (4.76:1 / 6.39:1), including the plan card's glow and the filter pill's inset
  ring. Verified in a browser, not by pattern-matching.
- **Section-header dark and print overrides were inert.** `-webkit-text-fill-color: transparent` on
  the base rule beats `color`, so both overrides painted nothing. They now clear the gradient fill
  first, so the dark `#dee2e6` and the print `#000` actually apply.
- **The Access / Continue pill's hover feedback never fired.** Its lift and its focus ring selected
  the pill as a descendant of the card link, but both templates make it the link's sibling subtree,
  so neither rule had ever matched — and the article-scoped block meant to replace them only set
  `opacity: 1`, itself a no-op. All three were replaced by one block on `.plan-card:hover` /
  `.competency-card:hover` / `:focus-within`, where `stretched-link` makes the states bubble. Hover
  and keyboard focus share the lift on purpose: the card already draws the focus ring, so the pill
  no longer carries a competing one. Dark deepens the hover shadow to `rgba(0, 0, 0, .5)` (its rest
  shadow is already `.35`), and `prefers-reduced-motion` drops the transform.

No text or non-text pair the block controls fails WCAG 2.1 AA in either mode. The
`prefers-contrast: high` exception recorded here no longer stands: those blocks are mode-aware as
of this release, and the single literal left in them is a mid grey that measures over 3:1 against
both the light and the dark card face.

Two acceptance items are known-open rather than closed by this work, and neither is silent: 2.4.11
Focus Not Obscured is unverified for the block's one sticky region (the mobile header, `top: 60px`
at widths under 576px), and 2.5.8 Target Size is addressed once, for the favourite button, and
nowhere else. A forced-colors pass in a real browser session remains a manual checklist item; the
automated half of it is that no focus indicator in this file is drawn with a `box-shadow`.

### Added
- **`tests/local/colour_tokens_test.php` — the colour contract as a build gate, in eighteen
  methods.** Nothing else in the pipeline reads a colour role: phpcs reads PHP, phpdoc reads
  docblocks, the mustache lint reads markup structure, stylelint reads CSS syntax, and not one of
  them can tell a focus ring drawn with a `box-shadow` from one drawn with an `outline`, a token
  chain that terminates in core's value from one frozen at a literal, or an activation selector
  anchored at the `html` element from one that fires off a navbar. This defect class has shipped
  three times in the sibling plugin with CI fully green, correctly root-caused each time, and
  recurred anyway; prose is not a gate. The file pins: the literal ban and its named exemption
  list (checked in both directions, so an exemption that stops matching anything fails too); the
  exact 34 declarations, compared as strings rather than shapes because a pattern cannot prove a
  chain terminates in the right literal; the suffix set, byte-identical to `local_dimensions`';
  the two blocks' declarations compared across repos with a prefix-residue check; the four CI jobs
  that check the sibling out, which is what stops that comparison silently skipping; the three
  tokens the mode layer may assign and no others; `:root` anchoring, with zero `.theme-dark` or
  `[data-theme` left anywhere; the inert OS-preference block, in three independent assertions;
  the inset-surface ink rule with ancestor resolution and a ratchet on what it cannot resolve;
  every declared pair recomputed against its WCAG floor in three resolutions from measured maps
  of core's own values — the light and dark `--bs-*` blocks read out of m502's compiled Boost
  sheet, and the Bootstrap 4 `:root` names read out of m405's, because 4.5 *does* declare those
  and a chain therefore lands on its middle rung there rather than on its terminal literal; the focus-indicator and focus-colour rules; the admin-colour
  transport from template to stylesheet; the branded-island boundary; the ban on the plugin ever
  writing `data-bs-theme`; `ink-faint` as inactive-control text only; and every `var()` read
  naming a declared token.
- **Every one of those tests was mutation-checked before it shipped.** Each carries the mutation
  that must redden it in its own docblock, and each was applied, run, confirmed red and reverted.
  The literal ban, the OS-preference block and the inset-surface rule carry a second mutation on
  purpose, because the obvious one for each would have left a hole: the allow-list could rot, the
  three claims could collapse into one assertion wearing three names, and the ancestry resolution
  could be decoration. Two of the mutations initially read GREEN and the tests were not at fault —
  both inserted a declaration that a later declaration in the same rule overrode, which is exactly
  what the cascade does and what the scanner models.
- **`tests/local/bootstrap_compat_test.php` — the Bootstrap 4/5 gate this plugin never had**,
  ported from the sibling with a sixth arm and its entry-point arm re-aimed. The block was on the
  wrong side of the asymmetry rather than missing a polyfill: it wrote `sr-only` seven times and
  `ml-2` once — Bootstrap 4 spellings that reach 5.x only through `bs4-compat.scss`, wrapped in a
  deprecated-styles mixin that paints a red outline under `behat-site` and themedesignermode, and
  that Moodle 6.0 removes outright (MDL-84465). Those eight are now `visually-hidden` and `ms-2`,
  and the new arm fails the build on any of the fourteen deprecated names.
- **A Bootstrap 4 utility polyfill, gated on the block's own root element.** `local_dimensions`
  gates its polyfill on a body class; a block cannot. `theme/boost/layout/columns2.php` calls
  `body_attributes()` on line 33 and `blocks('side-pre')` on line 34, and it is the latter that
  invokes `get_content()` — so the body tag is already written by the time the block is asked for
  anything. The marker therefore rides the block's own root, emitted by the summary renderable and
  pinned by a test of the same shape as the sibling's. The contract is identical; only the
  attachment point differs, and the reason is recorded in both `bootstrap.php` docblocks so the
  difference never reads as drift.
- **`classes/local/colour_mode.php`** — the attribute names in the family's activation contract,
  constants only. It deliberately exposes no `is_dark()` helper: whether the host page is dark is
  not server-knowable, because core's own answer needs a stored preference plus a client-side
  `matchMedia` resolution, and a wrong guess is the exact defect this design exists to prevent.
- **`tests/behat/` — the plugin's first Behat coverage of any kind**, four scenarios driving the
  production signal. B1 takes the page into dark mode and asserts the card still equals the page;
  B2 is the anti-vacuity control, proving the mechanism does not fire unasked; B3 asserts the same
  of a pill nested inside a card the AMD module builds after page load, inside the theme's own
  block drawer — the far end of everything this plugin paints, and the reason the tokens are
  declared on bare `:root` rather than on an enumeration of roots; B4 is the only one that needs a
  real dark palette and is the only one guarded, by a runtime measurement rather than a branch
  number. All four were mutation-checked: deleting the activation block and scoping it to a class
  each redden B4, a literal `surface` reddens B1, and an ungated dark rule reddens B2. **A Behat
  mutation on CSS reads as green unless the behat site's theme CSS is rebuilt first** — plugin CSS
  is compiled into the theme sheet — which is worth knowing before trusting any such run.
- **The cross-repo token comparison skips while the sibling has not adopted the contract, and
  that is deliberate.** The two plugins land the same token block on separate repos, and CI here
  checks `local_dimensions` out from its *default branch* — so between the two merges there is a
  window in which this plugin has the block and the sibling does not. Failing then would report
  that the blocks differ, which is not what is wrong; the test skips with the reason named
  instead, and starts running by itself once the sibling lands. A sibling that has adopted the
  contract and then diverges still fails, which is the case the lock exists for, and the
  sibling's own tests fail if its block is ever deleted. Land the two contract branches together,
  or `local_dimensions` first. Verified both ways: the comparison runs (not skips) with both
  plugins mounted on m502, and takes the skip when pointed at a namespace nothing declares.
- **The Behat step wording differs from `local_dimensions`' on purpose.** Behat step definitions
  are site-global: Moodle loads every installed plugin's context into one suite, so two contexts
  declaring the same regular expression is a hard failure that fails every scenario in *both*
  plugins. Measured on m502 with the sibling's identically worded steps present: 4 scenarios, 42
  steps, all failed before a single assertion ran. This plugin declares `local_dimensions` as a
  hard dependency and its CI checks it out on every job, so the collision is certain rather than
  hypothetical. These steps say "host" where the sibling's say "page"; the contract is the same.
- `docs/block-kit/` — an as-is design kit (tokens, eight screen replicas, two field maps) for the
  block's UI, excluded from the release zip via `.gitattributes`.

## [2.0] - 2026-07-13

Macro view of everything since v1.0 — per-change detail lives in the commit history.

### Added
- **Return-to-Plan integration**: the `block_dimensions_set_return_context` web service fires
  before trail navigation, and the dataset carries the plan association — so courses opened
  from the block get a working "Return to plan" button from `local_dimensions`.
- **Two-phase loading**: `block_dimensions_get_block_dataset` gained a `loadgroup` parameter;
  the block renders the user's favourites first and fetches each remaining card group on
  demand ("Show all" pill, ghost card, search, or automatically for groups without
  favourites).
- **Section headers**: optional `enable_section_headers` setting rendering
  language-customisable two-line headings above the plan and competency card groups.
- **Filter UX package**: clear-filters button, horizontal-scrolling pill navigation with
  paddles, adaptive card grid, mobile refinements, and admin-configured custom-field display
  names as the filter labels.
- **Full accessibility overhaul (WCAG 2.1 AA)**: real links replace simulated controls
  (cards use the stretched-link pattern, trail items are real anchors), filter pills became
  radiogroups with roving tabindex and Home/End, live regions announce results and errors,
  focus is preserved across re-renders, every control gained a real accessible name, plus
  contrast and focus-indicator fixes.
- **Favourites hardening**: ownership/existence validation on toggle, orphan cleanup on
  uninstall (`db/uninstall.php`), privacy provider covering the user data.
- **Testability**: `state` AMD module extracted from `filters.js`; PHPUnit suites for the
  dataset provider, sanitisers and web-service contract; CI dependency wiring for
  `local_dimensions`.
- **CI**: moodle-an-hochschulen reusable workflow — static checks plus PHPUnit and Behat
  runtime legs; full PHP × DB matrix on Moodle 5.02, single-database legs on
  5.01/5.00/4.05.

### Changed
- **`local_dimensions` dependency pinned**: `version.php` now requires `local_dimensions`
  2026071306 (its v2.0) or later instead of `ANY_VERSION` — the block depends on its cache
  classes, constants and return-context API.
- **Web-service contract**: the single `hasnonfavourites` flag in `get_block_dataset`'s
  response was replaced by per-group `hasnonfavouriteplans` / `hasnonfavouritecompetencies`.
- `dataset_provider` modularised into focused helpers; card metadata (images, tags,
  colours) is read exclusively from the `local_dimensions` caches — no direct
  customfield/File-API metadata reads left.
- Plugin maturity raised from BETA to STABLE.

### Security
- Card colours and image URLs are sanitised server-side before reaching inline styles
  (defense in depth on top of the `local_dimensions` field restrictions).

### Removed
- ~350 lines of unreachable code left over from the cache-based metadata refactor.

### Fixed
- Web-service return structures silently stripping undeclared fields (filter labels,
  favourite button aria attributes) via `clean_returnvalue`.
- `get_block_dataset` crashing outside page rendering (`$PAGE->context was not set`).
- Favourite star positioning, `favouritesdisabled` gating, and assorted lint/CI debt the
  previous workflow never actually ran.

## [1.0] - 2026-03-22

First stable release: learning plans and competencies rendered as visual cards (images,
colours and tags from the Dimensions custom fields), tag filters and search, the competency
trail, favourites with filter pills, ghost cards, and display-mode routing into the
`local_dimensions` learner views — backed by the `get_block_dataset` web service.

## 2026021800

### Added
- Initial release of the Dimensions block plugin.

[2.0]: https://github.com/uaiblaine/moodle-block_dimensions/releases/tag/v2.0
[1.0]: https://github.com/uaiblaine/moodle-block_dimensions/releases/tag/v1.0

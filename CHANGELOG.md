# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

### Fixed
- `toggle_favourite` now verifies the item exists and belongs to the user (own learning plan, or an existing competency) before creating a favourite; previously any — even non-existent — id was accepted.
- Added the missing `labelsjson` field to the `summary.mustache` example context so the mustache linter's JS check passes on all supported Moodle branches.
- Migrated CI to the moodle-an-hochschulen reusable workflow (PHPUnit + Behat on every PHP × DB leg) and cleared the pre-existing lang-order / ESLint debt the previous workflow never actually ran.

### Added
- Added `aria-pressed` to favourite toggle buttons in `plan_card.mustache` and `competency_card.mustache` so screen readers announce the toggle state explicitly (WCAG 4.1.2).
- Added two `aria-live` regions (`data-results-status` polite, `data-fav-status` assertive) in `summary.mustache` so result counts and favourite-toggle errors are announced (WCAG 4.1.3).
- Added language strings `favouriteerror`, `resultsfound`, `resultsnonefound`, `status_completed`, `status_pending`, `paddleleft`, `paddleright`, `filterbyplan`, `filterbycompetency`, `cardtags` (en + pt_br).
- Added a visually-hidden status suffix ("— concluída" / "— pendente") on each trail item so completion is no longer conveyed by colour alone (WCAG 1.4.1).
- Added `Home`/`End` keyboard navigation to the filter pill radiogroups (WAI-ARIA radiogroup pattern).
- Added `dataset_provider::get_customfield_name()` static helper that resolves the admin-configured display name of a custom field (e.g. "Year" / "Category"). `get_ui_config()` now exposes `tag1label`/`tag2label` per type so filter radiogroups and dropdowns can use them as their accessible name.
- Added language strings `landmark_plans`, `landmark_competencies`, `sectionheader_plan_line1/_line2`, `sectionheader_competency_line1/_line2` (en + pt_br).
- Added `searchlabel` language string (en + pt_br) and a visually-hidden `<label for>` connected to the search input via a stable `id`, so the field carries a real form label even though the visible style is icon + placeholder (WCAG 3.3.2).
- Added `.dims-noscript` style: 1rem font-size, dark-on-soft-yellow alert with explicit dark-mode override, replacing the previous low-contrast `text-muted small` paragraph that was the only message visible to JS-disabled users (WCAG 1.4.3).
- Added explicit `:focus-visible` indicators for `.dims-block-search-clear`, `.dims-error-retry`, and the `.stretched-link` card titles, so every focusable in the block has its own ≥ 3:1 indicator independent of the parent `:focus-within` (WCAG 2.4.7).
- Added `block_dimensions/state` AMD module extracting pure state functions (`createState`, `applyFilters`, `isCardVisible`, `hasActiveFiltersForType`, `updateFavouriteCounts`, `normalizeText`) from `filters.js` for independent testability and clarity.
- Added PHPUnit test suite for `dataset_provider` helpers (30 tests / 95 assertions): trail windowing, eligible-IDs logic, visibility/favourites skip rules, plan card dataset builder, `resolve_plan_display_context`, and plan-competency processor.
- Added `tests/external/get_block_dataset_test.php` covering guest rejection, disabled-competency early return, and response-key contract.
- Added `ci/extra-plugins-block_dimensions/` symlink so `make ci-install-plugin` installs `local_dimensions` as a test dependency.
- Added `CI_EXTRA_PLUGINS_DIR` variable to workspace `Makefile` enabling per-plugin extra dependency directories.
- Added local frontend/tooling baseline with package metadata and stylelint config.
- Added workspace automation target `make amd-block-dimensions` for Moodle-standard AMD rebuild.
- Added quality execution roadmap documentation for parity with local_dimensions.

### Changed
- Refactored card markup in `plan_card.mustache` and `competency_card.mustache` to the pseudo-link card pattern: only the card title is a real `<a class="stretched-link">` and a CSS `::after` overlay keeps the visible card area clickable. Removes the previous wrapping `<a>` that inflated the link's accessible name with the entire card content and prevented nested interactives from being valid HTML (WCAG 2.4.4 Link Purpose, 2.5.3 Label in Name, 4.1.1 Parsing).
- Trail items are now rendered as real `<a class="trail-item-link">` when clickable (and as plain `<li>` content otherwise) instead of `<div role="listitem" data-href>` simulated controls. Keyboard activation (Enter/Space) works natively (WCAG 2.1.1) and the previous nested-interactive HTML is gone.
- Replaced `role="tablist"`/`role="tab"`/`aria-selected` on the filter pills with `role="radiogroup"`/`role="radio"`/`aria-checked` (the controls are mutually-exclusive filters, not tabs that switch panels). Implemented roving tabindex so each radiogroup occupies a single tab stop (WCAG 4.1.2, 2.1.1, 2.4.3).
- Replaced the meaningless `aria-label="tag1"`/`"tag2"` on filter `<select>`s with the localized `filterbyplan`/`filterbycompetency` strings (WCAG 2.4.6).
- `filter_tabs_nav.js`: paddle aria-labels are now localized via `labels.paddleleft`/`labels.paddleright` instead of being hardcoded English (WCAG 3.1.2). Paddle `aria-hidden` now mirrors the visual hidden state. Keyboard navigation also accepts `Home`/`End`.
- Trail-marker empty circle border tightened from `#dee2e6` to `#adb5bd` for ≥ 3:1 contrast (WCAG 1.4.11).
- `summary.mustache`: each `<nav>` now carries its own type-specific `aria-label` (`landmark_plans` / `landmark_competencies`) instead of two navs sharing the same `aria-labelledby` to the block heading; section header `<h3>`s gained unique ids (WCAG 1.3.1, 2.4.6).
- `summary.mustache`: section headers no longer inline `<br>` inside the language string. Strings are split into `_line1`/`_line2` and the `<br>` lives in the template, where it is unambiguous HTML (WCAG 1.3.1).
- `filters.js`: filter radiogroups and dropdowns now use the admin-configured custom-field display name (`tag1label`/`tag2label` from `get_ui_config()`) — combined with the type-specific group label for disambiguation — instead of the meaningless `tag1`/`tag2` slug or the type-only fallback (WCAG 2.4.6, 4.1.2).
- `filters.js` `showError()`: reordered to clear the alert text → make the box visible → set the message in `requestAnimationFrame`, so screen readers reliably announce the `role="alert"` content change instead of missing it because the element transitioned out of `display:none` with the text already in place (WCAG 4.1.3).
- Refactored `toggleFavourite` in `amd/src/filters.js` to update favourite-pill counts in place (`updateFavouritePillCounts`) instead of forcing a full filter-bar rebuild on every star click; this preserves keyboard focus on the activated star button (WCAG 2.4.3). The full rebuild only runs when the count crosses the 0 boundary (pills appear/disappear).
- `toggleFavourite` now writes `aria-pressed` alongside the icon/label updates, and surfaces network errors via the new assertive live region (`announceFavouriteError`) instead of swallowing them silently (WCAG 3.3.1).
- `rerender` now captures the focused element before any filter-bar rebuild and restores focus afterwards via a `data-attribute` selector (WCAG 2.4.3), and announces the filtered results count through the polite live region (debounced 600 ms).
- Extracted ten protected helper methods from `dataset_provider::get_dataset()` and `build_plan_card()` into cohesive focused helpers: `get_active_plans`, `preload_favourite_ids`, `resolve_plan_display_context`, `build_plan_dataset_card`, `process_plan_competencies`, `get_eligible_competency_ids`, `get_ids_to_process`, `skip_competency_for_visibility`, `skip_competency_for_favourites`, `process_competency_dataset_item`.
- Removed orphaned phpdoc blocks for non-existent parameters across all refactored methods.
- Hardened `toggle_favourite` external endpoint against invalid `itemtype` and non-positive `itemid` inputs.
- Fixed CI badge link in README to point at the active workflow file.
- Updated `README.md` development workflow section with `CI_EXTRA_PLUGINS_DIR` usage, AMD module architecture, and corrected `ci-install-plugin` instructions.
- Simplified `@param array<K,V>` phpdoc annotations to plain `@param array` for compatibility with Moodle's phpdoc checker.

### Fixed
- Fixed `block_dimensions_get_block_dataset` web service raising `"$PAGE->context was not set"` coding_exception when the new `get_customfield_name()` accessibility helper ran before `validate_context()`. All `format_string()` calls in `dataset_provider` now pass an explicit `context_system` so they work outside of a page-rendering request (web services, scheduled tasks, CLI).
- Declared `filtersettings.{plan,competency}.tag1label`/`tag2label` in `get_block_dataset::execute_returns()` so the admin-configured custom-field names actually reach the front-end. Previously they were silently stripped by `external_api::clean_returnvalue()`, which left the filter radiogroups/dropdowns falling back to the generic localized label even when an admin had configured a custom field name.
- Declared `favouritearialabel` and `favouritetitle` on both plan and competency card schemas in `get_block_dataset::execute_returns()`. They were stripped by `clean_returnvalue()`, leaving `<button class="dims-fav-btn">` with `aria-label=""` — flagged by Lighthouse as "Buttons do not have an accessible name" (WCAG 4.1.2).
- Fixed favourite-star button positioning regression. The defensive z-index rule for `.stretched-link` siblings included `.dims-fav-btn` and overrode its `position: absolute` with `position: relative`, making the star occupy a flex slot beside the image instead of floating in the corner. The star block already declares `z-index: 3`, so the defensive rule is unnecessary for it; removed `.dims-fav-btn` from the shared selector. Star now sits top-left over the image on vertical cards and top-right on horizontal cards as designed.

## 2026021800

### Added
- Initial release of the Dimensions block plugin.

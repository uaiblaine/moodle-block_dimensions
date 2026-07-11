# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

Macro view of everything since v1.0 — per-change detail lives in the commit history.

### Added
- **Return-to-Plan integration**: the `block_dimensions_set_return_context` web service fires
  before trail navigation, and the dataset carries the plan association — so courses opened
  from the block get a working "Return to plan" button from `local_dimensions`.
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
  across the supported PHP × DB matrix.

### Changed
- `dataset_provider` modularised into focused helpers; all card metadata is read from the
  `local_dimensions` caches (no direct DB/File-API reads left).

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

[Unreleased]: https://github.com/uaiblaine/moodle-block_dimensions/compare/v1.0...HEAD
[1.0]: https://github.com/uaiblaine/moodle-block_dimensions/releases/tag/v1.0

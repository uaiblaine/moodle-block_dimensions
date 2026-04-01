# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

### Added
- Added `block_dimensions/state` AMD module extracting pure state functions (`createState`, `applyFilters`, `isCardVisible`, `hasActiveFiltersForType`, `updateFavouriteCounts`, `normalizeText`) from `filters.js` for independent testability and clarity.
- Added PHPUnit test suite for `dataset_provider` helpers (30 tests / 95 assertions): trail windowing, eligible-IDs logic, visibility/favourites skip rules, plan card dataset builder, `resolve_plan_display_context`, and plan-competency processor.
- Added `tests/external/get_block_dataset_test.php` covering guest rejection, disabled-competency early return, and response-key contract.
- Added `ci/extra-plugins-block_dimensions/` symlink so `make ci-install-plugin` installs `local_dimensions` as a test dependency.
- Added `CI_EXTRA_PLUGINS_DIR` variable to workspace `Makefile` enabling per-plugin extra dependency directories.
- Added local frontend/tooling baseline with package metadata and stylelint config.
- Added workspace automation target `make amd-block-dimensions` for Moodle-standard AMD rebuild.
- Added quality execution roadmap documentation for parity with local_dimensions.

### Changed
- Extracted ten protected helper methods from `dataset_provider::get_dataset()` and `build_plan_card()` into cohesive focused helpers: `get_active_plans`, `preload_favourite_ids`, `resolve_plan_display_context`, `build_plan_dataset_card`, `process_plan_competencies`, `get_eligible_competency_ids`, `get_ids_to_process`, `skip_competency_for_visibility`, `skip_competency_for_favourites`, `process_competency_dataset_item`.
- Removed orphaned phpdoc blocks for non-existent parameters across all refactored methods.
- Hardened `toggle_favourite` external endpoint against invalid `itemtype` and non-positive `itemid` inputs.
- Fixed CI badge link in README to point at the active workflow file.
- Updated `README.md` development workflow section with `CI_EXTRA_PLUGINS_DIR` usage, AMD module architecture, and corrected `ci-install-plugin` instructions.
- Simplified `@param array<K,V>` phpdoc annotations to plain `@param array` for compatibility with Moodle's phpdoc checker.

## 2026021800

### Added
- Initial release of the Dimensions block plugin.

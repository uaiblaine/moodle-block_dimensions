// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Client-side filter logic for block_dimensions.
 *
 * Handles tab clicks and dropdown changes to filter competency/plan cards
 * based on tag1/tag2 custom field values.
 *
 * @module     block_dimensions/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function () {

    /**
     * Active filters state. Keys are filter field names (e.g. "competency_tag1"),
     * values are the selected filter value (empty string = show all).
     * @type {Object<string, string>}
     */
    var activeFilters = {};
    var searchQuery = '';

    /**
     * Normalize text for accent-insensitive comparison.
     * Strips diacritics and lowercases.
     *
     * @param {string} str
     * @return {string}
     */
    function normalizeText(str) {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    /**
     * Initialize the search input.
     *
     * @param {HTMLElement} container The block content container.
     */
    function initSearch(container) {
        var input = container.querySelector('.dims-block-search-input');
        var clearBtn = container.querySelector('.dims-block-search-clear');
        if (!input) {
            return;
        }

        var debounceTimer = null;

        input.addEventListener('input', function () {
            // Show/hide clear button.
            if (clearBtn) {
                clearBtn.style.display = input.value.length > 0 ? 'flex' : 'none';
            }
            // Debounce filtering (100ms).
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                searchQuery = normalizeText(input.value.trim());
                applyFilters(container);
            }, 100);
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                clearBtn.style.display = 'none';
                input.focus();
                searchQuery = '';
                applyFilters(container);
            });
        }
    }

    /**
     * Initialize the filter module.
     * Sets up event listeners for tab buttons and dropdown selects.
     */
    function init() {
        var container = document.querySelector('.block-dimensions-content');
        if (!container) {
            return;
        }

        // Initialize search.
        initSearch(container);

        // Delegate click events for tab buttons.
        container.addEventListener('click', function (e) {
            var tab = e.target.closest('.dims-filter-tab');
            if (!tab) {
                return;
            }

            var field = tab.getAttribute('data-filter-field');
            var value = tab.getAttribute('data-filter-value');

            // Update active state for tabs in the same group.
            var group = tab.closest('[data-filter-group]');
            if (group) {
                var tabs = group.querySelectorAll('.dims-filter-tab');
                tabs.forEach(function (t) {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
            }
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');

            // Set the active filter value.
            activeFilters[field] = value;
            applyFilters(container);
        });

        // Delegate change events for dropdown selects.
        container.addEventListener('change', function (e) {
            var select = e.target.closest('.dims-filter-select');
            if (!select) {
                return;
            }

            var field = select.getAttribute('data-filter-field');
            var value = select.value;

            activeFilters[field] = value;
            applyFilters(container);
        });
    }

    /**
     * Apply all active filters to the card items.
     * Each filter only affects cards of its own type (competency or plan).
     *
     * @param {HTMLElement} container The block content container.
     */
    function applyFilters(container) {
        // Group active filters by card type (competency or plan).
        var filtersByType = {};

        for (var field in activeFilters) {
            if (!activeFilters.hasOwnProperty(field)) {
                continue;
            }
            // field is like "competency_tag1" or "plan_tag2".
            // Extract the type (everything before the last _tagN).
            var parts = field.split('_');
            var type = parts[0]; // "competency" or "plan"

            if (!filtersByType[type]) {
                filtersByType[type] = {};
            }
            filtersByType[type][field] = activeFilters[field];
        }

        // Process each card type independently.
        var cardLists = container.querySelectorAll('[data-cards-type]');
        cardLists.forEach(function (list) {
            var type = list.getAttribute('data-cards-type');
            var typeFilters = filtersByType[type] || {};
            var items = list.querySelectorAll('.dims-card-item');

            items.forEach(function (item) {
                var visible = true;

                // Tag filters.
                for (var field in typeFilters) {
                    if (!typeFilters.hasOwnProperty(field)) {
                        continue;
                    }

                    var filterValue = typeFilters[field];

                    // Empty value means "All" — don't filter on this field.
                    if (!filterValue) {
                        continue;
                    }

                    // Determine the data attribute name.
                    // field is like "competency_tag1" → "data-competency-tag1".
                    var attrName = 'data-' + field.replace(/_/g, '-');
                    var cardValue = item.getAttribute(attrName);

                    if (cardValue !== filterValue) {
                        visible = false;
                        break;
                    }
                }

                // Search filter (global, across all card types).
                if (visible && searchQuery.length > 0) {
                    var title = item.querySelector('.card-title');
                    if (title) {
                        visible = normalizeText(title.textContent).indexOf(searchQuery) !== -1;
                    }
                }

                if (visible) {
                    item.classList.remove('dims-card-hidden');
                    item.style.display = '';
                } else {
                    item.classList.add('dims-card-hidden');
                    item.style.display = 'none';
                }
            });
        });
    }

    return {
        init: init
    };
});

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
 * Pure state-management functions for block_dimensions filters.
 *
 * No DOM access. All functions accept and return plain objects.
 *
 * @module     block_dimensions/state
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Normalize a string for accent-insensitive search.
     *
     * @param {string} str Input string.
     * @return {string}
     */
    const normalizeText = (str) => (str || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

    /**
     * Create the initial state object from block options.
     *
     * @param {Object} options Block init options.
     * @return {Object}
     */
    function createState(options) {
        return {
            rawDataset: {
                hasactiveplans: false,
                hasplancards: false,
                hascompetencies: false,
                plancards: [],
                competencycards: []
            },
            filteredDataset: {
                plancards: [],
                competencycards: []
            },
            searchTerm: '',
            normalizedSearch: '',
            activeFilters: {
                plan_tag1: '',
                plan_tag2: '',
                competency_tag1: '',
                competency_tag2: ''
            },
            favouriteFilterActive: {
                plan: false,
                competency: false
            },
            favouritesEnabled: !!(options && options.favouritesenabled),
            filterSettings: (options && options.filtersettings) || {},
            renderToken: 0,
            filtersRendered: false,
            cardsRendered: {
                plan: false,
                competency: false
            },
            fullDatasetLoaded: false,
            hasnonfavouriteplans: false,
            hasnonfavouritecompetencies: false,
            totalplans: 0,
            totalcompetencies: 0,
            favouriteCountPlan: 0,
            favouriteCountCompetency: 0
        };
    }

    /**
     * Determine whether a single card should be visible given the current state.
     *
     * @param {Object} card Card data object.
     * @param {Object} state Application state.
     * @param {string} type 'plan' or 'competency'.
     * @return {boolean}
     */
    function isCardVisible(card, state, type) {
        if (state.favouriteFilterActive[type] && !card.isfavourite) {
            return false;
        }

        const tag1filter = state.activeFilters[type + '_tag1'];
        const tag2filter = state.activeFilters[type + '_tag2'];

        if (tag1filter && card.tag1 !== tag1filter) {
            return false;
        }
        if (tag2filter && card.tag2 !== tag2filter) {
            return false;
        }
        if (state.normalizedSearch) {
            return normalizeText(card.name || '').includes(state.normalizedSearch);
        }

        return true;
    }

    /**
     * Mutate state.filteredDataset based on current filters and search.
     *
     * @param {Object} state Application state (mutated in place).
     */
    function applyFilters(state) {
        state.filteredDataset.plancards = state.rawDataset.plancards.filter(
            (card) => isCardVisible(card, state, 'plan')
        );
        state.filteredDataset.competencycards = state.rawDataset.competencycards.filter(
            (card) => isCardVisible(card, state, 'competency')
        );
    }

    /**
     * Check whether the given type has any non-default filter active.
     *
     * @param {Object} state Application state.
     * @param {string} type 'plan' or 'competency'.
     * @return {boolean}
     */
    function hasActiveFiltersForType(state, type) {
        if (state.favouriteFilterActive[type]) {
            return true;
        }
        if (state.activeFilters[type + '_tag1']) {
            return true;
        }
        if (state.activeFilters[type + '_tag2']) {
            return true;
        }
        return false;
    }

    /**
     * Update favourite counts in state from current rawDataset.
     *
     * @param {Object} state Application state (mutated in place).
     */
    function updateFavouriteCounts(state) {
        state.favouriteCountPlan = state.rawDataset.plancards.filter(c => c.isfavourite).length;
        state.favouriteCountCompetency = state.rawDataset.competencycards.filter(c => c.isfavourite).length;
    }

    return {
        normalizeText,
        createState,
        isCardVisible,
        applyFilters,
        hasActiveFiltersForType,
        updateFavouriteCounts
    };
});

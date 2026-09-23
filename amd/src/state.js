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
 * No DOM access: createState() builds a plain state object, the other functions read or update it,
 * and normalizeText() works on plain strings.
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
                // Keys are the data-filter-field values filters.js writes (type + '_' + tag)
                // and are also built dynamically, so they stay snake_case.
                'plan_tag1': '',
                'plan_tag2': '',
                'competency_tag1': '',
                'competency_tag2': ''
            },
            favouriteFilterActive: {
                plan: false,
                competency: false
            },
            // The status axis. planStatus is the bucket on screen; statusCards keeps each
            // bucket's cards once they have been fetched, so coming back costs no request.
            planStatus: 'active',
            planCounts: {active: 0, review: 0, complete: 0},
            statusCards: {active: null, review: null, complete: null},
            statusLoading: null,
            // Group loads in flight: the first request and every fetch of the rest of a card type.
            // The loading line is shown while this or statusLoading says something is on its way.
            groupLoads: 0,
            // Whether this session's first dataset has arrived. Before it the lists are empty because
            // nothing has been fetched, not because the learner has nothing to show.
            datasetReady: false,
            favouritesEnabled: !!(options && options.favouritesenabled),
            filterSettings: (options && options.filtersettings) || {},
            // Counts the dataset loads: a response is applied only while the session that sent it
            // is still the current one. See resetSession().
            session: 0,
            renderToken: 0,
            filtersRendered: false,
            cardsRendered: {
                plan: false,
                competency: false
            },
            fullDatasetLoaded: {
                plan: false,
                competency: false
            },
            hasnonfavouriteplans: false,
            hasnonfavouritecompetencies: false,
            totalplans: 0,
            totalcompetencies: 0,
            favouriteCountPlan: 0,
            favouriteCountCompetency: 0
        };
    }

    /**
     * Start a new session: put the state back to where the block opens, before the dataset is
     * fetched again.
     *
     * Everything earlier responses built is dropped: the cards, the kept status buckets, a bucket
     * still loading, the count of group loads in flight (their responses are dropped too), the
     * flags saying the dataset arrived or a group or a card list is complete, the favourites
     * filter and the tag filters. A flag left set would make the next load skip a fetch or a
     * render it needs. Kept are the search term, which its input still holds, the settings the
     * server sent last, and renderToken, which must never repeat. session goes up by one, so a
     * response to a request of the previous session can be told apart and dropped.
     *
     * @param {Object} state Application state (mutated in place).
     */
    function resetSession(state) {
        const fresh = createState({
            favouritesenabled: state.favouritesEnabled,
            filtersettings: state.filterSettings
        });
        fresh.searchTerm = state.searchTerm;
        fresh.normalizedSearch = state.normalizedSearch;
        fresh.renderToken = state.renderToken;
        fresh.session = state.session + 1;
        Object.assign(state, fresh);
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
        resetSession,
        isCardVisible,
        applyFilters,
        hasActiveFiltersForType,
        updateFavouriteCounts
    };
});

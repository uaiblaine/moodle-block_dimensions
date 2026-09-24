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
            // The fetch of the rest of each card type while it is on its way, as its promise, or null.
            // One request can fetch both types. A type on its way is never asked for again; see
            // loadGroupDataset() in filters.js.
            groupRequests: {
                plan: null,
                competency: null
            },
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
     * still loading, the group loads in flight, both their count and the fetch of each card type
     * (their responses are dropped too), the flags saying the dataset arrived or a group or a card
     * list is complete, the favourites filter and the tag filters. A flag left set would make the
     * next load skip a fetch or a render it needs, and a fetch left recorded would make it wait
     * for a response that is dropped. Kept are the search term, which its input still holds, the
     * settings the server sent last, and renderToken, which must never repeat. session goes up by
     * one, so a response to a request of the previous session can be told apart and dropped.
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
     * Store the part of a get_block_dataset response that does not depend on the request.
     *
     * Every response carries the same plan counts, hasactiveplans, favourites flag and filter
     * settings, whichever status bucket or card type it was asked for, and each is the server's
     * latest word on them. Every response handler in filters.js calls this before merging the cards
     * it asked for, so no handler can leave one of them stale: the counts and hasactiveplans decide
     * which status pills are drawn, and the other two shape the filter bar.
     *
     * Favourites can be disabled by an admin in the middle of a session. Both favourites filters
     * are then dropped: the bar draws no favourites pill and the grid no ghost card while they are
     * off, so a filter left on would hide the other cards with nothing on screen to show them. The
     * lists still holding only the favourites of the first request are fetched by filters.js; see
     * loadWhatFavouritesLeftOut() there.
     *
     * @param {Object} state Application state (mutated in place).
     * @param {Object} dataset The web service response.
     */
    function applySharedMetadata(state, dataset) {
        state.rawDataset.hasactiveplans = !!dataset.hasactiveplans;
        if (dataset.plancounts) {
            state.planCounts = dataset.plancounts;
        }
        if (typeof dataset.favouritesenabled !== 'undefined') {
            state.favouritesEnabled = !!dataset.favouritesenabled;
        }
        if (!state.favouritesEnabled) {
            state.favouriteFilterActive.plan = false;
            state.favouriteFilterActive.competency = false;
        }
        if (dataset.filtersettings) {
            state.filterSettings = dataset.filtersettings;
        }
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
        applySharedMetadata,
        isCardVisible,
        applyFilters,
        hasActiveFiltersForType,
        updateFavouriteCounts
    };
});

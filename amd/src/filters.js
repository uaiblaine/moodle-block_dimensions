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
 * Client-side state-driven rendering for block_dimensions.
 *
 * @module     block_dimensions/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/templates'], function(Ajax, Templates) {
    const BATCH_SIZE = 24;

    const normalizeText = (str) => (str || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

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
            filterSettings: options.filtersettings || {},
            renderToken: 0,
            filtersRendered: false,
            cardsRendered: {
                plan: false,
                competency: false
            }
        };
    }

    function fetchDataset(methodname) {
        return Ajax.call([{methodname: methodname, args: {}}])[0];
    }

    function getUniqueTagValues(cards, tag) {
        const values = {};
        cards.forEach((card) => {
            if (card[tag]) {
                values[card[tag]] = true;
            }
        });
        return Object.keys(values).sort((a, b) => a.localeCompare(b));
    }

    function renderFilterControls(container, type, cards, state, labels) {
        const host = container.querySelector('.dims-filters-host[data-filters-type="' + type + '"]');
        if (!host) {
            return;
        }

        const settings = state.filterSettings[type] || {};
        const html = [];
        let hasAnyFilter = false;

        html.push('<div class="dims-filters-bar" role="toolbar" aria-label="' + labels.filterall + '">');

        ['tag1', 'tag2'].forEach((tag) => {
            const enabledKey = tag + 'enabled';
            const modeKey = tag + 'displaymode';
            if (!settings[enabledKey]) {
                return;
            }

            const field = type + '_' + tag;
            const values = getUniqueTagValues(cards, tag);
            if (!values.length) {
                return;
            }

            hasAnyFilter = true;
            const mode = settings[modeKey] === 'dropdown' ? 'dropdown' : 'tabs';
            const selectedValue = state.activeFilters[field] || '';

            if (mode === 'tabs') {
                html.push('<div class="dims-filter-tabs-wrapper" data-filter-group="' + field + '">');
                html.push('<div class="dims-filter-tabs" role="tablist">');
                html.push('<button type="button" class="dims-filter-tab ' + (selectedValue === '' ? 'active' : '') + '" role="tab" aria-selected="' + (selectedValue === '' ? 'true' : 'false') + '" data-filter-field="' + field + '" data-filter-value="">' + labels.filterall + '</button>');
                values.forEach((value) => {
                    const isSelected = selectedValue === value;
                    html.push('<button type="button" class="dims-filter-tab ' + (isSelected ? 'active' : '') + '" role="tab" aria-selected="' + (isSelected ? 'true' : 'false') + '" data-filter-field="' + field + '" data-filter-value="' + escapeHtml(value) + '">' + escapeHtml(value) + '</button>');
                });
                html.push('</div></div>');
            } else {
                html.push('<div class="dims-filter-dropdown-wrapper" data-filter-group="' + field + '">');
                html.push('<select class="dims-filter-select" data-filter-field="' + field + '" aria-label="' + tag + '">');
                html.push('<option value=""' + (selectedValue === '' ? ' selected' : '') + '>' + labels.filterall + '</option>');
                values.forEach((value) => {
                    const isSelected = selectedValue === value;
                    html.push('<option value="' + escapeHtml(value) + '"' + (isSelected ? ' selected' : '') + '>' + escapeHtml(value) + '</option>');
                });
                html.push('</select></div>');
            }
        });

        html.push('</div>');
        host.innerHTML = hasAnyFilter ? html.join('') : '';
    }

    function syncFilterActiveState(container, state) {
        container.querySelectorAll('.dims-filter-tab').forEach(function(tab) {
            var field = tab.dataset.filterField;
            var value = tab.dataset.filterValue || '';
            var isActive = (state.activeFilters[field] || '') === value;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        container.querySelectorAll('.dims-filter-select').forEach(function(select) {
            var field = select.dataset.filterField;
            select.value = state.activeFilters[field] || '';
        });
    }

    function isCardVisible(card, state, type) {
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

    function applyFilters(state) {
        state.filteredDataset.plancards = state.rawDataset.plancards.filter((card) => isCardVisible(card, state, 'plan'));
        state.filteredDataset.competencycards = state.rawDataset.competencycards.filter((card) => isCardVisible(card, state, 'competency'));
    }

    function renderCardItem(type, card) {
        const templatename = type === 'plan' ? 'block_dimensions/plan_card' : 'block_dimensions/competency_card';

        return Templates.render(templatename, card).then((html, js) => {
            const li = document.createElement('li');
            li.dataset.cardId = String(card.id);

            if (type === 'plan') {
                li.className = 'dims-plan-item mb-3 dims-card-item ' +
                    (card.ishorizontal ? 'dims-plan-item-horizontal col-12 col-lg-6 col-xl-4' : 'dims-plan-item-vertical col-12 col-sm-6 col-lg-4');
                li.dataset.planTag1 = card.tag1 || '';
                li.dataset.planTag2 = card.tag2 || '';
            } else {
                li.className = 'col-12 col-sm-6 col-lg-4 mb-3 dims-card-item';
                li.dataset.competencyTag1 = card.tag1 || '';
                li.dataset.competencyTag2 = card.tag2 || '';
            }

            li.innerHTML = html;
            if (js && typeof Templates.runTemplateJS === 'function') {
                Templates.runTemplateJS(js);
            }
            return li;
        });
    }

    function renderCardListIncremental(container, type, cards, token) {
        const list = container.querySelector('[data-cards-type="' + type + '"]');
        if (!list) {
            return Promise.resolve();
        }

        list.innerHTML = '';
        if (!cards.length) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const renderBatch = (start) => {
                if (token !== container.dataset.renderToken) {
                    resolve();
                    return;
                }

                const batch = cards.slice(start, start + BATCH_SIZE);
                if (!batch.length) {
                    resolve();
                    return;
                }

                Promise.all(batch.map((card) => renderCardItem(type, card)))
                    .then((nodes) => {
                        if (token !== container.dataset.renderToken) {
                            resolve();
                            return;
                        }

                        const fragment = document.createDocumentFragment();
                        nodes.forEach((node) => fragment.appendChild(node));
                        list.appendChild(fragment);

                        globalThis.requestAnimationFrame(() => renderBatch(start + BATCH_SIZE));
                    })
                    .catch(reject);
            };

            renderBatch(0);
        });
    }

    function ensureCardsRendered(container, state, type, token) {
        if (state.cardsRendered[type]) {
            return Promise.resolve();
        }

        const cards = type === 'plan' ? state.rawDataset.plancards : state.rawDataset.competencycards;
        return renderCardListIncremental(container, type, cards, token).then(() => {
            if (token === container.dataset.renderToken) {
                state.cardsRendered[type] = true;
            }
        });
    }

    function applyVisibility(container, type, filteredCards) {
        const list = container.querySelector('[data-cards-type="' + type + '"]');
        if (!list) {
            return;
        }

        const visibleIds = {};
        filteredCards.forEach((card) => {
            visibleIds[String(card.id)] = true;
        });

        list.querySelectorAll('.dims-card-item').forEach((item) => {
            const visible = !!visibleIds[item.dataset.cardId || ''];
            item.classList.toggle('dims-card-hidden', !visible);
            item.style.display = visible ? '' : 'none';
        });
    }

    function updateEmptyState(container, state, labels) {
        const empty = container.querySelector('.dims-empty-state');
        if (!empty) {
            return;
        }

        empty.style.display = 'none';

        if (!state.rawDataset.hasactiveplans) {
            empty.textContent = labels.noactiveplans;
            empty.style.display = '';
            return;
        }

        if (!state.filteredDataset.plancards.length && !state.filteredDataset.competencycards.length) {
            empty.textContent = labels.nocompetencies;
            empty.style.display = '';
        }
    }

    function showLoading(container, isLoading) {
        const loading = container.querySelector('.dims-loading-state');
        if (loading) {
            loading.style.display = isLoading ? '' : 'none';
        }
    }

    function clearError(container) {
        const errorBox = container.querySelector('.dims-error-message');
        if (errorBox) {
            errorBox.style.display = 'none';
        }
    }

    function showError(container, message) {
        const errorBox = container.querySelector('.dims-error-message');
        if (!errorBox) {
            return;
        }
        const text = errorBox.querySelector('.dims-error-text');
        if (text) {
            text.textContent = message;
        }
        errorBox.style.display = '';
    }

    function rerender(container, state, options) {
        applyFilters(state);

        if (!state.filtersRendered) {
            renderFilterControls(container, 'plan', state.rawDataset.plancards, state, options.labels);
            renderFilterControls(container, 'competency', state.rawDataset.competencycards, state, options.labels);
            state.filtersRendered = true;
        }

        syncFilterActiveState(container, state);

        const token = String(++state.renderToken);
        container.dataset.renderToken = token;

        Promise.all([
            ensureCardsRendered(container, state, 'plan', token),
            ensureCardsRendered(container, state, 'competency', token)
        ]).then(() => {
            applyVisibility(container, 'plan', state.filteredDataset.plancards);
            applyVisibility(container, 'competency', state.filteredDataset.competencycards);
            updateEmptyState(container, state, options.labels);
        }).catch(() => {
            showError(container, options.labels.loaderror);
        });
    }

    function bindEvents(container, state, options) {
        const searchInput = container.querySelector('.dims-block-search-input');
        const clearButton = container.querySelector('.dims-block-search-clear');
        let debounceTimer = null;

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                if (clearButton) {
                    clearButton.style.display = searchInput.value.length ? 'flex' : 'none';
                }

                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    state.searchTerm = searchInput.value.trim();
                    state.normalizedSearch = normalizeText(state.searchTerm);
                    rerender(container, state, options);
                }, 120);
            });
        }

        if (clearButton) {
            clearButton.addEventListener('click', () => {
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                clearButton.style.display = 'none';
                state.searchTerm = '';
                state.normalizedSearch = '';
                rerender(container, state, options);
            });
        }

        container.addEventListener('click', (e) => {
            const tab = e.target.closest('.dims-filter-tab');
            if (!tab) {
                return;
            }

            state.activeFilters[tab.dataset.filterField] = tab.dataset.filterValue || '';
            rerender(container, state, options);
        });

        container.addEventListener('change', (e) => {
            const select = e.target.closest('.dims-filter-select');
            if (!select) {
                return;
            }

            state.activeFilters[select.dataset.filterField] = select.value || '';
            rerender(container, state, options);
        });

        const retryButton = container.querySelector('.dims-error-retry');
        if (retryButton) {
            retryButton.addEventListener('click', () => {
                loadData(container, state, options);
            });
        }
    }

    function resetRenderedState(container, state) {
        state.filtersRendered = false;
        state.cardsRendered.plan = false;
        state.cardsRendered.competency = false;

        const planList = container.querySelector('[data-cards-type="plan"]');
        if (planList) {
            planList.innerHTML = '';
        }
        const competencyList = container.querySelector('[data-cards-type="competency"]');
        if (competencyList) {
            competencyList.innerHTML = '';
        }
    }

    function loadData(container, state, options) {
        clearError(container);
        showLoading(container, true);

        return fetchDataset(options.endpointmethod)
            .then((dataset) => {
                state.rawDataset = {
                    hasactiveplans: !!dataset.hasactiveplans,
                    hasplancards: !!dataset.hasplancards,
                    hascompetencies: !!dataset.hascompetencies,
                    plancards: dataset.plancards || [],
                    competencycards: dataset.competencycards || []
                };

                if (dataset.filtersettings) {
                    state.filterSettings = dataset.filtersettings;
                }

                resetRenderedState(container, state);
                rerender(container, state, options);
                showLoading(container, false);
            })
            .catch(() => {
                showLoading(container, false);
                showError(container, options.labels.loaderror);
            });
    }

    function init(options) {
        const safeOptions = options || {};
        const container = document.getElementById(safeOptions.containerid);
        if (!container) {
            return;
        }

        const state = createState(safeOptions);
        bindEvents(container, state, safeOptions);
        loadData(container, state, safeOptions);
    }

    return {init: init};
});

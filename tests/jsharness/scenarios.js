/*
 * Scenarios for block_dimensions/filters, run by harness.html in headless Chromium (see README.md).
 *
 * Each check is named "<family>: <what it pins>". The family is what a mutant in mutants.py names as the
 * checks it must redden. A precondition proves the state a check relies on was reached, and a control
 * proves the behaviour it pins still happens where it should, so no check can pass by doing nothing.
 *
 * Development tooling only: excluded from the release zip through .gitattributes.
 */
/* eslint-disable */
const L = {
    filterall: 'All', filterbyplan: 'Filter plans', filterbycompetency: 'Filter comps',
    noactiveplans: 'NOACTIVE', nocompetencies: 'NOCOMPS', resultsnonefound: 'NONEFOUND',
    resultsfound: 'P={$a->plans} C={$a->competencies}', loaderror: 'LOADERROR',
    ghostcardtitle: 'More', ghostcardsubtitle: 'sub', myfavourites: 'Favs', showallitems: 'All items',
    clearfilters: 'Clear', statusfilter: 'Status', statusactive: 'Active', statusreview: 'In review',
    statuscomplete: 'Completed', statusloadingactive: 'LOADING-ACTIVE', statusloadingreview: 'LOADING-REVIEW',
    statusloadingcomplete: 'LOADING-COMPLETE',
    favouriteerror: 'FAVERR', paddleleft: 'L', paddleright: 'R'
};

function shell(id) {
    return '<div id="' + id + '" class="block-dimensions-content">'
        + '<div class="dims-block-header"><div class="dims-search-row"><div class="dims-block-search-wrapper">'
        + '<input type="text" class="dims-block-search-input">'
        + '<button type="button" class="dims-block-search-clear" style="display:none;"></button></div>'
        + '<button type="button" class="dims-filter-toggle-btn" aria-expanded="false"></button></div></div>'
        + '<div class="dims-loading-state" role="status">Loading...</div>'
        + '<div class="dims-error-message" role="alert" style="display:none;"><span class="dims-error-text"></span>'
        + '<button type="button" class="dims-error-retry">Retry</button></div>'
        + '<h3 class="dims-section-header" data-section-header="plan" style="display:none">Plans</h3>'
        + '<div class="dims-filters-host" data-filters-type="plan"></div>'
        + '<nav><ul data-cards-type="plan"></ul></nav>'
        + '<h3 class="dims-section-header" data-section-header="competency" style="display:none">Comps</h3>'
        + '<div class="dims-filters-host" data-filters-type="competency"></div>'
        + '<nav><ul data-cards-type="competency"></ul></nav>'
        + '<p class="dims-empty-state" style="display:none;"></p>'
        + '<span data-results-status></span><span data-fav-status></span>'
        + '</div>';
}

const card = (id, name, extra) => Object.assign({id: id, name: name, tag1: '', tag2: '', isfavourite: false}, extra || {});
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
async function settle() {
    for (let i = 0; i < 8; i++) {
        await sleep(0);
    }
    await sleep(20);
}
async function micro(n) {
    for (let i = 0; i < (n || 6); i++) {
        await null;
    }
}

let counter = 0;
function mount(opts, preset) {
    const id = 'blk' + (++counter);
    const wrap = document.createElement('div');
    wrap.innerHTML = shell(id);
    document.getElementById('host').appendChild(wrap);
    if (preset) {
        // A value the browser put back in the input before the module ran.
        wrap.querySelector('.dims-block-search-input').value = preset;
    }
    const base = __calls.length;
    __mods['block_dimensions/filters'].init(Object.assign({
        containerid: id, endpointmethod: 'ep', favouritesenabled: false, filtersettings: {}, labels: L
    }, opts || {}));
    const c = document.getElementById(id);
    return {
        c: c,
        call: (i) => __calls[base + i],
        ncalls: () => __calls.length - base,
        respond: (i, data) => { __calls[base + i].settled = true; __calls[base + i].res(data); },
        fail: (i) => { __calls[base + i].settled = true; __calls[base + i].rej(new Error('x')); },
    };
}

const vis = (el) => !!el && el.style.display !== 'none';
const ids = (c, type) => Array.from(c.querySelectorAll('[data-cards-type="' + type + '"] li[data-card-id]'))
    .filter((li) => li.style.display !== 'none').map((li) => +li.dataset.cardId);
const allIds = (c, type) => Array.from(c.querySelectorAll('[data-cards-type="' + type + '"] li[data-card-id]'))
    .map((li) => +li.dataset.cardId);
const skel = (c) => Array.from(c.querySelectorAll('.dims-skeleton-card')).filter((li) => li.style.display !== 'none').length;
const skelAll = (c) => c.querySelectorAll('.dims-skeleton-card').length;
const loading = (c) => vis(c.querySelector('.dims-loading-state'));
const loadText = (c) => c.querySelector('.dims-loading-state').textContent.trim();
const region = (c) => c.querySelector('[data-results-status]').textContent;
const errorShown = (c) => vis(c.querySelector('.dims-error-message'));
const empty = (c) => vis(c.querySelector('.dims-empty-state')) ? c.querySelector('.dims-empty-state').textContent : '';
const pill = (c, key) => c.querySelector('.dims-status-filter-btn[data-status-filter="' + key + '"]');
const checked = (c) => { const p = c.querySelector('.dims-status-filter-btn[aria-checked="true"]'); return p ? p.dataset.statusFilter : null; };
const busy = (c) => { const p = c.querySelector('.dims-status-filter-btn[aria-busy="true"]'); return p ? p.dataset.statusFilter : null; };
const focused = () => document.activeElement && document.activeElement.dataset
    ? (document.activeElement.dataset.statusFilter || document.activeElement.className) : null;
const tagPills = (c) => c.querySelectorAll('[data-filter-field="plan_tag1"]').length;
const favChecked = (c, type) => { const b = c.querySelector('.dims-fav-filter-btn[data-fav-filter-type="' + type + '"]'); return b ? b.getAttribute('aria-checked') : 'none'; };
function typeSearch(c, v) {
    const i = c.querySelector('.dims-block-search-input');
    i.value = v;
    i.dispatchEvent(new Event('input', {bubbles: true}));
}
function key(k) {
    document.activeElement.dispatchEvent(new KeyboardEvent('keydown', {key: k, bubbles: true, cancelable: true}));
}

function base(extra) {
    return Object.assign({
        planstatus: 'active', plancounts: {active: 2, review: 1, complete: 1}, hasactiveplans: true,
        hasplancards: true, hascompetencies: true, plancards: [], competencycards: [],
        totalplans: 0, totalcompetencies: 0, hasnonfavouriteplans: false, hasnonfavouritecompetencies: false,
        favouritesenabled: false, filtersettings: {}
    }, extra);
}
const fullActive = () => base({plancards: [card(1, 'Alpha'), card(2, 'Beta')],
    competencycards: [card(11, 'Comp one'), card(12, 'Comp two')], totalplans: 2, totalcompetencies: 2, favouritesenabled: true});

const results = [];
function check(name, pass, detail) {
    results.push({name: name, pass: !!pass, detail: detail});
}

async function retryRebuildsTheBlock() {
    // No favourites at all: the first load fetches both groups in full.
    const phase1 = () => base({plancards: [], competencycards: [], hasplancards: false, hascompetencies: false,
        totalplans: 2, totalcompetencies: 2, hasnonfavouriteplans: true, hasnonfavouritecompetencies: true,
        favouritesenabled: true});
    const b = mount({favouritesenabled: true});
    b.respond(0, phase1());
    await settle();
    b.respond(1, fullActive());
    await settle();
    check('retry: the first load shows the plans', JSON.stringify(ids(b.c, 'plan')) === '[1,2]', ids(b.c, 'plan'));
    check('retry: the loading line is hidden after the first load', !loading(b.c), loading(b.c));
    pill(b.c, 'review').click();
    await settle();
    b.fail(2);
    await micro();
    // Retry while the failure path's render pass is still pending.
    b.c.querySelector('.dims-error-retry').click();
    await settle();
    check('retry: no empty-state line while the retry fetches', empty(b.c) === '', empty(b.c));
    check('retry: both lists are empty while the retry fetches', allIds(b.c, 'plan').length === 0 && allIds(b.c, 'competency').length === 0,
        [allIds(b.c, 'plan'), allIds(b.c, 'competency')]);
    check('retry: the retry asks for the favourites first', b.call(3) && b.call(3).args.favouritesonly === true, b.call(3) && b.call(3).args);
    b.respond(3, phase1());
    await settle();
    check('retry: the retry then fetches the rest', b.ncalls() === 5, b.ncalls());
    if (b.ncalls() === 5) {
        b.respond(4, fullActive());
        await settle();
    }
    check('retry: the plans after the retry', JSON.stringify(ids(b.c, 'plan')) === '[1,2]', ids(b.c, 'plan'));
    check('retry: the competencies after the retry', JSON.stringify(ids(b.c, 'competency')) === '[11,12]', ids(b.c, 'competency'));
    check('retry: the loading line is hidden after the retry', !loading(b.c), loading(b.c));
    check('retry: the error is hidden after the retry', !errorShown(b.c), errorShown(b.c));
    check('retry: Active is checked again after the retry', checked(b.c) === 'active', checked(b.c));
}

async function retryWithAllFavourites() {
    // The first session opens under "My favourites"; the retry answers with every item a favourite.
    const b = mount({favouritesenabled: true});
    b.respond(0, base({plancards: [card(1, 'Alpha', {isfavourite: true})], competencycards: [card(11, 'C', {isfavourite: true})],
        totalplans: 2, totalcompetencies: 2, hasnonfavouriteplans: true, hasnonfavouritecompetencies: true, favouritesenabled: true}));
    await settle();
    check('retry: precondition: the first load opens under the favourites filter', favChecked(b.c, 'plan') === 'true', favChecked(b.c, 'plan'));
    pill(b.c, 'review').click();
    await settle();
    b.fail(1);
    await settle();
    b.c.querySelector('.dims-error-retry').click();
    await settle();
    b.respond(2, base({plancards: [card(1, 'Alpha', {isfavourite: true})], competencycards: [card(11, 'C', {isfavourite: true})],
        totalplans: 1, totalcompetencies: 1, favouritesenabled: true}));
    await settle();
    check('retry: a retry whose items are all favourites opens without the favourites filter', favChecked(b.c, 'plan') === 'false', favChecked(b.c, 'plan'));
}

async function retryAfterASearchEvent() {
    // Plans have no favourite, competencies do: only the plans are fetched in full, and the
    // favourite competency of phase 1 must still render although a search event ran during the fetch.
    const b = mount({favouritesenabled: true});
    const phase1 = () => base({plancards: [], competencycards: [card(11, 'Comp one', {isfavourite: true})], hasplancards: false,
        totalplans: 2, totalcompetencies: 2, hasnonfavouriteplans: true, hasnonfavouritecompetencies: true, favouritesenabled: true});
    b.respond(0, phase1());
    await settle();
    b.respond(1, base({plancards: [card(1, 'Alpha'), card(2, 'Beta')], totalplans: 2, favouritesenabled: true}));
    await settle();
    pill(b.c, 'review').click();
    await settle();
    b.fail(2);
    await settle();
    b.c.querySelector('.dims-error-retry').click();
    typeSearch(b.c, '');
    await sleep(200);
    b.respond(3, phase1());
    await settle();
    check('retry: only the plans have no favourite, so only the plan group is fetched', b.ncalls() === 5 && b.call(4).args.loadgroup === 'plan', b.call(4) && b.call(4).args);
    if (b.ncalls() === 5) {
        b.respond(4, base({plancards: [card(1, 'Alpha'), card(2, 'Beta')], totalplans: 2, favouritesenabled: true}));
        await settle();
    }
    check('retry: the favourite competency of the first response renders although a search event ran', JSON.stringify(ids(b.c, 'competency')) === '[11]', ids(b.c, 'competency'));
    check('retry: the fetched plans render', JSON.stringify(ids(b.c, 'plan')) === '[1,2]', ids(b.c, 'plan'));
}

async function retryDropsAStaleBucket() {
    // A bucket request of the previous session lands during the retry: it must be dropped.
    const b = mount({favouritesenabled: true});
    b.respond(0, base({plancards: [card(1, 'Alpha', {isfavourite: true})], competencycards: [card(11, 'C', {isfavourite: true})],
        totalplans: 2, totalcompetencies: 2, hasnonfavouriteplans: true, hasnonfavouritecompetencies: true, favouritesenabled: true}));
    await settle();
    pill(b.c, 'review').click(); // Call 1, left pending.
    await settle();
    b.c.querySelector('.dims-all-filter-btn[data-all-filter-type="competency"]').click(); // Call 2.
    await settle();
    b.fail(2);
    await settle();
    check('retry: precondition: the error is shown', errorShown(b.c), errorShown(b.c));
    b.c.querySelector('.dims-error-retry').click(); // Call 3.
    await settle();
    b.respond(1, base({favouritesenabled: true, planstatus: 'review', plancards: [card(31, 'Stale review')], totalplans: 1}));
    await settle();
    check('retry: a bucket response of the replaced session is dropped', allIds(b.c, 'plan').length === 0 && !busy(b.c), [allIds(b.c, 'plan'), busy(b.c)]);
    b.respond(3, base({plancards: [card(1, 'Alpha', {isfavourite: true}), card(2, 'Beta')],
        competencycards: [card(11, 'C', {isfavourite: true}), card(12, 'D')], totalplans: 2, totalcompetencies: 2,
        favouritesenabled: true}));
    await settle();
    check('retry: the new session shows its own plans', JSON.stringify(ids(b.c, 'plan')) === '[1,2]', ids(b.c, 'plan'));
    check('retry: the new session opens on Active', checked(b.c) === 'active', checked(b.c));
}

async function retryDropsAStaleGroup() {
    // A group request of the previous session lands during the retry: it must be dropped.
    const b = mount({favouritesenabled: true});
    b.respond(0, base({plancards: [card(1, 'Alpha', {isfavourite: true})], competencycards: [card(11, 'C', {isfavourite: true})],
        totalplans: 2, totalcompetencies: 2, hasnonfavouriteplans: true, hasnonfavouritecompetencies: true, favouritesenabled: true}));
    await settle();
    b.c.querySelector('.dims-all-filter-btn[data-all-filter-type="competency"]').click(); // Call 1, pending.
    await settle();
    pill(b.c, 'review').click(); // Call 2.
    await settle();
    b.fail(2);
    await settle();
    b.c.querySelector('.dims-error-retry').click(); // Call 3.
    await settle();
    b.respond(1, base({competencycards: [card(11, 'C'), card(12, 'D'), card(13, 'E')], totalcompetencies: 3}));
    await settle();
    check('retry: a group response of the replaced session is dropped', allIds(b.c, 'competency').length === 0, allIds(b.c, 'competency'));
}

async function bucketLoading() {
    const fs = {plan: {tag1enabled: true, tag1displaymode: 'tabs', tag1label: 'Year'}, competency: {}};
    const b = mount({filtersettings: fs});
    b.respond(0, base({plancounts: {active: 2, review: 2, complete: 1}, filtersettings: fs,
        plancards: [card(1, 'Alpha', {tag1: '2024'}), card(2, 'Beta', {tag1: '2025'})],
        competencycards: [card(11, 'Comp one'), card(12, 'Comp two')], totalplans: 2, totalcompetencies: 2}));
    await settle();
    await sleep(700);
    const regionBefore = b.c.querySelector('[data-results-status]').textContent;
    check('bucket loading: precondition: the plans and their tag pills', JSON.stringify(ids(b.c, 'plan')) === '[1,2]' && tagPills(b.c) === 3,
        [ids(b.c, 'plan'), tagPills(b.c)]);
    pill(b.c, 'review').click();
    await settle();
    check('bucket loading: the skeleton alone while the bucket loads', skel(b.c) === 2 && allIds(b.c, 'plan').length === 0, [skel(b.c), allIds(b.c, 'plan')]);
    check('bucket loading: no tag pills of the bucket left', tagPills(b.c) === 0, tagPills(b.c));
    check('bucket loading: the loading pill is checked and busy', busy(b.c) === 'review' && checked(b.c) === 'review', [busy(b.c), checked(b.c)]);
    typeSearch(b.c, 'comp');
    await sleep(200);
    await settle();
    check('bucket loading: a search while loading keeps the skeleton', skel(b.c) === 2 && allIds(b.c, 'plan').length === 0,
        [skel(b.c), skelAll(b.c), allIds(b.c, 'plan')]);
    check('bucket loading: a search while loading draws no tag pills of the bucket left', tagPills(b.c) === 0, tagPills(b.c));
    check('bucket loading: a search while loading filters the competencies', JSON.stringify(ids(b.c, 'competency')) === '[11,12]', ids(b.c, 'competency'));
    typeSearch(b.c, 'zzz');
    await sleep(200);
    await settle();
    check('bucket loading: a search matching nothing while loading shows no empty-state line', empty(b.c) === '', empty(b.c));
    await sleep(700);
    const regionDuring = b.c.querySelector('[data-results-status]').textContent;
    check('bucket loading: nothing is announced while loading', regionDuring === regionBefore, [regionBefore, regionDuring]);
    typeSearch(b.c, '');
    await sleep(200);
    await settle();
    b.respond(1, base({planstatus: 'review', plancounts: {active: 2, review: 2, complete: 1}, filtersettings: fs,
        plancards: [card(21, 'Rev one', {tag1: '2030'}), card(22, 'Rev two')], totalplans: 2}));
    await settle();
    check('bucket loading: the landed bucket shows its cards', JSON.stringify(ids(b.c, 'plan')) === '[21,22]' && skelAll(b.c) === 0,
        [ids(b.c, 'plan'), skelAll(b.c)]);
    check('bucket loading: the landed bucket draws its own tag pills', tagPills(b.c) === 2, tagPills(b.c));
    await sleep(700);
    check('bucket loading: the landed bucket is announced', b.c.querySelector('[data-results-status]').textContent === 'P=2 C=2',
        b.c.querySelector('[data-results-status]').textContent);
    pill(b.c, 'complete').click();
    await settle();
    typeSearch(b.c, 'rev');
    await sleep(200);
    await settle();
    b.fail(2);
    await settle();
    check('bucket loading: a failed switch brings back the cards of the bucket left', JSON.stringify(ids(b.c, 'plan')) === '[21,22]' && skelAll(b.c) === 0,
        [ids(b.c, 'plan'), skelAll(b.c)]);
    check('bucket loading: a failed switch checks the bucket left and shows the error', checked(b.c) === 'review' && errorShown(b.c), [checked(b.c), errorShown(b.c)]);
    check('bucket loading: a failed switch hides the loading line', !loading(b.c), loading(b.c));
}

async function statusKeys() {
    const b = mount();
    b.respond(0, base({plancounts: {active: 2, review: 1, complete: 1}, plancards: [card(1, 'Alpha'), card(2, 'Beta')],
        competencycards: [], totalplans: 2}));
    await settle();
    pill(b.c, 'active').focus();
    key('ArrowRight');
    await settle();
    check('status keys: an arrow key checks the next bucket and loads it', focused() === 'review' && checked(b.c) === 'review' && busy(b.c) === 'review',
        [focused(), checked(b.c), busy(b.c)]);
    key('ArrowRight');
    await settle();
    check('status keys: ArrowRight does nothing while a bucket loads', focused() === 'review', focused());
    key('End');
    await settle();
    check('status keys: End does nothing while a bucket loads', focused() === 'review', focused());
    key('ArrowLeft');
    await settle();
    check('status keys: ArrowLeft does nothing while a bucket loads', focused() === 'review', focused());
    b.respond(1, base({planstatus: 'review', plancards: [card(21, 'Rev')], totalplans: 1}));
    await settle();
    check('status keys: once landed, focus is on the checked pill', focused() === 'review' && checked(b.c) === 'review' && !busy(b.c),
        [focused(), checked(b.c), busy(b.c)]);
    key('ArrowRight');
    await settle();
    check('status keys: the arrow keys work again once landed', focused() === 'complete' && busy(b.c) === 'complete', [focused(), busy(b.c)]);
    // A pointer click on another pill while "complete" loads: ignored, and focus lands on the checked pill.
    pill(b.c, 'active').focus();
    pill(b.c, 'active').click();
    await settle();
    check('status keys: a click on another pill is ignored while a bucket loads', checked(b.c) === 'complete' && busy(b.c) === 'complete', [checked(b.c), busy(b.c)]);
    b.respond(2, base({planstatus: 'complete', plancards: [card(41, 'Done')], totalplans: 1}));
    await settle();
    check('status keys: after an ignored click, focus lands on the checked pill', focused() === 'complete' && checked(b.c) === 'complete',
        [focused(), checked(b.c)]);
    key('Home');
    await settle();
    check('status keys: Home checks the kept Active bucket', focused() === 'active' && checked(b.c) === 'active',
        [focused(), checked(b.c)]);
}

async function statusKeysAfterAFailure() {
    const b = mount();
    b.respond(0, base({plancounts: {active: 2, review: 1, complete: 0}, plancards: [card(1, 'Alpha'), card(2, 'Beta')],
        competencycards: [], totalplans: 2}));
    await settle();
    pill(b.c, 'active').focus();
    key('ArrowRight');
    await settle();
    b.fail(1);
    await settle();
    check('status keys: after a failed switch, focus is back on the checked pill', focused() === 'active' && checked(b.c) === 'active', [focused(), checked(b.c)]);
}


async function basics() {
    // Favourites first: the ghost card loads the rest of the plans.
    const b = mount({favouritesenabled: true});
    b.respond(0, base({plancards: [card(1, 'Alpha', {isfavourite: true})], competencycards: [card(11, 'C', {isfavourite: true})],
        totalplans: 3, totalcompetencies: 2, hasnonfavouriteplans: true, hasnonfavouritecompetencies: true, favouritesenabled: true}));
    await settle();
    const ghost = b.c.querySelector('.dims-ghost-card[data-ghost-type="plan"]');
    check('favourites first: a ghost card offers the other plans', !!ghost, !!ghost);
    check('favourites first: the loading line is hidden after the favourites-only response', !loading(b.c), loading(b.c));
    ghost.querySelector('button').click();
    await settle();
    check('favourites first: the ghost card requests the plan group', b.call(1) && b.call(1).args.loadgroup === 'plan', b.call(1) && b.call(1).args);
    b.respond(1, base({plancards: [card(1, 'Alpha', {isfavourite: true}), card(2, 'Beta'), card(3, 'Gamma')], totalplans: 3,
        favouritesenabled: true}));
    await settle();
    check('favourites first: every plan is shown once loaded', JSON.stringify(ids(b.c, 'plan')) === '[1,2,3]', ids(b.c, 'plan'));
    check('favourites first: the plan ghost card is gone', !b.c.querySelector('.dims-ghost-card[data-ghost-type="plan"]'), 1);
    check('favourites first: the competency ghost card stays', !!b.c.querySelector('.dims-ghost-card[data-ghost-type="competency"]'), 1);
    check('favourites first: the loading line is hidden once loaded', !loading(b.c), loading(b.c));

    // A tag filter, and clearing it.
    const fs = {plan: {tag1enabled: true, tag1displaymode: 'tabs'}, competency: {}};
    const t = mount({filtersettings: fs});
    t.respond(0, base({filtersettings: fs, plancards: [card(1, 'A', {tag1: 'x'}), card(2, 'B', {tag1: 'y'})], totalplans: 2,
        competencycards: [card(11, 'C')], totalcompetencies: 1}));
    await settle();
    t.c.querySelector('[data-filter-field="plan_tag1"][data-filter-value="x"]').click();
    await settle();
    check('tag filter: a tag pill filters the plans', JSON.stringify(ids(t.c, 'plan')) === '[1]', ids(t.c, 'plan'));
    const clear = t.c.querySelector('.dims-clear-filters-btn[data-clear-type="plan"]');
    check('tag filter: the clear button shows', vis(clear), clear && clear.style.display);
    clear.click();
    await settle();
    check('tag filter: clearing shows every plan', JSON.stringify(ids(t.c, 'plan')) === '[1,2]', ids(t.c, 'plan'));

    // A fetched bucket is kept: switching back costs no request.
    pill(t.c, 'review').click();
    await settle();
    t.respond(1, base({planstatus: 'review', plancards: [card(21, 'R')], totalplans: 1, filtersettings: fs}));
    await settle();
    pill(t.c, 'active').click();
    await settle();
    check('bucket cache: back on Active without a request', JSON.stringify(ids(t.c, 'plan')) === '[1,2]' && t.ncalls() === 2,
        [ids(t.c, 'plan'), t.ncalls()]);
    pill(t.c, 'review').click();
    await settle();
    check('bucket cache: the fetched Review bucket is kept', JSON.stringify(ids(t.c, 'plan')) === '[21]' && t.ncalls() === 2, [ids(t.c, 'plan'), t.ncalls()]);
    check('bucket cache: the competencies are untouched', JSON.stringify(ids(t.c, 'competency')) === '[11]', ids(t.c, 'competency'));

    // A search that needs the rest of the competencies.
    const s = mount({favouritesenabled: true});
    s.respond(0, base({plancards: [card(1, 'Alpha', {isfavourite: true})], competencycards: [card(11, 'Comp a', {isfavourite: true})],
        totalplans: 1, totalcompetencies: 2, hasnonfavouritecompetencies: true, favouritesenabled: true}));
    await settle();
    typeSearch(s.c, 'comp');
    await sleep(200);
    check('search loads the rest: a search fetches the other competencies', s.call(1) && s.call(1).args.loadgroup === 'competency', s.call(1) && s.call(1).args);
    s.respond(1, base({competencycards: [card(11, 'Comp a', {isfavourite: true}), card(12, 'Comp b')], totalcompetencies: 2,
        favouritesenabled: true}));
    await settle();
    check('search loads the rest: the search results', JSON.stringify(ids(s.c, 'competency')) === '[11,12]' && JSON.stringify(ids(s.c, 'plan')) === '[]',
        [ids(s.c, 'competency'), ids(s.c, 'plan')]);
    await sleep(700);
    check('search loads the rest: the results are announced', s.c.querySelector('[data-results-status]').textContent === 'P=0 C=2',
        s.c.querySelector('[data-results-status]').textContent);
}

const competencyFavouritesOnly = () => base({plancards: [card(1, 'Alpha', {isfavourite: true})],
    competencycards: [card(11, 'Comp a', {isfavourite: true})], totalplans: 1, totalcompetencies: 2,
    hasnonfavouritecompetencies: true, favouritesenabled: true});
const competenciesWhole = () => base({competencycards: [card(11, 'Comp a', {isfavourite: true}), card(12, 'Comp b')],
    totalcompetencies: 2, favouritesenabled: true});

async function loadingLine() {
    // A group load that ends while a bucket is still loading.
    const b = mount({favouritesenabled: true});
    b.respond(0, competencyFavouritesOnly());
    await settle();
    check('loading line: precondition: hidden after the favourites-only response', !loading(b.c), loading(b.c));
    pill(b.c, 'review').click(); // Call 1, the bucket.
    await settle();
    check('loading line: a bucket loading alone names its bucket', loading(b.c) && loadText(b.c) === 'LOADING-REVIEW', [loading(b.c), loadText(b.c)]);
    typeSearch(b.c, 'comp'); // Call 2, the rest of the competencies.
    await sleep(200);
    check('loading line: precondition: a search loads the competencies while the bucket loads', b.ncalls() === 3 && b.call(2).args.loadgroup === 'competency',
        b.ncalls());
    check('loading line: a group load started during a bucket shows the page text', loading(b.c) && loadText(b.c) === 'Loading...',
        [loading(b.c), loadText(b.c)]);
    if (b.ncalls() === 3) {
        b.respond(2, competenciesWhole());
        await settle();
    }
    check('loading line: the group load ending first keeps the line with the bucket text',
        loading(b.c) && loadText(b.c) === 'LOADING-REVIEW' && skel(b.c) > 0, [loading(b.c), loadText(b.c), skel(b.c)]);
    b.respond(1, base({favouritesenabled: true, planstatus: 'review', plancards: [card(21, 'Rev')], totalplans: 1}));
    await settle();
    check('loading line: everything landed hides the line and restores the page text', !loading(b.c) && loadText(b.c) === 'Loading...', [loading(b.c), loadText(b.c)]);

    // A bucket that lands while a group load is still pending.
    const g = mount({favouritesenabled: true});
    g.respond(0, competencyFavouritesOnly());
    await settle();
    g.c.querySelector('.dims-all-filter-btn[data-all-filter-type="competency"]').click(); // Call 1, the group.
    await settle();
    check('loading line: a group load alone shows the page text', loading(g.c) && loadText(g.c) === 'Loading...', [loading(g.c), loadText(g.c)]);
    pill(g.c, 'review').click(); // Call 2, the bucket.
    await settle();
    check('loading line: a bucket started during a group load keeps the page text', loading(g.c) && loadText(g.c) === 'Loading...',
        [loading(g.c), loadText(g.c)]);
    g.respond(2, base({favouritesenabled: true, planstatus: 'review', plancards: [card(21, 'Rev')], totalplans: 1}));
    await settle();
    check('loading line: the bucket landing first keeps the line', loading(g.c) && loadText(g.c) === 'Loading...',
        [loading(g.c), loadText(g.c)]);
    g.respond(1, competenciesWhole());
    await settle();
    check('loading line: the group load landing last hides the line', !loading(g.c), loading(g.c));
}

const searchFavouritesOnly = () => base({plancards: [card(1, 'Alpha', {isfavourite: true})],
    competencycards: [card(11, 'Comp one', {isfavourite: true})], totalplans: 2, totalcompetencies: 2,
    hasnonfavouriteplans: true, hasnonfavouritecompetencies: true, favouritesenabled: true});
const searchWhole = () => base({plancards: [card(1, 'Alpha', {isfavourite: true}), card(2, 'Beta')],
    competencycards: [card(11, 'Comp one', {isfavourite: true}), card(12, 'Comp beta')], totalplans: 2, totalcompetencies: 2,
    favouritesenabled: true});

async function searchAtLoad() {
    // Retry while the input holds a search term.
    const b = mount({favouritesenabled: true});
    b.fail(0);
    await settle();
    typeSearch(b.c, 'beta');
    await sleep(200);
    await settle();
    b.c.querySelector('.dims-error-retry').click(); // Call 1.
    await settle();
    b.respond(1, searchFavouritesOnly());
    await settle();
    check('search at load: a retry under a search fetches the rest', b.ncalls() === 3 && b.call(2).args.loadgroup === ''
        && b.call(2).args.favouritesonly === false, b.ncalls() === 3 ? b.call(2).args : b.ncalls());
    if (b.ncalls() === 3) {
        b.respond(2, searchWhole());
        await settle();
    }
    check('search at load: a retry under a search leaves the favourites filter off', favChecked(b.c, 'plan') === 'false'
        && favChecked(b.c, 'competency') === 'false', [favChecked(b.c, 'plan'), favChecked(b.c, 'competency')]);
    check('search at load: a retry under a search finds the non-favourites', JSON.stringify(ids(b.c, 'plan')) === '[2]'
        && JSON.stringify(ids(b.c, 'competency')) === '[12]', [ids(b.c, 'plan'), ids(b.c, 'competency')]);

    // A value the browser put back in the input before init.
    const r = mount({favouritesenabled: true}, 'beta');
    check('search at load: a value put back in the input shows the clear button', r.c.querySelector('.dims-block-search-clear').style.display === 'flex',
        r.c.querySelector('.dims-block-search-clear').style.display);
    r.respond(0, searchFavouritesOnly());
    await settle();
    check('search at load: a value put back in the input fetches the rest', r.ncalls() === 2 && r.call(1).args.loadgroup === '', r.ncalls());
    if (r.ncalls() === 2) {
        r.respond(1, searchWhole());
        await settle();
    }
    check('search at load: a value put back in the input filters every card', JSON.stringify(ids(r.c, 'plan')) === '[2]'
        && JSON.stringify(ids(r.c, 'competency')) === '[12]', [ids(r.c, 'plan'), ids(r.c, 'competency')]);

    // A search typed while the first request is still on its way.
    const e = mount({favouritesenabled: true});
    typeSearch(e.c, 'beta');
    await sleep(200);
    e.respond(0, searchFavouritesOnly());
    await settle();
    check('search at load: a search typed during the first fetch fetches the rest', e.ncalls() === 2 && e.call(1).args.loadgroup === '', e.ncalls());
    if (e.ncalls() === 2) {
        e.respond(1, searchWhole());
        await settle();
    }
    check('search at load: a search typed during the first fetch filters every card', JSON.stringify(ids(e.c, 'plan')) === '[2]'
        && JSON.stringify(ids(e.c, 'competency')) === '[12]', [ids(e.c, 'plan'), ids(e.c, 'competency')]);

    // Control: with no search term the retry still opens under the favourites filter, phase 1 only.
    const n = mount({favouritesenabled: true});
    n.fail(0);
    await settle();
    n.c.querySelector('.dims-error-retry').click(); // Call 1.
    await settle();
    n.respond(1, searchFavouritesOnly());
    await settle();
    check('search at load: control: with no search, the favourites-first view is kept', n.ncalls() === 2 && favChecked(n.c, 'plan') === 'true'
        && JSON.stringify(ids(n.c, 'plan')) === '[1]', [n.ncalls(), favChecked(n.c, 'plan'), ids(n.c, 'plan')]);
}

async function beforeTheFirstDataset() {
    // A search typed before the first dataset lands.
    const b = mount();
    typeSearch(b.c, 'x');
    await sleep(200);
    await settle();
    check('before the first dataset: a search shows no empty-state line', empty(b.c) === '' && loading(b.c), [empty(b.c), loading(b.c)]);
    await sleep(700);
    check('before the first dataset: a search announces nothing', region(b.c) === '', region(b.c));
    b.respond(0, base({hasactiveplans: false, hasplancards: false, hascompetencies: false,
        plancounts: {active: 0, review: 0, complete: 0}}));
    await settle();
    check('before the first dataset: control: the empty-state line once the dataset lands', empty(b.c) === 'NOACTIVE', empty(b.c));
    await sleep(700);
    check('before the first dataset: control: the announcement once the dataset lands', region(b.c) === 'NONEFOUND', region(b.c));

    // The retry window behaves the same.
    const r = mount();
    r.respond(0, base({plancards: [card(1, 'Alpha'), card(2, 'Beta')], competencycards: [], totalplans: 2}));
    await settle();
    pill(r.c, 'review').click(); // Call 1.
    await settle();
    r.fail(1);
    await settle();
    r.c.querySelector('.dims-error-retry').click(); // Call 2.
    await settle();
    typeSearch(r.c, 'zzz');
    await sleep(200);
    await settle();
    check('before the first dataset: a search while the retry loads shows no empty-state line', empty(r.c) === '', empty(r.c));
}

async function retryFocus() {
    const b = mount();
    b.fail(0);
    await settle();
    const retry = b.c.querySelector('.dims-error-retry');
    const input = b.c.querySelector('.dims-block-search-input');
    // Control: a retry activated while focus is elsewhere leaves focus alone.
    input.focus();
    retry.click(); // Call 1.
    await settle();
    check('retry focus: control: a retry activated from elsewhere leaves focus alone', document.activeElement === input && !b.c.hasAttribute('tabindex'),
        document.activeElement && document.activeElement.className);
    b.fail(1);
    await settle();
    retry.focus();
    check('retry focus: precondition: the retry button is focused', document.activeElement === retry, document.activeElement && document.activeElement.className);
    retry.click(); // Call 2.
    await settle();
    check('retry focus: the retry moves focus to the block', document.activeElement === b.c && !errorShown(b.c),
        document.activeElement && (document.activeElement.id || document.activeElement.tagName));
    b.respond(2, base({plancards: [card(1, 'Alpha'), card(2, 'Beta')], competencycards: [], totalplans: 2}));
    await settle();
    check('retry focus: focus stays on the block once loaded', document.activeElement === b.c,
        document.activeElement && (document.activeElement.id || document.activeElement.tagName));
    input.focus();
    check('retry focus: the block drops its tabindex once focus leaves', document.activeElement === input && !b.c.hasAttribute('tabindex'),
        b.c.getAttribute('tabindex'));
}

async function failedSwitchKeepsFilters() {
    // Tag filter over the bucket left.
    const fs = {plan: {tag1enabled: true, tag1displaymode: 'tabs'}, competency: {}};
    const t = mount({filtersettings: fs});
    t.respond(0, base({filtersettings: fs, plancards: [card(1, 'A', {tag1: 'x'}), card(2, 'B', {tag1: 'y'})], totalplans: 2,
        competencycards: [card(11, 'C')], totalcompetencies: 1}));
    await settle();
    t.c.querySelector('[data-filter-field="plan_tag1"][data-filter-value="x"]').click();
    await settle();
    check('failed switch keeps filters: precondition: the tag filter is applied', JSON.stringify(ids(t.c, 'plan')) === '[1]', ids(t.c, 'plan'));
    pill(t.c, 'review').click(); // Call 1.
    await settle();
    t.fail(1);
    await settle();
    const x = t.c.querySelector('[data-filter-field="plan_tag1"][data-filter-value="x"]');
    const clearBtn = t.c.querySelector('.dims-clear-filters-btn[data-clear-type="plan"]');
    check('failed switch keeps filters: the tag filter holds after a failed switch', JSON.stringify(ids(t.c, 'plan')) === '[1]' && x && x.getAttribute('aria-checked') === 'true'
        && vis(clearBtn), [ids(t.c, 'plan'), x && x.getAttribute('aria-checked'), clearBtn && clearBtn.style.display]);
    // Control: a switch that succeeds starts the new bucket unfiltered.
    pill(t.c, 'review').click(); // Call 2.
    await settle();
    t.respond(2, base({planstatus: 'review', filtersettings: fs, plancards: [card(21, 'R', {tag1: 'x'}), card(22, 'S', {tag1: 'z'})],
        totalplans: 2}));
    await settle();
    check('failed switch keeps filters: control: a landed switch starts unfiltered', JSON.stringify(ids(t.c, 'plan')) === '[21,22]', ids(t.c, 'plan'));

    // Favourites filter over the bucket left, with every plan loaded.
    const f = mount({favouritesenabled: true});
    f.respond(0, base({plancards: [card(1, 'Alpha', {isfavourite: true}), card(2, 'Beta')], competencycards: [card(11, 'C')],
        totalplans: 2, totalcompetencies: 1, favouritesenabled: true}));
    await settle();
    f.c.querySelector('.dims-fav-filter-btn[data-fav-filter-type="plan"]').click();
    await settle();
    check('failed switch keeps filters: precondition: the favourites filter is applied', favChecked(f.c, 'plan') === 'true' && JSON.stringify(ids(f.c, 'plan')) === '[1]',
        [favChecked(f.c, 'plan'), ids(f.c, 'plan')]);
    pill(f.c, 'review').click(); // Call 1.
    await settle();
    f.fail(1);
    await settle();
    check('failed switch keeps filters: the favourites filter holds after a failed switch', favChecked(f.c, 'plan') === 'true'
        && JSON.stringify(ids(f.c, 'plan')) === '[1]', [favChecked(f.c, 'plan'), ids(f.c, 'plan')]);
}

async function announcementPerBlock() {
    const data = () => base({plancards: [card(1, 'Alpha'), card(2, 'Beta')], competencycards: [], totalplans: 2});
    const a = mount();
    a.respond(0, data());
    await settle();
    await sleep(700);
    const b = mount();
    b.respond(0, data());
    await settle();
    await sleep(700);
    check('announcement per block: precondition: both blocks announced their load', region(a.c) === 'P=2 C=0' && region(b.c) === 'P=2 C=0',
        [region(a.c), region(b.c)]);
    typeSearch(a.c, 'alpha');
    typeSearch(b.c, 'beta');
    await sleep(200);
    await settle();
    await sleep(700);
    check('announcement per block: two blocks searching at once both announce', region(a.c) === 'P=1 C=0' && region(b.c) === 'P=1 C=0', [region(a.c), region(b.c)]);
}

async function activePill() {
    // The Active pill must survive a switch away from it: hasactiveplans does not depend on the
    // bucket on screen, and a plan on a competencies-mode template adds no plan card, so the Active
    // count is 0 while the learner still has an active plan. On the first render planStatus is
    // 'active', which alone keeps the pill drawn, so the check looks from another bucket, where only
    // hasactiveplans can keep it.
    const b = mount();
    b.respond(0, base({plancounts: {active: 0, review: 1, complete: 1}, hasactiveplans: true,
        plancards: [], competencycards: [card(11, 'Comp')], hasplancards: false, hascompetencies: true,
        totalplans: 0, totalcompetencies: 1}));
    await settle();
    pill(b.c, 'review').click();
    await settle();
    b.respond(1, base({planstatus: 'review', plancounts: {active: 0, review: 1, complete: 1},
        hasactiveplans: true, plancards: [card(21, 'Rev')], totalplans: 1}));
    await settle();
    check('active pill: precondition: switched off the Active bucket', checked(b.c) === 'review', checked(b.c));
    const activePill = pill(b.c, 'active');
    check('active pill: the Active pill is offered although its count is 0', !!activePill, !!activePill);
    check('active pill: the Active pill carries no zero count',
        !!activePill && !activePill.querySelector('.dims-filter-count'),
        activePill && activePill.innerHTML);
}

async function competencyLoadAsksActive() {
    // A competency load fired from a non-active bucket must still ask the server for the active
    // one: dataset_provider::get_dataset() builds competency cards only for the active bucket, so
    // any other planstatus value comes back with none. The mocked response models that, keyed on
    // the request the module actually sent.
    const b = mount({favouritesenabled: true});
    b.respond(0, base({plancounts: {active: 1, review: 0, complete: 1}, hasactiveplans: true,
        plancards: [card(1, 'Alpha', {isfavourite: true})], competencycards: [card(11, 'Comp fav', {isfavourite: true})],
        totalplans: 1, totalcompetencies: 2, hasnonfavouriteplans: false, hasnonfavouritecompetencies: true,
        favouritesenabled: true}));
    await settle();
    pill(b.c, 'complete').click();
    await settle();
    b.respond(1, base({favouritesenabled: true, planstatus: 'complete', plancards: [card(41, 'Done')], totalplans: 1}));
    await settle();
    check('competency load bucket: precondition: switched to Completed', checked(b.c) === 'complete', checked(b.c));
    b.c.querySelector('.dims-all-filter-btn[data-all-filter-type="competency"]').click();
    await settle();
    const args = b.call(2) && b.call(2).args;
    check('competency load bucket: a competency load from Completed asks for the active bucket',
        !!args && args.planstatus === 'active', args);
    const askedActive = !!args && args.planstatus === 'active';
    b.respond(2, base({
        competencycards: askedActive ? [card(11, 'Comp fav', {isfavourite: true}), card(12, 'Comp b')] : [],
        totalcompetencies: askedActive ? 2 : 0, favouritesenabled: true
    }));
    await settle();
    check('competency load bucket: the competency cards survive a load from Completed',
        JSON.stringify(ids(b.c, 'competency')) === '[11,12]', ids(b.c, 'competency'));
}

async function keptActiveList() {
    // The active bucket's kept list can still be the favourites-only first response (never fully
    // loaded). Leaving and returning to it must restore the favourites filter and its ghost card,
    // not fall back to "Show all" over a partial list with no way to fetch the rest.
    const b = mount({favouritesenabled: true});
    b.respond(0, base({plancounts: {active: 2, review: 1, complete: 0}, hasactiveplans: true,
        plancards: [card(1, 'Alpha', {isfavourite: true})], competencycards: [],
        totalplans: 2, totalcompetencies: 0, hasnonfavouriteplans: true, hasnonfavouritecompetencies: false,
        favouritesenabled: true}));
    await settle();
    check('kept active list: precondition: the favourites filter is on after the favourites-only response', favChecked(b.c, 'plan') === 'true',
        favChecked(b.c, 'plan'));
    pill(b.c, 'review').click();
    await settle();
    b.respond(1, base({favouritesenabled: true, planstatus: 'review', plancards: [card(21, 'Rev')], totalplans: 1}));
    await settle();
    check('kept active list: precondition: switched to Review', checked(b.c) === 'review', checked(b.c));
    pill(b.c, 'active').click();
    await settle();
    check('kept active list: returning to Active restores the favourites filter',
        favChecked(b.c, 'plan') === 'true' && JSON.stringify(ids(b.c, 'plan')) === '[1]',
        [favChecked(b.c, 'plan'), ids(b.c, 'plan')]);
    check('kept active list: returning to Active offers the ghost card',
        !!b.c.querySelector('.dims-ghost-card[data-ghost-type="plan"]'),
        !!b.c.querySelector('.dims-ghost-card[data-ghost-type="plan"]'));
}

async function quotedTagValue() {
    // A tag value holding a double quote must not break the CSS selector captureFocusKey() builds
    // to find its pill again after a rebuild. The clear-filters button for 'plan' triggers a full
    // filter-bar rebuild (both types) without touching the competency tag filter under test.
    const fs = {plan: {}, competency: {tag1enabled: true, tag1displaymode: 'tabs'}};
    const b = mount({filtersettings: fs});
    b.respond(0, base({filtersettings: fs, plancounts: {active: 1, review: 1, complete: 0}, hasactiveplans: true,
        plancards: [card(1, 'P1')], totalplans: 1,
        competencycards: [card(11, 'C1', {tag1: 'A"B'}), card(12, 'C2', {tag1: 'Normal'})], totalcompetencies: 2}));
    await settle();
    const quotePill = Array.from(b.c.querySelectorAll('[data-filter-field="competency_tag1"]'))
        .find((el) => el.dataset.filterValue === 'A"B');
    check('quoted tag value: precondition: a tag pill with a quoted value is drawn', !!quotePill, !!quotePill);
    quotePill.click();
    await settle();
    check('quoted tag value: precondition: its filter is applied', JSON.stringify(ids(b.c, 'competency')) === '[11]',
        ids(b.c, 'competency'));
    quotePill.focus();
    b.c.querySelector('.dims-clear-filters-btn[data-clear-type="plan"]').click();
    await settle();
    const focusedPill = document.activeElement;
    check('quoted tag value: focus returns to the pill after a rebuild',
        !!focusedPill && !!focusedPill.dataset && focusedPill.dataset.filterField === 'competency_tag1'
            && focusedPill.dataset.filterValue === 'A"B',
        focusedPill && focusedPill.dataset ? focusedPill.dataset.filterValue : (focusedPill && focusedPill.className));
    check('quoted tag value: the filter still holds after the rebuild',
        JSON.stringify(ids(b.c, 'competency')) === '[11]', ids(b.c, 'competency'));
}

async function noResultWording() {
    // A search that hides every card must read differently from a dataset with no cards at all.
    const b = mount();
    b.respond(0, base({hasactiveplans: true, plancards: [card(1, 'Alpha')], competencycards: [], totalplans: 1}));
    await settle();
    typeSearch(b.c, 'zzz');
    await sleep(200);
    await settle();
    check('no-result wording: a search hiding every card reads resultsnonefound',
        empty(b.c) === L.resultsnonefound, empty(b.c));

    // Control: a genuinely empty dataset still reads nocompetencies.
    const n = mount();
    n.respond(0, base({hasactiveplans: true, hasplancards: false, hascompetencies: false,
        plancards: [], competencycards: [], totalplans: 0}));
    await settle();
    check('no-result wording: control: an empty dataset reads nocompetencies',
        empty(n.c) === L.nocompetencies, empty(n.c));
}

function readSource(file) {
    return new Promise((resolve) => {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', window.srcBase + file);
        xhr.onload = () => resolve(xhr.responseText);
        xhr.onerror = () => resolve(null);
        xhr.send();
    });
}

async function sourceDocblock() {
    // The file docblock of filter_tabs_nav.js carries no attribution, and its feature list keeps a lead-in.
    const src = await readSource('filter_tabs_nav.js');
    check('source docblock: precondition: filter_tabs_nav.js is read', typeof src === 'string' && src.length > 1000, src && src.length);
    const doc = (src || '').slice(0, (src || '').indexOf('*/'));
    check('source docblock: no attribution in filter_tabs_nav.js',
        !/adapted from/i.test(doc) && !/apple|macbook/i.test(src || ''), doc);
    check('source docblock: the feature list keeps its lead-in', /\n \* Provides:\n \* - Smooth horizontal scroll/.test(doc), doc);
}

async function activeLoadingText() {
    // The block opens on Completed; the Active bucket is fetched the first time it is picked.
    const b = mount();
    b.respond(0, base({planstatus: 'complete', plancounts: {active: 2, review: 1, complete: 1}, hasactiveplans: true,
        plancards: [card(41, 'Done')], competencycards: [], totalplans: 1}));
    await settle();
    check('loading line: precondition: opened on Completed', checked(b.c) === 'complete' && !loading(b.c), [checked(b.c), loading(b.c)]);
    pill(b.c, 'active').click(); // Call 1.
    await settle();
    check('loading line: precondition: the Active bucket is fetched', b.ncalls() === 2 && b.call(1).args.planstatus === 'active',
        b.ncalls() === 2 ? b.call(1).args : b.ncalls());
    check('loading line: the Active bucket loading names its bucket', loading(b.c) && loadText(b.c) === 'LOADING-ACTIVE',
        [loading(b.c), loadText(b.c)]);
    b.respond(1, base({planstatus: 'active', plancounts: {active: 2, review: 1, complete: 1},
        plancards: [card(1, 'Alpha'), card(2, 'Beta')], totalplans: 2}));
    await settle();
    check('loading line: the Active bucket landed hides the line', !loading(b.c) && JSON.stringify(ids(b.c, 'plan')) === '[1,2]',
        [loading(b.c), ids(b.c, 'plan')]);
    // Control: the Review bucket still reads its own text.
    pill(b.c, 'review').click(); // Call 2.
    await settle();
    check('loading line: control: the Review bucket loading names its bucket', loading(b.c) && loadText(b.c) === 'LOADING-REVIEW',
        [loading(b.c), loadText(b.c)]);

    // A payload without the Active label falls back to the page's own text.
    const labels = Object.assign({}, L);
    delete labels.statusloadingactive;
    const f = mount({labels: labels});
    f.respond(0, base({planstatus: 'complete', plancounts: {active: 2, review: 1, complete: 1}, hasactiveplans: true,
        plancards: [card(41, 'Done')], competencycards: [], totalplans: 1}));
    await settle();
    pill(f.c, 'active').click(); // Call 1.
    await settle();
    check('loading line: a payload without the Active label shows the page text', loading(f.c) && loadText(f.c) === 'Loading...',
        [loading(f.c), loadText(f.c)]);
}

const activeFavouritesOnly = () => base({plancounts: {active: 2, review: 1, complete: 0}, hasactiveplans: true,
    plancards: [card(1, 'Alpha', {isfavourite: true})], competencycards: [card(11, 'Comp one', {isfavourite: true})],
    totalplans: 2, totalcompetencies: 1, hasnonfavouriteplans: true, hasnonfavouritecompetencies: false,
    favouritesenabled: true});
const activeWhole = () => base({plancounts: {active: 2, review: 1, complete: 0},
    plancards: [card(1, 'Alpha', {isfavourite: true}), card(2, 'Beta')], totalplans: 2, favouritesenabled: true});

async function failedSwitchUnderASearch() {
    // A search typed while a bucket switch is in flight, and the switch fails.
    const b = mount({favouritesenabled: true});
    b.respond(0, activeFavouritesOnly());
    await settle();
    check('failed switch under a search: precondition: the favourites-only list under the favourites filter', favChecked(b.c, 'plan') === 'true'
        && !!b.c.querySelector('.dims-ghost-card[data-ghost-type="plan"]'), favChecked(b.c, 'plan'));
    pill(b.c, 'review').click(); // Call 1.
    await settle();
    typeSearch(b.c, 'beta');
    await sleep(200);
    await settle();
    check('failed switch under a search: precondition: the search fetched nothing during the switch', b.ncalls() === 2, b.ncalls());
    b.fail(1);
    await settle();
    check('failed switch under a search: precondition: back on Active with the error', checked(b.c) === 'active' && errorShown(b.c),
        [checked(b.c), errorShown(b.c)]);
    check('failed switch under a search: the favourites filter is off', favChecked(b.c, 'plan') === 'false',
        favChecked(b.c, 'plan'));
    const args = b.ncalls() === 3 ? b.call(2).args : null;
    check('failed switch under a search: the missing active plans are fetched', !!args && args.loadgroup === 'plan'
        && args.planstatus === 'active' && args.favouritesonly === false, args || b.ncalls());
    if (args) {
        b.respond(2, activeWhole());
        await settle();
    }
    check('failed switch under a search: the search finds the non-favourite plan', JSON.stringify(ids(b.c, 'plan')) === '[2]'
        && !b.c.querySelector('.dims-ghost-card[data-ghost-type="plan"]'), [ids(b.c, 'plan'), favChecked(b.c, 'plan')]);

    // Control: a search typed before the switch, then the favourites pill picked over its results;
    // a failed switch returns to exactly that, with no request.
    const c = mount({favouritesenabled: true});
    c.respond(0, activeFavouritesOnly());
    await settle();
    typeSearch(c.c, 'a');
    await sleep(200);
    check('failed switch under a search: control precondition: the search fetched the active plans', c.ncalls() === 2 && c.call(1).args.loadgroup === 'plan',
        c.ncalls());
    if (c.ncalls() === 2) {
        c.respond(1, activeWhole());
        await settle();
    }
    c.c.querySelector('.dims-fav-filter-btn[data-fav-filter-type="plan"]').click();
    await settle();
    check('failed switch under a search: control precondition: favourites picked over the search', favChecked(c.c, 'plan') === 'true'
        && JSON.stringify(ids(c.c, 'plan')) === '[1]', [favChecked(c.c, 'plan'), ids(c.c, 'plan')]);
    pill(c.c, 'review').click(); // Call 2.
    await settle();
    c.fail(2);
    await settle();
    check('failed switch under a search: control: a search set before the switch keeps the filters the grid left', favChecked(c.c, 'plan') === 'true'
        && JSON.stringify(ids(c.c, 'plan')) === '[1]' && c.ncalls() === 3, [favChecked(c.c, 'plan'), ids(c.c, 'plan'), c.ncalls()]);

    // Control: no search during the switch; phase 1 comes back under the favourites filter, no request.
    const n = mount({favouritesenabled: true});
    n.respond(0, activeFavouritesOnly());
    await settle();
    pill(n.c, 'review').click(); // Call 1.
    await settle();
    n.fail(1);
    await settle();
    check('failed switch under a search: control: with no search, the favourites-only list returns under its filter', favChecked(n.c, 'plan') === 'true'
        && JSON.stringify(ids(n.c, 'plan')) === '[1]' && n.ncalls() === 2, [favChecked(n.c, 'plan'), ids(n.c, 'plan'), n.ncalls()]);
}

async function sharedMetadata() {
    // An active plan on a competencies-mode template: no Active count, so the pill rides hasactiveplans alone.
    const counts = {active: 0, review: 1, complete: 1};

    // The bucket handler (pickStatus): hasactiveplans turns true on a later bucket response.
    const p = mount();
    p.respond(0, base({planstatus: 'review', plancounts: counts, hasactiveplans: false,
        plancards: [card(21, 'Rev')], competencycards: [], hascompetencies: false, totalplans: 1}));
    await settle();
    check('shared metadata: bucket response precondition: no Active pill', !pill(p.c, 'active') && checked(p.c) === 'review',
        [!!pill(p.c, 'active'), checked(p.c)]);
    pill(p.c, 'complete').click(); // Call 1.
    await settle();
    p.respond(1, base({planstatus: 'complete', plancounts: counts, hasactiveplans: true, plancards: [card(41, 'Done')],
        totalplans: 1}));
    await settle();
    check('shared metadata: a bucket response turning hasactiveplans on draws the Active pill', !!pill(p.c, 'active'),
        !!pill(p.c, 'active'));

    // The bucket handler also stores the filter settings.
    const fs = {plan: {tag1enabled: true, tag1displaymode: 'tabs'}, competency: {}};
    const t = mount();
    t.respond(0, base({plancards: [card(1, 'A', {tag1: 'x'})], competencycards: [], totalplans: 1}));
    await settle();
    check('shared metadata: bucket response precondition: no tag pills while tags are off', tagPills(t.c) === 0, tagPills(t.c));
    pill(t.c, 'review').click(); // Call 1.
    await settle();
    t.respond(1, base({planstatus: 'review', filtersettings: fs, plancards: [card(21, 'R', {tag1: 'y'})], totalplans: 1}));
    await settle();
    check('shared metadata: a bucket response carrying new filter settings draws the tag pills', tagPills(t.c) === 2,
        tagPills(t.c));

    // The group handler (loadGroupDataset): hasactiveplans turns false on a later competency response.
    const g = mount({favouritesenabled: true});
    g.respond(0, base({plancounts: counts, hasactiveplans: true, plancards: [], hasplancards: false,
        competencycards: [card(11, 'Comp one', {isfavourite: true})], totalcompetencies: 2,
        hasnonfavouritecompetencies: true, favouritesenabled: true}));
    await settle();
    pill(g.c, 'review').click(); // Call 1.
    await settle();
    g.respond(1, base({favouritesenabled: true, planstatus: 'review', plancounts: counts, hasactiveplans: true,
        plancards: [card(21, 'Rev')], totalplans: 1}));
    await settle();
    check('shared metadata: group response precondition: the Active pill is drawn on Review', checked(g.c) === 'review' && !!pill(g.c, 'active'),
        [checked(g.c), !!pill(g.c, 'active')]);
    g.c.querySelector('.dims-all-filter-btn[data-all-filter-type="competency"]').click(); // Call 2.
    await settle();
    check('shared metadata: group response precondition: the competency group is fetched', g.ncalls() === 3 && g.call(2).args.loadgroup === 'competency',
        g.ncalls());
    if (g.ncalls() === 3) {
        g.respond(2, base({plancounts: counts, hasactiveplans: false, competencycards: [card(11, 'Comp one', {isfavourite: true})],
            totalcompetencies: 1, favouritesenabled: true}));
        await settle();
    }
    check('shared metadata: a group response turning hasactiveplans off removes the Active pill', !pill(g.c, 'active')
        && checked(g.c) === 'review', [!!pill(g.c, 'active'), checked(g.c)]);

    // The first-request handler (loadData), reached again through Retry: hasactiveplans turns true.
    const r = mount();
    r.respond(0, base({planstatus: 'review', plancounts: counts, hasactiveplans: false,
        plancards: [card(21, 'Rev')], competencycards: [], hascompetencies: false, totalplans: 1}));
    await settle();
    pill(r.c, 'complete').click(); // Call 1.
    await settle();
    r.fail(1);
    await settle();
    check('shared metadata: first response precondition: no Active pill before the retry', !pill(r.c, 'active') && errorShown(r.c),
        [!!pill(r.c, 'active'), errorShown(r.c)]);
    r.c.querySelector('.dims-error-retry').click(); // Call 2.
    await settle();
    r.respond(2, base({planstatus: 'review', plancounts: counts, hasactiveplans: true,
        plancards: [card(21, 'Rev')], competencycards: [], hascompetencies: false, totalplans: 1}));
    await settle();
    check('shared metadata: a first response turning hasactiveplans on draws the Active pill', !!pill(r.c, 'active')
        && checked(r.c) === 'review', [!!pill(r.c, 'active'), checked(r.c)]);
}

const ghostPlan = (c) => !!c.querySelector('.dims-ghost-card[data-ghost-type="plan"]');
const favPills = (c) => c.querySelectorAll('.dims-fav-filter-btn, .dims-all-filter-btn').length;
const clearShown = (c, type) => vis(c.querySelector('.dims-clear-filters-btn[data-clear-type="' + type + '"]'));
const reviewBucket = (favouritesenabled) => base({favouritesenabled: favouritesenabled, planstatus: 'review',
    plancounts: {active: 2, review: 1, complete: 0}, plancards: [card(21, 'Rev')], totalplans: 1});
// With favourites disabled the server marks no card a favourite.
const activeWholeFavouritesOff = () => base({plancounts: {active: 2, review: 1, complete: 0},
    plancards: [card(1, 'Alpha'), card(2, 'Beta')], totalplans: 2, favouritesenabled: false});

async function backOnActiveUnderASearch() {
    // A search typed on Review, then back to Active, whose kept list is the favourites-only first response.
    const b = mount({favouritesenabled: true});
    b.respond(0, activeFavouritesOnly());
    await settle();
    pill(b.c, 'review').click(); // Call 1.
    await settle();
    b.respond(1, reviewBucket(true));
    await settle();
    typeSearch(b.c, 'beta');
    await sleep(200);
    await settle();
    check('back on active under a search: precondition: the search on Review fetched nothing', b.ncalls() === 2, b.ncalls());
    pill(b.c, 'active').click();
    await settle();
    check('back on active under a search: the favourites filter stays off, no ghost card', favChecked(b.c, 'plan') === 'false'
        && !ghostPlan(b.c) && checked(b.c) === 'active', [favChecked(b.c, 'plan'), ghostPlan(b.c), checked(b.c)]);
    const args = b.ncalls() === 3 ? b.call(2).args : null;
    check('back on active under a search: the missing active plans are fetched', !!args && args.loadgroup === 'plan'
        && args.planstatus === 'active' && args.favouritesonly === false, args || b.ncalls());
    if (args) {
        b.respond(2, activeWhole());
        await settle();
    }
    check('back on active under a search: the search finds the non-favourite plan', JSON.stringify(ids(b.c, 'plan')) === '[2]'
        && !ghostPlan(b.c) && !loading(b.c) && empty(b.c) === '', [ids(b.c, 'plan'), ghostPlan(b.c), loading(b.c), empty(b.c)]);

    // Control: the search is cleared before going back, so phase 1 returns under the favourites filter with no request.
    const n = mount({favouritesenabled: true});
    n.respond(0, activeFavouritesOnly());
    await settle();
    pill(n.c, 'review').click(); // Call 1.
    await settle();
    n.respond(1, reviewBucket(true));
    await settle();
    typeSearch(n.c, 'beta');
    await sleep(200);
    typeSearch(n.c, '');
    await sleep(200);
    await settle();
    pill(n.c, 'active').click();
    await settle();
    check('back on active under a search: control: a search cleared first brings the favourites-only list back under its filter', favChecked(n.c, 'plan') === 'true'
        && ghostPlan(n.c) && JSON.stringify(ids(n.c, 'plan')) === '[1]' && n.ncalls() === 2,
        [favChecked(n.c, 'plan'), ghostPlan(n.c), ids(n.c, 'plan'), n.ncalls()]);

    // Control: every active plan is a favourite, only competencies are missing; the search fetches no plan.
    const a = mount({favouritesenabled: true});
    a.respond(0, base({plancounts: {active: 1, review: 1, complete: 0}, plancards: [card(1, 'Alpha', {isfavourite: true})],
        competencycards: [card(11, 'Comp one', {isfavourite: true})], totalplans: 1, totalcompetencies: 2,
        hasnonfavouriteplans: false, hasnonfavouritecompetencies: true, favouritesenabled: true}));
    await settle();
    pill(a.c, 'review').click(); // Call 1.
    await settle();
    a.respond(1, reviewBucket(true));
    await settle();
    typeSearch(a.c, 'alpha');
    await sleep(200);
    check('back on active under a search: control precondition: the search on Review fetched the competencies', a.ncalls() === 3
        && a.call(2).args.loadgroup === 'competency', a.ncalls() === 3 ? a.call(2).args : a.ncalls());
    if (a.ncalls() === 3) {
        a.respond(2, base({competencycards: [card(11, 'Comp one', {isfavourite: true}), card(12, 'Comp two')],
            totalcompetencies: 2, favouritesenabled: true}));
        await settle();
    }
    pill(a.c, 'active').click();
    await settle();
    check('back on active under a search: control: a search over a whole active list fetches no plan', a.ncalls() === 3
        && JSON.stringify(ids(a.c, 'plan')) === '[1]', [a.ncalls(), ids(a.c, 'plan')]);
}

async function favouritesDisabled() {
    // (a) A bucket response disables favourites while Active still holds the favourites-only first response.
    const b = mount({favouritesenabled: true});
    b.respond(0, activeFavouritesOnly());
    await settle();
    pill(b.c, 'review').click(); // Call 1.
    await settle();
    b.respond(1, reviewBucket(false));
    await settle();
    const args = b.ncalls() === 3 ? b.call(2).args : null;
    check('favourites disabled: a bucket response disabling them fetches the plans they left out', !!args && args.loadgroup === 'plan'
        && args.planstatus === 'active' && args.favouritesonly === false, args || b.ncalls());
    check('favourites disabled: Review stays on screen meanwhile', checked(b.c) === 'review'
        && JSON.stringify(ids(b.c, 'plan')) === '[21]', [checked(b.c), ids(b.c, 'plan')]);
    pill(b.c, 'active').click();
    await settle();
    check('favourites disabled: back on Active, no favourites filter is left on', favPills(b.c) === 0 && !ghostPlan(b.c)
        && !clearShown(b.c, 'plan'), [favPills(b.c), ghostPlan(b.c), clearShown(b.c, 'plan')]);
    check('favourites disabled: back on Active while that fetch is pending, no second request', b.ncalls() === 3 && loading(b.c),
        [b.ncalls(), loading(b.c)]);
    if (args) {
        b.respond(2, activeWholeFavouritesOff());
        await settle();
    }
    check('favourites disabled: back on Active, the whole list', JSON.stringify(ids(b.c, 'plan')) === '[1,2]' && !loading(b.c)
        && favPills(b.c) === 0 && !ghostPlan(b.c), [ids(b.c, 'plan'), loading(b.c), favPills(b.c), ghostPlan(b.c)]);

    // (b) A group response disables favourites while the plans are still at phase 1 under their filter.
    const g = mount({favouritesenabled: true});
    g.respond(0, base({plancards: [card(1, 'Alpha', {isfavourite: true})], competencycards: [card(11, 'C', {isfavourite: true})],
        totalplans: 2, totalcompetencies: 2, hasnonfavouriteplans: true, hasnonfavouritecompetencies: true, favouritesenabled: true}));
    await settle();
    check('favourites disabled: group response precondition: the plans are under the favourites filter', favChecked(g.c, 'plan') === 'true', favChecked(g.c, 'plan'));
    g.c.querySelector('.dims-all-filter-btn[data-all-filter-type="competency"]').click(); // Call 1.
    await settle();
    g.respond(1, base({competencycards: [card(11, 'C'), card(12, 'D')], totalcompetencies: 2, favouritesenabled: false}));
    await settle();
    const gargs = g.ncalls() === 3 ? g.call(2).args : null;
    check('favourites disabled: a group response disabling them fetches the plans they left out', !!gargs && gargs.loadgroup === 'plan'
        && gargs.planstatus === 'active' && gargs.favouritesonly === false && loading(g.c), gargs || g.ncalls());
    check('favourites disabled: a group response disabling them drops the plan favourites filter', favPills(g.c) === 0 && !ghostPlan(g.c)
        && !clearShown(g.c, 'plan'), [favPills(g.c), ghostPlan(g.c), clearShown(g.c, 'plan')]);
    if (gargs) {
        g.respond(2, activeWholeFavouritesOff());
        await settle();
    }
    check('favourites disabled: after the group response, both lists are whole', JSON.stringify(ids(g.c, 'plan')) === '[1,2]'
        && JSON.stringify(ids(g.c, 'competency')) === '[11,12]' && !loading(g.c) && g.ncalls() === 3,
        [ids(g.c, 'plan'), ids(g.c, 'competency'), loading(g.c), g.ncalls()]);

    // (c) The fetch of the plans favourites left out fails; going back to Active fetches them again.
    const f = mount({favouritesenabled: true});
    f.respond(0, activeFavouritesOnly());
    await settle();
    pill(f.c, 'review').click(); // Call 1.
    await settle();
    f.respond(1, reviewBucket(false));
    await settle();
    check('favourites disabled: failed fetch precondition: the plans are fetched', f.ncalls() === 3, f.ncalls());
    if (f.ncalls() === 3) {
        f.fail(2);
        await settle();
    }
    check('favourites disabled: failed fetch precondition: the error is shown', errorShown(f.c), errorShown(f.c));
    pill(f.c, 'active').click();
    await settle();
    const fargs = f.ncalls() === 4 ? f.call(3).args : null;
    check('favourites disabled: after a failed fetch, back on Active fetches the plans again', !!fargs && fargs.loadgroup === 'plan'
        && fargs.planstatus === 'active', fargs || f.ncalls());
    if (fargs) {
        f.respond(3, activeWholeFavouritesOff());
        await settle();
    }
    check('favourites disabled: after a failed fetch, the whole list once fetched again', JSON.stringify(ids(f.c, 'plan')) === '[1,2]'
        && !loading(f.c), [ids(f.c, 'plan'), loading(f.c)]);

    // (d) A switch fails after a response disabled favourites during it: the favourites filter is not restored.
    const d = mount({favouritesenabled: true});
    d.respond(0, base({plancounts: {active: 2, review: 1, complete: 0}, plancards: [card(1, 'Alpha', {isfavourite: true})],
        competencycards: [card(11, 'C', {isfavourite: true})], totalplans: 2, totalcompetencies: 2,
        hasnonfavouriteplans: true, hasnonfavouritecompetencies: true, favouritesenabled: true}));
    await settle();
    pill(d.c, 'review').click(); // Call 1.
    await settle();
    d.c.querySelector('.dims-all-filter-btn[data-all-filter-type="competency"]').click(); // Call 2.
    await settle();
    check('favourites disabled: failed switch precondition: the competencies are fetched during the switch', d.ncalls() === 3
        && d.call(2).args.loadgroup === 'competency', d.ncalls());
    if (d.ncalls() === 3) {
        d.respond(2, base({plancounts: {active: 2, review: 1, complete: 0}, competencycards: [card(11, 'C'), card(12, 'D')],
            totalcompetencies: 2, favouritesenabled: false}));
        await settle();
    }
    check('favourites disabled: failed switch precondition: the plans they left out are fetched', d.ncalls() === 4
        && d.call(3).args.loadgroup === 'plan', d.ncalls());
    if (d.ncalls() === 4) {
        d.respond(3, activeWholeFavouritesOff());
        await settle();
    }
    d.fail(1);
    await settle();
    check('favourites disabled: a failed switch comes back to the whole Active list, no favourites filter', checked(d.c) === 'active'
        && JSON.stringify(ids(d.c, 'plan')) === '[1,2]' && !clearShown(d.c, 'plan') && errorShown(d.c),
        [checked(d.c), ids(d.c, 'plan'), clearShown(d.c, 'plan'), errorShown(d.c)]);

    // Control: a bucket response keeping favourites enabled leaves phase 1 alone, with no request.
    const e = mount({favouritesenabled: true});
    e.respond(0, activeFavouritesOnly());
    await settle();
    pill(e.c, 'review').click(); // Call 1.
    await settle();
    e.respond(1, reviewBucket(true));
    await settle();
    pill(e.c, 'active').click();
    await settle();
    check('favourites disabled: control: favourites still enabled, the favourites-only list returns under its filter with no request', e.ncalls() === 2
        && favChecked(e.c, 'plan') === 'true' && ghostPlan(e.c) && JSON.stringify(ids(e.c, 'plan')) === '[1]',
        [e.ncalls(), favChecked(e.c, 'plan'), ghostPlan(e.c), ids(e.c, 'plan')]);
}

// A first response with Active at its favourites-only phase 1, both lists missing cards.
const bothFavouritesOnly = () => base({plancounts: {active: 2, review: 1, complete: 0}, plancards: [card(1, 'Alpha', {isfavourite: true})],
    competencycards: [card(11, 'C', {isfavourite: true})], totalplans: 2, totalcompetencies: 2,
    hasnonfavouriteplans: true, hasnonfavouritecompetencies: true, favouritesenabled: true});
const competenciesWholeFavouritesOff = () => base({plancounts: {active: 2, review: 1, complete: 0},
    competencycards: [card(11, 'C'), card(12, 'D')], totalcompetencies: 2, favouritesenabled: false});

async function favouritesDisabledAndFailures() {
    // (a) During a switch from Active, a group response disables favourites and the fetch of the plans they
    // left out fails; the switch then fails too. Back on Active, those plans are fetched.
    const a = mount({favouritesenabled: true});
    a.respond(0, bothFavouritesOnly());
    await settle();
    pill(a.c, 'review').click(); // Call 1.
    await settle();
    a.c.querySelector('.dims-all-filter-btn[data-all-filter-type="competency"]').click(); // Call 2.
    await settle();
    check('favourites disabled: both failing, precondition: the competencies are fetched during the switch', a.ncalls() === 3
        && a.call(2).args.loadgroup === 'competency', a.ncalls());
    if (a.ncalls() === 3) {
        a.respond(2, competenciesWholeFavouritesOff());
        await settle();
    }
    check('favourites disabled: both failing, precondition: the plans they left out are fetched', a.ncalls() === 4
        && a.call(3).args.loadgroup === 'plan', a.ncalls());
    if (a.ncalls() === 4) {
        a.fail(3);
        await settle();
    }
    check('favourites disabled: both failing, precondition: that fetch failed, no other request', errorShown(a.c) && a.ncalls() === 4
        && busy(a.c) === 'review', [errorShown(a.c), a.ncalls(), busy(a.c)]);
    a.fail(1);
    await settle();
    check('favourites disabled: both failing, precondition: back on Active at the favourites-only list, no pill, no ghost card', checked(a.c) === 'active'
        && JSON.stringify(ids(a.c, 'plan')) === '[1]' && favPills(a.c) === 0 && !ghostPlan(a.c) && errorShown(a.c),
        [checked(a.c), ids(a.c, 'plan'), favPills(a.c), ghostPlan(a.c), errorShown(a.c)]);
    const aargs = a.ncalls() === 5 ? a.call(4).args : null;
    check('favourites disabled: both failing, the plans they left out are fetched again', !!aargs && aargs.loadgroup === 'plan'
        && aargs.planstatus === 'active' && aargs.favouritesonly === false && loading(a.c), aargs || a.ncalls());
    if (aargs) {
        a.respond(4, activeWholeFavouritesOff());
        await settle();
    }
    check('favourites disabled: both failing, the whole list, no favourites filter', checked(a.c) === 'active'
        && JSON.stringify(ids(a.c, 'plan')) === '[1,2]' && JSON.stringify(ids(a.c, 'competency')) === '[11,12]'
        && favPills(a.c) === 0 && !ghostPlan(a.c) && !loading(a.c) && a.ncalls() === 5,
        [checked(a.c), ids(a.c, 'plan'), ids(a.c, 'competency'), favPills(a.c), ghostPlan(a.c), loading(a.c), a.ncalls()]);

    // (b) Control for the guard: the fetch of the plans favourites left out is still pending when the switch
    // fails, so the failure sends no second request, and the pending one completes the list when it lands.
    const b = mount({favouritesenabled: true});
    b.respond(0, bothFavouritesOnly());
    await settle();
    pill(b.c, 'review').click(); // Call 1.
    await settle();
    b.c.querySelector('.dims-all-filter-btn[data-all-filter-type="competency"]').click(); // Call 2.
    await settle();
    if (b.ncalls() === 3) {
        b.respond(2, competenciesWholeFavouritesOff());
        await settle();
    }
    check('favourites disabled: control precondition: the plans they left out are still on their way', b.ncalls() === 4
        && b.call(3).args.loadgroup === 'plan' && !b.call(3).settled && loading(b.c), [b.ncalls(), loading(b.c)]);
    b.fail(1);
    await settle();
    check('favourites disabled: control: a failed switch with that fetch pending sends no second request', b.ncalls() === 4
        && checked(b.c) === 'active' && loading(b.c), [b.ncalls(), checked(b.c), loading(b.c)]);
    if (b.ncalls() >= 4) {
        b.respond(3, activeWholeFavouritesOff());
        await settle();
    }
    check('favourites disabled: control: the pending fetch completes the list', JSON.stringify(ids(b.c, 'plan')) === '[1,2]'
        && !loading(b.c) && b.ncalls() === 4, [ids(b.c, 'plan'), loading(b.c), b.ncalls()]);
}

// Put a marker in the results region, so a later announcement, or its absence, can be told apart.
const markRegion = (c) => { c.querySelector('[data-results-status]').textContent = 'MARK'; };
const threeBuckets = {active: 2, review: 1, complete: 1};

async function pendingLoadHoldsNoResult() {
    // (a) The cached Active list under a search: its missing plans are on their way.
    const b = mount({favouritesenabled: true});
    b.respond(0, activeFavouritesOnly());
    await settle();
    pill(b.c, 'review').click(); // Call 1.
    await settle();
    b.respond(1, reviewBucket(true));
    await settle();
    typeSearch(b.c, 'beta');
    await sleep(200);
    await settle();
    await sleep(700);
    await settle();
    check('pending load holds no-result: control: on Review with nothing on its way, a search finding nothing says so and announces it',
        empty(b.c) === 'NONEFOUND' && region(b.c) === 'NONEFOUND' && !loading(b.c) && b.ncalls() === 2,
        [empty(b.c), region(b.c), loading(b.c), b.ncalls()]);
    markRegion(b.c);
    pill(b.c, 'active').click(); // Call 2.
    await settle();
    check('pending load holds no-result: back on Active under a search, plans on their way: the loading line, no no-result line', b.ncalls() === 3
        && !b.call(2).settled && b.call(2).args.loadgroup === 'plan' && empty(b.c) === '' && loading(b.c)
        && ids(b.c, 'plan').length === 0, [b.ncalls(), empty(b.c), loading(b.c), ids(b.c, 'plan')]);
    await sleep(800);
    await settle();
    check('pending load holds no-result: back on Active under a search, plans on their way: no no-result announcement', region(b.c) === 'MARK',
        region(b.c));
    if (b.ncalls() === 3) {
        b.respond(2, activeWhole());
        await settle();
    }
    await sleep(800);
    await settle();
    check('pending load holds no-result: once the plans land, the result is shown and announced', JSON.stringify(ids(b.c, 'plan')) === '[2]'
        && empty(b.c) === '' && !loading(b.c) && region(b.c) === 'P=1 C=0', [ids(b.c, 'plan'), empty(b.c), loading(b.c), region(b.c)]);
    // Nothing is on its way any more: a search finding nothing says so at once.
    typeSearch(b.c, 'zzz');
    await sleep(200);
    await settle();
    check('pending load holds no-result: after the plans landed, a search finding nothing shows the no-result line', empty(b.c) === 'NONEFOUND'
        && !loading(b.c) && b.ncalls() === 3, [empty(b.c), loading(b.c), b.ncalls()]);

    // (b) A switch that fails under a search typed while it loaded: the missing active plans are on their way.
    const f = mount({favouritesenabled: true});
    f.respond(0, activeFavouritesOnly());
    await settle();
    pill(f.c, 'review').click(); // Call 1.
    await settle();
    typeSearch(f.c, 'beta');
    await sleep(200);
    await settle();
    markRegion(f.c);
    f.fail(1);
    await settle();
    check('pending load holds no-result: failed switch under a search, plans on their way: the loading line, no no-result line', f.ncalls() === 3
        && f.call(2).args.loadgroup === 'plan' && empty(f.c) === '' && loading(f.c) && errorShown(f.c),
        [f.ncalls(), empty(f.c), loading(f.c), errorShown(f.c)]);
    await sleep(800);
    await settle();
    check('pending load holds no-result: failed switch under a search, plans on their way: no no-result announcement', region(f.c) === 'MARK',
        region(f.c));
    // That fetch fails too: nothing is on its way, and no render follows, so the line is settled by the failure.
    if (f.ncalls() === 3) {
        f.fail(2);
        await settle();
    }
    check('pending load holds no-result: the plans failing too settle the no-result line and hide the loading line', empty(f.c) === 'NONEFOUND'
        && !loading(f.c) && errorShown(f.c) && f.ncalls() === 3, [empty(f.c), loading(f.c), errorShown(f.c), f.ncalls()]);

    // (c) Control: plans on their way for Active cannot change the Review grid, so its no-result line shows.
    const r = mount({favouritesenabled: true});
    r.respond(0, activeFavouritesOnly());
    await settle();
    pill(r.c, 'review').click(); // Call 1.
    await settle();
    r.respond(1, reviewBucket(false)); // Favourites disabled: call 2 fetches the active plans they left out.
    await settle();
    check('pending load holds no-result: control precondition: the active plans are on their way while Review is on screen', r.ncalls() === 3
        && r.call(2).args.loadgroup === 'plan' && !r.call(2).settled && loading(r.c) && checked(r.c) === 'review',
        [r.ncalls(), loading(r.c), checked(r.c)]);
    typeSearch(r.c, 'zzz');
    await sleep(200);
    await settle();
    await sleep(800);
    await settle();
    check('pending load holds no-result: control: active plans on their way do not hold the Review no-result line or its announcement',
        empty(r.c) === 'NONEFOUND' && region(r.c) === 'NONEFOUND' && loading(r.c) && r.ncalls() === 3,
        [empty(r.c), region(r.c), loading(r.c), r.ncalls()]);

    // (d) Competency cards on their way reach every bucket: they hold the line on Completed too.
    const k = mount({favouritesenabled: true});
    k.respond(0, base({plancounts: threeBuckets, plancards: [card(1, 'Alpha', {isfavourite: true})],
        competencycards: [card(11, 'C', {isfavourite: true})], totalplans: 2, totalcompetencies: 2,
        hasnonfavouriteplans: true, hasnonfavouritecompetencies: true, favouritesenabled: true}));
    await settle();
    pill(k.c, 'review').click(); // Call 1.
    await settle();
    k.respond(1, base({favouritesenabled: true, planstatus: 'review', plancounts: threeBuckets, plancards: [card(21, 'Rev')],
        totalplans: 1}));
    await settle();
    typeSearch(k.c, 'zzz'); // Call 2: the search reaches every competency card.
    await sleep(200);
    await settle();
    check('pending load holds no-result: competency precondition: the search fetches the competency cards', k.ncalls() === 3
        && k.call(2).args.loadgroup === 'competency', k.ncalls() === 3 ? k.call(2).args : k.ncalls());
    pill(k.c, 'complete').click(); // Call 3.
    await settle();
    markRegion(k.c);
    if (k.ncalls() === 4) {
        k.respond(3, base({favouritesenabled: true, planstatus: 'complete', plancounts: threeBuckets, plancards: [card(41, 'Done')],
            totalplans: 1}));
        await settle();
    }
    await sleep(800);
    await settle();
    check('pending load holds no-result: competency cards on their way hold the no-result line and its announcement on Completed',
        checked(k.c) === 'complete' && empty(k.c) === '' && loading(k.c) && region(k.c) === 'MARK' && k.ncalls() === 4,
        [checked(k.c), empty(k.c), loading(k.c), region(k.c), k.ncalls()]);
    if (k.ncalls() === 4) {
        k.respond(2, base({competencycards: [card(11, 'C', {isfavourite: true}), card(12, 'D')], totalcompetencies: 2,
            favouritesenabled: true}));
        await settle();
    }
    await sleep(800);
    await settle();
    check('pending load holds no-result: once the competency cards land, the no-result line shows and is announced', empty(k.c) === 'NONEFOUND'
        && region(k.c) === 'NONEFOUND' && !loading(k.c), [empty(k.c), region(k.c), loading(k.c)]);
}

async function oneLoadPerCardType() {
    // (a) The search is edited while the plans it needs are on their way.
    const s = mount({favouritesenabled: true});
    s.respond(0, activeFavouritesOnly());
    await settle();
    typeSearch(s.c, 'be'); // Call 1.
    await sleep(200);
    await settle();
    check('one load per card type: precondition: the search fetches the plans and renders once they land', s.ncalls() === 2
        && s.call(1).args.loadgroup === 'plan' && JSON.stringify(ids(s.c, 'plan')) === '[1]' && loading(s.c),
        [s.ncalls(), ids(s.c, 'plan'), loading(s.c)]);
    typeSearch(s.c, 'bet');
    await sleep(200);
    await settle();
    check('one load per card type: a search edited while its plans are on their way sends no second request', s.ncalls() === 2 && loading(s.c),
        [s.ncalls(), loading(s.c)]);
    check('one load per card type: the edited search too renders once they land',
        JSON.stringify(ids(s.c, 'plan')) === '[1]', ids(s.c, 'plan'));
    if (s.ncalls() === 2) {
        s.respond(1, activeWhole());
        await settle();
    }
    check('one load per card type: the one response serves the edited search', JSON.stringify(ids(s.c, 'plan')) === '[2]'
        && !loading(s.c) && s.ncalls() === 2, [ids(s.c, 'plan'), loading(s.c), s.ncalls()]);

    // (b) Back on the cached Active list while the search's plans are on their way.
    const k = mount({favouritesenabled: true});
    k.respond(0, activeFavouritesOnly());
    await settle();
    pill(k.c, 'review').click(); // Call 1.
    await settle();
    k.respond(1, reviewBucket(true));
    await settle();
    pill(k.c, 'active').click();
    await settle();
    typeSearch(k.c, 'beta'); // Call 2.
    await sleep(200);
    await settle();
    check('one load per card type: kept list precondition: the search fetches the plans', k.ncalls() === 3 && k.call(2).args.loadgroup === 'plan',
        k.ncalls());
    pill(k.c, 'review').click();
    await settle();
    pill(k.c, 'active').click();
    await settle();
    check('one load per card type: back on Active under a search while its plans are on their way, no second request', k.ncalls() === 3
        && checked(k.c) === 'active' && loading(k.c), [k.ncalls(), checked(k.c), loading(k.c)]);
    if (k.ncalls() >= 3) {
        k.respond(2, activeWhole());
        await settle();
    }
    check('one load per card type: back on Active, the pending fetch completes the search', JSON.stringify(ids(k.c, 'plan')) === '[2]'
        && !loading(k.c) && k.ncalls() === 3, [ids(k.c, 'plan'), loading(k.c), k.ncalls()]);

    // (c) A bucket response disables favourites while the plans they left out are already on their way.
    const d = mount({favouritesenabled: true});
    d.respond(0, activeFavouritesOnly());
    await settle();
    typeSearch(d.c, 'beta'); // Call 1.
    await sleep(200);
    await settle();
    pill(d.c, 'review').click(); // Call 2.
    await settle();
    check('one load per card type: bucket precondition: the plans and the Review bucket are both on their way', d.ncalls() === 3
        && d.call(1).args.loadgroup === 'plan' && d.call(2).args.planstatus === 'review', d.ncalls());
    if (d.ncalls() === 3) {
        d.respond(2, reviewBucket(false));
        await settle();
    }
    check('one load per card type: a bucket response disabling favourites while the plans are on their way sends no second request',
        d.ncalls() === 3 && checked(d.c) === 'review' && loading(d.c), [d.ncalls(), checked(d.c), loading(d.c)]);
    if (d.ncalls() >= 3) {
        d.respond(1, activeWholeFavouritesOff());
        await settle();
    }
    pill(d.c, 'active').click();
    await settle();
    check('one load per card type: back on Active, the plans that landed are searched with no request', JSON.stringify(ids(d.c, 'plan')) === '[2]'
        && !loading(d.c) && d.ncalls() === 3, [ids(d.c, 'plan'), loading(d.c), d.ncalls()]);

    // (d) Control: plans on their way do not hold back a load of the competency cards.
    const t = mount({favouritesenabled: true});
    t.respond(0, bothFavouritesOnly());
    await settle();
    t.c.querySelector('.dims-all-filter-btn[data-all-filter-type="plan"]').click(); // Call 1.
    await settle();
    t.c.querySelector('.dims-all-filter-btn[data-all-filter-type="competency"]').click(); // Call 2.
    await settle();
    check('one load per card type: control: plans on their way do not hold back a competency load', t.ncalls() === 3
        && t.call(1).args.loadgroup === 'plan' && t.call(2).args.loadgroup === 'competency',
        [t.ncalls(), [1, 2].map((i) => t.call(i) && t.call(i).args.loadgroup)]);
    if (t.ncalls() === 3) {
        t.respond(1, base({plancounts: {active: 2, review: 1, complete: 0}, plancards: [card(1, 'Alpha', {isfavourite: true}),
            card(2, 'Beta')], totalplans: 2, favouritesenabled: true}));
        t.respond(2, base({plancounts: {active: 2, review: 1, complete: 0}, competencycards: [card(11, 'C', {isfavourite: true}),
            card(12, 'D')], totalcompetencies: 2, favouritesenabled: true}));
        await settle();
    }
    check('one load per card type: control: both lists whole once both land', JSON.stringify(ids(t.c, 'plan')) === '[1,2]'
        && JSON.stringify(ids(t.c, 'competency')) === '[11,12]' && !loading(t.c) && t.ncalls() === 3,
        [ids(t.c, 'plan'), ids(t.c, 'competency'), loading(t.c), t.ncalls()]);

    // (e) A retry starts a new session: a load of the old one still on its way is not waited for.
    const e = mount({favouritesenabled: true});
    e.respond(0, bothFavouritesOnly());
    await settle();
    e.c.querySelector('.dims-all-filter-btn[data-all-filter-type="plan"]').click(); // Call 1.
    await settle();
    e.c.querySelector('.dims-all-filter-btn[data-all-filter-type="competency"]').click(); // Call 2.
    await settle();
    if (e.ncalls() === 3) {
        e.fail(1);
        await settle();
    }
    check('one load per card type: retry precondition: the plans failed while the competency cards are on their way', errorShown(e.c)
        && e.ncalls() === 3 && !e.call(2).settled, [errorShown(e.c), e.ncalls()]);
    e.c.querySelector('.dims-error-retry').click(); // Call 3.
    await settle();
    if (e.ncalls() === 4) {
        // The competency cards now have no favourite, so the first response fetches them whole.
        e.respond(3, base({plancounts: {active: 2, review: 1, complete: 0}, plancards: [card(1, 'Alpha', {isfavourite: true})],
            competencycards: [], totalplans: 2, totalcompetencies: 2, hasnonfavouriteplans: true,
            hasnonfavouritecompetencies: true, favouritesenabled: true}));
        await settle();
    }
    check('one load per card type: a retry fetches the competency cards the replaced session was loading', e.ncalls() === 5
        && e.call(4).args.loadgroup === 'competency' && loading(e.c), e.ncalls() === 5 ? e.call(4).args : e.ncalls());
    if (e.ncalls() === 5) {
        e.respond(2, competenciesWholeFavouritesOff());
        e.respond(4, base({plancounts: {active: 2, review: 1, complete: 0}, competencycards: [card(11, 'C'), card(12, 'D')],
            totalcompetencies: 2, favouritesenabled: true}));
        await settle();
    }
    check('one load per card type: after a retry, the old response is dropped and the new one fills the list', JSON.stringify(ids(e.c, 'competency')) === '[11,12]'
        && JSON.stringify(ids(e.c, 'plan')) === '[1]' && favChecked(e.c, 'plan') === 'true' && !loading(e.c) && e.ncalls() === 5,
        [ids(e.c, 'competency'), ids(e.c, 'plan'), favChecked(e.c, 'plan'), loading(e.c), e.ncalls()]);
}

async function ghostCardHoldsNoResult() {
    // A tag filter hides every favourite plan while the favourites filter is on: the ghost card offers the
    // rest, so neither the visible line nor the results announcement may claim that nothing matched.
    const fs = {plan: {tag1enabled: true, tag1displaymode: 'tabs'}, competency: {}};
    const b = mount({favouritesenabled: true, filtersettings: fs});
    b.respond(0, base({filtersettings: fs, favouritesenabled: true, hasactiveplans: true, hascompetencies: false,
        plancounts: {active: 3, review: 0, complete: 0},
        plancards: [card(1, 'Alpha', {isfavourite: true, tag1: 'X'}), card(2, 'Beta', {isfavourite: false, tag1: 'Y'})],
        competencycards: [], totalplans: 3, hasnonfavouriteplans: true}));
    await settle();
    await sleep(700);
    const yPill = Array.from(b.c.querySelectorAll('[data-filter-field="plan_tag1"]')).find((el) => el.dataset.filterValue === 'Y');
    check('ghost card holds no-result: precondition: the favourites filter is on with a ghost card', favChecked(b.c, 'plan') === 'true'
        && vis(b.c.querySelector('.dims-ghost-card')), [favChecked(b.c, 'plan'), !!b.c.querySelector('.dims-ghost-card')]);
    check('ghost card holds no-result: precondition: tag pill Y is drawn', !!yPill, !!yPill);
    yPill.click();
    await settle();
    await sleep(700);
    check('ghost card holds no-result: precondition: the tag filter hides every card', JSON.stringify(ids(b.c, 'plan')) === '[]', ids(b.c, 'plan'));
    check('ghost card holds no-result: no visible no-result line while the ghost card offers more', empty(b.c) === '', empty(b.c));
    check('ghost card holds no-result: no no-result announcement while the ghost card offers more', region(b.c) !== L.resultsnonefound,
        region(b.c));

    // Control: without favourites a search that hides every card is still announced as no result.
    const n = mount();
    n.respond(0, base({hasactiveplans: true, plancards: [card(1, 'Alpha')], competencycards: [], totalplans: 1}));
    await settle();
    typeSearch(n.c, 'zzz');
    await settle();
    await sleep(700);
    check('ghost card holds no-result: control: without favourites, a search hiding every card is announced as no result', region(n.c) === L.resultsnonefound,
        region(n.c));
}

// In run order. Scenarios share the page, so each mounts its own blocks and settles what it started.
const SCENARIOS = [
    basics,
    retryRebuildsTheBlock,
    retryWithAllFavourites,
    retryAfterASearchEvent,
    retryDropsAStaleBucket,
    retryDropsAStaleGroup,
    bucketLoading,
    statusKeys,
    statusKeysAfterAFailure,
    loadingLine,
    searchAtLoad,
    beforeTheFirstDataset,
    retryFocus,
    failedSwitchKeepsFilters,
    announcementPerBlock,
    activePill,
    competencyLoadAsksActive,
    keptActiveList,
    quotedTagValue,
    noResultWording,
    sourceDocblock,
    activeLoadingText,
    failedSwitchUnderASearch,
    sharedMetadata,
    backOnActiveUnderASearch,
    favouritesDisabled,
    favouritesDisabledAndFailures,
    pendingLoadHoldsNoResult,
    oneLoadPerCardType,
    ghostCardHoldsNoResult,
];

// The family of a check: its name up to the first ': '.
const familyOf = (name) => (name.indexOf(': ') > 0 ? name.slice(0, name.indexOf(': ')) : name);

window.runAll = async function() {
    results.length = 0;
    for (const s of SCENARIOS) {
        const before = results.length;
        try {
            await s();
        } catch (e) {
            // A scenario that throws fails in the family of its last check, so a mutant can still be matched.
            const family = results.length > before ? familyOf(results[results.length - 1].name) : s.name;
            check(family + ': ' + s.name + ' threw', false, String(e && e.stack || e));
        }
    }
    const failed = results.filter((r) => !r.pass);
    return {src: window.srcBase, total: results.length, failed: failed.length, failures: failed,
        families: Array.from(new Set(results.map((r) => familyOf(r.name))))};
};

if (new URLSearchParams(location.search).get('auto')) {
    window.addEventListener('load', () => {
        window.runAll().then((r) => {
            const pre = document.createElement('pre');
            pre.id = 'out';
            pre.textContent = 'RESULT ' + JSON.stringify(r);
            document.body.appendChild(pre);
            return null;
        }).catch((e) => {
            const pre = document.createElement('pre');
            pre.id = 'out';
            pre.textContent = 'RESULT-ERROR ' + String(e && e.stack || e);
            document.body.appendChild(pre);
        });
    });
}

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
 * The favourite star of a plan card drawn outside the block, by another plugin.
 *
 * A caller renders the cards of block_dimensions\plan_cards::for_user() with the favourites flag on and
 * calls init() once. A press toggles the favourite through block_dimensions_toggle_favourite, redraws
 * the button from the server's answer exactly as templates/plan_card.mustache draws that state, and
 * dispatches EVENT on the button. Stars inside the block's own root are left to block_dimensions/filters,
 * which keeps its own state and stops their clicks.
 *
 * @module     block_dimensions/favourite_star
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {getStrings} from 'core/str';

/**
 * The event a star dispatches on itself, bubbling, after a toggle the server confirmed.
 *
 * Its detail is {type, id, isfavourite}: the item type, the item id as a Number and the new state as
 * a Boolean, read from the server's answer.
 *
 * @type {string}
 */
export const EVENT = 'block_dimensions/favourite:changed';

/** The block's own root (templates/summary.mustache); the theme's host class is not it. */
const BLOCK_ROOT = '.block-dimensions-content';

/** A favourite button as the card templates draw it. */
const STAR = '.dims-fav-btn[data-fav-type][data-fav-id]';

/** Whether the listener is on the document. */
let initialised = false;

/** The promise of the two labels, kept while it has not failed. */
let labels = null;

/**
 * The button's two labels, fetched once and fetched again after a failure.
 *
 * @returns {Promise<Array<string>>} The add and remove labels, in that order.
 */
const fetchLabels = () => {
    if (!labels) {
        labels = getStrings([
            {key: 'addtofavourites', component: 'block_dimensions'},
            {key: 'removefromfavourites', component: 'block_dimensions'},
        ]).catch((error) => {
            labels = null;
            throw error;
        });
    }
    return labels;
};

/**
 * Draw a button in one state, as templates/plan_card.mustache draws it.
 *
 * @param {HTMLElement} button The favourite button.
 * @param {boolean} isfavourite The state to draw.
 * @param {Array<string>} strings The add and remove labels.
 */
const draw = (button, isfavourite, strings) => {
    const icon = button.querySelector('i');
    if (icon) {
        icon.className = isfavourite ? 'fa fa-star dims-fav-icon-filled' : 'fa fa-star-o dims-fav-icon-empty';
    }
    const label = isfavourite ? strings[1] : strings[0];
    button.setAttribute('aria-pressed', isfavourite ? 'true' : 'false');
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
};

/**
 * Toggle one star's favourite, and draw the button from the answer.
 *
 * The labels are read before the call, so a failure to read them sends nothing. On a failure the
 * button keeps the state it had and core's exception notification says why.
 *
 * @param {HTMLElement} button The favourite button.
 * @returns {Promise<void>}
 */
const toggle = async(button) => {
    const type = button.dataset.favType;
    const id = Number.parseInt(button.dataset.favId, 10);
    if ((type !== 'plan' && type !== 'competency') || !(id > 0)) {
        return;
    }
    if (button.dataset.favPending) {
        return;
    }
    button.dataset.favPending = '1';
    const pending = new Pending('block_dimensions/favourite_star:toggle');

    try {
        const strings = await fetchLabels();
        const result = await Ajax.call([{
            methodname: 'block_dimensions_toggle_favourite',
            args: {itemtype: type, itemid: id},
        }])[0];
        const isfavourite = Boolean(result.isfavourite);
        draw(button, isfavourite, strings);
        button.dispatchEvent(new CustomEvent(EVENT, {bubbles: true, detail: {type, id, isfavourite}}));
    } catch (error) {
        Notification.exception(error);
    } finally {
        delete button.dataset.favPending;
        pending.resolve();
    }
};

/**
 * The document's click listener: a star outside the block's root is pressed.
 *
 * @param {MouseEvent} event The click.
 */
const onClick = (event) => {
    const button = event.target instanceof Element ? event.target.closest(STAR) : null;
    if (!button || button.closest(BLOCK_ROOT)) {
        return;
    }
    // The card is a stretched link: the press stays on the star.
    event.preventDefault();
    event.stopPropagation();
    toggle(button);
};

/**
 * Listen for presses on every star outside the block's root; a second call does nothing.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    fetchLabels().catch(() => null);
    document.addEventListener('click', onClick);
};

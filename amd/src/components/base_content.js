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

import {BaseComponent} from 'core/reactive';

export default class BaseContent extends BaseComponent {

    constructor(descriptor) {
        super(descriptor);
        if (new.target === BaseContent) {
            throw new TypeError('Cannot instantiate abstract class directly');
        }
        if (typeof this.getViewName !== 'function') {
            throw new TypeError('Must implement method getViewName()');
        }
    }

    getWatchers() {
        return [
            {watch: `config.view:updated`, handler: this._toggleContentVisibility},
        ];
    }

    async _toggleContentVisibility({element}) {
        if (element.view === this.getViewName()) {
            await this._renderContent();
            this._showContent();
        } else {
            this._hideContent();
        }
    }

    _hideContent() {
        this.getElement().classList.add('d-none');
    }

    _showContent() {
        this.getElement().classList.remove('d-none');
    }
}

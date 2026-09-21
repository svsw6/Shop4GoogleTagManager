const STORAGE_KEY = 's4gtm_list_attribution';
const TTL = 30 * 60 * 1000;

export default class GtmListAttribution {
    constructor(now = () => Date.now()) {
        this._now = now;
        this._memory = null;
    }

    remember(itemId, list) {
        if (!itemId || !list || !list.item_list_id) {
            return;
        }

        this._write({
            item_id: itemId,
            item_list_id: list.item_list_id,
            item_list_name: list.item_list_name,
            index: list.index,
            ts: this._now(),
        });
    }

    /**
     * @returns {boolean} true, wenn etwas ergaenzt wurde
     */
    apply(item) {
        const stored = this._read();
        if (stored === null || item === null || stored.item_id !== item.item_id) {
            return false;
        }

        item.item_list_id = stored.item_list_id;
        if (stored.item_list_name !== undefined) {
            item.item_list_name = stored.item_list_name;
        }
        if (typeof stored.index === 'number' && item.index === undefined) {
            item.index = stored.index;
        }

        return true;
    }

    _read() {
        let raw = null;
        try {
            raw = window.sessionStorage.getItem(STORAGE_KEY);
        } catch (error) {
            raw = this._memory;
        }
        if (!raw) {
            return null;
        }

        let stored;
        try {
            stored = JSON.parse(raw);
        } catch (error) {
            return null;
        }

        if (!stored || typeof stored !== 'object' || this._now() - stored.ts > TTL) {
            return null;
        }

        return stored;
    }

    _write(entry) {
        const raw = JSON.stringify(entry);
        this._memory = raw;
        try {
            window.sessionStorage.setItem(STORAGE_KEY, raw);
        } catch (error) {
            
        }
    }
}

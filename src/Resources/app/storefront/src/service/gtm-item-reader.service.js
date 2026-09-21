export const PRODUCT_DATA_SELECTOR = '.s4gtm-product-data';
export const CART_ITEM_DATA_SELECTOR = '.s4gtm-cart-item-data';
export const CORE_PRODUCT_SELECTOR = '[data-product-information]';

export default class GtmItemReader {
    parseAnnotated(el) {
        try {
            return JSON.parse(el.getAttribute('data-s4gtm-item'));
        } catch (error) {
            return null;
        }
    }

    parseCoreProduct(box) {
        let data;
        try {
            data = JSON.parse(box.getAttribute('data-product-information'));
        } catch (error) {
            return null;
        }

        if (!data || !(data.sku || data.id)) {
            return null;
        }

        const item = {
            item_id: data.sku || data.id,
            item_name: data.name,
            price: typeof data.price === 'number' ? data.price : 0,
            quantity: 1,
        };

        if (data.brand) {
            item.item_brand = data.brand;
        }

        return item;
    }

    forElement(el) {
        const box = el.closest(CORE_PRODUCT_SELECTOR);
        if (box !== null) {
            const annotated = box.querySelector(PRODUCT_DATA_SELECTOR);
            return annotated ? this.parseAnnotated(annotated) : this.parseCoreProduct(box);
        }

        const all = document.querySelectorAll(PRODUCT_DATA_SELECTOR);
        return all.length === 1 ? this.parseAnnotated(all[0]) : null;
    }

    forForm(form, selector) {
        const item = this._annotatedForForm(form, selector);
        if (item !== null) {
            return item;
        }

        if (selector === PRODUCT_DATA_SELECTOR) {
            const box = form.closest(CORE_PRODUCT_SELECTOR);
            return box === null ? null : this.parseCoreProduct(box);
        }

        return null;
    }

    _annotatedForForm(form, selector) {
        const inForm = form.querySelector(selector);
        if (inForm) {
            return this.parseAnnotated(inForm);
        }

        const candidates = Array.from(document.querySelectorAll(selector));
        if (candidates.length === 0) {
            return null;
        }
        if (candidates.length === 1) {
            return this.parseAnnotated(candidates[0]);
        }

        const nearest = this._nearestByCommonAncestor(form, candidates);
        return nearest ? this.parseAnnotated(nearest) : null;
    }

    _nearestByCommonAncestor(form, candidates) {
        const formDepth = new Map();
        let depth = 0;
        for (let node = form; node; node = node.parentElement) {
            formDepth.set(node, depth);
            depth += 1;
        }

        let best = null;
        let bestDepth = Infinity;
        let ambiguous = false;

        candidates.forEach((candidate) => {
            for (let node = candidate; node; node = node.parentElement) {
                if (formDepth.has(node)) {
                    const d = formDepth.get(node);
                    if (d < bestDepth) {
                        bestDepth = d;
                        best = candidate;
                        ambiguous = false;
                    } else if (d === bestDepth) {
                        ambiguous = true;
                    }
                    break;
                }
            }
        });

        return ambiguous ? null : best;
    }

    collectProducts(root) {
        const annotated = this._map(root.querySelectorAll(PRODUCT_DATA_SELECTOR), (n) => this.parseAnnotated(n));
        if (annotated.length > 0) {
            return annotated;
        }

        return this._map(root.querySelectorAll(CORE_PRODUCT_SELECTOR), (n) => this.parseCoreProduct(n));
    }

    collectCartItems(root) {
        return this._map(root.querySelectorAll(CART_ITEM_DATA_SELECTOR), (n) => this.parseAnnotated(n));
    }

    _map(nodes, parse) {
        const items = [];
        const seen = [];

        Array.prototype.forEach.call(nodes, (node) => {
            const item = parse(node);
            if (item === null || item.item_id === undefined || seen.indexOf(item.item_id) !== -1) {
                return;
            }
            seen.push(item.item_id);
            items.push(item);
        });

        return items;
    }
}

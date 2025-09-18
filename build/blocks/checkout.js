(() => {
    "use strict";
    /**
     * WooCommerce Blocks integration for Distance Rate Shipping.
     */
    var _a;
    const storeKey = 'wc/store/cart';
    const badgeClass = 'drs-distance-badge';
    const badgeAttribute = 'data-drs-badge';
    const badgeAttributeValue = 'distance';
    const rawConfig = (_a = window === null || window === void 0 ? void 0 : window.drsDistanceBlocksData) !== null && _a !== void 0 ? _a : {};
    const config = {
        quoteEndpoint: typeof rawConfig.quoteEndpoint === 'string' ? rawConfig.quoteEndpoint : '',
        nonce: typeof rawConfig.nonce === 'string' ? rawConfig.nonce : undefined,
        showDistanceBadge: Boolean(rawConfig.showDistanceBadge),
        methodId: typeof rawConfig.methodId === 'string' ? rawConfig.methodId : 'drs_distance_rate',
        badgeLabel: typeof rawConfig.badgeLabel === 'string' ? rawConfig.badgeLabel : 'Distance',
        loadingText: typeof rawConfig.loadingText === 'string' ? rawConfig.loadingText : 'Calculating…',
        distancePrecision: typeof rawConfig.distancePrecision === 'number' ? rawConfig.distancePrecision : 1,
        distanceUnit: typeof rawConfig.distanceUnit === 'string' ? rawConfig.distanceUnit : 'km'
    };
    const state = {
        addressHash: '',
        ratesHash: '',
        distanceText: '',
        isLoading: false,
        quoteToken: 0
    };
    let applyScheduled = false;
    const scheduleApply = () => {
        var _a2;
        if (applyScheduled) {
            return;
        }
        applyScheduled = true;
        const executor = (_a2 = window.requestAnimationFrame) !== null && _a2 !== void 0 ? _a2 : window.setTimeout;
        executor(() => {
            applyScheduled = false;
            applyBadgeToDom();
        });
    };
    const normaliseAddress = (address) => {
        if (!address || typeof address !== 'object') {
            return '';
        }
        const keys = ['address_1', 'address_2', 'city', 'state', 'country', 'postcode', 'postal_code', 'zip', 'postalCode'];
        const snapshot = {};
        keys.forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(address, key)) {
                snapshot[key] = address[key];
            }
        });
        return JSON.stringify(snapshot);
    };
    const createDestinationKey = (address) => {
        if (!address || typeof address !== 'object') {
            return '';
        }
        const keys = ['address_1', 'address_2', 'city', 'state', 'country', 'postcode', 'postal_code', 'zip'];
        const parts = [];
        keys.forEach((key) => {
            const value = address[key];
            if (typeof value === 'string') {
                const trimmed = value.trim().toLowerCase();
                if (trimmed) {
                    parts.push(trimmed);
                }
            }
        });
        return parts.join('|');
    };
    const getStore = () => {
        var _a2;
        const wpData = (_a2 = window.wp) === null || _a2 === void 0 ? void 0 : _a2.data;
        if (!wpData || typeof wpData.select !== 'function') {
            return undefined;
        }
        return wpData.select(storeKey);
    };
    const getShippingAddress = (store) => {
        if (!store) {
            return undefined;
        }
        if (typeof store.getShippingAddress === 'function') {
            return store.getShippingAddress();
        }
        if (typeof store.getCustomerData === 'function') {
            const data = store.getCustomerData();
            if (data && typeof data === 'object' && Object.prototype.hasOwnProperty.call(data, 'shipping_address')) {
                return data.shipping_address;
            }
        }
        return undefined;
    };
    const getShippingRatesHash = (store) => {
        if (!store || typeof store.getShippingRates !== 'function') {
            return '';
        }
        const rates = store.getShippingRates();
        if (!Array.isArray(rates)) {
            return '';
        }
        const ids = rates.map((rate) => {
            if (typeof rate !== 'object' || !rate) {
                return '';
            }
            if (typeof rate.rate_id === 'string') {
                return rate.rate_id;
            }
            if (typeof rate.rateId === 'string') {
                return rate.rateId;
            }
            return '';
        }).filter(Boolean);
        return JSON.stringify(ids);
    };
    const refreshShippingRates = () => {
        var _a2;
        const wpData = (_a2 = window.wp) === null || _a2 === void 0 ? void 0 : _a2.data;
        if (!wpData || typeof wpData.dispatch !== 'function') {
            return;
        }
        const dispatcher = wpData.dispatch(storeKey);
        if (!dispatcher) {
            return;
        }
        const maybeCall = (method, args = []) => {
            const callable = dispatcher[method];
            if (typeof callable === 'function') {
                try {
                    callable(...args);
                } catch (error) {
                }
            }
        };
        maybeCall('invalidateResolutionForStore', ['getShippingRates', []]);
        maybeCall('invalidateResolutionForStore', ['getCart', []]);
        maybeCall('invalidateResolutionForStore', ['getCartTotals', []]);
        maybeCall('invalidateResolution', ['getShippingRates', []]);
        maybeCall('invalidateResolution', ['getCart', []]);
        maybeCall('invalidateResolution', ['getCartTotals', []]);
        maybeCall('refreshCart');
        maybeCall('requestCart');
    };
    const requestQuote = (address) => {
        if (!config.showDistanceBadge || !config.quoteEndpoint || typeof window.fetch !== 'function') {
            state.isLoading = false;
            state.distanceText = '';
            scheduleApply();
            return;
        }
        const destination = createDestinationKey(address);
        if (!destination) {
            state.isLoading = false;
            state.distanceText = '';
            scheduleApply();
            return;
        }
        state.quoteToken += 1;
        const currentToken = state.quoteToken;
        state.isLoading = true;
        state.distanceText = '';
        scheduleApply();
        const headers = { 'Content-Type': 'application/json' };
        if (config.nonce) {
            headers['X-WP-Nonce'] = config.nonce;
        }
        const payload = { destination };
        window.fetch(config.quoteEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify(payload)
        }).then((response) => {
            if (!response.ok) {
                throw new Error('Quote request failed');
            }
            return response.json();
        }).then((data) => {
            if (currentToken !== state.quoteToken) {
                return;
            }
            state.isLoading = false;
            state.distanceText = typeof (data === null || data === void 0 ? void 0 : data.distance_text) === 'string' ? data.distance_text : '';
            scheduleApply();
        }).catch(() => {
            if (currentToken !== state.quoteToken) {
                return;
            }
            state.isLoading = false;
            state.distanceText = '';
            scheduleApply();
        });
    };
    const removeExistingBadges = () => {
        const nodes = document.querySelectorAll(`${'.' + badgeClass}[${badgeAttribute}="${badgeAttributeValue}"]`);
        nodes.forEach((node) => {
            node.remove();
        });
    };
    const applyBadgeToDom = () => {
        if (!config.showDistanceBadge) {
            removeExistingBadges();
            return;
        }
        const baseText = state.isLoading ? config.loadingText : state.distanceText;
        if (!baseText) {
            removeExistingBadges();
            return;
        }
        const text = config.badgeLabel ? `${config.badgeLabel}: ${baseText}` : baseText;
        const selectors = [`input[type="radio"][value^="${config.methodId}"]`, `[data-shipping-method-id^="${config.methodId}"]`];
        const containers = new Set();
        selectors.forEach((selector) => {
            const matches = document.querySelectorAll(selector);
            matches.forEach((element) => {
                if (element instanceof HTMLInputElement) {
                    const label = element.closest('label');
                    if (label) {
                        containers.add(label);
                    } else if (element.parentElement instanceof HTMLElement) {
                        containers.add(element.parentElement);
                    }
                } else {
                    containers.add(element);
                }
            });
        });
        removeExistingBadges();
        containers.forEach((container) => {
            const badge = document.createElement('span');
            badge.className = badgeClass;
            badge.setAttribute(badgeAttribute, badgeAttributeValue);
            badge.textContent = text;
            container.appendChild(badge);
        });
    };
    const ensureStyles = () => {
        if (!config.showDistanceBadge) {
            return;
        }
        if (document.getElementById('drs-distance-badge-style')) {
            return;
        }
        const style = document.createElement('style');
        style.id = 'drs-distance-badge-style';
        style.textContent = `.${badgeClass}{margin-inline-start:0.5rem;padding:0.125rem 0.5rem;border-radius:999px;background:var(--wp--preset--color--contrast,#1e1e1e);color:var(--wp--preset--color--base,#fff);font-size:0.75rem;font-weight:600;line-height:1.4;display:inline-flex;align-items:center;white-space:nowrap;}`;
        document.head.appendChild(style);
    };
    const onStoreChange = () => {
        const store = getStore();
        if (!store) {
            return;
        }
        const address = getShippingAddress(store);
        const addressHash = normaliseAddress(address);
        if (addressHash !== state.addressHash) {
            state.addressHash = addressHash;
            refreshShippingRates();
            requestQuote(address);
        }
        const ratesHash = getShippingRatesHash(store);
        if (ratesHash !== state.ratesHash) {
            state.ratesHash = ratesHash;
            scheduleApply();
        }
    };
    const initialise = () => {
        ensureStyles();
        const waitForStore = () => {
            var _a2;
            const wpData = (_a2 = window.wp) === null || _a2 === void 0 ? void 0 : _a2.data;
            if (!wpData || typeof wpData.subscribe !== 'function') {
                window.setTimeout(waitForStore, 200);
                return;
            }
            const store = getStore();
            const address = getShippingAddress(store);
            state.addressHash = normaliseAddress(address);
            if (config.showDistanceBadge) {
                requestQuote(address);
            }
            state.ratesHash = getShippingRatesHash(store);
            wpData.subscribe == null ? void 0 : wpData.subscribe(onStoreChange);
            scheduleApply();
        };
        waitForStore();
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
})();

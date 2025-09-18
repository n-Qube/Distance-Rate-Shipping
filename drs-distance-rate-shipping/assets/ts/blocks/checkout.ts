/**
 * WooCommerce Blocks integration for Distance Rate Shipping.
 */

type ShippingAddress = Record<string, unknown> | null | undefined;

type QuoteResponse = {
    distance_text?: string;
};

type DrsDistanceBlocksConfig = {
    quoteEndpoint: string;
    nonce?: string;
    showDistanceBadge: boolean;
    methodId: string;
    badgeLabel: string;
    loadingText: string;
    distancePrecision: number;
    distanceUnit: string;
};

type DrsDistanceBlocksState = {
    addressHash: string;
    ratesHash: string;
    distanceText: string;
    isLoading: boolean;
    quoteToken: number;
};

declare global {
    interface Window {
        drsDistanceBlocksData?: Partial<DrsDistanceBlocksConfig>;
        wp?: {
            data?: {
                select?: (store: string) => any;
                dispatch?: (store: string) => any;
                subscribe?: (listener: () => void) => () => void;
            };
        };
    }
}

const storeKey = 'wc/store/cart';
const badgeClass = 'drs-distance-badge';
const badgeAttribute = 'data-drs-badge';
const badgeAttributeValue = 'distance';

const rawConfig = window?.drsDistanceBlocksData ?? {};
const config: DrsDistanceBlocksConfig = {
    quoteEndpoint: typeof rawConfig.quoteEndpoint === 'string' ? rawConfig.quoteEndpoint : '',
    nonce: typeof rawConfig.nonce === 'string' ? rawConfig.nonce : undefined,
    showDistanceBadge: Boolean(rawConfig.showDistanceBadge),
    methodId: typeof rawConfig.methodId === 'string' ? rawConfig.methodId : 'drs_distance_rate',
    badgeLabel: typeof rawConfig.badgeLabel === 'string' ? rawConfig.badgeLabel : 'Distance',
    loadingText: typeof rawConfig.loadingText === 'string' ? rawConfig.loadingText : 'Calculating…',
    distancePrecision: typeof rawConfig.distancePrecision === 'number' ? rawConfig.distancePrecision : 1,
    distanceUnit: typeof rawConfig.distanceUnit === 'string' ? rawConfig.distanceUnit : 'km',
};

const state: DrsDistanceBlocksState = {
    addressHash: '',
    ratesHash: '',
    distanceText: '',
    isLoading: false,
    quoteToken: 0,
};

let applyScheduled = false;

const scheduleApply = (): void => {
    if (applyScheduled) {
        return;
    }

    applyScheduled = true;

    const executor = window.requestAnimationFrame ?? window.setTimeout;
    executor(() => {
        applyScheduled = false;
        applyBadgeToDom();
    });
};

const normaliseAddress = (address: ShippingAddress): string => {
    if (!address || typeof address !== 'object') {
        return '';
    }

    const keys = [
        'address_1',
        'address_2',
        'city',
        'state',
        'country',
        'postcode',
        'postal_code',
        'zip',
        'postalCode',
    ];
    const snapshot: Record<string, unknown> = {};

    keys.forEach((key) => {
        if (Object.prototype.hasOwnProperty.call(address, key)) {
            snapshot[key] = (address as Record<string, unknown>)[key];
        }
    });

    return JSON.stringify(snapshot);
};

const getStore = (): any => {
    const wpData = window.wp?.data;
    if (!wpData || typeof wpData.select !== 'function') {
        return undefined;
    }

    return wpData.select(storeKey);
};

const getShippingAddress = (store: any): ShippingAddress => {
    if (!store) {
        return undefined;
    }

    if (typeof store.getShippingAddress === 'function') {
        return store.getShippingAddress();
    }

    if (typeof store.getCustomerData === 'function') {
        const data = store.getCustomerData();
        if (data && typeof data === 'object' && Object.prototype.hasOwnProperty.call(data, 'shipping_address')) {
            return (data as { shipping_address?: ShippingAddress }).shipping_address;
        }
    }

    return undefined;
};

const getShippingRatesHash = (store: any): string => {
    if (!store || typeof store.getShippingRates !== 'function') {
        return '';
    }

    const rates = store.getShippingRates();
    if (!Array.isArray(rates)) {
        return '';
    }

    const ids = rates
        .map((rate: Record<string, unknown>) => {
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
        })
        .filter(Boolean);

    return JSON.stringify(ids);
};

const refreshShippingRates = (): void => {
    const wpData = window.wp?.data;
    if (!wpData || typeof wpData.dispatch !== 'function') {
        return;
    }

    const dispatcher = wpData.dispatch(storeKey);
    if (!dispatcher) {
        return;
    }

    const maybeCall = (method: string, args: unknown[] = []): void => {
        const callable = (dispatcher as Record<string, unknown>)[method];
        if (typeof callable === 'function') {
            try {
                (callable as (...callArgs: unknown[]) => void)(...args);
            } catch (error) {
                // Silently swallow errors from unknown store implementations.
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

const sanitiseDestination = (address: ShippingAddress): Record<string, string> => {
    const destination: Record<string, string> = {};

    if (!address || typeof address !== 'object') {
        return destination;
    }

    const allowedKeys = [
        'address_1',
        'address_2',
        'city',
        'state',
        'country',
        'postcode',
        'postal_code',
        'zip',
    ];

    allowedKeys.forEach((key) => {
        const value = (address as Record<string, unknown>)[key];
        if (typeof value === 'string' && value.trim()) {
            destination[key] = value.trim();
        }
    });

    return destination;
};

const requestQuote = (address: ShippingAddress): void => {
    if (!config.showDistanceBadge || !config.quoteEndpoint || typeof window.fetch !== 'function') {
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

    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
    };

    if (config.nonce) {
        headers['X-WP-Nonce'] = config.nonce;
    }

    const payload = {
        destination: sanitiseDestination(address),
    };

    window
        .fetch(config.quoteEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify(payload),
        })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Quote request failed');
            }

            return response.json() as Promise<QuoteResponse>;
        })
        .then((data) => {
            if (currentToken !== state.quoteToken) {
                return;
            }

            state.isLoading = false;
            state.distanceText = typeof data.distance_text === 'string' ? data.distance_text : '';
            scheduleApply();
        })
        .catch(() => {
            if (currentToken !== state.quoteToken) {
                return;
            }

            state.isLoading = false;
            state.distanceText = '';
            scheduleApply();
        });
};

const removeExistingBadges = (): void => {
    const nodes = document.querySelectorAll<HTMLElement>(`${'.' + badgeClass}[${badgeAttribute}="${badgeAttributeValue}"]`);
    nodes.forEach((node) => {
        node.remove();
    });
};

const applyBadgeToDom = (): void => {
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

    const selectors = [
        `input[type="radio"][value^="${config.methodId}"]`,
        `[data-shipping-method-id^="${config.methodId}"]`,
    ];

    const containers = new Set<HTMLElement>();
    selectors.forEach((selector) => {
        const matches = document.querySelectorAll<HTMLElement>(selector);
        matches.forEach((element) => {
            if (element instanceof HTMLInputElement) {
                const label = element.closest('label');
                if (label) {
                    containers.add(label as HTMLElement);
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

const ensureStyles = (): void => {
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

const onStoreChange = (): void => {
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

const initialise = (): void => {
    ensureStyles();

    const waitForStore = (): void => {
        const wpData = window.wp?.data;
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

        wpData.subscribe?.(onStoreChange);
        scheduleApply();
    };

    waitForStore();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialise);
} else {
    initialise();
}

export {};

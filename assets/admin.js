(function () {
    'use strict';

    const data = window.drsAdmin || {};

    function generateId(prefix) {
        return `${prefix}-${Date.now().toString(36)}-${Math.floor(Math.random() * 100000).toString(36)}`;
    }

    function normalizeRule(rule) {
        return {
            id: rule && rule.id ? String(rule.id) : generateId('rule'),
            label: rule && rule.label ? String(rule.label) : '',
            min_distance: rule && rule.min_distance !== undefined ? String(rule.min_distance) : '0',
            max_distance:
                rule && rule.max_distance !== undefined && rule.max_distance !== null
                    ? String(rule.max_distance)
                    : '',
            base_cost: rule && rule.base_cost !== undefined ? String(rule.base_cost) : '0',
            cost_per_distance:
                rule && rule.cost_per_distance !== undefined ? String(rule.cost_per_distance) : '0',
        };
    }

    function normalizeOrigin(origin) {
        return {
            id: origin && origin.id ? String(origin.id) : generateId('origin'),
            label: origin && origin.label ? String(origin.label) : '',
            address: origin && origin.address ? String(origin.address) : '',
            postcode: origin && origin.postcode ? String(origin.postcode) : '',
        };
    }

    function formatMoney(value) {
        const number = Number.parseFloat(value);
        if (Number.isNaN(number)) {
            return '0.00';
        }

        return number.toFixed(2);
    }

    function setEmptyState(element, message, show) {
        if (!element) {
            return;
        }

        element.textContent = message;
        element.style.display = show ? 'block' : 'none';
    }

    function initRulesTab() {
        const tableBody = document.querySelector('#drs-rules-table tbody');
        const addButton = document.getElementById('drs-add-rule');
        const rulesInput = document.getElementById('drs_rules_json');
        const emptyState = document.getElementById('drs-no-rules');
        const form = document.querySelector('.drs-settings-form');

        if (!tableBody || !rulesInput) {
            return;
        }

        const messages = (data && data.i18n) || {};
        let rules = Array.isArray(data.rules) ? data.rules.map(normalizeRule) : [];

        function updateInput() {
            if (rulesInput) {
                rulesInput.value = JSON.stringify(rules);
            }
        }

        function createInput(type, value, field, step) {
            const input = document.createElement('input');
            input.type = type;
            input.dataset.field = field;
            if (step) {
                input.step = step;
            }
            if (type === 'number') {
                input.min = '0';
            }
            input.value = value || '';
            input.className = type === 'number' ? 'small-text' : 'regular-text';
            return input;
        }

        function renderRules() {
            tableBody.innerHTML = '';

            if (!rules.length) {
                setEmptyState(emptyState, messages.noRules || 'No rules yet.', true);
            } else {
                setEmptyState(emptyState, '', false);
            }

            rules.forEach((rule, index) => {
                const row = document.createElement('tr');
                row.dataset.id = rule.id;

                const cells = [
                    createInput('text', rule.label, 'label'),
                    createInput('number', rule.min_distance, 'min_distance', '0.01'),
                    createInput('number', rule.max_distance, 'max_distance', '0.01'),
                    createInput('number', rule.base_cost, 'base_cost', '0.01'),
                    createInput('number', rule.cost_per_distance, 'cost_per_distance', '0.01'),
                ];

                cells.forEach((input) => {
                    const cell = document.createElement('td');
                    cell.appendChild(input);
                    row.appendChild(cell);
                });

                const actionsCell = document.createElement('td');
                actionsCell.className = 'column-actions';
                const deleteButton = document.createElement('button');
                deleteButton.type = 'button';
                deleteButton.className = 'button-link delete-rule';
                deleteButton.textContent = messages.deleteRule || 'Delete rule';
                deleteButton.dataset.index = String(index);
                actionsCell.appendChild(deleteButton);
                row.appendChild(actionsCell);

                tableBody.appendChild(row);
            });

            updateInput();
        }

        tableBody.addEventListener('input', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }

            const row = target.closest('tr');
            if (!row) {
                return;
            }

            const id = row.dataset.id;
            const field = target.dataset.field;

            const rule = rules.find((item) => item.id === id);
            if (!rule || !field) {
                return;
            }

            rule[field] = target.value;
            updateInput();
        });

        tableBody.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLButtonElement)) {
                return;
            }

            if (!target.classList.contains('delete-rule')) {
                return;
            }

            const index = Number.parseInt(target.dataset.index || '-1', 10);
            if (Number.isNaN(index) || index < 0) {
                return;
            }

            rules.splice(index, 1);
            renderRules();
        });

        if (addButton) {
            addButton.addEventListener('click', () => {
                rules.push(
                    normalizeRule({
                        id: generateId('rule'),
                        label: '',
                        min_distance: '0',
                        max_distance: '',
                        base_cost: '0',
                        cost_per_distance: '0',
                    })
                );
                renderRules();
            });
        }

        if (form) {
            form.addEventListener('submit', () => {
                updateInput();
            });
        }

        renderRules();
    }

    function initOriginsTab() {
        const tableBody = document.querySelector('#drs-origins-table tbody');
        const addButton = document.getElementById('drs-add-origin');
        const originsInput = document.getElementById('drs_origins_json');
        const emptyState = document.getElementById('drs-no-origins');
        const form = document.querySelector('.drs-settings-form');

        if (!tableBody || !originsInput) {
            return;
        }

        const messages = (data && data.i18n) || {};
        let origins = Array.isArray(data.origins) ? data.origins.map(normalizeOrigin) : [];

        function updateInput() {
            originsInput.value = JSON.stringify(origins);
        }

        function createInput(value, field) {
            const input = document.createElement('input');
            input.type = 'text';
            input.value = value || '';
            input.dataset.field = field;
            input.className = 'regular-text';
            return input;
        }

        function renderOrigins() {
            tableBody.innerHTML = '';

            if (!origins.length) {
                setEmptyState(emptyState, messages.noOrigins || 'No origins configured yet.', true);
            } else {
                setEmptyState(emptyState, '', false);
            }

            origins.forEach((origin, index) => {
                const row = document.createElement('tr');
                row.dataset.id = origin.id;

                const cells = [
                    createInput(origin.label, 'label'),
                    createInput(origin.address, 'address'),
                    createInput(origin.postcode, 'postcode'),
                ];

                cells.forEach((input) => {
                    const cell = document.createElement('td');
                    cell.appendChild(input);
                    row.appendChild(cell);
                });

                const actionsCell = document.createElement('td');
                actionsCell.className = 'column-actions';
                const deleteButton = document.createElement('button');
                deleteButton.type = 'button';
                deleteButton.className = 'button-link delete-origin';
                deleteButton.textContent = messages.deleteOrigin || 'Delete origin';
                deleteButton.dataset.index = String(index);
                actionsCell.appendChild(deleteButton);
                row.appendChild(actionsCell);

                tableBody.appendChild(row);
            });

            updateInput();
        }

        tableBody.addEventListener('input', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }

            const row = target.closest('tr');
            if (!row) {
                return;
            }

            const id = row.dataset.id;
            const field = target.dataset.field;
            const origin = origins.find((item) => item.id === id);
            if (!origin || !field) {
                return;
            }

            origin[field] = target.value;
            updateInput();
        });

        tableBody.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLButtonElement)) {
                return;
            }

            if (!target.classList.contains('delete-origin')) {
                return;
            }

            const index = Number.parseInt(target.dataset.index || '-1', 10);
            if (Number.isNaN(index) || index < 0) {
                return;
            }

            origins.splice(index, 1);
            renderOrigins();
        });

        if (addButton) {
            addButton.addEventListener('click', () => {
                origins.push(
                    normalizeOrigin({
                        id: generateId('origin'),
                        label: '',
                        address: '',
                        postcode: '',
                    })
                );
                renderOrigins();
            });
        }

        if (form) {
            form.addEventListener('submit', () => {
                updateInput();
            });
        }

        renderOrigins();
    }

    function initCalculator() {
        const container = document.getElementById('drs-calculator');
        if (!container) {
            return;
        }

        const calculateButton = document.getElementById('drs-run-calculation');
        const spinner = document.getElementById('drs-calculator-spinner');
        const result = document.getElementById('drs-calculator-result');
        const inputs = {
            origin: document.getElementById('drs-calculator-origin'),
            destination: document.getElementById('drs-calculator-destination'),
            distance: document.getElementById('drs-calculator-distance'),
            weight: document.getElementById('drs-calculator-weight'),
            items: document.getElementById('drs-calculator-items'),
            subtotal: document.getElementById('drs-calculator-subtotal'),
        };

        const messages = (data && data.i18n) || {};
        const restUrl = data.restUrl;
        const nonce = data.nonce;

        if (!calculateButton || !result || !restUrl) {
            return;
        }

        function toggleSpinner(active) {
            if (!spinner) {
                return;
            }

            if (active) {
                spinner.classList.add('is-active');
            } else {
                spinner.classList.remove('is-active');
            }
        }

        function parseFloatValue(value) {
            const number = Number.parseFloat(value);
            return Number.isFinite(number) ? number : 0;
        }

        function parseIntValue(value) {
            const number = Number.parseInt(value, 10);
            return Number.isFinite(number) ? number : 0;
        }

        calculateButton.addEventListener('click', () => {
            toggleSpinner(true);
            calculateButton.disabled = true;
            result.textContent = '';
            result.classList.remove('drs-calculator__result--error');

            const payload = {
                origin: inputs.origin ? inputs.origin.value.trim() : '',
                destination: inputs.destination ? inputs.destination.value.trim() : '',
                distance: inputs.distance ? parseFloatValue(inputs.distance.value) : 0,
                weight: inputs.weight ? parseFloatValue(inputs.weight.value) : 0,
                items: inputs.items ? parseIntValue(inputs.items.value) : 0,
                subtotal: inputs.subtotal ? parseFloatValue(inputs.subtotal.value) : 0,
            };

            fetch(restUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            })
                .then((response) => {
                    return response.json().then((json) => ({ json, ok: response.ok }));
                })
                .then(({ json, ok }) => {
                    if (!ok || (json && json.code)) {
                        throw new Error((json && json.message) || messages.calculatorError || 'Unable to retrieve a quote.');
                    }

                    const lines = [];
                    const title = messages.calculatorTitle || 'Estimated shipping cost';
                    const currency = data.currencySymbol || '';

                    lines.push(`${title}: ${currency}${formatMoney(json.total)}`);

                    if (json.rule && json.rule.label) {
                        const ruleLabel = messages.calculatorRule || 'Applied rule';
                        lines.push(`${ruleLabel}: ${json.rule.label}`);
                    } else if (messages.calculatorNone) {
                        lines.push(messages.calculatorNone);
                    }

                    if (json.breakdown) {
                        const breakdown = json.breakdown;
                        if (breakdown.rule_cost !== undefined) {
                            lines.push(`Rule cost: ${currency}${formatMoney(breakdown.rule_cost)}`);
                        }
                        if (breakdown.handling_fee !== undefined) {
                            lines.push(`Handling fee: ${currency}${formatMoney(breakdown.handling_fee)}`);
                        }
                    }

                    result.textContent = lines.join('\n');
                })
                .catch((error) => {
                    result.textContent = error.message || messages.calculatorError || 'Unable to retrieve a quote.';
                    result.classList.add('drs-calculator__result--error');
                })
                .finally(() => {
                    toggleSpinner(false);
                    calculateButton.disabled = false;
                });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('.drs-settings-form');
        if (!form) {
            return;
        }

        const currentTab = form.dataset.drsTab;

        if (currentTab === 'rules') {
            initRulesTab();
            initCalculator();
        } else if (currentTab === 'origins') {
            initOriginsTab();
        } else if (currentTab === 'general') {
            initCalculator();
        }
    });
})();

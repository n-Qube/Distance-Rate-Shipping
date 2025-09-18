# Distance Rate Shipping (DRS) for WooCommerce

This repository contains a work-in-progress WooCommerce extension that experiments with distance-aware shipping rates. It ships the core scaffolding for a custom shipping method plus a set of admin tools that make it easier to design and test distance-driven pricing rules.

## Project status

The plugin is not yet feature complete. It registers the `drs_distance_rate` shipping method and exposes configuration screens, but the customer-facing rate calculation currently returns a placeholder zero-cost rate and no automatic distance lookup is performed. Large portions of the feature list from WooCommerce Distance Rate Shipping still need to be implemented before this can be used in production stores.

## Implemented components

* **Shipping method registration.** The plugin wires the `drs_distance_rate` shipping method into WooCommerce, evaluates saved rules plus handling fees, and supports zone/instance configuration.
* **Admin settings experience.** A dedicated settings screen (WooCommerce → Distance Rate) exposes General, Rules, Origins, and Advanced tabs with fields for enabling the method, naming it, defining a fallback/handling fee, and toggling debug logging.
* **Rule and origin editors.** Rules capture a label, min/max distance, base charge, and cost per distance unit, while origins store simple address data; both tables are managed via the bundled admin JavaScript that persists JSON back into the settings option.
* **Rate preview calculator.** The admin interface includes a calculator widget that calls the REST API so store managers can test how a distance, weight, item count, and subtotal interact with stored rules and handling fees.
* **REST API endpoint.** `POST /drs/v1/quote` evaluates the saved rules, applies handling/default costs, respects a transient cache, and records debug information through the logger helper when enabled.
* **Logging helper.** `DRS\Support\Logger` wraps WooCommerce logging so that info/debug messages are only emitted when the “Debug logging” toggle is enabled in settings.
* **HPOS compatibility declaration.** During bootstrap the plugin flags compatibility with WooCommerce High Performance Order Storage (custom order tables).

## Limitations and next steps

The following capabilities are not yet implemented:

* Automatic distance lookups (straight-line or road distance) or integrations with mapping APIs.
* Rich rule conditions (per-weight, per-item, subtotal thresholds, shipping class/category filters, free-delivery triggers) beyond the basic distance matrix saved today.
* CSV/JSON import or export wired into the admin UI (support classes exist but are not hooked up yet).
* WooCommerce Blocks integrations, customer-facing distance displays, or multisite-specific logic.

## Installation

1. Copy this directory into your WordPress `wp-content/plugins` folder.
2. Activate **Distance Rate Shipping (DRS)** from the Plugins screen.
3. Visit **WooCommerce → Distance Rate** to configure settings and draft rules.

## Configuration overview

* **General tab:** enable the method, choose the public name, set a fallback rate, handling fee, and preferred distance unit.
* **Rules tab:** add distance tiers with base and per-unit costs, then use the calculator to preview a quote via the REST endpoint.
* **Origins tab:** maintain labelled origin addresses or postcodes that could be reused once distance lookups are implemented.
* **Advanced tab:** store optional API credentials and enable debug logging to collect detailed log entries while testing.

## Development

The project does not rely on Composer autoloading. Utility classes under `Support/` include a CSV codec and options helper that are exercised by a lightweight test script. To run it locally:

```bash
php tests/RulesCsvTest.php
```

When adjusting plugin logic, consider adding additional automated coverage or extending the CSV utilities to surface rule data through the admin UI.

# Distance Rate Shipping (DRS) for WooCommerce

A custom WordPress/WooCommerce plugin that calculates shipping costs based on **distance and rule matrices**, designed to work **with or without WooCommerce Shipping Zones**.  
This project replicates and extends the functionality of [WooCommerce Distance Rate Shipping](https://woocommerce.com/document/woocommerce-distance-rate-shipping/).

---

## Features

- **Flexible Shipping Method**
  - Register a new method: `drs_distance_rate`
  - Works globally (no zones) or per-zone (with fallback option)
- **Distance Calculation**
  - Straight-line (Haversine formula)
  - Road distance/time (pluggable APIs: Google Maps, Mapbox, etc.)
- **Rule Engine**
  - Distance tiers
  - Per-weight, per-item, and subtotal conditions
  - Class/category adjustments
  - Free delivery thresholds
- **Admin Tools**
  - Settings tabs: General, Rules, Origins, Advanced
  - Rule editor with CSV/JSON import/export
  - Test calculator for rate previews
- **Customer Experience**
  - Accurate shipping rates on Cart/Checkout (classic + Blocks)
  - Optional distance display
  - Graceful fallbacks if external APIs fail
- **APIs & Extensibility**
  - REST API: `drs/v1` for rules, settings, and quotes
  - Hooks/filters for developer customizations
- **Performance & Compatibility**
  - WooCommerce HPOS-ready
  - WooCommerce Blocks support
  - Multisite-compatible

---

## Requirements

- WordPress ≥ 6.2
- WooCommerce ≥ 8.5
- PHP ≥ 8.0
- MySQL ≥ 5.7 / MariaDB ≥ 10.4

---

## Installation

1. Clone or download this repository into your WordPress `wp-content/plugins` directory.
2. Activate **Distance Rate Shipping (DRS)** from the Plugins menu.
3. Configure via **WooCommerce → Settings → Shipping → Distance Rate**.

---

## Usage

- Add shipping rules in the admin UI.
- Choose distance calculation strategy (straight-line or road-based).
- Test rates using the built-in calculator.
- Optionally import/export rules via CSV/JSON.

---

## Development

- Follows WordPress coding standards.
- WCAG 2.1 AA accessibility in admin UI.
- i18n-ready (`drs-distance` textdomain).
- Autoloaded via Composer (PSR-4).
- Includes PHPUnit tests, PHPStan, and PHPCS checks.

Run tests locally:
```bash
composer install
composer test
composer stan
composer cs

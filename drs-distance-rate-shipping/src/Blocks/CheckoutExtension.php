<?php

declare(strict_types=1);

namespace DRS\DistanceRateShipping\Blocks;

use function __;
use function absint;
use function add_action;
use function dirname;
use function file_exists;
use function filemtime;
use function function_exists;
use function get_option;
use function is_admin;
use function is_array;
use function is_cart;
use function is_checkout;
use function plugins_url;
use function rest_url;
use function wp_add_inline_script;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_json_encode;
use function wp_register_script;
use function wp_script_is;

class CheckoutExtension
{
    private string $pluginFile;

    private string $pluginDir;

    public function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;
        $this->pluginDir = dirname($pluginFile);
    }

    public function register(): void
    {
        add_action('init', [$this, 'register_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_scripts(): void
    {
        if (! function_exists('wp_register_script')) {
            return;
        }

        $handle = 'drs-distance-blocks-checkout';
        $src = plugins_url('build/blocks/checkout.js', $this->pluginFile);
        $deps = ['wp-data'];
        $version = $this->resolve_asset_version($this->pluginDir . '/build/blocks/checkout.js');

        wp_register_script($handle, $src, $deps, $version, true);
    }

    public function enqueue_assets(): void
    {
        if (is_admin()) {
            return;
        }

        $isCart = function_exists('is_cart') ? is_cart() : false;
        $isCheckout = function_exists('is_checkout') ? is_checkout() : false;

        if (! $isCart && ! $isCheckout) {
            return;
        }

        $handle = 'drs-distance-blocks-checkout';
        if (! wp_script_is($handle, 'registered')) {
            $this->register_scripts();
        }

        if (! wp_script_is($handle, 'registered')) {
            return;
        }

        $settings = $this->build_script_settings();

        wp_enqueue_script($handle);

        if ([] !== $settings) {
            wp_add_inline_script(
                $handle,
                'window.drsDistanceBlocksData = Object.assign({}, window.drsDistanceBlocksData || {}, ' . wp_json_encode($settings) . ');',
                'before'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function build_script_settings(): array
    {
        $general = $this->get_general_options();

        $showDistance = ! empty($general['show_distance']);
        $distanceUnit = isset($general['distance_unit']) && 'mi' === $general['distance_unit'] ? 'mi' : 'km';
        $precision = isset($general['distance_precision']) ? absint($general['distance_precision']) : 1;

        if ($precision > 3) {
            $precision = 3;
        }

        $settings = [
            'quoteEndpoint' => rest_url('drs/v1/quote'),
            'nonce' => wp_create_nonce('wp_rest'),
            'showDistanceBadge' => $showDistance,
            'methodId' => 'drs_distance_rate',
            'distanceUnit' => $distanceUnit,
            'distancePrecision' => $precision,
            'badgeLabel' => __('Distance', 'drs-distance'),
            'loadingText' => __('Calculating…', 'drs-distance'),
        ];

        return $settings;
    }

    private function resolve_asset_version(string $file): string
    {
        if (file_exists($file)) {
            $mtime = filemtime($file);
            if (false !== $mtime) {
                return (string) $mtime;
            }
        }

        return '1.0.0';
    }

    /**
     * @return array<string, mixed>
     */
    private function get_general_options(): array
    {
        if (! function_exists('get_option')) {
            return [];
        }

        $raw = get_option('drs_general', []);

        return is_array($raw) ? $raw : [];
    }
}

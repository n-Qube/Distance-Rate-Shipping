<?php
/**
 * WooCommerce Blocks checkout extension.
 *
 * @package DRS\Blocks
 */

declare( strict_types=1 );

namespace DRS\Blocks;

use function __;
use function absint;
use function add_action;
use function dirname;
use function file_exists;
use function filemtime;
use function function_exists;
use function get_option;
use function in_array;
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

/**
 * Loads front-end assets for WooCommerce Blocks.
 */
class CheckoutExtension {
    /**
     * Path to the main plugin file.
     */
    private string $plugin_file;

    /**
     * Cached plugin directory path.
     */
    private string $plugin_dir;

    /**
     * Constructor.
     *
     * @param string $plugin_file Main plugin file path.
     */
    public function __construct( string $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->plugin_dir  = dirname( $plugin_file );
    }

    /**
     * Register hooks required for the integration.
     */
    public function register(): void {
        add_action( 'init', array( $this, 'register_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register the JavaScript bundle.
     */
    public function register_scripts(): void {
        if ( ! function_exists( 'wp_register_script' ) ) {
            return;
        }

        $handle  = 'drs-distance-blocks-checkout';
        $src     = plugins_url( 'build/blocks/checkout.js', $this->plugin_file );
        $deps    = array( 'wp-data' );
        $version = $this->resolve_asset_version( $this->plugin_dir . '/build/blocks/checkout.js' );

        wp_register_script( $handle, $src, $deps, $version, true );
    }

    /**
     * Enqueue the bundle when viewing cart or checkout.
     */
    public function enqueue_assets(): void {
        if ( is_admin() ) {
            return;
        }

        $is_cart     = function_exists( 'is_cart' ) ? is_cart() : false;
        $is_checkout = function_exists( 'is_checkout' ) ? is_checkout() : false;

        if ( ! $is_cart && ! $is_checkout ) {
            return;
        }

        $handle = 'drs-distance-blocks-checkout';

        if ( ! wp_script_is( $handle, 'registered' ) ) {
            $this->register_scripts();
        }

        if ( ! wp_script_is( $handle, 'registered' ) ) {
            return;
        }

        $settings = $this->build_script_settings();

        wp_enqueue_script( $handle );

        if ( array() !== $settings ) {
            wp_add_inline_script(
                $handle,
                'window.drsDistanceBlocksData = Object.assign({}, window.drsDistanceBlocksData || {}, ' . wp_json_encode( $settings ) . ');',
                'before'
            );
        }
    }

    /**
     * Build configuration exposed to the script.
     *
     * @return array<string, mixed>
     */
    private function build_script_settings(): array {
        $general_options = $this->get_general_options();
        $distance_unit   = $this->resolve_distance_unit( $general_options );
        $precision       = isset( $general_options['distance_precision'] ) ? absint( $general_options['distance_precision'] ) : 1;

        if ( $precision > 3 ) {
            $precision = 3;
        }

        $settings = array(
            'quoteEndpoint'     => rest_url( 'drs/v1/quote' ),
            'nonce'             => wp_create_nonce( 'wp_rest' ),
            'showDistanceBadge' => ! empty( $general_options['show_distance'] ),
            'methodId'          => 'drs_distance_rate',
            'distanceUnit'      => $distance_unit,
            'distancePrecision' => $precision,
            'badgeLabel'        => __( 'Distance', 'drs-distance' ),
            'loadingText'       => __( 'Calculating…', 'drs-distance' ),
        );

        return $settings;
    }

    /**
     * Resolve the preferred distance unit, falling back to method settings.
     *
     * @param array<string, mixed> $general_options Stored general settings.
     */
    private function resolve_distance_unit( array $general_options ): string {
        if ( isset( $general_options['distance_unit'] ) && in_array( $general_options['distance_unit'], array( 'km', 'mi' ), true ) ) {
            return (string) $general_options['distance_unit'];
        }

        $method_settings = get_option( 'drs_distance_rate_settings', array() );
        if ( is_array( $method_settings ) && isset( $method_settings['distance_unit'] ) ) {
            $unit = (string) $method_settings['distance_unit'];
            if ( in_array( $unit, array( 'km', 'mi' ), true ) ) {
                return $unit;
            }
        }

        return 'km';
    }

    /**
     * Determine the current asset version based on file modification time.
     */
    private function resolve_asset_version( string $file ): string {
        if ( file_exists( $file ) ) {
            $mtime = filemtime( $file );
            if ( false !== $mtime ) {
                return (string) $mtime;
            }
        }

        return '1.0.0';
    }

    /**
     * Retrieve stored general options.
     *
     * @return array<string, mixed>
     */
    private function get_general_options(): array {
        if ( ! function_exists( 'get_option' ) ) {
            return array();
        }

        $raw = get_option( 'drs_general', array() );

        return is_array( $raw ) ? $raw : array();
    }
}

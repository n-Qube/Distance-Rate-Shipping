<?php
/**
 * Plugin Name: Distance Rate Shipping (DRS)
 * Description: Distance-based shipping calculations for WooCommerce.
 * Version: 0.1.0
 * Author: DRS
 * Text Domain: drs-distance
 *
 * @package DRS
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DRS_DISTANCE_RATE_SHIPPING_FILE' ) ) {
    define( 'DRS_DISTANCE_RATE_SHIPPING_FILE', __FILE__ );
}

$drs_autoload = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $drs_autoload ) ) {
    require_once $drs_autoload;
}

if ( ! defined( 'DRS_DISTANCE_RATE_SHIPPING_METHOD_FILE' ) ) {
    define( 'DRS_DISTANCE_RATE_SHIPPING_METHOD_FILE', __DIR__ . '/src/Shipping/Method.php' );
}

if ( ! defined( 'DRS_DISTANCE_RATE_SHIPPING_METHOD_CLASS' ) ) {
    define( 'DRS_DISTANCE_RATE_SHIPPING_METHOD_CLASS', 'DRS\\Shipping\\Method' );
}

if ( ! defined( 'DRS_DISTANCE_RATE_SHIPPING_METHOD_ALIAS' ) ) {
    define( 'DRS_DISTANCE_RATE_SHIPPING_METHOD_ALIAS', 'DRS\\DistanceRateShipping\\Shipping\\DistanceRateMethod' );
}

if ( ! function_exists( 'drs_distance_rate_shipping_load_textdomain' ) ) {
    /**
     * Load the plugin text domain.
     */
    function drs_distance_rate_shipping_load_textdomain(): void {
        load_plugin_textdomain(
            'drs-distance',
            false,
            dirname( plugin_basename( DRS_DISTANCE_RATE_SHIPPING_FILE ) ) . '/languages'
        );
    }
}

if ( ! function_exists( 'drs_distance_rate_shipping_require_method' ) ) {
    /**
     * Ensure the shipping method implementation is loaded.
     *
     * @return bool True when the shipping method class is available.
     */
    function drs_distance_rate_shipping_require_method(): bool {
        if ( class_exists( DRS_DISTANCE_RATE_SHIPPING_METHOD_CLASS, false ) ) {
            return true;
        }

        if ( class_exists( DRS_DISTANCE_RATE_SHIPPING_METHOD_ALIAS, false ) ) {
            if ( ! class_exists( DRS_DISTANCE_RATE_SHIPPING_METHOD_CLASS, false ) ) {
                class_alias(
                    DRS_DISTANCE_RATE_SHIPPING_METHOD_ALIAS,
                    DRS_DISTANCE_RATE_SHIPPING_METHOD_CLASS
                );
            }

            return true;
        }

        if ( ! class_exists( 'WC_Shipping_Method', false ) ) {
            return false;
        }

        if ( is_readable( DRS_DISTANCE_RATE_SHIPPING_METHOD_FILE ) ) {
            require_once DRS_DISTANCE_RATE_SHIPPING_METHOD_FILE;
        }

        return class_exists( DRS_DISTANCE_RATE_SHIPPING_METHOD_CLASS, false );
    }
}

if ( ! function_exists( 'drs_distance_rate_shipping_register_method' ) ) {
    /**
     * Register the Distance Rate shipping method with WooCommerce.
     *
     * @param array $methods Registered WooCommerce shipping methods.
     *
     * @return array
     */
    function drs_distance_rate_shipping_register_method( array $methods ): array {
        if ( drs_distance_rate_shipping_require_method() ) {
            $methods['drs_distance_rate'] = DRS_DISTANCE_RATE_SHIPPING_METHOD_CLASS;
        }

        return $methods;
    }
}

add_action( 'plugins_loaded', 'drs_distance_rate_shipping_load_textdomain' );
add_action( 'woocommerce_shipping_init', 'drs_distance_rate_shipping_require_method' );
add_filter( 'woocommerce_shipping_methods', 'drs_distance_rate_shipping_register_method' );

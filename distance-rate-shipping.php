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

$drs_shipping_file  = __DIR__ . '/src/Shipping/Method.php';
$drs_shipping_class = 'DRS\\Shipping\\Method';

$drs_require_shipping_method = static function () use ( $drs_shipping_file, $drs_shipping_class ): bool {
    if ( class_exists( $drs_shipping_class, false ) ) {
        return true;
    }

    if ( ! class_exists( 'WC_Shipping_Method', false ) ) {
        return false;
    }

    if ( is_readable( $drs_shipping_file ) ) {
        require_once $drs_shipping_file;
    }

    return class_exists( $drs_shipping_class, false );
};

add_action(
    'plugins_loaded',
    static function (): void {
        load_plugin_textdomain( 'drs-distance', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
);

add_action( 'woocommerce_shipping_init', $drs_require_shipping_method );

add_filter(
    'woocommerce_shipping_methods',
    static function ( array $methods ) use ( $drs_require_shipping_method, $drs_shipping_class ): array {
        $drs_require_shipping_method();

        $methods['drs_distance_rate'] = $drs_shipping_class;

        return $methods;
    }
);

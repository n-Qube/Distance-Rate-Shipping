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

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DRS_DISTANCE_RATE_SHIPPING_FILE' ) ) {
    define( 'DRS_DISTANCE_RATE_SHIPPING_FILE', __FILE__ );
}

$drs_autoload = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $drs_autoload ) ) {
    require $drs_autoload;
}

$drs_bootstrap_class = 'DRS\\DistanceRateShipping\\Bootstrap';
$drs_bootstrap_file  = __DIR__ . '/src/Bootstrap.php';

if ( ! class_exists( $drs_bootstrap_class ) && is_readable( $drs_bootstrap_file ) ) {
    require $drs_bootstrap_file;
}

if ( class_exists( $drs_bootstrap_class ) ) {
    $drs_bootstrap = new $drs_bootstrap_class( DRS_DISTANCE_RATE_SHIPPING_FILE );

    if ( method_exists( $drs_bootstrap, 'init' ) ) {
        $drs_bootstrap->init();
    }

    return;
}

add_action(
    'woocommerce_shipping_init',
    static function () {
        $method_file = __DIR__ . '/src/Shipping/Method.php';

        if ( ! class_exists( 'DRS\\Shipping\\Method', false ) && is_readable( $method_file ) ) {
            require_once $method_file;
        }
    }
);

add_filter(
    'woocommerce_shipping_methods',
    static function ( $methods ) {
        if ( ! class_exists( 'DRS\\Shipping\\Method', false ) && class_exists( 'WC_Shipping_Method', false ) ) {
            $method_file = __DIR__ . '/src/Shipping/Method.php';

            if ( is_readable( $method_file ) ) {
                require_once $method_file;
            }
        }

        if ( class_exists( 'DRS\\Shipping\\Method', false ) ) {
            $methods['drs_distance_rate'] = 'DRS\\Shipping\\Method';
        }

        return $methods;
    }
);

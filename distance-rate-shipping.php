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

defined( 'ABSPATH' ) || exit;

add_action(
    'woocommerce_shipping_init',
    static function () {
        require_once __DIR__ . '/src/Shipping/Method.php';
    }
);

add_filter(
    'woocommerce_shipping_methods',
    static function ( $methods ) {
        if ( ! class_exists( 'DRS\\Shipping\\Method', false ) && class_exists( 'WC_Shipping_Method', false ) ) {
            require_once __DIR__ . '/src/Shipping/Method.php';
        }

        $methods['drs_distance_rate'] = 'DRS\\Shipping\\Method';

        return $methods;
    }
);

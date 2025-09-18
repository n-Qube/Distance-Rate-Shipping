<?php
/**
 * Distance Rate shipping method.
 *
 * @package DRS\Shipping
 */

namespace DRS\Shipping;

use DRS\Settings\Settings;
use WC_Shipping_Method;
use function absint;
use function function_exists;
use function get_option;
use function is_array;
use function wc_get_weight;

if ( ! class_exists( __NAMESPACE__ . '\\Rate_Calculator', false ) && is_readable( __DIR__ . '/Rate_Calculator.php' ) ) {
    require_once __DIR__ . '/Rate_Calculator.php';
}

if ( ! class_exists( __NAMESPACE__ . '\\Distance_Service', false ) && is_readable( __DIR__ . '/Distance_Service.php' ) ) {
    require_once __DIR__ . '/Distance_Service.php';
}

if ( ! class_exists( __NAMESPACE__ . '\\Method' ) ) {
    /**
     * Distance Rate Shipping method implementation.
     */
    class Method extends WC_Shipping_Method {
        /**
         * Constructor.
         *
         * @param int $instance_id Shipping method instance.
         */
        public function __construct( $instance_id = 0 ) {
            $this->id                 = 'drs_distance_rate';
            $this->instance_id        = absint( $instance_id );
            $this->method_title       = __( 'Distance Rate', 'drs-distance' );
            $this->method_description = __( 'Calculate shipping rates based on customer distance.', 'drs-distance' );
            $this->supports           = array(
                'shipping-zones',
                'instance-settings',
            );

            $this->init();

            $this->enabled = $this->get_option( 'enabled', 'yes' );
            $this->title   = $this->get_option( 'title', __( 'Distance Rate', 'drs-distance' ) );
        }

        /**
         * Initialize form fields and settings.
         */
        public function init() {
            $this->init_form_fields();
            $this->init_settings();

            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

            if ( $this->instance_id ) {
                add_action( 'woocommerce_update_options_shipping_' . $this->id . '_' . $this->instance_id, array( $this, 'process_admin_options' ) );
            }
        }

        /**
         * Setup the admin settings fields.
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled'  => array(
                    'title'   => __( 'Enable/Disable', 'drs-distance' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Distance Rate Shipping', 'drs-distance' ),
                    'default' => 'yes',
                ),
                'title'    => array(
                    'title'       => __( 'Method Title', 'drs-distance' ),
                    'type'        => 'text',
                    'description' => __( 'Title shown to customers during checkout.', 'drs-distance' ),
                    'default'     => __( 'Distance Rate', 'drs-distance' ),
                    'desc_tip'    => true,
                ),
                'units'    => array(
                    'title'       => __( 'Distance Units', 'drs-distance' ),
                    'type'        => 'select',
                    'description' => __( 'Unit system used when evaluating distances.', 'drs-distance' ),
                    'default'     => 'km',
                    'options'     => array(
                        'km' => __( 'Kilometers', 'drs-distance' ),
                        'mi' => __( 'Miles', 'drs-distance' ),
                    ),
                ),
                'strategy' => array(
                    'title'       => __( 'Distance Strategy', 'drs-distance' ),
                    'type'        => 'select',
                    'description' => __( 'How distance is calculated for rate determinations.', 'drs-distance' ),
                    'default'     => 'straight_line',
                    'options'     => array(
                        'straight_line' => __( 'Straight Line (Haversine)', 'drs-distance' ),
                        'road_distance' => __( 'Road distance (requires provider)', 'drs-distance' ),
                    ),
                ),
            );
        }

        /**
         * Calculate shipping for the package.
         *
         * @param array $package Package data.
         */
        public function calculate_shipping( $package = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
            if ( 'yes' !== $this->enabled ) {
                return;
            }

            $settings = Settings::get_settings();

            $unit_option      = $this->get_option( 'units', $settings['distance_unit'] ?? 'km' );
            $strategy_option  = $this->get_option( 'strategy', $settings['calculation_strategy'] ?? 'straight_line' );
            $settings['distance_unit']         = in_array( $unit_option, array( 'km', 'mi' ), true ) ? $unit_option : ( $settings['distance_unit'] ?? 'km' );
            $settings['calculation_strategy']  = in_array( $strategy_option, array( 'straight_line', 'road_distance' ), true ) ? $strategy_option : ( $settings['calculation_strategy'] ?? 'straight_line' );

            $package_array = is_array( $package ) ? $package : array();
            $totals        = $this->extract_package_totals( $package_array );
            $origin        = $this->get_origin_location( $settings );
            $destination   = $this->get_destination_location( $package_array );

            $distance_service = new Distance_Service( $settings );
            $distance_result  = $distance_service->get_distance( $origin, $destination );

            if ( null === $distance_result || ! isset( $distance_result['value'] ) ) {
                $this->add_backup_rate(
                    $settings,
                    array(
                        'drs_distance_available' => false,
                        'drs_distance_reason'    => 'unavailable',
                    )
                );
                return;
            }

            $distance = (float) $distance_result['value'];

            $calculator = new Rate_Calculator();
            $quote      = $calculator->calculate(
                $settings,
                $distance,
                $totals['weight'],
                $totals['items'],
                $totals['subtotal']
            );

            $meta = array(
                'drs_distance'          => $distance,
                'drs_distance_unit'     => $distance_result['unit'] ?? $settings['distance_unit'],
                'drs_distance_strategy' => $distance_result['strategy'] ?? $settings['calculation_strategy'],
                'drs_provider_fallback' => ! empty( $distance_result['was_fallback'] ),
                'drs_distance_cached'   => ! empty( $distance_result['from_cache'] ),
                'drs_items'             => $totals['items'],
                'drs_weight'            => $totals['weight'],
                'drs_subtotal'          => $totals['subtotal'],
            );

            if ( isset( $distance_result['coordinates'] ) ) {
                $meta['drs_coordinates'] = $distance_result['coordinates'];
            }

            if ( isset( $quote['rule'] ) && is_array( $quote['rule'] ) ) {
                $meta['drs_matched_rule'] = $quote['rule']['id'] ?? '';
            } else {
                $meta['drs_used_default_rate'] = true;
            }

            $this->add_rate(
                array(
                    'id'       => $this->get_rate_id(),
                    'label'    => $this->title,
                    'cost'     => $quote['total'],
                    'meta_data'=> $meta,
                )
            );
        }

        /**
         * Get the unique rate identifier.
         *
         * @return string
         */
        protected function get_rate_id() {
            if ( $this->instance_id ) {
                return $this->id . ':' . $this->instance_id;
            }

            return $this->id;
        }

        /**
         * Retrieve the origin location from settings or store defaults.
         *
         * @param array<string, mixed> $settings Plugin settings.
         *
         * @return array<string, mixed>
         */
        private function get_origin_location( array $settings ): array {
            $origins = isset( $settings['origins'] ) && is_array( $settings['origins'] ) ? $settings['origins'] : array();

            if ( ! empty( $origins ) && is_array( $origins[0] ) ) {
                $origin      = $origins[0];
                $store_origin = $this->get_store_origin();

                return array(
                    'address'   => (string) ( $origin['address'] ?? $store_origin['address'] ),
                    'address_1' => (string) ( $origin['address'] ?? $store_origin['address'] ),
                    'address_2' => (string) ( $origin['address_2'] ?? $store_origin['address_2'] ),
                    'postcode'  => (string) ( $origin['postcode'] ?? $store_origin['postcode'] ),
                    'city'      => (string) ( $origin['city'] ?? $store_origin['city'] ),
                    'state'     => (string) ( $origin['state'] ?? $store_origin['state'] ),
                    'country'   => (string) ( $origin['country'] ?? $store_origin['country'] ),
                    'lat'       => $origin['lat'] ?? $origin['latitude'] ?? null,
                    'lng'       => $origin['lng'] ?? $origin['longitude'] ?? null,
                );
            }

            return $this->get_store_origin();
        }

        /**
         * Retrieve the destination location from the package data.
         *
         * @param array<string, mixed> $package Package data.
         *
         * @return array<string, string>
         */
        private function get_destination_location( array $package ): array {
            $destination = isset( $package['destination'] ) && is_array( $package['destination'] ) ? $package['destination'] : array();

            $address  = $destination['address'] ?? $destination['address_1'] ?? '';
            $address2 = $destination['address_2'] ?? '';
            $postcode = $destination['postcode'] ?? $destination['zip'] ?? '';

            return array(
                'address'   => (string) $address,
                'address_1' => (string) $address,
                'address_2' => (string) $address2,
                'postcode'  => (string) $postcode,
                'city'      => (string) ( $destination['city'] ?? '' ),
                'state'     => (string) ( $destination['state'] ?? '' ),
                'country'   => (string) ( $destination['country'] ?? '' ),
                'lat'       => $destination['lat'] ?? $destination['latitude'] ?? null,
                'lng'       => $destination['lng'] ?? $destination['longitude'] ?? null,
            );
        }

        /**
         * Retrieve the store base location as a fallback.
         *
         * @return array<string, string>
         */
        private function get_store_origin(): array {
            $default_country = (string) get_option( 'woocommerce_default_country', '' );
            $country         = '';
            $state           = '';

            if ( '' !== $default_country ) {
                $parts   = explode( ':', $default_country );
                $country = $parts[0] ?? '';
                $state   = $parts[1] ?? '';
            }

            return array(
                'address'   => (string) get_option( 'woocommerce_store_address', '' ),
                'address_1' => (string) get_option( 'woocommerce_store_address', '' ),
                'address_2' => (string) get_option( 'woocommerce_store_address_2', '' ),
                'postcode'  => (string) get_option( 'woocommerce_store_postcode', '' ),
                'city'      => (string) get_option( 'woocommerce_store_city', '' ),
                'state'     => $state,
                'country'   => $country,
            );
        }

        /**
         * Extract totals from the package contents.
         *
         * @param array<string, mixed> $package Package data.
         *
         * @return array{items: int, weight: float, subtotal: float}
         */
        private function extract_package_totals( array $package ): array {
            $items    = 0;
            $weight   = 0.0;
            $subtotal = 0.0;

            $contents = isset( $package['contents'] ) && is_array( $package['contents'] ) ? $package['contents'] : array();

            foreach ( $contents as $line ) {
                if ( ! is_array( $line ) ) {
                    continue;
                }

                $quantity = isset( $line['quantity'] ) ? (int) $line['quantity'] : 0;

                if ( $quantity <= 0 ) {
                    continue;
                }

                $items    += $quantity;
                $subtotal += isset( $line['line_total'] ) ? (float) $line['line_total'] : 0.0;

                if ( isset( $line['data'] ) && is_object( $line['data'] ) && method_exists( $line['data'], 'get_weight' ) ) {
                    $product_weight = (float) $line['data']->get_weight();

                    if ( function_exists( 'wc_get_weight' ) ) {
                        $product_weight = (float) wc_get_weight( $product_weight, 'kg' );
                    }

                    $weight += $product_weight * $quantity;
                }
            }

            return array(
                'items'    => max( 0, $items ),
                'weight'   => max( 0.0, $weight ),
                'subtotal' => max( 0.0, $subtotal ),
            );
        }

        /**
         * Register a backup rate when distance calculations fail entirely.
         *
         * @param array<string, mixed> $settings Plugin settings.
         * @param array<string, mixed> $meta     Additional metadata for the rate.
         */
        private function add_backup_rate( array $settings, array $meta = array() ): void {
            $cost               = isset( $settings['default_rate'] ) ? (float) $settings['default_rate'] : 0.0;
            $label              = $this->title;
            $used_backup_label  = false;
            $fallback_preferred = isset( $settings['fallback_enabled'] ) && in_array( $settings['fallback_enabled'], array( true, 'yes', 'true', 1 ), true );

            if ( $fallback_preferred ) {
                $label_value = isset( $settings['fallback_label'] ) ? (string) $settings['fallback_label'] : '';
                if ( '' !== $label_value ) {
                    $label = $label_value;
                }

                if ( isset( $settings['fallback_cost'] ) ) {
                    $cost = (float) $settings['fallback_cost'];
                }

                $used_backup_label = true;
            }

            $this->add_rate(
                array(
                    'id'       => $this->get_rate_id(),
                    'label'    => $label,
                    'cost'     => round( max( 0.0, $cost ), 2 ),
                    'meta_data'=> array_merge(
                        array(
                            'drs_distance_available' => false,
                            'drs_used_backup_rate'   => $used_backup_label,
                        ),
                        $meta
                    ),
                )
            );
        }
    }
}

<?php
/**
 * Distance Rate shipping method.
 *
 * @package DRS\Shipping
 */

namespace DRS\Shipping;

use DRS\Settings\Settings;
use WC_Shipping_Method;
use function array_key_exists;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_object;
use function is_string;
use function method_exists;

if ( ! class_exists( '\\DRS\\Settings\\Settings', false ) ) {
    require_once dirname( __DIR__ ) . '/Settings/Settings.php';
}

if ( ! class_exists( __NAMESPACE__ . '\\Calculator', false ) ) {
    require_once __DIR__ . '/Calculator.php';
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

            if ( ! is_array( $package ) ) {
                $package = array();
            }

            $settings = Settings::get_settings();

            $global_enabled = $settings['enabled'] ?? 'yes';

            if ( is_bool( $global_enabled ) ) {
                $settings_enabled = $global_enabled;
            } else {
                $value            = is_string( $global_enabled ) ? $global_enabled : (string) $global_enabled;
                $settings_enabled = 'yes' === $value || '1' === $value;
            }

            if ( ! $settings_enabled ) {
                return;
            }

            $inputs = $this->build_calculator_inputs( $package );
            $result = Calculator::calculate( $inputs, $settings );

            $meta_data = array(
                'drs_used_fallback' => $result['used_fallback'] ? 'yes' : 'no',
                'drs_rule_cost'     => Settings::format_decimal( $result['rule_cost'] ),
                'drs_handling_fee'  => Settings::format_decimal( $result['handling_fee'] ),
            );

            if ( isset( $result['rule'] ) && is_array( $result['rule'] ) ) {
                if ( isset( $result['rule']['id'] ) && '' !== (string) $result['rule']['id'] ) {
                    $meta_data['drs_rule_id'] = (string) $result['rule']['id'];
                }

                if ( isset( $result['rule']['label'] ) && '' !== (string) $result['rule']['label'] ) {
                    $meta_data['drs_rule_label'] = (string) $result['rule']['label'];
                }
            }

            $this->add_rate(
                array(
                    'id'        => $this->get_rate_id(),
                    'label'     => $this->title,
                    'cost'      => $result['total'],
                    'meta_data' => $meta_data,
                )
            );
        }

        /**
         * Build the calculator context for a WooCommerce shipping package.
         *
         * @param array<string, mixed> $package Shipping package data.
         * @return array<string, mixed>
         */
        protected function build_calculator_inputs( array $package ): array {
            $distance = $this->extract_distance( $package );

            $weight           = 0.0;
            $items            = 0;
            $subtotal         = 0.0;
            $has_line_totals  = false;

            if ( isset( $package['contents'] ) && is_array( $package['contents'] ) ) {
                foreach ( $package['contents'] as $item ) {
                    if ( ! is_array( $item ) ) {
                        continue;
                    }

                    $quantity = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;

                    if ( $quantity < 0 ) {
                        $quantity = 0;
                    }

                    $items += $quantity;

                    $item_weight = 0.0;

                    if ( isset( $item['data'] ) && is_object( $item['data'] ) && method_exists( $item['data'], 'get_weight' ) ) {
                        $item_weight = (float) $item['data']->get_weight();
                    } elseif ( isset( $item['weight'] ) ) {
                        $item_weight = (float) $item['weight'];
                    }

                    if ( $item_weight < 0 ) {
                        $item_weight = 0.0;
                    }

                    $weight += $item_weight * $quantity;

                    if ( array_key_exists( 'line_subtotal', $item ) ) {
                        $subtotal += (float) $item['line_subtotal'];
                        $has_line_totals = true;
                    } elseif ( array_key_exists( 'line_total', $item ) ) {
                        $subtotal += (float) $item['line_total'];
                        $has_line_totals = true;
                    }
                }
            }

            if ( ! $has_line_totals && isset( $package['contents_cost'] ) ) {
                $subtotal = (float) $package['contents_cost'];
            }

            if ( $subtotal <= 0.0 && isset( $package['cart_subtotal'] ) ) {
                $subtotal = (float) $package['cart_subtotal'];
            }

            if ( $subtotal <= 0.0 && isset( $package['subtotal'] ) ) {
                $subtotal = (float) $package['subtotal'];
            }

            $origin = '';

            if ( isset( $package['origin'] ) ) {
                $origin = $this->stringify_location_value( $package['origin'] );
            }

            $destination = '';

            if ( isset( $package['destination'] ) && is_array( $package['destination'] ) ) {
                $destination = $this->stringify_destination( $package['destination'] );
            }

            return array(
                'distance'    => $distance,
                'weight'      => $weight,
                'items'       => $items,
                'subtotal'    => $subtotal,
                'origin'      => $origin,
                'destination' => $destination,
            );
        }

        /**
         * Attempt to read a distance value from the package payload.
         *
         * @param array<string, mixed> $package Shipping package data.
         * @return mixed Raw distance value.
         */
        protected function extract_distance( array $package ) {
            $candidates = array();

            foreach ( array( 'drs_distance', 'distance' ) as $key ) {
                if ( isset( $package[ $key ] ) ) {
                    $candidates[] = $package[ $key ];
                }
            }

            if ( isset( $package['destination'] ) && is_array( $package['destination'] ) ) {
                foreach ( array( 'drs_distance', 'distance' ) as $key ) {
                    if ( isset( $package['destination'][ $key ] ) ) {
                        $candidates[] = $package['destination'][ $key ];
                    }
                }
            }

            foreach ( $candidates as $value ) {
                if ( null !== $value && '' !== $value ) {
                    return $value;
                }
            }

            return 0.0;
        }

        /**
         * Convert an arbitrary location value into a string.
         *
         * @param mixed $value Raw location value.
         */
        protected function stringify_location_value( $value ): string {
            if ( is_string( $value ) ) {
                return $value;
            }

            if ( is_numeric( $value ) ) {
                return (string) $value;
            }

            if ( is_array( $value ) ) {
                return $this->stringify_destination( $value );
            }

            if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
                return (string) $value;
            }

            return '';
        }

        /**
         * Convert a destination array into a representative string.
         *
         * @param array<string, mixed> $destination Destination payload.
         */
        protected function stringify_destination( array $destination ): string {
            $order = array( 'postcode', 'zip', 'city', 'address', 'address_1', 'address_2', 'state', 'country' );

            foreach ( $order as $key ) {
                if ( isset( $destination[ $key ] ) && '' !== (string) $destination[ $key ] ) {
                    return (string) $destination[ $key ];
                }
            }

            return '';
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
    }
}

<?php
/**
 * Distance Rate Shipping method implementation.
 *
 * @package DistanceRateShipping
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DRS_Shipping_Method' ) && class_exists( 'WC_Shipping_Method' ) ) {
    /**
     * Main shipping method class.
     */
    class DRS_Shipping_Method extends WC_Shipping_Method {
        /**
         * Constructor.
         *
         * @param int $instance_id Instance identifier.
         */
        public function __construct( $instance_id = 0 ) {
            $this->id                 = 'drs_shipping';
            $this->instance_id        = absint( $instance_id );
            $this->method_title       = __( 'Distance Rate Shipping', 'distance-rate-shipping' );
            $this->method_description = __( 'Calculate shipping rates using configurable distance rules.', 'distance-rate-shipping' );
            $this->supports           = array(
                'shipping-zones',
                'instance-settings',
                'instance-settings-modal',
            );

            $this->init();
        }

        /**
         * Initialize form fields and settings.
         */
        public function init() {
            $this->init_form_fields();
            $this->init_settings();

            $this->enabled = $this->get_option( 'enabled', 'yes' );
            $this->title   = $this->get_option( 'title', __( 'Distance Rate Shipping', 'distance-rate-shipping' ) );

            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_update_options_shipping_' . $this->id . '_' . $this->instance_id, array( $this, 'process_admin_options' ) );
        }

        /**
         * Register instance form fields.
         */
        public function init_form_fields() {
            $this->instance_form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'distance-rate-shipping' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Distance Rate Shipping', 'distance-rate-shipping' ),
                    'default' => 'yes',
                ),
                'title'   => array(
                    'title'       => __( 'Method Title', 'distance-rate-shipping' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title the customer sees during checkout.', 'distance-rate-shipping' ),
                    'default'     => __( 'Distance Rate Shipping', 'distance-rate-shipping' ),
                ),
                'cost'    => array(
                    'title'       => __( 'Base Cost', 'distance-rate-shipping' ),
                    'type'        => 'price',
                    'description' => __( 'Flat base cost applied to the shipment.', 'distance-rate-shipping' ),
                    'default'     => '0',
                ),
                'tax_status' => array(
                    'title'   => __( 'Tax Status', 'distance-rate-shipping' ),
                    'type'    => 'select',
                    'default' => 'taxable',
                    'options' => array(
                        'taxable' => __( 'Taxable', 'distance-rate-shipping' ),
                        'none'    => __( 'None', 'distance-rate-shipping' ),
                    ),
                ),
            );
        }

        /**
         * Calculate the shipping cost for the package.
         *
         * @param array $package Package data from WooCommerce.
         */
        public function calculate_shipping( $package = array() ) {
            if ( 'yes' !== $this->get_option( 'enabled', 'yes' ) ) {
                return;
            }

            $label = $this->get_option( 'title', $this->method_title );
            $cost  = (float) wc_format_decimal( $this->get_option( 'cost', '0' ) );

            $tax_status = $this->get_option( 'tax_status', 'taxable' );
            $taxes      = array();

            if ( 'none' !== $tax_status ) {
                $taxes = WC_Tax::calc_shipping_tax( $cost, WC_Tax::get_shipping_tax_rates( null ) );
            }

            $rate = array(
                'id'    => $this->get_rate_id(),
                'label' => $label,
                'cost'  => $cost,
                'taxes' => $taxes,
            );

            $this->add_rate( $rate );
        }

        /**
         * Unique rate identifier for the instance.
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

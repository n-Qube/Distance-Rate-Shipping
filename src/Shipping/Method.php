<?php
/**
 * Distance Rate shipping method.
 *
 * @package DRS\Shipping
 */

namespace DRS\Shipping;

use WC_Shipping_Method;

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

            $this->add_rate(
                array(
                    'id'    => $this->get_rate_id(),
                    'label' => __( 'DRS (Demo)', 'drs-distance' ),
                    'cost'  => 0,
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
    }
}

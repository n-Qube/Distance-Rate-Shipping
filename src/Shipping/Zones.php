<?php
/**
 * Helpers for zone detection and global fallback handling.
 *
 * @package DistanceRateShipping
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'drs_bootstrap_zone_hooks' ) ) {
    /**
     * Register global hooks once.
     */
    function drs_bootstrap_zone_hooks() {
        static $bootstrapped = false;

        if ( $bootstrapped ) {
            return;
        }

        $bootstrapped = true;

        add_filter( 'woocommerce_get_settings_pages', 'drs_register_global_settings_page' );
        add_filter( 'woocommerce_package_rates', 'drs_maybe_apply_global_rate', 99, 2 );
    }
}

if ( ! function_exists( 'drs_has_defined_zones' ) ) {
    /**
     * Determine if the store has any custom shipping zones.
     *
     * @return bool
     */
    function drs_has_defined_zones() {
        if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
            return false;
        }

        $zones = WC_Shipping_Zones::get_zones();

        return ! empty( $zones );
    }
}

if ( ! function_exists( 'drs_register_global_settings_page' ) ) {
    /**
     * Append the custom settings page into WooCommerce shipping settings.
     *
     * @param array $settings_pages Settings page instances.
     * @return array
     */
    function drs_register_global_settings_page( $settings_pages ) {
        if ( ! class_exists( 'WC_Settings_Page' ) ) {
            return $settings_pages;
        }

        if ( ! class_exists( 'DRS_Global_Settings_Page' ) ) {
            class DRS_Global_Settings_Page extends WC_Settings_Page {
                /**
                 * Constructor.
                 */
                public function __construct() {
                    $this->id    = 'drs_global';
                    $this->label = __( 'DRS (Global)', 'distance-rate-shipping' );

                    parent::__construct();
                }

                /**
                 * Build settings fields to mimic the zone-instance configuration.
                 *
                 * @return array
                 */
                public function get_settings() {
                    $description = drs_has_defined_zones()
                        ? __( 'Use these settings as a fallback when shipping zones do not return a Distance Rate Shipping quote.', 'distance-rate-shipping' )
                        : __( 'No shipping zones detected. These settings will be used for all Distance Rate Shipping quotes.', 'distance-rate-shipping' );

                    $settings = array(
                        array(
                            'title' => __( 'Global Distance Rate Shipping', 'distance-rate-shipping' ),
                            'type'  => 'title',
                            'id'    => 'drs_global_section_start',
                            'desc'  => $description,
                        ),
                    );

                    foreach ( drs_get_global_settings_fields() as $field ) {
                        $settings[] = $field;
                    }

                    $settings[] = array(
                        'type' => 'sectionend',
                        'id'   => 'drs_global_section_start',
                    );

                    return $settings;
                }

                /**
                 * Persist values when the settings form is saved.
                 */
                public function save() {
                    WC_Admin_Settings::save_fields( $this->get_settings() );
                }
            }
        }

        $settings_pages[] = new DRS_Global_Settings_Page();

        return $settings_pages;
    }
}

if ( ! function_exists( 'drs_get_global_settings_fields' ) ) {
    /**
     * Retrieve global settings fields based on the instance settings of the shipping method.
     *
     * @return array
     */
    function drs_get_global_settings_fields() {
        static $settings = null;

        if ( null !== $settings ) {
            return $settings;
        }

        $instance_fields = drs_get_reference_method_fields();

        if ( empty( $instance_fields ) ) {
            $instance_fields = drs_get_default_method_fields();
        }

        $settings = array();

        foreach ( $instance_fields as $key => $field ) {
            $field['id'] = drs_global_option_name( $key );

            if ( 'checkbox' === $field['type'] && isset( $field['label'] ) ) {
                $field['desc'] = $field['label'];
                unset( $field['label'] );
            }

            if ( 'checkbox' === $field['type'] && isset( $field['description'] ) ) {
                $field['desc_tip'] = $field['description'];
                unset( $field['description'] );
            }

            if ( ! isset( $field['default'] ) ) {
                $field['default'] = '';
            }

            $settings[] = $field;
        }

        $settings[] = array(
            'title'   => __( 'Enable global fallback', 'distance-rate-shipping' ),
            'type'    => 'checkbox',
            'id'      => drs_global_option_name( 'global_fallback' ),
            'default' => 'no',
            'desc'    => __( 'When enabled, these global settings will run after shipping zone methods.', 'distance-rate-shipping' ),
        );

        return $settings;
    }
}

if ( ! function_exists( 'drs_get_reference_method_fields' ) ) {
    /**
     * Access the shipping method instance form fields for reuse.
     *
     * @return array
     */
    function drs_get_reference_method_fields() {
        static $fields = null;

        if ( null !== $fields ) {
            return $fields;
        }

        if ( ! class_exists( 'DRS_Shipping_Method' ) ) {
            $path = trailingslashit( DRS_PLUGIN_DIR ) . 'src/Shipping/Method.php';
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }

        if ( ! class_exists( 'DRS_Shipping_Method' ) ) {
            return array();
        }

        $method = new DRS_Shipping_Method();

        if ( ! method_exists( $method, 'get_instance_form_fields' ) ) {
            return array();
        }

        $fields = $method->get_instance_form_fields();

        return $fields;
    }
}

if ( ! function_exists( 'drs_get_default_method_fields' ) ) {
    /**
     * Provide baseline fields when the shipping method cannot be loaded.
     *
     * @return array
     */
    function drs_get_default_method_fields() {
        return array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'distance-rate-shipping' ),
                'type'    => 'checkbox',
                'desc'    => __( 'Enable Distance Rate Shipping', 'distance-rate-shipping' ),
                'default' => 'yes',
            ),
            'title'   => array(
                'title'       => __( 'Method Title', 'distance-rate-shipping' ),
                'type'        => 'text',
                'description' => __( 'Displayed to customers at checkout.', 'distance-rate-shipping' ),
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
}

if ( ! function_exists( 'drs_global_option_name' ) ) {
    /**
     * Calculate the option name for a given global key.
     *
     * @param string $key Setting key.
     * @return string
     */
    function drs_global_option_name( $key ) {
        return 'drs_global_' . $key;
    }
}

if ( ! function_exists( 'drs_get_global_setting' ) ) {
    /**
     * Fetch a single global setting value.
     *
     * @param string $key Setting key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    function drs_get_global_setting( $key, $default = '' ) {
        return get_option( drs_global_option_name( $key ), $default );
    }
}

if ( ! function_exists( 'drs_get_global_settings' ) ) {
    /**
     * Collect all global settings for the fallback.
     *
     * @return array
     */
    function drs_get_global_settings() {
        $settings = array();

        foreach ( drs_get_global_settings_fields() as $field ) {
            if ( empty( $field['id'] ) || in_array( $field['type'], array( 'title', 'sectionend' ), true ) ) {
                continue;
            }

            $key               = str_replace( 'drs_global_', '', $field['id'] );
            $settings[ $key ]  = drs_get_global_setting( $key, isset( $field['default'] ) ? $field['default'] : '' );
        }

        return $settings;
    }
}

if ( ! function_exists( 'drs_global_method_enabled' ) ) {
    /**
     * Determine if the global method is enabled.
     *
     * @return bool
     */
    function drs_global_method_enabled() {
        $enabled = drs_get_global_setting( 'enabled', 'yes' );

        return wc_string_to_bool( $enabled );
    }
}

if ( ! function_exists( 'drs_global_fallback_enabled' ) ) {
    /**
     * Determine if the global fallback toggle is active.
     *
     * @return bool
     */
    function drs_global_fallback_enabled() {
        $enabled = drs_get_global_setting( 'global_fallback', 'no' );

        return wc_string_to_bool( $enabled );
    }
}

if ( ! function_exists( 'drs_maybe_apply_global_rate' ) ) {
    /**
     * Inject the global rate when required.
     *
     * @param array $rates   Calculated rates.
     * @param array $package Package data.
     * @return array
     */
    function drs_maybe_apply_global_rate( $rates, $package ) {
        if ( ! drs_global_method_enabled() ) {
            return $rates;
        }

        $zones_exist = drs_has_defined_zones();

        if ( $zones_exist && ! drs_global_fallback_enabled() ) {
            return $rates;
        }

        if ( $zones_exist ) {
            foreach ( $rates as $rate ) {
                if ( isset( $rate->method_id ) && 'drs_shipping' === $rate->method_id ) {
                    return $rates;
                }
            }
        }

        $settings  = drs_get_global_settings();
        $is_enable = isset( $settings['enabled'] ) ? wc_string_to_bool( $settings['enabled'] ) : true;

        if ( ! $is_enable ) {
            return $rates;
        }

        $label      = ! empty( $settings['title'] ) ? $settings['title'] : __( 'Distance Rate Shipping', 'distance-rate-shipping' );
        $cost       = isset( $settings['cost'] ) ? wc_format_decimal( $settings['cost'] ) : '0';
        $tax_status = isset( $settings['tax_status'] ) ? $settings['tax_status'] : 'taxable';

        $cost_float = (float) $cost;
        $taxes      = array();

        if ( 'none' !== $tax_status ) {
            $taxes = WC_Tax::calc_shipping_tax( $cost_float, WC_Tax::get_shipping_tax_rates( null ) );
        }

        $rate_id                = 'drs_shipping_global';
        $rates[ $rate_id ] = new WC_Shipping_Rate(
            $rate_id,
            $label,
            $cost_float,
            $taxes,
            'drs_shipping_global'
        );

        return $rates;
    }
}

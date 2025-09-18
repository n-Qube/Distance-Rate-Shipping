<?php
/**
 * Shared settings helpers.
 *
 * @package DRS\Settings
 */

declare( strict_types=1 );

namespace DRS\Settings;

/**
 * Settings helper utilities.
 */
class Settings {
    /**
     * Option name used to persist settings.
     */
    public const OPTION_NAME = 'drs_distance_rate_settings';

    /**
     * Retrieve default settings.
     *
     * @return array<string, mixed>
     */
    public static function get_defaults(): array {
        return array(
            'enabled'               => 'yes',
            'method_title'          => __( 'Distance Rate', 'drs-distance' ),
            'handling_fee'          => '0.00',
            'default_rate'          => '0.00',
            'distance_unit'         => 'km',
            'calculation_strategy'  => 'straight_line',
            'cache_enabled'         => 'yes',
            'cache_ttl'             => 30,
            'fallback_enabled'      => 'no',
            'fallback_label'        => __( 'Backup rate', 'drs-distance' ),
            'fallback_cost'         => '0.00',
            'fallback_distance'     => '0.00',
            'rules'                 => array(),
            'origins'               => array(),
            'api_key'               => '',
            'debug_mode'            => 'no',
        );
    }

    /**
     * Fetch stored settings merged with defaults.
     *
     * @return array<string, mixed>
     */
    public static function get_settings(): array {
        $stored = get_option( self::OPTION_NAME, array() );

        if ( ! is_array( $stored ) ) {
            $stored = array();
        }

        $settings = array_merge( self::get_defaults(), $stored );
        $settings['rules']   = self::normalize_rules( $settings['rules'] ?? array() );
        $settings['origins'] = self::normalize_origins( $settings['origins'] ?? array() );

        return $settings;
    }

    /**
     * Retrieve stored rules as an array.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_rules(): array {
        $settings = self::get_settings();

        return $settings['rules'];
    }

    /**
     * Retrieve stored origins as an array.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_origins(): array {
        $settings = self::get_settings();

        return $settings['origins'];
    }

    /**
     * Retrieve the configured handling fee as a float.
     */
    public static function get_handling_fee(): float {
        $settings = self::get_settings();

        return (float) $settings['handling_fee'];
    }

    /**
     * Retrieve the configured default rate when no rule matches.
     */
    public static function get_default_rate(): float {
        $settings = self::get_settings();

        return (float) $settings['default_rate'];
    }

    /**
     * Retrieve the preferred distance unit.
     */
    public static function get_distance_unit(): string {
        $settings = self::get_settings();
        $unit     = (string) ( $settings['distance_unit'] ?? 'km' );

        return in_array( $unit, array( 'km', 'mi' ), true ) ? $unit : 'km';
    }

    /**
     * Normalize a rule collection.
     *
     * @param mixed $rules Raw rules data.
     * @return array<int, array<string, mixed>>
     */
    public static function normalize_rules( $rules ): array {
        if ( is_string( $rules ) ) {
            $decoded = json_decode( $rules, true );
            $rules   = is_array( $decoded ) ? $decoded : array();
        }

        if ( ! is_array( $rules ) ) {
            return array();
        }

        $normalized = array();

        foreach ( $rules as $rule ) {
            if ( ! is_array( $rule ) ) {
                continue;
            }

            $normalized[] = array(
                'id'                => isset( $rule['id'] ) ? (string) $rule['id'] : '',
                'label'             => isset( $rule['label'] ) ? (string) $rule['label'] : '',
                'min_distance'      => isset( $rule['min_distance'] ) ? (string) $rule['min_distance'] : '0',
                'max_distance'      => array_key_exists( 'max_distance', $rule ) ? (string) $rule['max_distance'] : '',
                'base_cost'         => isset( $rule['base_cost'] ) ? (string) $rule['base_cost'] : '0',
                'cost_per_distance' => isset( $rule['cost_per_distance'] ) ? (string) $rule['cost_per_distance'] : '0',
            );
        }

        return $normalized;
    }

    /**
     * Normalize a collection of origins.
     *
     * @param mixed $origins Raw origins data.
     * @return array<int, array<string, string>>
     */
    public static function normalize_origins( $origins ): array {
        if ( is_string( $origins ) ) {
            $decoded = json_decode( $origins, true );
            $origins = is_array( $decoded ) ? $decoded : array();
        }

        if ( ! is_array( $origins ) ) {
            return array();
        }

        $normalized = array();

        foreach ( $origins as $origin ) {
            if ( ! is_array( $origin ) ) {
                continue;
            }

            $normalized[] = array(
                'id'       => isset( $origin['id'] ) ? (string) $origin['id'] : '',
                'label'    => isset( $origin['label'] ) ? (string) $origin['label'] : '',
                'address'  => isset( $origin['address'] ) ? (string) $origin['address'] : '',
                'postcode' => isset( $origin['postcode'] ) ? (string) $origin['postcode'] : '',
            );
        }

        return $normalized;
    }

    /**
     * Format a numeric value using WooCommerce helpers when available.
     */
    public static function format_decimal( $value ): string {
        $value = is_string( $value ) ? trim( $value ) : (string) $value;

        if ( function_exists( 'wc_format_decimal' ) ) {
            $formatted = wc_format_decimal( $value );

            if ( null !== $formatted ) {
                return (string) $formatted;
            }
        }

        return number_format( (float) $value, 2, '.', '' );
    }

    /**
     * Retrieve WooCommerce currency symbol or fallback to code.
     */
    public static function get_currency_symbol(): string {
        if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
            return get_woocommerce_currency_symbol();
        }

        $currency = get_option( 'woocommerce_currency', 'USD' );

        return is_string( $currency ) ? $currency : 'USD';
    }
}

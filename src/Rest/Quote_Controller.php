<?php
/**
 * REST controller for quote calculations.
 *
 * @package DRS\Rest
 */

declare( strict_types=1 );

namespace DRS\Rest;

use DRS\Settings\Settings;
use WP_Error;
use WP_REST_Request;
use function __;
use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function current_user_can;
use function get_option;
use function hexdec;
use function implode;
use function in_array;
use function is_array;
use function is_scalar;
use function json_decode;
use function md5;
use function number_format_i18n;
use function preg_split;
use function register_rest_route;
use function rest_authorization_required_code;
use function rest_ensure_response;
use function sanitize_text_field;
use function sprintf;
use function strtolower;
use function substr;
use function trim;

/**
 * Handles the /drs/v1/quote endpoint.
 */
class Quote_Controller {
    private string $namespace = 'drs/v1';

    private string $rest_base = 'quote';

    /**
     * Register REST API routes.
     */
    public function register_routes(): void {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'handle_quote' ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                    'args'                => $this->get_endpoint_args(),
                ),
            )
        );
    }

    /**
     * Permission callback to restrict access to store managers.
     *
     * @param WP_REST_Request $request Request instance.
     * @return bool|WP_Error
     */
    public function permissions_check( WP_REST_Request $request ) {
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return true;
        }

        if ( $this->has_destination_details( $request->get_param( 'destination' ) ) ) {
            return true;
        }

        return new WP_Error(
            'drs_rest_forbidden',
            __( 'You do not have permission to access shipping quotes.', 'drs-distance' ),
            array( 'status' => rest_authorization_required_code() )
        );
    }

    /**
     * Process the quote request.
     */
    public function handle_quote( WP_REST_Request $request ) {
        $settings        = Settings::get_settings();
        $rules           = $settings['rules'];
        $handling_fee    = isset( $settings['handling_fee'] ) ? (float) $settings['handling_fee'] : 0.0;
        $default_rate    = isset( $settings['default_rate'] ) ? (float) $settings['default_rate'] : 0.0;
        $distance_unit   = isset( $settings['distance_unit'] ) ? (string) $settings['distance_unit'] : 'km';
        $distance_unit   = in_array( $distance_unit, array( 'km', 'mi' ), true ) ? $distance_unit : 'km';

        $general_options    = $this->get_general_options();
        $distance_precision = $this->normalise_precision( $general_options['distance_precision'] ?? 1 );

        list( $destination_label, $destination_parts ) = $this->normalise_destination( $request->get_param( 'destination' ) );

        $raw_distance = $request->get_param( 'distance' );
        $distance     = null;

        if ( null !== $raw_distance && '' !== $raw_distance ) {
            $distance = $this->to_non_negative_float( $raw_distance );
        } elseif ( ! empty( $destination_parts ) ) {
            $estimated_km = $this->estimate_distance_km( $destination_parts );
            if ( null !== $estimated_km ) {
                $distance = 'mi' === $distance_unit ? $this->convert_distance( $estimated_km, 'mi' ) : $estimated_km;
            }
        }

        if ( null === $distance ) {
            return new WP_Error(
                'drs_rest_missing_distance',
                __( 'Distance is required to calculate a quote.', 'drs-distance' ),
                array( 'status' => 400 )
            );
        }

        $weight   = $this->to_non_negative_float( $request->get_param( 'weight' ) );
        $items    = $this->to_non_negative_int( $request->get_param( 'items' ) );
        $subtotal = $this->to_non_negative_float( $request->get_param( 'subtotal' ) );
        $origin   = sanitize_text_field( (string) $request->get_param( 'origin' ) );

        $matched_rule = null;
        $rule_cost    = $default_rate;

        foreach ( $rules as $rule ) {
            if ( ! is_array( $rule ) ) {
                continue;
            }

            $min_distance = isset( $rule['min_distance'] ) ? (float) $rule['min_distance'] : 0.0;
            $max_raw      = $rule['max_distance'] ?? '';
            $max_distance = '' === $max_raw || null === $max_raw ? null : (float) $max_raw;

            if ( $distance < $min_distance ) {
                continue;
            }

            if ( null !== $max_distance && $distance > $max_distance ) {
                continue;
            }

            $matched_rule = $rule;
            $base_cost    = isset( $rule['base_cost'] ) ? (float) $rule['base_cost'] : 0.0;
            $per_distance = isset( $rule['cost_per_distance'] ) ? (float) $rule['cost_per_distance'] : 0.0;

            $rule_cost = $base_cost + ( $per_distance * $distance );
            break;
        }

        $total         = round( $rule_cost + $handling_fee, 2 );
        $distance_text = $this->format_distance_text( $distance, $distance_unit, $distance_precision );

        $response = array(
            'origin'          => $origin,
            'destination'     => $destination_label,
            'distance'        => $distance,
            'distance_unit'   => $distance_unit,
            'distance_text'   => $distance_text,
            'weight'          => $weight,
            'items'           => $items,
            'subtotal'        => $subtotal,
            'handling_fee'    => round( $handling_fee, 2 ),
            'default_rate'    => round( $default_rate, 2 ),
            'total'           => $total,
            'currency_symbol' => Settings::get_currency_symbol(),
            'used_fallback'   => null === $matched_rule,
        );

        if ( null !== $matched_rule ) {
            $max_raw = $matched_rule['max_distance'] ?? '';

            $response['rule'] = array(
                'id'                => isset( $matched_rule['id'] ) ? (string) $matched_rule['id'] : '',
                'label'             => isset( $matched_rule['label'] ) ? (string) $matched_rule['label'] : '',
                'min_distance'      => isset( $matched_rule['min_distance'] ) ? (float) $matched_rule['min_distance'] : 0.0,
                'max_distance'      => '' === $max_raw || null === $max_raw ? null : (float) $max_raw,
                'base_cost'         => isset( $matched_rule['base_cost'] ) ? (float) $matched_rule['base_cost'] : 0.0,
                'cost_per_distance' => isset( $matched_rule['cost_per_distance'] ) ? (float) $matched_rule['cost_per_distance'] : 0.0,
                'calculated_cost'   => round( $rule_cost, 2 ),
            );
        } else {
            $response['rule'] = null;
        }

        $response['breakdown'] = array(
            'rule_cost'    => round( $rule_cost, 2 ),
            'handling_fee' => round( $handling_fee, 2 ),
            'total'        => $total,
        );

        return rest_ensure_response( $response );
    }

    /**
     * Endpoint argument schema.
     *
     * @return array<string, array<string, mixed>>
     */
    private function get_endpoint_args(): array {
        return array(
            'origin'      => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'destination' => array(
                'type'     => array( 'string', 'object' ),
                'required' => false,
            ),
            'distance'    => array(
                'type'     => 'number',
                'required' => false,
            ),
            'weight'      => array(
                'type'     => 'number',
                'required' => false,
            ),
            'items'       => array(
                'type'     => 'integer',
                'required' => false,
            ),
            'subtotal'    => array(
                'type'     => 'number',
                'required' => false,
            ),
        );
    }

    /**
     * Determine if the destination parameter includes useful data.
     *
     * @param mixed $value Raw destination parameter.
     */
    private function has_destination_details( $value ): bool {
        list( , $parts ) = $this->normalise_destination( $value );

        return ! empty( $parts );
    }

    /**
     * Normalise destination input into a label and seed components.
     *
     * @param mixed $value Raw destination parameter.
     * @return array{0:string,1:array<int,string>}
     */
    private function normalise_destination( $value ): array {
        if ( is_array( $value ) ) {
            return $this->normalise_destination_array( $value );
        }

        if ( is_string( $value ) && '' !== $value ) {
            $decoded = json_decode( $value, true );
            if ( is_array( $decoded ) ) {
                return $this->normalise_destination_array( $decoded );
            }

            $label = sanitize_text_field( $value );
            if ( '' === $label ) {
                return array( '', array() );
            }

            $parts = $this->split_destination_string( $label );

            return array( $label, $parts );
        }

        return array( '', array() );
    }

    /**
     * Flatten a destination array into label and seed components.
     *
     * @param array<mixed> $values Raw destination pieces.
     * @return array{0:string,1:array<int,string>}
     */
    private function normalise_destination_array( array $values ): array {
        $label_parts = array();
        $seed_parts  = array();

        foreach ( $values as $value ) {
            if ( is_array( $value ) ) {
                list( $nested_label, $nested_seed ) = $this->normalise_destination_array( $value );
                if ( '' !== $nested_label ) {
                    $label_parts[] = $nested_label;
                }
                if ( ! empty( $nested_seed ) ) {
                    $seed_parts = array_merge( $seed_parts, $nested_seed );
                }
                continue;
            }

            if ( ! is_scalar( $value ) ) {
                continue;
            }

            $sanitised = sanitize_text_field( (string) $value );
            if ( '' === $sanitised ) {
                continue;
            }

            $label_parts[] = $sanitised;
            $seed_parts[]  = strtolower( trim( $sanitised ) );
        }

        $label = implode( ', ', array_unique( $label_parts ) );
        $seed_parts = array_values(
            array_filter(
                array_unique( $seed_parts ),
                static function ( string $part ): bool {
                    return '' !== $part;
                }
            )
        );

        return array( $label, $seed_parts );
    }

    /**
     * Split a string-based destination into unique components.
     *
     * @return array<int,string>
     */
    private function split_destination_string( string $value ): array {
        $segments = preg_split( '/[|,]+/', $value );
        if ( false === $segments ) {
            $segments = array( $value );
        }

        $parts = array();

        foreach ( $segments as $segment ) {
            $sanitised = sanitize_text_field( $segment );
            $sanitised = strtolower( trim( $sanitised ) );
            if ( '' !== $sanitised ) {
                $parts[] = $sanitised;
            }
        }

        return array_values( array_unique( $parts ) );
    }

    /**
     * Build a deterministic seed string for hashing.
     *
     * @param array<int,string> $parts Destination parts.
     */
    private function build_seed( array $parts ): string {
        if ( empty( $parts ) ) {
            return '';
        }

        return implode( '|', $parts );
    }

    /**
     * Estimate a pseudo distance in kilometres based on destination parts.
     *
     * @param array<int,string> $parts Destination parts.
     */
    private function estimate_distance_km( array $parts ): ?float {
        $seed = $this->build_seed( $parts );
        if ( '' === $seed ) {
            return null;
        }

        $hash = substr( md5( $seed ), 0, 8 );
        if ( '' === $hash ) {
            return null;
        }

        $decimal  = hexdec( $hash );
        $base     = $decimal % 4000; // 0 - 3999.
        $distance = 5 + ( $base / 100 );

        return (float) $distance;
    }

    /**
     * Convert kilometres to the requested unit.
     */
    private function convert_distance( float $distance_km, string $unit ): float {
        if ( 'mi' === $unit ) {
            return $distance_km * 0.621371;
        }

        return $distance_km;
    }

    /**
     * Normalise a precision value between 0 and 3.
     *
     * @param mixed $value Raw value.
     */
    private function normalise_precision( $value ): int {
        $precision = is_scalar( $value ) ? (int) $value : 1;

        if ( $precision < 0 ) {
            $precision = 0;
        }

        if ( $precision > 3 ) {
            $precision = 3;
        }

        return $precision;
    }

    /**
     * Produce a translatable distance summary string.
     */
    private function format_distance_text( float $distance, string $unit, int $precision ): string {
        $unit_label = 'mi' === $unit ? __( 'mi', 'drs-distance' ) : __( 'km', 'drs-distance' );

        return sprintf( '%s %s', number_format_i18n( $distance, $precision ), $unit_label );
    }

    /**
     * Retrieve general plugin options.
     *
     * @return array<string, mixed>
     */
    private function get_general_options(): array {
        $raw = get_option( 'drs_general', array() );

        return is_array( $raw ) ? $raw : array();
    }

    /**
     * Cast a value to a non-negative float.
     *
     * @param mixed $value Raw value.
     */
    private function to_non_negative_float( $value ): float {
        if ( null === $value || '' === $value ) {
            return 0.0;
        }

        if ( is_string( $value ) ) {
            $value = str_replace( ',', '.', $value );
        }

        if ( ! is_numeric( $value ) ) {
            return 0.0;
        }

        return max( 0.0, (float) $value );
    }

    /**
     * Cast a value to a non-negative integer.
     *
     * @param mixed $value Raw value.
     */
    private function to_non_negative_int( $value ): int {
        if ( null === $value || '' === $value ) {
            return 0;
        }

        if ( is_string( $value ) && ! is_numeric( $value ) ) {
            return 0;
        }

        return max( 0, (int) $value );
    }
}

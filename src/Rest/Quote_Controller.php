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
use function register_rest_route;
use function current_user_can;
use function rest_authorization_required_code;
use function rest_ensure_response;
use function sanitize_text_field;

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
        $raw_distance = $request->get_param( 'distance' );

        if ( null === $raw_distance || '' === $raw_distance ) {
            return new WP_Error(
                'drs_rest_missing_distance',
                __( 'Distance is required to calculate a quote.', 'drs-distance' ),
                array( 'status' => 400 )
            );
        }

        $distance    = $this->to_non_negative_float( $raw_distance );
        $weight      = $this->to_non_negative_float( $request->get_param( 'weight' ) );
        $items       = $this->to_non_negative_int( $request->get_param( 'items' ) );
        $subtotal    = $this->to_non_negative_float( $request->get_param( 'subtotal' ) );
        $origin      = sanitize_text_field( (string) $request->get_param( 'origin' ) );
        $destination = sanitize_text_field( (string) $request->get_param( 'destination' ) );

        $settings     = Settings::get_settings();
        $rules        = $settings['rules'];
        $handling_fee = isset( $settings['handling_fee'] ) ? (float) $settings['handling_fee'] : 0.0;
        $default_rate = isset( $settings['default_rate'] ) ? (float) $settings['default_rate'] : 0.0;

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

        $total = round( $rule_cost + $handling_fee, 2 );

        $response = array(
            'origin'          => $origin,
            'destination'     => $destination,
            'distance'        => $distance,
            'distance_unit'   => $settings['distance_unit'] ?? 'km',
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
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'distance'    => array(
                'type'     => 'number',
                'required' => true,
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

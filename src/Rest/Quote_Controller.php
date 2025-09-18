<?php
/**
 * REST controller for quote calculations.
 *
 * @package DRS\Rest
 */

declare( strict_types=1 );

namespace DRS\Rest;

use DRS\Settings\Settings;
use DRS\Shipping\Rate_Calculator;
use WP_Error;
use WP_REST_Request;
use function current_user_can;
use function register_rest_route;
use function rest_authorization_required_code;
use function rest_ensure_response;
use function sanitize_text_field;

if ( ! class_exists( '\\DRS\\Shipping\\Rate_Calculator', false ) && is_readable( dirname( __DIR__ ) . '/Shipping/Rate_Calculator.php' ) ) {
    require_once dirname( __DIR__ ) . '/Shipping/Rate_Calculator.php';
}

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

        $settings   = Settings::get_settings();
        $calculator = new Rate_Calculator();
        $quote      = $calculator->calculate( $settings, $distance, $weight, $items, $subtotal );

        $response = array(
            'origin'          => $origin,
            'destination'     => $destination,
            'distance'        => $distance,
            'distance_unit'   => $settings['distance_unit'] ?? 'km',
            'weight'          => $weight,
            'items'           => $items,
            'subtotal'        => $subtotal,
            'handling_fee'    => $quote['handling_fee'],
            'default_rate'    => $quote['default_rate'],
            'total'           => $quote['total'],
            'currency_symbol' => Settings::get_currency_symbol(),
            'used_fallback'   => $quote['used_fallback'],
            'rule'            => $quote['rule'],
            'breakdown'       => array(
                'rule_cost'    => $quote['rule_cost'],
                'handling_fee' => $quote['handling_fee'],
                'total'        => $quote['total'],
            ),
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

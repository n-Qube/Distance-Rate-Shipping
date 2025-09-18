<?php
/**
 * REST controller for quote calculations.
 *
 * @package DRS\Rest
 */

declare( strict_types=1 );

namespace DRS\Rest;

use DRS\Settings\Settings;
use DRS\Support\Logger;
use WP_Error;
use WP_REST_Request;
use function apply_filters;
use function __;
use function register_rest_route;
use function current_user_can;
use function rest_authorization_required_code;
use function rest_ensure_response;
use function sanitize_text_field;
use function get_transient;
use function set_transient;
use function wp_json_encode;

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
        $provider     = $this->determine_provider( $settings );

        $cache_hit = false;
        $cache_key = null;

        if ( $this->is_cache_enabled( $settings ) ) {
            $cache_key = $this->build_cache_key( $settings, $distance, $weight, $items, $subtotal, $origin, $destination );
            $cached    = get_transient( $cache_key );

            if ( is_array( $cached ) && isset( $cached['response'] ) ) {
                $cache_hit = true;

                Logger::debug(
                    __( 'Distance Rate quote served from cache.', 'drs-distance' ),
                    array(
                        'provider'        => $provider,
                        'cache_hit'       => true,
                        'selected_rule'   => $cached['selected_rule'] ?? null,
                        'cost_components' => $cached['cost_components'] ?? array(),
                    ),
                    $settings
                );

                return rest_ensure_response( $cached['response'] );
            }
        }

        $selected_rule = null;
        $rule_cost     = $default_rate;

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

            $base_cost    = isset( $rule['base_cost'] ) ? (float) $rule['base_cost'] : 0.0;
            $per_distance = isset( $rule['cost_per_distance'] ) ? (float) $rule['cost_per_distance'] : 0.0;

            $rule_cost = $base_cost + ( $per_distance * $distance );
            $selected_rule = array(
                'id'                => isset( $rule['id'] ) ? (string) $rule['id'] : '',
                'label'             => isset( $rule['label'] ) ? (string) $rule['label'] : '',
                'min_distance'      => $min_distance,
                'max_distance'      => $max_distance,
                'base_cost'         => $base_cost,
                'cost_per_distance' => $per_distance,
                'calculated_cost'   => round( $rule_cost, 2 ),
            );
            break;
        }

        $total = round( $rule_cost + $handling_fee, 2 );

        $cost_components = array(
            'rule_cost'    => round( $rule_cost, 2 ),
            'handling_fee' => round( $handling_fee, 2 ),
            'total'        => $total,
        );

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
            'used_fallback'   => null === $selected_rule,
        );

        $response['rule']       = $selected_rule;
        $response['breakdown']  = $cost_components;

        if ( null !== $cache_key ) {
            $ttl = $this->get_cache_ttl( $settings );

            if ( $ttl > 0 ) {
                set_transient(
                    $cache_key,
                    array(
                        'response'        => $response,
                        'selected_rule'   => $selected_rule,
                        'cost_components' => $cost_components,
                    ),
                    $ttl
                );
            }
        }

        Logger::debug(
            __( 'Distance Rate quote calculated.', 'drs-distance' ),
            array(
                'provider'        => $provider,
                'cache_hit'       => $cache_hit,
                'selected_rule'   => $selected_rule,
                'cost_components' => $cost_components,
            ),
            $settings
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

    /**
     * Determine the provider label used for logging.
     *
     * @param array<string, mixed> $settings Stored plugin settings.
     */
    private function determine_provider( array $settings ): string {
        $provider = '';

        if ( isset( $settings['strategy'] ) && is_string( $settings['strategy'] ) ) {
            $provider = $settings['strategy'];
        } elseif ( isset( $settings['api_key'] ) && '' !== (string) $settings['api_key'] ) {
            $provider = 'api';
        }

        if ( '' === $provider ) {
            $provider = 'straight_line';
        }

        if ( function_exists( 'apply_filters' ) ) {
            $provider = (string) apply_filters( 'drs_quote_provider', $provider, $settings );
        }

        return $provider;
    }

    /**
     * Check if transient caching is enabled.
     *
     * @param array<string, mixed> $settings Stored plugin settings.
     */
    private function is_cache_enabled( array $settings ): bool {
        $enabled = true;

        if ( function_exists( 'apply_filters' ) ) {
            $enabled = (bool) apply_filters( 'drs_quote_cache_enabled', $enabled, $settings );
        }

        return $enabled;
    }

    /**
     * Resolve cache lifetime in seconds.
     *
     * @param array<string, mixed> $settings Stored plugin settings.
     */
    private function get_cache_ttl( array $settings ): int {
        $default = defined( 'MINUTE_IN_SECONDS' ) ? 5 * (int) MINUTE_IN_SECONDS : 300;

        if ( function_exists( 'apply_filters' ) ) {
            $filtered = apply_filters( 'drs_quote_cache_ttl', $default, $settings );
            $ttl      = (int) $filtered;

            return $ttl > 0 ? $ttl : $default;
        }

        return $default;
    }

    /**
     * Generate a cache key for the current request context.
     *
     * @param array<string, mixed> $settings Stored plugin settings.
     */
    private function build_cache_key(
        array $settings,
        float $distance,
        float $weight,
        int $items,
        float $subtotal,
        string $origin,
        string $destination
    ): string {
        $payload = array(
            'distance'      => $distance,
            'weight'        => $weight,
            'items'         => $items,
            'subtotal'      => $subtotal,
            'origin'        => $origin,
            'destination'   => $destination,
            'handling_fee'  => $settings['handling_fee'] ?? '0.00',
            'default_rate'  => $settings['default_rate'] ?? '0.00',
            'rules_version' => md5( (string) wp_json_encode( $settings['rules'] ?? array() ) ),
        );

        $encoded = wp_json_encode( $payload );

        if ( ! is_string( $encoded ) ) {
            $encoded = serialize( $payload );
        }

        return 'drs_quote_' . md5( $encoded );
    }
}

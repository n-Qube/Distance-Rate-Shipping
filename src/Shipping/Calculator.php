<?php
/**
 * Shared shipping calculator for WooCommerce packages and REST requests.
 *
 * @package DRS\Shipping
 */

declare( strict_types=1 );

namespace DRS\Shipping;

use DRS\Settings\Settings;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function str_replace;

/**
 * Encapsulates the rule evaluation logic used by the shipping method and REST API.
 */
class Calculator {
    /**
     * Normalise raw inputs into the expected calculator context.
     *
     * @param array<string, mixed> $args Raw calculator arguments.
     * @return array{
     *     distance: float,
     *     weight: float,
     *     items: int,
     *     subtotal: float,
     *     origin: string,
     *     destination: string
     * }
     */
    public static function normalize_inputs( array $args ): array {
        return array(
            'distance'    => self::to_non_negative_float( $args['distance'] ?? 0.0 ),
            'weight'      => self::to_non_negative_float( $args['weight'] ?? 0.0 ),
            'items'       => self::to_non_negative_int( $args['items'] ?? 0 ),
            'subtotal'    => self::to_non_negative_float( $args['subtotal'] ?? 0.0 ),
            'origin'      => self::to_string( $args['origin'] ?? '' ),
            'destination' => self::to_string( $args['destination'] ?? '' ),
        );
    }

    /**
     * Evaluate stored rules and return the computed totals.
     *
     * @param array<string, mixed>      $args      Calculator arguments.
     * @param array<string, mixed>|null $settings  Optional preloaded settings.
     * @return array<string, mixed>                Calculator result payload.
     */
    public static function calculate( array $args, ?array $settings = null ): array {
        $inputs   = self::normalize_inputs( $args );
        $settings = null === $settings ? Settings::get_settings() : $settings;

        $rules = is_array( $settings['rules'] ?? null ) ? $settings['rules'] : array();

        $handling_fee = isset( $settings['handling_fee'] ) ? (float) $settings['handling_fee'] : 0.0;
        $default_rate = isset( $settings['default_rate'] ) ? (float) $settings['default_rate'] : 0.0;

        $selected_rule = null;
        $rule_cost     = $default_rate;

        foreach ( $rules as $rule ) {
            if ( ! is_array( $rule ) ) {
                continue;
            }

            $min_distance = isset( $rule['min_distance'] ) ? (float) $rule['min_distance'] : 0.0;
            $max_raw      = $rule['max_distance'] ?? '';
            $max_distance = '' === $max_raw || null === $max_raw ? null : (float) $max_raw;

            if ( $inputs['distance'] < $min_distance ) {
                continue;
            }

            if ( null !== $max_distance && $inputs['distance'] > $max_distance ) {
                continue;
            }

            $base_cost    = isset( $rule['base_cost'] ) ? (float) $rule['base_cost'] : 0.0;
            $per_distance = isset( $rule['cost_per_distance'] ) ? (float) $rule['cost_per_distance'] : 0.0;

            $rule_cost = $base_cost + ( $per_distance * $inputs['distance'] );
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

        $unit          = isset( $settings['distance_unit'] ) ? (string) $settings['distance_unit'] : 'km';
        $distance_unit = in_array( $unit, array( 'km', 'mi' ), true ) ? $unit : 'km';

        $rule_cost_rounded     = round( $rule_cost, 2 );
        $handling_fee_rounded  = round( $handling_fee, 2 );
        $breakdown             = array(
            'rule_cost'    => $rule_cost_rounded,
            'handling_fee' => $handling_fee_rounded,
            'total'        => $total,
        );

        return array_merge(
            $inputs,
            array(
                'distance_unit' => $distance_unit,
                'handling_fee'  => $handling_fee_rounded,
                'default_rate'  => round( $default_rate, 2 ),
                'rule_cost'     => $rule_cost_rounded,
                'rule'          => $selected_rule,
                'breakdown'     => $breakdown,
                'total'         => $total,
                'used_fallback' => null === $selected_rule,
            )
        );
    }

    /**
     * Cast a value to a non-negative float.
     *
     * @param mixed $value Raw numeric value.
     */
    private static function to_non_negative_float( $value ): float {
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
     * @param mixed $value Raw numeric value.
     */
    private static function to_non_negative_int( $value ): int {
        if ( null === $value || '' === $value ) {
            return 0;
        }

        if ( is_string( $value ) && ! is_numeric( $value ) ) {
            return 0;
        }

        return max( 0, (int) $value );
    }

    /**
     * Convert a value into a safe string representation.
     *
     * @param mixed $value Raw value.
     */
    private static function to_string( $value ): string {
        if ( is_string( $value ) ) {
            return $value;
        }

        if ( is_numeric( $value ) ) {
            return (string) $value;
        }

        return '';
    }
}

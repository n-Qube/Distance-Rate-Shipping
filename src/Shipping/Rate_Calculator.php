<?php
/**
 * Shared rate calculator used by the REST endpoint and the shipping method.
 *
 * @package DRS\Shipping
 */

declare( strict_types=1 );

namespace DRS\Shipping;

/**
 * Evaluates the configured rule set to determine shipping totals.
 */
class Rate_Calculator {
    /**
     * Calculate the shipping totals using the stored settings.
     *
     * @param array<string, mixed> $settings Plugin settings array.
     * @param float                $distance Distance in the configured unit.
     * @param float                $weight   Total package weight.
     * @param int                  $items    Number of items in the package.
     * @param float                $subtotal Package subtotal.
     *
     * @return array<string, mixed>
     */
    public function calculate( array $settings, float $distance, float $weight = 0.0, int $items = 0, float $subtotal = 0.0 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        $handling_fee = isset( $settings['handling_fee'] ) ? (float) $settings['handling_fee'] : 0.0;
        $default_rate = isset( $settings['default_rate'] ) ? (float) $settings['default_rate'] : 0.0;
        $rules        = isset( $settings['rules'] ) && is_array( $settings['rules'] ) ? $settings['rules'] : array();

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

        $total = $rule_cost + $handling_fee;

        return array(
            'total'         => round( max( 0.0, $total ), 2 ),
            'rule_cost'     => round( max( 0.0, $rule_cost ), 2 ),
            'handling_fee'  => round( max( 0.0, $handling_fee ), 2 ),
            'default_rate'  => round( max( 0.0, $default_rate ), 2 ),
            'used_fallback' => null === $matched_rule,
            'rule'          => $this->format_matched_rule( $matched_rule, $distance, $rule_cost ),
        );
    }

    /**
     * Normalise the matched rule data for responses.
     *
     * @param array<string, mixed>|null $rule      Rule data.
     * @param float                     $distance  Evaluated distance.
     * @param float                     $rule_cost Raw rule cost prior to fees.
     */
    private function format_matched_rule( ?array $rule, float $distance, float $rule_cost ): ?array {
        if ( null === $rule ) {
            return null;
        }

        $max_raw = $rule['max_distance'] ?? '';

        return array(
            'id'                => isset( $rule['id'] ) ? (string) $rule['id'] : '',
            'label'             => isset( $rule['label'] ) ? (string) $rule['label'] : '',
            'min_distance'      => isset( $rule['min_distance'] ) ? (float) $rule['min_distance'] : 0.0,
            'max_distance'      => '' === $max_raw || null === $max_raw ? null : (float) $max_raw,
            'base_cost'         => isset( $rule['base_cost'] ) ? (float) $rule['base_cost'] : 0.0,
            'cost_per_distance' => isset( $rule['cost_per_distance'] ) ? (float) $rule['cost_per_distance'] : 0.0,
            'calculated_cost'   => round( max( 0.0, $rule_cost ), 2 ),
            'evaluated_distance'=> round( max( 0.0, $distance ), 2 ),
        );
    }
}

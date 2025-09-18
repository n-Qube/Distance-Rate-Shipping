<?php

declare(strict_types=1);

namespace DRS\DistanceRateShipping\Blocks;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function __;
use function function_exists;
use function get_option;
use function is_array;
use function is_scalar;
use function hexdec;
use function implode;
use function md5;
use function number_format_i18n;
use function register_rest_route;
use function sprintf;
use function substr;
use function strtolower;
use function trim;

class RestLoader
{
    private const METHOD_ID = 'drs_distance_rate';

    public function register(): void
    {
        if (! function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(
            'drs/v1',
            '/quote',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_quote'],
                'permission_callback' => static function (): bool {
                    return true;
                },
                'args' => [
                    'destination' => [
                        'type' => 'object',
                        'required' => false,
                    ],
                ],
            ]
        );
    }

    /**
     * @return WP_REST_Response
     */
    public function handle_quote(WP_REST_Request $request)
    {
        $destination = $request->get_param('destination');
        if (! is_array($destination)) {
            $destination = [];
        }

        $options = $this->get_general_options();
        $unit = $this->normalise_unit($options['distance_unit'] ?? 'km');
        $precision = $this->normalise_precision($options['distance_precision'] ?? 1);

        $distanceKm = $this->estimate_distance_km($destination);
        $distance = null === $distanceKm ? null : $this->convert_distance($distanceKm, $unit);

        $distanceText = '';
        if (null !== $distance) {
            $unitLabel = 'mi' === $unit ? __('mi', 'drs-distance') : __('km', 'drs-distance');
            $distanceText = sprintf('%s %s', number_format_i18n($distance, $precision), $unitLabel);
        }

        $payload = [
            'method_id' => self::METHOD_ID,
            'distance' => null === $distance ? null : (float) $distance,
            'distance_text' => $distanceText,
            'unit' => $unit,
        ];

        return new WP_REST_Response($payload);
    }

    /**
     * @param array<string, mixed> $destination
     */
    private function estimate_distance_km(array $destination): ?float
    {
        $seed = $this->build_seed($destination);
        if ('' === $seed) {
            return null;
        }

        $hash = substr(md5($seed), 0, 8);
        if ('' === $hash) {
            return null;
        }

        $decimal = hexdec($hash);
        $base = $decimal % 4000; // 0 - 3999.
        $distance = 5 + ($base / 100); // 5.00 - 44.99 km.

        return (float) $distance;
    }

    /**
     * @param array<string, mixed> $destination
     */
    private function build_seed(array $destination): string
    {
        $parts = [];
        foreach (['postcode', 'postal_code', 'zip', 'city', 'state', 'country'] as $key) {
            if (isset($destination[$key]) && is_scalar($destination[$key])) {
                $value = trim((string) $destination[$key]);
                if ('' !== $value) {
                    $parts[] = strtolower($value);
                }
            }
        }

        return implode('|', $parts);
    }

    private function convert_distance(float $distanceKm, string $unit): float
    {
        if ('mi' === $unit) {
            return $distanceKm * 0.621371;
        }

        return $distanceKm;
    }

    /**
     * @param mixed $value
     */
    private function normalise_unit($value): string
    {
        $unit = is_scalar($value) ? strtolower((string) $value) : 'km';
        return 'mi' === $unit ? 'mi' : 'km';
    }

    /**
     * @param mixed $value
     */
    private function normalise_precision($value): int
    {
        $precision = is_scalar($value) ? (int) $value : 1;
        if ($precision < 0) {
            $precision = 0;
        }
        if ($precision > 3) {
            $precision = 3;
        }

        return $precision;
    }

    /**
     * @return array<string, mixed>
     */
    private function get_general_options(): array
    {
        if (! function_exists('get_option')) {
            return [];
        }

        $raw = get_option('drs_general', []);

        return is_array($raw) ? $raw : [];
    }
}

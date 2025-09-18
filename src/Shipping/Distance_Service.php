<?php
/**
 * Handles distance calculations with provider fallbacks and caching.
 *
 * @package DRS\Shipping
 */

declare( strict_types=1 );

namespace DRS\Shipping;

use WP_Error;
use function apply_filters;
use function atan2;
use function cos;
use function deg2rad;
use function get_transient;
use function is_array;
use function is_numeric;
use function is_wp_error;
use function set_transient;
use function sin;
use function sqrt;
use function wp_json_encode;

/**
 * Provides cached geocoding and distance lookups.
 */
class Distance_Service {
    private const CACHE_PREFIX_GEO      = 'drs_geo_';
    private const CACHE_PREFIX_DISTANCE = 'drs_distance_';

    /**
     * Raw plugin settings.
     *
     * @var array<string, mixed>
     */
    private array $settings;

    private bool $cache_enabled;

    private int $cache_ttl;

    private string $strategy;

    private string $unit;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $settings Plugin settings.
     */
    public function __construct( array $settings ) {
        $this->settings = $settings;

        $unit       = isset( $settings['distance_unit'] ) ? (string) $settings['distance_unit'] : 'km';
        $strategy   = isset( $settings['calculation_strategy'] ) ? (string) $settings['calculation_strategy'] : 'straight_line';
        $cache_flag = $settings['cache_enabled'] ?? 'yes';
        $ttl_raw    = isset( $settings['cache_ttl'] ) ? (int) $settings['cache_ttl'] : 30;

        $this->unit     = in_array( $unit, array( 'km', 'mi' ), true ) ? $unit : 'km';
        $this->strategy = 'road_distance' === $strategy ? 'road_distance' : 'straight_line';
        $this->cache_enabled = in_array( $cache_flag, array( true, 'yes', 'true', 1 ), true );

        $ttl_raw = max( 0, $ttl_raw );
        $seconds = $ttl_raw * ( defined( 'MINUTE_IN_SECONDS' ) ? (int) MINUTE_IN_SECONDS : 60 );
        $this->cache_ttl = $seconds > 0 ? $seconds : 0;
    }

    /**
     * Resolve the distance between the provided locations.
     *
     * @param array<string, mixed> $origin      Origin data (address, postcode, etc.).
     * @param array<string, mixed> $destination Destination data.
     *
     * @return array<string, mixed>|null Returns the distance breakdown or null when unavailable.
     */
    public function get_distance( array $origin, array $destination ): ?array {
        $origin_coords      = $this->resolve_coordinates( $origin );
        $destination_coords = $this->resolve_coordinates( $destination );

        if ( null === $origin_coords || null === $destination_coords ) {
            return null;
        }

        $distance_km  = null;
        $from_cache   = false;
        $was_fallback = false;
        $strategy_used = 'straight_line';

        if ( 'road_distance' === $this->strategy ) {
            $cached = $this->get_cached_distance( $origin, $destination );

            if ( null !== $cached ) {
                $distance_km   = (float) $cached;
                $from_cache    = true;
                $strategy_used = 'road_distance';
            } else {
                $provider_result = apply_filters( 'drs_distance_provider_get_distance', null, $origin_coords, $destination_coords, $this->settings );
                $normalized      = $this->normalize_provider_distance( $provider_result );

                if ( null !== $normalized ) {
                    $distance_km   = $this->to_kilometers( $normalized['value'], $normalized['unit'] );
                    $strategy_used = 'road_distance';
                    $this->set_cached_distance( $origin, $destination, $distance_km );
                } else {
                    $was_fallback = true;
                }
            }
        }

        if ( null === $distance_km ) {
            $distance_km = $this->calculate_straight_line( $origin_coords, $destination_coords );

            if ( null === $distance_km ) {
                return null;
            }

            $strategy_used = 'straight_line';
        }

        return array(
            'value'        => $this->from_kilometers( $distance_km ),
            'unit'         => $this->unit,
            'strategy'     => $strategy_used,
            'was_fallback' => $was_fallback && 'road_distance' === $this->strategy,
            'from_cache'   => $from_cache,
            'coordinates'  => array(
                'origin'      => $origin_coords,
                'destination' => $destination_coords,
            ),
        );
    }

    /**
     * Attempt to resolve cached coordinates or query the provider.
     *
     * @param array<string, mixed> $location Location metadata.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function resolve_coordinates( array $location ): ?array {
        if ( isset( $location['lat'], $location['lng'] ) && is_numeric( $location['lat'] ) && is_numeric( $location['lng'] ) ) {
            return array( 'lat' => (float) $location['lat'], 'lng' => (float) $location['lng'] );
        }

        if ( isset( $location['latitude'], $location['longitude'] ) && is_numeric( $location['latitude'] ) && is_numeric( $location['longitude'] ) ) {
            return array(
                'lat' => (float) $location['latitude'],
                'lng' => (float) $location['longitude'],
            );
        }

        $cache_key = $this->build_geo_cache_key( $location );

        if ( $this->cache_enabled && $this->cache_ttl > 0 ) {
            $cached = get_transient( $cache_key );

            if ( is_array( $cached ) && isset( $cached['lat'], $cached['lng'] ) ) {
                return array( 'lat' => (float) $cached['lat'], 'lng' => (float) $cached['lng'] );
            }
        }

        $geocode_result = apply_filters( 'drs_geocode_location', null, $location, $this->settings );

        if ( is_wp_error( $geocode_result ) ) {
            return null;
        }

        if ( is_array( $geocode_result ) ) {
            $lat = null;
            $lng = null;

            if ( isset( $geocode_result['lat'], $geocode_result['lng'] ) ) {
                $lat = $geocode_result['lat'];
                $lng = $geocode_result['lng'];
            } elseif ( isset( $geocode_result['latitude'], $geocode_result['longitude'] ) ) {
                $lat = $geocode_result['latitude'];
                $lng = $geocode_result['longitude'];
            }

            if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
                $coordinates = array( 'lat' => (float) $lat, 'lng' => (float) $lng );

                if ( $this->cache_enabled && $this->cache_ttl > 0 ) {
                    set_transient( $cache_key, $coordinates, $this->cache_ttl );
                }

                return $coordinates;
            }
        }

        return null;
    }

    /**
     * Calculate a straight-line distance using the Haversine formula.
     *
     * @param array{lat: float, lng: float} $origin
     * @param array{lat: float, lng: float} $destination
     */
    private function calculate_straight_line( array $origin, array $destination ): ?float {
        $lat1 = deg2rad( $origin['lat'] );
        $lon1 = deg2rad( $origin['lng'] );
        $lat2 = deg2rad( $destination['lat'] );
        $lon2 = deg2rad( $destination['lng'] );

        $delta_lat = $lat2 - $lat1;
        $delta_lon = $lon2 - $lon1;

        $a = sin( $delta_lat / 2 ) ** 2 + cos( $lat1 ) * cos( $lat2 ) * sin( $delta_lon / 2 ) ** 2;
        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        return 6371.0 * $c;
    }

    /**
     * Retrieve a previously cached provider distance.
     *
     * @param array<string, mixed> $origin
     * @param array<string, mixed> $destination
     */
    private function get_cached_distance( array $origin, array $destination ): ?float {
        if ( ! $this->cache_enabled || $this->cache_ttl <= 0 ) {
            return null;
        }

        $cache_key = $this->build_distance_cache_key( $origin, $destination );
        $cached    = get_transient( $cache_key );

        if ( is_numeric( $cached ) ) {
            return (float) $cached;
        }

        return null;
    }

    /**
     * Store the computed provider distance in the cache.
     *
     * @param array<string, mixed> $origin
     * @param array<string, mixed> $destination
     */
    private function set_cached_distance( array $origin, array $destination, float $distance_km ): void {
        if ( ! $this->cache_enabled || $this->cache_ttl <= 0 ) {
            return;
        }

        $cache_key = $this->build_distance_cache_key( $origin, $destination );
        set_transient( $cache_key, $distance_km, $this->cache_ttl );
    }

    /**
     * Build a cache key for the provided location data.
     *
     * @param array<string, mixed> $location
     */
    private function build_geo_cache_key( array $location ): string {
        $signature = $this->build_location_signature( $location );

        return self::CACHE_PREFIX_GEO . md5( $signature );
    }

    /**
     * Build a cache key for distance requests.
     *
     * @param array<string, mixed> $origin
     * @param array<string, mixed> $destination
     */
    private function build_distance_cache_key( array $origin, array $destination ): string {
        $origin_signature      = $this->build_location_signature( $origin );
        $destination_signature = $this->build_location_signature( $destination );

        return self::CACHE_PREFIX_DISTANCE . md5( $origin_signature . '|' . $destination_signature );
    }

    /**
     * Normalise a location array for hashing.
     *
     * @param array<string, mixed> $location
     */
    private function build_location_signature( array $location ): string {
        $allowed = array( 'address', 'address_1', 'address_2', 'postcode', 'zip', 'city', 'state', 'country' );
        $normalised = array();

        foreach ( $allowed as $key ) {
            if ( isset( $location[ $key ] ) ) {
                $normalised[ $key ] = (string) $location[ $key ];
            }
        }

        if ( isset( $location['lat'], $location['lng'] ) ) {
            $normalised['lat'] = (string) $location['lat'];
            $normalised['lng'] = (string) $location['lng'];
        }

        if ( isset( $location['latitude'], $location['longitude'] ) ) {
            $normalised['lat'] = (string) $location['latitude'];
            $normalised['lng'] = (string) $location['longitude'];
        }

        ksort( $normalised );

        return (string) wp_json_encode( $normalised );
    }

    /**
     * Convert the provider response into a numeric value and unit.
     *
     * @param mixed $value Provider response.
     *
     * @return array{value: float, unit: string}|null
     */
    private function normalize_provider_distance( $value ): ?array {
        if ( is_wp_error( $value ) || $value instanceof WP_Error ) {
            return null;
        }

        if ( is_numeric( $value ) ) {
            return array(
                'value' => (float) $value,
                'unit'  => 'km',
            );
        }

        if ( ! is_array( $value ) ) {
            return null;
        }

        if ( isset( $value['distance'] ) && is_array( $value['distance'] ) ) {
            return $this->normalize_provider_distance( $value['distance'] );
        }

        $raw_value = $value['value'] ?? $value['distance'] ?? null;
        $raw_unit  = $value['unit'] ?? $value['units'] ?? 'km';

        if ( null === $raw_value || ! is_numeric( $raw_value ) ) {
            return null;
        }

        $unit = strtolower( (string) $raw_unit );
        if ( ! in_array( $unit, array( 'km', 'mi' ), true ) ) {
            $unit = 'km';
        }

        return array(
            'value' => (float) $raw_value,
            'unit'  => $unit,
        );
    }

    /**
     * Convert a distance in the provider unit to kilometres.
     */
    private function to_kilometers( float $value, string $unit ): float {
        return 'mi' === $unit ? $value * 1.609344 : $value;
    }

    /**
     * Convert a distance in kilometres to the configured unit.
     */
    private function from_kilometers( float $value ): float {
        return 'mi' === $this->unit ? $value / 1.609344 : $value;
    }
}

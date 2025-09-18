<?php
/**
 * Google Maps geocoding adapter.
 */

declare(strict_types=1);

namespace DRS\Geo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use function add_query_arg;
use function apply_filters;
use function is_wp_error;
use function wp_remote_get;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

/**
 * Geocoder implementation that uses the Google Maps Geocoding API.
 */
class GoogleGeocoder implements GeocoderInterface {
    private Cache $cache;

    /**
     * @var callable
     */
    private $apiKeyProvider;

    private string $endpoint;

    private int $cacheTtl;

    private int $timeout;

    public function __construct( Cache $cache, callable $apiKeyProvider, string $endpoint = 'https://maps.googleapis.com/maps/api/geocode/json', int $cacheTtl = 86400, int $timeout = 10 ) {
        $this->cache          = $cache;
        $this->apiKeyProvider = $apiKeyProvider;
        $this->endpoint       = $endpoint;
        $this->cacheTtl       = $cacheTtl;
        $this->timeout        = $timeout;
    }

    public function geocodeAddress( string $address ): ?array {
        $address = trim( $address );
        if ( '' === $address ) {
            return null;
        }

        $apiKey = $this->resolveApiKey();
        if ( '' === $apiKey ) {
            return null;
        }

        $queries = $this->prepareQueries( $address );

        foreach ( $queries as $query ) {
            $cached = $this->cache->getForAddress( $query );
            if ( null !== $cached ) {
                return $cached;
            }

            $result = $this->fetchCoordinates( $query, $apiKey );
            if ( null !== $result ) {
                $this->cache->setForAddress( $query, $result, $this->cacheTtl );

                return $result;
            }
        }

        return null;
    }

    private function resolveApiKey(): string {
        if ( ! is_callable( $this->apiKeyProvider ) ) {
            return '';
        }

        $value = (string) call_user_func( $this->apiKeyProvider );

        return trim( $value );
    }

    /**
     * Prepare the list of queries to attempt, including fallbacks.
     *
     * @return string[]
     */
    private function prepareQueries( string $address ): array {
        $candidates = [];
        $address    = trim( $address );

        if ( '' !== $address ) {
            $candidates[] = $address;
        }

        $parts = preg_split( '/[,\n]+/', $address ) ?: [];
        $parts = array_values(
            array_filter(
                array_map(
                    static fn( string $part ): string => trim( $part ),
                    $parts
                ),
                static fn( string $part ): bool => '' !== $part
            )
        );

        $postalCandidates = [];
        $cityCandidates   = [];

        foreach ( $parts as $part ) {
            if ( $this->looksLikePostalCode( $part ) ) {
                $postalCandidates[] = $part;
            } elseif ( preg_match( '/[\p{L}]{2,}/u', $part ) ) {
                $cityCandidates[] = $part;
            }
        }

        foreach ( $postalCandidates as $candidate ) {
            $candidates[] = $candidate;
        }

        foreach ( $cityCandidates as $candidate ) {
            $candidates[] = $candidate;
        }

        if ( ! empty( $cityCandidates ) && ! empty( $parts ) ) {
            $last = end( $parts );
            foreach ( $cityCandidates as $city ) {
                if ( $city === $last ) {
                    continue;
                }

                $combined = $city . ', ' . $last;
                $candidates[] = $combined;
            }
        }

        $candidates = array_values( array_unique( array_filter( $candidates ) ) );

        if ( function_exists( 'apply_filters' ) ) {
            /**
             * Filter the list of geocoding queries attempted for an address.
             *
             * @param string[] $candidates Ordered list of address candidates.
             * @param string   $address    Original address string.
             */
            $candidates = (array) apply_filters( 'drs_geocoder_queries', $candidates, $address );
        }

        return $candidates;
    }

    private function looksLikePostalCode( string $value ): bool {
        return (bool) preg_match( '/([0-9]{3,}|[A-Za-z]\d[A-Za-z])/', $value );
    }

    private function fetchCoordinates( string $query, string $apiKey ): ?array {
        if ( ! function_exists( 'add_query_arg' ) || ! function_exists( 'wp_remote_get' ) ) {
            return null;
        }

        $url = add_query_arg(
            [
                'address' => $query,
                'key'     => $apiKey,
            ],
            $this->endpoint
        );

        $response = wp_remote_get(
            $url,
            [
                'timeout' => $this->timeout,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $code = function_exists( 'wp_remote_retrieve_response_code' ) ? wp_remote_retrieve_response_code( $response ) : 200;
        if ( 200 !== (int) $code ) {
            return null;
        }

        $body = function_exists( 'wp_remote_retrieve_body' ) ? wp_remote_retrieve_body( $response ) : '';
        if ( '' === $body ) {
            return null;
        }

        $payload = json_decode( $body, true );
        if ( ! is_array( $payload ) ) {
            return null;
        }

        $status = $payload['status'] ?? '';
        if ( is_string( $status ) && 'OK' !== strtoupper( $status ) ) {
            return null;
        }

        if ( empty( $payload['results'] ) || ! is_array( $payload['results'] ) ) {
            return null;
        }

        return $this->selectBestResult( $payload['results'] );
    }

    /**
     * Extract the most relevant result from a list returned by Google.
     *
     * @param array<int,mixed> $results
     *
     * @return array{lat:float,lng:float,precision:string}|null
     */
    private function selectBestResult( array $results ): ?array {
        foreach ( $results as $result ) {
            if ( ! is_array( $result ) ) {
                continue;
            }

            $geometry = $result['geometry'] ?? null;
            if ( ! is_array( $geometry ) ) {
                continue;
            }

            $location = $geometry['location'] ?? null;
            if ( ! is_array( $location ) || ! isset( $location['lat'], $location['lng'] ) ) {
                continue;
            }

            $precision = '';
            if ( isset( $geometry['location_type'] ) && is_string( $geometry['location_type'] ) ) {
                $precision = $geometry['location_type'];
            }

            return [
                'lat'       => (float) $location['lat'],
                'lng'       => (float) $location['lng'],
                'precision' => $precision,
            ];
        }

        return null;
    }
}

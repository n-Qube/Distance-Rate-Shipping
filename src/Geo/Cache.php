<?php
/**
 * Simple transient-backed cache for geospatial lookups.
 */

declare(strict_types=1);

namespace DRS\Geo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Lightweight cache helper that stores responses in WordPress transients.
 */
class Cache {
    private const TRANSIENT_PREFIX = 'drs_geo_';

    private int $expiration;

    /**
     * Fallback in-memory store when transients are unavailable.
     *
     * @var array<string,mixed>
     */
    private static array $memoryStore = [];

    public function __construct( ?int $expiration = null ) {
        $this->expiration = $expiration ?? 86400; // 1 day default.
    }

    /**
     * Retrieve cached data for the given origin/destination/precision triple.
     */
    public function get( string $origin, string $destination, int $precision ): ?array {
        $value = $this->read( $this->build_key( $origin, $destination, $precision ) );

        return is_array( $value ) ? $value : null;
    }

    /**
     * Store data for the given origin/destination/precision triple.
     */
    public function set( string $origin, string $destination, int $precision, array $value, ?int $ttl = null ): void {
        $this->write( $this->build_key( $origin, $destination, $precision ), $value, $ttl ?? $this->expiration );
    }

    /**
     * Remove a cached entry.
     */
    public function delete( string $origin, string $destination, int $precision ): void {
        $this->remove( $this->build_key( $origin, $destination, $precision ) );
    }

    /**
     * Retrieve a geocode response for a specific address.
     */
    public function getForAddress( string $address ): ?array {
        return $this->get( 'geocode', $address, 0 );
    }

    /**
     * Persist a geocode response for an address.
     */
    public function setForAddress( string $address, array $value, ?int $ttl = null ): void {
        $this->set( 'geocode', $address, 0, $value, $ttl );
    }

    /**
     * Remove cached geocode data for an address.
     */
    public function deleteForAddress( string $address ): void {
        $this->delete( 'geocode', $address, 0 );
    }

    private function build_key( string $origin, string $destination, int $precision ): string {
        $payload = strtolower( trim( $origin ) ) . '|' . strtolower( trim( $destination ) ) . '|' . $precision;

        return self::TRANSIENT_PREFIX . md5( $payload );
    }

    /**
     * Read a value from persistent storage.
     *
     * @return mixed
     */
    private function read( string $key ) {
        if ( function_exists( 'get_transient' ) ) {
            $value = get_transient( $key );

            return false !== $value ? $value : null;
        }

        return self::$memoryStore[ $key ] ?? null;
    }

    /**
     * Write a value to persistent storage.
     *
     * @param mixed $value Data to store.
     */
    private function write( string $key, $value, int $ttl ): void {
        if ( function_exists( 'set_transient' ) ) {
            set_transient( $key, $value, $ttl );

            return;
        }

        self::$memoryStore[ $key ] = $value;
    }

    /**
     * Delete a cached entry.
     */
    private function remove( string $key ): void {
        if ( function_exists( 'delete_transient' ) ) {
            delete_transient( $key );

            return;
        }

        unset( self::$memoryStore[ $key ] );
    }
}

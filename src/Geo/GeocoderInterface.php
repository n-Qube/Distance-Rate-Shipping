<?php
/**
 * Contract for address geocoding services.
 */

declare(strict_types=1);

namespace DRS\Geo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Defines behaviour for classes that can translate an address into coordinates.
 */
interface GeocoderInterface {
    /**
     * Attempt to geocode a text address into coordinates.
     *
     * @return array{lat:float,lng:float,precision:string}|null
     */
    public function geocodeAddress( string $address ): ?array;
}

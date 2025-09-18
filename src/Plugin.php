<?php
/**
 * Core plugin bootstrap.
 */

declare(strict_types=1);

namespace DRS;

use DRS\Admin\SettingsPage;
use DRS\Geo\Cache;
use DRS\Geo\GoogleGeocoder;
use DRS\Geo\GeocoderInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin bootstrapper.
 */
final class Plugin {
    /**
     * Singleton instance.
     */
    private static ?Plugin $instance = null;

    /**
     * Geocoder implementation used by the plugin.
     */
    private ?GeocoderInterface $geocoder = null;

    /**
     * Retrieve singleton instance.
     */
    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize plugin hooks and services.
     */
    public function init(): void {
        if ( function_exists( 'is_admin' ) && is_admin() ) {
            $settings = new SettingsPage();
            $settings->register();
        }

        $cache    = new Cache();
        $provider = function (): string {
            if ( ! function_exists( 'get_option' ) ) {
                return '';
            }

            $option = get_option( SettingsPage::OPTION_KEY, '' );

            return is_string( $option ) ? trim( $option ) : '';
        };

        $this->geocoder = new GoogleGeocoder( $cache, $provider );
    }

    /**
     * Provide access to the geocoder.
     */
    public function geocoder(): ?GeocoderInterface {
        return $this->geocoder;
    }

    /**
     * Convenience helper to geocode an address via the configured adapter.
     */
    public function geocode( string $address ): ?array {
        if ( null === $this->geocoder ) {
            return null;
        }

        return $this->geocoder->geocodeAddress( $address );
    }
}

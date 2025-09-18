<?php
/**
 * Admin settings to configure external services.
 */

declare(strict_types=1);

namespace DRS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings controller for managing API credentials.
 */
class SettingsPage {
    public const OPTION_KEY = 'drs_google_maps_api_key';

    private const SETTINGS_GROUP = 'drs_geo_settings';

    private const PAGE_SLUG = 'drs-distance-rate-shipping';

    /**
     * Register admin hooks.
     */
    public function register(): void {
        if ( ! function_exists( 'add_action' ) ) {
            return;
        }

        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Register the options page under the Settings menu.
     */
    public function register_menu(): void {
        if ( ! function_exists( 'add_options_page' ) || ! function_exists( '__' ) ) {
            return;
        }

        add_options_page(
            __( 'Distance Rate Shipping', 'drs-distance' ),
            __( 'Distance Rate Shipping', 'drs-distance' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ]
        );
    }

    /**
     * Register the Google Maps API key setting and field.
     */
    public function register_settings(): void {
        if ( ! function_exists( 'register_setting' ) ) {
            return;
        }

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_KEY,
            [
                'type'              => 'string',
                'sanitize_callback' => [ $this, 'sanitize_api_key' ],
                'default'           => '',
            ]
        );

        if ( function_exists( 'add_settings_section' ) ) {
            add_settings_section(
                'drs_geo_section',
                __( 'Google Maps Geocoding', 'drs-distance' ),
                [ $this, 'render_section_description' ],
                self::PAGE_SLUG
            );
        }

        if ( function_exists( 'add_settings_field' ) ) {
            add_settings_field(
                self::OPTION_KEY,
                __( 'Google Maps API Key', 'drs-distance' ),
                [ $this, 'render_api_key_field' ],
                self::PAGE_SLUG,
                'drs_geo_section'
            );
        }
    }

    /**
     * Section description callback.
     */
    public function render_section_description(): void {
        if ( ! function_exists( 'esc_html__' ) ) {
            return;
        }

        echo '<p>' . esc_html__( 'Enter a Google Maps Geocoding API key. This key will be used to translate addresses into coordinates for distance-based rates.', 'drs-distance' ) . '</p>';
    }

    /**
     * Render the API key input field.
     */
    public function render_api_key_field(): void {
        if ( ! function_exists( 'get_option' ) || ! function_exists( 'esc_attr' ) ) {
            return;
        }

        $value = get_option( self::OPTION_KEY, '' );
        $value = is_string( $value ) ? $value : '';

        printf(
            '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" autocomplete="off" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $value )
        );

        if ( function_exists( 'esc_html__' ) ) {
            echo '<p class="description">' . esc_html__( 'Create an API key with access to the Geocoding API in the Google Cloud Console.', 'drs-distance' ) . '</p>';
        }
    }

    /**
     * Render the settings page.
     */
    public function render_page(): void {
        if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        echo '<div class="wrap">';
        if ( function_exists( 'esc_html__' ) ) {
            echo '<h1>' . esc_html__( 'Distance Rate Shipping', 'drs-distance' ) . '</h1>';
        }

        echo '<form method="post" action="options.php">';
        if ( function_exists( 'settings_fields' ) ) {
            settings_fields( self::SETTINGS_GROUP );
        }
        if ( function_exists( 'do_settings_sections' ) ) {
            do_settings_sections( self::PAGE_SLUG );
        }
        if ( function_exists( 'submit_button' ) ) {
            submit_button();
        }
        echo '</form>';
        echo '</div>';
    }

    /**
     * Sanitize the API key value prior to persisting it.
     *
     * @param mixed $value Value supplied by the form.
     */
    public function sanitize_api_key( $value ): string {
        if ( ! is_string( $value ) ) {
            return '';
        }

        $value = trim( $value );

        return preg_replace( '/[^A-Za-z0-9_\-]/', '', $value ) ?? '';
    }
}

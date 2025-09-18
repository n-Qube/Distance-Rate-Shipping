<?php
/**
 * WooCommerce status page widget.
 *
 * @package DRS\Admin
 */

declare( strict_types=1 );

namespace DRS\Admin;

use DRS\Settings\Settings;
use DRS\Support\Logger;
use function add_action;
use function esc_html;
use function esc_html__;
use function number_format_i18n;
use function sanitize_text_field;
use function __;

/**
 * Renders a widget on WooCommerce → Status with current plugin diagnostics.
 */
class Status_Widget {
    /**
     * Hook registrations.
     */
    public function init(): void {
        add_action( 'woocommerce_system_status_report', array( $this, 'render' ) );
    }

    /**
     * Output the widget markup.
     */
    public function render(): void {
        $settings = Settings::get_settings();
        $rules    = isset( $settings['rules'] ) && is_array( $settings['rules'] ) ? $settings['rules'] : array();

        $provider        = $this->format_provider( $this->determine_provider( $settings ) );
        $rules_count     = number_format_i18n( count( $rules ) );
        $logging_enabled = Logger::is_enabled( $settings );

        $widget_title   = __( 'Distance Rate Shipping', 'drs-distance' );
        $provider_label = __( 'Provider:', 'drs-distance' );
        $rules_label    = __( 'Configured rules:', 'drs-distance' );
        $logging_label  = __( 'Debug logging:', 'drs-distance' );
        $logging_value  = $logging_enabled ? __( 'Enabled', 'drs-distance' ) : __( 'Disabled', 'drs-distance' );

        echo '<div class="woocommerce-status-boxes drs-status-widget">';
        echo '<div class="woocommerce-status-box">';
        echo '<h3>' . esc_html( $widget_title ) . '</h3>';
        echo '<ul>';
        echo '<li><strong>' . esc_html( $provider_label ) . '</strong> ' . esc_html( $provider ) . '</li>';
        echo '<li><strong>' . esc_html( $rules_label ) . '</strong> ' . esc_html( $rules_count ) . '</li>';
        echo '<li><strong>' . esc_html( $logging_label ) . '</strong> ' . esc_html( $logging_value ) . '</li>';
        echo '</ul>';
        echo '</div>';
        echo '</div>';

        Logger::debug(
            'Rendered WooCommerce status widget.',
            array(
                'provider'       => $provider,
                'rules_count'    => (int) count( $rules ),
                'logging_active' => $logging_enabled,
            ),
            $settings
        );
    }

    /**
     * Resolve the configured provider from plugin settings.
     *
     * @param array<string, mixed> $settings Stored settings.
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

        return sanitize_text_field( $provider );
    }

    /**
     * Provide a human readable provider label.
     */
    private function format_provider( string $provider ): string {
        switch ( $provider ) {
            case 'straight_line':
                return esc_html__( 'Straight-line (Haversine)', 'drs-distance' );
            case 'road_distance':
                return esc_html__( 'Road distance service', 'drs-distance' );
            case 'api':
                return esc_html__( 'External API', 'drs-distance' );
            default:
                return $provider;
        }
    }
}

<?php
/**
 * Dedicated logging wrapper for the Distance Rate Shipping plugin.
 *
 * @package DRS\Support
 */

declare( strict_types=1 );

namespace DRS\Support;

use DRS\Settings\Settings;
use function class_exists;
use function interface_exists;
use function is_bool;
use function is_object;
use function wc_get_logger;

/**
 * Helper around wc_get_logger() that respects the plugin debug flag.
 */
class Logger {
    /**
     * WooCommerce logging channel used for all plugin messages.
     */
    public const CHANNEL = 'drs';

    /**
     * Cached WooCommerce logger instance.
     *
     * @var \WC_Logger_Interface|object|null
     */
    private static $logger = null;

    /**
     * Log an informational message when debug logging is enabled.
     *
     * @param string                $message  Message to log.
     * @param array<string, mixed>  $context  Additional context values.
     * @param array<string, mixed>|null $settings Optional settings payload to avoid repeated lookups.
     */
    public static function info( string $message, array $context = array(), ?array $settings = null ): void {
        self::write( 'info', $message, $context, $settings );
    }

    /**
     * Log a debug level message when enabled.
     *
     * @param string                $message  Message to log.
     * @param array<string, mixed>  $context  Additional context values.
     * @param array<string, mixed>|null $settings Optional settings payload to avoid repeated lookups.
     */
    public static function debug( string $message, array $context = array(), ?array $settings = null ): void {
        self::write( 'debug', $message, $context, $settings );
    }

    /**
     * Always record an error regardless of the debug flag.
     *
     * @param string               $message Message to log.
     * @param array<string, mixed> $context Context values.
     */
    public static function error( string $message, array $context = array() ): void {
        self::write( 'error', $message, $context, null, true );
    }

    /**
     * Determine if logging should occur given current settings.
     *
     * @param array<string, mixed>|null $settings Optional settings payload.
     */
    public static function is_enabled( ?array $settings = null ): bool {
        if ( null === $settings ) {
            if ( ! class_exists( Settings::class ) ) {
                return false;
            }

            $settings = Settings::get_settings();
        }

        $debug = $settings['debug_mode'] ?? 'no';

        if ( is_bool( $debug ) ) {
            return $debug;
        }

        return 'yes' === $debug || '1' === $debug;
    }

    /**
     * Internal helper to write log entries.
     *
     * @param string                $level     Log level.
     * @param string                $message   Message to record.
     * @param array<string, mixed>  $context   Context values.
     * @param array<string, mixed>|null $settings Optional settings payload.
     * @param bool                  $force     When true the message is recorded even if debug logging is disabled.
     */
    private static function write( string $level, string $message, array $context, ?array $settings = null, bool $force = false ): void {
        if ( ! $force && ! self::is_enabled( $settings ) ) {
            return;
        }

        $logger = self::get_logger();

        if ( null === $logger ) {
            return;
        }

        if ( ! isset( $context['source'] ) ) {
            $context['source'] = self::CHANNEL;
        }

        $logger->log( $level, $message, $context );
    }

    /**
     * Retrieve the shared WooCommerce logger instance.
     *
     * @return WC_Logger_Interface|object|null
     */
    private static function get_logger() {
        if ( null !== self::$logger ) {
            return self::$logger;
        }

        if ( ! function_exists( 'wc_get_logger' ) ) {
            return null;
        }

        $logger = wc_get_logger();

        if ( interface_exists( 'WC_Logger_Interface' ) ) {
            if ( $logger instanceof \WC_Logger_Interface ) {
                self::$logger = $logger;
                return self::$logger;
            }
        }

        if ( is_object( $logger ) ) {
            self::$logger = $logger;
        }

        return self::$logger;
    }
}

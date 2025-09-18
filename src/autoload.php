<?php
/**
 * Simple PSR-4 autoloader for the DRS plugin namespace.
 */

declare(strict_types=1);

namespace DRS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

spl_autoload_register(
    static function ( string $class ): void {
        if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
            return;
        }

        $relative = substr( $class, strlen( __NAMESPACE__ . '\\' ) );
        $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
        $path     = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';

        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
);

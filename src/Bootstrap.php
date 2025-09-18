<?php
/**
 * Plugin bootstrap.
 *
 * @package DRS\DistanceRateShipping
 */

declare( strict_types=1 );

namespace DRS\DistanceRateShipping;

use DRS\Admin\Settings_Page;
use DRS\Rest\Quote_Controller;
use DRS\Settings\Settings;

/**
 * Bootstrap class wires all plugin functionality.
 */
class Bootstrap {
    /**
     * Main plugin file path.
     */
    private string $plugin_file;

    /**
     * Cached admin page instance.
     */
    private ?Settings_Page $settings_page = null;

    /**
     * Constructor.
     *
     * @param string $plugin_file Main plugin file.
     */
    public function __construct( string $plugin_file ) {
        $this->plugin_file = $plugin_file;
    }

    /**
     * Register hooks.
     */
    public function init(): void {
        $this->maybe_define_constants();

        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'woocommerce_shipping_init', array( $this, 'include_shipping_method' ) );
        add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        $this->register_blocks_extension();

        if ( is_admin() ) {
            $this->boot_admin();
        }
    }

    /**
     * Load plugin translations.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( 'drs-distance', false, dirname( plugin_basename( $this->plugin_file ) ) . '/languages' );
    }

    /**
     * Define helper constants on demand.
     */
    private function maybe_define_constants(): void {
        if ( ! defined( 'DRS_DISTANCE_RATE_SHIPPING_DIR' ) ) {
            define( 'DRS_DISTANCE_RATE_SHIPPING_DIR', dirname( $this->plugin_file ) );
        }

        if ( ! defined( 'DRS_DISTANCE_RATE_SHIPPING_URL' ) ) {
            define( 'DRS_DISTANCE_RATE_SHIPPING_URL', plugin_dir_url( $this->plugin_file ) );
        }
    }

    /**
     * Load the shipping method implementation.
     */
    public function include_shipping_method(): void {
        $method_class = '\\DRS\\Shipping\\Method';

        if ( class_exists( $method_class, false ) ) {
            return;
        }

        $method_file = dirname( $this->plugin_file ) . '/src/Shipping/Method.php';

        if ( is_readable( $method_file ) ) {
            require_once $method_file;
        }
    }

    /**
     * Register the shipping method with WooCommerce.
     *
     * @param array<string, string> $methods Registered methods.
     * @return array<string, string>
     */
    public function register_shipping_method( array $methods ): array {
        $this->include_shipping_method();

        $method_class = '\\DRS\\Shipping\\Method';

        if ( class_exists( $method_class, false ) ) {
            $methods['drs_distance_rate'] = $method_class;
        }

        return $methods;
    }

    /**
     * Initialize admin experience.
     */
    private function boot_admin(): void {
        $this->include_common_classes();

        $page_class = '\\DRS\\Admin\\Settings_Page';

        if ( ! class_exists( $page_class, false ) ) {
            $file = dirname( $this->plugin_file ) . '/src/Admin/Settings_Page.php';

            if ( is_readable( $file ) ) {
                require_once $file;
            }
        }

        if ( class_exists( $page_class ) ) {
            $this->settings_page = new Settings_Page( $this->plugin_file );
            $this->settings_page->init();
        }
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes(): void {
        $this->include_common_classes();

        $controller_class = '\\DRS\\Rest\\Quote_Controller';

        if ( ! class_exists( $controller_class, false ) ) {
            $file = dirname( $this->plugin_file ) . '/src/Rest/Quote_Controller.php';

            if ( is_readable( $file ) ) {
                require_once $file;
            }
        }

        if ( class_exists( $controller_class ) ) {
            $controller = new Quote_Controller();
            $controller->register_routes();
        }
    }

    /**
     * Register WooCommerce Blocks integrations.
     */
    private function register_blocks_extension(): void {
        $class = '\\DRS\\Blocks\\CheckoutExtension';

        if ( ! class_exists( $class, false ) ) {
            $file = dirname( $this->plugin_file ) . '/src/Blocks/CheckoutExtension.php';

            if ( is_readable( $file ) ) {
                require_once $file;
            }
        }

        if ( class_exists( $class ) ) {
            $extension = new \DRS\Blocks\CheckoutExtension( $this->plugin_file );
            $extension->register();
        }
    }

    /**
     * Ensure shared classes are available.
     */
    private function include_common_classes(): void {
        $settings_class = '\\DRS\\Settings\\Settings';

        if ( class_exists( $settings_class, false ) ) {
            return;
        }

        $file = dirname( $this->plugin_file ) . '/src/Settings/Settings.php';

        if ( is_readable( $file ) ) {
            require_once $file;
        }
    }
}

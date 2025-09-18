<?php

declare(strict_types=1);

namespace DRS\DistanceRateShipping;

class Bootstrap
{
    /**
     * Path to the main plugin file.
     */
    private string $pluginFile;

    private string $pluginDir;

    public function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;
        $this->pluginDir = dirname($pluginFile);
    }

    public function init(): void
    {
        error_log('[DRS] Bootstrap init');

        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_filter('woocommerce_shipping_methods', [$this, 'register_shipping_method']);
        add_action('init', [$this, 'wire_admin_blocks_loader']);
        add_action('rest_api_init', [$this, 'wire_rest_blocks_loader']);
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('drs-distance', false, dirname(plugin_basename($this->pluginFile)) . '/languages');
    }

    /**
     * @param array<string, string> $methods
     * @return array<string, string>
     */
    public function register_shipping_method(array $methods): array
    {
        if (! class_exists('WC_Shipping_Method')) {
            return $methods;
        }

        $shippingClass = 'DRS\\DistanceRateShipping\\Shipping\\DistanceRateMethod';

        if (! class_exists($shippingClass) && is_readable($this->pluginDir . '/src/Shipping/DistanceRateMethod.php')) {
            require_once $this->pluginDir . '/src/Shipping/DistanceRateMethod.php';
        }

        if (! class_exists($shippingClass)) {
            // Shipping method implementation can be provided by extensions.
            return $methods;
        }

        $methods['drs_distance_rate'] = $shippingClass;

        return $methods;
    }

    public function wire_admin_blocks_loader(): void
    {
        if (! is_admin()) {
            return;
        }

        $class = 'DRS\\DistanceRateShipping\\Blocks\\AdminLoader';

        if (! class_exists($class) && is_readable($this->pluginDir . '/src/Blocks/AdminLoader.php')) {
            require_once $this->pluginDir . '/src/Blocks/AdminLoader.php';
        }

        if (class_exists($class)) {
            $loader = new \DRS\DistanceRateShipping\Blocks\AdminLoader();
            if (method_exists($loader, 'register')) {
                $loader->register();
            }
        }
    }

    public function wire_rest_blocks_loader(): void
    {
        $class = 'DRS\\DistanceRateShipping\\Blocks\\RestLoader';

        if (! class_exists($class) && is_readable($this->pluginDir . '/src/Blocks/RestLoader.php')) {
            require_once $this->pluginDir . '/src/Blocks/RestLoader.php';
        }

        if (class_exists($class)) {
            $loader = new \DRS\DistanceRateShipping\Blocks\RestLoader();
            if (method_exists($loader, 'register')) {
                $loader->register();
            }
        }
    }
}

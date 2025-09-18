<?php

declare(strict_types=1);

namespace DRS\DistanceRateShipping\Shipping;

if (class_exists('WC_Shipping_Method')) {
    class DistanceRateMethod extends \WC_Shipping_Method
    {
        public function __construct()
        {
            $this->id = 'drs_distance_rate';
            $this->method_title = __('Distance Rate Shipping', 'drs-distance');
            $this->method_description = __('Calculates shipping rates based on distance.', 'drs-distance');

            $this->enabled = 'no';
            $this->title = __('Distance Rate Shipping', 'drs-distance');
        }

        /**
         * @param array<string, mixed> $package
         */
        public function calculate_shipping($package = []): void
        {
            // Shipping cost calculation will be implemented in a future iteration.
        }
    }
} else {
    class DistanceRateMethod
    {
    }
}

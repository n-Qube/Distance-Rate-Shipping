<?php

declare(strict_types=1);

namespace DRS\DistanceRateShipping\Blocks;

use DRS\DistanceRateShipping\Shipping\RuleEngine\CostCalculator;
use DRS\DistanceRateShipping\Shipping\RuleEngine\Rule;
use DRS\DistanceRateShipping\Shipping\RuleEngine\RuleMatcher;
use DRS\DistanceRateShipping\Support\Options;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;
use function current_user_can;
use function rest_ensure_response;

class RestLoader
{
    private Options $options;

    public function __construct(?Options $options = null)
    {
        $this->options = $options ?? new Options();
    }

    public function register(): void
    {
        register_rest_route(
            'drs/v1',
            '/rules',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_rules'],
                'permission_callback' => [$this, 'can_read'],
            ]
        );

        register_rest_route(
            'drs/v1',
            '/rules',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_rules'],
                'permission_callback' => [$this, 'can_manage'],
                'args'                => [
                    'rules' => [
                        'description' => __( 'Rules to create.', 'drs-distance' ),
                    ],
                ],
            ]
        );

        register_rest_route(
            'drs/v1',
            '/rules',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_rules'],
                'permission_callback' => [$this, 'can_manage'],
                'args'                => [
                    'rules' => [
                        'description' => __( 'Complete rules collection.', 'drs-distance' ),
                    ],
                ],
            ]
        );

        register_rest_route(
            'drs/v1',
            '/rules',
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_rules'],
                'permission_callback' => [$this, 'can_manage'],
            ]
        );

        register_rest_route(
            'drs/v1',
            '/settings',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_settings'],
                'permission_callback' => [$this, 'can_read'],
            ]
        );

        register_rest_route(
            'drs/v1',
            '/settings',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_settings'],
                'permission_callback' => [$this, 'can_manage'],
            ]
        );

        register_rest_route(
            'drs/v1',
            '/quote',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_quote'],
                'permission_callback' => [$this, 'can_read'],
            ]
        );
    }

    /**
     * @return WP_REST_Response|array
     */
    public function get_rules(WP_REST_Request $request)
    {
        unset($request);

        $rules = $this->options->get_rules();

        return rest_ensure_response([
            'rules' => $rules,
        ]);
    }

    /**
     * @return WP_REST_Response|WP_Error|array
     */
    public function create_rules(WP_REST_Request $request)
    {
        $payload = $this->get_request_body($request);
        $incoming = $this->normalise_rules_payload($payload, false);

        if ([] === $incoming) {
            return new WP_Error('drs_invalid_rules', __( 'No valid rules were provided.', 'drs-distance' ), ['status' => 400]);
        }

        $existing = $this->options->get_rules();
        $collection = array_merge($existing, $incoming);
        $sanitised = $this->options->save_rules($collection);

        $created = array_slice($sanitised, -count($incoming));

        return rest_ensure_response([
            'rules'   => $sanitised,
            'created' => $created,
        ]);
    }

    /**
     * @return WP_REST_Response|WP_Error|array
     */
    public function update_rules(WP_REST_Request $request)
    {
        $explicitEmpty = false;
        $jsonPayload = $request->get_json_params();
        if (is_array($jsonPayload)) {
            $payload = $jsonPayload;
            $explicitEmpty = [] === $jsonPayload || (array_key_exists('rules', $jsonPayload) && [] === $jsonPayload['rules']);
        } else {
            $bodyParams = $request->get_body_params();
            $payload = is_array($bodyParams) ? $bodyParams : [];
            $explicitEmpty = is_array($bodyParams) && array_key_exists('rules', $bodyParams) && [] === $bodyParams['rules'];
        }

        $incoming = $this->normalise_rules_payload($payload, true);

        if ([] === $incoming && !$explicitEmpty) {
            return new WP_Error('drs_invalid_rules', __( 'No valid rules were provided.', 'drs-distance' ), ['status' => 400]);
        }

        $sanitised = $this->options->save_rules($incoming);

        return rest_ensure_response([
            'rules' => $sanitised,
        ]);
    }

    /**
     * @return WP_REST_Response|WP_Error|array
     */
    public function delete_rules(WP_REST_Request $request)
    {
        $payload = $this->get_request_body($request);
        $ruleId = null;

        if (isset($payload['id']) && is_scalar($payload['id'])) {
            $ruleId = (string) $payload['id'];
        } elseif ($request->get_param('id')) {
            $ruleId = (string) $request->get_param('id');
        }

        if (null !== $ruleId) {
            $existing = $this->options->get_rules();
            $filtered = array_values(array_filter(
                $existing,
                static fn (array $rule): bool => ($rule['id'] ?? '') !== $ruleId
            ));

            if (count($filtered) === count($existing)) {
                return new WP_Error('drs_rule_not_found', __( 'Rule not found.', 'drs-distance' ), ['status' => 404]);
            }

            $sanitised = $this->options->save_rules($filtered);

            return rest_ensure_response([
                'deleted' => true,
                'rules'   => $sanitised,
            ]);
        }

        $this->options->delete_rules();

        return rest_ensure_response([
            'deleted' => true,
            'rules'   => [],
        ]);
    }

    /**
     * @return WP_REST_Response|array
     */
    public function get_settings(WP_REST_Request $request)
    {
        unset($request);

        $settings = $this->options->get_general();

        return rest_ensure_response([
            'settings' => $settings,
        ]);
    }

    /**
     * @return WP_REST_Response|array
     */
    public function update_settings(WP_REST_Request $request)
    {
        $payload = $this->get_request_body($request);
        $settings = [];

        if (isset($payload['settings']) && is_array($payload['settings'])) {
            $settings = $payload['settings'];
        } elseif (is_array($payload)) {
            $settings = $payload;
        }

        $sanitised = $this->options->save_general($settings);

        return rest_ensure_response([
            'settings' => $sanitised,
        ]);
    }

    /**
     * @return WP_REST_Response|WP_Error|array
     */
    public function create_quote(WP_REST_Request $request)
    {
        $payload = $this->get_request_body($request);

        $general = $this->options->get_general();

        $origin = $this->normalise_location($payload['origin'] ?? null);
        if (null === $origin) {
            $origin = $this->normalise_location($general['origin_address'] ?? null);
        }

        if (null === $origin) {
            return new WP_Error('drs_missing_origin', __( 'An origin coordinate is required.', 'drs-distance' ), ['status' => 400]);
        }

        $destination = $this->normalise_location($payload['destination'] ?? null);
        if (null === $destination) {
            return new WP_Error('drs_invalid_destination', __( 'A destination coordinate is required.', 'drs-distance' ), ['status' => 400]);
        }

        $package = [];
        if (isset($payload['package']) && is_array($payload['package'])) {
            $package = $payload['package'];
        }

        $distanceKm = $this->calculate_distance($origin, $destination);
        $distanceForCalc = $this->apply_distance_rounding($distanceKm, $general);

        $existingOrigin = isset($package['origin']) && is_array($package['origin']) ? $package['origin'] : [];
        $existingDestination = isset($package['destination']) && is_array($package['destination']) ? $package['destination'] : [];

        $package['origin'] = array_merge($existingOrigin, $origin);
        $package['destination'] = array_merge($existingDestination, $destination);
        $package['distance'] = $distanceForCalc;
        $package['distance_km'] = $distanceForCalc;

        $rules = array_filter(
            $this->options->get_rules(),
            static fn (array $rule): bool => !isset($rule['enabled']) || (bool) $rule['enabled']
        );

        $matcher = new RuleMatcher($rules);
        $matched = $matcher->match($package, $distanceForCalc);

        $ruleId = null;
        $total = 0.0;

        if ($matched instanceof Rule) {
            $ruleId = $matched->getId();
            $calculator = new CostCalculator();
            $total = $calculator->total($matched, $package, $distanceForCalc);
        } elseif (!empty($general['fallback_enabled'])) {
            $ruleId = 'fallback';
            $total = isset($general['fallback_cost']) ? (float) $general['fallback_cost'] : 0.0;
            if (isset($general['fallback_distance']) && is_numeric($general['fallback_distance']) && $distanceForCalc <= 0) {
                $distanceForCalc = (float) $general['fallback_distance'];
            }
        }

        $response = [
            'distance_km' => $distanceForCalc,
            'rule_id'     => $ruleId,
            'total'       => $total,
        ];

        return rest_ensure_response($response);
    }

    private function can_read(): bool
    {
        return function_exists('current_user_can') ? current_user_can('read') : true;
    }

    private function can_manage(): bool
    {
        return function_exists('current_user_can') ? current_user_can('manage_woocommerce') : true;
    }

    /**
     * @return array<string,mixed>
     */
    private function get_request_body(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        if (is_array($json)) {
            return $json;
        }

        $params = $request->get_body_params();
        if (is_array($params)) {
            return $params;
        }

        return [];
    }

    /**
     * @param mixed $payload
     * @return array<int, array<string, mixed>>
     */
    private function normalise_rules_payload($payload, bool $requireCollection): array
    {
        if (!is_array($payload)) {
            return [];
        }

        if (isset($payload['rules']) && is_array($payload['rules'])) {
            $payload = $payload['rules'];
        } elseif (isset($payload['rule']) && is_array($payload['rule'])) {
            $payload = [$payload['rule']];
        } elseif ($requireCollection && $this->is_list_array($payload)) {
            // keep payload as-is
        } elseif (!$requireCollection) {
            if ($this->is_list_array($payload)) {
                // keep payload as list
            } elseif ([] !== $payload) {
                $payload = [$payload];
            }
        }

        if (!is_array($payload)) {
            return [];
        }

        if (!$this->is_list_array($payload)) {
            $allArrays = true;
            foreach ($payload as $value) {
                if (!is_array($value)) {
                    $allArrays = false;
                    break;
                }
            }

            if ($requireCollection && $allArrays) {
                $payload = array_values($payload);
            } elseif ($requireCollection) {
                $candidates = array_values(array_filter($payload, 'is_array'));
                $payload = [] !== $candidates ? $candidates : [$payload];
            } else {
                $payload = [$payload];
            }
        }

        $rules = [];
        foreach ($payload as $rule) {
            if (is_array($rule)) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @param array<string, mixed>|null $value
     * @return array{lat: float, lng: float}|null
     */
    private function normalise_location($value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $lat = null;
        $lng = null;

        foreach (['lat', 'latitude'] as $key) {
            if (isset($value[$key]) && is_numeric($value[$key])) {
                $lat = (float) $value[$key];
                break;
            }
        }

        foreach (['lng', 'lon', 'longitude'] as $key) {
            if (isset($value[$key]) && is_numeric($value[$key])) {
                $lng = (float) $value[$key];
                break;
            }
        }

        if (null === $lat || null === $lng) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * @param array{lat: float, lng: float} $origin
     * @param array{lat: float, lng: float} $destination
     */
    private function calculate_distance(array $origin, array $destination): float
    {
        $lat1 = deg2rad($origin['lat']);
        $lat2 = deg2rad($destination['lat']);
        $deltaLat = $lat2 - $lat1;
        $deltaLon = deg2rad($destination['lng'] - $origin['lng']);

        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $earthRadiusKm = 6371.0;

        return $earthRadiusKm * $c;
    }

    private function apply_distance_rounding(float $distanceKm, array $settings): float
    {
        $precision = 0;
        if (isset($settings['distance_precision']) && is_numeric($settings['distance_precision'])) {
            $precision = max(0, (int) $settings['distance_precision']);
        }

        $mode = isset($settings['distance_rounding']) ? (string) $settings['distance_rounding'] : 'round';
        $factor = 10 ** $precision;
        $value = $distanceKm * $factor;

        switch ($mode) {
            case 'ceil':
                $value = ceil($value);
                break;
            case 'floor':
                $value = floor($value);
                break;
            default:
                $value = round($value);
        }

        return $value / $factor;
    }

    private function is_list_array(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        return $value === array_values($value);
    }
}

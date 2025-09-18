<?php

declare(strict_types=1);

namespace DRS\Support;

require_once __DIR__ . '/RulesCsv.php';

use InvalidArgumentException;

/**
 * Handles persistence and validation of plugin options.
 */
class Options
{
    public const GENERAL_OPTION_KEY = 'drs_general';

    public const RULES_OPTION_KEY = 'drs_rules';

    /**
     * Default and validation metadata for the general settings.
     *
     * @var array<string, array<string, mixed>>
     */
    private const GENERAL_SCHEMA = [
        'enabled' => [
            'type'    => 'boolean',
            'default' => false,
        ],
        'title' => [
            'type'    => 'string',
            'default' => 'Distance Rate',
            'max_length' => 100,
        ],
        'tax_status' => [
            'type'    => 'string',
            'default' => 'taxable',
            'enum'    => ['taxable', 'none'],
        ],
        'calculation_strategy' => [
            'type'    => 'string',
            'default' => 'straight_line',
            'enum'    => ['straight_line', 'road_distance'],
        ],
        'distance_unit' => [
            'type'    => 'string',
            'default' => 'km',
            'enum'    => ['km', 'mi'],
        ],
        'distance_rounding' => [
            'type'    => 'string',
            'default' => 'round',
            'enum'    => ['round', 'ceil', 'floor'],
        ],
        'rounding_precision' => [
            'type'    => 'integer',
            'default' => 0,
            'min'     => 0,
            'max'     => 3,
        ],
        'show_distance' => [
            'type'    => 'boolean',
            'default' => false,
        ],
        'show_breakdown' => [
            'type'    => 'boolean',
            'default' => false,
        ],
        'distance_precision' => [
            'type'    => 'integer',
            'default' => 1,
            'min'     => 0,
            'max'     => 3,
        ],
        'fallback_enabled' => [
            'type'    => 'boolean',
            'default' => false,
        ],
        'fallback_label' => [
            'type'    => 'string',
            'default' => 'Distance rate',
            'max_length' => 150,
        ],
        'fallback_cost' => [
            'type'    => 'number',
            'default' => 0.0,
            'min'     => 0,
        ],
        'fallback_distance' => [
            'type'    => 'number',
            'default' => 0.0,
            'min'     => 0,
        ],
        'api_provider' => [
            'type'    => 'string',
            'default' => 'none',
        ],
        'api_key' => [
            'type'    => 'string',
            'default' => '',
        ],
        'api_secret' => [
            'type'    => 'string',
            'default' => '',
        ],
        'api_timeout' => [
            'type'    => 'integer',
            'default' => 10,
            'min'     => 1,
            'max'     => 120,
        ],
        'cache_enabled' => [
            'type'    => 'boolean',
            'default' => true,
        ],
        'cache_ttl' => [
            'type'    => 'integer',
            'default' => 30,
            'min'     => 0,
        ],
        'origin_mode' => [
            'type'    => 'string',
            'default' => 'store',
            'enum'    => ['store', 'custom', 'per_rule'],
        ],
        'origin_address' => [
            'type'    => 'array',
            'default' => [],
        ],
        'debug_mode' => [
            'type'    => 'boolean',
            'default' => false,
        ],
    ];

    /**
     * Default and validation metadata for a single rule.
     *
     * @var array<string, array<string, mixed>>
     */
    private const RULE_SCHEMA = [
        'id' => [
            'type'    => 'string',
            'default' => '',
            'max_length' => 190,
        ],
        'name' => [
            'type'    => 'string',
            'default' => '',
        ],
        'description' => [
            'type'    => 'string',
            'default' => '',
        ],
        'enabled' => [
            'type'    => 'boolean',
            'default' => true,
        ],
        'priority' => [
            'type'    => 'integer',
            'default' => 10,
            'min'     => 0,
        ],
        'conditions' => [
            'type'    => 'array',
            'default' => [],
        ],
        'costs' => [
            'type'    => 'array',
            'default' => [],
        ],
        'filters' => [
            'type'    => 'array',
            'default' => [],
        ],
        'metadata' => [
            'type'    => 'array',
            'default' => [],
        ],
        'actions' => [
            'type'    => 'array',
            'default' => [],
        ],
    ];

    /**
     * Defaults for numeric cost adjustments that every rule should expose.
     *
     * @var array<string, float>
     */
    private const RULE_COST_DEFAULTS = [
        'base'         => 0.0,
        'per_distance' => 0.0,
        'per_item'     => 0.0,
        'per_weight'   => 0.0,
        'per_stop'     => 0.0,
        'percentage'   => 0.0,
        'handling_fee' => 0.0,
        'surcharge'    => 0.0,
        'discount'     => 0.0,
    ];

    /**
     * Example rule used to seed the database during the first migration.
     *
     * @var array<string, mixed>
     */
    private const SAMPLE_RULE = [
        'id'          => 'sample',
        'name'        => 'Sample: Local delivery (0-5 km)',
        'description' => 'Automatically generated example rule.',
        'enabled'     => true,
        'priority'    => 10,
        'conditions'  => [
            [
                'type' => 'distance',
                'min'  => 0,
                'max'  => 5,
                'unit' => 'km',
            ],
            [
                'type' => 'weight',
                'min'  => 0,
                'max'  => null,
                'unit' => 'kg',
            ],
        ],
        'costs'       => [
            'base'         => 5.0,
            'per_distance' => 1.25,
            'per_item'     => 0.0,
            'per_weight'   => 0.0,
            'min_cost'     => null,
            'max_cost'     => null,
        ],
        'filters'     => [],
        'metadata'    => [
            'source' => 'seed',
        ],
        'actions'     => [
            'stop' => false,
        ],
    ];

    /**
     * In-memory fallback used when WordPress option helpers are not available.
     *
     * @var array<string, mixed>
     */
    private static array $memoryStore = [];

    /** @var callable */
    private $getOptionCallback;

    /** @var callable */
    private $updateOptionCallback;

    /** @var callable */
    private $deleteOptionCallback;

    public function __construct(
        ?callable $getOption = null,
        ?callable $updateOption = null,
        ?callable $deleteOption = null
    ) {
        $this->getOptionCallback    = $getOption ?? $this->createDefaultGetter();
        $this->updateOptionCallback = $updateOption ?? $this->createDefaultUpdater();
        $this->deleteOptionCallback = $deleteOption ?? $this->createDefaultDeleter();
    }

    /**
     * Returns the default configuration for the general settings.
     */
    public static function general_defaults(): array
    {
        $defaults = [];

        foreach (self::GENERAL_SCHEMA as $key => $definition) {
            $defaults[$key] = array_key_exists('default', $definition)
                ? self::clone_value($definition['default'])
                : null;
        }

        return $defaults;
    }

    /**
     * Returns the schema for general options.
     */
    public static function general_schema(): array
    {
        return self::clone_value(self::GENERAL_SCHEMA);
    }

    /**
     * Returns the schema for rules.
     */
    public static function rule_schema(): array
    {
        return self::clone_value(self::RULE_SCHEMA);
    }

    /**
     * Returns the default cost layout for a rule.
     */
    public static function rule_cost_defaults(): array
    {
        return self::clone_value(self::RULE_COST_DEFAULTS);
    }

    /**
     * Retrieves general plugin options.
     */
    public function get_general(): array
    {
        $raw = ($this->getOptionCallback)(self::GENERAL_OPTION_KEY, []);
        $data = is_array($raw) ? $raw : [];

        return $this->apply_schema(self::GENERAL_SCHEMA, $data, true);
    }

    /**
     * Persists general plugin options after sanitisation.
     */
    public function save_general(array $values): array
    {
        $existing = ($this->getOptionCallback)(self::GENERAL_OPTION_KEY, []);
        $existing = is_array($existing) ? $existing : [];

        $merged = array_merge($existing, $values);
        $sanitised = $this->apply_schema(self::GENERAL_SCHEMA, $merged, true);

        ($this->updateOptionCallback)(self::GENERAL_OPTION_KEY, $sanitised);

        return $sanitised;
    }

    /**
     * Removes all general options.
     */
    public function delete_general(): bool
    {
        return (bool) ($this->deleteOptionCallback)(self::GENERAL_OPTION_KEY);
    }

    /**
     * Returns the current rule collection.
     */
    public function get_rules(): array
    {
        $decoded = $this->decode_rules(($this->getOptionCallback)(self::RULES_OPTION_KEY, ''));

        $rules = [];
        $existingIds = [];
        foreach ($decoded as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $normalised = $this->normalise_rule($rule, $existingIds);
            $existingIds[] = $normalised['id'];
            $rules[] = $normalised;
        }

        return $rules;
    }

    /**
     * Saves a rule collection, enforcing schema and JSON persistence.
     */
    public function save_rules(array $rules): array
    {
        $sanitised = [];
        $existingIds = [];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $normalised = $this->normalise_rule($rule, $existingIds);
            $existingIds[] = $normalised['id'];
            $sanitised[] = $normalised;
        }

        $this->persist_rules($sanitised);

        return $sanitised;
    }

    /**
     * Deletes all stored rules.
     */
    public function delete_rules(): bool
    {
        return (bool) ($this->deleteOptionCallback)(self::RULES_OPTION_KEY);
    }

    /**
     * Exports the current rule collection as CSV.
     */
    public function export_rules_csv(): string
    {
        $codec = new RulesCsv();

        return $codec->export($this->get_rules());
    }

    /**
     * Imports rules from CSV, optionally merging with existing ones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function import_rules_csv(string $csv, bool $mergeExisting = true): array
    {
        $codec = new RulesCsv();
        $existing = $mergeExisting ? $this->get_rules() : [];

        $rules = $codec->import($csv, $existing);

        $this->persist_rules($rules);

        return $rules;
    }

    /**
     * Returns the default sample rule used for migrations.
     */
    public function get_sample_rule(): array
    {
        return $this->normalise_rule(self::SAMPLE_RULE, []);
    }

    /**
     * Ensures there is at least one rule configured by seeding a sample rule.
     */
    public function maybe_seed_sample_rule(): bool
    {
        $existing = $this->get_rules();
        if (!empty($existing)) {
            return false;
        }

        $this->persist_rules([$this->get_sample_rule()]);

        return true;
    }

    /**
     * Returns the raw value stored for rules (mostly useful for tests).
     *
     * @return mixed
     */
    public function get_raw_rules_option()
    {
        return ($this->getOptionCallback)(self::RULES_OPTION_KEY, '');
    }

    /**
     * Factory for the default option getter.
     */
    private function createDefaultGetter(): callable
    {
        if (function_exists('get_option')) {
            return static function (string $key, $default = false) {
                return get_option($key, $default);
            };
        }

        return static function (string $key, $default = false) {
            return Options::$memoryStore[$key] ?? $default;
        };
    }

    /**
     * Factory for the default option updater.
     */
    private function createDefaultUpdater(): callable
    {
        if (function_exists('update_option')) {
            return static function (string $key, $value): bool {
                return update_option($key, $value);
            };
        }

        return static function (string $key, $value): bool {
            Options::$memoryStore[$key] = $value;

            return true;
        };
    }

    /**
     * Factory for the default option deleter.
     */
    private function createDefaultDeleter(): callable
    {
        if (function_exists('delete_option')) {
            return static function (string $key): bool {
                return delete_option($key);
            };
        }

        return static function (string $key): bool {
            if (array_key_exists($key, Options::$memoryStore)) {
                unset(Options::$memoryStore[$key]);
            }

            return true;
        };
    }

    /**
     * Applies schema validation to an array of settings.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $values
     */
    private function apply_schema(array $schema, array $values, bool $includeUnknown = false): array
    {
        $result = [];

        foreach ($schema as $key => $definition) {
            $default = array_key_exists('default', $definition)
                ? self::clone_value($definition['default'])
                : null;

            $value = array_key_exists($key, $values) ? $values[$key] : $default;
            $result[$key] = $this->cast_by_definition($value, $definition, $default);
        }

        if ($includeUnknown) {
            foreach ($values as $key => $value) {
                if (!array_key_exists($key, $schema)) {
                    $result[$key] = self::clone_value($value);
                }
            }
        }

        return $result;
    }

    /**
     * Casts a value according to the provided schema definition.
     *
     * @param array<string, mixed> $definition
     * @param mixed $value
     * @param mixed $default
     *
     * @return mixed
     */
    private function cast_by_definition($value, array $definition, $default)
    {
        $type = $definition['type'] ?? null;

        switch ($type) {
            case 'boolean':
                if (is_bool($value)) {
                    // Already a boolean value, no casting necessary.
                    break;
                }

                if (is_array($value)) {
                    $value = !empty($value);
                    break;
                }

                if (is_int($value) || is_float($value)) {
                    $value = (bool) $value;
                    break;
                }

                if (is_string($value)) {
                    $normalised = strtolower(trim($value));
                    $falseStrings = ['', '0', 'false', 'no', 'off'];

                    if (in_array($normalised, $falseStrings, true)) {
                        $value = false;
                        break;
                    }

                    $value = $normalised !== '';
                    break;
                }

                $value = (bool) $value;
                break;
            case 'string':
                $value = $value === null ? '' : (string) $value;
                if (isset($definition['max_length']) && is_int($definition['max_length']) && $definition['max_length'] >= 0) {
                    $length = $definition['max_length'];
                    if (function_exists('mb_substr')) {
                        $value = mb_substr($value, 0, $length);
                    } else {
                        $value = substr($value, 0, $length);
                    }
                }
                break;
            case 'integer':
                $value = $this->to_int($value, is_int($default) ? $default : 0);
                break;
            case 'number':
            case 'float':
                $value = $this->to_float(
                    $value,
                    is_float($default) ? $default : (is_int($default) ? (float) $default : 0.0)
                );
                break;
            case 'array':
                $value = is_array($value)
                    ? self::clone_value($value)
                    : (is_array($default) ? self::clone_value($default) : []);
                if (isset($definition['schema']) && is_array($definition['schema'])) {
                    if ($this->is_list_array($value) && isset($definition['schema']['items']) && is_array($definition['schema']['items'])) {
                        $itemDefinition = $definition['schema']['items'];
                        $itemDefault    = $itemDefinition['default'] ?? null;
                        $value = array_values(array_map(
                            fn ($item) => $this->cast_by_definition($item, $itemDefinition, $itemDefault),
                            $value
                        ));
                    } else {
                        $value = $this->apply_schema(
                            $definition['schema'],
                            $value,
                            (bool) ($definition['schema']['allow_unknown'] ?? true)
                        );
                    }
                }
                break;
            default:
                $value = $default;
        }

        if (isset($definition['enum']) && is_array($definition['enum']) && !in_array($value, $definition['enum'], true)) {
            $value = $default;
        }

        if (isset($definition['min']) && is_numeric($definition['min']) && is_numeric($value)) {
            $value = max((float) $definition['min'], (float) $value);
        }

        if (isset($definition['max']) && is_numeric($definition['max']) && is_numeric($value)) {
            $value = min((float) $definition['max'], (float) $value);
        }

        return $value;
    }

    /**
     * Normalises a single rule against the schema.
     *
     * @param array<int|string, mixed> $rule
     * @param array<int, string>       $existingIds
     *
     * @return array<string, mixed>
     */
    private function normalise_rule(array $rule, array $existingIds): array
    {
        $sanitised = $this->apply_schema(self::RULE_SCHEMA, $rule, true);

        $sanitised['id'] = $this->ensure_rule_id(
            $this->slugify((string) $sanitised['id']),
            (string) $sanitised['name'],
            $existingIds
        );
        $sanitised['name'] = $this->normalise_text($sanitised['name']);
        $sanitised['description'] = $this->normalise_text($sanitised['description']);
        $sanitised['enabled'] = (bool) $sanitised['enabled'];
        $sanitised['priority'] = max(0, $this->to_int($sanitised['priority'], 0));
        $sanitised['conditions'] = $this->normalise_conditions($sanitised['conditions']);
        $sanitised['costs'] = $this->normalise_costs($sanitised['costs']);
        $sanitised['filters'] = $this->normalise_filters($sanitised['filters']);
        $sanitised['metadata'] = $this->normalise_metadata($sanitised['metadata']);
        $sanitised['actions'] = $this->normalise_actions($sanitised['actions']);

        return $sanitised;
    }

    /**
     * Ensures the provided rule identifier is usable and unique.
     *
     * @param array<int, string> $existingIds
     */
    private function ensure_rule_id(string $candidate, string $fallback, array $existingIds): string
    {
        $base = $candidate !== '' ? $candidate : $this->slugify($fallback);
        if ($base === '') {
            $base = 'rule';
        }

        $id = $base;
        $suffix = 2;
        while (in_array($id, $existingIds, true)) {
            $id = $base . '-' . $suffix;
            ++$suffix;
        }

        return $id;
    }

    /**
     * Normalises the conditions block of a rule.
     *
     * @param mixed $conditions
     *
     * @return array<int|string, mixed>
     */
    private function normalise_conditions($conditions): array
    {
        if (!is_array($conditions)) {
            return [];
        }

        if ($this->is_list_array($conditions)) {
            $result = [];
            foreach ($conditions as $condition) {
                if (!is_array($condition)) {
                    continue;
                }

                $type = isset($condition['type']) ? (string) $condition['type'] : '';
                if ($type === '' && isset($condition['name'])) {
                    $type = (string) $condition['name'];
                }

                $normalised = ['type' => $type];
                if (array_key_exists('min', $condition)) {
                    $normalised['min'] = $this->to_float_nullable($condition['min'], 0.0);
                }
                if (array_key_exists('max', $condition)) {
                    $normalised['max'] = $this->to_float_nullable($condition['max']);
                }
                if (array_key_exists('value', $condition)) {
                    $normalised['value'] = $condition['value'];
                }
                if (array_key_exists('unit', $condition)) {
                    $normalised['unit'] = (string) $condition['unit'];
                }
                if (array_key_exists('operator', $condition)) {
                    $normalised['operator'] = (string) $condition['operator'];
                }

                foreach ($condition as $key => $value) {
                    if (!array_key_exists($key, $normalised)) {
                        $normalised[$key] = $value;
                    }
                }

                $result[] = $normalised;
            }

            return $result;
        }

        $result = [];
        foreach ($conditions as $name => $condition) {
            if (!is_array($condition)) {
                continue;
            }

            $normalised = [];
            $normalised['min'] = array_key_exists('min', $condition)
                ? $this->to_float_nullable($condition['min'], 0.0)
                : 0.0;
            $normalised['max'] = array_key_exists('max', $condition)
                ? $this->to_float_nullable($condition['max'])
                : null;

            if (array_key_exists('unit', $condition)) {
                $normalised['unit'] = (string) $condition['unit'];
            }
            if (array_key_exists('operator', $condition)) {
                $normalised['operator'] = (string) $condition['operator'];
            }

            foreach ($condition as $key => $value) {
                if (!array_key_exists($key, $normalised)) {
                    $normalised[$key] = $value;
                }
            }

            $result[$name] = $normalised;
        }

        return $result;
    }

    /**
     * Normalises the cost block of a rule.
     *
     * @param mixed $costs
     *
     * @return array<string, mixed>
     */
    private function normalise_costs($costs): array
    {
        $costs = is_array($costs) ? $costs : [];
        $normalised = [];

        foreach (self::RULE_COST_DEFAULTS as $key => $default) {
            $normalised[$key] = $this->to_float($costs[$key] ?? $default, $default);
        }

        foreach (['min_cost', 'max_cost'] as $bound) {
            if (array_key_exists($bound, $costs)) {
                $normalised[$bound] = $this->to_float_nullable($costs[$bound]);
            } else {
                $normalised[$bound] = null;
            }
        }

        foreach ($costs as $key => $value) {
            if (array_key_exists($key, $normalised)) {
                continue;
            }

            $normalised[$key] = is_numeric($value)
                ? $this->to_float($value, 0.0)
                : $value;
        }

        return $normalised;
    }

    /**
     * Normalises rule filters ensuring every value is an array of strings.
     *
     * @param mixed $filters
     *
     * @return array<int|string, mixed>
     */
    private function normalise_filters($filters)
    {
        if (!is_array($filters)) {
            return [];
        }

        if ($this->is_list_array($filters)) {
            $list = [];
            foreach ($filters as $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }
                $list[] = $value;
            }

            return $list;
        }

        $normalised = [];
        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $list = [];
                foreach ($value as $item) {
                    if (!is_scalar($item)) {
                        continue;
                    }
                    $item = trim((string) $item);
                    if ($item === '') {
                        continue;
                    }
                    $list[] = $item;
                }
                $normalised[$key] = array_values(array_unique($list));
                continue;
            }

            if (is_scalar($value)) {
                $stringValue = trim((string) $value);
                if ($stringValue !== '') {
                    $normalised[$key] = [$stringValue];
                }
            }
        }

        return $normalised;
    }

    /**
     * Ensures metadata is stored as an associative array.
     *
     * @param mixed $metadata
     *
     * @return array<string, mixed>
     */
    private function normalise_metadata($metadata): array
    {
        return is_array($metadata) ? self::clone_value($metadata) : [];
    }

    /**
     * Normalises rule actions, guaranteeing supported flags exist.
     *
     * @param mixed $actions
     *
     * @return array<string, mixed>
     */
    private function normalise_actions($actions): array
    {
        $actions = is_array($actions) ? $actions : [];

        $normalised = $actions;
        $normalised['stop'] = isset($actions['stop']) ? (bool) $actions['stop'] : false;

        return $normalised;
    }

    /**
     * Persists a normalised rule collection as JSON.
     *
     * @param array<int, array<string, mixed>> $rules
     */
    private function persist_rules(array $rules): void
    {
        $json = json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new InvalidArgumentException('Unable to encode rules as JSON.');
        }

        ($this->updateOptionCallback)(self::RULES_OPTION_KEY, $json);
    }

    /**
     * Decodes the rules option.
     *
     * @param mixed $raw
     *
     * @return array<int, mixed>
     */
    private function decode_rules($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw)) {
            return [];
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $maybeArray = $this->maybe_unserialize($trimmed);
        if (is_array($maybeArray)) {
            return $maybeArray;
        }

        return [];
    }

    /**
     * Attempts to unserialise a string if it looks like a PHP serialised payload.
     *
     * @return mixed
     */
    private function maybe_unserialize(string $value)
    {
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^(a|O|s|i|b|d):/', $value)) {
            return null;
        }

        set_error_handler(static function (): bool {
            return true;
        });

        try {
            $data = unserialize($value, ['allowed_classes' => false]);
        } catch (\Throwable $throwable) {
            $data = null;
        } finally {
            restore_error_handler();
        }

        if ($data === false && $value !== 'b:0;') {
            return null;
        }

        return $data;
    }

    /**
     * Normalises arbitrary text content.
     *
     * @param mixed $value
     */
    private function normalise_text($value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_numeric($value) || is_bool($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';

        return trim($value, '-');
    }

    /**
     * Normalises a value to integer.
     *
     * @param mixed $value
     */
    private function to_int($value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        return $default;
    }

    /**
     * Normalises a value to float.
     *
     * @param mixed $value
     */
    private function to_float($value, float $default = 0.0): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if (is_string($value)) {
            $normalised = $this->normalise_numeric_string($value);
            if ($normalised !== null) {
                return (float) $normalised;
            }
        }

        return $default;
    }

    /**
     * Normalises a value to float allowing null results.
     *
     * @param mixed $value
     */
    private function to_float_nullable($value, ?float $default = null): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalised = $this->normalise_numeric_string($value);
            if ($normalised !== null) {
                return (float) $normalised;
            }
        }

        return $default;
    }

    private function normalise_numeric_string(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = str_replace(["\u{00A0}", ' '], '', $trimmed);
        $lastDot = strrpos($normalized, '.');
        $lastComma = strrpos($normalized, ',');

        if ($lastDot !== false && $lastComma !== false) {
            if ($lastDot > $lastComma) {
                $normalized = str_replace(',', '', $normalized);
            } else {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            }
        } elseif ($lastComma !== false) {
            $normalized = str_replace(',', '.', $normalized);
        }

        $normalized = str_replace(["'", '`'], '', $normalized);

        if (substr_count($normalized, '.') > 1) {
            $parts = explode('.', $normalized);
            $lastPart = array_pop($parts);
            $normalized = implode('', $parts) . '.' . $lastPart;
        }

        $normalized = preg_replace('/[^0-9\.\-]+/', '', $normalized) ?? '';

        if ($normalized === '' || $normalized === '-' || $normalized === '.' || $normalized === '-.') {
            return null;
        }

        return is_numeric($normalized) ? $normalized : null;
    }

    private function is_list_array(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Recursively clones array values to avoid accidental references.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private static function clone_value($value)
    {
        if (is_array($value)) {
            $copy = [];
            foreach ($value as $key => $item) {
                $copy[$key] = self::clone_value($item);
            }

            return $copy;
        }

        return $value;
    }
}

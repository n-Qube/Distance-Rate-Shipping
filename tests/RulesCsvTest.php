<?php

declare(strict_types=1);

use DRS\Support\Options;
use DRS\Support\RulesCsv;

require __DIR__ . '/../Support/RulesCsv.php';
require __DIR__ . '/../Support/Options.php';

function assertSameValue($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message !== '' ? $message : sprintf(
            'Failed asserting that %s is identical to %s.',
            var_export($actual, true),
            var_export($expected, true)
        ));
    }
}

function assertTrueValue(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new RuntimeException($message !== '' ? $message : 'Failed asserting that condition is true.');
    }
}

function assertFalseValue(bool $condition, string $message = ''): void
{
    if ($condition) {
        throw new RuntimeException($message !== '' ? $message : 'Failed asserting that condition is false.');
    }
}

function assertNotNullValue($value, string $message = ''): void
{
    if (null === $value) {
        throw new RuntimeException($message !== '' ? $message : 'Failed asserting that value is not null.');
    }
}

function assertCountValue(int $expected, array $value, string $message = ''): void
{
    if (count($value) !== $expected) {
        throw new RuntimeException($message !== '' ? $message : sprintf(
            'Failed asserting that array has %d elements, got %d.',
            $expected,
            count($value)
        ));
    }
}

/**
 * @param array<int, array<string, mixed>> $rules
 */
function getRuleByName(array $rules, string $name): ?array
{
    foreach ($rules as $rule) {
        if (is_array($rule) && isset($rule['name']) && $rule['name'] === $name) {
            return $rule;
        }
    }

    return null;
}

function runGeneralOptionsBooleanTest(): void
{
    $store = [];

    $options = new Options(
        static function (string $key, $default = false) use (&$store) {
            return $store[$key] ?? $default;
        },
        static function (string $key, $value) use (&$store): bool {
            $store[$key] = $value;

            return true;
        },
        static function (string $key) use (&$store): bool {
            if (array_key_exists($key, $store)) {
                unset($store[$key]);
            }

            return true;
        }
    );

    $result = $options->save_general(['show_distance' => 'no']);

    assertSameValue(false, $result['show_distance'], 'General options should treat "no" as boolean false.');
    assertSameValue(false, $store[Options::GENERAL_OPTION_KEY]['show_distance'], 'Stored options should persist "no" as false.');

    $result = $options->save_general(['show_distance' => '0']);

    assertSameValue(false, $result['show_distance'], 'General options should treat "0" as boolean false.');
    assertSameValue(false, $store[Options::GENERAL_OPTION_KEY]['show_distance'], 'Stored options should persist "0" as false.');
}

function runRoundTripTest(): void
{
    $options = new Options(
        static fn () => '',
        static fn (string $key, $value): bool => true,
        static fn (string $key): bool => true
    );

    $rules = [
        [
            'name' => 'Primary Rule',
            'description' => 'Round trip rule',
            'enabled' => true,
            'priority' => 12,
            'conditions' => [
                ['type' => 'distance', 'min' => 2, 'max' => 25, 'unit' => 'km'],
                ['type' => 'weight', 'min' => 1.5, 'max' => 10, 'unit' => 'kg'],
                ['type' => 'items', 'min' => 1, 'max' => 4],
                ['type' => 'subtotal', 'min' => 20, 'max' => 200],
            ],
            'costs' => array_merge(
                Options::rule_cost_defaults(),
                [
                    'base' => 10.5,
                    'per_distance' => 0.75,
                    'handling_fee' => 3.25,
                    'free_over_subtotal' => 120,
                ]
            ),
            'filters' => [
                'include_classes' => ['class-a', 'class-b'],
                'exclude_classes' => ['class-c'],
                'include_categories' => ['cat-1'],
                'exclude_categories' => ['cat-2', 'cat-3'],
            ],
            'metadata' => [
                'tax_status' => 'none',
            ],
            'actions' => [
                'stop' => false,
                'apply_once' => true,
                'rounding' => 'up',
            ],
        ],
        [
            'name' => 'Backup Rule',
            'enabled' => false,
            'priority' => 5,
            'conditions' => [
                ['type' => 'distance', 'min' => 0, 'max' => null, 'unit' => 'km'],
            ],
            'costs' => Options::rule_cost_defaults(),
            'filters' => [],
            'metadata' => [],
            'actions' => ['stop' => true],
        ],
    ];

    $sanitised = $options->save_rules($rules);

    $codec = new RulesCsv();
    $csv = $codec->export($sanitised);
    $roundTrip = $codec->import($csv);

    assertSameValue($sanitised, $roundTrip, 'Round-trip export/import should yield identical rules.');
}

function runMergeTest(): void
{
    $options = new Options(
        static fn () => '',
        static fn (string $key, $value): bool => true,
        static fn (string $key): bool => true
    );

    $existing = $options->save_rules([
        [
            'id' => 'primary',
            'name' => 'Primary Rule',
            'description' => 'Existing primary rule',
            'enabled' => true,
            'priority' => 9,
            'conditions' => [
                ['type' => 'distance', 'min' => 1, 'max' => 40, 'unit' => 'km'],
                ['type' => 'weight', 'min' => 0.5, 'max' => 8, 'unit' => 'kg'],
            ],
            'costs' => [
                'base' => 12,
                'per_distance' => 0.9,
                'per_item' => 0.4,
                'per_weight' => 0.1,
                'per_stop' => 0.0,
                'percentage' => 0.0,
                'handling_fee' => 1.5,
                'surcharge' => 0.0,
                'discount' => 0.0,
                'min_cost' => 3,
                'max_cost' => 30,
            ],
            'filters' => [
                'include_classes' => ['class-a'],
                'exclude_categories' => ['cat-legacy'],
            ],
            'metadata' => [
                'tax_status' => 'inherit',
            ],
            'actions' => [
                'stop' => false,
                'apply_once' => true,
            ],
        ],
        [
            'id' => 'legacy',
            'name' => 'Legacy Rule',
            'enabled' => true,
            'priority' => 20,
            'conditions' => [
                ['type' => 'distance', 'min' => 0, 'max' => 10, 'unit' => 'km'],
            ],
            'costs' => [
                'base' => 5,
                'per_distance' => 0.5,
                'per_item' => 0.0,
                'per_weight' => 0.0,
                'per_stop' => 0.0,
                'percentage' => 0.0,
                'handling_fee' => 0.0,
                'surcharge' => 0.0,
                'discount' => 0.0,
                'min_cost' => null,
                'max_cost' => null,
            ],
            'filters' => [],
            'metadata' => [],
            'actions' => ['stop' => false],
        ],
    ]);

    $primaryId = $existing[0]['id'];

    $columns = [
        'enabled',
        'title',
        'min_distance',
        'max_distance',
        'base_cost',
        'per_distance',
        'free_over_subtotal',
        'handling_fee',
        'tax_status',
        'weight_min',
        'weight_max',
        'items_min',
        'items_max',
        'subtotal_min',
        'subtotal_max',
        'include_classes',
        'exclude_classes',
        'include_categories',
        'exclude_categories',
        'rounding',
        'apply_once',
        'priority',
    ];

    $rows = [
        [
            'enabled' => '1',
            'title' => 'Primary Rule',
            'min_distance' => '5',
            'max_distance' => '50',
            'base_cost' => '15.5',
            'per_distance' => '1.25',
            'free_over_subtotal' => '150',
            'handling_fee' => '4.25',
            'tax_status' => 'taxable',
            'weight_min' => '2',
            'weight_max' => '',
            'items_min' => '',
            'items_max' => '',
            'subtotal_min' => '30',
            'subtotal_max' => '',
            'include_classes' => 'class-a|class-d',
            'exclude_classes' => '',
            'include_categories' => '',
            'exclude_categories' => 'cat-5',
            'rounding' => 'down',
            'apply_once' => '0',
            'priority' => '7',
        ],
        [
            'enabled' => '1',
            'title' => 'Fresh Rule',
            'min_distance' => '',
            'max_distance' => '',
            'base_cost' => '9.99',
            'per_distance' => '0.5',
            'free_over_subtotal' => '',
            'handling_fee' => '1.00',
            'tax_status' => '',
            'weight_min' => '',
            'weight_max' => '',
            'items_min' => '1',
            'items_max' => '3',
            'subtotal_min' => '',
            'subtotal_max' => '',
            'include_classes' => '',
            'exclude_classes' => '',
            'include_categories' => '',
            'exclude_categories' => '',
            'rounding' => 'nearest',
            'apply_once' => '1',
            'priority' => '20',
        ],
    ];

    $stream = fopen('php://temp', 'r+');
    fputcsv($stream, $columns, ',', '"', '\\');
    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $column) {
            $line[] = $row[$column] ?? '';
        }
        fputcsv($stream, $line, ',', '"', '\\');
    }
    rewind($stream);
    $csv = stream_get_contents($stream) ?: '';
    fclose($stream);

    $codec = new RulesCsv();
    $imported = $codec->import($csv, $existing);

    assertCountValue(3, $imported, 'Import should yield three rules.');

    $primary = getRuleByName($imported, 'Primary Rule');
    assertNotNullValue($primary, 'Primary rule should exist after import.');
    assertSameValue($primaryId, $primary['id'], 'Primary rule ID should be preserved.');
    assertSameValue(7, $primary['priority'], 'Primary rule priority should update.');
    assertSameValue(15.5, $primary['costs']['base'], 'Primary base cost should update.');
    assertSameValue(0.4, $primary['costs']['per_item'], 'Existing per-item cost should be preserved.');
    assertSameValue(150.0, $primary['costs']['free_over_subtotal'], 'Free over subtotal should update.');
    assertSameValue(['class-a', 'class-d'], $primary['filters']['include_classes'], 'Include classes should merge.');
    assertSameValue(['cat-5'], $primary['filters']['exclude_categories'], 'Exclude categories should update.');
    assertSameValue('taxable', $primary['metadata']['tax_status'], 'Tax status should update.');
    assertSameValue('down', $primary['actions']['rounding'], 'Rounding mode should update.');
    assertFalseValue(isset($primary['actions']['apply_once']), 'Apply once flag should be removed.');

    $distanceCondition = null;
    foreach ($primary['conditions'] as $condition) {
        if (isset($condition['type']) && 'distance' === $condition['type']) {
            $distanceCondition = $condition;
        }
    }
    assertNotNullValue($distanceCondition, 'Distance condition should exist.');
    assertSameValue(5.0, $distanceCondition['min'], 'Distance min should update.');
    assertSameValue(50.0, $distanceCondition['max'], 'Distance max should update.');

    $weightCondition = null;
    foreach ($primary['conditions'] as $condition) {
        if (isset($condition['type']) && 'weight' === $condition['type']) {
            $weightCondition = $condition;
        }
    }
    assertNotNullValue($weightCondition, 'Weight condition should exist.');
    assertSameValue(2.0, $weightCondition['min'], 'Weight min should update.');
    assertTrueValue(array_key_exists('max', $weightCondition) && null === $weightCondition['max'], 'Weight max should be cleared.');

    $fresh = getRuleByName($imported, 'Fresh Rule');
    assertNotNullValue($fresh, 'Fresh rule should be created.');
    assertTrueValue(!empty($fresh['id']), 'Fresh rule should receive an identifier.');
    assertSameValue(20, $fresh['priority'], 'Fresh rule priority should be applied.');
    assertTrueValue($fresh['actions']['apply_once'], 'Fresh rule apply once flag should be enabled.');
    assertSameValue('nearest', $fresh['actions']['rounding'], 'Fresh rule rounding mode should be nearest.');

    $itemsCondition = null;
    foreach ($fresh['conditions'] as $condition) {
        if (isset($condition['type']) && 'items' === $condition['type']) {
            $itemsCondition = $condition;
        }
    }
    assertNotNullValue($itemsCondition, 'Fresh rule should include an items condition.');
    assertSameValue(1.0, $itemsCondition['min'], 'Fresh rule items min should be 1.');
    assertSameValue(3.0, $itemsCondition['max'], 'Fresh rule items max should be 3.');

    $legacy = getRuleByName($imported, 'Legacy Rule');
    assertNotNullValue($legacy, 'Legacy rule should remain untouched.');
    assertSameValue($existing[1], $legacy, 'Legacy rule should remain unchanged.');
}

runGeneralOptionsBooleanTest();
runRoundTripTest();
runMergeTest();

echo "All tests passed\n";

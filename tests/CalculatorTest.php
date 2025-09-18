<?php

declare(strict_types=1);

use DRS\Shipping\Calculator;

require __DIR__ . '/../src/Shipping/Calculator.php';

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

function assertNullValue($value, string $message = ''): void
{
    if (null !== $value) {
        throw new RuntimeException($message !== '' ? $message : 'Failed asserting that value is null.');
    }
}

function runMatchingRuleTest(): void
{
    $settings = [
        'rules' => [
            [
                'id' => 'tier-1',
                'label' => 'Local',
                'min_distance' => '0',
                'max_distance' => '10',
                'base_cost' => '5',
                'cost_per_distance' => '1.5',
            ],
        ],
        'handling_fee' => 2,
        'default_rate' => 3,
        'distance_unit' => 'km',
    ];

    $result = Calculator::calculate([
        'distance' => 4,
        'weight' => 2.5,
        'items' => 3,
        'subtotal' => 50,
        'origin' => '12345',
        'destination' => '67890',
    ], $settings);

    assertSameValue(4.0, $result['distance'], 'Distance should normalise to a float.');
    assertSameValue(2.5, $result['weight'], 'Weight should be preserved.');
    assertSameValue(3, $result['items'], 'Item count should be preserved.');
    assertSameValue(50.0, $result['subtotal'], 'Subtotal should be preserved.');
    assertSameValue(11.0, $result['rule_cost'], 'Rule cost should equal base plus per-distance charges.');
    assertSameValue(2.0, $result['handling_fee'], 'Handling fee should be rounded to two decimals.');
    assertSameValue(13.0, $result['total'], 'Total cost should be non-zero and include handling fee.');
    assertTrueValue(!$result['used_fallback'], 'Matching rule should not trigger fallback.');
    assertTrueValue(is_array($result['rule']), 'Matching rule should expose rule metadata.');
    assertSameValue('tier-1', $result['rule']['id'], 'Rule metadata should include ID.');
}

function runFallbackRuleTest(): void
{
    $settings = [
        'rules' => [
            [
                'id' => 'long-haul',
                'label' => 'Long Haul',
                'min_distance' => '10',
                'max_distance' => '',
                'base_cost' => '25',
                'cost_per_distance' => '0.5',
            ],
        ],
        'handling_fee' => 1.5,
        'default_rate' => 7,
        'distance_unit' => 'mi',
    ];

    $result = Calculator::calculate([
        'distance' => 2,
        'weight' => '-5',
        'items' => '-4',
        'subtotal' => '100',
        'origin' => '',
        'destination' => '',
    ], $settings);

    assertSameValue(2.0, $result['distance'], 'Distance should be converted to float.');
    assertSameValue(0.0, $result['weight'], 'Negative weights should clamp to zero.');
    assertSameValue(0, $result['items'], 'Negative item counts should clamp to zero.');
    assertSameValue(100.0, $result['subtotal'], 'Subtotal should normalise to float.');
    assertSameValue(7.0, $result['rule_cost'], 'Fallback should use the configured default rate.');
    assertSameValue(1.5, $result['handling_fee'], 'Handling fee should be preserved.');
    assertSameValue(8.5, $result['total'], 'Fallback total should include handling fee.');
    assertTrueValue($result['used_fallback'], 'Fallback flag should be set when no rule matches.');
    assertNullValue($result['rule'], 'No rule metadata should be returned when fallback applies.');
    assertSameValue('mi', $result['distance_unit'], 'Distance unit should respect settings.');
}

runMatchingRuleTest();
runFallbackRuleTest();

echo "All calculator tests passed\n";

<?php

declare(strict_types=1);

namespace DRS\Support;

use InvalidArgumentException;

class RulesCsv
{
    private const COLUMNS = [
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

    private const OPTIONAL_COLUMNS = [
        'extras',
    ];

    /**
     * Exports a rule collection to CSV.
     *
     * @param array<int, array<string, mixed>> $rules
     */
    public function export(array $rules): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new InvalidArgumentException('Unable to create temporary stream for CSV export.');
        }

        fputcsv($handle, array_merge(self::COLUMNS, self::OPTIONAL_COLUMNS), ',', '"', '\\');

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            fputcsv($handle, $this->mapRuleToRow($rule), ',', '"', '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return false === $csv ? '' : $csv;
    }

    /**
     * Imports a rule collection from CSV.
     *
     * @param array<int, array<string, mixed>> $existingRules
     *
     * @return array<int, array<string, mixed>>
     */
    public function import(string $csv, array $existingRules = []): array
    {
        $rows = $this->parseCsv($csv);

        if ([] === $rows) {
            return $this->normaliseRules($existingRules);
        }

        $existingMap = $this->mapExistingRules($existingRules);
        $matchedIds = [];
        $merged = [];

        foreach ($rows as $values) {
            $row = $this->parseRow($values);
            $key = $this->normaliseKey($row['title']);
            if ('' === $key) {
                throw new InvalidArgumentException('Rule title cannot be empty.');
            }

            $existing = $existingMap[$key] ?? null;
            if (is_array($existing) && array_key_exists('id', $existing)) {
                $matchedIds[] = (string) $existing['id'];
            }

            $merged[] = $this->mergeRowIntoRule($row, $existing);
        }

        foreach ($existingRules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $id = array_key_exists('id', $rule) ? (string) $rule['id'] : '';
            if ('' !== $id && in_array($id, $matchedIds, true)) {
                continue;
            }

            $merged[] = $rule;
        }

        return $this->normaliseRules($merged);
    }

    /**
     * Parses the CSV data into raw rows keyed by canonical column names.
     *
     * @return array<int, array<string, string>>
     */
    private function parseCsv(string $csv): array
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new InvalidArgumentException('Unable to create temporary stream for CSV parsing.');
        }

        fwrite($handle, $csv);
        rewind($handle);

        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if (false === $header) {
            fclose($handle);

            throw new InvalidArgumentException('CSV content is empty.');
        }

        if (isset($header[0])) {
            $header[0] = $this->stripBom((string) $header[0]);
        }

        $normalizedHeader = [];
        foreach ($header as $column) {
            $normalizedHeader[] = strtolower(trim((string) $column));
        }

        foreach (self::COLUMNS as $required) {
            if (!in_array($required, $normalizedHeader, true)) {
                fclose($handle);

                throw new InvalidArgumentException(sprintf('Missing required column "%s".', $required));
            }
        }

        $columnMap = [];
        $recognizedColumns = array_merge(self::COLUMNS, self::OPTIONAL_COLUMNS);

        foreach ($normalizedHeader as $index => $column) {
            if (in_array($column, $recognizedColumns, true)) {
                $columnMap[$index] = $column;
            }
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($row === [null]) {
                continue;
            }

            $values = [];
            $hasData = false;

            foreach ($columnMap as $index => $column) {
                $value = $row[$index] ?? '';
                $value = is_string($value) ? trim($value) : (null === $value ? '' : trim((string) $value));

                if ('' !== $value) {
                    $hasData = true;
                }

                $values[$column] = $value;
            }

            if (!$hasData) {
                continue;
            }

            $rows[] = $values;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Converts a CSV row into typed values.
     *
     * @param array<string, string> $row
     *
     * @return array{
     *     enabled: bool,
     *     title: string,
     *     min_distance: ?string,
     *     max_distance: ?string,
     *     base_cost: ?string,
     *     per_distance: ?string,
     *     free_over_subtotal: ?string,
     *     handling_fee: ?string,
     *     tax_status: string,
     *     weight_min: ?string,
     *     weight_max: ?string,
     *     items_min: ?string,
     *     items_max: ?string,
     *     subtotal_min: ?string,
     *     subtotal_max: ?string,
     *     include_classes: array<int, string>,
     *     exclude_classes: array<int, string>,
     *     include_categories: array<int, string>,
     *     exclude_categories: array<int, string>,
     *     rounding: string,
     *     apply_once: bool,
     *     priority: ?string,
     *     extras: array<int|string, mixed>
     * }
     */
    private function parseRow(array $row): array
    {
        $title = trim($row['title'] ?? '');
        if ('' === $title) {
            throw new InvalidArgumentException('Every rule must include a title.');
        }

        return [
            'enabled' => $this->parseBool($row['enabled'] ?? '', true),
            'title' => $title,
            'min_distance' => $this->parseNumericField($row['min_distance'] ?? null),
            'max_distance' => $this->parseNumericField($row['max_distance'] ?? null),
            'base_cost' => $this->parseNumericField($row['base_cost'] ?? null),
            'per_distance' => $this->parseNumericField($row['per_distance'] ?? null),
            'free_over_subtotal' => $this->parseNumericField($row['free_over_subtotal'] ?? null),
            'handling_fee' => $this->parseNumericField($row['handling_fee'] ?? null),
            'tax_status' => $this->parseTaxStatus($row['tax_status'] ?? ''),
            'weight_min' => $this->parseNumericField($row['weight_min'] ?? null),
            'weight_max' => $this->parseNumericField($row['weight_max'] ?? null),
            'items_min' => $this->parseIntegerField($row['items_min'] ?? null),
            'items_max' => $this->parseIntegerField($row['items_max'] ?? null),
            'subtotal_min' => $this->parseNumericField($row['subtotal_min'] ?? null),
            'subtotal_max' => $this->parseNumericField($row['subtotal_max'] ?? null),
            'include_classes' => $this->parseList($row['include_classes'] ?? ''),
            'exclude_classes' => $this->parseList($row['exclude_classes'] ?? ''),
            'include_categories' => $this->parseList($row['include_categories'] ?? ''),
            'exclude_categories' => $this->parseList($row['exclude_categories'] ?? ''),
            'rounding' => $this->parseRounding($row['rounding'] ?? ''),
            'apply_once' => $this->parseBool($row['apply_once'] ?? '', false),
            'priority' => $this->parseIntegerField($row['priority'] ?? null),
            'extras' => $this->parseExtras($row['extras'] ?? null),
        ];
    }

    /**
     * Merges a parsed CSV row into an existing rule.
     *
     * @param array<string, mixed>|null $existing
     *
     * @return array<string, mixed>
     */
    private function mergeRowIntoRule(array $row, ?array $existing): array
    {
        $hasExisting = is_array($existing);
        $rule = $hasExisting ? $existing : $this->createEmptyRule();

        if ([] !== $row['extras']) {
            $rule = $this->mergeExtrasIntoRule($rule, $row['extras'], $hasExisting);
        }

        $rule['id'] = $existing['id'] ?? ($rule['id'] ?? '');
        $rule['name'] = $row['title'];
        $rule['enabled'] = $row['enabled'];
        $rule['priority'] = $this->resolvePriority($row['priority'], $existing['priority'] ?? ($rule['priority'] ?? null));

        $rule['conditions'] = $this->mergeConditions(
            $row,
            $rule['conditions'] ?? []
        );
        $rule['costs'] = $this->mergeCosts(
            $row,
            $rule['costs'] ?? []
        );
        $rule['filters'] = $this->mergeFilters(
            $row,
            $rule['filters'] ?? []
        );
        $rule['metadata'] = $this->mergeMetadata(
            $row,
            $rule['metadata'] ?? []
        );
        $rule['actions'] = $this->mergeActions(
            $row,
            $rule['actions'] ?? []
        );

        return $rule;
    }

    /**
     * Applies extras data embedded in the CSV to the rule baseline.
     *
     * @param array<string, mixed> $rule
     * @param array<int|string, mixed> $extras
     */
    private function mergeExtrasIntoRule(array $rule, array $extras, bool $hasExisting): array
    {
        if ([] === $extras) {
            return $rule;
        }

        $allowed = ['id', 'description', 'conditions', 'costs', 'filters', 'metadata', 'actions'];
        $filtered = array_intersect_key($extras, array_flip($allowed));
        if ([] === $filtered) {
            return $rule;
        }

        $merged = $this->mergeArrayData($rule, $filtered);

        if ($hasExisting && array_key_exists('id', $rule)) {
            $merged['id'] = $rule['id'];
        }

        return $merged;
    }

    /**
     * Recursively merges associative arrays while replacing list structures.
     *
     * @param array<int|string, mixed> $base
     * @param array<int|string, mixed> $overrides
     * @return array<int|string, mixed>
     */
    private function mergeArrayData(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value)) {
                if (!array_key_exists($key, $base) || !is_array($base[$key]) || $this->isListArray($value) || $this->isListArray((array) $base[$key])) {
                    $base[$key] = $value;
                    continue;
                }

                $base[$key] = $this->mergeArrayData($base[$key], $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * Determines if an array uses sequential numeric keys.
     *
     * @param array<int|string, mixed> $array
     */
    private function isListArray(array $array): bool
    {
        if ([] === $array) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Normalises a rule collection using the Options helper.
     *
     * @param array<int, array<string, mixed>> $rules
     *
     * @return array<int, array<string, mixed>>
     */
    private function normaliseRules(array $rules): array
    {
        $options = new Options(
            static fn () => '',
            static fn (string $key, $value): bool => true,
            static fn (string $key): bool => true
        );

        return $options->save_rules($rules);
    }

    /**
     * Creates an empty rule template with sensible defaults.
     *
     * @return array<string, mixed>
     */
    private function createEmptyRule(): array
    {
        return [
            'id' => '',
            'name' => '',
            'description' => '',
            'enabled' => true,
            'priority' => 10,
            'conditions' => [],
            'costs' => Options::rule_cost_defaults(),
            'filters' => [],
            'metadata' => [],
            'actions' => ['stop' => false],
        ];
    }

    /**
     * Resolves the priority value based on CSV data and existing rule.
     *
     * @param mixed $existing
     */
    private function resolvePriority(?string $priority, $existing): int|string
    {
        if (null !== $priority) {
            return $priority;
        }

        if (is_int($existing) || is_string($existing)) {
            return $existing;
        }

        return 10;
    }

    /**
     * Updates the condition block with CSV values while preserving unknown entries.
     *
     * @param mixed $existingConditions
     *
     * @return array<int, array<string, mixed>>
     */
    private function mergeConditions(array $row, $existingConditions): array
    {
        $conditions = $this->normaliseConditionsList($existingConditions);
        $indexMap = [];

        foreach ($conditions as $index => $condition) {
            if (isset($condition['type']) && is_string($condition['type']) && '' !== $condition['type']) {
                $indexMap[strtolower($condition['type'])] = $index;
            }
        }

        $this->applyConditionValues($conditions, $indexMap, 'distance', $row['min_distance'], $row['max_distance']);
        $this->applyConditionValues($conditions, $indexMap, 'weight', $row['weight_min'], $row['weight_max']);
        $this->applyConditionValues($conditions, $indexMap, 'items', $row['items_min'], $row['items_max'], true);
        $this->applyConditionValues($conditions, $indexMap, 'subtotal', $row['subtotal_min'], $row['subtotal_max']);

        return $conditions;
    }

    /**
     * Applies condition values to a condition list.
     *
     * @param array<int, array<string, mixed>> $conditions
     * @param array<string, int> $indexMap
     */
    private function applyConditionValues(
        array &$conditions,
        array &$indexMap,
        string $type,
        ?string $minValue,
        ?string $maxValue,
        bool $forceInteger = false
    ): void {
        $existingIndex = $indexMap[$type] ?? null;
        $existing = null !== $existingIndex ? $conditions[$existingIndex] : null;

        if (null === $existing && null === $minValue && null === $maxValue) {
            return;
        }

        $condition = is_array($existing) ? $existing : ['type' => $type];
        $condition['type'] = $type;

        if ($forceInteger) {
            if (null !== $minValue) {
                $condition['min'] = $this->normaliseIntegerString($minValue);
            } else {
                $condition['min'] = isset($existing['min']) ? $this->normaliseIntegerString((string) $existing['min']) : '0';
            }

            if (null !== $maxValue) {
                $condition['max'] = $this->normaliseIntegerString($maxValue);
            } else {
                $condition['max'] = null;
            }
        } else {
            $condition['min'] = $minValue ?? ($existing['min'] ?? '0');
            $condition['max'] = $maxValue;
        }

        if ('distance' === $type) {
            $condition['unit'] = $existing['unit'] ?? ($condition['unit'] ?? 'km');
        } elseif ('weight' === $type) {
            $condition['unit'] = $existing['unit'] ?? ($condition['unit'] ?? 'kg');
        }

        if (is_array($existing)) {
            foreach ($existing as $key => $value) {
                if (!array_key_exists($key, $condition)) {
                    $condition[$key] = $value;
                }
            }
        }

        if (null !== $existingIndex) {
            $conditions[$existingIndex] = $condition;
        } else {
            $indexMap[$type] = count($conditions);
            $conditions[] = $condition;
        }
    }

    /**
     * Merges cost data while preserving unrelated adjustments.
     *
     * @param mixed $existingCosts
     *
     * @return array<string, mixed>
     */
    private function mergeCosts(array $row, $existingCosts): array
    {
        $costs = is_array($existingCosts) ? $existingCosts : Options::rule_cost_defaults();

        $costs['base'] = $row['base_cost'] ?? ($costs['base'] ?? '0');
        $costs['per_distance'] = $row['per_distance'] ?? ($costs['per_distance'] ?? '0');
        $costs['handling_fee'] = $row['handling_fee'] ?? ($costs['handling_fee'] ?? '0');

        if (null !== $row['free_over_subtotal']) {
            $costs['free_over_subtotal'] = $row['free_over_subtotal'];
        } else {
            unset($costs['free_over_subtotal']);
        }

        return $costs;
    }

    /**
     * Merges filter values.
     *
     * @param mixed $existingFilters
     *
     * @return array<int|string, mixed>
     */
    private function mergeFilters(array $row, $existingFilters)
    {
        $filters = is_array($existingFilters) ? $existingFilters : [];

        $this->applyFilterList($filters, 'include_classes', $row['include_classes']);
        $this->applyFilterList($filters, 'exclude_classes', $row['exclude_classes']);
        $this->applyFilterList($filters, 'include_categories', $row['include_categories']);
        $this->applyFilterList($filters, 'exclude_categories', $row['exclude_categories']);

        return $filters;
    }

    /**
     * Applies a list of values to a filter entry.
     *
     * @param array<int|string, mixed> $filters
     * @param array<int, string> $values
     */
    private function applyFilterList(array &$filters, string $key, array $values): void
    {
        if ([] !== $values) {
            $filters[$key] = $values;

            return;
        }

        unset($filters[$key]);
    }

    /**
     * Merges metadata values.
     *
     * @param mixed $existingMetadata
     *
     * @return array<string, mixed>
     */
    private function mergeMetadata(array $row, $existingMetadata): array
    {
        $metadata = is_array($existingMetadata) ? $existingMetadata : [];

        if ('' === $row['tax_status']) {
            unset($metadata['tax_status']);
        } else {
            $metadata['tax_status'] = $row['tax_status'];
        }

        return $metadata;
    }

    /**
     * Merges action values while preserving other flags.
     *
     * @param mixed $existingActions
     *
     * @return array<string, mixed>
     */
    private function mergeActions(array $row, $existingActions): array
    {
        $actions = is_array($existingActions) ? $existingActions : [];

        if (!array_key_exists('stop', $actions)) {
            $actions['stop'] = false;
        }

        if ($row['apply_once']) {
            $actions['apply_once'] = true;
        } else {
            unset($actions['apply_once']);
        }

        if ('' === $row['rounding']) {
            unset($actions['rounding']);
        } else {
            $actions['rounding'] = $row['rounding'];
        }

        return $actions;
    }

    /**
     * Returns a map of existing rules keyed by a normalised title.
     *
     * @param array<int, array<string, mixed>> $rules
     *
     * @return array<string, array<string, mixed>>
     */
    private function mapExistingRules(array $rules): array
    {
        $map = [];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $name = isset($rule['name']) ? $this->normaliseKey((string) $rule['name']) : '';
            if ('' === $name) {
                continue;
            }

            $map[$name] = $rule;
        }

        return $map;
    }

    /**
     * Normalises a key for lookups.
     */
    private function normaliseKey(string $value): string
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            return '';
        }

        return strtolower($trimmed);
    }

    /**
     * Formats a rule into a CSV row.
     *
     * @param array<string, mixed> $rule
     *
     * @return array<int, string>
     */
    private function mapRuleToRow(array $rule): array
    {
        $distance = $this->getCondition($rule, 'distance');
        $weight = $this->getCondition($rule, 'weight');
        $items = $this->getCondition($rule, 'items');
        $subtotal = $this->getCondition($rule, 'subtotal');

        return [
            $this->formatBool(isset($rule['enabled']) ? (bool) $rule['enabled'] : true),
            isset($rule['name']) ? (string) $rule['name'] : '',
            $this->formatNumber($distance['min'] ?? null),
            $this->formatNumber($distance['max'] ?? null),
            $this->formatNumber($this->getCost($rule, 'base')),
            $this->formatNumber($this->getCost($rule, 'per_distance')),
            $this->formatNumber($this->getCost($rule, 'free_over_subtotal')),
            $this->formatNumber($this->getCost($rule, 'handling_fee')),
            $this->getTaxStatus($rule),
            $this->formatNumber($weight['min'] ?? null),
            $this->formatNumber($weight['max'] ?? null),
            $this->formatInt($items['min'] ?? null),
            $this->formatInt($items['max'] ?? null),
            $this->formatNumber($subtotal['min'] ?? null),
            $this->formatNumber($subtotal['max'] ?? null),
            $this->formatList($this->readFilterList($rule, 'include_classes')),
            $this->formatList($this->readFilterList($rule, 'exclude_classes')),
            $this->formatList($this->readFilterList($rule, 'include_categories')),
            $this->formatList($this->readFilterList($rule, 'exclude_categories')),
            $this->formatRounding($this->readRounding($rule)),
            $this->formatBool($this->readApplyOnce($rule)),
            $this->formatPriority($rule['priority'] ?? null),
            $this->formatExtras($rule),
        ];
    }

    /**
     * Retrieves a condition by type.
     *
     * @param array<string, mixed> $rule
     *
     * @return array<string, mixed>|null
     */
    private function getCondition(array $rule, string $type): ?array
    {
        $conditions = $rule['conditions'] ?? null;
        if (!is_array($conditions)) {
            return null;
        }

        foreach ($conditions as $key => $condition) {
            if (!is_array($condition)) {
                continue;
            }

            $candidate = null;
            if (isset($condition['type']) && is_string($condition['type'])) {
                $candidate = strtolower($condition['type']);
            } elseif (is_string($key)) {
                $candidate = strtolower($key);
            }

            if ($candidate === $type) {
                return $condition;
            }
        }

        return null;
    }

    /**
     * Normalises a list of conditions to a sequential array.
     *
     * @param mixed $conditions
     *
     * @return array<int, array<string, mixed>>
     */
    private function normaliseConditionsList($conditions): array
    {
        if (!is_array($conditions)) {
            return [];
        }

        $list = [];
        foreach ($conditions as $key => $condition) {
            if (!is_array($condition)) {
                continue;
            }

            $normalised = $condition;
            if (!isset($normalised['type']) || !is_string($normalised['type']) || '' === $normalised['type']) {
                if (isset($normalised['name']) && is_string($normalised['name']) && '' !== $normalised['name']) {
                    $normalised['type'] = strtolower($normalised['name']);
                } elseif (is_string($key) && '' !== $key) {
                    $normalised['type'] = strtolower($key);
                }
            } else {
                $normalised['type'] = strtolower($normalised['type']);
            }

            $list[] = $normalised;
        }

        return $list;
    }

    /**
     * Formats numeric values for CSV output.
     *
     * @param mixed $value
     */
    private function formatNumber($value): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        if (is_string($value)) {
            $value = trim($value);
            if ('' === $value) {
                return '';
            }
            if (!is_numeric($value)) {
                return $value;
            }
            $value = (float) $value;
        } elseif (is_int($value) || is_float($value)) {
            $value = (float) $value;
        } else {
            return '';
        }

        if (abs($value) < 1e-9) {
            $value = 0.0;
        }

        $formatted = sprintf('%.14F', $value);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted;
    }

    /**
     * Formats integer-like values for CSV output.
     *
     * @param mixed $value
     */
    private function formatInt($value): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        if (is_string($value)) {
            $value = trim($value);
            if ('' === $value) {
                return '';
            }
            if (!is_numeric($value)) {
                return '';
            }
            $value = (float) $value;
        }

        if (is_float($value) || is_int($value)) {
            return (string) (int) round((float) $value);
        }

        return '';
    }

    /**
     * Formats boolean values as CSV strings.
     */
    private function formatBool(bool $value): string
    {
        return $value ? '1' : '0';
    }

    /**
     * Formats a priority value for CSV output.
     *
     * @param mixed $value
     */
    private function formatPriority($value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_numeric($value)) {
            return (string) (int) round((float) $value);
        }

        return '0';
    }

    /**
     * Formats the extras payload for CSV output.
     *
     * @param array<string, mixed> $rule
     */
    private function formatExtras(array $rule): string
    {
        $payload = $this->prepareExtrasPayload($rule);
        if ([] === $payload) {
            return '';
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return false === $json ? '' : $json;
    }

    /**
     * Builds the extras payload retained in the CSV.
     *
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    private function prepareExtrasPayload(array $rule): array
    {
        $keys = ['id', 'description', 'conditions', 'costs', 'filters', 'metadata', 'actions'];
        $payload = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $rule)) {
                $payload[$key] = $rule[$key];
            }
        }

        return $payload;
    }

    /**
     * Formats a list of values for CSV output.
     *
     * @param array<int, string> $values
     */
    private function formatList(array $values): string
    {
        if ([] === $values) {
            return '';
        }

        $clean = [];
        foreach ($values as $value) {
            $trimmed = trim((string) $value);
            if ('' === $trimmed) {
                continue;
            }
            $clean[] = $trimmed;
        }

        if ([] === $clean) {
            return '';
        }

        return implode('|', $clean);
    }

    /**
     * Formats the rounding mode.
     */
    private function formatRounding(string $rounding): string
    {
        return $rounding;
    }

    /**
     * Reads the rounding mode from a rule.
     *
     * @param array<string, mixed> $rule
     */
    private function readRounding(array $rule): string
    {
        $actions = $rule['actions'] ?? [];
        if (!is_array($actions)) {
            return '';
        }

        $value = $actions['rounding'] ?? '';
        return is_string($value) ? strtolower(trim($value)) : '';
    }

    /**
     * Determines whether the apply-once flag is enabled.
     *
     * @param array<string, mixed> $rule
     */
    private function readApplyOnce(array $rule): bool
    {
        $actions = $rule['actions'] ?? [];
        if (!is_array($actions)) {
            return false;
        }

        return isset($actions['apply_once']) && (bool) $actions['apply_once'];
    }

    /**
     * Retrieves a list filter from the rule.
     *
     * @param array<string, mixed> $rule
     * @return array<int, string>
     */
    private function readFilterList(array $rule, string $key): array
    {
        $filters = $rule['filters'] ?? [];
        if (!is_array($filters)) {
            return [];
        }

        if (isset($filters[$key]) && is_array($filters[$key])) {
            return $this->normaliseStringList($filters[$key]);
        }

        switch ($key) {
            case 'include_classes':
                if (isset($filters['classes']['include']) && is_array($filters['classes']['include'])) {
                    return $this->normaliseStringList($filters['classes']['include']);
                }
                break;
            case 'exclude_classes':
                if (isset($filters['classes']['exclude']) && is_array($filters['classes']['exclude'])) {
                    return $this->normaliseStringList($filters['classes']['exclude']);
                }
                break;
            case 'include_categories':
                if (isset($filters['categories']['include']) && is_array($filters['categories']['include'])) {
                    return $this->normaliseStringList($filters['categories']['include']);
                }
                break;
            case 'exclude_categories':
                if (isset($filters['categories']['exclude']) && is_array($filters['categories']['exclude'])) {
                    return $this->normaliseStringList($filters['categories']['exclude']);
                }
                break;
        }

        return [];
    }

    /**
     * Normalises an arbitrary list of values to trimmed unique strings.
     *
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    private function normaliseStringList(array $values): array
    {
        $list = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $trimmed = trim((string) $value);
            if ('' === $trimmed) {
                continue;
            }

            $list[] = $trimmed;
        }

        return array_values(array_unique($list));
    }

    /**
     * Retrieves a numeric cost from a rule.
     *
     * @param array<string, mixed> $rule
     * @return mixed
     */
    private function getCost(array $rule, string $key)
    {
        $costs = $rule['costs'] ?? [];
        if (!is_array($costs)) {
            return null;
        }

        return $costs[$key] ?? null;
    }

    /**
     * Retrieves the tax status metadata.
     *
     * @param array<string, mixed> $rule
     */
    private function getTaxStatus(array $rule): string
    {
        $metadata = $rule['metadata'] ?? [];
        if (!is_array($metadata)) {
            return '';
        }

        $status = $metadata['tax_status'] ?? '';
        return is_string($status) ? strtolower(trim($status)) : '';
    }

    /**
     * Parses a boolean flag from CSV.
     */
    private function parseBool(string $value, bool $default): bool
    {
        $normalized = strtolower(trim($value));
        if ('' === $normalized) {
            return $default;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'y', 'on', 'enabled'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n', 'off', 'disabled'], true)) {
            return false;
        }

        return $default;
    }

    /**
     * Parses a numeric field, allowing locale-friendly formats.
     */
    private function parseNumericField(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);
        if ('' === $trimmed) {
            return null;
        }

        if ('null' === strtolower($trimmed)) {
            return null;
        }

        $normalized = $this->normaliseNumericString($trimmed);
        if (null === $normalized) {
            throw new InvalidArgumentException(sprintf('Invalid numeric value "%s".', $value));
        }

        return $normalized;
    }

    /**
     * Parses an integer field.
     */
    private function parseIntegerField(?string $value): ?string
    {
        $numeric = $this->parseNumericField($value);
        if (null === $numeric) {
            return null;
        }

        return (string) (int) round((float) $numeric);
    }

    /**
     * Parses a list of values separated by recognised delimiters.
     *
     * @return array<int, string>
     */
    private function parseList(string $value): array
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            return [];
        }

        $delimiter = null;
        foreach (['|', ';', "\n"] as $candidate) {
            if (str_contains($trimmed, $candidate)) {
                $delimiter = $candidate;
                break;
            }
        }

        if (null === $delimiter && str_contains($trimmed, ',')) {
            $delimiter = ',';
        }

        if (null === $delimiter) {
            return [$trimmed];
        }

        $parts = array_map('trim', explode($delimiter, $trimmed));

        $result = [];
        foreach ($parts as $part) {
            if ('' === $part) {
                continue;
            }

            $result[] = $part;
        }

        return array_values(array_unique($result));
    }

    /**
     * Parses the extras column storing preserved rule data.
     *
     * @return array<int|string, mixed>
     */
    private function parseExtras(?string $value): array
    {
        if (null === $value) {
            return [];
        }

        $trimmed = trim($value);
        if ('' === $trimmed) {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new InvalidArgumentException(sprintf('Invalid extras payload "%s".', $value));
        }

        if (null === $decoded) {
            return [];
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Extras payload must decode to an array.');
        }

        return $decoded;
    }

    /**
     * Parses the tax status column.
     */
    private function parseTaxStatus(string $value): string
    {
        $trimmed = strtolower(trim($value));
        if ('' === $trimmed) {
            return '';
        }

        if (in_array($trimmed, ['inherit', 'default'], true)) {
            return 'inherit';
        }

        if (in_array($trimmed, ['taxable', 'none'], true)) {
            return $trimmed;
        }

        throw new InvalidArgumentException(sprintf('Invalid tax status "%s".', $value));
    }

    /**
     * Parses the rounding column.
     */
    private function parseRounding(string $value): string
    {
        $trimmed = strtolower(trim($value));
        if ('' === $trimmed || 'none' === $trimmed || 'default' === $trimmed) {
            return '';
        }

        if (in_array($trimmed, ['nearest', 'round'], true)) {
            return 'nearest';
        }

        if (in_array($trimmed, ['up', 'ceil', 'ceiling'], true)) {
            return 'up';
        }

        if (in_array($trimmed, ['down', 'floor'], true)) {
            return 'down';
        }

        throw new InvalidArgumentException(sprintf('Invalid rounding mode "%s".', $value));
    }

    /**
     * Normalises numeric strings using the same rules as Options.
     */
    private function normaliseNumericString(string $value): ?string
    {
        $normalized = str_replace(["\u{00A0}", ' '], '', $value);
        $lastDot = strrpos($normalized, '.');
        $lastComma = strrpos($normalized, ',');

        if (false !== $lastDot && false !== $lastComma) {
            if ($lastDot > $lastComma) {
                $normalized = str_replace(',', '', $normalized);
            } else {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            }
        } elseif (false !== $lastComma) {
            $normalized = str_replace(',', '.', $normalized);
        }

        $normalized = str_replace(["'", '`'], '', $normalized);

        if (substr_count($normalized, '.') > 1) {
            $parts = explode('.', $normalized);
            $lastPart = array_pop($parts);
            $normalized = implode('', $parts) . '.' . $lastPart;
        }

        $normalized = preg_replace('/[^0-9\.\-]+/', '', $normalized) ?? '';

        if ('' === $normalized || '-' === $normalized || '.' === $normalized || '-.' === $normalized) {
            return null;
        }

        return is_numeric($normalized) ? $normalized : null;
    }

    /**
     * Converts a numeric string into an integer string.
     */
    private function normaliseIntegerString(string $value): string
    {
        if (!is_numeric($value)) {
            return '0';
        }

        return (string) (int) round((float) $value);
    }

    /**
     * Strips a UTF-8 BOM if present.
     */
    private function stripBom(string $value): string
    {
        if (0 === strncmp($value, "\xEF\xBB\xBF", 3)) {
            return substr($value, 3) ?: '';
        }

        return $value;
    }
}

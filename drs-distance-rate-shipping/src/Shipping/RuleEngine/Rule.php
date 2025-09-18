<?php

declare(strict_types=1);

namespace DRS\DistanceRateShipping\Shipping\RuleEngine;

/**
 * Representation of a distance rate rule.
 *
 * The rule data intentionally accepts a very flexible schema so that the
 * calculators and matchers used in the unit tests can be fed with either the
 * simplified arrays built in the tests or with the associative structures that
 * would come from the plugin's UI.  Only a small subset of the data is used by
 * the tests but keeping the implementation tolerant makes the class future
 * proof.
 *
 * @phpstan-type Adjustment array{
 *     type: string,
 *     amount: float,
 *     mode: string,
 *     targets: list<string|null>,
 *     min: float|null,
 *     max: float|null
 * }
 */
class Rule
{
    private const FLOAT_TOLERANCE = 1e-9;

    /**
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getId(): ?string
    {
        $value = $this->getScalarValue([
            ['id'],
            ['rule_id'],
            ['identifier'],
        ]);

        return null === $value ? null : (string) $value;
    }

    public function getPriority(): int
    {
        $value = $this->getNumericValue([
            ['priority'],
            ['prio'],
            ['order'],
            ['sort'],
            ['sort_order'],
        ]);

        return null === $value ? 0 : (int) round($value);
    }

    public function getOrder(): int
    {
        $value = $this->getNumericValue([
            ['sort_order'],
            ['order'],
            ['sequence'],
        ]);

        return null === $value ? 0 : (int) round($value);
    }

    public function getDistanceMin(): ?float
    {
        return $this->getNumericValue([
            ['distance_min'],
            ['min_distance'],
            ['distance', 'min'],
            ['distance', 'from'],
            ['predicates', 'distance', 'min'],
            ['predicates', 'distance', 'from'],
        ]);
    }

    public function getDistanceMax(): ?float
    {
        return $this->getNumericValue([
            ['distance_max'],
            ['max_distance'],
            ['distance', 'max'],
            ['distance', 'to'],
            ['predicates', 'distance', 'max'],
            ['predicates', 'distance', 'to'],
        ]);
    }

    public function getWeightMin(): ?float
    {
        return $this->getNumericValue([
            ['weight_min'],
            ['min_weight'],
            ['weight', 'min'],
            ['weight', 'from'],
            ['predicates', 'weight', 'min'],
            ['predicates', 'weight', 'from'],
        ]);
    }

    public function getWeightMax(): ?float
    {
        return $this->getNumericValue([
            ['weight_max'],
            ['max_weight'],
            ['weight', 'max'],
            ['weight', 'to'],
            ['predicates', 'weight', 'max'],
            ['predicates', 'weight', 'to'],
        ]);
    }

    public function getItemsMin(): ?int
    {
        $value = $this->getNumericValue([
            ['items_min'],
            ['min_items'],
            ['quantity', 'min'],
            ['items', 'min'],
            ['predicates', 'items', 'min'],
            ['predicates', 'items', 'from'],
        ]);

        return null === $value ? null : (int) ceil($value);
    }

    public function getItemsMax(): ?int
    {
        $value = $this->getNumericValue([
            ['items_max'],
            ['max_items'],
            ['quantity', 'max'],
            ['items', 'max'],
            ['predicates', 'items', 'max'],
            ['predicates', 'items', 'to'],
        ]);

        return null === $value ? null : (int) floor($value);
    }

    public function getSubtotalMin(): ?float
    {
        return $this->getNumericValue([
            ['subtotal_min'],
            ['min_subtotal'],
            ['subtotal', 'min'],
            ['subtotal', 'from'],
            ['predicates', 'subtotal', 'min'],
            ['predicates', 'subtotal', 'from'],
        ]);
    }

    public function getSubtotalMax(): ?float
    {
        return $this->getNumericValue([
            ['subtotal_max'],
            ['max_subtotal'],
            ['subtotal', 'max'],
            ['subtotal', 'to'],
            ['predicates', 'subtotal', 'max'],
            ['predicates', 'subtotal', 'to'],
        ]);
    }

    /**
     * @return list<string>
     */
    public function getIncludeClasses(): array
    {
        return $this->getListValue([
            ['include_classes'],
            ['classes', 'include'],
            ['predicates', 'classes', 'include'],
            ['conditions', 'classes', 'include'],
        ]);
    }

    /**
     * @return list<string>
     */
    public function getExcludeClasses(): array
    {
        return $this->getListValue([
            ['exclude_classes'],
            ['classes', 'exclude'],
            ['predicates', 'classes', 'exclude'],
            ['conditions', 'classes', 'exclude'],
        ]);
    }

    /**
     * @return list<string>
     */
    public function getIncludeCategories(): array
    {
        return $this->getListValue([
            ['include_categories'],
            ['categories', 'include'],
            ['predicates', 'categories', 'include'],
            ['conditions', 'categories', 'include'],
        ]);
    }

    /**
     * @return list<string>
     */
    public function getExcludeCategories(): array
    {
        return $this->getListValue([
            ['exclude_categories'],
            ['categories', 'exclude'],
            ['predicates', 'categories', 'exclude'],
            ['conditions', 'categories', 'exclude'],
        ]);
    }

    public function getClassMatchMode(): string
    {
        $mode = $this->getScalarValue([
            ['classes', 'mode'],
            ['classes', 'match'],
            ['predicates', 'classes', 'mode'],
            ['predicates', 'classes', 'match'],
            ['conditions', 'classes', 'mode'],
        ]);

        if (null === $mode) {
            return 'any';
        }

        $mode = strtolower((string) $mode);

        if ('all' === $mode || 'any' === $mode || 'one' === $mode) {
            return $mode;
        }

        if ('require-all' === $mode || 'match-all' === $mode) {
            return 'all';
        }

        return 'any';
    }

    public function getCategoryMatchMode(): string
    {
        $mode = $this->getScalarValue([
            ['categories', 'mode'],
            ['categories', 'match'],
            ['predicates', 'categories', 'mode'],
            ['predicates', 'categories', 'match'],
            ['conditions', 'categories', 'mode'],
        ]);

        if (null === $mode) {
            return 'any';
        }

        $mode = strtolower((string) $mode);

        if ('all' === $mode || 'any' === $mode || 'one' === $mode) {
            return $mode;
        }

        if ('require-all' === $mode || 'match-all' === $mode) {
            return 'all';
        }

        return 'any';
    }

    public function getBaseCost(): float
    {
        $value = $this->getNumericValue([
            ['base_cost'],
            ['base'],
            ['cost'],
            ['charges', 'base'],
            ['pricing', 'base'],
        ]);

        return null === $value ? 0.0 : $value;
    }

    public function getPerDistance(): float
    {
        $value = $this->getNumericValue([
            ['per_distance'],
            ['distance_rate'],
            ['per_km'],
            ['per_distance_unit'],
            ['charges', 'per_distance'],
            ['pricing', 'per_distance'],
        ]);

        return null === $value ? 0.0 : $value;
    }

    public function getPerItem(): float
    {
        $value = $this->getNumericValue([
            ['per_item'],
            ['item_rate'],
            ['per_product'],
            ['charges', 'per_item'],
            ['pricing', 'per_item'],
        ]);

        return null === $value ? 0.0 : $value;
    }

    public function getPerWeight(): float
    {
        $value = $this->getNumericValue([
            ['per_weight'],
            ['weight_rate'],
            ['per_kg'],
            ['per_weight_unit'],
            ['charges', 'per_weight'],
            ['pricing', 'per_weight'],
        ]);

        return null === $value ? 0.0 : $value;
    }

    public function getMinCost(): ?float
    {
        return $this->getNumericValue([
            ['min_cost'],
            ['minimum_cost'],
            ['limits', 'min'],
            ['pricing', 'min'],
        ]);
    }

    public function getMaxCost(): ?float
    {
        return $this->getNumericValue([
            ['max_cost'],
            ['maximum_cost'],
            ['limits', 'max'],
            ['pricing', 'max'],
        ]);
    }

    public function getRoundPrecision(): ?int
    {
        $value = $this->getNumericValue([
            ['round_precision'],
            ['round_decimals'],
            ['round'],
            ['rounding', 'precision'],
            ['rounding', 'decimals'],
        ]);

        if (null === $value) {
            return null;
        }

        $value = (int) round($value);

        return $value >= 0 ? $value : null;
    }

    public function getRoundStep(): ?float
    {
        $value = $this->getNumericValue([
            ['round_to'],
            ['round_step'],
            ['rounding', 'step'],
            ['rounding', 'to'],
        ]);

        if (null === $value) {
            return null;
        }

        return $value > 0 ? $value : null;
    }

    public function getRoundMode(): string
    {
        $mode = $this->getScalarValue([
            ['round_mode'],
            ['rounding', 'mode'],
            ['rounding', 'direction'],
        ]);

        if (null === $mode) {
            return 'nearest';
        }

        $mode = strtolower((string) $mode);

        if (in_array($mode, ['nearest', 'up', 'down', 'ceil', 'ceiling', 'floor'], true)) {
            return $mode;
        }

        return 'nearest';
    }

    public function getFreeOverSubtotal(): ?float
    {
        return $this->getNumericValue([
            ['free_over_subtotal'],
            ['free_shipping_over'],
            ['free_over'],
            ['free_if_subtotal_exceeds'],
            ['charges', 'free_over_subtotal'],
        ]);
    }

    /**
     * @return list<Adjustment>
     */
    public function getAdjustments(): array
    {
        $adjustments = [];

        foreach ([
            ['adjustments'],
            ['charges', 'adjustments'],
            ['surcharges'],
            ['fees'],
        ] as $path) {
            $value = $this->getValueFromPath($path, $found);
            if ($found) {
                $adjustments = array_merge($adjustments, $this->collectAdjustments($value, 'flat'));
            }
        }

        foreach ([
            ['class_adjustments'],
            ['adjustments', 'classes'],
            ['charges', 'class_adjustments'],
            ['fees', 'classes'],
        ] as $path) {
            $value = $this->getValueFromPath($path, $found);
            if ($found) {
                $adjustments = array_merge($adjustments, $this->collectAdjustments($value, 'class'));
            }
        }

        foreach ([
            ['category_adjustments'],
            ['adjustments', 'categories'],
            ['charges', 'category_adjustments'],
            ['fees', 'categories'],
        ] as $path) {
            $value = $this->getValueFromPath($path, $found);
            if ($found) {
                $adjustments = array_merge($adjustments, $this->collectAdjustments($value, 'category'));
            }
        }

        return array_values($adjustments);
    }

    public function getSpecificityScore(): int
    {
        $score = 0;

        foreach ([
            $this->getDistanceMin(),
            $this->getDistanceMax(),
            $this->getWeightMin(),
            $this->getWeightMax(),
            $this->getSubtotalMin(),
            $this->getSubtotalMax(),
        ] as $value) {
            if (null !== $value) {
                ++$score;
            }
        }

        foreach ([
            $this->getItemsMin(),
            $this->getItemsMax(),
        ] as $value) {
            if (null !== $value) {
                ++$score;
            }
        }

        if ([] !== $this->getIncludeClasses()) {
            $score += 2;
        }

        if ([] !== $this->getExcludeClasses()) {
            ++$score;
        }

        if ([] !== $this->getIncludeCategories()) {
            $score += 2;
        }

        if ([] !== $this->getExcludeCategories()) {
            ++$score;
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $cartCtx
     */
    public function matches(array $cartCtx, float $distanceKm): bool
    {
        $distanceMin = $this->getDistanceMin();
        if (null !== $distanceMin && $distanceKm + self::FLOAT_TOLERANCE < $distanceMin) {
            return false;
        }

        $distanceMax = $this->getDistanceMax();
        if (null !== $distanceMax && $distanceKm - self::FLOAT_TOLERANCE > $distanceMax) {
            return false;
        }

        $weight = self::cartWeight($cartCtx);
        $weightMin = $this->getWeightMin();
        if (null !== $weightMin && $weight + self::FLOAT_TOLERANCE < $weightMin) {
            return false;
        }

        $weightMax = $this->getWeightMax();
        if (null !== $weightMax && $weight - self::FLOAT_TOLERANCE > $weightMax) {
            return false;
        }

        $items = self::cartItemCount($cartCtx);
        $itemsMin = $this->getItemsMin();
        if (null !== $itemsMin && $items < $itemsMin) {
            return false;
        }

        $itemsMax = $this->getItemsMax();
        if (null !== $itemsMax && $items > $itemsMax) {
            return false;
        }

        $subtotal = self::cartSubtotal($cartCtx);
        $subtotalMin = $this->getSubtotalMin();
        if (null !== $subtotalMin && $subtotal + self::FLOAT_TOLERANCE < $subtotalMin) {
            return false;
        }

        $subtotalMax = $this->getSubtotalMax();
        if (null !== $subtotalMax && $subtotal - self::FLOAT_TOLERANCE > $subtotalMax) {
            return false;
        }

        $classes = self::cartClasses($cartCtx);
        $includeClasses = $this->getIncludeClasses();
        if ([] !== $includeClasses) {
            $mode = $this->getClassMatchMode();
            if ('all' === $mode) {
                if (!self::listContainsAll($classes, $includeClasses)) {
                    return false;
                }
            } else {
                if (!self::listsIntersect($classes, $includeClasses)) {
                    return false;
                }
            }
        }

        $excludeClasses = $this->getExcludeClasses();
        if ([] !== $excludeClasses && self::listsIntersect($classes, $excludeClasses)) {
            return false;
        }

        $categories = self::cartCategories($cartCtx);
        $includeCategories = $this->getIncludeCategories();
        if ([] !== $includeCategories) {
            $mode = $this->getCategoryMatchMode();
            if ('all' === $mode) {
                if (!self::listContainsAll($categories, $includeCategories)) {
                    return false;
                }
            } else {
                if (!self::listsIntersect($categories, $includeCategories)) {
                    return false;
                }
            }
        }

        $excludeCategories = $this->getExcludeCategories();
        if ([] !== $excludeCategories && self::listsIntersect($categories, $excludeCategories)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $cartCtx
     * @return list<array{
     *     quantity: float,
     *     weight: float,
     *     subtotal: float,
     *     classes: list<string>,
     *     categories: list<string>
     * }>
     */
    public static function cartItems(array $cartCtx): array
    {
        $items = $cartCtx['items'] ?? $cartCtx['contents'] ?? $cartCtx['lines'] ?? $cartCtx['line_items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        if (self::isAssoc($items)) {
            $items = array_values($items);
        }

        $normalised = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $quantity = self::arrayNumeric($item, ['quantity', 'qty', 'count', 'items'], 1.0);
            if ($quantity <= 0) {
                $quantity = 0.0;
            }

            $lineWeight = self::arrayNumericOrNull($item, ['line_weight', 'total_weight']);
            if (null === $lineWeight) {
                $perItemWeight = self::arrayNumeric($item, ['weight', 'weight_kg', 'weight_g', 'weight_lb'], 0.0);
                if (isset($item['weight_lb']) && is_numeric($item['weight_lb'])) {
                    $perItemWeight = (float) $item['weight_lb'] * 0.45359237;
                }
                $lineWeight = $perItemWeight * $quantity;
            }

            $lineSubtotal = self::arrayNumericOrNull($item, ['line_subtotal', 'subtotal', 'line_total', 'total']);
            if (null === $lineSubtotal) {
                $price = self::arrayNumeric($item, ['price', 'cost', 'unit_price'], 0.0);
                $lineSubtotal = $price * $quantity;
            }

            $classes = self::normalizeList([
                $item['classes'] ?? null,
                $item['class'] ?? null,
                $item['class_id'] ?? null,
                $item['class_ids'] ?? null,
                $item['shipping_class'] ?? null,
                $item['shipping_class_id'] ?? null,
                $item['shipping_class_slug'] ?? null,
            ]);

            $categories = self::normalizeList([
                $item['categories'] ?? null,
                $item['category'] ?? null,
                $item['category_id'] ?? null,
                $item['category_ids'] ?? null,
                $item['cat'] ?? null,
                $item['cat_ids'] ?? null,
            ]);

            $normalised[] = [
                'quantity' => $quantity,
                'weight' => $lineWeight,
                'subtotal' => $lineSubtotal,
                'classes' => $classes,
                'categories' => $categories,
            ];
        }

        return $normalised;
    }

    /**
     * @param array<string, mixed> $cartCtx
     */
    public static function cartWeight(array $cartCtx): float
    {
        $items = self::cartItems($cartCtx);
        $weight = 0.0;

        foreach ($items as $item) {
            $weight += (float) $item['weight'];
        }

        if ($weight > 0) {
            return $weight;
        }

        if (isset($cartCtx['total_weight']) && is_numeric($cartCtx['total_weight'])) {
            return (float) $cartCtx['total_weight'];
        }

        if (isset($cartCtx['weight']) && is_numeric($cartCtx['weight'])) {
            return (float) $cartCtx['weight'];
        }

        if (isset($cartCtx['weight_kg']) && is_numeric($cartCtx['weight_kg'])) {
            return (float) $cartCtx['weight_kg'];
        }

        if (isset($cartCtx['weight_lb']) && is_numeric($cartCtx['weight_lb'])) {
            return (float) $cartCtx['weight_lb'] * 0.45359237;
        }

        return 0.0;
    }

    /**
     * @param array<string, mixed> $cartCtx
     */
    public static function cartItemCount(array $cartCtx): int
    {
        $items = self::cartItems($cartCtx);
        $count = 0.0;
        foreach ($items as $item) {
            $count += $item['quantity'];
        }

        if ($count > 0) {
            return (int) round($count);
        }

        foreach (['item_count', 'items_count', 'items', 'quantity', 'qty'] as $key) {
            if (isset($cartCtx[$key]) && !is_array($cartCtx[$key]) && is_numeric($cartCtx[$key])) {
                return (int) round((float) $cartCtx[$key]);
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $cartCtx
     */
    public static function cartSubtotal(array $cartCtx): float
    {
        foreach (['subtotal', 'subtotal_ex_tax', 'cart_subtotal', 'total'] as $key) {
            if (isset($cartCtx[$key]) && !is_array($cartCtx[$key]) && is_numeric($cartCtx[$key])) {
                return (float) $cartCtx[$key];
            }
        }

        $items = self::cartItems($cartCtx);
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) $item['subtotal'];
        }

        return $subtotal;
    }

    /**
     * @param array<string, mixed> $cartCtx
     * @return list<string>
     */
    public static function cartClasses(array $cartCtx): array
    {
        $classes = [];
        $items = self::cartItems($cartCtx);
        foreach ($items as $item) {
            $classes = array_merge($classes, $item['classes']);
        }

        $classes = array_merge(
            $classes,
            self::normalizeList([
                $cartCtx['classes'] ?? null,
                $cartCtx['class_ids'] ?? null,
                $cartCtx['shipping_classes'] ?? null,
                $cartCtx['shipping_class_ids'] ?? null,
            ])
        );

        return self::uniqueList($classes);
    }

    /**
     * @param array<string, mixed> $cartCtx
     * @return list<string>
     */
    public static function cartCategories(array $cartCtx): array
    {
        $categories = [];
        $items = self::cartItems($cartCtx);
        foreach ($items as $item) {
            $categories = array_merge($categories, $item['categories']);
        }

        $categories = array_merge(
            $categories,
            self::normalizeList([
                $cartCtx['categories'] ?? null,
                $cartCtx['category_ids'] ?? null,
                $cartCtx['cat_ids'] ?? null,
            ])
        );

        return self::uniqueList($categories);
    }

    /**
     * @param list<string> $haystack
     * @param list<string> $needles
     */
    private static function listContainsAll(array $haystack, array $needles): bool
    {
        if ([] === $needles) {
            return true;
        }

        $haystack = array_map('strval', $haystack);
        $haystack = array_flip($haystack);

        foreach ($needles as $needle) {
            if (!isset($haystack[(string) $needle])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function listsIntersect(array $left, array $right): bool
    {
        if ([] === $left || [] === $right) {
            return false;
        }

        $leftMap = array_flip(array_map('strval', $left));
        foreach ($right as $value) {
            if (isset($leftMap[(string) $value])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function normalizeList($value): array
    {
        $items = [];
        if (is_array($value)) {
            foreach ($value as $key => $entry) {
                if (is_array($entry)) {
                    if (isset($entry['id'])) {
                        $items[] = (string) $entry['id'];
                    } elseif (isset($entry['value'])) {
                        $items[] = (string) $entry['value'];
                    } elseif (isset($entry['slug'])) {
                        $items[] = (string) $entry['slug'];
                    } elseif (isset($entry['name'])) {
                        $items[] = (string) $entry['name'];
                    } else {
                        $items[] = is_string($key) ? (string) $key : (string) json_encode($entry);
                    }
                } elseif (null !== $entry && '' !== $entry) {
                    $items[] = (string) $entry;
                } elseif (is_string($key) && '' !== $key) {
                    $items[] = $key;
                }
            }
        } elseif (is_string($value)) {
            $parts = preg_split('/[|,]/', $value) ?: [];
            foreach ($parts as $part) {
                $part = trim($part);
                if ('' !== $part) {
                    $items[] = $part;
                }
            }
        } elseif (is_numeric($value)) {
            $items[] = (string) $value;
        }

        return self::uniqueList($items);
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function uniqueList(array $values): array
    {
        if ([] === $values) {
            return [];
        }

        $values = array_values(array_map('strval', $values));

        return array_values(array_unique($values));
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $keys
     */
    private static function arrayNumeric(array $source, array $keys, float $default): float
    {
        $value = self::arrayNumericOrNull($source, $keys);
        if (null === $value) {
            return $default;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $keys
     */
    private static function arrayNumericOrNull(array $source, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_numeric($source[$key])) {
                return (float) $source[$key];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function isAssoc(array $source): bool
    {
        if ([] === $source) {
            return false;
        }

        return array_keys($source) !== range(0, count($source) - 1);
    }

    /**
     * @param list<string> $path
     * @return mixed
     */
    private function getValueFromPath(array $path, ?bool &$found = null)
    {
        $found = false;
        $value = $this->data;
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        $found = true;

        return $value;
    }

    /**
     * @param list<list<string>> $paths
     */
    private function getNumericValue(array $paths): ?float
    {
        foreach ($paths as $path) {
            $value = $this->getValueFromPath($path, $found);
            if ($found && null !== $value && '' !== $value && is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    /**
     * @param list<list<string>> $paths
     */
    private function getScalarValue(array $paths)
    {
        foreach ($paths as $path) {
            $value = $this->getValueFromPath($path, $found);
            if ($found && null !== $value && '' !== $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param list<list<string>> $paths
     * @return list<string>
     */
    private function getListValue(array $paths): array
    {
        foreach ($paths as $path) {
            $value = $this->getValueFromPath($path, $found);
            if ($found) {
                return self::normalizeList($value);
            }
        }

        return [];
    }

    /**
     * @param mixed $value
     * @param string|null $defaultTarget
     * @return list<Adjustment>
     */
    private function collectAdjustments($value, string $defaultType, ?string $defaultTarget = null): array
    {
        $result = [];

        if (null === $value || '' === $value) {
            return $result;
        }

        if (is_array($value) && self::isAssoc($value)) {
            if (array_key_exists('type', $value) || array_key_exists('amount', $value) || array_key_exists('value', $value)) {
                $normalized = $this->normalizeAdjustment($value, $defaultType, $defaultTarget);
                if (null !== $normalized) {
                    $result[] = $normalized;
                }

                return $result;
            }

            foreach ($value as $key => $entry) {
                $type = $defaultType;
                $target = is_string($key) ? $key : $defaultTarget;

                if ('flat' === $defaultType) {
                    if ('classes' === $key) {
                        $type = 'class';
                        $target = null;
                    } elseif ('categories' === $key) {
                        $type = 'category';
                        $target = null;
                    } elseif ('flat' === $key || 'general' === $key) {
                        $type = 'flat';
                        $target = null;
                    }
                }

                $result = array_merge($result, $this->collectAdjustments($entry, $type, $target));
            }

            return $result;
        }

        if (is_array($value)) {
            foreach ($value as $entry) {
                $result = array_merge($result, $this->collectAdjustments($entry, $defaultType, $defaultTarget));
            }

            return $result;
        }

        $normalized = $this->normalizeAdjustment($value, $defaultType, $defaultTarget);
        if (null !== $normalized) {
            $result[] = $normalized;
        }

        return $result;
    }

    /**
     * @param mixed $value
     */
    private function normalizeAdjustment($value, string $defaultType, ?string $defaultTarget): ?array
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!is_array($value)) {
            if (!is_numeric($value)) {
                return null;
            }

            $amount = (float) $value;

            return [
                'type' => $defaultType,
                'amount' => $amount,
                'mode' => 'flat',
                'targets' => 'flat' === $defaultType ? [] : self::normalizeList($defaultTarget),
                'min' => null,
                'max' => null,
            ];
        }

        $type = strtolower((string) ($value['type'] ?? $defaultType));
        if ('classes' === $type) {
            $type = 'class';
        } elseif ('categories' === $type) {
            $type = 'category';
        }

        $amount = 0.0;
        foreach (['amount', 'value', 'cost', 'fee'] as $key) {
            if (isset($value[$key]) && is_numeric($value[$key])) {
                $amount = (float) $value[$key];
                break;
            }
        }

        $targets = $value['targets'] ?? $value['target'] ?? $value['ids'] ?? $value['id'] ?? $value['class'] ?? $value['category'] ?? $defaultTarget;
        if ('flat' === $type) {
            $targets = [];
        } else {
            $targets = self::normalizeList($targets);
            if ([] === $targets) {
                $targets = self::normalizeList($defaultTarget);
            }
            if ([] === $targets) {
                $targets = [null];
            }
        }

        $mode = strtolower((string) ($value['mode'] ?? $value['calculation'] ?? $value['basis'] ?? 'flat'));
        if (!empty($value['per_item']) || 'item' === $mode) {
            $mode = 'per_item';
        } elseif (!empty($value['per_weight']) || 'weight' === $mode) {
            $mode = 'per_weight';
        } elseif (!empty($value['per_distance']) || 'distance' === $mode) {
            $mode = 'per_distance';
        } elseif (!empty($value['percent']) || !empty($value['percentage']) || 'percent' === $mode || 'percentage' === $mode) {
            $mode = 'percent_subtotal';
        } elseif ('per_item' !== $mode && 'per_weight' !== $mode && 'per_distance' !== $mode && 'percent_subtotal' !== $mode) {
            $mode = 'flat';
        }

        $min = isset($value['min']) && is_numeric($value['min']) ? (float) $value['min'] : null;
        $max = isset($value['max']) && is_numeric($value['max']) ? (float) $value['max'] : null;

        return [
            'type' => $type,
            'amount' => $amount,
            'mode' => $mode,
            'targets' => $targets,
            'min' => $min,
            'max' => $max,
        ];
    }
}

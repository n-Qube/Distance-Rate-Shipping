<?php

declare(strict_types=1);

namespace DRS\DistanceRateShipping\Shipping\RuleEngine;

/**
 * Calculates shipping totals for a matched rule.
 */
class CostCalculator
{
    private const EPSILON = 1e-9;

    /**
     * @param array<string, mixed> $cartCtx
     */
    public function total(Rule $rule, array $cartCtx, float $distanceKm): float
    {
        $distanceKm = max(0.0, $distanceKm);
        $subtotal = Rule::cartSubtotal($cartCtx);
        $itemCount = Rule::cartItemCount($cartCtx);
        $weight = Rule::cartWeight($cartCtx);

        $freeThreshold = $rule->getFreeOverSubtotal();
        if (null !== $freeThreshold && $subtotal >= $freeThreshold - self::EPSILON) {
            return 0.0;
        }

        $total = $rule->getBaseCost();
        $total += $rule->getPerDistance() * $distanceKm;
        $total += $rule->getPerItem() * $itemCount;
        $total += $rule->getPerWeight() * $weight;

        $total += $this->calculateAdjustments($rule, $cartCtx, $distanceKm, $subtotal, $itemCount, $weight);

        $minCost = $rule->getMinCost();
        if (null !== $minCost && $total < $minCost) {
            $total = $minCost;
        }

        $maxCost = $rule->getMaxCost();
        if (null !== $maxCost && $total > $maxCost) {
            $total = $maxCost;
        }

        $total = $this->roundTotal($total, $rule);

        if ($total < 0) {
            $total = 0.0;
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $cartCtx
     */
    private function calculateAdjustments(Rule $rule, array $cartCtx, float $distanceKm, float $subtotal, float $itemCount, float $weight): float
    {
        $items = Rule::cartItems($cartCtx);
        $classes = Rule::cartClasses($cartCtx);
        $categories = Rule::cartCategories($cartCtx);

        $total = 0.0;

        foreach ($rule->getAdjustments() as $adjustment) {
            $type = $adjustment['type'] ?? 'flat';
            $amount = (float) ($adjustment['amount'] ?? 0.0);
            $mode = isset($adjustment['mode']) ? (string) $adjustment['mode'] : 'flat';
            $targets = $adjustment['targets'] ?? [];
            if (!is_array($targets)) {
                $targets = [$targets];
            }

            $value = 0.0;
            if ('class' === $type) {
                $value = $this->applyTargetedAdjustment($targets, $mode, $amount, $items, 'classes', $classes, $distanceKm, $subtotal, $itemCount, $weight);
            } elseif ('category' === $type) {
                $value = $this->applyTargetedAdjustment($targets, $mode, $amount, $items, 'categories', $categories, $distanceKm, $subtotal, $itemCount, $weight);
            } else {
                $value = $this->resolveAdjustmentAmount($mode, $amount, $itemCount, $weight, $distanceKm, $subtotal, $subtotal);
            }

            $min = $adjustment['min'] ?? null;
            if (is_numeric($min) && $value < (float) $min) {
                $value = (float) $min;
            }

            $max = $adjustment['max'] ?? null;
            if (is_numeric($max) && $value > (float) $max) {
                $value = (float) $max;
            }

            $total += $value;
        }

        return $total;
    }

    /**
     * @param list<array{quantity: float, weight: float, subtotal: float, classes: list<string>, categories: list<string>}> $items
     * @param list<string> $fallbackList
     * @param list<string|null> $targets
     */
    private function applyTargetedAdjustment(array $targets, string $mode, float $amount, array $items, string $targetType, array $fallbackList, float $distanceKm, float $cartSubtotal, float $cartItemCount, float $cartWeight): float
    {
        if ([] === $targets) {
            $targets = [null];
        }

        $total = 0.0;

        foreach ($targets as $target) {
            $stats = $this->collectTargetStats($items, $targetType, $target, $fallbackList, $cartSubtotal, $cartItemCount, $cartWeight);
            if (!$stats['matched']) {
                continue;
            }

            $total += $this->resolveAdjustmentAmount($mode, $amount, $stats['item_count'], $stats['weight'], $distanceKm, $stats['subtotal'], $cartSubtotal);
        }

        return $total;
    }

    /**
     * @param list<array{quantity: float, weight: float, subtotal: float, classes: list<string>, categories: list<string>}> $items
     * @param list<string> $fallbackList
     * @return array{matched: bool, item_count: float, weight: float, subtotal: float}
     */
    private function collectTargetStats(array $items, string $targetType, ?string $target, array $fallbackList, float $cartSubtotal, float $cartItemCount, float $cartWeight): array
    {
        $matched = false;
        $itemCount = 0.0;
        $weight = 0.0;
        $subtotal = 0.0;

        foreach ($items as $item) {
            $haystack = 'classes' === $targetType ? $item['classes'] : $item['categories'];
            if (null === $target || in_array((string) $target, $haystack, true)) {
                $matched = true;
                $itemCount += $item['quantity'];
                $weight += $item['weight'];
                $subtotal += $item['subtotal'];
            }
        }

        if (!$matched) {
            if (null === $target) {
                if ([] !== $items || [] !== $fallbackList) {
                    $matched = true;
                    $itemCount = $cartItemCount;
                    $weight = $cartWeight;
                    $subtotal = $cartSubtotal;
                }
            } elseif (in_array((string) $target, $fallbackList, true)) {
                $matched = true;
                $itemCount = $cartItemCount;
                $weight = $cartWeight;
                $subtotal = $cartSubtotal;
            }
        }

        return [
            'matched' => $matched,
            'item_count' => $itemCount,
            'weight' => $weight,
            'subtotal' => $subtotal,
        ];
    }

    private function resolveAdjustmentAmount(string $mode, float $amount, float $itemCount, float $weight, float $distanceKm, float $targetSubtotal, float $cartSubtotal): float
    {
        switch ($mode) {
            case 'per_item':
                return $amount * $itemCount;
            case 'per_weight':
                return $amount * $weight;
            case 'per_distance':
                return $amount * $distanceKm;
            case 'percent_subtotal':
            case 'percent':
            case 'percentage':
                $percent = $amount;
                if (abs($percent) > 1.0) {
                    $percent /= 100.0;
                }
                $base = $targetSubtotal > 0 ? $targetSubtotal : $cartSubtotal;
                return $base * $percent;
            default:
                return $amount;
        }
    }

    private function roundTotal(float $value, Rule $rule): float
    {
        $mode = $rule->getRoundMode();
        $precision = $rule->getRoundPrecision();
        $step = $rule->getRoundStep();

        if (null !== $precision) {
            $value = $this->roundToPrecision($value, $precision, $mode);
        }

        if (null !== $step) {
            $value = $this->roundToStep($value, $step, $mode);
        }

        return $value;
    }

    private function roundToPrecision(float $value, int $precision, string $mode): float
    {
        $factor = 10 ** max(0, $precision);
        return $this->roundScaled($value, $factor, $mode);
    }

    private function roundToStep(float $value, float $step, string $mode): float
    {
        if ($step <= 0) {
            return $value;
        }

        $factor = 1 / $step;
        $rounded = $this->roundScaled($value, $factor, $mode);

        return $rounded;
    }

    private function roundScaled(float $value, float $factor, string $mode): float
    {
        if (0.0 === $factor) {
            return $value;
        }

        $scaled = $value * $factor;

        switch ($mode) {
            case 'up':
            case 'ceil':
            case 'ceiling':
                $scaled = ceil($scaled - self::EPSILON);
                break;
            case 'down':
            case 'floor':
                $scaled = floor($scaled + self::EPSILON);
                break;
            default:
                $scaled = round($scaled);
                break;
        }

        return $scaled / $factor;
    }
}

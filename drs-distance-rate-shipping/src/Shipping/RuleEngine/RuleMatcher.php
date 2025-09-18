<?php

declare(strict_types=1);

namespace DRS\DistanceRateShipping\Shipping\RuleEngine;

/**
 * Determines which rule applies to a given cart context.
 */
class RuleMatcher
{
    /**
     * @var list<Rule>
     */
    private array $rules = [];

    /**
     * @param iterable<Rule|array<string, mixed>> $rules
     */
    public function __construct(iterable $rules = [])
    {
        foreach ($rules as $rule) {
            $this->addRule($rule);
        }
    }

    /**
     * @param Rule|array<string, mixed> $rule
     */
    public function addRule($rule): void
    {
        if (!$rule instanceof Rule) {
            $rule = new Rule($rule);
        }

        $this->rules[] = $rule;
    }

    /**
     * @return list<Rule>
     */
    public function all(): array
    {
        return $this->rules;
    }

    /**
     * @param array<string, mixed> $cartCtx
     */
    public function match(array $cartCtx, float $distanceKm): ?Rule
    {
        if ([] === $this->rules) {
            return null;
        }

        $matching = [];
        foreach ($this->rules as $rule) {
            if ($rule->matches($cartCtx, $distanceKm)) {
                $matching[] = $rule;
            }
        }

        if ([] === $matching) {
            return null;
        }

        usort($matching, static function (Rule $left, Rule $right): int {
            $priority = $right->getPriority() <=> $left->getPriority();
            if (0 !== $priority) {
                return $priority;
            }

            $specificity = $right->getSpecificityScore() <=> $left->getSpecificityScore();
            if (0 !== $specificity) {
                return $specificity;
            }

            $order = $left->getOrder() <=> $right->getOrder();
            if (0 !== $order) {
                return $order;
            }

            $distanceMax = self::compareDistanceMax($left->getDistanceMax(), $right->getDistanceMax());
            if (0 !== $distanceMax) {
                return $distanceMax;
            }

            $distanceMin = self::compareDistanceMin($left->getDistanceMin(), $right->getDistanceMin());
            if (0 !== $distanceMin) {
                return $distanceMin;
            }

            return self::compareIds($left->getId(), $right->getId());
        });

        return $matching[0];
    }

    private static function compareDistanceMax(?float $left, ?float $right): int
    {
        if (null === $left && null === $right) {
            return 0;
        }

        if (null === $left) {
            return 1;
        }

        if (null === $right) {
            return -1;
        }

        return $left <=> $right;
    }

    private static function compareDistanceMin(?float $left, ?float $right): int
    {
        if (null === $left && null === $right) {
            return 0;
        }

        if (null === $left) {
            return 1;
        }

        if (null === $right) {
            return -1;
        }

        return $right <=> $left;
    }

    private static function compareIds(?string $left, ?string $right): int
    {
        if (null === $left || null === $right) {
            return 0;
        }

        return $left <=> $right;
    }
}

<?php

declare(strict_types=1);

namespace DRS\Admin\Migrations;

use DRS\Support\Options;

/**
 * Migration that seeds a sample rule when the rules store is empty.
 */
class SeedSampleRule
{
    public const MIGRATION_KEY = 'drs_seed_sample_rule';

    private Options $options;

    public function __construct(?Options $options = null)
    {
        $this->options = $options ?? new Options();
    }

    /**
     * Determines if the migration should run.
     */
    public function should_run(): bool
    {
        return [] === $this->options->get_rules();
    }

    /**
     * Executes the migration.
     */
    public function run(): bool
    {
        return $this->options->maybe_seed_sample_rule();
    }
}

<?php

namespace UsmanAhmed\ImportExport\Contracts;

use UsmanAhmed\ImportExport\Data\BatchWriteResult;
use UsmanAhmed\ImportExport\Data\ImportContext;
use UsmanAhmed\ImportExport\Definitions\ImportDefinition;
use UsmanAhmed\ImportExport\Enums\DuplicateStrategy;

interface ImportTarget
{
    public function connectionName(ImportContext $context): ?string;

    public function lockScope(ImportContext $context): string;

    /**
     * Return canonical business-key hashes that currently exist.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, true>
     */
    public function existingKeyHashes(
        array $rows,
        ImportDefinition $definition,
        ImportContext $context,
    ): array;

    /**
     * The caller has already applied ERROR/SKIP/UPDATE filtering.
     * Implementations must still rely on a database unique constraint.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $uniqueBy
     */
    public function write(
        array $rows,
        array $uniqueBy,
        DuplicateStrategy $strategy,
        ImportContext $context,
    ): BatchWriteResult;
}

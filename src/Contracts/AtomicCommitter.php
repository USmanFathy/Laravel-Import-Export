<?php

namespace UsmanAhmed\ImportExport\Contracts;

use UsmanAhmed\ImportExport\Data\BatchWriteResult;
use UsmanAhmed\ImportExport\Data\ImportContext;
use UsmanAhmed\ImportExport\Definitions\ImportDefinition;
use UsmanAhmed\ImportExport\Models\Import;

interface AtomicCommitter
{
    public function connectionName(ImportDefinition $definition, ImportContext $context): ?string;

    /**
     * Commit all staged rows. The caller verifies that connectionName() is the
     * metadata connection, opens the transaction there, and includes the final
     * import state update in that same transaction.
     */
    public function commit(Import $import, ImportDefinition $definition, ImportContext $context): BatchWriteResult;
}

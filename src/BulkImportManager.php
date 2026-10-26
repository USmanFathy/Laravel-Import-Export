<?php

namespace UsmanAhmed\ImportExport;

use UsmanAhmed\ImportExport\Definitions\ImportDefinition;

final class BulkImportManager
{
    /** @param class-string<ImportDefinition> $definition */
    public function make(string $definition): PendingImport
    {
        return new PendingImport($definition);
    }
}

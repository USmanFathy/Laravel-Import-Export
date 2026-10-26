<?php

namespace UsmanAhmed\ImportExport\Models\Concerns;

trait UsesBulkImportConnection
{
    public function getConnectionName(): ?string
    {
        return config('bulk-imports.database.connection') ?: parent::getConnectionName();
    }
}

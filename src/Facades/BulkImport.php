<?php

namespace UsmanAhmed\ImportExport\Facades;

use Illuminate\Support\Facades\Facade;
use UsmanAhmed\ImportExport\PendingImport;

/** @method static PendingImport make(string $definition) */
final class BulkImport extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bulk-imports';
    }
}

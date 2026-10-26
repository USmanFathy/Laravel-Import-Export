<?php

namespace UsmanAhmed\ImportExport\Facades;

use Illuminate\Support\Facades\Facade;
use UsmanAhmed\ImportExport\PendingImport;

/** @method static PendingImport make(string $definition) */
final class ImportExport extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bulk-imports';
    }
}

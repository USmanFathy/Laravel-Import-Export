<?php

namespace UsmanAhmed\ImportExport\Examples\Imports;

use UsmanAhmed\ImportExport\Contracts\AtomicCommitter;
use UsmanAhmed\ImportExport\Enums\ImportMode;
use UsmanAhmed\ImportExport\Services\EloquentAtomicCommitter;

final class AtomicStudentsImport extends StudentsImport
{
    public function mode(): ImportMode
    {
        return ImportMode::ATOMIC;
    }

    public function atomicCommitter(): AtomicCommitter
    {
        return app(EloquentAtomicCommitter::class);
    }
}

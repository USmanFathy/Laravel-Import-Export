<?php

namespace UsmanAhmed\ImportExport\Tests\Support;

use UsmanAhmed\ImportExport\Contracts\AtomicCommitter;
use UsmanAhmed\ImportExport\Enums\DuplicateStrategy;
use UsmanAhmed\ImportExport\Enums\ImportMode;
use UsmanAhmed\ImportExport\Examples\Imports\StudentsImport;
use UsmanAhmed\ImportExport\Services\EloquentAtomicCommitter;

final class AtomicErrorStudentsImport extends StudentsImport
{
    public function mode(): ImportMode
    {
        return ImportMode::ATOMIC;
    }

    public function duplicateStrategy(): DuplicateStrategy
    {
        return DuplicateStrategy::ERROR;
    }

    public function atomicCommitter(): AtomicCommitter
    {
        return app(EloquentAtomicCommitter::class);
    }
}

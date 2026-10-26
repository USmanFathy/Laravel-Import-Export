<?php

namespace UsmanAhmed\ImportExport\Tests\Support;

use UsmanAhmed\ImportExport\Enums\DuplicateStrategy;
use UsmanAhmed\ImportExport\Examples\Imports\StudentsImport;

final class SkipStudentsImport extends StudentsImport
{
    public function duplicateStrategy(): DuplicateStrategy
    {
        return DuplicateStrategy::SKIP;
    }
}

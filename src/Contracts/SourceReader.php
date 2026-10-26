<?php

namespace UsmanAhmed\ImportExport\Contracts;

use UsmanAhmed\ImportExport\Data\FileInspection;
use UsmanAhmed\ImportExport\Data\SourceRow;

interface SourceReader
{
    public function supports(string $extension, ?string $mimeType = null): bool;

    public function inspect(string $disk, string $path, ?string $worksheet = null): FileInspection;

    /** @return iterable<SourceRow> */
    public function rows(string $disk, string $path, ?string $worksheet = null): iterable;
}

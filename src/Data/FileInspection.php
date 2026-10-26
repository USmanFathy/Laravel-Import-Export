<?php

namespace UsmanAhmed\ImportExport\Data;

final readonly class FileInspection
{
    /** @param list<string> $headers */
    public function __construct(
        public array $headers,
        public ?int $totalRows,
        public ?string $worksheet = null,
    ) {}
}

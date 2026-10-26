<?php

namespace UsmanAhmed\ImportExport\Services;

use UsmanAhmed\ImportExport\Contracts\CurrentContext;

final class EmptyCurrentContext implements CurrentContext
{
    public function get(): array
    {
        return [];
    }
}

<?php

namespace UsmanAhmed\ImportExport\Services;

use Closure;
use UsmanAhmed\ImportExport\Contracts\ContextRestorer;

final class NullContextRestorer implements ContextRestorer
{
    public function run(array $context, Closure $callback): mixed
    {
        return $callback();
    }
}

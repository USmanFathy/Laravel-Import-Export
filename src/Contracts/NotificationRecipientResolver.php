<?php

namespace UsmanAhmed\ImportExport\Contracts;

use UsmanAhmed\ImportExport\Models\Export;
use UsmanAhmed\ImportExport\Models\Import;

interface NotificationRecipientResolver
{
    /** Resolve only a stored creator or server-verified recipient, under the restored context. */
    public function resolve(Import|Export $operation): ?object;
}

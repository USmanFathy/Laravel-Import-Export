<?php

namespace UsmanAhmed\ImportExport\Enums;

enum RelationMissingStrategy: string
{
    case ERROR = 'error';
    case SKIP_ROW = 'skip_row';
    case NULL = 'null';
}

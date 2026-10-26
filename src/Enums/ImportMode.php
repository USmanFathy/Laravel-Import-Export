<?php

namespace UsmanAhmed\ImportExport\Enums;

enum ImportMode: string
{
    case PARTIAL = 'partial';
    case ATOMIC = 'atomic';
}
